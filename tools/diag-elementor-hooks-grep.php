<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. Where the installed Elementor (GPL, as on wordpress.org) fires
 * the hooks the theme's ports register their scripts and styles on, and
 * where it applies a page's stored asset list (_elementor_page_assets), so
 * the Elementor Pro port's slides script and style load with Pro unloaded
 * (Preview Look, 5 Oct: they did not). Prints matching lines with file:line.
 *
 * Run: wp eval-file tools/diag-elementor-hooks-grep.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
$root = WP_PLUGIN_DIR . '/elementor';
$pat = '/after_register_scripts|after_register_styles|before_register_scripts|before_register_styles|_elementor_page_assets|enable_assets\(|function register_scripts|function register_styles|function enqueue_scripts\(|function enqueue_styles\(/';
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$hits = 0;
foreach ($it as $f) {
    $p = $f->getPathname();
    if (substr($p, -4) !== '.php' || strpos($p, '/vendor') !== false || strpos($p, '/node_modules/') !== false) continue;
    foreach (file($p) as $i => $line) {
        if (preg_match($pat, $line)) {
            $hits++;
            echo substr($p, strlen($root) + 1) . ':' . ($i + 1) . '  ' . trim($line) . "\n";
        }
    }
}
echo "$hits lines\n";
global $wp_filter;
foreach (array('wp_enqueue_scripts') as $h) {
    echo "\n$h callbacks from Elementor:\n";
    foreach ((array) ($wp_filter[$h]->callbacks ?? array()) as $prio => $cbs) foreach ($cbs as $cb) {
        $fn = $cb['function'];
        if (is_array($fn) && is_object($fn[0]) && strpos(get_class($fn[0]), 'Elementor') === 0) echo "  $prio  " . get_class($fn[0]) . '::' . $fn[1] . "\n";
    }
}
echo "done\n";
