<?php
/**
 * Slider Revolution: the plugin "Slider Revolution" (revslider) 6.7.40, SR7
 * engine, moved into the theme — the home page slider (Elementor widget
 * slider_revolution on page #75, [rev_slider alias="slider-1"]) and what the
 * plugin prints on every page.
 *
 * inc/ports/revslider/ is the plugin's own code, copied unchanged with its
 * folder layout: the slider, slide and output classes, the front-end classes
 * (RevSliderFront: tp-tools, sr7 and sr7css on every page, the head script
 * with SR7.E.* and the page processor, the Google-font links and the slider
 * JSON in the footer; the SR6 engine's for ?srengine=6), the shortcodes
 * [rev_slider] and [sr7], the REST routes /wp-json/sliderrevolution/… and the
 * guest AJAX action revslider_ajax_call_front that sr7.js loads further slides
 * through, the Elementor widget, the WordPress widget, the "Slider Revolution
 * Blank Template" page template, and the classes those use. boot.php does what
 * revslider.php did (its lines copied unchanged, the theme's marked "port:");
 * the classes a page does not use are loaded only when first needed, so a
 * page reads about a quarter of the plugin's PHP. The plugin's public and
 * sr6/assets folders are copied byte for byte to assets/ports/revslider/,
 * with the four data files it reads from includes/. Sliders, slides, global
 * settings and images stay where they are (wp_revslider_* tables,
 * revslider-global-settings, uploads).
 *
 * Differences, none of them on the page:
 *  - SR7.E.plugin_url (and every file sr7.js loads after it) is the theme
 *    copy, …/themes/postero-child/assets/ports/revslider/, not
 *    …/plugins/revslider/.
 *  - Not carried (admin/editor only): the slider editor and its AJAX actions
 *    (rs_ajax_action, revslider_ajax_action), the shortcode wizard and the
 *    Elementor/Gutenberg/WPBakery/Divi editor panels, the update and licence
 *    checks, the monthly update-server refresh. Editing a slider, or the
 *    widget's Select/Edit buttons in Elementor, needs the plugin switched on
 *    for that time.
 *
 * While the plugin is still active this file does nothing.
 */
defined('ABSPATH') || exit;

if (defined('RS_REVISION') || class_exists('RevSliderFront', false)) {
    return; // the plugin is active and does the work
}

// Switching the plugin back on (wp plugin activate revslider, Activate on the
// Plugins screen, or the REST plugins route) loads revslider.php later in that
// same request, after the theme: the plugin stops with "more than one
// instance of Slider Revolution installed" when RevSliderFront exists, and its
// classes cannot be declared twice. So that one request runs without the port.
$af_rs_activating = false;
if (defined('WP_CLI') && WP_CLI) {
    $af_rs_argv = isset($GLOBALS['argv']) ? (array) $GLOBALS['argv'] : (isset($_SERVER['argv']) ? (array) $_SERVER['argv'] : array());
    if (in_array('plugin', $af_rs_argv, true) && (in_array('activate', $af_rs_argv, true) || in_array('toggle', $af_rs_argv, true))) {
        foreach ($af_rs_argv as $af_rs_arg) {
            if ($af_rs_arg === '--all' || strpos((string) $af_rs_arg, 'revslider') === 0) $af_rs_activating = true;
        }
    }
    unset($af_rs_argv, $af_rs_arg);
} elseif (isset($GLOBALS['pagenow']) && $GLOBALS['pagenow'] === 'plugins.php') {
    $af_rs_action = array(isset($_REQUEST['action']) ? $_REQUEST['action'] : '', isset($_REQUEST['action2']) ? $_REQUEST['action2'] : '');
    if (array_intersect($af_rs_action, array('activate', 'activate-selected', 'error_scrape'))) {
        $af_rs_plugins = array_merge((array) (isset($_REQUEST['plugin']) ? $_REQUEST['plugin'] : array()), (array) (isset($_REQUEST['checked']) ? $_REQUEST['checked'] : array()));
        if (in_array('revslider/revslider.php', $af_rs_plugins, true)) $af_rs_activating = true;
    }
    unset($af_rs_action, $af_rs_plugins);
} elseif (isset($_SERVER['REQUEST_URI']) && strpos(rawurldecode((string) $_SERVER['REQUEST_URI']), 'wp/v2/plugins/revslider') !== false
    && isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    $af_rs_activating = true;
}
if ($af_rs_activating) {
    unset($af_rs_activating);
    return;
}
unset($af_rs_activating);

require_once __DIR__ . '/revslider/boot.php';
