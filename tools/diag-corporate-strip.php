<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. Why is Corporate Printing missing from the Shop by Collection
 * strip when it is in the menu and the sidebar?
 *
 * The strip renders ten tabs and this is not one of them. Three candidates,
 * and they need different fixes, so they are told apart here rather than
 * guessed at:
 *   1. the term is not top-level (a child never reaches that strip);
 *   2. it holds no products and the strip's term query hides empty terms -
 *      which is exactly what happened to Embossed Prints;
 *   3. the strip takes a fixed number of terms, or a list chosen elsewhere,
 *      and this one falls outside it.
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$SLUG = 'corporate-printing';
echo "=== THE CATEGORY ===\n";
$t = get_term_by( 'slug', $SLUG, 'product_cat' );
if ( ! $t || is_wp_error( $t ) ) { echo "ABORT: no product_cat '{$SLUG}'.\n"; return; }
printf( "  #%d  \"%s\"  slug=%s  parent=%d  products=%d\n",
    $t->term_id, html_entity_decode( $t->name ), $t->slug, $t->parent, $t->count );
printf( "  top-level : %s\n", (int) $t->parent === 0 ? 'yes' : 'NO - it is a child, which is candidate 1' );
$thumb = (int) get_term_meta( $t->term_id, 'thumbnail_id', true );
printf( "  thumbnail : %s\n", $thumb ? '#' . $thumb : 'NONE' );
$kids = get_terms( array( 'taxonomy' => 'product_cat', 'parent' => $t->term_id, 'hide_empty' => false ) );
printf( "  children  : %d\n", is_array( $kids ) ? count( $kids ) : 0 );
foreach ( (array) $kids as $k ) printf( "      #%-5d %-26s %d products\n", $k->term_id, html_entity_decode( $k->name ), $k->count );

echo "\n=== WHAT A TOP-LEVEL TERM QUERY RETURNS ===\n";
foreach ( array( true, false ) as $hide ) {
    $terms = get_terms( array(
        'taxonomy' => 'product_cat', 'parent' => 0,
        'hide_empty' => $hide, 'orderby' => 'name', 'order' => 'ASC',
    ) );
    printf( "\n  hide_empty = %s  ->  %d terms\n", $hide ? 'true' : 'false', is_wp_error( $terms ) ? -1 : count( $terms ) );
    if ( is_wp_error( $terms ) ) continue;
    foreach ( $terms as $x ) {
        $mine = $x->slug === $SLUG ? '   <-- CORPORATE PRINTING' : '';
        printf( "      %-28s %-24s %3d products%s\n", html_entity_decode( $x->name ), $x->slug, $x->count, $mine );
    }
}

echo "\n=== THE ORDER THE STRIP SHOWS ===\n";
echo "  The live strip reads: DIGITAL CANVAS PRINTS, EMBOSSED PRINTS, ART ACCESSORIES,\n";
echo "  BANNERS & SIGNAGE, FRAMED CANVASES, DIRECT FROM ARTISTS, DIGITAL DOWNLOADS,\n";
echo "  HOME DECOR BY SPACE, PERSONALIZED PRINTS, GIFTS  (10 tabs).\n";
echo "  That is NOT alphabetical, so the list is ordered by something else -\n";
echo "  menu_order, term_order, or a list held in the theme's own settings.\n\n";
foreach ( array( 'menu_order', 'term_order' ) as $ob ) {
    $terms = get_terms( array( 'taxonomy' => 'product_cat', 'parent' => 0, 'hide_empty' => true, 'orderby' => $ob ) );
    if ( is_wp_error( $terms ) ) { printf( "  orderby=%-12s -> error\n", $ob ); continue; }
    printf( "  orderby=%-12s -> %s\n", $ob, implode( ', ', array_map( function( $x ) { return html_entity_decode( $x->name ); }, $terms ) ) );
}

echo "\n=== IS THE EMBOSSED INJECTION THE ONLY REASON IT SHOWS? ===\n";
if ( function_exists( 'af_goldfoil_term' ) ) {
    $g = af_goldfoil_term();
    printf( "  Embossed Prints: #%d products=%d  -- it is injected by inc/goldfoil-collection.php\n",
        $g ? $g->term_id : 0, $g ? $g->count : -1 );
    echo "  If Embossed has 0 products and still shows, the strip hides empty terms and\n";
    echo "  the injection is what puts it back. Corporate Printing has no such injection.\n";
}
