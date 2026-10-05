<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. How the installed Elementor (GPL, as on wordpress.org) decides
 * to serve a page from its element cache (_elementor_element_cache), so a
 * preview request can be rendered afresh. Preview Look (5 Oct) showed the
 * home page served from the copy rendered while Elementor Pro was loaded,
 * although the preview file returns "inactive" for the old experiment option.
 *
 * Prints the element-cache module's code, every line in Elementor that reads
 * or writes _elementor_element_cache or the cache's settings, the settings'
 * current values, and whether the home page has a stored copy (size and
 * timeout only).
 *
 * Run: wp eval-file tools/diag-elementor-element-cache.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
if (!class_exists('\Elementor\Plugin')) { echo "Elementor not loaded\n"; exit; }
echo 'Elementor ' . ELEMENTOR_VERSION . "\n";
foreach (array('elementor_element_cache_ttl', 'elementor_experiment-e_element_cache') as $o) echo "$o: " . var_export(get_option($o), true) . "\n";
$exp = \Elementor\Plugin::$instance->experiments;
echo 'experiment e_element_cache: ' . ($exp->get_features('e_element_cache') ? 'known, ' . ($exp->is_feature_active('e_element_cache') ? 'active' : 'inactive') : 'not an experiment') . "\n";
$front = (int) get_option('page_on_front');
$c = get_post_meta($front, '_elementor_element_cache', true);
echo "home #$front stored copy: " . (is_array($c) ? 'array, timeout ' . (isset($c['timeout']) ? gmdate('c', (int) $c['timeout']) : '?') . ', ' . strlen(json_encode($c)) . ' bytes' : (is_string($c) && $c !== '' ? strlen($c) . ' bytes' : 'none')) . "\n";

$root = WP_PLUGIN_DIR . '/elementor';
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
echo "\nlines about the element cache:\n";
foreach ($it as $f) {
    $p = $f->getPathname();
    if (substr($p, -4) !== '.php' || strpos($p, '/vendor') !== false) continue;
    foreach (file($p) as $i => $line) {
        if (preg_match('/_elementor_element_cache|element_cache_ttl|e_element_cache|should_render_shortcode|ELEMENT_CACHE|element-cache/i', $line)) echo '  ' . substr($p, strlen($root) + 1) . ':' . ($i + 1) . '  ' . trim($line) . "\n";
    }
}
foreach (array('Elementor\Modules\ElementCache\Module') as $class) {
    if (!class_exists($class)) { echo "\n$class not loaded\n"; continue; }
    $r = new ReflectionClass($class);
    echo "\n## " . substr($r->getFileName(), strlen($root) + 1) . "\n";
    echo file_get_contents($r->getFileName());
}
echo "\ndone\n";
