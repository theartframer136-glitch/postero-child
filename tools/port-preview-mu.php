<?php
/**
 * AF port preview — TEMPORARY. Put in wp-content/mu-plugins/ by the Preview
 * Ports workflow at the start of its run and deleted at its end.
 *
 * A request carrying the run's one-time secret in X-AF-Port-Preview gets the
 * plugins named in X-AF-Port-Skip left unloaded, so their theme ports do the
 * work for that one request; it is never cached. Every other request (every
 * visitor) is untouched: without the secret this file returns at once.
 */
// the secret arrives as a header or, where a CDN strips custom headers, as ?af_pv=
$af_preview_key = isset($_SERVER['HTTP_X_AF_PORT_PREVIEW']) ? (string) $_SERVER['HTTP_X_AF_PORT_PREVIEW'] : (isset($_GET['af_pv']) ? (string) $_GET['af_pv'] : '');
if ($af_preview_key === '' || !hash_equals('__AF_PREVIEW_TOKEN__', $af_preview_key)) {
    return;
}
$af_preview_skip_raw = isset($_SERVER['HTTP_X_AF_PORT_SKIP']) ? (string) $_SERVER['HTTP_X_AF_PORT_SKIP'] : (isset($_GET['af_skip']) ? (string) $_GET['af_skip'] : '');
$af_preview_skip = array_filter(array_map('trim', explode(',', preg_replace('/[^a-z0-9,-]/', '', $af_preview_skip_raw))));
if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
if (!defined('LSCACHE_NO_CACHE')) define('LSCACHE_NO_CACHE', true);
add_filter('option_active_plugins', function ($plugins) use ($af_preview_skip) {
    return array_values(array_filter((array) $plugins, function ($file) use ($af_preview_skip) {
        return !in_array(strtok((string) $file, '/'), $af_preview_skip, true);
    }));
}, 1);
add_action('wp_head', function () use ($af_preview_skip) {
    echo '<meta name="af-port-preview" content="' . esc_attr(implode(',', $af_preview_skip) ?: 'none') . '">' . "\n";
}, 1);
add_action('send_headers', function () {
    header('X-LiteSpeed-Cache-Control: no-cache');
    header('Cache-Control: no-store, private');
    header('X-AF-Port-Preview: on');
});
