<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. Builds a whole front-end page the way a visitor's request does
 * (main query, template-loader, wp_head/wp_footer) inside this one wp-cli
 * process, so a theme port can be checked with its plugin left unloaded
 * (--skip-plugins=<folder>) while the live site keeps the plugin. Prints the
 * page size, plugin markers found in it, and any PHP error.
 *
 * Run: wp eval-file tools/diag-port-fullpage.php --allow-root [--skip-plugins=a]
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
$errs = array();
set_error_handler(function ($no, $str, $file, $line) use (&$errs) {
    if ($no & (E_WARNING | E_USER_WARNING | E_RECOVERABLE_ERROR)) $errs[] = "$str @ " . preg_replace('#^.*/wp-content/#', '', $file) . ":$line";
    return false;
});
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR), true)) echo "\nFATAL: {$e['message']} in {$e['file']}:{$e['line']}\n";
});
foreach (array('/' => 'home') as $path => $label) {
    $_SERVER['REQUEST_URI'] = $path; $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = parse_url(home_url(), PHP_URL_HOST);
    $_SERVER['HTTPS'] = 'on'; $_SERVER['SERVER_PORT'] = '443';
    $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/124 Safari/537.36';
    remove_action('template_redirect', 'redirect_canonical');
    add_filter('wp_redirect', function ($to) { echo "  (redirect to $to suppressed)\n"; return false; }, 1);
    $GLOBALS['wp']->main();
    if (!defined('WP_USE_THEMES')) define('WP_USE_THEMES', true);
    ob_start();
    try {
        include ABSPATH . WPINC . '/template-loader.php';
    } catch (\Throwable $e) {
        echo "\nEXCEPTION " . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
    }
    $html = ob_get_clean();
    echo "=== $label: " . strlen($html) . " bytes\n";
    foreach (array('instagram-gallery-feed', 'qligg-frontend', 'g-review', 'grwp', 'sr7-module', 'elementor-widget-shortcode', 'There has been a critical error', 'EXCEPTION') as $n) echo "  $n: " . substr_count($html, $n) . "\n";
    if (preg_match('/.{0,300}instagram-gallery-feed.{0,200}/s', $html, $m)) echo "  sample: " . preg_replace('/\s+/', ' ', $m[0]) . "\n";
}
echo "=== warnings (" . count($errs) . ")\n";
foreach (array_slice(array_unique($errs), 0, 25) as $e) echo "  $e\n";
echo "=== END\n";
