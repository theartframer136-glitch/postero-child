<?php
/**
 * Sales count on product cards  (audit item 16)
 *
 * "N sold" under the price, only once a piece has actually sold enough to
 * be social proof rather than an admission — threshold filterable.
 */
if (!defined('ABSPATH')) exit;

function af_sales_count_min() { return (int) apply_filters('af_sales_count_min', 3); }

add_action('woocommerce_after_shop_loop_item_title', function() {
    global $product;
    if (!$product) return;
    $sold = (int) $product->get_total_sales();
    if ($sold < af_sales_count_min()) return;
    $label = $sold >= 100 ? (floor($sold / 50) * 50) . '+' : (string) $sold;
    echo '<span class="af-sold">🔥 ' . esc_html($label) . ' sold</span>';
}, 11);

add_action('wp_head', function() {
    echo '<style>.af-sold{display:inline-block;font-size:11px;font-weight:700;color:#b26b00;background:#fdf3e2;'
       . 'border-radius:999px;padding:2px 9px;margin:4px 0 2px;}</style>';
}, 30);
