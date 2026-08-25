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
 *   1. The SKU is the art code verbatim — "RK 01" is stored as "RK 01".
 *      A SKU must be UNIQUE, so only a product whose art code is its own gets
 *      it. Where one code sits on several products the SKU is left alone and
 *      the whole group is listed, because on this shop those products are
 *      unrelated artworks, not sizes of one — inventing "RK 01 2" would mint a
 *      code the shop never assigned.
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

$VERSION = 'artcode-sku-v3-hyphen';
$SECONDS = (int) ( getenv( 'AF_SKU_SECONDS' ) ?: 200 );
$MAX     = (int) ( getenv( 'AF_SKU_MAX' )     ?: 800 );
$DRY     = (bool) getenv( 'AF_SKU_DRYRUN' );

$started = microtime( true );

echo "=== SKU → ART CODE ===\n";
echo "target: {$VERSION}  |  budget: {$SECONDS}s or {$MAX} products per run"
   . ( $DRY ? '  |  DRY RUN — nothing is written' : '' ) . "\n";

/**
 * The SKU is the art code in the shop's SKU shape: "RK 01" becomes "RK-01".
 *
 * It used to be the code verbatim, spaces and all, on the reasoning that what
 * a customer reads should be character-for-character what the book says. The
 * shop has since specified the format — prefix, hyphen, two-digit number, and
 * on an order line the chosen size after another hyphen: RK-01-2/3. So the
 * product SKU is the first two parts of that, and inc/sku.php builds the full
 * one for the order line, where a size actually exists.
 *
 * The formatting lives in inc/sku.php so the product SKU and the line SKU can
 * never drift apart. This falls back to a local copy only if the theme is not
 * loaded, which wp eval-file always does load.
 */
function af_sku_from_code( $code ) {
	if ( function_exists( 'af_sku_code_part' ) ) {
		return af_sku_code_part( $code );
	}
	$s = preg_replace( '/\s+/', ' ', trim( (string) $code ) );
	if ( $s === '' ) { return ''; }
	if ( preg_match( '/^([A-Za-z]+)\s*0*(\d+)$/', $s, $m ) ) {
		return strtoupper( $m[1] ) . '-' . str_pad( $m[2], 2, '0', STR_PAD_LEFT );
	}
	return strtoupper( str_replace( ' ', '-', $s ) );
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
$by_sku  = array();   // KEY => ordered list of pids that want that code
$as_written = array();// KEY => the code exactly as the first product spells it
$nocode  = array();

foreach ( $ids as $pid ) {
	$code = get_post_meta( $pid, '_taf_art_code', true );
	$code = is_string( $code ) ? trim( $code ) : '';
	if ( $code === '' ) { $nocode[] = $pid; continue; }
	$base = af_sku_from_code( $code );
	if ( $base === '' ) { $nocode[] = $pid; continue; }
	// Grouped case-insensitively because MySQL compares SKUs that way: "rk 01"
	// and "RK 01" would collide in the database even though they differ here.
	// The spelling that gets stored is the one the first product uses.
	$key = strtoupper( $base );
	if ( ! isset( $as_written[ $key ] ) ) { $as_written[ $key ] = $base; }
	$codes[ $pid ] = $code;
	$by_sku[ $key ][] = $pid;
}

echo "products: " . count( $ids ) . "\n";
echo "  with an art code:    " . count( $codes ) . "\n";
echo "  WITHOUT an art code: " . count( $nocode ) . "  (SKU left exactly as it is)\n";

// ── Decide the SKU for every product before writing anything ────────────────
// Done in one pass so a resumed run assigns the same SKU it would have on the
// first run: the order is by product id, not by whatever happens to be left.
$want   = array();   // pid => sku
$shared = array();   // base => pids, left alone and reported

// v1 assumed a shared art code meant one artwork sold in several sizes, and
// appended the size to tell them apart. The live data says otherwise: of 56
// shared codes, NOT ONE is the same artwork twice. TA 04 alone sits on 28
// unrelated pieces — a cartoon cat, a waterfall, a Balaji temple, a family
// photo collage. Numbering those "TA 04 2 … TA 04 28" invents codes the shop
// never assigned and implies a relationship between pictures that have none.
//
// So a shared code is now treated as what it is: a data problem in the codes,
// not something a SKU rule can paper over. Only a product whose art code is
// its own gets that code as its SKU. The rest keep the SKU they already had
// and are listed in full, so the codes can be corrected and this re-run.
foreach ( $by_sku as $key => $pids ) {
	$base = $as_written[ $key ];
	if ( count( $pids ) === 1 ) {
		$want[ $pids[0] ] = $base;
	} else {
		$shared[ $base ] = $pids;
	}
}

echo "  art codes shared by more than one product: " . count( $shared ) . "\n";
echo "  products under a shared code (SKU left alone): "
   . array_sum( array_map( 'count', $shared ) ) . "\n";

// v1 already wrote invented SKUs for those products. Put them back.
$undone = 0;
foreach ( $shared as $base => $pids ) {
	foreach ( $pids as $pid ) {
		$was = (string) get_post_meta( $pid, '_af_sku_before_artcode', true );
		if ( $was === '' ) { continue; }
		$now = (string) get_post_meta( $pid, '_sku', true );
		if ( $now === $was ) { continue; }
		if ( $DRY ) { $undone++; continue; }
		$product = wc_get_product( $pid );
		if ( ! $product ) { continue; }
		try { $product->set_sku( $was ); $product->save(); } catch ( Exception $e ) { continue; }
		delete_post_meta( $pid, '_af_sku_artcode' );
		delete_post_meta( $pid, '_af_sku_before_artcode' );
		if ( function_exists( 'wc_delete_product_transients' ) ) { wc_delete_product_transients( $pid ); }
		$undone++;
	}
}
if ( $undone ) {
	echo "  put back {$undone} SKU(s) an earlier run had invented from a shared code\n";
}

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
	echo "\n--- ART CODES ON MORE THAN ONE PRODUCT ---\n";
	echo "These " . count( $shared ) . " codes are each on several unrelated products, so\n"
	   . "none of them can be a SKU (a SKU has to be unique). Every product below\n"
	   . "keeps the SKU it already had. Give these pieces their own codes and this\n"
	   . "will pick them up on the next run.\n";
	foreach ( $shared as $base => $pids ) {
		echo "  {$base} (" . count( $pids ) . " products)\n";
		foreach ( $pids as $pid ) {
			echo "     #{$pid}  " . mb_substr( html_entity_decode( wp_strip_all_tags( get_the_title( $pid ) ) ), 0, 62 ) . "\n";
		}
	}
}

if ( $nocode ) {
	echo "\nno art code, so the SKU was not touched (first 10 of " . count( $nocode ) . "):\n";
	foreach ( array_slice( $nocode, 0, 10 ) as $pid ) {
		echo "  #{$pid}  " . get_the_title( $pid ) . "\n";
	}
}
