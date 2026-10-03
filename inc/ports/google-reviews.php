<?php
/**
 * Google reviews: the storefront half of the plugin "Embedder for Google
 * Reviews" 2.1.1, moved into the theme ([google-reviews] slider on the home
 * page).
 *
 * inc/ports/grwp/public/includes/ is the plugin's own front-end code, copied
 * unchanged (the shortcode, the output and widget classes, the partials and
 * the allowed-HTML list); inc/ports/grwp/helpers.php holds the plugin's helper
 * functions, unchanged. Its dist/ folder (CSS, JS, images, vendor files) is
 * copied byte for byte to assets/ports/grwp/dist/, so the handles, the
 * swiperSettings object and the markup stay the same, and the theme's own
 * slider loader (functions.php, "af_grwp_page_has_widget") finds the same
 * files in the same order.
 *
 * Left out: the Freemius SDK (about 47 PHP files loaded on every request) and
 * the admin screens. The free build never had premium code, so grwp_fs() below
 * answers "no premium" exactly as Freemius did for this install. The reviews
 * themselves stay in the plugin's options (gr_latest_results*,
 * google_reviews_option_name, grwp_place_info); to pull fresh reviews from
 * Google, switch the plugin back on for a moment and press "Pull reviews".
 *
 * While the plugin is still active this file does nothing.
 */
defined('ABSPATH') || exit;

if (defined('GRWP_GOOGLE_REVIEWS_VERSION') || function_exists('grwp_fs')) {
    return; // the plugin is active and does the work
}

define('AF_GRWP_PORT', true);
define('GRWP_GOOGLE_REVIEWS_VERSION', '2.1.1');
define('GR_BASE_PATH', get_stylesheet_directory() . '/assets/ports/grwp/');
define('GR_BASE_PATH_PUBLIC', __DIR__ . '/grwp/public/');
define('GR_PLUGIN_DIR_URL', get_stylesheet_directory_uri() . '/assets/ports/grwp/');
define('GR_PLUGIN_REL_PATH', 'embedder-for-google-reviews');

if (!class_exists('AF_Grwp_Free_Licence', false)) {
    /* What the plugin's Freemius object answers on this free install with no
       licence and no trial: can_use_premium_code() = is_trial() ||
       has_features_enabled_license() = false; is__premium_only() = false. */
    class AF_Grwp_Free_Licence {
        public function can_use_premium_code() { return false; }
        public function is__premium_only() { return false; }
        public function is_premium() { return false; }
        public function is_trial() { return false; }
    }
}
if (!function_exists('grwp_fs')) {
    function grwp_fs() {
        static $fs = null;
        if ($fs === null) $fs = new AF_Grwp_Free_Licence();
        return $fs;
    }
}

require_once __DIR__ . '/grwp/helpers.php';
require_once GR_BASE_PATH_PUBLIC . 'includes/allowed-html.php';
require_once GR_BASE_PATH_PUBLIC . 'includes/class-grwp-google-reviews-output.php';
require_once GR_BASE_PATH_PUBLIC . 'includes/class-grwp-reviews-widget-slider.php';
require_once GR_BASE_PATH_PUBLIC . 'includes/class-grwp-reviews-widget-grid.php';
require_once GR_BASE_PATH_PUBLIC . 'includes/class-grwp-reviews-widget-badge.php';
require_once GR_BASE_PATH_PUBLIC . 'includes/class-grwp-shortcode.php';
require_once GR_BASE_PATH_PUBLIC . 'includes/class-grwp-google-reviews-public.php';

/* GRWP_Google_Reviews_Startup (public/includes/class-grwp-google-reviews-startup.php):
   the shortcode, and the public styles and scripts at wp_enqueue_scripts:10. */
new GRWP_Shortcode();
$af_grwp_public = new GRWP_Google_Reviews_Public('google-reviews', GRWP_GOOGLE_REVIEWS_VERSION);
add_action('wp_enqueue_scripts', array($af_grwp_public, 'enqueue_styles'));
add_action('wp_enqueue_scripts', array($af_grwp_public, 'enqueue_scripts'));
unset($af_grwp_public);
