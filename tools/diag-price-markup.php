<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Dump the price markup of homepage product cards so we can see how the sale
 * price / original price / discount are structured and add a strikethrough to
 * the original price. Read-only.
 * Run: wp eval-file tools/diag-price-markup.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$r = wp_remote_get(home_url('/?afpm='.time()), array('timeout'=>35,'sslverify'=>false,'headers'=>array('User-Agent'=>'AF-Diag')));
if (is_wp_error($r)) { echo "fetch failed: ".$r->get_error_message()."\n"; exit; }
$b = wp_remote_retrieve_body($r);
echo "home bytes: ".strlen($b)."\n";

// Find price containers and dump their markup
if (preg_match_all('/<[^>]*class="[^"]*\bprice\b[^"]*"[\s\S]{0,320}?<\/(?:span|div|p)>/i', $b, $m)) {
    echo "found ".count($m[0])." .price blocks; first 5:\n";
    foreach (array_slice($m[0],0,5) as $i=>$blk){
        echo "  --- price#{$i} ---\n  ".preg_replace('/\s+/',' ', $blk)."\n";
    }
} else {
    echo "no .price block matched\n";
}

echo "\n=== windows around '% off' / 'off)' ===\n";
$needle = '% off';
$off=0; $n=0;
while (($x = stripos($b, $needle, $off)) !== false && $n < 5){
    echo "  ".preg_replace('/\s+/',' ', substr($b, max(0,$x-260), 360))."\n---\n";
    $off = $x + 1; $n++;
}
if (!$n) echo "  '% off' not found in server HTML (client-rendered)\n";

echo "\n=== del / ins / old-price presence ===\n";
foreach (array('<del','<ins','old-price','regular-price','sale-price','discount') as $t){
    echo "  '{$t}' x".substr_count($b, $t)."\n";
}
echo "\n=== DONE ===\n";
