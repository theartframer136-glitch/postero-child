<?php
/**
 * Instagram feed: the plugin "Social Feed Gallery" (insta-gallery) 5.0.9,
 * moved into the theme (the [insta-gallery] feed on the home page, its REST
 * endpoints that fetch the posts, the token-renewal cron, the settings page).
 *
 * inc/ports/qligg/ is the plugin's own code, copied unchanged (lib/, its
 * composer vendor/ and jetpack_vendor/, compatibility/). Its build/ and
 * assets/ folders, and the three built scripts of its Jetpack assets package
 * (jetpack_vendor/automattic/jetpack-assets/build/), are copied byte for byte
 * to assets/ports/qligg/ with the plugin's folder layout. Accounts, feeds and
 * settings stay in the same options (insta_gallery_accounts,
 * insta_gallery_feeds, …). Admin keeps the Social Feed Gallery menu (accounts,
 * feeds, settings), the Elementor widget, the block and the legacy widget.
 *
 * Asset URLs. The plugin builds every asset URL with plugins_url($path,
 * QLIGG_PLUGIN_FILE), and the Jetpack package with plugins_url($path,
 * __FILE__); the filter below answers both with the theme copy's URL, so not
 * one line of the plugin had to change. plugins_url() hands its filter the
 * path run through wp_normalize_path() (no '//'), and in a web request the
 * theme folder can read …/public_html//wp-content/… (a DOCUMENT_ROOT ending in
 * '/'; WP-CLI always passes a clean path), so QLIGG_PLUGIN_FILE is normalised
 * too and the file name …/assets/ports/qligg/insta-gallery.php alone also
 * matches. When the match fails, WordPress builds
 * /wp-content/plugins/<server path>/assets/ports/qligg/…, which does not
 * exist: the feed's script and style do not load and the feed stays empty.
 * As a last check, a qligg-* script or style still under /wp-content/plugins/
 * is pointed at the theme copy before the page is printed.
 *
 * Hook order: the plugin's callbacks are put back where the plugin had them
 * (af_ports_restore_order), ahead of LiteSpeed, Rank Math, Transposh, WOOCS,
 * Square, WooCommerce and the theme at the same hook and priority; e.g. the
 * "Social Feed Gallery" admin menu stays above "Transposh".
 *
 * Left out: the vendor_packages/ add-ons (dashboard news widget, promotion
 * notices, plugin-install tab, plugin-table links, feedback form, and an
 * i18n map that never ran) — admin marketing only.
 *
 * While the plugin is still active this file does nothing.
 */
defined('ABSPATH') || exit;

if (defined('QLIGG_PLUGIN_VERSION') || class_exists('QuadLayers\IGG\Plugin', false)) {
    return; // the plugin is active and does the work
}

// insta-gallery.php, the same constants; FILE/DIR point at the asset copy,
// normalised the way plugins_url() compares paths.
define('QLIGG_PLUGIN_NAME', 'Social Feed Gallery');
define('QLIGG_PLUGIN_VERSION', '5.0.9');
define('QLIGG_PLUGIN_FILE', wp_normalize_path(get_stylesheet_directory() . '/assets/ports/qligg/insta-gallery.php'));
define('QLIGG_PLUGIN_BASENAME', 'insta-gallery/insta-gallery.php');
define('QLIGG_PLUGIN_DIR', trailingslashit(wp_normalize_path(get_stylesheet_directory() . '/assets/ports/qligg')));
define('QLIGG_DOMAIN', 'qligg');
define('QLIGG_PREFIX', QLIGG_DOMAIN);
define('QLIGG_WORDPRESS_URL', 'https://wordpress.org/plugins/insta-gallery/');
define('QLIGG_REVIEW_URL', 'https://wordpress.org/support/plugin/insta-gallery/reviews/?filter=5#new-post');
define('QLIGG_GROUP_URL', 'https://www.facebook.com/groups/quadlayers');
define('QLIGG_DEVELOPER', false);
define('QLIGG_ACCOUNT_URL', admin_url('admin.php?page=qligg_backend&tab=accounts'));

