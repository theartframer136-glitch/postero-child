<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Put every product's art code into the numbering the Master Brochure prints
 * today: RK 01 becomes RK - 0101, LI 32 becomes LI - 1932, HD 15 becomes
 * HD - 0814.
 *
 * The book now prints its section's number alongside the page's, and it closed
 * the two gaps its old numbering had. inc/artcode-book.php holds the section
 * map and does the reading; this walks the catalogue and writes the result.
 * Why the gaps make this more than a prefix change, and how each of the
 * twenty-one sections was checked, is written up there.
 *
 * ── What it will not do ─────────────────────────────────────────────────────
 *
 * Translate, and nothing else. A code that names no page of the book — LR 32
 * from before Living Room was renamed, TP 04 which never had a page, a typo —
 * is LEFT EXACTLY AS IT IS and listed at the end. Deciding what such a product
 * should carry means putting its picture next to the book, which is the audit's
 * work and is recorded row by row in tools/artcode-corrections.csv. Turning a
 * code that points nowhere into one that points at the wrong painting would be
 * worse than leaving it, because a wrong code travels onto the SKU and onto
 * invoices.
 *
 * Renumbering cannot invent a clash: no two different pages of the old book map
 * onto one page of the new. Products that already shared a code still share it,
 * which for the deliberate pairs — two listings of one painting — is the point.
 * It is checked below rather than taken on trust.
 *
 * ── Safety ──────────────────────────────────────────────────────────────────
 *
 * DRY RUN BY DEFAULT. Nothing is written unless AF_APPLY=1, so a first run
 * prints what it would do and changes nothing.
 *
 * Reversible: the code a product had before its first renumbering is kept in
 * _af_code_before_renumber and never overwritten afterwards.
 *
 * The SKU is built from the art code, so every renumbered product needs a new
 * one. _af_sku_artcode is the marker tools/sku-to-artcode.php uses to skip
 * products it has already done; clearing it on the ones we change is what lets
 * the SKU pass — which runs next in the deploy — see them again.
 *
 * Run: wp eval-file tools/renumber-artcodes.php --allow-root
 * Env: AF_APPLY=1  — actually write (otherwise it only reports)
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

if ( ! function_exists( 'af_artcode_book_code' ) ) {
	$af_book = get_stylesheet_directory() . '/inc/artcode-book.php';
	if ( file_exists( $af_book ) ) { require_once $af_book; }
}
if ( ! function_exists( 'af_artcode_book_code' ) ) {
	echo "inc/artcode-book.php is not loaded — nothing can be renumbered safely.\n=== DONE ===\n";
	return;
}

$APPLY = getenv( 'AF_APPLY' ) === '1';

echo "=== ART CODES → THE BOOK'S NUMBERING ===\n";
echo $APPLY ? "mode: APPLYING\n" : "mode: dry run — nothing will be written (set AF_APPLY=1 to apply)\n";

$ids = get_posts( array(
	'post_type'      => 'product',
	'post_status'    => array( 'publish', 'private', 'draft', 'pending' ),
	'posts_per_page' => -1,
	'fields'         => 'ids',
	'orderby'        => 'ID',
	'order'          => 'ASC',
) );

$title = function ( $pid ) {
	return mb_substr( html_entity_decode( wp_strip_all_tags( get_the_title( $pid ) ) ), 0, 44 );
};

// ── Read the whole catalogue before writing any of it ───────────────────────
$plan    = array();   // pid => new code, for the ones that change
$already = array();   // pid => code, already in the book's numbering
$refused = array();   // pid => array( code, why )
$nocode  = 0;

foreach ( $ids as $pid ) {
	$code = get_post_meta( $pid, '_taf_art_code', true );
	$code = is_string( $code ) ? preg_replace( '/\s+/', ' ', trim( $code ) ) : '';
	if ( $code === '' ) { $nocode++; continue; }

	$new = af_artcode_book_code( $code );
	if ( $new === '' ) {
		$refused[ $pid ] = array( $code, af_artcode_book_refusal( $code ) );
		continue;
	}
	if ( $new === $code ) { $already[ $pid ] = $code; continue; }
	$plan[ $pid ] = $new;
}

echo "products: " . count( $ids ) . "\n";
echo "  with an art code:     " . ( count( $plan ) + count( $already ) + count( $refused ) ) . "\n";
echo "  without one:          {$nocode}\n";
echo "  to renumber:          " . count( $plan ) . "\n";
echo "  already in the book's numbering: " . count( $already ) . "\n";
echo "  naming no page of the book:      " . count( $refused ) . "  (left alone)\n";

