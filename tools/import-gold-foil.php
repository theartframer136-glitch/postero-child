<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Turn a folder of artwork files into the Gold Foiled & UV section.
 *
 * The owner supplies a folder; every image in it becomes one product, with that
 * image as the product image. Nothing here is hand-typed per product:
 *
 *   title  — the filename, cleaned up ("ganesha-gold-36x48.webp" → "Ganesha
 *            Gold 36x48 Inch"), with the studio's own size wording appended
 *            when the filename carries a size
 *   size   — read out of the filename when it is there, otherwise the default
 *            size (option af_goldfoil_default_size)
 *   price  — the rate card's price for that size x af_goldfoil_ratio, written
 *            as the selling price with the usual struck-through figure above it
 *   image  — side-loaded into the Media Library (the original file is COPIED,
 *            never moved, so the owner's folder is left exactly as it was)
 *
 * WHERE THE FOLDER IS NAMED
 *   wp option update af_goldfoil_source '/home/USER/domains/theartframer.us/public_html/wp-content/uploads/gold-foil'
 * An absolute path, a path relative to the WordPress root, or a path relative
 * to the uploads folder all work. Several folders can be listed, one per line.
 *
 * WHEN NOTHING IS NAMED the importer finds the artwork on its own, in order:
 * the theme's assets/gold-foil/ (pictures committed to the repo), a folder or
 * zip in uploads/ named gold-foil or Personalised, and finally media:fresh —
 * Media Library uploads newer than this section that nothing uses yet, so
 * dragging the folder into wp-admin → Media → Add New is the whole procedure.
 * `wp option update af_goldfoil_source off` stops every automatic route.
 *
 * SAFE TO RE-RUN
 * Each product records the file it came from (_af_goldfoil_src). A second run
 * skips files that already have a product, so the deploy can call this every
 * time and only ever picks up what is new.
 *
 * Run: wp eval-file tools/import-gold-foil.php --allow-root
 *      wp eval-file tools/import-gold-foil.php --allow-root /path/to/folder
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
if (!function_exists('af_goldfoil_slug')) { echo "ABORT: the child theme's gold-foil module is not loaded.\n"; return; }
if (!function_exists('wc_get_product'))   { echo "ABORT: WooCommerce is not active.\n"; return; }

require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

echo "=== IMPORT GOLD FOILED & UV ===\n";

/* ── where to look ────────────────────────────────────────────────────── */
global $wpdb;
$up  = wp_get_upload_dir();
$raw = '';
if (!empty($args) && is_array($args)) $raw = implode("\n", $args);   // wp eval-file passes trailing args
if (trim($raw) === '') $raw = (string) get_option('af_goldfoil_source', '');
if (in_array(strtolower(trim($raw)), array('off', 'none', 'disabled'), true)) {
    // the one word that stops every automatic route below
    echo "  imports switched off (af_goldfoil_source = '" . trim($raw) . "')\n=== DONE ===\n";
    return;
}
if (trim($raw) === '') {
    // The convention, so dropping files in over FTP or File Manager is the
    // whole procedure — no option to set, no command to remember. The owner's
    // folder is named "Personalised" on their own machine, so an upload that
    // keeps that name is found too, whatever case it arrives in.
    // Two homes, checked in order.
    //
    // The THEME's own assets/gold-foil/ comes first, because that is the one
    // route that works when the artwork can only reach Claude as a chat
    // attachment: the pictures are committed alongside the code and the deploy
    // rsyncs them to the server like any other theme file, no upload step for
    // the owner at all. uploads/ is checked next, for a folder or zip put there
    // by hand.
    $homes = array();
    foreach (array('gold-foil', 'gold-foiled-uv', 'Personalised', 'personalised') as $cand) {
        $homes[] = trailingslashit(get_stylesheet_directory()) . 'assets/' . $cand;
    }
    foreach (array('gold-foil', 'Personalised', 'personalised', 'Personalized',
                   'personalized', 'gold-foiled-uv') as $cand) {
        $homes[] = trailingslashit($up['basedir']) . $cand;
    }
    foreach ($homes as $conv) {
        if (is_dir($conv) || is_file($conv . '.zip')) {
            $raw = is_dir($conv) ? $conv : $conv . '.zip';
            printf("  using %s (no af_goldfoil_source set)\n", $raw);
            break;
        }
    }
}
$auto = false;
if (trim($raw) === '') {
    // No folder, no zip, no option: fall through to the Media Library. Pictures
    // dragged into wp-admin → Media → Add New are the least work the owner can
    // possibly do, so that has to be enough by itself — media:fresh picks up
    // exactly the uploads that arrived after this section shipped and that
    // nothing on the site uses yet. Everything older was measured (deploy 818):
    // 3,502 unused images, all room mockups and source files, none of them this
    // artwork — which is what the date floor is for.
    $auto = true;
    $raw  = 'media:fresh';
    echo "  no folder or zip on the server — checking the Media Library for new uploads\n";
    echo "  (pictures dragged into wp-admin -> Media -> Add New become products automatically)\n";
}

/**
 * A source can also be pictures ALREADY in the Media Library, which is what a
 * folder dragged into WordPress and never turned into products looks like:
 *
 *   media:fresh         every image uploaded after this section shipped that
 *                       nothing on the site uses yet — what a folder dragged
 *                       into Media → Add New looks like. This is the DEFAULT
 *                       when no folder, zip or option names anything else, so
 *                       dragging the folder in IS the whole procedure.
 *   media:since:2026-08-22   every image uploaded on or after that date — the
 *                           precise way to say "the batch I just added"
 *   media:unused        every image no product or category currently uses
 *   media:<word>        every image whose filename or title contains <word>
 *   media:123,456       these attachment ids
 *
 * Those are reused in place — the file is not copied or uploaded a second
 * time, the existing attachment simply becomes the product's image.
 */
$media = array();
$dirs  = array();
foreach (preg_split('/[\r\n,]+(?![0-9])/', $raw) as $cand) {
    $cand = trim($cand);
    if ($cand === '' || stripos($cand, 'media:') !== 0) continue;
    $spec = trim(substr($cand, 6));
    if ($spec === '') continue;

    if (preg_match('/^[\d,\s]+$/', $spec)) {
        foreach (preg_split('/[,\s]+/', $spec) as $id) {
            $id = (int) $id;
            if ($id && wp_attachment_is_image($id)) $media[] = $id;
        }
        printf("  media: %d attachment id(s) named\n", count($media));
        continue;
    }

    $all = $wpdb->get_col("SELECT ID FROM {$wpdb->posts}
                            WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'");

    if (stripos($spec, 'since:') === 0) {
        $when = trim(substr($spec, 6));
        // a bare date means from the start of that day
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $when)) $when .= ' 00:00:00';
        $media = array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
              WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%%'
                AND post_date >= %s ORDER BY ID ASC", $when)));
        printf("  media: %d image(s) uploaded on or after %s\n", count($media), $when);
        continue;
    }

    if (strtolower($spec) === 'fresh') {
        // Uploads that arrived AFTER the section existed and that nothing uses.
        // The floor sits past deploy 818's measurement of the library (every
        // unused image then was a mockup or source file), so none of that
        // backlog can ever ride in on this route.
        $since = (string) get_option('af_goldfoil_fresh_since', '2026-08-22 12:00:00');
        $recent = array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
              WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%%'
                AND post_date >= %s ORDER BY ID ASC", $since)));
        $used = array();
        foreach ($wpdb->get_col("SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id'") as $v) {
            $used[(int) $v] = true;
        }
        foreach ($wpdb->get_col("SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_product_image_gallery'") as $v) {
            foreach (explode(',', (string) $v) as $g) { $g = (int) trim($g); if ($g) $used[$g] = true; }
        }
        foreach ($wpdb->get_col("SELECT meta_value FROM {$wpdb->termmeta} WHERE meta_key = 'thumbnail_id'") as $v) {
            $used[(int) $v] = true;
        }
        foreach ($recent as $id) if (!isset($used[$id])) $media[] = $id;
        printf("  media: %d upload(s) since %s, %d not yet used by anything\n",
            count($recent), $since, count($media));
        continue;
    }

    if (strtolower($spec) === 'unused') {
        $used = array();
        foreach ($wpdb->get_col("SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id'") as $v) {
            $used[(int) $v] = true;
        }
        foreach ($wpdb->get_col("SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_product_image_gallery'") as $v) {
            foreach (explode(',', (string) $v) as $g) { $g = (int) trim($g); if ($g) $used[$g] = true; }
        }
        foreach ($wpdb->get_col("SELECT meta_value FROM {$wpdb->termmeta} WHERE meta_key = 'thumbnail_id'") as $v) {
            $used[(int) $v] = true;
        }
        foreach ($all as $id) if (!isset($used[(int) $id])) $media[] = (int) $id;
        printf("  media: %d image(s) in the library that nothing uses\n", count($media));
    } else {
        $like = '%' . $wpdb->esc_like($spec) . '%';
        $media = array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
              WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%%'
                AND (guid LIKE %s OR post_title LIKE %s)", $like, $like)));
        printf("  media: %d image(s) matching \"%s\"\n", count($media), $spec);
    }
}
$media = array_values(array_unique($media));

/**
 * Two guards, learned from measuring this site rather than imagined.
 *
 * The Media Library here holds 5,512 images, 3,502 of which no product uses —
 * and they are not artwork waiting to be sold. They are room mockups
 * ("…-scene1.jpg" … "…-scene5.jpg") and source files from earlier batches. A
 * careless `media:unused` would have created three and a half thousand
 * products. So: mockups and WordPress's own resized copies are dropped, and
 * whatever survives is capped, with the number skipped stated out loud rather
 * than silently truncated.
 */
if ($media) {
    $before = count($media);
    $media = array_values(array_filter($media, function ($id) {
        $f = get_attached_file($id);
        $n = $f ? basename($f) : '';
        if ($n === '') return false;
        if (preg_match('/-scene\d+/i', $n)) return false;              // room mockup
        if (preg_match('/-\d{2,4}x\d{2,4}\.[a-z]+$/i', $n)) return false; // resized copy
        return true;
    }));
    if (count($media) !== $before) {
        printf("  media: %d dropped as mockups or resized copies, %d left\n",
            $before - count($media), count($media));
    }

    $cap = (int) get_option('af_goldfoil_max', 400);
    if ($cap > 0 && count($media) > $cap) {
        printf("  media: %d is more than the %d cap — importing the first %d, SKIPPING %d.\n",
            count($media), $cap, $cap, count($media) - $cap);
        echo  "         Raise it deliberately if that is really the intent:\n";
        echo  "         wp option update af_goldfoil_max 2000\n";
        $media = array_slice($media, 0, $cap);
    }
}

foreach (preg_split('/[\r\n,]+/', $raw) as $cand) {
    $cand = trim($cand);
    if ($cand === '' || stripos($cand, 'media:') === 0) continue;
    if (preg_match('#^https?://#i', $cand)) {
        echo "  SKIPPED (a web link, not a folder on this server): {$cand}\n";
        echo "    Upload the images to the site first — Media Library, FTP or\n";
        echo "    wp-content/uploads/gold-foil/ — then point af_goldfoil_source at that folder.\n";
        continue;
    }
    // an absolute path, a path from the WordPress root, or one from uploads/
    $found = '';
    foreach (array($cand,
                   ABSPATH . ltrim($cand, '/'),
                   trailingslashit($up['basedir']) . ltrim($cand, '/')) as $try) {
        if (is_dir($try) || (is_file($try) && strtolower(pathinfo($try, PATHINFO_EXTENSION)) === 'zip')) {
            $found = realpath($try);
            break;
        }
    }
    if ($found === '' || $found === false) {
        echo "  NOT FOUND on this server: {$cand}\n";
        continue;
    }
    $dirs[] = $found;
}
$dirs = array_values(array_unique(array_filter($dirs)));
if (!$dirs && !$media) {
    if ($auto) {
        echo "  no new artwork anywhere yet. The pictures have to reach the site once —\n";
        echo "  a path on the owner's PC (C:\\Users\\...) cannot be read from here. Any of:\n";
        echo "    (a) wp-admin -> Media -> Add New -> drag the whole folder in\n";
        echo "        (the next deploy imports it automatically — nothing else to do)\n";
        echo "    (b) upload the folder, or a zip of it, to wp-content/uploads/gold-foil/\n";
        echo "        (a folder still named Personalised is found there too)\n";
        echo "    (c) attach the pictures in the Claude chat — they get committed to\n";
        echo "        assets/gold-foil/ and arrive with the deploy\n";
    } else {
        echo "  nothing to read.\n";
    }
    echo "=== DONE ===\n";
    return;
}

/**
 * A folder usually arrives as a zip. Unpack any that turn up — either named
 * directly or sitting inside a folder we were pointed at — into a working copy,
 * so the hand-off is the same whether the files were uploaded loose or zipped.
 * The archive itself is left alone.
 */
$zips = array();
foreach ($dirs as $d) {
    if (is_file($d)) { $zips[] = $d; continue; }
    foreach ((array) glob(trailingslashit($d) . '*.[Zz][Ii][Pp]') as $z) $zips[] = $z;
}
if ($zips && $dirs) {
    WP_Filesystem();
    $work = trailingslashit($up['basedir']) . 'gold-foil-unpacked';
    if (!is_dir($work)) wp_mkdir_p($work);
    foreach ($zips as $z) {
        $into = trailingslashit($work) . sanitize_title(pathinfo($z, PATHINFO_FILENAME));
        if (is_dir($into)) {                       // already unpacked on an earlier run
            $dirs[] = $into;
            continue;
        }
        wp_mkdir_p($into);
        $ok = unzip_file($z, $into);
        if (is_wp_error($ok)) {
            printf("  zip: could not unpack %s — %s\n", basename($z), $ok->get_error_message());
            continue;
        }
        printf("  zip: unpacked %s\n", basename($z));
        $dirs[] = $into;
    }
    // a zip named directly is not itself a folder to scan
    $dirs = array_values(array_filter($dirs, 'is_dir'));
    $dirs = array_values(array_unique($dirs));
}

/* ── collect the pictures ─────────────────────────────────────────────────
 * One list, two kinds of entry: a file on disk, or an attachment already in
 * the Media Library. Everything downstream treats them the same except at the
 * moment the image is attached, where a library picture is reused in place
 * rather than uploaded a second time.
 */
$exts  = array('jpg', 'jpeg', 'png', 'webp', 'gif');
$files = array();
foreach ($dirs as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile()) continue;
        $ext = strtolower($f->getExtension());
        if (!in_array($ext, $exts, true)) continue;
        // WordPress's own resized copies are not separate artworks
        if (preg_match('/-\d{2,4}x\d{2,4}\.(jpe?g|png|webp|gif)$/i', $f->getFilename())) continue;
        if (strpos($f->getFilename(), '._') === 0) continue;         // macOS resource forks
        $files[] = $f->getPathname();
    }
    printf("  folder: %s\n", $dir);
}
sort($files);

