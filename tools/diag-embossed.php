<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. Two questions the fix depends on, neither of which can be
 * answered from the repo:
 *
 *  1. What is the category ACTUALLY called in the database now? The rename
 *     script was run, but af_goldfoil_name() still returns a hardcoded
 *     "Gold Foiled & UV", and that constant is what feeds the Shop by
 *     Collection tab's label. If the term reads "Embossed Prints" then the
 *     constant is the whole of the first fault.
 *
 *  2. What children does it have, and how many products does each hold? The
 *     circle row under the tab drops any child whose count is 0, and the
 *     three finish categories are empty until the artwork is uploaded. If
 *     there are also SUBJECT children left over from the earlier pass, the
 *     row would mix two schemes, and that has to be seen before it is
 *     changed rather than after.
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$SLUG = 'gold-foiled-uv';
echo "=== THE CATEGORY ===\n";
$term = get_term_by( 'slug', $SLUG, 'product_cat' );
if ( ! $term || is_wp_error( $term ) ) { echo "ABORT: no product_cat '{$SLUG}'.\n"; return; }
printf( "  #%d  name in DB : \"%s\"\n", $term->term_id, html_entity_decode( $term->name ) );
printf( "      slug       : %s\n", $term->slug );
printf( "      products   : %d\n", $term->count );
printf( "      parent     : %d\n", $term->parent );
if ( function_exists( 'af_goldfoil_name' ) ) {
    printf( "\n  af_goldfoil_name() returns: \"%s\"%s\n", af_goldfoil_name(),
        html_entity_decode( $term->name ) === af_goldfoil_name() ? '' : '   <-- DOES NOT MATCH THE DATABASE' );
}

echo "\n=== ITS CHILDREN ===\n";
$kids = get_terms( array( 'taxonomy' => 'product_cat', 'parent' => $term->term_id, 'hide_empty' => false, 'orderby' => 'name' ) );
if ( is_wp_error( $kids ) || ! $kids ) { echo "  none\n"; }
else {
    $finish = array( 'gold-foil-prints', 'uv-prints', 'mixed-prints' );
    foreach ( $kids as $k ) {
        $thumb = (int) get_term_meta( $k->term_id, 'thumbnail_id', true );
        printf( "  #%-5d %-26s slug=%-20s %2d products  thumb=%s  %s\n",
            $k->term_id, html_entity_decode( $k->name ), $k->slug, $k->count,
            $thumb ? '#' . $thumb : 'NONE',
            in_array( $k->slug, $finish, true ) ? 'finish' : 'SUBJECT (other scheme)' );
    }
}

echo "\n=== WHAT THE CIRCLE ROW WOULD SHOW TODAY ===\n";
if ( function_exists( 'af_goldfoil_collection_payload' ) ) {
    $p = af_goldfoil_collection_payload();
    if ( ! $p ) echo "  payload is null — the tab would not be drawn at all\n";
    else {
        printf( "  tab label : \"%s\"\n", $p['label'] );
        printf( "  circles   : %d\n", count( $p['circles'] ) );
        foreach ( $p['circles'] as $c )
            printf( "      %-30s slug=%-20s img=%s\n", $c['n'], $c['s'] === '' ? '(a product)' : $c['s'], $c['i'] ? 'yes' : 'NONE' );
        if ( ! $p['circles'] ) echo "      (empty — every child has 0 products and there are no products to fall back to)\n";
    }
} else echo "  af_goldfoil_collection_payload() not loaded\n";

echo "\n=== THE MENU ROW (for comparison — the dropdown is already right) ===\n";
$menu = wp_get_nav_menu_object( 'Category' );
if ( $menu ) {
    foreach ( (array) wp_get_nav_menu_items( $menu->term_id ) as $i ) {
        if ( $i->type === 'taxonomy' && $i->object === 'product_cat' && (int) $i->object_id === (int) $term->term_id )
            printf( "  row #%d titled \"%s\"\n", $i->ID, html_entity_decode( $i->title ) );
    }
}
