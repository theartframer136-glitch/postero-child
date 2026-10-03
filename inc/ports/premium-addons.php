<?php
/**
 * Premium Addons for Elementor 4.11.110: the one part this site uses, moved
 * into the theme.
 *
 * Read from the live database (tools/diag-elementor-addons.php): no Premium
 * Addons widget is placed anywhere. Its only effect on the storefront is the
 * "Wrapper Link" extension, switched on for 5 elements of the home page (the
 * Try on Wall and Frame the Moment boxes), plus the tiny elements-handler
 * script it prints on every page. (514 elements also carry the global-tooltip
 * text "Hi, I'm a global tooltip." but never had tooltips switched on, so
 * nothing renders from them.)
 *
 * inc/ports/pa/wrapper-link.php is the plugin's own Wrapper Link class (same
 * controls, same render attributes: class premium-wrapper-link-yes,
 * data-premium-element-link, cursor:pointer). Its scripts are copied byte for
 * byte to assets/ports/pa/ with the plugin's layout and registered under the
 * same handles, version and conditions.
 *
 * While the plugin is still active this file does nothing.
 */
defined('ABSPATH') || exit;

if (defined('PREMIUM_ADDONS_VERSION') || class_exists('PremiumAddons\Addons\Wrapper_Link')) {
    return; // the plugin is active and does the work
}

define('AF_PA_PORT_VERSION', '4.11.110');
define('AF_PA_PORT_URL', get_stylesheet_directory_uri() . '/assets/ports/pa/');

require_once __DIR__ . '/pa/wrapper-link.php';

// Premium_Addons_Integration: the extension is built once Elementor is up.
add_action('elementor/init', function () {
    AF_PA_Wrapper_Link::get_instance();
});

// Assets_Manager::register_frontend_scripts() (includes/assets-manager.php),
// the 'pa-wrapper-link' handle: js/…js with SCRIPT_DEBUG, min-js/….min.js otherwise.
add_action('elementor/frontend/after_register_scripts', function () {
    $debug  = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG;
    $dir    = $debug ? 'js' : 'min-js';
    $suffix = $debug ? '' : '.min';
    wp_register_script(
        'pa-wrapper-link',
        AF_PA_PORT_URL . 'assets/frontend/' . $dir . '/premium-wrap-link' . $suffix . '.js',
        array('jquery'),
        AF_PA_PORT_VERSION,
        true
    );
});

// Assets_Manager::handle_assets_load() -> enqueue_elements_handler(), every
// front-end page (wp_enqueue_scripts:100, the assets generator is on).
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_script(
        'pa-elements-handler',
        AF_PA_PORT_URL . 'assets/frontend/min-js/elements-handler.min.js',
        array(),
        AF_PA_PORT_VERSION,
        true
    );
}, 100);
