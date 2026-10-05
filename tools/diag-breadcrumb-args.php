<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. Why the WooCommerce breadcrumb reads "Home Page" + something
 * between the crumbs with Elementor Pro unloaded, but "Home / Shop" with it
 * (Preview Look, 5 Oct). Run it twice: as is, and with skip_plugins
 * elementor-pro.
 *
 * Prints:
 *   - every callback on woocommerce_breadcrumb_defaults, woocommerce_get_breadcrumb
 *     and woocommerce_breadcrumb_home_url, with the file (under wp-content) and line
 *   - the defaults after those filters
 *   - which global/breadcrumb.php template is used, and whether it is WooCommerce's own
 *   - the breadcrumb HTML on the shop page, from woocommerce_breadcrumb() and
 *     from Elementor's "woocommerce-breadcrumb" widget (whichever is registered:
 *     Elementor Pro's, or the theme's port when Pro is unloaded)
 *
 * Run: wp eval-file tools/diag-breadcrumb-args.php --allow-root [--skip-plugins=elementor-pro]
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }

echo 'Elementor Pro loaded: ' . (defined('ELEMENTOR_PRO_VERSION') ? 'yes' : 'no') . "\n";
$rel = function ($f) { $p = strpos($f, '/wp-content/'); return $p === false ? basename($f) : substr($f, $p + 12); };
$where = function ($cb) use ($rel) {
    try {
        if (is_string($cb) && strpos($cb, '::') !== false) $cb = explode('::', $cb);
        if (is_array($cb)) { $r = new ReflectionMethod($cb[0], $cb[1]); $n = (is_object($cb[0]) ? get_class($cb[0]) : $cb[0]) . '::' . $cb[1]; }
        elseif ($cb instanceof Closure) { $r = new ReflectionFunction($cb); $n = 'closure'; }
        else { $r = new ReflectionFunction($cb); $n = (string) $cb; }
        return $n . '  (' . ($r->getFileName() ? $rel($r->getFileName()) . ':' . $r->getStartLine() : 'internal') . ')';
    } catch (Throwable $e) { return '?'; }
};
global $wp_filter;
foreach (array('woocommerce_breadcrumb_defaults', 'woocommerce_get_breadcrumb', 'woocommerce_breadcrumb_home_url') as $h) {
    echo "\n$h:\n";
    if (empty($wp_filter[$h])) { echo "  (none)\n"; continue; }
    foreach ($wp_filter[$h]->callbacks as $prio => $cbs) foreach ($cbs as $cb) echo "  $prio  " . $where($cb['function']) . "\n";
}

if (!function_exists('woocommerce_breadcrumb')) { echo "\nWooCommerce not loaded\n"; exit; }
$stock = array(
    'delimiter' => '&nbsp;&#47;&nbsp;', 'wrap_before' => '<nav class="woocommerce-breadcrumb" aria-label="Breadcrumb">',
    'wrap_after' => '</nav>', 'before' => '', 'after' => '', 'home' => _x('Home', 'breadcrumb', 'woocommerce'),
);
$filtered = apply_filters('woocommerce_breadcrumb_defaults', $stock);
echo "\ndefaults after the filters:\n";
foreach ($filtered as $k => $v) echo '  ' . str_pad($k, 12) . ' ' . json_encode($v) . ((isset($stock[$k]) && $stock[$k] === $v) ? '' : '   <- changed') . "\n";

$tpl = wc_locate_template('global/breadcrumb.php');
$own = WC()->plugin_path() . '/templates/global/breadcrumb.php';
echo "\ntemplate: " . $rel($tpl) . (realpath($tpl) === realpath($own) ? ' (WooCommerce\'s own)' : (is_readable($own) && md5_file($tpl) === md5_file($own) ? ' (a copy of WooCommerce\'s)' : ' (an override, differs from WooCommerce\'s)')) . "\n";

// The shop page, as a visitor's request sees it.
$shop = wc_get_page_id('shop');
$GLOBALS['wp_query'] = new WP_Query(array('post_type' => 'product', 'posts_per_page' => 1));
$GLOBALS['wp_query']->is_post_type_archive = true;
$GLOBALS['wp_query']->is_archive = true;
$GLOBALS['wp_query']->set('post_type', 'product');
$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
echo "is_shop(): " . (is_shop() ? 'yes' : 'no') . " (shop page #$shop)\n";
ob_start(); woocommerce_breadcrumb(); $html = trim(ob_get_clean());
echo "\nwoocommerce_breadcrumb():\n  " . $html . "\n";
ob_start(); woocommerce_breadcrumb($stock); $html = trim(ob_get_clean());
echo "woocommerce_breadcrumb(WooCommerce's stock defaults):\n  " . $html . "\n";

if (class_exists('\Elementor\Plugin')) {
    $wm = \Elementor\Plugin::$instance->widgets_manager;
    $type = $wm->get_widget_types('woocommerce-breadcrumb');
    echo "\nElementor widget woocommerce-breadcrumb: " . ($type ? get_class($type) . '  (' . $rel((new ReflectionClass($type))->getFileName()) . ')' : 'not registered') . "\n";
    if ($type) {
        try {
            $el = \Elementor\Plugin::$instance->elements_manager->create_element_instance(array('id' => 'afdiag', 'elType' => 'widget', 'widgetType' => 'woocommerce-breadcrumb', 'settings' => array()));
            if ($el) {
                ob_start(); $el->print_element(); $html = trim(preg_replace('/\s+/', ' ', ob_get_clean()));
                echo "  rendered: " . $html . "\n";
            }
        } catch (Throwable $e) { while (ob_get_level() > 1) ob_end_clean(); echo '  could not render: ' . $e->getMessage() . "\n"; }
    }
}
echo "done\n";
