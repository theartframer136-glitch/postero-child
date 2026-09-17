<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Tests what tools/assign-temp-artcodes.php will WRITE onto a product.
 *
 * That pass runs on every deploy and sku-to-artcode.php stamps the result onto
 * the SKU straight after, so what it decides reaches live products and invoices.
 * The properties that have to hold while 171 SKUs are rewritten:
 *
 *   only the empty ones      a product holding any code at all is untouched —
 *                            including the AL codes the audit left as a flag
 *   it settles               a second run assigns nothing and moves nobody, so
 *                            a SKU already printed cannot be pulled away
 *   no climb                 102 correction rows clear a code on purpose and
 *                            that pass runs BEFORE this one, so an empty code
 *                            can mean "cleared a moment ago". The remembered
 *                            number goes back on rather than the next free one.
 *                            Without this the 102 gained a new SKU every deploy,
 *                            and for one deploy on 2026-09-17 they did
 *   numbers never repeat     the counter clears the highest TMP ever ISSUED,
 *                            not merely the highest anything is wearing now
 *   order is the product id  so the same catalogue always gives the same answer
 *
 *     php tools/test-temp-artcodes.php
 */
define( 'ABSPATH', '/nowhere/' );

// The function from assign-temp-artcodes.php that decides what is written. It is
// lifted out rather than required, the way tools/test-renumber-writes.php lifts
// af_sku_code_part(), because requiring the tool would run the pass itself.
$src = file_get_contents( __DIR__ . '/assign-temp-artcodes.php' );
if ( ! preg_match( '/\nfunction af_temp_plan\( array \$codes, array \$stored = array\(\), \$start = 1000 \) \{.*?\n\}\n/s', $src, $m ) ) {
	fwrite( STDERR, "assign-temp-artcodes.php no longer defines af_temp_plan() as this test expects — the test is stale\n" );
	exit( 2 );
}
eval( $m[0] );

$pass = 0; $fail = 0;
$ok = function ( $cond, $label, $got = null, $want = null ) use ( &$pass, &$fail ) {
	if ( $cond ) { $pass++; printf( "  OK    %s\n", $label ); }
	else {
		$fail++;
		printf( "  FAIL  %-58s got %s want %s\n", $label,
			var_export( $got, true ), var_export( $want, true ) );
	}
};

echo "=== TEMPORARY ART CODE PLAN ===\n\n";

// ── the ordinary case ───────────────────────────────────────────────────────
echo "-- a catalogue with some coded and some not --\n";
$codes = array(
	7800  => 'LC - 140001-3050',
	229   => 'TP - 050004-5030',
	26145 => 'AL 01',            // names no page, but it is a flag, not a blank
	8591  => '',
	7662  => '',
	31527 => '',
);
$plan = af_temp_plan( $codes, array() );
$ok( count( $plan ) === 3, 'only the three blanks are planned', count( $plan ), 3 );
$ok( ! isset( $plan[7800] ),  'a book code is left alone' );
$ok( ! isset( $plan[229] ),   'a second book code is left alone' );
$ok( ! isset( $plan[26145] ), 'AL 01 is left alone — it is the audit\'s flag, not an empty' );

// ── numbering ───────────────────────────────────────────────────────────────
echo "\n-- the numbers --\n";
$ok( $plan[7662] === 'TMP-1000', 'the lowest product id gets the first number', $plan[7662], 'TMP-1000' );
$ok( $plan[8591] === 'TMP-1001', 'the next id gets the next number', $plan[8591], 'TMP-1001' );
$ok( $plan[31527] === 'TMP-1002', 'and the next', $plan[31527], 'TMP-1002' );
$ok( array_keys( $plan ) === array( 7662, 8591, 31527 ),
     'assignment runs in ascending product id order, not hash order' );

// ── it settles ──────────────────────────────────────────────────────────────
echo "\n-- a second run --\n";
$after = $codes;
foreach ( $plan as $pid => $c ) { $after[ $pid ] = $c; }
$again = af_temp_plan( $after, array() );
$ok( $again === array(), 'a second run over the result assigns nothing', $again, array() );

// ── a new product arrives ───────────────────────────────────────────────────
echo "\n-- a product added after the first run --\n";
$after[40000] = '';
$third = af_temp_plan( $after, array() );
$ok( count( $third ) === 1, 'only the new product is planned', count( $third ), 1 );
$ok( $third[40000] === 'TMP-1003',
     'it continues above the highest TMP in the shop, it does not reuse 1000',
     $third[40000], 'TMP-1003' );

