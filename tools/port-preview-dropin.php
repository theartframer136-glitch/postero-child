<?php
/**
 * AF-PORT-PREVIEW-DROPIN. TEMPORARY: put in place as wp-content/db.php by the
 * Preview Ports and Preview Look workflows (tools/port-preview-ctl.sh) at the
 * start of a run and deleted at its end.
 *
 * Why db.php: WordPress loads it on every request before any plugin, and the
 * site has none of its own (tools/diag-dropins.php, 5 Oct). The must-use
 * folder cannot be used: on this host wp-content/mu-plugins is a link to
 * Hostinger's shared, read-only folder (tools/diag-mu-plugins-dir.php), so
 * until 5 Oct every upload there failed and the previews compared the live
 * site with itself. A db.php that does not set up $wpdb leaves WordPress to
 * open its own database connection as usual.
 *
 * A request carrying the run's one-time secret (header X-AF-Port-Preview, or
 * ?af_pv= where a CDN strips custom headers) gets the plugins named in
 * X-AF-Port-Skip / ?af_skip= left unloaded, so their theme ports do the work
 * for that one request. It is never cached, and it keeps nothing visitors are
 * served: no Elementor CSS file, CSS record, element cache or Elementor
 * option. Every other request (every visitor) is untouched: without the
 * secret this file returns at once, and after the end time the run wrote
 * into it, it does nothing at all.
 */
// Unfilled (the copy in the theme) the end time reads 0, so it does nothing.
if (time() > (int) '__AF_PREVIEW_UNTIL__' || strpos('__AF_PREVIEW_TOKEN__', '__AF_') === 0) {
    return;
}
$af_preview_key = isset($_SERVER['HTTP_X_AF_PORT_PREVIEW']) ? (string) $_SERVER['HTTP_X_AF_PORT_PREVIEW'] : (isset($_GET['af_pv']) ? (string) $_GET['af_pv'] : '');
if ($af_preview_key === '' || !hash_equals('__AF_PREVIEW_TOKEN__', $af_preview_key)) {
    return;
}
$af_preview_skip_raw = isset($_SERVER['HTTP_X_AF_PORT_SKIP']) ? (string) $_SERVER['HTTP_X_AF_PORT_SKIP'] : (isset($_GET['af_skip']) ? (string) $_GET['af_skip'] : '');
$af_preview_skip = array_values(array_filter(array_map('trim', explode(',', preg_replace('/[^a-z0-9,-]/', '', $af_preview_skip_raw)))));
if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
if (!defined('LSCACHE_NO_CACHE')) define('LSCACHE_NO_CACHE', true);
// Sent now as well as on send_headers, which REST and AJAX answers skip.
if (!headers_sent()) {
    header('X-AF-Port-Preview: on');
    header('X-LiteSpeed-Cache-Control: no-cache');
    header('Cache-Control: no-store, private');
}
add_filter('option_active_plugins', function ($plugins) use ($af_preview_skip) {
    return array_values(array_filter((array) $plugins, function ($file) use ($af_preview_skip) {
        return !in_array(strtok((string) $file, '/'), $af_preview_skip, true);
    }));
}, 1);
// Elementor's element cache hands back a page as rendered earlier, while
// the skipped plugins were still loaded (and without the scripts their
// replacements ask for); for a preview request every element is rendered
// afresh. In Elementor 4.x the cache is on unless its "Element Cache"
// setting reads "disable" (core/base/document.php; the old experiment
// option no longer counts: tools/diag-elementor-element-cache.php, 5 Oct).
// X-AF-Port-Cache: keep / ?af_cache=keep instead serves the copy as visitors
// would get it just after a switch-off (read only: writes are stopped below).
$af_preview_cache = isset($_SERVER['HTTP_X_AF_PORT_CACHE']) ? (string) $_SERVER['HTTP_X_AF_PORT_CACHE'] : (isset($_GET['af_cache']) ? (string) $_GET['af_cache'] : '');
if ($af_preview_cache !== 'keep') {
    add_filter('pre_option_elementor_element_cache_ttl', function () {
        return 'disable';
    });
    add_filter('pre_option_elementor_experiment-e_element_cache', function () {
        return 'inactive';
    });
}
// Nothing a preview request works out is kept for visitors. Elementor
// rebuilds a page's CSS during a page view only when its record is empty
// (after a CSS flush; core/files/css/base.php enqueue()). For a preview
// request it may not write the file (print method "internal" makes write() a
// no-op), nor any _elementor post meta (the CSS record, the element cache),
// nor any Elementor option.
add_filter('pre_option_elementor_css_print_method', function () {
    return 'internal';
});
$af_preview_keep_meta = function ($check, $id, $key) {
    return (is_string($key) && strpos($key, '_elementor') === 0) ? false : $check;
};
add_filter('update_post_metadata', $af_preview_keep_meta, 1, 3);
add_filter('add_post_metadata', $af_preview_keep_meta, 1, 3);
add_filter('delete_post_metadata', $af_preview_keep_meta, 1, 3);
add_filter('pre_update_option', function ($value, $option, $old) {
    return (is_string($option) && (strpos($option, 'elementor') === 0 || strpos($option, '_elementor') === 0)) ? $old : $value;
}, 1, 3);
add_action('wp_head', function () use ($af_preview_skip) {
    echo '<meta name="af-port-preview" content="' . esc_attr(implode(',', $af_preview_skip) ?: 'none') . '">' . "\n";
}, 1);
add_action('send_headers', function () {
    header('X-LiteSpeed-Cache-Control: no-cache');
    header('Cache-Control: no-store, private');
    header('X-AF-Port-Preview: on');
});
