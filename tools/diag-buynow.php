<?php
/**
 * Diagnostic: list simple vs variable products + sample permalinks,
 * and confirm the Buy Now hook conditions. Read-only.
 * Run: wp eval-file tools/diag-buynow.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }
if ( ! function_exists( 'wc_get_product' ) ) { echo "WooCommerce not active\n"; exit(1); }

$ids = get_posts(array('post_type'=>'product','post_status'=>'publish','posts_per_page'=>-1,'fields'=>'ids'));
$simple=0; $variable=0; $other=0; $simple_links=array();

foreach ($ids as $pid) {
    $p = wc_get_product($pid); if(!$p) continue;
    if ($p->is_type('simple')) {
        $simple++;
        if (count($simple_links) < 5 && $p->is_purchasable() && $p->is_in_stock() && $p->get_image_id()) {
            $simple_links[] = get_permalink($pid);
        }
    } elseif ($p->is_type('variable')) { $variable++; }
    else { $other++; }
}

echo "=== BUY NOW DIAGNOSTIC ===\n\n";
echo "Simple products (SHOW Buy Now)   : {$simple}\n";
echo "Variable products (NO Buy Now)   : {$variable}\n";
echo "Other types                      : {$other}\n\n";

// Does the hook have our callbacks?
global $wp_filter;
$has = isset($wp_filter['woocommerce_after_add_to_cart_button']);
echo "Hook 'woocommerce_after_add_to_cart_button' registered: " . ($has?'YES':'no') . "\n\n";

echo "Sample SIMPLE product links (Buy Now should appear here):\n";
foreach ($simple_links as $l) echo "  " . $l . "\n";

echo "\n=== DONE ===\n";
