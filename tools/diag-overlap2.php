<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Root-cause dump for the reviews-overlap: print every height/position rule in
 * the Embedder-for-Google-Reviews plugin CSS that targets the widget, plus any
 * fixed heights / negative margins in the front page's Elementor CSS.
 * Read-only. Run: wp eval-file tools/diag-overlap2.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$pdir = WP_PLUGIN_DIR . '/embedder-for-google-reviews';
echo "=== plugin dir: {$pdir} ===\n";
if (is_dir($pdir)) {
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pdir, FilesystemIterator::SKIP_DOTS));
    foreach ($rii as $f) {
        $ext = $f->getExtension();
        if (!in_array($ext, array('css','js'))) continue;
        $rel = str_replace($pdir . '/', '', $f->getPathname());
        $src = @file_get_contents($f->getPathname());
        if ($src === false) continue;
        if ($ext === 'css') {
            // whole CSS rules whose selector or body mentions the widget AND height/position/margin
            if (preg_match_all('/[^{}]{1,300}\{[^{}]*\}/', $src, $rules)) {
                foreach ($rules[0] as $rule) {
                    if (!preg_match('/g-review|grwp|layout_style/i', $rule)) continue;
                    if (!preg_match('/height|position|margin|overflow/i', $rule)) continue;
                    echo "  [css {$rel}] " . preg_replace('/\s+/', ' ', trim($rule)) . "\n";
                }
            }
        } else {
            // JS lines that set heights
            foreach (preg_split('/[\n;]/', $src) as $ln) {
                if (preg_match('/(height|outerHeight|css\(|style)/i', $ln) && preg_match('/height/i', $ln) && strlen(trim($ln)) < 220) {
                    echo "  [js  {$rel}] " . trim($ln) . "\n";
                }
            }
        }
    }
} else {
    echo "  plugin dir missing — listing plugins:\n";
    foreach (glob(WP_PLUGIN_DIR . '/*', GLOB_ONLYDIR) as $d) echo "    - " . basename($d) . "\n";
}

echo "\n=== Elementor front-page CSS: fixed heights / negative margins near reviews + Corporate sections ===\n";
$fp = (int) get_option('page_on_front');
$css_file = WP_CONTENT_DIR . "/uploads/elementor/css/post-{$fp}.css";
echo "front page id={$fp}, css=" . (file_exists($css_file) ? 'found' : 'MISSING') . "\n";
if (file_exists($css_file)) {
    $css = file_get_contents($css_file);
    foreach (array('2596335','268b6b3','4450f00','15657f8','1396cc1') as $id) {
        $off = 0;
        while (($x = strpos($css, $id, $off)) !== false) {
            $start = strrpos(substr($css, 0, $x), '}');
            $end = strpos($css, '}', $x);
            if ($end !== false) echo "  [{$id}] " . preg_replace('/\s+/', ' ', substr($css, $start === false ? 0 : $start + 1, $end - ($start === false ? 0 : $start))) . "\n";
            $off = $x + strlen($id);
        }
    }
    $off = 0; $n = 0;
    while (($x = strpos($css, 'margin-top:-', $off)) !== false && $n < 10) {
        $start = strrpos(substr($css, 0, $x), '}');
        echo "  [neg-margin] " . preg_replace('/\s+/', ' ', substr($css, $start === false ? 0 : $start + 1, 220)) . "\n";
        $off = $x + 1; $n++;
    }
    if (!$n) echo "  (no negative margin-top in post CSS)\n";
}
echo "\n=== DONE ===\n";
