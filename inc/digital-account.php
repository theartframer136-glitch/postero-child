<?php
/**
 * Digital downloads stay in the buyer's account.
 *
 * Owner, 10 Oct 2026: "if a person purchase a digital product the product link
 * stay in their account which they have login in our website". The owner's
 * choices:
 *   - forever, unlimited: a download never expires and has no download count
 *   - an account is made at checkout when the cart has a digital download
 *     (guest checkout stays as it is for prints and canvases)
 *   - logged in only: a download opens only for the buyer, logged in
 *     (woocommerce_downloads_require_login = yes; tools/enable-digital-
 *     downloads.php sets it, and the products' limit and expiry to -1, on
 *     every deploy)
 *
 * The link in the account (My Account → Downloads, and in the order email) is
 * WooCommerce's own and permanent. Each click on it still makes a fresh
 * 5-minute Cloudflare link to the file (inc/digital-masters.php), so a link
 * copied out of the browser stops working on its own.
 */
if (!defined('ABSPATH')) exit;

// Every download permission: no expiry and no download count, whatever a
// product's own limit says (an imported or newly made product included).
add_filter('woocommerce_downloadable_file_permission', function ($download, $product = null, $order = null, $qty = 1, $item = null) {
    if (is_object($download) && method_exists($download, 'set_downloads_remaining')) {
        $download->set_downloads_remaining('');
        $download->set_access_expires(null);
    }
    return $download;
}, 10, 5);

/** A guest at checkout with a digital download in the cart: needs an account. */
function af_checkout_needs_account() {
    // the cart first: it is not there yet early in a request, so the login
    // state is never asked for before WordPress has settled who is visiting
    if (!function_exists('af_cart_has_digital') || !af_cart_has_digital()) return false;
    return !is_user_logged_in();
}

// The account is required (and so offered) only for such a cart.
add_filter('woocommerce_checkout_registration_required', function ($required) {
    return af_checkout_needs_account() ? true : $required;
});
add_filter('woocommerce_checkout_registration_enabled', function ($enabled) {
    return af_checkout_needs_account() ? true : $enabled;
});
// Nothing to fill in: WooCommerce makes the username from the email, and the
// password is already generated and sent as a "set your password" link (the
// "Your account has been created" email is on).
add_filter('option_woocommerce_registration_generate_username', function ($value) {
    return af_checkout_needs_account() ? 'yes' : $value;
});

// Say so where the account section of the checkout form is.
add_action('woocommerce_before_checkout_registration_form', function () {
    if (!af_checkout_needs_account()) return;
    echo '<p class="af-dl-account-note" style="background:#faf7f0;border:1px solid #e0d5b8;padding:12px 14px;margin:0 0 12px;">'
        . '<b>Your download is kept in your account.</b> We create it with the email address above and email you a link to set your password. '
        . 'Your file then stays in <b>My Account → Downloads</b>, to download again whenever you need it. '
        . 'Already have an account? Log in first with the link at the top of this page.'
        . '</p>';
});
