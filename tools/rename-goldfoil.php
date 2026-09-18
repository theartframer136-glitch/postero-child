<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Rename "Gold Foiled & UV" to "Embossed Prints" and give it three
 * sub-categories: Gold Foil Prints, UV Prints, Mixed Prints.
 *
 * THE SLUG DOES NOT CHANGE. inc/gold-foil.php hardcodes af_goldfoil_slug() as
 * 'gold-foiled-uv', and its stylesheet targets .term-gold-foiled-uv and
 * li[class*="product_cat-gold-foiled-uv"]. Changing the slug would silently
 * break the category's own styling and its homepage band, and would change a
 * live URL for no gain. Only the visible name moves.
 *
 * THE EXISTING CHILDREN ARE THE THING TO WATCH. tools/goldfoil-subcategories.php
 * builds children of this category BY SUBJECT — Radha Krishna, Lord Shiva,
 * Seven Horses and so on — and af_goldfoil_collection_payload() draws those
 * children as the circle row under the homepage collection tab. Adding three
 * children by TECHNIQUE puts two different classification schemes under one
 * parent, and that circle row would then mix them. The dry run prints every
 * existing child so that is a decision taken with eyes open, not a surprise.
 *
 * Run: DRY=1 wp eval-file tools/rename-goldfoil.php --allow-root
 *      wp eval-file tools/rename-goldfoil.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$dry      = (bool) getenv( 'DRY' );
$NEW_NAME = getenv( 'NEW_NAME' ) ?: 'Embossed Prints';
$SLUG     = 'gold-foiled-uv';
$MENU     = 'Category';
$KIDS = array(
    array( 'name' => 'Gold Foil Prints', 'slug' => 'gold-foil-prints',
           'desc' => 'Artwork finished with real gold foil detailing, applied by hand over the print.' ),
    array( 'name' => 'UV Prints',        'slug' => 'uv-prints',
           'desc' => 'Raised UV-cured ink, giving texture you can feel across the surface of the print.' ),
    array( 'name' => 'Mixed Prints',     'slug' => 'mixed-prints',
           'desc' => 'Gold foil and raised UV together on one piece, for the fullest embossed finish.' ),
);

echo '=== EMBOSSED PRINTS ' . ( $dry ? '(DRY RUN — nothing is written)' : '(WRITING)' ) . " ===\n\n";

$term = get_term_by( 'slug', $SLUG, 'product_cat' );
if ( ! $term || is_wp_error( $term ) ) { echo "ABORT: no product_cat with slug '{$SLUG}'.\n"; return; }
printf( "  term      : #%d\n", $term->term_id );
printf( "  name now  : %s\n", html_entity_decode( $term->name ) );
printf( "  name after: %s\n", $NEW_NAME );
printf( "  slug      : %s   (unchanged on purpose — the theme hardcodes it)\n", $term->slug );
printf( "  products  : %d\n", $term->count );

$existing = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'parent' => $term->term_id ) );
printf( "\n  children it already has: %d\n", is_array( $existing ) ? count( $existing ) : 0 );
$wanted_slugs = wp_list_pluck( $KIDS, 'slug' );
$foreign = array();
foreach ( (array) $existing as $c ) {
    $mine = in_array( $c->slug, $wanted_slugs, true );
    printf( "      #%-5d %-26s slug=%-22s %d products%s\n", $c->term_id,
        html_entity_decode( $c->name ), $c->slug, $c->count, $mine ? '' : '   <-- not one of the three' );
    if ( ! $mine ) $foreign[] = $c;
}
if ( $foreign ) {
    printf( "\n  NOTE: %d existing child categor%s classify by SUBJECT, not by finish.\n",
        count( $foreign ), count( $foreign ) === 1 ? 'y' : 'ies' );
    echo "        The homepage collection strip draws these as its circle row. Adding the\n";
    echo "        three finish categories does not remove them; the row will show both\n";
    echo "        kinds together until you decide what to do with the subject ones.\n";
}

/* ---- rename ---- */
if ( html_entity_decode( $term->name ) !== $NEW_NAME ) {
    if ( $dry ) echo "\n  WOULD rename the term\n";
    else {
        $r = wp_update_term( $term->term_id, 'product_cat', array( 'name' => $NEW_NAME, 'slug' => $SLUG ) );
        if ( is_wp_error( $r ) ) { echo "ABORT: rename failed — " . $r->get_error_message() . "\n"; return; }
        echo "\n  renamed.\n";
    }
} else echo "\n  already named that.\n";