// plugins_url($path, <a file of the plugin>) -> the same file in the theme
// copy: QLIGG_PLUGIN_FILE (build/…, assets/…), and a file of the copied PHP
// (jetpack-assets: src/../build/i18n-loader.js and the like).
$af_qligg_php = wp_normalize_path(__DIR__ . '/qligg/');
add_filter('plugins_url', function ($url, $path, $plugin) use ($af_qligg_php) {
    if (!is_string($plugin) || $plugin === '') return $url;
    $p = wp_normalize_path($plugin);
    $main = '/assets/ports/qligg/insta-gallery.php';
    if ($p === QLIGG_PLUGIN_FILE || substr($p, -strlen($main)) === $main) {
        $dir = '';
    } elseif (strpos($p, $af_qligg_php) === 0) {
        $dir = dirname(substr($p, strlen($af_qligg_php)));
        $dir = $dir === '.' ? '' : '/' . $dir;
    } else {
        return $url;
    }
    $url = get_stylesheet_directory_uri() . '/assets/ports/qligg' . $dir;
    if ($path !== '' && is_string($path)) $url .= '/' . ltrim($path, '/');
    return $url;
}, 10, 3);

// Last check, after the plugin registers its scripts and styles (priority 10)
// on the front end, in admin and in the Elementor editor: any qligg-* src that
// still went through WordPress's plugin-folder fallback is pointed at the copy.
$af_qligg_fix_src = function () {
    $plugins = wp_parse_url(WP_PLUGIN_URL, PHP_URL_PATH) . '/';
    $copy = '/assets/ports/qligg/';
    foreach (array(wp_scripts(), wp_styles()) as $deps) {
        foreach ($deps->registered as $handle => $dep) {
            if (strpos($handle, 'qligg-') !== 0 || !is_string($dep->src)) continue;
            $at = strpos($dep->src, $copy);
            if ($at !== false && strpos($dep->src, $plugins) !== false) {
                $dep->src = get_stylesheet_directory_uri() . $copy . substr($dep->src, $at + strlen($copy));
            }
        }
    }
};
add_action('wp_enqueue_scripts', $af_qligg_fix_src, 11);
add_action('admin_enqueue_scripts', $af_qligg_fix_src, 11);
add_action('elementor/editor/after_enqueue_scripts', $af_qligg_fix_src, 11);

$af_qligg_snap = af_ports_hook_snapshot();

require_once __DIR__ . '/qligg/vendor/autoload.php';
require_once __DIR__ . '/qligg/compatibility/wordpress.php';
require_once __DIR__ . '/qligg/compatibility/php.php';
require_once __DIR__ . '/qligg/compatibility/old.php';
require_once __DIR__ . '/qligg/compatibility/widget.php';
require_once __DIR__ . '/qligg/compatibility/class-backend.php';
require_once __DIR__ . '/qligg/compatibility/class-frontend.php';
require_once __DIR__ . '/qligg/compatibility/class-gutenberg.php';
require_once __DIR__ . '/qligg/lib/class-plugin.php';

af_ports_restore_order($af_qligg_snap, 'insta-gallery');

// jetpack-assets/actions.php, which the plugin's autoloader ran when it loaded:
// on plugins_loaded:1 Script_Data and Shared_Stores_Assets hook the
// registration of their scripts to wp_loaded. WooCommerce bundles the same
// file under the same Composer id, so with the plugin off WooCommerce's copy
// runs instead (its version may lack the shared-stores part); whatever is
// still unhooked is done here.
foreach (array('Automattic\Jetpack\Assets\Script_Data', 'Automattic\Jetpack\Assets\Shared_Stores_Assets') as $af_qligg_c) {
    if (!has_action('wp_loaded', array($af_qligg_c, 'register_assets'))) $af_qligg_c::configure();
}
unset($af_qligg_php, $af_qligg_fix_src, $af_qligg_snap, $af_qligg_c);

/*
 * ---- Smaller Instagram images ----------------------------------------------
 *
 * Measured 16 Oct 2026: the home page weighed 7.6 MB and one Instagram feed
 * image was 4.4 MB of it. The Graph API hands the plugin each post's original
 * file and offers no size parameter, and the feed is drawn in the browser from
 * the plugin's REST response, so the only place to intervene is that response.
 * Every image URL in it is swapped for a copy this site resized to at most
 * 1080 px (JPEG, quality 80) and keeps under uploads/af-ig/. A copy is made
 * the first time a URL is seen (at most four downloads per request, the rest
 * queued for a cron run a minute later), then served from disk. The key is
 * the CDN path without its signed query string, so a token refresh does not
 * start the cache over. Video posters are shrunk the same way; videos are not
 * touched. Anything that fails falls back to the original URL.
 */
function af_ig_cache_dir() {
    $up = wp_get_upload_dir();
    return array($up['basedir'] . '/af-ig', $up['baseurl'] . '/af-ig');
}

