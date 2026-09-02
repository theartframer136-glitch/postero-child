<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Check the SKU format against every size the rate card actually sells.
 *
 * The shape asked for is RK-01-2/3: section prefix, art code number, size in
 * feet. The two halves are built in different places — the product SKU by
 * tools/sku-to-artcode.php, the line SKU by inc/sku.php on the order line — so
 * this proves they agree, and that all fifteen card sizes produce a token.
 *
 * Read-only. Run: wp eval-file tools/test-sku-format.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

echo "=== SKU FORMAT CHECK ===\n";

$fail = 0;
$ok   = function ( $cond, $label, $got, $want = null ) use ( &$fail ) {
	if ( $cond ) {
		printf( "  OK    %-46s %s\n", $label, $got );
	} else {
		$fail++;
		printf( "  FAIL  %-46s got %s want %s\n", $label, var_export( $got, true ), var_export( $want, true ) );
	}
};

// ── the code half ───────────────────────────────────────────────────────────
echo "\n-- art code -> SKU --\n";
$codes = array(
	'RK 01' => 'RK-01',
	'RK 1'  => 'RK-01',       // a one-digit code pads, so RK-1 and RK-01 cannot both exist
	'rk 01' => 'RK-01',       // case is normalised
	'RK  01' => 'RK-01',      // doubled space collapses
	'LI 32' => 'LI-32',
	'HD 28' => 'HD-28',
	'RK 101' => 'RK-101',     // three digits are left alone, not truncated
	''      => '',            // no code, no SKU — nothing is invented
	// The shape the book prints now: section number and page number together,
	// kept whole because the four digits are one identifier.
	'RK - 0101' => 'RK-0101',
	'HD - 0814' => 'HD-0814',
	'LI - 1944' => 'LI-1944',
	'HD - 0814-GF' => 'HD-0814-GF',   // the Gold Foiled & UV suffix survives
	'HD 15-GF'     => 'HD-15-GF',     // and did before, in the old shape
);
foreach ( $codes as $in => $want ) {
	$got = af_sku_code_part( $in );
	$ok( $got === $want, "'" . $in . "'", $got, $want );
}

// ── the book's own numbering ────────────────────────────────────────────────
// The brochure prints its section's number alongside the page's, and closed the
// two gaps its old numbering had. That second part is what makes this worth a
// test: TP 05 and everything after it, and HD 15 and everything after it, move
// down one, so a product renumbered by pasting the section number on the front
// would end up pointing at somebody else's painting.
echo "\n-- the book's numbering --\n";
if ( ! function_exists( 'af_artcode_book_code' ) ) {
	$fail++;
	echo "  FAIL  inc/artcode-book.php is not loaded\n";
} else {
	$book_cases = array(
		'RK 01'    => 'RK - 0101',   // a section that maps straight across
		'RK 91'    => 'RK - 0191',
		'LC 09'    => 'LC - 1409',
		'LI 32'    => 'LI - 1932',
		'TP 03'    => 'TP - 0503',   // before the gap: unmoved
		'TP 05'    => 'TP - 0504',   // after it: down one
		'TP 16'    => 'TP - 0515',
		'HD 13'    => 'HD - 0813',   // before the gap
		'HD 15'    => 'HD - 0814',   // after it
		'HD 28'    => 'HD - 0827',
		'HD 15-GF' => 'HD - 0814-GF',
		'rk 1'     => 'RK - 0101',   // case and padding are forgiven
		'RK-01'    => 'RK - 0101',
		'RK - 0101' => 'RK - 0101',  // already the book's shape: unchanged
		'TP - 0504' => 'TP - 0504',
		'TP 04'    => '',            // never had a page
		'HD 14'    => '',            // nor this
		'LR 32'    => '',            // Lord Rama has nine pages
		'ZZ 01'    => '',            // not a section of the book
		''         => '',
	);
	foreach ( $book_cases as $in => $want ) {
		$got = af_artcode_book_code( $in );
		$ok( $got === $want, "'" . $in . "'", $got === '' ? '(no page)' : $got, $want );
	}

	// Every page of the book must be reachable, exactly once, and reading a
	// code twice must give the same answer — the renumbering runs on every
	// deploy, so it has to be a no-op the second time.
	$pages = 0; $bad = 0; $seen = array();
	foreach ( af_artcode_book() as $prefix => $sec ) {
		$top = $sec['count'] + count( $sec['absent'] );
		$in_section = 0;
		for ( $n = 1; $n <= $top; $n++ ) {
			$new = af_artcode_book_code( $prefix . ' ' . $n );
			if ( in_array( $n, $sec['absent'], true ) ) {
				if ( $new !== '' ) { $bad++; }
				continue;
			}
			if ( $new === '' || isset( $seen[ $new ] ) || af_artcode_book_code( $new ) !== $new ) { $bad++; continue; }
			$seen[ $new ] = true;
			$in_section++;
			$pages++;
		}
		if ( $in_section !== $sec['count'] ) { $bad++; }
	}
	$ok( $bad === 0 && $pages === 340, 'every page maps, once, and maps to itself',
		$pages . ' pages, ' . $bad . ' problems', '340 pages, 0 problems' );
}

