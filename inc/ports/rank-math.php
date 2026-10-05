<?php
/**
 * SEO: the plugin "Rank Math SEO" (seo-by-rank-math) 1.0.279, moved into the
 * theme - everything it prints for visitors and search engines, and its
 * editing screens.
 *
 * inc/ports/rank-math/ is the plugin's own code, copied unchanged with its
 * folder layout (rank-math.php, includes/, assets/, and the libraries it
 * bundles in vendor/: CMB2, wp-background-processing, the user-agent parser,
 * the Mixpanel client and Action Scheduler 3.9.3). Its Composer autoloader is replaced by
 * vendor/autoload.php, generated from the plugin's own class map (every class
 * whose file was copied, and the files Composer loaded at once). Every file
 * the plugin links by URL (assets/ and the non-PHP files of includes/, CMB2's
 * css/ and js/) is copied byte for byte to assets/ports/rank-math/ with the
 * same layout; the plugin builds those URLs with plugins_url() and
 * plugin_dir_url(), which the filter below answers with the copy's URL, so
 * not one line of the plugin had to change.
 *
 * What it does here, from the copy, as before (the same modules, the same
 * settings, nothing reconfigured):
 *  - the <head> block: title, meta description, robots, canonical, prev/next,
 *    the OpenGraph, Twitter and Slack tags, the rank-math-schema JSON-LD graph
 *    (Organization from Local SEO, WebSite, WebPage, Article, Product), and
 *    the og: prefix on <html>; the theme's own filters on rank_math/json_ld,
 *    rank_math/frontend/robots, rank_math/frontend/description and
 *    rank_math/sitemap/entry still apply;
 *  - the XML sitemaps (sitemap_index.xml and its sitemaps, main-sitemap.xsl,
 *    the /sitemap.xml and wp-sitemap.xml redirects), the robots.txt text, the
 *    attachment and date-archive redirects, ?replytocom, target/rel on
 *    external links, rel="ugc" on comment links, the primary category in
 *    get_the_terms() and permalinks, no WooCommerce generator tag, the
 *    IndexNow key file and submissions, the shortcodes, the cron jobs, the
 *    rankmath/v1 REST routes;
 *  - wp-admin: the post, term and user metaboxes, the settings screens, the
 *    setup wizard, bulk editing, the SEO column, Status & Tools, Content AI,
 *    AI Visibility, SEO Analyzer, Role Manager, Instant Indexing, the admin
 *    bar menu, the SEO tab in Elementor's editor, `wp rankmath sitemap
 *    generate`;
 *  - the WordPress Abilities it registers (rankmath/* in the Abilities API
 *    and the abilities REST routes).
 *
 * Left out:
 *  - The MCP OAuth server the plugin bundles (vendor/wp-media/mcp-oauth, with
 *    the php-mcp-schema and apply-filters-typed libraries): its /oauth/* and
 *    /.well-known/oauth-* endpoints (404 from here on) and its REST route
 *    mcp/mcp-oauth-server. No AI client was connected through it (usermeta
 *    mcp_refresh_jti_*: none, 5 Oct 2026). The MCP adapter library (WP\MCP)
 *    is not copied either: rank-math.php starts it only if the class can be
 *    loaded, and WooCommerce bundles the same library, so its copy is the one
 *    started, as the plugin started its own (the mcp/mcp-adapter-default-server
 *    route stays).
 *  - uninstall.php, wpml-config.xml.
 *  - Its row and links on the Plugins screen (the plugin shows as inactive).
 *    Version Control (rollback, beta, auto-update) acts on the inactive
 *    plugin folder, not on this copy.
 *
 * Differences, none of them on a page:
 *  - The admin scripts and styles load from …/themes/postero-child/assets/
 *    ports/rank-math/ instead of …/plugins/seo-by-rank-math/; the block
 *    editor scripts that register_block_type() finds next to the blocks'
 *    block.json, from inc/ports/rank-math/includes/ (WordPress builds those
 *    URLs from where the file is).
 *  - [rank_math_seo_score] is registered after the plugins' shortcodes
 *    instead of between Social Feed Gallery's and Transposh's: only the order
 *    of WordPress's shortcode list changes.
 *  - At init@10, Settings::init, Tracking::hooks and pass_admin_content run
 *    ahead of Social Feed Gallery's callbacks instead of after them:
 *    af_ports_restore_order() stops at the first callback from a later
 *    plugin's folder, and Hostinger's Jetpack autoloader adds one first (the
 *    Abilities API library WooCommerce bundles). They share no data.
 *
 * The data stays where the plugin keeps it: the rank-math-options-* options
 * and the other rank_math_* options, the rank_math_* post, term and user meta,
 * the rank_math_internal_links table, the sitemap cache in uploads/rank-math/.
 * The plugin must be switched off silently (deactivate_plugins(…, true)): its
 * deactivation code disconnects the site from the Rank Math account and
 * clears its cron jobs. Keep it installed: deleting it runs its uninstall.php.
 *
 * While the plugin is still active this file does nothing.
 */
