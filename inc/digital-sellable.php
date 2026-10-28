<?php
/**
 * A digital download is sold only where there is a print file to deliver,
 * and the buyer is told plainly when it opens and how to reach it.
 *
 * Site test, 27 Oct 2026, walking the purchase as a 35-year-old buyer:
 *
 *  1. Every piece offered "High-resolution, print-ready file", but only the
 *     pieces with a master in the Cloudflare bucket (inc/digital-masters.php)
 *     deliver one. The rest delivered the product's web image (about 430 KB
 *     on average, measured 17 Sep), which prints blurred at 3x4 ft. A
 *     download is now sold only where af_r2_master_for_buyer() finds the
 *     master - the same test the delivery itself uses, so what is sold and
 *     what is delivered cannot disagree.
 *  2. Cash on delivery was offered for a basket of downloads alone. There is
 *     nothing to deliver, and WooCommerce holds such an order until it is
 *     marked paid by hand, so the file never arrived. Not offered for a
 *     download-only basket any more; Zelle stays, with a note saying when the
 *     download opens.
 *  3. The buyer was not told that the download link in the order email opens
 *     only when logged in, nor that an offline payment holds the download.
 *     Both are said at checkout, on the thank-you page and in the emails.
 *
 * Switches, without a deploy:
 *   wp option update af_dd_require_master no    sell downloads on every piece again
 * And when the bucket is not connected or has never been read, nothing here
 * hides a download: there is then no way to know which pieces have a master,
 * and the shop behaves as it did before.
 */
defined('ABSPATH') || exit;

/** Is a master required before a piece is sold as a download? */
function af_digital_master_required() {
    return (bool) apply_filters('af_digital_master_required', get_option('af_dd_require_master', 'yes') !== 'no');
}

/**
 * Can this piece be sold as a download right now?
 * Accepts a product or a product ID.
 */
function af_digital_available($product) {
    if (is_numeric($product)) $product = function_exists('wc_get_product') ? wc_get_product((int) $product) : null;
    if (!($product instanceof WC_Product)) return false;
    if (!af_digital_master_required()) return true;
    if (!function_exists('af_r2_config') || !af_r2_config()) return true;          // no bucket: as before
    $idx = get_option('af_r2_index');
    if (!is_array($idx) || empty($idx['map'])) return true;                          // never read: as before
    return function_exists('af_r2_master_for_buyer') && (bool) af_r2_master_for_buyer($product);
}

function af_digital_unavailable_text($name = '') {
    $what = $name !== '' ? '“' . $name . '”' : 'This piece';
    return $what . ' is not available as a print-ready download yet. It can still be ordered as a canvas from its product page.';
}

/** Is this ORDER line a download? (The cart's own test is af_cart_line_is_digital.) */
function af_order_item_is_digital($item) {
    if (!is_object($item) || !method_exists($item, 'get_meta')) return false;
    if ($item->get_meta('Format') === 'Digital Download' || $item->get_meta('af_digital')) return true;
    $kit = $item->get_meta('_af_kit');
    return $kit !== '' && $kit !== null && function_exists('af_kit_ships') && !af_kit_ships($kit);
}

function af_order_has_digital($order) {
    if (!is_object($order) || !method_exists($order, 'get_items')) return false;
    foreach ($order->get_items() as $item) if (af_order_item_is_digital($item)) return true;
    return false;
}

/** A basket with lines in it, every one of them a download. */
function af_cart_download_only() {
    if (!function_exists('WC') || !WC() || !WC()->cart || !function_exists('af_cart_line_is_digital')) return false;
    $items = WC()->cart->get_cart();
    if (!$items) return false;
    foreach ($items as $item) if (!af_cart_line_is_digital($item)) return false;
    return true;
}

/** The download link in the account; the account page when the endpoint is unknown. */
function af_downloads_url() {
    if (function_exists('wc_get_account_endpoint_url')) {
        $u = wc_get_account_endpoint_url('downloads');
        if ($u) return $u;
    }
    return home_url('/my-account/downloads/');
}

// ── 1. Sold only where there is a master ────────────────────────────────

// Refused at the door: the modal, "What you receive: Digital download", and
// a plain Add to Cart on a product that is only ever a file. Runs before the
// line is built, so nothing is ever silently turned into a canvas instead.
add_filter('woocommerce_add_to_cart_validation', function ($passed, $pid = 0, $qty = 1) {
    if (!$passed || !function_exists('af_sells_as_digital') || !af_sells_as_digital($pid)) return $passed;
    if (af_digital_available($pid)) return $passed;
    if (function_exists('wc_add_notice')) wc_add_notice(af_digital_unavailable_text(get_the_title($pid)), 'error');
    return false;
}, 20, 3);

