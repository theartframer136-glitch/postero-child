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
 * OR NO NEW ARTWORK AT ALL. Gold foil and a UV coat are a FINISH, so a piece
 * the studio already sells can be offered in it without a single new file:
 *   wp option update af_goldfoil_source 'category:personalised-prints'
 *   wp option update af_goldfoil_source 'products:1234,5678'
 * The source product's picture, gallery and words come with it — which is what
 * makes the new listing as complete as the one it came from — while the price
 * is computed from the rate card exactly as it is for every other piece here.
 *
 * OR A LINK, which needs nothing installed anywhere. This catalogue's own
 * pictures arrived that way — bulk-import-products.php pulls every image from
 * a URL and nobody ever uploaded a file by hand:
 *   wp option update af_goldfoil_source 'https://.../artwork.zip'
 * A zip is unpacked and every picture in it becomes a product; a link to one
 * picture becomes one product. Synology, Dropbox, Google Drive, WeTransfer —
 * anything that serves the bytes without asking who you are.
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
        // added to what other specs found, never assigned over it: several
        // sources may be listed, and one quietly erasing another's matches is
        // the kind of thing nobody notices until the section is short
        $found = array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
              WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%%'
                AND post_date >= %s ORDER BY ID ASC", $when)));
        foreach ($found as $id) $media[] = $id;
        printf("  media: %d image(s) uploaded on or after %s\n", count($found), $when);
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
        $found = array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
              WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%%'
                AND (guid LIKE %s OR post_title LIKE %s)", $like, $like)));
        foreach ($found as $id) $media[] = $id;
        printf("  media: %d image(s) matching \"%s\"\n", count($found), $spec);
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

/**
 * A source can also be PRODUCTS THIS SITE ALREADY SELLS.
 *
 *   category:personalised-prints   one premium piece per published product in
 *                                  that category, its sub-categories included
 *   products:1234,5678             these product ids
 *
 * Gold foil and a UV coat are a FINISH, not different artwork — so the same
 * piece the studio already sells can be offered in the premium finish without
 * a single new file. That matters here for a blunt reason: the artwork the
 * owner named lives on their own PC, which no machine in this pipeline can
 * read, and asking for an upload has not produced one. This route needs
 * nothing from anybody.
 *
 * The source product's PICTURE is reused where it is (no second copy on disk),
 * and so is its gallery and its description — which is what makes the new
 * listing as complete as the one it came from. Its PRICE is not: that is
 * computed from the rate card for the size, times the ratio, exactly as for
 * every other piece in the section.
 */
$from_products = array();
foreach (preg_split('/[\r\n]+/', $raw) as $cand) {
    $cand = trim($cand);
    if ($cand === '') continue;
    $is_cat  = (stripos($cand, 'category:') === 0);
    $is_prod = (stripos($cand, 'products:') === 0);
    if (!$is_cat && !$is_prod) continue;
    $spec = trim(substr($cand, strpos($cand, ':') + 1));
    if ($spec === '') continue;

    if ($is_prod) {
        $n = 0;
        foreach (preg_split('/[,\s]+/', $spec) as $id) {
            $id = (int) $id;
            if ($id && get_post_type($id) === 'product') { $from_products[] = $id; $n++; }
        }
        printf("  products: %d named product(s) resolved\n", $n);
        continue;
    }

    $src_term = get_term_by('slug', $spec, 'product_cat');
    if (!$src_term || is_wp_error($src_term)) {
        printf("  category: there is no product category with the slug \"%s\"\n", $spec);
        continue;
    }
    $ids = get_posts(array(
        'post_type' => 'product', 'post_status' => 'publish',
        'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true,
        'orderby' => 'date', 'order' => 'DESC',
        'tax_query' => array(array('taxonomy' => 'product_cat', 'field' => 'term_id',
            'terms' => array((int) $src_term->term_id), 'include_children' => true)),
    ));
    printf("  category: %s (#%d) holds %d published product(s)\n",
        $spec, $src_term->term_id, count($ids));
    foreach ($ids as $id) $from_products[] = (int) $id;
}
$from_products = array_values(array_unique($from_products));

