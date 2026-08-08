<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Fix checkout: convert af-pricing-applicable products that still carry
 * WooCommerce variations to SIMPLE so Add-to-Cart / Buy Now work (the custom
 * size/frame/color engine drives options + price; the leftover variations only
 * block the cart with a required variation_id).
 *
 * Robust + idempotent + self-healing:
 *   - Reads the price range straight from the variation CHILD posts (they are
 *     kept, not deleted), so it works even after a prior run already flipped
 *     the type term (avoids WooCommerce's cached product-object type check).
 *   - Sets the product_type term to 'simple' AND writes _regular_price /
 *     _sale_price / _price meta directly. Price preserved: sale = current base
 *     (min child price), regular = MRP (max child price, or base*1.25 if no
 *     range) so the struck price + % discount stay identical.
 *   - Skips products already simple with a valid price. Excluded categories
 *     (accessories/banners/gift cards) untouched. Variations are NOT deleted.
 * Run: wp eval-file tools/convert-variable-to-simple.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }
if ( ! function_exists('af_pricing_applies') ) { echo "af_pricing_applies() missing\n"; exit(1); }

$ids = get_posts(array('post_type'=>'product','post_status'=>array('publish','draft','pending','private'),'posts_per_page'=>-1,'fields'=>'ids'));
$fixed=0; $skipped=0; $noprice=0; $shown=0;

foreach ($ids as $pid) {
    // Which type is this right now (read term directly, not the cached object)?
    $types = wp_get_object_terms($pid, 'product_type', array('fields'=>'names'));
    if (is_wp_error($types)) $types = array();
    $is_variable = in_array('variable', $types, true);

    // Collect variation child prices (they persist regardless of parent type).
    $children = get_posts(array('post_type'=>'product_variation','post_parent'=>$pid,'numberposts'=>-1,'fields'=>'ids','post_status'=>'any'));
    $has_children = !empty($children);

    // Only touch products that are STILL variable. This used to also grab
    // already-simple products with orphaned variation children so a past
    // migration could repair them — but the orphan children keep their old
    // $179/$348 prices forever, so on every full deploy this quietly reset
    // ~280 card-priced listings back to the old regime and left the
    // reprice-from-card step to fix them again. The migration is long done;
    // an already-simple product's price belongs to the rate card now.
    if (!$is_variable) { continue; }

    // Respect the pricing-applies exclusions (accessories/banners/gift cards).
    $prod = wc_get_product($pid);
    if ($prod && !af_pricing_applies($prod)) { $skipped++; continue; }

    // Compute base (min) and MRP (max) from the child prices.
    $sale = array(); $reg = array();
    foreach ($children as $c) {
        $sp = get_post_meta($c, '_price', true);
        $rp = get_post_meta($c, '_regular_price', true);
        if ($sp !== '' && $sp !== null) $sale[] = (float) $sp;
        if ($rp !== '' && $rp !== null) $reg[]  = (float) $rp;
    }
    $pool = !empty($sale) ? $sale : $reg;
    if (empty($pool)) {
        // fall back to any existing product-level price
        $ex = (float) get_post_meta($pid, '_price', true);
        if ($ex > 0) { $pool = array($ex); }
    }
    if (empty($pool)) { $noprice++; echo "NOPRICE #{$pid}\n"; continue; }

    $base = min($pool);
    $max  = max($pool);
    $mrp  = ($max > $base) ? $max : round($base * 1.25, 2);
    if ($base <= 0) { $noprice++; echo "NOPRICE #{$pid}\n"; continue; }

    // Flip type → simple (idempotent).
    wp_set_object_terms($pid, 'simple', 'product_type');

    // Write pricing meta directly.
    update_post_meta($pid, '_regular_price', wc_format_decimal($mrp > $base ? $mrp : $base));
    if ($mrp > $base) update_post_meta($pid, '_sale_price', wc_format_decimal($base));
    else              delete_post_meta($pid, '_sale_price');
    update_post_meta($pid, '_price', wc_format_decimal($base));   // active price
    update_post_meta($pid, '_manage_stock', 'no');
    update_post_meta($pid, '_stock_status', 'instock');

    clean_post_cache($pid);
    if (function_exists('wc_delete_product_transients')) wc_delete_product_transients($pid);

    $fixed++;
    if ($shown < 20) { echo "OK #{$pid}  base={$base} mrp={$mrp}  ".get_the_title($pid)."\n"; $shown++; }
}

echo "\n=== CONVERT VARIABLE → SIMPLE (robust) ===\n";
echo "fixed: {$fixed}  skipped(excluded): {$skipped}  no-price: {$noprice}\n";
if (function_exists('wc_delete_product_transients')) wc_delete_product_transients();

// Verify a few products by reading term + price straight from the DB.
echo "\n=== VERIFY (direct read) ===\n";
$sample_ids = array();
$vk = get_page_by_path('vaikuntha-celestial-court-canvas-wall-art', OBJECT, 'product');
if ($vk) $sample_ids[] = $vk->ID;
$more = get_posts(array('post_type'=>'product','post_status'=>'publish','posts_per_page'=>4,'fields'=>'ids'));
$sample_ids = array_slice(array_unique(array_merge($sample_ids, $more)), 0, 5);
foreach ($sample_ids as $pid) {
    $t = wp_get_object_terms($pid, 'product_type', array('fields'=>'names'));
    $reg = get_post_meta($pid, '_regular_price', true);
    $sale= get_post_meta($pid, '_sale_price', true);
    $pr  = get_post_meta($pid, '_price', true);
    echo "  #{$pid}  type=".(is_wp_error($t)?'?':implode(',', $t))."  reg={$reg} sale={$sale} price={$pr}\n";
}
echo "=== DONE ===\n";
