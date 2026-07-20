<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Dump the exact markup of the first .product-card on the homepage (image area)
 * so we can target the main + gallery image containers precisely.
 * Read-only. Run: wp eval-file tools/diag-newarrivals.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$r = wp_remote_get(home_url('/'), array('timeout'=>25,'sslverify'=>false,'headers'=>array('User-Agent'=>'AF-Diag')));
if (is_wp_error($r)) { echo "fetch failed: ".$r->get_error_message()."\n"; exit; }
$body = wp_remote_retrieve_body($r);
echo "HOME bytes: ".strlen($body)."\n\n";

// Find the first element with class product-card and dump a chunk from it.
$pos = false;
if (preg_match('/<(li|div|article)[^>]*class="[^"]*\bproduct-card\b[^"]*"/i', $body, $m, PREG_OFFSET_CAPTURE)) {
    $pos = $m[0][1];
}
if ($pos === false) { echo "no .product-card element found\n"; }
else {
    $card = substr($body, $pos, 3000);
    // cut at the price so we mainly see the image area + first content
    $card = preg_replace('/\s+/', ' ', $card);
    echo "=== FIRST .product-card markup (first 3000 chars) ===\n";
    echo $card . "\n\n";

    echo "=== <img> tags inside that card ===\n";
    if (preg_match_all('/<img\b[^>]*>/i', $card, $imgs)) {
        foreach (array_slice($imgs[0],0,6) as $t){ echo "  ".preg_replace('/\s+/',' ', $t)."\n"; }
    } else { echo "  (none)\n"; }

    echo "\n=== image WRAPPER classes (a/div/figure just before an <img>) ===\n";
    if (preg_match_all('/<(a|div|figure|span)[^>]*class="([^"]*)"[^>]*>\s*(?:<[^>]+>\s*)?<img/i', $card, $wr)) {
        foreach (array_slice($wr[2],0,8) as $c){ echo "  ".$c."\n"; }
    } else { echo "  (none matched)\n"; }
}
echo "\n=== DONE ===\n";
