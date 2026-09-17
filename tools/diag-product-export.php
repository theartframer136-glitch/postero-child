<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Every product as four columns: art code, category, sub-category, link.
 *
 * Read-only. Nothing is written to the catalogue.
 *
 * The rows come back gzipped and base64'd rather than printed plainly. 424
 * products of readable text is about 60KB of log, and this log truncates from
 * the FRONT — a big dump erases every check that ran before it, which has cost
 * this project whole deploys' worth of output before. Compressed it is a few
 * kilobytes. The same trick is used by tools/diag-product-contact-sheets.php,
 * for the same reason.
 *
 * A product sits in as many categories as it likes. TOP-LEVEL terms (no parent)
 * go in the category column and CHILD terms in the sub-category column, each
 * joined with " | " when there is more than one, so nothing is silently dropped
 * by picking a "primary" that WooCommerce does not really have.
 *
 * Run: wp eval-file tools/diag-product-export.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit( 1 ); }

echo "=== PRODUCT EXPORT: art code, category, sub-category, link ===\n";

$ids = get_posts( array(
	'post_type'      => 'product',
	'post_status'    => 'any',
	'posts_per_page' => -1,
	'fields'         => 'ids',
	'orderby'        => 'ID',
	'order'          => 'ASC',
) );

$rows    = array();
$rows[]  = implode( "\t", array( 'art_code', 'category', 'sub_category', 'link', 'product_id', 'title', 'status' ) );
$nocode  = 0;
$nolink  = 0;

foreach ( $ids as $pid ) {
	$code = trim( (string) get_post_meta( $pid, '_taf_art_code', true ) );
	if ( $code === '' ) { $nocode++; }

	$tops = array();
	$subs = array();
	$terms = get_the_terms( $pid, 'product_cat' );
	if ( is_array( $terms ) ) {
		foreach ( $terms as $t ) {
			if ( (int) $t->parent === 0 ) { $tops[] = $t->name; }
			else                          { $subs[] = $t->name; }
		}
	}
	sort( $tops ); sort( $subs );

	$link = get_permalink( $pid );
	if ( ! $link ) { $link = ''; $nolink++; }

	$title = html_entity_decode( wp_strip_all_tags( (string) get_the_title( $pid ) ) );

	// Tabs and newlines would break the row apart; a category name has neither,
	// but a title can, so every field is flattened rather than trusted.
	$cell = function ( $s ) {
		return trim( preg_replace( '/\s+/u', ' ', str_replace( array( "\t", "\r", "\n" ), ' ', (string) $s ) ) );
	};

	$rows[] = implode( "\t", array(
		$cell( $code ),
		$cell( implode( ' | ', $tops ) ),
		$cell( implode( ' | ', $subs ) ),
		$cell( $link ),
		(int) $pid,
		$cell( $title ),
		$cell( get_post_status( $pid ) ),
	) );
}

echo 'products: ' . count( $ids ) . "\n";
echo '  without an art code: ' . $nocode . "\n";
echo '  without a link:      ' . $nolink . "\n";

$tsv = implode( "\n", $rows );
$b64 = base64_encode( gzencode( $tsv, 9 ) );

echo 'rows (including the header): ' . count( $rows ) . "\n";
echo 'raw bytes: ' . strlen( $tsv ) . '  |  gzipped+base64: ' . strlen( $b64 ) . "\n";
echo "\n--- gzipped TSV, base64, 200 chars per line ---\n";
foreach ( str_split( $b64, 200 ) as $chunk ) {
	echo 'B64 ' . $chunk . "\n";
}
echo "--- end ---\n";
echo "=== DONE ===\n";
