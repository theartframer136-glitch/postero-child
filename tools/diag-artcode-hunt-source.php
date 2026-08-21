<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Where did the art codes come from — and is that source still on the server?
 *
 * The codes were written by an importer (tools/product-importer/apply_art_codes.py,
 * per the note in functions.php) which is not in this repository. Whatever list
 * it read from is the only place the true code for each picture exists outside
 * the printed book and the design files. If that list is still on the host, the
 * 167 products under a shared code can be corrected from it exactly, with no
 * guessing at all.
 *
 * So: look for it. Every small text-ish file under the site — csv, json, txt,
 * py, tsv — is opened and checked for art-code-shaped tokens sitting next to
 * words that look like product titles. Images and other binaries are never
 * read. Nothing is written and nothing is changed; this only reports what it
 * finds and where.
 *
 * Run: wp eval-file tools/diag-artcode-hunt-source.php --allow-root
 * Env: AF_HUNT_MAXKB (default 4096) — skip files larger than this
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$MAXKB = (int) ( getenv( 'AF_HUNT_MAXKB' ) ?: 4096 );

echo "=== HUNT FOR THE ART CODE SOURCE ===\n";

// The codes actually in use, so a file can be scored on how many of them it
// contains. A file holding 40 of this shop's real codes is the source; a file
// holding one is a coincidence.
$live = array();
foreach ( get_posts( array(
	'post_type' => 'product', 'post_status' => array( 'publish', 'private', 'draft', 'pending' ),
	'posts_per_page' => -1, 'fields' => 'ids',
) ) as $pid ) {
	$c = get_post_meta( $pid, '_taf_art_code', true );
	if ( is_string( $c ) && trim( $c ) !== '' ) {
		$live[ strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', $c ) ) ] = true;
	}
}
echo "distinct codes in use: " . count( $live ) . "\n";

$roots = array_unique( array_filter( array(
	defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : '',
	defined( 'ABSPATH' ) ? rtrim( ABSPATH, '/' ) : '',
	defined( 'ABSPATH' ) ? dirname( rtrim( ABSPATH, '/' ) ) : '',
) ) );

$exts   = array( 'csv', 'tsv', 'json', 'txt', 'py', 'md', 'xml', 'sql' );
$skip   = array( '/node_modules/', '/.git/', '/vendor/', '/cache/', '/litespeed/', '/wp-includes/' );
$looked = 0; $opened = 0; $hits = array();

foreach ( $roots as $root ) {
	if ( ! is_dir( $root ) ) { continue; }
	try {
		$it = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::LEAVES_ONLY
		);
	} catch ( Exception $e ) { continue; }

	foreach ( $it as $file ) {
		if ( $looked++ > 200000 ) { break; }
		$path = $file->getPathname();
		$bad  = false;
		foreach ( $skip as $s ) { if ( strpos( $path, $s ) !== false ) { $bad = true; break; } }
		if ( $bad || ! $file->isFile() ) { continue; }
		if ( ! in_array( strtolower( $file->getExtension() ), $exts, true ) ) { continue; }
		if ( $file->getSize() > $MAXKB * 1024 ) { continue; }

		$text = @file_get_contents( $path );
		if ( $text === false || $text === '' ) { continue; }
		$opened++;

		// How many of THIS SHOP's codes appear in the file?
		$flat  = strtoupper( preg_replace( '/[^A-Za-z0-9]/', ' ', $text ) );
		$found = array();
		if ( preg_match_all( '/\b([A-Z]{2,3})\s?(\d{1,3})\b/', $flat, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $one ) {
				$k = $one[1] . ltrim( $one[2], '0' );
				$k2 = $one[1] . str_pad( $one[2], 2, '0', STR_PAD_LEFT );
				if ( isset( $live[ $k ] ) || isset( $live[ $k2 ] ) ) { $found[ $one[1] . ' ' . $one[2] ] = true; }
			}
		}
		if ( count( $found ) < 5 ) { continue; }   // a handful is coincidence

		$hits[] = array(
			'path'  => $path,
			'codes' => count( $found ),
			'size'  => $file->getSize(),
			'when'  => date( 'Y-m-d H:i', $file->getMTime() ),
			'peek'  => array_slice( array_filter( array_map( 'trim', explode( "\n", $text ) ) ), 0, 4 ),
		);
	}
}

usort( $hits, function ( $a, $b ) { return $b['codes'] - $a['codes']; } );

echo "text files opened: {$opened}\n";
echo "files carrying 5+ of this shop's codes: " . count( $hits ) . "\n";

if ( ! $hits ) {
	echo "\nNOTHING FOUND. The list the importer read from is not on this server,\n";
	echo "so the true code for each picture cannot be recovered from the site.\n";
	echo "It exists only in the design files and the printed book.\n";
} else {
	echo "\n--- best candidates ---\n";
	foreach ( array_slice( $hits, 0, 12 ) as $h ) {
		echo "\n  {$h['codes']} codes  |  " . round( $h['size'] / 1024 ) . " KB  |  {$h['when']}\n";
		echo "  {$h['path']}\n";
		foreach ( $h['peek'] as $line ) {
			echo '      ' . mb_substr( preg_replace( '/\s+/', ' ', $line ), 0, 110 ) . "\n";
		}
	}
	echo "\nIf one of these is the original list, it can be turned into an import\n";
	echo "and the 167 products corrected from it exactly — no guessing.\n";
}