if ($from_products) {
    $gfterm = get_term_by('slug', af_goldfoil_slug(), 'product_cat');
    $gfid   = ($gfterm && !is_wp_error($gfterm)) ? (int) $gfterm->term_id : 0;
    $before = count($from_products);
    $from_products = array_values(array_filter($from_products, function ($pid) use ($gfid) {
        // NEVER source from the section itself. Without this, pointing the
        // importer at a category that contains gold-foil products would make a
        // premium copy of a premium copy on every deploy, each one 40% dearer
        // than the last, for as long as nobody looked.
        if (get_post_meta($pid, '_af_goldfoil', true) === 'yes') return false;
        if ($gfid && has_term($gfid, 'product_cat', $pid)) return false;
        // a listing with no picture is not a listing
        $att = get_post_thumbnail_id($pid);
        if (!$att) return false;
        $f = get_attached_file($att);
        return (bool) ($f && @file_exists($f));
    }));
    if (count($from_products) !== $before) {
        printf("  products: %d skipped (already premium, or no usable picture), %d left\n",
            $before - count($from_products), count($from_products));
    }

    $cap = (int) get_option('af_goldfoil_max', 400);
    if ($cap > 0 && count($from_products) > $cap) {
        printf("  products: %d is more than the %d cap — taking the first %d, SKIPPING %d.\n",
            count($from_products), $cap, $cap, count($from_products) - $cap);
        echo  "            Raise it deliberately if that is really the intent:\n";
        echo  "            wp option update af_goldfoil_max 2000\n";
        $from_products = array_slice($from_products, 0, $cap);
    }
}

