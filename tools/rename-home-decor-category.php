<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Rename the "Home Decor" product category to "Home Decors".
 * Slug (home-decor) is left untouched so the existing URL keeps working.
 * Also updates any nav-menu item whose custom title is exactly "Home Decor".
 * Idempotent. Run: wp eval-file tools/rename-home-decor-category.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$old = 'Home Decor';
$new = 'Home Decors';

$term = get_term_by( 'slug', 'home-decor', 'product_cat' );
if ( ! $term ) { $term = get_term_by( 'name', $old, 'product_cat' ); }

if ( $term && strtolower( $term->name ) !== strtolower( $new ) ) {
    $result = wp_update_term( $term->term_id, 'product_cat', array( 'name' => $new ) );
    if ( is_wp_error( $result ) ) {
        echo "ERROR renaming term #{$term->term_id}: " . $result->get_error_message() . "\n";
    } else {
        echo "Renamed product_cat #{$term->term_id}: \"{$old}\" -> \"{$new}\" (slug unchanged: {$term->slug})\n";
    }
} elseif ( $term ) {
    echo "Term #{$term->term_id} already named \"{$term->name}\" — skipping\n";
} else {
    echo "No product_cat term found for slug/name \"home-decor\"/\"{$old}\"\n";
}

/* Catch any nav-menu item using a custom label of exactly "Home Decor" */
$menu_items = get_posts( array(
    'post_type'      => 'nav_menu_item',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'title'          => $old,
) );
$menu_fixed = 0;
foreach ( $menu_items as $item ) {
    if ( trim( $item->post_title ) !== $old ) continue;
    wp_update_post( array( 'ID' => $item->ID, 'post_title' => $new ) );
    $menu_fixed++;
    echo "Renamed nav menu item #{$item->ID}: \"{$old}\" -> \"{$new}\"\n";
}
if ( ! $menu_fixed ) echo "No nav menu items with custom label \"{$old}\" found\n";

echo "=== DONE ===\n";
