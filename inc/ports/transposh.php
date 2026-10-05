<?php
/**
 * Translation: the plugin "Transposh Translation Filter" 1.0.11
 * (transposh-translation-filter-for-wordpress), moved into the theme - the
 * Hindi version of the shop. The English / Hindi switcher in the header is
 * the language-switcher port (inc/ports/language-switcher.php), which calls
 * into this code; it is loaded right after this file.
 *
 * What it does on this site, and still does from here, with the plugin's own
 * code: on a Hindi address (?lang=hi, or /hi/... - the first path segment is
 * read as a language too) it buffers the page from init on and translates
 * it from the wp_translations table: <html lang="hi">, every link to the shop
 * carries ?lang=hi, phrases with no translation yet are wrapped in
 * span.tr_ for transposh.js, which asks for them through the guest AJAX
 * action tp_tp (Google, through the server) and so keeps adding rows to the
 * table. Also: the WooCommerce AJAX calls made from a Hindi page (wc-ajax
 * update_order_review, cart fragments, add to cart) are translated by their
 * Referer, the cart and checkout links keep ?lang, redirects keep the
 * language, the locale is en_US / hi_IN, mail loses Transposh's markers, the
 * hreflang links in the head (the theme removes them, 24c), search in Hindi,
 * the [tp] shortcodes, the Transposh widget, edit mode (?tpedit=1, open to
 * guests as anonymous translation is on), and the admin side: the Transposh
 * menu (settings, languages, editor, utilities), the post metaboxes and the
 * admin AJAX actions. English pages pass through untouched.
 *
 * inc/ports/transposh/ is the plugin's PHP, copied unchanged with its folder
 * layout (transposh.php, core/, wp/, widgets/), less wp/transposh_ajax.php, a
 * stub that only prints "This file was removed in 0.8.0". Its css/, img/,
 * js/ and the widgets' styles, scripts and images are copied byte for byte
 * to assets/ports/transposh/ with the same layout, except css/flags.css and
 * css/square-flags.css (2.4 MB), which no Transposh PHP, script or style
 * refers to (the plugin only enqueues css/circle-flags.css). Three widget
 * styles and js/l/*.js are also next to the PHP, because the plugin tests
 * that they exist in its own folder before it links them from its URL.
 *
 * Differences, none of them in the translated text:
 *  - The plugin's URL (t_jp.plugin_url and transposh.js on Hindi pages, what
 *    edit mode loads after it, the admin scripts and styles, the menu icon)
 *    is the copy, //.../themes/postero-child/assets/ports/transposh, not
 *    //.../plugins/transposh-translation-filter-for-wordpress.
 *  - Its own update check against svc.transposh.org and its plugin-details
 *    override are not carried (a switched-off plugin does not update
 *    itself), nor is the "Settings" link on the Plugins screen.
 *  - On requests for the English site (front end, wc-ajax, REST, cron,
 *    admin-ajax) its four gettext filters are left out: there they return
 *    every string unchanged (see the proof below), about 13,000 calls on an
 *    uncached page.
 *  - Strings that other code translates before the theme loads were marked
 *    for Transposh on Hindi pages by the plugin; the copy starts with the
 *    theme, so they are not (in the tests only WordPress's and Square's
 *    admin warnings were, and none of them reaches a page).
 *  - [tp] and [tpe] are registered after WooCommerce's brand shortcodes
 *    instead of before them: only the order of WordPress's shortcode list
 *    changes, not what any shortcode matches or prints.
 *
 * Its translations (langs/) still load from the plugin folder, which stays on
 * the server. The data stays where the plugin keeps it: the wp_translations
 * and wp_translations_log tables, the options transposh_options,
 * transposh_db_version and transposh_options_* (engine proxies, language
 * overrides), and the tp_language / transposh_can_translate post and comment
 * meta. All of the admin side works from the copy and edits the same data
 * (settings, languages, translation editor, utilities, post metaboxes, the
 * widget); only the update check and the Plugins-screen link above are gone.
 * Its activation and deactivation code belongs to the plugin:
 * `wp plugin deactivate` runs a rewrite-rules flush, so it is switched off
 * silently. To switch back: activate Transposh first, then the Language
 * Switcher plugin if wanted (both in one command also works).
 *
 * While the plugin is still active this file does nothing.
 */
defined('ABSPATH') || exit;

if (class_exists('transposh_plugin', false) || function_exists('transposh_get_current_language')) {
    return; // the plugin is active and does the work
}

// Switching the Language Switcher plugin back on (rollback) includes, after
// the theme and in the same request, the plugin folder's core/utils.php and
// core/constants.php, which declare the same classes as the copy here. That
// one request runs without the copy. (plugin-ports.php already skips this
// file while Transposh itself is being switched on.)
if (in_array('language-switcher-for-transposh', (array) af_ports_activating(), true)) {
    return;
}

