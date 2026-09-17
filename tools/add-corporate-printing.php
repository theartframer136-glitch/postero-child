<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Add "Corporate Printing" as a category under Banners & Signage, and put it
 * in the header's CATEGORIES dropdown beside its siblings.
 *
 * WHAT THE DROPDOWN ACTUALLY IS, since it decides all of this: a WordPress nav
 * menu named "Category" (#25), not a list generated from the taxonomy. Its
 * rows are menu items of type=taxonomy pointing at product_cat terms, nested
 * under a custom "Categories" item (#6810). Banners & Signage is menu item
 * #5982 -> term 134, and its five existing children (Backdrops, Banner Stands,
 * Fabric / Cloth Banners, Fence Banners, Vinyl Banners) are menu items whose
 * parent is #5982. So creating the term alone would put nothing in the menu,
 * and adding a menu item alone would point at nothing. Both are needed.
 *
 * ORDER. The new item is appended with the highest menu_order in the menu.
 * WordPress sorts items by menu_order and then groups them under their parent,
 * so the highest order lands it LAST among its siblings — after Vinyl Banners
 * — without renumbering a single existing item. Nothing else in the menu moves.
 *
 * PRICING. af_pricing_applies() excludes anything under banners-signage, and
 * it already walks ancestors, so this category inherits that exclusion with no
 * change: the canvas size-and-frame engine will not try to price corporate
 * print jobs. That is the behaviour wanted, and it is worth stating because it
 * happens silently.
 *
 * IDEMPOTENT. Re-running finds the term and the menu item already correct and
 * reports so. Safe to run twice.
 *
 * Run: DRY=1 wp eval-file tools/add-corporate-printing.php --allow-root
 *      wp eval-file tools/add-corporate-printing.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$dry = (bool) getenv( 'DRY' );

$NAME        = 'Corporate Printing';
$SLUG        = 'corporate-printing';
$PARENT_SLUG = 'banners-signage';
$MENU_NAME   = 'Category';
$DESC        = 'Corporate signage and large-format print work: banners, standees, '
             . 'signage, stickers, business cards and event backdrops, printed and '
             . 'delivered from Delaware.';

echo '=== CORPORATE PRINTING ' . ( $dry ? '(DRY RUN — nothing is written)' : '(WRITING)' ) . " ===\n\n";

/* ---------- 1. the parent term ---------- */
$parent = get_term_by( 'slug', $PARENT_SLUG, 'product_cat' );
if ( ! $parent || is_wp_error( $parent ) ) { echo "ABORT: no product_cat with slug '{$PARENT_SLUG}'.\n"; return; }
printf( "  parent  : #%d %s (slug %s)\n", $parent->term_id, html_entity_decode( $parent->name ), $parent->slug );

/* ---------- 2. the term ---------- */
$term = get_term_by( 'slug', $SLUG, 'product_cat' );
$term_id = 0;

if ( $term && ! is_wp_error( $term ) ) {
    $term_id = (int) $term->term_id;
    printf( "  term    : already exists, #%d, parent #%d\n", $term_id, $term->parent );
    if ( (int) $term->parent !== (int) $parent->term_id ) {
        echo "            parent is wrong; " . ( $dry ? 'WOULD move' : 'moving' ) . " it under {$parent->slug}\n";
        if ( ! $dry ) wp_update_term( $term_id, 'product_cat', array( 'parent' => $parent->term_id ) );
    }
} else {
    // A term with this name may exist under a different slug — check before
    // creating a near-duplicate, which this catalogue already suffers from
    // (digital-downloads vs digital-downloads-2).
    $by_name = get_term_by( 'name', $NAME, 'product_cat' );
    if ( $by_name && ! is_wp_error( $by_name ) ) {
        $term_id = (int) $by_name->term_id;
        printf( "  term    : found by NAME instead, #%d (slug %s) — reusing it rather than making a second one\n",
            $term_id, $by_name->slug );
        if ( (int) $by_name->parent !== (int) $parent->term_id && ! $dry ) {
            wp_update_term( $term_id, 'product_cat', array( 'parent' => $parent->term_id ) );
        }
    } elseif ( $dry ) {
        echo "  term    : WOULD create '{$NAME}' (slug {$SLUG}) under #{$parent->term_id}\n";
    } else {
        $res = wp_insert_term( $NAME, 'product_cat', array(
            'slug' => $SLUG, 'parent' => (int) $parent->term_id, 'description' => $DESC,
        ) );
        if ( is_wp_error( $res ) ) { echo "ABORT: wp_insert_term failed — " . $res->get_error_message() . "\n"; return; }
        $term_id = (int) $res['term_id'];
        printf( "  term    : created #%d\n", $term_id );
    }
}

/* ---------- 3. term meta, matching what its siblings carry ---------- */
if ( $term_id && ! $dry ) {
    if ( get_term_meta( $term_id, 'display_type', true ) === '' ) {
        update_term_meta( $term_id, 'display_type', '' );   // "" = inherit the shop default, like the siblings
    }
    // Sit after the existing children rather than jumping to the front of any
    // widget that sorts on this meta.
    if ( get_term_meta( $term_id, 'order', true ) === '' ) {
        $sib_max = 0;
        foreach ( get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false,
                                    'parent' => $parent->term_id ) ) as $s ) {
            $o = (int) get_term_meta( $s->term_id, 'order', true );
            if ( $o > $sib_max ) $sib_max = $o;
        }
        update_term_meta( $term_id, 'order', $sib_max + 1 );
    }
    if ( get_term_meta( $term_id, 'rank_math_title', true ) === '' ) {
        update_term_meta( $term_id, 'rank_math_title', $NAME . ' – Shop Online | The Art Framer' );
    }
    if ( get_term_meta( $term_id, 'rank_math_description', true ) === '' ) {
        update_term_meta( $term_id, 'rank_math_description', wp_trim_words( $DESC, 26, '' ) );
    }
    if ( get_term_description( $term_id, 'product_cat' ) === '' ) {
        wp_update_term( $term_id, 'product_cat', array( 'description' => $DESC ) );
    }
}
if ( $term_id ) echo "  link    : " . get_term_link( (int) $term_id, 'product_cat' ) . "\n";