/* ---- the three children ---- */
echo "\n=== THE THREE SUB-CATEGORIES ===\n";
$kid_ids = array();
foreach ( $KIDS as $k ) {
    $t = get_term_by( 'slug', $k['slug'], 'product_cat' );
    if ( $t && ! is_wp_error( $t ) ) {
        $kid_ids[ $k['slug'] ] = (int) $t->term_id;
        printf( "  %-18s exists as #%d, parent %d%s\n", $k['name'], $t->term_id, $t->parent,
            (int) $t->parent === (int) $term->term_id ? '' : '   <-- wrong parent' );
        if ( (int) $t->parent !== (int) $term->term_id && ! $dry ) {
            wp_update_term( $t->term_id, 'product_cat', array( 'parent' => $term->term_id ) );
            echo "                     moved under Embossed Prints\n";
        }
    } elseif ( $dry ) {
        printf( "  %-18s WOULD be created under #%d\n", $k['name'], $term->term_id );
    } else {
        $r = wp_insert_term( $k['name'], 'product_cat', array(
            'slug' => $k['slug'], 'parent' => $term->term_id, 'description' => $k['desc'] ) );
        if ( is_wp_error( $r ) ) { printf( "  %-18s FAILED — %s\n", $k['name'], $r->get_error_message() ); continue; }
        $kid_ids[ $k['slug'] ] = (int) $r['term_id'];
        printf( "  %-18s created as #%d\n", $k['name'], $r['term_id'] );
    }
}

/* ---- the menu ---- */
$menu = wp_get_nav_menu_object( $MENU );
if ( ! $menu ) { echo "\nABORT: no nav menu named '{$MENU}'.\n"; return; }
$items = wp_get_nav_menu_items( $menu->term_id );
$row = null;
foreach ( (array) $items as $i ) {
    if ( $i->type === 'taxonomy' && $i->object === 'product_cat' && (int) $i->object_id === (int) $term->term_id ) { $row = $i; break; }
}
if ( ! $row ) { echo "\nABORT: that category is not in the '{$MENU}' menu.\n"; return; }
printf( "\n=== THE MENU ===\n  its row : #%d titled \"%s\"\n", $row->ID, html_entity_decode( $row->title ) );

// A menu item stores its own title, which overrides the term name. Blank it so
// the row follows the category from now on instead of needing a second edit.
if ( html_entity_decode( $row->title ) !== $NEW_NAME ) {
    if ( $dry ) printf( "  WOULD retitle the row to \"%s\"\n", $NEW_NAME );
    else {
        wp_update_post( array( 'ID' => $row->ID, 'post_title' => $NEW_NAME ) );
        echo "  row retitled.\n";
    }
}

$max = 0;
foreach ( (array) $items as $i ) if ( (int) $i->menu_order > $max ) $max = (int) $i->menu_order;
foreach ( $KIDS as $k ) {
    $tid = $kid_ids[ $k['slug'] ] ?? 0;
    $have = null;
    foreach ( (array) $items as $i ) {
        if ( $i->type === 'taxonomy' && $i->object === 'product_cat' && $tid && (int) $i->object_id === $tid ) { $have = $i; break; }
    }
    if ( $have && (int) $have->menu_item_parent === (int) $row->ID ) {
        printf( "  %-18s already in the menu as #%d\n", $k['name'], $have->ID );
        continue;
    }
    if ( $dry ) { printf( "  %-18s WOULD be added under #%d at order %d\n", $k['name'], $row->ID, ++$max ); continue; }
    if ( ! $tid ) { printf( "  %-18s SKIPPED — no term id\n", $k['name'] ); continue; }
    $new = wp_update_nav_menu_item( $menu->term_id, $have ? (int) $have->ID : 0, array(
        'menu-item-title'     => $k['name'],
        'menu-item-object'    => 'product_cat',
        'menu-item-object-id' => $tid,
        'menu-item-type'      => 'taxonomy',
        'menu-item-parent-id' => $row->ID,
        'menu-item-status'    => 'publish',
    ) );
    if ( is_wp_error( $new ) ) { printf( "  %-18s FAILED — %s\n", $k['name'], $new->get_error_message() ); continue; }
    wp_update_post( array( 'ID' => $new, 'menu_order' => ++$max ) );

    // The parent's icon lives in this meta; a row created by the API has none.
    // Sub-rows in this menu carry no icon, so nothing is copied — but if the
    // parent ever gains one, this is where it would go.
    printf( "  %-18s added as #%d under #%d\n", $k['name'], $new, $row->ID );
}

if ( ! $dry ) {
    delete_transient( 'wc_term_counts' );
    if ( function_exists( 'do_action' ) ) do_action( 'litespeed_purge_all' );
    echo "\n  caches cleared.\n";
}

echo "\n=== HOW IT READS NOW ===\n";
$after = $dry ? $items : wp_get_nav_menu_items( $menu->term_id, array( 'update_post_term_cache' => false ) );
$kids = array();
foreach ( (array) $after as $i ) if ( (int) $i->menu_item_parent === (int) $row->ID ) $kids[] = $i;
usort( $kids, function ( $a, $b ) { return $a->menu_order - $b->menu_order; } );
echo "  {$NEW_NAME}\n";
foreach ( $kids as $c ) printf( "      \xe2\x94\x94 %s\n", html_entity_decode( $c->title ) );
if ( $dry ) foreach ( $KIDS as $k ) printf( "      \xe2\x94\x94 %s   <-- would be added\n", $k['name'] );
