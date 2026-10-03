<?php
/**
 * Wishlist: the plugin "WPC Smart Wishlist for WooCommerce" 6.2.0, moved into
 * the theme.
 *
 * inc/ports/woosw/ is the plugin's own code, copied unchanged: woosw_init(),
 * the WPCleverWoosw class (buttons, the wishlist page [woosw_list] on page 14,
 * the wc-ajax add/remove/load endpoints, the header count), Woosw_Helper and
 * Woosw_Statistics. Its assets are copied byte for byte to
 * assets/ports/woosw/assets/ with the plugin's folder layout, so handles,
 * woosw_vars and markup stay the same. The wishlists themselves stay where
 * the plugin kept them (options woosw_list_*, user meta woosw_key(s), option
 * woosw_page_id), so nobody loses a saved item.
 *
 * The parent theme (postero) checks function_exists('woosw_init') and calls
 * WPCleverWoosw::get_key()/get_count()/get_url() for the header link; both
 * still exist. Left out: the shared WPC dashboard/kit/log admin pages, the
 * activation hook (the wishlist page exists already) and the HPOS declaration.
 *
 * While the plugin is still active this file does nothing.
 */
defined('ABSPATH') || exit;

if (function_exists('woosw_init') || class_exists('WPCleverWoosw', false) || defined('WOOSW_LITE')) {
    return; // the plugin is active and does the work
}

if (!defined('WOOSW_VERSION')) {
    define('WOOSW_VERSION', '6.2.0');
    define('WOOSW_FILE', __DIR__ . '/woosw/wpc-smart-wishlist.php');
    define('WOOSW_URI', get_stylesheet_directory_uri() . '/assets/ports/woosw/');
    define('WOOSW_DIR', get_stylesheet_directory() . '/assets/ports/woosw/');
    define('WOOSW_SUPPORT', 'https://wpclever.net/support/?utm_source=support&utm_medium=woosw&utm_campaign=wporg');
    define('WOOSW_REVIEWS', 'https://wordpress.org/support/plugin/woo-smart-wishlist/reviews/');
    define('WOOSW_CHANGELOG', 'https://wordpress.org/plugins/woo-smart-wishlist/#developers');
    define('WOOSW_DISCUSSION', 'https://wordpress.org/support/plugin/woo-smart-wishlist');
}

// woosw_init() includes includes/class-helper.php and class-statistics.php by
// relative path; PHP resolves those next to the copied file.
require_once __DIR__ . '/woosw/wpc-smart-wishlist.php';

// The plugin ran this at plugins_loaded:11, which has passed by the time the
// theme loads; everything it hooks is still ahead.
woosw_init();
