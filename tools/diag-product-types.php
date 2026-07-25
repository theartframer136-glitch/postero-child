<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Diagnose why Buy Now / Add to Cart is blocked: count simple vs variable
 * products among those the af-opts pricing engine applies to, and inspect a
 * sample variable product (variations, attributes, default variation,
 * purchasable). Read-only.
 * Run: wp eval-file tools/diag-product-types.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$ids = get_posts(array('post_type'=>'product','post_status'=>'publish','posts_per_page'=>-1,'fields'=>'ids'));
$simple=0; $variable=0; $other=0; $af_variable=array();
foreach ($ids as $pid){
    $p = wc_get_product($pid); if(!$p) continue;
    $applies = function_exists('af_pricing_applies') ? af_pricing_applies($p) : false;
    if ($p->is_type('variable')) { $variable++; if ($applies && count($af_variable)<15) $af_variable[]=$pid; }
    elseif ($p->is_type('simple')) { $simple++; }
    else { $other++; }
}
echo "=== PRODUCT TYPES ===\n";
echo "total: ".count($ids)."  simple: {$simple}  variable: {$variable}  other: {$other}\n";
echo "af-applicable VARIABLE products (these break Buy Now/ATC): ".count($af_variable)." shown, e.g.:\n";
foreach ($af_variable as $pid) echo "  #{$pid}  ".get_the_title($pid)."\n";

// Inspect the sample from the video
$sample = get_page_by_path('vaikuntha-celestial-court-canvas-wall-art', OBJECT, 'product');
$sid = $sample ? $sample->ID : ($af_variable[0] ?? 0);
echo "\n=== SAMPLE variable product #{$sid} ===\n";
if ($sid){
    $p = wc_get_product($sid);
    echo "  title: ".$p->get_name()."\n";
    echo "  type: ".$p->get_type()."  purchasable: ".($p->is_purchasable()?'yes':'no')."  in_stock: ".($p->is_in_stock()?'yes':'no')."\n";
    echo "  attributes: ".implode(', ', array_keys($p->get_attributes()))."\n";
    $children = $p->get_children();
    echo "  variations: ".count($children)."\n";
    $def = $p->get_default_attributes();
    echo "  default_attributes: ".(empty($def)?'(none)':json_encode($def))."\n";
    $reg = $p->get_variation_regular_price('min'); $pr = $p->get_variation_price('min');
    echo "  variation price min: reg={$reg} price={$pr}\n";
    // first variation detail
    if ($children){
        $v = wc_get_product($children[0]);
        if ($v){ echo "  first variation #{$children[0]}: price=".$v->get_price()." attrs=".json_encode($v->get_variation_attributes())."\n"; }
    }
}
echo "\n=== DONE ===\n";
