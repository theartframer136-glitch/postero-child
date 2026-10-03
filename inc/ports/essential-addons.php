<?php
/**
 * Essential Addons for Elementor (Lite) 6.8.5: the parts this site uses, moved
 * into the theme.
 *
 * Read from the live database (tools/diag-elementor-addons.php): four of its
 * widgets are placed — the Woo Product Carousel on the home page (#75), the
 * Login | Register form on Login (#6030) and Sign-up (#6032), Advanced Tabs in
 * the mega-menu template #7411 and Woo Add To Cart in the Elementor loop item
 * #9012. None of its extensions is switched on anywhere: the
 * eael_image_masking_*, eael_vto_* and eael_liquid_glass_effect values found
 * on elements are saved defaults without their enabling switch, and with the
 * switch off each extension's render code adds nothing (checked in
 * Image_Masking::before_render, Vertical_Text_Orientation::before_render — its
 * repeater has the switch as a condition, so Elementor hands it over empty —
 * and Liquid_Glass_Effect, whose free render hooks only fire a Pro action).
 * Global extensions (scroll to top, reading progress, table of contents) are
 * not enabled either: eael_global_settings is {"eael_ext_scroll_to_top":[]}.
 *
 * inc/ports/eael/includes/ is the plugin's own code, copied unchanged and
 * autoloaded under its own namespace exactly as the plugin's autoload.php did:
 * the four widget classes and the carousel's templates, Classes\Helper,
 * Asset_Builder and Elements_Manager, the traits they use, the eael-select2
 * control and the Custom JS page-settings control. One line differs:
 * Traits\Template_Query::get_template_dir() reads the carousel templates from
 * inc/ports/eael/includes/Template/. inc/ports/eael/class-af-eael-port.php is
 * Bootstrap's storefront half (forms, AJAX, widget registration, the
 * WooCommerce hooks); inc/ports/eael/config.php is config.php cut to these
 * widgets. The assets are copied byte for byte to assets/ports/eael/assets/
 * with the plugin's layout.
 *
 * One trace of a widget that is not placed is kept too: the plugin built a
 * type instance of every switched-on widget whenever Elementor filled its
 * widget registry, and the Woo Checkout one adds the eael-woo-checkout body
 * class on the checkout page (AF_Eael_Port::woo_checkout_type_instance()).
 * The other widgets' type instances only add what the carousel's already
 * adds (the WooCommerce single-product scripts in the footer).
 *
 * Assets come out as before: Asset_Builder still enqueues eael-general (CSS
 * and JS, every front-end page, with the same `localize` object) and still
 * writes and enqueues the per-document files
 * uploads/essential-addons-elementor/eael-<id>.css|js, same handles, same
 * names, same versions, same content, built from the same config. The only
 * URL that moves is eael-general's own: wp-content/themes/postero-child/
 * assets/ports/eael/assets/front-end/... instead of wp-content/plugins/
 * essential-addons-for-elementor-lite/assets/front-end/... (same file, same
 * ?ver=6.8.5).
 *
 * Left out (no effect on this site's storefront): the admin screens, setup
 * wizard, notices, usage tracking, plugin installer and promotions; the
 * Theme Builder (no ea_theme_builder templates exist) and the Mega Menu
 * widget; the other 60-odd widgets and their AJAX endpoints (load more,
 * pagination, product gallery, checkout, Facebook feed, compare table);
 * every extension class except Custom JS — including Post Duplicator, whose
 * only front-end trace was an "EA Duplicator" item in logged-in editors'
 * admin bar; the footer printers for the global reading-progress bar, table
 * of contents, scroll-to-top button and Advanced Accordion FAQ schema (all
 * empty on this site); the eael_global_settings upkeep on editor save; the
 * WCML and Beehive-theme filters and the Mondial Relay integration.
 *
 * While the plugin is still active this file does nothing.
 */
defined('ABSPATH') || exit;

if (defined('EAEL_PLUGIN_VERSION') || class_exists('Essential_Addons_Elementor\Classes\Bootstrap', false)) {
    return; // the plugin is active and does the work
}

define('AF_EAEL_PORT', true);
define('AF_EAEL_PORT_DIR', __DIR__ . '/eael/');

// essential_adons_elementor.php. EAEL_PLUGIN_PATH/URL point at the copied
// assets, so every asset path and URL in the copied code resolves unchanged.
define('EAEL_PLUGIN_PATH', trailingslashit(get_stylesheet_directory()) . 'assets/ports/eael/');
define('EAEL_PLUGIN_URL', trailingslashit(get_stylesheet_directory_uri()) . 'assets/ports/eael/');
define('EAEL_PLUGIN_VERSION', '6.8.5');
define('EAEL_ASSET_PATH', wp_upload_dir()['basedir'] . '/essential-addons-elementor');
define('EAEL_ASSET_URL', wp_upload_dir()['baseurl'] . '/essential-addons-elementor');

// autoload.php, pointed at inc/ports/eael/includes/.
spl_autoload_register(function ($class) {
    $prefix = 'Essential_Addons_Elementor\\';
    $len    = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $file = AF_EAEL_PORT_DIR . 'includes/' . str_replace('\\', '/', substr($class, $len)) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

/**
 * Neutralize WordPress shortcode syntax in untrusted external / reflected data
 * (essential_adons_elementor.php, unchanged; the Login | Register form uses it).
 */
if (!function_exists('eael_neutralize_shortcodes')) {
    function eael_neutralize_shortcodes($value) {
        if (is_array($value)) {
            return array_map('eael_neutralize_shortcodes', $value);
        }

        if (is_string($value)) {
            return str_replace(array('[', ']'), array('&#91;', '&#93;'), $value);
        }

        return $value;
    }
}

$GLOBALS['eael_config'] = require AF_EAEL_PORT_DIR . 'config.php';

require_once AF_EAEL_PORT_DIR . 'class-af-eael-port.php';

// The plugin built this at plugins_loaded, which has passed by the time the
// theme loads; everything it hooks (init, wp_loaded, wp, wp_enqueue_scripts,
// the Elementor registries, the AJAX endpoints) is still ahead.
AF_Eael_Port::instance();

// Image_Masking::enqueue_scripts() (includes/Extensions/Image_Masking.php): the
// one thing the image-masking extension does on a page where no element uses
// it — the EAELImageMaskingConfig object beside elementor-frontend, on every
// front-end page (wp_enqueue_scripts:10). Nothing reads it unless masking is
// switched on for an element; the svg-shapes folder it names is not copied.
add_action('wp_enqueue_scripts', function () {
    $data = array('svg_dir_url' => EAEL_PLUGIN_URL . 'assets/front-end/img/image-masking/svg-shapes/');
    wp_localize_script('elementor-frontend', 'EAELImageMaskingConfig', $data);
});
