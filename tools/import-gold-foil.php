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
$raw = '';
if (!empty($args) && is_array($args)) $raw = implode("\n", $args);   // wp eval-file passes trailing args
if (trim($raw) === '') $raw = (string) get_option('af_goldfoil_source', '');
if (trim($raw) === '') {
    echo "  no folder configured. Set one and re-run:\n";
    echo "    wp option update af_goldfoil_source '/absolute/path/to/folder'\n";
    echo "=== DONE ===\n";
    return;
}

$up   = wp_get_upload_dir();
$dirs = array();
foreach (preg_split('/[\r\n,]+/', $raw) as $cand) {
    $cand = trim($cand);
    if ($cand === '') continue;
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
        if (is_dir($try)) { $found = realpath($try); break; }
    }
    if ($found === '' || $found === false) {
        echo "  NOT FOUND on this server: {$cand}\n";
        continue;
    }
    $dirs[] = $found;
}
$dirs = array_values(array_unique(array_filter($dirs)));
if (!$dirs) { echo "  nothing to read.\n=== DONE ===\n"; return; }

/* ── collect the files ────────────────────────────────────────────────── */
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
printf("  images found: %d\n\n", count($files));
if (!$files) { echo "=== DONE ===\n"; return; }

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
foreach ($files as $path) {
    $base = basename($path);
    if (isset($seen[$path])) { $skipped++; continue; }

    $title = af_gf_title_from_file($path);
    // a size written into the filename decides the price; otherwise the default
    $label = function_exists('af_size_label_for_product') ? af_size_label_for_product($title) : '';
    if ($label === '' || !in_array($label, $sizes, true)) $label = $def;
    if (stripos($title, 'gold') === false) $title .= ' Gold Foiled';
    if (!preg_match('/\b(canvas|print|art)\b/i', $title)) $title .= ' UV Canvas Art';

    // never two products with the same name (get_page_by_title() is deprecated
    // from WP 6.2, so the lookup goes through WP_Query's own title match)
    $dupe = new WP_Query(array(
        'post_type' => 'product', 'post_status' => array('publish', 'draft', 'pending', 'private'),
        'title' => $title, 'posts_per_page' => 1, 'fields' => 'ids',
        'no_found_rows' => true, 'update_post_meta_cache' => false, 'update_post_term_cache' => false,
    ));
    if (!empty($dupe->posts)) {
        // adopt it rather than making a near-duplicate, and remember the file
        $exists = (int) $dupe->posts[0];
        update_post_meta($exists, '_af_goldfoil_src', $path);
        update_post_meta($exists, '_af_goldfoil', 'yes');
        wp_set_object_terms($exists, array((int) $term->term_id), 'product_cat', true);
        printf("  = %-52s adopted existing product #%d\n", $base, $exists);
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
    update_post_meta($pid, '_af_goldfoil_src', $path);

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
echo '  section: ' . get_term_link($term) . "\n";
echo "=== DONE ===\n";
