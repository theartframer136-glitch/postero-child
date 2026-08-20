<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Which products share an art code — the full picture, read-only.
 *
 * The SKU migration has to disambiguate any code that lands on more than one
 * product, so this prints exactly which products those are: the code, every
 * product carrying it, the size read from each, and the SKU each one ends up
 * with. Nothing is written; this only reports.
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

// The size token, mirroring tools/sku-to-artcode.php so the SKU column here
// is the SKU that migration actually assigns.
function af_dup_trim_number( $n ) {
	$n = (string) $n;
	if ( strpos( $n, '.' ) !== false ) { $n = rtrim( rtrim( $n, '0' ), '.' ); }
	return $n;
}
function af_dup_size_token( $pid ) {
	$candidates = array();
	$product = function_exists( 'wc_get_product' ) ? wc_get_product( $pid ) : null;
	if ( $product ) {
		foreach ( array( 'pa_size', 'size', 'pa_dimensions' ) as $key ) {
			$v = $product->get_attribute( $key );
			if ( $v ) { $candidates[] = $v; }
		}
	}
	$title = html_entity_decode( wp_strip_all_tags( get_the_title( $pid ) ) );
	if ( preg_match( '/(\d+(?:\.\d+)?)\s*(?:x|×)\s*(\d+(?:\.\d+)?)\s*(feet|foot|ft|inch|inches|in|cm|mm)?/i', $title, $m ) ) {
		$candidates[] = $m[0];
	}
	foreach ( $candidates as $raw ) {
		$raw = strtolower( (string) $raw );
		$raw = str_replace( array( '×', '"', "'" ), array( 'x', '', '' ), $raw );
		if ( ! preg_match( '/(\d+(?:\.\d+)?)\s*x\s*(\d+(?:\.\d+)?)\s*([a-z]*)/', $raw, $m ) ) { continue; }
		$unit = '';
		if ( preg_match( '/^(feet|foot|ft)/', $m[3] ) )       { $unit = 'FT'; }
		elseif ( preg_match( '/^(inch|inches|in)/', $m[3] ) ) { $unit = 'IN'; }
		elseif ( preg_match( '/^(cm|centimet)/', $m[3] ) )    { $unit = 'CM'; }
		elseif ( preg_match( '/^(mm|millimet)/', $m[3] ) )    { $unit = 'MM'; }
		$w = af_dup_trim_number( $m[1] );
		$h = af_dup_trim_number( $m[2] );
		if ( $w === '' || $h === '' ) { continue; }
		return strtoupper( $w . 'X' . $h . $unit );
	}
	return '';
}

echo "\n--- every shared code (first {$LIMIT} groups) ---\n";
echo "code | product id | size read | SKU assigned | title\n";

$printed = 0;
$csv = array( 'code,product_id,size,sku_assigned,current_sku,title' );

foreach ( $shared as $key => $pids ) {
	$base  = $spell[ $key ];
	$taken = array( $base => true );
	$first = true;
	$lines = array();

	foreach ( $pids as $pid ) {
		$size = af_dup_size_token( $pid );
		if ( $first ) {
			$sku   = $base;
			$first = false;
		} else {
			$sku = $size !== '' ? $base . ' ' . $size : '';
			if ( $sku === '' || isset( $taken[ $sku ] ) ) {
				$n = 2;
				do { $sku = $base . ' ' . $n; $n++; } while ( isset( $taken[ $sku ] ) );
			}
			$taken[ $sku ] = true;
		}
		$title = html_entity_decode( wp_strip_all_tags( get_the_title( $pid ) ) );
		$now   = (string) get_post_meta( $pid, '_sku', true );
		$lines[] = sprintf( '  %-10s #%-7d %-10s %-18s %s',
			$base, $pid, ( $size !== '' ? $size : '—' ), $sku, mb_substr( $title, 0, 58 ) );
		$csv[] = '"' . str_replace( '"', '""', $base ) . '",' . $pid . ',"' . $size . '","'
		       . str_replace( '"', '""', $sku ) . '","' . str_replace( '"', '""', $now ) . '","'
		       . str_replace( '"', '""', $title ) . '"';
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
