<?php
/**
 * Quick view: the plugin "WPC Smart Quick View for WooCommerce" 4.3.1, moved
 * into the theme.
 *
 * inc/ports/woosq/wpc-smart-quick-view.php is the plugin's own code, copied
 * unchanged (woosq_init() and the WPCleverWoosq class: the [woosq] button,
 * the popup at /?wc-ajax=woosq_quickview, the #woosq-<id> cart links, its
 * settings page). Its assets are copied byte for byte to
 * assets/ports/woosq/assets/ with the plugin's folder layout, so the handles,
 * the woosq_vars object and the markup stay the same. Settings: none saved,
 * so the plugin defaults apply, as before.
 *
 * The parent theme prints the card button only when woosq_init() exists,
 * which it still does. Left out: the shared WPC dashboard/kit admin pages and
 * the HPOS declaration (plugin-only).
 *
 * While the plugin is still active this file does nothing.
 */
defined('ABSPATH') || exit;

if (function_exists('woosq_init') || class_exists('WPCleverWoosq', false) || defined('WOOSQ_LITE')) {
    return; // the plugin is active and does the work
}

if (!defined('WOOSQ_VERSION')) {
    define('WOOSQ_VERSION', '4.3.1');
    define('WOOSQ_FILE', __DIR__ . '/woosq/wpc-smart-quick-view.php');
    define('WOOSQ_URI', get_stylesheet_directory_uri() . '/assets/ports/woosq/');
    define('WOOSQ_DIR', get_stylesheet_directory() . '/assets/ports/woosq/');
    define('WOOSQ_SUPPORT', 'https://wpclever.net/support?utm_source=support&utm_medium=woosq&utm_campaign=wporg');
    define('WOOSQ_REVIEWS', 'https://wordpress.org/support/plugin/woo-smart-quick-view/reviews/');
    define('WOOSQ_CHANGELOG', 'https://wordpress.org/plugins/woo-smart-quick-view/#developers');
    define('WOOSQ_DISCUSSION', 'https://wordpress.org/support/plugin/woo-smart-quick-view');
}

require_once __DIR__ . '/woosq/wpc-smart-quick-view.php';

// The plugin ran this at plugins_loaded:11, which has passed by the time the
// theme loads; everything it hooks (init, wp_enqueue_scripts, the cart link
// filter, the AJAX endpoint) is still ahead.
woosq_init();
