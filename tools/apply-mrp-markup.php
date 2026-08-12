<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Give every product a struck-through reference price above what it sells for,
 * so a card reads "$126.98  $80.00  (37% off)" instead of "$80.00  $80.00
 * (0% off)".
 *
 * Each product gets its OWN saving, somewhere in the 20–45% band, rather than
 * one figure repeated down the grid — a whole page showing an identical
 * percentage reads as a pricing rule, not a saving. The percentage comes from
 * af_mrp_discount_pct() in functions.php, which derives it from the product id:
 * varied across the catalogue, but fixed for any one product, so the struck
 * price does not move every time this runs.
 *
 * How: the price the customer pays becomes the SALE price and the marked-up
 * figure becomes the REGULAR price. Doing it in the product's own price fields
 * — rather than in one template — means every renderer agrees: shop cards, the
 * homepage sliders, Trending Today, Quick View, the single product page and the
 * REST API all read the same two numbers.
 *
 * What the customer pays does not change. The sale price is exactly the price
 * that was there before, and the size/frame engine still computes cart totals
 * from the rate card regardless.
 *
 * IDEMPOTENT. Re-running finds the reference price already correct and skips.
 * That also stops the markup from compounding: each run marks up the same sale
 * price, never the already-marked-up regular price.
 *
 * TO REVERT: set $REVERT to true and run again — regular collapses back onto
 * the selling price and the strike-through disappears. To change the spread of
 * savings, edit the band in af_mrp_discount_pct() instead.
 *
 * Run: wp eval-file tools/apply-mrp-markup.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$REVERT = false;   // true = strip the reference prices back off

if ( ! function_exists( 'af_mrp_multiplier' ) ) {
    echo "ABORT: af_mrp_multiplier() is missing — the child theme is not active.\n";
    return;
}

/**
 * The price this product actually sells for. For anything this script has
 * already touched that is the sale price; for an untouched product it is
 * whatever single price is set. Reading the sale price first is what keeps
 * the markup from compounding run after run.
 */
function afm_selling_price( $product ) {
    $sale = $product->get_sale_price( 'edit' );
    if ( $sale !== '' && $sale !== null && (float) $sale > 0 ) return (float) $sale;
    $reg  = $product->get_regular_price( 'edit' );
    if ( $reg !== '' && $reg !== null && (float) $reg > 0 )  return (float) $reg;
    $p    = $product->get_price( 'edit' );
    return ( $p === '' || $p === null ) ? 0.0 : (float) $p;
}

function afm_apply( $product, $REVERT, &$done, &$skip, &$none, &$hist ) {
    $sell = afm_selling_price( $product );
    if ( $sell <= 0 ) { $none++; return; }                 // "Price on request" products

    // A variation inherits its parent's percentage, so every size of one
    // artwork advertises the same saving instead of a different one each.
    $seed = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
    $pct  = af_mrp_discount_pct( $seed );

    $want_regular = $REVERT ? $sell : round( $sell * af_mrp_multiplier( $seed ), 2 );
    $has_regular  = (float) $product->get_regular_price( 'edit' );
    $has_sale     = $product->get_sale_price( 'edit' );
    $has_sale_f   = ( $has_sale === '' || $has_sale === null ) ? 0.0 : (float) $has_sale;
    $want_sale_f  = $REVERT ? 0.0 : $sell;

    // Already exactly as we want it — leave the row alone.
    if ( abs( $has_regular - $want_regular ) < 0.005
         && abs( $has_sale_f - $want_sale_f ) < 0.005 ) {
        $skip++;
        if ( ! $REVERT ) $hist[ $pct ] = ( $hist[ $pct ] ?? 0 ) + 1;
        return;
    }

    if ( $REVERT ) {
        $product->set_regular_price( (string) $sell );
        $product->set_sale_price( '' );
    } else {
        $product->set_regular_price( (string) $want_regular );
        $product->set_sale_price( (string) $sell );
        $hist[ $pct ] = ( $hist[ $pct ] ?? 0 ) + 1;
    }
    $product->save();
    $done++;
    echo sprintf( "  #%d  %-52s  %.2f -> regular %.2f  (%d%% off)\n",
        $product->get_id(), substr( wp_strip_all_tags( $product->get_name() ), 0, 52 ),
        $sell, $want_regular, $REVERT ? 0 : $pct );
}

echo '=== MRP MARKUP (' . ( $REVERT ? 'REVERT' : 'per-product 20-45% off' ) . ") ===\n";

$ids = get_posts( array(
    'post_type'      => array( 'product', 'product_variation' ),
    'post_status'    => array( 'publish', 'private' ),
    'posts_per_page' => -1,
    'fields'         => 'ids',
) );

$done = $skip = $none = $bad = 0;
$hist = array();
foreach ( $ids as $pid ) {
    $product = wc_get_product( $pid );
    if ( ! $product ) { $bad++; continue; }
    // A variable parent has no price of its own — its variations carry them,
    // and those are in this list already.
    if ( $product->is_type( 'variable' ) ) { $skip++; continue; }
    afm_apply( $product, $REVERT, $done, $skip, $none, $hist );
}

// Variable parents cache their price range; rebuild it so the strike-through
// shows on their cards too.
foreach ( $ids as $pid ) {
    $product = wc_get_product( $pid );
    if ( $product && $product->is_type( 'variable' ) ) {
        WC_Product_Variable::sync( $product->get_id() );
    }
}
wc_delete_product_transients();

// How the savings actually landed across the catalogue — the point of the
// change is that this is a spread, so print it rather than assume it.
if ( $hist ) {
    ksort( $hist );
    $line = array();
    foreach ( $hist as $p => $n ) $line[] = "{$p}%:{$n}";
    echo "spread  " . implode( '  ', $line ) . "\n";
}

echo "=== DONE  updated={$done}  unchanged={$skip}  no-price={$none}  unreadable={$bad} ===\n";
