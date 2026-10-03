<?php
/**
 * Instagram feed: the plugin "Social Feed Gallery" (insta-gallery) 5.0.7,
 * moved into the theme (the [insta-gallery] feed on the home page, its REST
 * endpoints that fetch the posts, the token-renewal cron, the settings page).
 *
 * inc/ports/qligg/ is the plugin's own code, copied unchanged (lib/, its
 * composer vendor/ and jetpack_vendor/, compatibility/). Its build/ and
 * assets/ folders are copied byte for byte to assets/ports/qligg/. The plugin
 * builds every asset URL with plugins_url($path, QLIGG_PLUGIN_FILE); the
 * filter below answers those calls with the theme copy's URL, so not one line
 * of the plugin had to change. Accounts, feeds and settings stay in the same
 * options (insta_gallery_accounts, insta_gallery_feeds, …).
 *
 * Left out: the vendor_packages/ add-ons (dashboard news widget, promotion
 * notices, plugin-install tab, plugin-table links, feedback form) — admin
 * marketing only.
 *
 * While the plugin is still active this file does nothing.
 */
defined('ABSPATH') || exit;

if (defined('QLIGG_PLUGIN_VERSION') || class_exists('QuadLayers\IGG\Plugin', false)) {
    return; // the plugin is active and does the work
}

// insta-gallery.php, the same constants; FILE/DIR point at the asset copy.
define('QLIGG_PLUGIN_NAME', 'Social Feed Gallery');
define('QLIGG_PLUGIN_VERSION', '5.0.7');
define('QLIGG_PLUGIN_FILE', get_stylesheet_directory() . '/assets/ports/qligg/insta-gallery.php');
define('QLIGG_PLUGIN_BASENAME', 'insta-gallery/insta-gallery.php');
define('QLIGG_PLUGIN_DIR', get_stylesheet_directory() . '/assets/ports/qligg/');
define('QLIGG_DOMAIN', 'qligg');
define('QLIGG_PREFIX', QLIGG_DOMAIN);
define('QLIGG_WORDPRESS_URL', 'https://wordpress.org/plugins/insta-gallery/');
define('QLIGG_REVIEW_URL', 'https://wordpress.org/support/plugin/insta-gallery/reviews/?filter=5#new-post');
define('QLIGG_GROUP_URL', 'https://www.facebook.com/groups/quadlayers');
define('QLIGG_DEVELOPER', false);
define('QLIGG_ACCOUNT_URL', admin_url('admin.php?page=qligg_backend&tab=accounts'));

// plugins_url('/build/…', QLIGG_PLUGIN_FILE) -> the theme copy.
add_filter('plugins_url', function ($url, $path, $plugin) {
    if ($plugin === QLIGG_PLUGIN_FILE) {
        $url = get_stylesheet_directory_uri() . '/assets/ports/qligg';
        if ($path !== '' && is_string($path)) $url .= '/' . ltrim($path, '/');
    }
    return $url;
}, 10, 3);

require_once __DIR__ . '/qligg/vendor/autoload.php';
require_once __DIR__ . '/qligg/compatibility/wordpress.php';
require_once __DIR__ . '/qligg/compatibility/php.php';
require_once __DIR__ . '/qligg/compatibility/old.php';
require_once __DIR__ . '/qligg/compatibility/widget.php';
require_once __DIR__ . '/qligg/compatibility/class-backend.php';
require_once __DIR__ . '/qligg/compatibility/class-frontend.php';
require_once __DIR__ . '/qligg/compatibility/class-gutenberg.php';
require_once __DIR__ . '/qligg/lib/class-plugin.php';
