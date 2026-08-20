<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Which products share an art code — the full picture, read-only.
 *
 * A SKU has to be unique, so any code sitting on more than one product cannot
 * become one. This prints exactly which products those are — the code, every
 * product under it, and the SKU each still carries — so the codes can be
 * corrected. Nothing is written; this only reports.
 *
 * Run: wp eval-file tools/diag-artcode-duplicates.php --allow-root
 * Env: AF_DUP_GROUPS (default 200) — how many shared codes to print in full
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$LIMIT = (int) ( getenv( 'AF_DUP_GROUPS' ) ?: 200 );

echo "=== ART CODES ON MORE THAN ONE PRODUCT ===\n";

$ids = get_posts( array(
	'post_type'      => 'product',
	'post_status'    => array( 'publish', 'private', 'draft', 'pending' ),
	'posts_per_page' => -1,
	'fields'         => 'ids',
	'orderby'        => 'ID',
	'order'          => 'ASC',
) );

$groups = array();   // KEY => pids
$spell  = array();   // KEY => code as the first product spells it
$nocode = 0;

foreach ( $ids as $pid ) {
	$code = get_post_meta( $pid, '_taf_art_code', true );
	$code = is_string( $code ) ? preg_replace( '/\s+/', ' ', trim( $code ) ) : '';
	if ( $code === '' ) { $nocode++; continue; }
	$key = strtoupper( $code );
	if ( ! isset( $spell[ $key ] ) ) { $spell[ $key ] = $code; }
	$groups[ $key ][] = $pid;
}

$shared = array_filter( $groups, function ( $pids ) { return count( $pids ) > 1; } );
uasort( $shared, function ( $a, $b ) { return count( $b ) - count( $a ); } );

$products_in_shared = 0;
foreach ( $shared as $pids ) { $products_in_shared += count( $pids ); }

echo "products: " . count( $ids ) . "\n";
echo "  with an art code: " . ( count( $ids ) - $nocode ) . "  |  without: {$nocode}\n";
echo "  distinct art codes: " . count( $groups ) . "\n";
echo "  codes used by more than one product: " . count( $shared ) . "\n";
echo "  products sharing a code with something else: {$products_in_shared}\n";

// How big do the groups get? A shop selling one artwork in three sizes looks
// very different from one where a code was pasted onto forty products.
$sizes = array();
foreach ( $shared as $pids ) {
	$n = count( $pids );
	$sizes[ $n ] = isset( $sizes[ $n ] ) ? $sizes[ $n ] + 1 : 1;
}
ksort( $sizes );
echo "\n--- how many products per shared code ---\n";
foreach ( $sizes as $n => $count ) {
	echo "  {$n} products: {$count} code(s)\n";
}

echo "\n--- every shared code (first {$LIMIT} groups) ---\n";
echo "code | product id | current SKU | title\n";

$printed = 0;
$csv = array( 'code,product_id,current_sku,title' );

foreach ( $shared as $key => $pids ) {
	$base  = $spell[ $key ];
	$lines = array();

	foreach ( $pids as $pid ) {
		$title = html_entity_decode( wp_strip_all_tags( get_the_title( $pid ) ) );
		$now   = (string) get_post_meta( $pid, '_sku', true );
		$lines[] = sprintf( '  %-10s #%-7d %-22s %s',
			$base, $pid, ( $now !== '' ? mb_substr( $now, 0, 22 ) : '—' ), mb_substr( $title, 0, 60 ) );
		$csv[] = '"' . str_replace( '"', '""', $base ) . '",' . $pid . ',"'
		       . str_replace( '"', '""', $now ) . '","' . str_replace( '"', '""', $title ) . '"';
	}

	if ( $printed < $LIMIT ) {
		echo implode( "\n", $lines ) . "\n";
		$printed++;
	}
}

if ( count( $shared ) > $printed ) {
	echo "  … and " . ( count( $shared ) - $printed ) . " more shared codes (raise AF_DUP_GROUPS to print them)\n";
}

// The whole table, for the cases where the log is not the right place to read
// hundreds of rows.
$up  = wp_get_upload_dir();
$dir = $up['basedir'] . '/taf-reports';
if ( ! is_dir( $dir ) ) { wp_mkdir_p( $dir ); }
if ( file_put_contents( $dir . '/artcode-duplicates.csv', implode( "\n", $csv ) ) !== false ) {
	echo "\nfull table: " . $up['baseurl'] . "/taf-reports/artcode-duplicates.csv\n";
}
