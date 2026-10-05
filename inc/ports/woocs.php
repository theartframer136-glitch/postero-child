<?php
/**
 * Currency switcher: the plugin "FOX - Currency Switcher Professional for
 * WooCommerce" (woocommerce-currency-switcher, WOOCS) 1.5.4, moved into the
 * theme.
 *
 * What it does on this site, and still does from here: US dollars are the
 * base currency and Canadian dollars the second one, at the fixed rate 1.37
 * (option woocs). For a shopper in CAD it converts every product price, the
 * variation prices, the cart, the shipping rates and fixed coupons, and the
 * shopper pays in CAD (multiple currencies allowed); orders keep
 * _woocs_order_rate, _woocs_order_base_currency and
 * _woocs_order_currency_changed_mannualy, and emails, My account and the
 * order screens show each order in its own currency. In USD it still runs:
 * its sale-price, free-shipping, cart-hash and mini-cart filters, the
 * woocs_price_code / woocs_special_price_code spans around prices, the
 * currency-usd / currency-cad body class, and on every page front.css,
 * front.js (woocs_redirect(), the woocs_* JS variables), ddslick, the two
 * price-filter scripts and its own price slider in place of WooCommerce's.
 * The parent theme's header switcher (Elementor widget
 * postero-product-currency, in both headers) is built only when class
 * WOOCS_STARTER exists, reads global $WOOCS and calls woocs_redirect(); the
 * child theme's currency code (af_fx_rate(), the wp_loaded pin in
 * functions.php) reads $WOOCS too. All of that is the plugin's own code here.
 *
 * inc/ports/woocs/ holds the plugin's PHP with its folder layout: classes/
 * and views/ copied unchanged, and boot.php, which is index.php from line
 * 104 on (constants, class files, WOOCS_STARTER, the WOOCS object, the
 * closures) with the lines that differ marked "Port:". The lines before 104,
 * the plugin's early returns, are copied below as they are: like the plugin,
 * the copy does not load for ?woocommerce_gpf, for REST routes outside its
 * list, nor for the admin refund and order-items AJAX calls. The css/, js/,
 * img/ and fonts/ folders and the shortcode skins' CSS and JS (views/) are
 * copied byte for byte to assets/ports/woocs/ with the plugin's layout, so
 * the handles, versions and file names stay the same; only their URL moves
 * from the plugin folder to the theme.
 *
 * Left out: the Freemius bootstrap (index.php 31-36; this build ships no
 * freemius.php, so the plugin skipped it too), the HPOS compatibility
 * declaration (see boot.php) and the translation files (the site runs in
 * en_US). Settings, rates and order data stay where they are (the woocs*
 * options, the per-visitor transients, the order meta).
 *
 * Admin keeps everything: WooCommerce > Settings > Currency, the FOX order
 * box and order recalculation, its widgets, shortcodes, REST routes
 * (woocs/v3) and AJAX actions. What is lost is updates: the copy stays at
 * 1.5.4.
 *
 * Differences: the asset URLs (above); on Plugins, the switched-off plugin's
 * row still shows the copy's Settings / Documentation / Go Pro! links; and in
 * the requests where index.php returns early, PHP had still declared the
 * plugin's WOOCS_STARTER class (a file's classes exist once it is compiled)
 * without a $WOOCS, so the parent theme's switcher widget was built and
 * called get_currencies() on nothing; without the plugin the class does not
 * exist in those requests and the widget is simply not built.
 *
 * While the plugin is still active this file does nothing.
 */
defined('ABSPATH') || exit;

// index.php returns early in some requests (below) before it defines
// anything, so "the plugin is active" is "its index.php was loaded".
if (defined('WOOCS_VERSION') || class_exists('WOOCS_STARTER', false)
    || in_array(realpath(WP_PLUGIN_DIR . '/woocommerce-currency-switcher/index.php'), get_included_files(), true)) {
    return; // the plugin is active and does the work
}

// The plugin's index.php lines 27-102, unchanged (31-36, Freemius, left out).
if ( isset( $_GET['woocommerce_gpf'] ) ) {
	return false;
}

// disable FOX influence for REST api requests
if ( isset( $_SERVER['SCRIPT_URI'] ) ) {
	$uri = wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['SCRIPT_URI'] ) ) );
	$uri = explode( '/', trim( $uri['path'], ' /' ) );
	if ( $uri[0] === 'wp-json' ) {
		$show_legacy = array( 'widget-types', 'sidebars', 'widgets', 
			'batch', 'collection-data', 'cart', 'store' );
		$match       = array_intersect( $show_legacy, $uri );

		if ( count( $match ) == 0 ) {
			$allow = array( 'woocs', 'divi-ajax-filter', 'bricks' );
			
			$extra = get_option( 'woocs_rest_allow_namespaces', '' );
			if ( $extra ) {
				$extra = array_filter( array_map( 'trim', explode( ',', $extra ) ) );
				$allow = array_merge( $allow, $extra );
			}
			
			if ( isset( $uri[1] ) and ! in_array( $uri[1], $allow ) ) {
				return; // !!it is important for different reports to exclude FOX influence
			}
		}
	}
}


if ( defined( 'DOING_AJAX' ) ) {

	add_action( 'wp_ajax_woocommerce_refund_line_items', function() {
		//https://pluginus.net/support/topic/unable-to-refund-order-invalid-refund-amount/
		if ( isset( $_POST['refund_amount'] ) ) {
			$_POST['refund_amount'] = str_replace( ',', '.', wp_unslash( $_POST['refund_amount'] ) );
		}
	}, 1 );

	if ( isset( $_REQUEST['action'] ) ) {
		// do not recalculate refund amounts when we are in order backend
		if ( $_REQUEST['action'] == 'woocommerce_refund_line_items' ) {
			if ( ! class_exists( 'WooCommerce_PDF_IPS_Pro' ) && ! class_exists( 'WC_Smart_Coupons' ) && ! class_exists( 'ACFWF' ) ) {
				return;
			}

			if ( apply_filters( 'woocs_disable_backend_refund_calculation', false ) ) {
				return;
			}
		}

		if ( isset( $_REQUEST['order_id'] ) and $_REQUEST['action'] == 'woocommerce_load_order_items' ) {
			return;
		}

		//fix for BEAR plugin
		if ( strpos($_REQUEST['action'], 'woobe') !== false ) {
			return;
		}
	}
}

// fix for WooCommerce PayPal Payments
if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
	$rest_route = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
	if ( strpos( $rest_route, '/refunds' ) !== false || strpos( $rest_route, 'paypal' ) !== false ) {
		return;
	}
}

/*
 * Hook order. The plugin's file ran before woocommerce-square's and
 * WooCommerce's (active_plugins sorts 'woocommerce-currency-switcher/'
 * first), and they share hook and priority with it (init, admin_init,
 * rest_api_init, admin_notices at 10, and more). The copy runs after them,
 * so its callbacks are put back where the plugin had them.
 *
 * If the copy stops with an error, failed.php takes out whatever it had
 * hooked and the shop runs in US dollars only (AF_WOOCS_PORT_FAILED), rather
 * than half a currency switcher converting some amounts and not others.
 */
$af_woocs_hooks = af_ports_hook_snapshot();
try {
    require_once __DIR__ . '/woocs/boot.php';
} catch (\Throwable $af_woocs_error) {
    require __DIR__ . '/woocs/failed.php';
}
af_ports_restore_order($af_woocs_hooks, 'woocommerce-currency-switcher');
unset($af_woocs_hooks, $af_woocs_error);
