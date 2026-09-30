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
