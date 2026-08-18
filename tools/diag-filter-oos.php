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
 * Run: wp eval-file tools/diag-filter-oos.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

echo "=== FRAME / SIZE FILTERS (withdrawn options are hidden) ===\n";

$foos = function_exists( 'af_frames_out_of_stock' ) ? af_frames_out_of_stock() : array();
$soos = function_exists( 'af_sizes_out_of_stock' )  ? af_sizes_out_of_stock()  : array();
$fin  = function_exists( 'af_frames_in_stock' )     ? af_frames_in_stock()     : array();
$sin  = function_exists( 'af_sizes_offered' )       ? af_sizes_offered()       : array();
echo "frames on sale:  " . ( $fin  ? implode( ', ', $fin  ) : '(none)' ) . "\n";
echo "frames withdrawn: " . ( $foos ? implode( ', ', $foos ) : '(none)' ) . "\n";
echo "sizes on sale:   " . count( $sin ) . " — " . ( $sin ? implode( ', ', $sin ) : '(none)' ) . "\n";
echo "sizes withdrawn: " . count( $soos ) . "\n";
$oos = array_merge( $foos, $soos );
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

// Frames and sizes are two separate widgets, so look at one of each rather
// than reporting on whichever happens to come first.
// Anchor on an option still ON SALE, never on a withdrawn one.
//
// The first version anchored on a withdrawn name, which worked only while
// those names were still printed. Now that they are hidden the name is gone
// from the filter — but it is NOT gone from the page: product titles carry
// phrases like "Floating Frame", so the search matched a product title and
// the walk back to the enclosing <ul> landed on the digital-download feature
// list. Every withdrawn option was then reported "gone" from a block that
// never contained any of them, and 12-of-12 looked like a pass. A check that
// reports success from the wrong block is worse than no check.
//
// An on-sale option is guaranteed to be in the filter, so it can always be
// found, and the block it anchors is the one that matters.
$needles = array();
if ( $fin ) $needles['frame'] = $fin[0];
if ( $sin ) $needles['size']  = $sin[0];

foreach ( $needles as $what => $needle ) {
    echo "\n──── {$what} filter — anchored on '{$needle}' ────\n";
    af_ff_report( $html, $needle, $oos );
}
echo "\n=== DONE ===\n";
return;

function af_ff_report( $html, $needle, $oos ) {
$at = stripos( $html, $needle );
if ( $at === false ) {
    echo "'{$needle}' does not appear on this page at all — the filter may be\n";
    echo "elsewhere, or rendered in the browser. Nothing to report.\n";
    return;
}

// An option name can appear anywhere on the page — most often inside a
// product title — so finding it once proves nothing. Walk EVERY occurrence,
// take the list enclosing each, and keep only a list that is recognisably a
// filter. If none of them is, say so instead of reporting on whatever text
// happened to match.
$block = '';
$from  = 0;
$tried = 0;
while ( ( $at = stripos( $html, $needle, $from ) ) !== false && $tried < 40 ) {
    $tried++;
    $from = $at + 1;
    $open = strripos( substr( $html, 0, $at ), '<ul' );
    if ( $open === false ) continue;
    $depth = 0; $i = $open; $len = strlen( $html ); $cand = '';
    while ( $i < $len ) {
        if ( substr( $html, $i, 3 ) === '<ul' )       { $depth++; }
        elseif ( substr( $html, $i, 5 ) === '</ul>' ) {
            $depth--;
            if ( $depth === 0 ) { $cand = substr( $html, $open, $i + 5 - $open ); break; }
        }
        $i++;
    }
    if ( $cand === '' ) continue;
    // What makes a list a filter: it links back into the archive with a
    // filter_ parameter, or carries WooCommerce's layered-nav classes.
    if ( stripos( $cand, 'filter_' ) !== false || stripos( $cand, 'layered-nav' ) !== false ) {
        $block = $cand;
        break;
    }
}
if ( $block === '' ) {
    echo "'{$needle}' appears on the page ({$tried} time(s)) but never inside a\n";
    echo "filter list — no filter_ link and no layered-nav markup around any of\n";
    echo "them. Either the filter is not on this page, or it is built in the\n";
    echo "browser. INCONCLUSIVE — this is not evidence either way.\n";
    return;
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

// Withdrawn options are HIDDEN now, so the test is the opposite of what it
// used to be: the name should not be in the list at all. A name still present
// and a marker beside it means the PHP hook fired but the row is only being
// hidden in the browser — which works, but flashes on first paint, so it is
// worth knowing. A name present with no marker anywhere near it means nothing
// reached that row and the option is still on offer.
echo "\nwithdrawn options — should be absent from the list:\n";
$still = 0;
foreach ( $oos as $f ) {
    $pos = stripos( $block, $f );
    if ( $pos === false ) { printf( "  %-24s gone\n", $f ); continue; }
    $near   = substr( $block, max( 0, $pos - 400 ), 800 );
    $marker = stripos( $near, 'af-fx-gone' ) !== false || stripos( $near, 'af-fx-oos' ) !== false;
    $still++;
    printf( "  %-24s %s\n", $f, $marker
        ? 'still in the markup, marked — hidden in the browser'
        : 'STILL OFFERED  <<<  nothing reached this row' );
}
echo "  " . ( count( $oos ) - $still ) . " of " . count( $oos ) . " withdrawn options are out of the markup entirely\n";

echo "\noptions still on sale — should ALL be present and clickable:\n";
$sale = function_exists( 'af_frames_in_stock' ) && function_exists( 'af_sizes_offered' )
        ? array_merge( af_frames_in_stock(), af_sizes_offered() ) : array();
$missing = 0;
foreach ( $sale as $f ) {
    $pos = stripos( $block, $f );
    if ( $pos === false ) continue;               // belongs to the other widget
    $near = substr( $block, max( 0, $pos - 300 ), 600 );
    $ok   = stripos( $near, '<a ' ) !== false;
    if ( ! $ok ) $missing++;
    printf( "  %-24s %s\n", $f, $ok ? 'linked' : 'PRESENT BUT NOT LINKED  <<<' );
}
if ( $missing ) echo "  a size or frame you DO sell lost its link — that is a real fault\n";
}
