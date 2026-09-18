<?php
/**
 * Two fixes from the full-site audit of 18 Sep 2026, both correcting plugin
 * behaviour from the theme so no plugin file is patched.
 *
 * 1. THE WISHLIST REDIRECT MAKES TWO HOPS INSTEAD OF ONE.
 *    Every "add to wishlist" link — /?add-to-wishlist=ID — answers with a 302
 *    to /wishlist/WOOSW, and WordPress then answers THAT with a 301 to
 *    /wishlist/WOOSW/ because it insists on the trailing slash. Thirty-one of
 *    them in the crawl, each costing the visitor a needless round trip. The
 *    plugin builds the address without the slash; WordPress runs every
 *    wp_redirect() location through the wp_redirect filter, so the slash is
 *    added there and the second hop never happens.
 *
 * 2. THE HOMEPAGE WEIGHS 30 MB, AND 20 MB OF IT IS INSTAGRAM VIDEO.
 *    The Instagram feed plugin renders its reels as <video> elements with a
 *    source, and a browser fetches a video's data the moment it parses the
 *    tag — before the visitor has scrolled anywhere near it, and whether or
 *    not they ever press play. Four reels at 2.8 to 8 MB each were arriving
 *    on every homepage visit. preload="none" is the standard control for
 *    exactly this: the browser shows the poster and fetches nothing until the
 *    video is played. Autoplaying videos still play — play() triggers the
 *    fetch — so nothing above the fold changes behaviour.
 *
 *    Done as an output buffer on the front page rather than the_content,
 *    because the feed may be rendered by a widget outside the post body and
 *    the buffer sees the whole page. LiteSpeed caches the result, so the
 *    pass runs once per cache generation, not once per visitor.
 */
if (!defined('ABSPATH')) exit;

/* ---- 1. wishlist: one hop, not two ---- */
add_filter('wp_redirect', function ($location) {
    if (!is_string($location) || $location === '') return $location;
    $host = (string) wp_parse_url(home_url(), PHP_URL_HOST);
    if ($host === '') return $location;
    // /wishlist/<key> with no trailing slash and no query string
    if (preg_match('#^https?://(?:www\.)?' . preg_quote($host, '#') . '/wishlist/[A-Za-z0-9]+$#', $location)) {
        return $location . '/';
    }
    return $location;
}, 5);

/* ---- 2. front page: videos wait to be played; feed images wait to be seen ---- */
function af_audit_fix_front_html($html) {
    if (!is_string($html) || $html === '' || stripos($html, '<video') === false && stripos($html, 'cdninstagram') === false) return $html;

    // <video ...> that states no preload policy gets preload="none".
    $html = preg_replace_callback('#<video\b([^>]*)>#i', function ($m) {
        $attrs = $m[1];
        if (stripos($attrs, 'preload=') !== false) return $m[0];
        $attrs = rtrim($attrs);
        if (substr($attrs, -1) === '/') $attrs = rtrim(substr($attrs, 0, -1));
        return '<video' . $attrs . ' preload="none">';
    }, $html);

    // Instagram feed images are far below the fold; let the browser defer them.
    $html = preg_replace_callback('#<img\b([^>]*cdninstagram[^>]*)>#i', function ($m) {
        $attrs = $m[1];
        if (stripos($attrs, 'loading=') !== false) return $m[0];
        $attrs = rtrim($attrs);
        if (substr($attrs, -1) === '/') $attrs = rtrim(substr($attrs, 0, -1));
        return '<img' . $attrs . ' loading="lazy">';
    }, $html);

    return $html;
}

add_action('template_redirect', function () {
    if (is_admin() || !is_front_page()) return;
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] !== 'GET') return;
    if (function_exists('wp_doing_ajax') && wp_doing_ajax()) return;
    if (defined('REST_REQUEST') && REST_REQUEST) return;
    ob_start('af_audit_fix_front_html');
}, 1);
