<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * READ-ONLY. Fetches a populated category page and prints the structure of one
 * product card: every top-level block inside the <li>, in order, with whether
 * it carries an image and roughly how tall it is asking to be.
 *
 * Why: the cards on the category grid have a large blank band between the
 * artwork and the title. The related row had the same fault and it was the
 * card template's second "in the room" preview falling into normal flow. That
 * fix was scoped to the related row. Whether the category grid has the same
 * shape is a question about markup, and guessing at markup is what has cost
 * this project several deploys — so this asks the page.
 *
 * Makes no changes.
 *
 * Run: wp eval-file tools/diag-product-card.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

echo "=== PRODUCT CARD STRUCTURE ===\n";

// a category that actually has cards in it
$term = null;
foreach ( get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC', 'number' => 5 ) ) as $t ) {
    if ( (int) $t->count >= 4 ) { $term = $t; break; }
}
if ( ! $term ) { echo "no category with 4+ products\n=== DONE ===\n"; return; }

$link = get_term_link( $term );
if ( is_wp_error( $link ) ) { echo "no permalink for {$term->name}\n=== DONE ===\n"; return; }
$url  = add_query_arg( 'afnocache', time(), $link );
echo "category: {$term->name} ({$term->count} products)\n{$url}\n";

$resp = null;
for ( $i = 0; $i < 3; $i++ ) {
    $resp = wp_remote_get( $url, array( 'timeout' => 45, 'sslverify' => false,
        'headers' => array( 'User-Agent' => 'af-card-diag' ) ) );
    if ( ! is_wp_error( $resp ) && (int) wp_remote_retrieve_response_code( $resp ) === 200 ) break;
    sleep( 5 );
}
if ( is_wp_error( $resp ) ) { echo "FETCH ERROR: " . $resp->get_error_message() . "\n=== DONE ===\n"; return; }
$code = (int) wp_remote_retrieve_response_code( $resp );
$html = wp_remote_retrieve_body( $resp );
echo "http {$code} | length " . strlen( $html ) . "\n";
if ( $code !== 200 ) { echo "the host did not render the page — inconclusive\n=== DONE ===\n"; return; }

// first <li ... class="... product ..."> and its matching close
$pos = 0; $card = '';
while ( preg_match( '#<li[^>]*class="[^"]*\bproduct\b[^"]*"[^>]*>#i', $html, $m, PREG_OFFSET_CAPTURE, $pos ) ) {
    $start = $m[0][1];
    $depth = 0; $i = $start; $len = strlen( $html );
    while ( $i < $len ) {
        if ( substr( $html, $i, 3 ) === '<li' )       { $depth++; }
        elseif ( substr( $html, $i, 5 ) === '</li>' ) { $depth--; if ( $depth === 0 ) { $card = substr( $html, $start, $i + 5 - $start ); break; } }
        $i++;
    }
    if ( $card !== '' ) break;
    $pos = $start + 4;
}
if ( $card === '' ) { echo "no li.product found in the page\n=== DONE ===\n"; return; }

echo "card length: " . strlen( $card ) . " bytes\n\n";

// The first pass printed only the card's top-level blocks and found exactly
// one — a single .product-block wrapping everything — so the blank band is
// somewhere inside it and one level of structure cannot show where. The card
// is small enough to print outright, which ends the guessing: the markup is
// the answer, not a summary of it.
$out = preg_replace( '/>\s+</', '><', $card );      // drop inter-tag whitespace
$out = preg_replace( '/\s+/', ' ', $out );          // collapse the rest
// one tag per line, indented by depth, so the nesting is readable
$depth = 0; $lines = array();
foreach ( preg_split( '/(?=<)/', $out ) as $chunk ) {
    if ( $chunk === '' ) continue;
    $closing = strpos( $chunk, '</' ) === 0;
    if ( $closing ) $depth = max( 0, $depth - 1 );
    $lines[] = str_repeat( '  ', $depth ) . trim( $chunk );
    if ( ! $closing
         && preg_match( '#^<([a-z0-9]+)#i', $chunk, $tm )
         && ! in_array( strtolower( $tm[1] ), array( 'img','br','hr','input','source','path','use','meta' ), true )
         && substr( rtrim( explode( '>', $chunk )[0] ), -1 ) !== '/' ) {
        $depth++;
    }
}
echo "card markup as delivered:\n";
foreach ( $lines as $l ) echo "  " . substr( $l, 0, 300 ) . "\n";

echo "\n=== DONE ===\n";
