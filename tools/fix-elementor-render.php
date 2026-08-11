<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Put the Elementor pages back to rendering with their design.
 *
 * Two things can leave a page showing raw text and giant unsized icons:
 *
 *   1. _elementor_data no longer parses as JSON. Elementor then has no widget
 *      tree to build and the browser falls back to the bare content. The
 *      restore step could cause this: it read the backup straight out of the
 *      database with $wpdb (already unslashed, because update_post_meta
 *      unslashes on save) and then called wp_unslash on it a second time,
 *      which eats the backslashes that JSON needs.
 *   2. The generated per-post CSS is gone. Elementor writes each page's styles
 *      to a file under uploads/elementor/css and remembers it in _elementor_css.
 *      Clearing the file cache deletes those files; if the directory is not
 *      writable they never come back, so the markup renders with no styling at
 *      all — exactly what "unstyled page" looks like.
 *
 * This reports which of the two is true, repairs both, and proves the result by
 * fetching the pages and looking for the styles in the response. It only ever
 * writes _elementor_data from a stored backup that parses, so a page whose data
 * is already healthy is left alone.
 *
 * Run: wp eval-file tools/fix-elementor-render.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
global $wpdb;

echo "=== ELEMENTOR RENDER REPAIR ===\n";

/** A design is usable when it decodes to a non-empty list of sections. */
function af_el_ok($raw) {
    if (!is_string($raw) || $raw === '') return false;
    $d = json_decode($raw, true);
    return is_array($d) && !empty($d);
}

