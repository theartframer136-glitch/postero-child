<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. Prints the installed Elementor's own code (GPL, as published on
 * wordpress.org) for when a page view writes a CSS file, its post meta or
 * the element cache, so the preview tooling can make sure a preview request
 * (plugins unloaded for that one request) never writes anything visitors
 * would then be served.
 *
 * Run: wp eval-file tools/diag-elementor-css-writes.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }

echo 'Elementor ' . (defined('ELEMENTOR_VERSION') ? ELEMENTOR_VERSION : '?') . "\n";
echo 'elementor_css_print_method: ' . var_export(get_option('elementor_css_print_method'), true) . "\n";
echo 'element cache experiment: ' . var_export(get_option('elementor_experiment-e_element_cache'), true) . ', ttl ' . var_export(get_option('elementor_element_cache_ttl'), true) . "\n";
$m75 = get_post_meta(75, '_elementor_css', true);
echo 'post 75 _elementor_css: ' . (is_array($m75) ? 'status ' . (isset($m75['status']) ? $m75['status'] : '?') . ', time ' . (isset($m75['time']) ? gmdate('c', (int) $m75['time']) : '?') : var_export($m75, true)) . "\n";

$show = function ($class, $methods) {
    if (!class_exists($class)) { echo "\n## $class: not loaded\n"; return; }
    foreach ($methods as $m) {
        if (!method_exists($class, $m)) { echo "\n## $class::$m: none\n"; continue; }
        $r = new ReflectionMethod($class, $m);
        $f = $r->getFileName();
        $lines = file($f);
        $rel = substr($f, strpos($f, '/plugins/') !== false ? strpos($f, '/plugins/') + 9 : 0);
        echo "\n## " . $r->getDeclaringClass()->getName() . "::$m  ($rel:" . $r->getStartLine() . ")\n";
        echo implode('', array_slice($lines, $r->getStartLine() - 1, $r->getEndLine() - $r->getStartLine() + 1));
    }
};
$show('Elementor\Core\Files\CSS\Base', array('enqueue', 'update', 'update_file', 'use_external_file', 'is_update_required', 'get_meta', 'update_meta', 'write'));
$show('Elementor\Core\Files\Base', array('update_file', 'write', 'get_content'));
$show('Elementor\Core\Files\CSS\Post', array('update_meta', 'get_meta', 'is_update_required', 'use_external_file'));
$show('Elementor\Element_Base', array('should_render_shortcode', 'print_element'));
echo "done\n";
