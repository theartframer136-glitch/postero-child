<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Make every product's SKU its art code.
 *
 * The shop was showing machine-made SKUs — TAF-CRUCIFIXION-SCENE-053-5503711 —
 * next to a hand-assigned art code from the printed collection book (RK 01).
 * The art code is the one a person actually uses to find the piece, so it
 * becomes the SKU: on the page, in the admin, on invoices and in exports.
 *
 * Two rules do the work:
 *
 *   1. A SKU must be UNIQUE in WooCommerce. Several products can legitimately
 *      share one art code (the same artwork sold in several sizes), so the
 *      first claimant takes the bare code and the rest get their size appended
 *      — RK-01-3X4FT reads correctly on an invoice, which -2 does not. Only
 *      when no size can be read does it fall back to a number.
 *   2. Nothing is destroyed. The SKU a product had before is kept in
 *      _af_sku_before_artcode, so tools/restore-sku-from-backup.php can put
 *      every one of them back exactly as it was.
 *
 * Resumable: each product is marked done, so a run cut short by the host's
 * time limit continues on the next deploy instead of starting over.
 *
 * Run: wp eval-file tools/sku-to-artcode.php --allow-root
 * Env: AF_SKU_SECONDS (default 200) — time budget for one run
 *      AF_SKU_MAX     (default 800) — product cap for one run
 *      AF_SKU_DRYRUN=1              — report what would change, write nothing
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$VERSION = 'artcode-sku-v1';
$SECONDS = (int) ( getenv( 'AF_SKU_SECONDS' ) ?: 200 );
$MAX     = (int) ( getenv( 'AF_SKU_MAX' )     ?: 800 );
$DRY     = (bool) getenv( 'AF_SKU_DRYRUN' );

$started = microtime( true );

echo "=== SKU → ART CODE ===\n";
echo "target: {$VERSION}  |  budget: {$SECONDS}s or {$MAX} products per run"
   . ( $DRY ? '  |  DRY RUN — nothing is written' : '' ) . "\n";

/**
 * An art code as it is written in the book ("RK 01") turned into the form a
 * SKU should take: upper case, single hyphens, nothing exotic. Anything that
 * is not a letter, a digit or a separator is dropped rather than escaped,
 * because a SKU ends up in URLs, CSV exports and printed paperwork.
 */
function af_sku_from_code( $code ) {
	$s = strtoupper( trim( (string) $code ) );
	$s = preg_replace( '/[^A-Z0-9]+/', '-', $s );
	return trim( (string) $s, '-' );
}

/**
 * A short, readable token for the product's size, used only to tell apart two
 * products that share an art code. "3x4 Feet" becomes 3X4FT. Returns '' when
 * the product has no size worth printing.
 */
function af_sku_size_token( $pid ) {
	$candidates = array();

	$product = function_exists( 'wc_get_product' ) ? wc_get_product( $pid ) : null;
	if ( $product ) {
		foreach ( array( 'pa_size', 'size', 'pa_dimensions' ) as $key ) {
			$v = $product->get_attribute( $key );
			if ( $v ) { $candidates[] = $v; }
		}
	}
	// Titles on this shop carry the size when the attribute does not:
	// "… Canvas Wall Art 3x4 Feet – Floating Frame …"
	$title = html_entity_decode( wp_strip_all_tags( get_the_title( $pid ) ) );
	if ( preg_match( '/(\d+(?:\.\d+)?)\s*(?:x|×)\s*(\d+(?:\.\d+)?)\s*(feet|foot|ft|inch|inches|in|cm|mm)?/i', $title, $m ) ) {
		$candidates[] = $m[0];
	}

	foreach ( $candidates as $raw ) {
		$raw = strtolower( (string) $raw );
		$raw = str_replace( array( '×', '"', "'" ), array( 'x', '', '' ), $raw );
		if ( ! preg_match( '/(\d+(?:\.\d+)?)\s*x\s*(\d+(?:\.\d+)?)\s*([a-z]*)/', $raw, $m ) ) { continue; }
		$unit = '';
		if ( preg_match( '/^(feet|foot|ft)/', $m[3] ) )              { $unit = 'FT'; }
		elseif ( preg_match( '/^(inch|inches|in)/', $m[3] ) )        { $unit = 'IN'; }
		elseif ( preg_match( '/^(cm|centimet)/', $m[3] ) )           { $unit = 'CM'; }
		elseif ( preg_match( '/^(mm|millimet)/', $m[3] ) )           { $unit = 'MM'; }
		// Trailing zeros only mean nothing AFTER a decimal point: 3.50 is 3.5,
		// but 60 is not 6. Getting this wrong turned "60 x 90 cm" into 6X9CM.
		$w = af_sku_trim_number( $m[1] );
		$h = af_sku_trim_number( $m[2] );
		if ( $w === '' || $h === '' ) { continue; }
		// A dot survives here where af_sku_from_code would drop it, because
		// 3.5X4.5FT is a size a person can read and 3-5X4-5FT is not.
		return strtoupper( $w . 'X' . $h . $unit );
	}
	return '';
}

