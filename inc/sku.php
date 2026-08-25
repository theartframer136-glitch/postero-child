<?php
/**
 * The SKU, in the shape the shop asked for: RK-01-2/3.
 *
 *   RK    the section prefix from the collection book (Radha Krishna)
 *   01    the art code number inside that section
 *   2/3   the size the customer chose, in feet
 *
 * ── Why this is two SKUs and not one ────────────────────────────────────────
 *
 * A WooCommerce product has ONE sku field. These products are simple products
 * with a size selector, not variable products with a variation per size — the
 * size travels as cart item meta (af_size) and lands on the order line. One
 * artwork is sold in fifteen sizes, so a size cannot live in the product's own
 * sku without picking one of the fifteen and being wrong about the other
 * fourteen.
 *
 * So the size joins the SKU at the point where a size actually exists:
 *
 *   product SKU   RK-01        what identifies the artwork; unique, one per
 *                              product, shown on the shop and in the admin
 *   line SKU      RK-01-2/3    what identifies the thing being bought; written
 *                              on the order line, so invoices, packing slips
 *                              and exports carry the full code
 *
 * ── The size token ──────────────────────────────────────────────────────────
 *
 * Sizes are labelled '2×3 ft (24×36 in)' in the rate card. The token is the
 * two leading numbers joined with a slash: 2/3. Orientation is preserved,
 * because the card lists 2×3 and 3×2 as different sizes and they are — one is
 * portrait, one landscape — so they get 2/3 and 3/2 and never collapse
 * together.
 *
 * ── A caveat worth stating ──────────────────────────────────────────────────
 *
 * A SKU built from the art code is only as right as the art code. That audit
 * is still running: some products carry a code that belongs to another
 * artwork, and some carry none. This does not make matters worse — the SKU is
 * already the art code today — but a wrong code now travels onto invoices in a
 * tidier format, not a truer one. Products with no code keep whatever SKU they
 * have; nothing is invented for them.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * "RK 01" -> "RK-01". Whitespace collapses, spaces become hyphens, and the
 * number is padded to two digits so RK-1 and RK-01 cannot both exist for the
 * same piece. A code that is not the usual PREFIX NUMBER shape is passed
 * through with its spaces hyphenated and nothing else assumed about it.
 */
function af_sku_code_part( $code ) {
	$s = preg_replace( '/\s+/', ' ', trim( (string) $code ) );
	if ( $s === '' ) { return ''; }
	if ( preg_match( '/^([A-Za-z]+)\s*0*(\d+)$/', $s, $m ) ) {
		return strtoupper( $m[1] ) . '-' . str_pad( $m[2], 2, '0', STR_PAD_LEFT );
	}
	return strtoupper( str_replace( ' ', '-', $s ) );
}

/**
 * "2×3 ft (24×36 in)" -> "2/3". Accepts the × the rate card uses and a plain
 * x, and keeps decimals, so "2.5×4 ft" gives "2.5/4". Anything that does not
 * start with two numbers gives '' and the size is simply left off rather than
 * guessed at.
 */
function af_sku_size_part( $size_label ) {
	$s = trim( (string) $size_label );
	if ( $s === '' ) { return ''; }
	if ( preg_match( '/^\s*(\d+(?:\.\d+)?)\s*[×xX]\s*(\d+(?:\.\d+)?)/u', $s, $m ) ) {
		return $m[1] . '/' . $m[2];
	}
	return '';
}

/** The product's own SKU shape: RK-01, or '' when it carries no art code. */
function af_sku_for_product( $product_id ) {
	$code = get_post_meta( (int) $product_id, '_taf_art_code', true );
	return af_sku_code_part( $code );
}

/**
 * The full thing: RK-01-2/3. Falls back to the product part alone when no size
 * is known, so a line without a size still carries a usable code rather than a
 * trailing hyphen.
 */
function af_sku_full( $product_id, $size_label = '' ) {
	$base = af_sku_for_product( $product_id );
	if ( $base === '' ) { return ''; }
	$size = af_sku_size_part( $size_label );
	return $size === '' ? $base : $base . '-' . $size;
}

// ── The line SKU, where a size actually exists ──────────────────────────────

/** Show it in the cart and at checkout, next to Size / Frame / Colour. */
add_filter( 'woocommerce_get_item_data', function ( $data, $item ) {
	$pid = 0;
	if ( ! empty( $item['product_id'] ) ) { $pid = (int) $item['product_id']; }
	$sku = af_sku_full( $pid, isset( $item['af_size'] ) ? $item['af_size'] : '' );
	if ( $sku !== '' ) {
		$data[] = array( 'name' => 'SKU', 'value' => $sku );
	}
	return $data;
}, 11, 2 );

/**
 * Write it onto the order line. Priority 11 so it lands after the Size meta
 * added at 10 — the value does not depend on that ordering, but the line reads
 * better with Size above the SKU it was built from.
 */
add_action( 'woocommerce_checkout_create_order_line_item', function ( $item, $key, $values ) {
	$pid = 0;
	if ( is_callable( array( $item, 'get_product_id' ) ) ) { $pid = (int) $item->get_product_id(); }
	if ( ! $pid && ! empty( $values['product_id'] ) ) { $pid = (int) $values['product_id']; }
	$sku = af_sku_full( $pid, isset( $values['af_size'] ) ? $values['af_size'] : '' );
	if ( $sku !== '' ) {
		$item->add_meta_data( 'SKU', $sku );
	}
}, 11, 3 );
