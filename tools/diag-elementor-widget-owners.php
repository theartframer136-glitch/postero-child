<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. Which code owns each Elementor widget name, and who registers
 * widgets when. With Elementor Pro unloaded (Preview Look, 5 Oct) the hero
 * slideshow and the shop breadcrumbs were drawn by the parent theme
 * (Postero), not by the child theme's ports, which register under the same
 * names (inc/ports/elementor-pro.php). Run with skip_plugins elementor-pro.
 *
 * Prints:
 *   - the class and file (under wp-content) behind "slides" and
 *     "woocommerce-breadcrumb"
 *   - every widget the parent theme registers (name -> class)
 *   - every callback on elementor/widgets/register and the older
 *     elementor/widgets/widgets_registered, with its priority and file:line
 *
 * Run: wp eval-file tools/diag-elementor-widget-owners.php --allow-root [--skip-plugins=elementor-pro]
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
if (!class_exists('\Elementor\Plugin')) { echo "Elementor not loaded\n"; exit; }

echo 'Elementor Pro loaded: ' . (defined('ELEMENTOR_PRO_VERSION') ? 'yes' : 'no') . "\n";
$rel = function ($f) { $p = strpos((string) $f, '/wp-content/'); return $p === false ? basename((string) $f) : substr($f, $p + 12); };
$types = \Elementor\Plugin::$instance->widgets_manager->get_widget_types();

echo "\nnames the ports use:\n";
foreach (array('slides', 'woocommerce-breadcrumb') as $n) {
    $w = isset($types[$n]) ? $types[$n] : null;
    echo '  ' . str_pad($n, 24) . ($w ? get_class($w) . '  (' . $rel((new ReflectionClass($w))->getFileName()) . ')' : 'not registered') . "\n";
    if ($w) echo '  ' . str_repeat(' ', 24) . 'scripts ' . json_encode($w->get_script_depends()) . ', styles ' . json_encode($w->get_style_depends()) . "\n";
}

echo "\nwidgets from the parent theme:\n";
$n = 0;
foreach ($types as $name => $w) {
    $f = $rel((new ReflectionClass($w))->getFileName());
    if (strpos($f, 'themes/postero/') === 0) { $n++; echo '  ' . str_pad($name, 32) . get_class($w) . "\n"; }
}
if (!$n) echo "  none\n";

global $wp_filter;
foreach (array('elementor/widgets/register', 'elementor/widgets/widgets_registered') as $h) {
    echo "\n$h:\n";
    if (empty($wp_filter[$h])) { echo "  (none)\n"; continue; }
    foreach ($wp_filter[$h]->callbacks as $prio => $cbs) foreach ($cbs as $cb) {
        $fn = $cb['function']; $where = '?';
        try {
            if (is_string($fn) && strpos($fn, '::') !== false) $fn = explode('::', $fn);
            $r = is_array($fn) ? new ReflectionMethod($fn[0], $fn[1]) : new ReflectionFunction($fn);
            $label = is_array($fn) ? (is_object($fn[0]) ? get_class($fn[0]) : $fn[0]) . '::' . $fn[1] : ($fn instanceof Closure ? 'closure' : (string) $fn);
            $where = $label . '  (' . ($r->getFileName() ? $rel($r->getFileName()) . ':' . $r->getStartLine() : 'internal') . ')';
        } catch (Throwable $e) {}
        echo '  ' . str_pad((string) $prio, 6) . $where . "\n";
    }
}
echo "done\n";