// ── the size half, against the real card ────────────────────────────────────
echo "\n-- every size the card sells -> token --\n";
$cfg   = function_exists( 'af_pricing_config' ) ? af_pricing_config() : array( 'sizes' => array() );
$sizes = isset( $cfg['sizes'] ) ? $cfg['sizes'] : array();
$ok( count( $sizes ) > 0, 'the rate card has sizes', count( $sizes ) . ' sizes' );

$tokens = array();
foreach ( array_keys( $sizes ) as $label ) {
	$tok = af_sku_size_part( $label );
	$ok( $tok !== '', $label, $tok === '' ? '(none)' : $tok, 'a token' );
	if ( $tok !== '' ) { $tokens[ $tok ][] = $label; }
}

// Portrait and landscape must not collapse onto the same token.
echo "\n-- orientation is preserved --\n";
$collide = 0;
foreach ( $tokens as $tok => $labels ) {
	if ( count( $labels ) > 1 ) {
		$collide++;
		printf( "  FAIL  token %-8s is shared by: %s\n", $tok, implode( ' | ', $labels ) );
	}
}
$ok( $collide === 0, 'no two card sizes share a token', $collide . ' collisions', '0' );
$ok( af_sku_size_part( '2×3 ft (24×36 in)' ) === '2/3', 'portrait 2x3', af_sku_size_part( '2×3 ft (24×36 in)' ), '2/3' );
$ok( af_sku_size_part( '3×2 ft (36×24 in)' ) === '3/2', 'landscape 3x2', af_sku_size_part( '3×2 ft (36×24 in)' ), '3/2' );
$ok( af_sku_size_part( '2.5×4 ft (30×48 in)' ) === '2.5/4', 'a half-foot size keeps its decimal', af_sku_size_part( '2.5×4 ft (30×48 in)' ), '2.5/4' );
$ok( af_sku_size_part( 'nonsense' ) === '', 'an unparseable size yields nothing', var_export( af_sku_size_part( 'nonsense' ), true ), "''" );

// ── the two halves agree ────────────────────────────────────────────────────
echo "\n-- the product SKU and the line SKU agree --\n";
$probe = get_posts( array(
	'post_type' => 'product', 'posts_per_page' => 5, 'fields' => 'ids',
	'meta_query' => array( array( 'key' => '_taf_art_code', 'compare' => 'EXISTS' ) ),
) );
foreach ( $probe as $pid ) {
	$code = get_post_meta( $pid, '_taf_art_code', true );
	$base = af_sku_for_product( $pid );
	$full = af_sku_full( $pid, '2×3 ft (24×36 in)' );
	$want = $base === '' ? '' : $base . '-2/3';
	$ok( $full === $want, "#{$pid} '{$code}'", $full === '' ? '(none)' : $full, $want === '' ? '(none)' : $want );
	// tools/sku-to-artcode.php must produce exactly the product half
	if ( function_exists( 'af_sku_from_code' ) ) {
		$ok( af_sku_from_code( $code ) === $base, "#{$pid} writer matches formatter", af_sku_from_code( $code ), $base );
	}
}

