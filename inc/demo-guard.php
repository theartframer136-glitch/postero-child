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

/**
 * A page path typed into a link field as if it were a whole address.
 *
 * Full site test, 2026-10-23: the header and footer templates carry a link to
 * "http://shop/" on every page. Someone typed "shop/" into an Elementor link
 * field and the editor helpfully prefixed http://, which makes "shop" the host
 * name. A browser tries to resolve a machine called "shop", fails, and shows
 * the visitor an error page.
 *
 * A host name with no dot in it is never a public site (localhost aside), so
 * the rule is general rather than a list of the links found this time: any
 * href whose host is a single word is read as the path it was meant to be.
 * Only href attributes are touched; text and scripts are left alone.
 */
function af_bare_host_rewrite($html, $home) {
    $home = rtrim((string) $home, '/');
    return preg_replace_callback(
        '~(\bhref\s*=\s*)(["\'])https?://([a-z0-9][a-z0-9-]*)(?=[/?#"\'])([^"\']*)\2~i',
        function ($m) use ($home) {
            if (strtolower($m[3]) === 'localhost') return $m[0];
            return $m[1] . $m[2] . $home . '/' . $m[3] . $m[4] . $m[2];
        },
        $html
    );
}

/** True if the markup has a link whose host is a single word. */
function af_bare_host_present($html) {
    return (bool) preg_match('~\bhref\s*=\s*["\']https?://(?!localhost\b)[a-z0-9][a-z0-9-]*(?=[/?#"\'])~i', $html);
}

/**
 * The header's phone number and email address, server side.
 *
 * Both are stored in the Elementor header as href="#". functions.php (PHASE
 * 19) corrects them in the browser after load, so a visitor with JavaScript
 * gets working tap-to-call and mailto links. The full site test of
 * 2026-10-23 found the raw "#" on every page of the server HTML, two per
 * page, which is what search engines and visitors without JavaScript see.
 * Same rule as the browser fix: an href="#" anchor whose text is the number
 * or the address.
 */
function af_contact_link_rewrite($html) {
    return preg_replace_callback(
        '~<a\b([^>]*?)\bhref\s*=\s*(["\'])#\2([^>]*)>(.*?)</a>~is',
        function ($m) {
            $text = trim(preg_replace('~\s+~', ' ', af_link_text($m[4])));
            if (preg_match('~470[\s\-]?7280~', $text)) $to = 'tel:+16104707280';
            elseif (stripos($text, 'theartframer136@gmail.com') !== false) $to = 'mailto:theartframer136@gmail.com';
            else return $m[0];
            return '<a' . $m[1] . 'href=' . $m[2] . $to . $m[2] . $m[3] . '>' . $m[4] . '</a>';
        },
        $html
    );
}

/** Tags out, entities decoded; enough to read an anchor's visible text. */
function af_link_text($s) {
    return html_entity_decode(strip_tags((string) $s), ENT_QUOTES, 'UTF-8');
}

/** True if a "#" anchor might hold the phone number or the address. */
function af_contact_link_present($html) {
    return (strpos($html, '7280') !== false || strpos($html, 'theartframer136@') !== false)
        && (strpos($html, 'href="#"') !== false || strpos($html, "href='#'") !== false);
}

/** The buffer callback. Separate from the rewrite so it can be tested. */
function af_demo_filter_page($html) {
    if (!is_string($html) || $html === '') return $html;
    if (af_demo_present($html))      $html = af_demo_rewrite($html, home_url());
    if (af_bare_host_present($html)) $html = af_bare_host_rewrite($html, home_url());
    if (af_contact_link_present($html)) $html = af_contact_link_rewrite($html);
    return $html;
}

/**
 * Why the buffer starts at -100 and not 1.
 *
 * Full site test, 2026-10-23: /blog/ and /frame-the-moment/ still sent the
 * demo-store links in their HTML. Both pages are drawn by their own
 * template_redirect handler (inc/blog-hub.php and PHASE 27 in functions.php),
 * which renders the whole page and exits, at priority 1. Both are registered
 * before this file is loaded, so at an equal priority they ran first and the
 * page was finished before this buffer existed. Every page drawn that way is
 * covered once the buffer opens ahead of all of them.
 */
if (!defined('AF_DEMO_GUARD_PRIORITY')) define('AF_DEMO_GUARD_PRIORITY', -100);

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
}, AF_DEMO_GUARD_PRIORITY);