$items = array();
foreach ($files as $p) $items[] = array('key' => $p, 'name' => basename($p), 'file' => $p);
foreach ($media as $id) {
    $src = get_attached_file($id);
    $items[] = array(
        'key'  => 'att:' . $id,
        'name' => $src ? basename($src) : ('attachment-' . $id),
        'att'  => (int) $id,
    );
}
printf("  pictures found: %d (%d on disk, %d already in the library)\n\n",
    count($items), count($files), count($media));
if (!$items) { echo "=== DONE ===\n"; return; }

/* ── the category everything lands in ─────────────────────────────────── */
$term = get_term_by('slug', af_goldfoil_slug(), 'product_cat');
if (!$term || is_wp_error($term)) {
    echo "  ABORT: the category does not exist yet — run tools/setup-gold-foil.php first.\n=== DONE ===\n";
    return;
}

$ratio = af_goldfoil_ratio();
$cfgb  = af_pricing_config_base();
$sizes = function_exists('af_sizes_available') ? af_sizes_available() : array_keys($cfgb['sizes']);
$def   = (string) get_option('af_goldfoil_default_size', '');
if ($def === '' || !in_array($def, $sizes, true)) $def = $sizes ? $sizes[0] : array_key_first($cfgb['sizes']);
printf("  price ratio x%.2f, default size %s\n\n", $ratio, $def);

