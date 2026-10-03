<?php
/**
 * Header & footer: the plugin "Ultimate Addons for Elementor (UAE)" lite
 * (header-footer-elementor) 2.9.5, moved into the theme.
 *
 * What it does on this site, and still does from here:
 *  - the elementor-hf post type and the target rules that pick the template
 *    for each request: "Header 1" (#443) for visitors, "Header 1 - after
 *    login" (#6147) for logged-in users, "Footer 1" (#20) and "Footer Bar"
 *    (#2592, before the footer) for everyone, all "Entire Website";
 *  - hfe_init(), hfe_header_enabled(), hfe_footer_enabled(),
 *    hfe_is_before_footer_enabled(), the hfe_header / hfe_footer_before /
 *    hfe_footer actions (through the plugin's fallback compatibility for
 *    themes it has no class for, as for postero) and
 *    HFE\Lib\Astra_Target_Rules_Fields, which the parent theme's header,
 *    footer and breadcrumb code call;
 *  - rendering the templates through Elementor, their CSS, the plugin's CSS
 *    and the Font Awesome / icon-list / social-icons styles on every page,
 *    the body classes (ehf-header, ehf-footer, ehf-template-*,
 *    ehf-stylesheet-*), the [hfe_template], [hfe_current_year] and
 *    [hfe_site_title] shortcodes, the cart-fragment filter, and the
 *    scroll-to-top and reading-progress extensions (with their Site
 *    Settings tabs);
 *  - all fifteen of its Elementor widgets, as in the plugin (three are placed
 *    on the site: Site Logo, Navigation Menu and Basic Posts on the home page);
 *  - editing: the post type keeps Elementor support, and the template
 *    metabox (type, display rules, user roles, canvas option) still saves.
 *
 * inc/ports/hfe/ holds the plugin's PHP with its folder layout; lines that
 * differ are marked "Port:" in each file. assets/ports/hfe/ holds its CSS,
 * JS and the icon font, byte for byte, with the plugin's layout, so the
 * handles, versions and URLs inside the CSS stay the same.
 *
 * Left out, admin only: the UAE dashboard (settings app, its REST routes,
 * onboarding, AJAX), analytics and usage tracking, the NPS survey, admin
 * notices, Pro upsells, the Learn API, the Abilities/AI integration,
 * rollback and the post duplicator (off on this site). The template list
 * moves from the plugin's "UAE" menu to Appearance > Header & Footer.
 *
 * While the plugin is still active this file does nothing.
 */
defined('ABSPATH') || exit;

if (defined('HFE_VER') || function_exists('hfe_init') || class_exists('Header_Footer_Elementor', false)) {
    return; // the plugin is active and does the work
}

/*
 * Switching the plugin back on (rollback). The plugin's main file declares
 * hfe_init() and three other functions unconditionally, so PHP stops with
 * "Cannot redeclare hfe_init()" if that file is included in a request in
 * which this port has already declared them. Activating it does exactly
 * that: WP-CLI `wp plugin activate` and Plugins > Activate both load the
 * theme first, then include the plugin. In that one request the port stays
 * out of the way; from the next request on the plugin is active and the
 * check above applies.
 */
$af_hfe_activating = false;
if (defined('WP_CLI') && WP_CLI && class_exists('WP_CLI', false) && is_callable(array('WP_CLI', 'get_runner'))) {
    try {
        $af_hfe_runner = WP_CLI::get_runner();
        $af_hfe_args   = is_object($af_hfe_runner) ? (array) $af_hfe_runner->arguments : array();
        $af_hfe_assoc  = is_object($af_hfe_runner) ? (array) $af_hfe_runner->assoc_args : array();
    } catch (\Throwable $af_hfe_e) {
        $af_hfe_runner = null;
        $af_hfe_args   = array();
        $af_hfe_assoc  = array();
    }
    if (isset($af_hfe_args[0], $af_hfe_args[1]) && 'plugin' === $af_hfe_args[0] && in_array($af_hfe_args[1], array('activate', 'toggle', 'install'), true)) {
        if (!empty($af_hfe_assoc['all'])) {
            $af_hfe_activating = true;
        }
        foreach (array_slice($af_hfe_args, 2) as $af_hfe_arg) {
            if (0 === strpos((string) $af_hfe_arg, 'header-footer-elementor')) {
                $af_hfe_activating = true;
            }
        }
    }
    unset($af_hfe_runner, $af_hfe_args, $af_hfe_assoc, $af_hfe_arg, $af_hfe_e);
} elseif (is_admin() && isset($GLOBALS['pagenow']) && in_array($GLOBALS['pagenow'], array('plugins.php', 'update.php', 'admin-ajax.php'), true)) {
    $af_hfe_req     = wp_unslash($_REQUEST);
    $af_hfe_act     = (isset($af_hfe_req['action']) && '-1' !== $af_hfe_req['action']) ? $af_hfe_req['action'] : (isset($af_hfe_req['action2']) ? $af_hfe_req['action2'] : '');
    $af_hfe_plugins = array_merge(
        isset($af_hfe_req['plugin']) ? (array) $af_hfe_req['plugin'] : array(),
        isset($af_hfe_req['checked']) ? (array) $af_hfe_req['checked'] : array()
    );
    if (in_array($af_hfe_act, array('activate', 'activate-selected', 'activate-plugin', 'error_scrape'), true)
        && in_array('header-footer-elementor/header-footer-elementor.php', $af_hfe_plugins, true)) {
        $af_hfe_activating = true;
    }
    unset($af_hfe_req, $af_hfe_act, $af_hfe_plugins);
}
if ($af_hfe_activating) {
    unset($af_hfe_activating);
    return; // this request switches the plugin on
}
unset($af_hfe_activating);

