<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Print the wording each product carries, so it can be matched to the
 * collection book.
 *
 * The book — "TheARTFramer Mixed Brochure" in Canva, 358 pages — gives every
 * artwork a page labelled with its art code and a short line describing it
 * ("This divine image of Radha and Krishna symbolizes eternal love…"). If the
 * shop's product text came from those lines, the line itself identifies which
 * page a product is, and the 167 products under a shared code can be corrected
 * from the book exactly rather than by eye.
 *
 * This prints what the site actually says, so that can be tested rather than
 * assumed. Two groups:
 *   CALIBRATION — 25 products whose code is already its own. If their wording
 *                 matches their own page in the book, the method works.
 *   TO FIX      — every product under a shared code, which is what we need.
 *
 * Read-only. Text is normalised to one line and capped so the log stays
 * readable; the full text goes to a CSV alongside it.
 *
 * Run: wp eval-file tools/diag-product-text-dump.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

echo "=== PRODUCT WORDING, FOR MATCHING TO THE COLLECTION BOOK ===\n";

function af_ptd_text( $pid ) {
	$post = get_post( $pid );
	if ( ! $post ) { return ''; }
	// The short description first: on this shop it is the line a customer
	// reads under the title, which is where a book line would have landed.
	$parts = array( (string) $post->post_excerpt, (string) $post->post_content );
	$out   = array();
	foreach ( $parts as $p ) {
		$p = wp_strip_all_tags( html_entity_decode( $p ) );
		$p = preg_replace( '/\s+/', ' ', trim( $p ) );
		// Our own art-code line is not the product's own wording.
		$p = preg_replace( '/Art Code:\s*\S+\s*/i', '', $p );
		if ( $p !== '' ) { $out[] = $p; }
	}
	return trim( implode( '  ~  ', $out ) );
}

$ids = get_posts( array(
	'post_type'      => 'product',
	'post_status'    => array( 'publish', 'private', 'draft', 'pending' ),
	'posts_per_page' => -1,
	'fields'         => 'ids',
	'orderby'        => 'ID',
	'order'          => 'ASC',
) );

$groups = array(); $spell = array(); $code_of = array();
foreach ( $ids as $pid ) {
	$code = get_post_meta( $pid, '_taf_art_code', true );
	$code = is_string( $code ) ? preg_replace( '/\s+/', ' ', trim( $code ) ) : '';
	if ( $code === '' ) { continue; }
	$key = strtoupper( $code );
	if ( ! isset( $spell[ $key ] ) ) { $spell[ $key ] = $code; }
	$groups[ $key ][] = $pid;
	$code_of[ $pid ]  = $code;
}
$shared = array_filter( $groups, function ( $p ) { return count( $p ) > 1; } );
$unique = array_filter( $groups, function ( $p ) { return count( $p ) === 1; } );

$csv = array( 'group,product_id,current_code,title,text' );

echo "\n--- CALIBRATION: 25 products whose code is already their own ---\n";
echo "If these lines appear in the book under the same code, matching works.\n\n";
$n = 0;
foreach ( $unique as $key => $pids ) {
	if ( $n++ >= 25 ) { break; }
	$pid   = $pids[0];
	$title = html_entity_decode( wp_strip_all_tags( get_the_title( $pid ) ) );
	$text  = af_ptd_text( $pid );
	echo "  [{$code_of[$pid]}] #{$pid} " . mb_substr( $title, 0, 44 ) . "\n";
	echo "      " . ( $text !== '' ? mb_substr( $text, 0, 230 ) : '(no description at all)' ) . "\n";
	$csv[] = '"calibration",' . $pid . ',"' . str_replace( '"', '""', $code_of[ $pid ] ) . '","'
	       . str_replace( '"', '""', $title ) . '","' . str_replace( '"', '""', $text ) . '"';
}

echo "\n--- TO FIX: every product under a shared code ---\n\n";
$empty = 0; $total = 0;
foreach ( $shared as $key => $pids ) {
	foreach ( $pids as $pid ) {
		$total++;
		$title = html_entity_decode( wp_strip_all_tags( get_the_title( $pid ) ) );
		$text  = af_ptd_text( $pid );
		if ( $text === '' ) { $empty++; }
		echo "  [{$spell[$key]}] #{$pid} " . mb_substr( $title, 0, 44 ) . "\n";
		echo "      " . ( $text !== '' ? mb_substr( $text, 0, 230 ) : '(no description at all)' ) . "\n";
		$csv[] = '"to-fix",' . $pid . ',"' . str_replace( '"', '""', $spell[ $key ] ) . '","'
		       . str_replace( '"', '""', $title ) . '","' . str_replace( '"', '""', $text ) . '"';
	}
}

echo "\nproducts under a shared code: {$total}  |  with no description at all: {$empty}\n";

$up  = wp_get_upload_dir();
$dir = $up['basedir'] . '/taf-reports';
if ( ! is_dir( $dir ) ) { wp_mkdir_p( $dir ); }
if ( file_put_contents( $dir . '/product-text.csv', implode( "\n", $csv ) ) !== false ) {
	echo "full text: " . $up['baseurl'] . "/taf-reports/product-text.csv\n";
}