defined('ABSPATH') || exit;

// rank-math.php declares class RankMath as it is loaded, and defines
// RANK_MATH_VERSION first thing when it starts.
if (defined('RANK_MATH_VERSION') || class_exists('RankMath', false)) {
    return; // the plugin is active and does the work
}

define('AF_RANK_MATH_PORT', true);

// plugins_url($path, <a file of the copy>) -> the same path under
// assets/ports/rank-math/. This is how the plugin builds RANK_MATH_URL
// (rank-math.php:251, once, as it starts: so before the require) and every
// plugin_dir_url(__FILE__) in its modules.
add_filter('plugins_url', function ($url, $path, $plugin) {
    static $dir = null;
    if ($dir === null) $dir = wp_normalize_path(__DIR__ . '/rank-math/');
    if (!is_string($plugin) || strpos($plugin, $dir) !== 0) return $url;
    $folder = dirname(substr($plugin, strlen($dir)));
    $url = get_stylesheet_directory_uri() . '/assets/ports/rank-math';
    if ($folder !== '.') $url .= '/' . $folder;
    if ($path && is_string($path)) $url .= '/' . ltrim($path, '/');
    return $url;
}, 10, 3);

// CMB2 (vendor/cmb2/cmb2) makes its URL from its folder: for a plugin with
// plugins_url(), for a theme from the theme root, which would be the copy's
// PHP folder. Its css/ and js/ are with the other assets.
add_filter('cmb2_meta_box_url', function ($url) {
    $dir = wp_normalize_path(__DIR__ . '/rank-math/');
    if (defined('CMB2_DIR') && strpos(wp_normalize_path(CMB2_DIR), $dir) === 0) {
        $url = get_stylesheet_directory_uri() . '/assets/ports/rank-math/' . substr(wp_normalize_path(CMB2_DIR), strlen($dir));
    }
    return $url;
});

// rank-math.php, unchanged. Its hooks go where the plugin had them (see
// af_ports_restore_order()): it loaded after Elementor, Hostinger, Social
// Feed Gallery and LiteSpeed Cache and before Transposh, WOOCS, Square and
// WooCommerce.
$af_rm_snap = af_ports_hook_snapshot();
require_once __DIR__ . '/rank-math/rank-math.php';
af_ports_restore_order($af_rm_snap, 'seo-by-rank-math');
unset($af_rm_snap);

// Its Action Scheduler (3.9.3) registers itself on plugins_loaded@0 and the
// newest registered copy starts at plugins_loaded@1. Normally WooCommerce's
// newer one has started by now and nothing is needed. In a request without
// WooCommerce (WP-CLI --skip-plugins=woocommerce, WooCommerce switched off or
// paused) this copy's is the only one, as the plugin's was: start it now, so
// the as_*() calls of the analytics module and the schedulers still work.
if (!class_exists('ActionScheduler', false) && function_exists('action_scheduler_register_3_dot_9_dot_3')) {
    action_scheduler_register_3_dot_9_dot_3();
    ActionScheduler_Versions::initialize_latest_version();
}

