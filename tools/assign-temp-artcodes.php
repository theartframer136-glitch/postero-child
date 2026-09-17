<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Give every product without an art code a temporary one: TMP-1000, TMP-1001, …
 *
 * 391 products, 220 of them carrying a code from the collection book. The other
 * 171 carry nothing, so they have no SKU built from a code either — they still
 * show whatever machine-made SKU they were created with. That is the gap this
 * closes: a placeholder that is unmistakably a placeholder, so every product in
 * the shop can be referred to by one short string until the real code is known.
 *
 * TMP- is deliberate. A bare number would be indistinguishable from a real code
 * to anyone reading the catalogue later, and these will sit in the renumber
 * report among the codes that name no page of the book. The prefix says what it
 * is, and `_af_artcode_temp` marks each one so the whole set can be found and
 * cleared in a single query when the real codes arrive.
 *
 * ── What it will and will not touch ─────────────────────────────────────────
 *
 * ONLY a product whose art code is empty. A product holding anything at all is
 * left exactly as it is, and that is not a detail:
 *
 *   #26145, #23496, #23435 hold AL 01, AL 05 and AL 06. AL is not a section of
 *   the book, so those codes name no page — but they are a flag the audit put
 *   there on purpose, recording that the product came from the Alwars set and
 *   has no home yet. Overwriting them with TMP codes would erase that.
 *
 * ── It settles ──────────────────────────────────────────────────────────────
 *
 * A number, once issued, never moves. The counter starts above the highest TMP
 * already in the catalogue rather than at 1000, so a second deploy assigns
 * nothing and a product that was TMP-1004 yesterday is TMP-1004 today. A SKU
 * that has been printed on an invoice cannot be pulled out from under it —
 * which is the same promise tools/sku-to-artcode.php makes about its letters.
 *
 * ── The SKU follows on its own ──────────────────────────────────────────────
 *
 * Nothing here writes a SKU. tools/sku-to-artcode.php runs straight after this
 * on every deploy and makes the SKU the art code, so TMP-1000 becomes SKU
 * TMP-1000 by the route every other code takes — and that pass keeps the SKU it
 * replaced in _af_sku_before_artcode, so tools/restore-sku-from-backup.php can
 * put all 171 back exactly as they were.
 *
 * Run: wp eval-file tools/assign-temp-artcodes.php --allow-root
 * Env: AF_TEMP_DRYRUN=1   report what would change, write nothing
 *      AF_TEMP_START      first number to issue (default 1000)
 */

/**
 * Which products get a temporary code, and which number each one gets.
 *
 * Pure: takes pid => current art code for the whole catalogue and returns
 * pid => new code for the ones that need one. Kept separate from the writing so
 * tools/test-temp-artcodes.php can hold the rules still while several hundred
 * SKUs are rewritten by the pass that runs after this one.
 *
 * @param array $codes pid => art code exactly as stored ('' when it has none)
 * @param int   $start the first number to issue when none exist yet
 * @return array pid => 'TMP-nnnn', in ascending pid order
 */
function af_temp_plan( array $codes, $start = 1000 ) {
	$highest = 0;
	$blank   = array();

	foreach ( $codes as $pid => $code ) {
		$c = trim( (string) $code );
		if ( $c === '' ) {
			$blank[] = (int) $pid;
			continue;
		}
		// Already temporary: remember its number so the counter clears it.
		if ( preg_match( '/^TMP-(\d+)$/i', $c, $m ) ) {
			$highest = max( $highest, (int) $m[1] );
		}
	}

	sort( $blank, SORT_NUMERIC );
	$n    = max( (int) $start, $highest + 1 );
	$plan = array();
	foreach ( $blank as $pid ) {
		$plan[ $pid ] = 'TMP-' . $n;
		$n++;
	}
	return $plan;
}

if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit( 1 ); }

$DRY   = (bool) getenv( 'AF_TEMP_DRYRUN' );
$START = (int) ( getenv( 'AF_TEMP_START' ) ?: 1000 );

echo "=== TEMPORARY ART CODES ===\n";
echo 'mode: ' . ( $DRY ? 'DRY RUN — nothing is written' : 'APPLYING' ) . "  |  first number: {$START}\n";

$ids = get_posts( array(
	'post_type'      => 'product',
	'post_status'    => 'any',
	'posts_per_page' => -1,
	'fields'         => 'ids',
	'orderby'        => 'ID',
	'order'          => 'ASC',
) );

$codes = array();
foreach ( $ids as $pid ) {
	$codes[ $pid ] = (string) get_post_meta( $pid, '_taf_art_code', true );
}

$plan   = af_temp_plan( $codes, $START );
$held   = 0;   // already carrying a code of some kind
$temp   = 0;   // already carrying a TMP code from an earlier run
foreach ( $codes as $c ) {
	$c = trim( (string) $c );
	if ( $c === '' ) { continue; }
	$held++;
	if ( preg_match( '/^TMP-\d+$/i', $c ) ) { $temp++; }
}

echo 'products: ' . count( $ids ) . "\n";
echo '  carrying a code already: ' . $held . "  (of which temporary: {$temp})\n";
echo '  to be given one now:     ' . count( $plan ) . "\n";

if ( $plan ) {
	$first = reset( $plan );
	$last  = end( $plan );
	echo "  range: {$first} … {$last}\n";
}

echo "\n--- assigning ---\n";
$written = 0;
foreach ( $plan as $pid => $code ) {
	$title = mb_substr( html_entity_decode( wp_strip_all_tags( get_the_title( $pid ) ) ), 0, 46 );
	printf( "  #%-7d %-10s %s\n", $pid, $code, $title );
	if ( ! $DRY ) {
		update_post_meta( $pid, '_taf_art_code', $code );
		update_post_meta( $pid, '_af_artcode_temp', '1' );
		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients( $pid );
		}
		$written++;
	}
}

// A product that was given a temporary code and has since been given a real one
// should stop being marked temporary, or the flag stops meaning anything.
$cleared = 0;
foreach ( $codes as $pid => $code ) {
	$c = trim( (string) $code );
	if ( $c === '' || preg_match( '/^TMP-\d+$/i', $c ) ) { continue; }
	if ( (string) get_post_meta( $pid, '_af_artcode_temp', true ) !== '' ) {
		if ( ! $DRY ) { delete_post_meta( $pid, '_af_artcode_temp' ); }
		$cleared++;
		printf( "  #%-7d no longer temporary — now holds %s\n", $pid, $c );
	}
}

echo "\nassigned: " . ( $DRY ? count( $plan ) . ' (dry run)' : $written )
   . "  |  temporary flags cleared: {$cleared}\n";
echo "\nThe SKU is not written here. tools/sku-to-artcode.php runs next and makes\n";
echo "each of these its SKU, keeping the one it replaces in _af_sku_before_artcode.\n";
echo "=== DONE ===\n";
