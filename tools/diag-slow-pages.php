<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * READ-ONLY. Why does an uncached page (cart, wishlist, a shop cache miss)
 * take ~1.3 s to render? Measures the usual suspects: autoloaded options,
 * a persistent object cache, Redis/Memcached availability, the number and
 * cost of database queries while rendering the cart page from this process.
 *
 *   wp eval-file wp-content/themes/postero-child/tools/diag-slow-pages.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
global $wpdb;

echo "=== ENVIRONMENT\n";
echo "  php " . PHP_VERSION . " | opcache: " . (function_exists('opcache_get_status') && ($s = @opcache_get_status(false)) && !empty($s['opcache_enabled']) ? 'on, hit rate ' . round($s['opcache_statistics']['opcache_hit_rate'], 1) . '%' : 'off/unknown') . "\n";
echo "  object-cache.php drop-in: " . (file_exists(WP_CONTENT_DIR . '/object-cache.php') ? 'present' : 'absent') . " | wp_using_ext_object_cache: " . (wp_using_ext_object_cache() ? 'yes' : 'no') . "\n";
echo "  redis extension: " . (extension_loaded('redis') ? 'yes' : 'no') . " | memcached extension: " . (extension_loaded('memcached') ? 'yes' : 'no') . "\n";
foreach (array(array('127.0.0.1', 6379), array('localhost', 6379)) as $h) {
    $fp = @fsockopen($h[0], $h[1], $errno, $errstr, 1);
    echo "  redis at {$h[0]}:{$h[1]}: " . ($fp ? 'reachable' : "no ($errstr)") . "\n"; if ($fp) fclose($fp);
}
foreach (glob('/tmp/redis*.sock') ?: array() as $sock) echo "  redis socket: $sock\n";
foreach (array('object', 'object_kind', 'object_host', 'object_port', 'cache', 'cache-priv', 'esi', 'cache-mobile', 'optm-css_min', 'optm-js_min', 'optm-js_defer', 'optm-css_async', 'optm-ucss', 'media-lazy', 'media-vpi', 'img_optm-auto', 'optm-localize_js', 'optm-gm_js', 'cache-ttl_pub', 'cache-ttl_priv') as $k) {
    $v = get_option('litespeed.conf.' . $k, '(unset)'); echo "  litespeed $k = " . (is_array($v) ? json_encode($v) : $v) . "\n";
}

echo "\n=== AUTOLOADED OPTIONS\n";
$tot = $wpdb->get_var("SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload IN ('yes','on','auto-on','auto')");
$cnt = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE autoload IN ('yes','on','auto-on','auto')");
printf("  %d autoloaded options, %.2f MB total (healthy: under 1 MB)\n", $cnt, $tot / 1048576);
foreach ($wpdb->get_results("SELECT option_name, LENGTH(option_value) len FROM {$wpdb->options} WHERE autoload IN ('yes','on','auto-on','auto') ORDER BY len DESC LIMIT 15") as $r) printf("    %8d  %s\n", $r->len, $r->option_name);
echo "  transients in options table: " . $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_%'") . " (expired: " . $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_timeout\\_%' AND option_value < UNIX_TIMESTAMP()") . ")\n";
echo "  options rows: " . $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options}") . " | postmeta rows: " . $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta}") . " | posts: " . $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts}") . "\n";

echo "\n=== RENDER THE CART PAGE IN THIS PROCESS (queries, time)\n";
if (!defined('SAVEQUERIES')) define('SAVEQUERIES', true);
$wpdb->queries = array();
$_SERVER['REQUEST_URI'] = '/cart/'; $_SERVER['HTTP_HOST'] = parse_url(home_url(), PHP_URL_HOST); $_SERVER['HTTPS'] = 'on'; $_SERVER['REQUEST_METHOD'] = 'GET'; $_SERVER['HTTP_USER_AGENT'] = 'af-diag/1.0';
$hook_time = array(); $last = microtime(true); $last_hook = 'start';
add_action('all', function ($h) use (&$hook_time, &$last, &$last_hook) {
    $now = microtime(true); $hook_time[$last_hook] = ($hook_time[$last_hook] ?? 0) + ($now - $last); $last = $now; $last_hook = $h;
});
$t0 = microtime(true); $q0 = count($wpdb->queries);
try {
    ob_start();
    $GLOBALS['wp']->main();
    $t_main = microtime(true);
    require ABSPATH . WPINC . '/template-loader.php';
    $html = ob_get_clean();
    $t1 = microtime(true);
    printf("  rendered %d bytes | routing %.0f ms | template %.0f ms | total %.0f ms | queries: %d\n", strlen($html), ($t_main - $t0) * 1000, ($t1 - $t_main) * 1000, ($t1 - $t0) * 1000, count($wpdb->queries) - $q0);
    echo "  is_cart(): " . (function_exists('is_cart') && is_cart() ? 'yes' : 'no') . " | template: " . basename((string) ($GLOBALS['template'] ?? '')) . "\n";
} catch (\Throwable $e) {
    @ob_end_clean();
    echo "  render stopped: " . get_class($e) . ' ' . $e->getMessage() . " (queries so far: " . (count($wpdb->queries) - $q0) . ")\n";
}
$qs = array_slice($wpdb->queries, $q0);
usort($qs, function ($a, $b) { return $b[1] <=> $a[1]; });
$sumq = 0; foreach ($qs as $q) $sumq += $q[1];
printf("  time in SQL: %.0f ms across %d queries\n", $sumq * 1000, count($qs));
echo "  slowest queries:\n";
foreach (array_slice($qs, 0, 8) as $q) printf("    %6.1f ms  %s\n", $q[1] * 1000, preg_replace('/\s+/', ' ', mb_strimwidth($q[0], 0, 150, '…')));
// which caller pattern repeats most
$by = array();
foreach ($qs as $q) { $k = preg_replace('/\d+/', 'N', preg_replace('/\s+/', ' ', mb_strimwidth($q[0], 0, 70))); $by[$k] = ($by[$k] ?? 0) + 1; }
arsort($by); echo "  most repeated query shapes:\n";
foreach (array_slice($by, 0, 8, true) as $k => $n) printf("    %4d×  %s\n", $n, $k);
arsort($hook_time); echo "  time between hooks (which stage is slow):\n";
foreach (array_slice($hook_time, 0, 10, true) as $h => $t) printf("    %6.0f ms  after %s\n", $t * 1000, $h);
echo "\n=== DONE ===\n";
