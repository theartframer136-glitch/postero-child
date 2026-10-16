<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * READ-ONLY. What makes a page 950 KB of HTML and 1.1 s of PHP: the inline
 * script and style blocks by size, the external assets by host, Elementor's
 * CSS settings (internal embedding re-parses CSS on every request), and
 * which code makes a remote HTTP call while rendering.
 *
 *   wp eval-file wp-content/themes/postero-child/tools/diag-page-weight.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
global $wpdb;

echo "=== ELEMENTOR SETTINGS THAT COST RENDER TIME\n";
foreach (array('elementor_css_print_method', 'elementor_optimized_css_loading', 'elementor_experiment-e_optimized_css_loading', 'elementor_experiment-e_font_icon_svg', 'elementor_experiment-e_optimized_assets_loading', 'elementor_experiment-e_element_cache', 'elementor_element_cache_ttl', 'elementor_load_fa4_shim', 'elementor_disable_color_schemes', 'elementor_disable_typography_schemes', 'elementor_experiment-e_lazyload', 'elementor_experiment-container', 'elementor_google_font') as $k) {
    $v = get_option($k, '(unset)'); echo "  $k = " . (is_array($v) ? json_encode($v) : $v) . "\n";
}
$up = wp_get_upload_dir(); $cssdir = $up['basedir'] . '/elementor/css';
echo "  uploads/elementor/css: " . (is_dir($cssdir) ? count(glob($cssdir . '/*.css')) . ' files, writable ' . (is_writable($cssdir) ? 'yes' : 'NO') : 'MISSING') . "\n";
foreach (array(10 => 'cart page', 75 => 'home', 14 => 'wishlist') as $pid => $label) {
    $m = get_post_meta($pid, '_elementor_css', true);
    echo "  _elementor_css #$pid ($label): " . (is_array($m) ? ('status=' . ($m['status'] ?? '?') . ' time=' . (isset($m['time']) ? gmdate('Y-m-d H:i', (int) $m['time']) : '?') . ' css bytes=' . strlen((string) ($m['css'] ?? ''))) : 'none') . "\n";
}
$hfe = get_posts(array('post_type' => 'elementor-hf', 'post_status' => 'publish', 'posts_per_page' => 10, 'fields' => 'ids'));
foreach ($hfe as $tid) { $m = get_post_meta($tid, '_elementor_css', true); echo "  header/footer template #$tid '" . get_the_title($tid) . "': css status=" . (is_array($m) ? ($m['status'] ?? '?') : 'none') . "\n"; }

echo "\n=== REMOTE CALLS DURING A PAGE RENDER (who, where)\n";
$calls = array();
add_filter('pre_http_request', function ($pre, $args, $url) use (&$calls) {
    $bt = array();
    foreach (array_slice(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 25), 0, 25) as $f) {
        if (!empty($f['file']) && strpos($f['file'], 'wp-includes/') === false && strpos($f['file'], 'class-http') === false) { $bt[] = str_replace(ABSPATH, '', $f['file']) . ':' . ($f['line'] ?? ''); if (count($bt) >= 3) break; }
    }
    $calls[] = array($url, implode(' < ', $bt));
    return $pre;
}, 1, 3);

$_SERVER['REQUEST_URI'] = '/cart/'; $_SERVER['HTTP_HOST'] = parse_url(home_url(), PHP_URL_HOST); $_SERVER['HTTPS'] = 'on'; $_SERVER['REQUEST_METHOD'] = 'GET';
if (!defined('WP_USE_THEMES')) define('WP_USE_THEMES', true);
$html = '';
try { ob_start(); $GLOBALS['wp']->main(); require ABSPATH . WPINC . '/template-loader.php'; $html = (string) ob_get_clean(); } catch (\Throwable $e) { @ob_end_clean(); echo "  render stopped: " . $e->getMessage() . "\n"; }
foreach ($calls as $c) echo "  " . preg_replace('#\?.*#', '?…', $c[0]) . "\n      from " . $c[1] . "\n";
if (!$calls) echo "  none\n";

echo "\n=== THE CART PAGE HTML: " . strlen($html) . " bytes\n";
preg_match_all('#<script\b([^>]*)>(.*?)</script>#is', $html, $sc, PREG_SET_ORDER);
preg_match_all('#<style\b([^>]*)>(.*?)</style>#is', $html, $st, PREG_SET_ORDER);
$inline_js = 0; $ext = array(); $blocks = array();
foreach ($sc as $m) {
    if (preg_match('/\bsrc=["\']([^"\']+)/i', $m[1], $s)) { $h = parse_url($s[1], PHP_URL_HOST) ?: 'relative'; $ext[$h] = ($ext[$h] ?? 0) + 1; continue; }
    $inline_js += strlen($m[2]);
    $id = preg_match('/\bid=["\']([^"\']+)/i', $m[1], $i) ? $i[1] : '';
    $blocks[] = array(strlen($m[2]), 'script', $id, preg_replace('/\s+/', ' ', mb_strimwidth(trim(strip_tags($m[2])), 0, 90)));
}
$inline_css = 0;
foreach ($st as $m) { $inline_css += strlen($m[2]); $id = preg_match('/\bid=["\']([^"\']+)/i', $m[1], $i) ? $i[1] : ''; $blocks[] = array(strlen($m[2]), 'style', $id, preg_replace('/\s+/', ' ', mb_strimwidth(trim($m[2]), 0, 90))); }
$links = preg_match_all('#<link\b[^>]*rel=["\']stylesheet["\'][^>]*>#i', $html);
printf("  inline <script>: %d blocks, %d KB | external scripts: %d | inline <style>: %d blocks, %d KB | stylesheet links: %d\n", count($blocks) - count($st), round($inline_js / 1024), array_sum($ext), count($st), round($inline_css / 1024), $links);
printf("  markup (no script/style): %d KB\n", round((strlen($html) - $inline_js - $inline_css) / 1024));
echo "  external script hosts: " . json_encode($ext) . "\n";
usort($blocks, function ($a, $b) { return $b[0] <=> $a[0]; });
echo "  biggest inline blocks:\n";
foreach (array_slice($blocks, 0, 18) as $b) printf("    %6d KB  %-6s %-28s %s\n", round($b[0] / 1024), $b[1], $b[2], $b[3]);
// the theme's own fingerprints inside inline scripts
$theme_marks = array('AF_SHIP' => 0, 'af-' => 0, 'taf-' => 0, 'elementorFrontendConfig' => 0, 'wc_add_to_cart_params' => 0, 'rs-module' => 0, 'revslider' => 0);
foreach ($blocks as $b) foreach ($theme_marks as $k => $_) if (stripos($b[3], $k) !== false) $theme_marks[$k] += $b[0];
echo "  inline bytes by fingerprint of the first line: " . json_encode(array_map(function ($v) { return round($v / 1024) . ' KB'; }, $theme_marks)) . "\n";
echo "  <img> tags: " . preg_match_all('#<img\b#i', $html) . " | <svg>: " . preg_match_all('#<svg\b#i', $html) . " | data: URIs: " . preg_match_all('#data:image/#i', $html) . " (" . round(array_sum(array_map('strlen', preg_match_all('#data:image/[^"\')]+#i', $html, $du) ? $du[0] : array())) / 1024) . " KB)\n";
echo "\n=== DONE ===\n";
