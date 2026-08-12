<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Who owns .pf-value, and what did its click do before?
 *
 * The collection circles are a.pf-value anchors. Ten days ago clicking one
 * filtered the grid, so some code somewhere binds that class. Find it: grep
 * the parent theme AND every plugin for pf-value / load_products and print the
 * surrounding code, so the original behaviour is restored rather than
 * reinvented. Read-only.
 *
 * Run: wp eval-file tools/diag-pf-handler.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }

echo "=== WHO HANDLES .pf-value ===\n";

$roots = array(
    'parent theme' => get_template_directory(),
    'plugins'      => WP_PLUGIN_DIR,
);

function af_pf_scan($root, $needle, $max_hits = 12) {
    $hits = 0;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($hits >= $max_hits) break;
        $path = $f->getPathname();
        if (!preg_match('/\.(php|js)$/', $path)) continue;
        if (strpos($path, 'postero-child') !== false) continue;   // our own code
        if (strpos($path, '/node_modules/') !== false) continue;
        if ($f->getSize() > 2000000) continue;
        $src = @file_get_contents($path);
        if ($src === false) continue;
        $pos = 0;
        while (($pos = strpos($src, $needle, $pos)) !== false && $hits < $max_hits) {
            $hits++;
            printf("\n  %s @%d\n", str_replace(array(get_template_directory(), WP_PLUGIN_DIR), array('THEME', 'PLUGINS'), $path), $pos);
            echo "  " . trim(preg_replace('/\s+/', ' ',
                 mb_strimwidth(substr($src, max(0, $pos - 400), 1200), 0, 1200))) . "\n";
            $pos += strlen($needle);
        }
    }
    if (!$hits) echo "  (no {$needle} here)\n";
}

foreach ($roots as $label => $root) {
    foreach (array('pf-value', 'load_products') as $needle) {
        echo "\n-- {$needle} in {$label} --\n";
        af_pf_scan($root, $needle);
    }
}
echo "\n=== DONE ===\n";
