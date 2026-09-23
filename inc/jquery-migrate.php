<?php
/**
 * jQuery Migrate off on the storefront.
 *
 * DEF-14. WordPress loads jquery-migrate as a dependency of 'jquery', so it
 * came with every page: a development shim that restores APIs jQuery 3
 * removed, and logs "JQMIGRATE: Migrate is installed, version 3.4.1" to
 * every visitor's console.
 *
 * Measured before removing it (tools/probe-jquery-migrate.mjs). Migrate
 * logs every call it patches, so the probe walked home, shop, a category, a
 * product (a size picked and added to the cart), cart, checkout, wishlist and
 * login, and kept each warning with the script that made the call. What
 * matters is whether any of them is an API jQuery 3 REMOVED. Those break
 * without Migrate. Deprecated-but-present APIs keep working. The theme's own
 * code uses neither.
 *
 * FRONT END ONLY. wp-admin, the login screen and the Customizer keep it.
 * Plugin admin screens were not measured, and Migrate costs nothing there
 * that a visitor pays for.
 *
 * A SWITCH BACK, with no deploy. If a path the probe did not reach turns out
 * to need it:
 *     wp option update af_jquery_migrate on      (Migrate back)
 *     wp option delete af_jquery_migrate         (off again)
 *
 * A plugin that declares jquery-migrate as its own dependency still gets it.
 * Only the blanket load through 'jquery' is removed.
 */
if (!defined('ABSPATH')) exit;

/**
 * Takes jquery-migrate out of 'jquery''s dependencies.
 *
 * Run twice on purpose. wp_default_scripts fires once, when WordPress first
 * builds its script registry, and a plugin that touches scripts before the
 * theme loads builds it before this hook exists. So the removal also runs
 * at the very end of wp_enqueue_scripts. By then the registry certainly
 * exists, and nothing has been printed. Whichever runs second finds nothing
 * left to do.
 */
function af_drop_jquery_migrate($scripts = null) {
    try {
        if (is_admin()) return;
        if (isset($GLOBALS['pagenow']) && $GLOBALS['pagenow'] === 'wp-login.php') return;
        if (function_exists('is_customize_preview') && is_customize_preview()) return;
        if (get_option('af_jquery_migrate', 'off') === 'on') return;
        if (!is_object($scripts)) $scripts = function_exists('wp_scripts') ? wp_scripts() : null;
        if (!is_object($scripts) || empty($scripts->registered['jquery'])) return;
        $jq = $scripts->registered['jquery'];
        if (is_array($jq->deps)) {
            $jq->deps = array_values(array_diff($jq->deps, array('jquery-migrate')));
        }
    } catch (\Throwable $e) {
        // Leave jQuery exactly as WordPress registered it.
    }
}
add_action('wp_default_scripts', 'af_drop_jquery_migrate', 20);
add_action('wp_enqueue_scripts', function () { af_drop_jquery_migrate(); }, PHP_INT_MAX);
