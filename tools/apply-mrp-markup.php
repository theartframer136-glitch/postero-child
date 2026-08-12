<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Give every product a struck-through reference price 40% above what it sells
 * for, so the card reads "$112.00  $80.00  (29% off)" instead of the current
 * "$80.00  $80.00  (0% off)".
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
 * IDEMPOTENT. Re-running finds regular == round(sale * 1.4) and skips. That
 * also stops the markup from compounding: the second run marks up the same
 * sale price, never the already-marked-up regular price.
 *
 * TO REVERT: set AF_MRP_MULT to 1.0 and run again — regular collapses back
 * onto the selling price and the strike-through disappears.
 *
 * Run: wp eval-file tools/apply-mrp-markup.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$MULT = 1.40;   // reference price = selling price + 40%

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

function afm_apply( $product, $MULT, &$done, &$skip, &$none ) {
    $sell = afm_selling_price( $product );
    if ( $sell <= 0 ) { $none++; return; }                 // "Price on request" products

    $want_regular = round( $sell * $MULT, 2 );
    $has_regular  = (float) $product->get_regular_price( 'edit' );
    $has_sale     = $product->get_sale_price( 'edit' );
    $has_sale_f   = ( $has_sale === '' || $has_sale === null ) ? 0.0 : (float) $has_sale;

    // Already exactly as we want it — leave the row alone.
    if ( abs( $has_regular - $want_regular ) < 0.005
         && abs( $has_sale_f - $sell ) < 0.005 ) { $skip++; return; }

    if ( $MULT <= 1.0 ) {
        // revert: one price, no strike-through
        $product->set_regular_price( (string) $sell );
        $product->set_sale_price( '' );
    } else {
        $product->set_regular_price( (string) $want_regular );
        $product->set_sale_price( (string) $sell );
    }
    $product->save();
    $done++;
    echo sprintf( "  #%d  %-58s  %.2f -> regular %.2f / sale %.2f\n",
        $product->get_id(), substr( wp_strip_all_tags( $product->get_name() ), 0, 58 ),
        $sell, $want_regular, $sell );
}

echo "=== MRP MARKUP (x{$MULT}) ===\n";

$ids = get_posts( array(
    'post_type'      => array( 'product', 'product_variation' ),
    'post_status'    => array( 'publish', 'private' ),
    'posts_per_page' => -1,
    'fields'         => 'ids',
) );

$done = $skip = $none = $bad = 0;
foreach ( $ids as $pid ) {
    $product = wc_get_product( $pid );
    if ( ! $product ) { $bad++; continue; }
    // A variable parent has no price of its own — its variations carry them,
    // and those are in this list already.
    if ( $product->is_type( 'variable' ) ) { $skip++; continue; }
    afm_apply( $product, $MULT, $done, $skip, $none );
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

echo "=== DONE  updated={$done}  unchanged={$skip}  no-price={$none}  unreadable={$bad} ===\n";
