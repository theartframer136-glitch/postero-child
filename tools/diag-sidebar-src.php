<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Find the source of the sidebar "Frame Colors / Frame Sizes / Frame Types"
 * filter block and dump the rendered HTML region (with classes) so we can see
 * why Rose Gold / frame types are missing. Read-only.
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$needles = array('Frame Colors','Frame Sizes','Frame Types','Frame Color','frame_color','Rose Gold','pa_colors','pa_frame');
foreach (array('theme'=>get_template_directory(),'child'=>get_stylesheet_directory()) as $lbl=>$dir) {
    echo "=== {$lbl}: {$dir} ===\n";
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($rii as $f) {
        if (!in_array($f->getExtension(), array('php'))) continue;
        $lines = @file($f->getPathname()); if (!$lines) continue;
        foreach ($lines as $i=>$ln) {
            foreach ($needles as $n) {
                if (strpos($ln, $n) !== false) { echo "  ".str_replace($dir.'/','',$f->getPathname()).":".($i+1)."  ".trim(substr($ln,0,150))."\n"; break; }
            }
        }
    }
}

echo "\n=== Rendered category HTML around 'Frame Colors' (2200 chars) ===\n";
$r = wp_remote_get(home_url('/product-category/digital-canvas-prints/radha-krishna/'), array('timeout'=>25,'sslverify'=>false,'headers'=>array('User-Agent'=>'AF-Diag')));
if (!is_wp_error($r)) {
    $b = wp_remote_retrieve_body($r);
    $p = stripos($b, 'Frame Colors');
    if ($p === false) $p = stripos($b, 'Frame Color');
    if ($p !== false) {
        $seg = substr($b, max(0,$p-200), 2200);
        $seg = preg_replace('/\s+/', ' ', $seg);
        echo $seg . "\n";
    } else echo "'Frame Color(s)' not found in HTML\n";
}
echo "\n=== DONE ===\n";
