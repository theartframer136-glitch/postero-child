<?php
/**
 * GA4 / GTM  (requirements §16)
 *
 * Wired end to end but silent until an ID is saved (Settings → General →
 * "GA4 Measurement ID" / "GTM Container ID"). Tags load through the cookie
 * consent gate — shipped as inert scripts that activate only once the
 * visitor allows the analytics category. Ecommerce events: view_item,
 * add_to_cart, begin_checkout, purchase.
 */
if (!defined('ABSPATH')) exit;

function af_ga4_id() { return trim((string) get_option('af_ga4_id', '')); }
function af_gtm_id() { return trim((string) get_option('af_gtm_id', '')); }

/**
 * A JavaScript expression that is true for the automated visitors GA4 was
 * counting as people. Measured 16 Oct 2026: of 2,414 visits in 28 days about
 * 2,100 were bots — a Singapore fleet on Chrome/Windows 10 with a 1280×1200
 * screen, and US data-centre visits with an 800×600 screen; real visitors
 * use neither size. The site is served by Hostinger's CDN, not Cloudflare,
 * so there is no edge rule to block them; instead every tag (GA4, the Meta
 * pixel) skips loading when this is true. A reporting choice only: nothing is
 * blocked and the page itself is unchanged for anyone.
 */
function af_bot_signal_js() {
    return "navigator.webdriver||(screen.width==800&&screen.height==600)||(screen.width==1280&&screen.height==1200)";
}

add_action('admin_init', function() {
    register_setting('general', 'af_ga4_id', array('type' => 'string', 'sanitize_callback' => function($v) {
        $v = strtoupper(trim((string) $v));
        return ($v === '' || preg_match('/^G-[A-Z0-9]{6,14}$/', $v)) ? $v : '';
    }));
    register_setting('general', 'af_gtm_id', array('type' => 'string', 'sanitize_callback' => function($v) {
        $v = strtoupper(trim((string) $v));
        return ($v === '' || preg_match('/^GTM-[A-Z0-9]{4,10}$/', $v)) ? $v : '';
    }));
    add_settings_field('af_ga4_id', 'GA4 Measurement ID', function() {
        echo '<input type="text" name="af_ga4_id" value="' . esc_attr(af_ga4_id()) . '" placeholder="G-XXXXXXXXXX" class="regular-text">'
           . '<p class="description">Analytics loads only after the visitor allows it in the cookie banner.</p>';
    }, 'general');
    add_settings_field('af_gtm_id', 'GTM Container ID', function() {
        echo '<input type="text" name="af_gtm_id" value="' . esc_attr(af_gtm_id()) . '" placeholder="GTM-XXXXXXX" class="regular-text">';
    }, 'general');
});

/**
 * Add to cart from a product page is a form post and a page load, not
 * WooCommerce's AJAX add, so the 'added_to_cart' listener below never saw
 * it. Note the item in the session; the next page reports it. AJAX adds are
 * left to the listener so nothing is reported twice.
 */
add_action('woocommerce_add_to_cart', function ($key, $product_id, $qty, $variation_id = 0) {
    if (wp_doing_ajax() || !empty($_REQUEST['wc-ajax']) || !function_exists('WC') || !WC()->session) return;
    $p = wc_get_product($variation_id ?: $product_id);
    if (!$p) return;
    $pending = (array) WC()->session->get('af_ga4_atc', array());
    $pending[] = array('item_id' => $p->get_sku() ?: (string) $p->get_id(), 'item_name' => $p->get_name(),
                       'quantity' => (int) $qty, 'price' => (float) wc_get_price_to_display($p));
    WC()->session->set('af_ga4_atc', array_slice($pending, -10));
}, 20, 4);