// A basket built before the master went (or before this rule): checkout says
// which line cannot be delivered rather than taking money for a blurred file.
add_action('woocommerce_check_cart_items', function () {
    if (!function_exists('WC') || !WC()->cart || !function_exists('af_cart_line_is_digital')) return;
    foreach (WC()->cart->get_cart() as $item) {
        if (!af_cart_line_is_digital($item)) continue;
        $p = isset($item['data']) ? $item['data'] : null;
        if (is_object($p) && method_exists($p, 'get_parent_id') && $p->get_parent_id()) $p = wc_get_product($p->get_parent_id());
        if (!$p && !empty($item['product_id'])) $p = wc_get_product((int) $item['product_id']);
        if ($p && !af_digital_available($p)) {
            wc_add_notice(af_digital_unavailable_text($p->get_name()) . ' Please remove it from your cart to continue.', 'error');
        }
    }
});

// A product that is only ever a file, with no file to give, cannot be bought:
// WooCommerce then shows "Read more" on its card and no button on its page.
add_filter('woocommerce_is_purchasable', function ($purchasable, $product) {
    if (!$purchasable || !function_exists('af_is_digital_download') || !af_is_digital_download($product)) return $purchasable;
    return af_digital_available($product);
}, 20, 2);

// ── 2. Paying for a download ────────────────────────────────────────────

add_filter('woocommerce_available_payment_gateways', function ($gateways) {
    if (is_admin() && !wp_doing_ajax()) return $gateways;
    if (is_array($gateways) && af_cart_download_only()) unset($gateways['cod']);
    return $gateways;
}, 21);

/** The offline methods still on offer for this basket, by title. */
function af_digital_offline_titles() {
    $out = array();
    if (!function_exists('WC') || !WC() || !WC()->payment_gateways) return $out;
    foreach (WC()->payment_gateways->get_available_payment_gateways() as $id => $gw) {
        // "Pay via Zelle" reads "Paid by Zelle" in the sentence it goes into
        if (in_array($id, array('cod', 'zelle', 'bacs', 'cheque'), true)) $out[] = preg_replace('/^Pay\s+(via|by|with)\s+/i', '', wp_strip_all_tags($gw->get_title()));
    }
    return $out;
}

add_action('woocommerce_review_order_before_payment', function () {
    if (!function_exists('af_cart_has_digital') || !af_cart_has_digital()) return;
    $offline = af_digital_offline_titles();
    echo '<p class="af-dl-pay-note" style="background:#faf7f0;border:1px solid #e0d5b8;padding:10px 14px;margin:0 0 12px;">'
        . '<b>When your download opens:</b> paid by card, it is in <b>My Account → Downloads</b> straight away.';
    if ($offline) {
        echo ' Paid by ' . esc_html(implode(' or ', $offline)) . ', it opens there as soon as we confirm your payment.';
    }
    echo '</p>';
});

// ── 3. Telling the buyer where it is ────────────────────────────────────

/** The sentence for an order, by whether its money has arrived. */
function af_digital_order_line($order) {
    $paid = method_exists($order, 'is_paid') ? $order->is_paid() : in_array($order->get_status(), array('processing', 'completed'), true);
    if ($paid) {
        return 'Your download is in My Account → Downloads. Log in with ' . $order->get_billing_email()
             . ' to open it (a new account: set your password first, from the "Your account has been created" email).';
    }
    return 'Your download opens in My Account → Downloads as soon as we confirm your payment. We will email you when it is ready.';
}

add_action('woocommerce_thankyou', function ($order_id) {
    $order = function_exists('wc_get_order') ? wc_get_order($order_id) : null;
    if (!$order || !af_order_has_digital($order)) return;
    echo '<p class="af-dl-thanks" style="background:#faf7f0;border:1px solid #e0d5b8;padding:12px 14px;margin:0 0 16px;">'
        . esc_html(af_digital_order_line($order))
        . ' <a href="' . esc_url(af_downloads_url()) . '">Go to My Downloads</a></p>';
}, 5);

add_action('woocommerce_email_before_order_table', function ($order, $sent_to_admin = false, $plain_text = false, $email = null) {
    if ($sent_to_admin || !is_object($order) || !af_order_has_digital($order)) return;
    $id = is_object($email) && isset($email->id) ? $email->id : '';
    if (!in_array($id, array('customer_on_hold_order', 'customer_processing_order', 'customer_completed_order'), true)) return;
    $line = af_digital_order_line($order);
    if ($plain_text) { echo "\n" . $line . "\n" . af_downloads_url() . "\n\n"; return; }
    echo '<p style="margin:0 0 16px;padding:12px 14px;background:#faf7f0;border:1px solid #e0d5b8;">'
        . esc_html($line) . ' <a href="' . esc_url(af_downloads_url()) . '">My Downloads</a></p>';
}, 10, 4);
