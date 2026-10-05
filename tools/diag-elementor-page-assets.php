<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. How the installed Elementor (GPL, as on wordpress.org) decides
 * which scripts and styles a widget gets on the page, because with Elementor
 * Pro unloaded the home page carried the theme's own "slides" widget but not
 * the scripts and styles it asks for (assets/ports/epro/slides.js / .css),
 * so the hero slideshow never started (Preview Look, 5 Oct).
 *
 * Prints:
 *   - the page-assets record Elementor keeps for the home page (#75) and the
 *     breadcrumb templates: the script and style handles it will enqueue
 *   - Elementor's code that enqueues a widget's get_script_depends /
 *     get_style_depends, and the page-assets code that reads/writes that record
 *   - whether the theme's handles are registered on the front end
 *
 * Run: wp eval-file tools/diag-elementor-page-assets.php --allow-root [--skip-plugins=elementor-pro]
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
if (!class_exists('\Elementor\Plugin')) { echo "Elementor not loaded\n"; exit; }
echo 'Elementor ' . ELEMENTOR_VERSION . ', Pro loaded: ' . (defined('ELEMENTOR_PRO_VERSION') ? 'yes' : 'no') . "\n";

foreach (array(75, 3014, 1853) as $id) {
    echo "\n#$id post meta with 'assets' in the key:\n";
    $all = get_post_meta($id);
    $n = 0;
    foreach ($all as $k => $v) {
        if (stripos($k, 'asset') === false) continue;
        $n++;
        $val = maybe_unserialize($v[0]);
        echo '  ' . $k . ': ' . substr(json_encode($val), 0, 1500) . "\n";
    }
    if (!$n) echo "  none\n";
}

$rel = function ($f) { $p = strpos((string) $f, '/wp-content/'); return $p === false ? basename((string) $f) : substr($f, $p + 12); };
$show = function ($class, $methods) use ($rel) {
    if (!class_exists($class)) { echo "\n## $class: not loaded\n"; return; }
    foreach ($methods as $m) {
        if (!method_exists($class, $m)) { echo "\n## $class::$m: none\n"; continue; }
        $r = new ReflectionMethod($class, $m);
        $lines = file($r->getFileName());
        echo "\n## " . $r->getDeclaringClass()->getName() . "::$m  (" . $rel($r->getFileName()) . ':' . $r->getStartLine() . ")\n";
        echo implode('', array_slice($lines, $r->getStartLine() - 1, $r->getEndLine() - $r->getStartLine() + 1));
    }
};
$show('Elementor\Element_Base', array('enqueue_scripts', 'enqueue_styles', 'get_script_depends', 'get_style_depends'));
$show('Elementor\Widget_Base', array('enqueue_scripts', 'enqueue_styles', 'get_script_depends', 'get_style_depends'));
$show('Elementor\Core\Page_Assets\Data_Managers\Base', array('get_asset_data'));
foreach (array('Elementor\Core\Page_Assets\Loader', 'Elementor\Core\Base\Document', 'Elementor\Plugin') as $c) {
    if (!class_exists($c)) continue;
    $names = array();
    foreach ((new ReflectionClass($c))->getMethods() as $m) if (preg_match('/asset/i', $m->getName())) $names[] = $m->getName();
    echo "\n$c methods about assets: " . implode(', ', $names) . "\n";
    if ($c !== 'Elementor\Plugin') $show($c, $names);
}

// The theme's handles, as registered for the front end.
do_action('elementor/frontend/after_register_scripts');
do_action('elementor/frontend/after_register_styles');
echo "\nhandles: script af-epro-slides " . (wp_script_is('af-epro-slides', 'registered') ? 'registered' : 'NOT registered')
    . ', script swiper ' . (wp_script_is('swiper', 'registered') ? 'registered' : 'NOT registered')
    . ', style af-epro-slides ' . (wp_style_is('af-epro-slides', 'registered') ? 'registered' : 'NOT registered')
    . ', style e-swiper ' . (wp_style_is('e-swiper', 'registered') ? 'registered' : 'NOT registered') . "\n";
echo "done\n";