/** Products already imported, keyed by the file they came from. */
global $wpdb;
$seen = array();
$rows = $wpdb->get_results("SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_af_goldfoil_src'");
foreach ($rows as $r) $seen[$r->meta_value] = (int) $r->post_id;

/** "ganesha-gold-foil-36x48" → "Ganesha Gold Foil" plus the size it named. */
function af_gf_title_from_file($path) {
    $n = pathinfo($path, PATHINFO_FILENAME);
    $n = preg_replace('/[_\-\.]+/', ' ', $n);
    $n = preg_replace('/\b(final|copy|edited|new|v\d+|img|image|dsc|scan)\b/i', ' ', $n);
    $n = preg_replace('/^\s*\d{1,3}\s+/', '', $n);          // leading sequence number
    $n = preg_replace('/\s{2,}/', ' ', trim($n));
    if ($n === '') $n = 'Gold Foiled Artwork';
    // Title Case, leaving an all-caps word (RK, UV) alone
    $n = implode(' ', array_map(function ($w) {
        return (strtoupper($w) === $w && strlen($w) <= 3) ? $w : ucwords(strtolower($w));
    }, explode(' ', $n)));
    return $n;
}

$created = 0; $skipped = 0; $failed = 0;
foreach ($items as $item) {
    $key  = $item['key'];                 // the file path, or "att:<id>"
    $base = $item['name'];
    $path = isset($item['file']) ? $item['file'] : '';
    if (isset($seen[$key])) { $skipped++; continue; }

    $title = af_gf_title_from_file($base);
    // A size written into the filename decides the price. Everything downstream
    // — the size the selector opens on, and the deploy's repricing pass — reads
    // the size out of the TITLE, so when the filename carries none the default
    // is written into the title rather than only into the price. Otherwise this
    // product would list at one size and open its options on another.
    $label = function_exists('af_size_label_for_product') ? af_size_label_for_product($title) : '';
    $titled = ($label !== '' && in_array($label, $sizes, true));
    if (!$titled) $label = $def;
    if (stripos($title, 'gold') === false) $title .= ' Gold Foiled';
    if (!preg_match('/\b(canvas|print|art)\b/i', $title)) $title .= ' UV Canvas Art';
    if (!$titled && preg_match('/^(\d+(?:\.\d+)?)×(\d+(?:\.\d+)?) ft/u', $label, $lm)) {
        $title .= ' ' . $lm[1] . 'x' . $lm[2] . ' Feet';
    }

    // never two products with the same name (get_page_by_title() is deprecated
    // from WP 6.2, so the lookup goes through WP_Query's own title match)
    $af_gf_by_title = function ($t) {
        $q = new WP_Query(array(
            'post_type' => 'product', 'post_status' => array('publish', 'draft', 'pending', 'private'),
            'title' => $t, 'posts_per_page' => 1, 'fields' => 'ids',
            'no_found_rows' => true, 'update_post_meta_cache' => false, 'update_post_term_cache' => false,
        ));
        return empty($q->posts) ? 0 : (int) $q->posts[0];
    };
    $exists = $af_gf_by_title($title);
    if ($exists && get_post_meta($exists, '_af_goldfoil', true) !== 'yes'
                && !has_term((int) $term->term_id, 'product_cat', $exists)) {
        // The name belongs to an ORDINARY product. Adopting it would silently
        // move a normal print into this section and reprice it 40% up — the
        // request was a premium section, not a premium on the existing
        // catalogue. The gold version gets a name of its own instead.
        $title .= ' (Gold Foiled & UV)';
        $exists = $af_gf_by_title($title);
        if ($exists && get_post_meta($exists, '_af_goldfoil', true) !== 'yes'
                    && !has_term((int) $term->term_id, 'product_cat', $exists)) {
            printf("  ! %-52s its name is taken twice over by ordinary products — left alone\n", $base);
            $skipped++;
            continue;
        }
    }
    if ($exists) {
        // the same piece imported before (or created by hand in this section):
        // remember the file rather than making a near-duplicate
        update_post_meta($exists, '_af_goldfoil_src', $key);
        update_post_meta($exists, '_af_goldfoil', 'yes');
        wp_set_object_terms($exists, array((int) $term->term_id), 'product_cat', true);
        printf("  = %-52s adopted existing gold-foil product #%d\n", $base, $exists);
        $skipped++;
        continue;
    }

    $card  = isset($cfgb['sizes'][$label]) ? (float) $cfgb['sizes'][$label] : (float) reset($cfgb['sizes']);
    $sell  = af_goldfoil_scale($card, $ratio);

    $p = new WC_Product_Simple();
    $p->set_name($title);
    $p->set_status('publish');
    $p->set_catalog_visibility('visible');
    $p->set_manage_stock(false);
    $p->set_stock_status('instock');
    $p->set_short_description('Gold foil detailing sealed under a UV-cured coat. Printed on premium cotton-blend canvas with archival inks, in the size and frame you choose.');
    $p->set_description(
        '<h3>' . esc_html($title) . '</h3>'
        . '<p>Finished with genuine gold foil detailing and sealed under a UV-cured coat, this piece catches the light the way an ordinary print cannot — the foil lifts the highlights, and the UV layer keeps the colour and the sheen intact for years.</p>'
        . '<h4>Product Highlights</h4><ul>'
        . '<li>Gold foil detailing, applied by hand to the highlights</li>'
        . '<li>UV-cured protective coat — scratch-resistant and fade-resistant</li>'
        . '<li>Premium cotton-blend canvas with archival pigment inks</li>'
        . '<li>Available in every size and frame the studio offers</li>'
        . '<li>Ships ready to hang</li>'
        . '</ul>'
    );
    $p->set_regular_price((string) $sell);
    $pid = $p->save();
    if (!$pid) { printf("  ! %-52s could not be created\n", $base); $failed++; continue; }

    // the struck-through reference price, the same way every other product gets it
    if (function_exists('af_mrp_multiplier')) {
        $ref = round($sell * af_mrp_multiplier($pid), 2);
        if ($ref > $sell) {
            $p->set_regular_price((string) $ref);
            $p->set_sale_price((string) $sell);
            $p->save();
        }
    }

    wp_set_object_terms($pid, array((int) $term->term_id), 'product_cat');
    update_post_meta($pid, '_af_goldfoil', 'yes');
    update_post_meta($pid, '_af_goldfoil_src', $key);

    // A picture already in the Media Library is reused where it is — attaching
    // it costs nothing and avoids a second copy of the same file on disk.
    if (!empty($item['att'])) {
        set_post_thumbnail($pid, (int) $item['att']);
        printf("  + %-52s #%-7d %-22s $%s  (library image #%d)\n",
            $base, $pid, $label, $sell, (int) $item['att']);
        $created++;
        continue;
    }

    // Copy, never move: media_handle_sideload() consumes the file it is given,
    // and the owner's folder must survive the import untouched.
    $tmp = wp_tempnam($base);
    if ($tmp && @copy($path, $tmp)) {
        $att = media_handle_sideload(array('name' => sanitize_file_name($base), 'tmp_name' => $tmp), $pid, $title);
        if (is_wp_error($att)) {
            @unlink($tmp);
            printf("  ! %-52s image failed: %s\n", $base, $att->get_error_message());
        } else {
            set_post_thumbnail($pid, $att);
        }
    } else {
        if ($tmp) @unlink($tmp);
        printf("  ! %-52s could not read the file\n", $base);
    }

    printf("  + %-52s #%-7d %-22s $%s\n", $base, $pid, $label, $sell);
    $created++;
}