/** The events this page should report, decided server-side. */
function af_ga4_page_events() {
    $ev = array();
    if (function_exists('WC') && WC()->session && ($atc = WC()->session->get('af_ga4_atc'))) {
        WC()->session->set('af_ga4_atc', null);
        $GLOBALS['af_atc_items'] = (array) $atc;   // inc/meta-pixel.php reports the same add later in this request
        // this page now carries one visitor's cart event: never serve it from cache
        do_action('litespeed_control_set_nocache', 'ga4 add_to_cart event');
        $value = 0;
        foreach ((array) $atc as $i) $value += $i['price'] * $i['quantity'];
        $ev[] = array('add_to_cart', array('currency' => get_woocommerce_currency(), 'value' => round($value, 2), 'items' => array_values((array) $atc)));
    }
    if (function_exists('is_product') && is_product()) {
        $p = af_wc_product();
        if ($p) $ev[] = array('view_item', array(
            'currency' => get_woocommerce_currency(),
            'value'    => (float) wc_get_price_to_display($p),
            'items'    => array(array('item_id' => $p->get_sku() ?: (string) $p->get_id(), 'item_name' => $p->get_name())),
        ));
    }
    if (function_exists('is_checkout') && is_checkout() && !is_order_received_page() && WC()->cart && !WC()->cart->is_empty()) {
        $items = array();
        foreach (WC()->cart->get_cart() as $it) {
            $p = $it['data'];
            $items[] = array('item_id' => $p->get_sku() ?: (string) $p->get_id(), 'item_name' => $p->get_name(), 'quantity' => (int) $it['quantity']);
        }
        $ev[] = array('begin_checkout', array(
            'currency' => get_woocommerce_currency(),
            'value'    => (float) WC()->cart->get_total('edit'),
            'items'    => $items,
        ));
    }
    if (function_exists('is_order_received_page') && is_order_received_page()) {
        $oid = absint(get_query_var('order-received'));
        $order = $oid ? wc_get_order($oid) : false;
        // Only the order this visitor owns: the ?key= WooCommerce puts in the
        // thank-you URL must match. Without it any order number put a stranger's
        // totals and items in the page and marked that order "reported"
        // (security audit, 12 Oct).
        $key = isset($_GET['key']) && is_string($_GET['key']) ? wc_clean(wp_unslash($_GET['key'])) : '';
        if ($order && ($key === '' || !hash_equals((string) $order->get_order_key(), $key))) $order = false;
        if ($order && !$order->get_meta('_af_ga4_purchase_sent')) {
            $items = array();
            foreach ($order->get_items() as $it) {
                $p = $it->get_product();
                $items[] = array('item_id' => ($p && $p->get_sku()) ? $p->get_sku() : (string) $it->get_product_id(),
                                 'item_name' => $it->get_name(), 'quantity' => (int) $it->get_quantity(),
                                 'price' => (float) ($it->get_quantity() ? $it->get_total() / $it->get_quantity() : 0));
            }
            $ev[] = array('purchase', array(
                'transaction_id' => (string) $order->get_order_number(),
                'currency'       => $order->get_currency(),
                'value'          => (float) $order->get_total(),
                'tax'            => (float) $order->get_total_tax(),
                'shipping'       => (float) $order->get_shipping_total(),
                'items'          => $items,
            ));
            $order->update_meta_data('_af_ga4_purchase_sent', 1);   // refreshing the page must not double-report
            $order->save();
        }
    }
    return $ev;
}

add_action('wp_head', function() {
    $ga4 = af_ga4_id(); $gtm = af_gtm_id();
    if ($ga4 === '' && $gtm === '') return;
    // type="text/plain" + data-consent: inert until the consent module
    // (inc/cookie-consent.php) flips it live for the analytics category
    if ($gtm !== '') {
        echo '<script type="text/plain" data-consent="analytics">'
           . "(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});"
           . "var f=d.getElementsByTagName(s)[0],j=d.createElement(s);j.async=true;"
           . "j.src='https://www.googletagmanager.com/gtm.js?id='+i+'&l='+l;f.parentNode.insertBefore(j,f);"
           . "})(window,document,'script','dataLayer'," . wp_json_encode($gtm) . ');</script>' . "\n";
    }
    if ($ga4 !== '') {
        echo '<script type="text/plain" data-consent="analytics" src="https://www.googletagmanager.com/gtag/js?id=' . esc_attr($ga4) . '"></script>' . "\n";
        $events = af_ga4_page_events();
        echo '<script type="text/plain" data-consent="analytics">'
           . "window.dataLayer=window.dataLayer||[];window.gtag=window.gtag||function(){dataLayer.push(arguments);};"
           . "if(!(" . af_bot_signal_js() . ")){"
           . "gtag('js',new Date());gtag('config'," . wp_json_encode($ga4) . ");";
        foreach ($events as $e) {
            echo "gtag('event'," . wp_json_encode($e[0]) . ',' . wp_json_encode($e[1]) . ');';
        }
        // add_to_cart fires client-side on WooCommerce's own AJAX event
        echo "if(window.jQuery){jQuery(document.body).on('added_to_cart',function(){gtag('event','add_to_cart',{currency:" . wp_json_encode(get_woocommerce_currency()) . "});});}"
           . '}</script>' . "\n";
    }
}, 4);

/**
 * No ID saved in the theme's setting, but the GA4 tag carried over from Header
 * Footer Code Manager (inc/ports/hfcm.php) is on every page: send the same
 * ecommerce events to that tag. GA4 had page views and no shop events at all
 * in 90 days (checked 14 Oct) because the block above stays silent without
 * an ID. Printed after the tag (wp_head 10) and, like it, outside the consent
 * gate, so it measures the same visitors the tag already does.
 */
add_action('wp_head', function () {
    if (af_ga4_id() !== '' || af_gtm_id() !== '' || !defined('AF_HFCM_GA4_ID') || is_feed()) return;
    $events = af_ga4_page_events();
    echo '<script>if(typeof gtag==="function"){';
    foreach ($events as $e) {
        echo "gtag('event'," . wp_json_encode($e[0]) . ',' . wp_json_encode($e[1]) . ');';
    }
    echo "if(window.jQuery){jQuery(document.body).on('added_to_cart',function(){gtag('event','add_to_cart',{currency:" . wp_json_encode(get_woocommerce_currency()) . "});});}"
       . '}</script>' . "\n";
}, 11);