// What the plugin had hooked to plugins_loaded, which has fired by the time
// the theme loads: run now, in the plugin's order, each only where the plugin
// hooked it in this request (init_actions(), rank-math.php:324-353, decides:
// a valid registration; init_admin in wp-admin; init_frontend on the front end
// and in Elementor's editor; init_wp_cli under WP-CLI).
//  - Notification_Center::get_from_storage (@5) reads rank_math_notifications;
//    without it update_storage() at shutdown deletes the stored notices.
//  - RankMath::init (@14), empty.
//  - init_admin / init_frontend (@15): Admin_Init, or Frontend (the head
//    block, OpenGraph, redirects, link attributes, ...).
//  - init_wp_cli (@20): `wp rankmath sitemap generate`.
// What these add at equal priority goes where it was: after everything the
// plugins had added by then (this copy's own hooks above among them), ahead
// of the theme's own callbacks (the other ports' among them).
$af_rm = rank_math();
if (isset($af_rm->notification)) {
    $af_rm_snap = af_ports_hook_snapshot();
    foreach (array(
        array($af_rm->notification, 'get_from_storage'),
        array($af_rm, 'init'),
        array($af_rm, 'init_admin'),
        array($af_rm, 'init_frontend'),
        array($af_rm, 'init_wp_cli'),
    ) as $af_rm_cb) {
        if (has_action('plugins_loaded', $af_rm_cb) !== false) call_user_func($af_rm_cb);
    }
    $af_rm_theme = wp_normalize_path(dirname(__DIR__, 2) . '/');
    $af_rm_copy = wp_normalize_path(__DIR__ . '/rank-math/');
    foreach ($GLOBALS['wp_filter'] as $af_rm_tag => $af_rm_hook) {
        if (!isset($af_rm_snap[$af_rm_tag])) continue;
        foreach ($af_rm_hook->callbacks as $af_rm_p => $af_rm_cbs) {
            if (!isset($af_rm_snap[$af_rm_tag][$af_rm_p])) continue;
            $af_rm_old = array_intersect_key($af_rm_cbs, array_flip($af_rm_snap[$af_rm_tag][$af_rm_p]));
            $af_rm_new = array_diff_key($af_rm_cbs, $af_rm_old);
            if (!$af_rm_new) continue;
            $af_rm_head = array();
            foreach ($af_rm_old as $af_rm_k => $af_rm_cb) {
                $af_rm_file = af_ports_cb_file($af_rm_cb['function']);
                if (strpos($af_rm_file, $af_rm_theme) === 0 && strpos($af_rm_file, $af_rm_copy) !== 0) break;
                $af_rm_head[$af_rm_k] = $af_rm_cb;
            }
            $af_rm_hook->callbacks[$af_rm_p] = $af_rm_head + $af_rm_new + array_diff_key($af_rm_old, $af_rm_head);
        }
    }
    unset($af_rm_snap, $af_rm_theme, $af_rm_copy, $af_rm_tag, $af_rm_hook, $af_rm_p, $af_rm_cbs, $af_rm_old, $af_rm_new, $af_rm_head, $af_rm_k, $af_rm_file);
}

unset($af_rm, $af_rm_cb);

/*
 * Rewrite rules, once. The site's stored rules were last built while the
 * plugin was active, so they still hold the OAuth server's /oauth/* and
 * /.well-known/oauth-* rules (which would now serve the home page), and a
 * build made while neither the plugin nor this copy ran would lack the
 * sitemap rules. When the stored rules hold the OAuth rule that this request
 * no longer registers, or lack the sitemap_index rule that it does register,
 * they are rebuilt once. Rebuilt at wp_loaded, after every plugin and the
 * theme have added their rules on init (where WordPress itself defers a
 * rebuild to). Never under WP-CLI (the deploy's `wp rewrite flush` is there,
 * and --skip-plugins would leave a plugin's rules out), nor in a request
 * whose active plugins are filtered (a Preview Ports request). The stored
 * rules it leaves behind are remembered (af_rank_math_rewrite_heal): if they
 * still do not pass, nothing is rebuilt again until they change.
 */
add_action('wp_loaded', function () {
    if ((defined('WP_CLI') && WP_CLI) || wp_installing() || has_filter('option_active_plugins')) return;
    $saved = get_option('rewrite_rules');
    if (!is_array($saved) || !$saved) return; // empty: WordPress builds them on its own
    $now = $GLOBALS['wp_rewrite']->extra_rules_top;
    $index = array_search('index.php?sitemap=1', $now, true);
    $stale = (isset($saved['^oauth/authorize$']) && !isset($now['^oauth/authorize$']))
        || ($index !== false && !isset($saved[$index]));
    if (!$stale || get_option('af_rank_math_rewrite_heal') === md5(serialize($saved))) return;
    flush_rewrite_rules(false);
    update_option('af_rank_math_rewrite_heal', md5(serialize(get_option('rewrite_rules'))), false);
}, 20);
