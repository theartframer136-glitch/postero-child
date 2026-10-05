<?php
/**
 * Hostinger Tools: what the plugin "Hostinger Tools" (hostinger) 3.0.78 still
 * does on this site, moved into the theme.
 *
 * Every switch on its Tools screen is off here (option hostinger_tools, read
 * 5 Oct 2026: maintenance mode, disable XML-RPC, force HTTPS, force WWW,
 * disable application passwords, LLMs.txt and Web2Agent all false), so the
 * plugin prints nothing on the storefront: no maintenance page, no llms.txt
 * link in the head, no redirects, no scripts or styles. What it does do, and
 * what this file does the same way:
 *
 * - Application passwords stay available even where WordPress would turn
 *   them off (core allows them only over HTTPS or on a local site):
 *   Hooks::check_authentication_password_enabled, includes/Hooks.php:16 and
 *   139-148. The owner's REST upload tools (tools/upload-folder-to-site.ps1,
 *   tools/download-reels-to-site.ps1) sign in with one. The plugin's own
 *   "Disable application passwords" setting is still read.
 * - In wp-admin and in every admin-ajax.php request (the storefront's guest
 *   AJAX included) every kses allowed-HTML list, whatever the context, gets
 *   the plugin's <svg> and <path> entries: Admin\Hooks::custom_kses_allowed_html,
 *   includes/Admin/Hooks.php:25 and 43-59, camel-case 'viewBox' included
 *   (kses lowercases attribute names, so viewbox itself stays disallowed).
 *   Kept so whatever passes through kses there comes out the same.
 * - Saving either of WooCommerce's "Coming soon" settings
 *   (woocommerce_coming_soon, woocommerce_store_pages_only) empties the
 *   LiteSpeed cache: Hooks::litespeed_flush_cache, includes/Hooks.php:20-21
 *   and 177-181.
 *
 * No plugin file is copied: nothing else in the plugin has an effect with
 * these settings, and these three are a few lines each. The kses and purge
 * callbacks below are the plugin's own lines; the application-password one
 * reads the setting the way the plugin's PluginOptions class does.
 *
 * Left out:
 * - Action Scheduler. The plugin bundles 3.9.3, but the site already runs
 *   WooCommerce's own copy (4.0.0, woocommerce/packages/action-scheduler),
 *   which stays. No pending action belonged to the plugin (5 Oct 2026).
 * - The "Hostinger" admin menu and admin-bar node, the Tools screen (a Vue
 *   app) and its REST routes (hostinger-tools-plugin/v1, administrators only;
 *   the namespace leaves the /wp-json/ index), the rate-us footer, the empty
 *   admin stylesheet, the ?platform=hpanel redirect to that screen, and the
 *   "wp hostinger ..." WP-CLI commands.
 * - Maintenance mode, the XML-RPC switch, force HTTPS/WWW, LLMs.txt and
 *   Web2Agent: all off (the theme already turns XML-RPC off and sends http to
 *   https itself). The Web2Agent and LLMs.txt background jobs only looked up
 *   Action Scheduler on each saved option or published post and stopped.
 * - The Hostinger wording for "Application passwords are not available."
 *   (used only when the plugin's switch turns them off).
 *
 * Settings stay where the plugin keeps them (hostinger_tools,
 * hostinger_first_login_at, hts_new_installation), untouched. Only
 * disable_authentication_password is read here; to change any setting, switch
 * the plugin back on (this file then steps aside again).
 *
 * Admin: the Tools screen is gone. A bookmark or hPanel link to its pages
 * (admin.php?page=hostinger, ?page=hostinger-tools) now opens the dashboard
 * instead of WordPress's "Sorry, you are not allowed to access this page."
 *
 * While the plugin is still active this file does nothing.
 */
defined('ABSPATH') || exit;

// hostinger.php defines the version constant and declares hostinger_activate()
// unconditionally the moment it is loaded (lines 27 and 114).
if (defined('HOSTINGER_WORDPRESS_PLUGIN_VERSION') || function_exists('hostinger_activate')) {
    return; // the plugin is active and does the work
}

/* Hooks::check_authentication_password_enabled (includes/Hooks.php:139-148),
   with the setting read as PluginOptions reads it (! empty()). */
add_filter('wp_is_application_passwords_available', function () {
    $settings = get_option('hostinger_tools', array());

    return !(is_array($settings) && !empty($settings['disable_authentication_password']));
});

/* Hooks::litespeed_flush_cache (includes/Hooks.php:177-181). WooCommerce's own
   cache invalidation on the same two hooks was added before the plugin's and
   is still added before this, so it still runs first. */
$af_hst_purge = function () {
    if (has_action('litespeed_purge_all')) {
        do_action('litespeed_purge_all');
    }
};
add_action('update_option_woocommerce_coming_soon', $af_hst_purge);
add_action('update_option_woocommerce_store_pages_only', $af_hst_purge);
unset($af_hst_purge);

if (is_admin()) {
    /* Admin\Hooks::custom_kses_allowed_html (includes/Admin/Hooks.php:43-59),
       added only in is_admin() requests (Bootstrap.php:46-47), at 10 with one
       argument. The plugin added it at plugins_loaded, so ahead of the theme's
       own filter at the same priority (the header-footer-elementor port's);
       af_ports_restore_order() puts it back there, and the lists keep their
       keys in the same order as well. */
    $af_hst_snap = af_ports_hook_snapshot();
    add_filter('wp_kses_allowed_html', function ($allowed) {
        $allowed['svg']  = array(
            'xmlns'   => true,
            'width'   => true,
            'height'  => true,
            'viewBox' => true,
            'fill'    => true,
            'style'   => true,
            'class'   => true,
        );
        $allowed['path'] = array(
            'd'    => true,
            'fill' => true,
        );

        return $allowed;
    }, 10, 1);
    af_ports_restore_order($af_hst_snap, 'hostinger');
    unset($af_hst_snap);

    /* Not the plugin's: its admin pages no longer exist, and WordPress refuses
       an unknown admin page before admin_init, so the redirect hooks in where
       it does that. */
    add_action('admin_page_access_denied', function () {
        $page = isset($_GET['page']) ? wp_unslash($_GET['page']) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (is_string($page) && in_array($page, array('hostinger', 'hostinger-tools'), true)) {
            wp_safe_redirect(admin_url());
            exit;
        }
    });
}