printf("\n  created: %d   already there: %d   failed: %d\n", $created, $skipped, $failed);
if ($created) {
    if (function_exists('wc_delete_product_transients')) wc_delete_product_transients();
    wp_update_term_count_now(array((int) $term->term_id), 'product_cat');
    delete_transient('af_deal_ids_40');
    echo "  caches cleared, category count refreshed\n";
}

/* ── the category's own icon ──────────────────────────────────────────────
 * The menu draws each category's icon from the term thumbnail. While the
 * section was empty, setup-gold-foil.php put a stand-in there and flagged it.
 * Now that there is real gold-foil artwork, it should be the icon.
 */
$thumb = (int) get_term_meta($term->term_id, 'thumbnail_id', true);
$file  = $thumb ? get_attached_file($thumb) : '';
$stale = !$thumb || !$file || !@file_exists($file)
      || get_term_meta($term->term_id, '_af_goldfoil_thumb_auto', true);
if ($stale) {
    $first = get_posts(array('post_type' => 'product', 'post_status' => 'publish',
        'posts_per_page' => 6, 'fields' => 'ids', 'no_found_rows' => true,
        'orderby' => 'date', 'order' => 'ASC',
        'tax_query' => array(array('taxonomy' => 'product_cat', 'field' => 'term_id',
            'terms' => array((int) $term->term_id), 'include_children' => true))));
    foreach ($first as $fid) {
        $att = get_post_thumbnail_id($fid);
        $f   = $att ? get_attached_file($att) : '';
        if ($att && $f && @file_exists($f)) {
            update_term_meta($term->term_id, 'thumbnail_id', (int) $att);
            delete_term_meta($term->term_id, '_af_goldfoil_thumb_auto');
            printf("  icon: now the real artwork (attachment #%d from product #%d)\n", $att, $fid);
            break;
        }
    }
}
echo '  section: ' . get_term_link($term) . "\n";
echo "=== DONE ===\n";
