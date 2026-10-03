<?php
/**
 * Smart messages: the storefront half of the plugin
 * "WPC Smart Messages for WooCommerce" 4.3.4, moved into the theme.
 *
 * inc/ports/wpcsm/class-frontend.php and class-shortcode.php are the plugin's
 * own files, copied unchanged; class-backend.php keeps only the post type and
 * the location table they read. The assets are copied byte for byte to
 * assets/ports/wpcsm/ with the plugin's folder layout, so the handles, markup,
 * classes and hooks stay the same. The messages themselves (wpc_smart_message
 * posts and their wpcsm_* meta) stay in the database and are read as before.
 *
 * Loaded from the top of functions.php (inc/plugin-ports.php): the plugin hung
 * its messages on WooCommerce hooks before any theme callback at the same
 * priority (e.g. the Art Code line at woocommerce_single_product_summary:6).
 *
 * While the plugin is still active this file does nothing.
 */
defined('ABSPATH') || exit;

if (function_exists('wpcsm_init') || class_exists('Wpcsm_Frontend', false) || defined('WPCSM_LITE')) {
    return; // the plugin is active and does the work
}

if (!defined('WPCSM_VERSION')) {
    define('WPCSM_VERSION', '4.3.4');
    define('WPCSM_URI', get_stylesheet_directory_uri() . '/assets/ports/wpcsm/');
    define('WPCSM_DIR', get_stylesheet_directory() . '/assets/ports/wpcsm/');
}

require_once __DIR__ . '/wpcsm/class-shortcode.php';
require_once __DIR__ . '/wpcsm/class-backend.php';
require_once __DIR__ . '/wpcsm/class-frontend.php';
