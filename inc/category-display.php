<?php
/**
 * Banners & Signage lists its products, not tiles for its subcategories.
 *
 * Owner, 30 Sep, with a screenshot of /product-category/banners-signage/: "why
 * this are showing empty?". The category was set to show its subcategories as
 * well as its products, so the grid opened with five category tiles
 * (Backdrops, Banner Stands, Fabric / Cloth Banners, Fence Banners, Vinyl
 * Banners) and then the same five banners again as products, since each
 * subcategory holds exactly one. A tile carries only a picture, a name, its
 * count and the brochure button, and the grid stretches every card in a row to
 * the tallest, so a tile beside a product card read as an empty card: 214 px
 * of white on a desktop, 278 px on a phone (tools/diag-subcat-tiles.mjs).
 * Every other category already lists products only.
 *
 * WooCommerce decides tiles, products or both in
 * woocommerce_get_loop_display_mode(), which has no filter of its own: it
 * reads the category's "Display type" (term meta display_type). So that one
 * setting reads "products" for the categories named below, the same as
 * choosing Products in the admin, and the admin's Display type field shows
 * Products for them too. The subcategory pages are untouched.
 */
if (!defined('ABSPATH')) exit;

/** slugs of the product categories whose page lists products only */
function af_products_only_categories() {
    return array('banners-signage');
}

/**
 * get_term_metadata filter: the Display type of a products-only category is
 * "products". Anything else — another key, another category, a term of
 * another taxonomy with the same slug — passes through as it was.
 */
function af_category_display_type($value, $term_id, $meta_key, $single) {
    if ($meta_key !== 'display_type') { return $value; }
    $term = get_term((int) $term_id);
    if (!$term || is_wp_error($term) || $term->taxonomy !== 'product_cat') { return $value; }
    if (!in_array($term->slug, af_products_only_categories(), true)) { return $value; }
    return $single ? 'products' : array('products');
}
add_filter('get_term_metadata', 'af_category_display_type', 10, 4);
