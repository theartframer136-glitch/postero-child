<?php
/**
 * The SKU, in the shape the shop asked for: RK-0101-2/3.
 *
 *   RK    the section prefix from the collection book (Radha Krishna)
 *   01    that section's own number, which the book now prints too
 *   01    the page's number inside the section
 *   2/3   the size the customer chose, in feet
 *
 * It was RK-01-2/3 until the book started printing the section number as well.
 * See inc/artcode-book.php: the change is not only longer codes — the book also
 * closed the two gaps its numbering had, so pages after them moved down one.
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
 *   product SKU   RK-0101      what identifies the artwork; unique, one per
 *                              product, shown on the shop and in the admin
 *   line SKU      RK-0101-2/3  what identifies the thing being bought; written
 *                              on the order line, so invoices, packing slips
 *                              and exports carry the full code
 *
 * Where several products share one art code the SKU gains a letter — RK-0101A,
 * RK-0101B — because a SKU must be unique and an art code here often is not. The
 * letter is stored and never moves; see af_sku_for_product() below.
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
 * "RK - 0101" -> "RK-0101", and "RK 01" -> "RK-01". Whitespace collapses and
 * spaces become hyphens; a code that is not a shape named here is passed
 * through with its spaces hyphenated and nothing else assumed about it.
 *
 * Two shapes, because the book has two. It now prints its section's number
 * alongside the page's — RK - 0101 is section 01, page 01 of it — and
 * tools/renumber-artcodes.php puts every product on that numbering. Those four
 * digits are kept whole: they are one identifier, and splitting or padding them
 * would lose the section. The older PREFIX NUMBER shape still parses, because a
 * code naming no page of the book is deliberately left in it and a product may
 * hold one for as long as the audit takes to settle what it should be.
 *
 * Whatever trails the number survives both: the Gold Foiled & UV importer gives
 * its copies the source's code with '-GF' on the end, so HD - 0814-GF is a real
 * code and its SKU is HD-0814-GF.
 */
function af_sku_code_part( $code ) {
	$s = preg_replace( '/\s+/', ' ', trim( (string) $code ) );
	if ( $s === '' ) { return ''; }
	if ( preg_match( '/^([A-Za-z]+)\s*-\s*(\d{4})(.*)$/', $s, $m ) ) {
		return strtoupper( $m[1] ) . '-' . $m[2]
		     . strtoupper( str_replace( ' ', '-', trim( $m[3] ) ) );
	}
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

/**
 * A, B, ... Z, AA, AB, ... for the nth product under a shared art code.
 */
function af_sku_letter_seq( $i ) {
	$i = (int) $i;
	if ( $i < 0 ) { return ''; }
	$s = '';
	do {
		$s = chr( 65 + ( $i % 26 ) ) . $s;
		$i = intdiv( $i, 26 ) - 1;
	} while ( $i >= 0 );
	return $s;
}

/**
 * The product's own SKU: RK-0101, or RK-0101A when several products share the
 * art code RK - 0101. '' when the product carries no code — nothing is invented.
 *
 * ── Why a letter, and why it never moves ────────────────────────────────────
 *
 * A SKU has to be unique; an art code, on this shop, often is not. 56 codes sit
 * on more than one product and they are not sizes of one artwork — TA 04 alone
 * carries 28 unrelated pieces. The code is what the printed book says and is
 * left exactly as it is; the letter is the SKU's own business, added only to
 * tell apart products the code cannot.
 *
 * The letter is STORED, not recomputed. Once #23191 is RK-0101B it stays it
 * even if the product listed before it is deleted, its code corrected, or the
 * catalogue reordered — because that SKU is already on somebody's invoice. It
 * is only reassigned if the product's own art code changes, which makes it a
 * member of a different group; _af_sku_letter_for records which code the letter
 * was issued under so that case can be told from the others.
 */
function af_sku_for_product( $product_id ) {
	$pid  = (int) $product_id;
	$base = af_sku_code_part( get_post_meta( $pid, '_taf_art_code', true ) );
	if ( $base === '' ) { return ''; }
	$letter = (string) get_post_meta( $pid, '_af_sku_letter', true );
	$issued = (string) get_post_meta( $pid, '_af_sku_letter_for', true );
	if ( $letter !== '' && strcasecmp( $issued, $base ) === 0 ) {
		return $base . strtoupper( $letter );
	}
	return $base;
}

/**
 * The full thing: RK-0101-2/3. Falls back to the product part alone when no size
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