function af_sku_trim_number( $n ) {
	$n = (string) $n;
	if ( strpos( $n, '.' ) !== false ) {
		$n = rtrim( rtrim( $n, '0' ), '.' );
	}
	return $n;
}

// ── Every published product, and the art code it carries ────────────────────
$ids = get_posts( array(
	'post_type'      => 'product',
	'post_status'    => array( 'publish', 'private', 'draft', 'pending' ),
	'posts_per_page' => -1,
	'fields'         => 'ids',
	'orderby'        => 'ID',
	'order'          => 'ASC',
) );

$codes   = array();   // pid => art code as written
$by_sku  = array();   // base sku => ordered list of pids that want it
$nocode  = array();

foreach ( $ids as $pid ) {
	$code = get_post_meta( $pid, '_taf_art_code', true );
	$code = is_string( $code ) ? trim( $code ) : '';
	if ( $code === '' ) { $nocode[] = $pid; continue; }
	$base = af_sku_from_code( $code );
	if ( $base === '' ) { $nocode[] = $pid; continue; }
	$codes[ $pid ] = $code;
	$by_sku[ $base ][] = $pid;
}

echo "products: " . count( $ids ) . "\n";
echo "  with an art code:    " . count( $codes ) . "\n";
echo "  WITHOUT an art code: " . count( $nocode ) . "  (SKU left exactly as it is)\n";

// ── Decide the SKU for every product before writing anything ────────────────
// Done in one pass so a resumed run assigns the same SKU it would have on the
// first run: the order is by product id, not by whatever happens to be left.
$want      = array();   // pid => sku
$shared    = array();   // base => pids, for the report
$fellback  = array();   // pid => sku, where a number had to be used

foreach ( $by_sku as $base => $pids ) {
	if ( count( $pids ) === 1 ) {
		$want[ $pids[0] ] = $base;
		continue;
	}
	$shared[ $base ] = $pids;
	$taken = array( $base => true );
	$first = true;
	foreach ( $pids as $pid ) {
		if ( $first ) {                       // lowest id keeps the bare code
			$want[ $pid ] = $base;
			$first = false;
			continue;
		}
		$size = af_sku_size_token( $pid );
		$sku  = $size !== '' ? $base . '-' . $size : '';
		if ( $sku === '' || isset( $taken[ $sku ] ) ) {
			$n = 2;
			do { $sku = $base . '-' . $n; $n++; } while ( isset( $taken[ $sku ] ) );
			$fellback[ $pid ] = $sku;
		}
		$taken[ $sku ] = true;
		$want[ $pid ]  = $sku;
	}
}

echo "  art codes shared by more than one product: " . count( $shared ) . "\n";

// ── Write ───────────────────────────────────────────────────────────────────
$done = 0; $already = 0; $skipped = 0; $clash = 0; $samples = array();