// ── a number already issued is never moved ──────────────────────────────────
echo "\n-- an existing number is never reassigned --\n";
$sparse = array( 100 => 'TMP-1007', 200 => '', 300 => '' );
$sp = af_temp_plan( $sparse, array() );
$ok( ! isset( $sp[100] ), 'the product already holding TMP-1007 is untouched' );
$ok( $sp[200] === 'TMP-1008', 'the counter clears the highest, leaving no collision',
     $sp[200], 'TMP-1008' );
$ok( $sp[300] === 'TMP-1009', 'and carries on', $sp[300], 'TMP-1009' );
$ok( count( array_unique( array_merge( array( 'TMP-1007' ), array_values( $sp ) ) ) ) === 3,
     'no number is issued twice' );

// ── whitespace is not a code ────────────────────────────────────────────────
echo "\n-- a code that is only whitespace --\n";
$ws = af_temp_plan( array( 500 => '   ', 600 => "\t" ), array() );
$ok( count( $ws ) === 2, 'blank-looking codes count as empty, not as held', count( $ws ), 2 );

// ── the start can move ──────────────────────────────────────────────────────
echo "\n-- a different starting number --\n";
$st = af_temp_plan( array( 900 => '' ), array(), 5000 );
$ok( $st[900] === 'TMP-5000', 'AF_TEMP_START is honoured', $st[900], 'TMP-5000' );
$st2 = af_temp_plan( array( 900 => '', 901 => 'TMP-9999' ), array(), 5000 );
$ok( $st2[900] === 'TMP-10000',
     'an existing higher number still wins over the start', $st2[900], 'TMP-10000' );

// ── the SKU the code will become ────────────────────────────────────────────
echo "\n-- what the SKU pass will make of it --\n";
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter() {} function add_action() {} function apply_filters( $t, $v ) { return $v; }
}
require_once dirname( __DIR__ ) . '/inc/sku.php';
$ok( af_sku_code_part( 'TMP-1000' ) === 'TMP-1000',
     'TMP-1000 survives the SKU formatter unchanged', af_sku_code_part( 'TMP-1000' ), 'TMP-1000' );
$ok( af_sku_code_part( 'TMP-1170' ) === 'TMP-1170',
     'and so does the top of the range', af_sku_code_part( 'TMP-1170' ), 'TMP-1170' );
$ok( af_sku_code_part( 'RK - 010001-3050' ) === 'RK-010001-3050',
     'a real code is still formatted the way it always was' );

// ── the churn this file exists to prevent ───────────────────────────────────
// 102 correction rows clear a product's code on purpose, and that pass runs
// BEFORE this one every deploy. Without the remembered number, each deploy saw
// an empty code and issued a fresh one, so those products climbed a number at a
// time and their SKUs moved with them. It was live for one deploy.
echo "\n-- a code cleared by tools/artcode-corrections.csv after it was issued --\n";
$cleared_again = af_temp_plan(
	array( 27133 => '', 25124 => '', 7800 => 'LC - 140001-3050' ),
	array( 27133 => 'TMP-1104', 25124 => 'TMP-1089' )
);
$ok( $cleared_again[27133] === 'TMP-1104',
     'a product whose code was cleared gets the SAME number back, not the next one',
     $cleared_again[27133], 'TMP-1104' );
$ok( $cleared_again[25124] === 'TMP-1089',
     'and so does the next one', $cleared_again[25124], 'TMP-1089' );
$ok( ! isset( $cleared_again[7800] ), 'a real book code is still left alone' );

echo "\n-- a new product alongside products whose codes were cleared --\n";
$mixed = af_temp_plan(
	array( 27133 => '', 40000 => '' ),
	array( 27133 => 'TMP-1203' )
);
$ok( $mixed[27133] === 'TMP-1203', 'the cleared one is restored', $mixed[27133], 'TMP-1203' );
$ok( $mixed[40000] === 'TMP-1204',
     'the new one continues above the highest number EVER issued, even though no '
   . 'product is currently wearing it', $mixed[40000], 'TMP-1204' );
$ok( count( array_unique( array_values( $mixed ) ) ) === 2, 'the two cannot collide' );

echo "\n-- it settles across deploys --\n";
$state  = array( 27133 => '', 25124 => '' );
$memory = array( 27133 => 'TMP-1104', 25124 => 'TMP-1089' );
$first  = af_temp_plan( $state, $memory );
$second = af_temp_plan( $state, $memory );
$third  = af_temp_plan( $state, $memory );
$ok( $first === $second && $second === $third,
     'three deploys in a row write the identical codes — no climb' );

printf( "\n%d passed, %d failed\n", $pass, $fail );
exit( $fail ? 1 : 0 );
