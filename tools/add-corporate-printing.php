<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * "Corporate Printing" as a category of its own, listed directly after
 * Banners & Signage in the header's CATEGORIES dropdown.
 *
 * It was first built as a CHILD of Banners & Signage; the owner wants it as a
 * separate category instead. This moves it rather than making a second one, so
 * the term keeps its id, its slug and anything already filed under it.
 *
 * Two moves, because the dropdown is a WordPress nav menu ("Category", #25)
 * and not a list generated from the taxonomy:
 *   1. the TERM's parent goes to 0, so it is top level in WooCommerce and its
 *      archive sits at /product-category/corporate-printing/
 *   2. the MENU ROW moves from under the Banners & Signage row to under the
 *      "Categories" wrapper, which is where the ten top-level rows live
 *
 * POSITION. Sitting "after Banners & Signage" means after that row AND after
 * its five children, since WordPress lays a menu out as one flat ordered list
 * and nests it afterwards. So the target position is one past the last item of
 * that subtree, and every item from there on shifts up by one. That shift
 * changes only integers: no item changes parent, title, URL or rendered
 * neighbour. It is exactly what dragging the row in the menu editor does.
 *
 * IDEMPOTENT. Re-running sees the term already top level and the row already
 * placed, and reports so without touching anything.
 *
 * Run: DRY=1 wp eval-file tools/add-corporate-printing.php --allow-root
 *      wp eval-file tools/add-corporate-printing.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$dry = (bool) getenv( 'DRY' );

$NAME      = 'Corporate Printing';
$SLUG      = 'corporate-printing';
$AFTER     = 'banners-signage';     // the category it must follow in the list
$MENU_NAME = 'Category';
$DESC      = 'Corporate signage and large-format print work: banners, standees, '
           . 'signage, stickers, business cards and event backdrops, printed and '
           . 'delivered from Delaware.';

echo '=== CORPORATE PRINTING, TOP LEVEL ' . ( $dry ? '(DRY RUN — nothing is written)' : '(WRITING)' ) . " ===\n\n";

/* ---------- the term ---------- */
$term = get_term_by( 'slug', $SLUG, 'product_cat' );
if ( ! $term || is_wp_error( $term ) ) $term = get_term_by( 'name', $NAME, 'product_cat' );

if ( $term && ! is_wp_error( $term ) ) {
    $term_id = (int) $term->term_id;
    printf( "  term    : #%d %s (slug %s), parent is currently %d\n",
        $term_id, html_entity_decode( $term->name ), $term->slug, $term->parent );
    if ( (int) $term->parent !== 0 ) {
        echo "            " . ( $dry ? 'WOULD move' : 'moving' ) . " it to top level\n";
        if ( ! $dry ) {
            $r = wp_update_term( $term_id, 'product_cat', array( 'parent' => 0 ) );
            if ( is_wp_error( $r ) ) { echo "ABORT: " . $r->get_error_message() . "\n"; return; }
        }
    } else {
        echo "            already top level\n";
    }
} elseif ( $dry ) {
    echo "  term    : WOULD create '{$NAME}' (slug {$SLUG}) at top level\n";
    $term_id = 0;
} else {
    $res = wp_insert_term( $NAME, 'product_cat', array( 'slug' => $SLUG, 'parent' => 0, 'description' => $DESC ) );
    if ( is_wp_error( $res ) ) { echo "ABORT: wp_insert_term failed — " . $res->get_error_message() . "\n"; return; }
    $term_id = (int) $res['term_id'];
    printf( "  term    : created #%d at top level\n", $term_id );
}

$after_term = get_term_by( 'slug', $AFTER, 'product_cat' );

if ( $term_id && ! $dry ) {
    // Sit directly after Banners & Signage anywhere that sorts on this meta.
    $after_order = $after_term ? (int) get_term_meta( $after_term->term_id, 'order', true ) : 0;
    if ( get_term_meta( $term_id, 'order', true ) === '' || $after_order ) {
        update_term_meta( $term_id, 'order', $after_order + 1 );
    }
    if ( get_term_meta( $term_id, 'rank_math_title', true ) === '' ) {
        update_term_meta( $term_id, 'rank_math_title', $NAME . ' – Shop Online | The Art Framer' );
    }
    if ( get_term_meta( $term_id, 'rank_math_description', true ) === '' ) {
        update_term_meta( $term_id, 'rank_math_description', wp_trim_words( $DESC, 26, '' ) );
    }
    $tobj = get_term( $term_id, 'product_cat' );
    if ( $tobj && ! is_wp_error( $tobj ) && trim( (string) $tobj->description ) === '' ) {
        wp_update_term( $term_id, 'product_cat', array( 'description' => $DESC ) );
    }
    echo "  link    : " . get_term_link( $term_id, 'product_cat' ) . "\n";
}

/* ---------- the menu row ---------- */
$menu = wp_get_nav_menu_object( $MENU_NAME );
if ( ! $menu ) { echo "\nABORT: no nav menu named '{$MENU_NAME}'.\n"; return; }
$items = wp_get_nav_menu_items( $menu->term_id );
if ( ! $items ) { echo "\nABORT: that menu is empty.\n"; return; }
printf( "\n  menu    : %s (#%d), %d items\n", $menu->name, $menu->term_id, count( $items ) );

// The row for Banners & Signage, and the wrapper it hangs from.
$after_item = null;
foreach ( $items as $i ) {
    if ( $i->type === 'taxonomy' && $i->object === 'product_cat'
         && $after_term && (int) $i->object_id === (int) $after_term->term_id ) { $after_item = $i; break; }
}
if ( ! $after_item ) { echo "ABORT: Banners & Signage is not in this menu.\n"; return; }
$wrapper_id = (int) $after_item->menu_item_parent;
printf( "  after   : menu item #%d \"%s\" (order %d), which hangs from wrapper #%d\n",
    $after_item->ID, html_entity_decode( $after_item->title ), $after_item->menu_order, $wrapper_id );

// "After Banners & Signage" means after its whole subtree.
$subtree = array( (int) $after_item->ID );
$grew = true;
while ( $grew ) {
    $grew = false;
    foreach ( $items as $i ) {
        if ( in_array( (int) $i->menu_item_parent, $subtree, true ) && ! in_array( (int) $i->ID, $subtree, true ) ) {
            $subtree[] = (int) $i->ID; $grew = true;
        }
    }
}
$our = null;
foreach ( $items as $i ) {
    if ( $i->type === 'taxonomy' && $i->object === 'product_cat' && $term_id && (int) $i->object_id === (int) $term_id ) { $our = $i; break; }
}
$subtree = array_values( array_diff( $subtree, $our ? array( (int) $our->ID ) : array() ) );

$last = 0;
foreach ( $items as $i ) if ( in_array( (int) $i->ID, $subtree, true ) && (int) $i->menu_order > $last ) $last = (int) $i->menu_order;
$target = $last + 1;
printf( "  subtree : %d rows, last at order %d, so the target position is %d\n", count( $subtree ), $last, $target );

if ( $our && (int) $our->menu_item_parent === $wrapper_id && (int) $our->menu_order === $target ) {
    printf( "\n  menu row: #%d is already top level at order %d. Nothing to do.\n", $our->ID, $target );
} elseif ( $dry ) {
    printf( "\n  menu row: %s, hanging from wrapper #%d at order %d\n",
        $our ? "WOULD move #{$our->ID} (now under #{$our->menu_item_parent}, order {$our->menu_order})" : 'WOULD add a new row',
        $wrapper_id, $target );
    $shift = 0;
    foreach ( $items as $i ) if ( (int) $i->menu_order >= $target && ( ! $our || (int) $i->ID !== (int) $our->ID ) ) $shift++;
    printf( "            %d later rows would shift up by one position (numbering only — none changes parent, title or URL)\n", $shift );
} else {
    // Make room, then place. Only menu_order moves.
    foreach ( $items as $i ) {
        if ( $our && (int) $i->ID === (int) $our->ID ) continue;
        if ( (int) $i->menu_order >= $target ) {
            wp_update_post( array( 'ID' => $i->ID, 'menu_order' => (int) $i->menu_order + 1 ) );
        }
    }
    $new_id = wp_update_nav_menu_item( $menu->term_id, $our ? (int) $our->ID : 0, array(
        'menu-item-title'     => $NAME,
        'menu-item-object'    => 'product_cat',
        'menu-item-object-id' => $term_id,
        'menu-item-type'      => 'taxonomy',
        'menu-item-parent-id' => $wrapper_id,
        'menu-item-status'    => 'publish',
    ) );
    if ( is_wp_error( $new_id ) ) { echo "ABORT: " . $new_id->get_error_message() . "\n"; return; }
    wp_update_post( array( 'ID' => $new_id, 'menu_order' => $target ) );
    printf( "\n  menu row: #%d placed under wrapper #%d at order %d\n", $new_id, $wrapper_id, $target );

    delete_transient( 'wc_term_counts' );
    if ( function_exists( 'do_action' ) ) do_action( 'litespeed_purge_all' );
    echo "  caches cleared.\n";
}

/* ---------- show the result ---------- */
echo "\n=== THE TOP-LEVEL LIST NOW READS ===\n";
$after_items = $dry ? $items : wp_get_nav_menu_items( $menu->term_id, array( 'update_post_term_cache' => false ) );
$tops = array();
foreach ( (array) $after_items as $i ) if ( (int) $i->menu_item_parent === $wrapper_id ) $tops[] = $i;
usort( $tops, function ( $a, $b ) { return $a->menu_order - $b->menu_order; } );
foreach ( $tops as $t ) {
    printf( "  %-26s (order %d)%s\n", html_entity_decode( $t->title ), $t->menu_order,
        ( $term_id && $t->type === 'taxonomy' && (int) $t->object_id === (int) $term_id ) ? '   <-- this one' : '' );
}
if ( $dry ) printf( "  ...and %s would appear immediately after %s\n", $NAME, html_entity_decode( $after_item->title ) );