// A product with no size chosen still gets a usable SKU, not a trailing hyphen.
if ( $probe ) {
	$pid  = $probe[0];
	$base = af_sku_for_product( $pid );
	$ok( af_sku_full( $pid, '' ) === $base, 'no size chosen: no trailing hyphen', af_sku_full( $pid, '' ), $base );
}

// ── every SKU in the catalogue is distinct ──────────────────────────────────
// This is the guarantee the letters exist for, so it is measured on the live
// catalogue rather than assumed from the rule.
echo "\n-- every product SKU is unique --\n";
$all = get_posts( array(
	'post_type'   => 'product',
	'post_status' => array( 'publish', 'private', 'draft', 'pending' ),
	'posts_per_page' => -1, 'fields' => 'ids',
) );
$seen = array(); $dupes = array(); $blank = 0;
foreach ( $all as $pid ) {
	$sku = (string) get_post_meta( $pid, '_sku', true );
	if ( $sku === '' ) { $blank++; continue; }
	$k = strtoupper( $sku );
	if ( isset( $seen[ $k ] ) ) { $dupes[ $k ][] = $pid; } else { $seen[ $k ] = $pid; }
}
$ok( count( $dupes ) === 0, 'no two products share a SKU',
     count( $dupes ) === 0 ? count( $seen ) . ' distinct SKUs across ' . count( $all ) . ' products'
                           : count( $dupes ) . ' duplicated', '0 duplicated' );
foreach ( array_slice( $dupes, 0, 10, true ) as $k => $pids ) {
	printf( "        %-16s held by #%d and #%s\n", $k, $seen[ $k ], implode( ', #', $pids ) );
}
if ( $blank ) { printf( "  note  %d product(s) carry no SKU at all\n", $blank ); }

// ── the letter is stable ────────────────────────────────────────────────────
// A letter is only valid for the code it was issued under. If a product's art
// code has changed since, the stored letter is stale and is simply ignored --
// af_sku_for_product() only uses a letter whose issued-for code matches the
// current one. So a stale letter is inert, not wrong: the product gets its
// plain code as its SKU, and if that code later becomes shared it is issued a
// fresh letter. Listed here so the state is visible, not because it needs
// fixing.
echo "\n-- stored letters still match the code they were issued for --\n";
$stale = 0; $lettered = 0;
foreach ( $all as $pid ) {
	$l = (string) get_post_meta( $pid, '_af_sku_letter', true );
	if ( $l === '' ) { continue; }
	$lettered++;
	$issued = (string) get_post_meta( $pid, '_af_sku_letter_for', true );
	$now    = af_sku_code_part( get_post_meta( $pid, '_taf_art_code', true ) );
	if ( strcasecmp( $issued, $now ) !== 0 ) {
		$stale++;
		if ( $stale <= 10 ) {
			printf( "        #%-7d letter %-3s issued for %-10s but the code is now %s\n",
			        $pid, $l, $issued === '' ? '(none)' : $issued, $now === '' ? '(none)' : $now );
		}
	}
}
printf( "  note  %d product(s) carry a letter; %d stale and ignored (harmless -- see the comment above)\n", $lettered, $stale );

echo "\n";
echo $fail === 0 ? "=== ALL CHECKS PASSED ===\n" : "=== {$fail} CHECK(S) FAILED ===\n";
echo "=== DONE ===\n";
