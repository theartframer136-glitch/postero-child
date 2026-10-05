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
        } elseif (isset($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']) && 'GET' !== $_SERVER['REQUEST_METHOD']
            && preg_match_all('#wp/v2/plugins/([a-z0-9_-]+)#i', rawurldecode((string) $_SERVER['REQUEST_URI']), $af_m)) {
            // REST: POST/PUT /wp-json/wp/v2/plugins/<folder>/<file> (block editor, apps)
            foreach ($af_m[1] as $p) $found[] = strtolower($p);
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

/*
 * Hook order. WordPress runs callbacks of equal priority in the order they
 * were added. A plugin's main file runs before the plugins that sort after it
 * in the active list and before plugins_loaded; its theme copy runs after all
 * of them. For a copy whose plugin shares hooks with those later plugins
 * (WooCommerce, Square), af_ports_restore_order() puts the copy's callbacks
 * back where the plugin's were: after callbacks added by core, mu-plugins,
 * drop-ins and the plugins that load before it, ahead of everything else at
 * the same priority. Usage, in a port file:
 *     $snap = af_ports_hook_snapshot();
 *     require ...the plugin's code...;
 *     af_ports_restore_order($snap, 'plugin-folder');
 */
if (!function_exists('af_ports_hook_snapshot')) {
    function af_ports_hook_snapshot() {
        $s = array();
        foreach ($GLOBALS['wp_filter'] as $tag => $h) {
            if (!($h instanceof WP_Hook)) continue;
            foreach ($h->callbacks as $p => $cbs) $s[$tag][$p] = array_keys($cbs);
        }
        return $s;
    }
}
if (!function_exists('af_ports_cb_file')) {
    // the file a callback is defined in, '' for PHP built-ins and unknowns
    function af_ports_cb_file($f) {
        try {
            if ($f instanceof Closure) $r = new ReflectionFunction($f);
            elseif (is_string($f) && strpos($f, '::') !== false) $r = new ReflectionMethod($f);
            elseif (is_string($f)) $r = function_exists($f) ? new ReflectionFunction($f) : null;
            elseif (is_array($f) && isset($f[0], $f[1])) $r = new ReflectionMethod($f[0], $f[1]);
            elseif (is_object($f) && method_exists($f, '__invoke')) $r = new ReflectionMethod($f, '__invoke');
            else $r = null;
            return ($r && $r->getFileName()) ? wp_normalize_path($r->getFileName()) : '';
        } catch (\Throwable $e) {
            return '';
        }
    }
}
if (!function_exists('af_ports_restore_order')) {
    function af_ports_restore_order(array $snap, $folder) {
        $early = array(wp_normalize_path(ABSPATH), wp_normalize_path(WPMU_PLUGIN_DIR . '/'));
        $plugins = wp_normalize_path(WP_PLUGIN_DIR . '/');
        $content = wp_normalize_path(WP_CONTENT_DIR . '/');
        foreach ((array) get_option('active_plugins', array()) as $p) {
            if (is_string($p) && strcmp($p, $folder . '/') < 0) $early[] = $plugins . dirname($p) . '/';
        }
        // core objects set up after plugins_loaded: their callbacks were added
        // after the plugin's, though their code lives in core
        $late_objs = array();
        foreach (array('wp_locale_switcher', 'wp_widget_factory') as $g) if (isset($GLOBALS[$g]) && is_object($GLOBALS[$g])) $late_objs[] = $GLOBALS[$g];
        $is_early = function ($f) use ($early, $plugins, $content, $late_objs) {
            if (is_array($f) && isset($f[0]) && is_object($f[0])) foreach ($late_objs as $o) if ($f[0] === $o) return false;
            $file = af_ports_cb_file($f);
            if ($file === '') return true; // PHP built-ins (core default filters such as trim)
            foreach ($early as $d) {
                // ABSPATH may hold wp-content (and so plugins and themes): those are decided below
                if (strpos($file, $d) === 0 && ($d !== $early[0] || (strpos($file, $content) !== 0))) return true;
            }
            // drop-ins (object-cache.php, advanced-cache.php) sit directly in wp-content
            return dirname($file) . '/' === $content;
        };
        foreach ($GLOBALS['wp_filter'] as $tag => $h) {
            if (!($h instanceof WP_Hook) || !isset($snap[$tag])) continue;
            foreach ($h->callbacks as $p => $cbs) {
                if (!isset($snap[$tag][$p])) continue;
                $oldk = array_flip($snap[$tag][$p]);
                $new = array_diff_key($cbs, $oldk);
                if (!$new) continue;
                $old = array_intersect_key($cbs, $oldk);
                $head = array();
                foreach ($old as $k => $cb) {
                    if (!$is_early($cb['function'])) break;
                    $head[$k] = $cb;
                }
                if (count($head) === count($old)) continue; // already where the plugin had them
                $h->callbacks[$p] = $head + $new + array_diff_key($old, $head);
            }
        }
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
    'transposh'         => 'transposh-translation-filter-for-wordpress',
    'language-switcher' => 'language-switcher-for-transposh',
    'essential-addons'  => 'essential-addons-for-elementor-lite',
    'premium-addons'    => 'premium-addons-for-elementor',
    'instagram-feed'    => 'insta-gallery',
    'header-footer-elementor' => 'header-footer-elementor',
    'customer-reviews'  => 'customer-reviews-woocommerce',
    'revslider'         => 'revslider',
    'elementor-pro'     => 'elementor-pro',
    'hostinger'         => 'hostinger',
    'woocs'             => 'woocommerce-currency-switcher',
    'rank-math'         => 'seo-by-rank-math',
);
$af_activating = af_ports_activating();
foreach ($af_ports as $af_port => $af_port_plugin) {
    if ($af_activating === true || in_array($af_port_plugin, (array) $af_activating, true)) continue;
    $af_port_file = __DIR__ . '/ports/' . $af_port . '.php';
    if (is_readable($af_port_file)) require_once $af_port_file;
}
unset($af_ports, $af_port, $af_port_plugin, $af_port_file, $af_activating);