// ── 1. data integrity ─────────────────────────────────────────────
echo "\n-- design data --\n";
$rows = $wpdb->get_results(
    "SELECT meta_id, post_id, meta_value FROM {$wpdb->postmeta}
      WHERE meta_key = '_elementor_data' AND meta_value != ''");
$broken = array(); $healthy = 0;
foreach ($rows as $r) {
    if (af_el_ok($r->meta_value)) { $healthy++; continue; }
    $broken[] = $r;
}
printf("  designs stored: %d   parse: %d ok, %d broken\n", count($rows), $healthy, count($broken));

$repaired = 0; $unrepairable = array();
foreach ($broken as $r) {
    $backup = $wpdb->get_var($wpdb->prepare(
        "SELECT meta_value FROM {$wpdb->postmeta}
          WHERE post_id = %d AND meta_key = '_af_elementor_backup' LIMIT 1", $r->post_id));
    // try the backup exactly as stored, then with slashes put back — whichever
    // parses is the original; guessing is not involved because JSON either
    // decodes or it does not
    $candidates = array($backup, wp_slash((string) $backup), stripslashes((string) $backup));
    $good = null; $from = 'backup';
    foreach ($candidates as $c) { if (af_el_ok($c)) { $good = $c; break; } }

    if ($good === null) {
        // No usable backup. Elementor saves a revision on every edit, and each
        // revision carries its own copy of the design — the newest one that
        // parses is the page as the owner last left it, which is exactly what
        // "put it back the way it was" means here.
        $revs = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
              WHERE post_parent = %d AND post_type = 'revision'
              ORDER BY post_date DESC LIMIT 30", (int) $r->post_id));
        foreach ($revs as $rev) {
            $rv = $wpdb->get_var($wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->postmeta}
                  WHERE post_id = %d AND meta_key = '_elementor_data' LIMIT 1", (int) $rev));
            foreach (array($rv, wp_slash((string) $rv), stripslashes((string) $rv)) as $c) {
                if (af_el_ok($c)) { $good = $c; $from = 'revision #' . $rev; break 2; }
            }
        }
    }

    if ($good === null) {
        $unrepairable[] = $r->post_id;
        printf("  UNREPAIRABLE #%-6d %s (no parseable backup)\n", $r->post_id,
               mb_strimwidth((string) get_the_title($r->post_id), 0, 44, '…'));
        continue;
    }
    $ok = $wpdb->update($wpdb->postmeta, array('meta_value' => $good), array('meta_id' => (int) $r->meta_id));
    if ($ok === false) { echo "  FAILED write meta #{$r->meta_id}\n"; continue; }
    $repaired++;
    printf("  repaired  #%-6d %-44s from %s\n", $r->post_id,
           mb_strimwidth((string) get_the_title($r->post_id), 0, 44, '…'), $from);
    clean_post_cache((int) $r->post_id);
}
printf("  repaired: %d   still broken: %d\n", $repaired, count($unrepairable));

// ── 2. where the styles are printed ───────────────────────────────
echo "\n-- css delivery --\n";
$method = get_option('elementor_css_print_method', 'external');
echo "  print method: {$method}\n";
$up  = wp_upload_dir();
$dir = trailingslashit($up['basedir']) . 'elementor/css';
$writable = wp_mkdir_p($dir) && is_writable($dir);
$files = $writable && is_dir($dir) ? count(glob($dir . '/post-*.css')) : 0;
printf("  css dir: %s  writable: %s  files: %d\n", $dir, $writable ? 'yes' : 'NO', $files);

if (!$writable) {
    // Nothing can regenerate into a directory that cannot be written. Printing
    // the styles inline removes the dependency on the filesystem entirely; it
    // costs a little page weight and is the difference between a styled page
    // and an unstyled one.
    update_option('elementor_css_print_method', 'internal');
    echo "  css dir is not writable — switched print method to internal (styles inline)\n";
}

// ── 3. force regeneration ─────────────────────────────────────────
echo "\n-- regeneration --\n";
// stale _elementor_css meta points at a file that may no longer exist; dropping
// it makes Elementor rebuild on the next render
$dropped = $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_css'");
echo "  cleared per-post css records: " . (int) $dropped . "\n";
delete_option('_elementor_global_css');
delete_option('elementor-custom-breakpoints-files');
if (class_exists('\\Elementor\\Plugin')) {
    try {
        \Elementor\Plugin::$instance->files_manager->clear_cache();
        echo "  elementor file cache cleared\n";
    } catch (\Throwable $e) { echo "  (clear_cache skipped: " . $e->getMessage() . ")\n"; }
}
if (function_exists('wp_cache_flush')) wp_cache_flush();
if (has_action('litespeed_purge_all')) do_action('litespeed_purge_all');

// ── 4. prove it by looking at the pages ───────────────────────────
echo "\n-- what a visitor now receives --\n";
$targets = array('home' => home_url('/'));
foreach (array('about' => '/about/', 'shipping' => '/shipping-delivery/', 'contact' => '/contact/') as $k => $p) {
    $targets[$k] = home_url($p);
}
$shop = wc_get_page_id('shop');
if ($shop > 0) $targets['shop'] = get_permalink($shop);

foreach ($targets as $name => $url) {
    // ask twice if needed: the first hit is what regenerates the css
    $body = '';
    for ($try = 1; $try <= 2; $try++) {
        $res = wp_remote_get(add_query_arg('afcssgen', time() . $try, $url),
            array('timeout' => 60, 'sslverify' => false, 'headers' => array('User-Agent' => 'AF-Fix')));
        if (is_wp_error($res)) { $body = ''; continue; }
        $body = wp_remote_retrieve_body($res);
        if (strpos($body, 'elementor') !== false) break;
    }
    if ($body === '') { printf("  %-9s FETCH FAILED\n", $name); continue; }
    $hasWidgets = strpos($body, 'elementor-widget') !== false;
    $hasCssFile = (bool) preg_match('#uploads/elementor/css/post-\d+\.css#', $body);
    $hasInline  = (bool) preg_match('#<style[^>]*>[^<]*\.elementor-#s', $body);
    $frontend   = strpos($body, 'elementor/assets/css/frontend') !== false
               || strpos($body, 'elementor-frontend') !== false;
    printf("  %-9s widgets:%-3s post-css:%-3s inline-css:%-3s frontend-css:%-3s %s\n",
        $name,
        $hasWidgets ? 'yes' : 'no',
        $hasCssFile ? 'yes' : 'no',
        $hasInline  ? 'yes' : 'no',
        $frontend   ? 'yes' : 'no',
        ($hasWidgets && !$hasCssFile && !$hasInline) ? '<-- STYLES MISSING' : 'ok');
}

update_option('af_elementor_fix_summary', sprintf(
    'designs %d ok / %d repaired / %d broken, css %s, files %d, at %s UTC',
    $healthy, $repaired, count($unrepairable),
    get_option('elementor_css_print_method', 'external'),
    $files, gmdate('Y-m-d H:i')), false);

echo "=== DONE ===\n";