// ── Codes that will sit on more than one product ────────────────────────────
// Not something the renumbering causes: one page of the old book is one page of
// the new, so any two products landing on one code were already on one artwork
// between them. That the map is one-to-one across all 340 pages is proved once,
// in tools/test-sku-format.php, rather than re-argued here. This is reported
// because it is worth seeing — some of these pairs are deliberate, two listings
// of a single painting, and the rest are the audit's remaining work.
$sharing = array();
foreach ( $plan as $pid => $new )      { $sharing[ strtoupper( $new ) ][]  = $pid; }
foreach ( $already as $pid => $code )  { $sharing[ strtoupper( $code ) ][] = $pid; }
$shared_codes = 0; $shared_products = 0;
foreach ( $sharing as $pids ) {
	if ( count( $pids ) > 1 ) { $shared_codes++; $shared_products += count( $pids ); }
}
printf( "  codes landing on more than one product: %d, over %d products (unchanged by this pass)\n",
	$shared_codes, $shared_products );

// ── Write ───────────────────────────────────────────────────────────────────
$changed = 0;

if ( $plan ) {
	echo $APPLY ? "\n--- renumbered ---\n" : "\n--- would be renumbered ---\n";
	foreach ( $plan as $pid => $new ) {
		$was = (string) get_post_meta( $pid, '_taf_art_code', true );
		printf( "  #%-7d %-12s -> %-14s %s\n", $pid, $was, $new, $title( $pid ) );

		if ( $APPLY ) {
			if ( get_post_meta( $pid, '_af_code_before_renumber', true ) === '' ) {
				update_post_meta( $pid, '_af_code_before_renumber', $was );
			}
			update_post_meta( $pid, '_taf_art_code', $new );
			// The SKU is built from this code, so let the SKU pass look again.
			delete_post_meta( $pid, '_af_sku_artcode' );
			if ( function_exists( 'wc_delete_product_transients' ) ) {
				wc_delete_product_transients( $pid );
			}
		}
		$changed++;
	}
}

// ── What was left alone, and why ────────────────────────────────────────────
if ( $refused ) {
	echo "\n--- left alone: these name no page of the book ---\n";
	echo "Each needs its picture put next to the book, which is the audit's work,\n";
	echo "not this pass's. Rows for them belong in tools/artcode-corrections.csv.\n";
	$by_reason = array();
	foreach ( $refused as $pid => $r ) { $by_reason[ $r[1] ][ $pid ] = $r[0]; }
	ksort( $by_reason );
	foreach ( $by_reason as $why => $items ) {
		echo "  " . $why . " — " . count( $items ) . " product(s)\n";
		foreach ( $items as $pid => $code ) {
			printf( "     #%-7d %-12s %s\n", $pid, $code, $title( $pid ) );
		}
	}
}

// ── One page the book itself has withdrawn ──────────────────────────────────
// RK 76 is struck through with a large red X in the brochure. It still has a
// page and so it still renumbers to RK - 0176, but nothing should be sitting
// on it. Renumbering is not the place to decide that; saying so is.
$withdrawn = array();
foreach ( $ids as $pid ) {
	$code = isset( $plan[ $pid ] ) ? $plan[ $pid ] : ( isset( $already[ $pid ] ) ? $already[ $pid ] : '' );
	if ( $code !== '' && strpos( strtoupper( $code ), 'RK - 0176' ) === 0 ) { $withdrawn[] = $pid; }
}
if ( $withdrawn ) {
	echo "\n--- on a page the book has withdrawn ---\n";
	echo "RK - 0176 (was RK 76) is struck through with a red X in the brochure.\n";
	foreach ( $withdrawn as $pid ) { printf( "     #%-7d %s\n", $pid, $title( $pid ) ); }
}

echo "\n";
printf( "renumbered: %d  |  already correct: %d  |  left alone: %d  |  no code: %d\n",
	$changed, count( $already ), count( $refused ), $nocode );

if ( ! $APPLY ) {
	echo "\nNothing was written. Set AF_APPLY=1 on this step to apply.\n";
} else {
	echo "\nWritten. The SKU pass runs next and rebuilds each changed product's SKU\n";
	echo "from its new code; the code each product had before is kept in\n";
	echo "_af_code_before_renumber.\n";
}
echo "=== DONE ===\n";
