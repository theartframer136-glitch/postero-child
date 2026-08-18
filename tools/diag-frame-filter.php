<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * READ-ONLY. Finds the frame filter on a real category page and prints the
 * markup that ships with it.
 *
 * Why ask the page: the sidebar filter listing Aluminium / Fibre / Floating /
 * Without Frame is not markup this child theme builds, so which widget draws
 * it decides whether the PHP route (WooCommerce's layered-nav term filter)
 * reaches it or whether the label sweep in the footer is doing all the work.
 * Guessing at that is exactly what cost this project three deploys on the
 * struck-through price.
 *
 * Reports, for each out-of-stock frame, whether the delivered HTML already
 * carries the out-of-stock marking. A "no" is not a failure — it means the
 * marking is applied in the browser, which this cannot see — but it does say
 * the PHP hook did not match, which is worth knowing.
 *
 * Makes no changes.
 *
 * Run: wp eval-file tools/diag-frame-filter.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

echo "=== FRAME FILTER ===\n";

$oos = function_exists( 'af_frames_out_of_stock' ) ? af_frames_out_of_stock() : array();
$in  = function_exists( 'af_frames_in_stock' )     ? af_frames_in_stock()     : array();
echo "in stock:     " . ( $in  ? implode( ', ', $in  ) : '(none)' ) . "\n";
echo "out of stock: " . ( $oos ? implode( ', ', $oos ) : '(none)' ) . "\n";
if ( ! $oos ) { echo "nothing to mark.\n=== DONE ===\n"; return; }

// a category page with products on it, which is where the filter lives
$term = null;
foreach ( get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true,
                            'orderby' => 'count', 'order' => 'DESC', 'number' => 5 ) ) as $t ) {
    if ( (int) $t->count >= 4 ) { $term = $t; break; }
}
$url = $term ? get_term_link( $term ) : get_permalink( wc_get_page_id( 'shop' ) );
if ( is_wp_error( $url ) || ! $url ) { echo "no page to fetch\n=== DONE ===\n"; return; }
$url = add_query_arg( 'afnocache', time(), $url );
echo "page: {$url}\n";

$resp = null;
for ( $i = 0; $i < 3; $i++ ) {
    $resp = wp_remote_get( $url, array( 'timeout' => 45, 'sslverify' => false,
        'headers' => array( 'User-Agent' => 'af-frame-diag' ) ) );
    if ( ! is_wp_error( $resp )
         && ! in_array( (int) wp_remote_retrieve_response_code( $resp ), array( 508, 503, 429, 502, 504 ), true ) ) break;
    sleep( 5 );
}
if ( is_wp_error( $resp ) ) { echo "FETCH ERROR: " . $resp->get_error_message() . "  (inconclusive)\n=== DONE ===\n"; return; }
$code = (int) wp_remote_retrieve_response_code( $resp );
$html = wp_remote_retrieve_body( $resp );
echo "http {$code} | length " . strlen( $html ) . "\n";
if ( $code !== 200 ) { echo "the host did not render the page — inconclusive\n=== DONE ===\n"; return; }

// Where does the first out-of-stock frame name appear, and what is around it?
$needle = $oos[0];
$at = stripos( $html, $needle );
if ( $at === false ) {
    echo "'{$needle}' does not appear on this page at all — the filter may be\n";
    echo "elsewhere, or rendered in the browser. Nothing to report.\n=== DONE ===\n";
    return;
}

// walk out to the enclosing list, so the whole filter is shown rather than
// one row torn out of it
$open = strripos( substr( $html, 0, $at ), '<ul' );
$block = '';
if ( $open !== false ) {
    $depth = 0; $i = $open; $len = strlen( $html );
    while ( $i < $len ) {
        if ( substr( $html, $i, 3 ) === '<ul' )       { $depth++; }
        elseif ( substr( $html, $i, 5 ) === '</ul>' ) { $depth--; if ( $depth === 0 ) { $block = substr( $html, $open, $i + 5 - $open ); break; } }
        $i++;
    }
}
if ( $block === '' ) {                       // not a list — take a window round it
    $from  = max( 0, $at - 600 );
    $block = substr( $html, $from, 1600 );
}

$out = preg_replace( '/>\s+</', '><', $block );
$out = preg_replace( '/\s+/', ' ', $out );
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
echo "\nfilter markup as delivered:\n";
foreach ( array_slice( $lines, 0, 80 ) as $l ) echo "  " . substr( $l, 0, 240 ) . "\n";
if ( count( $lines ) > 80 ) echo "  … " . ( count( $lines ) - 80 ) . " more lines\n";

echo "\nmarking already present in the delivered HTML:\n";
foreach ( $oos as $f ) {
    $pos = stripos( $block, $f );
    $marked = $pos !== false && stripos( substr( $block, max( 0, $pos - 400 ), 800 ), 'af-fx-oos' ) !== false;
    printf( "  %-20s %s\n", $f, $pos === false ? 'not in this block'
        : ( $marked ? 'yes — the PHP hook reached it'
                    : 'no  — marked in the browser instead' ) );
}
echo "\n=== DONE ===\n";