/** A smaller local copy of an Instagram CDN image, or the URL unchanged. */
function af_ig_small_image($url, $force = false) {
    static $budget = 4;
    try {
        if (!is_string($url) || $url === '') return $url;
        $host = (string) parse_url($url, PHP_URL_HOST);
        if ($host === '' || (stripos($host, 'cdninstagram.com') === false && stripos($host, 'fbcdn.net') === false)) return $url;
        $path = (string) parse_url($url, PHP_URL_PATH);
        if ($path === '' || !preg_match('/\.(jpe?g|png|webp|heic)$/i', $path)) return $url;
        list($dir, $base) = af_ig_cache_dir();
        $key  = md5($path) . '.jpg';
        $file = $dir . '/' . $key;
        if (is_file($file) && filesize($file) > 0) return $base . '/' . $key;
        if (!$force) {
            if ($budget <= 0) { af_ig_queue_warm($url); return $url; }
            $budget--;
        }
        if (!wp_mkdir_p($dir)) return $url;
        if (!is_file($dir . '/index.html')) @file_put_contents($dir . '/index.html', '');
        if (!function_exists('download_url')) require_once ABSPATH . 'wp-admin/includes/file.php';
        $tmp = download_url($url, 20);
        if (is_wp_error($tmp)) return $url;
        $ok = false;
        $ed = wp_get_image_editor($tmp);
        if (!is_wp_error($ed)) {
            $ed->resize(1080, 1080, false);
            $ed->set_quality(80);
            $saved = $ed->save($file, 'image/jpeg');
            if (!is_wp_error($saved) && !empty($saved['path'])) {
                if ($saved['path'] !== $file) @rename($saved['path'], $file);
                $ok = is_file($file) && filesize($file) > 0;
            }
        }
        @unlink($tmp);
        return $ok ? $base . '/' . $key : $url;
    } catch (\Throwable $e) {
        return $url;
    }
}

/** Walk the plugin's items (and carousel children) and shrink every image URL. */
function af_ig_shrink_items($items) {
    if (!is_array($items)) return $items;
    foreach ($items as &$item) {
        if (!is_array($item)) continue;
        if (isset($item['media']) && is_array($item['media'])) {
            $type = isset($item['media']['type']) ? strtoupper((string) $item['media']['type']) : '';
            if (!empty($item['media']['url']) && $type !== 'VIDEO') $item['media']['url'] = af_ig_small_image($item['media']['url']);
            if (!empty($item['media']['thumbnail'])) $item['media']['thumbnail'] = af_ig_small_image($item['media']['thumbnail']);
        }
        if (!empty($item['children']) && is_array($item['children'])) $item['children'] = af_ig_shrink_items($item['children']);
    }
    unset($item);
    return $items;
}

add_filter('rest_post_dispatch', function ($result, $server, $request) {
    try {
        if (!($result instanceof WP_REST_Response) || !($request instanceof WP_REST_Request)) return $result;
        if (strpos((string) $request->get_route(), '/quadlayers/instagram/frontend/') === false) return $result;
        $data = $result->get_data();
        if (!is_array($data) || empty($data['data']) || !is_array($data['data'])) return $result;
        $data['data'] = af_ig_shrink_items($data['data']);
        $result->set_data($data);
    } catch (\Throwable $e) {
    }
    return $result;
}, 10, 3);

/** URLs that missed the per-request budget: make their copies a minute later. */
function af_ig_queue_warm($url) {
    $q = get_transient('af_ig_warm_queue');
    $q = is_array($q) ? $q : array();
    if (in_array($url, $q, true) || count($q) >= 80) return;
    $q[] = $url;
    set_transient('af_ig_warm_queue', $q, HOUR_IN_SECONDS);
    if (!wp_next_scheduled('af_ig_warm')) wp_schedule_single_event(time() + 60, 'af_ig_warm');
}
add_action('af_ig_warm', function () {
    $q = get_transient('af_ig_warm_queue');
    delete_transient('af_ig_warm_queue');
    if (!is_array($q)) return;
    $t0 = time();
    foreach ($q as $u) {
        af_ig_small_image($u, true);
        if (time() - $t0 > 50) break;   // leave the rest for the next page view to queue again
    }
});

// Copies nobody has asked for in 90 days are removed once a week.
add_action('init', function () {
    if (!wp_next_scheduled('af_ig_prune')) wp_schedule_event(time() + DAY_IN_SECONDS, 'weekly', 'af_ig_prune');
});
add_action('af_ig_prune', function () {
    list($dir) = af_ig_cache_dir();
    foreach (glob($dir . '/*.jpg') ?: array() as $f) {
        $seen = max((int) @fileatime($f), (int) @filemtime($f));
        if ($seen && $seen < time() - 90 * DAY_IN_SECONDS) @unlink($f);
    }
});
