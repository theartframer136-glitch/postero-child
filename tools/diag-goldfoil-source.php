<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Where could the Gold Foiled & UV artwork already be?
 *
 * The owner points at C:\Users\user\SynologyDrive\Personalised — a folder on
 * their own machine, which nothing here can read. But the same pictures may
 * already have reached the site once before, and if they have, the section can
 * be built today instead of waiting on an upload. Three places worth asking:
 *
 *   A. a folder of them sitting in uploads/ under some name
 *   B. a zip of them sitting in uploads/
 *   C. images in the Media Library that no product uses — the residue of an
 *      earlier upload, which is exactly what a folder dragged into WordPress
 *      and never turned into products looks like
 *
 * Read-only. Run: wp eval-file tools/diag-goldfoil-source.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
global $wpdb;

echo "=== DIAG: GOLD-FOIL ARTWORK SOURCES ON THIS SERVER ===\n";
$up   = wp_get_upload_dir();
$base = $up['basedir'];
printf("  uploads: %s\n\n", $base);

$WORDS = '/person|gold|foil|\buv\b|synolog|framer/i';
$EXTS  = array('jpg', 'jpeg', 'png', 'webp', 'gif');

/* ── A. folders ───────────────────────────────────────────────────────── */
echo "A. FOLDERS IN uploads/ (year folders aside)\n";
$dirs = @scandir($base);
$found_dir = 0;
if ($dirs) {
    foreach ($dirs as $d) {
        if ($d === '.' || $d === '..') continue;
        $p = $base . '/' . $d;
        if (!is_dir($p)) continue;
        // count images one level down, cheaply
        $n = 0;
        $it = @scandir($p);
        if ($it) foreach ($it as $f) {
            if (in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), $EXTS, true)) $n++;
        }
        $flag = preg_match('/^\d{4}$/', $d) ? '' : (preg_match($WORDS, $d) ? '   <-- LOOKS LIKE IT' : '');
        if ($flag !== '') $found_dir++;
        printf("   %-40s %4d image(s) at top level%s\n", $d, $n, $flag);
    }
}
if (!$found_dir) echo "   (nothing named like the owner's folder)\n";

/* ── B. zips ──────────────────────────────────────────────────────────── */
echo "\nB. ZIP FILES IN uploads/\n";
$zips = array();
foreach ((array) glob($base . '/*.zip') as $z)       $zips[] = $z;
foreach ((array) glob($base . '/*/*.zip') as $z)     $zips[] = $z;
if ($zips) {
    foreach (array_slice($zips, 0, 20) as $z) {
        printf("   %-60s %s\n", str_replace($base . '/', '', $z), size_format(@filesize($z)));
    }
} else {
    echo "   (none)\n";
}

/* ── C. Media Library images no product uses ──────────────────────────── */
echo "\nC. MEDIA LIBRARY IMAGES NOT USED BY ANY PRODUCT\n";

$all = $wpdb->get_results(
    "SELECT ID, post_title, post_date, guid
       FROM {$wpdb->posts}
      WHERE post_type = 'attachment'
        AND post_mime_type LIKE 'image/%'");
printf("   image attachments in the library: %d\n", count($all));

// everything a product points at: its featured image and its gallery
$used = array();
foreach ($wpdb->get_col("SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id'") as $v) {
    $used[(int) $v] = true;
}
foreach ($wpdb->get_col("SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_product_image_gallery'") as $v) {
    foreach (explode(',', (string) $v) as $id) { $id = (int) trim($id); if ($id) $used[$id] = true; }
}
// and anything a category uses as its thumbnail
foreach ($wpdb->get_col("SELECT meta_value FROM {$wpdb->termmeta} WHERE meta_key = 'thumbnail_id'") as $v) {
    $used[(int) $v] = true;
}
printf("   referenced by a product or category: %d\n", count($used));

$loose = array();
foreach ($all as $a) if (!isset($used[(int) $a->ID])) $loose[] = $a;
printf("   NOT referenced anywhere: %d\n", count($loose));

if ($loose) {
    // newest first — an upload that never became products is usually recent
    usort($loose, function ($x, $y) { return strcmp($y->post_date, $x->post_date); });
    echo "   newest unused images:\n";
    foreach (array_slice($loose, 0, 25) as $a) {
        printf("     #%-8d %-16s %s\n", $a->ID, substr($a->post_date, 0, 16),
            basename(parse_url($a->guid, PHP_URL_PATH)));
    }
    // which folders they live in says whether they arrived together
    $folders = array();
    foreach ($loose as $a) {
        $path = parse_url($a->guid, PHP_URL_PATH);
        $dir  = dirname($path);
        $folders[$dir] = isset($folders[$dir]) ? $folders[$dir] + 1 : 1;
    }
    arsort($folders);
    echo "   where they live:\n";
    $i = 0;
    foreach ($folders as $dir => $n) {
        if ($i++ >= 10) break;
        printf("     %-56s %d\n", $dir, $n);
    }
}

/* ── C½. what the importer's automatic route would take right now ─────── */
// The importer's default (media:fresh) is the unused images above, kept only
// when uploaded after the section shipped and not shaped like a mockup or a
// resized copy. Listing them here makes the deploy log state, in one place,
// exactly what the import pass of this same deploy picked up — or, when this
// list is empty, that the artwork has still not reached the site.
echo "\nC-half. UNUSED UPLOADS NEW ENOUGH TO IMPORT AUTOMATICALLY\n";
$since = (string) get_option('af_goldfoil_fresh_since', '2026-08-22 12:00:00');
$fresh = array();
foreach ($loose as $a) {
    if ($a->post_date < $since) continue;
    $n = basename(parse_url($a->guid, PHP_URL_PATH));
    if (preg_match('/-scene\d+/i', $n)) continue;
    if (preg_match('/-\d{2,4}x\d{2,4}\.[a-z]+$/i', $n)) continue;
    $fresh[] = $a;
}
printf("   uploaded on or after %s and shaped like artwork: %d\n", $since, count($fresh));
foreach (array_slice($fresh, 0, 25) as $a) {
    printf("     #%-8d %-16s %s\n", $a->ID, substr($a->post_date, 0, 16),
        basename(parse_url($a->guid, PHP_URL_PATH)));
}
if (!$fresh) echo "   (none — nothing new has been uploaded since the section went up)\n";

/* ── D. anything named like the owner's folder ────────────────────────── */
echo "\nD. ATTACHMENTS WHOSE NAME MENTIONS personalised / gold / foil / uv\n";
$named = $wpdb->get_results(
    "SELECT ID, post_title, guid FROM {$wpdb->posts}
      WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'
        AND (guid REGEXP 'person|gold|foil' OR post_title REGEXP 'person|gold|foil')
      LIMIT 30");
printf("   matches: %d\n", count($named));
foreach ($named as $a) {
    printf("     #%-8d %s\n", $a->ID, basename(parse_url($a->guid, PHP_URL_PATH)));
}

echo "=== DONE ===\n";
