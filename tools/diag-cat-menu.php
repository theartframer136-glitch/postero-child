<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * How is the header's CATEGORIES dropdown actually built, and what is already
 * under Banners & Signage?
 *
 * A new category has to appear in that dropdown to count as done, and there is
 * more than one way this theme could be producing it: a WordPress nav menu
 * whose items are stored in the database, or a list generated from the
 * product_cat terms themselves. The two need completely different work, so
 * settle it before writing any.
 *
 * Read-only.
 * Run: wp eval-file tools/diag-cat-menu.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

echo "=== PRODUCT CATEGORY TREE (top level and their children) ===\n";
$tops = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'parent' => 0 ) );
foreach ( $tops as $t ) {
    printf( "  #%-5d %-28s slug=%-26s count=%-4d order=%s\n",
        $t->term_id, substr( $t->name, 0, 28 ), $t->slug, $t->count,
        get_term_meta( $t->term_id, 'order', true ) ?: '-' );
    $kids = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'parent' => $t->term_id ) );
    foreach ( $kids as $k ) {
        printf( "        └ #%-5d %-26s slug=%-24s count=%d\n", $k->term_id, substr( $k->name, 0, 26 ), $k->slug, $k->count );
    }
}

echo "\n=== BANNERS & SIGNAGE IN DETAIL ===\n";
$bs = null;
foreach ( array( 'banners-signage', 'banners-and-signage' ) as $s ) {
    $t = get_term_by( 'slug', $s, 'product_cat' );
    if ( $t && ! is_wp_error( $t ) ) { $bs = $t; break; }
}
if ( ! $bs ) echo "  NOT FOUND by either slug\n";
else {
    echo "  term_id   : {$bs->term_id}\n";
    echo "  name      : {$bs->name}\n";
    echo "  slug      : {$bs->slug}\n";
    echo "  parent    : {$bs->parent}\n";
    echo "  count     : {$bs->count}\n";
    echo "  link      : " . get_term_link( $bs ) . "\n";
    echo "  term meta :\n";
    foreach ( get_term_meta( $bs->term_id ) as $k => $v ) {
        echo "      {$k} = " . substr( is_array( $v ) ? ( $v[0] ?? '' ) : $v, 0, 70 ) . "\n";
    }
}

echo "\n=== NAV MENUS REGISTERED ===\n";
foreach ( get_registered_nav_menus() as $loc => $desc ) {
    $obj = wp_get_nav_menu_object( get_nav_menu_locations()[ $loc ] ?? 0 );
    printf( "  %-26s -> %s\n", $loc, $obj ? "{$obj->name} (#{$obj->term_id}, {$obj->count} items)" : '(nothing assigned)' );
}

echo "\n=== EVERY NAV MENU AND ITS ITEMS ===\n";
foreach ( wp_get_nav_menus() as $menu ) {
    echo "  --- {$menu->name} (#{$menu->term_id}) — {$menu->count} items\n";
    $items = wp_get_nav_menu_items( $menu->term_id );
    if ( ! $items ) { echo "      (none)\n"; continue; }
    foreach ( $items as $i ) {
        $depth = $i->menu_item_parent ? '      └ ' : '    ';
        printf( "%s#%-6d %-30s type=%-14s obj=%-12s objid=%-6s parent=%-6s url=%s\n",
            $depth, $i->ID, substr( $i->title, 0, 30 ), $i->type, $i->object,
            $i->object_id, $i->menu_item_parent, substr( $i->url, 0, 50 ) );
    }
}

echo "\n=== DOES ANYTHING BUILD THE DROPDOWN FROM TERMS? ===\n";
$hay = array(
    'child theme functions.php' => get_stylesheet_directory() . '/functions.php',
    'parent theme header'       => get_template_directory() . '/header.php',
);
foreach ( glob( get_template_directory() . '/inc/*.php' ) as $f ) $hay[ 'parent inc/' . basename( $f ) ] = $f;
foreach ( glob( get_template_directory() . '/template-parts/header/*.php' ) as $f ) $hay[ 'parent header part ' . basename( $f ) ] = $f;
foreach ( $hay as $label => $f ) {
    if ( ! file_exists( $f ) ) continue;
    $src = file_get_contents( $f );
    $hits = array();
    foreach ( array( 'product_cat', 'wp_list_categories', 'get_terms', 'vertical-menu', 'category-menu', 'menu-category' ) as $needle ) {
        if ( stripos( $src, $needle ) !== false ) $hits[] = $needle;
    }
    if ( $hits ) printf( "  %-42s %s\n", substr( $label, 0, 42 ), implode( ', ', $hits ) );
}

echo "\n=== CATEGORY ICONS: where do they come from? ===\n";
global $wpdb;
$iconish = $wpdb->get_results( "
    SELECT meta_key, COUNT(*) n FROM {$wpdb->termmeta}
    WHERE meta_key LIKE '%icon%' OR meta_key LIKE '%image%' OR meta_key LIKE '%thumb%'
    GROUP BY meta_key" );
if ( ! $iconish ) echo "  no icon-ish term meta at all\n";
foreach ( $iconish as $r ) printf( "  %-34s %d terms\n", $r->meta_key, $r->n );