/* ---------- 4. the menu item ---------- */
$menu = wp_get_nav_menu_object( $MENU_NAME );
if ( ! $menu ) { echo "\nABORT: no nav menu named '{$MENU_NAME}'.\n"; return; }
printf( "\n  menu    : %s (#%d)\n", $menu->name, $menu->term_id );

$items = wp_get_nav_menu_items( $menu->term_id );
if ( ! $items ) { echo "ABORT: that menu has no items.\n"; return; }

// The menu item that represents the PARENT category — the one our new row
// must hang from.
$parent_item = null;
foreach ( $items as $i ) {
    if ( $i->type === 'taxonomy' && $i->object === 'product_cat' && (int) $i->object_id === (int) $parent->term_id ) {
        $parent_item = $i; break;
    }
}
if ( ! $parent_item ) { echo "ABORT: '{$parent->name}' is not in the '{$MENU_NAME}' menu, so there is nothing to nest under.\n"; return; }
printf( "  under   : menu item #%d \"%s\"\n", $parent_item->ID, html_entity_decode( $parent_item->title ) );

$siblings = array();
foreach ( $items as $i ) if ( (int) $i->menu_item_parent === (int) $parent_item->ID ) $siblings[] = $i;
echo "  already there:\n";
foreach ( $siblings as $s ) printf( "      #%-6d %-26s order=%d\n", $s->ID, html_entity_decode( $s->title ), $s->menu_order );

$existing = null;
foreach ( $items as $i ) {
    if ( $i->type === 'taxonomy' && $i->object === 'product_cat' && $term_id && (int) $i->object_id === (int) $term_id ) {
        $existing = $i; break;
    }
}

$max_order = 0;
foreach ( $items as $i ) if ( (int) $i->menu_order > $max_order ) $max_order = (int) $i->menu_order;

if ( $existing ) {
    printf( "\n  menu row: already present as #%d (parent #%s, order %d)\n",
        $existing->ID, $existing->menu_item_parent, $existing->menu_order );
    if ( (int) $existing->menu_item_parent !== (int) $parent_item->ID ) {
        echo "            but nested under the wrong item; " . ( $dry ? 'WOULD fix' : 'fixing' ) . "\n";
        if ( ! $dry ) {
            wp_update_nav_menu_item( $menu->term_id, $existing->ID, array(
                'menu-item-parent-id' => $parent_item->ID,
                'menu-item-object'    => 'product_cat',
                'menu-item-object-id' => $term_id,
                'menu-item-type'      => 'taxonomy',
                'menu-item-status'    => 'publish',
            ) );
        }
    } else {
        echo "            correctly placed. Nothing to do.\n";
    }
} elseif ( $dry ) {
    printf( "\n  menu row: WOULD add \"%s\" -> term #%s, nested under #%d, at order %d (last in the menu,\n",
        $NAME, $term_id ?: '(new)', $parent_item->ID, $max_order + 1 );
    echo "            which renders it last among the siblings above without renumbering any of them)\n";
} else {
    $new_id = wp_update_nav_menu_item( $menu->term_id, 0, array(
        'menu-item-title'     => $NAME,
        'menu-item-object'    => 'product_cat',
        'menu-item-object-id' => $term_id,
        'menu-item-type'      => 'taxonomy',
        'menu-item-parent-id' => $parent_item->ID,
        'menu-item-position'  => $max_order + 1,
        'menu-item-status'    => 'publish',
    ) );
    if ( is_wp_error( $new_id ) ) { echo "ABORT: wp_update_nav_menu_item failed — " . $new_id->get_error_message() . "\n"; return; }
    printf( "\n  menu row: added #%d under #%d at order %d\n", $new_id, $parent_item->ID, $max_order + 1 );
}

if ( ! $dry ) {
    delete_transient( 'wc_term_counts' );
    wp_cache_flush();
    if ( function_exists( 'do_action' ) ) do_action( 'litespeed_purge_all' );
    echo "\n  caches cleared (term counts, object cache, LiteSpeed).\n";
}

/* ---------- 5. show the result ---------- */
echo "\n=== HOW THE SUBMENU READS NOW ===\n";
$after = $dry ? $items : wp_get_nav_menu_items( $menu->term_id, array( 'update_post_term_cache' => false ) );
$rows  = array();
foreach ( (array) $after as $i ) if ( (int) $i->menu_item_parent === (int) $parent_item->ID ) $rows[] = $i;
usort( $rows, function ( $a, $b ) { return $a->menu_order - $b->menu_order; } );
printf( "  %s\n", html_entity_decode( $parent_item->title ) );
foreach ( $rows as $r ) printf( "      └ %s\n", html_entity_decode( $r->title ) );
if ( $dry ) printf( "      └ %s   <-- would be added here\n", $NAME );