// Transposh's core already loaded from somewhere else (the plugin folder, by
// the Language Switcher plugin when it is active again), or one of the names
// the plugin defines without a check taken: loading the copy would stop PHP
// with "Cannot declare class" or define the names twice. Transposh stays off
// for this request; the error log says why.
$af_tp_clash = '';
foreach (array('tp_logger', 'ChromePhp_tp', 'transposh_consts', 'transposh_utils') as $af_tp_name) {
    if (class_exists($af_tp_name, false)) {
        $af_tp_file = wp_normalize_path((string) (new ReflectionClass($af_tp_name))->getFileName());
        if (strpos($af_tp_file, wp_normalize_path(__DIR__ . '/transposh/')) !== 0) $af_tp_clash = "class $af_tp_name ($af_tp_file)";
    }
}
foreach (array('DB_VERSION', 'HDOM_TYPE_ELEMENT', 'TR_NONCE', 'TP_FROM_POST') as $af_tp_name) {
    if (defined($af_tp_name)) $af_tp_clash = "constant $af_tp_name";
}
if ($af_tp_clash !== '') {
    error_log("Transposh port: $af_tp_clash is already defined elsewhere; the theme copy of Transposh is not loaded in this request.");
    unset($af_tp_clash, $af_tp_name, $af_tp_file);
    return;
}
unset($af_tp_clash, $af_tp_name, $af_tp_file);

// transposh.php: hooks from here on go where the plugin had them (see
// af_ports_restore_order()).
$af_tp_snap = af_ports_hook_snapshot();

// The files transposh.php requires, in its order and by full path; its own
// relative require_once lines then find them already loaded.
require_once __DIR__ . '/transposh/core/logging.php';
require_once __DIR__ . '/transposh/core/constants.php';
require_once __DIR__ . '/transposh/core/utils.php';
require_once __DIR__ . '/transposh/core/parser.php';
require_once __DIR__ . '/transposh/core/translate.php';
require_once __DIR__ . '/transposh/wp/transposh_db.php';
require_once __DIR__ . '/transposh/wp/transposh_widget.php';
require_once __DIR__ . '/transposh/wp/transposh_admin.php';
require_once __DIR__ . '/transposh/wp/transposh_options.php';
require_once __DIR__ . '/transposh/wp/transposh_postpublish.php';
require_once __DIR__ . '/transposh/wp/transposh_backup.php';
require_once __DIR__ . '/transposh/wp/transposh_3rdparty.php';
require_once __DIR__ . '/transposh/wp/transposh_mail.php';

// transposh.php sets $my_transposh_plugin at the top level of the file, and
// its widget, editor, translate and helper functions read it as a global.
// The theme is included at global scope in a web request but inside a
// function under WP-CLI.
global $my_transposh_plugin;
require_once __DIR__ . '/transposh/transposh.php';

$af_tp = $GLOBALS['my_transposh_plugin'];

// The plugin builds its URL from WP_PLUGIN_URL and its folder name
// (transposh.php:150-153), not with plugins_url(): the same form,
// protocol-relative, for the copy of its assets.
$af_tp->transposh_plugin_url = preg_replace('#^https?://#', '//', get_stylesheet_directory_uri() . '/assets/ports/transposh');

// Its plugins_loaded callback (transposh.php:182, 785-810: set up or upgrade
// the tables when transposh_db_version differs from DB_VERSION) has already
// run by the time the theme loads.
$af_tp->plugin_loaded();

// Its own update channel (transposh.php:235-237, 1619-1696): it would put an
// entry for the copy's path into the plugin updates, and it answered "no
// details" for every other plugin on the Plugins screen.
remove_filter('http_request_args', array($af_tp, 'filter_wordpress_org_update'), 10);
remove_filter('pre_set_site_transient_update_plugins', array($af_tp, 'check_for_plugin_update'), 10);
remove_filter('plugins_api', array($af_tp, 'plugin_api_call'), 10);

// Its translations: on_init (transposh.php:506) registers langs/ relative to
// the file's plugin folder, which for the copy is no plugin folder. Right
// after it, the same call with the plugin's folder.
add_action('init', function () {
    load_plugin_textdomain(TRANSPOSH_TEXT_DOMAIN, false, 'transposh-translation-filter-for-wordpress/langs');
}, 0);

// The plugin's four gettext filters stay registered on every request, as
// with the plugin. (Leaving them out on English requests, though they return
// the string unchanged there, coincided with the checkout's order summary not
// refreshing in the 5 Oct preview; the copy now keeps them.)

// The plugin loaded before WOOCS, Square and WooCommerce and before the
// theme; its callbacks go back ahead of theirs at equal priority (its
// 'locale' filter, for one, ahead of WP_Locale_Switcher's, so a
// switch_to_locale() still wins). Not restored: callbacks that a plugin
// sorting before Transposh adds only at plugins_loaded came after the
// plugin's and stay ahead of the copy's - Hostinger's at wp_head and
// admin_head @10 (its llms.txt link, off here, and its menu CSS).
af_ports_restore_order($af_tp_snap, 'transposh-translation-filter-for-wordpress');
unset($af_tp, $af_tp_snap);
