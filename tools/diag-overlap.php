<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Diagnose the "What Clients Say" review cards overlapping the next section's
 * heading on the homepage. Dumps the raw HTML around the reviews block and the
 * headings that follow it, plus any inline styles that could cause overlap.
 * Read-only. Run: wp eval-file tools/diag-overlap.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$r = wp_remote_get(home_url('/?afdiag=' . time()), array('timeout'=>30,'sslverify'=>false,'headers'=>array('User-Agent'=>'AF-Diag')));
if (is_wp_error($r)) { echo "fetch failed: " . $r->get_error_message() . "\n"; exit; }
$b = wp_remote_retrieve_body($r);
echo "home bytes: " . strlen($b) . "\n";

$p = stripos($b, 'Clients Say');
echo "'Clients Say' pos: " . var_export($p, true) . "\n";

echo "\n=== plugin fingerprints ===\n";
foreach (array('ti-widget','trustindex','grw-','google-reviews','elfsight','wprev','sk-ww','wp-review-slider','rplg','embedsocial') as $n) {
    $c = substr_count($b, $n);
    if ($c) echo "  '{$n}' x{$c}\n";
}

if ($p !== false) {
    echo "\n=== window A: 2600 chars from 600 before 'Clients Say' (section opener) ===\n";
    echo preg_replace('/\s+/', ' ', substr($b, max(0,$p-600), 2600)) . "\n";

    echo "\n=== headings AFTER the reviews block ===\n";
    $q = $p;
    for ($i = 0; $i < 5; $i++) {
        $h = false;
        foreach (array('<h2','<h1 ','elementor-heading-title') as $tag) {
            $x = stripos($b, $tag, $q + 100);
            if ($x !== false && ($h === false || $x < $h)) $h = $x;
        }
        if ($h === false) break;
        echo "  --- heading#{$i} at {$h} ---\n  " . preg_replace('/\s+/', ' ', substr($b, max(0,$h-300), 800)) . "\n";
        $q = $h;
    }

    echo "\n=== suspicious inline styles in region (p .. p+40000) ===\n";
    $region = substr($b, $p, 40000);
    foreach (array('margin-top:-','margin-top: -','margin-top:\s*-','position:absolute','position: absolute','height:') as $needle) {
        $off = 0; $shown = 0;
        while (($x = stripos($region, str_replace('\\s*','',$needle), $off)) !== false && $shown < 6) {
            echo "  [{$needle}] " . preg_replace('/\s+/', ' ', substr($region, max(0,$x-120), 260)) . "\n";
            $off = $x + 1; $shown++;
        }
    }
} else {
    echo "'Clients Say' not in HTML — dumping heading list instead\n";
    if (preg_match_all('/<h2[^>]*>([\s\S]{0,120}?)<\/h2>/i', $b, $m)) {
        foreach (array_slice($m[1],0,25) as $t) echo "  h2: " . trim(preg_replace('/\s+/',' ', strip_tags($t))) . "\n";
    }
}
echo "\n=== DONE ===\n";
