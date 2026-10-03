<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. Renders the home page (Elementor document #75) and the review and
 * Instagram shortcodes in this one wp-cli process, so a theme port can be
 * tried with its plugin left unloaded (run with --skip-plugins=<folder>) while
 * the live site keeps using the plugin. Prints which ports are active, the
 * size of each render, and any PHP error or exception with file and line.
 *
 * Run: wp eval-file tools/diag-port-render.php --allow-root [--skip-plugins=a,b]
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR), true)) {
        echo "\nFATAL: {$e['message']}\n  in {$e['file']}:{$e['line']}\n";
    }
});
set_error_handler(function ($no, $str, $file, $line) {
    if ($no & (E_WARNING | E_USER_WARNING)) echo "  warning: $str in " . preg_replace('#^.*/wp-content/#', '', $file) . ":$line\n";
    return false;
});
echo "=== ports\n";
foreach (array('AF_GRWP_PORT' => 'google-reviews', 'QLIGG_PLUGIN_VERSION' => 'instagram (constant)', 'AF_EAEL_PORT' => 'essential-addons', 'AF_HFE_PORT' => 'header-footer-elementor') as $c => $n) echo "  $n: " . (defined($c) ? 'defined' : 'no') . "\n";
echo '  grwp plugin loaded: ' . (class_exists('Freemius', false) ? 'yes (Freemius present)' : 'no') . "\n";
echo '  insta plugin file loaded: ' . (defined('QLIGG_PLUGIN_FILE') ? QLIGG_PLUGIN_FILE : '-') . "\n";

$try = function ($label, $fn) {
    echo "=== $label\n";
    try {
        ob_start();
        $out = $fn();
        $echoed = ob_get_clean();
        $html = (string) $out . $echoed;
        echo '  ok, ' . strlen($html) . " bytes\n";
        return $html;
    } catch (\Throwable $e) {
        while (ob_get_level() > 1) ob_end_clean();
        echo '  ' . get_class($e) . ': ' . $e->getMessage() . "\n  in " . $e->getFile() . ':' . $e->getLine() . "\n";
        foreach (array_slice(explode("\n", $e->getTraceAsString()), 0, 12) as $t) echo "    $t\n";
        return '';
    }
};
$try('[google-reviews]', function () { return do_shortcode('[google-reviews]'); });
$try('[insta-gallery id="0"]', function () { return do_shortcode('[insta-gallery id="0"]'); });
$GLOBALS['wp_query'] = new WP_Query(array('page_id' => 75));
$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
if (have_posts()) the_post();
$html = $try('home (Elementor #75)', function () {
    return class_exists('\Elementor\Plugin') ? \Elementor\Plugin::instance()->frontend->get_builder_content(75, true) : 'no elementor';
});
foreach (array('g-review', 'qligg', 'insta-gallery', 'sr7-module', 'eael-woo-product-carousel') as $needle) echo "  contains $needle: " . substr_count($html, $needle) . "\n";
echo "=== END\n";
