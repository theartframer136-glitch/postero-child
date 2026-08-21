<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read the audit file already sitting on this server, and say whether it holds
 * the corrected art codes.
 *
 * The hunt turned up uploads/taf-reports/artcode-audit.csv — written by
 * tools/report-artcodes.php on 28 July, carrying 146 of this shop's codes and,
 * crucially, a column called new_art_code. That column exists precisely so a
 * person can write the right code beside each product and hand it back. If it
 * was ever filled in, the 167 products under a shared code can be corrected
 * from it exactly, and nobody has to match artwork by eye.
 *
 * So this reads it and reports what is actually in that column: how many rows
 * carry a proposed code, how many differ from what the product has now, and —
 * the question that decides whether it can be applied — whether those proposed
 * codes are unique, or would simply move the collision somewhere else.
 *
 * Read-only. It writes nothing and applies nothing.
 *
 * Run: wp eval-file tools/diag-artcode-audit-file.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

echo "=== THE AUDIT FILE: DOES IT HOLD THE CORRECTED CODES? ===\n";

$up   = wp_get_upload_dir();
$path = $up['basedir'] . '/taf-reports/artcode-audit.csv';

if ( ! file_exists( $path ) ) {
	echo "not found: {$path}\n=== DONE ===\n";
	return;
}
echo "file: {$path}\n";
echo "size: " . round( filesize( $path ) / 1024 ) . " KB  |  last written: " . date( 'Y-m-d H:i', filemtime( $path ) ) . "\n";

$fh = fopen( $path, 'r' );
if ( ! $fh ) { echo "could not open it\n=== DONE ===\n"; return; }

$head = fgetcsv( $fh );
if ( ! $head ) { echo "empty file\n=== DONE ===\n"; fclose( $fh ); return; }
$col = array();
foreach ( $head as $i => $name ) { $col[ strtolower( trim( $name ) ) ] = $i; }
echo "columns: " . implode( ', ', array_map( 'trim', $head ) ) . "\n";

if ( ! isset( $col['product_id'] ) || ! isset( $col['new_art_code'] ) ) {
	echo "\nThis file has no new_art_code column, so it carries no corrections.\n=== DONE ===\n";
	fclose( $fh );
	return;
}

$rows = 0; $proposed = 0; $same = 0; $changes = array(); $missing = 0;
$by_code = array();

while ( ( $r = fgetcsv( $fh ) ) !== false ) {
	$rows++;
	$pid = isset( $r[ $col['product_id'] ] ) ? (int) $r[ $col['product_id'] ] : 0;
	$new = isset( $r[ $col['new_art_code'] ] ) ? preg_replace( '/\s+/', ' ', trim( (string) $r[ $col['new_art_code'] ] ) ) : '';
	if ( $pid === 0 || $new === '' ) { continue; }
	$proposed++;

	if ( ! get_post( $pid ) ) { $missing++; continue; }
	$now = (string) get_post_meta( $pid, '_taf_art_code', true );
	$by_code[ strtoupper( $new ) ][] = $pid;

	if ( strcasecmp( trim( $now ), $new ) === 0 ) { $same++; continue; }
	$changes[ $pid ] = array( 'from' => $now, 'to' => $new );
}
fclose( $fh );

echo "\nrows: {$rows}\n";
echo "  with something in new_art_code: {$proposed}\n";
echo "  of those, already correct:      {$same}\n";
echo "  of those, a real change:        " . count( $changes ) . "\n";
if ( $missing ) { echo "  naming a product that no longer exists: {$missing}\n"; }

if ( ! $proposed ) {
	echo "\nThe column is empty throughout — the file was generated but never filled\n";
	echo "in. It carries no corrections, so nothing can be recovered from it.\n";
	echo "=== DONE ===\n";
	return;
}

// The question that decides everything: would applying these actually leave
// every product with a code of its own?
$dupes = array_filter( $by_code, function ( $p ) { return count( $p ) > 1; } );
echo "\ndistinct codes proposed: " . count( $by_code ) . "\n";
echo "codes proposed for more than one product: " . count( $dupes ) . "\n";
if ( $dupes ) {
	echo "  (these would still collide, so they cannot all become SKUs)\n";
	$n = 0;
	foreach ( $dupes as $code => $pids ) {
		if ( $n++ >= 8 ) { break; }
		echo "    {$code}: #" . implode( ' #', $pids ) . "\n";
	}
}

echo "\n--- what would change (first 30) ---\n";
$n = 0;
foreach ( $changes as $pid => $c ) {
	if ( $n++ >= 30 ) { break; }
	printf( "  #%-7d %-9s -> %-9s  %s\n", $pid,
		( $c['from'] !== '' ? $c['from'] : '(none)' ), $c['to'],
		mb_substr( html_entity_decode( wp_strip_all_tags( get_the_title( $pid ) ) ), 0, 50 ) );
}
if ( count( $changes ) > 30 ) { echo "  … and " . ( count( $changes ) - 30 ) . " more\n"; }

echo "\nNothing has been applied. To apply it, this file is already in the right\n";
echo "shape for tools/import-artcodes.php — it only needs saving as\n";
echo "artcode-import.csv in the same folder.\n";
