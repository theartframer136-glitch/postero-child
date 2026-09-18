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
    if (!is_string($html) || $html === '') return $html;
    if (stripos($html, '<video') === false && stripos($html, 'cdninstagram') === false) return $html;

    // Whole <video>…</video> blocks, because the decision depends on the
    // <source> inside: a video served from another company's CDN is the one
    // worth holding back.
    //
    // The first version of this only touched videos that stated NO preload
    // policy, and measurement showed why that was not enough. Of 38 videos on
    // the front page it covered 20; the other 18 are Elementor video widgets
    // that already declare preload="metadata", so it skipped them — and those
    // are exactly the Instagram reels. Measured by host, the homepage pulls
    // 25.1 MB from cdninstagram against 4.3 MB from this site. "metadata" is
    // meant to fetch a few hundred kilobytes; these arrive at about 4.8 MB
    // apiece, so whatever the attribute promises, the bytes come anyway.
    //
    // So: a video whose sources are all local keeps whatever policy it was
    // given, and a video pulling from an outside host is pinned to "none".
    // It still plays the moment anyone presses play.
    $host = (string) wp_parse_url(home_url(), PHP_URL_HOST);
    $html = preg_replace_callback('#<video\b([^>]*)>(.*?)</video>#is', function ($m) use ($host) {
        list($all, $attrs, $inner) = array($m[0], $m[1], $m[2]);

        $srcs = array();
        if (preg_match('#\bsrc=(["\'])(.*?)\1#i', $attrs, $a)) $srcs[] = $a[2];
        if (preg_match_all('#<source\b[^>]*\bsrc=(["\'])(.*?)\1#i', $inner, $b)) $srcs = array_merge($srcs, $b[2]);

        $remote = false;
        foreach ($srcs as $u) {
            $h = (string) wp_parse_url($u, PHP_URL_HOST);
            if ($h !== '' && $host !== '' && stripos($h, $host) === false) { $remote = true; break; }
        }

        $has = stripos($attrs, 'preload=') !== false;
        if ($has && !$remote) return $all;          // local video, leave its own policy alone

        $clean = rtrim($attrs);
        if (substr($clean, -1) === '/') $clean = rtrim(substr($clean, 0, -1));
        if ($has) {
            $clean = preg_replace('#\s*\bpreload=(["\']).*?\1#i', '', $clean);
            $clean = preg_replace('#\s*\bpreload=[^\s>]+#i', '', $clean);
        }
        return '<video' . $clean . ' preload="none">' . $inner . '</video>';
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
