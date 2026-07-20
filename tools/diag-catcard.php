<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Dump one category-archive product card's markup (swatch rows) and the
 * sidebar Categories widget markup, so we can target the duplicate swatch
 * rows and trim the sidebar precisely. Read-only.
 * Run: wp eval-file tools/diag-catcard.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$url = home_url('/product-category/digital-canvas-prints/lakshmi-ganesha/');
$r = wp_remote_get($url, array('timeout'=>25,'sslverify'=>false,'headers'=>array('User-Agent'=>'AF-Diag')));
if (is_wp_error($r)) { echo "fetch failed\n"; exit; }
$body = wp_remote_retrieve_body($r);
echo "bytes: ".strlen($body)."\n\n";

// first li.product card
if (preg_match('/<li[^>]*class="[^"]*\bproduct\b[^"]*"[\s\S]*?<\/li>/i', $body, $m)) {
    $card = preg_replace('/\s+/',' ', $m[0]);
    echo "=== FIRST li.product (first 2600 chars) ===\n".substr($card,0,2600)."\n\n";
} else { echo "no li.product found\n"; }

// swatch/variation rows — look for the repeated "sizes" / "frames" text block
if (preg_match_all('/<[^>]*class="([^"]*)"[^>]*>[^<]*(sizes|frames|From)/i', $body, $sw)) {
    echo "=== classes near 'sizes/frames/From' (first 12) ===\n";
    foreach (array_slice(array_unique($sw[1]),0,12) as $c) echo "  ".$c."\n";
    echo "\n";
}

// sidebar Categories widget
if (preg_match('/<(aside|div|section)[^>]*class="[^"]*widget_product_categories[^"]*"[\s\S]*?<\/(aside|div|section)>/i', $body, $wm)) {
    echo "=== Categories widget wrapper class ===\n";
    if (preg_match('/class="([^"]*widget_product_categories[^"]*)"/i',$wm[0],$c)) echo "  ".$c[1]."\n";
    // count list items
    echo "  <li> count: ".substr_count($wm[0],'<li')."\n";
} else { echo "product categories widget not matched\n"; }
echo "\n=== DONE ===\n";
