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
 *   1. The SKU is the art code in the shop's format: "RK 01" is stored as
 *      "RK-01". A SKU must be UNIQUE and an art code here often is not, so
 *      where one code sits on several products each gets a letter — RK-01A,
 *      RK-01B — assigned once and stored, never recomputed from position, so a
 *      SKU already printed on an invoice can never move. The code itself is
 *      left exactly as the book writes it; the letter is the SKU's own
 *      business.
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

$VERSION = 'artcode-sku-v4-unique';
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
// A SKU must be unique; an art code on this shop often is not. 56 codes sit on
// more than one product, and they are NOT sizes of one artwork — TA 04 alone
// carries 28 unrelated pieces, a cartoon cat and a waterfall among them. So the
// code stays exactly as the book writes it and the SKU gains a letter:
//
//     RK-01     the only product with that code
//     RK-01A    first of several sharing RK 01
//     RK-01B    second, and so on; past Z it runs AA, AB
//
// The letter is ASSIGNED ONCE AND STORED, never recomputed from position. Once
// a product is RK-01B it stays RK-01B even if the product before it is deleted,
// has its code corrected, or the catalogue is reordered — because that SKU is
// already on somebody's invoice. A letter is only reissued when the product's
// own art code changes and it therefore joins a different group;
// _af_sku_letter_for records the code the letter was issued under, which is how
// that case is told apart from the others.
$want    = array();   // pid => sku
$lettered = array();  // base => pids, the groups that needed letters
$setlet  = array();   // pid => array( letter, base )  to persist on write
$clearlet = array();  // pid list: no longer shares a code, letter must go

foreach ( $by_sku as $key => $pids ) {
	$base = $as_written[ $key ];

	if ( count( $pids ) === 1 ) {
		$want[ $pids[0] ] = $base;
		// It used to share a code and no longer does: drop the stale letter so
		// the SKU is the plain code again.
		if ( (string) get_post_meta( $pids[0], '_af_sku_letter', true ) !== '' ) {
			$clearlet[] = $pids[0];
		}
		continue;
	}

	sort( $pids );   // product id order, so a first run and a resumed run agree

	// Honour every letter already issued under THIS code before handing out new
	// ones, so existing SKUs never move.
	$taken = array(); $got = array();
	foreach ( $pids as $pid ) {
		$l = strtoupper( (string) get_post_meta( $pid, '_af_sku_letter', true ) );
		$f = (string) get_post_meta( $pid, '_af_sku_letter_for', true );
		if ( $l !== '' && strcasecmp( $f, $base ) === 0 && ! isset( $taken[ $l ] ) ) {
			$got[ $pid ] = $l;
			$taken[ $l ] = true;
		}
	}
	$n = 0;
	foreach ( $pids as $pid ) {
		if ( isset( $got[ $pid ] ) ) { continue; }
		while ( isset( $taken[ af_sku_letter_seq( $n ) ] ) ) { $n++; }
		$l = af_sku_letter_seq( $n );
		$got[ $pid ] = $l; $taken[ $l ] = true; $n++;
	}

	foreach ( $pids as $pid ) {
		$want[ $pid ]   = $base . $got[ $pid ];
		$setlet[ $pid ] = array( $got[ $pid ], $base );
	}
	$lettered[ $base ] = $pids;
}

echo "  art codes shared by more than one product: " . count( $lettered ) . "\n";
echo "  products given a letter so their SKU is unique: "
   . array_sum( array_map( 'count', $lettered ) ) . "\n";

// Every SKU about to be written must be distinct. This is the guarantee the
// whole change exists for, so it is checked rather than assumed.
$seen = array(); $dupe = 0;
foreach ( $want as $pid => $sku ) {
	$k = strtoupper( $sku );
	if ( isset( $seen[ $k ] ) ) {
		$dupe++;
		echo "  COLLISION {$sku} wanted by #{$seen[$k]} and #{$pid}\n";
	} else {
		$seen[ $k ] = $pid;
	}
}
echo $dupe === 0
	? "  uniqueness: OK — " . count( $want ) . " products, " . count( $seen ) . " distinct SKUs\n"
	: "  uniqueness: {$dupe} COLLISION(S) — nothing will be written for those\n";
if ( $dupe > 0 ) {
	// Refuse the whole run rather than write a set known to contain duplicates.
	echo "\nRefusing to write: the planned SKUs are not unique.\n=== DONE ===\n";
	return;
}

/**
 * Record (or drop) the letter that makes this product's SKU unique, so
 * af_sku_for_product() in inc/sku.php builds the same SKU for the order line
 * that is stored on the product.
 */
function af_sku_persist_letter( $pid, $setlet, $clearlet ) {
	if ( isset( $setlet[ $pid ] ) ) {
		update_post_meta( $pid, '_af_sku_letter', $setlet[ $pid ][0] );
		update_post_meta( $pid, '_af_sku_letter_for', $setlet[ $pid ][1] );
		return;
	}
	if ( in_array( $pid, $clearlet, true ) ) {
		delete_post_meta( $pid, '_af_sku_letter' );
		delete_post_meta( $pid, '_af_sku_letter_for' );
	}
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
		if ( ! $DRY ) {
			af_sku_persist_letter( $pid, $setlet, $clearlet );
			update_post_meta( $pid, '_af_sku_artcode', $VERSION );
		}
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

	// Only after the SKU is safely written: a letter recorded for a SKU that
	// failed to save would make the order line disagree with the product.
	af_sku_persist_letter( $pid, $setlet, $clearlet );
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

if ( $lettered ) {
	echo "\n--- ART CODES ON MORE THAN ONE PRODUCT ---\n";
	echo "These " . count( $lettered ) . " codes are each on several products, so each product\n"
	   . "gets a letter to make its SKU unique. The art code itself is untouched —\n"
	   . "the letter belongs to the SKU alone. A letter, once issued, never moves.\n"
	   . "These are still codes that want correcting; the letters keep the shop\n"
	   . "working in the meantime.\n";
	foreach ( $lettered as $base => $pids ) {
		echo "  {$base} (" . count( $pids ) . " products)\n";
		foreach ( $pids as $pid ) {
			$sku = isset( $want[ $pid ] ) ? $want[ $pid ] : '?';
			printf( "     %-12s #%-7d %s\n", $sku, $pid,
				mb_substr( html_entity_decode( wp_strip_all_tags( get_the_title( $pid ) ) ), 0, 54 ) );
		}
	}
}

if ( $nocode ) {
	echo "\nno art code, so the SKU was not touched (first 10 of " . count( $nocode ) . "):\n";
	foreach ( array_slice( $nocode, 0, 10 ) as $pid ) {
		echo "  #{$pid}  " . get_the_title( $pid ) . "\n";
	}
}
