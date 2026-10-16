<?php
/**
 * Meta (Facebook / Instagram) pixel.
 *
 * The 16 Oct audit found no pixel anywhere: none in the Meta business
 * account, none on the site. Without one, Facebook and Instagram ads cannot
 * count visits or sales, build retargeting audiences or optimise for
 * purchases. This module prints the pixel and the shop events as soon as an
 * ID is saved (Settings → General → "Meta Pixel ID"); until then it prints
 * nothing. The owner creates the pixel in Meta Events Manager (Data sources →
 * Add → Web), which takes a minute, and pastes the 15–16 digit ID.
 *
 * Consent. The script ships inert (type="text/plain" data-consent="marketing")
 * and inc/cookie-consent.php switches it on only when the visitor allows the
 * marketing category, the same gate the analytics tag uses. No noscript
 * image is printed: that would fire before consent.
 *
 * Events, all decided server-side so cached pages stay correct:
 *   PageView          every page
 *   ViewContent       product page (id, name, price)
 *   AddToCart         WooCommerce's AJAX add, and the form-post add that
 *                     inc/analytics.php notes in the session
 *   InitiateCheckout  checkout page (cart value, item count)
 *   Purchase          thank-you page, once per order (_af_fb_purchase_sent)
 *
 * Bots (87% of visits in GA4, 16 Oct) are not reported: see
 * af_bot_signal_js() in inc/analytics.php.
 */
if (!defined('ABSPATH')) exit;

function af_meta_pixel_id() { return trim((string) get_option('af_meta_pixel_id', '')); }

add_action('admin_init', function () {
    register_setting('general', 'af_meta_pixel_id', array('type' => 'string', 'sanitize_callback' => function ($v) {
        $v = preg_replace('/\D+/', '', (string) $v);
        return ($v === '' || preg_match('/^\d{10,20}$/', $v)) ? $v : '';
    }));
    add_settings_field('af_meta_pixel_id', 'Meta Pixel ID', function () {
        echo '<input type="text" name="af_meta_pixel_id" value="' . esc_attr(af_meta_pixel_id()) . '" placeholder="15 or 16 digits" class="regular-text">'
           . '<p class="description">From Meta Events Manager → Data sources. Loads only after the visitor allows marketing cookies. Sends PageView, ViewContent, AddToCart, InitiateCheckout and Purchase.</p>';
    }, 'general');
});

/** The pixel events this page should report, decided server-side. */
function af_meta_pixel_page_events() {
    $ev = array();
    try {
        if (!function_exists('WC')) return $ev;
        $cur = get_woocommerce_currency();
        if (function_exists('is_product') && is_product()) {
            $p = wc_get_product(get_queried_object_id());
            if ($p) $ev[] = array('ViewContent', array(
                'content_ids'  => array($p->get_sku() ?: (string) $p->get_id()),
                'content_name' => $p->get_name(),
                'content_type' => 'product',
                'value'        => (float) wc_get_price_to_display($p),
                'currency'     => $cur,
            ));
        }
        // the form-post add to cart that inc/analytics.php lifted out of the session this request
        if (!empty($GLOBALS['af_atc_items']) && is_array($GLOBALS['af_atc_items'])) {
            $value = 0; $ids = array(); $n = 0;
            foreach ($GLOBALS['af_atc_items'] as $i) { $value += (float) $i['price'] * (int) $i['quantity']; $ids[] = (string) $i['item_id']; $n += (int) $i['quantity']; }
            $ev[] = array('AddToCart', array('content_ids' => $ids, 'content_type' => 'product', 'value' => round($value, 2), 'currency' => $cur, 'num_items' => $n));
        }
        if (function_exists('is_checkout') && is_checkout() && !is_order_received_page() && WC()->cart) {
            $ev[] = array('InitiateCheckout', array(
                'value'     => (float) WC()->cart->get_total('edit'),
                'currency'  => $cur,
                'num_items' => (int) WC()->cart->get_cart_contents_count(),
            ));
        }
        if (function_exists('is_order_received_page') && is_order_received_page()) {
            $order_id = absint(get_query_var('order-received'));
            $order = $order_id ? wc_get_order($order_id) : null;
            if ($order && !$order->get_meta('_af_fb_purchase_sent')) {
                $ids = array(); $n = 0;
                foreach ($order->get_items() as $item) {
                    $p = $item->get_product();
                    $ids[] = $p ? ($p->get_sku() ?: (string) $p->get_id()) : (string) $item->get_product_id();
                    $n += (int) $item->get_quantity();
                }
                $ev[] = array('Purchase', array(
                    'value'        => (float) $order->get_total(),
                    'currency'     => $order->get_currency(),
                    'content_ids'  => $ids,
                    'content_type' => 'product',
                    'num_items'    => $n,
                ));
                $order->update_meta_data('_af_fb_purchase_sent', 1);   // a refresh must not report the sale twice
                $order->save();
            }
        }
    } catch (\Throwable $e) {
    }
    return $ev;
}

add_action('wp_head', function () {
    $id = af_meta_pixel_id();
    if ($id === '' || is_admin() || is_feed()) return;
    $bot = function_exists('af_bot_signal_js') ? af_bot_signal_js() : 'navigator.webdriver';
    $events = af_meta_pixel_page_events();
    $cur = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD';
    $js = "if(!({$bot})){"
        . "!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};"
        . "if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;"
        . "s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');"
        . "fbq('init'," . wp_json_encode($id) . ");fbq('track','PageView');";
    foreach ($events as $e) $js .= "fbq('track'," . wp_json_encode($e[0]) . ',' . wp_json_encode($e[1]) . ');';
    $js .= "if(window.jQuery){jQuery(document.body).on('added_to_cart',function(){fbq('track','AddToCart',{currency:" . wp_json_encode($cur) . "});});}"
        . "}";
    echo '<script type="text/plain" data-consent="marketing">' . $js . '</script>' . "\n";
}, 12);   // after inc/analytics.php (4 and 11), which lifts the form-post add to cart out of the session