define('AF_HFE_PORT', true);

// The plugin's constants (header-footer-elementor.php), same names and values;
// the folder and URL point at the theme copy (PHP in inc/ports/hfe/, assets in
// assets/ports/hfe/, each with the plugin's own layout).
define('HFE_VER', '2.9.5');
define('HFE_FILE', __DIR__ . '/hfe/header-footer-elementor.php');
define('HFE_DIR', __DIR__ . '/hfe/');
define('HFE_URL', get_stylesheet_directory_uri() . '/assets/ports/hfe/');
define('HFE_PATH', 'header-footer-elementor/header-footer-elementor.php');
define('HFE_DOMAIN', trailingslashit('https://ultimateelementor.com'));
define('UAE_LITE', true);
define('AF_HFE_ASSETS_DIR', get_stylesheet_directory() . '/assets/ports/hfe/');

require_once HFE_DIR . 'header-footer-elementor.php';

// The plugin ran this at plugins_loaded, which has passed by the time the
// theme loads; everything it hooks (elementor/init, init, wp, get_header,
// wp_enqueue_scripts, body_class, the shortcodes, the Elementor widget and
// kit hooks) is still ahead.
hfe_init();

// The plugin also loaded the BSF admin-notices library on every request
// (inc/lib/astra-notices/class-bsf-admin-notices.php, not ported: it only
// shows wp-admin notices). Its one effect outside wp-admin is this
// wp_kses_allowed_html filter (BSF_Admin_Notices::add_data_attributes, at 10
// with 2 args, for every context), kept unchanged so whatever passes through
// kses keeps coming out the same.
add_filter('wp_kses_allowed_html', function ($allowedposttags, $context) {
    $allowedposttags['a']['data-repeat-notice-after'] = true;

    return $allowedposttags;
}, 10, 2);

// The templates screens' script (admin/assets/js/ehf-admin.js, enqueued by
// Header_Footer_Elementor::enqueue_admin_scripts) reads the hfe_admin_data
// object, which the plugin's settings page localized on every admin page.
// The settings page is not ported, so the same object (built the same way,
// class-hfe-settings-page.php enqueue_admin_scripts()) is attached here.
add_action('admin_enqueue_scripts', function () {
    if (!wp_script_is('hfe-admin-script', 'enqueued')) {
        return;
    }

    global $pagenow, $post_type;

    $show_view_all = ( $post_type === 'elementor-hf' && $pagenow === 'post.php' ) ? 'yes' : 'no';
    $hfe_edit_url  = admin_url( 'edit.php?post_type=elementor-hf' );

    $upgrade_notice_dismissed = get_user_meta( get_current_user_id(), 'hfe_upgrade_notice_dismissed', 'false' ) === 'true';

    $strings = [
        'addon_activate'        => esc_html__( 'Activate', 'header-footer-elementor' ),
        'addon_activated'       => esc_html__( 'Activated', 'header-footer-elementor' ),
        'addon_active'          => esc_html__( 'Active', 'header-footer-elementor' ),
        'addon_deactivate'      => esc_html__( 'Deactivate', 'header-footer-elementor' ),
        'addon_inactive'        => esc_html__( 'Inactive', 'header-footer-elementor' ),
        'addon_install'         => esc_html__( 'Install', 'header-footer-elementor' ),
        'theme_installed'       => esc_html__( 'Theme Installed', 'header-footer-elementor' ),
        'plugin_installed'      => esc_html__( 'Plugin Installed', 'header-footer-elementor' ),
        'addon_download'        => esc_html__( 'Download', 'header-footer-elementor' ),
        'addon_exists'          => esc_html__( 'Already Exists.', 'header-footer-elementor' ),
        'visit_site'            => esc_html__( 'Visit Website', 'header-footer-elementor' ),
        'plugin_error'          => esc_html__( 'Could not install. Please download from WordPress.org and install manually.', 'header-footer-elementor' ),
        'subscribe_success'     => esc_html__( 'Your details are submitted successfully.', 'header-footer-elementor' ),
        'subscribe_error'       => esc_html__( 'Encountered an error while performing your request.', 'header-footer-elementor' ),
        'ajax_url'              => admin_url( 'admin-ajax.php' ),
        'nonce'                 => wp_create_nonce( 'hfe-admin-nonce' ),
        'installer_nonce'       => wp_create_nonce( 'updates' ),
        'upgrade_notice_dismissed' => $upgrade_notice_dismissed,
        'popup_dismiss'         => false,
        'data_source'           => 'HFE',
        'show_all_hfe'          => $show_view_all,
        'hfe_edit_url'          => $hfe_edit_url,
        'view_all_text'         => esc_html__( 'View All', 'header-footer-elementor' ),
        'header_footer_builder' => $hfe_edit_url,
    ];

    $strings = apply_filters( 'hfe_admin_strings', $strings );

    wp_localize_script(
        'hfe-admin-script',
        'hfe_admin_data',
        $strings
    );
});
