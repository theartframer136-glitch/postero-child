<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Fix checkout: convert af-pricing-applicable VARIABLE products to SIMPLE so
 * Add-to-Cart and Buy Now work (the custom size/frame/color engine already
 * drives options + price; the leftover WooCommerce variations only block the
 * cart with a required variation_id).
 *
 * Safe & reversible:
 *   - price preserved: sale_price = current base (variation min), regular_price
 *     = MRP (variation max, or base*1.25 if no range) so the crossed-out price
 *     and % discount stay identical.
 *   - the product's variations are NOT deleted — they stay as orphaned child
 *     posts, so the change can be reverted by switching the type back.
 *   - product attributes (pa_size / pa_colors / pa_frame used by shop filters)
 *     are untouched.
 * Idempotent. Run: wp eval-file tools/convert-variable-to-simple.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }
if ( ! function_exists('af_pricing_applies') ) { echo "af_pricing_applies() missing — theme not loaded\n"; exit(1); }

$ids = get_posts(array('post_type'=>'product','post_status'=>array('publish','draft','pending','private'),'posts_per_page'=>-1,'fields'=>'ids'));
$converted = 0; $skipped = 0; $failed = 0;

foreach ($ids as $pid) {
    $p = wc_get_product($pid);
    if (!$p) { continue; }
    if (!$p->is_type('variable')) { continue; }        // only variable
    if (!af_pricing_applies($p))   { $skipped++; continue; } // skip accessories/banners/gift cards

    // Capture the current RAW price range BEFORE changing type (no display tax).
    $min = (float) $p->get_variation_price('min');            // current (incl. sale) low
    $max = (float) $p->get_variation_price('max');            // current high
    $reg_min = (float) $p->get_variation_regular_price('min');
    if ($min <= 0) $min = $reg_min > 0 ? $reg_min : 0;
    $base = $min > 0 ? $min : 0;                               // af-opts base
    // MRP = the top of the range if it's higher than the base; else a +25% ref
    $mrp  = ($max > $base) ? $max : round($base * 1.25, 2);

    // Flip the type to simple.
    wp_set_object_terms($pid, 'simple', 'product_type');
    clean_post_cache($pid);
    $s = wc_get_product($pid);                                // reload as simple
    if (!$s || !$s->is_type('simple')) { $failed++; echo "FAIL #{$pid}\n"; continue; }

    // Preserve pricing: regular = MRP, sale = base (so struck MRP + % stay).
    if ($base > 0) {
        $s->set_regular_price( wc_format_decimal($mrp > $base ? $mrp : $base) );
        if ($mrp > $base) $s->set_sale_price( wc_format_decimal($base) );
        else              $s->set_sale_price('');
        $s->set_manage_stock(false);
        $s->set_stock_status('instock');
        $s->save();
    }
    $converted++;
    if ($converted <= 20) echo "OK #{$pid}  base={$base} mrp={$mrp}  ".get_the_title($pid)."\n";
}

echo "\n=== CONVERT VARIABLE → SIMPLE ===\n";
echo "converted: {$converted}  skipped(excluded cats): {$skipped}  failed: {$failed}\n";
if (function_exists('wc_delete_product_transients')) wc_delete_product_transients();
echo "=== DONE ===\n";