/**
 * The net the output buffer cannot cast.
 *
 * 2026-09-11. The owner tapped "Shop" on the live site and landed on
 * demo2wpopal.b-cdn.net/postero/shop/ — which now serves Bunny CDN's "Domain
 * suspended or not configured" page — a day AFTER the buffer guard above
 * shipped and deployed cleanly. The module was loaded, the rewrite was
 * correct, and the link still pointed at the demo store.
 *
 * That combination only has one explanation: the link is not in the HTML the
 * server sends. The parent theme builds that bar in its own JavaScript, from
 * its own options, after the page has arrived. An output buffer runs on the
 * server, before any of that happens, so it cannot see the link and no amount
 * of improving it ever will.
 *
 * So the guard has to run where the link actually appears: in the browser, on
 * the finished DOM.
 *
 *   sweep        every href/action already in the document
 *   observe      anything added or changed afterwards, which is the case that
 *                matters here — the bar is built late
 *   click        the last word. Even a link written a millisecond before the
 *                tap is corrected in the capture phase, before the browser
 *                begins navigating.
 *
 * The buffer above still runs and still does the bulk of the work on ordinary
 * server-rendered markup. This covers what it structurally cannot.
 */
add_action('wp_footer', 'af_demo_dom_net', 99);
function af_demo_dom_net() {
    if (is_admin()) return;
    $hosts = array_values(af_demo_hosts());
    $home  = rtrim(home_url(), '/');
    ?>
<script id="af-demo-guard">
(function () {
  var HOSTS = <?php echo wp_json_encode($hosts); ?>;
  var HOME  = <?php echo wp_json_encode($home); ?>;

  // The same rule the PHP side enforces: the match must END at a host
  // boundary. Without the lookahead "demo2wpopal.b-cdn.net.evil.test" — a
  // lookalike that merely starts with ours — would be rewritten into
  // "theartframer.us.evil.test", which is still someone else's domain wearing
  // our name. In an attribute the URL is the whole string, so end-of-string
  // counts as a boundary too.
  var RE = [];
  for (var h = 0; h < HOSTS.length; h++) {
    RE.push(new RegExp('(?:https?:)?//' + HOSTS[h].replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '(?=[/?#]|$)', 'i'));
  }

  // A page path typed as an address ("http://shop/"): the host is one word,
  // which no public site has. Same rule as af_bare_host_rewrite() in PHP.
  var BARE = /^https?:\/\/([a-z0-9][a-z0-9-]*)([\/?#].*)?$/i;

  function fix(url) {
    if (!url) return url;
    for (var i = 0; i < RE.length; i++) {
      if (RE[i].test(url)) return url.replace(RE[i], HOME);
    }
    var b = BARE.exec(url);
    if (b && b[1].toLowerCase() !== 'localhost') return HOME + '/' + b[1] + (b[2] || '');
    return url;
  }

  /** Correct one element in place. Returns true if it had to. */
  function mend(el) {
    if (!el || el.nodeType !== 1) return false;
    var attr = el.tagName === 'FORM' ? 'action' : 'href';
    if (!el.hasAttribute || !el.hasAttribute(attr)) return false;
    var was = el.getAttribute(attr), now = fix(was);
    if (now === was) return false;
    el.setAttribute(attr, now);
    return true;
  }

  function sweep(root) {
    if (!root || root.nodeType !== 1) return;
    mend(root);                                   // querySelectorAll skips the root itself
    var found = root.querySelectorAll('a[href],area[href],form[action]');
    for (var i = 0; i < found.length; i++) mend(found[i]);
  }

  sweep(document.documentElement);

  // The bar is built after this script runs, so watching is the whole point.
  if (window.MutationObserver) {
    new MutationObserver(function (recs) {
      for (var i = 0; i < recs.length; i++) {
        var r = recs[i];
        if (r.type === 'attributes') { mend(r.target); continue; }
        for (var j = 0; j < r.addedNodes.length; j++) sweep(r.addedNodes[j]);
      }
    }).observe(document.documentElement, {
      childList: true, subtree: true, attributes: true, attributeFilter: ['href', 'action']
    });
  }

  // Capture phase: this runs before any handler the theme attached, and before
  // the browser starts navigating. Whatever wrote the href, the tap lands here.
  document.addEventListener('click', function (e) {
    var el = e.target;
    while (el && el.nodeType === 1 && el.tagName !== 'A') el = el.parentNode;
    if (el && el.nodeType === 1) mend(el);
  }, true);
})();
</script>
    <?php
}
