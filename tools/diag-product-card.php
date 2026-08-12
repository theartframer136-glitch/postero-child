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
echo "top-level blocks inside the card, in document order:\n";

// walk the card's immediate children by tracking tag depth
$depth = 0; $blocks = array(); $cur = null;
$n = strlen( $card ); $i = 0;
while ( $i < $n ) {
    if ( $card[ $i ] === '<' && preg_match( '#^</?([a-z0-9]+)#i', substr( $card, $i, 20 ), $tm ) ) {
        $tag     = strtolower( $tm[1] );
        $closing = $card[ $i + 1 ] === '/';
        $void    = in_array( $tag, array( 'img', 'br', 'hr', 'input', 'source', 'path', 'use', 'meta' ), true );
        $end     = strpos( $card, '>', $i );
        if ( $end === false ) break;
        $selfclose = $card[ $end - 1 ] === '/';

        if ( ! $closing && ! $void && ! $selfclose ) {
            $depth++;
            if ( $depth === 2 && $cur === null ) {
                $cur = array( 'tag' => $tag, 'open' => substr( $card, $i, min( 160, $end - $i + 1 ) ), 'start' => $i );
            }
        } elseif ( $closing ) {
            $depth--;
            if ( $depth === 1 && $cur !== null ) {
                $cur['html'] = substr( $card, $cur['start'], $end + 1 - $cur['start'] );
                $blocks[] = $cur; $cur = null;
            }
        }
        $i = $end + 1;
        continue;
    }
    $i++;
}

foreach ( $blocks as $k => $b ) {
    $imgs  = preg_match_all( '#<img\b#i', $b['html'] );
    $text  = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $b['html'] ) ) );
    echo sprintf( "\n  [%d] <%s>  imgs:%d  bytes:%d\n", $k + 1, $b['tag'], $imgs, strlen( $b['html'] ) );
    echo "      open: " . preg_replace( '/\s+/', ' ', $b['open'] ) . "\n";
    echo "      text: " . ( $text === '' ? '(none)' : substr( $text, 0, 90 ) ) . "\n";
}

echo "\nreading: a block AFTER the first image-carrying block that ALSO carries an\n";
echo "image is the in-the-room preview stacking in flow — the blank band. A block\n";
echo "with no image and no text is dead space of some other kind.\n";
echo "=== DONE ===\n";
