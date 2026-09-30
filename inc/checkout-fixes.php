<?php
/**
 * Cart and checkout fixes. Owner, 30 Sep: "test full checkout page what is
 * the issue ... fix all those issues".
 *
 * WHERE A SHOPPER IS BEFORE THEY HAVE SAID.
 *
 * Measured live as a first-time guest (tools/diag-checkout.mjs):
 *
 *   cart      "Shipment  Delivery: $27.51  Shipping to Rajasthan."
 *   checkout  Country = India, State = Rajasthan, preselected
 *
 * The store's base country is set to India (woocommerce_default_country
 * IN:RJ) and "Default customer location" is "Shop base address", so every
 * visitor to a shop that sells "throughout the USA" was placed in Rajasthan,
 * quoted delivery there, and had to change two fields before checkout made
 * sense. The owner's own cart said "Shipping to" the studio because his
 * account carried the studio's address (fixed on the account itself).
 *
 * Two changes:
 *
 * 1. A guest starts in the United States - the country, nothing narrower.
 *    Not the store's address: that is where orders come FROM, and quoting it
 *    as the destination is exactly what the owner reported. With no state and
 *    no ZIP there is nothing to mistake for the customer's own address.
 *
 * 2. No delivery figure until there is a ZIP to measure from. Delivery here
 *    is priced by distance from ZIP 19707; a country alone has no distance,
 *    so the figure shown was a guess that changed at checkout. The site
 *    already says "Shipping cost shown at checkout" under the total; this
 *    makes the total agree with it. Set once through WooCommerce's own
 *    setting (Settings > Shipping > "Hide shipping costs until an address is
 *    entered"), so it stays visible and changeable in wp-admin.
 *
 * Logged-in customers with a saved address are unaffected: WooCommerce uses
 * their own address, as before.
 */
if (!defined('ABSPATH')) exit;

add_filter('woocommerce_customer_default_location', function ($location) {
    return 'US';
}, 20);

define('AF_CHECKOUT_FIXES_REV', '1');

add_action('wp_loaded', function () {
    try {
        if (get_option('af_checkout_fixes_rev') === AF_CHECKOUT_FIXES_REV) return;
        update_option('af_checkout_fixes_rev', AF_CHECKOUT_FIXES_REV, true);
        $was = get_option('woocommerce_shipping_cost_requires_address');
        update_option('woocommerce_shipping_cost_requires_address', 'yes');
        update_option('af_checkout_fixes', 'shipping_cost_requires_address ' . var_export($was, true)
            . ' -> ' . get_option('woocommerce_shipping_cost_requires_address') . ' @ ' . gmdate('c'), false);
        // Cart and checkout are never page-cached; nothing else to purge.
    } catch (\Throwable $e) {
        update_option('af_checkout_fixes', 'failed: ' . substr($e->getMessage(), 0, 160), false);
    }
}, 99);

/**
 * Words break between letters in the cart and checkout tables. Measured at
 * the owner's 1918px (tools/diag-carttotals.mjs): the parent theme's
 * style.css sets "table td, table th { word-break: break-all }" for every
 * table, and the cart totals label column is 147px wide at 18px type, so
 * "Price before discount" broke as "Price before disc / ount" and the
 * address under Shipment as "te st" and "(U S)". Break between words
 * instead; a single string too long for its cell (an email, a URL) may
 * still wrap, which is what break-word is for.
 */
add_action('wp_head', function () {
    if (!function_exists('is_cart') || !(is_cart() || is_checkout() || is_account_page())) return;
    ?>
<style id="af-shop-table-words">
.woocommerce table.shop_table th, .woocommerce table.shop_table td,
.woocommerce-page table.shop_table th, .woocommerce-page table.shop_table td,
.cart_totals table th, .cart_totals table td{word-break:normal!important;overflow-wrap:break-word!important}
</style>
    <?php
}, 99);

/**
 * The product photo beside each line of the checkout "Your order" list.
 * Owner, 30 Sep, with a screenshot of that list: "photo to be shown in the
 * final product list". The list named each piece but showed no picture, so
 * the last look before paying was a column of long, near-identical titles.
 *
 * Only this list changes. WooCommerce prints every cart item name through
 * one filter (the cart page, mini-carts, plugins), so the photo is added
 * only between the two hooks WooCommerce fires around this list's rows, and
 * the list keeps it when checkout redraws the list after an address change.
 * The picture is the product's own gallery thumbnail, the variation's when
 * there is one, passed through the cart's thumbnail filter like the cart
 * page's; alt is empty because the name is written right beside it.
 */
function af_review_rows($set = null) {
    static $on = false;
    if ($set !== null) $on = (bool) $set;
    return $on;
}
add_action('woocommerce_review_order_before_cart_contents', function () { af_review_rows(true); }, 1);
add_action('woocommerce_review_order_after_cart_contents', function () { af_review_rows(false); }, 999);

add_filter('woocommerce_cart_item_name', function ($name, $cart_item = array(), $cart_item_key = '') {
    if (!af_review_rows()) return $name;
    try {
        $product = (is_array($cart_item) && isset($cart_item['data'])) ? $cart_item['data'] : null;
        if (!$product instanceof WC_Product) return $name;
        $img = $product->get_image('woocommerce_gallery_thumbnail', array('alt' => '', 'loading' => 'lazy', 'class' => 'af-co-thumb-img'));
        $img = apply_filters('woocommerce_cart_item_thumbnail', $img, $cart_item, $cart_item_key);
        if (!is_string($img) || stripos($img, '<img') === false) return $name;
        // the site fills an empty alt with the product title (functions.php
        // 24e); here the title is printed right beside the photo, so keep it empty
        $img = preg_replace('/\salt=("[^"]*"|\'[^\']*\')/', ' alt=""', $img, 1);
        return '<span class="af-co-thumb" aria-hidden="true">' . $img . '</span>' . $name;
    } catch (\Throwable $e) {
        return $name;
    }
}, 20, 3);

add_action('wp_head', function () {
    if (!function_exists('is_checkout') || !is_checkout() || (function_exists('is_order_received_page') && is_order_received_page())) return;
    ?>
<style id="af-co-thumbs">
/* the photo sits in the name cell's left padding, level with the first line
   of the name; the cell keeps its own top padding explicit so the two agree
   at every width (checkout.css stacks the rows under 500px, each cell at
   width:100%, so there the padding has to count inside that width) */
.woocommerce-checkout-review-order-table tr.cart_item td.product-name{position:relative;padding-left:70px!important;padding-top:14px!important}
/* a zero-width float the cell must contain, so a line with little text is
   still as tall as its photo (min-height does nothing on a table cell) */
.woocommerce-checkout-review-order-table tr.cart_item td.product-name::before{content:"";float:left;width:0;height:58px}
.woocommerce-checkout-review-order-table .af-co-thumb{position:absolute;left:0;top:14px;width:56px;height:56px;border-radius:8px;overflow:hidden;background:#f3efe8;box-shadow:0 0 0 1px rgba(0,0,0,.08)}
.woocommerce-checkout-review-order-table .af-co-thumb img{display:block;width:100%!important;height:100%!important;max-width:none!important;object-fit:cover;margin:0!important;border-radius:0}
@media (max-width:499px){
.woocommerce-checkout-review-order-table tr.cart_item td.product-name{padding-left:64px!important;display:flow-root;box-sizing:border-box}
.woocommerce-checkout-review-order-table tr.cart_item td.product-name::before{height:54px}
.woocommerce-checkout-review-order-table .af-co-thumb{width:52px;height:52px}
}
</style>
    <?php
}, 99);