$exts_link = array('jpg', 'jpeg', 'png', 'webp', 'gif');
foreach (preg_split('/[\r\n,]+/', $raw) as $cand) {
    $cand = trim($cand);
    if ($cand === '' || stripos($cand, 'media:') === 0) continue;
    if (stripos($cand, 'category:') === 0 || stripos($cand, 'products:') === 0) continue;
    if (preg_match('/^\d+$/', $cand)) continue;   // a stray id from "products:12,34"
    // A LINK. The server fetches it itself, which is how this catalogue's
    // pictures arrived in the first place — bulk-import-products.php pulls
    // every image from a URL (af_sideload_image, download_url) and nobody ever
    // uploaded a file by hand. So a share link to the artwork — Synology,
    // Dropbox, Google Drive, WeTransfer, anything that serves the bytes without
    // a login — is a complete answer on its own: paste the link, and the
    // pictures walk in.
    //
    // A zip is unpacked and every picture inside it is imported. A link to a
    // single picture is imported as one product.
    if (preg_match('#^https?://#i', $cand)) {
        $tmp = download_url($cand, 300);
        if (is_wp_error($tmp)) {
            printf("  link: could not fetch %s — %s\n", $cand, $tmp->get_error_message());
            echo  "        A link that needs a login cannot be read from here. Use the\n";
            echo  "        share/anyone-with-the-link version, or one that downloads\n";
            echo  "        without asking who you are.\n";
            continue;
        }

        // What actually came back, decided by the bytes and not by the URL —
        // share links rarely end in .zip even when that is what they serve.
        $magic = '';
        $fh = @fopen($tmp, 'rb');
        if ($fh) { $magic = (string) fread($fh, 4); fclose($fh); }
        $is_zip = (substr($magic, 0, 2) === 'PK');

        $work = trailingslashit($up['basedir']) . 'gold-foil-unpacked';
        if (!is_dir($work)) wp_mkdir_p($work);

        if ($is_zip) {
            WP_Filesystem();
            $into = trailingslashit($work) . 'link-' . substr(md5($cand), 0, 10);
            if (!is_dir($into)) {
                wp_mkdir_p($into);
                $ok = unzip_file($tmp, $into);
                if (is_wp_error($ok)) {
                    @unlink($tmp);
                    printf("  link: fetched, but could not unpack it — %s\n", $ok->get_error_message());
                    continue;
                }
            }
            @unlink($tmp);
            printf("  link: fetched and unpacked %s\n", $cand);
            $dirs[] = $into;
            continue;
        }

        // a single picture: park it under its real name so the product is named
        // after the artwork rather than after a temp file
        $name = basename(parse_url($cand, PHP_URL_PATH));
        if ($name === '' || strpos($name, '.') === false) $name = 'artwork-' . substr(md5($cand), 0, 8) . '.jpg';
        $ft = wp_check_filetype($name);
        if (empty($ft['ext']) || !in_array(strtolower($ft['ext']), $exts_link, true)) {
            @unlink($tmp);
            printf("  link: %s is neither a zip nor a picture — skipped\n", $cand);
            continue;
        }
        $into = trailingslashit($work) . 'link-single';
        if (!is_dir($into)) wp_mkdir_p($into);
        @rename($tmp, trailingslashit($into) . sanitize_file_name($name));
        @unlink($tmp);
        printf("  link: fetched %s\n", $name);
        $dirs[] = $into;
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
if (!$dirs && !$media && !$from_products) {
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
/**
 * A product's name as it is actually STORED, not as a page would print it.
 *
 * get_the_title() runs the `the_title` filter, and wptexturize on that filter
 * rewrites digit-x-digit — "3x4" becomes the literal seven characters
 * "3&#215;4". Harmless on a page, ruinous here: that string would be written
 * straight back into post_title, and af_size_label_for_product() cannot read a
 * size out of it. The piece would then be priced from the default size instead
 * of its own, and reprice-from-card.php would file it under "no size" and skip
 * it on every future run, so its price would never track the rate card again.
 * Six other tools in this folder already decode for exactly this reason
 * (import-artcodes.php, sku-to-artcode.php, apply-artcode-batch.php, ...).
 */
function af_gf_stored_title($pid) {
    return html_entity_decode(wp_strip_all_tags(get_the_title($pid)), ENT_QUOTES, 'UTF-8');
}

// A piece the studio already sells, to be offered in the premium finish. Its
// own picture and words come with it, which is what makes the new listing as
// complete as the one it came from.
foreach ($from_products as $pid) {
    $items[] = array(
        'key'         => 'product:' . $pid,
        'name'        => af_gf_stored_title($pid),
        'att'         => (int) get_post_thumbnail_id($pid),
        'src_product' => (int) $pid,
    );
}
printf("  pictures found: %d (%d on disk, %d in the library, %d from products already on the site)\n\n",
    count($items), count($files), count($media), count($from_products));
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

/**
 * The same artwork, named as the premium finish of itself.
 *
 * The suffix goes on the END on purpose: the size the selector opens on and
 * the deploy's repricing pass both read the size out of the TITLE, so
 * everything before it has to survive untouched. The suffix carries no digits,
 * so it cannot be mistaken for one.
 */
function af_gf_title_from_product($src_title) {
    $t = trim(preg_replace('/\s{2,}/', ' ', (string) $src_title));
    // a source that already says gold foil or UV must not end up saying it twice
    $t = preg_replace('/\s*[\x{2014}\x{2013}-]?\s*\bgold\s*foil(ed)?\b(\s*(&|and)\s*\buv\b)?/iu', '', $t);
    $t = trim(preg_replace('/\s{2,}/', ' ', $t));
    if ($t === '') $t = 'Gold Foiled Artwork';
    return $t . ' — Gold Foiled & UV';
}

$created = 0; $skipped = 0; $failed = 0;
foreach ($items as $item) {
    $key  = $item['key'];                 // the file path, or "att:<id>"
    $base = $item['name'];
    $path = isset($item['file']) ? $item['file'] : '';
    if (isset($seen[$key])) { $skipped++; continue; }

    // A product this site already sells arrives with a finished name; a bare
    // file has to have one built out of its filename.
    $src_pid = isset($item['src_product']) ? (int) $item['src_product'] : 0;
    $title   = $src_pid ? af_gf_title_from_product($base) : af_gf_title_from_file($base);

    // A size written into the filename decides the price. Everything downstream
    // — the size the selector opens on, and the deploy's repricing pass — reads
    // the size out of the TITLE, so when the filename carries none the default
    // is written into the title rather than only into the price. Otherwise this
    // product would list at one size and open its options on another.
    $label = function_exists('af_size_label_for_product') ? af_size_label_for_product($title) : '';
    // A size the title really names is kept whenever the RATE CARD prices it,
    // not only when the selector currently offers it. The narrower test was
    // resetting the price to the default size while the title went on naming
    // the real one — so a piece called 24x48 was charged as if it were 24x36,
    // which is the one kind of mistake a shop must never make.
    $titled = ($label !== '' && (in_array($label, $sizes, true) || isset($cfgb['sizes'][$label])));
    if (!$titled) {
        if ($label !== '') {
            printf("  ~ %-52s size \"%s\" is not on the rate card — priced as %s\n",
                substr($base, 0, 52), $label, $def);
        }
        $label = $def;
    }
    if (!$src_pid) {
        if (stripos($title, 'gold') === false) $title .= ' Gold Foiled';
        if (!preg_match('/\b(canvas|print|art)\b/i', $title)) $title .= ' UV Canvas Art';
        if (!$titled && preg_match('/^(\d+(?:\.\d+)?)×(\d+(?:\.\d+)?) ft/u', $label, $lm)) {
            $title .= ' ' . $lm[1] . 'x' . $lm[2] . ' Feet';
        }
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
        // catalogue. The gold version gets a name of its own instead, without
        // naming the finish twice on a title that already ends with it.
        $title .= (stripos($title, 'Gold Foiled & UV') === false)
            ? ' (Gold Foiled & UV)' : ' (Premium Finish)';
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

    // What the finish adds, said once, in front of whatever else the listing says.
    $gf_copy =
        '<p>Finished with genuine gold foil detailing and sealed under a UV-cured coat, this piece catches the light the way an ordinary print cannot — the foil lifts the highlights, and the UV layer keeps the colour and the sheen intact for years.</p>'
        . '<h4>The Gold Foiled &amp; UV finish</h4><ul>'
        . '<li>Gold foil detailing, applied by hand to the highlights</li>'
        . '<li>UV-cured protective coat — scratch-resistant and fade-resistant</li>'
        . '<li>Premium cotton-blend canvas with archival pigment inks</li>'
        . '<li>Available in every size and frame the studio offers</li>'
        . '<li>Ships ready to hang</li>'
        . '</ul>';

    // A piece already on the site brings its own words with it. Reusing them is
    // the difference between a listing as complete as the rest of the catalogue
    // and a stub carrying only the finish note — the source has already been
    // through the template, the specs and the FAQ passes, and those are exactly
    // the "all details" the owner asked these products to have. The old title
    // inside that copy is replaced so the piece does not introduce itself by
    // its other name.
    $desc = '';
    $short = '';
    if ($src_pid) {
        $src_desc  = (string) get_post_field('post_content', $src_pid);
        $src_short = (string) get_post_field('post_excerpt', $src_pid);
        $src_name  = af_gf_stored_title($src_pid);
        if (trim($src_desc) !== '') {
            if ($src_name !== '') $src_desc = str_replace($src_name, $title, $src_desc);
            $desc = $gf_copy . $src_desc;
        }
        if (trim($src_short) !== '') {
            $short = 'Gold foil detailing sealed under a UV-cured coat. ' . $src_short;
        }
    }
    if ($desc === '')  $desc  = '<h3>' . esc_html($title) . '</h3>' . $gf_copy;
    if ($short === '') $short = 'Gold foil detailing sealed under a UV-cured coat. Printed on premium cotton-blend canvas with archival inks, in the size and frame you choose.';

    $p = new WC_Product_Simple();
    $p->set_name($title);
    $p->set_status('publish');
    $p->set_catalog_visibility('visible');
    $p->set_manage_stock(false);
    $p->set_stock_status('instock');
    $p->set_short_description($short);
    $p->set_description($desc);
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

    // ONLY the section's own category. Filing the premium copy under the source
    // product's categories too would show both versions of the same artwork
    // side by side in every ordinary listing, which reads as a duplicated
    // catalogue rather than a premium line.
    wp_set_object_terms($pid, array((int) $term->term_id), 'product_cat');
    update_post_meta($pid, '_af_goldfoil', 'yes');
    update_post_meta($pid, '_af_goldfoil_src', $key);

    // The source's other views, reused in place. No SKU or art code is copied:
    // WooCommerce requires SKUs to be unique, and a shared art code is the very
    // data problem this catalogue has been untangling for weeks — the deploy's
    // own passes give this product its own.
    if ($src_pid) {
        update_post_meta($pid, '_af_goldfoil_of', $src_pid);
        $gal = (string) get_post_meta($src_pid, '_product_image_gallery', true);
        if ($gal !== '') update_post_meta($pid, '_product_image_gallery', $gal);

        // An art code identifies the ARTWORK, and this is the same artwork in a
        // different finish — so it takes the source's code with a suffix. The
        // suffix is not decoration: tools/sku-to-artcode.php gives a product its
        // SKU from this field and skips anything without one, so a gold piece
        // with no code would be the only thing in the catalogue with no SKU.
        // Copying the code unchanged was the other option and is exactly the
        // shared-code data problem this catalogue has spent weeks untangling.
        $code = trim((string) get_post_meta($src_pid, '_taf_art_code', true));
        if ($code !== '') update_post_meta($pid, '_taf_art_code', $code . '-GF');
    }

    // A picture already in the Media Library is reused where it is — attaching
    // it costs nothing and avoids a second copy of the same file on disk.
    if (!empty($item['att'])) {
        set_post_thumbnail($pid, (int) $item['att']);
        printf("  + %-52s #%-7d %-22s $%s  (%s)\n",
            substr($base, 0, 52), $pid, $label, $sell,
            $src_pid ? ('from product #' . $src_pid) : ('library image #' . (int) $item['att']));
        $created++;
        continue;
    }

    // Copy, never move: media_handle_sideload() consumes the file it is given,
    // and the owner's folder must survive the import untouched.
    $tmp    = wp_tempnam($base);
    $why    = '';
    if ($tmp && @copy($path, $tmp)) {
        $att = media_handle_sideload(array('name' => sanitize_file_name($base), 'tmp_name' => $tmp), $pid, $title);
        if (is_wp_error($att)) {
            @unlink($tmp);
            $why = $att->get_error_message();
        } else {
            set_post_thumbnail($pid, $att);
        }
    } else {
        if ($tmp) @unlink($tmp);
        $why = 'the file could not be read';
    }

    // A product with no picture is not a listing, it is a blank card — and the
    // deploy's own draft-imageless.php pass would quietly unpublish it later in
    // this same run, leaving something half-made behind and a log that claimed
    // success. Take it back out instead, and say so.
    if ($why !== '') {
        wp_delete_post($pid, true);
        printf("  ! %-52s image failed (%s) — product removed\n", substr($base, 0, 52), $why);
        $failed++;
        continue;
    }

    printf("  + %-52s #%-7d %-22s $%s\n", substr($base, 0, 52), $pid, $label, $sell);
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
