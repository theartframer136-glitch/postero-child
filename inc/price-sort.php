<?php
if (!defined('ABSPATH')) exit;
/**
 * A price sort that puts "Price on request" last, instead of leaving it out.
 *
 * M-02. Test Run 01: sorting the shop by price, low to high, opened on three
 * pages of the 35 products that have no price (stretcher bars, DIY frame
 * kits, custom-size canvases), all "Price on request" with an Enquire button.
 * A shopper sorting by price never reached anything they could buy.
 *
 * The first fix (functions.php, 21 Sep) took those products OUT of any price
 * sort: a meta_query keeping only _price > 0. Test Run 03 measured the shop's
 * page 1 and marked M-02 fixed. What it did not look at is every other
 * listing a shopper can sort, and there, going by the code:
 *
 *   - Art Accessories is mostly those products (PHASE 23 in functions.php
 *     says so: "The frame / accessory products (Art Accessories category)
 *     have no price"). Sorted by price, it loses them, and a listing left
 *     with nothing says "This collection is empty right now." — about a
 *     category with thirty-odd products in it, because the visitor chose a
 *     sort. inc/price-filter.php does not count a sort as a filter, rightly.
 *   - Everywhere, the number of results changes with the sort, by the number
 *     of products without a price. Products do not stop existing when you
 *     reorder them.
 * tools/verify-m02.mjs measures both on the live shop.
 *
 * So they are sorted, not removed: every product with a price first, in the
 * order asked for, and the ones without a price after them, in both
 * directions. Page 1 of the shop, low to high, still opens on the cheapest
 * piece that can be bought, which is what M-02 asked for.
 *
 * HOW. WooCommerce sorts by price through its lookup table: for this sort it
 * adds a posts_clauses filter that joins wc_product_meta_lookup and orders by
 * min_price ASC (low to high) or max_price DESC (high to low). This runs
 * after it, on the same query only, and puts one term in front of that
 * ORDER BY: whether the product has a price at all. A product's highest price
 * is 0 or missing exactly when every price it has is 0 or missing, which is
 * the rule PHASE 23 uses to show "Price on request".
 *
 * If some other plugin has replaced WooCommerce's price sort and the lookup
 * table is not in the ORDER BY, there is nothing safe to reorder; those
 * products are then left out as before, so the shop's page 1 never goes back
 * to opening on them.
 */

/**
 * Is this listing sorted by price? The sort in the URL, or the shop's default
 * when the URL has none.
 */
function af_price_sort_requested($get, $default) {
    $o = isset($get['orderby']) ? strtolower(trim((string) $get['orderby'])) : '';
    if ($o === '') $o = strtolower(trim((string) $default));     // WooCommerce does the same
    return $o === 'price' || $o === 'price-desc';
}

/**
 * The query clauses with "Price on request" moved to the end. Pure, so the
 * tests can hand it WooCommerce's own clauses.
 *
 * @param array  $clauses     posts_clauses pieces (where, orderby, ...)
 * @param string $posts_table $wpdb->posts
 * @param string $meta_table  $wpdb->postmeta
 */
function af_price_sort_clauses($clauses, $posts_table, $meta_table) {
    $ob = isset($clauses['orderby']) ? (string) $clauses['orderby'] : '';
    if (strpos($ob, 'wc_product_meta_lookup.') !== false) {
        $clauses['orderby'] = ' ( COALESCE(wc_product_meta_lookup.max_price, 0) <= 0 ) ASC, ' . ltrim($ob);
        return $clauses;
    }
    // Not WooCommerce's price sort: leave them out, as the first fix did.
    $clauses['where'] = (isset($clauses['where']) ? $clauses['where'] : '')
        . " AND EXISTS (SELECT 1 FROM {$meta_table} afps WHERE afps.post_id = {$posts_table}.ID"
        . " AND afps.meta_key = '_price' AND afps.meta_value + 0 > 0)";
    return $clauses;
}

// Mark the shop's own product query when it is sorted by price.
add_action('woocommerce_product_query', function ($q) {
    if (is_admin()) return;
    $get = isset($_GET['orderby']) ? array('orderby' => sanitize_key(wp_unslash($_GET['orderby']))) : array();
    if (!af_price_sort_requested($get, get_option('woocommerce_default_catalog_orderby', 'menu_order'))) return;
    $q->set('af_price_sort', 1);
});

// After WooCommerce has written its price ORDER BY (priority 10).
add_filter('posts_clauses', function ($clauses, $q) {
    if (!is_object($q) || !method_exists($q, 'get') || !$q->get('af_price_sort')) return $clauses;
    try {
        global $wpdb;
        return af_price_sort_clauses($clauses, $wpdb->posts, $wpdb->postmeta);
    } catch (\Throwable $e) {
        return $clauses;    // WooCommerce's sort, unchanged
    }
}, 20, 2);
