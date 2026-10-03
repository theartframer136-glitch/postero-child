<?php

/**
 * Plugin Name:             Social Feed Gallery
 * Plugin URI:              https://quadlayers.com/products/instagram-feed-gallery/
 * Description:             Display beautiful and responsive galleries on your website from your Instagram feed account.
 * Version:                 5.0.7
 * Text Domain:             insta-gallery
 * Author:                  QuadLayers
 * Author URI:              https://quadlayers.com
 * License:                 GPLv3
 * Domain Path:             /languages
 * Request at least:        4.7
 * Tested up to:            7.0
 * Requires PHP:            5.6
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'QLIGG_PLUGIN_NAME', 'Social Feed Gallery' );
define( 'QLIGG_PLUGIN_VERSION', '5.0.7' );
define( 'QLIGG_PLUGIN_FILE', __FILE__ );
define( 'QLIGG_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'QLIGG_PLUGIN_DIR', __DIR__ . DIRECTORY_SEPARATOR );
define( 'QLIGG_DOMAIN', 'qligg' );
define( 'QLIGG_PREFIX', QLIGG_DOMAIN );
define( 'QLIGG_WORDPRESS_URL', 'https://wordpress.org/plugins/insta-gallery/' );
define( 'QLIGG_REVIEW_URL', 'https://wordpress.org/support/plugin/insta-gallery/reviews/?filter=5#new-post' );
define( 'QLIGG_GROUP_URL', 'https://www.facebook.com/groups/quadlayers' );
define( 'QLIGG_DEVELOPER', false );

define( 'QLIGG_ACCOUNT_URL', admin_url( 'admin.php?page=qligg_backend&tab=accounts' ) );

/**
* Load composer autoload
*/
require_once __DIR__ . '/vendor/autoload.php';
/**
* Load compatibility
*/
require_once __DIR__ . '/compatibility/wordpress.php';
require_once __DIR__ . '/compatibility/php.php';
require_once __DIR__ . '/compatibility/old.php';
require_once __DIR__ . '/compatibility/widget.php';
require_once __DIR__ . '/compatibility/class-backend.php';
require_once __DIR__ . '/compatibility/class-frontend.php';
require_once __DIR__ . '/compatibility/class-gutenberg.php';
/**
* Load vendor_packages packages
*/
require_once __DIR__ . '/vendor_packages/wp-i18n-map.php';
require_once __DIR__ . '/vendor_packages/wp-dashboard-widget-news.php';
require_once __DIR__ . '/vendor_packages/wp-plugin-table-links.php';
require_once __DIR__ . '/vendor_packages/wp-notice-plugin-promote.php';
require_once __DIR__ . '/vendor_packages/wp-plugin-install-tab.php';
require_once __DIR__ . '/vendor_packages/wp-plugin-feedback.php';
/**
* Load plugin classes
*/
require_once __DIR__ . '/lib/class-plugin.php';
