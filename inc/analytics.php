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

/** The events this page should report, decided server-side. */
function af_ga4_page_events() {
    $ev = array();
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
           . "window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}"
           . "gtag('js',new Date());gtag('config'," . wp_json_encode($ga4) . ");";
        foreach ($events as $e) {
            echo "gtag('event'," . wp_json_encode($e[0]) . ',' . wp_json_encode($e[1]) . ');';
        }
        // add_to_cart fires client-side on WooCommerce's own AJAX event
        echo "if(window.jQuery){jQuery(document.body).on('added_to_cart',function(){gtag('event','add_to_cart',{currency:" . wp_json_encode(get_woocommerce_currency()) . "});});}"
           . '</script>' . "\n";
    }
}, 4);
