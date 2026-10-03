<?php
/**
 * Plugins moved into the theme (owner, 3 Oct 2026: "change wordpress plugin
 * to custom code", the plugins "taking too much time to load", with nothing a
 * visitor sees or does changing).
 *
 * Each file in inc/ports/ does, in the theme, what one plugin did on this
 * site, and stays dormant while that plugin is still active: the plugin and
 * its replacement never both run. Switching a plugin off (switch-plugins.yml,
 * behind a whole-site before/after comparison) hands its work to the file
 * here; switching it back on hands it back. Nothing to reconfigure either way.
 */
if (!defined('ABSPATH')) exit;

/*
 * Switching a plugin back on (rollback). WP-CLI `wp plugin activate` and
 * Plugins > Activate both load the theme first and include the plugin's main
 * file afterwards, in the same request. Several plugins declare functions or
 * classes there without checking whether they exist (e.g. hfe_init(), a
 * Composer autoloader class), and their theme copy declares the same names,
 * so PHP would stop with "Cannot redeclare". In that one request the copy
 * stays out of the way; from the next request on the plugin is active and
 * each copy's own check keeps it dormant.
 *
 * Returns the plugin folders being switched on in this request, or true for
 * "all of them" (--all).
 */
if (!function_exists('af_ports_activating')) {
    function af_ports_activating() {
        static $found = null;
        if ($found !== null) return $found;
        $found = array();
        if (defined('WP_CLI') && WP_CLI && class_exists('WP_CLI', false) && is_callable(array('WP_CLI', 'get_runner'))) {
            try {
                $runner = WP_CLI::get_runner();
                $args   = is_object($runner) ? (array) $runner->arguments : array();
                $assoc  = is_object($runner) ? (array) $runner->assoc_args : array();
            } catch (\Throwable $e) {
                $args = $assoc = array();
            }
            if (isset($args[0], $args[1]) && 'plugin' === $args[0] && in_array($args[1], array('activate', 'toggle', 'install'), true)) {
                if (!empty($assoc['all'])) return $found = true;
                foreach (array_slice($args, 2) as $a) $found[] = strtok((string) $a, '/');
            }
        } elseif (is_admin() && isset($GLOBALS['pagenow']) && in_array($GLOBALS['pagenow'], array('plugins.php', 'update.php', 'admin-ajax.php'), true)) {
            $req = wp_unslash($_REQUEST); // phpcs:ignore WordPress.Security.NonceVerification
            $act = (isset($req['action']) && '-1' !== $req['action']) ? $req['action'] : (isset($req['action2']) ? $req['action2'] : '');
            if (in_array($act, array('activate', 'activate-selected', 'activate-plugin', 'error_scrape'), true)) {
                $list = array_merge(isset($req['plugin']) ? (array) $req['plugin'] : array(), isset($req['checked']) ? (array) $req['checked'] : array());
                foreach ($list as $p) $found[] = strtok((string) $p, '/');
            }
        }
        return $found;
    }
}

// port file => the plugin folder it stands in for
$af_ports = array(
    'code-snippets'     => 'code-snippets',
    'hfcm'              => 'header-footer-code-manager',
    'classic-editor'    => 'classic-editor',
    'delivery-date'     => 'wpc-estimated-delivery-date',
    'click-to-chat'     => 'click-to-chat-for-whatsapp',
    'mas-brands'        => 'mas-woocommerce-brands',
    'smart-messages'    => 'wpc-smart-messages',
    'featured-video'    => 'yith-woocommerce-featured-video',
    'quick-view'        => 'woo-smart-quick-view',
    'wishlist'          => 'woo-smart-wishlist',
    'google-reviews'    => 'embedder-for-google-reviews',
    'language-switcher' => 'language-switcher-for-transposh',
    'essential-addons'  => 'essential-addons-for-elementor-lite',
    'premium-addons'    => 'premium-addons-for-elementor',
    'instagram-feed'    => 'insta-gallery',
    'header-footer-elementor' => 'header-footer-elementor',
);
$af_activating = af_ports_activating();
foreach ($af_ports as $af_port => $af_port_plugin) {
    if ($af_activating === true || in_array($af_port_plugin, (array) $af_activating, true)) continue;
    $af_port_file = __DIR__ . '/ports/' . $af_port . '.php';
    if (is_readable($af_port_file)) require_once $af_port_file;
}
unset($af_ports, $af_port, $af_port_plugin, $af_port_file, $af_activating);