foreach ( $ids as $pid ) {
	if ( ! isset( $want[ $pid ] ) ) { continue; }

	if ( get_post_meta( $pid, '_af_sku_artcode', true ) === $VERSION ) { $already++; continue; }
	if ( $done >= $MAX || ( microtime( true ) - $started ) > $SECONDS ) { break; }

	$new = $want[ $pid ];
	$old = (string) get_post_meta( $pid, '_sku', true );

	if ( $old === $new ) {                       // nothing to do, but it is done
		if ( ! $DRY ) { update_post_meta( $pid, '_af_sku_artcode', $VERSION ); }
		$already++;
		continue;
	}

	// Someone else already holds this SKU and it is not a product we are about
	// to move off it — leave this product alone and say so, rather than
	// silently minting a variant of a code the shop did not choose.
	if ( function_exists( 'wc_get_product_id_by_sku' ) ) {
		$holder = (int) wc_get_product_id_by_sku( $new );
		if ( $holder && $holder !== (int) $pid && ! isset( $want[ $holder ] ) ) {
			$clash++;
			echo "  CLASH  #{$pid} wanted {$new} — already held by #{$holder}; left as {$old}\n";
			continue;
		}
	}

	if ( count( $samples ) < 8 ) {
		$samples[] = "  #{$pid}  " . ( $old === '' ? '(no sku)' : $old ) . "  →  {$new}"
		           . "   [" . $codes[ $pid ] . "]";
	}

	if ( $DRY ) { $done++; continue; }

	$product = wc_get_product( $pid );
	if ( ! $product ) { $skipped++; continue; }

	if ( $old !== '' && get_post_meta( $pid, '_af_sku_before_artcode', true ) === '' ) {
		update_post_meta( $pid, '_af_sku_before_artcode', $old );
	}

	try {
		$product->set_sku( $new );               // CRUD, so the lookup table follows
		$product->save();
	} catch ( Exception $e ) {
		$skipped++;
		echo "  FAILED #{$pid} → {$new}: " . $e->getMessage() . "\n";
		continue;
	}

	update_post_meta( $pid, '_af_sku_artcode', $VERSION );
	if ( function_exists( 'wc_delete_product_transients' ) ) { wc_delete_product_transients( $pid ); }
	$done++;
}

$remaining = count( $want ) - $already - $done;

echo "\nchanged {$done}  |  already correct {$already}  |  clashes left alone {$clash}"
   . "  |  failed {$skipped}  |  " . (int) ( microtime( true ) - $started ) . "s\n";
if ( $remaining > 0 ) {
	echo "still to do: {$remaining} — the next deploy continues from here.\n";
} else {
	echo "every product with an art code now carries it as its SKU.\n";
}

if ( $samples ) {
	echo "\nwhat changed (first " . count( $samples ) . "):\n" . implode( "\n", $samples ) . "\n";
}

if ( $shared ) {
	echo "\nart codes on more than one product (first 10) — the lowest id keeps the\n"
	   . "bare code, the others carry their size:\n";
	$n = 0;
	foreach ( $shared as $base => $pids ) {
		if ( $n++ >= 10 ) { break; }
		$line = array();
		foreach ( $pids as $pid ) { $line[] = '#' . $pid . '=' . $want[ $pid ]; }
		echo "  {$base}: " . implode( '  ', $line ) . "\n";
	}
}

if ( $fellback ) {
	echo "\nno size could be read, so these were numbered instead (" . count( $fellback ) . "):\n";
	$n = 0;
	foreach ( $fellback as $pid => $sku ) {
		if ( $n++ >= 10 ) { break; }
		echo "  #{$pid} → {$sku}   " . get_the_title( $pid ) . "\n";
	}
}

if ( $nocode ) {
	echo "\nno art code, so the SKU was not touched (first 10 of " . count( $nocode ) . "):\n";
	foreach ( array_slice( $nocode, 0, 10 ) as $pid ) {
		echo "  #{$pid}  " . get_the_title( $pid ) . "\n";
	}
}
