<?php
/**
 * No link on this site may point at the theme vendor's demo store.
 *
 * Audit, 2026-09-10 (TAF-01, the most expensive finding in it): three of the
 * four buttons in the mobile bottom bar — the whole navigation on a phone —
 * pointed at demo2wpopal.b-cdn.net, the Postero demo catalogue:
 *
 *   Shop     -> https://demo2wpopal.b-cdn.net/postero/shop/
 *   Account  -> https://demo2wpopal.b-cdn.net/postero/my-account/
 *   Wishlist -> https://demo2wpopal.b-cdn.net/postero/wishlist
 *
 * A visitor who taps "Shop" — the most obvious control on the screen — leaves
 * the shop and lands on someone else's demo. They do not come back.
 *
 * WHY THE EXISTING FIX DID NOT CATCH IT. functions.php has filtered
 * wp_nav_menu_items for this since long before the audit. That filter only
 * fires for menus rendered through wp_nav_menu(), and the bottom bar is not
 * one: the parent theme builds it from its own options. The filter was doing
 * its job perfectly and never saw the markup that mattered.
 *
 * SO THIS DOES NOT GUESS WHERE THE URL COMES FROM. Rather than hunt for the
 * option the parent theme reads — which a parent update could rename anyway,
 * and which this child theme cannot see — the finished page is checked on the
 * way out. Wherever a demo link is emitted, by whatever plugin, widget,
 * shortcode or theme option, it is rewritten to this site.
 *
 * IT IS CHEAP. The callback runs once per request on the assembled HTML and
 * begins with a strpos: on the overwhelming majority of pages, where no demo
 * host appears at all, that single scan is the entire cost and the buffer is
 * returned untouched. Only a page that actually contains one pays for the
 * replacement.
 *
 * THIS IS A GUARD, NOT THE CURE. The real fix is to correct the URLs in the
 * theme options, which needs admin access to the live site. Once that is done
 * this module quietly becomes a no-op that costs one strpos and makes sure a
 * parent-theme update can never reintroduce them.
 */
if (!defined('ABSPATH')) exit;

/**
 * Hosts that must never appear in a link on this site, longest first so a
 * host carrying a path prefix is rewritten before the bare host is.
 */
function af_demo_hosts() {
    return apply_filters('af_demo_hosts', array(
        'demo2wpopal.b-cdn.net/postero',              // the Postero demo store
        'demo2wpopal.b-cdn.net',
        'chocolate-chicken-365829.hostingersite.com', // this site's old staging name
    ));
}

/** True if the markup contains anything worth rewriting. */
function af_demo_present($html) {
    foreach (af_demo_hosts() as $host) {
        if (strpos($html, $host) !== false) return true;
    }
    return false;
}

/**
 * Point every demo link at this site instead.
 *
 * Kept pure — no WordPress, no globals beyond the home URL passed in — so the
 * one rule that matters can be tested directly: a real link is never touched.
 */
function af_demo_rewrite($html, $home) {
    $home = rtrim((string) $home, '/');
    foreach (af_demo_hosts() as $host) {
        // (https?:)?//  covers both schemes and the protocol-relative form a
        // CDN host is often emitted as.
        //
        // The lookahead is the point of using a pattern rather than a plain
        // str_replace: the match must END at a host boundary. Without it
        // "demo2wpopal.b-cdn.net.evil.test" — a lookalike host that merely
        // starts with ours — would be rewritten into
        // "theartframer.us.evil.test", which is still someone else's domain
        // wearing our name. Caught by the test below before this shipped.
        $pattern = '~(?:https?:)?//' . preg_quote($host, '~') . '(?=[/?#"\'\s<>)\]]|$)~i';
        $html = preg_replace($pattern, $home, $html);
    }
    return $html;
}

/** The buffer callback. Separate from the rewrite so it can be tested. */
function af_demo_filter_page($html) {
    if (!is_string($html) || $html === '') return $html;
    if (!af_demo_present($html)) return $html;   // the usual case, and the cheap one
    return af_demo_rewrite($html, home_url());
}

/**
 * Front-end page views only. The admin, AJAX, the REST API and cron are left
 * alone: none of them render the bottom bar, and buffering a REST response
 * would be a good way to break something that has nothing to do with this.
 */
add_action('template_redirect', function () {
    if (is_admin())                                     return;
    if (defined('DOING_AJAX')   && DOING_AJAX)          return;
    if (defined('REST_REQUEST') && REST_REQUEST)        return;
    if (defined('DOING_CRON')   && DOING_CRON)          return;
    if (defined('WP_CLI')       && WP_CLI)              return;
    if (function_exists('wp_is_json_request') && wp_is_json_request()) return;
    ob_start('af_demo_filter_page');
}, 1);
