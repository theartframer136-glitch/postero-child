<?php

/**
 * The theme's larger static styles and scripts live in assets/af/ and are
 * printed as <link>/<script src> where they used to be inline (16 Oct 2026:
 * an uncached page carried 427 KB of inline scripts and 265 KB of inline
 * styles that the browser could never cache). Same position in the page, same
 * bytes, now cached by the browser and the CDN; ?v= is the file's time so a
 * deploy busts the cache. data-no-optimize keeps LiteSpeed from rewriting them.
 */
function af_static_url($rel) {
    $rel  = ltrim((string) $rel, '/');
    $path = get_stylesheet_directory() . '/assets/af/' . $rel;
    $ver  = @filemtime($path);
    return get_stylesheet_directory_uri() . '/assets/af/' . $rel . ($ver ? '?v=' . $ver : '');
}
/**
 * Postero Child Theme - functions.php
 * The Art Framer - theartframer.us
 */

// 0. Plugins moved into the theme (inc/plugin-ports.php). First, before any
//    hook below: the plugins registered theirs before the theme loaded, and a
//    port that shares a hook and priority with theme code must keep running
//    before it, as the plugin did.
require_once get_stylesheet_directory() . '/inc/plugin-ports.php';

// 1. Enqueue parent and child theme styles
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('postero-parent', get_template_directory_uri() . '/style.css');
    wp_enqueue_style('postero-child', get_stylesheet_uri(), array('postero-parent'), '1.0.0');
    // Version by file mtime: every deploy rewrites the files, so the URL
    // changes and no browser or edge cache can keep serving a stale copy.
    // (The hand-bumped strings before this were forgotten on deploy — the
    // Aug-13 drag-guard fix shipped server-side while every visitor's
    // browser kept the old custom.js?ver=1.3.6 for days.)
    $af_css_ver = @filemtime(get_stylesheet_directory() . '/assets/css/custom.css') ?: '3.4.13';
    $af_js_ver  = @filemtime(get_stylesheet_directory() . '/assets/js/custom.js') ?: '1.4.0';
    wp_enqueue_style('postero-child-custom', get_stylesheet_directory_uri() . '/assets/css/custom.css', array('postero-child'), $af_css_ver);
    wp_enqueue_script('postero-child-custom-js', get_stylesheet_directory_uri() . '/assets/js/custom.js', array('jquery'), $af_js_ver, true);
    // nl_nonce is for the home-page pop-up's email field, which belongs to a
    // plugin and shipped with no form, no name and no handler — DEF-01, and
    // measured: pressing its button sent nothing at all. custom.js now posts
    // it to af_nl_subscribe(), the same endpoint the footer form uses, and
    // that endpoint checks this nonce. Baked into the page like the footer
    // form's own data-nonce, with the same known limit: the page is cached
    // and a nonce lasts a day, so a copy older than that fails the check.
    // The handler says so to the visitor rather than failing silently.
    wp_localize_script('postero-child-custom-js', 'af_ajax', array(
        'url'      => admin_url('admin-ajax.php'),
        'nl_nonce' => wp_create_nonce('af_nl_subscribe'),
    ));

    // Checkout-only form styling — kept out of custom.css so the other
    // pages don't carry it.
    if (function_exists('is_checkout') && is_checkout()) {
        $af_co_ver = @filemtime(get_stylesheet_directory() . '/assets/css/checkout.css') ?: '1.0.0';
        wp_enqueue_style('postero-child-checkout', get_stylesheet_directory_uri() . '/assets/css/checkout.css', array('postero-child-custom'), $af_co_ver);
    }
}, 20);

/**
 * Two plugins are writing 105,000 lines of the same notice into the error log.
 *
 * Measured on the server, 22 Sep 2026:
 *
 *     105,043 of 105,280 lines
 *
 * — 99.8% of wp-content/debug.log is _load_textdomain_just_in_time, twice per
 * request, one each for woo-smart-wishlist and woo-smart-quick-view. Both load
 * their translations before init, which WordPress has warned about since 6.7.
 *
 * That is not a cosmetic problem. It is two disk writes on every single page
 * view, on a box measuring a load average of 27 to 36, and it is the reason
 * the log was useless this morning: the shop returned HTTP 500 on every
 * uncached render for half an hour and the last twenty-five lines of the log
 * were all this, from the previous twelve seconds. Whatever the outage wrote,
 * if it wrote anything, was buried under a notice about translation timing.
 *
 * WordPress asks before it writes: _doing_it_wrong() passes the decision
 * through this filter first. Returning false for these two domains stops the
 * write without touching WP_DEBUG, so a real error still reaches the log.
 *
 * Deliberately named, not blanket-suppressed. This notice is a genuine warning
 * and another plugin making the same mistake should still show up; these two
 * are silenced because they are known, constant, and drowning everything else.
 * If either plugin fixes its loading order, this quietly becomes a no-op.
 *
 * What it does NOT do is fix the plugins. They still load translations too
 * early; that is theirs to fix, and this only stops them writing about it.
 *
 * One caveat, stated because it is not yet measured: a child theme loads after
 * the plugin files and after plugins_loaded. If either plugin triggers its
 * first translation call before that, this filter is registered too late to
 * catch it and the count will keep climbing. The health-check workflow prints
 * that count, so the next run says plainly whether this worked rather than
 * leaving it to be assumed.
 */
add_filter('doing_it_wrong_trigger_error', function ($trigger, $function_name, $message, $version) {
    if ($function_name !== '_load_textdomain_just_in_time') return $trigger;
    foreach (array('woo-smart-wishlist', 'woo-smart-quick-view') as $af_noisy_domain) {
        // The domain arrives wrapped in <code> tags; match the tag boundaries
        // rather than the bare name, so a domain that merely contains one of
        // these as a substring is not silenced with it.
        if (strpos($message, '>' . $af_noisy_domain . '<') !== false) return false;
    }
    return $trigger;
}, 10, 4);

/**
 * The mtime versioning above assumes the browser is asked for our file. It is
 * not.
 *
 * Measured on the live home page, 21 Sep 2026: 91 stylesheets, and not one of
 * them is ours. What is served is
 *
 *     /wp-content/litespeed/css/814e966d8b82a0bc599e087d4c…
 *     /wp-content/litespeed/css/31a0ace210f48c2c5c5d466168…
 *
 * — LiteSpeed's combined bundles. Our filename and its ?ver never reach the
 * browser, so the comment above is true of a request nobody makes.
 *
 * What that cost: the checkout error colour measured 8.39:1 at 12:31 and
 * 1.09:1 at 12:36 on the same deployed code, because the two page loads were
 * served different cached bundles. Not slow to arrive — intermittent. Some
 * visitors get the fix and some do not, which is worse than either, and it
 * made a deployed, correct change look like a failed one.
 *
 * So the bundles are purged when, and only when, our CSS actually changes.
 * The stamp is written BEFORE the purge fires: if a purge throws, or these
 * hooks do not exist because LiteSpeed is gone, this must not retry on every
 * request forever. An unknown hook name is a no-op in WordPress, so naming
 * three costs nothing and covers LiteSpeed's separate CSS/JS, critical-CSS
 * and unused-CSS stores.
 *
 * Deliberately not litespeed_purge_all: that takes the page cache with it and
 * leaves the whole shop cold on every deploy. If a verification run still
 * reads a stale bundle after this, the page cache is the next thing to try —
 * measured, not assumed.
 */
/**
 * Keep our two stylesheets out of the combiner entirely.
 *
 * The purge below was the first answer and it did not work: the bundle hashes
 * measured byte-identical across three runs either side of it
 * (814e966d…, 31a0ace2…, 75dc67bb…), and H-04 and M-07 stayed exactly as they
 * were. The reason is that purging the CSS store does not touch the cached
 * HTML, and the cached HTML is what names the bundle. New bundles, nobody
 * pointed at them.
 *
 * Purging the page cache too would work and would empty it on every deploy,
 * leaving the first visitor to each page on a shop that measures seven to
 * eight seconds uncached. That is a permanent cost for an occasional problem.
 *
 * Excluded, our files are served under their own names with the filemtime
 * ?ver at the top of this file — which is what that versioning was written
 * for and has never once been able to do. Two extra requests per page, on a
 * home page that currently makes 404 of them and wastes 14 on missing videos.
 *
 * Both markers and both option names are set on purpose: LiteSpeed reads
 * data-no-optimize, Autoptimize reads data-noptimize, and the option id has
 * moved between LiteSpeed versions. A filter nothing listens to is a no-op,
 * so covering the variants costs nothing and the verification run reports
 * which one landed — DEPLOY-CSS says whether our files are linked at all.
 */
add_filter('style_loader_tag', function ($tag, $handle) {
    if (!in_array($handle, array('postero-child-custom', 'postero-child-checkout'), true)) return $tag;
    if (strpos($tag, 'data-no-optimize') !== false) return $tag;
    return str_replace('<link ', '<link data-no-optimize="1" data-noptimize="1" ', $tag);
}, 10, 2);

foreach (array('litespeed_conf_optm-css_exc', 'litespeed_option_optm-css_exc', 'litespeed_optm_css_exc') as $af_exc_filter) {
    add_filter($af_exc_filter, function ($val) {
        $ours = array('assets/css/custom.css', 'assets/css/checkout.css');
        if (is_array($val))  return array_values(array_unique(array_merge($val, $ours)));
        if (is_string($val)) return trim($val . "\n" . implode("\n", $ours));
        return $val;
    });
}

/**
 * Put our files' ?ver back after LiteSpeed strips it.
 *
 * Measured 23 Sep. LiteSpeed's "remove query strings" is on (optm-qs_rm 1,
 * although it was set to 0 on 18 Sep). At priority 999 on style_loader_src and
 * script_loader_src it deletes the whole query from every internal .css/.js
 * URL, so custom.css and checkout.css were linked with no version at all. The
 * Hostinger CDN (server: hcdn) in front of the origin answers that URL for a
 * year (max-age=31536000). All twelve requests for checkout.css returned the
 * 17,674-byte copy from 21 Sep, 27 hours old, while the file on disk, reached
 * with a cache-buster, was the 22,056 bytes that include DEF-16. A deploy
 * clears LiteSpeed's cache, not the CDN's, and a URL that never changes is
 * never fetched again. So a CSS fix could deploy and not reach the page.
 *
 * The 22 Sep answer copied each file to a stamped filename, and it took the
 * shop down on every uncached render until it was reverted. This one touches
 * no files. It runs after LiteSpeed and puts back the version WordPress
 * registered (the file's mtime, above), so every deploy is a URL the CDN has
 * never seen. Only this theme's own files, and only when the version is
 * missing. It works whatever the setting is: turning query stripping off
 * makes it a no-op. Any failure returns the URL exactly as it came.
 */
function af_keep_asset_version($src, $handle = '') {
    try {
        if (!is_string($src) || $src === '' || !is_string($handle) || $handle === '') return $src;
        if (preg_match('/[?&]ver=/', $src)) return $src;
        if (strpos($src, '/wp-content/themes/' . get_stylesheet() . '/') === false) return $src;
        $deps = (current_filter() === 'script_loader_src') ? wp_scripts() : wp_styles();
        $dep  = (is_object($deps) && isset($deps->registered[$handle])) ? $deps->registered[$handle] : null;
        $ver  = $dep ? $dep->ver : null;
        if (!is_scalar($ver) || $ver === '' || $ver === false) return $src;
        return add_query_arg('ver', rawurlencode((string) $ver), $src);
    } catch (\Throwable $e) {
        return $src;
    }
}
add_filter('style_loader_src', 'af_keep_asset_version', PHP_INT_MAX, 2);
add_filter('script_loader_src', 'af_keep_asset_version', PHP_INT_MAX, 2);

add_action('wp_loaded', function () {
    if (wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) return;
    // functions.php is in the stamp, and it is the reason this is being
    // rewritten. The CSS exclusion shipped in a commit that changed only PHP,
    // so the three CSS mtimes were identical, the stamp did not move, and no
    // purge fired. LiteSpeed went on serving its minified copy of custom.css
    // from before the focus ring existed — measured at 122,417 bytes against
    // the 129,901 on disk, with the rule absent from the served file and
    // present in the deployed one.
    //
    // Any deploy can change what the optimiser ought to re-read, because a
    // PHP change can alter which stylesheets are enqueued or how. Stamping
    // the whole set costs one more stat call and removes a class of failure
    // that took four rounds to see.
    $dir = get_stylesheet_directory();
    $stamp = md5(
        (int) @filemtime($dir . '/assets/css/custom.css') . '|' .
        (int) @filemtime($dir . '/assets/css/checkout.css') . '|' .
        (int) @filemtime($dir . '/style.css') . '|' .
        (int) @filemtime($dir . '/functions.php')
    );
    if (get_option('af_css_stamp') === $stamp) return;
    update_option('af_css_stamp', $stamp);
    do_action('litespeed_purge_all_cssjs');
    do_action('litespeed_purge_all_ccss');
    do_action('litespeed_purge_all_ucss');
}, 20);

// 1b. Tag Sign Up / Login / user-icon nav items with CSS classes (server-side, reliable)
add_filter('nav_menu_css_class', function($classes, $item) {
    if (is_user_logged_in()) return $classes;
    $title = strtolower(trim(strip_tags($item->title)));
    $url   = strtolower($item->url);
    if (preg_match('/sign.?up|register/i', $title)) {
        $classes[] = 'af-nav-signup';
        $classes[] = 'af-nav-acc-hide';
    } elseif (preg_match('/^log\s*in$/i', $title) || preg_match('/^login$/i', $title)) {
        $classes[] = 'af-nav-login';
        $classes[] = 'af-nav-acc-hide';
    } elseif (preg_match('/my-account|account/i', $url) && !preg_match('/sign|register|login/i', $title . $url)) {
        $classes[] = 'af-nav-user-icon';
    } elseif (preg_match('/account|user|person/i', $title) && !preg_match('/sign|register|login/i', $title)) {
        $classes[] = 'af-nav-user-icon';
    }
    return $classes;
}, 10, 2);

// 2. Force USD as default currency — override currency switcher plugins
/**
 * Storefront currencies (spec: USD + CAD). The site once geo-switched visitors
 * to INR, so anything outside this whitelist is still forced back to USD.
 */
function af_allowed_currencies() { return array('USD', 'CAD'); }

function af_currency_symbol_for($code) { return $code === 'CAD' ? 'CA$' : '$'; }

/**
 * The plain-English name of each currency. "CA$ CAD" is two codes and no
 * words — it tells a shopper what the symbol is and what the code is, but
 * never what the money is. The switcher spells it out instead.
 */
function af_currency_name_for($code) {
    $names = array('USD' => 'US Dollar', 'CAD' => 'Canadian Dollar');
    return isset($names[$code]) ? $names[$code] : $code;
}

/**
 * US dollars to the shopper's currency, at the rate the currency plugin
 * (FOX / WOOCS) converts the cart with. 1 for USD. The product page's live
 * price is worked out in the browser from the US price list, and it printed
 * those US figures behind a "CA$" sign: CA$80.00 on the page, CA$109.60 in
 * the cart (site test, 30 Sep).
 */
function af_fx_rate() {
    $cur = af_active_currency();
    if ($cur === 'USD') return 1.0;
    global $WOOCS;
    if (is_object($WOOCS) && method_exists($WOOCS, 'get_currencies')) {
        $all = $WOOCS->get_currencies();
        if (isset($all[$cur]['rate']) && (float) $all[$cur]['rate'] > 0) return (float) $all[$cur]['rate'];
    }
    return 1.0;
}

function af_active_currency() {
    static $cur = null;
    if ($cur !== null) return $cur;
    if (defined('AF_WOOCS_PORT_FAILED')) return $cur = 'USD'; // nothing converts: inc/ports/woocs/failed.php
    $allowed = af_allowed_currencies();
    $c = '';
    if (isset($_GET['currency'])) {
        $c = strtoupper(sanitize_text_field(wp_unslash($_GET['currency'])));
    } elseif (isset($_COOKIE['woocs_current_currency'])) {
        $c = strtoupper(sanitize_text_field(wp_unslash($_COOKIE['woocs_current_currency'])));
    } elseif (isset($_COOKIE['woocs_session_currency'])) {
        $c = strtoupper(sanitize_text_field(wp_unslash($_COOKIE['woocs_session_currency'])));
    }
    $cur = in_array($c, $allowed, true) ? $c : 'USD';
    return $cur;
}

add_filter('woocommerce_currency', function() { return af_active_currency(); }, 9999);
add_filter('woocommerce_currency_symbol', function($symbol, $currency) { return af_currency_symbol_for($currency); }, 9999, 2);

// Redirect any ?currency= outside the whitelist (e.g. INR) back to USD
add_action('template_redirect', function() {
    if (isset($_GET['currency']) && (!is_string($_GET['currency']) || !in_array(strtoupper($_GET['currency']), af_allowed_currencies(), true))) {
        // Same-site only, and never "//other.host": the target is built from
        // this site's address and the request's path (security audit, 12 Oct).
        $path = '/' . ltrim((string) wp_parse_url(isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH), '/');
        $args = $_GET;
        unset($args['currency']);
        $args = array_filter($args, 'is_string');
        $args['currency'] = 'USD';
        wp_safe_redirect(add_query_arg(array_map('rawurlencode', $args), home_url($path)), 302);
        exit;
    }
});

// DEF-13. One currency plugin runs here: FOX / WOOCS (woocommerce-currency-
// switcher 1.4.9), which converts CAD. Measured on the server: it is the only
// currency plugin installed, and no wmc_* option exists. The Multi Currency
// shims that stood here, and the four cookies written for it (wmc_current_
// currency, wmc-currency, currency, chosen_currency), served a plugin that is
// not there. Nothing reads those cookies; only the two woocs_* ones are read,
// by WOOCS and by af_active_currency() above. So only those are written, and
// the four are expired in the <head> script below for visitors who have them.
add_action('init', function() {
    // Fix WOOCS plugin database default
    $woocs_def = get_option('woocs_default_currency', '');
    if ($woocs_def && $woocs_def !== 'USD') {
        update_option('woocs_default_currency', 'USD');
    }

    // Pin all currency cookies to the visitor's chosen whitelist currency
    // (USD default, CAD allowed). Anything else — e.g. geo-set INR — is reset.
    $active = af_active_currency();
    $exp  = time() + 86400 * 365;
    $path = COOKIEPATH ?: '/';
    $host = COOKIE_DOMAIN ?: '';
    foreach (['woocs_session_currency'] as $name) {   // woocs_current_currency: see 10a-2
        if (!isset($_COOKIE[$name]) || $_COOKIE[$name] !== $active) {
            setcookie($name, $active, $exp, $path, $host);
            $_COOKIE[$name] = $active;
        }
    }
    // WPML / WCML
    if (defined('WCML_VERSION')) {
        add_filter('wcml_client_currency', function() { return af_active_currency(); }, 9999);
    }
}, 1);

// Override any non-whitelisted currency stored in WC session
add_action('woocommerce_init', function() {
    if (!WC()->session) return;
    $sess_cur = WC()->session->get('currency');
    $active   = af_active_currency();
    if ($sess_cur && $sess_cur !== $active) {
        WC()->session->set('currency', $active);
    }
}, 9999);

// Symbol before number, no space; currency comes from the whitelist helper
add_filter('woocommerce_price_format', function() { return '%1$s%2$s'; }, 9999);
add_filter('woocommerce_currency_pos', function() { return 'left'; }, 9999);
add_filter('woocommerce_price_args', function($args) {
    $args['currency'] = af_active_currency();
    $args['currency_pos'] = 'left';
    return $args;
}, 9999);

// Immediately fix navbar currency display before page renders
add_action('wp_head', function() { ?>
<script>
(function(){
  // Set cookies immediately — before any plugin JS reads them.
  // "cur" is the server-validated choice: USD default, CAD allowed.
  var cur = '<?php echo esc_js(af_active_currency()); ?>';
  var opts = '; path=/; max-age=' + (86400 * 365);
  document.cookie = 'woocs_session_currency=' + cur + opts;
  document.cookie = 'woocs_current_currency=' + cur + opts;
  // DEF-13: these four were written for a currency plugin that is not
  // installed, and read by nothing. Expire them where a visitor still has one.
  ['wmc_current_currency', 'wmc-currency', 'currency', 'chosen_currency'].forEach(function(n){
    if (('; ' + document.cookie).indexOf('; ' + n + '=') !== -1) document.cookie = n + '=; path=/; max-age=0';
  });

  // Select the active currency in dropdowns without removing other options
  function fixNavCurrency() {
    // Fix <select> dropdowns — just change selected value, keep all options intact
    document.querySelectorAll('select[name*="currency"], select[id*="currency"], select[class*="currency"]').forEach(function(sel) {
      var opt = Array.from(sel.options).find(function(o) { return o.value === cur || o.text.indexOf(cur) !== -1; });
      if (opt) { sel.value = opt.value; opt.selected = true; }
    });
    // Fix the visible label/button text (not inside <select>) — replace only the display label
    document.querySelectorAll(
      '.currency-switcher > span, .wmc-currency-wrapper > span, ' +
      '[class*="woocs"] .selected-currency, [class*="woocs"] > span, ' +
      '.wmc-switcher .current, [class*="wmc-cur"] .current'
    ).forEach(function(el) {
      if (el.children.length === 0 && (el.textContent.indexOf('INR') !== -1 || el.textContent.indexOf('₹') !== -1)) {
        el.textContent = el.textContent.replace(/₹\s*/g, '$ ').replace(/INR/g, 'USD');
      }
    });
  }
  document.addEventListener('DOMContentLoaded', fixNavCurrency);
  window.addEventListener('load', fixNavCurrency);
  // Run once immediately in case DOM is already ready
  if (document.readyState !== 'loading') fixNavCurrency();
})();
</script>
<?php }, 1);

// 3. Enable WooCommerce registration
add_action('init', function() {
    update_option('woocommerce_enable_myaccount_registration', 'yes');
    update_option('woocommerce_enable_checkout_login_reminder', 'yes');
    update_option('woocommerce_registration_generate_password', 'yes');
    update_option('woocommerce_registration_generate_username', 'no');
});

// NOTE: previous top-level delete_transient('af_yt_*') calls ran on EVERY
// request (not just deploys), nuking the feed cache and getting the server
// rate-limited by YouTube. Removed — the transient's own TTL handles freshness.

// 4. AJAX proxy: fetch YouTube playlist/channel RSS — no API key needed
add_action('wp_ajax_af_yt_feed',        'af_yt_feed_handler');
add_action('wp_ajax_nopriv_af_yt_feed', 'af_yt_feed_handler');
function af_yt_feed_handler() {
    // Rate limit: 30 requests/hour per IP — this endpoint is nopriv and each
    // distinct id writes a transient, so cap it to prevent DB/cache bloat.
    $af_yt_ip = af_visitor_ip();
    $af_yt_rl = 'af_yt_rl_' . md5($af_yt_ip);
    $af_yt_n  = (int) get_transient($af_yt_rl);
    if ($af_yt_n >= 30) { wp_send_json_error('rate limited'); return; }
    set_transient($af_yt_rl, $af_yt_n + 1, HOUR_IN_SECONDS);

    $list_id    = sanitize_text_field($_GET['list']    ?? '');
    $channel_id = sanitize_text_field($_GET['channel'] ?? '');

    // Whitelist the YouTube id charset. Blocks query-string injection into the
    // upstream feed URL and stops attacker-chosen transient cache keys.
    if ($list_id !== ''    && !preg_match('/^[A-Za-z0-9_-]{10,64}$/', $list_id))    { wp_send_json_error('bad id'); return; }
    if ($channel_id !== '' && !preg_match('/^[A-Za-z0-9_-]{10,64}$/', $channel_id)) { wp_send_json_error('bad id'); return; }

    if ($list_id) {
        $url = 'https://www.youtube.com/feeds/videos.xml?playlist_id=' . $list_id;
    } elseif ($channel_id) {
        $url = 'https://www.youtube.com/feeds/videos.xml?channel_id=' . $channel_id;
    } else {
        wp_send_json_error('no id'); return;
    }

    $cached = get_transient('af_yt_' . ($list_id ?: $channel_id));
    if ($cached) { wp_send_json_success($cached); return; }

    $af_yt_key = $list_id ?: $channel_id;
    $resp = wp_remote_get($url, ['timeout' => 10]);
    $af_yt_body = is_wp_error($resp) ? '' : wp_remote_retrieve_body($resp);
    $xml = $af_yt_body ? simplexml_load_string($af_yt_body) : false;
    if (!$xml) {
        // YouTube failed/blocked us — serve the last-known-good list so the
        // section never collapses to a single video.
        $fallback = get_option('af_yt_lastgood_' . $af_yt_key);
        if (is_array($fallback) && $fallback) { wp_send_json_success($fallback); return; }
        wp_send_json_error(is_wp_error($resp) ? $resp->get_error_message() : 'bad xml'); return;
    }

    $xml->registerXPathNamespace('yt', 'http://www.youtube.com/xml/schemas/2015');
    $xml->registerXPathNamespace('media', 'http://search.yahoo.com/mrss/');

    $videos = [];
    foreach ($xml->entry as $entry) {
        $yt    = $entry->children('http://www.youtube.com/xml/schemas/2015');
        $media = $entry->children('http://search.yahoo.com/mrss/');
        $vid   = (string)($yt->videoId ?? '');
        if (!$vid) {
            // fallback: parse from <id> tag like yt:video:VIDEOID
            preg_match('/video:([A-Za-z0-9_-]{11})/', (string)$entry->id, $m);
            $vid = $m[1] ?? '';
        }
        if ($vid) {
            $videos[] = [
                'id'    => $vid,
                'title' => (string)($entry->title ?? $media->group->title ?? ''),
                'thumb' => 'https://img.youtube.com/vi/' . $vid . '/hqdefault.jpg',
            ];
        }
    }

    if ($videos) {
        set_transient('af_yt_' . $af_yt_key, $videos, 2 * HOUR_IN_SECONDS);
        update_option('af_yt_lastgood_' . $af_yt_key, $videos, false); // durable fallback
    } else {
        $fallback = get_option('af_yt_lastgood_' . $af_yt_key);
        if (is_array($fallback) && $fallback) { wp_send_json_success($fallback); return; }
    }
    wp_send_json_success($videos);
}


// 4. Fix Login and Register page URLs
add_filter('login_url', function() { return home_url('/my-account/'); });
add_filter('register_url', function() { return home_url('/my-account/?action=register'); });
add_filter('logout_url', function() { return home_url('/my-account/'); });

// Add body class for front page (works for both static and blog front page)
add_filter('body_class', function($classes) {
    if (is_front_page()) $classes[] = 'af-front-page';
    return $classes;
});

// 5a. Force Elementor footer template to show on all pages (not just home)
add_action('init', function() {
    if (!class_exists('\Elementor\Plugin')) return;
    $done = get_option('af_footer_condition_fixed', false);
    if ($done) return;

    $footers = get_posts([
        'post_type'      => 'elementor_library',
        'posts_per_page' => -1,
        'meta_query'     => [[
            'key'   => '_elementor_template_type',
            'value' => 'footer',
        ]],
    ]);

    foreach ($footers as $footer) {
        $conditions = get_post_meta($footer->ID, '_elementor_conditions', true);
        if (!is_array($conditions)) $conditions = [];
        // Check if "entire site" condition already exists
        $has_global = in_array('include/general//', $conditions, true);
        if (!$has_global) {
            // Replace any narrow conditions with "entire site"
            $new_conditions = ['include/general//'];
            // Keep any explicit exclude conditions
            foreach ($conditions as $c) {
                if (strpos($c, 'exclude') === 0) $new_conditions[] = $c;
            }
            update_post_meta($footer->ID, '_elementor_conditions', $new_conditions);
        }
    }

    // Clear Elementor conditions cache so the change takes effect immediately
    delete_option('elementor_pro_theme_builder_conditions');
    if (class_exists('\ElementorPro\Modules\ThemeBuilder\Classes\Conditions_Manager')) {
        delete_transient('elementor_pro_conditions_cache');
    }
    update_option('af_footer_condition_fixed', true);
}, 20);

// 5. Fix footer demo links
add_filter('wp_nav_menu_items', function($items, $args) {
    $items = str_replace('demo2wpopal.b-cdn.net/postero', 'theartframer.us', $items);
    $items = str_replace('chocolate-chicken-365829.hostingersite.com', 'theartframer.us', $items);
    return $items;
}, 10, 2);

// 6. Enqueue WooCommerce price slider scripts
add_action('wp_enqueue_scripts', function() {
    if (is_shop() || is_product_category() || is_product_tag()) {
        wp_enqueue_script('wc-price-slider');
    }
});

// 7. Fix price filter button
add_action('wp_footer', function() {
    if (is_shop() || is_product_category()) { ?>
    <script>
    jQuery(document).ready(function($) {
        $(document).on('click', '.price_slider_wrapper .button, .widget_price_filter .button', function(e) {
            e.preventDefault();
            var form = $(this).closest('form');
            if (form.length) { form.submit(); }
            else {
                var url = new URL(window.location.href);
                var minPrice = $('.price_slider_amount #min_price').val();
                var maxPrice = $('.price_slider_amount #max_price').val();
                if (minPrice) url.searchParams.set('min_price', minPrice);
                if (maxPrice) url.searchParams.set('max_price', maxPrice);
                window.location.href = url.toString();
            }
        });
    });
    </script>
    <?php }
});

// 8. Hide Themes widget from sidebar only
add_action('wp_head', function() {
    echo '<style>
    .widget_postero_product_themes { display: none !important; }
    </style>';
}, 999);

// 8b. Trim the sidebar "Categories" widget — hide empty categories so the list
//     isn't the entire 90-item site tree (functional cleanup on archives).
add_filter('woocommerce_product_categories_widget_args', function($args){
    $args['hide_empty'] = 1;
    return $args;
});

// 9. Subcategory circles fix
add_action('wp_footer', function() { ?>
<style id="artframer-subcat-override">
/* Target the actual subcategory slider container */
#subcategorySlider,
.subcategory-slider {
    display: flex !important;
    flex-direction: row !important;
    flex-wrap: nowrap !important;
    overflow-x: auto !important;
    overflow-y: visible !important;
    gap: 16px !important;
    width: 100% !important;
    height: auto !important;
    max-height: none !important;
    padding: 4px 0 8px !important;
    scrollbar-width: none !important;
    -ms-overflow-style: none !important;
    grid-template-columns: unset !important;
    grid-template-rows: unset !important;
}
#subcategorySlider::-webkit-scrollbar,
.subcategory-slider::-webkit-scrollbar { display: none !important; }
#subcategorySlider > *,
.subcategory-slider > * {
    flex: 0 0 auto !important;
    width: auto !important;
    display: flex !important;
    flex-direction: column !important;
    align-items: center !important;
    position: static !important;
    transform: none !important;
}
#subcategoryContainer,
.subcategory-container {
    overflow: visible !important;
    height: auto !important;
    max-height: none !important;
    width: 100% !important;
}
/* Also fix the UL-based version */
ul.postero-scroll-content {
    display: flex !important;
    flex-direction: row !important;
    flex-wrap: nowrap !important;
    overflow-x: auto !important;
    height: auto !important;
    max-height: none !important;
    transform: none !important;
    scrollbar-width: none !important;
}
ul.postero-scroll-content > li.cat-item {
    flex: 0 0 auto !important;
    position: static !important;
    transform: none !important;
    width: auto !important;
}
div.list-wrapper.postero-scroll {
    overflow: visible !important;
    height: auto !important;
    max-height: none !important;
}
</style>
<script>
(function() {
    var _busy = false;
    var _fixedEl = null;

    // Fixed-width columns — matches the breakpoints in assets/css/custom.css —
    // so every subcategory item is the same size regardless of its label
    // length. Must be applied here (not left to the stylesheet alone):
    // this script previously set width:auto inline with !important, and an
    // inline !important always wins over an external stylesheet's
    // !important, so the stylesheet's fixed-width rule was silently losing.
    function itemWidth() {
        var w = window.innerWidth;
        if (w <= 576) return 72;
        if (w <= 991) return 78;
        return 92;
    }

    function fixEl(el) {
        if (!el) return;
        _busy = true;
        _fixedEl = el;
        el.style.setProperty('display',               'flex',    'important');
        el.style.setProperty('flex-direction',        'row',     'important');
        el.style.setProperty('flex-wrap',             'nowrap',  'important');
        el.style.setProperty('overflow-x',            'auto',    'important');
        el.style.setProperty('height',                'auto',    'important');
        el.style.setProperty('max-height',            'none',    'important');
        el.style.setProperty('width',                 '100%',    'important');
        el.style.setProperty('grid-template-columns', 'unset',   'important');
        el.style.setProperty('grid-template-rows',    'unset',   'important');
        var w = itemWidth() + 'px';
        Array.from(el.children).forEach(function(child) {
            child.style.setProperty('flex',      '0 0 ' + w, 'important');
            child.style.setProperty('position',  'static',   'important');
            child.style.setProperty('width',     w,          'important');
            child.style.setProperty('transform', 'none',     'important');
        });
        if (el.parentElement) {
            el.parentElement.style.setProperty('overflow',   'visible', 'important');
            el.parentElement.style.setProperty('height',     'auto',    'important');
            el.parentElement.style.setProperty('max-height', 'none',    'important');
        }
        setTimeout(function() { _busy = false; }, 50);
    }

    var _initDone = false;
    function init() {
        if (_initDone) return;
        // Primary target: #subcategorySlider (confirmed display:grid, the actual circles container)
        var slider = document.getElementById('subcategorySlider');
        if (slider) { fixEl(slider); _initDone = true; }

        // Fallback: UL with cat-items
        var catItems = document.querySelectorAll('li.cat-item');
        if (catItems.length && catItems[0].parentElement) {
            fixEl(catItems[0].parentElement);
            _initDone = true;
        }
        if (!_initDone) return; // nothing found yet, allow retry

        // MutationObserver — skip re-entry while we are mid-fix
        var mo = new MutationObserver(function(mutations) {
            if (_busy) return;
            var relevant = mutations.some(function(m) {
                return m.target === slider || (slider && slider.contains(m.target));
            });
            if (relevant) fixEl(slider);
        });
        if (slider) {
            mo.observe(slider, { attributes: true, attributeFilter: ['style', 'class'] });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    window.addEventListener('load', function() {
        init();
        setTimeout(init, 300);
        setTimeout(init, 1000);
    });

    // Keep the item width current across breakpoint changes (e.g. rotating
    // a tablet, resizing a desktop browser window).
    var _resizeTimer = null;
    window.addEventListener('resize', function() {
        clearTimeout(_resizeTimer);
        _resizeTimer = setTimeout(function() { if (_fixedEl) fixEl(_fixedEl); }, 150);
    });
}());
</script>
<?php }, 9999);


// 9b. Collection circles: the child theme owns the click (2026-08-13).
// History: an Aug-11 capture-phase interceptor swallowed circle clicks; the
// Aug-12 restore (bee70f9) removed the child's wiring entirely, betting the
// parent theme's own handler would take the clicks back. The Aug-12 server
// diagnostics disprove that bet: the circles are a.pf-value anchors produced
// by the parent's category walker (inc/woocommerce/woocommerce-template-
// functions.php), its load_products AJAX endpoint is registered and answers
// with product cards — but NO code in the parent theme or any plugin binds a
// click to the circles ("no direct click binding found by pattern",
// diag-pf-handler.php / diag-subcat-click.php, deploy runs 31599177961 and
// 31602796393). The endpoint is an orphan; with the child wiring gone every
// circle click has been dead, which is exactly what the owner's Aug-13
// screen recording shows. So the child owns the interaction, with three
// guarantees:
//   1. instant response — the clicked circle is marked active and the
//      visible product area dims while products load;
//   2. products swap in place through the theme's own contract
//      (POST action=load_products&subcategory=<data-val slug>);
//   3. never a dead click — if the request fails or answers without cards,
//      the browser navigates to the circle's real category archive URL.
// Delegated from document (bubble phase), so circles re-rendered later by
// tab switches or AJAX are covered without re-wiring, and the drag guard in
// assets/js/custom.js still cancels post-drag clicks before they get here.
// Handles every strip variant the theme renders: li.cat-item > a.pf-value
// (data-val + real href), and the .sub-cat <img>+<span> circles (no anchor —
// resolved by caption against the category map below).
add_action('wp_footer', function() {
  if (is_admin()) return;
  $terms = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false));
  if (is_wp_error($terms) || !$terms) return;
  $map = array();
  foreach ($terms as $t) {
    $link = get_term_link($t);
    if (is_wp_error($link)) continue;
    $key = strtolower(preg_replace('/[^a-z0-9]+/i', '', html_entity_decode($t->name)));
    if ($key !== '') $map[$key] = array('u' => $link, 's' => $t->slug);
  }
  if (!$map) return;
  ?>
<style id="af-circle-active">
/* The chosen circle keeps a visible gold ring until another is chosen */
#subcategorySlider .active img, .subcategory-slider .active img,
li.cat-item.active img, .sub-cat.active img,
ul.postero-scroll-content li.cat-item.active img {
    outline: 3px solid #c9a84c !important;
    outline-offset: 2px !important;
    border-radius: 50% !important;
}
</style>
<script>
(function(){
  var AJAX = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
  var CATS = <?php echo wp_json_encode($map); ?>;
  function dbg(m){ if (window.afdbg) window.afdbg(m); }
  function norm(s){ return (s||'').toLowerCase().replace(/[^a-z0-9]+/g,''); }

  var STRIPS   = '#subcategorySlider, .subcategory-slider, ul.postero-scroll-content, .subcategory-container';
  var ITEM     = 'a.pf-value, li.cat-item, .sub-cat';
  var GRID_SEL = '#productGrid, .product-slider, .custom-product-track, ul.products';

  // Resolve which category a circle stands for: trust data-val (the exact
  // slug load_products expects) and the anchor's real href when present;
  // otherwise match the caption against the category map.
  function catFor(item){
    var a = (item.matches && item.matches('a.pf-value,[data-val]')) ? item
          : (item.querySelector ? item.querySelector('a.pf-value,[data-val]') : null);
    if (a && a.getAttribute('data-val')) {
      var href = (a.tagName === 'A' && a.getAttribute('href')) || '';
      if (!href || href === '#' || href.indexOf('javascript:') === 0) {
        var m = CATS[norm(a.getAttribute('data-title') || a.textContent)];
        href = m ? m.u : '';
      }
      return { s: a.getAttribute('data-val'), u: href };
    }
    var label = norm((item.getAttribute && item.getAttribute('data-title')) || item.textContent);
    if (!label) return null;
    if (CATS[label]) return CATS[label];
    var best = null, bestLen = 0;
    for (var k in CATS) {
      if (k.length > bestLen && label.indexOf(k) !== -1) { best = CATS[k]; bestLen = k.length; }
    }
    return best;
  }

  // The grid to swap is the one in the SAME section as the clicked strip —
  // walking up keeps a grid from an unrelated section out of the swap.
  function gridFor(item){
    for (var node = item; node && node !== document.body; node = node.parentElement) {
      var g = node.querySelector(GRID_SEL);
      if (g && !g.contains(item)) return g;
    }
    return document.querySelector(GRID_SEL);
  }

  // What the visitor actually SEES may be the af-shell carousel built from
  // the (then hidden) grid — dim that, not the hidden element.
  function visibleAreaFor(grid){
    if (grid.classList.contains('af-grid-hidden') && grid.parentNode) {
      var shell = grid.parentNode.querySelector('.af-shell');
      if (shell) return shell;
    }
    return grid;
  }

  function markActive(item){
    var circle = (item.closest && (item.closest('li.cat-item, .sub-cat') || item)) || item;
    var strip = item.closest ? item.closest(STRIPS) : null;
    if (strip) {
      strip.querySelectorAll('.active').forEach(function(x){ x.classList.remove('active'); });
    }
    circle.classList.add('active');
  }

  var busy = false;
  function filterTo(cat, item, e){
    var grid = gridFor(item);
    dbg('click slug=' + cat.s + ' grid=' + (grid ? (grid.id || grid.className || grid.tagName).toString().slice(0,50) : 'NONE'));
    if (!grid) {
      // nothing to filter on this page — the click must still go somewhere
      var a = item.tagName === 'A' ? item : (item.querySelector && item.querySelector('a[href]'));
      if (!a && cat.u) { e.preventDefault(); window.location.href = cat.u; }
      return; // real anchors navigate natively
    }
    e.preventDefault();
    if (busy) return; // one request at a time; the click is already answered
    busy = true;
    markActive(item);

    var area = visibleAreaFor(grid);
    area.style.opacity = '.45';
    // Safety: never leave the page dimmed (shell rebuilds replace the dimmed
    // element on success; this covers a rebuild that never comes)
    var unDim = setTimeout(function(){ area.style.opacity = ''; }, 4000);

    var body = new URLSearchParams();
    body.set('action', 'load_products');
    body.set('subcategory', cat.s);
    fetch(AJAX, { method: 'POST', credentials: 'same-origin',
                  headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                  body: body.toString() })
      .then(function(r){ dbg('ajax http=' + r.status); if (!r.ok) throw new Error('http ' + r.status); return r.text(); })
      .then(function(html){
        var ok = html && (html.indexOf('product-card') !== -1 || html.indexOf('woocommerce-loop-product') !== -1);
        dbg('ajax bytes=' + (html ? html.length : 0) + (ok ? '' : ' (no cards)'));
        busy = false;
        if (!ok) {
          // an empty category reads better on its archive page, which offers
          // similar pieces — and the click still visibly does something
          clearTimeout(unDim); area.style.opacity = '';
          if (cat.u) { window.location.href = cat.u; }
          return;
        }
        grid.innerHTML = html;
        // A visible grid is done now; a shell-backed grid is rebuilt by the
        // slider's own MutationObserver moments later (it replaces the shell).
        if (area === grid) { clearTimeout(unDim); area.style.opacity = ''; }
        document.dispatchEvent(new Event('af_products_appended'));
        if (window.jQuery) jQuery(document.body).trigger('wc_fragments_refreshed');
        dbg('swapped into ' + (grid.id || grid.className || grid.tagName).toString().slice(0,50));
      })
      .catch(function(err){
        dbg('ajax FAILED ' + err);
        busy = false;
        clearTimeout(unDim); area.style.opacity = '';
        if (cat.u) window.location.href = cat.u; // never a dead click
      });
  }

  document.addEventListener('click', function(e){
    if (e.defaultPrevented) return;                 // someone else owns this click
    if (!e.target || !e.target.closest) return;
    var item = e.target.closest(ITEM);
    if (!item || !item.closest(STRIPS)) return;     // only circles inside a strip
    if (e.target.closest('button, .next-circle, .prev-circle, [class*="arrow"]')) return;
    var cat = catFor(item);
    if (!cat || !cat.s) { dbg('click: no category match for "' + (item.textContent||'').trim().slice(0,40) + '"'); return; }
    filterTo(cat, item, e);
  });
})();
</script>
<?php }, 9999);

// 10. Product card slider
add_action('wp_footer', function() { ?>
<style>
.af-shell { box-sizing: border-box; }
.af-shell-btn {
    flex-shrink: 0 !important;
    border-radius: 50% !important;
    background: #c9a84c !important;
    border: none !important;
    color: #fff !important;
    font-size: 28px !important;
    line-height: 1 !important;
    cursor: pointer !important;
    padding: 0 !important;
    box-shadow: 0 2px 8px rgba(0,0,0,.25) !important;
}
.af-shell-btn:hover { background: #a8872e !important; }
.af-shell-track {
    display: flex !important;
    flex-direction: row !important;
    flex-wrap: nowrap !important;
    align-items: stretch !important;  /* all cards same height */
    margin: 0 !important;
    padding: 4px 0 12px !important;
    list-style: none !important;
    transition: transform 0.4s ease !important;
    will-change: transform !important;
}
.af-shell-track .product-card {
    height: 100% !important;  /* fill the stretched row */
}
.af-shell-track .product-card {
    flex-shrink: 0 !important;
    float: none !important;
    margin: 0 !important;
    box-sizing: border-box !important;
}
.af-grid-hidden { display: none !important; }
</style>
<script id="af-features-bar-mobile" src="<?php echo esc_url(af_static_url('features-bar-mobile.js')); ?>" data-no-optimize="1"></script>
<?php }, 10000);

// Product card override — injected late in <head> to beat all theme/plugin CSS
add_action('wp_head', function() { ?>
<link rel="stylesheet" id="af-card-override" href="<?php echo esc_url(af_static_url('card-override.css')); ?>" data-no-optimize="1">
<script>
(function(){
  if (window.innerWidth > 600) return;
  if (document.body && document.body.classList.contains('logged-in')) return;

  function run() {
    if (document.body.classList.contains('logged-in')) return;
    if (document.body.dataset.afDdDone) return;

    var menu   = document.querySelector('.mobile_navbar_menu_dropdown_menu');
    var title  = document.querySelector('.mobile_navbar_menu_dropdown_title');
    var parent = document.querySelector('.mobile_navbar_menu_dropdown_parent');

    if (!menu || !title) return;
    document.body.dataset.afDdDone = '1';

    if (parent) parent.style.setProperty('position', 'relative', 'important');

    var open = false;
    title.addEventListener('click', function(e) {
      e.preventDefault(); e.stopPropagation();
      open = !open;
      menu.classList.toggle('af-dd-open', open);
    });
    document.addEventListener('click', function(e) {
      if (open && menu && !menu.contains(e.target) && !title.contains(e.target)) {
        open = false;
        menu.classList.remove('af-dd-open');
      }
    });
  }

  if (document.readyState !== 'loading') run();
  document.addEventListener('DOMContentLoaded', run);
  window.addEventListener('load', run);
  setTimeout(run, 500);
  setTimeout(run, 1500);
}());
</script>
<?php }, 99);

// Features bar: force inline on mobile
add_action('wp_head', function() { ?>
<style>
@media (max-width: 600px) {
  .features-container {
    display: flex !important;
    flex-direction: row !important;
    flex-wrap: nowrap !important;
    justify-content: space-around !important;
    align-items: flex-start !important;
    width: 100% !important;
    padding: 10px 2px !important;
    box-sizing: border-box !important;
    gap: 0 !important;
  }
  .feature-box {
    display: flex !important;
    flex-direction: column !important;
    align-items: center !important;
    justify-content: flex-start !important;
    flex: 1 1 0 !important;
    min-width: 0 !important;
    max-width: 25% !important;
    text-align: center !important;
    cursor: pointer !important;
    padding: 0 2px !important;
    gap: 0 !important;
  }
  .feature-box .feature-icon {
    width: 50px !important;
    height: 50px !important;
    min-width: 50px !important;
    border-radius: 50% !important;
    background: #c9a84c !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    margin: 0 auto 5px !important;
  }
  .feature-box .feature-icon i,
  .feature-box .feature-icon svg,
  .feature-box .feature-icon img {
    font-size: 20px !important;
    color: #fff !important;
    fill: #fff !important;
    width: 22px !important;
    height: 22px !important;
  }
  .feature-box .feature-title {
    font-size: 9px !important;
    font-weight: 700 !important;
    color: #222 !important;
    line-height: 1.2 !important;
    margin: 0 !important;
    padding: 0 !important;
    word-break: break-word !important;
    hyphens: auto !important;
  }

  /* Google review cards — fit to content height on mobile */
  /* Widget forces height:100% on .g-review — override it */
  #g-review .swiper .swiper-wrapper .swiper-slide .g-review,
  .g-review,
  [class*="g-review"] {
    height: auto !important;
  }
  /* Slider track: don't force equal heights */
  .eapps-google-reviews-list,
  [class*="google-reviews-list"],
  [class*="reviews-list"],
  .swiper-wrapper {
    align-items: flex-start !important;
  }
  .swiper-slide {
    height: auto !important;
  }
  .eapps-google-reviews-list-item,
  .eapps-google-reviews-list .eapps-google-reviews-list-item,
  [class*="google-reviews"] [class*="list-item"],
  [class*="review-card"],
  [class*="ReviewItem"],
  [class*="review_item"] {
    height: auto !important;
    min-height: 0 !important;
    max-height: none !important;
    align-self: flex-start !important;
    overflow: visible !important;
    display: flex !important;
    flex-direction: column !important;
  }
  .eapps-google-reviews-list-item-body,
  [class*="google-reviews"] [class*="body"],
  [class*="ReviewBody"],
  [class*="review_body"] {
    overflow: visible !important;
    max-height: none !important;
    -webkit-line-clamp: unset !important;
    display: block !important;
    flex: 0 0 auto !important;
  }
  .eapps-google-reviews-list-item-text,
  [class*="google-reviews"] [class*="text"],
  [class*="ReviewText"],
  [class*="review_text"] {
    overflow: visible !important;
    white-space: normal !important;
    text-overflow: unset !important;
    -webkit-line-clamp: unset !important;
    display: block !important;
    height: auto !important;
  }
}
</style>
<script id="af-shop-collection-mobile" src="<?php echo esc_url(af_static_url('shop-collection-mobile.js')); ?>" data-no-optimize="1"></script>
<?php }, 99);

/* ============================================================
   SHOP / CATEGORY PAGE — PHP hooks for card buttons & badge
   ============================================================ */

// Inject hidden product data (fires inside the product link, inside the image area)
add_action('woocommerce_before_shop_loop_item_title', function() {
  $product = af_wc_product();
  if (!$product) return;
  $id       = $product->get_id();
  $url      = get_permalink($id);
  $cart_url = esc_url(add_query_arg('add-to-cart', $id, $url));
  $regular  = (float) $product->get_regular_price();
  $sale     = (float) $product->get_sale_price();
  $pct      = ($regular > 0 && $sale > 0 && $sale < $regular)
              ? round(($regular - $sale) / $regular * 100) : 0;
  echo '<span class="af-pd" data-id="' . $id . '" data-url="' . esc_url($url) . '" data-cart="' . $cart_url . '" data-pct="' . $pct . '" style="display:none"></span>';
}, 1);

// ─────────────────────────────────────────────────────────────
// ONE PRICE ROW EVERYWHERE: what you pay, then what it was, then the saving.
//
//     $80.00  $121.21  (34% off)
//
// The site renders product cards in at least five different ways — the shop
// grid, the homepage sliders, Trending Today, the related/wishlist rows and
// Quick View — and each carries its own price markup. Some put the struck
// price first, some omit the percentage entirely, so the same product read
// differently depending on where you met it. The price a customer pays
// should lead every time.
//
// Rows are found by looking for the struck price itself — a <del> or an
// .old-price — and taking its parent, rather than by guessing at class names
// that differ per card. The current price is whatever is left in the row; if
// it is a bare text node with no element around it, one is added, because
// CSS cannot reorder what is not an element.
//
// The cart's own "price before discount" line uses <s>, not <del>, so it is
// untouched — its layout is a table and reordering would wreck it.
// ─────────────────────────────────────────────────────────────
add_action('wp_footer', function () {
    if (is_admin()) return;
    ?>
<script>
(function(){
  function num(t){
    t = (t || '').replace(/[^0-9.,]/g, '').replace(/,/g, '');
    var v = parseFloat(t);
    return isFinite(v) ? v : 0;
  }
  function fix(row){
    if (!row || row.dataset.afPrice) return;
    row.dataset.afPrice = '1';

    var was = row.querySelector(':scope > del, :scope > .old-price');
    if (!was) return;
    var oldV = num(was.textContent);
    if (oldV <= 0) return;

    // The current price: an <ins> when the markup has one, otherwise the
    // text left over once the struck price is set aside. Bare text gets
    // wrapped so it can be ordered and weighted.
    var now = row.querySelector(':scope > ins, :scope > .current-price, :scope > .af-now');
    if (!now) {
      var moved = [], n;
      for (var i = 0; i < row.childNodes.length; i++) {
        n = row.childNodes[i];
        if (n === was || was.contains(n)) continue;
        if (n.nodeType === 3 && !n.nodeValue.trim()) continue;
        // The saving belongs to the row, not inside the pay price. Sweeping
        // the theme's own .discount in here made the wrapper as narrow as
        // "$80.00" and stacked the two inside it — the pay price on one line,
        // the saving on the next, overflowing up into the rating row.
        if (n.nodeType === 1 && n.classList && (
              n.classList.contains('af-pct-off') ||
              n.classList.contains('discount') ||
              n.classList.contains('discount-percentage'))) continue;
        moved.push(n);
      }
      if (!moved.length) return;
      now = document.createElement('span');
      now.className = 'af-now';
      moved[0].parentNode.insertBefore(now, moved[0]);
      moved.forEach(function(x){ now.appendChild(x); });
    }
    var newV = num(now.textContent);
    if (!(newV > 0) || newV >= oldV) return;

    row.classList.add('af-pricerow');

    // one percentage per row — never a second next to the theme's own
    if (!row.querySelector('.af-pct-off, .af-disc-badge, .discount, .discount-percentage')) {
      var b = document.createElement('span');
      b.className = 'af-pct-off';
      b.textContent = '(' + Math.round((oldV - newV) / oldV * 100) + '% off)';
      row.appendChild(b);
    }
  }
  function scan(){
    // find the rows by the struck price, not by a class name that differs
    // between every card variant on the site
    document.querySelectorAll('del, .old-price').forEach(function(el){
      if (el.closest('.af-ct-was')) return;         // the cart totals table
      fix(el.parentElement);
    });
  }
  if (document.readyState !== 'loading') scan();
  else document.addEventListener('DOMContentLoaded', scan);
  window.addEventListener('load', scan);
  var t = null;
  try {
    new MutationObserver(function(){ clearTimeout(t); t = setTimeout(scan, 120); })
      .observe(document.body, { childList:true, subtree:true });
  } catch(e){}
})();
</script>
<style id="af-pricerow-style">
/* Order is stated here rather than in the markup, so it holds however each
   card happens to nest its two prices. */
/* af-pricerow, NOT af-price-row: the listing toolbar's min/max price FILTER
   already owns that name, and its rules (align-items, gap, and a span colour)
   were landing on every card price on the same page. */
.af-pricerow{display:flex !important;align-items:baseline !important;flex-wrap:wrap !important;
  gap:4px 7px !important;margin:0 !important;}
/* Whatever the theme sets on these — display:block, width:100%, a float —
   would put each part on its own line inside a flex row. Neutralise it. */
.af-pricerow > *{flex:0 0 auto !important;width:auto !important;max-width:none !important;
  min-width:0 !important;float:none !important;margin:0 !important;clear:none !important;}
.af-pricerow > ins,.af-pricerow > .af-now,.af-pricerow > .current-price{order:1;
  text-decoration:none !important;font-weight:700;}
.af-pricerow > del,.af-pricerow > .old-price{order:2;opacity:.65;font-weight:400;
  text-decoration:line-through;}
.af-pricerow > .af-pct-off,.af-pricerow > .discount,
.af-pricerow > .discount-percentage{order:3;}
.af-pct-off{color:var(--af-save, #2e7d32);font-weight:700;font-size:.85em;white-space:nowrap;
  text-decoration:none !important;}
/* The pay price is one line, whatever ends up inside it. A card that already
   nests its saving in there (or any theme markup that turns a child into a
   block) would otherwise stack them and grow the wrapper past the row. */
.af-pricerow > .af-now{display:inline-flex !important;flex-direction:row !important;
  flex-wrap:nowrap !important;align-items:baseline !important;gap:6px !important;
  width:auto !important;white-space:nowrap !important;}
.af-pricerow .af-now > *{display:inline !important;width:auto !important;
  max-width:none !important;float:none !important;margin:0 !important;
  padding:0 !important;white-space:nowrap !important;}

/* ONE LOOK FOR EVERY CARD'S PRICE, the Corporate Signages card's (owner's
   screenshot, 24 Sep). Measured live, the other cards each had their own:
     reference        now 14/700 #1a1a1a  was 13/400 #999    off 12/600 green
     shop / wishlist  now 15/700          was 12/400 #6b6b6b off 12.75/700 (10.2 on a phone)
     Trending         now 15/700 #555     was 15/400 #6b6b6b off 12/600
     New Arrivals     now 15/700 #555     was 15/400 #555    off 12.75/700
   Price only: sizes, weights, colours, the gap between them. Tripled class so
   it outranks the per-section rules without touching any of them. */
html body :is(li.product,.product-card,.trending-card,.new-product-card) .af-pricerow.af-pricerow.af-pricerow{
  gap:4px !important;align-items:center !important;}
html body :is(li.product,.product-card,.trending-card,.new-product-card) .af-pricerow.af-pricerow.af-pricerow > :is(ins,.af-now,.current-price),
html body :is(li.product,.product-card,.trending-card,.new-product-card) .af-pricerow.af-pricerow.af-pricerow > :is(ins,.af-now,.current-price) *{
  font-size:14px !important;font-weight:700 !important;color:#1a1a1a !important;
  text-decoration:none !important;opacity:1 !important;}
html body :is(li.product,.product-card,.trending-card,.new-product-card) .af-pricerow.af-pricerow.af-pricerow > :is(del,.old-price),
html body :is(li.product,.product-card,.trending-card,.new-product-card) .af-pricerow.af-pricerow.af-pricerow > :is(del,.old-price) *{
  font-size:13px !important;font-weight:400 !important;color:#999 !important;opacity:1 !important;}
html body :is(li.product,.product-card,.trending-card,.new-product-card) .af-pricerow.af-pricerow.af-pricerow > :is(del,.old-price){
  text-decoration:line-through !important;}
html body :is(li.product,.product-card,.trending-card,.new-product-card) .af-pricerow.af-pricerow.af-pricerow :is(.af-pct-off,.discount,.discount-percentage){
  font-size:12px !important;font-weight:600 !important;color:var(--af-save, #2e7d32) !important;
  text-decoration:none !important;opacity:1 !important;}
</style>
    <?php
}, 41);

// Discount badge (fires after price, inside the product link — span is OK here)
add_action('woocommerce_after_shop_loop_item_title', function() {
  $product = af_wc_product();
  if (!$product || !$product->is_on_sale() || !$product->get_regular_price()) return;
  $regular = (float) $product->get_regular_price();
  $sale    = (float) $product->get_sale_price();
  if ($regular > 0 && $sale < $regular) {
    $pct = round(($regular - $sale) / $regular * 100);
    echo '<span class="af-shop-discount-badge">' . $pct . '% off</span>';
  }
}, 12);


// Features bar: force inline on mobile
add_action('wp_footer', function() { ?>
<script>
(function() {
  // shipping wording comes from af_shipping_copy() so the badge, its popup,
  // the announcement bar and the chatbot always say the same thing
  var AF_SHIP = <?php echo wp_json_encode(af_shipping_copy()); ?>;
  if (window.innerWidth > 600) return;
  var sp = function(el, p, v) { el.style.setProperty(p, v, 'important'); };

  function fixFeaturesBar() {
    // First try: custom HTML widget structure .features-container > .feature-box
    var container = document.querySelector('.features-container');

    // Second try: Elementor structure inside .features-section
    if (!container) {
      var section = document.querySelector('.features-section');
      container = section ? (section.querySelector('.e-con-inner') || section) : null;
    }

    // Third try: .collection-header
    if (!container) {
      var ch = document.querySelector('.collection-header');
      container = ch ? (ch.querySelector('.e-con-inner') || ch) : null;
    }

    // Last resort: find by data-popup attribute
    if (!container) {
      var firstBox = document.querySelector('[data-popup]');
      if (firstBox) container = firstBox.parentElement;
    }
    if (!container || container === document.body) return;
    var alreadyFixed = container.dataset.afFixed === '1';
    container.dataset.afFixed = '1';
    if (alreadyFixed) {
      // Only re-wire clicks on any newly added items, skip style work
      var popupMap2 = { 'shipping': 0, 'resolution': 1, 'frames': 2, 'payment': 3 };
      var labelMap2 = [AF_SHIP.label,'High Resolution','Premium Frames','Secure Payment'];
      Array.from(container.children).forEach(function(item) {
        if (item.dataset.afClick) return;
        item.dataset.afClick = '1';
        var idx = -1;
        var dp = item.getAttribute('data-popup');
        if (dp && popupMap2[dp] !== undefined) idx = popupMap2[dp];
        if (idx === -1) { var txt2 = item.textContent.trim(); labelMap2.forEach(function(l,i){ if(txt2.indexOf(l)!==-1) idx=i; }); }
        if (idx >= 0) { item.style.cursor='pointer'; item.addEventListener('click', function(){ if(window.afOpenSheet) window.afOpenSheet(idx); }); }
      });
      return;
    }

    // Override Elementor CSS custom properties (flex-direction: var(--flex-direction))
    sp(container,'--flex-direction','row');
    sp(container,'--flex-wrap','nowrap');
    sp(container,'--justify-content','space-around');
    sp(container,'--align-items','flex-start');
    // Also override parent section's custom properties
    if (container.parentElement) {
      sp(container.parentElement,'--flex-direction','row');
      sp(container.parentElement,'--flex-wrap','nowrap');
    }

    // Force container to horizontal flex row
    sp(container,'display','flex');
    sp(container,'flex-direction','row');
    sp(container,'flex-wrap','nowrap');
    sp(container,'justify-content','space-around');
    sp(container,'align-items','flex-start');
    sp(container,'width','100%');
    sp(container,'transform','none');
    sp(container,'height','auto');

    // Each child item = one feature column
    Array.from(container.children).forEach(function(item) {
      sp(item,'flex','1 1 0');
      sp(item,'min-width','0');
      sp(item,'max-width','25%');
      sp(item,'display','flex');
      sp(item,'flex-direction','column');
      sp(item,'align-items','center');
      sp(item,'text-align','center');
      sp(item,'padding','6px 2px');
      sp(item,'width','auto');
      sp(item,'height','auto');

      // Drill into any inner wrappers
      item.querySelectorAll('div,span').forEach(function(d) {
        if (d.children.length > 1 || d.querySelector('i,svg,img')) {
          sp(d,'display','flex'); sp(d,'flex-direction','column');
          sp(d,'align-items','center'); sp(d,'text-align','center');
          sp(d,'padding','0'); sp(d,'width','100%');
        }
      });

      // Gold circle around icon
      var icon = item.querySelector('i,svg,img');
      if (icon) {
        var iconWrap = icon.parentElement;
        sp(iconWrap,'width','52px'); sp(iconWrap,'height','52px');
        sp(iconWrap,'border-radius','50%'); sp(iconWrap,'background','#c9a84c');
        sp(iconWrap,'display','flex'); sp(iconWrap,'align-items','center');
        sp(iconWrap,'justify-content','center'); sp(iconWrap,'margin','0 auto 5px');
        sp(iconWrap,'flex-shrink','0');
        sp(icon,'color','#fff'); sp(icon,'fill','#fff');
        sp(icon,'font-size','20px'); sp(icon,'width','20px'); sp(icon,'height','20px');
      }

      // Label — smallest readable size
      var texts = item.querySelectorAll('h1,h2,h3,h4,h5,h6,p,span');
      texts.forEach(function(t) {
        if (t.children.length === 0 && t.textContent.trim().length > 1) {
          sp(t,'font-size','10px'); sp(t,'font-weight','700');
          sp(t,'color','#222'); sp(t,'text-align','center');
          sp(t,'line-height','1.3'); sp(t,'margin','0'); sp(t,'padding','0');
          sp(t,'display','block');
        }
      });
    });

    // Wire click on each item to open the popup — only once per item
    var popupMap = { 'shipping': 0, 'resolution': 1, 'frames': 2, 'payment': 3 };
    var labelMap = [AF_SHIP.label,'High Resolution','Premium Frames','Secure Payment'];
    Array.from(container.children).forEach(function(item) {
      if (item.dataset.afClick) return; // already wired
      item.dataset.afClick = '1';
      var idx = -1;
      // Try data-popup attribute first (e.g. data-popup="shipping")
      var dp = item.getAttribute('data-popup');
      if (dp && popupMap[dp] !== undefined) idx = popupMap[dp];
      // Fallback: match label text
      if (idx === -1) {
        var txt = item.textContent.trim();
        labelMap.forEach(function(l, i) { if (txt.indexOf(l) !== -1) idx = i; });
      }
      if (idx >= 0) {
        sp(item,'cursor','pointer');
        item.addEventListener('click', function() {
          if (window.afOpenSheet) window.afOpenSheet(idx);
        });
      }
    });
  }

  document.addEventListener('DOMContentLoaded', fixFeaturesBar);
  window.addEventListener('load', fixFeaturesBar);
  setTimeout(fixFeaturesBar, 800);
})();
</script>
<?php }, 5);

// Mobile hero slider width fix — force Swiper to recalculate using correct 100vw container
add_action('wp_footer', function() { ?>
<script id="af-hero-slider-mobile" src="<?php echo esc_url(af_static_url('hero-slider-mobile.js')); ?>" data-no-optimize="1"></script>
<?php }, 5);

// 10z. Personalised Prints stay out of the general product rows.
// Measured (deploy 931 diagnostic): the customer-event photos from the
// "Exclusively Customised Creations" gallery exist as ordinary products in
// the "Personalised Prints" category, all catalog-visible — and New Arrivals
// and Trending Today simply show the newest products, so a batch of freshly
// uploaded event photos takes those rows over. Hidden here at the QUERY, not
// by catalog visibility: visibility would also empty the gallery, which
// selects the very same category.
// The rule: any products query that does not itself ask for this category
// excludes it; a query that asks for it (the gallery) is untouched. The
// shortcode filter covers the homepage rows — Elementor's Products widget
// and the AJAX-rendered rows both build on WooCommerce's shortcode query.
// A switch for the two row filters below, so their effect can be MEASURED
// rather than argued about: ?af_nofilter=1 turns both off for that one
// request. Nothing is cached under that URL and nothing else changes, so the
// same page can be fetched twice and the two card counts compared. The live
// site is unaffected — no visitor ever sends it.
function af_rows_filters_off() {
    return isset($_GET['af_nofilter']);
}
function af_personalised_cat_id() {
    static $id = null;
    if ($id !== null) return $id;
    $t = get_term_by('name', 'Personalised Prints', 'product_cat');
    if (!$t) $t = get_term_by('slug', 'personalised-prints', 'product_cat');
    $id = $t ? (int) $t->term_id : 0;
    return $id;
}
add_filter('woocommerce_shortcode_products_query', function ($args, $atts = array(), $type = '') {
    if (af_rows_filters_off()) return $args;
    $cat_id = af_personalised_cat_id();
    if (!$cat_id) return $args;
    // A row that asks for this category by any handle keeps it.
    $asked = '';
    foreach (array('category', 'terms', 'term') as $k) {
        if (!empty($atts[$k])) $asked .= ' ' . (is_array($atts[$k]) ? implode(' ', $atts[$k]) : $atts[$k]);
    }
    if ($asked !== '') {
        $t = get_term($cat_id, 'product_cat');
        if ($t && !is_wp_error($t) && stripos($asked, $t->slug) !== false) return $args;
        if (preg_match('/\b' . $cat_id . '\b/', $asked)) return $args;
    }
    if (!empty($args['tax_query']) && is_array($args['tax_query'])) {
        foreach ($args['tax_query'] as $tq) {
            if (is_array($tq) && isset($tq['taxonomy']) && $tq['taxonomy'] === 'product_cat'
                && (empty($tq['operator']) || $tq['operator'] === 'IN')) {
                $terms = isset($tq['terms']) ? (array) $tq['terms'] : array();
                $t = get_term($cat_id, 'product_cat');
                if (in_array($cat_id, array_map('intval', $terms), true)) return $args;
                if ($t && !is_wp_error($t) && in_array($t->slug, $terms, true)) return $args;
            }
        }
    }
    // Record what each row actually asks for, so the next report can name the
    // query behind a section instead of guessing at it. One write per NEW
    // signature only — an ordinary page load reads and writes nothing.
    $sig = $type . '|' . implode(',', array_keys((array) $atts))
         . '|orderby=' . (isset($args['orderby']) ? (is_array($args['orderby']) ? 'array' : $args['orderby']) : '-')
         . '|' . (isset($atts['category']) ? (is_array($atts['category']) ? implode('+', $atts['category']) : $atts['category']) : '-')
         . '|tq' . (empty($args['tax_query']) ? 0 : count($args['tax_query']));

    // Count what this row returns WITHOUT any interference from here. The row
    // is empty on the page while the report says the query returns twelve, and
    // those cannot both be true — so measure the row itself rather than a
    // reconstruction of it. Recorded once per signature; an ordinary page load
    // after the first reads a transient and writes nothing.
    $seen = get_transient('af_sc_seen');
    if (!is_array($seen)) $seen = array();
    if (!isset($seen[$sig]) && count($seen) < 24) {
        $bare = $args;
        $bare['posts_per_page']         = 20;
        $bare['fields']                 = 'ids';
        $bare['no_found_rows']          = true;
        $bare['update_post_meta_cache'] = false;
        $bare['update_post_term_cache'] = false;
        $bq = new WP_Query($bare);
        $seen[$sig] = 'before-exclusion=' . count($bq->posts);
        set_transient('af_sc_seen', $seen, DAY_IN_SECONDS);
    }

    $probe = $args;
    if (empty($probe['tax_query'])) $probe['tax_query'] = array();
    $probe['tax_query'][] = array(
        'taxonomy' => 'product_cat',
        'field'    => 'term_id',
        'terms'    => array($cat_id),
        'operator' => 'NOT IN',
    );

    // NEVER empty a row. Hiding a category from the generic rows is worth
    // doing; leaving a section on the homepage with a heading and nothing
    // under it is not, and that is exactly what happened to New Arrivals when
    // this filter first shipped. So the exclusion is tried before it is
    // applied: one ids-only query for a single post, and if the row would come
    // back empty the original arguments are returned untouched. The failure
    // mode of this feature is now "the photos are still there", which is a
    // cosmetic complaint, rather than "the section is gone", which is a hole
    // in the page.
    $test = $probe;
    $test['posts_per_page']        = 1;
    $test['fields']                = 'ids';
    $test['no_found_rows']         = true;
    $test['update_post_meta_cache']= false;
    $test['update_post_term_cache']= false;
    unset($test['paged'], $test['offset']);
    $probe_q = new WP_Query($test);
    if (empty($probe_q->posts)) {
        $seen = get_transient('af_sc_seen');
        if (is_array($seen) && isset($seen[$sig]) && strpos((string) $seen[$sig], 'stood-down') === false) {
            $seen[$sig] .= ' stood-down(would-empty)';
            set_transient('af_sc_seen', $seen, DAY_IN_SECONDS);
        }
        return $args;
    }

    return $probe;
}, 10, 3);

// 11z. Match the owner's OWN uploaded reels to the row's cards — on upload.
//
// The owner downloaded the sixteen reels to their machine and wants those exact
// files in the cards. Nothing here can read their disk, so the files arrive the
// one way a browser can send them: dragged into WordPress → Media. Everything
// after that is this code's job, and it must not need a deploy — the moment an
// upload finishes, the map is rebuilt and the cards play the real file.
//
// MATCHING. A downloader names its output after the video's TITLE, usually with
// its own litter around it ("y2mate.com - Radha Krishna Flute … .mp4"), and the
// channel feed already gives this site every reel's title. So: strip the litter
// and the channel's own branding, compare the remaining words both ways, and
// assign globally best-first so two reels can never claim one file. An id found
// inside a filename still wins outright — an 11-character YouTube id cannot be
// a coincidence.
function af_pim_tokens($s) {
    $s = strtolower(html_entity_decode((string) $s, ENT_QUOTES, 'UTF-8'));
    $s = preg_replace('/\.[a-z0-9]{2,4}$/', '', $s);        // file extension
    $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
    // Words a downloader or the channel adds to every single file: they carry
    // no signal, and left in they make unrelated reels look similar.
    static $junk = array(
        'y2mate','ssyoutube','savefrom','yt1s','yt5s','snapsave','tubemate','x2mate',
        'ytmp4','youtube','yout','yt','shorts','short','video','videos','download',
        'downloader','free','online','com','net','org','mp4','hd','sd','full','final',
        'copy','1080p','720p','480p','360p','x264','h264','media','clip','reel',
        'theartframer','artframer','framer','art','the','and','for','with','your','you',
        'this','that','from','into','out','new',
    );
    $out = array();
    foreach (explode(' ', trim($s)) as $t) {
        if ($t === '' || strlen($t) < 2) continue;
        if (in_array($t, $junk, true)) continue;
        $out[] = $t;
    }
    return $out;
}
// Coverage of the shorter side, plus a small bonus for words that also appear
// in the same order. Coverage rather than Jaccard because a downloader's name
// is usually the title PLUS extra words, and a title that is fully contained
// in a filename is a match however much litter surrounds it.
function af_pim_pair_score($a, $b) {
    if (!$a || !$b) return array(0.0, 0, 0);
    $ca = array_count_values($a);
    $cb = array_count_values($b);
    $inter = 0;
    foreach ($ca as $t => $n) if (isset($cb[$t])) $inter += min($n, $cb[$t]);
    $cov  = $inter / max(1, min(count($a), count($b)));
    // Coverage of the shorter side alone cannot tell a title apart from a
    // SHORTER title nested inside it — both cover fully — so a subset reel
    // ties with, or beats, the true owner of a truncated filename, and the
    // greedy pass can hand two cards each other's video. Ask also how much of
    // the LONGER side is accounted for. Additive and non-negative, so nothing
    // that used to clear the gate stops clearing it, and a littered downloader
    // filename still matches its reel.
    $tight = $inter / max(1, max(count($a), count($b)));
    $lead = 0;
    while ($lead < count($a) && $lead < count($b) && $a[$lead] === $b[$lead]) $lead++;
    return array($cov + $tight * 0.5 + min($lead, 6) * 0.05, $inter, $lead);
}
// $skip_id exists for one caller: delete_attachment fires BEFORE WordPress
// removes the row, so a rebuild there would re-match the file that is about to
// vanish and store its dead URL — which then outranks the card's working
// generated clip and leaves it on a 404 for good.
function af_pim_media_videos($skip_id = 0) {
    global $wpdb;
    $rows = $wpdb->get_results(
        "SELECT ID, post_title FROM {$wpdb->posts}
          WHERE post_type = 'attachment' AND post_mime_type LIKE 'video/%'
          ORDER BY post_date DESC");
    $out = array();
    foreach ($rows as $r) {
        if ($skip_id && (int) $r->ID === (int) $skip_id) continue;
        // _wp_attached_file, not guid: guid keeps the ORIGINAL address after a
        // move or a domain change, and the filename is what carries the title.
        $rel = (string) get_post_meta($r->ID, '_wp_attached_file', true);
        $ext = strtolower((string) pathinfo($rel, PATHINFO_EXTENSION));
        $out[] = array(
            'id'    => (int) $r->ID,
            'title' => (string) $r->post_title,
            'file'  => $rel !== '' ? basename($rel) : '',
            'url'   => (string) wp_get_attachment_url($r->ID),
            // Whether a plain <video> can actually decode it. Listed either
            // way, so the report can name a file the owner needs to re-export;
            // only playable ones are allowed into the map, because a container
            // no browser opens would outrank the card's working clip and leave
            // it blank. Judged on the stored extension rather than the declared
            // mime, which an uploader can set to video/mp4 for anything.
            'play'  => in_array($ext, array('mp4', 'm4v', 'webm', 'ogv'), true),
        );
    }
    return $out;
}
/**
 * Rebuild videoid => local URL. Returns the map; fills $report with lines.
 * Sources in order of certainty: mirrored file, id in filename, title match.
 */
function af_pim_build_local_map(&$report = null, $skip_id = 0) {
    $channel = 'UC_GX4vXRQrN4GsvSfgmZxYw';
    $ids = get_transient('af_yt_ids3_' . $channel);
    if (!is_array($ids) || !$ids) $ids = get_option('af_yt_ids3_lastgood_' . $channel);
    $ids = is_array($ids) ? array_values(array_filter($ids)) : array();
    if (!$ids) { if (is_array($report)) $report[] = 'no video ids stored'; return array(); }

    $titles = get_option('af_yt_titles_' . $channel);
    if (!is_array($titles)) $titles = array();

    $up  = wp_get_upload_dir();
    $dir = trailingslashit($up['basedir']) . 'pim/';
    $url = trailingslashit($up['baseurl']) . 'pim/';

    $map = array();
    foreach ($ids as $vid) {
        if (file_exists($dir . $vid . '.mp4')) $map[$vid] = $url . $vid . '.mp4';
    }
    // Only files a browser can actually decode may claim a card; the rest are
    // still reported, so an unplayable upload reads as "re-export this one"
    // rather than as a card that mysteriously went blank.
    $all  = af_pim_media_videos($skip_id);
    $atts = array_values(array_filter($all, function ($a) { return !empty($a['play']); }));
    if (is_array($report)) {
        foreach ($all as $a) {
            if (empty($a['play'])) $report[] = sprintf('SKIP  #%d %s — not a format a browser plays', $a['id'], $a['file']);
        }
    }
    $used = array();

    // 1. The id inside a filename — compared after the same normalisation
    //    WordPress applies on upload, since sanitize_file_name() trims '.-_'
    //    and collapses hyphen runs, so ids starting with '-' or containing
    //    '--' are not present verbatim in the stored name.
    $key = function ($s) { return preg_replace('/[^a-z0-9]+/', '', strtolower((string) $s)); };
    foreach ($ids as $vid) {
        if (isset($map[$vid])) continue;
        $needle = $key($vid);
        if ($needle === '') continue;
        foreach ($atts as $a) {
            if (isset($used[$a['id']])) continue;
            if (strpos($key($a['file']), $needle) !== false || strpos($key($a['title']), $needle) !== false) {
                $map[$vid] = $a['url'];
                $used[$a['id']] = true;
                if (is_array($report)) $report[] = sprintf('ID    %s -> #%d %s', $vid, $a['id'], $a['file']);
                break;
            }
        }
    }

    // 2. Titles, assigned globally best-first so the strongest pair wins the
    //    file rather than whichever reel happened to be examined first.
    $pairs = array();
    foreach ($ids as $vid) {
        if (isset($map[$vid])) continue;
        $tt = isset($titles[$vid]) ? af_pim_tokens($titles[$vid]) : array();
        if (!$tt) continue;
        foreach ($atts as $a) {
            if (isset($used[$a['id']])) continue;
            list($s1, $i1, $l1) = af_pim_pair_score($tt, af_pim_tokens($a['file']));
            list($s2, $i2, $l2) = af_pim_pair_score($tt, af_pim_tokens($a['title']));
            $score = max($s1, $s2);
            $inter = $s1 >= $s2 ? $i1 : $i2;
            $lead  = $s1 >= $s2 ? $l1 : $l2;
            // Three shared meaningful words covering most of the shorter side,
            // or four words in the same order: either is a deliberate title,
            // not two reels that both say "Krishna".
            if (($inter >= 3 && $score >= 0.60) || $lead >= 4) {
                $pairs[] = array('vid' => $vid, 'att' => $a, 'score' => $score, 'inter' => $inter);
            }
        }
    }
    // Tie-break on shared word count. Two pairs can score identically when one
    // title's words are a prefix of the other's — coverage is 1.0 both ways —
    // and then insertion order, which is the channel feed's order, decided
    // which card got the file. The pair sharing more real words is the pair
    // that means it.
    usort($pairs, function ($x, $y) {
        return ($y['score'] <=> $x['score']) ?: ($y['inter'] <=> $x['inter']);
    });
    foreach ($pairs as $p) {
        if (isset($map[$p['vid']]) || isset($used[$p['att']['id']])) continue;
        $map[$p['vid']] = $p['att']['url'];
        $used[$p['att']['id']] = true;
        if (is_array($report)) {
            $report[] = sprintf('TITLE %s -> #%d %s (score %.2f, %d words)',
                $p['vid'], $p['att']['id'], $p['att']['file'], $p['score'], $p['inter']);
        }
    }

    update_option('af_pim_local', $map, false);
    return $map;
}
/**
 * Purge the product LISTING pages, and only those.
 *
 * This host returns HTTP 508 when its resource limit is reached, and a cold
 * page cache is what gets it there: the deploy workflow says so in as many
 * words — "the mid-pipeline purge left every page cold — without this, real
 * visitors pay the cold-render cost and hit 508s while the host is still
 * busy" — and it carries a whole guarded re-warm step to stop it happening.
 *
 * Three places in this theme were calling litespeed_purge_all, which puts the
 * site into exactly that state with nothing to warm it afterwards, and one of
 * them ran every single day. None of them needed the whole site: a change to
 * the running order of the catalogue shows up on the shop, the category pages
 * and the tag pages, and nowhere else. Product pages, the homepage, the blog,
 * cart and checkout were being thrown away for nothing.
 *
 * wp_cache_flush() went with them, and that was worse than unnecessary: it
 * empties the OBJECT cache — every option, term and post WordPress had in
 * memory — so the next request rebuilds the lot. Nothing here is stored in
 * the object cache to begin with.
 *
 * @param int[] $term_ids Limit to these product_cat terms; empty means every
 *                        listing page.
 */
function af_purge_listing_pages($term_ids = array()) {
    $urls = array();

    if (function_exists('wc_get_page_id')) {
        $shop = wc_get_page_id('shop');
        if ($shop > 0) {
            $u = get_permalink($shop);
            if ($u) $urls[] = $u;
        }
    }

    if ($term_ids) {
        foreach ((array) $term_ids as $tid) {
            $u = get_term_link((int) $tid, 'product_cat');
            if ($u && !is_wp_error($u)) $urls[] = $u;
        }
    } else {
        // Every listing the order actually appears on. Bounded: a runaway
        // taxonomy must not turn a purge into its own load problem.
        foreach (array('product_cat', 'product_tag') as $tax) {
            $terms = get_terms(array(
                'taxonomy'   => $tax,
                'hide_empty' => true,
                'number'     => 100,
                'orderby'    => 'count',
                'order'      => 'DESC',
                'fields'     => 'all',
            ));
            if (is_wp_error($terms) || !$terms) continue;
            foreach ($terms as $t) {
                $u = get_term_link($t);
                if ($u && !is_wp_error($u)) $urls[] = $u;
            }
        }
    }

    $urls = array_values(array_unique(array_filter($urls)));
    foreach ($urls as $u) {
        do_action('litespeed_purge_url', $u);
    }
    // The warmer restarts its rotation on this, so the pages just dropped are
    // the first ones put back rather than whatever the cursor was pointing at.
    do_action('af_listing_pages_purged', $urls);
    return count($urls);
}

// The whole point of doing this on upload: dragging the files into Media is
// the owner's only step, and it must be the last one. No deploy, no command.
function af_pim_media_changed($post_id, $skip_id = 0) {
    if (strpos((string) get_post_mime_type($post_id), 'video/') !== 0) return;
    $r = null;
    af_pim_build_local_map($r, $skip_id);
    // The homepage is cached; a new file that nothing serves is not a fix.
    // The row is on the homepage and nowhere else, so purge that one page —
    // emptying the whole cache on this host is what produces the 508s the
    // deploy workflow's re-warm step exists to prevent.
    do_action('litespeed_purge_url', home_url('/'));
}
add_action('add_attachment', 'af_pim_media_changed', 20);
// Two hooks, because one of them can be too early: add_attachment fires as the
// row is inserted, and some uploaders fill in the file path a moment later
// while generating metadata. Re-running then is cheap and idempotent — it
// rebuilds one option — and it means no upload path can quietly miss.
add_action('wp_update_attachment_metadata', function ($data, $post_id) {
    af_pim_media_changed($post_id);
    return $data;
}, 20, 2);
// Deleting one: the row is still in the database at this moment, so the file
// being removed has to be excluded by hand or the rebuild simply re-matches it
// and stores a URL that is about to 404 — which would then outrank the card's
// working generated clip and leave that card broken until the next upload.
add_action('delete_attachment', function ($post_id) {
    af_pim_media_changed($post_id, $post_id);
}, 20);

// 10z-2. New Arrivals: today's uploads, or a random selection.
//
// The owner's rule (2026-09-08): the row shows what was added TODAY, and when
// nothing was added today it shows a random pick rather than the same newest
// items every day. Which is also why the row must never be empty — a heading
// with nothing under it was the state this replaces.
//
// Identifying the row from PHP: the section is found in the browser by its
// heading text, so nothing in the markup names it here. What does name it is
// the shape of its query — the first generic newest-first products row on the
// front page, with no category or id list of its own. That is New Arrivals;
// the other rows either scope themselves or arrive over AJAX.
add_filter('woocommerce_shortcode_products_query', function ($args, $atts = array(), $type = '') {
    static $claimed = false;
    if (af_rows_filters_off()) return $args;
    if ($claimed || is_admin() || wp_doing_ajax()) return $args;
    if (!is_front_page() && !is_home()) return $args;
    if (!empty($atts['category']) || !empty($atts['ids']) || !empty($atts['skus'])) return $args;
    $orderby = isset($args['orderby']) ? strtolower((string) $args['orderby']) : '';
    if ($orderby !== '' && $orderby !== 'date') return $args;
    $claimed = true;

    // Midnight in the site's own timezone, not the server's: "today" has to
    // mean the owner's today or a piece uploaded this morning can be missing
    // from the row that exists to show it.
    $start = function_exists('current_datetime')
        ? current_datetime()->setTime(0, 0, 0)->format('Y-m-d H:i:s')
        : date('Y-m-d 00:00:00', current_time('timestamp'));

    $today = $args;
    $today['date_query'] = array(array('after' => $start, 'inclusive' => true));

    $probe = $today;
    $probe['posts_per_page']         = 1;
    $probe['fields']                 = 'ids';
    $probe['no_found_rows']          = true;
    $probe['update_post_meta_cache'] = false;
    $probe['update_post_term_cache'] = false;
    unset($probe['paged'], $probe['offset']);
    $q = new WP_Query($probe);
    if (!empty($q->posts)) return $today;      // something arrived today

    // Nothing today — a random pick, so the row is different each time rather
    // than the same newest dozen it would otherwise be frozen on.
    //
    // Chosen as explicit ids rather than by handing WooCommerce orderby=rand:
    // the shortcode's own result caching and ordering both key off the query
    // it was given, and a row that renders on the server but not on the page
    // is exactly the failure being chased here. post__in with a fixed list is
    // an ordinary query that cannot behave differently in one context than
    // another.
    $pool = $args;
    $pool['posts_per_page']         = 60;
    $pool['fields']                 = 'ids';
    $pool['no_found_rows']          = true;
    $pool['update_post_meta_cache'] = false;
    $pool['update_post_term_cache'] = false;
    unset($pool['paged'], $pool['offset'], $pool['date_query']);
    $pq = new WP_Query($pool);
    $ids = $pq->posts;
    if (count($ids) < 2) return $args;          // nothing to shuffle: leave it alone
    shuffle($ids);
    $want = isset($args['posts_per_page']) ? (int) $args['posts_per_page'] : 12;
    if ($want < 1) $want = 12;
    $args['post__in'] = array_slice($ids, 0, $want);
    $args['orderby']  = 'post__in';
    unset($args['date_query']);
    return $args;
}, 11, 3);

// 10z-3. A product carousel the theme never initialised is healed here.
//
// Measured (deploy 960): the theme renders New Arrivals as a Swiper carousel —
// <div class="products swiper-wrapper" data-items="3" data-autoplay="2000"
// data-loop="1"> with a .product.swiper-slide per card — and all 36 slides are
// in the DOM of a row that shows nothing. An uninitialised Swiper is invisible:
// its slides have no width and its container no height, until the library
// marks it swiper-initialized. Whatever stopped the theme's own setup, the
// visitor should not be looking at a blank band, so a few seconds after load
// any product carousel still waiting is started here with the theme's own
// data-* settings (Elementor ships the Swiper library on this page), and if
// the library is somehow absent the wrapper is shown as a plain scrolling strip.
// Nothing here runs when the theme has done its job.
add_action('wp_footer', function () {
    if (!is_front_page() && !is_home()) return; ?>
<style>
.af-swiper-fallback > .products.swiper-wrapper{display:flex !important;flex-wrap:nowrap !important;gap:16px;
  overflow-x:auto !important;padding:4px 0 12px;height:auto !important;transform:none !important;}
.af-swiper-fallback > .products.swiper-wrapper > .product.swiper-slide{flex:0 0 auto !important;
  width:clamp(200px,23vw,320px) !important;height:auto !important;}
</style>
<script>
(function(){
  function heal(){
    document.querySelectorAll('.products.swiper-wrapper').forEach(function(wrap){
      var c = wrap.parentElement;
      if (!c || wrap.getAttribute('data-af-healed')) return;
      if (c.classList.contains('swiper-initialized') || c.classList.contains('swiper-container-initialized')) return;
      if (!wrap.children.length) return;
      var d = c.dataset || {};
      var items = parseInt(d.items, 10) || 4;
      if (window.Swiper) {
        try {
          // Swiper's own stylesheet keys on these classes; the theme's
          // container may carry neither.
          c.classList.add('swiper'); c.classList.add('swiper-container');
          new Swiper(c, {
            slidesPerView: items,
            spaceBetween: parseInt(d.margin, 10) || 16,
            slidesPerGroup: parseInt(d.slideItems, 10) || 1,
            speed: parseInt(d.speed, 10) || 400,
            loop: d.loop === '1',
            autoplay: d.autoplay ? { delay: parseInt(d.autoplay, 10) || 3000, disableOnInteraction: false } : false,
            breakpoints: { 0: { slidesPerView: 1.3 }, 600: { slidesPerView: 2 },
                           900: { slidesPerView: Math.min(items, 3) }, 1200: { slidesPerView: items } }
          });
          wrap.setAttribute('data-af-healed', 'swiper');
          return;
        } catch (e) {}
      }
      c.classList.add('af-swiper-fallback');
      wrap.setAttribute('data-af-healed', 'fallback');
    });
  }
  window.addEventListener('load', function(){ setTimeout(heal, 4000); });
  setTimeout(heal, 9000);
})();
</script>
<?php }, 95);

// 11a. The row's video ids, as plain text at /?af_pim_ids=1.
// This exists for the reel-fetch tool on the OWNER'S machine — the one
// address YouTube actually serves, after downloads from GitHub's runners and
// the work container were both refused (datacenter addresses, measured, three
// strikes). The tool asks the site which videos the row uses, fetches them at
// home, and uploads them named by id. Public and read-only on purpose: these
// ids already sit in the homepage markup and on the public channel, so this
// discloses nothing — it only saves the tool parsing HTML.
add_action('init', function () {
    if (!isset($_GET['af_pim_ids'])) return;
    $channel = 'UC_GX4vXRQrN4GsvSfgmZxYw';
    $ids = get_transient('af_yt_ids3_' . $channel);
    if (!is_array($ids) || !$ids) $ids = get_option('af_yt_ids3_lastgood_' . $channel);
    $ids = is_array($ids) ? array_values(array_filter($ids)) : array();
    // NOT sanitize_key: it lowercases, and video ids are case-sensitive.
    // The output rule is the id's own alphabet, nothing else escapes.
    $ids = array_values(array_filter($ids, function ($v) {
        return is_string($v) && preg_match('/^[A-Za-z0-9_-]{11}$/', $v);
    }));

    // ?af_pim_ids=missing lists only the videos with no local copy yet, so the
    // owner's tool can re-run without downloading and re-uploading the whole
    // set every time — which would pile up a duplicate attachment per reel.
    if (isset($_GET['af_pim_ids']) && $_GET['af_pim_ids'] === 'missing') {
        $have = get_option('af_pim_local');
        if (!is_array($have)) $have = array();
        $up  = wp_get_upload_dir();
        $dir = trailingslashit($up['basedir']) . 'pim/';
        $ids = array_values(array_filter($ids, function ($v) use ($have, $dir) {
            return !isset($have[$v]) && !file_exists($dir . $v . '.mp4');
        }));
    }

    nocache_headers();
    header('Content-Type: text/plain; charset=utf-8');
    // The sentinel line lets a caller tell "the endpoint answered and the
    // list is empty" apart from "something else answered" — a cached page, a
    // maintenance screen, a challenge. An empty body cannot make that
    // distinction, and one misread of it cost a deploy once already.
    echo "#af-pim-ids\n";
    echo implode("\n", $ids);
    exit;
});

// 11. Products In Motion — circular video slider (PHP-rendered, no JS dependency)
add_action('wp_footer', function() {
    if (!is_front_page()) return;

    // Fetch video IDs, cached 1 hour. (Key bumped to _ids3_ to bypass stale cache.)
    $channel = 'UC_GX4vXRQrN4GsvSfgmZxYw';
    $ids = get_transient('af_yt_ids3_' . $channel);
    // Treat a too-small cached list (e.g. only the single Elementor video, left
    // over from a moment when the YouTube RSS fetch was blocked) as needing a
    // rebuild — otherwise the 1-hour transient pins the row to one video.
    $af_yt_titles = array();
    if (!is_array($ids) || count($ids) < 3) {
        $ids = [];

        // 1) PRIMARY: pull the YouTube video IDs already placed in the homepage's
        //    Elementor content (reliable — no external request needed).
        $raw = get_post_meta(get_the_ID(), '_elementor_data', true);
        if ($raw) {
            $flat = str_replace('\\/', '/', $raw); // unescape JSON slashes
            if (preg_match_all('#(?:youtube(?:-nocookie)?\.com/(?:watch\?(?:[^"&]*&)*v=|embed/|shorts/|v/)|youtu\.be/)([A-Za-z0-9_-]{11})#i', $flat, $mm)) {
                $ids = array_merge($ids, $mm[1]);
            }
            if (preg_match_all('#[?&]v=([A-Za-z0-9_-]{11})#', $flat, $mm2)) {
                $ids = array_merge($ids, $mm2[1]);
            }
        }

        // 2) SUPPLEMENT: latest uploads from the channel RSS (best-effort)
        $resp = wp_remote_get(
            'https://www.youtube.com/feeds/videos.xml?channel_id=' . $channel,
            ['timeout' => 8]
        );
        if (!is_wp_error($resp)) {
            $xml = @simplexml_load_string(wp_remote_retrieve_body($resp));
            if ($xml) {
                foreach ($xml->entry as $entry) {
                    if (preg_match('/video:([A-Za-z0-9_-]{11})/', (string)$entry->id, $m)) {
                        $ids[] = $m[1];
                        // Keep the title too — the cards carry a caption now.
                        // Stored separately from $ids on purpose: that list has
                        // a last-known-good fallback tuned to survive a blocked
                        // fetch, and changing its shape would put that at risk.
                        $t = trim((string) $entry->title);
                        if ($t !== '') $af_yt_titles[$m[1]] = $t;
                    }
                }
            }
        }

        $ids = array_values(array_unique(array_filter($ids)));

        // 3) DURABLE FALLBACK: merge the last-known-good list so a blocked RSS
        //    fetch can never shrink the row back to a single video. Cap the
        //    total so it can't grow unbounded as videos are added over time.
        $lastgood = get_option('af_yt_ids3_lastgood_' . $channel);
        if (is_array($lastgood) && $lastgood) {
            $ids = array_values(array_unique(array_merge($ids, $lastgood)));
        }
        $ids = array_slice($ids, 0, 30);

        // Only persist / update last-good once we actually have the full row,
        // so a bad fetch never overwrites a good saved list.
        if (count($ids) >= 3) {
            set_transient('af_yt_ids3_' . $channel, $ids, HOUR_IN_SECONDS);
            update_option('af_yt_ids3_lastgood_' . $channel, $ids, false);
        }
        // Merge rather than replace: a fetch that returns fewer entries than
        // last time must not cost us captions we already had.
        if ($af_yt_titles) {
            $known = get_option('af_yt_titles_' . $channel);
            update_option('af_yt_titles_' . $channel,
                array_merge(is_array($known) ? $known : array(), $af_yt_titles), false);
        }
    }
    // ($titles used to feed a caption on each card; the row now shows only
    // the moving picture, so nothing here reads them — the pim tools still do.)

    // Nothing to show
    if (empty($ids)) return;
    ?>
<link rel="stylesheet" id="af-pim-slider" href="<?php echo esc_url(af_static_url('pim-slider.css')); ?>" data-no-optimize="1">

<?php
    // Build one card per video. Two copies of the run are emitted below, so
    // the marquee can slide exactly one copy's width and start over without a
    // visible seam.
    $cards_html = '';
    // The row plays the site's OWN copy of each video when one exists —
    // uploads/pim/<videoid>.mp4, mirrored there by the deploy. A same-origin
    // <video> starts in the time of one range request, with no YouTube
    // negotiation, no spinner and no title text; the owner timed the embeds at
    // several seconds of black per card, which is exactly that negotiation.
    // YouTube still owns the lightbox, where the full player belongs. A card
    // whose mp4 has not been mirrored yet keeps the embed path, so a missing
    // file costs a slower card, never a dead one.
    $pim_up   = wp_get_upload_dir();
    $pim_dirf = trailingslashit($pim_up['basedir']) . 'pim/';
    $pim_urlf = trailingslashit($pim_up['baseurl']) . 'pim/';
    // tools/pim-local-video.php also matches videos ALREADY in the Media
    // Library to the row's videos by title, so a reel the studio has uploaded
    // itself serves its card without anything being downloaded at all.
    $pim_local = get_option('af_pim_local');
    if (!is_array($pim_local)) $pim_local = array();
    // A MOVING preview per card, discovered by tools/pim-anim-probe.php: an
    // animated WebP from YouTube's image CDN. It is an image to the browser,
    // so it autoplays and loops with no player and therefore none of a
    // player's chrome. Empty until the probe has run, or for a video that has
    // no animation - those cards keep the cross-fading stills.
    $pim_anim = get_option('af_pim_anim');
    if (!is_array($pim_anim)) $pim_anim = array();
    // The stills were rendering 290x163 inside a 516px-tall card — full width,
    // intrinsic height — so every card carried a black band above and below the
    // picture. The stylesheet already says height:100% !important on
    // .af-pim-thumb, and it was still losing, which means something later in
    // the cascade carries !important of its own on an img selector. An inline
    // declaration marked important is the top of the author cascade: nothing in
    // any stylesheet can outrank it. Carried per element rather than added to
    // the class, precisely because the class rule is the thing being overruled.
    $thumb_fit = 'width:100%!important;height:100%!important;max-width:none!important;'
               . 'max-height:none!important;object-fit:cover!important;object-position:center!important;';
    foreach ($ids as $vid) {
        $vid = esc_attr($vid);
        $thumb_hq  = "https://img.youtube.com/vi/{$vid}/hqdefault.jpg";
        $thumb_max = "https://img.youtube.com/vi/{$vid}/maxresdefault.jpg";
        // Three sources, best first. The true reel (fetched or matched in the
        // Media Library) always wins. <id>.anim.mp4 is the site's OWN motion
        // clip, built by the fetch workflow from the reel's published frames
        // for every reel YouTube refuses to hand over — a real file, played by
        // a real <video>, with nothing drawn on it. The ids endpoint's
        // "missing" list deliberately ignores .anim.mp4, so the hunt for the
        // original continues daily and replaces the built clip the moment it
        // lands, under the same card without another deploy.
        $mp4       = file_exists($pim_dirf . $vid . '.mp4') ? $pim_urlf . $vid . '.mp4'
                   : (isset($pim_local[$vid]) ? $pim_local[$vid]
                   : (file_exists($pim_dirf . $vid . '.anim.mp4') ? $pim_urlf . $vid . '.anim.mp4' : ''));
        // The owner's spec for this row (2026-08-31): ONLY the moving picture.
        // No title text — so the caption is gone, not styled away. No player
        // element in the markup either: the JS builds one, only for cards on
        // screen and never more than a handful at once. Both the card's player
        // and the lightbox derive their URLs from data-vid.
        $cards_html .= '
<div class="af-pim-card" data-vid="' . $vid . '"'
  . ($mp4 !== '' ? ' data-mp4="' . esc_attr($mp4) . '"' : '') . '>
  <img class="af-pim-thumb af-pim-f0" style="' . $thumb_fit . '" src="' . $thumb_max . '" onerror="this.src=\'' . $thumb_hq . '\';this.onerror=null" alt="" loading="lazy" decoding="async">
  <img class="af-pim-thumb af-pim-f1" style="' . $thumb_fit . '" src="https://img.youtube.com/vi/' . $vid . '/hq1.jpg" alt="" loading="lazy" decoding="async">
  <img class="af-pim-thumb af-pim-f2" style="' . $thumb_fit . '" src="https://img.youtube.com/vi/' . $vid . '/hq2.jpg" alt="" loading="lazy" decoding="async">
  <img class="af-pim-thumb af-pim-f3" style="' . $thumb_fit . '" src="https://img.youtube.com/vi/' . $vid . '/hq3.jpg" alt="" loading="lazy" decoding="async">'
  . (isset($pim_anim[$vid]) ? '
  <img class="af-pim-anim" style="' . $thumb_fit . '" src="' . esc_url($pim_anim[$vid]) . '" alt="" loading="lazy" decoding="async" onerror="this.remove()">' : '') . '
  <div class="af-pim-overlay"></div>
</div>';
    }
    // Seconds for one full pass. Tied to the number of cards so adding videos
    // makes the row longer, not faster — the apparent speed stays constant.
    $pim_dur = max(20, count($ids) * 5);
?>
<?php
/* Open the connections the players will need before any of them is created.
   The first embed otherwise pays for DNS, TLS and the redirect before a single
   frame arrives, which is most of the delay a visitor sees on arrival — and
   the preload above only helps if the connection is ready when it fires. */
?>
<link rel="preconnect" href="https://www.youtube-nocookie.com" crossorigin>
<link rel="preconnect" href="https://i.ytimg.com" crossorigin>
<link rel="preconnect" href="https://www.google.com" crossorigin>
<link rel="dns-prefetch" href="https://www.youtube-nocookie.com">

<div class="af-pim-wrap" id="afPimWrap">
  <div class="af-pim-vp" id="afPimVp">
    <div class="af-pim-track" id="afPimTrack" style="--af-pim-dur:<?php echo (int) $pim_dur; ?>s">
      <?php echo $cards_html; ?>
      <?php
      /* The second copy is decorative: it exists to make the loop seamless and
         must not be announced twice to a screen reader. */
      ?>
      <span class="af-pim-dupe" aria-hidden="true" style="display:contents"><?php echo $cards_html; ?></span>
    </div>
  </div>
</div>

<!-- Lightbox -->
<div class="af-pim-lb" id="afPimLb">
  <button class="af-pim-lb-x" id="afPimLbX">&times;</button>
  <!-- No src attribute: src="" resolves to the page's own URL, which made
       every page load a full hidden copy of itself inside this lightbox. -->
  <iframe id="afPimLbFrame" allow="autoplay; fullscreen; encrypted-media" allowfullscreen></iframe>
</div>

<script id="af-pim-track" src="<?php echo esc_url(af_static_url('pim-track.js')); ?>" data-no-optimize="1"></script>
<?php }, 10002);

// Shortcode: [af_features] — place in any Elementor HTML widget
add_shortcode('af_features', function() {
    return '<div class="af-features-bar"></div>';
});

/* ============================================================
   Section 12: Feature Icons Bar with slide-up overlays
   ============================================================ */
add_action('wp_footer', function() { ?>
<div class="af-feat-overlay" id="afFeatOverlay"></div>
<div class="af-feat-sheet" id="afFeatSheet">
  <div class="af-feat-sheet-handle"></div>
  <button class="af-feat-sheet-close" id="afFeatClose">&times;</button>
  <h3 id="afFeatTitle"></h3>
  <div id="afFeatBody"></div>
</div>
<script>
(function(){
  // shipping wording comes from af_shipping_copy() so the badge, its popup,
  // the announcement bar and the chatbot always say the same thing
  var AF_SHIP = <?php echo wp_json_encode(af_shipping_copy()); ?>;
  var features = [
    {
      label: AF_SHIP.label,
      icon: '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M20 8h-3V4H3c-1.1 0-2 .9-2 2v11h2c0 1.66 1.34 3 3 3s3-1.34 3-3h6c0 1.66 1.34 3 3 3s3-1.34 3-3h2v-5l-3-4zM6 18.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm13.5-9l1.96 2.5H17V9.5h2.5zm-1.5 9c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/></svg>',
      title: '🚚 ' + AF_SHIP.label,
      // the old copy made a no-cost shipping promise here and then limited it to
      // four states in the popup — one claim, stated once, from af_shipping_copy()
      body: '<p>' + AF_SHIP.blurb + '</p><h4>📦 How it travels:</h4><ul><li>Smaller unframed prints ship rolled in a protective tube</li><li>Framed and larger pieces ship flat in a corner-protected crate</li><li>Oversize handling, where it applies, is shown at checkout</li></ul><p>Everything is made to order, so allow a few days for production before it ships. Tracking follows by email.</p>'
    },
    {
      label: 'High Resolution',
      icon: '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14zm-5-7h-2V8h-2v4H8v2h2v4h2v-4h2v-2z"/></svg>',
      title: '🖨️ High Resolution Printing',
      body: '<p>We use professional-grade printing technology to deliver crisp, vibrant, museum-quality prints.</p><h4>✅ Features:</h4><ul><li>Up to 1200 DPI resolution</li><li>Fade-resistant inks</li><li>True-to-life color accuracy</li><li>UV protective coating available</li></ul>'
    },
    {
      label: 'Premium Frames',
      icon: '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M20 2H4c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 18H4V4h16v16zM6 6h12v12H6z"/></svg>',
      title: '🛡️ Premium Frames',
      body: '<p>Handcrafted frames built to last — because great art deserves a great frame.</p><h4>✅ Frame Options:</h4><ul><li>Solid wood &amp; metal frames</li><li>Custom sizing available</li><li>Floater frames for canvas</li><li>Gallery-wrap ready</li></ul><h4>🎨 Styles:</h4><ul><li>Modern, Classic, Rustic, Minimalist</li></ul>'
    },
    {
      label: 'Secure Payment',
      icon: '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M20 4H4c-1.11 0-2 .89-2 2v12c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/></svg>',
      title: '🔒 Secure Payment',
      body: '<p>Your payment information is always safe with our encrypted checkout.</p><h4>✅ We Accept:</h4><ul>'
            + <?php echo wp_json_encode( implode( '', array_map( function ( $m ) {
                  return '<li>' . esc_html( $m ) . '</li>';
              }, af_payment_methods() ) ) ); ?>
            + '</ul><h4>🔐 Security:</h4><ul><li>256-bit SSL encryption</li><li>PCI DSS compliant</li><li>No card data stored</li></ul>'
    }
  ];

  function buildBar() {
    var bars = document.querySelectorAll('.af-features-bar');
    if (!bars.length) return;
    bars.forEach(function(bar) {
      if (bar.dataset.afBuilt) return;
      bar.dataset.afBuilt = '1';
      features.forEach(function(f, i) {
        var item = document.createElement('div');
        item.className = 'af-feature-item';
        var iconDiv = document.createElement('div');
        iconDiv.className = 'af-feature-icon';
        iconDiv.innerHTML = f.icon;
        var label = document.createElement('div');
        label.className = 'af-feature-label';
        label.textContent = f.label;
        item.appendChild(iconDiv);
        item.appendChild(label);
        item.addEventListener('click', function() { openSheet(i); });
        bar.appendChild(item);
      });
    });
  }

  var overlay = document.getElementById('afFeatOverlay');
  var sheet   = document.getElementById('afFeatSheet');
  var closeBtn= document.getElementById('afFeatClose');
  var titleEl = document.getElementById('afFeatTitle');
  var bodyEl  = document.getElementById('afFeatBody');

  function openSheet(i) {
    titleEl.innerHTML = features[i].title;
    bodyEl.innerHTML  = features[i].body;
    overlay.classList.add('active');
    requestAnimationFrame(function(){ sheet.classList.add('open'); });
  }
  window.afOpenSheet = openSheet;
  function closeSheet() {
    sheet.classList.remove('open');
    setTimeout(function(){ overlay.classList.remove('active'); }, 350);
  }
  if (closeBtn) closeBtn.addEventListener('click', closeSheet);
  if (overlay)  overlay.addEventListener('click', closeSheet);

  document.addEventListener('DOMContentLoaded', buildBar);
  window.addEventListener('load', buildBar);
}());
</script>
<script>
(function(){
  function styleIcon(iconEl) {
    // Walk up to find the best wrapper to turn into a gold circle
    var wrap = iconEl.closest('.elementor-icon') || iconEl.parentElement;
    wrap.style.setProperty('width', '56px', 'important');
    wrap.style.setProperty('height', '56px', 'important');
    wrap.style.setProperty('min-width', '56px', 'important');
    wrap.style.setProperty('border-radius', '50%', 'important');
    wrap.style.setProperty('background', '#c9a84c', 'important');
    wrap.style.setProperty('display', 'flex', 'important');
    wrap.style.setProperty('align-items', 'center', 'important');
    wrap.style.setProperty('justify-content', 'center', 'important');
    wrap.style.setProperty('margin', '0 auto 6px', 'important');
    wrap.style.setProperty('flex-shrink', '0', 'important');
    iconEl.style.setProperty('color', '#fff', 'important');
    iconEl.style.setProperty('fill', '#fff', 'important');
    iconEl.style.setProperty('font-size', '24px', 'important');
    iconEl.style.setProperty('width', '24px', 'important');
    iconEl.style.setProperty('height', '24px', 'important');
    iconEl.style.setProperty('line-height', '1', 'important');
  }

  function findFeaturesSec() {
    var sec = document.querySelector('.features-section');
    if (sec) return sec;
    sec = document.querySelector('[data-id="810fb7a"]');
    if (sec) return sec;
    return null;
  }

  function fixFeaturesSection() {
    var sec = findFeaturesSec();
    if (!sec || sec.dataset.fsFixed) return;
    sec.dataset.fsFixed = '1';

    // Find ALL icon widgets inside the section
    var iconEls = sec.querySelectorAll('.elementor-icon i, .elementor-icon svg');
    iconEls.forEach(function(iconEl) { styleIcon(iconEl); });

    // Find ALL label texts
    sec.querySelectorAll('.elementor-icon-box-title, .elementor-heading-title, .elementor-widget-container > p').forEach(function(label) {
      label.style.setProperty('font-size', '11px', 'important');
      label.style.setProperty('font-weight', '600', 'important');
      label.style.setProperty('color', '#222', 'important');
      label.style.setProperty('text-align', 'center', 'important');
      label.style.setProperty('line-height', '1.3', 'important');
      label.style.setProperty('margin', '0', 'important');
    });

    // Force EVERY .e-con-inner and .e-con inside features-section to row
    // Must use setProperty('--flex-direction') because Elementor sets it as inline var()
    sec.querySelectorAll('.e-con-inner, .e-con').forEach(function(el) {
      el.style.setProperty('--flex-direction', 'row', 'important');
      el.style.setProperty('flex-direction', 'row', 'important');
      el.style.setProperty('flex-wrap', 'nowrap', 'important');
      el.style.setProperty('justify-content', 'space-evenly', 'important');
      el.style.setProperty('align-items', 'flex-start', 'important');
      el.style.setProperty('gap', '4px', 'important');
    });

    // Style each direct elementor widget as a flex column item
    sec.querySelectorAll('.elementor-element').forEach(function(item) {
      item.style.setProperty('flex', '1 1 0', 'important');
      item.style.setProperty('min-width', '0', 'important');
      item.style.setProperty('max-width', '90px', 'important');
      item.style.setProperty('text-align', 'center', 'important');
    });
  }

  // Poll until Elementor lazy-loads the section (up to ~10 seconds)
  var _fsAttempts = 0;
  function _fsPoll() {
    _fsAttempts++;
    fixFeaturesSection();
    var found = document.querySelector('[data-id="810fb7a"]') || document.querySelector('.features-section');
    var fixed = found && found.dataset.fsFixed;
    if (!fixed && _fsAttempts < 50) {
      setTimeout(_fsPoll, 200);
    }
  }
  document.addEventListener('DOMContentLoaded', _fsPoll);
  window.addEventListener('load', _fsPoll);
}());
</script>
<script>
// Hide inline popup content inside .feature-box — should only show in overlay sheet
(function(){
  function hideStaticPopupContent() {
    // 1. Hide .popup-data elements (Elementor HTML widgets with popup content)
    document.querySelectorAll('.popup-data, [class*="popup-data"], [id*="popup-data"]').forEach(function(el) {
      el.style.setProperty('display', 'none', 'important');
    });

    // 2. Hide any Elementor section/column that comes AFTER the features-container
    //    AND contains a close button (×) or has popup-style content — but NOT our #afFeatSheet
    var featContainer = document.querySelector('.features-container, .features-section');
    if (featContainer) {
      // Walk sibling elements after the features section and hide those with popup content
      var parent = featContainer.closest('.elementor-section, .e-con, section') || featContainer.parentElement;
      if (parent && parent.parentElement) {
        var siblings = Array.from(parent.parentElement.children);
        var found = false;
        siblings.forEach(function(sib) {
          if (sib === parent) { found = true; return; }
          if (!found) return;
          // Skip our own overlay elements
          if (sib.id === 'afFeatOverlay' || sib.id === 'afFeatSheet') return;
          // If sibling has content matching popup style (close btn + heading + list), hide it
          var hasClose = sib.querySelector('[class*="close"], button') && sib.querySelector('h3,h4');
          var hasList  = sib.querySelector('ul li');
          if (hasClose || (hasList && sib.querySelector('h3,h4'))) {
            sib.style.setProperty('display', 'none', 'important');
          }
        });
      }
    }
  }
  document.addEventListener('DOMContentLoaded', hideStaticPopupContent);
  window.addEventListener('load', hideStaticPopupContent);
  setTimeout(hideStaticPopupContent, 500);
}());
</script>
<?php }, 10003);

/* ============================================================
   Hero Slider mobile fix — the parent column of the slider
   widget is too narrow; walk up the DOM and widen all ancestors,
   then patch the Swiper instance and force slide widths.
   ============================================================ */
add_action('wp_footer', function() { ?>
<script>
(function(){
  if (window.innerWidth > 768) return;

  function doFix() {
    var vw = window.innerWidth;
    var widget = document.querySelector('.elementor-element-0971963');
    if (!widget) return;

    // 1. Full-bleed escape: pull widget to 100vw regardless of parent column width
    widget.style.setProperty('width', '100vw', 'important');
    widget.style.setProperty('max-width', '100vw', 'important');
    widget.style.setProperty('margin-left', 'calc(50% - 50vw)', 'important');
    widget.style.setProperty('position', 'relative', 'important');
    // Also widen all ancestors so overflow:hidden doesn't clip
    var el = widget.parentElement;
    while (el && el !== document.body) {
      var ow = el.offsetWidth;
      if (ow < vw) {
        el.style.setProperty('width', '100%', 'important');
        el.style.setProperty('max-width', '100%', 'important');
        el.style.setProperty('overflow', 'visible', 'important');
      }
      el = el.parentElement;
    }

    // 2. Find the Swiper container (walk from slide upward, find .swiper property)
    var slide = widget.querySelector('.swiper-slide');
    if (slide) {
      var node = slide.parentElement;
      while (node && node !== document.body) {
        if (node.swiper) {
          var sw = node.swiper;
          node.style.setProperty('width', vw + 'px', 'important');
          sw.params.slidesPerView = 1;
          sw.params.spaceBetween = 0;
          sw.params.centeredSlides = false;
          if (sw.originalParams) {
            sw.originalParams.slidesPerView = 1;
            sw.originalParams.spaceBetween = 0;
            sw.originalParams.centeredSlides = false;
          }
          sw.update();
          break;
        }
        node = node.parentElement;
      }
    }

    // 3. Force every slide to exactly viewport width
    widget.querySelectorAll('.swiper-slide').forEach(function(s) {
      s.style.setProperty('width', vw + 'px', 'important');
      s.style.setProperty('min-width', vw + 'px', 'important');
      s.style.setProperty('flex-shrink', '0', 'important');
    });
    // Note: attachSlideLock (above) already watches these slides via MutationObserver.
    // Do NOT add a second observer here — parseInt('100vw')=100 causes an infinite loop
    // between two observers watching the same slides.
  }

  window.addEventListener('load', doFix);
  setTimeout(doFix, 500);
  setTimeout(doFix, 1500);
  setTimeout(doFix, 3000);
}());
</script>
<?php }, 10004);


/* ============================================================
   SHOP / CATEGORY PAGE — comprehensive card fix via JS
   JS inline styles beat all CSS (including theme overrides).
   ============================================================ */
add_action('wp_footer', function() {
  $is_shop = is_product_category() || is_shop() || is_tax('product_cat');
  if (!$is_shop) return;
?><script id="af-page-class-tweaks" src="<?php echo esc_url(af_static_url('page-class-tweaks.js')); ?>" data-no-optimize="1"></script>
<?php
}, 200);

// ─────────────────────────────────────────────────────────────
// "Try It On Your Wall" — upload-photo AR mockup (no plugin, no cost)
// ─────────────────────────────────────────────────────────────

// Try-On-Wall CSS — loaded site-wide (not just product pages) so the button
// is styled inside the Quick View modal too (Quick View is injected on the
// homepage/shop where is_product() is false). The CSS is fully class-scoped
// (.af-arw-*), so it has no effect on any other markup.
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('af-ar-wall', get_stylesheet_directory_uri() . '/assets/css/ar-wall.css', array(), '1.2.0');
}, 25);

// "Try It On Your Wall" button under Add to Cart — now LINKS to the full
// standalone /try-on-wall/ page with this product pre-selected.
add_action('woocommerce_after_add_to_cart_button', function() {
    $product = af_wc_product();
    if (!$product) return;
    $url = add_query_arg('tow', $product->get_id(), home_url('/try-on-wall/'));
    ?>
    <a href="<?php echo esc_url($url); ?>" class="af-arw-btn">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px;flex-shrink:0">
            <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/>
        </svg>
        Try It On Your Wall
    </a>
    <?php
}, 15);

// Render the AR modal in the footer (once, on product pages)
add_action('wp_footer', function() {
    return; // DISABLED: the button now links to the full /try-on-wall/ page.
    if (!function_exists('is_product') || !is_product()) return;
    // wp_footer often runs after WooCommerce clears the global $product,
    // so fetch it from the queried page instead — otherwise the modal
    // never renders and the button has nothing to open.
    $product = wc_get_product(get_queried_object_id());
    if (!($product instanceof WC_Product)) { $product = af_wc_product($product); }
    if (!$product) return;

    // Build size options from the product's Size attribute (parse inch width)
    $size_opts = array();
    $size_terms = $product->get_attribute('pa_size');
    if (!$size_terms) $size_terms = $product->get_attribute('size');
    if ($size_terms) {
        foreach (array_map('trim', explode(',', $size_terms)) as $s) {
            if (preg_match('/(\d+(?:\.\d+)?)\s*[x×*]\s*(\d+(?:\.\d+)?)/i', $s, $m)) {
                $w = floatval($m[1]);
                // If dimensions are in feet, convert to inches
                if (stripos($s, 'ft') !== false || stripos($s, 'feet') !== false) $w *= 12;
                $size_opts[$w] = $s;
            }
        }
    }
    if (empty($size_opts)) {
        $size_opts = array(24 => '24 inches wide', 36 => '36 inches wide', 48 => '48 inches wide', 60 => '60 inches wide');
    }
    ?>
    <div id="af-arw-overlay" class="af-arw-overlay" data-arw-close>
      <div class="af-arw-modal">
        <div class="af-arw-head">
          <h3>Try It On Your Wall</h3>
          <button type="button" class="af-arw-close" data-arw-close aria-label="Close">&times;</button>
        </div>
        <div class="af-arw-body">
          <div class="af-arw-stage-wrap">
            <div id="af-arw-stage" class="af-arw-stage">
              <img id="af-arw-wall" class="af-arw-wall" alt="" style="display:none">
              <div id="af-arw-placeholder" class="af-arw-placeholder">
                📷 Upload a photo of your wall to preview this artwork in your space. Then drag to position and adjust the size.
              </div>
              <img id="af-arw-art" class="af-arw-art" alt="Artwork preview" style="display:none" crossorigin="anonymous">
            </div>
          </div>
          <div class="af-arw-panel">
            <div class="af-arw-field">
              <label>1. Your wall photo</label>
              <label class="af-arw-upload">
                <input type="file" id="af-arw-wall-file" accept="image/*" hidden>
                ⬆ Upload wall photo
              </label>
            </div>
            <div class="af-arw-field">
              <label>2. Artwork size</label>
              <select id="af-arw-size" class="af-arw-size-select">
                <?php foreach ($size_opts as $w => $label): ?>
                  <option value="<?php echo esc_attr($w); ?>"><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="af-arw-field">
              <label>3. Your wall width (inches)</label>
              <input type="number" id="af-arw-wallwidth" class="af-arw-size-select" placeholder="e.g. 120" min="12" step="1">
              <p class="af-arw-hint">Enter the real width of the wall in the photo, so the artwork appears true-to-scale.</p>
            </div>
            <div class="af-arw-field">
              <label>Fine-tune size</label>
              <input type="range" id="af-arw-scale" min="50" max="150" value="100">
            </div>
            <div class="af-arw-actions">
              <button type="button" class="af-arw-reset" id="af-arw-reset">Reset</button>
              <button type="button" class="af-arw-save" id="af-arw-save">Save preview</button>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php
}, 50);

// ─────────────────────────────────────────────────────────────
// PHASE 4 — 4-column site footer (brand · shop · service · company).
// Theme-code footer, appended sitewide. Reversible via this block.
// ─────────────────────────────────────────────────────────────
add_action('wp_footer', function() {
    if (is_admin()) return;
    // DISABLED: the theme's own Elementor footer is used instead, to avoid a
    // duplicate/stacked footer. Kept here (reversible) in case it's wanted later.
    return;

    // Column 2: top shop categories (by product count), fall back gracefully
    $shop_cats = get_terms(array(
        'taxonomy'=>'product_cat','hide_empty'=>true,'number'=>7,
        'orderby'=>'count','order'=>'DESC','exclude'=>array(get_option('default_product_cat')),
    ));
    ?>
    <footer class="af-footer" aria-label="Site footer">
      <div class="af-footer-inner">

        <div class="af-fcol af-fcol-brand">
          <h4 class="af-f-logo">The Art Framer</h4>
          <p class="af-f-blurb">Premium digital canvas wall art &amp; framed prints — spiritual, cultural, and modern pieces crafted with archival, fade-resistant inks. Ready to hang, made to inspire.</p>
          <ul class="af-f-contact">
            <li>📍 Delaware, USA</li>
            <li>📞 <a href="tel:+16104707280">+1 (610) 470-7280</a></li>
            <li>✉️ <a href="mailto:theartframer136@gmail.com">theartframer136@gmail.com</a></li>
          </ul>
          <div class="af-f-social">
            <a href="https://www.facebook.com/" target="_blank" rel="noopener" aria-label="Facebook">
              <svg viewBox="0 0 24 24" fill="currentColor"><path d="M22 12a10 10 0 10-11.5 9.9v-7H8v-2.9h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.5h-1.3c-1.2 0-1.6.8-1.6 1.6v1.8H16l-.4 2.9h-2.1v7A10 10 0 0022 12z"/></svg>
            </a>
            <a href="https://www.instagram.com/" target="_blank" rel="noopener" aria-label="Instagram">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg>
            </a>
            <a href="https://wa.me/16104707280" target="_blank" rel="noopener" aria-label="WhatsApp">
              <svg viewBox="0 0 24 24" fill="currentColor"><path d="M.057 24l1.687-6.163a11.867 11.867 0 01-1.587-5.945C.16 5.335 5.495 0 12.05 0a11.82 11.82 0 018.413 3.488 11.82 11.82 0 013.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 01-5.688-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884a9.86 9.86 0 001.51 5.26l-.999 3.648 3.978-1.005z"/></svg>
            </a>
          </div>
        </div>

        <div class="af-fcol">
          <h5 class="af-f-title">Shop</h5>
          <ul class="af-f-links">
            <?php if (!is_wp_error($shop_cats) && $shop_cats): foreach ($shop_cats as $c): ?>
              <li><a href="<?php echo esc_url(get_term_link($c)); ?>"><?php echo esc_html($c->name); ?></a></li>
            <?php endforeach; endif; ?>
            <li><a href="/shop/">View All Products</a></li>
          </ul>
        </div>

        <div class="af-fcol">
          <h5 class="af-f-title">Customer Service</h5>
          <ul class="af-f-links">
            <li><a href="/help-support/">Help &amp; Support</a></li>
            <li><a href="/shipping-delivery/">Shipping &amp; Delivery</a></li>
            <li><a href="/returns-exchanges/">Returns &amp; Exchanges</a></li>
            <li><a href="/refund-policy/">Refund Policy</a></li>
            <li><a href="/track-your-order/">Track Your Order</a></li>
            <li><a href="/low-price-guarantee/">Low Price Guarantee</a></li>
            <li><a href="/gift-cards/">Gift Cards</a></li>
            <li><a href="/faqs/">FAQs</a></li>
          </ul>
        </div>

        <div class="af-fcol">
          <h5 class="af-f-title">Company</h5>
          <ul class="af-f-links">
            <li><a href="/about/">About Us</a></li>
            <li><a href="/contact/">Contact</a></li>
            <li><a href="/wholesale-corporate/">Wholesale &amp; Corporate</a></li>
            <li><a href="/artists/">Artists &amp; Creators</a></li>
            <li><a href="/reviews-press/">Reviews &amp; Press</a></li>
            <li><a href="/refer-a-friend/">Refer a Friend</a></li>
            <li><a href="/affiliates/">Affiliates</a></li>
            <li><a href="/exhibitions-events/">Exhibitions &amp; Events</a></li>
            <li><a href="/privacy-policy/">Privacy Policy</a></li>
            <li><a href="/terms-conditions/">Terms &amp; Conditions</a></li>
            <li><a href="/content-ethics-policy/">Content &amp; Ethics</a></li>
            <li><a href="/legal-imprint/">Legal Imprint</a></li>
          </ul>
          <div class="af-f-news">
            <h5 class="af-f-title">Newsletter</h5>
            <?php if (shortcode_exists('mc4wp_form')): ?>
              <?php echo do_shortcode('[mc4wp_form]'); ?>
            <?php else: ?>
              <form class="af-f-newsform" method="post"
                    data-ajax="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
                    data-nonce="<?php echo esc_attr(wp_create_nonce('af_nl_subscribe')); ?>">
                <input type="email" name="af_nl_email" placeholder="Your email" required aria-label="Email for newsletter">
                <input type="text" name="af_nl_hp" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;">
                <button type="submit">Join</button>
                <?php if (function_exists('af_turnstile_box')) af_turnstile_box('newsletter', true, true); // Cloudflare's check: drawn on use, shown only when a tick is needed ?>
              </form>
              <p class="af-f-newsmsg" role="status" aria-live="polite" style="display:none;margin:8px 0 0;font-size:12.5px;"></p>
            <?php endif; ?>
          </div>
        </div>

      </div>
      <div class="af-footer-bottom">
        <p>&copy; <?php echo date('Y'); ?> The Art Framer &middot; theartframer.us &middot; All rights reserved.</p>
        <p class="af-f-pay">🔒 Secure checkout &middot; Square &middot; Zelle &middot; COD</p>
      </div>
    </footer>
    <style>
    .af-footer{background:#141414;color:#cfcfcf;padding:48px 16px 0;border-top:3px solid #c9a84c;}
    .af-footer-inner{max-width:1240px;margin:0 auto;display:grid;grid-template-columns:1.6fr 1fr 1fr 1.4fr;gap:34px;}
    .af-fcol-brand{max-width:340px;}
    .af-f-logo{font-size:22px;font-weight:800;color:#fff;margin:0 0 12px;letter-spacing:.02em;}
    .af-f-blurb{font-size:13px;line-height:1.7;color:#a8a8a8;margin:0 0 16px;}
    .af-f-contact{list-style:none;margin:0 0 16px;padding:0;display:flex;flex-direction:column;gap:7px;}
    .af-f-contact li{font-size:13px;color:#b8b8b8;}
    .af-f-contact a{color:#b8b8b8;text-decoration:none;}
    .af-f-contact a:hover{color:#c9a84c;}
    .af-f-social{display:flex;gap:12px;}
    .af-f-social a{width:36px;height:36px;border-radius:50%;background:#242424;display:flex;align-items:center;justify-content:center;color:#cfcfcf;transition:background .2s,color .2s;}
    .af-f-social a:hover{background:#c9a84c;color:#141414;}
    .af-f-social svg{width:18px;height:18px;}
    .af-f-title{font-size:14px;font-weight:700;color:#fff;margin:0 0 16px;text-transform:uppercase;letter-spacing:.06em;}
    .af-f-links{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:10px;}
    .af-f-links a{font-size:13px;color:#a8a8a8;text-decoration:none;transition:color .2s;}
    .af-f-links a:hover{color:#c9a84c;padding-left:3px;}
    .af-f-news{margin-top:22px;}
    .af-f-newsform{display:flex;gap:8px;}
    .af-f-newsform input{flex:1;min-width:0;padding:9px 11px;border:1px solid #333;border-radius:6px;background:#1e1e1e;color:#eee;font-size:13px;}
    .af-f-newsform button{padding:9px 16px;border:none;border-radius:6px;background:#c9a84c;color:#141414;font-weight:700;font-size:13px;cursor:pointer;}
    .af-f-newsform button:hover{background:#dcb85a;}
    .af-footer-bottom{max-width:1240px;margin:38px auto 0;border-top:1px solid #2a2a2a;padding:18px 0;display:flex;flex-wrap:wrap;justify-content:space-between;gap:8px;}
    .af-footer-bottom p{margin:0;font-size:12px;color:#8a8a8a;}
    @media(max-width:900px){
      .af-footer-inner{grid-template-columns:1fr 1fr;gap:28px;}
      .af-fcol-brand{grid-column:1 / -1;max-width:none;}
    }
    @media(max-width:560px){
      .af-footer-inner{grid-template-columns:1fr;}
      .af-footer-bottom{flex-direction:column;text-align:center;}
    }
    </style>
    <?php
}, 300);

// ─────────────────────────────────────────────────────────────
// PHASE 2 — Header: top utility/help-support bar (Layer 1) + sticky
// Additive; injected above the theme header. Reversible via this block.
// ─────────────────────────────────────────────────────────────
// The visible top utility bar (rotating message + Help + phone + currency) has
// been removed. Two things it hosted are kept: the country selector — rendered
// hidden here so the relocation script in its shortcode can still move it
// beside the social icons — and the sticky-header-on-scroll behaviour.
add_action('wp_footer', function() {
    if (is_admin()) return;
    ?>
    <div class="af-ub-hidden-host" aria-hidden="true" style="display:none !important;">
      <?php echo do_shortcode('[af_country_selector]'); ?>
    </div>
    <script>
    (function(){
      // Sticky header on scroll — add class to the theme header
      var header = document.querySelector(
        'header.site-header, #masthead, .site-header, header#header, ' +
        '.postero-header, .header-main, [class*="site-header"], header[class*="header"]'
      );
      if (header) {
        window.addEventListener('scroll', function(){
          var y = window.pageYOffset || document.documentElement.scrollTop;
          if (y > 120) header.classList.add('af-header-stuck');
          else header.classList.remove('af-header-stuck');
        }, { passive: true });
      }
    })();
    </script>
    <style>
    /* Sticky header polish (kept from the old utility-bar block) */
    .af-header-stuck{
      position:sticky !important;top:0 !important;z-index:9999 !important;
      box-shadow:0 4px 18px rgba(0,0,0,.12) !important;
      animation:af-slidedown .3s ease !important;
    }
    @keyframes af-slidedown{from{transform:translateY(-6px);opacity:.85;}to{transform:translateY(0);opacity:1;}}
    /* The relocated selector must stay visible even though its host is hidden. */
    .af-cty.af-cty--inline{display:inline-flex !important;}
    </style>
    <?php
}, 5);

// ─────────────────────────────────────────────────────────────
// PHASE 3 — Homepage: floating quick-access panel (sitewide) +
// trust badges bar (front page). Additive; reversible via this block.
// ─────────────────────────────────────────────────────────────

// 3a. Floating quick-access side panel — WhatsApp, Wishlist, Track, Top
add_action('wp_footer', function() {
    if (is_admin()) return;
    ?>
    <div class="af-quickpanel" aria-label="Quick access">
      <a class="af-qp-btn af-qp-wa" href="https://wa.me/16104707280" target="_blank" rel="noopener" data-tip="Chat on WhatsApp" aria-label="Chat on WhatsApp">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M.057 24l1.687-6.163a11.867 11.867 0 01-1.587-5.945C.16 5.335 5.495 0 12.05 0a11.82 11.82 0 018.413 3.488 11.82 11.82 0 013.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 01-5.688-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884a9.86 9.86 0 001.51 5.26l-.999 3.648 3.978-1.005zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.767.967-.94 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.71.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
      </a>
      <a class="af-qp-btn af-qp-wl" href="/wishlist/" data-tip="Wishlist" aria-label="Wishlist">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
        <!-- filled in by JS, never by PHP: this page is full-page cached, so a
             server-rendered count would show one visitor's wishlist to everyone -->
        <span class="af-qp-count" id="af-qp-wl-count" hidden>0</span>
      </a>
      <a class="af-qp-btn af-qp-tr" href="/track-your-order/" data-tip="Track Order" aria-label="Track Order">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
      </a>
      <button class="af-qp-btn af-qp-top" id="af-qp-top" data-tip="Back to top" aria-label="Back to top">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 15l-6-6-6 6"/></svg>
      </button>
    </div>
    <script>
    (function(){
      var top = document.getElementById('af-qp-top');
      if (top) top.addEventListener('click', function(){ window.scrollTo({top:0,behavior:'smooth'}); });
      window.addEventListener('scroll', function(){
        var y = window.pageYOffset||document.documentElement.scrollTop;
        if (top) top.style.opacity = y>400 ? '1':'0';
        if (top) top.style.pointerEvents = y>400 ? 'auto':'none';
      }, {passive:true});

      // ── one WhatsApp button, not two ──────────────────────────────────
      // A plugin drops its own floating WhatsApp bubble into the same corner
      // as ours. Rather than trust a list of plugin class names, find it by
      // what it is: a WhatsApp link inside a fixed-position element that is
      // not our quick panel. Anything in the page flow (footer, contact,
      // product share) is left alone.
      function dedupeWhatsApp(){
        var links = document.querySelectorAll(
          'a[href*="wa.me"],a[href*="api.whatsapp.com"],a[href*="whatsapp://"],a[href*="web.whatsapp.com"]');
        Array.prototype.forEach.call(links, function(a){
          if (a.closest('.af-quickpanel')) return;          // ours — keep
          var n = a, floating = null;
          for (var i = 0; i < 8 && n && n !== document.body; i++) {
            var pos = getComputedStyle(n).position;
            if (pos === 'fixed' || pos === 'sticky') { floating = n; break; }
            n = n.parentElement;
          }
          if (!floating) return;                            // in-page link — keep
          if (floating.closest('.af-quickpanel') || floating.id === 'af-chat') return;

          // A floating bar can hold far more than WhatsApp: the site has a
          // slide-out panel whose toggle opens a bar of several options, and
          // WhatsApp is only one row of it. Hiding the whole container to
          // remove one duplicate took that entire panel with it — the toggle
          // still showed, but the bar it opened was display:none, so clicking
          // it appeared to do nothing. Remove the duplicate itself and leave
          // everything else in the bar alone.
          var others = floating.querySelectorAll('a[href],button');
          var foreign = 0;
          Array.prototype.forEach.call(others, function (el) {
            if (el === a) return;
            if (/wa\.me|whatsapp/i.test(el.getAttribute('href') || '')) return;
            foreign++;
          });
          if (foreign > 0) {
            // hide just this entry: the link, or the list item wrapping it
            var item = a.closest('li,.item,[class*="item"]') || a;
            if (item === floating || floating.contains(item) === false) item = a;
            item.style.setProperty('display', 'none', 'important');
            return;
          }
          floating.style.setProperty('display', 'none', 'important');
        });
      }

      // ── wishlist count on the floating button ─────────────────────────
      // Computed in the browser, never in PHP: these pages are full-page
      // cached, so a server-rendered number would hand one visitor's wishlist
      // to every other visitor. Reads the plugin's own cookie first, then
      // falls back to whatever counter the header is already showing.
      function wishlistCount(){
        var m = document.cookie.match(/(?:^|;\s*)yith_wcwl_products=([^;]*)/);
        if (m && m[1]) {
          try {
            var arr = JSON.parse(decodeURIComponent(m[1].replace(/\+/g, ' ')));
            if (Array.isArray(arr)) return arr.length;
            if (arr && typeof arr === 'object') return Object.keys(arr).length;
          } catch(e){}
        }
        var el = document.querySelector(
          '.wishlist-count,.wishlist_count,.yith-wcwl-items-count,[class*="wishlist"] .count,[class*="wishlist"] .counter');
        if (el) {
          var n2 = parseInt((el.textContent||'').replace(/[^\d]/g, ''), 10);
          if (!isNaN(n2)) return n2;
        }
        return 0;
      }
      function paintWishlist(){
        var badge = document.getElementById('af-qp-wl-count');
        if (!badge) return;
        var n = wishlistCount();
        badge.textContent = n > 99 ? '99+' : String(n);
        if (n > 0) badge.removeAttribute('hidden'); else badge.setAttribute('hidden','');
      }

      function refresh(){ dedupeWhatsApp(); paintWishlist(); }
      refresh();
      // plugin widgets and counters arrive late and change as the visitor
      // adds items, so re-run on the events that matter rather than once
      window.addEventListener('load', refresh);
      [400, 1200, 2500].forEach(function(t){ setTimeout(refresh, t); });
      ['added_to_wishlist','removed_from_wishlist','yith_wcwl_reload_fragments','added_to_cart']
        .forEach(function(ev){ document.body.addEventListener(ev, paintWishlist); });
      if (window.jQuery) {
        jQuery(document.body).on('added_to_wishlist removed_from_wishlist yith_wcwl_reload_fragments', paintWishlist);
      }
      document.addEventListener('click', function(e){
        if (e.target.closest('[class*="wishlist"],[class*="wcwl"]')) setTimeout(paintWishlist, 900);
      }, true);
    })();
    </script>
    <style>
    .af-quickpanel{position:fixed;right:16px;bottom:20px;z-index:9998;display:flex;flex-direction:column;gap:12px;}
    .af-qp-btn{
      width:48px;height:48px;border-radius:50%;border:none;cursor:pointer;
      /* padding:0 !important — the theme's global button padding (14px 40px) otherwise
         collapses the border-box content area to 0, hiding the SVG icon. */
      padding:0 !important;
      display:flex;align-items:center;justify-content:center;
      box-shadow:0 4px 14px rgba(0,0,0,.22);transition:transform .2s,box-shadow .2s;
      position:relative;color:#fff;text-decoration:none;
    }
    /* Live-chat launcher (#af-chat is injected as a site snippet, not from this theme):
       same global-button-padding bug turns the 56px circle into an icon-less black oval. */
    .af-chat-bub{padding:0 !important;}
    .af-qp-btn:hover{transform:scale(1.08);box-shadow:0 6px 20px rgba(0,0,0,.3);}
    .af-qp-btn svg{width:24px;height:24px;}
    .af-qp-wa{background:#25d366;}
    .af-qp-wl{background:#c9a84c;}
    .af-qp-tr{background:#1a1a1a;}
    .af-qp-top{background:#555;opacity:0;pointer-events:none;transition:opacity .3s,transform .2s;}
    .af-qp-btn[data-tip]:hover::after{
      content:attr(data-tip);position:absolute;right:58px;top:50%;transform:translateY(-50%);
      background:#1a1a1a;color:#fff;font-size:12px;white-space:nowrap;padding:5px 10px;border-radius:6px;
    }
    /* wishlist count badge */
    .af-qp-count{
      position:absolute;top:-4px;right:-4px;min-width:20px;height:20px;padding:0 5px;
      border-radius:999px;background:#1a1a1a;color:#fff;font-size:11px;font-weight:800;
      line-height:20px;text-align:center;box-sizing:border-box;box-shadow:0 2px 6px rgba(0,0,0,.3);
      pointer-events:none;
    }
    .af-qp-count[hidden]{display:none !important;}
    /* A second, plugin-supplied WhatsApp bubble sits in the same corner as ours.
       Hidden by class where the common plugins are known; the script below also
       catches any other floating WhatsApp widget by behaviour. */
    .ht-ctc.ht-ctc-chat, .joinchat, .wa__floating_btn, .wa-chat-box-wrapper,
    .whatsapp-chat-widget, #whatsapp-chat-widget, .wt-whatsapp-float {
      display:none !important;
    }
    @media(max-width:600px){
      /* bottom:14px put this whole stack UNDERNEATH the fixed bottom
         navigation bar — WhatsApp, wishlist, delivery and back-to-top were all
         simply invisible on a phone. Measured 2026-09-10 at 420px. It now
         clears the bar, level with the chat launcher on the other side, and
         safe-area-inset keeps it off the home indicator on an iPhone. */
      .af-quickpanel{right:10px;bottom:calc(72px + env(safe-area-inset-bottom,0px));gap:10px;}
      .af-qp-btn{width:44px;height:44px;box-shadow:0 4px 14px rgba(0,0,0,.26);}
      .af-qp-btn svg{width:21px;height:21px;}
      .af-qp-btn[data-tip]:hover::after{display:none;}
    }
    @media(max-width:380px){
      .af-quickpanel{right:8px;bottom:calc(68px + env(safe-area-inset-bottom,0px));gap:8px;}
      .af-qp-btn{width:40px;height:40px;}
      .af-qp-btn svg{width:19px;height:19px;}
    }
    </style>
    <?php
}, 20);

// 3b. Trust badges bar — front page only, injected above the footer
add_action('wp_footer', function() {
    if (!is_front_page()) return;
    ?>
    <div class="af-trustbar">
      <div class="af-trust-inner">
        <div class="af-trust-item"><span class="af-trust-ico">🚚</span><div><strong><?php echo esc_html(af_shipping_copy()['label']); ?></strong><small><?php echo esc_html(af_shipping_copy()['cost'] !== '' ? 'Shipping ' . af_shipping_copy()['cost'] : 'Cost shown at checkout'); ?></small></div></div>
        <div class="af-trust-item"><span class="af-trust-ico">🎨</span><div><strong>Archival Quality</strong><small>Fade-resistant inks</small></div></div>
        <div class="af-trust-item"><span class="af-trust-ico">🔒</span><div><strong>Secure Checkout</strong><small>Encrypted payments</small></div></div>
        <div class="af-trust-item"><span class="af-trust-ico">↩️</span><div><strong>Easy Returns</strong><small>7-day policy</small></div></div>
        <div class="af-trust-item"><span class="af-trust-ico">🖼️</span><div><strong>Try On Your Wall</strong><small>Preview before buying</small></div></div>
      </div>
    </div>
    <script>
    (function(){
      // Place the trust bar just before the footer/policy bar for a natural flow
      var bar = document.querySelector('.af-trustbar');
      var anchor = document.querySelector('.af-footer') || document.querySelector('footer');
      if (bar && anchor && anchor.parentNode) anchor.parentNode.insertBefore(bar, anchor);
    })();
    </script>
    <style>
    .af-trustbar{background:#faf7ef;border-top:1px solid #ece4cf;border-bottom:1px solid #ece4cf;padding:22px 16px;}
    .af-trust-inner{max-width:1200px;margin:0 auto;display:flex;flex-wrap:wrap;gap:20px;justify-content:space-between;}
    .af-trust-item{display:flex;align-items:center;gap:12px;flex:1 1 180px;min-width:170px;}
    .af-trust-ico{font-size:26px;line-height:1;}
    .af-trust-item strong{display:block;font-size:14px;color:#1a1a1a;}
    .af-trust-item small{display:block;font-size:12px;color:#777;}
    @media(max-width:600px){
      .af-trust-inner{gap:14px;}
      .af-trust-item{flex:1 1 45%;min-width:140px;}
    }
    </style>
    <?php
}, 25);

// ─────────────────────────────────────────────────────────────
// PHASE 5 — Category/Listing pages: filter & sort toolbar,
// subcategory chips, price filter, AR "Try on Wall" hooks on cards.
// Uses native WooCommerce params so filtering reliably works.
// ─────────────────────────────────────────────────────────────

// 5a. Enqueue AR assets on shop/category pages too (for card AR links -> product)
add_action('wp_enqueue_scripts', function() {
    if (function_exists('is_shop') && (is_shop() || is_product_category() || is_product_tag())) {
        // AR modal lives on the product page; listing only needs the toolbar CSS.
    }
}, 26);

// 5b. Filter / sort toolbar above the product loop
add_action('woocommerce_before_shop_loop', function() {
    if (is_admin()) return;

    // Subcategory chips: children of current category, else top-level categories
    $current_id = 0;
    if (is_product_category()) { $obj = get_queried_object(); $current_id = $obj->term_id ?? 0; }
    $chips = get_terms(array(
        'taxonomy'=>'product_cat','hide_empty'=>true,'parent'=>$current_id,
        'orderby'=>'count','order'=>'DESC','number'=>12,
    ));

    // Filterable global attributes.
    //
    // These are canvas attributes and they were fetched on every archive,
    // including Digital Downloads — where the recording showed a sidebar
    // offering "2.5×3 Ft (30×36 In)" and frame colours for a JPG. A file has
    // none of the three, so on those archives the groups are not narrowed, they
    // are dropped: an empty filter group is noise, and a populated one that
    // cannot apply is worse.
    $digital_archive = false;
    if (is_product_category()) {
        $obj = get_queried_object();
        if ($obj && !empty($obj->slug)) {
            $chain = array($obj->slug);
            foreach (get_ancestors($obj->term_id, 'product_cat') as $aid) {
                $anc = get_term($aid, 'product_cat');
                if ($anc && !is_wp_error($anc)) $chain[] = $anc->slug;
            }
            $digital_archive = (bool) array_intersect($chain, af_digital_cat_slugs());
        }
    }
    $sizes  = $digital_archive ? array() : get_terms(array('taxonomy'=>'pa_size','hide_empty'=>true,'number'=>30));
    $colors = $digital_archive ? array() : get_terms(array('taxonomy'=>'pa_colors','hide_empty'=>true,'number'=>12));
    $frames = $digital_archive ? array() : get_terms(array('taxonomy'=>'pa_frame','hide_empty'=>true,'number'=>12));
    $tags   = get_terms(array('taxonomy'=>'product_tag','hide_empty'=>true,'orderby'=>'count','order'=>'DESC','number'=>25));

    $base_url = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
    // Current price bounds
    $cur_min = isset($_GET['min_price']) ? (float)$_GET['min_price'] : '';
    $cur_max = isset($_GET['max_price']) ? (float)$_GET['max_price'] : '';
    // Helper: current URL params minus paging, for merging filter links
    $keep = $_GET;
    unset($keep['paged']);
    // Build a link that ADDS a filter param while keeping the others
    $flink = function($key, $val) use ($keep) {
        $q = $keep; $q[$key] = $val; $q['query_type_'.str_replace('filter_','',$key)] = 'or';
        return '?' . http_build_query($q);
    };
    $has_filters = !empty($_GET['filter_colors']) || !empty($_GET['filter_size']) || !empty($_GET['filter_frame']) || !empty($_GET['orientation']) || $cur_min !== '' || $cur_max !== '';
    ?>
    <div class="af-listing-toolbar">
      <?php if (!is_wp_error($chips) && $chips): ?>
      <div class="af-lt-chips" role="navigation" aria-label="Categories">
        <a class="af-chip<?php echo $current_id? '' : ' af-chip-active'; ?>" href="/shop/">All</a>
        <?php foreach ($chips as $c): ?>
          <a class="af-chip" href="<?php echo esc_url(get_term_link($c)); ?>"><?php echo esc_html($c->name); ?> <span><?php echo (int)$c->count; ?></span></a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div class="af-lt-controls">
        <!-- Price -->
        <div class="af-lt-drop">
          <button type="button" class="af-lt-dbtn">Price ▾</button>
          <div class="af-lt-menu af-lt-price">
            <form method="get" action="">
              <?php foreach ($keep as $k=>$v){ if(in_array($k,array('min_price','max_price'),true)) continue; echo '<input type="hidden" name="'.esc_attr($k).'" value="'.esc_attr(is_array($v)?reset($v):$v).'">'; } ?>
              <div class="af-price-row">
                <input type="number" name="min_price" placeholder="Min $" value="<?php echo esc_attr($cur_min); ?>" min="0">
                <span>–</span>
                <input type="number" name="max_price" placeholder="Max $" value="<?php echo esc_attr($cur_max); ?>" min="0">
              </div>
              <button type="submit" class="af-price-apply">Apply</button>
            </form>
          </div>
        </div>

        <?php if (!is_wp_error($sizes) && $sizes): ?>
        <div class="af-lt-drop">
          <button type="button" class="af-lt-dbtn">Size ▾</button>
          <div class="af-lt-menu">
            <?php foreach ($sizes as $t): $soos = function_exists('af_filter_label_is_oos') && af_filter_label_is_oos($t->name); ?>
              <?php if ($soos) continue; // not on sale: not on the menu ?>
              <a rel="nofollow" href="<?php echo esc_url($flink('filter_size',$t->slug)); ?>"><?php echo esc_html($t->name); ?></a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php if (!is_wp_error($colors) && $colors): ?>
        <div class="af-lt-drop">
          <button type="button" class="af-lt-dbtn">Frame Color ▾</button>
          <div class="af-lt-menu">
            <?php foreach ($colors as $t): ?>
              <a rel="nofollow" href="<?php echo esc_url($flink('filter_colors',$t->slug)); ?>"><?php echo esc_html($t->name); ?></a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php if (!is_wp_error($frames) && $frames): ?>
        <div class="af-lt-drop">
          <button type="button" class="af-lt-dbtn">Frame Type ▾</button>
          <div class="af-lt-menu">
            <?php foreach ($frames as $t): $foos = function_exists('af_filter_label_is_oos') && af_filter_label_is_oos($t->name); ?>
              <?php if ($foos) continue; // not on sale: not on the menu ?>
              <a rel="nofollow" href="<?php echo esc_url($flink('filter_frame',$t->slug)); ?>"><?php echo esc_html($t->name); ?></a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php if (function_exists('af_orientation_current')):
          $cur_o = af_orientation_current();
          $olink = function($val) use ($keep) { $q = $keep; $q['orientation'] = $val; return '?' . http_build_query($q); };
        ?>
        <div class="af-lt-drop" data-af-orient="1">
          <button type="button" class="af-lt-dbtn"><?php echo $cur_o ? esc_html(ucfirst($cur_o)) : 'Orientation'; ?> ▾</button>
          <div class="af-lt-menu">
            <a href="<?php echo esc_url($olink('portrait')); ?>">▯ Portrait</a>
            <a href="<?php echo esc_url($olink('landscape')); ?>">▭ Landscape</a>
            <a href="<?php echo esc_url($olink('square')); ?>">□ Square</a>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($has_filters): ?>
          <a class="af-lt-clear" href="<?php echo esc_url($base_url); ?>">✕ Clear filters</a>
        <?php endif; ?>
      </div>

      <?php if (!is_wp_error($tags) && $tags):
        $active_tag = isset($_GET['product_tag']) ? sanitize_title($_GET['product_tag']) : '';
        // Merge product_tag into current params (keeps other filters)
        $tlink = function($slug) use ($keep){ $q=$keep; $q['product_tag']=$slug; return '?'.http_build_query($q); };
        $clear_tag = $keep; unset($clear_tag['product_tag']);
      ?>
      <div class="af-lt-tags" role="navigation" aria-label="Tags">
        <span class="af-lt-tagslabel">Tags:</span>
        <?php foreach ($tags as $t): $on = ($active_tag === $t->slug); ?>
          <a class="af-tag-btn<?php echo $on?' active':''; ?>"
             href="<?php echo esc_url($on ? '?'.http_build_query($clear_tag) : $tlink($t->slug)); ?>">
            #<?php echo esc_html($t->name); ?>
          </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <script>
    (function(){
      document.querySelectorAll('.af-lt-dbtn').forEach(function(b){
        b.addEventListener('click', function(e){
          e.stopPropagation();
          var m = this.nextElementSibling;
          document.querySelectorAll('.af-lt-menu.open').forEach(function(o){ if(o!==m) o.classList.remove('open'); });
          m.classList.toggle('open');
        });
      });
      document.addEventListener('click', function(){ document.querySelectorAll('.af-lt-menu.open').forEach(function(o){ o.classList.remove('open'); }); });
    })();
    </script>
    <?php
}, 5);

// 5c. Add "Try on Wall" AR hook button on each listing card
add_action('woocommerce_after_shop_loop_item', function() {
    $product = af_wc_product();
    if (!$product) return;
    $url = add_query_arg('tow', $product->get_id(), home_url('/try-on-wall/'));
    echo '<a class="af-card-ar" href="'.esc_url($url).'" aria-label="Try on your wall">'
       . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>'
       . '<span>Try on Wall</span></a>';
}, 15);

// 5d. Listing toolbar + card AR styling + grid polish
add_action('wp_head', function() {
    if (!function_exists('is_shop')) return;
    if (!(is_shop() || is_product_category() || is_product_tag())) return;
    ?>
    <style>
    .af-listing-toolbar{max-width:1240px;margin:0 auto 20px;padding:14px 0;border-bottom:1px solid #eee;display:flex;flex-direction:column;gap:12px;}
    .af-lt-chips{display:flex;flex-wrap:wrap;gap:8px;}
    .af-chip{display:inline-flex;align-items:center;gap:5px;padding:6px 13px;border-radius:20px;background:#f4f1e9;color:#333;font-size:13px;font-weight:600;text-decoration:none;transition:background .2s,color .2s;}
    .af-chip:hover{background:#c9a84c;color:#fff;}
    .af-chip-active{background:#1a1a1a;color:#fff;}
    .af-chip span{font-size:11px;opacity:.7;}
    .af-lt-controls{display:flex;flex-wrap:wrap;gap:10px;align-items:center;}
    .af-lt-drop{position:relative;}
    .af-lt-dbtn{background:#fff;border:1px solid #ddd;border-radius:8px;padding:8px 14px;font-size:13px;font-weight:600;cursor:pointer;color:#333;}
    .af-lt-dbtn:hover{border-color:#c9a84c;color:#a8872e;}
    .af-lt-menu{position:absolute;top:calc(100% + 6px);left:0;z-index:50;background:#fff;border:1px solid #eee;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.12);padding:8px;min-width:170px;display:none;max-height:280px;overflow:auto;}
    .af-lt-menu.open{display:block;}
    .af-lt-menu a{display:block;padding:8px 12px;font-size:13px;color:#333;text-decoration:none;border-radius:6px;}
    .af-lt-menu a:hover{background:#faf7ef;color:#a8872e;}
    .af-lt-price{padding:14px;min-width:210px;}
    .af-price-row{display:flex;align-items:center;gap:8px;margin-bottom:10px;}
    .af-price-row input{width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:6px;font-size:13px;}
    .af-price-row span{color:#999;}
    .af-price-apply{width:100%;background:#1a1a1a;color:#fff;border:none;border-radius:7px;padding:9px;font-size:13px;font-weight:700;cursor:pointer;}
    .af-price-apply:hover{background:#c9a84c;}
    .af-lt-clear{font-size:12.5px;color:#c0392b;text-decoration:none;font-weight:600;margin-left:4px;}
    /* Tag filter — simple clickable buttons */
    .af-lt-tags{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin-top:6px;}
    .af-lt-tagslabel{font-size:12.5px;font-weight:700;color:#666;margin-right:2px;}
    .af-tag-btn{display:inline-block;padding:5px 12px;border-radius:16px;background:#fff;border:1.5px solid #e2ddcf;color:#6b6250;font-size:12.5px;font-weight:600;text-decoration:none;transition:all .15s;}
    .af-tag-btn:hover{border-color:#c9a84c;color:#a8872e;}
    .af-tag-btn.active{background:#c9a84c;border-color:#c9a84c;color:#fff;}
    /* Card AR button */
    .woocommerce ul.products li.product{position:relative;}
    .af-card-ar{position:absolute;left:10px;bottom:64px;z-index:6;display:inline-flex;align-items:center;gap:5px;background:rgba(20,20,20,.86);color:#fff;font-size:11.5px;font-weight:600;padding:6px 10px;border-radius:20px;text-decoration:none;opacity:0;transform:translateY(6px);transition:opacity .25s,transform .25s,background .2s;}
    .woocommerce ul.products li.product:hover .af-card-ar{opacity:1;transform:translateY(0);}
    .af-card-ar:hover{background:#c9a84c;}
    .af-card-ar svg{width:14px;height:14px;}
    /* There is no hover on a phone, so this shows permanently — which made it
       a 26x24 chip parked in the TOP-LEFT corner of the artwork, the same
       corner the discount ribbon occupies, at 11px and well under the ~40px a
       fingertip wants. It moves to the opposite corner, clear of the ribbon,
       and gets a real tap target. The label still goes: at a 158px card there
       is no room for words on top of the picture. */
    @media(max-width:768px){
      .af-card-ar{opacity:1;transform:none;left:auto;right:8px;bottom:8px;top:auto;
        padding:0;font-size:12px;width:40px;height:40px;justify-content:center;border-radius:50%;}
      .af-card-ar span{display:none;}
      .af-card-ar svg{width:18px;height:18px;}
    }
    </style>
    <?php
}, 20);

// ─────────────────────────────────────────────────────────────
// PHASE 7 — Product page missing sections (per spec):
// Buy Now, trust badges under CTA, Related Searches, Popular
// Products, Recently Viewed. Additive; existing sections untouched.
// ─────────────────────────────────────────────────────────────

// 7a. Recently viewed is recorded in the visitor's browser: see "Recently
//     viewed" under PHASE 18. A cookie set here was stored in the page cache
//     with the page and sent to every later visitor (DEF-04).

// 7b. "Buy Now" button beside Add to Cart.
//     Simple products: direct link to checkout with add-to-cart.
//     Variable products: JS adds the chosen variation, then redirects.
add_action('woocommerce_after_add_to_cart_button', function() {
    $product = af_wc_product();
    if (!$product || !$product->is_purchasable() || !$product->is_in_stock()) return;

    if ($product->is_type('simple')) {
        // Submits the product form, so the size, frame, colour and "What you
        // receive" the shopper chose travel with it, then goes to checkout
        // (woocommerce_add_to_cart_redirect below). It used to be a link to
        // /checkout/?add-to-cart=ID, which added the piece with every option
        // at its default, and left ?add-to-cart in the address so a reload
        // added a second copy (owner's checkout test, 30 Sep). A button is
        // also nothing for a crawler to follow.
        echo '<button type="button" class="af-buynow af-buynow-simple button">Buy Now</button>';
        ?>
        <script>
        (function(){
          if (window.__afBuyNowSimple) return; window.__afBuyNowSimple = true;
          // Back from checkout restores the page with the flag still in the
          // form; drop it so a plain Add to cart stays on the page.
          window.addEventListener('pageshow', function(){
            document.querySelectorAll('form.cart input[name="af_buy_now"]').forEach(function(i){ i.remove(); });
          });
          document.addEventListener('click', function(e){
            var b = e.target.closest('.af-buynow-simple'); if(!b) return;
            e.preventDefault();
            var form = b.closest('form.cart'); if(!form) return;
            if (!form.querySelector('input[name="af_buy_now"]')) {
              var flag = document.createElement('input');
              flag.type = 'hidden'; flag.name = 'af_buy_now'; flag.value = '1';
              form.appendChild(flag);
            }
            var addBtn = form.querySelector('.single_add_to_cart_button');
            if (addBtn) addBtn.click(); else form.submit();
          });
        })();
        </script>
        <?php
    } elseif ($product->is_type('variable')) {
        // Button submits the variation form, then redirects to checkout.
        echo '<button type="button" class="af-buynow af-buynow-var button" data-checkout="' . esc_url(wc_get_checkout_url()) . '">Buy Now</button>';
        ?>
        <script>
        (function(){
          document.addEventListener('click', function(e){
            var b = e.target.closest('.af-buynow-var'); if(!b) return;
            e.preventDefault();
            var form = b.closest('form.cart');
            if(!form){ return; }
            var vid = form.querySelector('input[name="variation_id"]');
            if(!vid || !vid.value || vid.value === '0'){
              alert('Please select the options (size / frame / color) first.');
              return;
            }
            // Mark that we want to go to checkout after add-to-cart, then submit.
            var flag = document.createElement('input');
            flag.type='hidden'; flag.name='af_buy_now'; flag.value='1';
            form.appendChild(flag);
            var addBtn = form.querySelector('.single_add_to_cart_button');
            if(addBtn){ addBtn.click(); } else { form.submit(); }
          });
        })();
        </script>
        <?php
    }
}, 4);

// Redirect to checkout when Buy Now was used on a variable product
add_filter('woocommerce_add_to_cart_redirect', function($url){
    if (!empty($_REQUEST['af_buy_now'])) { return wc_get_checkout_url(); }
    return $url;
}, 20);

// 7c. Trust badges directly under the CTA
add_action('woocommerce_after_add_to_cart_button', function() {
    $product = af_wc_product();
    if (!$product) return;
    // This badge used to read "Free across USA" as a hardcoded string, which is
    // how it survived the correction that took the same claim off every other
    // surface — it was never wired to af_shipping_copy(). It is now, so it
    // states the policy and cannot drift from it again.
    $ship = af_shipping_copy();
    ?>
    <div class="af-pp-trust">
      <div class="af-ppt"><span>🔒</span><div><strong>Secure Payments</strong><small>Encrypted checkout</small></div></div>
      <div class="af-ppt"><span>↩️</span><div><strong>Easy Returns</strong><small>7-day policy</small></div></div>
      <div class="af-ppt"><span>🚚</span><div><strong><?php echo esc_html($ship['free'] ? 'Free Shipping' : 'Fast Shipping'); ?></strong><small><?php echo esc_html($ship['free'] ? 'On all US orders' : $ship['short']); ?></small></div></div>
    </div>
    <?php
}, 25);

// 7d. Related Searches (from product tags + category) after summary
af_section(function() {
    if (!af_show_product_sections()) return;   // page + quick-view modal, nothing else
    $product = af_wc_product();
    if (!$product) return;
    $terms = array();
    foreach (array('product_tag','product_cat') as $tax) {
        $t = get_the_terms($product->get_id(), $tax);
        if ($t && !is_wp_error($t)) foreach ($t as $term) $terms[$term->name] = get_term_link($term);
    }
    if (count($terms) < 2) return;
    $terms = array_slice($terms, 0, 10, true);
    echo '<section class="af-pp-sec af-related-searches"><h2>Related Searches</h2><div class="af-rs-chips">';
    foreach ($terms as $name => $link) {
        if (is_wp_error($link)) continue;
        echo '<a class="af-rs-chip" href="'.esc_url($link).'">'.esc_html($name).'</a>';
    }
    echo '</div></section>';
}, 16);

// 7e. Popular Products (best sellers) after tabs
af_section(function() {
    if (!af_show_product_sections()) return;   // page + quick-view modal, nothing else
    $product = af_wc_product();
    if (!$product) return;
    $ids = wc_get_products(array(
        'status'=>'publish','limit'=>12,'orderby'=>'meta_value_num','meta_key'=>'total_sales',
        'order'=>'DESC','exclude'=>array($product->get_id()),'return'=>'ids',
    ));
    if (count($ids) < 4) { // fallback: recent if not enough sales data
        $ids = wc_get_products(array('status'=>'publish','limit'=>12,'orderby'=>'date','order'=>'DESC','exclude'=>array($product->get_id()),'return'=>'ids'));
    }
    $ids = af_ids_with_image($ids, 4);
    if (count($ids) < 4) return;
    echo '<section class="af-pp-sec af-popular"><h2>Popular Products</h2><div class="af-pp-row">';
    foreach ($ids as $pid) { af_render_mini_card($pid); }
    echo '</div></section>';
}, 21);

// The native "Related products" row has the same fault: two of its four
// cards were placeholder pictures. Drop those before WooCommerce slices the
// candidate list to four, so real artwork fills the row instead.
add_filter('woocommerce_related_products', function($ids) {
    return array_values(array_filter((array) $ids, function($pid) {
        $p = wc_get_product($pid);
        return $p && $p->get_image_id() && !af_is_placeholder_image($p->get_image_id());
    }));
}, 20);

// 7f. Recently Viewed: one strip, drawn in the browser (PHASE 18 below).

// Filter product IDs down to those with a real featured image (avoids
// grey placeholder cards), capped at $max.
/**
 * Is this attachment the theme's stand-in picture rather than artwork?
 * Four leftover theme demo products (Balance Poster, IL Lemone Poster, The
 * Penguin Show Poster, Geometric Shapes poster) carry placeholder-1.jpeg as
 * their product image - the grey box with "Postero" on it. They HAVE an image
 * id, so "has an image" let them through into Popular Products and Related
 * products as blank cards. Measured on the live product page, 24 Sep.
 */
function af_is_placeholder_image($image_id) {
    if (!$image_id) return true;
    $file = (string) get_post_meta($image_id, '_wp_attached_file', true);
    return $file === '' || (bool) preg_match('/(^|\/)(woocommerce-)?placeholder[^\/]*$/i', $file);
}

function af_ids_with_image($ids, $max) {
    $out = array();
    foreach ((array) $ids as $pid) {
        $p = wc_get_product($pid);
        if (!$p || $p->get_status() !== 'publish' || !$p->get_image_id()) continue;
        if (af_is_placeholder_image($p->get_image_id())) continue;
        $out[] = $pid;
        if (count($out) >= $max) break;
    }
    return $out;
}

// Shared mini product card renderer (assumes a valid featured image)
function af_render_mini_card($pid) {
    $p = wc_get_product($pid);
    if (!$p || $p->get_status() !== 'publish') return;
    // The card image was the 300px 'medium' size, drawn at ~300 CSS px - soft
    // on every 1.5x/2x screen. The catalogue size (800px here) with a srcset
    // lets the browser pick a sharp file for the screen it is on.
    $img = wp_get_attachment_image($p->get_image_id(), 'woocommerce_thumbnail', false, array(
        'loading' => 'lazy', 'alt' => $p->get_name(),
        'sizes'   => '(max-width: 600px) 50vw, (max-width: 1024px) 33vw, 300px',
    ));
    if (!$img) return; // never render a placeholder card
    echo '<a class="af-mini-card" href="'.esc_url(get_permalink($pid)).'">';
    echo '<div class="af-mini-img">'.$img.'</div>';
    echo '<div class="af-mini-info"><span class="af-mini-title">'.esc_html($p->get_name()).'</span>';
    echo '<span class="af-mini-price">'.$p->get_price_html().'</span></div></a>';
}

// 7g. Styles for the new product-page sections.
//     Loaded site-wide (all selectors are .af-* class-scoped) so the Quick View
//     modal — which renders the same product summary on the homepage/shop —
//     gets identical button/chip/card styling as the product page.
add_action('wp_head', function() {
    ?>
    <style>
    /* Buy Now (works on product page AND inside the Quick View modal) */
    .af-buynow.button{background:#1a1a1a !important;color:#fff !important;margin-left:8px !important;}
    .af-buynow.button:hover{background:#c9a84c !important;color:var(--af-on-primary, #1a1206) !important;}
    /* Trust badges under CTA */
    .af-pp-trust{display:flex;flex-wrap:wrap;gap:14px;margin:18px 0 0;padding:14px 0 0;border-top:1px solid #eee;width:100%;}
    .af-ppt{display:flex;align-items:center;gap:9px;flex:1 1 150px;min-width:140px;}
    .af-ppt span{font-size:22px;line-height:1;}
    .af-ppt strong{display:block;font-size:12.5px;color:#1a1a1a;}
    .af-ppt small{display:block;font-size:11px;color:var(--af-mute, #6b6b6b);}
    /* Post-summary sections — full width of the theme content column so
       they line up with the native "Related products" section. */
    .af-pp-sec{width:100%;max-width:100%;margin:40px 0 0;padding:0;box-sizing:border-box;clear:both;}
    .af-pp-sec h2{font-size:22px;font-weight:800;color:#1a1a1a;margin:0 0 16px;}
    .af-rs-chips{display:flex;flex-wrap:wrap;gap:9px;}
    .af-rs-chip{padding:7px 15px;border-radius:20px;background:#f4f1e9;color:#333;font-size:13px;font-weight:600;text-decoration:none;transition:background .2s,color .2s;}
    .af-rs-chip:hover{background:#c9a84c;color:#fff;}
    /* 4-up grid; fewer items naturally left-align into the first columns */
    .af-pp-row{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;align-items:stretch;}
    .af-mini-card{background:#fff;border:1px solid #eee;border-radius:12px;overflow:hidden;text-decoration:none;transition:box-shadow .25s,transform .25s;display:flex;flex-direction:column;height:100%;}
    .af-mini-card:hover{box-shadow:0 8px 22px rgba(0,0,0,.12);transform:translateY(-3px);}
    .af-mini-img{aspect-ratio:1/1;overflow:hidden;background:#f4f4f4;}
    .af-mini-img img{width:100%;height:100%;object-fit:cover;transition:transform .4s;}
    .af-mini-card:hover .af-mini-img img{transform:scale(1.05);}
    .af-mini-info{padding:11px 13px;display:flex;flex-direction:column;gap:5px;flex:1 1 auto;}
    .af-mini-title{font-size:13px;font-weight:700;color:#1a1a1a;line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;min-height:2.7em;}
    .af-mini-price{font-size:13.5px;font-weight:700;color:var(--af-gold-text, #8a6d1f);margin-top:auto;}
    @media(max-width:900px){ .af-pp-row{grid-template-columns:repeat(2,1fr);} }
    /* Inside the quick-view popup the same sections render in a narrower,
       scrolling container — two cards across, tighter rhythm. */
    .eael-product-popup .af-pp-row{grid-template-columns:repeat(2,1fr);}
    .eael-product-popup .af-pp-sec{margin-top:28px;}
    .eael-product-popup .af-recent-row a{flex:0 0 120px;}
    .eael-product-popup .af-recent-row img{width:120px;height:120px;}
    @media(max-width:600px){ .af-pp-sec h2{font-size:19px;} .af-ppt{flex:1 1 45%;} }
    </style>
    <?php
}, 21);

// ─────────────────────────────────────────────────────────────
// PHASE 7b — Remaining post-tab sections per spec Section 13:
// Digital Downloads + Customize Your Picture. Additive; reversible.
// ─────────────────────────────────────────────────────────────

// 7h. Digital Downloads section (post-tab) — shows Digital Downloads category
af_section(function() {
    if (!af_show_product_sections()) return;   // page + quick-view modal, nothing else
    $product = af_wc_product();
    if (!$product) return;
    $ids = wc_get_products(array(
        'status'=>'publish','limit'=>12,'return'=>'ids',
        // The canonical list, not a third private copy: this one was also
        // missing digital-downloads-2, so on a site where that is the live term
        // the query returned nothing and the whole section silently vanished.
        'category'=>af_digital_cat_slugs(),
        'exclude'=>array($product->get_id()),
    ));
    $ids = af_ids_with_image($ids, 4);
    if (count($ids) < 2) return;
    echo '<section class="af-pp-sec af-digital"><h2>Digital Downloads</h2>';
    echo '<p class="af-pp-sub">Instant high-resolution files — delivered to your inbox, ready to print.</p>';
    echo '<div class="af-pp-row">';
    foreach ($ids as $pid) { af_render_mini_card($pid); }
    echo '</div></section>';
}, 23);

// 7i. Customize Your Picture CTA (post-tab)
af_section(function() {
    if (!af_show_product_sections()) return;   // page + quick-view modal, nothing else
    $product = af_wc_product();
    if (!$product) return;
    // Link to Personalised Prints category if it exists, else Contact
    $term = get_term_by('slug', 'personalised-prints', 'product_cat');
    $url  = $term ? get_term_link($term) : home_url('/contact/');
    if (is_wp_error($url)) $url = home_url('/contact/');
    ?>
    <section class="af-pp-sec af-customize">
      <div class="af-cz-inner">
        <div class="af-cz-text">
          <h2>Customize Your Picture</h2>
          <p>Turn your own photo into premium canvas or framed art — portraits, family collages, and personalised gifts, made to order.</p>
        </div>
        <a class="af-cz-btn" href="<?php echo esc_url($url); ?>">Start Customizing →</a>
      </div>
    </section>
    <?php
}, 24);

// 7j. Styles for the two added sections (loaded site-wide; .af-* class-scoped
//     so the Quick View modal's Digital Downloads / Customize sections match).
add_action('wp_head', function() {
    ?>
    <style>
    .af-pp-sub{font-size:13.5px;color:var(--af-mute, #6b6b6b);margin:-8px 0 16px;}
    .af-customize{background:linear-gradient(90deg,#141414,#2a2416);border-radius:16px;padding:30px 28px;color:#fff;}
    .af-cz-inner{display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap;}
    .af-cz-text h2{color:#fff;margin:0 0 6px;font-size:22px;}
    .af-cz-text p{margin:0;font-size:14px;color:#cfc7b3;max-width:640px;line-height:1.6;}
    .af-cz-btn{flex-shrink:0;background:#c9a84c;color:#141414;font-weight:800;font-size:14px;padding:13px 24px;border-radius:9px;text-decoration:none;transition:background .2s,transform .2s;white-space:nowrap;}
    .af-cz-btn:hover{background:#dcb85a;transform:translateY(-1px);}
    @media(max-width:600px){ .af-cz-inner{flex-direction:column;align-items:flex-start;} .af-cz-btn{width:100%;text-align:center;} }
    </style>
    <?php
}, 22);

// ─────────────────────────────────────────────────────────────
// PHASE 8 — Universal Size / Frame / Color selector + dynamic
// pricing on ALL products (simple ones). Formula-based; the
// default selection equals the product's current price, so nothing
// is mispriced unless the customer upgrades. Fully adjustable below.
// ─────────────────────────────────────────────────────────────

// ---- Pricing configuration (edit these numbers anytime) ----
// Which products get the size/frame/color engine: every purchasable
// simple or variable product EXCEPT the gift card and non-canvas
// categories (accessories, banners) that have their own option sets.
/**
 * The current product, or null — never a string.
 *
 * WooCommerce's $product global is only reliable inside its own loop. A
 * plugin, shortcode or template that reassigns it (we have one that leaves a
 * string behind by the time wp_footer runs) turns every `if (!$product)`
 * guard into a lie, because a non-empty string is truthy — and the next
 * `$product->get_id()` is a fatal that takes the whole page down with it.
 * Ask for the product through here instead of trusting the global.
 */
function af_wc_product($maybe = null) {
    if ($maybe instanceof WC_Product) return $maybe;
    if (!function_exists('wc_get_product')) return null;
    $global = isset($GLOBALS['product']) ? $GLOBALS['product'] : null;
    if ($global instanceof WC_Product) return $global;

    $id = 0;
    if (is_numeric($maybe))      $id = (int) $maybe;
    elseif (is_numeric($global)) $id = (int) $global;
    else {
        $id = (int) get_the_ID();
        if (!$id && function_exists('get_queried_object_id')) $id = (int) get_queried_object_id();
    }
    if (!$id) return null;
    $p = wc_get_product($id);
    return ($p instanceof WC_Product) ? $p : null;
}

/**
 * True only on the real single-product page — no ajax, no REST, no admin.
 */
function af_is_product_page() {
    if (is_admin()) return false;
    if (function_exists('wp_doing_ajax') && wp_doing_ajax()) return false;
    if (defined('REST_REQUEST') && REST_REQUEST) return false;
    if (defined('DOING_AJAX') && DOING_AJAX) return false;
    return function_exists('is_product') && is_product();
}

/**
 * Does this ajax action name belong to a quick-view plugin?
 *
 * Split out from the request check because it is the part worth testing, and
 * the request check cannot be exercised from WP-CLI, where wp_doing_ajax() is
 * always false. Matched on the action rather than a plugin's name: the site
 * currently quick-views through Essential Addons
 * (eael_product_quickview_popup), and swapping that for WooSQ or another
 * plugin must not silently empty the modal.
 */
function af_is_quick_view_action($action) {
    $action = (string) $action;
    return $action !== '' && (bool) preg_match('/(woosq|quick_?view|^qv_|_qv$)/i', $action);
}

/**
 * True while a quick-view plugin is rendering its modal.
 */
function af_is_quick_view_request() {
    $ajax = (function_exists('wp_doing_ajax') && wp_doing_ajax()) || (defined('DOING_AJAX') && DOING_AJAX);
    if (!$ajax) return false;
    if (defined('REST_REQUEST') && REST_REQUEST) return false;
    return af_is_quick_view_action(isset($_REQUEST['action']) ? $_REQUEST['action'] : '');
}

/**
 * Register a product-page section so it renders in BOTH places it belongs.
 *
 * On the real product page these hang off woocommerce_after_single_product_summary.
 * The quick-view modal (Essential Addons) renders the summary hook but never
 * fires the after-summary hook at all — measured directly: every in-summary
 * piece of ours reaches the modal, every after-summary section is absent, and
 * the response ends right after the product meta. So each section also joins a
 * private af_product_sections action, and a bridge at the end of the summary
 * hook fires that action only during a quick-view request. The bridge
 * deliberately does NOT fire the whole after-summary hook inside the modal:
 * WooCommerce hangs tabs, up-sells and related products there (44 callbacks),
 * and the modal wants the page's own sections, not the entire tail of the
 * template.
 */
function af_section($fn, $prio = 10) {
    add_action('woocommerce_after_single_product_summary', $fn, $prio);
    add_action('af_product_sections', $fn, $prio);
}
/**
 * Fire the sections inside the modal, on a hook the modal actually reaches.
 *
 * Essential Addons builds its popup layout by hand: it never fires
 * woocommerce_single_product_summary either — proven by which of our pieces
 * arrive in its response (all three are on the add-to-cart button hooks) and
 * by a first bridge on the summary hook that never ran. What its popup DOES
 * render is the product meta, whose template fires
 * woocommerce_product_meta_end — the very tail of the modal. The bridge sits
 * there, and also on the summary hook for quick-view plugins that do fire it,
 * with a latch so the sections render once whichever hook comes first. On the
 * real product page af_is_quick_view_request() is false and both are no-ops.
 */
function af_qv_bridge() {
    static $done = false;
    if ($done || !af_is_quick_view_request()) return;
    $done = true;
    do_action('af_product_sections');
}
add_action('woocommerce_product_meta_end', 'af_qv_bridge', 200);
add_action('woocommerce_single_product_summary', 'af_qv_bridge', 200);

/**
 * Should the product page's own sections — Related Searches, Popular Products,
 * Recently Viewed, Digital Downloads, the Customize CTA, the FAQ — render here?
 *
 * The quick view is meant to be the product page for that product, not a
 * trimmed version of it, so the answer is yes on the real page AND inside the
 * modal. What it is not is a licence to render during any old ajax call, a
 * REST response or an admin screen, which is what this keeps out.
 *
 * These sections hang off woocommerce_after_single_product_summary, which the
 * quick-view plugin fires too. They used to stay out of the modal only by
 * accident — af_wc_product() found no $product global there and each section
 * bailed on its own first line — and when that helper learned to fall back to
 * the current post they appeared unstyled, because the stylesheet they need was
 * gated to is_product(). That stylesheet is now site-wide, so they render in
 * the modal exactly as they do on the page.
 */
function af_show_product_sections() {
    return af_is_product_page() || af_is_quick_view_request();
}

/* ============================================================
   QUICK VIEW = THE PRODUCT PAGE ITSELF
   The plugin's popup rebuilds the product from its own template, so it can
   only ever be an approximation of the page. The owner wants an exact full
   copy — tabs, art code, highlights, spec table, placement ideas, FAQ,
   related pieces, everything. The only rendering that is exactly the product
   page is the product page, so Quick View now opens the real page inside a
   modal frame, with the site chrome (header, footer, floating widgets)
   hidden via the af_qv=1 embed mode below. It cannot drift out of sync with
   the page, because it IS the page.
   ============================================================ */

// ── embed mode: the product page stripped to its content ──
function af_is_qv_embed() {
    return !empty($_GET['af_qv']);
}
add_filter('body_class', function($classes) {
    if (af_is_qv_embed()) $classes[] = 'af-qv-embed';
    return $classes;
});

/**
 * The "You may also like" rows print the same product-card markup the shop
 * archives do, but they sit on the wishlist and cart pages, which are ordinary
 * WordPress pages. Every card rule in this theme is scoped to
 * body.tax-product_cat or body.woocommerce-page, and those pages carry
 * neither — so the identical markup arrived with none of the design on it.
 *
 * Measured rather than assumed. Against a category card, those cards came out
 * with an 18px normal-weight title instead of 13.5px semibold, a flat
 * gradient-less ribbon at the wrong corner and the wrong size, a 275px square
 * image box holding a 300px image so 25px of every picture was clipped, and a
 * softer shadow.
 *
 * Rather than restate several hundred lines of card CSS under new selectors,
 * where the two copies would drift apart, the page is given the class those
 * rules already look for. That is also what WooCommerce itself adds to any
 * page carrying its content.
 *
 * The blast radius was checked before doing it: every rule in the theme scoped
 * to body.woocommerce-page — 29 in custom.css and 14 in this file — targets
 * ul.products or li.product. None of them styles anything else on a page, so
 * the class cannot reach past the cards it is meant to fix.
 */
add_filter('body_class', function ($classes) {
    if (is_admin() || !function_exists('is_woocommerce')) return $classes;
    if (in_array('woocommerce-page', $classes, true)) return $classes;
    if (!is_page()) return $classes;

    $post = get_post();
    if (!$post) return $classes;

    // The cart already gets the class from WooCommerce. This is for the pages
    // that only borrow the card markup: the wishlist, and any page the
    // cross-sell row is printed into.
    $has_row = (function_exists('af_is_wishlist_page') && af_is_wishlist_page())
            || strpos((string) $post->post_content, 'af-wl-related') !== false
            || strpos((string) $post->post_content, 'af-xsell') !== false;

    if ($has_row) $classes[] = 'woocommerce-page';
    return $classes;
});
// Don't merely hide the admin bar in the modal — don't render it. It ships its
// own stylesheet and markup, and it is site furniture, not product detail.
add_filter('show_admin_bar', function($show) {
    return af_is_qv_embed() ? false : $show;
}, 99);
add_action('wp_head', function() {
    if (!af_is_qv_embed()) return;
    ?>
    <style>
    /* hide everything that belongs to the surrounding site, keep the page */
    .af-qv-embed header, .af-qv-embed .site-header, .af-qv-embed #masthead,
    .af-qv-embed footer, .af-qv-embed .site-footer,
    .af-qv-embed [data-elementor-type="header"], .af-qv-embed [data-elementor-type="footer"],
    .af-qv-embed .af-quickpanel, .af-qv-embed #af-chat, .af-qv-embed #af-consent,
    .af-qv-embed .af-ck-footwrap, .af-qv-embed .woocommerce-breadcrumb,
    /* the top utility strip (Track Order / Help / phone / currency) and the
       WordPress admin bar — site furniture, not product detail */
    .af-qv-embed .af-utilitybar, .af-qv-embed #wpadminbar {
      display: none !important;
    }
    /* WordPress reserves space for the admin bar on <html>; with the bar hidden
       that margin would leave a strip of empty page at the top of the modal */
    html { margin-top: 0 !important; }
    * html body { margin-top: 0 !important; }
    .af-qv-embed { background: #fff; }
    </style>
    <?php
}, 99);
add_action('wp_footer', function() {
    if (!af_is_qv_embed()) return;
    ?>
    <script>
    (function(){
      // Inside the frame, anything that leaves the product (Buy Now, checkout,
      // cart, breadcrumbs to categories, related-product cards) should take
      // over the whole window rather than navigating within the modal.
      // Add-to-cart form posts stay inside so the modal keeps its context.
      document.addEventListener('click', function(e){
        var a = e.target.closest('a[href]');
        if (!a) return;
        var h = a.getAttribute('href') || '';
        if (h.charAt(0) === '#' || /^javascript:/i.test(h)) return;
        // keep in-frame: gallery lightboxes and same-page anchors only
        a.target = '_top';
      }, true);
    })();
    </script>
    <?php
}, 99);

// ── the modal: intercept quick-view clicks on catalogue pages ──
add_action('wp_footer', function() {
    if (is_admin() || af_is_qv_embed()) return;
    ?>
    <div id="af-qv" class="af-qv" hidden>
      <div class="af-qv-back"></div>
      <div class="af-qv-box" role="dialog" aria-modal="true" aria-label="Quick view">
        <button type="button" class="af-qv-x" aria-label="Close quick view">✕</button>
        <div class="af-qv-spin"></div>
        <iframe class="af-qv-frame" title="Product quick view"></iframe>
      </div>
    </div>
    <style>
    .af-qv[hidden]{display:none !important;}
    .af-qv{position:fixed;inset:0;z-index:100000;display:flex;align-items:center;justify-content:center;}
    .af-qv-back{position:absolute;inset:0;background:rgba(12,10,6,.62);backdrop-filter:blur(2px);}
    .af-qv-box{position:relative;width:min(1200px,94vw);height:92vh;background:#fff;border-radius:16px;
      overflow:hidden;box-shadow:0 30px 90px rgba(0,0,0,.45);}
    /* A bare cross, not a black disc. The owner asked for the circle gone
       (2026-09-14) — and it was not even a circle on the page: this button
       inherits the theme's global button padding of 14px 40px, which stretched
       a 38px round button into a wide black pill floating over the artwork.
       The chat launcher and the quick panel both carry padding:0 !important
       for exactly this reason; this one was missed.

       So: no background, no border, no shadow — just the glyph in the page's
       own ink, darkening to gold on hover with the faintest disc behind it so
       there is still something to aim at. The box stays 40px because that is
       what a fingertip needs, even though nothing of it is painted. */
    .af-qv-x{position:absolute;top:10px;right:10px;z-index:3;
      width:40px !important;height:40px !important;min-width:0 !important;
      padding:0 !important;margin:0 !important;
      border:none !important;border-radius:50% !important;
      background:transparent !important;box-shadow:none !important;
      color:#1a1a1a !important;font-size:20px;font-weight:400;line-height:1;
      cursor:pointer;display:flex;align-items:center;justify-content:center;
      transition:background .18s,color .18s,transform .18s;}
    .af-qv-x:hover,.af-qv-x:focus{background:rgba(26,26,26,.07) !important;
      color:#a8872e !important;transform:rotate(90deg);}
    .af-qv-x:focus-visible{outline:2px solid #c9a84c;outline-offset:2px;}
    .af-qv-frame{position:absolute;inset:0;width:100%;height:100%;border:0;opacity:0;transition:opacity .25s;}
    .af-qv.loaded .af-qv-frame{opacity:1;}
    .af-qv-spin{position:absolute;left:50%;top:50%;width:42px;height:42px;margin:-21px 0 0 -21px;border-radius:50%;
      border:4px solid #eee2c8;border-top-color:#c9a84c;animation:afqvspin .8s linear infinite;}
    .af-qv.loaded .af-qv-spin{display:none;}
    @keyframes afqvspin{to{transform:rotate(360deg);}}
    @media(max-width:600px){ .af-qv-box{width:100vw;height:100vh;border-radius:0;} }
    /* Belt and braces: if the plugin's own popup still manages to open behind
       ours, keep it off the screen rather than stacking two modals. */
    html.af-qv-open .eael-product-popup, html.af-qv-open .woosq-popup,
    html.af-qv-open .mfp-wrap, html.af-qv-open .pswp--open { display:none !important; }
    </style>
    <script>
    (function(){
      var wrap=document.getElementById('af-qv');
      if(!wrap) return;
      var frame=wrap.querySelector('.af-qv-frame'), readyTimer=null, giveUp=null, target='';

      // Find the product URL for whatever card this button belongs to. The
      // first version looked for li.product/.product, which does not match the
      // homepage's .product-card (a class selector matches whole tokens), so
      // on the homepage it found nothing, bailed, and let the plugin's popup
      // open — which is exactly what the visitor kept seeing. Walk up instead:
      // any ancestor holding a /product/ link is the card, whatever it's called.
      function productURL(btn){
        var own=btn.getAttribute('href')||'';
        if(own.indexOf('/product/')>-1) return own;
        var d=btn.getAttribute('data-product-url')||btn.getAttribute('data-url')||'';
        if(d.indexOf('/product/')>-1) return d;
        // Walk up for the card's own product link — but only while the answer
        // is unambiguous.
        //
        // This used to take the FIRST product link in each ancestor's subtree,
        // ten levels up. A button sitting inside a real card finds its own link
        // in a level or two, so that worked. Anything else kept climbing until
        // it reached a container holding the whole listing, where the "first
        // product link" is simply the first card on the page — which is how
        // clicking the header logo opened a product that had nothing to do
        // with it.
        //
        // A card holds exactly one product link. More than one means we have
        // climbed past the card into the grid, and there is no longer anything
        // here that identifies what was clicked, so stop rather than guess.
        var n=btn;
        for(var i=0;i<10 && n && n!==document.body;i++){
          if(n.querySelectorAll){
            var links=n.querySelectorAll('a[href*="/product/"]');
            if(links.length>1) return '';        // ambiguous: not one card
            if(links.length===1 && links[0].href) return links[0].href;
          }
          n=n.parentElement;
        }
        return '';
      }
      function reveal(){
        wrap.classList.add('loaded');
        if(readyTimer){ clearInterval(readyTimer); readyTimer=null; }
        if(giveUp){ clearTimeout(giveUp); giveUp=null; }
      }
      function openQV(url){
        target=url;
        wrap.classList.remove('loaded');
        document.documentElement.classList.add('af-qv-open');
        frame.onload=reveal;
        frame.src=url+(url.indexOf('?')>-1?'&':'?')+'af_qv=1';
        wrap.hidden=false;
        document.documentElement.style.overflow='hidden';
        // The frame is same-origin, so show it the moment it has a document
        // rather than waiting for every image — a 500 KB product page kept the
        // spinner up long enough to read as broken.
        if(readyTimer) clearInterval(readyTimer);
        readyTimer=setInterval(function(){
          try{
            var d=frame.contentDocument;
            if(d && (d.readyState==='interactive'||d.readyState==='complete') && d.body && d.body.children.length) reveal();
          }catch(err){ /* cross-origin: wait for onload */ }
        }, 120);
        // and never spin for ever: if the page will not frame, just go to it
        if(giveUp) clearTimeout(giveUp);
        giveUp=setTimeout(function(){
          if(!wrap.classList.contains('loaded') && target){ window.location.href=target; }
        }, 12000);
      }
      function closeQV(){
        wrap.hidden=true;
        frame.src='about:blank';
        document.documentElement.style.overflow='';
        document.documentElement.classList.remove('af-qv-open');
        if(readyTimer){ clearInterval(readyTimer); readyTimer=null; }
        if(giveUp){ clearTimeout(giveUp); giveUp=null; }
      }
      wrap.querySelector('.af-qv-x').addEventListener('click', closeQV);
      wrap.querySelector('.af-qv-back').addEventListener('click', closeQV);
      document.addEventListener('keydown', function(e){ if(e.key==='Escape' && !wrap.hidden) closeQV(); });

      // Capture phase on document runs before any delegated handler the
      // quick-view plugin binds, so this wins without a race. Matched broadly:
      // the buttons ship as quick-view, quickview, quick_view or view-btn
      // depending on which section rendered the card.
      var SEL='[class*="quick-view"],[class*="quickview"],[class*="quick_view"],'
             +'[class*="eael-product-quick"],[data-quick-view],.view-btn,a[class*="quick"]';
      // The header is never a quick-view trigger. SEL is deliberately broad —
      // it ends in a[class*="quick"], which matches any anchor with "quick"
      // anywhere in any class — and the site logo lives inside markup this
      // theme does not control. Clicking it must go home, so the masthead and
      // the branding are excluded outright rather than relying on the selector
      // never matching there.
      var NOT_QV='header,.site-header,[class*="site-header"],[class*="header-"],'
                +'.elementor-widget-site-logo,.hfe-site-logo,.site-logo,'
                +'[class*="site-logo"],[class*="site-branding"],.custom-logo-link,'
                +'nav,.main-navigation,[role="banner"],[role="navigation"]';
      function grab(e){
        var b=e.target.closest(SEL);
        if(!b || wrap.contains(b)) return;
        if(b.closest(NOT_QV)) return;          // header/logo/nav: let the link do its job
        var url=productURL(b);
        if(!url) return;                       // unknown card: leave the plugin to it
        e.preventDefault(); e.stopPropagation(); e.stopImmediatePropagation();
        openQV(url.split('#')[0]);
      }
      document.addEventListener('click', grab, true);
    })();
    </script>
    <?php
}, 98);

/**
 * Repair a corrupted $product global before the footer runs. Something on the
 * product page hands the global to wp_footer as a string; anything that then
 * calls a method on it dies, and WordPress answers 500 for the whole page.
 * Runs first (priority 0) so every later footer hook — ours, the parent
 * theme's, a plugin's — sees a real product or nothing at all.
 */
add_action('wp_footer', function() {
    if (!function_exists('is_product') || !is_product()) return;
    if (!array_key_exists('product', $GLOBALS)) return;
    if ($GLOBALS['product'] instanceof WC_Product) return;
    $p = function_exists('wc_get_product') ? wc_get_product(get_queried_object_id()) : null;
    $GLOBALS['product'] = ($p instanceof WC_Product) ? $p : null;
}, 0);

/**
 * The categories that hold products sold AS FILES rather than as framed prints.
 *
 * One list, because two places used to keep their own and they had already
 * drifted: the watermark gate listed three slugs and missed digital-downloads-2,
 * which is the term the live category page actually runs on
 * (/product-category/digital-downloads-2/). The site carries near-duplicate
 * terms — af_sidebar_cat_menu() documents the same pair — so every slug that
 * may be the live one belongs here, and nowhere else.
 */
function af_digital_cat_slugs() {
    return apply_filters('af_digital_cat_slugs', array(
        'digital-downloads', 'digital-downloads-2', 'instant-downloads', 'printable-art',
    ));
}

/**
 * Is this product sold as a downloadable file?
 *
 * NOT is_downloadable(): tools/enable-digital-downloads.php flags every
 * published product downloadable so the add-on option can exist on any piece,
 * so that method answers "yes" for the entire catalogue of physical canvases.
 * The category is the honest signal, children included.
 */
function af_is_digital_download($product) {
    if (!($product instanceof WC_Product)) return false;
    static $ids = null;
    if ($ids === null) {
        $ids = array();
        foreach (af_digital_cat_slugs() as $slug) {
            $t = get_term_by('slug', $slug, 'product_cat');
            if (!$t) continue;
            $ids[] = (int) $t->term_id;
            $kids = get_term_children($t->term_id, 'product_cat');
            if (!is_wp_error($kids)) $ids = array_merge($ids, array_map('intval', $kids));
        }
        $ids = array_values(array_unique($ids));
    }
    if (!$ids) return false;
    return has_term($ids, 'product_cat', $product->get_id());
}

function af_pricing_applies($product) {
    if (!($product instanceof WC_Product)) return false;
    if (!$product->is_type('simple') && !$product->is_type('variable')) return false;
    if (function_exists('af_gc_product_id') && (int) $product->get_id() === af_gc_product_id()) return false;
    if (get_post_meta($product->get_id(), '_af_is_gift_card', true) === 'yes') return false;
    if ($product->get_slug() === 'the-art-framer-gift-card') return false;
    // A file has no size, no frame, no colour and nothing to ship. Leaving the
    // canvas engine switched on for these is what put "5 sizes · 2 frames" and
    // "From $80.00" on the Digital Downloads cards, under a modal selling the
    // same piece for $9.43.
    if (af_is_digital_download($product)) return false;
    // Corporate Printing sells a banner, a standee or a pack of 500 cards at
    // the size the rate card prints (owner, 30 Sep: "5 sizes and 2 frames not
    // applicable"). inc/corporate-specs.php shows that size and quantity.
    $excluded = array('art-accessories', 'banners-signage', 'corporate-printing');
    $terms = get_the_terms($product->get_id(), 'product_cat');
    if ($terms && !is_wp_error($terms)) {
        foreach ($terms as $t) {
            if (in_array($t->slug, $excluded, true)) return false;
            // also honour excluded parents (e.g. child cats of accessories)
            $anc = get_ancestors($t->term_id, 'product_cat');
            foreach ($anc as $aid) {
                $at = get_term($aid, 'product_cat');
                if ($at && !is_wp_error($at) && in_array($at->slug, $excluded, true)) return false;
            }
        }
    }
    return true;
}

/**
 * The studio's public contact details — the single place any customer-facing
 * surface should ask.
 *
 * Deliberately NOT get_option('admin_email'): that is whoever owns the
 * WordPress login, so publishing it hands customers a personal address that
 * has nothing to do with the studio. These match what the header, footer and
 * contact page already show. Set the af_contact_email / af_contact_phone
 * options, or filter af_studio_contact, to change them everywhere at once.
 */
function af_studio_contact() {
    $email = get_option('af_contact_email');
    $phone = get_option('af_contact_phone');
    $c = apply_filters('af_studio_contact', array(
        'email' => $email ? $email : 'theartframer136@gmail.com',
        'phone' => $phone ? $phone : '+1 (610) 470-7280',
    ));
    // a blank option must never fall through as an empty mailto:
    if (empty($c['email'])) $c['email'] = 'theartframer136@gmail.com';
    if (empty($c['phone'])) $c['phone'] = '+1 (610) 470-7280';
    $digits     = preg_replace('/\D+/', '', $c['phone']);
    $c['tel']   = 'tel:+' . $digits;
    $c['wa']    = $digits;
    return $c;
}

/**
 * The home page and /about/ still showed the old "+91 8107236836" and
 * "info@farmergmail.com" (checked 17 Oct 2026). They live in an Elementor
 * icon list in the database, not in theme code. The Google Business Profile
 * (Hockessin, DE) is suspended and Google compares it with the website, so
 * every Elementor render swaps them for the studio's US details. strtr()
 * takes the longest match first, so tel:/mailto:/wa.me forms stay whole.
 */
add_filter('elementor/frontend/the_content', function ($html) {
    if (strpos($html, '8107236836') === false && stripos($html, 'farmergmail') === false) return $html;
    $c = af_studio_contact();
    return strtr($html, array(
        'mailto:info@farmergmail.com' => 'mailto:' . $c['email'],
        'info@farmergmail.com'        => $c['email'],
        'tel:+918107236836'           => $c['tel'],
        'wa.me/918107236836'          => 'wa.me/' . $c['wa'],
        '+91 8107236836'              => $c['phone'],
        '+918107236836'               => $c['phone'],
    ));
}, 99);

/**
 * How the site talks about payment — one source, for exactly the reason
 * af_shipping_copy() below has one.
 *
 * Measured on the live checkout, 21 Sep 2026, the gateways offered were:
 *
 *     Pay via Zelle · Credit Card (Square) · Cash on delivery
 *
 * And four places told the customer otherwise. The cart page footer said
 * "cards, PayPal & more"; the trust popup listed PayPal, Google Pay, Apple
 * Pay and Bank Transfer; the chatbot answered "major cards and PayPal"; the
 * About page promised "Card, PayPal and wallet payments". None of those is
 * on offer. A shopper who picks the store because it takes PayPal reaches
 * the last screen of the purchase and finds Zelle and cash on delivery.
 *
 * So the list is read from the gateways that are actually enabled, and the
 * copy is built from it. Enable PayPal and every one of those four places
 * starts saying PayPal on its own; disable one and they stop. The fallback
 * is deliberately vague rather than a second hardcoded list that could go
 * stale the same way.
 */
function af_payment_methods() {
    $names = array();
    if (function_exists('WC') && WC() && WC()->payment_gateways) {
        foreach (WC()->payment_gateways->payment_gateways() as $gw) {
            if (!isset($gw->enabled) || $gw->enabled !== 'yes') continue;
            $t = trim(wp_strip_all_tags((string) $gw->get_title()));
            if ($t !== '') $names[] = $t;
        }
    }
    return apply_filters('af_payment_methods', array_values(array_unique($names)));
}

/** "Credit Card, Pay via Zelle & Cash on delivery" — or a safe vague phrase. */
function af_payment_copy() {
    $m = af_payment_methods();
    if (!$m) return apply_filters('af_payment_copy', 'secure checkout');
    if (count($m) === 1) return apply_filters('af_payment_copy', $m[0]);
    $last = array_pop($m);
    return apply_filters('af_payment_copy', implode(', ', $m) . ' & ' . $last);
}

/**
 * DEF-02: a gateway that is switched on but cannot take the currency in use.
 *
 * Measured on the live checkout: in USD it offers zelle, square_credit_card
 * and cod. The report says CAD loses square_credit_card, leaving a shopper who
 * used the currency switcher in this site's own header with Zelle and cash on
 * delivery and no way to pay by card — and no explanation.
 *
 * The report proposes removing CAD from the switcher. That is not a decision
 * this file should make: af_allowed_currencies() returns USD and CAD under the
 * comment "spec: USD + CAD", and tools/switch-currency-cad.php exists to have
 * added it. Enabling CAD on the Square account is the real resolution and it
 * is not in this repository either.
 *
 * What is wrong here, and fixable here, is the silence. WooCommerce renders
 * checkout from get_available_payment_gateways(), which applies each gateway's
 * is_available() — that is where Square refuses a currency it does not
 * support. af_payment_methods() above reads the enabled list instead, so the
 * trust badge, the chatbot and the about page all keep promising a method the
 * checkout will not offer.
 *
 * So: compare the two lists on the checkout, and when a gateway is enabled and
 * unavailable, say so and offer the way back. If nothing is missing this
 * renders nothing at all — the notice is a report on a live condition, not an
 * assumption that the condition holds.
 *
 * @return array Titles of gateways switched on but not offered right now.
 */
function af_unavailable_gateways() {
    $missing = array();
    if (!function_exists('WC') || !WC() || !WC()->payment_gateways) return $missing;
    try {
        $available = WC()->payment_gateways->get_available_payment_gateways();
        if (!is_array($available)) return $missing;
        foreach (WC()->payment_gateways->payment_gateways() as $id => $gw) {
            if (!isset($gw->enabled) || $gw->enabled !== 'yes') continue;
            if (isset($available[$id])) continue;
            $t = trim(wp_strip_all_tags((string) $gw->get_title()));
            if ($t !== '') $missing[$t] = $t;
        }
    } catch (\Throwable $e) {
        // Nothing this function does is worth a broken checkout. An empty list
        // means the notice does not render, which is exactly today's behaviour.
        return array();
    }
    return array_values($missing);
}

add_action('woocommerce_before_checkout_form', function () {
    if (!function_exists('is_checkout') || !is_checkout()) return;
    $active = function_exists('af_active_currency') ? af_active_currency() : '';
    if ($active === '' || $active === 'USD') return;          // USD is the base; nothing to explain
    $missing = af_unavailable_gateways();
    if (!$missing) return;                                     // nothing is missing — say nothing

    $names = count($missing) === 1
        ? $missing[0]
        : implode(', ', array_slice($missing, 0, -1)) . ' and ' . end($missing);

    $back = esc_url(add_query_arg('currency', 'USD'));
    $cur  = function_exists('af_currency_name_for') ? af_currency_name_for($active) : $active;

    // Live measurement, 22 Sep: CAD loses three gateways, not one — Credit
    // Card, Cash App Pay and Square Gift Card. "X, Y and Z is not available"
    // was what a shopper actually read.
    echo '<div class="woocommerce-info af-currency-gateway-notice" role="status">'
       . esc_html(sprintf(
            /* translators: 1: payment method names, 2: currency name */
            count($missing) === 1 ? '%1$s is not available in %2$s.' : '%1$s are not available in %2$s.',
            $names, $cur
         ))
       . ' <a href="' . $back . '">'
       . esc_html__('Switch back to US Dollars to pay by card', 'postero-child')
       . '</a></div>';
}, 5);


/**
 * How the site talks about shipping — one source, so the trust badge, its
 * popup, the announcement bar and the chatbot can never contradict each other.
 *
 * The site used to promise "Free Shipping across the USA" while the badge's
 * own popup listed free delivery in four states only, so the claim was taken
 * out and the cost stated instead.
 *
 * THE SHOP DOES NOT SHIP FREE. Owner, 2026-09-10, correcting an earlier
 * instruction of the same day: "we do not ship free for any place". So the
 * default states no price at all — the cost is shown at checkout, which is
 * true whatever the rate turns out to be — and NOTHING anywhere may say
 * otherwise unless the policy genuinely changes.
 *
 * Three states, all of them explicit:
 *
 *   af_shipping_cost = ''       (the default)   "shown at checkout" — the safe
 *                                               wording, and the current policy
 *   af_shipping_cost = '$15'    or 'from $12'   that cost, stated everywhere
 *   af_shipping_cost = 'free'                   free — ONLY if that ever
 *                                               becomes true
 *
 * Blank does not mean free, and the free branch has to be asked for by name.
 * A false free-shipping claim is the exact failure this function was written
 * to prevent: it exists because the site once promised "Free Shipping across
 * the USA" while its own popup listed free delivery in four states only.
 * Filter af_shipping_copy to override any of it.
 */
function af_shipping_copy() {
    $cost = trim((string) get_option('af_shipping_cost', ''));
    $free = (strcasecmp($cost, 'free') === 0 || $cost === '0' || $cost === '$0');

    if ($free) {
        $out = array(
            'label' => 'Free Shipping Across the USA',
            'short' => 'Free shipping on all orders across the USA',
            'blurb' => 'Delivered anywhere in the USA, free on every order — no minimum, and nothing added at checkout.',
            // '0' rather than '' so the Google feed states a rate of zero,
            // which is now true, instead of omitting the rate entirely.
            'cost'  => '0',
            'free'  => true,
        );
    } elseif ($cost !== '') {
        $out = array(
            'label' => 'Shipping Throughout the USA',
            'short' => 'Shipping ' . $cost . ' throughout the USA',
            'blurb' => 'Delivered anywhere in the USA — shipping ' . $cost . ', shown before you pay.',
            'cost'  => $cost,
            'free'  => false,
        );
    } else {
        $out = array(
            'label' => 'Shipping Throughout the USA',
            'short' => 'Shipping cost shown at checkout',
            'blurb' => 'Delivered anywhere in the USA — shipping cost is shown at checkout before you pay.',
            'cost'  => '',
            'free'  => false,
        );
    }
    return apply_filters('af_shipping_copy', $out);
}

/**
 * The price book.
 *
 * $product_id is optional and changes nothing for a normal print. It exists so
 * a section with its own price level — Gold Foiled & UV — can be expressed as a
 * ratio on this one card instead of a second book that would drift out of sync:
 * pass the product and the size prices come back scaled for it. Frame and
 * colour surcharges are never scaled; a moulding costs what a moulding costs.
 */
function af_pricing_config($product_id = 0) {
    $cfg = af_pricing_config_base();
    // Frames priced by area rather than a flat fee. Aluminium is $4 a square
    // foot of the chosen size (owner, 28 Sep) - the same af_alu_rate_sqft()
    // the Premium Aluminium Frames products sell at, so the frame costs the
    // same wherever it is bought. Every price calculation reads this key.
    $cfg['frame_sqft'] = function_exists('af_alu_rate_sqft')
        ? array('Aluminium Frame' => af_alu_rate_sqft()) : array();
    if ($product_id && function_exists('af_goldfoil_factor')) {
        $factor = af_goldfoil_factor($product_id);
        if ($factor != 1.0) {
            foreach ($cfg['sizes'] as $label => $usd) {
                $cfg['sizes'][$label] = af_goldfoil_scale($usd, $factor);
            }
        }
    }
    return $cfg;
}

function af_pricing_config_base() {
    return array(
        // Size label => PRICE IN USD, straight from the printed "Pine Wood
        // Framing Sizes & Pricing" rate card. These used to be multipliers on
        // each product's listing price, which was an invented curve nobody had
        // reconciled with the card — a 4×6 came out at $608 against the card's
        // $150. The card is the price book now. Sizes the card doesn't list
        // (2×3.5, 2×4, 2.5×…, and 2×5/2.5×4/3×5 which match card areas) are
        // interpolated along the card's own per-square-foot curve and rounded
        // to $5.
        'sizes' => array(
            '2×3 ft (24×36 in)'   => 60,    // card
            '3×2 ft (36×24 in)'   => 60,    // card 2×3, turned (same 6 sq ft)
            '2×3.5 ft (24×42 in)' => 65,    // interpolated (7 sq ft)
            '2×4 ft (24×48 in)'   => 70,    // interpolated (8 sq ft)
            '2×5 ft (24×60 in)'   => 75,    // card 5×2 (same 10 sq ft)
            '2.5×3 ft (30×36 in)' => 65,    // interpolated (7.5 sq ft)
            '2.5×4 ft (30×48 in)' => 75,    // interpolated (10 sq ft)
            '2.5×5 ft (30×60 in)' => 85,    // interpolated (12.5 sq ft)
            '3×4 ft (36×48 in)'   => 80,    // card
            '3×5 ft (36×60 in)'   => 100,   // card 5×3 (same 15 sq ft)
            '3×6 ft (36×72 in)'   => 120,   // card
            '4×3 ft (48×36 in)'   => 80,    // card
            '4×4 ft (48×48 in)'   => 110,   // card
            '4×5 ft (48×60 in)'   => 130,   // card
            '4×6 ft (48×72 in)'   => 150,   // card
        ),
        // Quick-filter groups (spec: Small / Medium / Large / Custom)
        'groups' => array(
            'Small'  => array('2×3 ft (24×36 in)','3×2 ft (36×24 in)','2×3.5 ft (24×42 in)','2.5×3 ft (30×36 in)'),
            'Medium' => array('2×4 ft (24×48 in)','2.5×4 ft (30×48 in)','3×4 ft (36×48 in)','4×3 ft (48×36 in)'),
            'Large'  => array('2×5 ft (24×60 in)','2.5×5 ft (30×60 in)','3×5 ft (36×60 in)','3×6 ft (36×72 in)','4×4 ft (48×48 in)','4×5 ft (48×60 in)','4×6 ft (48×72 in)'),
        ),
        // Wall-suitability hints (spec: "Best for 10×12 ft walls")
        'hints' => array(
            '2×3 ft (24×36 in)'   => 'Best for 6×8 ft walls & cozy corners',
            '3×2 ft (36×24 in)'   => 'Best for 6×8 ft walls & cozy corners (landscape)',
            '2×3.5 ft (24×42 in)' => 'Best for 6×8 ft walls',
            '2×4 ft (24×48 in)'   => 'Best for 8×10 ft walls',
            '2×5 ft (24×60 in)'   => 'Best above sofas & consoles',
            '2.5×3 ft (30×36 in)' => 'Best for 7×9 ft walls',
            '2.5×4 ft (30×48 in)' => 'Best for 8×10 ft walls',
            '2.5×5 ft (30×60 in)' => 'Best for 9×11 ft walls',
            '3×4 ft (36×48 in)'   => 'Best for 9×11 ft walls',
            '3×5 ft (36×60 in)'   => 'Best for 10×12 ft walls',
            '3×6 ft (36×72 in)'   => 'Statement piece — 10×12 ft+ walls',
            '4×3 ft (48×36 in)'   => 'Best for 9×11 ft walls (landscape)',
            '4×4 ft (48×48 in)'   => 'Best for 10×12 ft walls',
            '4×5 ft (48×60 in)'   => 'Grand walls & double-height spaces',
            '4×6 ft (48×72 in)'   => 'Extra large — lobbies & feature walls',
        ),
        // Frame type => flat add-on fee (USD). Floating matches the rate
        // card's "ADD FLOATING FRAME: +$50"; the card is silent on the others.
        'frames' => array(
            'Without Frame'   => 0,
            'Fibre Frame'     => 25,
            'Floating Frame'  => 50,
            'Aluminium Frame' => 55,
        ),
        // Frame color => flat add-on fee (USD)
        'colors' => array(
            'Black'     => 0,
            'Silver'    => 0,
            'Gold'      => 10,
            'Rose Gold' => 10,
        ),
    );
}

/**
 * The discount percentage shown against a product's struck-through reference
 * price. Every product used to sit at the same figure, which reads as a
 * pricing rule rather than a saving, so each one gets its own percentage
 * inside the band below.
 *
 * Deterministic, not actually random: the value is derived from the product
 * id, so a product shows the same percentage on every page load and on every
 * re-run of tools/apply-mrp-markup.php. A genuinely random number would move
 * the struck price each time the script ran.
 */
function af_mrp_discount_pct( $product_id ) {
    $min = 20;   // gentlest saving shown
    $max = 45;   // largest saving shown
    $hash = abs( crc32( 'af-mrp-' . (int) $product_id ) );
    return $min + ( $hash % ( $max - $min + 1 ) );
}

/**
 * What to multiply the selling price by to get that product's reference
 * price. Derived from the percentage so the badge the theme computes back
 * out of the two prices lands on exactly af_mrp_discount_pct().
 */
function af_mrp_multiplier( $product_id ) {
    $pct = af_mrp_discount_pct( $product_id );
    if ( $pct <= 0 || $pct >= 100 ) return 1.0;
    return 1 / ( 1 - $pct / 100 );
}

/**
 * Frame types we can currently make. Everything else in af_pricing_config()
 * stays priced and previewable — the selector still shows the full range — but
 * is offered as OUT OF STOCK and cannot be chosen or ordered. Editing this one
 * list is all it takes to put a frame back on sale.
 */
function af_frames_in_stock() {
    return array( 'Without Frame', 'Aluminium Frame' );
}

function af_frame_is_in_stock( $frame ) {
    return in_array( $frame, af_frames_in_stock(), true );
}

/** The frames currently NOT available, in the same order the card lists them. */
/**
 * The first frame in stock that is actually a frame. "Without Frame" opens the
 * selector (it is first and always in stock), which is right for a rolled
 * print and wrong for "Painting + structure bars + frame": that kit has a
 * frame in it by definition, so where nothing better is chosen it gets this.
 */
function af_frame_first_real() {
    foreach ( array_keys( af_pricing_config()['frames'] ) as $f ) {
        if ( $f !== 'Without Frame' && af_frame_is_in_stock( $f ) ) return $f;
    }
    return '';
}
function af_frames_out_of_stock() {
    return array_values( array_filter(
        array_keys( af_pricing_config()['frames'] ),
        function ( $f ) { return ! af_frame_is_in_stock( $f ); }
    ) );
}

/** The sizes we no longer sell, in the order the price book lists them. */
function af_sizes_out_of_stock() {
    return array_values( array_filter(
        array_keys( af_pricing_config()['sizes'] ),
        function ( $s ) { return ! af_size_is_offered( $s ); }
    ) );
}

/**
 * Every option a filter may list that cannot actually be bought — frames we
 * cannot make and sizes we no longer sell, in one list.
 */
function af_filter_oos_labels() {
    return array_merge( af_frames_out_of_stock(), af_sizes_out_of_stock() );
}

/**
 * Does this label name an option we cannot sell — either frame or size?
 *
 * Sizes needed the same treatment as frames and for the same reason: the
 * filter offered all fourteen with a count beside each while the product page
 * sells five. It is worth being clear about one difference, because it looks
 * like an inconsistency and is not: the product page DROPS a size it does not
 * sell and STRIKES a frame it cannot make. In the filter both are struck, so
 * a shopper who knows the size exists is told plainly that it is unavailable
 * rather than left wondering where it went.
 */
function af_filter_label_is_oos( $label ) {
    $clean = trim( preg_replace( '/\s+/', ' ',
        preg_replace( '/\s*\(\s*\d+\s*\)\s*$/', '', wp_strip_all_tags( (string) $label ) ) ) );
    if ( $clean === '' ) return false;
    foreach ( af_filter_oos_labels() as $oos ) {
        if ( strcasecmp( $oos, $clean ) === 0 ) return true;
    }
    return false;
}

/**
 * Does this label name a frame we cannot currently make?
 *
 * Written to take a label rather than a clean name because the places that
 * need the answer are filter widgets, where the text arrives as the term name
 * with a count stuck on the end — "Fibre Frame (337)" — and sometimes wrapped
 * in markup. Returns false for anything that is not one of our frames at all,
 * so it is safe to run over every label on a page.
 */
function af_frame_label_is_oos( $label ) {
    $clean = trim( preg_replace( '/\s*\(\s*\d+\s*\)\s*$/', '', wp_strip_all_tags( (string) $label ) ) );
    if ( $clean === '' ) return false;
    foreach ( array_keys( af_pricing_config()['frames'] ) as $frame ) {
        if ( strcasecmp( $frame, $clean ) === 0 ) return ! af_frame_is_in_stock( $frame );
    }
    return false;
}

/** The frame a product opens on: the first in-stock one in config order. */
function af_frame_default() {
    $frames = array_keys( af_pricing_config()['frames'] );
    foreach ( $frames as $f ) { if ( af_frame_is_in_stock( $f ) ) return $f; }
    return $frames[0];
}

/**
 * Sizes we currently offer. Unlike frames — which stay on the page struck
 * through — a size we do not sell is simply not shown: nine greyed-out chips
 * would be noise, not information. The rest of af_pricing_config()['sizes']
 * stays put so the price book, the size-from-title lookup and every existing
 * order keep resolving exactly as before. Offering a size again is one edit
 * to this list.
 */
function af_sizes_offered() {
    return array(
        '2×3 ft (24×36 in)',
        '3×2 ft (36×24 in)',
        '2.5×3 ft (30×36 in)',
        '3×4 ft (36×48 in)',
        '3×5 ft (36×60 in)',
    );
}

function af_size_is_offered( $size ) {
    return in_array( $size, af_sizes_offered(), true );
}

/** Offered sizes in price-book order, so the selector reads small → large. */
function af_sizes_available() {
    return array_values( array_filter(
        array_keys( af_pricing_config()['sizes'] ), 'af_size_is_offered' ) );
}

/**
 * The size a product opens on: its own titled size when we still offer it,
 * otherwise the first size we do offer.
 */
function af_size_default( $product = null ) {
    $avail = af_sizes_available();
    if ( ! $avail ) return array_key_first( af_pricing_config()['sizes'] );
    if ( $product ) {
        $titled = af_size_label_for_product( $product );
        if ( $titled !== '' && in_array( $titled, $avail, true ) ) return $titled;
    }
    return $avail[0];
}

/**
 * The size a product is titled as — "…Canvas Wall Art 3x4 Feet", "60 x 36
 * Inch…" — resolved to its selector label. This is what makes the numbers
 * agree "variation-wise": the listing price, the pre-selected size chip and
 * the cart default all key off the product's own size instead of everything
 * defaulting to 2×3. Returns '' when the title carries no recognisable size.
 */
function af_size_label_for_product($product) {
    $name = is_object($product) ? $product->get_name() : (string) $product;
    if (!preg_match('/(\d+(?:\.\d+)?)\s*[x×]\s*(\d+(?:\.\d+)?)\s*(ft|feet|foot|inches|inch|in)?\b/iu', $name, $m)) return '';
    $a = (float) $m[1]; $b = (float) $m[2];
    $u = strtolower(isset($m[3]) ? $m[3] : '');
    if ($u === '') $u = ($a > 12 || $b > 12) ? 'in' : 'ft';   // a bare "36x48" is inches
    if (strpos($u, 'in') === 0) { $a /= 12; $b /= 12; }
    $labels = array();
    foreach (af_pricing_config()['sizes'] as $label => $usd) {
        if (preg_match('/^(\d+(?:\.\d+)?)×(\d+(?:\.\d+)?) ft/u', $label, $lm)) {
            $labels[$label] = array((float) $lm[1], (float) $lm[2]);
        }
    }
    foreach ($labels as $label => $d) {                        // exact orientation
        if (abs($d[0] - $a) < 0.26 && abs($d[1] - $b) < 0.26) return $label;
    }
    foreach ($labels as $label => $d) {                        // same print, turned
        if (abs($d[0] - $b) < 0.26 && abs($d[1] - $a) < 0.26) return $label;
    }
    return '';
}

// Authoritative server-side price calculation.
// The size sets the price outright from the rate card — the product's listing
// price no longer multiplies into it ($base stays in the signature so the
// call sites don't churn, and in case a per-product premium ever returns).
// The colour fee pays for the frame's finish, so an unframed (gallery-wrapped)
// print is never charged for one — there is no moulding to finish.
/**
 * What a frame adds for this size: square feet × its rate when it is priced
 * by area (cfg frame_sqft), otherwise its flat fee.
 * Aluminium at $4/sq ft: 2×3 $24, 2.5×3 $30, 3×4 $48, 3×5 $60.
 */
function af_frame_fee($cfg, $frame, $size) {
    if (!empty($cfg['frame_sqft'][$frame]) && function_exists('af_ship_inches')) {
        $in = af_ship_inches($size);
        if ($in) return round($in[0] * $in[1] / 144 * (float) $cfg['frame_sqft'][$frame], 2);
    }
    return isset($cfg['frames'][$frame]) ? (float) $cfg['frames'][$frame] : 0.0;
}

function af_calc_price($base, $size, $frame, $color, $product_id = 0) {
    $cfg = af_pricing_config($product_id);
    $sizes = $cfg['sizes'];
    $price = isset($sizes[$size]) ? (float)$sizes[$size] : (float)reset($sizes);
    $fee  = af_frame_fee($cfg, $frame, $size);
    if ($frame !== 'Without Frame') {
        $fee += (isset($cfg['colors'][$color]) ? (float)$cfg['colors'][$color] : 0);
    }
    return round($price + $fee, 2);
}

// 8a. Render selectors inside the add-to-cart form (simple products)
add_action('woocommerce_before_add_to_cart_button', function() {
    $product = af_wc_product();
    if (!$product || !af_pricing_applies($product) || !$product->is_purchasable() || !$product->is_in_stock()) return;
    // priced FOR THIS PRODUCT: the Gold Foiled & UV section rides a ratio on
    // the same card, and data-config below is what the selector recomputes from
    $cfg  = af_pricing_config($product->get_id());
    if ($product->is_type('variable')) {
        $min  = (float) $product->get_variation_price('min');
        $base = $min > 0 ? $min : (float) wc_get_price_to_display($product);
    } else {
        $base = (float) wc_get_price_to_display($product);
    }
    // only the sizes we actually offer reach the selector
    $sizes  = af_sizes_available();
    $frames = array_keys($cfg['frames']);
    $colors = array_keys($cfg['colors']);
    // pre-select the size the product is titled as, so the opening price IS
    // this product's price — not every product pretending to be a 2×3
    $def_size = af_size_default($product);
    // Frames that are out of stock are shown but cannot be picked, so the
    // selector opens on the first one we can actually make.
    $def_frame = af_frame_default();
    ?>
    <div class="af-opts" id="af-opts" data-base="<?php echo esc_attr($base); ?>" data-config='<?php echo esc_attr(wp_json_encode($cfg)); ?>' data-symbol="<?php echo esc_attr(get_woocommerce_currency_symbol()); ?>" data-rate="<?php echo esc_attr(function_exists('af_fx_rate') ? af_fx_rate() : 1); ?>" data-mrp-mult="<?php echo esc_attr(round(af_mrp_multiplier($product->get_id()), 6)); ?>">
      <div class="af-opt-group">
        <label class="af-opt-label" for="af-size-select">Size <span class="af-opt-sub">(height × width)</span></label>
        <div class="af-size-row">
          <select id="af-size-select" class="af-size-select" data-type="size">
            <?php foreach ($sizes as $s): ?>
              <option value="<?php echo esc_attr($s); ?>"<?php selected($s, $def_size); ?>><?php echo esc_html($s); ?></option>
            <?php endforeach; ?>
          </select>
          <a class="af-chip-grp af-chip-custom" href="/customize-your-picture/">Custom ↗</a>
        </div>
      </div>
      <p class="af-wall-hint" id="afWallHint"></p>
      <div class="af-opt-group">
        <label class="af-opt-label">Frame Type</label>
        <div class="af-chips af-frame-chips">
          <?php foreach ($frames as $i => $f): $fee=af_frame_fee($cfg, $f, $def_size); $oos = !af_frame_is_in_stock($f); ?>
            <button type="button" class="af-chip-opt<?php echo $f===$def_frame?' active':''; ?><?php echo $oos?' af-chip-oos':''; ?>" data-type="frame" data-val="<?php echo esc_attr($f); ?>"<?php echo $oos?' disabled aria-disabled="true"':''; ?>><?php if(!$oos && $f==='Floating Frame') echo '<span class="af-rec">Recommended</span>'; ?><?php echo esc_html($f); ?><?php if($fee>0) echo ' <em>+'.get_woocommerce_currency_symbol().$fee.'</em>'; ?><?php if($oos) echo ' <span class="af-oos">Out of stock</span>'; ?></button>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="af-opt-group">
        <label class="af-opt-label">Frame Color <span class="af-opt-sub af-color-needs">(choose a frame first)</span></label>
        <div class="af-chips af-color-chips">
          <?php $swatch=array('Black'=>'#1a1a1a','Silver'=>'#c0c0c0','Gold'=>'#d4af37','Rose Gold'=>'#b76e79'); foreach ($colors as $i => $c): $cfee = isset($cfg['colors'][$c]) ? (float) $cfg['colors'][$c] : 0; ?>
            <button type="button" class="af-swatch<?php echo $i===0?' active':''; ?>" data-type="color" data-val="<?php echo esc_attr($c); ?>" title="<?php echo esc_attr($c); ?>"><span style="background:<?php echo esc_attr($swatch[$c]??'#ccc'); ?>"></span><?php echo esc_html($c); ?><?php if($cfee>0) echo ' <em class="af-swatch-fee">+'.get_woocommerce_currency_symbol().$cfee.'</em>'; ?></button>
          <?php endforeach; ?>
        </div>
      </div>
      <p class="af-color-tip">🎨 Color may slightly vary due to lighting</p>
      <div class="af-price-live">Your Price: <strong id="af-live-price"><?php echo wc_price($base); ?></strong>
        <s id="af-live-mrp" class="af-live-mrp"></s>
        <span id="af-live-disc" class="af-live-disc"></span></div>
      <p class="af-price-notes">✓ Inclusive of all taxes &nbsp;·&nbsp; 📦 Free Secure Packaging</p>
      <input type="hidden" name="af_size"  value="<?php echo esc_attr($def_size); ?>">
      <input type="hidden" name="af_frame" value="<?php echo esc_attr($def_frame); ?>">
      <input type="hidden" name="af_color" value="<?php echo esc_attr($colors[0]); ?>">
    </div>
    <?php
}, 8);

// 8b. Capture selections + compute authoritative price on add to cart
add_filter('woocommerce_add_cart_item_data', function($data, $pid) {
    if (af_request_wants_digital()) return $data; // digital handled separately
    $product = wc_get_product($pid);
    if (!$product || !af_pricing_applies($product)) return $data;
    $cfg = af_pricing_config($pid);
    // quick add-to-cart (no options chosen) defaults to the product's OWN
    // titled size, so a 4×6-titled piece is never silently sold as a 2×3
    $size  = isset($_POST['af_size'])  ? sanitize_text_field(wp_unslash($_POST['af_size']))
             : af_size_default($product);
    $frame = isset($_POST['af_frame']) ? sanitize_text_field(wp_unslash($_POST['af_frame'])) : af_frame_default();
    $color = isset($_POST['af_color']) ? sanitize_text_field(wp_unslash($_POST['af_color'])) : array_key_first($cfg['colors']);
    // Validate against config. A size or frame we no longer offer is refused
    // here too, not just hidden in the selector, so a stale page, a saved
    // preview link or a hand-made POST cannot order one.
    if (!isset($cfg['sizes'][$size]) || !af_size_is_offered($size)) $size = af_size_default($product);
    if (!isset($cfg['frames'][$frame]) || !af_frame_is_in_stock($frame)) $frame = af_frame_default();
    if (!isset($cfg['colors'][$color])) $color = array_key_first($cfg['colors']);
    // "Painting only" and "Painting + structure bars + DIY kit" arrive rolled,
    // with no frame, so a frame chip left selected on the page is not charged.
    $kit_posted = isset($_POST['af_kit']) ? sanitize_key(wp_unslash($_POST['af_kit'])) : '';
    if ($kit_posted !== '' && isset($cfg['frames']['Without Frame'])
        && in_array($kit_posted, array('painting', 'painting_bar'), true)) {
        $frame = 'Without Frame';
    }
    // "Painting + structure bars + frame + DIY kit" has a frame in it by
    // definition. The page opens on Without Frame, so a shopper who picked the
    // framed kit and nothing else was charged for bars alone and would have
    // received no frame (owner's recording, 26 Sep). The kit gets the first
    // frame in stock, the same one the page switches to.
    if ($kit_posted === 'painting_bar_frame' && $frame === 'Without Frame') {
        $real = af_frame_first_real();
        if ($real !== '') $frame = $real;
    }
    $data['af_size']  = $size;
    $data['af_frame'] = $frame;
    $data['af_color'] = $color;
    $af_base = $product->is_type('variable') ? (float) $product->get_variation_price('min') : (float) wc_get_price_to_display($product);
    if ($af_base <= 0) $af_base = (float) wc_get_price_to_display($product);
    $data['af_price'] = af_calc_price($af_base, $size, $frame, $color, $pid);
    $data['af_unique'] = md5($size.'|'.$frame.'|'.$color.'|'.$pid);
    return $data;
}, 10, 2);

// 8c. Apply the computed price in the cart
add_action('woocommerce_before_calculate_totals', function($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;
    if (empty($cart) || !is_a($cart, 'WC_Cart')) return;
    foreach ($cart->get_cart() as $item) {
        if (isset($item['af_price']) && $item['af_price'] > 0) {
            $item['data']->set_price($item['af_price']);
        }
    }
}, 20);

// 8d. Show selected options in cart/checkout
add_filter('woocommerce_get_item_data', function($data, $item) {
    foreach (array('af_size'=>'Size','af_frame'=>'Frame Type','af_color'=>'Frame Color') as $k=>$label) {
        if (empty($item[$k])) continue;
        // A gallery-wrapped print has no moulding, so it has no finish. The
        // colour is still stored — af_calc_price reads it, and it is what the
        // shopper would return to if they added a frame — but printing "Frame
        // Color: Black" beside "Frame Type: Without Frame" describes a thing
        // that is not in the box.
        if ($k === 'af_color' && isset($item['af_frame']) && $item['af_frame'] === 'Without Frame') continue;
        $data[] = array('name'=>$label, 'value'=>$item[$k]);
    }
    return $data;
}, 10, 2);

// 8e. Persist selected options to the order line items
add_action('woocommerce_checkout_create_order_line_item', function($item, $key, $values) {
    foreach (array('af_size'=>'Size','af_frame'=>'Frame Type','af_color'=>'Frame Color') as $k=>$label) {
        if (empty($values[$k])) continue;
        // Same gate as the cart, so the order, the invoice and the packing
        // list describe the same object the shopper was shown.
        if ($k === 'af_color' && isset($values['af_frame']) && $values['af_frame'] === 'Without Frame') continue;
        $item->add_meta_data($label, $values[$k]);
    }
}, 10, 3);

// 8f. Selector styling + live price JS.
//     Loaded site-wide (all .af-* class-scoped) so the Size/Frame/Color chips,
//     swatches and live price look identical inside the Quick View modal.
add_action('wp_head', function() {
    ?>
    <style>
    .af-opts{margin:14px 0 6px;padding:16px 0 4px;border-top:1px solid #eee;}
    .af-opt-group{margin:0 0 14px;}
    .af-opt-label{display:block;font-size:12px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;color:#444;margin:0 0 8px;}
    .af-chips{display:flex;flex-wrap:wrap;gap:8px;}
    .af-chip-opt{background:#fff;border:1.5px solid #ddd;border-radius:8px;padding:8px 14px;font-size:13px;font-weight:600;color:#333;cursor:pointer;transition:all .15s;}
    .af-chip-opt em{font-style:normal;color:var(--af-gold-text, #8a6d1f);font-weight:700;}
    .af-chip-opt:hover{border-color:#c9a84c;}
    .af-chip-opt.active{border-color:#1a1a1a;background:#1a1a1a;color:#fff;}
    .af-chip-opt.active em{color:#e8c766;}
    /* Out-of-stock frames stay visible (so the range still reads) but are
       plainly unavailable: greyed, struck through and not clickable. */
    .af-chip-opt.af-chip-oos{background:#f6f6f6;border-color:#e4e4e4;color:#a3a3a3;cursor:not-allowed;
      text-decoration:line-through;text-decoration-color:#c9c9c9;}
    .af-chip-opt.af-chip-oos:hover{border-color:#e4e4e4;}
    .af-chip-opt.af-chip-oos em{color:#b5b5b5;}
    .af-chip-opt .af-oos{display:inline-block;margin-left:6px;padding:1px 6px;border-radius:4px;
      background:#ececec;color:#8a8a8a;font-size:10px;font-weight:800;letter-spacing:.04em;
      text-transform:uppercase;text-decoration:none;vertical-align:1px;}
    .af-swatch{display:inline-flex;align-items:center;gap:7px;background:#fff;border:1.5px solid #ddd;border-radius:8px;padding:6px 12px 6px 8px;font-size:12.5px;font-weight:600;color:#333;cursor:pointer;transition:all .15s;}
    .af-swatch span{width:18px;height:18px;border-radius:50%;border:1px solid rgba(0,0,0,.15);display:inline-block;}
    .af-swatch em.af-swatch-fee{font-style:normal;color:var(--af-gold-text, #8a6d1f);font-weight:700;}
    /* The finish fee pays for the moulding, so af_calc_price does not charge it
       on an unframed print — and the label must not claim it either. The
       default frame is "Without Frame" (af_frame_default returns the first one
       in stock), so this is the state a product page opens in. */
    .af-opts.af-color-free em.af-swatch-fee{display:none;}
    .af-swatch:hover{border-color:#c9a84c;}
    .af-swatch.active{border-color:#1a1a1a;}
    /* The theme's generic button:hover/:focus paints buttons black, which in
       the recording turned a pressed swatch into a black pill with grey text.
       A swatch keeps its own look in every state. */
    .af-opts .af-swatch:hover,.af-opts .af-swatch:focus,.af-opts .af-swatch:active,.af-opts .af-swatch.active{
      background:#fff !important;color:#333 !important;box-shadow:none !important;}
    /* Without Frame: no moulding, so no colour. Shown, but plainly off. */
    .af-color-needs{display:none;}
    .af-opts.af-color-free .af-color-needs{display:inline;}
    .af-opts.af-color-free .af-color-chips .af-swatch{opacity:.38;cursor:not-allowed;border-color:#ddd !important;}
    .af-opts.af-color-free .af-color-chips .af-swatch:hover{border-color:#ddd !important;}
    /* Size is a dropdown rather than a chip grid — fourteen chips was a wall,
       and the list is short enough now that a select reads faster. */
    .af-size-row{display:flex;flex-wrap:wrap;align-items:center;gap:10px;}
    .af-size-select{flex:1 1 220px;max-width:340px;background:#fff;border:1.5px solid #ddd;border-radius:8px;
      padding:10px 34px 10px 14px;font-size:14px;font-weight:600;color:#1a1a1a;cursor:pointer;
      transition:border-color .15s;appearance:none;-webkit-appearance:none;
      background-image:url("data:image/svg+xml;charset=UTF-8,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%23666' d='M1 1h10L6 7z'/%3E%3C/svg%3E");
      background-repeat:no-repeat;background-position:right 13px center;}
    .af-size-select:hover{border-color:#c9a84c;}
    .af-size-select:focus{outline:none;border-color:#1a1a1a;box-shadow:0 0 0 3px rgba(201,168,76,.18);}
    .af-price-live{margin:6px 0 0;font-size:15px;color:#333;}
    .af-price-live strong{font-size:20px;color:#1a1a1a;}
    .af-price-live .amount{font-weight:800;}
    </style>
    <script id="af-price-list" src="<?php echo esc_url(af_static_url('price-list.js')); ?>" data-no-optimize="1"></script>
    <?php
}, 23);

// ─────────────────────────────────────────────────────────────
// PHASE 9 — Styled FAQ accordion (rendered via hook, not in content).
// Fixes duplicate FAQ + missing styling. Shows once on every product.
// ─────────────────────────────────────────────────────────────
function af_product_faqs() {
    return array(
        array('What is canvas wall art made of?', 'Our canvas wall art is made using premium-quality, high-density artist-grade canvas combined with durable wooden or metal frames. The material ensures vibrant color reproduction, long-lasting durability, and a premium gallery-style finish suitable for homes and offices.'),
        array('Is digital canvas printing long-lasting?', 'Yes, digital canvas printing is highly durable when produced with fade-resistant pigment inks and premium canvas materials. Under normal indoor conditions, our canvas prints maintain their color vibrancy and quality for many years.'),
        array('Does canvas wall art fade over time?', 'Our canvas prints are produced using advanced fade-resistant inks that help prevent color fading. When kept away from direct sunlight and moisture, the artwork retains its color richness for years.'),
        array('Is canvas wall art better than paper posters?', 'Yes, canvas wall art offers better durability, texture, and a premium appearance compared to paper posters. Canvas prints provide a realistic artistic look and are more resistant to tearing and fading.'),
        array('Is canvas wall art a good gift option?', 'Yes, canvas wall art is an excellent gift choice for housewarming events, weddings, festivals, birthdays, and special occasions.'),
        array('How can I contact customer support?', 'You can contact our customer support team for any canvas wall art or digital canvas printing inquiries through phone, email, or WhatsApp. Our team assists with product details, customization requests, order updates, and installation guidance.'),
        array('What is your customer support contact number?', 'You can reach our customer support team at +1 (610) 470-7280 for assistance related to product inquiries, order tracking, and customization support.'),
        array('What is your customer support email address?', 'You can contact us via email at theartframer136@gmail.com for product inquiries, bulk orders, or support-related questions.'),
        array('Do you provide WhatsApp support?', 'Yes, we provide WhatsApp support for quick assistance with product selection, order inquiries, and customization requests. You can message us on WhatsApp at +1 (610) 470-7280.'),
        array('Where is your canvas printing business located?', 'Our canvas printing business operates from Delaware, USA, and we provide delivery services across multiple locations.'),
        // wording follows af_shipping_copy() so this FAQ cannot promise
        // something the badge, the bar and the chatbot no longer say
        array('Where do you deliver, and what does shipping cost?',
              (function_exists('af_shipping_copy') ? af_shipping_copy()['blurb']
               : 'We ship throughout the USA — the cost is shown at checkout.')
              . ' We ship from our Delaware studio to every state.'),
    );
}

af_section(function() {
    if (!af_show_product_sections()) return;   // page + quick-view modal, nothing else
    $faqs = af_product_faqs();
    if (empty($faqs)) return;
    echo '<section class="af-pp-sec af-faq"><h2>Frequently Asked Questions</h2><div class="af-faq-list">';
    foreach ($faqs as $i => $f) {
        echo '<details class="af-faq-item"'.($i===0?' open':'').'>';
        echo '<summary class="af-faq-q">'.esc_html($f[0]).'</summary>';
        echo '<div class="af-faq-a">'.esc_html($f[1]).'</div>';
        echo '</details>';
    }
    echo '</div></section>';
}, 14);

// FAQ accordion styling (site-wide; .af-faq-* class-scoped, matches modal)
add_action('wp_head', function() {
    ?>
    <style>
    .af-faq-list{border:1px solid #eee;border-radius:12px;overflow:hidden;}
    .af-faq-item{border-bottom:1px solid #eee;background:#fff;}
    .af-faq-item:last-child{border-bottom:none;}
    .af-faq-q{list-style:none;cursor:pointer;padding:16px 46px 16px 18px;font-size:14.5px;font-weight:700;color:#1a1a1a;position:relative;transition:background .15s;user-select:none;}
    .af-faq-q::-webkit-details-marker{display:none;}
    .af-faq-q:hover{background:#faf7ef;}
    .af-faq-q::after{content:'+';position:absolute;right:18px;top:50%;transform:translateY(-50%);font-size:22px;font-weight:400;color:#c9a84c;line-height:1;transition:transform .2s;}
    .af-faq-item[open] .af-faq-q::after{content:'−';}
    .af-faq-item[open] .af-faq-q{background:#faf7ef;color:var(--af-gold-text, #8a6d1f);}
    .af-faq-a{padding:0 18px 18px;font-size:13.5px;line-height:1.7;color:#555;}
    </style>
    <?php
}, 24);

// ─────────────────────────────────────────────────────────────
// PHASE 10 — Shop tag filter (works on Elementor archives) + force
// price-filter widget to USD. Additive; other sections untouched.
// ─────────────────────────────────────────────────────────────

// 10a. Pin the WooCommerce price-slider/filter widget to the active currency
add_filter('woocommerce_currency', function(){ return af_active_currency(); }, PHP_INT_MAX);
add_filter('woocommerce_currency_symbol', function($s,$c){ return af_currency_symbol_for($c); }, PHP_INT_MAX, 2);
add_filter('woocommerce_price_slider_params', function($p){
    $p['currency_format_symbol']   = af_currency_symbol_for(af_active_currency());
    $p['currency_format_num_decimals'] = 0;
    return $p;
}, PHP_INT_MAX);

// 10a-2. Keep FOX/WOOCS off geoip (it used to geo-switch visitors to INR) and
// pin its current currency to the visitor's whitelisted choice (USD or CAD).
// Conversion is bypassed only for USD (base); CAD converts at the WOOCS rate.
add_filter('woocs_is_geoip_activated', '__return_false', PHP_INT_MAX);
add_filter('woocs_geoip_country', function(){ return 'US'; }, PHP_INT_MAX);
add_filter('woocs_current_currency', function(){ return af_active_currency(); }, PHP_INT_MAX);
add_filter('woocs_convert_price', function($price){
    return $price; // passthrough; WOOCS handles CAD conversion via its rate
}, 1);
add_action('init', function(){
    // WOOCS reads this cookie first — pin it to the whitelisted choice before the plugin runs
    $active = af_active_currency();
    if (!isset($_COOKIE['woocs_current_currency']) || $_COOKIE['woocs_current_currency'] !== $active) {
        @setcookie('woocs_current_currency', $active, time()+YEAR_IN_SECONDS, defined('COOKIEPATH')?COOKIEPATH:'/', defined('COOKIE_DOMAIN')?COOKIE_DOMAIN:'');
        $_COOKIE['woocs_current_currency'] = $active;
    }
    if (session_status() === PHP_SESSION_ACTIVE) { $_SESSION['woocs_current_currency'] = $active; }
    $def = get_option('woocs_default_currency', '');
    if ($def !== 'USD') update_option('woocs_default_currency', 'USD');
}, 0);
add_action('wp_loaded', function(){
    if (isset($GLOBALS['WOOCS']) && is_object($GLOBALS['WOOCS'])) {
        $w = $GLOBALS['WOOCS'];
        $active = af_active_currency();
        if (method_exists($w, 'set_currency')) { $w->set_currency($active); }
        if (property_exists($w, 'current_currency')) { $w->current_currency = $active; }
        if (property_exists($w, 'default_currency')) { $w->default_currency = 'USD'; }
    }
}, 1);

// 10b. Guarantee ?product_tag= filters the main product query (so it works
//      even on Elementor-built archives that use the main query)
add_action('woocommerce_product_query', function($q){
    if (is_admin()) return;
    if (!empty($_GET['product_tag'])) {
        $slug = sanitize_title(wp_unslash($_GET['product_tag']));
        $tax = (array) $q->get('tax_query');
        $tax[] = array('taxonomy'=>'product_tag','field'=>'slug','terms'=>array($slug));
        $q->set('tax_query', $tax);
    }
});

// 10c. Inject a clickable Tag filter bar above the product grid on shop/category
add_action('wp_footer', function() {
    if (!function_exists('is_shop')) return;
    if (!(is_shop() || is_product_category() || is_product_tag())) return;

    $tags = get_terms(array('taxonomy'=>'product_tag','hide_empty'=>true,'orderby'=>'count','order'=>'DESC','number'=>30));
    if (is_wp_error($tags) || !$tags) return;

    $active = isset($_GET['product_tag']) ? sanitize_title($_GET['product_tag']) : '';
    $keep = $_GET; unset($keep['paged']);
    $items = array();
    foreach ($tags as $t) {
        $q = $keep;
        if ($active === $t->slug) { unset($q['product_tag']); $on = true; }
        else { $q['product_tag'] = $t->slug; $on = false; }
        $items[] = array('name'=>$t->name, 'url'=>'?'.http_build_query($q), 'on'=>$on);
    }
    ?>
    <script>
    (function(){
      var tags = <?php echo wp_json_encode($items); ?>;
      if(!tags.length) return;
      // Find the "Categories" widget in the sidebar so we can place Tags below it
      function findCategoriesWidget(){
        var heads = document.querySelectorAll('h1,h2,h3,h4,h5,h6,.elementor-heading-title,.widget-title,.wp-block-heading,.widgettitle');
        for(var i=0;i<heads.length;i++){
          if(heads[i].textContent.trim().toLowerCase()==='categories'){
            return heads[i].closest('.elementor-widget, aside .widget, .widget, section, .elementor-element') || heads[i].parentElement;
          }
        }
        return null;
      }
      function build(){
        if(document.querySelector('.af-tagbar')) return true;

        var bar = document.createElement('div'); bar.className='af-tagbar af-tagbar-side';
        var lbl = document.createElement('div'); lbl.className='af-tagbar-label'; lbl.textContent='Filter by Tag';
        bar.appendChild(lbl);
        var wrap = document.createElement('div'); wrap.className='af-tagbar-chips';
        tags.forEach(function(t){
          var a=document.createElement('a');
          a.className='af-tag-btn2'+(t.on?' active':'');
          a.href=t.url; a.textContent='#'+t.name;
          wrap.appendChild(a);
        });
        bar.appendChild(wrap);

        // Preferred: place in the sidebar right after the Categories widget
        var catWidget = findCategoriesWidget();
        if(catWidget && catWidget.parentNode){
          catWidget.parentNode.insertBefore(bar, catWidget.nextSibling);
          return true;
        }
        // Fallback: above the product grid
        var anchor = document.querySelector('ul.products') || document.querySelector('.products') || document.querySelector('.woocommerce-result-count');
        if(anchor && anchor.parentNode){ bar.classList.remove('af-tagbar-side'); anchor.parentNode.insertBefore(bar, anchor); return true; }
        return false;
      }
      if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',build); else build();
      window.addEventListener('load', function(){ build(); setTimeout(build,700); setTimeout(build,1600); });
    })();
    </script>
    <style>
    /* Above-grid fallback layout */
    .af-tagbar{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin:0 0 20px;padding:0 0 6px;}
    /* Sidebar layout (preferred) */
    .af-tagbar-side{display:block;margin:22px 0 10px;padding:0;}
    .af-tagbar .af-tagbar-label{font-size:13px;font-weight:800;color:#555;margin-right:2px;}
    .af-tagbar-side .af-tagbar-label{display:block;font-size:17px;font-weight:800;color:#1a1a1a;margin:0 0 14px;}
    .af-tagbar-side .af-tagbar-chips{display:flex;flex-wrap:wrap;gap:7px;}
    .af-tag-btn2{display:inline-block;padding:6px 12px;border-radius:16px;background:#fff;border:1.5px solid #e2ddcf;color:#6b6250;font-size:12px;font-weight:600;text-decoration:none;transition:all .15s;cursor:pointer;}
    .af-tag-btn2:hover{border-color:#c9a84c;color:#a8872e;}
    .af-tag-btn2.active{background:#c9a84c;border-color:#c9a84c;color:#fff;}
    </style>
    <?php
}, 30);

// ─────────────────────────────────────────────────────────────
// PHASE 11 — Sidebar Color filter (clickable swatches), injected into
// the sidebar like the tag filter. Uses pa_colors (?filter_colors).
// Additive; other sections untouched.
// ─────────────────────────────────────────────────────────────

// 11a. Guarantee ?filter_colors= narrows the main product query (Elementor-safe)
add_action('woocommerce_product_query', function($q){
    if (is_admin()) return;
    if (!empty($_GET['filter_colors'])) {
        $slugs = array_filter(array_map('sanitize_title', explode(',', wp_unslash($_GET['filter_colors']))));
        if ($slugs) {
            $tax = (array) $q->get('tax_query');
            $tax[] = array('taxonomy'=>'pa_colors','field'=>'slug','terms'=>$slugs,'operator'=>'IN');
            $q->set('tax_query', $tax);
        }
    }
});

// 11b. Inject the Color filter into the sidebar
add_action('wp_footer', function() {
    if (!function_exists('is_shop')) return;
    if (!(is_shop() || is_product_category() || is_product_tag())) return;

    $colors = get_terms(array('taxonomy'=>'pa_colors','hide_empty'=>true,'number'=>12));
    if (is_wp_error($colors) || !$colors) return;

    $swatch = array('black'=>'#1a1a1a','silver'=>'#c0c0c0','gold'=>'#d4af37','rose-gold'=>'#b76e79');
    $active = isset($_GET['filter_colors']) ? sanitize_title($_GET['filter_colors']) : '';
    $keep = $_GET; unset($keep['paged']);
    $items = array();
    foreach ($colors as $t) {
        $q = $keep;
        if ($active === $t->slug) { unset($q['filter_colors']); $on = true; }
        else { $q['filter_colors'] = $t->slug; $q['query_type_colors']='or'; $on = false; }
        $items[] = array(
            'name'=>$t->name, 'slug'=>$t->slug, 'on'=>$on,
            'hex'=>($swatch[$t->slug] ?? '#cccccc'),
            'url'=>'?'.http_build_query($q),
        );
    }
    ?>
    <script>
    (function(){
      var colors = <?php echo wp_json_encode($items); ?>;
      if(!colors.length) return;
      function findWidget(label){
        var heads = document.querySelectorAll('h1,h2,h3,h4,h5,h6,.elementor-heading-title,.widget-title,.wp-block-heading,.widgettitle');
        for(var i=0;i<heads.length;i++){
          if(heads[i].textContent.trim().toLowerCase()===label){
            return heads[i].closest('.elementor-widget, aside .widget, .widget, section, .elementor-element') || heads[i].parentElement;
          }
        }
        return null;
      }
      function build(){
        if(document.querySelector('.af-colorbar')) return true;
        var box = document.createElement('div'); box.className='af-colorbar';
        var lbl = document.createElement('div'); lbl.className='af-colorbar-label'; lbl.textContent='Frame Color';
        box.appendChild(lbl);
        var wrap = document.createElement('div'); wrap.className='af-colorbar-swatches';
        colors.forEach(function(c){
          var a=document.createElement('a');
          a.className='af-swatch2'+(c.on?' active':'');
          a.href=c.url; a.title=c.name;
          var dot=document.createElement('span'); dot.className='af-swatch2-dot'; dot.style.background=c.hex;
          var nm=document.createElement('span'); nm.className='af-swatch2-name'; nm.textContent=c.name;
          a.appendChild(dot); a.appendChild(nm); wrap.appendChild(a);
        });
        box.appendChild(wrap);
        // place after Frame widget, else after Size, else after Categories, else above grid
        var anchor = findWidget('frame') || findWidget('size') || findWidget('categories');
        if(anchor && anchor.parentNode){ anchor.parentNode.insertBefore(box, anchor.nextSibling); return true; }
        var grid = document.querySelector('ul.products') || document.querySelector('.woocommerce-result-count');
        if(grid && grid.parentNode){ box.classList.add('af-colorbar-top'); grid.parentNode.insertBefore(box, grid); return true; }
        return false;
      }
      if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',build); else build();
      window.addEventListener('load', function(){ build(); setTimeout(build,700); setTimeout(build,1600); });
    })();
    </script>
    <style>
    .af-colorbar{margin:22px 0 10px;}
    .af-colorbar-label{font-size:17px;font-weight:800;color:#1a1a1a;margin:0 0 14px;}
    .af-colorbar-swatches{display:flex;flex-direction:column;gap:9px;}
    .af-swatch2{display:inline-flex;align-items:center;gap:9px;text-decoration:none;color:#555;font-size:13px;font-weight:600;padding:4px 6px;border-radius:8px;border:1.5px solid transparent;transition:all .15s;}
    .af-swatch2:hover{color:#a8872e;background:#faf7ef;}
    .af-swatch2.active{border-color:#c9a84c;background:#faf7ef;color:#1a1a1a;}
    .af-swatch2-dot{width:20px;height:20px;border-radius:50%;border:1px solid rgba(0,0,0,.2);flex-shrink:0;}
    .af-colorbar-top .af-colorbar-swatches{flex-direction:row;flex-wrap:wrap;gap:10px;}
    </style>
    <?php
}, 31);

// ─────────────────────────────────────────────────────────────
// PHASE 12 — Fix "Try It On Your Wall" nav link to point to the page.
// ─────────────────────────────────────────────────────────────
add_filter('wp_nav_menu_objects', function($items){
    // Resolve the Try-On-Wall page URL (fall back to a sensible slug)
    $page = get_page_by_path('try-on-wall');
    if (!$page) { $page = get_page_by_path('try-it-on-your-wall'); }
    $url = $page ? get_permalink($page) : home_url('/try-on-wall/');
    foreach ($items as $it) {
        $t = strtolower(trim(wp_strip_all_tags($it->title)));
        if (strpos($t,'try') !== false && strpos($t,'wall') !== false) {
            $it->url = $url;
            // Clear any "#" or empty target that blocks navigation
            $it->target = '';
        }
    }
    return $items;
}, 20);

// Drop the "Social Media", "Contact Us" and "About" items (and their dropdown
// children) from the header navigation only, leaving that bar as Blog. Footer
// menus and standalone social icon links are left alone.
add_filter('wp_nav_menu_objects', function($items, $args){
    $loc = isset($args->theme_location) ? strtolower((string) $args->theme_location) : '';
    if ($loc !== '' && strpos($loc, 'footer') !== false) return $items;

    // Belt-and-braces footer guard for menus rendered without a theme
    // location (widgets, page builders): anything self-describing as a footer
    // menu keeps every item.
    foreach (array('menu', 'menu_class', 'container_class', 'menu_id') as $k) {
        if (empty($args->$k)) continue;
        $v = $args->$k;
        if (is_object($v)) { $v = isset($v->slug) ? $v->slug : (isset($v->name) ? $v->name : ''); }
        if (is_string($v) && stripos($v, 'footer') !== false) return $items;
    }

    $is_header = ($loc !== '' && preg_match('/primary|header|main|top/', $loc));
    if (!$is_header) {
        // No usable location (builder menus): identify the header nav by the
        // items it carries. Checked BEFORE anything is removed, so dropping
        // those items below does not break this detection.
        $markers = 0;
        foreach ($items as $it) {
            $t = strtolower(wp_strip_all_tags($it->title));
            if (strpos($t, 'contact') !== false || strpos($t, 'about') !== false
                || strpos($t, 'blog') !== false) { $markers++; }
        }
        if ($markers < 2) return $items;
    }

    $remove = array();
    foreach ($items as $it) {
        $t = strtolower(trim(wp_strip_all_tags($it->title)));
        $t = preg_replace('/\s+/', ' ', $t);
        if ($t === 'social media' || $t === 'social' || $t === 'social medias'
            || $t === 'contact us' || $t === 'contact'
            || $t === 'about' || $t === 'about us') {
            $remove[] = (int) $it->ID;
        }
    }
    if (!$remove) return $items;

    // Pull the dropdown children down with the parent.
    $changed = true;
    while ($changed) {
        $changed = false;
        foreach ($items as $it) {
            if (in_array((int) $it->menu_item_parent, $remove, true)
                && !in_array((int) $it->ID, $remove, true)) {
                $remove[] = (int) $it->ID;
                $changed  = true;
            }
        }
    }
    return array_values(array_filter($items, function($it) use ($remove) {
        return !in_array((int) $it->ID, $remove, true);
    }));
}, 25, 2);

// DOM fallback for builder-rendered headers that never pass through
// wp_nav_menu_objects. Same scoping rule: only the nav holding Contact, and
// never inside a footer.
add_action('wp_footer', function() {
    if (is_admin()) return;
    ?>
<script>
(function(){
  function strip(){
    var lists = document.querySelectorAll('ul');
    for (var i = 0; i < lists.length; i++) {
      var ul = lists[i];
      if (ul.closest('footer, .footer, #footer, .site-footer, .elementor-location-footer')) continue;
      var links = ul.querySelectorAll(':scope > li > a');
      if (links.length < 2) continue;                 // not a real nav bar
      // Identify the header nav by its remaining items, so this keeps
      // matching after Contact Us is gone.
      var markers = 0;
      for (var j = 0; j < links.length; j++) {
        if (/contact|about|blog/i.test(links[j].textContent || '')) markers++;
      }
      if (markers < 2) continue;
      for (var k = 0; k < links.length; k++) {
        var txt = (links[k].textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
        if (txt === 'social media' || txt === 'social'
            || txt === 'contact us' || txt === 'contact'
            || txt === 'about' || txt === 'about us') {
          var li = links[k].closest('li');
          if (li) li.style.setProperty('display', 'none', 'important');
        }
      }
    }
  }
  document.addEventListener('DOMContentLoaded', strip);
  window.addEventListener('load', strip);
  [300, 1000, 2200].forEach(function(d){ setTimeout(strip, d); });
})();
</script>
<?php }, 60);

// ─────────────────────────────────────────────────────────────
// PHASE 13 — Fully dynamic standalone "Try It On Your Wall" page.
// Replaces the static Elementor mockup at /try-on-wall/ with a working
// tool: Category→Product pickers, frame type/size/color, wall-photo
// upload, live framed preview, drag + scale, live price, buy link.
// ─────────────────────────────────────────────────────────────
add_action('template_redirect', function(){
    if (!function_exists('is_page') || !is_page(array('try-on-wall','try-it-on-your-wall'))) return;
    if (!function_exists('wc_get_products')) return;

    // Build catalog data. Skip utility categories that aren't real shopping
    // groups: size cats ("3x4 ft"), frame-color/size/type cats and their
    // children (Black/Gold/Silver…), uncategorized.
    $cats = get_terms(array('taxonomy'=>'product_cat','hide_empty'=>true,'orderby'=>'name'));
    $cat_items = array();
    $skip_slugs = array('frame-colors','frame-sizes','frame-types','black','gold','silver','rose-gold','uncategorized');
    $skip_parents = array();
    if (!is_wp_error($cats)) {
        foreach ($cats as $c) {
            if (in_array($c->slug, array('frame-colors','frame-sizes','frame-types'), true)) $skip_parents[] = (int) $c->term_id;
        }
        foreach ($cats as $c) {
            if (in_array($c->slug, $skip_slugs, true)) continue;
            if (in_array((int) $c->parent, $skip_parents, true)) continue;
            if (preg_match('/^\d+(?:\.\d+)?\s*[x×]\s*\d+/iu', $c->name)) continue;   // "3x4 ft" size cats
            $cat_items[] = array('slug'=>$c->slug,'name'=>$c->name);
        }
    }

    $ids = wc_get_products(array('status'=>'publish','limit'=>-1,'return'=>'ids'));
    $prod_items = array();
    foreach ($ids as $pid) {
        $p = wc_get_product($pid);
        if (!$p || !$p->get_image_id()) continue;
        $prod_items[] = array(
            'id'=>$pid,
            'name'=>$p->get_name(),
            'cats'=>wp_get_post_terms($pid,'product_cat',array('fields'=>'slugs')),
            'img'=>wp_get_attachment_image_url($p->get_image_id(),'large'),
            'price'=>(float) wc_get_price_to_display($p),
            'gf'=>function_exists('af_goldfoil_factor') ? af_goldfoil_factor($pid) : 1,
            'url'=>get_permalink($pid),
        );
    }
    $cfg = function_exists('af_pricing_config') ? af_pricing_config() : array(
        'sizes'=>array('24×36 in'=>1.0,'30×45 in'=>1.4,'36×54 in'=>1.9,'48×72 in'=>2.7),
        'frames'=>array('Without Frame'=>0,'Fibre Frame'=>25,'Floating Frame'=>40,'Aluminium Frame'=>55),
        'colors'=>array('Black'=>0,'Silver'=>0,'Gold'=>10,'Rose Gold'=>10),
    );
    $sym = get_woocommerce_currency_symbol();

    get_header();
    af_preview_share_assets();
    ?>
    <div class="af-tow-wrap">
      <div class="af-tow-card">
        <a class="af-tow-home" href="<?php echo esc_url(home_url('/')); ?>">← Back to Home</a>
        <span class="af-tow-badge">Live Preview</span>
        <h1 class="af-tow-title">See It on Your Wall</h1>
        <p class="af-tow-sub">Choose an artwork and frame, then place it in a real room — or upload a photo of your own wall to preview it to scale.</p>

        <div class="af-tow-grid">
          <div class="af-tow-panel">
            <div class="af-tow-step">
              <span class="af-tow-stepnum">1</span>
              <span class="af-tow-steptitle">Pick your artwork</span>
            </div>
            <label>Category</label>
            <select id="tow-cat"><option value="">All Categories</option></select>

            <label>Choose Product</label>
            <select id="tow-prod"><option value="">Select a product</option></select>

            <div class="af-tow-step">
              <span class="af-tow-stepnum">2</span>
              <span class="af-tow-steptitle">Style the frame</span>
            </div>
            <label>Frame Type</label>
            <select id="tow-frame"></select>

            <label>Frame Size</label>
            <select id="tow-size"></select>

            <label>Frame Color</label>
            <div id="tow-colorsw" class="af-tow-swatches"></div>
            <select id="tow-color" class="af-tow-hiddensel"></select>

            <label>Wall Layout</label>
            <div class="af-tow-layouts" id="tow-layouts">
              <button type="button" class="af-tow-lay on" data-n="1"><span>Single</span></button>
              <button type="button" class="af-tow-lay" data-n="2"><span>2 Panels</span></button>
              <button type="button" class="af-tow-lay" data-n="4"><span>4 Panels</span></button>
            </div>

            <!-- Wall height belongs here, in the panel, because it changes what
                 the visitor is looking at: a 3 ft print covers more of an 8 ft
                 wall than of a 10 ft one. There is a second copy of this row
                 inside the camera calibration overlay, which is where it used
                 to live ALONE — and that overlay only exists while the camera
                 is running, and hides itself a second after the wall locks. So
                 for anyone using a room photo the control was not on screen at
                 all. Both rows drive af_tow_set_wall_ft() and stay in step. -->
            <label>Wall height</label>
            <div class="af-tow-wallh" id="tow-wallh">
              <button type="button" data-ft="8">8 ft</button>
              <button type="button" data-ft="9">9 ft</button>
              <button type="button" data-ft="10" class="on">10 ft</button>
            </div>

            <div class="af-tow-price"><span>Your price</span><strong id="tow-price">—</strong></div>
            <div class="af-tow-actions">
              <button type="button" id="tow-save" class="af-tow-btn ghost">⤓ Save Preview</button>
              <a href="#" id="tow-view" class="af-tow-btn solid">View Product</a>
            </div>

            <p class="af-sharelabel">Keep it &amp; share it</p>
            <div class="af-share">
              <button type="button" id="tow-saveacct">💾 Save to my account</button>
            </div>
            <div class="af-share">
              <button type="button" id="tow-share-wa" class="af-share-wa">WhatsApp</button>
              <button type="button" id="tow-share-mail">Email</button>
              <button type="button" id="tow-share-copy">Copy link</button>
            </div>
            <p class="af-tow-fine">
              Saved previews live in your account.
              <a href="#" id="tow-acctlink" style="display:none">View saved previews →</a>
            </p>
          </div>

          <div class="af-tow-stagewrap">
            <!-- Phones only. The camera's two controls used to sit ON the video:
                 the instruction box across the top of it and the ✕ in its top
                 right corner, both over the very wall the visitor is lining up.
                 They are drawn here instead, in a strip above the picture, and
                 the copies inside the stage are hidden at this width. This is a
                 strip, not a move: #tow-calmsg is hidden by its PARENT #tow-cal
                 when the wall locks, so lifting that node out of the stage would
                 leave the instruction on screen after it stopped being true. -->
            <div id="tow-camhead" class="af-tow-camhead" style="display:none">
              <span class="af-tow-camhead-msg"></span>
              <button type="button" class="af-tow-camhead-ft" style="display:none">10 ft</button>
              <button type="button" class="af-tow-camhead-recal" style="display:none">📐 Recalibrate</button>
              <button type="button" class="af-tow-camhead-x" aria-label="Stop camera">✕</button>
            </div>
            <div id="tow-stage" class="af-tow-stage">
              <img id="tow-wallimg" class="af-tow-wallimg" alt="">
              <video id="tow-cam" class="af-tow-cam" autoplay playsinline muted></video>
              <div id="tow-placeholder" class="af-tow-ph"><span class="af-tow-ph-ic">🖼️</span>Choose a product on the left to preview it on the wall. Drag to reposition, and use the slider to resize.</div>
              <div id="tow-framebox" class="af-tow-framebox" style="display:none">
                <div id="tow-panels" class="af-tow-panels"></div>
                <span class="af-tow-hint">✥ drag</span>
              </div>
              <button type="button" id="tow-camstop" class="af-tow-camstop" style="display:none" aria-label="Stop camera">✕<span class="af-tow-camstop-t"> Stop camera</span></button>
              <!-- live-camera calibration: fit the wall into the rectangle and it
                   turns green — from that moment scale is MEASURED, not assumed -->
              <div id="tow-cal" class="af-tow-cal" style="display:none">
                <div id="tow-calbox" class="af-tow-calbox">
                  <span class="af-tow-calcorner tl"></span><span class="af-tow-calcorner tr"></span>
                  <span class="af-tow-calcorner bl"></span><span class="af-tow-calcorner br"></span>
                </div>
                <div id="tow-calmsg" class="af-tow-calmsg"><span class="af-tow-calmsg-long">Step back or forward until the <strong>ceiling line</strong> touches the top edge and the <strong>floor line</strong> touches the bottom edge</span><span class="af-tow-calmsg-short"><strong>Ceiling</strong> to the top edge, <strong>floor</strong> to the bottom</span></div>
                <div id="tow-calh" class="af-tow-calh">
                  <span>Wall height</span>
                  <button type="button" data-ft="8">8 ft</button>
                  <button type="button" data-ft="9">9 ft</button>
                  <button type="button" data-ft="10" class="on">10 ft</button>
                </div>
              </div>
              <button type="button" id="tow-recal" class="af-tow-recal" style="display:none">📐 True scale locked · tap to recalibrate</button>
              <div id="tow-toast" class="af-tow-toast"></div>
            </div>
            <p class="af-tow-tip">Tip: drag the artwork to line it up with your furniture, then hit <strong>Save Preview</strong> — the image downloads to your device.</p>

            <!-- Step 3 sits under the wall it controls: it fills the column
                 beside the (much taller) options rail instead of leaving the
                 page half empty, and the room switcher is next to the room. -->
            <div class="af-tow-panel af-tow-room">
              <div class="af-tow-step">
                <span class="af-tow-stepnum">3</span>
                <span class="af-tow-steptitle">Set the room</span>
              </div>
              <div class="af-tow-roomgrid">
                <div class="af-tow-roomcol">
                  <label>Room Scene</label>
                  <div id="tow-scenes" class="af-tow-scenes"></div>
                  <label class="af-tow-upload"><input type="file" id="tow-wall" accept="image/*" hidden><span>⬆ Upload your own wall photo</span></label>
                </div>
                <div class="af-tow-roomcol">
                  <button type="button" id="tow-cambtn" class="af-tow-cambtn">🎥 Use live camera <em>point it at your wall</em></button>
                  <p class="af-tow-camnote">When the guide turns green the wall is matched: the picture is held still, and saved to your device and to your account.</p>
                  <p class="af-tow-scalenote" id="tow-scalenote">📏 Shown true to scale on a 10&nbsp;ft wall</p>
                  <label>Adjust Size <span id="tow-scaleval">100%</span></label>
                  <input type="range" id="tow-scale" min="40" max="160" value="100">
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <script>
    (function(){
      var CATS = <?php echo wp_json_encode($cat_items); ?>;
      var PRODUCTS = <?php echo wp_json_encode($prod_items); ?>;
      window.AFProducts = PRODUCTS;   // same array, for the test harness (already public in page source)
      var CFG = <?php echo wp_json_encode($cfg); ?>;
      var SYM = <?php echo wp_json_encode($sym); ?>;
      // Frames we can actually make — the rest are listed but not selectable.
      var INSTOCK = <?php echo wp_json_encode(function_exists('af_frames_in_stock') ? af_frames_in_stock() : array_keys($cfg['frames'])); ?>;
      // Sizes we currently offer — the rest are not listed at all.
      var SIZES = <?php echo wp_json_encode(function_exists('af_sizes_available') ? af_sizes_available() : array_keys($cfg['sizes'])); ?>;
      var $=function(id){return document.getElementById(id);};

      // Populate selects
      CATS.forEach(function(c){ var o=document.createElement('option'); o.value=c.slug; o.textContent=c.name; $('tow-cat').appendChild(o); });
      Object.keys(CFG.frames).forEach(function(f){ var o=document.createElement('option'); o.value=f;
        var oos = INSTOCK.indexOf(f) === -1;
        var perSq = CFG.frame_sqft && CFG.frame_sqft[f];
        o.textContent=f+(perSq?(' (+'+SYM+perSq+'/sq ft)'):(CFG.frames[f]>0?(' (+'+SYM+CFG.frames[f]+')'):''))+(oos?' — Out of stock':'');
        o.disabled = oos; if(!oos && !$('tow-frame').value) o.selected = true;
        $('tow-frame').appendChild(o); });
      SIZES.forEach(function(s){ var o=document.createElement('option'); o.value=s; o.textContent=s; $('tow-size').appendChild(o); });
      Object.keys(CFG.colors).forEach(function(c){ var o=document.createElement('option'); o.value=c; o.textContent=c+(CFG.colors[c]>0?(' (+'+SYM+CFG.colors[c]+')'):''); $('tow-color').appendChild(o); });

      // Realistic frame material gradients (bevel comes from CSS box-shadows)
      var SWATCH={'Black':'#1e1e1e','Silver':'#c0c0c0','Gold':'#d4af37','Rose Gold':'#b76e79'};
      var MAT={
        'Black':'linear-gradient(135deg,#4a4a4a 0%,#1c1c1c 42%,#050505 58%,#333 100%)',
        'Silver':'linear-gradient(135deg,#fdfdfd 0%,#c4c4c4 42%,#8f8f8f 58%,#e6e6e6 100%)',
        'Gold':'linear-gradient(135deg,#fbe7ad 0%,#d8b445 42%,#a67c1e 58%,#f2d879 100%)',
        'Rose Gold':'linear-gradient(135deg,#f7d3c8 0%,#dca596 42%,#b76e79 58%,#efbcae 100%)'
      };
      // Layers a mitred corner seam (the diagonal joint where two moulding
      // sides meet on a real frame) plus a polished-metal specular glint on
      // top of the material gradient — matched to reference photos of the
      // actual aluminium mouldings (buffed sheen + hard highlight, not a
      // brushed/sandy texture). Corner lines use a fixed px width via
      // calc() so they read the same on a thumbnail as on a full-size stage.
      function frameBG(mat){
        var hi='rgba(255,255,255,.45)', lo='rgba(0,0,0,.5)';
        return [
          // mitred corner grooves: a lit inner edge then a dark outer edge per
          // quadrant, so each corner reads as a carved cut rather than a
          // printed line
          'linear-gradient(to bottom right, transparent calc(50% - 2.5px), '+hi+' calc(50% - 1.5px), '+lo+' calc(50% + .5px), transparent calc(50% + 2px)) left top/50% 50% no-repeat',
          'linear-gradient(to bottom left,  transparent calc(50% - 2.5px), '+hi+' calc(50% - 1.5px), '+lo+' calc(50% + .5px), transparent calc(50% + 2px)) right top/50% 50% no-repeat',
          'linear-gradient(to top right,    transparent calc(50% - 2.5px), '+hi+' calc(50% - 1.5px), '+lo+' calc(50% + .5px), transparent calc(50% + 2px)) left bottom/50% 50% no-repeat',
          'linear-gradient(to top left,     transparent calc(50% - 2.5px), '+hi+' calc(50% - 1.5px), '+lo+' calc(50% + .5px), transparent calc(50% + 2px)) right bottom/50% 50% no-repeat',
          // polished-metal glint: a narrow, hard-edged bright streak swept
          // around the ring so the top/left face catches a sharp specular
          // highlight the way buffed aluminium does, with a fainter second
          // glint opposite it
          'conic-gradient(from 300deg at 50% 50%, rgba(255,255,255,0) 0deg, rgba(255,255,255,.85) 16deg, rgba(255,255,255,0) 38deg, rgba(255,255,255,0) 175deg, rgba(255,255,255,.5) 196deg, rgba(255,255,255,0) 218deg, rgba(255,255,255,0) 360deg)',
          // broad wraparound light/shadow so each of the 4 faces reads as its
          // own lit or shaded plane (upper-left key light), pushed to higher
          // contrast so the moulding reads as metal, not painted flat colour
          'conic-gradient(from 315deg at 50% 50%, rgba(255,255,255,.5) 0deg, rgba(255,255,255,0) 95deg, rgba(0,0,0,.55) 180deg, rgba(0,0,0,0) 265deg, rgba(255,255,255,.5) 360deg)',
          mat
        ].join(',');
      }

      // ── PHOTOREAL FRAME TEXTURES ─────────────────────────────────────────
      // Paints an actual picture of a frame — mitred corners, a moulding
      // profile lit from above, and per-material surface texture (brushed
      // metal, satin lacquer, fibre grain) — on an offscreen canvas, matched
      // to the studio's real moulding photos. Applied with border-image, so
      // the corners stay true 45° joints at any panel size, and drawn into
      // the saved preview by 9-slice so the download matches the screen.
      var TEXQ = {};
      // Desaturated faces, patina-dark recesses and CREAM highlights — never
      // white. Full-saturation colour with a white-hot crown is exactly what
      // made the first pass read as plastic.
      function framePalette(color){
        return ({
          'Black':     {face:'#282520', hi:'#6e6a62', crown:'#3d3a34', lo:'#0a0806'},
          'Silver':    {face:'#b6b9bd', hi:'#eef0f2', crown:'#d0d3d7', lo:'#585c61'},
          'Gold':      {face:'#b08a40', hi:'#f0dfa4', crown:'#d2ab58', lo:'#553f10'},
          'Rose Gold': {face:'#a86f60', hi:'#e2bfae', crown:'#c28d7c', lo:'#4e2e25'}
        })[color] || null;
      }
      // Fine monochrome grain, tiled over the moulding at low alpha so flat
      // runs of colour stop looking computer-clean. Deterministic, so every
      // render of a texture is identical.
      var NOISETILE = null;
      function noiseTile(){
        if (NOISETILE) return NOISETILE;
        var n = document.createElement('canvas'); n.width = 128; n.height = 128;
        var nx = n.getContext('2d'), id = nx.createImageData(128, 128);
        var s = 13; function r(){ s = (s * 16807) % 2147483647; return s / 2147483647; }
        for (var i = 0; i < id.data.length; i += 4) {
          var v = Math.floor(r() * 255);
          id.data[i] = v; id.data[i+1] = v; id.data[i+2] = v; id.data[i+3] = 16;
        }
        nx.putImageData(id, 0, 0);
        NOISETILE = n; return n;
      }
      function frameTexture(frame, color){
        var key = frame + '|' + color;
        if (TEXQ[key]) return TEXQ[key];
        var pal = framePalette(color) || framePalette('Black');
        var S = 512, B = 96;
        var cv = document.createElement('canvas'); cv.width = S; cv.height = S;
        var g = cv.getContext('2d');

        // One horizontal strip of moulding, grain running along its length.
        var strip = document.createElement('canvas'); strip.width = S; strip.height = B;
        var sg = strip.getContext('2d');
        // The moulding profile, lit from the top. Every transition passes
        // through crown (a midtone) on its way to hi, so the highlight rolls
        // off the way light leaves a curved surface — a stop that jumps
        // straight from face to hi is the hard bright band that read as fake.
        var grad = sg.createLinearGradient(0, 0, 0, B);
        grad.addColorStop(0.00, pal.lo);
        grad.addColorStop(0.05, pal.crown);
        grad.addColorStop(0.13, pal.face);
        grad.addColorStop(0.30, pal.crown);
        grad.addColorStop(0.42, pal.hi);
        grad.addColorStop(0.50, pal.crown);
        grad.addColorStop(0.68, pal.face);
        grad.addColorStop(0.88, pal.lo);
        grad.addColorStop(0.935, pal.crown);
        grad.addColorStop(1.00, pal.lo);
        sg.fillStyle = grad; sg.fillRect(0, 0, S, B);
        // Deterministic pseudo-random, so every render of a texture is identical
        var seed = 7; function rnd(){ seed = (seed * 16807) % 2147483647; return seed / 2147483647; }
        if (frame === 'Aluminium Frame') {
          // brushed metal: dense hairlines along the extrusion
          for (var i = 0; i < 110; i++) {
            var y = rnd() * B;
            sg.strokeStyle = 'rgba(' + (rnd() < 0.5 ? '255,255,255' : '0,0,0') + ',' + (0.028 + rnd() * 0.062).toFixed(3) + ')';
            sg.lineWidth = 0.6 + rnd() * 0.9;
            sg.beginPath(); sg.moveTo(0, y); sg.lineTo(S, y); sg.stroke();
          }
        } else if (frame === 'Fibre Frame') {
          // fibre/wood: long shallow grain waves with occasional darker streaks
          for (var j = 0; j < 26; j++) {
            var gy = 4 + rnd() * (B - 8), amp = 0.6 + rnd() * 1.8, wav = 70 + rnd() * 160, ph = rnd() * 6.28;
            var dark = rnd() < 0.28;
            sg.strokeStyle = dark ? 'rgba(0,0,0,' + (0.10 + rnd() * 0.08).toFixed(3) + ')'
                                  : 'rgba(255,255,255,' + (0.035 + rnd() * 0.05).toFixed(3) + ')';
            sg.lineWidth = dark ? 1.2 : 0.8;
            sg.beginPath();
            for (var x = 0; x <= S; x += 7) {
              var yy = gy + Math.sin(x / wav + ph) * amp;
              x === 0 ? sg.moveTo(x, yy) : sg.lineTo(x, yy);
            }
            sg.stroke();
          }
        } else {
          // Floating frame: satin lacquer — a soft sheen, almost no pattern
          for (var k = 0; k < 18; k++) {
            var sy = rnd() * B;
            sg.strokeStyle = 'rgba(255,255,255,' + (0.015 + rnd() * 0.03).toFixed(3) + ')';
            sg.lineWidth = 1.5;
            sg.beginPath(); sg.moveTo(0, sy); sg.lineTo(S, sy); sg.stroke();
          }
        }
        // slow luminance drift along the stick — real moulding is never one
        // even value end to end
        var lw = sg.createLinearGradient(0, 0, S, 0);
        lw.addColorStop(0, 'rgba(255,255,255,.04)');
        lw.addColorStop(0.3, 'rgba(0,0,0,.03)');
        lw.addColorStop(0.55, 'rgba(255,255,255,.05)');
        lw.addColorStop(0.8, 'rgba(0,0,0,.04)');
        lw.addColorStop(1, 'rgba(255,255,255,.02)');
        sg.fillStyle = lw; sg.fillRect(0, 0, S, B);
        // crisp outer and rebate edges
        sg.fillStyle = 'rgba(0,0,0,.38)'; sg.fillRect(0, 0, S, 1); sg.fillRect(0, B - 1, S, 1);

        // Lay the strip on all four sides, each clipped to its mitred trapezoid
        // so the grain runs along every side and the corners join at 45°.
        // Each side then takes its own exposure from the room's key light,
        // above and slightly left: top rail lit, left rail faintly lit, right
        // rail shaded, bottom rail in shadow. One flat gradient over the whole
        // ring — the old approach — is precisely what a photograph never does.
        var sides = [
          {path: [[0,0],[S,0],[S-B,B],[B,B]],       tx: 0, ty: 0, rot: 0,           lum:  0.09},
          {path: [[S,0],[S,S],[S-B,S-B],[S-B,B]],   tx: S, ty: 0, rot: Math.PI/2,   lum: -0.06},
          {path: [[S,S],[0,S],[B,S-B],[S-B,S-B]],   tx: S, ty: S, rot: Math.PI,     lum: -0.14},
          {path: [[0,S],[0,0],[B,B],[B,S-B]],       tx: 0, ty: S, rot: -Math.PI/2,  lum:  0.03}
        ];
        sides.forEach(function(sd){
          g.save();
          g.beginPath();
          sd.path.forEach(function(pt, n){ n === 0 ? g.moveTo(pt[0], pt[1]) : g.lineTo(pt[0], pt[1]); });
          g.closePath(); g.clip();
          g.translate(sd.tx, sd.ty); g.rotate(sd.rot);
          g.drawImage(strip, 0, 0);
          g.setTransform(1, 0, 0, 1, 0, 0);
          g.fillStyle = sd.lum > 0 ? 'rgba(255,255,255,' + sd.lum + ')' : 'rgba(0,0,0,' + (-sd.lum) + ')';
          g.fillRect(0, 0, S, S);
          g.restore();
        });
        // mitre seams: the dark joint line with a lit edge beside it — present,
        // as in the moulding photos, but a joint, not a drawn-on stripe
        [[0,0,B,B],[S,0,S-B,B],[S,S,S-B,S-B],[0,S,B,S-B]].forEach(function(c){
          g.strokeStyle = 'rgba(0,0,0,.28)'; g.lineWidth = 1.6;
          g.beginPath(); g.moveTo(c[0], c[1]); g.lineTo(c[2], c[3]); g.stroke();
          g.strokeStyle = 'rgba(255,255,255,.12)'; g.lineWidth = 0.8;
          g.beginPath(); g.moveTo(c[0] + (c[2] > c[0] ? 1.6 : -1.6), c[1]); g.lineTo(c[2] + (c[2] > c[0] ? 1.6 : -1.6), c[3]); g.stroke();
        });
        // a soft light-catch where the key light grazes the top rail
        g.globalCompositeOperation = 'source-atop';
        var blob = g.createRadialGradient(S * 0.30, B * 0.45, 4, S * 0.30, B * 0.45, S * 0.28);
        blob.addColorStop(0, 'rgba(255,255,255,.22)');
        blob.addColorStop(1, 'rgba(255,255,255,0)');
        g.fillStyle = blob; g.fillRect(0, 0, S, S);
        // film grain over the whole ring
        g.globalAlpha = 0.06;
        var nt = noiseTile();
        for (var ny = 0; ny < S; ny += 128) for (var nx2 = 0; nx2 < S; nx2 += 128) g.drawImage(nt, nx2, ny);
        g.globalAlpha = 1;
        g.globalCompositeOperation = 'source-over';

        TEXQ[key] = {url: cv.toDataURL('image/png'), cv: cv, B: B, S: S};
        return TEXQ[key];
      }
      // Border width for the current profile, from the panel's real on-screen
      // size — border-width cannot take %, so this runs whenever the box is
      // (re)measured.
      function sizeFrameBorders(){
        var fb = $('tow-framebox');
        fb.querySelectorAll('.af-tow-pframe').forEach(function(frEl){
          var prof = parseFloat(frEl.getAttribute('data-prof') || '0');
          if (!(prof > 0)) { frEl.style.borderWidth = '0'; return; }
          var w = frEl.parentNode.getBoundingClientRect().width || 0;
          frEl.style.borderWidth = Math.max(11, w * prof / 100).toFixed(1) + 'px';
        });
      }

      // Colour swatches (drive the hidden select for pricing/logic)
      (function buildSwatches(){
        var wrap=$('tow-colorsw'); wrap.innerHTML='';
        Object.keys(CFG.colors).forEach(function(c,i){
          var b=document.createElement('button'); b.type='button'; b.className='af-tow-sw'+(i===0?' on':'');
          b.setAttribute('data-c',c); b.title=c+(CFG.colors[c]>0?(' (+'+SYM+CFG.colors[c]+')'):'');
          b.style.background=SWATCH[c]||'#1a1a1a';
          b.innerHTML='<span>'+c+'</span>';
          b.addEventListener('click',function(){
            if(b.disabled) return;
            wrap.querySelectorAll('.af-tow-sw').forEach(function(x){x.classList.remove('on');});
            b.classList.add('on'); $('tow-color').value=c; refresh();
          });
          wrap.appendChild(b);
        });
      })();
      // Same rule as the product page: "Without Frame" has no moulding, so the
      // colour swatches are switched off - the price already ignored them.
      function syncTowColor(){
        var off = $('tow-frame').value === 'Without Frame';
        $('tow-colorsw').classList.toggle('af-tow-sw-off', off);
        $('tow-colorsw').querySelectorAll('.af-tow-sw').forEach(function(x){ x.disabled = off; });
      }
      $('tow-frame').addEventListener('change', syncTowColor);
      syncTowColor();

      // ── Photorealistic room scenes (SVG data-URIs, no external assets) ──
      // Bright modern living room styled like a real product mockup:
      // floor-to-ceiling window on the right casting a soft light patch on
      // the wall, curtains, pale wood floor in perspective, woven rug,
      // light sofa cropped at the left with a throw pillow + side table,
      // slim floor lamp, and a fiddle-leaf fig on the right.
      function room(o){
        var W=1600,H=1000,FLOOR=690;              // wall/floor split line
        var wall=o.wall, wall2=o.wall2, floor=o.floor, floor2=o.floor2;
        var sofa=o.sofa, sofa2=o.sofa2, rug=o.rug, curtain=o.curtain, pot=o.pot;
        // Receding floor plank seams (converge outward toward viewer)
        var seams='';
        for(var sx=-500; sx<=2100; sx+=150){
          var nx=800+(sx-800)*2.3;
          seams+='<line x1="'+sx+'" y1="'+FLOOR+'" x2="'+nx.toFixed(0)+'" y2="'+H+'"/>';
        }
        var rungs=''; var n=6;
        for(var i=1;i<=n;i++){ var t=Math.pow(i/n,2.1); var y=(FLOOR+(H-FLOOR)*t).toFixed(1);
          rungs+='<line x1="0" y1="'+y+'" x2="'+W+'" y2="'+y+'"/>'; }
        // fiddle-leaf helper
        function leaf(cx,cy,rx,ry,rot,fill){ return '<ellipse cx="'+cx+'" cy="'+cy+'" rx="'+rx+'" ry="'+ry+'" fill="'+fill+'" transform="rotate('+rot+' '+cx+' '+cy+')"/>'; }
        var svg='<svg xmlns="http://www.w3.org/2000/svg" width="'+W+'" height="'+H+'" viewBox="0 0 '+W+' '+H+'">'
        +'<defs>'
        +'<linearGradient id="wg" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="'+wall+'"/><stop offset="100%" stop-color="'+wall2+'"/></linearGradient>'
        +'<linearGradient id="fg" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="'+floor2+'"/><stop offset="46%" stop-color="'+floor+'"/><stop offset="100%" stop-color="'+floor+'"/></linearGradient>'
        +'<linearGradient id="sky" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#d6e7ef"/><stop offset="60%" stop-color="#eaf3f7"/><stop offset="100%" stop-color="#f3f8fa"/></linearGradient>'
        +'<linearGradient id="drape" x1="0" y1="0" x2="1" y2="0"><stop offset="0%" stop-color="'+curtain+'"/><stop offset="50%" stop-color="#ffffff" stop-opacity=".5"/><stop offset="100%" stop-color="'+curtain+'"/></linearGradient>'
        +'<linearGradient id="aocc" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#000" stop-opacity=".22"/><stop offset="100%" stop-color="#000" stop-opacity="0"/></linearGradient>'
        +'<radialGradient id="vig" cx="50%" cy="42%" r="78%"><stop offset="62%" stop-color="#000" stop-opacity="0"/><stop offset="100%" stop-color="#000" stop-opacity=".16"/></radialGradient>'
        +'<filter id="wt"><feTurbulence type="fractalNoise" baseFrequency="0.9" numOctaves="2" seed="7" result="n"/><feColorMatrix in="n" type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 .03 0"/></filter>'
        +'<filter id="wood"><feTurbulence type="fractalNoise" baseFrequency="0.012 0.15" numOctaves="3" seed="3" result="n"/><feColorMatrix in="n" type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 .10 0"/></filter>'
        +'<filter id="rugt"><feTurbulence type="turbulence" baseFrequency="0.5 0.5" numOctaves="2" seed="5" result="n"/><feColorMatrix in="n" type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 .10 0"/></filter>'
        +'<filter id="soft" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur stdDeviation="16"/></filter>'
        +'<filter id="lightblur" x="-40%" y="-40%" width="180%" height="180%"><feGaussianBlur stdDeviation="18"/></filter>'
        +'<clipPath id="fc"><rect x="0" y="'+FLOOR+'" width="'+W+'" height="'+(H-FLOOR)+'"/></clipPath>'
        +'<clipPath id="wc"><rect x="0" y="0" width="'+W+'" height="'+FLOOR+'"/></clipPath>'
        +'</defs>'
        // WALL
        +'<rect width="'+W+'" height="'+FLOOR+'" fill="url(#wg)"/>'
        +'<rect width="'+W+'" height="'+FLOOR+'" filter="url(#wt)"/>'
        // soft window-light patch projected on the wall (signature look)
        +'<g clip-path="url(#wc)">'
        +'<polygon points="1030,150 1230,120 1250,540 1040,560" fill="#ffffff" opacity=".34" filter="url(#lightblur)"/>'
        +'<g stroke="#c9cdd2" stroke-opacity=".5" stroke-width="6" filter="url(#lightblur)"><line x1="1140" y1="130" x2="1150" y2="552"/><line x1="1035" y1="345" x2="1245" y2="330"/></g>'
        +'</g>'
        // floor-to-ceiling WINDOW on the right
        +'<g>'
        +'<rect x="1430" y="30" width="200" height="'+(FLOOR-30)+'" fill="url(#sky)"/>'
        +'<rect x="1430" y="30" width="200" height="'+(FLOOR-30)+'" fill="none" stroke="#3a3d42" stroke-width="12"/>'
        +'<line x1="1524" y1="36" x2="1524" y2="'+(FLOOR-6)+'" stroke="#3a3d42" stroke-width="9"/>'
        +'<line x1="1436" y1="330" x2="1624" y2="330" stroke="#3a3d42" stroke-width="9"/>'
        // curtain drape on the left edge of the window
        +'<rect x="1360" y="24" width="86" height="'+(FLOOR-10)+'" fill="url(#drape)"/>'
        +'<g stroke="#000" stroke-opacity=".06" stroke-width="10"><line x1="1378" y1="30" x2="1378" y2="'+FLOOR+'"/><line x1="1404" y1="30" x2="1404" y2="'+FLOOR+'"/><line x1="1430" y1="30" x2="1430" y2="'+FLOOR+'"/></g>'
        +'</g>'
        // baseboard
        +'<rect x="0" y="'+(FLOOR-16)+'" width="'+W+'" height="18" fill="#f4f4f2"/>'
        +'<rect x="0" y="'+(FLOOR-16)+'" width="'+W+'" height="3" fill="#000" opacity=".05"/>'
        // FLOOR (pale wood, perspective)
        +'<g clip-path="url(#fc)">'
        +'<rect x="0" y="'+FLOOR+'" width="'+W+'" height="'+(H-FLOOR)+'" fill="url(#fg)"/>'
        +'<rect x="0" y="'+FLOOR+'" width="'+W+'" height="'+(H-FLOOR)+'" filter="url(#wood)"/>'
        +'<g stroke="#000" stroke-opacity=".08" stroke-width="2">'+seams+'</g>'
        +'<g stroke="#000" stroke-opacity=".06" stroke-width="2">'+rungs+'</g>'
        +'<rect x="0" y="'+FLOOR+'" width="'+W+'" height="70" fill="url(#aocc)"/>'
        // reflected window light on the floor
        +'<polygon points="1180,'+FLOOR+' 1360,'+FLOOR+' 1520,'+H+' 1150,'+H+'" fill="#ffffff" opacity=".10" filter="url(#soft)"/>'
        +'</g>'
        // woven area RUG (perspective)
        +'<polygon points="430,'+(H-140)+' 1150,'+(H-140)+' 1360,'+H+' 250,'+H+'" fill="'+rug+'"/>'
        +'<g clip-path="url(#fc)"><polygon points="430,'+(H-140)+' 1150,'+(H-140)+' 1360,'+H+' 250,'+H+'" filter="url(#rugt)"/></g>'
        +'<polygon points="430,'+(H-140)+' 1150,'+(H-140)+' 1360,'+H+' 250,'+H+'" fill="none" stroke="#000" stroke-opacity=".08" stroke-width="4"/>'
        // slim black FLOOR LAMP (left) — drawn behind the sofa
        +'<ellipse cx="384" cy="'+(FLOOR+120)+'" rx="40" ry="12" fill="#000" opacity=".16" filter="url(#soft)"/>'
        +'<rect x="380" y="'+(FLOOR-350)+'" width="9" height="480" fill="#26282b"/>'
        +'<rect x="360" y="'+(FLOOR+118)+'" width="50" height="12" rx="4" fill="#26282b"/>'   // foot
        +'<path d="M318 '+(FLOOR-408)+' h150 l-20 84 h-110 z" fill="#f0ebde"/>'                // drum shade
        +'<ellipse cx="393" cy="'+(FLOOR-408)+'" rx="75" ry="15" fill="#f7f3e8"/>'
        +'<ellipse cx="393" cy="'+(FLOOR-408)+'" rx="55" ry="10" fill="#fffdf5" opacity=".7"/>'
        // SOFA cropped at the left (light, modern) + contact shadow
        +'<ellipse cx="250" cy="'+(FLOOR+152)+'" rx="380" ry="48" fill="#000" opacity=".22" filter="url(#soft)"/>'
        +'<g>'
        +'<rect x="-70" y="'+(FLOOR-160)+'" width="580" height="164" rx="30" fill="'+sofa2+'"/>'          // backrest
        +'<rect x="-70" y="'+(FLOOR-150)+'" width="580" height="30" rx="16" fill="#ffffff" opacity=".22"/>' // back top highlight
        +'<rect x="-80" y="'+(FLOOR-130)+'" width="150" height="290" rx="30" fill="'+sofa2+'"/>'          // arm L (cropped)
        +'<rect x="440" y="'+(FLOOR-130)+'" width="96" height="290" rx="26" fill="'+sofa+'"/>'            // arm R
        +'<rect x="60" y="'+(FLOOR-40)+'" width="400" height="190" rx="26" fill="'+sofa+'"/>'             // seat
        +'<rect x="60" y="'+(FLOOR+96)+'" width="400" height="54" rx="24" fill="#000" opacity=".10"/>'    // seat under-shadow
        +'<line x1="262" y1="'+(FLOOR-30)+'" x2="262" y2="'+(FLOOR+120)+'" stroke="#000" stroke-opacity=".08" stroke-width="6"/>' // cushion split
        +'<rect x="80" y="'+(FLOOR-30)+'" width="360" height="20" rx="10" fill="#ffffff" opacity=".14"/>' // seat top highlight
        // throw pillow
        +'<rect x="86" y="'+(FLOOR-128)+'" width="140" height="128" rx="22" fill="'+o.pillow+'" transform="rotate(-8 156 '+(FLOOR-64)+')"/>'
        +'<rect x="104" y="'+(FLOOR-110)+'" width="104" height="20" rx="10" fill="#ffffff" opacity=".18" transform="rotate(-8 156 '+(FLOOR-64)+')"/>'
        +'<rect x="452" y="'+(FLOOR+120)+'" width="18" height="34" fill="#2a221c"/>'
        +'</g>'
        // low side TABLE with books (front-left)
        +'<ellipse cx="250" cy="'+(H-64)+'" rx="150" ry="26" fill="#000" opacity=".14" filter="url(#soft)"/>'
        +'<rect x="120" y="'+(H-150)+'" width="240" height="30" rx="6" fill="#8a6a48"/>'
        +'<rect x="140" y="'+(H-120)+'" width="16" height="120" fill="#6f5236"/><rect x="324" y="'+(H-120)+'" width="16" height="120" fill="#6f5236"/>'
        +'<rect x="150" y="'+(H-172)+'" width="120" height="22" rx="4" fill="#c9bfa8"/><rect x="164" y="'+(H-190)+'" width="96" height="20" rx="4" fill="#b7ac93"/>'
        // FIDDLE-LEAF fig on the right
        +'<ellipse cx="1300" cy="'+(FLOOR+140)+'" rx="90" ry="22" fill="#000" opacity=".18" filter="url(#soft)"/>'
        +'<rect x="1250" y="'+(FLOOR+40)+'" width="110" height="120" rx="12" fill="'+pot+'"/>'
        +'<rect x="1250" y="'+(FLOOR+40)+'" width="110" height="18" rx="8" fill="#000" opacity=".06"/>'
        +'<g stroke="#5a4632" stroke-width="7" fill="none"><path d="M1305 '+(FLOOR+40)+' C 1300 '+(FLOOR-120)+' 1300 '+(FLOOR-220)+' 1305 '+(FLOOR-300)+'"/></g>'
        +leaf(1305,(FLOOR-300),34,58,4,'#3f6b3a')
        +leaf(1240,(FLOOR-250),30,52,-38,'#4b7d43')
        +leaf(1372,(FLOOR-250),30,52,38,'#356035')
        +leaf(1235,(FLOOR-140),30,50,-58,'#4b7d43')
        +leaf(1378,(FLOOR-140),30,50,58,'#3f6b3a')
        +leaf(1305,(FLOOR-210),30,52,0,'#57904c')
        +leaf(1258,(FLOOR-60),26,44,-72,'#3f6b3a')
        +leaf(1352,(FLOOR-60),26,44,72,'#4b7d43')
        // GLOBAL soft vignette
        +'<rect width="'+W+'" height="'+H+'" fill="url(#vig)"/>'
        +'</svg>';
        return 'data:image/svg+xml;charset=utf8,'+encodeURIComponent(svg);
      }
      var MOCKBASE=<?php echo wp_json_encode( wp_get_upload_dir()['baseurl'] . '/mockups/' ); ?>;
      var SCENES=[
        {name:'Living Room', photo:1, bg:MOCKBASE+'Radha-Krishna-Canvas-Wall-Art-Placement_Living-Room-blankwall.jpg', focus:'52% 50%'},
        {name:'Lounge',      photo:1, bg:MOCKBASE+'Radha-Krishna_Wall-Art_-Living-Room-blankwall.jpg',                 focus:'51% 50%'},
        {name:'Bright Loft', photo:1, bg:MOCKBASE+'Radha-Krishna-Abstract-Wall-Art_living-room-blankwall.jpg',         focus:'56% 50%'},
        {name:'Studio',      photo:1, bg:MOCKBASE+'TAF-RADHA-KRISHNA-18530-room-1-blankwall.jpg',                      focus:'48% 50%'}
      ];
      var sceneWrap=$('tow-scenes');
      SCENES.forEach(function(s,i){
        var b=document.createElement('button'); b.type='button'; b.className='af-tow-scene'+(i===0?' on':'');
        b.style.backgroundImage='url("'+s.bg+'")'; b.title=s.name;
        b.innerHTML='<span>'+s.name+'</span>';
        b.addEventListener('click',function(){ setScene(i); });
        sceneWrap.appendChild(b);
      });
      var usingUpload=false, sceneIdx=0;
      function setScene(i){
        usingUpload=false; sceneIdx=i; savedURL=''; stopCam();
        sceneWrap.querySelectorAll('.af-tow-scene').forEach(function(x,j){ x.classList.toggle('on', j===i); });
        var im=$('tow-wallimg'); im.src=SCENES[i].bg; im.style.display='block';
        im.style.objectPosition = SCENES[i].focus || '50% 50%';
        $('tow-placeholder').style.display = current() ? 'none' : 'flex';
      }

      // ── LIVE CAMERA (spec §8): point the camera at your wall and the framed
      //    artwork is overlaid in real time. Uses the rear camera on phones.
      var camOn=false, camStream=null, camSeq=0, camStarting=false;
      function startCam(){
        if(camOn || camStarting) return;            // ignore impatient double clicks
        if(!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia)){
          toast('Camera not supported on this browser'); return;
        }
        var seq=++camSeq; camStarting=true;
        navigator.mediaDevices.getUserMedia({ video:{ facingMode:'environment', width:{ideal:1920}, height:{ideal:1080} }, audio:false })
          .then(function(stream){
            camStarting=false;
            // The visitor moved on while the permission prompt was open —
            // throw the stream away instead of hijacking the stage back.
            if(seq!==camSeq){ stream.getTracks().forEach(function(t){ t.stop(); }); return; }
            // Only now is the kept picture replaced. Throwing it away at the
            // top of this function meant a browser without a camera, or a
            // visitor who declined the permission prompt, lost a good capture
            // and got the old room photo back for nothing.
            unfreeze();
            camStream=stream; camOn=true;
            // if the track dies on its own (call, app switch, revoked permission)
            // fall back to the room photo instead of freezing on a dead frame
            stream.getTracks().forEach(function(t){
              t.addEventListener('ended', function(){ if(camStream===stream) stopCam(); });
            });
            var v=$('tow-cam'); v.srcObject=stream; v.style.display='block';
            $('tow-wallimg').style.display='none';
            $('tow-camstop').style.display='block';
            sceneWrap.querySelectorAll('.af-tow-scene').forEach(function(x){x.classList.remove('on');});
            $('tow-placeholder').style.display = current() ? 'none' : 'flex';
            camLabel();
            calStart();
            // Redraw, or the note keeps the room photo's claim. camOn has just
            // become true and applyScale() is what reads it, so without this
            // the line still reads "Shown true to scale on a 10 ft wall" for
            // the whole time the visitor is being asked to fit the rectangle —
            // the one state where it is certainly not true. calLock() and
            // stopCam() already redraw; only this transition did not.
            applyScale();
            toast('🎥 Live camera on — point it at your wall');
          })
          .catch(function(err){
            camStarting=false; camLabel();
            if(seq!==camSeq) return;
            toast(err && err.name==='NotAllowedError' ? 'Camera permission was denied' : 'Could not start the camera');
          });
      }
      function stopCam(){
        unfreeze();                               // room photo / upload / leaving: drop the still
        camSeq++; camStarting=false;              // cancels any start still in flight
        if(camStream){ camStream.getTracks().forEach(function(t){ t.stop(); }); camStream=null; }
        camOn=false;
        calStop();
        var v=$('tow-cam'); v.srcObject=null; v.style.display='none';
        $('tow-camstop').style.display='none';
        // bring the room photo (or the uploaded wall) back, and re-highlight
        // the thumbnail that matches what is now on the stage
        var im=$('tow-wallimg');
        if(im.getAttribute('src')){
          im.style.display='block';
          if(!usingUpload) sceneWrap.querySelectorAll('.af-tow-scene').forEach(function(x,j){ x.classList.toggle('on', j===sceneIdx); });
        }
        camLabel();
      }
      // the button is a toggle, so it has to say which way it will go
      function camLabel(){
        $('tow-cambtn').innerHTML = (camOn || camStarting)
          ? '⏹ Stop live camera <em>back to the room photo</em>'
          : '🎥 Use live camera <em>point it at your wall</em>';
      }
      $('tow-cambtn').addEventListener('click', function(){
        if(camOn || camStarting) stopCam(); else startCam();
        camLabel();
      });
      $('tow-camstop').addEventListener('click', function(){ stopCam(); });
      window.addEventListener('pagehide', stopCam);

      // ── LIVE-CAMERA CALIBRATION — measured true scale ─────────────────
      // The rectangle over the feed stands for the wall between ceiling and
      // floor. The visitor steps back or forward until both lines sit on its
      // edges; an edge detector watches the feed and flips the rectangle red
      // → amber → green, and at the lock pixels-per-foot becomes MEASURED
      // (rectangle height ÷ chosen wall height) instead of assumed. After
      // the lock the same detector keeps tracking the two lines, so walking
      // closer or farther rescales the artwork exactly as a real painting
      // grows and shrinks in a person's view. If the lines are lost the
      // scale simply holds — it never jumps.
      // wallFt is the height of the wall being looked at, in feet, and it is
      // the visitor's to choose — the Wall height buttons set it. It starts at
      // the height the room photos are drawn against (WALL_FT below), so the
      // button marked "on" and the picture on screen agree before anything is
      // touched. Both must move together if this default ever changes.
      var CAL = { locked:false, wallFt:10, base:0, pxPerFt:0, factor:1,
                  streak:0, spanLock:0, timer:null,
                  // frozen: the lock has been kept as a still picture (below)
                  frozen:false, autoSaved:false, preFreeze:null };
      window.AFCal = CAL;   // read-only view for the harness and live verifier
      var CAL_TOP=0.16, CAL_BOT=0.84;   // rectangle edges as fractions of stage height
      var calCv=document.createElement('canvas');
      calCv.width=120; calCv.height=90;
      var calCx=calCv.getContext('2d', { willReadFrequently:true });

      // where the rectangle's edges land in detector-row space, allowing for
      // the object-fit:cover crop of the feed inside the stage
      function calRows(){
        var v=$('tow-cam'); if(!(v.videoWidth>0)) return null;
        var st=$('tow-stage').getBoundingClientRect();
        var s=Math.max(st.width/v.videoWidth, st.height/v.videoHeight);
        var cropY=(v.videoHeight - st.height/s)/2;
        var toRow=function(frac){
          return (cropY + (frac*st.height)/s) / v.videoHeight * calCv.height;
        };
        return { top:toRow(CAL_TOP), bot:toRow(CAL_BOT) };
      }

      // the strongest horizontal edge in the upper and the lower half of the
      // feed — on a wall shot those are the ceiling line and the floor line
      function calSample(){
        var v=$('tow-cam'); if(!(v.videoWidth>0)) return null;
        try{
          calCx.drawImage(v,0,0,calCv.width,calCv.height);
          var d=calCx.getImageData(0,0,calCv.width,calCv.height).data;
        }catch(e){ return null; }
        var W=calCv.width,H=calCv.height,x0=Math.round(W*0.15),x1=Math.round(W*0.85);
        function lum(x,y){ var i=(y*W+x)*4; return d[i]*0.299+d[i+1]*0.587+d[i+2]*0.114; }
        var rows=[];
        for(var y=1;y<H-1;y++){ var acc=0; for(var x=x0;x<x1;x++){ acc+=Math.abs(lum(x,y+1)-lum(x,y-1)); } rows[y]=acc; }
        var sorted=rows.filter(function(v2){return v2!=null;}).sort(function(a,b){return a-b;});
        var med=sorted[Math.floor(sorted.length/2)]||1;
        function pick(a,b){
          var best=-1, by=0;
          for(var y=Math.max(1,Math.round(a)); y<Math.min(H-1,Math.round(b)); y++){
            if(rows[y]>best){ best=rows[y]; by=y; }
          }
          return { y:by, ok: best > med*2.2 };
        }
        return { top:pick(H*0.03,H*0.48), bot:pick(H*0.52,H*0.97) };
      }

      function calTick(){
        if(!camOn || CAL.frozen) return;
        var s=calSample(); if(!s) return;
        if(!CAL.locked){
          var r=calRows(); if(!r) return;
          var tol=calCv.height*0.055;
          var hit=s.top.ok && s.bot.ok &&
                  Math.abs(s.top.y-r.top)<tol && Math.abs(s.bot.y-r.bot)<tol;
          $('tow-calbox').classList.toggle('near', hit);
          CAL.streak = hit ? CAL.streak+1 : 0;
          if(CAL.streak>=5) calLock(s);      // ~0.8s of steady alignment
        }else{
          if(!(s.top.ok && s.bot.ok)) return;          // lines lost: hold scale
          var span=s.bot.y-s.top.y; if(span<=4) return;
          var f=span/CAL.spanLock; if(!(f>0.35 && f<2.8)) return;
          CAL.factor += (f-CAL.factor)*0.25;           // smooth, no jitter
          var want=CAL.base*CAL.factor;
          if(CAL.pxPerFt>0 && Math.abs(want-CAL.pxPerFt)/CAL.pxPerFt>0.015){
            CAL.pxPerFt=want; applyScale();
          }
        }
      }

      function calLock(s){
        var st=$('tow-stage').getBoundingClientRect();
        CAL.base=((CAL_BOT-CAL_TOP)*st.height)/CAL.wallFt;   // MEASURED px per ft
        CAL.factor=1; CAL.pxPerFt=CAL.base;
        CAL.spanLock=s.bot.y-s.top.y;
        CAL.locked=true; CAL.streak=0;
        $('tow-calbox').classList.remove('near');
        $('tow-calbox').classList.add('locked');
        // Take the matched wall as a picture, right now, while the lines are
        // on the edges - then the tracker below is not needed. See freezeWall.
        var frozen=freezeWall();
        $('tow-calmsg').textContent = frozen
          ? '✓ Wall locked — picture captured. The frame is now shown at its real size'
          : '✓ Wall locked — the frame is now shown at its real size';
        if(navigator.vibrate) try{ navigator.vibrate(60); }catch(e){}
        setTimeout(function(){
          if(CAL.locked){ $('tow-cal').style.display='none'; $('tow-recal').style.display='block'; }
        }, 1100);
        var re=$('tow-recal');
        if(re) re.textContent = frozen
          ? '📷 Wall captured · tap to scan again'
          : '📐 True scale locked · tap to recalibrate';
        applyScale();
        if(frozen) autoSaveLocked();
      }

      /* ── THE LOCK IS A PHOTOGRAPH ─────────────────────────────────────
         Until now the lock only started a second job: the detector went on
         following the ceiling and floor lines, and the artwork grew and
         shrank as the phone moved - a 26% swing at arm's length, and one
         the owner watched happen. What was wanted is the opposite. The
         moment the rectangle turns green the wall is MATCHED; that view is
         the one to keep. So the frame the camera is showing at that instant
         is drawn onto a canvas with the stage's own cover-crop (the same
         maths composePreview uses, so what is kept is exactly what was on
         screen), that still becomes the wall picture, the camera is stopped
         and the tracker with it. From here the page behaves as it does with
         an uploaded wall photo - drag, resize, save - except that the scale
         is the MEASURED one: CAL.base was taken from the stage height at the
         lock and the still fills that same stage, so pixels-per-foot is
         unchanged. Moving the phone afterwards changes nothing, because
         nothing is looking through it any more. Recalibrate, a room scene,
         an upload or the camera button all leave this state (unfreeze).
         Returns false, leaving the old live tracking in place, only if the
         frame could not be read. */
      function freezeWall(){
        var v=$('tow-cam'); if(!(v.videoWidth>0 && v.videoHeight>0)) return false;
        var st=$('tow-stage').getBoundingClientRect();
        if(!(st.width>0 && st.height>0)) return false;
        // up to 2x the CSS size so the still is as sharp as the feed was
        var dpr=Math.min(2, window.devicePixelRatio||1);
        var cw=Math.round(st.width*dpr), ch=Math.round(st.height*dpr);
        var cv=document.createElement('canvas'); cv.width=cw; cv.height=ch;
        var cx=cv.getContext('2d'), url='';
        var sc=Math.max(cw/v.videoWidth, ch/v.videoHeight), dw=v.videoWidth*sc, dh=v.videoHeight*sc;
        try{
          cx.drawImage(v, (cw-dw)/2, (ch-dh)/2, dw, dh);
          url=cv.toDataURL('image/jpeg', 0.92);
        }catch(e){ return false; }
        if(!url || url.length<64) return false;
        var im=$('tow-wallimg');
        // remember the wall that was underneath, for unfreeze
        CAL.preFreeze={ src: im.getAttribute('src')||'', pos: im.style.objectPosition||'' };
        im.src=url; im.style.objectPosition='50% 50%'; im.style.display='block';
        // The still IS the wall from here, and it is drawn object-fit:cover.
        // Turn the phone and the stage changes shape, cover rescales the
        // picture, and the wall in it grows or shrinks by exactly that factor
        // - so the measurement has to follow it. Remember the box it was
        // captured against; frozenScale() below turns the two into a factor.
        CAL.lockW=st.width; CAL.lockH=st.height;
        // And hold the box itself still. On desktop the stage stretches to
        // match the control rail, so anything that makes the rail taller -
        // the "View saved previews" link this very lock is about to reveal -
        // grew the stage 728 -> 760px, cover scaled the still with it, and the
        // artwork jumped 4.4% a moment after the capture: measured, and the
        // exact flicker being fixed. Written WITHOUT !important on purpose, so
        // the phone rule (.af-tow-stage{height:auto!important;aspect-ratio:4/3}
        // at <=781px) still wins there and a rotation re-derives the box -
        // which frozenScale() then accounts for. flex:0 0 auto so grow cannot
        // put the height back. unfreeze() removes both.
        var stg=$('tow-stage');
        stg.style.height=st.height+'px';
        stg.style.flex='0 0 auto';
        CAL.frozen=true; CAL.autoSaved=false;
        // The pin is for the reflow that happens in the next few hundred
        // milliseconds, not for the rest of the session. The moment the window
        // itself changes - a phone turned on its side is 820px wide, past the
        // 781px breakpoint whose height:auto was going to release it - the pin
        // is dropped and the stylesheet decides the box again. frozenScale()
        // then accounts for however the still is covered into it, so the
        // measurement stays right either way.
        if(CAL.timer){ clearInterval(CAL.timer); CAL.timer=null; }
        // the still is the wall now: the stream can stop (no camera light,
        // no battery), and any start still in flight is cancelled
        camSeq++; camStarting=false;
        var stream=camStream; camStream=null; camOn=false;
        if(stream){ stream.getTracks().forEach(function(t){ try{ t.stop(); }catch(e){} }); }
        v.srcObject=null; v.style.display='none';
        $('tow-camstop').style.display='none';
        camLabel();
        return true;
      }
      // how much object-fit:cover is scaling the frozen still right now
      function frozenScale(){
        if(!(CAL.frozen && CAL.lockW>0 && CAL.lockH>0)) return 1;
        var st=$('tow-stage').getBoundingClientRect();
        if(!(st.width>0 && st.height>0)) return 1;
        return Math.max(st.width/CAL.lockW, st.height/CAL.lockH);
      }
      function unpinStage(){
        var stg=$('tow-stage');
        stg.style.removeProperty('height'); stg.style.removeProperty('flex');
      }
      function unfreeze(){
        if(!CAL.frozen) return;
        CAL.frozen=false; CAL.locked=false; CAL.autoSaved=false;
        if(autoTimer){ clearInterval(autoTimer); autoTimer=null; }
        var im=$('tow-wallimg'), pf=CAL.preFreeze||{};
        if(pf.src){ im.src=pf.src; im.style.objectPosition=pf.pos||'50% 50%'; }
        else { im.removeAttribute('src'); im.style.display='none'; }
        CAL.preFreeze=null;
        unpinStage();
        $('tow-cal').style.display='none';
        $('tow-recal').style.display='none';
        $('tow-calbox').classList.remove('locked','near');
        // Redraw. stopCam() reaches applyScale() through calStop(), but
        // startCam() whose getUserMedia then FAILS does not - and without this
        // the room photo underneath keeps the measured size and the note keeps
        // saying "measured against your own wall", permanently.
        applyScale();
      }

      /* ── AND IT IS KEPT, TWICE ───────────────────────────────────────
         The owner asked for the matched wall to land in the visitor's own
         photo gallery and in their account on the site, without another
         tap. So once per lock, as soon as the framed artwork can be drawn
         (it may still be downloading; or no product may be chosen yet, in
         which case this waits for the pick - see the change handler), the
         same picture the Save buttons make is downloaded (Photos / Downloads
         on a phone) and posted to the account through AFPreview.save, which
         is the one place saving happens. Not signed in: the download still
         happens and the toast offers the sign-in link. */
      var autoTimer=null, lastAutoSig='', autoAcctSaves=0;
      /* The account is not a bottomless drawer: the server takes twelve saves
         in ten minutes and keeps sixty per person. A visit that matches a wall
         two or three times is the normal case and is worth a save each; past
         that, someone is sweeping the room and every extra shot is both a
         wasted slot and a step closer to the manual Save to my account button
         failing for them. So the first three matches of a visit go to the
         account, and after that the shot still lands in the gallery and the
         visitor is told which button keeps it. */
      var AUTO_ACCT_MAX=3;
      function p2(){ var q=current(); return q?q.id:0; }
      function autoSaveLocked(){
        if(autoTimer){ clearInterval(autoTimer); autoTimer=null; }
        if(!CAL.frozen || CAL.autoSaved) return;
        if(!current()){ toast('✓ Wall captured — choose an artwork and it will be saved for you'); return; }
        var tries=0;
        autoTimer=setInterval(function(){
          if(!CAL.frozen || CAL.autoSaved){ clearInterval(autoTimer); autoTimer=null; return; }
          var out=composePreview(true);
          if(!out){
            if(++tries>20){    // 8s: the artwork is not going to arrive
              clearInterval(autoTimer); autoTimer=null;
              toast('✓ Wall captured — tap Save Preview to keep this shot');
            }
            return;
          }
          clearInterval(autoTimer); autoTimer=null;
          CAL.autoSaved=true;
          // A re-scan that produces the very same picture should not spend
          // another of the twelve saves the server allows in ten minutes.
          // This only catches an IDENTICAL composite - a real camera moves and
          // its noise alone changes the bytes - so it is a floor, not a dedupe:
          // it stops a still scene being saved over and over.
          var sig=[p2(), $('tow-size').value, $('tow-frame').value, $('tow-color').value, LAYOUT, out.url.length].join('|');
          if(sig===lastAutoSig){ toast('✓ Already saved — the same shot is in your account'); return; }
          lastAutoSig=sig;
          keepPreview(out);
        }, 400);
      }
      function keepPreview(out){
        var file=downloadPreview(out);
        var p=current();
        if(autoAcctSaves>=AUTO_ACCT_MAX){
          offerPhotos(file, ' — tap “Save to my account” to keep this one too');
          return;
        }
        // A visitor who is not signed in cannot spend an account slot, so do
        // not count one against them: the save below answers straight away
        // with the sign-in prompt.
        if(AFPreview.cfg.logged) autoAcctSaves++;
        AFPreview.save(out.url, {
          product: p?p.id:0, source:'try-on-wall',
          size: $('tow-size').value, frame: $('tow-frame').value,
          color: $('tow-color').value, layout: LAYOUT
        }, function(ok, msg, url, fallback){
          var link=$('tow-acctlink');
          if(ok){
            // NOT savedURL. That variable is what the WhatsApp / Email / Copy
            // buttons hand out, and this save was automatic - the visitor
            // asked for a wall preview, not to publish a photograph of their
            // room. Sharing stays on the product link until they press "Save
            // to my account" themselves, which is the tap that means "this
            // picture is mine to send".
            offerPhotos(file, ' — and saved to your account');
            if(link && fallback){ link.href=fallback; link.style.display='inline'; }
          }else if(!AFPreview.cfg.logged){
            offerPhotos(file, ' — sign in to keep it in your account');
            if(link && fallback){ link.href=AFPreview.withRedirect(fallback); link.textContent='Sign in →'; link.style.display='inline'; }
          }else{
            offerPhotos(file, ' · '+msg);
          }
        });
      }
      // A file, not a data: link: Safari on iOS opens a data: URL as a page
      // instead of saving it, and a Blob is what its Save Image sheet wants.
      // Returns the File for offerPhotos(), or null if the bytes would not
      // decode - the download itself still happens either way.
      function downloadPreview(out){
        var a=document.createElement('a'); a.download=out.name; a.rel='noopener';
        var file=null, href='';
        try{
          var bin=atob(out.url.split(',')[1]), n=bin.length, u8=new Uint8Array(n);
          for(var i=0;i<n;i++) u8[i]=bin.charCodeAt(i);
          var blob=new Blob([u8], {type:'image/png'});
          href=URL.createObjectURL(blob); a.href=href;
          try{ file=new File([blob], out.name, {type:'image/png'}); }catch(e){}
          setTimeout(function(){ URL.revokeObjectURL(href); }, 60000);
        }catch(e){ a.href=out.url; }
        document.body.appendChild(a); a.click(); a.remove();
        return file;
      }

      /* Where the picture actually goes, said honestly.
         The download lands in Downloads (Android) or Files (iOS) - never the
         camera roll, and on iOS Safari a programmatic download from a timer
         (which this is: the lock is not a tap) may not happen at all. The
         camera roll is one step further and the browser will only take it from
         a gesture, because navigator.share() throws NotAllowedError without
         one. So where a file can be shared the toast says "tap to add to
         Photos" and IS that tap, and it does not claim a gallery save it
         cannot guarantee; where it cannot, the download is all there is and
         the toast says downloads. Seven seconds - long enough to read the line
         and hit it, short enough that a box that catches taps is not sitting
         across the bottom of the wall while the visitor is dragging. */
      function offerPhotos(file, kept){
        var t=$('tow-toast');
        var can=false;
        try{ can = !!(file && navigator.canShare && navigator.canShare({files:[file]}) && navigator.share); }catch(e){ can=false; }
        if(!(can && t)){ toast('✓ Saved to your downloads' + kept); return; }
        toast('✓ Tap here to add it to your Photos' + kept, 7000);
        t.style.pointerEvents='auto'; t.style.cursor='pointer';
        var timer=null;
        var done=function(){
          if(timer){ clearTimeout(timer); timer=null; }
          if(clearShareOffer===done) clearShareOffer=null;
          t.style.pointerEvents=''; t.style.cursor='';
          t.removeEventListener('click', go);
        };
        var go=function(){
          done();
          navigator.share({ files:[file], title:'My wall preview' }).catch(function(){});
        };
        t.addEventListener('click', go);
        clearShareOffer=done;
        timer=setTimeout(done, 6800);    // inside the toast's own life
      }

      function calStart(){
        CAL.locked=false; CAL.streak=0; CAL.factor=1;
        var re0=$('tow-recal');
        if(re0) re0.textContent='📐 True scale locked · tap to recalibrate';
        $('tow-cal').style.display='block';
        $('tow-recal').style.display='none';
        $('tow-calbox').classList.remove('locked','near');
        $('tow-calmsg').innerHTML='<span class="af-tow-calmsg-long">Step back or forward until the <strong>ceiling line</strong> touches the top edge and the <strong>floor line</strong> touches the bottom edge</span><span class="af-tow-calmsg-short"><strong>Ceiling</strong> to the top edge, <strong>floor</strong> to the bottom</span>';
        if(!CAL.timer) CAL.timer=setInterval(calTick,160);
      }
      function calStop(){
        if(CAL.timer){ clearInterval(CAL.timer); CAL.timer=null; }
        CAL.locked=false;
        $('tow-cal').style.display='none';
        $('tow-recal').style.display='none';
        applyScale();                       // back to the room-photo scale
      }
      $('tow-recal').addEventListener('click', function(){
        if(CAL.frozen){ startCam(); return; }     // unfreezes, then calStart on the stream
        calStart();
      });

      /**
       * The one place the wall height changes. Two rows offer it — the panel's
       * (always on screen) and the calibration overlay's (only while the camera
       * is aligning) — and both land here, so they can never disagree about
       * which button is lit or what the preview is drawn against.
       *
       * The redraw is unconditional. It used to happen only when CAL.locked,
       * which is the one moment the visitor is NOT being asked for a height:
       * they are asked while the rectangle is still red and nothing is locked,
       * and that press lit a button and changed nothing.
       */
      /** "an 8 ft wall" / "a 9 ft wall" — 8 is the only one that takes "an". */
      function af_tow_wall_phrase() {
        var n = CAL.wallFt || WALL_FT;
        return ( n === 8 ? 'an ' : 'a ' ) + n + ' ft wall';
      }
      function af_tow_set_wall_ft( n ) {
        CAL.wallFt = parseInt( n, 10 ) || 10;
        [ 'tow-wallh', 'tow-calh' ].forEach(function(id){
          var row = $(id); if ( ! row ) { return; }
          row.querySelectorAll('button[data-ft]').forEach(function(x){
            x.classList.toggle('on', parseInt(x.getAttribute('data-ft'),10) === CAL.wallFt);
          });
        });
        if(CAL.locked){                     // re-derive the measurement, keep tracking
          // Which height the wall was measured against. While a still is held
          // it is the box that still was CAPTURED in (CAL.lockH): applyScale()
          // already multiplies by frozenScale() for however the picture is
          // being covered into today's stage, so reading today's stage here
          // too would apply the same factor twice - a phone rotated from a
          // 360px stage to a 520px one drew the artwork 44% oversize while
          // still claiming true scale.
          var ruler = CAL.frozen && CAL.lockH > 0
            ? CAL.lockH
            : $('tow-stage').getBoundingClientRect().height;
          CAL.base=((CAL_BOT-CAL_TOP)*ruler)/CAL.wallFt;
          CAL.pxPerFt=CAL.base*CAL.factor;
        }
        applyScale();
      }
      [ 'tow-wallh', 'tow-calh' ].forEach(function(id){
        var row = $(id); if ( ! row ) { return; }
        row.addEventListener('click', function(e){
          var b = e.target.closest('button[data-ft]'); if ( ! b ) { return; }
          af_tow_set_wall_ft( b.getAttribute('data-ft') );
        });
      });

      // ── 1 / 2 / 4 PANEL WALL LAYOUTS (spec §8) ─────────────────────────
      // 2 and 4 render the artwork as a split multi-panel set (vertical
      // slices), each slice in its own frame — the classic gallery-wall look.
      var LAYOUT=1, artImg=null, artRatio=1.33;   // ratio = h/w of the artwork
      $('tow-layouts').addEventListener('click', function(e){
        var b=e.target.closest('.af-tow-lay'); if(!b) return;
        this.querySelectorAll('.af-tow-lay').forEach(function(x){x.classList.remove('on');});
        b.classList.add('on');
        LAYOUT=parseInt(b.getAttribute('data-n'),10)||1;
        rebuild();
      });

      // A print is produced at the SIZE the visitor chose, so the artwork is
      // cover-cropped to that ratio. Without this the frame keeps the photo's
      // own shape and every size with the same height (2×3, 2×4, 2×5 ft) draws
      // exactly the same box while the price moves — the width does nothing.
      var cropKey='', cropURL='', cropImg=null;
      function printRatio(){
        var ft=sizeFeet($('tow-size').value);
        return (ft && ft.h>0 && ft.w>0) ? (ft.h/ft.w) : artRatio;
      }
      function ensureCrop(cb){
        var p=current();
        if(!p || !(artImg && artImg.naturalWidth>0)){ cb(); return; }
        var ratio=printRatio(), key=p.img+'|'+ratio.toFixed(4);
        if(key===cropKey && cropURL){ cb(); return; }
        try{
          var iw=artImg.naturalWidth, ih=artImg.naturalHeight;
          var tw=Math.min(iw,1600), th=Math.max(1, Math.round(tw*ratio));
          var cv=document.createElement('canvas'); cv.width=tw; cv.height=th;
          var cx=cv.getContext('2d');
          var s=Math.max(tw/iw, th/ih);
          cx.drawImage(artImg,(tw-iw*s)/2,(th-ih*s)/2,iw*s,ih*s);
          cropURL=cv.toDataURL('image/jpeg',0.92);
          cropImg=new Image(); cropImg.src=cropURL;
          cropKey=key;
        }catch(err){
          // image host without CORS headers taints the canvas — keep the
          // uncropped artwork rather than losing the preview
          cropURL=''; cropImg=null; cropKey='';
        }
        cb();
      }
      function rebuild(){ ensureCrop(function(){ buildPanels(); applyFrame(); applyScale(); }); }

      function buildPanels(){
        var p=current(); if(!p) return;      // never wipe the wall when there is nothing to rebuild
        // The frame's shape is ALWAYS the chosen print size, even while the
        // artwork is still downloading. It used to fall back to the photo's own
        // aspect until the crop was ready, so picking a product flashed a
        // wrong-shape frame on a page whose whole promise is true-to-scale —
        // a briefly stretched artwork is the better half of that trade.
        var src=cropURL||p.img, ratio=printRatio();
        var wrap=$('tow-panels'); wrap.innerHTML='';
        for(var i=0;i<LAYOUT;i++){
          var panel=document.createElement('div'); panel.className='af-tow-wpanel';
          var frame=document.createElement('div'); frame.className='af-tow-pframe';
          var mat=document.createElement('div'); mat.className='af-tow-pmat';
          var art=document.createElement('div'); art.className='af-tow-part';
          art.style.backgroundImage='url("'+src+'")';
          if(LAYOUT===1){
            art.style.backgroundSize='100% 100%';
            art.style.backgroundPosition='0 0';
          }else{
            art.style.backgroundSize=(LAYOUT*100)+'% 100%';
            art.style.backgroundPosition=(i/(LAYOUT-1)*100)+'% 0';
          }
          art.style.paddingBottom=(ratio*LAYOUT*100)+'%';
          var glass=document.createElement('span'); glass.className='af-tow-glass';
          mat.appendChild(art); mat.appendChild(glass);
          frame.appendChild(mat); panel.appendChild(frame); wrap.appendChild(panel);
        }
      }

      function fillProducts(cat){
        var sel=$('tow-prod'); sel.innerHTML='<option value="">Select a product</option>';
        PRODUCTS.filter(function(p){ return !cat || (p.cats||[]).indexOf(cat)>-1; })
                .forEach(function(p){ var o=document.createElement('option'); o.value=p.id; o.textContent=p.name; sel.appendChild(o); });
      }
      fillProducts('');
      // changing category clears the product selection — put the stage back to
      // its prompt rather than leaving the previous artwork stranded on the wall
      $('tow-cat').addEventListener('change', function(){ fillProducts(this.value); refresh(); });

      function current(){ return PRODUCTS.filter(function(p){ return String(p.id)===String($('tow-prod').value); })[0]; }

      function applyFrame(){
        var frame=$('tow-frame').value, color=$('tow-color').value;
        var fb=$('tow-framebox');
        // Frame profile thickness (% of panel width so it scales) + reveal.
        // These are CANVAS prints: a real fibre or aluminium frame mounts the
        // canvas edge-to-edge — no paper-print mat. The wide cream border the
        // preview used to draw between moulding and art was a museum mat that
        // this product never ships with, and at 4×6 ft it scaled up to a
        // hand-width band of nothing. Only the floating frame keeps a border:
        // its slim dark reveal is how a real float frame is built.
        var prof, matw, matbg='#f6f1e6';
        if(frame==='Without Frame'){ prof=0; matw=0; }         // gallery-wrapped canvas
        else if(frame==='Floating Frame'){ prof=5; matw=2.5; matbg='#161616'; }
        else if(frame==='Aluminium Frame'){ prof=4; matw=0; }
        else { prof=7; matw=0; }                                // Fibre / default wood
        // Multi-panel float sets keep a hairline reveal so slices stay separate
        if(LAYOUT>1 && matw>0){ matw=Math.max(1.5, matw-1); }
        var tex = prof>0 ? frameTexture(frame, color) : null;
        fb.querySelectorAll('.af-tow-pframe').forEach(function(frEl){
          frEl.setAttribute('data-prof', prof);
          frEl.style.padding='0';
          if(tex){
            // the moulding is a rendered picture of a frame, not a gradient —
            // border-image keeps the 45° mitres true at any panel size
            frEl.style.borderStyle='solid';
            frEl.style.borderColor='transparent';
            frEl.style.borderImage='url("'+tex.url+'") '+tex.B+' stretch';
            frEl.style.background='transparent';
            frEl.style.borderRadius='0';
            frEl.style.boxShadow='none';
          }else{
            frEl.style.border='0';
            frEl.style.borderImage='none';
            frEl.style.background='transparent';
            frEl.style.boxShadow='none';
          }
        });
        sizeFrameBorders();
        fb.querySelectorAll('.af-tow-pmat').forEach(function(mat){
          mat.style.padding = matw>0 ? ('max(8px, '+matw+'%)') : '0';
          mat.style.background = matw>0 ? matbg : 'transparent';
          // the frame's rebate overhangs whatever sits in it, so its fine
          // shadow falls on the reveal when there is one and straight onto
          // the canvas when there is not — only an unframed wrap has none
          mat.style.boxShadow = prof>0 ? 'inset 0 2px 5px rgba(0,0,0,.34), inset 0 0 9px rgba(0,0,0,.16)' : 'none';
        });
        // Sitting on the wall: a tight contact shadow right behind the
        // moulding plus the broad soft cast from the room's key light — two
        // shadows is what makes it read as hanging ON the wall rather than
        // pasted over the photo.
        fb.style.filter = 'drop-shadow(0 2px 4px rgba(0,0,0,.35)) drop-shadow(12px 18px 26px rgba(0,0,0,.30))';
      }

      function calcPrice(){
        var p=current(); if(!p) return null;
        // the size IS the price, from the rate card (matches af_calc_price)
        var sizePrice=CFG.sizes[$('tow-size').value]||p.price;
        // a Gold Foiled & UV piece rides a ratio on the same card, so the
        // preview quotes what the product page will (1 for everything else)
        if (p.gf && p.gf !== 1) sizePrice = Math.max(5, Math.round(sizePrice * p.gf / 5) * 5);
        var frameVal=$('tow-frame').value;
        // no frame means no frame-finish surcharge (matches af_calc_price)
        // Aluminium is $4 a square foot of the size (matches af_frame_fee)
        var sqm=($('tow-size').value||'').match(/\((\d+(?:\.\d+)?)\s*[x×]\s*(\d+(?:\.\d+)?)\s*in\)/);
        var perSq=CFG.frame_sqft && CFG.frame_sqft[frameVal];
        var frameFee=(perSq && sqm) ? Math.round(sqm[1]*sqm[2]/144*perSq*100)/100 : (CFG.frames[frameVal]||0);
        var fee=frameFee+(frameVal!=='Without Frame' ? (CFG.colors[$('tow-color').value]||0) : 0);
        return Math.round((sizePrice+fee)*100)/100;
      }
      function refresh(){
        if(typeof syncTowColor==='function') syncTowColor();   // frame may have been set in code
        var p=current();
        if(!p){
          $('tow-panels').innerHTML='';
          $('tow-framebox').style.display='none';
          $('tow-placeholder').style.display='flex';
          $('tow-price').textContent='—';
          $('tow-view').removeAttribute('href');
          return;
        }
        if(p){
          // (re)load only when the product changed — the same artwork is reused
          // across size / frame / colour changes so the crop cache stays warm
          if(!artImg || artImg.getAttribute('data-src')!==p.img){
            artImg=new Image(); artImg.crossOrigin='anonymous';
            artImg.setAttribute('data-src', p.img);
            artImg.onload=function(){
              if(artImg.naturalWidth>0) artRatio=artImg.naturalHeight/artImg.naturalWidth;
              cropKey=''; rebuild();
            };
            // If the image host doesn't answer with CORS headers the anonymous
            // request fails outright — retry plainly so the preview still works.
            artImg.onerror=function(){
              var plain=new Image();
              plain.setAttribute('data-src', p.img);
              plain.onload=function(){
                artImg=plain;
                if(plain.naturalWidth>0) artRatio=plain.naturalHeight/plain.naturalWidth;
                cropKey=''; rebuild();
              };
              plain.src=p.img;
            };
            artImg.src=p.img;
          }
          $('tow-framebox').style.display='inline-block';
          $('tow-placeholder').style.display='none';
          // carry the visitor's choices through to the product page
          $('tow-view').href=p.url + (p.url.indexOf('?')>-1?'&':'?') +
            'af_size='  + encodeURIComponent($('tow-size').value) +
            '&af_frame='+ encodeURIComponent($('tow-frame').value) +
            '&af_color='+ encodeURIComponent($('tow-color').value);
          rebuild();
          var pr=calcPrice(); $('tow-price').textContent = pr!=null ? (SYM+pr.toFixed(2)) : '—';
        }
      }
      ['tow-prod','tow-frame','tow-size','tow-color'].forEach(function(id){ $(id).addEventListener('change', refresh); });

      // ── TRUE-TO-SCALE preview ──────────────────────────────────────────
      // The room photos are shot against a wall whose height the visitor tells
      // us (CAL.wallFt, from the Wall height buttons; WALL_FT is only the
      // fallback if that is somehow unset), and the wall occupies WALL_FRAC of
      // the stage's height (the rest is floor).
      // A size label like "3×5 ft (36×60 in)" is HEIGHT × WIDTH in feet, so the
      // artwork is drawn at (height_ft / wall height) of the wall — e.g. 3 ft on
      // a 10 ft wall = 30% of the wall height — and its width follows from the
      // real aspect ratio, not from the price multiplier.
      // Saying the wall is shorter makes the same print cover more of it, which
      // is the whole point of asking: an 8 ft ceiling is not a 10 ft one.
      var WALL_FT = 10;      // fallback wall height; CAL.wallFt starts here too
      var WALL_FRAC = 0.78;  // portion of the stage height that is wall
      var scaleSeq = 0;      // guards the deferred size correction below
      function sizeFeet(label){
        // "3×5 ft (36×60 in)" → {h:3, w:5}   (also accepts x / X / ×)
        var m = String(label||'').match(/(\d+(?:\.\d+)?)\s*[x×X]\s*(\d+(?:\.\d+)?)/);
        if(!m) return null;
        return { h: parseFloat(m[1]), w: parseFloat(m[2]) };
      }
      function applyScale(){
        var stage=$('tow-stage'), box=$('tow-framebox');
        var r=stage.getBoundingClientRect(), sw=r.width||500, sh=r.height||560;
        var label=$('tow-size').value;
        var ft=sizeFeet(label);
        var slider=parseFloat($('tow-scale').value)/100;

        if(ft && ft.h>0 && ft.w>0){
          // pixels-per-foot from the wall height in this scene
          // Calibrated live camera: the scale is measured against the visitor's
          // actual wall and tracks them as they move. Otherwise: the room photo,
          // read as a wall of the height the visitor picked.
          var measured = ( (camOn || CAL.frozen) && CAL.locked && CAL.pxPerFt > 0 );
          // With the camera on, the wall IS the red rectangle - 68% of the
          // stage (CAL_BOT - CAL_TOP) - so draw against that, not the 78% the
          // room photos use. Otherwise a 3 ft piece on a 9 ft wall was 222px
          // inside a 577px box (38%, not the true 1/3 = one grid cell), and
          // shrank 13% the moment the box turned green.
          var pxPerFt = measured
            ? CAL.pxPerFt * (CAL.frozen ? frozenScale() : 1)
            : (sh * (camOn ? (CAL_BOT - CAL_TOP) : WALL_FRAC)) / (CAL.wallFt || WALL_FT);
          var targetH = ft.h * pxPerFt * slider;         // true height on the wall
          var targetW = ft.w * pxPerFt * slider;         // true width  on the wall

          // Say which of those two it was.
          //
          // The note used to read "Shown true to scale on a 10 ft wall" in every
          // state, and both halves of that could be false. It ignored the wall
          // height the visitor picked, so choosing 8 ft rescaled the artwork
          // while the note still said 10. And it claimed true scale with the
          // camera running and NOT yet locked — which is the state a visitor is
          // in for the whole time they are being asked to fit the rectangle to
          // their wall. There the artwork is drawn against the room-photo
          // assumption (a wall of the chosen height filling WALL_FRAC of the
          // frame) applied to whatever the camera happens to be pointed at, so
          // it is not to scale and cannot be until the measurement exists.
          // Nothing about the sizing changes here; only the claim made about it.
          var note = $('tow-scalenote');
          if ( note ) {
            note.textContent = measured
              ? ( CAL.frozen
                  ? '📏 True to scale — measured against your own wall. Picture locked at the match.'
                  : '📏 True to scale — measured against your own wall.' )
              : ( camOn
                  ? '📐 Not to scale yet — drawn against ' + af_tow_wall_phrase()
                    + '. Fit the rectangle to your wall to measure it.'
                  : '📏 Shown true to scale on ' + af_tow_wall_phrase() );
          }
          // Set width first, then correct so the RENDERED box (frame + mat +
          // art, whose own ratio may differ) matches the real footprint.
          // Floor of 20px, not 40: on a phone the red box is ~178px tall, so a
          // 3x2 ft piece on a 10 ft wall is truly 36px wide, and a 40px floor
          // drew it 12% too big. 20px is still a grabbable target.
          box.style.width = Math.max(20, targetW) + 'px';
          // Only the newest correction may run: two applyScale() calls in one
          // frame (refresh() then the artwork's onload) used to have the second
          // measure the already-corrected box and undo the correction.
          var seq = ++scaleSeq;
          requestAnimationFrame(function(){
            if(seq!==scaleSeq) return;
            var b = box.getBoundingClientRect();
            if(b.height>0 && b.width>0){
              // Scale from what is actually on screen, so this is idempotent and
              // works for any panel count: once the height matches, it is a no-op.
              var corrected = Math.max(20, b.width * (targetH / b.height));
              // never exceed the wall itself
              corrected = Math.min(corrected, sw*0.92);
              box.style.width = corrected + 'px';
            }
            // the moulding thickness is a % of the panel, expressed in border
            // px — re-derive it now that the box has its final width
            sizeFrameBorders();
          });
          return;
        }
        // Fallback (unparsable label): gentle scaling — CFG.sizes now holds
        // dollar prices, so normalise against the smallest to get a ratio
        var sMin=Math.min.apply(null, Object.keys(CFG.sizes).map(function(k){return CFG.sizes[k];}));
        var sizeMult=(CFG.sizes[label]||sMin)/(sMin||1);
        box.style.width=Math.max(60, sw*0.28*Math.sqrt(sizeMult)*slider)+'px';
        sizeFrameBorders();
      }
      $('tow-scale').addEventListener('input', function(){ $('tow-scaleval').textContent=this.value+'%'; applyScale(); });

      // Wall upload (overrides the chosen room scene)
      $('tow-wall').addEventListener('change', function(){
        var f=this.files&&this.files[0]; if(!f) return;
        var r=new FileReader();
        r.onload=function(e){
          usingUpload=true; savedURL=''; stopCam();
          sceneWrap.querySelectorAll('.af-tow-scene').forEach(function(x){x.classList.remove('on');});
          var im=$('tow-wallimg'); im.src=e.target.result; im.style.display='block';
          im.style.objectPosition='50% 50%';
          $('tow-placeholder').style.display = current() ? 'none' : 'flex';
        };
        r.readAsDataURL(f);
      });

      // Drag artwork
      var box=$('tow-framebox'), drag=false,sx,sy,ox,oy;
      box.addEventListener('pointerdown', function(e){
        // The box is centred with translate(-50%,-50%); read where it ACTUALLY
        // is before dropping that transform, or it jumps half its own size.
        var sr=$('tow-stage').getBoundingClientRect(), br=box.getBoundingClientRect();
        ox=br.left-sr.left; oy=br.top-sr.top;
        box.style.left=ox+'px'; box.style.top=oy+'px'; box.style.transform='none';
        sx=e.clientX; sy=e.clientY;
        drag=true; box.classList.add('dragging');
        if(box.setPointerCapture){ try{ box.setPointerCapture(e.pointerId); }catch(err){} }
        e.preventDefault();
      });
      document.addEventListener('pointermove', function(e){
        if(!drag) return;
        var sr=$('tow-stage').getBoundingClientRect(), br=box.getBoundingClientRect();
        var nx=ox+(e.clientX-sx), ny=oy+(e.clientY-sy);
        // always leave a grabbable piece of the artwork on the wall
        var keepX=Math.min(70, br.width*0.5), keepY=Math.min(70, br.height*0.5);
        nx=Math.max(keepX-br.width, Math.min(nx, sr.width-keepX));
        ny=Math.max(keepY-br.height, Math.min(ny, sr.height-keepY));
        box.style.left=nx+'px'; box.style.top=ny+'px';
      });
      function endDrag(){ if(!drag) return; drag=false; box.classList.remove('dragging'); }
      document.addEventListener('pointerup', endDrag);
      document.addEventListener('pointercancel', endDrag);
      window.addEventListener('blur', endDrag);

      // Save preview
      var toastTimer;
      // One element shows every message, so a message arriving on top of the
      // Photos offer must take the offer down with it: otherwise the toast
      // stays tappable over the wall with a handler holding the PREVIOUS
      // picture, and a tap meant for the artwork shares a shot the visitor has
      // moved on from. clearShareOffer is set by offerPhotos while an offer is
      // live and nulled here.
      var clearShareOffer=null;
      function toast(msg, ms){
        var t=$('tow-toast'); if(!t) return;
        if(clearShareOffer){ var c=clearShareOffer; clearShareOffer=null; c(); }
        t.textContent=msg; t.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer=setTimeout(function(){ t.classList.remove('show'); }, ms || 3200);
      }
      // Compose the wall + framed artwork onto a canvas. Returns {url,name} or
      // null (having explained why). Shared by download, save-to-account and share.
      function composePreview(quiet){
        var say = quiet ? function(){} : toast;   // the auto-save polls this; it must not nag
        var wall=$('tow-wallimg');
        if(!current()){ say('Please choose a product first'); return null; }
        var haveWall = camOn || (wall.src && wall.style.display!=='none');
        if(!haveWall){ say('Please pick a room, use the camera, or upload a wall photo'); return null; }
        var panels=$('tow-framebox').querySelectorAll('.af-tow-wpanel');
        if(!panels.length){ say('Still preparing the preview — try again in a moment'); return null; }
        // draw from the same cropped image the panels display, so the file matches
        var artSrc=(cropImg && cropImg.complete && cropImg.naturalWidth>0) ? cropImg : artImg;
        if(!(artSrc && artSrc.naturalWidth>0)){ say('Artwork is still loading — try again in a moment'); return null; }
        try{
          var stage=$('tow-stage'), r=stage.getBoundingClientRect();
          var cv=document.createElement('canvas'); cv.width=Math.round(r.width); cv.height=Math.round(r.height); var ctx=cv.getContext('2d');
          // Cover-fit like CSS object-fit:cover, honouring object-position so the
          // export frames the room exactly the way the preview does.
          function cover(src, nw, nh, pos){
            var sc=Math.max(r.width/nw, r.height/nh), dw=nw*sc, dh=nh*sc;
            var m=String(pos||'50% 50%').match(/([\d.]+)%\s+([\d.]+)%/);
            var px=m?parseFloat(m[1])/100:0.5, py=m?parseFloat(m[2])/100:0.5;
            ctx.drawImage(src, (r.width-dw)*px, (r.height-dh)*py, dw, dh);
          }
          if(camOn){
            var v=$('tow-cam');
            cover(v, v.videoWidth||r.width, v.videoHeight||r.height, '50% 50%');
          }else{
            cover(wall, wall.naturalWidth||r.width, wall.naturalHeight||r.height, getComputedStyle(wall).objectPosition);
          }
          function rect(el){ var b=el.getBoundingClientRect(); return {x:b.left-r.left,y:b.top-r.top,w:b.width,h:b.height}; }
          var color=$('tow-color').value, hex=SWATCH[color]||'#1a1a1a';
          var frameName=$('tow-frame').value;
          var N=panels.length||1;
          // The same rendered moulding the screen shows, 9-sliced into the
          // export so corners keep their true mitres at any panel size.
          function drawFrameRing(fr, bw){
            if(!(bw>0)) return;
            var t=frameTexture(frameName, color), s=t.cv, B=t.B, S=t.S;
            var iw=Math.max(1, fr.w-2*bw), ih=Math.max(1, fr.h-2*bw);
            ctx.drawImage(s, 0,0,B,B,             fr.x,fr.y,bw,bw);                       // corners
            ctx.drawImage(s, S-B,0,B,B,           fr.x+fr.w-bw,fr.y,bw,bw);
            ctx.drawImage(s, 0,S-B,B,B,           fr.x,fr.y+fr.h-bw,bw,bw);
            ctx.drawImage(s, S-B,S-B,B,B,         fr.x+fr.w-bw,fr.y+fr.h-bw,bw,bw);
            ctx.drawImage(s, B,0,S-2*B,B,         fr.x+bw,fr.y,iw,bw);                    // edges
            ctx.drawImage(s, B,S-B,S-2*B,B,       fr.x+bw,fr.y+fr.h-bw,iw,bw);
            ctx.drawImage(s, 0,B,B,S-2*B,         fr.x,fr.y+bw,bw,ih);
            ctx.drawImage(s, S-B,B,B,S-2*B,       fr.x+fr.w-bw,fr.y+bw,bw,ih);
          }
          // Pass 1: cast every panel's shadow on the wall first, so a later
          // panel's shadow can never fall across an already-drawn frame.
          ctx.save(); ctx.shadowColor='rgba(0,0,0,.4)'; ctx.shadowBlur=26; ctx.shadowOffsetX=10; ctx.shadowOffsetY=16;
          ctx.fillStyle=hex;
          panels.forEach(function(panel){
            var fr=rect(panel.querySelector('.af-tow-pframe'));
            ctx.fillRect(fr.x,fr.y,fr.w,fr.h);
          });
          ctx.restore();
          // Pass 2: moulding, mat and each panel's slice of the artwork
          var idx=0;
          panels.forEach(function(panel){
            var frEl=panel.querySelector('.af-tow-pframe'), matEl=panel.querySelector('.af-tow-pmat'), artEl=panel.querySelector('.af-tow-part');
            var fr=rect(frEl);
            var bw=parseFloat(getComputedStyle(frEl).borderTopWidth)||0;
            if(bw>0){ drawFrameRing(fr, bw); }
            else { ctx.fillStyle=hex; }
            var mt=rect(matEl); var st=getComputedStyle(matEl);
            if(parseFloat(st.paddingTop)>0){ ctx.fillStyle=(st.backgroundColor&&st.backgroundColor!=='rgba(0, 0, 0, 0)')?st.backgroundColor:'#f6f1e6'; ctx.fillRect(mt.x,mt.y,mt.w,mt.h); }
            var ar=rect(artEl);
            var siw=artSrc.naturalWidth/N;
            ctx.drawImage(artSrc, idx*siw, 0, siw, artSrc.naturalHeight, ar.x, ar.y, ar.w, ar.h);
            idx++;
          });
          var p=current(); var fname=((p&&p.name)?p.name.replace(/[^a-z0-9]+/gi,'-').replace(/^-+|-+$/g,'').toLowerCase():'my')+'-on-wall.png';
          return { url: cv.toDataURL('image/png'), name: fname };
        }catch(err){ say('Couldn’t build the preview — right-click the wall to save it'); return null; }
      }

      $('tow-save').addEventListener('click', function(){
        var out=composePreview(); if(!out) return;
        var a=document.createElement('a'); a.download=out.name; a.href=out.url;
        document.body.appendChild(a); a.click(); a.remove();
        toast('✓ Saved to your downloads: '+out.name);
      });

      // ── SAVE TO ACCOUNT + SHARE (spec §8) ────────────────────────────
      // Cleared whenever the configuration changes, so sharing never sends the
      // image of a preview the visitor has already moved on from.
      var savedURL='';
      ['tow-prod','tow-frame','tow-size','tow-color','tow-scale'].forEach(function(id){
        $(id).addEventListener('change', function(){
          savedURL='';
          // the wall was captured before an artwork was chosen: keep it now
          if(id==='tow-prod' && CAL.frozen && !CAL.autoSaved) autoSaveLocked();
        });
      });
      $('tow-layouts').addEventListener('click', function(){ savedURL=''; });
      function shareTarget(){
        // share the saved image when there is one, otherwise the configured product
        if(savedURL) return savedURL;
        var p=current();
        return p ? ($('tow-view').getAttribute('href')||p.url) : location.href;
      }
      function shareText(){
        var p=current();
        return (p?('“'+p.name+'” '):'') + 'on my wall — from The Art Framer';
      }
      $('tow-saveacct').addEventListener('click', function(){
        var btn=this, out=composePreview(); if(!out) return;
        btn.disabled=true; toast('Saving to your account…');
        var p=current();
        AFPreview.save(out.url, {
          product: p?p.id:0, source:'try-on-wall',
          size: $('tow-size').value, frame: $('tow-frame').value,
          color: $('tow-color').value, layout: LAYOUT
        }, function(ok, msg, url, fallback){
          btn.disabled=false;
          if(ok){
            savedURL=url||'';
            toast('✓ '+msg);
            var link=$('tow-acctlink');
            if(link && fallback){ link.href=fallback; link.style.display='inline'; }
          }else{
            toast(msg);
            if(fallback && !AFPreview.cfg.logged){
              var l=$('tow-acctlink');
              if(l){ l.href=AFPreview.withRedirect(fallback); l.textContent='Sign in →'; l.style.display='inline'; }
            }
          }
        });
      });
      $('tow-share-wa').addEventListener('click', function(){
        if(AFPreview.native('The Art Framer', shareText(), shareTarget())) return;
        window.open(AFPreview.whatsapp(shareText(), shareTarget()), '_blank', 'noopener');
      });
      $('tow-share-mail').addEventListener('click', function(){
        location.href = AFPreview.email('My wall preview — The Art Framer', shareText(), shareTarget());
      });
      $('tow-share-copy').addEventListener('click', function(){
        AFPreview.copy(shareTarget(), function(ok){
          toast(ok ? '✓ Link copied' : 'Could not copy — long-press the link to copy it');
        });
      });

      window.addEventListener('resize', function(){
        if(CAL.frozen) unpinStage();   // before the measurement reads the box
        applyScale();
      });

      // The stage now stretches to match the control rail, so its height can
      // change without the window changing size. pixels-per-foot is derived
      // from that height, so re-run the maths whenever the box itself moves.
      if (window.ResizeObserver) {
        var scaleRO = new ResizeObserver(function(){ applyScale(); });
        scaleRO.observe($('tow-stage'));
      }

      // Show a realistic room by default so the preview always looks real
      setScene(0);

      // Pre-select a product passed via ?product=<id> (from product page / cards)
      (function preselect(){
        var m = location.search.match(/[?&]tow=(\d+)/);
        if(!m) return;
        var pid = m[1];
        var prod = PRODUCTS.filter(function(p){ return String(p.id)===String(pid); })[0];
        if(!prod) return;
        // Set category (first cat) then repopulate products, then select the product
        var cat = (prod.cats||[])[0] || '';
        if(cat){ $('tow-cat').value = cat; fillProducts(cat); }
        $('tow-prod').value = pid;
        // defaults for a nice first view — the first frame we can still make
        refresh();
        var el=document.querySelector('.af-tow-wrap'); if(el) el.scrollIntoView({behavior:'smooth',block:'start'});
      })();

      /* ── THE CAMERA STRIP, PHONES ONLY ───────────────────────────────────
         Nothing here changes the calibration. The rectangle, the stage and the
         maths that turn it into a measured price all stay exactly as they were
         - CAL.base is ((CAL_BOT-CAL_TOP) * stage height) / wallFt, so anything
         that shrank the video inside the stage would quietly corrupt the one
         number this feature exists to get right.

         So the strip above the video only MIRRORS the two controls, and reads
         their state from the inline display the existing code already sets:
         #tow-cal for the instruction (its parent - which is why the node itself
         is not moved) and #tow-camstop for whether the camera is running. The ✕
         forwards its click to the real button, so stopping the camera still
         runs exactly one code path. A hidden button still answers .click(). */
      (function(){
        var head = document.getElementById('tow-camhead');
        var cal  = document.getElementById('tow-cal');
        var msg  = document.getElementById('tow-calmsg');
        var stop = document.getElementById('tow-camstop');
        if(!head || !cal || !msg || !stop) return;
        var hMsg = head.querySelector('.af-tow-camhead-msg');
        var hX   = head.querySelector('.af-tow-camhead-x');
        // The third thing that sits on the video, and the one nobody asked
        // about because it only appears 1.1 seconds after the wall locks:
        // #tow-recal, the "tap to recalibrate" pill in the top LEFT corner.
        // Hiding it without a replacement would strand anyone whose lock
        // drifted, so it is mirrored here like the other two.
        var recal = document.getElementById('tow-recal');
        var hRe   = head.querySelector('.af-tow-camhead-recal');
        // After the lock the camera is off and its picture is held, so the
        // pill's old words ("tap to recalibrate") describe the wrong thing:
        // what that tap now does is look again. The full-size button keeps
        // its own label; this is the phone strip's shorter one.
        if(hRe) hRe.textContent = '📐 Scan again';
        // Wall height, back within reach - but as one chip, not the row of
        // three that used to lie across the video.
        //
        // It is measured that the three buttons DO work: pressing 8, 9 and 10
        // on the panel row moves the drawn artwork 47, 41 and 37px high. What
        // they deliberately do not move is the red rectangle, which stayed
        // 163px throughout - it is the target the ceiling and floor are lined
        // up to, not a picture of the wall, and shrinking it for an 8ft wall
        // would give a smaller target to aim at for no gain in accuracy.
        //
        // The problem is only that on a phone the row is now in the panel,
        // scrolled away while the phone is held up at the wall: the height the
        // preview is being measured against is neither visible nor reachable.
        // So the chip shows the current value and cycles 8 -> 9 -> 10 on tap,
        // by pressing the real panel button - af_tow_set_wall_ft stays the one
        // place wall height changes.
        var wallRow = document.getElementById('tow-wallh');
        var hFt     = head.querySelector('.af-tow-camhead-ft');
        var FT      = ['8','9','10'];
        function currentFt(){
          if(!wallRow) return '10';
          var on = wallRow.querySelector('button[data-ft].on');
          return on ? on.getAttribute('data-ft') : '10';
        }
        // One literal, used by the stylesheet and by this script. The file
        // already has four different phone widths in play (600, 781, 900 and
        // 480); the two elements this strip replaces have always been treated
        // at 600, so that is the number, and it must not drift from the @media
        // rule or a rotation lands the controls back on the video.
        var mq   = window.matchMedia('(max-width:600px)');

        // Read the INLINE display, not the computed one: at this width the CSS
        // hides both originals, so computed style says 'none' for each of them
        // whatever the camera is doing. The inline value is the state.
        function on(el){ return el.style.display !== 'none' && el.style.display !== ''; }

        function sync(){
          if(!mq.matches){ head.style.display='none'; return; }
          var wantMsg = on(cal), wantX = on(stop), wantRe = !!recal && on(recal);
          if(wantMsg && hMsg.innerHTML !== msg.innerHTML) hMsg.innerHTML = msg.innerHTML;
          if(!wantMsg) hMsg.innerHTML = '';
          hMsg.style.display = wantMsg ? 'block' : 'none';
          hX.style.display   = wantX   ? 'block' : 'none';
          if(hRe) hRe.style.display = wantRe ? 'block' : 'none';
          if(hFt){
            // For the whole camera session - and on past the lock, because the
            // still the lock keeps is still being measured against a wall of
            // this height and the panel row is scrolled away on a phone.
            hFt.style.display = (wantX || wantRe) ? 'block' : 'none';
            var ft = currentFt();
            if(hFt.getAttribute('data-ft') !== ft){
              hFt.setAttribute('data-ft', ft);
              hFt.textContent = ft + ' ft';
              hFt.setAttribute('aria-label', 'Wall height ' + ft + ' feet, tap to change');
            }
          }
          head.style.display = (wantMsg || wantX || wantRe) ? 'flex' : 'none';
        }

        hX.addEventListener('click', function(){ stop.click(); });
        if(hRe && recal) hRe.addEventListener('click', function(){ recal.click(); });
        if(hFt && wallRow){
          hFt.addEventListener('click', function(){
            var next = FT[(FT.indexOf(currentFt()) + 1) % FT.length];
            var btn = wallRow.querySelector('button[data-ft="' + next + '"]');
            if(btn) btn.click();      // the panel's own button, so one code path
            sync();
          });
          // The panel row can also be pressed directly; keep the chip in step.
          new MutationObserver(sync).observe(wallRow, {
            attributes:true, attributeFilter:['class'], subtree:true });
        }

        var mo = new MutationObserver(sync);
        mo.observe(cal,  { attributes:true, attributeFilter:['style'],
                           childList:true, subtree:true, characterData:true });
        mo.observe(stop, { attributes:true, attributeFilter:['style'] });
        if(recal) mo.observe(recal, { attributes:true, attributeFilter:['style'] });
        if(mq.addEventListener) mq.addEventListener('change', sync); else mq.addListener(sync);
        sync();
      })();
    })();
    </script>

    <link rel="stylesheet" id="af-try-on-wall" href="<?php echo esc_url(af_static_url('try-on-wall.css')); ?>" data-no-optimize="1">
    <?php
    get_footer();
    exit;
}, 1);

// ─────────────────────────────────────────────────────────────
// PHASE 14 — Mobile responsiveness hardening. Only applies at mobile
// breakpoints; additive, does not change desktop layout/logic.
// ─────────────────────────────────────────────────────────────
add_action('wp_head', function() {
    ?>
    <link rel="stylesheet" id="af-mobile-responsive" href="<?php echo esc_url(af_static_url('mobile-responsive.css')); ?>" data-no-optimize="1">
    <?php
}, 99);

// ─────────────────────────────────────────────────────────────
// PHASE 15 — Fix blank product cards on shop/category: force any
// lazy-loaded images (LiteSpeed/Elementor/native) to load. Their
// data is valid; the lazy placeholder just never swapped in.
// DISABLED: LiteSpeed lazy-load is now turned off at the source
// (media-lazy=false), so this JS patch is unnecessary and could
// conflict with other image scripts.
// ─────────────────────────────────────────────────────────────
add_action('wp_footer', function() {
    return; // DISABLED — lazy-load off at source
    if (!function_exists('is_shop')) return;
    if (!(is_shop() || is_product_category() || is_product_tag())) return;
    ?>
    <script>
    (function(){
      function unlazy(){
        // <img> lazy variants
        document.querySelectorAll('img:not([data-afun])').forEach(function(im){
          var real = im.getAttribute('data-src') || im.getAttribute('data-lazy-src') ||
                     im.getAttribute('data-lazysrc') || im.getAttribute('data-original') ||
                     im.getAttribute('data-ls-src');
          var cur = im.getAttribute('src') || '';
          if (real && (cur === '' || cur.indexOf('data:image') === 0 || cur.indexOf('base64') > -1 || cur.indexOf('lazy') > -1)) {
            im.src = real;
          }
          var ss = im.getAttribute('data-srcset') || im.getAttribute('data-lazy-srcset');
          if (ss && !im.getAttribute('srcset')) im.setAttribute('srcset', ss);
          im.removeAttribute('loading');
          im.classList.remove('lazyload','litespeed-lazyload');
          im.setAttribute('data-afun','1');
        });
        // lazy background images
        document.querySelectorAll('[data-bg]:not([data-afun]),[data-lazy-bg]:not([data-afun])').forEach(function(el){
          var v = el.getAttribute('data-bg') || el.getAttribute('data-lazy-bg');
          if (v) el.style.backgroundImage = 'url("' + v + '")';
          el.setAttribute('data-afun','1');
        });
      }
      function run(){ try{ unlazy(); }catch(e){} }
      if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run); else run();
      window.addEventListener('load', function(){ run(); setTimeout(run,400); setTimeout(run,1200); });
      // Re-run when the grid loads more cards (AJAX) — childList only, no loop
      var mo = new MutationObserver(function(muts){
        if (muts.some(function(m){ return m.addedNodes && m.addedNodes.length; })) run();
      });
      if (document.body) mo.observe(document.body, { childList:true, subtree:true });
    })();
    </script>
    <?php
}, 40);

// ─────────────────────────────────────────────────────────────
// PHASE 16 — Digital Download modal from product-card buttons.
// Clicking a card's "Digital Download" opens a modal; Add to Cart
// adds a digital line (own price + label). Additive.
// ─────────────────────────────────────────────────────────────

// Digital download price — per piece, not one flat number.
//
// Every download used to be $9.99, which reads as a placeholder rather than a
// price. Each piece now gets its own figure between $9.00 and $9.99, fixed for
// life by its product ID so it never drifts between page loads or between the
// modal, the cart and the order. The "was" price is 40% above it and shown
// struck through, in the same sale format the canvases use.
function af_digital_price($pid = 0) {
    $pid = (int) $pid;
    $base = $pid ? 9.00 + ((crc32('af-dd-' . $pid) % 100) / 100) : 9.99;
    return (float) apply_filters('af_digital_price', round($base, 2), $pid);
}
function af_digital_was($pid = 0) {
    return round(af_digital_price($pid) * 1.40, 2);
}
function af_digital_price_html($pid = 0) {
    // <del>was</del><ins>now</ins> is WooCommerce's own sale markup; the
    // pricerow script that styles every card then adds the "(x% off)" badge
    // and the weights, so this reads exactly like the canvases' prices.
    // The badge is written here rather than left to the pricerow scanner:
    // the modal's price is swapped in after load and the scanner did not
    // reliably catch that swap, so the owner saw the strike without the saving.
    $now = af_digital_price($pid); $was = af_digital_was($pid);
    $pct = $was > 0 ? (int) round(($was - $now) / $was * 100) : 0;
    return wc_format_sale_price(wc_price($was), wc_price($now))
         . ($pct > 0 ? ' <span class="af-pct-off">(' . $pct . '% off)</span>' : '');
}

/**
 * Is this line a digital download?
 *
 * Two ways to be one. The modal posts af_digital, which is how ANY canvas can
 * be bought as a file alongside its print. And a product that lives in a
 * digital-download category is only ever a file — there is no print to sell —
 * so it is digital however it reached the cart.
 *
 * That second case is what was missing. The shop showed such a product at its
 * WooCommerce price of $80.00 on the card while the modal sold the very same
 * piece for $9.43, and a plain Add to Cart charged the $80.00: the canvas price
 * for a JPG with no canvas behind it.
 */
/**
 * Did the shopper ask for the file? Either the Digital Download modal (it posts
 * af_digital) or "What you receive: Digital download" on the product page (it
 * posts af_kit=digital). Both are the same purchase and are priced as one.
 */
function af_request_wants_digital() {
    if (!empty($_REQUEST['af_digital'])) return true;
    return isset($_REQUEST['af_kit']) && sanitize_key(wp_unslash($_REQUEST['af_kit'])) === 'digital';
}

function af_sells_as_digital($pid) {
    $p = wc_get_product($pid);
    if (!$p) return false;
    if (af_is_digital_download($p)) return true;
    // The Digital Download modal and "What you receive: Digital download" sell
    // the file of an ARTWORK. A request saying af_digital=1 on anything else
    // (a gift card, stretcher bars, a frame) was priced at the file's ~$9 and
    // made shipping-free (security audit, 12 Oct): only artwork honours it.
    if (!af_request_wants_digital()) return false;
    return af_pricing_applies($p) || (function_exists('af_kit_is_corporate') && af_kit_is_corporate($pid));
}

// Mark the cart item as a digital download with its own price/label
add_filter('woocommerce_add_cart_item_data', function($data, $pid) {
    if (af_sells_as_digital($pid)) {
        $data['af_digital'] = '1';
        $data['af_price']   = af_digital_price($pid);
        $data['af_unique']  = md5('digital|' . $pid . '|' . microtime());
    }
    return $data;
}, 20, 2);

/**
 * A digital-only product prices as the file it is, everywhere the shop quotes
 * it — card, product page, related strips, quick view.
 *
 * Front end only. The admin list and any feed keep WooCommerce's own figure, so
 * what is stored on the product is never hidden from whoever maintains it.
 */
add_filter('woocommerce_get_price_html', function($html, $product) {
    if (is_admin() && !defined('DOING_AJAX')) return $html;
    if (!($product instanceof WC_Product) || !af_is_digital_download($product)) return $html;
    return af_digital_price_html($product->get_id());
}, 20, 2);

// Show "Digital Download" in cart; hide size/frame for digital lines
add_filter('woocommerce_get_item_data', function($data, $item) {
    if (!empty($item['af_digital'])) {
        // strip any size/frame rows that may have been added
        $data = array_values(array_filter($data, function($row){
            return !in_array(($row['name'] ?? ''), array('Size','Frame Type','Frame Color'), true);
        }));
        $data[] = array('name'=>'Format', 'value'=>'Digital Download (instant, kept in your account)');
    }
    return $data;
}, 20, 2);

// Make digital lines virtual (no shipping) at cart calc time
add_action('woocommerce_before_calculate_totals', function($cart){
    if (is_admin() && !defined('DOING_AJAX')) return;
    if (empty($cart) || !is_a($cart,'WC_Cart')) return;
    foreach ($cart->get_cart() as $item) {
        if (!empty($item['af_digital']) && is_object($item['data'])) {
            $item['data']->set_virtual(true);
        }
    }
}, 21);

// Persist to order line item
add_action('woocommerce_checkout_create_order_line_item', function($item, $key, $values){
    if (!empty($values['af_digital'])) $item->add_meta_data('Format', 'Digital Download');
}, 20, 3);

// The modal used to display the card's raw image, which a right-click saved
// cleanly — the exact leak shown in the screen recording. It now asks the
// server for a WATERMARKED preview of the product's artwork instead.
function af_dd_preview_handler() {
    $pid = isset($_GET['pid']) ? absint($_GET['pid']) : 0;
    if (!$pid || get_post_type($pid) !== 'product') wp_send_json_error();
    if ((get_post_status($pid) !== 'publish' || post_password_required($pid)) && !current_user_can('edit_post', $pid)) wp_send_json_error();
    $thumb = get_post_thumbnail_id($pid);
    if (!$thumb) wp_send_json_error();
    $price = af_digital_price_html($pid);
    $wm = function_exists('af_wm_preview_url') ? af_wm_preview_url($thumb) : false;
    if ($wm && !empty($wm['url'])) wp_send_json_success(array('url' => $wm['url'], 'wm' => 1, 'price_html' => $price));
    // Watermarking unavailable (GD can choke on the very large masters).
    // Fall back to a genuinely small size — and never the master: WordPress
    // returns the ORIGINAL file for any size an image never generated, which
    // is exactly the leak this endpoint exists to close.
    //
    // This used to guess at three size NAMES and compare URLs. That is the
    // blank preview pane in the recording: a site whose masters never produced
    // 'medium', 'woocommerce_thumbnail' or 'thumbnail' — or produced them under
    // other names — failed all three tests and the modal opened empty, with Add
    // to Cart still live, selling a file nobody could see.
    //
    // Ask the attachment what it ACTUALLY generated instead. The metadata's
    // 'sizes' map lists only real files sitting beside the master, whatever
    // they are called, so there is no guessing and still no way to serve the
    // original. Largest first: this is a preview pane, not a thumbnail.
    $master = wp_get_attachment_url($thumb);
    $meta   = wp_get_attachment_metadata($thumb);
    if (!empty($meta['sizes']) && is_array($meta['sizes'])) {
        $cands = array();
        foreach ($meta['sizes'] as $name => $s) {
            if (empty($s['file'])) continue;
            $cands[$name] = (int) (isset($s['width']) ? $s['width'] : 0);
        }
        arsort($cands);
        foreach (array_keys($cands) as $name) {
            $src = wp_get_attachment_image_src($thumb, $name);
            if ($src && !empty($src[0]) && $src[0] !== $master) {
                wp_send_json_success(array('url' => $src[0], 'wm' => 0, 'price_html' => $price));
            }
        }
    }
    // Nothing beside the master. Rather than open blank, say so in the payload
    // and let the modal explain itself — the price is still worth sending, so
    // the visitor sees what the piece costs instead of a stale default.
    wp_send_json_success(array('url' => '', 'wm' => 0, 'price_html' => $price, 'nopreview' => 1));
}
add_action('wp_ajax_af_dd_preview',        'af_dd_preview_handler');
add_action('wp_ajax_nopriv_af_dd_preview', 'af_dd_preview_handler');

// Some theme-built cards carry no data-product_id at all — but they do link to
// the product page. Resolve the slug so those cards can still sell properly
// instead of falling into the not-available state.
function af_dd_resolve_handler() {
    $slug = isset($_GET['slug']) ? sanitize_title(wp_unslash($_GET['slug'])) : '';
    if ($slug === '') wp_send_json_error();
    $post = get_page_by_path($slug, OBJECT, 'product');
    if (!$post || $post->post_status !== 'publish') wp_send_json_error();
    wp_send_json_success(array('pid' => (int) $post->ID, 'name' => get_the_title($post)));
}
add_action('wp_ajax_af_dd_resolve',        'af_dd_resolve_handler');
add_action('wp_ajax_nopriv_af_dd_resolve', 'af_dd_resolve_handler');

// Render the Digital Download modal on homepage / shop / category
add_action('wp_footer', function() {
    if (is_admin()) return;
    $is_shop = function_exists('is_shop') && (is_shop() || is_product_category() || is_product_tag());
    if (!is_front_page() && !$is_shop) return;
    $price_html = af_digital_price_html();   // replaced per piece once the preview loads
    ?>
    <div id="af-dd-overlay" class="af-dd-overlay" data-dd-close>
      <div class="af-dd-modal">
        <button class="af-dd-x" data-dd-close aria-label="Close">&times;</button>
        <div class="af-dd-flex">
          <div class="af-dd-imgwrap"><img id="af-dd-img" alt="" draggable="false"><span class="af-dd-shield" aria-hidden="true"></span></div>
          <div class="af-dd-info">
            <span class="af-dd-tag">⬇ Instant Digital Download</span>
            <h3 id="af-dd-title">Digital Download</h3>
            <ul class="af-dd-feat">
              <li>High-resolution, print-ready file (JPG)</li>
              <li>Delivered instantly by email after purchase</li>
              <li>Print at home or any print shop — no frame needed</li>
            </ul>
            <div class="af-dd-price" id="af-dd-price"><?php echo $price_html; ?></div>
            <div class="af-dd-actions">
              <button id="af-dd-add" class="af-dd-btn solid">Add to Cart</button>
              <a id="af-dd-view" class="af-dd-btn ghost">View Product</a>
            </div>
            <p class="af-dd-msg" id="af-dd-msg"></p>
          </div>
        </div>
      </div>
    </div>
    <script>
    (function(){
      var overlay=document.getElementById('af-dd-overlay');
      var imgEl=document.getElementById('af-dd-img'), titleEl=document.getElementById('af-dd-title');
      var viewEl=document.getElementById('af-dd-view'), addEl=document.getElementById('af-dd-add'), msgEl=document.getElementById('af-dd-msg');
      var wrapEl=document.querySelector('#af-dd-overlay .af-dd-imgwrap');
      // Guarded: a throw in here would leave the modal half-built, and the
      // shimmer is decoration - never a reason to lose the preview.
      function loading(on){ if(wrapEl) wrapEl.classList.toggle('loading', !!on); }
      var curPid='';
      var AJAX = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
      // Bumped on every open, so a lookup that comes back after the visitor has
      // moved on to another card cannot repaint the modal behind their back.
      var reqSeq = 0;
      function open(){ overlay.classList.add('open'); document.body.style.overflow='hidden'; }
      function close(){ overlay.classList.remove('open'); document.body.style.overflow=''; msgEl.textContent=''; loading(false); }
      /* ── CLOSING IT ──────────────────────────────────────────────────
         This used to bind click on the × and on the backdrop, and neither
         worked: the quick view that opens this modal leaves a listener on
         DOCUMENT, in the CAPTURE phase, that calls stopPropagation() on every
         click. Capture runs document -> html -> body -> ... -> target, so the
         event was stopped at the very first step and never reached anything
         inside the modal at all. Measured on the live page: the capture chain
         recorded exactly one entry, "document", and then nothing; pressing ×
         twenty times did nothing, and only Escape - a different event type,
         which that listener does not touch - ever closed it. That is the
         recording: the shopper hitting × over and over on a modal that will
         not go away.

         So close from DOCUMENT CAPTURE too. stopPropagation() does not stop
         other listeners on the same node, so this one still runs (proven: a
         listener added there during the same test did fire). Kept as a target
         test rather than a bound element, since the event never descends.

         CLICK ONLY, and that matters. A pointerdown listener was here too,
         for the stricter case of a trap using stopImmediatePropagation() - and
         it made things worse in a way only the live page showed. Closing on
         pointerdown takes the modal out from under the pointer before the
         click is dispatched, so the click's target is computed against
         whatever the modal had been covering: a product card, whose own quick
         view then opens. Measured on the category page as the class sequence
         ["closed","OPEN"] - it closed and something immediately reopened it,
         which from the shopper's chair is indistinguishable from the × being
         dead. On click the modal is still under the pointer when the target is
         chosen, so there is no second dispatch to go astray. */
      function wantsClose(e){
        var t = e.target;
        if(!t || !t.closest) return false;
        if(!overlay.classList.contains('open')) return false;
        if(t === overlay) return true;                       // the backdrop itself
        if(!overlay.contains(t)) return false;               // not our modal at all
        // The OVERLAY carries data-dd-close as well, and it is an ancestor of
        // everything in here - so a bare closest('[data-dd-close]') matches a
        // click on Add to Cart just as readily as one on the ×, and would shut
        // the modal on the one action it exists to offer. Only a marked
        // element that is not the overlay counts.
        var hit = t.closest('[data-dd-close]');
        return !!(hit && hit !== overlay);
      }
      document.addEventListener('click', function(e){
        if(wantsClose(e)){ e.preventDefault(); close(); }
      }, true);
      document.addEventListener('keydown', function(e){ if(e.key==='Escape') close(); });

      // "View Product" is the one action that has to survive every branch of
      // the handler below — including the not-available one, where an item you
      // cannot buy as a download is still an item you want to go and look at.
      // Left at its markup default of href="#" it does nothing but jump the
      // page to the top, which is the dead click reported on the homepage.
      function setView(a){
        if(!viewEl) return;
        if(a && a.href){ viewEl.href = a.href; viewEl.style.display = ''; }
        else { viewEl.removeAttribute('href'); viewEl.style.display = 'none'; }
      }
      // WooCommerce stamps post-<id> on the loop element and themes prefix it
      // (elementor-post-123). Digits are required, so post-thumbnail and its
      // friends can never be mistaken for an id.
      function postId(el){
        var cn = (el && typeof el.className === 'string') ? el.className : '';
        var m = cn.match(/\bpost-(\d+)(?=\s|$)/);
        return m ? m[1] : '';
      }
      // Every product id named inside a subtree. One card names exactly one;
      // a section wrapper that happens to match CARD_SEL names as many as it
      // holds, which is how the modal can end up describing a card other than
      // the one that was clicked.
      function idsIn(el){
        var out=[];
        function add(v){ if(v && out.indexOf(v)<0) out.push(v); }
        el.querySelectorAll('[data-product_id],[data-product-id]').forEach(function(n){
          add(n.getAttribute('data-product_id') || n.getAttribute('data-product-id'));
        });
        el.querySelectorAll('a[href]').forEach(function(a){
          var m=(a.getAttribute('href')||'').match(/[?&]add-to-cart=(\d+)/); if(m) add(m[1]);
        });
        el.querySelectorAll('[class*="post-"]').forEach(function(n){ add(postId(n)); });
        add(postId(el));
        return out;
      }
      function sameOrigin(href){
        try { return new URL(href, location.href).origin === location.origin; } catch(e){ return false; }
      }
      // src='' renders the browser's broken-image icon; the recording caught it
      // sitting in the corner of the preview pane. Hide the <img> instead and
      // let the wrap's own grey stand in for it.
      function clearImg(){ imgEl.removeAttribute('src'); imgEl.style.display='none'; imgEl.style.opacity='1'; }
      function showImg(url){
        if(url){ imgEl.src = url; imgEl.style.display=''; imgEl.style.opacity='1'; }
        else { clearImg(); }
      }
      function unavailable(seq){
        if(seq !== undefined && seq !== reqSeq) return;
        loading(false);
        clearImg();
        titleEl.textContent = 'Not available for instant download';
        if(addEl){ addEl.style.display = 'none'; addEl.disabled = false; }
        msgEl.style.color = '';
        msgEl.textContent = 'This item cannot be purchased as a digital download.';
        open();
      }
      // never show the raw artwork here: ask the server for the watermarked
      // preview (the raw file was one right-click away in the old modal)
      function loadPreview(pid, seq){
        clearImg();
        // Not silence. The first visitor to open a given piece waits while the
        // server renders its watermarked preview from the master - seconds, on
        // a large file - and until now that wait was an empty white pane with
        // no explanation, which is what the recording shows. Say what is
        // happening; the answer below replaces this line either way.
        msgEl.style.color = '';
        msgEl.textContent = 'Preparing preview…';
        loading(true);
        fetch(AJAX + '?action=af_dd_preview&pid=' + encodeURIComponent(pid), {credentials:'same-origin'})
          .then(function(r){ return r.json(); })
          .then(function(j){
            if(seq!==reqSeq) return;
            loading(false);
            var d = j&&j.success&&j.data ? j.data : null;
            showImg(d&&d.url ? d.url : '');
            msgEl.textContent = '';
            var priceEl = document.getElementById('af-dd-price');
            if(priceEl && d && d.price_html) priceEl.innerHTML = d.price_html;
            // An empty pane with a live Add to Cart is what the recording
            // caught. If the server could not produce a preview, say so rather
            // than leave the visitor looking at nothing and guessing.
            if(d && !d.url){
              msgEl.style.color = '';
              msgEl.textContent = 'Preview image unavailable — the full-resolution file is unaffected.';
            }
          })
          .catch(function(){
            if(seq!==reqSeq) return;
            loading(false);
            clearImg();
            msgEl.style.color = '';
            msgEl.textContent = 'Preview could not be loaded. Please try again.';
          });
      }

      // Detect a "Digital Download" trigger inside a product card.
      // Robust across sections: strip icons/whitespace and match the label
      // exactly ("⊕ Digital Download", "Digital Download", etc.), walking up a
      // few levels so clicking the icon or its link still opens the modal.
      var CARD_SEL = '.product-card, li.product, .product, .product-block, [class*="product-block"]';
      // Where a Digital Download trigger can never be, whatever the selectors
      // below happen to match: the page's own chrome.
      var DD_NEVER = 'header,.site-header,[class*="site-header"],[class*="site-branding"],'
                   + '.custom-logo-link,[class*="site-logo"],.elementor-widget-site-logo,.hfe-site-logo,'
                   + 'nav,.main-navigation,[role="banner"],[role="navigation"],footer,#colophon,'
                   // The card's eye is the QUICK VIEW, and the site already
                   // has one modal for it - inc/card-actions.php deliberately
                   // routes that button to the theme's, "one modal
                   // everywhere". With this modal answering the same click,
                   // both opened and the quick view's iframe landed on top of
                   // this one's × (measured: topAtX was iframe.af-qv-frame,
                   // so the close button could not even be reached). A
                   // Digital Download trigger is its own control on the card.
                   + '[class*="quick-view"],[class*="quickview"],[class*="quick_view"],'
                   + '[class*="woosq"],[data-quick-view],.view-btn';
      document.addEventListener('click', function(e){
        /* A click INSIDE the modal is never a card trigger. This listener and
           the one that closes the modal both sit on document in the capture
           phase, so both run for the same click, in the order they were bound
           - close first, this one second. Pressing × therefore closed the
           modal and then re-opened it, 2ms apart, within one click: stack
           traces from the live page name both sides, "at close(...)" then
           "at open(...)" from this handler. From the shopper's chair that is
           a × that does nothing, which is what the owner recorded. The
           overlay is not a product card and never was; say so before anything
           else is considered. */
        if(overlay.contains(e.target)) return;
        /* Nor is the chrome around the page. A Digital Download trigger is a
           control on a product card; the header, the logo, the nav and the
           footer are none of those, and a click there has somewhere of its
           own to go. The sibling quick-view interceptor already excludes the
           same places, for the same reason. */
        if(e.target.closest(DD_NEVER)) return;
        var trg = e.target.closest('.digital-download, .digital-download-btn, [class*="digital-download"], [data-digital-download]');
        /* And <body> is not a trigger, however it is dressed.
           WordPress puts the term slug on the body of a category archive, so
           on /product-category/digital-downloads-2/ the body carries
           class="... term-digital-downloads-2 ..." - which the substring
           selector above matches. Measured on the live page: every click
           anywhere on that archive resolved to trg = <body>, and body also
           satisfies CARD_SEL, so the handler called preventDefault() and
           opened the modal. Clicking the logo stopped going home. Only a real
           element inside a card can be a trigger. */
        if(trg && (trg === document.body || trg === document.documentElement)) trg = null;
        if(!trg){
          var node = e.target;
          for(var i=0;i<4 && node && node!==document.body;i++){
            var txt = (node.textContent||'').replace(/[^a-z]/gi,'').toLowerCase();
            if(txt === 'digitaldownload'){ trg = node; break; }
            node = node.parentElement;
          }
        }
        if(!trg) return;
        var card = trg.closest(CARD_SEL); if(!card) return;
        // Same trap one level down: the archive body answers CARD_SEL too.
        if(card === document.body || card === document.documentElement) return;
        e.preventDefault(); e.stopPropagation();

        // CARD_SEL matches a row wrapper as readily as a card, and closest()
        // stops at the first match either way — so on a four-across row whose
        // cards carry no matching class, every lookup below reads the FIRST
        // card and the modal opens the wrong artwork. Now that View Product
        // navigates, that would send the shopper somewhere else entirely.
        // Walk up from the trigger and keep the LARGEST container that still
        // names exactly one product, never widening past the CARD_SEL match.
        var scope=null, hop=trg;
        for(var up=0; up<12 && hop && hop!==document.body; up++){
          var n=idsIn(hop).length;
          if(n===1) scope=hop;
          else if(n>1) break;
          if(hop===card) break;
          hop=hop.parentElement;
        }
        if(scope) card=scope;
        var seq = ++reqSeq;
        msgEl.style.color = '';
        var anchors = Array.prototype.slice.call(card.querySelectorAll('a[href]'));

        // Pick the card's product link. Half the anchors in a card go
        // somewhere else — the wishlist heart, the brochure PDF, and Add to
        // Cart, which is only ever ?add-to-cart=<id>. Taking whichever anchor
        // happened to come first is how View Product ended up on a bare "#".
        function browsable(h){
          if(!h) return false;
          if(/^\s*(#|javascript:|mailto:|tel:)/i.test(h)) return false;
          if(/[?&]add-to-cart=/i.test(h)) return false;        // that link buys, it does not browse
          if(/[?&]add_to_wishlist=/i.test(h)) return false;
          if(/\.(pdf|jpe?g|png|webp|gif|svg|zip)(\?|#|$)/i.test(h)) return false;
          return true;
        }
        var lnk = null, i2, href2;
        for(i2=0;i2<anchors.length;i2++){
          href2 = anchors[i2].getAttribute('href');
          if(browsable(href2) && /\/product\//.test(href2)){ lnk = anchors[i2]; break; }
        }
        if(!lnk){
          // No /product/ permalink base: the title's link, then the image's,
          // then whatever else in the card actually goes somewhere.
          var titleLink = card.querySelector('.product-title a[href], .woocommerce-loop-product__title a[href], h2 a[href], h3 a[href]');
          var imgInA    = card.querySelector('a[href] img');
          var cands = [titleLink, imgInA ? imgInA.closest('a[href]') : null].concat(anchors);
          for(i2=0;i2<cands.length;i2++){
            if(cands[i2] && browsable(cands[i2].getAttribute('href'))){ lnk = cands[i2]; break; }
          }
        }
        // Assign it here, ahead of every early return below: whatever the
        // modal can or cannot say about this item, the way through to its
        // product page has to work.
        setView(lnk);

        var atc = card.querySelector('[data-product_id],[data-product-id]');
        curPid = atc ? (atc.getAttribute('data-product_id') || atc.getAttribute('data-product-id') || '') : '';
        if(!curPid){
          // The loop element carries post-<id>, but a theme's own card markup
          // often sits inside it — so look up the tree as well as down it.
          var up = card;
          while(!curPid && up && up !== document.body){ curPid = postId(up); up = up.parentElement; }
        }
        if(!curPid){
          var inner = card.querySelectorAll('[class*="post-"]');
          for(i2=0;i2<inner.length && !curPid;i2++) curPid = postId(inner[i2]);
        }
        if(!curPid){
          // Add to Cart spells the id out in its own href: ?add-to-cart=31395
          for(i2=0;i2<anchors.length;i2++){
            var mc = (anchors[i2].getAttribute('href')||'').match(/[?&]add-to-cart=(\d+)/);
            if(mc){ curPid = mc[1]; break; }
          }
        }

        var im = card.querySelector('img');
        var t  = card.querySelector('.product-title, h2, h3, .woocommerce-loop-product__title');
        var name = t ? t.textContent.trim() : (lnk ? (lnk.title || lnk.textContent || '').trim() : '');

        // Still no id? The product link knows who it is — ask the server to
        // turn its slug back into one.
        var slug = '';
        if(!curPid && lnk && sameOrigin(lnk.href)){
          var ms = lnk.href.match(/\/product\/([^\/?#]+)/) || lnk.href.replace(/[?#].*$/,'').match(/\/([^\/]+)\/?$/);
          if(ms) slug = ms[1];
        }
        if(slug){
          // Say nothing about availability while the lookup is in flight. The
          // old code announced "not available" first and corrected itself a
          // round-trip later — so that wording was what the visitor was left
          // staring at whenever the lookup never ran.
          titleEl.textContent = name ? (name + ' — Digital Download') : 'Digital Download';
          if(addEl){ addEl.style.display = ''; addEl.disabled = true; }
          msgEl.textContent = '';
          clearImg();
          open();
          fetch(AJAX + '?action=af_dd_resolve&slug=' + encodeURIComponent(slug), {credentials:'same-origin'})
            .then(function(r){ return r.json(); })
            .then(function(j){
              if(seq !== reqSeq) return;
              if(j && j.success && j.data && j.data.pid){
                curPid = String(j.data.pid);
                if(!name && j.data.name) name = j.data.name;
                titleEl.textContent = name + ' — Digital Download';
                if(addEl){ addEl.style.display = ''; addEl.disabled = false; }
                loadPreview(curPid, seq);
              } else {
                unavailable(seq);
              }
            })
            .catch(function(){ unavailable(seq); });
          return;
        }

        // Ghost guard: a product with no image or no name must not sell blind —
        // the recording showed exactly that (blank title, broken image, Add to Cart).
        if(!curPid || !im || !name){ unavailable(seq); return; }

        if(addEl){ addEl.style.display = ''; addEl.disabled = false; }
        // Clear the previous piece's note FIRST. loadPreview() replaces it with
        // "Preparing preview…", and this line used to run after that call and
        // wipe it again - so the wait went back to being a silent blank pane.
        msgEl.textContent = '';
        titleEl.textContent = name + ' — Digital Download';
        loadPreview(curPid, seq);
        open();
      }, true);

      addEl.addEventListener('click', function(){
        if(!curPid){ msgEl.textContent='Please open this from a product to add it.'; return; }
        addEl.disabled=true; addEl.textContent='Adding…';
        fetch('/?wc-ajax=add_to_cart', {
          method:'POST', credentials:'same-origin',
          headers:{'Content-Type':'application/x-www-form-urlencoded'},
          body:'product_id='+encodeURIComponent(curPid)+'&quantity=1&af_digital=1'
        }).then(function(r){ return r.json(); }).then(function(res){
          addEl.disabled=false; addEl.textContent='Add to Cart';
          msgEl.style.color='#2e7d32';
          msgEl.textContent='✓ Digital download added to cart.';
          // update mini-cart counters if present
          document.body.dispatchEvent(new Event('wc_fragment_refresh'));
          if(res && res.fragments){ for(var k in res.fragments){ document.querySelectorAll(k).forEach(function(n){ n.outerHTML=res.fragments[k]; }); } }
        }).catch(function(){ addEl.disabled=false; addEl.textContent='Add to Cart'; msgEl.style.color='#c0392b'; msgEl.textContent='Could not add. Please try again.'; });
      });
    })();
    </script>
    <style>
    .af-dd-overlay{position:fixed;inset:0;z-index:100000;display:none;background:rgba(15,15,15,.82);backdrop-filter:blur(3px);align-items:center;justify-content:center;padding:20px;}
    .af-dd-overlay.open{display:flex;}
    .af-dd-modal{background:#fff;border-radius:16px;max-width:720px;width:100%;position:relative;box-shadow:0 24px 70px rgba(0,0,0,.5);overflow:hidden;}
    /* A preview being rendered on the server is work, not a blank wall: the
       pane shimmers while the fetch is out, so the first visitor to open a
       given piece can see that something is coming. Removed the moment the
       answer lands, whichever way it lands. */
    .af-dd-imgwrap.loading{position:relative;overflow:hidden;}
    .af-dd-imgwrap.loading::after{content:'';position:absolute;inset:0;pointer-events:none;
      background:linear-gradient(100deg,rgba(255,255,255,0) 20%,rgba(255,255,255,.65) 50%,rgba(255,255,255,0) 80%);
      background-size:220% 100%;animation:af-dd-shimmer 1.15s linear infinite;}
    @keyframes af-dd-shimmer{from{background-position:180% 0;}to{background-position:-80% 0;}}
    /* Stated in full, on every state, because the theme's generic button:hover
       otherwise lands on it: that rule carries a dark fill and its own padding,
       which turned the small grey cross into a black pill wider than the modal
       corner the moment the pointer touched it. */
    html body .af-dd-modal .af-dd-x{
      position:absolute;top:10px;right:10px;z-index:3;
      width:32px;height:32px;min-width:0;min-height:0;padding:0;margin:0;
      display:flex;align-items:center;justify-content:center;
      box-sizing:border-box;border:1px solid rgba(26,26,26,.10);border-radius:50%;
      background:rgba(26,26,26,.05);box-shadow:none;
      font-family:inherit;font-size:20px;font-weight:400;line-height:1;
      letter-spacing:0;text-transform:none;text-indent:0;
      color:#6b6b6b;cursor:pointer;
      transition:background .18s ease,color .18s ease,border-color .18s ease;}
    html body .af-dd-modal .af-dd-x:hover,
    html body .af-dd-modal .af-dd-x:focus,
    html body .af-dd-modal .af-dd-x:focus-visible,
    html body .af-dd-modal .af-dd-x:active{
      width:32px;height:32px;padding:0;
      background:#f3ead6;border-color:rgba(201,168,76,.55);
      color:#a8872e;box-shadow:none;outline:none;transform:none;}
    .af-dd-flex{display:flex;flex-wrap:wrap;}
    .af-dd-imgwrap{flex:1 1 260px;min-height:240px;background:#f4f4f4;position:relative;}
    .af-dd-shield{position:absolute;inset:0;z-index:2;}
    .af-dd-imgwrap img{-webkit-user-drag:none;user-select:none;}
    .af-dd-imgwrap img{width:100%;height:100%;object-fit:cover;display:block;}
    .af-dd-info{flex:1 1 300px;padding:26px 24px;}
    .af-dd-tag{display:inline-block;background:#faf2df;color:#a8872e;font-weight:800;font-size:12px;padding:5px 11px;border-radius:14px;letter-spacing:.03em;}
    .af-dd-info h3{font-size:19px;font-weight:800;color:#1a1a1a;margin:12px 0 12px;line-height:1.35;}
    .af-dd-feat{list-style:none;margin:0 0 16px;padding:0;display:flex;flex-direction:column;gap:8px;}
    .af-dd-feat li{font-size:13px;color:#555;padding-left:22px;position:relative;line-height:1.5;}
    .af-dd-feat li::before{content:'✓';position:absolute;left:0;color:#c9a84c;font-weight:800;}
    .af-dd-price{font-size:24px;font-weight:800;color:#1a1a1a;margin:0 0 16px;
      display:flex;align-items:baseline;gap:10px;flex-wrap:wrap;}
    .af-dd-price del{color:var(--af-was, #6b6b6b);font-weight:400;font-size:.8em;text-decoration:line-through;}
    .af-dd-price .af-pct-off{color:var(--af-save, #2e7d32);font-weight:700;font-size:.62em;}
    .af-dd-actions{display:flex;gap:10px;flex-wrap:wrap;}
    .af-dd-btn{flex:1;min-width:130px;text-align:center;padding:12px 14px;border-radius:9px;font-weight:800;font-size:13.5px;cursor:pointer;text-decoration:none;border:none;}
    .af-dd-btn.solid{background:#c9a84c;color:#fff;}
    .af-dd-btn.solid:hover{background:#a8872e;}
    .af-dd-btn.ghost{background:#efe9db;color:#333;}
    .af-dd-msg{font-size:13px;margin:12px 0 0;min-height:16px;}
    @media(max-width:560px){ .af-dd-imgwrap{flex-basis:100%;min-height:200px;} }
    </style>
    <?php
}, 45);

// ─────────────────────────────────────────────────────────────
// PHASE 16b — Digital download is delivered ONLY after payment, and
// ONLY for lines bought as digital. WooCommerce natively grants the
// download on payment; here we revoke it for non-digital (physical)
// lines so only paying digital customers can download.
// ─────────────────────────────────────────────────────────────
add_action('woocommerce_grant_product_download_permissions', function($order_id){
    $order = wc_get_order($order_id);
    if (!$order) return;

    // Which products in this order were purchased AS digital?
    $digital_pids = array();
    foreach ($order->get_items() as $item) {
        $is_digital = ($item->get_meta('Format') === 'Digital Download') || $item->get_meta('af_digital');
        if ($is_digital) $digital_pids[$item->get_product_id()] = true;
    }

    // Revoke download permissions for products NOT bought as digital
    if (!class_exists('WC_Data_Store')) return;
    try {
        $store = WC_Data_Store::load('customer-download');
        $downloads = $store->get_downloads(array('order_id' => $order_id));
        foreach ($downloads as $d) {
            if (empty($digital_pids[$d->get_product_id()])) {
                $store->delete_by_id($d->get_id());
            }
        }
    } catch (Exception $e) { /* no-op */ }
}, 20);

// ─────────────────────────────────────────────────────────────
// PHASE 17 — Keep the primary nav menu on ONE line (desktop only).
// ─────────────────────────────────────────────────────────────
add_action('wp_head', function() {
    ?>
    <style id="af-nav-oneline">
    @media (min-width: 1025px){
      /* Force the header menu row to not wrap */
      .elementor-nav-menu,
      ul.elementor-nav-menu,
      .elementor-nav-menu--main > .elementor-nav-menu__container > ul,
      .elementor-nav-menu--main ul.elementor-nav-menu,
      nav .elementor-nav-menu,
      .main-navigation > ul,
      #site-navigation > ul,
      .site-header .menu,
      .postero-nav ul.menu {
        display: flex !important;
        flex-wrap: nowrap !important;
        align-items: center !important;
        white-space: nowrap !important;
        row-gap: 0 !important;
      }
      /* Each item stays on one line */
      .elementor-nav-menu > li,
      .elementor-nav-menu li.menu-item,
      .main-navigation li,
      .site-header .menu > li {
        white-space: nowrap !important;
        flex: 0 0 auto !important;
      }
      /* Tighten spacing + font so all items fit one row */
      .elementor-nav-menu .elementor-item,
      .elementor-nav-menu > li > a,
      .main-navigation a,
      .site-header .menu > li > a {
        padding-left: 11px !important;
        padding-right: 11px !important;
        font-size: 14px !important;
        white-space: nowrap !important;
      }
    }
    @media (min-width: 1025px) and (max-width: 1200px){
      /* Slightly smaller on narrower desktops to still fit one line */
      .elementor-nav-menu .elementor-item,
      .elementor-nav-menu > li > a,
      .main-navigation a { font-size: 13px !important; padding-left: 8px !important; padding-right: 8px !important; }
    }
    </style>
    <?php
}, 100);

// ─────────────────────────────────────────────────────────────
// PHASE 17b — Force the primary nav onto one line via JS (finds the
// menu by its content, so it works regardless of theme class names).
// ─────────────────────────────────────────────────────────────
add_action('wp_footer', function() {
    ?>
    <script>
    (function(){
      function fixNav(){
        if (window.innerWidth < 1025) return; // desktop only
        var target = null;
        document.querySelectorAll('ul').forEach(function(ul){
          if (target) return;
          var t = (ul.textContent || '').toLowerCase();
          // top-level header menu contains these items
          if (t.indexOf('try it on your wall') > -1 && t.indexOf('categories') > -1 &&
              t.indexOf('contact us') > -1 && ul.children.length >= 5) {
            // prefer the shallowest matching UL (the main menu, not a wrapper)
            target = ul;
          }
        });
        if (!target) return;
        target.style.setProperty('display','flex','important');
        target.style.setProperty('flex-wrap','nowrap','important');
        target.style.setProperty('align-items','center','important');
        target.style.setProperty('white-space','nowrap','important');
        target.style.setProperty('width','100%','important');
        Array.from(target.children).forEach(function(li){
          li.style.setProperty('flex','0 0 auto','important');
          li.style.setProperty('white-space','nowrap','important');
          var a = li.querySelector('a');
          if (a){
            a.style.setProperty('white-space','nowrap','important');
            a.style.setProperty('padding-left','9px','important');
            a.style.setProperty('padding-right','9px','important');
            a.style.setProperty('font-size','13.5px','important');
          }
        });
      }
      if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fixNav); else fixNav();
      window.addEventListener('load', function(){ fixNav(); setTimeout(fixNav,400); setTimeout(fixNav,1200); });
      window.addEventListener('resize', fixNav);
    })();
    </script>
    <?php
}, 101);

// ─────────────────────────────────────────────────────────────
// PHASE 18 — Styling for the custom About Us page (loaded from theme
// so it isn't stripped like inline <style> in page content).
// ─────────────────────────────────────────────────────────────
add_action('wp_head', function() {
    return; // DISABLED: original Elementor About page restored; theme design used.
    if (!function_exists('is_page') || !is_page(array('about','about-us'))) return;
    ?>
    <style id="af-about-css">
    .taf-about{max-width:1140px;margin:0 auto;padding:10px 18px 50px;color:#2a2a2a;font-family:inherit;}
    .taf-about h1,.taf-about h2,.taf-about h3{color:#1a1a1a;}
    /* Hero */
    .taf-hero{position:relative;text-align:center;border-radius:22px;padding:64px 28px 58px;margin:8px 0 26px;overflow:hidden;
      background:radial-gradient(1200px 400px at 50% -10%, #3a3320 0%, #191712 60%, #141210 100%);color:#f3eede;}
    .taf-hero::after{content:"";position:absolute;inset:0;background:
      repeating-linear-gradient(135deg, rgba(201,168,76,.06) 0 2px, transparent 2px 22px);pointer-events:none;}
    .taf-eyebrow{position:relative;display:inline-block;background:rgba(201,168,76,.16);color:#e8c766;border:1px solid rgba(201,168,76,.4);
      font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;padding:7px 15px;border-radius:20px;margin-bottom:18px;}
    .taf-hero h1{position:relative;font-size:52px;line-height:1.08;font-weight:800;color:#fff;margin:0 0 16px;letter-spacing:-.01em;}
    .taf-hero h1 span{color:#c9a84c;}
    .taf-hero p{position:relative;max-width:720px;margin:0 auto;font-size:16px;line-height:1.8;color:#d8d1bf;}
    .taf-hero-badges{position:relative;display:flex;flex-wrap:wrap;gap:10px;justify-content:center;margin-top:24px;}
    .taf-hero-badges span{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);color:#f0ead9;
      font-size:13px;font-weight:600;padding:9px 15px;border-radius:30px;backdrop-filter:blur(4px);}
    /* Stats */
    .taf-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin:0 0 40px;}
    .taf-stat{background:#faf7ef;border:1px solid #ece4cf;border-radius:14px;padding:22px 14px;text-align:center;}
    .taf-stat strong{display:block;font-size:30px;font-weight:800;color:#c9a84c;line-height:1;}
    .taf-stat span{display:block;margin-top:7px;font-size:12.5px;font-weight:700;letter-spacing:.03em;text-transform:uppercase;color:#6b6250;}
    /* Blocks */
    .taf-block{margin:0 0 44px;}
    .taf-block-head{margin:0 0 18px;}
    .taf-block-head h2{font-size:30px;font-weight:800;margin:0 0 10px;}
    .taf-rule{width:64px;height:4px;border-radius:3px;background:linear-gradient(90deg,#c9a84c,#e8c766);}
    .taf-story p, .taf-block > p{font-size:15.5px;line-height:1.85;color:#444;max-width:900px;}
    /* Offer cards */
    .taf-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;}
    .taf-card{background:#fff;border:1px solid #ececec;border-radius:16px;padding:24px 20px;transition:box-shadow .25s,transform .25s,border-color .25s;}
    .taf-card:hover{box-shadow:0 14px 34px rgba(0,0,0,.10);transform:translateY(-4px);border-color:#e6d9b5;}
    .taf-ico{width:52px;height:52px;border-radius:13px;background:#faf2df;display:flex;align-items:center;justify-content:center;font-size:26px;margin-bottom:14px;}
    .taf-card h3{font-size:17px;font-weight:800;margin:0 0 8px;}
    .taf-card p{font-size:13.5px;line-height:1.65;color:#5a5a5a;margin:0;}
    /* Why-choose list */
    .taf-list{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:1fr 1fr;gap:14px 30px;}
    .taf-list li{position:relative;padding:14px 16px 14px 46px;background:#faf7ef;border:1px solid #efe8d6;border-radius:12px;font-size:14.5px;line-height:1.55;color:#444;}
    .taf-list li strong{color:#1a1a1a;}
    .taf-list li::before{content:"✓";position:absolute;left:16px;top:14px;width:20px;height:20px;background:#c9a84c;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;}
    /* Mission */
    .taf-mission{position:relative;text-align:center;border-radius:20px;padding:46px 28px;margin:0 0 40px;overflow:hidden;
      background:radial-gradient(900px 300px at 50% 0%, #2a2416 0%, #17150f 70%);}
    .taf-mission h2{color:#fff;font-size:28px;margin:0 0 12px;}
    .taf-mission p{color:#e8e2cf;font-size:18px;line-height:1.7;max-width:740px;margin:0 auto;font-style:italic;}
    /* CTA */
    .taf-cta{text-align:center;background:#faf7ef;border:1px solid #ece4cf;border-radius:20px;padding:40px 26px;}
    .taf-cta h2{font-size:26px;margin:0 0 20px;}
    .taf-cta-btns{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;}
    .taf-btn-gold,.taf-btn-dark{display:inline-block;padding:14px 30px;border-radius:10px;font-weight:800;font-size:14.5px;text-decoration:none;transition:background .2s,transform .2s;}
    .taf-btn-gold{background:#c9a84c;color:#fff;}
    .taf-btn-gold:hover{background:#a8872e;transform:translateY(-1px);}
    .taf-btn-dark{background:#1a1a1a;color:#fff;}
    .taf-btn-dark:hover{background:#333;transform:translateY(-1px);}
    .taf-contact{margin-top:22px;font-size:13.5px;color:#666;}
    .taf-contact a{color:#a8872e;text-decoration:none;}
    @media(max-width:900px){
      .taf-hero h1{font-size:38px;}
      .taf-grid{grid-template-columns:1fr 1fr;}
      .taf-stats{grid-template-columns:repeat(2,1fr);}
      .taf-list{grid-template-columns:1fr;}
    }
    @media(max-width:540px){
      .taf-hero{padding:44px 20px;}
      .taf-hero h1{font-size:30px;}
      .taf-grid{grid-template-columns:1fr;}
    }
    </style>
    <?php
}, 100);

// ─────────────────────────────────────────────────────────────
// PHASE 19 — Fix header email/phone links (they pointed to About).
// Email -> mailto:, Phone -> tel:. Class-agnostic (finds by text).
// ─────────────────────────────────────────────────────────────
add_action('wp_footer', function() {
    ?>
    <script>
    (function(){
      var EMAIL = 'theartframer136@gmail.com';
      var TEL   = '+16104707280';
      function fix(){
        document.querySelectorAll('a').forEach(function(a){
          var t = (a.textContent || '').replace(/\s+/g,' ').trim();
          if (!t) return;
          if (t.toLowerCase().indexOf(EMAIL) > -1) {
            if (a.getAttribute('href') !== 'mailto:' + EMAIL) a.setAttribute('href', 'mailto:' + EMAIL);
            a.removeAttribute('target');
          } else if (/470[\s\-]?7280/.test(t)) {
            if (a.getAttribute('href') !== 'tel:' + TEL) a.setAttribute('href', 'tel:' + TEL);
            a.removeAttribute('target');
          }
        });
      }
      if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fix); else fix();
      window.addEventListener('load', function(){ fix(); setTimeout(fix,500); setTimeout(fix,1500); });
    })();
    </script>
    <?php
}, 102);

// ─────────────────────────────────────────────────────────────
// PHASE 20 — Fixes for issues reported in the site review PDF.
// All additive & scoped; existing section styles/logic untouched.
// ─────────────────────────────────────────────────────────────

// 20a. Related products: drop any product with no real featured image so the
//      blank "Posters" card (and similar) never appears in the row.
add_filter('woocommerce_related_products', function($related) {
    if (empty($related) || !is_array($related)) return $related;
    $out = array();
    foreach ($related as $pid) {
        if (!has_post_thumbnail($pid)) continue;            // no image set at all
        $tid = get_post_thumbnail_id($pid);
        $file = $tid ? get_attached_file($tid) : '';
        if ($file && !@file_exists($file)) continue;        // image record exists but file missing
        $out[] = $pid;
    }
    return $out ?: $related; // never return empty (WooCommerce would query fresh)
}, 20);

// 20b. Cart page: auto-recalculate totals when the quantity changes (theme
//      +/- buttons and manual edits), so the "Cart totals" box stays in sync
//      without the shopper having to click "Update cart".
add_action('wp_footer', function() {
    if (!function_exists('is_cart') || !is_cart()) return;
    ?>
    <script>
    (function(){
      if (typeof jQuery === 'undefined') return;
      var $ = jQuery, timer = null;
      function triggerUpdate(){
        var $btn = $('.woocommerce-cart-form [name="update_cart"], .woocommerce [name="update_cart"]');
        if (!$btn.length) return;
        $btn.prop('disabled', false).trigger('click');
      }
      function schedule(){
        clearTimeout(timer);
        timer = setTimeout(triggerUpdate, 700); // debounce rapid +/- clicks
      }
      // Manual edits / native change
      $(document.body).on('change', '.woocommerce-cart-form input.qty', schedule);
      // Theme +/- stepper buttons change the value programmatically — poll after click
      $(document.body).on('click', '.woocommerce-cart-form .quantity button, .woocommerce-cart-form .quantity .plus, .woocommerce-cart-form .quantity .minus, .woocommerce-cart-form .quantity a', function(){
        schedule();
      });
    })();
    </script>
    <?php
}, 103);

// 20c. (Removed) Cart page CSS overrides — reverted to the theme's native cart
//      styling per request. Only the quantity auto-update behaviour (20b) and
//      the theme's own layout remain.

// 20d. Front page: make banner/landing carousel arrows work and give the
//      "Explore Now" popup button a destination if its link is empty.
add_action('wp_footer', function() {
    if (!function_exists('is_front_page') || !is_front_page()) return;
    ?>
    <script>
    (function(){
      function findSwiper(arrow){
        var node = arrow;
        for (var i=0; i<8 && node; i++){ if (node.swiper) return node.swiper; node = node.parentElement; }
        var cont = arrow.closest('.elementor-widget-slides, .elementor-widget-media-carousel, .swiper, .swiper-container, .elementor-main-swiper');
        if (cont){
          var sw = cont.querySelector('.swiper, .swiper-container');
          if (sw && sw.swiper) return sw.swiper;
          if (cont.swiper) return cont.swiper;
        }
        return null;
      }
      function bindArrows(){
        document.querySelectorAll('.elementor-swiper-button-next, .swiper-button-next, .elementor-swiper-button-prev, .swiper-button-prev').forEach(function(a){
          if (a.getAttribute('data-af-bound')) return;
          a.setAttribute('data-af-bound','1');
          a.addEventListener('click', function(){
            var sw = findSwiper(a);
            if (!sw) return;
            if (a.className.indexOf('prev') > -1) sw.slidePrev(); else sw.slideNext();
          });
        });
      }
      function fixExploreBtn(){
        var links = document.querySelectorAll('a.elementor-button, .elementor-popup-modal a, a');
        links.forEach(function(a){
          var t = (a.textContent||'').replace(/\s+/g,' ').trim().toLowerCase();
          if (t !== 'explore now') return;
          var href = a.getAttribute('href');
          if (!href || href === '#' || href === '') a.setAttribute('href', '/shop/');
        });
      }
      function run(){ bindArrows(); fixExploreBtn(); }
      if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run); else run();
      window.addEventListener('load', function(){ run(); setTimeout(run, 800); setTimeout(run, 2000); });
    })();
    </script>
    <?php
}, 104);

// ─────────────────────────────────────────────────────────────
// PHASE 21 — Homepage product-card hover swap fix.
// The theme swaps to the product's gallery image on hover; on the
// homepage grids the second image rendered in normal flow (sliver
// below the card). Overlay it on the first image with a fade instead.
// Scoped to the front page only.
// ─────────────────────────────────────────────────────────────
add_action('wp_head', function() {
    if (!is_front_page()) return;
    ?>
    <style>
    body.home ul.products li.product .woocommerce-loop-product__link,
    body.af-front-page ul.products li.product .woocommerce-loop-product__link{
      position:relative;display:block;overflow:hidden;
    }
    body.home ul.products li.product .woocommerce-loop-product__link > img,
    body.af-front-page ul.products li.product .woocommerce-loop-product__link > img{
      width:100%;display:block;
    }
    body.home ul.products li.product .woocommerce-loop-product__link > img + img,
    body.home ul.products li.product .woocommerce-loop-product__link .secondary-image,
    body.af-front-page ul.products li.product .woocommerce-loop-product__link > img + img,
    body.af-front-page ul.products li.product .woocommerce-loop-product__link .secondary-image{
      position:absolute;top:0;left:0;width:100%;height:100%;
      object-fit:cover;opacity:0;transition:opacity .35s ease;z-index:2;
    }
    body.home ul.products li.product:hover .woocommerce-loop-product__link > img + img,
    body.home ul.products li.product:hover .woocommerce-loop-product__link .secondary-image,
    body.af-front-page ul.products li.product:hover .woocommerce-loop-product__link > img + img,
    body.af-front-page ul.products li.product:hover .woocommerce-loop-product__link .secondary-image{
      opacity:1;
    }
    </style>
    <?php
}, 30);

// ─────────────────────────────────────────────────────────────
// PHASE 22 — Quick View modal: keep it COMPACT (buy box only).
// The modal renders the entire product page, but the theme's card
// CSS doesn't load inside it, so every post-summary card section
// (Additional info, Reviews, Related Searches, Related products,
// Popular, Recently Viewed, Digital Downloads, Customize, FAQ)
// renders broken (grey gaps / missing images). A Quick View should
// be short anyway — hide those sections in the modal; they remain on
// the full product page (one click via the title / "View details").
// STRICTLY scoped to the modal containers — no effect elsewhere.
// ─────────────────────────────────────────────────────────────
add_action('wp_head', function() {
    ?>
    <style>
    .quick-view-modal .woocommerce-tabs,
    .quick-view-modal .woocommerce-Tabs-panel,
    .quick-view-modal .related.products,
    .quick-view-modal .up-sells,
    .quick-view-modal .woocommerce-product-attributes,
    .quick-view-modal .af-pp-sec,
    .quick-view-modal .cross-sells,
    .quick-view-wrapper .woocommerce-tabs,
    .quick-view-wrapper .related.products,
    .quick-view-wrapper .up-sells,
    .quick-view-wrapper .af-pp-sec,
    .quick-view-wrapper .cross-sells,
    #quickViewContent .woocommerce-tabs,
    #quickViewContent .related.products,
    #quickViewContent .up-sells,
    #quickViewContent .woocommerce-product-attributes,
    #quickViewContent .af-pp-sec,
    #quickViewContent .cross-sells{
      display:none !important;
    }
    </style>
    <?php
}, 31);

// ─────────────────────────────────────────────────────────────
// PHASE 23 — "Price on request" for products with no price set.
// The frame / accessory products (Art Accessories category) have no
// price, so they displayed as $0.00. Until real prices are entered
// in wp-admin, show "Price on request" and make them non-purchasable
// (no Add to Cart / Buy Now at $0). Fully automatic & reversible:
// the moment a real price is saved, the product behaves normally.
// ─────────────────────────────────────────────────────────────

// A zero / empty price → not purchasable (removes Add to Cart + Buy Now)
add_filter('woocommerce_is_purchasable', function($purchasable, $product){
    if (!$product) return $purchasable;
    $p = $product->get_price();
    if ($p === '' || $p === null || (float)$p <= 0) return false;
    return $purchasable;
}, 20, 2);

// Show "Price on request" instead of $0.00 anywhere the price renders
add_filter('woocommerce_get_price_html', function($html, $product){
    if (!$product) return $html;
    $p = $product->get_price();
    if ($p === '' || $p === null || (float)$p <= 0) {
        return '<span class="af-por">Price on request</span>';
    }
    return $html;
}, 20, 2);

// Loop cards: swap the (now absent) Add to Cart for an "Enquire" link
add_filter('woocommerce_loop_add_to_cart_link', function($html, $product){
    if (!$product) return $html;
    $p = $product->get_price();
    if ($p === '' || $p === null || (float)$p <= 0) {
        return '<a href="'.esc_url(home_url('/contact/')).'" class="button af-por-btn">Enquire</a>';
    }
    return $html;
}, 20, 2);

// Single product page: add an "Enquire for price" button where Add to Cart would be
add_action('woocommerce_single_product_summary', function(){
    $product = af_wc_product();
    if (!$product) return;
    $p = $product->get_price();
    if ($p === '' || $p === null || (float)$p <= 0) {
        echo '<a href="'.esc_url(home_url('/contact/')).'" class="button af-por-btn af-por-single">Enquire for Price</a>';
    }
}, 31);

// The cart never said anything back.
//
// Measured on the live cart, 21 Sep 2026: applied a coupon that does not
// exist, waited, and counted the notices rendered on the page. Zero. The
// server answers properly — WooCommerce returns "Coupon does not exist" —
// and nothing on the page is listening, because the cart template in use
// never calls woocommerce_output_all_notices().
//
// So a shopper with a code that has expired, or a typo in a good one, clicks
// APPLY COUPON and watches nothing happen. Nothing happening is how a broken
// site behaves, so that is what they conclude.
//
// Hooked rather than templated on purpose. Copying the parent's cart.php into
// the child theme would freeze a WooCommerce template at today's version and
// quietly inherit every upstream change it later misses — a large, permanent
// cost for one missing call.
//
// The first attempt hooked woocommerce_before_cart alone, and a run after it
// deployed measured the same zero notices. That hook does not fire here, so
// the parent's cart page is not WooCommerce's cart.php — most likely an
// Elementor widget rendering the cart.
//
// What actually has to reach the page is the wrapper. Applying a coupon is an
// AJAX round trip, and WooCommerce's own script writes the response's notices
// into .woocommerce-notices-wrapper — which is the markup
// woocommerce_output_all_notices() emits. No wrapper, nowhere to write, and
// the notice is discarded after the server has correctly produced it. That is
// why nothing appeared despite the response being right.
//
// So several hooks are tried rather than one, printing at most once and only
// on the cart. A hook that never fires costs nothing; the first that does
// wins. Each prints a marker naming itself, so the next verification run says
// WHICH hook carried it — or, if the wrapper is still missing, confirms that
// none of them fires and the template override is the only route left. Either
// way the answer is measured instead of guessed at twice.
add_action('init', function () {
    if (!function_exists('woocommerce_output_all_notices')) return;
    $printed = false;
    $print = function () use (&$printed) {
        if ($printed || !function_exists('is_cart') || !is_cart()) return;
        $printed = true;
        echo '<!-- af-notices via ' . esc_html(current_filter()) . ' -->';
        woocommerce_output_all_notices();
    };
    foreach (array(
        'woocommerce_before_cart',
        'woocommerce_before_cart_table',
        'woocommerce_before_cart_contents',
        'woocommerce_cart_is_empty',
        'woocommerce_before_main_content',
    ) as $af_hook) {
        add_action($af_hook, $print, 5);
    }
});

// A shopper who sorts by price is telling you price is what they care about.
//
// Measured on the live shop, 21 Sep 2026: page 1 of /shop/?orderby=price was
// 12 of 12 "Price on request". Nothing above has a price, so ascending order
// puts every one of them first — the shopper most focused on price sees the
// least of it, and the items they are shown cannot be bought at all because
// woocommerce_is_purchasable above refuses them.
//
// They were excluded from the price sort here. They are now sorted LAST in it
// instead, by inc/price-sort.php: leaving them out emptied the categories
// that are mostly these products (Art Accessories) whenever a visitor sorted
// them by price, and changed the number of results with the sort (M-02).

// Minimal styling for the label + enquire buttons
add_action('wp_head', function(){
    ?>
    <style>
    .af-por{font-size:15px;font-weight:700;color:var(--af-gold-text, #8a6d1f);}
    .af-por-btn{display:inline-block;}
    .af-por-single{background:#1a1a1a !important;color:#fff !important;padding:12px 22px !important;border-radius:8px !important;margin:6px 0 0 !important;}
    .af-por-single:hover{background:#c9a84c !important;}
    </style>
    <?php
}, 32);

/* ============================================================
   PHASE 12 — Spec completions (added 2026-07-15)
   12b. Clearance countdown timer (spec §11)
   12c. Newsletter subscribe endpoint (kept for future form use)
   (12a Shop Through Videos and 12d footer strip removed 2026-07-15 —
    Products In Motion already covers video commerce, and footer links
    now live in the Elementor footer itself via tools/fix-footer-links.php)
   All blocks are additive: existing sections are never modified.
   ============================================================ */

// ── 12b. Clearance countdown timer ───────────────────────────
// Attaches a live countdown under the "Stock Clearance Sale" heading.
// End time: option 'af_clearance_end' ("YYYY-MM-DD HH:MM", site timezone) if
// set and in the future; otherwise rolls to next Sunday 23:59:59 each week.
add_action('wp_footer', function() {
    if (!is_front_page()) return;

    $tz  = wp_timezone();
    $now = new DateTime('now', $tz);
    $end = null;
    $opt = trim((string) get_option('af_clearance_end', ''));
    if ($opt !== '') {
        $try = date_create($opt, $tz);
        if ($try && $try > $now) $end = $try;
    }
    if (!$end) {
        $end = new DateTime('now', $tz);
        $end->modify('sunday this week')->setTime(23, 59, 59);
        if ($end <= $now) $end->modify('+7 days');
    }
    $end_ms = $end->getTimestamp() * 1000;
    ?>
<style>
.af-cd-bar{display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin:10px 0 6px;}
.af-cd-label{font-size:14px;font-weight:700;color:#b4342a;letter-spacing:.02em;}
.af-cd-units{display:flex;gap:8px;}
.af-cd-unit{min-width:56px;background:#1a1a1a;color:#fff;border-radius:10px;padding:7px 8px 6px;text-align:center;border:2px solid #c9a84c;}
.af-cd-num{display:block;font-size:20px;font-weight:800;font-variant-numeric:tabular-nums;line-height:1.1;}
.af-cd-word{display:block;font-size:9.5px;text-transform:uppercase;letter-spacing:.09em;color:#c9a84c;margin-top:2px;}
.af-cd-note{font-size:12.5px;color:#8a6d1f;font-weight:600;}
@media(max-width:480px){.af-cd-unit{min-width:48px;padding:6px 6px 5px;}.af-cd-num{font-size:17px;}}
</style>
<div class="af-cd-bar" id="afCdBar" style="display:none;" data-end="<?php echo esc_attr($end_ms); ?>">
  <span class="af-cd-label">&#9889; Offers end in</span>
  <span class="af-cd-units">
    <span class="af-cd-unit"><span class="af-cd-num" data-u="d">0</span><span class="af-cd-word">Days</span></span>
    <span class="af-cd-unit"><span class="af-cd-num" data-u="h">0</span><span class="af-cd-word">Hours</span></span>
    <span class="af-cd-unit"><span class="af-cd-num" data-u="m">0</span><span class="af-cd-word">Mins</span></span>
    <span class="af-cd-unit"><span class="af-cd-num" data-u="s">0</span><span class="af-cd-word">Secs</span></span>
  </span>
  <span class="af-cd-note">Limited stock &mdash; while supplies last</span>
</div>
<script>
(function(){
  var bar = document.getElementById('afCdBar');
  if (!bar) return;
  var end = parseInt(bar.dataset.end, 10);

  function place(){
    if (bar.dataset.placed) return;
    var heading = null;
    document.querySelectorAll('h1,h2,h3,h4,.elementor-heading-title').forEach(function(h){
      if (!heading && /stock\s*clearance/i.test(h.textContent)) heading = h;
    });
    if (!heading) return;
    bar.dataset.placed = '1';
    var w = heading.closest('.elementor-widget') || heading;
    w.parentElement.insertBefore(bar, w.nextSibling);
    bar.style.display = 'flex';
  }

  function pad(n){ return n < 10 ? '0' + n : '' + n; }
  function tick(){
    var left = end - Date.now();
    if (left <= 0) { end += 7 * 86400000; left = end - Date.now(); } // roll weekly
    var d = Math.floor(left / 86400000),
        h = Math.floor(left % 86400000 / 3600000),
        m = Math.floor(left % 3600000 / 60000),
        s = Math.floor(left % 60000 / 1000);
    bar.querySelector('[data-u="d"]').textContent = d;
    bar.querySelector('[data-u="h"]').textContent = pad(h);
    bar.querySelector('[data-u="m"]').textContent = pad(m);
    bar.querySelector('[data-u="s"]').textContent = pad(s);
  }
  setInterval(tick, 1000); tick();
  document.addEventListener('DOMContentLoaded', place);
  window.addEventListener('load', place);
  setTimeout(place, 600); setTimeout(place, 1600);
})();
</script>
<?php }, 10004);

// ── 12c. Newsletter subscribe endpoint ───────────────────────
// Stores subscribers in the 'af_newsletter_subscribers' option and notifies
// the admin. Swap for Klaviyo/Mailchimp later without touching the form.
function af_nl_subscribe_handler() {
    check_ajax_referer('af_nl_subscribe', 'nonce');
    // Rate limit: 10 attempts/hour per IP — each new subscriber triggers an
    // admin wp_mail and grows the af_newsletter_subscribers option.
    $af_nl_ip = af_visitor_ip();
    $af_nl_rl = 'af_nl_rl_' . md5($af_nl_ip);
    $af_nl_n  = (int) get_transient($af_nl_rl);
    if ($af_nl_n >= 10) {
        wp_send_json_error(array('message' => 'Too many attempts — please try again later.'));
    }
    set_transient($af_nl_rl, $af_nl_n + 1, HOUR_IN_SECONDS);
    if (!empty($_POST['af_nl_hp'])) { // honeypot
        wp_send_json_success(array('message' => 'Thanks — you are subscribed!'));
    }
    if (function_exists('af_turnstile_require_ajax')) af_turnstile_require_ajax('newsletter'); // Cloudflare's check, when switched on
    $email = isset($_POST['af_nl_email']) ? sanitize_email(wp_unslash($_POST['af_nl_email'])) : '';
    if (!$email || !is_email($email)) {
        wp_send_json_error(array('message' => 'Please enter a valid email address.'));
    }
    $subs = get_option('af_newsletter_subscribers', array());
    if (!is_array($subs)) $subs = array();
    foreach ($subs as $s) {
        if (isset($s['email']) && strtolower($s['email']) === strtolower($email)) {
            wp_send_json_success(array('message' => 'You are already on the list — thank you!'));
        }
    }
    $subs[] = array('email' => $email, 'time' => current_time('mysql'), 'ip' => af_visitor_ip());
    if (count($subs) > 5000) $subs = array_slice($subs, -5000);
    update_option('af_newsletter_subscribers', $subs, false);
    wp_mail(get_option('admin_email'), '[The Art Framer] New newsletter subscriber', "New subscriber: {$email}\nTotal subscribers: " . count($subs));
    wp_send_json_success(array('message' => 'Thanks — you are subscribed!'));
}
add_action('wp_ajax_af_nl_subscribe',        'af_nl_subscribe_handler');
add_action('wp_ajax_nopriv_af_nl_subscribe', 'af_nl_subscribe_handler');

// ── 12e. [af_faqs] shortcode — renders the SAME FAQ list used on
// product detail pages (af_product_faqs), so /faqs/ never drifts.
add_shortcode('af_faqs', function() {
    if (!function_exists('af_product_faqs')) return '';
    $faqs = af_product_faqs();
    $out  = '<div class="taf-faq">';
    $ld   = array();
    foreach ($faqs as $i => $qa) {
        $q = esc_html($qa[0]);
        $a = esc_html($qa[1]);
        $out .= '<details' . ($i === 0 ? ' open' : '') . '><summary>' . $q . '</summary><div class="taf-faq-a"><p>' . $a . '</p></div></details>';
        $ld[] = array(
            '@type' => 'Question', 'name' => $qa[0],
            'acceptedAnswer' => array('@type' => 'Answer', 'text' => $qa[1]),
        );
    }
    $out .= '</div>';
    $out .= '<script type="application/ld+json">' . wp_json_encode(array(
        '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $ld,
    )) . '</script>';
    return $out;
});

// Front-end submit handler for the footer form
add_action('wp_footer', function() { ?>
<script>
(function(){
  document.querySelectorAll('form.af-f-newsform[data-ajax]').forEach(function(form){
    form.addEventListener('submit', function(e){
      e.preventDefault();
      var btn = form.querySelector('button'),
          msg = form.parentElement.querySelector('.af-f-newsmsg'),
          fd  = new FormData(form);
      fd.append('action', 'af_nl_subscribe');
      fd.append('nonce', form.dataset.nonce);
      if (btn) { btn.disabled = true; btn.textContent = '…'; }
      fetch(form.dataset.ajax, { method: 'POST', credentials: 'same-origin', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(res){
          if (msg) {
            msg.style.display = 'block';
            msg.style.color = res.success ? '#7ec98f' : '#e08e88';
            msg.textContent = (res.data && res.data.message) ? res.data.message : 'Something went wrong — please try again.';
          }
          if (res.success) form.reset();
        })
        .catch(function(){
          if (msg) { msg.style.display='block'; msg.style.color='#e08e88'; msg.textContent='Network error — please try again.'; }
        })
        .finally(function(){
          if (btn) { btn.disabled = false; btn.textContent = 'Join'; }
          if (window.afTsApi) window.afTsApi.reset(form); // a security-check token is spent by one post
        });
    });
  });
})();
</script>
<?php }, 301);



// ── 12f. Contact form — custom table, AJAX submit, admin viewer ─
// [af_contact_form] renders the form; submissions are stored in the
// {prefix}af_contact_messages table and listed under wp-admin →
// "Contact Messages". Admin also gets an email notification.

function af_contact_table() {
    global $wpdb;
    return $wpdb->prefix . 'af_contact_messages';
}

// Create/upgrade the table once (guarded by a schema-version option)
add_action('init', function() {
    if (get_option('af_contact_db_ver') === '1') return;
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table   = af_contact_table();
    $charset = $wpdb->get_charset_collate();
    dbDelta("CREATE TABLE {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        created_at DATETIME NOT NULL,
        name VARCHAR(120) NOT NULL,
        email VARCHAR(190) NOT NULL,
        phone VARCHAR(40) DEFAULT '',
        subject VARCHAR(120) DEFAULT '',
        message TEXT NOT NULL,
        ip VARCHAR(45) DEFAULT '',
        status VARCHAR(10) DEFAULT 'new',
        PRIMARY KEY  (id),
        KEY created_at (created_at),
        KEY status (status)
    ) {$charset};");
    update_option('af_contact_db_ver', '1');
}, 5);

// Shortcode: the form
add_shortcode('af_contact_form', function() {
    $subjects = array('General Question', 'Custom Order / Personalised Print', 'Bulk & Corporate Order', 'Order Support / Tracking', 'Returns & Refunds', 'Artist / Partnership');
    ob_start(); ?>
<form class="taf-form" id="afContactForm"
      data-ajax="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
      data-nonce="<?php echo esc_attr(wp_create_nonce('af_contact_submit')); ?>">
  <div class="taf-form-row">
    <label>Full Name *<input type="text" name="af_name" required maxlength="120" autocomplete="name"></label>
    <label>Email *<input type="email" name="af_email" required maxlength="190" autocomplete="email"></label>
  </div>
  <div class="taf-form-row">
    <label>Phone (optional)<input type="tel" name="af_phone" maxlength="40" autocomplete="tel"></label>
    <label>Subject *
      <select name="af_subject" required>
        <?php foreach ($subjects as $s) echo '<option>' . esc_html($s) . '</option>'; ?>
      </select>
    </label>
  </div>
  <label>Your Message *<textarea name="af_message" rows="6" required maxlength="5000" placeholder="Tell us about your wall, your order, or your question…"></textarea></label>
  <input type="text" name="af_hp" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;">
  <?php if (function_exists('af_turnstile_box')) af_turnstile_box('contact'); // Cloudflare's "verify you are human" (inc/turnstile.php) ?>
  <button type="submit" class="taf-form-submit">Send Message</button>
  <p class="taf-form-msg" role="status" aria-live="polite" style="display:none;"></p>
</form>
<script>
(function(){
  var form = document.getElementById('afContactForm');
  if (!form) return;
  form.addEventListener('submit', function(e){
    e.preventDefault();
    var btn = form.querySelector('.taf-form-submit'),
        msg = form.querySelector('.taf-form-msg'),
        fd  = new FormData(form);
    fd.append('action', 'af_contact_submit');
    fd.append('nonce', form.dataset.nonce);
    btn.disabled = true; btn.textContent = 'Sending…';
    fetch(form.dataset.ajax, { method:'POST', credentials:'same-origin', body: fd })
      .then(function(r){ return r.json(); })
      .then(function(res){
        msg.style.display = 'block';
        msg.className = 'taf-form-msg ' + (res.success ? 'ok' : 'err');
        msg.textContent = (res.data && res.data.message) ? res.data.message : 'Something went wrong — please try again.';
        if (res.success) form.reset();
      })
      .catch(function(){
        msg.style.display = 'block';
        msg.className = 'taf-form-msg err';
        msg.textContent = 'Network error — please try again, or email us directly.';
      })
      .finally(function(){
        btn.disabled = false; btn.textContent = 'Send Message';
        if (window.afTsApi) window.afTsApi.reset(form); // a security-check token is spent by one post
      });
  });
})();
</script>
<?php return ob_get_clean();
});

// AJAX endpoint
function af_contact_submit_handler() {
    check_ajax_referer('af_contact_submit', 'nonce');
    if (!empty($_POST['af_hp'])) { // honeypot: pretend success
        wp_send_json_success(array('message' => 'Thank you! Your message has been sent.'));
    }
    if (function_exists('af_turnstile_require_ajax')) af_turnstile_require_ajax('contact'); // Cloudflare's check, when switched on
    $ip = af_visitor_ip();
    // Rate limit: 5 messages per hour per IP
    $rl_key = 'af_ct_rl_' . md5($ip);
    $count  = (int) get_transient($rl_key);
    if ($count >= 5) {
        wp_send_json_error(array('message' => 'Too many messages from this connection — please try again in an hour, or email us directly.'));
    }

    $name    = isset($_POST['af_name'])    ? sanitize_text_field(wp_unslash($_POST['af_name']))    : '';
    $email   = isset($_POST['af_email'])   ? sanitize_email(wp_unslash($_POST['af_email']))        : '';
    $phone   = isset($_POST['af_phone'])   ? sanitize_text_field(wp_unslash($_POST['af_phone']))   : '';
    $subject = isset($_POST['af_subject']) ? sanitize_text_field(wp_unslash($_POST['af_subject'])) : '';
    $message = isset($_POST['af_message']) ? sanitize_textarea_field(wp_unslash($_POST['af_message'])) : '';

    if (mb_strlen($name) < 2)          wp_send_json_error(array('message' => 'Please enter your name.'));
    if (!$email || !is_email($email))  wp_send_json_error(array('message' => 'Please enter a valid email address.'));
    if (mb_strlen($message) < 10)      wp_send_json_error(array('message' => 'Please write a few words about your request.'));

    global $wpdb;
    $ok = $wpdb->insert(af_contact_table(), array(
        'created_at' => current_time('mysql'),
        'name'       => mb_substr($name, 0, 120),
        'email'      => mb_substr($email, 0, 190),
        'phone'      => mb_substr($phone, 0, 40),
        'subject'    => mb_substr($subject, 0, 120),
        'message'    => mb_substr($message, 0, 5000),
        'ip'         => $ip,
        'status'     => 'new',
    ), array('%s','%s','%s','%s','%s','%s','%s','%s'));

    if (!$ok) {
        wp_send_json_error(array('message' => 'Could not save your message — please email us directly at theartframer136@gmail.com.'));
    }
    set_transient($rl_key, $count + 1, HOUR_IN_SECONDS);

    wp_mail(
        get_option('admin_email'),
        '[The Art Framer] New contact message: ' . $subject,
        "From: {$name} <{$email}>" . ($phone ? " / {$phone}" : '') . "\nSubject: {$subject}\n\n{$message}\n\n— Saved in wp-admin → Contact Messages",
        // The name goes into a mail header: no characters that could add an
        // address or a second header to it.
        array('Reply-To: ' . (trim(preg_replace('/[^\p{L}\p{N} .\'-]+/u', '', $name)) ?: 'Website visitor') . ' <' . $email . '>')
    );
    wp_send_json_success(array('message' => 'Thank you! Your message has been sent — we usually reply within 24 hours.'));
}
add_action('wp_ajax_af_contact_submit',        'af_contact_submit_handler');
add_action('wp_ajax_nopriv_af_contact_submit', 'af_contact_submit_handler');

// Admin viewer: wp-admin → Contact Messages
add_action('admin_menu', function() {
    global $wpdb;
    $table  = af_contact_table();
    $unread = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status='new'");
    $badge  = $unread ? " <span class='awaiting-mod'>{$unread}</span>" : '';
    $cap = current_user_can('af_view_contact_messages') ? 'af_view_contact_messages' : 'manage_options';
    add_menu_page('Contact Messages', 'Contact Messages' . $badge, $cap,
        'af-contact-messages', 'af_contact_admin_page', 'dashicons-email-alt2', 26);
});

function af_contact_admin_page() {
    if (!current_user_can('manage_options') && !current_user_can('af_view_contact_messages')) return;
    global $wpdb;
    $table = af_contact_table();

    // Row actions (mark read / delete), nonce-protected
    if (isset($_GET['af_action'], $_GET['id'], $_GET['_wpnonce'])) {
        $id = (int) $_GET['id'];
        if (wp_verify_nonce($_GET['_wpnonce'], 'af_ct_' . $id)) {
            if ($_GET['af_action'] === 'read')   $wpdb->update($table, array('status' => 'read'), array('id' => $id));
            if ($_GET['af_action'] === 'unread') $wpdb->update($table, array('status' => 'new'),  array('id' => $id));
            if ($_GET['af_action'] === 'delete') $wpdb->delete($table, array('id' => $id));
        }
        echo '<script>location.replace("' . esc_url_raw(admin_url('admin.php?page=af-contact-messages')) . '");</script>';
        return;
    }

    $per  = 20;
    $pg   = max(1, (int) ($_GET['paged'] ?? 1));
    $tot  = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $per, ($pg - 1) * $per
    ));
    echo '<div class="wrap"><h1>Contact Messages</h1>';
    echo '<p>' . esc_html($tot) . ' total. Submissions from the <a href="' . esc_url(home_url('/contact/')) . '" target="_blank">contact form</a> are stored here and emailed to ' . esc_html(get_option('admin_email')) . '.</p>';
    if (!$rows) { echo '<p><em>No messages yet.</em></p></div>'; return; }
    echo '<table class="widefat striped"><thead><tr><th>Date</th><th>Name</th><th>Email</th><th>Phone</th><th>Subject</th><th style="width:34%">Message</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        $n1 = wp_create_nonce('af_ct_' . $r->id);
        $base = admin_url('admin.php?page=af-contact-messages&id=' . $r->id . '&_wpnonce=' . $n1);
        $bold = $r->status === 'new' ? ' style="font-weight:700;"' : '';
        echo '<tr' . $bold . '>';
        echo '<td>' . esc_html(mysql2date('M j, Y g:i a', $r->created_at)) . '</td>';
        echo '<td>' . esc_html($r->name) . '</td>';
        echo '<td><a href="mailto:' . esc_attr($r->email) . '">' . esc_html($r->email) . '</a></td>';
        echo '<td>' . esc_html($r->phone) . '</td>';
        echo '<td>' . esc_html($r->subject) . '</td>';
        echo '<td>' . esc_html(mb_strimwidth($r->message, 0, 220, '…')) . '</td>';
        echo '<td>' . esc_html($r->status) . '</td>';
        echo '<td>';
        echo $r->status === 'new'
            ? '<a href="' . esc_url($base . '&af_action=read') . '">Mark read</a>'
            : '<a href="' . esc_url($base . '&af_action=unread') . '">Mark unread</a>';
        echo ' | <a href="' . esc_url($base . '&af_action=delete') . '" onclick="return confirm(\'Delete this message?\');" style="color:#b32d2e;">Delete</a>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:8px;display:flex;flex-direction:column;gap:5px;min-width:200px;">';
        echo '<input type="hidden" name="action" value="af_msg_reply">';
        echo '<input type="hidden" name="parent_id" value="' . (int) $r->id . '">';
        wp_nonce_field('af_msg_reply');
        echo '<textarea name="reply" rows="2" placeholder="Reply (emails the customer)"></textarea>';
        echo '<button class="button button-primary">Send reply</button>';
        echo '</form>';
        echo '</td></tr>';
    }
    echo '</tbody></table>';
    $pages = (int) ceil($tot / $per);
    if ($pages > 1) {
        echo '<p>';
        for ($i = 1; $i <= $pages; $i++) {
            echo $i === $pg ? "<strong style='margin-right:8px;'>{$i}</strong>"
                : '<a style="margin-right:8px;" href="' . esc_url(admin_url('admin.php?page=af-contact-messages&paged=' . $i)) . '">' . $i . '</a>';
        }
        echo '</p>';
    }
    echo '</div>';
}

// ── 12g. [af_delivery_checker] — ZIP-based delivery estimate ──
// Interactive widget for the Shipping & Delivery page: tells the visitor
// whether their ZIP is in the fast-delivery area and computes an estimated arrival
// window (production 3–5 business days + delivery 5–10 business days).
add_shortcode('af_delivery_checker', function() {
    ob_start(); ?>
<div class="taf-shipcheck" id="afShipCheck">
  <div class="taf-shipcheck-head">
    <span class="taf-ico">📮</span>
    <div>
      <h3>Check Your Delivery</h3>
      <p>Enter your ZIP code — see your shipping cost and estimated arrival dates instantly.</p>
    </div>
  </div>
  <div class="taf-shipcheck-row">
    <input type="text" id="afZip" inputmode="numeric" maxlength="10" placeholder="e.g. 19801" aria-label="ZIP code">
    <button type="button" id="afZipBtn">Check Delivery</button>
  </div>
  <div class="taf-shipcheck-result" id="afZipResult" role="status" aria-live="polite" style="display:none;"></div>
</div>
<script>
(function(){
  // The one shipping claim, from af_shipping_copy(), so this checker says the
  // same thing as the trust badge, the ticker, the FAQ and the chatbot.
  var AF_SHIP = <?php echo wp_json_encode(af_shipping_copy()); ?>;
  var input = document.getElementById('afZip'),
      btn   = document.getElementById('afZipBtn'),
      out   = document.getElementById('afZipResult');
  if (!input) return;

  // Nearby states by ZIP3 prefix: DE 197-199, PA 150-196, MD 206-219, NJ 070-089
  function zone(z3){
    if (z3 >= 197 && z3 <= 199) return 'Delaware';
    if (z3 >= 150 && z3 <= 196) return 'Pennsylvania';
    if (z3 >= 206 && z3 <= 219) return 'Maryland';
    if (z3 >=  70 && z3 <=  89) return 'New Jersey';
    return null;
  }
  function addBiz(date, days){
    var d = new Date(date);
    while (days > 0) { d.setDate(d.getDate() + 1); var w = d.getDay(); if (w !== 0 && w !== 6) days--; }
    return d;
  }
  function fmt(d){ return d.toLocaleDateString('en-US', { month:'short', day:'numeric' }); }

  function check(){
    var raw = (input.value || '').trim(),
        m   = raw.match(/^(\d{5})(?:-\d{4})?$/);
    out.style.display = 'block';
    if (!m) {
      out.className = 'taf-shipcheck-result err';
      out.innerHTML = 'Please enter a valid 5-digit US ZIP code. Outside the US? <a href="/contact/">Contact us</a> for an international quote.';
      return;
    }
    var z3    = parseInt(m[1].substring(0,3), 10),
        st    = zone(z3),
        today = new Date(),
        early = addBiz(addBiz(today, 3), 5),   // fastest: 3d production + 5d transit
        late  = addBiz(addBiz(today, 5), 10);  // slowest: 5d production + 10d transit
    var arrival = 'Order today and your artwork should arrive between <b>' +
                  fmt(early) + '</b> and <b>' + fmt(late) + '</b>.';
    // The policy, not a guess. This checker promised no-cost delivery only to
    // ZIPs whose prefix it recognised and "calculated at checkout" to the rest
    // — the same split claim that made the trust badge and its own popup
    // contradict each other before. Both branches now read AF_SHIP, so if
    // shipping is free it is free for every US ZIP, recognised or not, and if
    // it ever stops being free neither branch can go on saying otherwise.
    if (st) {
      out.className = 'taf-shipcheck-result ok';
      // No literal "free" in this file: the free branch takes its words from
      // AF_SHIP too, so the page source never carries the claim while the
      // policy says otherwise (the diag counts script text as well).
      out.innerHTML = (AF_SHIP.free
            ? '<b>🎁 ' + AF_SHIP.short + ' — delivering to ' + m[1] + ' (' + st + ').</b><br>'
            : '<b>🚚 We deliver to ' + m[1] + ' (' + st + ').</b> ' + AF_SHIP.short + '<br>')
        + arrival;
    } else if (AF_SHIP.free) {
      // A valid US ZIP whose prefix this table does not carry. Free is free.
      out.className = 'taf-shipcheck-result ok';
      out.innerHTML = '<b>🎁 ' + AF_SHIP.short + ' — delivering to ' + m[1] + '.</b><br>' + arrival;
    } else {
      out.className = 'taf-shipcheck-result mid';
      out.innerHTML = '<b>🚚 We deliver to ' + m[1] + '.</b> Shipping is calculated at checkout based on artwork size.<br>' +
        'Estimated arrival between <b>' + fmt(early) + '</b> and <b>' + fmt(late) + '</b>.';
    }
  }
  btn.addEventListener('click', check);
  input.addEventListener('keydown', function(e){ if (e.key === 'Enter') { e.preventDefault(); check(); } });
})();
</script>
<?php return ob_get_clean();
});

// ── 12h. [af_artists] — dynamic artist cards from the admin panel ─
// Renders one card per child category of "Direct from Artists"
// (Products → Categories in wp-admin). Add a new artist category there
// and it appears on /artists/ automatically: name, description, category
// thumbnail, product count, links to the profile page (if one exists at
// /artists/<slug>/) and the shop archive.
add_shortcode('af_artists', function() {
    $parent = get_term_by('slug', 'direct-from-artists', 'product_cat');
    if (!$parent) return '<p>No artists found yet — check back soon.</p>';
    $terms = get_terms(array(
        'taxonomy'   => 'product_cat',
        'parent'     => $parent->term_id,
        'hide_empty' => false,
        'orderby'    => 'name',
    ));
    if (is_wp_error($terms) || empty($terms)) return '<p>No artists found yet — check back soon.</p>';

    $fallback_img = 'https://theartframer.us/wp-content/uploads/2026/04/works-of-artist-69da11c3d9a55.webp';
    $out = '<div class="taf-grid taf-artists">';
    foreach ($terms as $t) {
        $thumb_id = get_term_meta($t->term_id, 'thumbnail_id', true);
        $img      = $thumb_id ? wp_get_attachment_image_url($thumb_id, 'large') : '';
        if (!$img) $img = $fallback_img;
        $desc = trim($t->description);
        if ($desc === '') $desc = 'Original artworks by ' . $t->name . ', printed and framed by The Art Framer.';
        $archive = get_term_link($t);
        if (is_wp_error($archive)) $archive = '/product-category/direct-from-artists/';
        $profile = get_page_by_path('artists/' . $t->slug);
        $count   = (int) $t->count;

        $out .= '<div class="taf-card taf-artist-card">';
        $out .= '<img class="taf-artist-img" loading="lazy" src="' . esc_url($img) . '" alt="' . esc_attr('Artwork by ' . $t->name) . '">';
        $out .= '<h3>' . esc_html($t->name) . '</h3>';
        if ($count) $out .= '<span class="taf-badge">' . $count . ' artwork' . ($count === 1 ? '' : 's') . '</span>';
        $out .= '<p>' . esc_html(wp_trim_words($desc, 28, '…')) . '</p>';
        $out .= '<p class="taf-artist-actions">';
        if ($profile && $profile->post_status === 'publish') {
            $out .= '<a class="taf-btn" href="' . esc_url(get_permalink($profile)) . '">View Profile</a> ';
        }
        $out .= '<a class="taf-btn-alt" href="' . esc_url($archive) . '">Shop Artworks</a>';
        $out .= '</p></div>';
    }
    $out .= '</div>';
    return $out;
});

// ── 12i. [af_dashboard] — dynamic customer dashboard ─────────
// Replaces the dead [dokan-dashboard] shortcode on /dashboard/.
// Logged in: greeting, live order/download counts, recent orders,
// quick links to My Account endpoints. Logged out: login/register CTAs.
add_shortcode('af_dashboard', function() {
    if (!function_exists('wc_get_account_endpoint_url')) return '';
    ob_start();

    if (!is_user_logged_in()) { ?>
<div class="afd afd-guest">
  <div class="afd-welcome">
    <span class="afd-welcome-ico">🖼️</span>
    <span class="afd-eyebrow">Your Account</span>
    <h2>Welcome to Your Dashboard</h2>
    <p>Sign in to see your orders, downloads, and saved details — or create a free account in under a minute.</p>
    <div class="afd-welcome-btns">
      <a class="afd-btn" href="<?php echo esc_url(home_url('/my-account/')); ?>">Sign In</a>
      <a class="afd-btn afd-btn-ghost" href="<?php echo esc_url(home_url('/sign-up/')); ?>">Create Account</a>
    </div>
    <ul class="afd-perks">
      <li>Track orders and deliveries in one place</li>
      <li>Instant access to your digital downloads</li>
      <li>Faster checkout with saved addresses</li>
      <li>Wishlist saved across devices</li>
    </ul>
  </div>
</div>
<?php
        return ob_get_clean();
    }

    $user   = wp_get_current_user();
    $uid    = $user->ID;
    $name   = $user->first_name ? $user->first_name : $user->display_name;
    $initial = strtoupper(mb_substr($name, 0, 1));
    $orders = function_exists('wc_get_customer_order_count') ? wc_get_customer_order_count($uid) : 0;
    $downloads = function_exists('wc_get_customer_available_downloads') ? count(wc_get_customer_available_downloads($uid)) : 0;
    $recent = wc_get_orders(array('customer_id' => $uid, 'limit' => 3, 'orderby' => 'date', 'order' => 'DESC'));
    ?>
<div class="afd">

  <div class="afd-hero">
    <div class="afd-hero-user">
      <div class="afd-avatar"><?php echo esc_html($initial); ?></div>
      <div class="afd-hero-txt">
        <span class="afd-eyebrow">My Dashboard</span>
        <h2>Hi <?php echo esc_html($name); ?> <span class="afd-wave">👋</span></h2>
        <p>Welcome back — here's what's happening with your art.</p>
      </div>
    </div>
    <a class="afd-btn afd-btn-outline" href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>">Log Out</a>
  </div>

  <div class="afd-stats">
    <div class="afd-stat"><span class="afd-stat-ico">📦</span><span class="afd-num"><?php echo (int) $orders; ?></span><span class="afd-lbl">Orders placed</span></div>
    <div class="afd-stat"><span class="afd-stat-ico">💾</span><span class="afd-num"><?php echo (int) $downloads; ?></span><span class="afd-lbl">Digital downloads</span></div>
    <div class="afd-stat"><span class="afd-stat-ico">⭐</span><span class="afd-num"><?php echo esc_html(date_i18n('M Y', strtotime($user->user_registered))); ?></span><span class="afd-lbl">Member since</span></div>
  </div>

  <section class="afd-block">
    <div class="afd-block-head">
      <h3>Recent Orders</h3>
      <?php if ($recent): ?><a class="afd-viewall" href="<?php echo esc_url(wc_get_account_endpoint_url('orders')); ?>">View all →</a><?php endif; ?>
    </div>
    <?php if ($recent) : ?>
    <div class="afd-orders">
      <?php foreach ($recent as $o) :
        $st = $o->get_status(); ?>
      <a class="afd-order" href="<?php echo esc_url($o->get_view_order_url()); ?>">
        <span class="afd-order-id">#<?php echo esc_html($o->get_order_number()); ?></span>
        <span class="afd-order-date"><?php echo esc_html(wc_format_datetime($o->get_date_created(), 'M j, Y')); ?></span>
        <span class="afd-badge afd-badge-<?php echo esc_attr($st); ?>"><?php echo esc_html(wc_get_order_status_name($st)); ?></span>
        <span class="afd-order-total"><?php echo wp_kses_post($o->get_formatted_order_total()); ?></span>
        <span class="afd-order-arrow">→</span>
      </a>
      <?php endforeach; ?>
    </div>
    <?php else : ?>
    <div class="afd-empty">
      <span class="afd-empty-ico">🖼️</span>
      <h4>No orders yet</h4>
      <p>Your walls are waiting — browse the collection or preview art in your own room.</p>
      <div class="afd-empty-btns">
        <a class="afd-btn" href="/shop/">Start Shopping</a>
        <a class="afd-btn afd-btn-ghost" href="/try-on-wall/">Try It on Your Wall</a>
      </div>
    </div>
    <?php endif; ?>
  </section>

  <section class="afd-block">
    <div class="afd-block-head"><h3>Quick Access</h3></div>
    <div class="afd-grid">
      <a class="afd-tile" href="<?php echo esc_url(wc_get_account_endpoint_url('orders')); ?>"><span class="afd-tile-ico">📦</span><span class="afd-tile-txt"><strong>My Orders</strong><span>Order history, invoices &amp; details.</span></span><span class="afd-tile-go">→</span></a>
      <a class="afd-tile" href="<?php echo esc_url(wc_get_account_endpoint_url('downloads')); ?>"><span class="afd-tile-ico">💾</span><span class="afd-tile-txt"><strong>Downloads</strong><span>Your purchased digital files.</span></span><span class="afd-tile-go">→</span></a>
      <a class="afd-tile" href="/track-your-order/"><span class="afd-tile-ico">🚚</span><span class="afd-tile-txt"><strong>Track an Order</strong><span>From studio to your doorstep.</span></span><span class="afd-tile-go">→</span></a>
      <a class="afd-tile" href="<?php echo esc_url(wc_get_account_endpoint_url('edit-address')); ?>"><span class="afd-tile-ico">📍</span><span class="afd-tile-txt"><strong>Addresses</strong><span>Shipping &amp; billing details.</span></span><span class="afd-tile-go">→</span></a>
      <a class="afd-tile" href="/wishlist/"><span class="afd-tile-ico">❤️</span><span class="afd-tile-txt"><strong>Wishlist</strong><span>Pieces saved for later.</span></span><span class="afd-tile-go">→</span></a>
      <a class="afd-tile" href="<?php echo esc_url(wc_get_account_endpoint_url('edit-account')); ?>"><span class="afd-tile-ico">⚙️</span><span class="afd-tile-txt"><strong>Account Details</strong><span>Name, email &amp; password.</span></span><span class="afd-tile-go">→</span></a>
    </div>
  </section>

</div>
<?php
    return ob_get_clean();
});

// ─────────────────────────────────────────────────────────────
// PHASE 24 — SEO hardening for US search (added 2026-07-16).
// 24a force one https:// origin   24b robots.txt (virtual)
// 24c strip invalid hreflang tags 24d (removed per request)
// 24e alt-text fallback on rendered images  24f /about-us 301.
// Persisted meta (titles, descriptions, alts, physical robots.txt)
// is written by tools/seo-improvements.php on deploy.
// Additive; reversible via this block.
// ─────────────────────────────────────────────────────────────

// 24a. http:// serves the full site with a 200 — force a single 301
// to https so search engines see one origin.
add_action('init', function () {
    if (is_ssl()) return;
    if (php_sapi_name() === 'cli' || (defined('WP_CLI') && WP_CLI) || wp_doing_cron()) return;
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') return;
    // The site's own host, not the request's Host header (security audit, 12 Oct).
    $host = (string) parse_url(home_url('/'), PHP_URL_HOST);
    $uri  = !empty($_SERVER['REQUEST_URI']) ? '/' . ltrim($_SERVER['REQUEST_URI'], '/') : '/';
    wp_redirect('https://' . $host . $uri, 301);
    exit;
}, 0);

// 24b. robots.txt content. Served two ways: WP's virtual robots.txt
// (filter below) and a physical file written on deploy, because the
// web server 404s /robots.txt before WordPress sees it.
// The "Disallow: /items/" tourniquet was REMOVED 2026-07-16 once the
// cloaking hack was confirmed cleaned (sitemaps serve real URLs, all
// /items/ 404 to Googlebot). Google must be able to crawl those URLs
// to see the 404s and drop them from the index — do NOT re-add it.
function af_seo_robots_txt() {
    $lines = array(
        '# theartframer-seo',
        'User-agent: *',
        'Disallow: /wp-admin/',
        'Allow: /wp-admin/admin-ajax.php',
        'Disallow: /cart/',
        'Disallow: /checkout/',
        'Disallow: /my-account/',
        'Disallow: /?s=',
        // The faceted-filter URL space (sizes x colors x frames x orientation
        // x price x sort) is combinatorially infinite for well-behaved
        // crawlers; every combination is a cache MISS and a ~3s render. The
        // crawl guard already bounces headerless clients — these lines stop
        // Googlebot/Bingbot (deliberately never blocked) from queueing them.
        'Disallow: /*?*filter_',
        'Disallow: /*?*orientation=',
        'Disallow: /*?*min_price=',
        'Disallow: /*?*max_price=',
        'Disallow: /*?*orderby=',
        'Disallow: /*?*query_type_',
        'Disallow: /*?*add-to-cart=',
        // Every product card carries a wishlist link, so Googlebot was queueing
        // ?add-to-wishlist= / ?add_to_wishlist= URLs (30 in the 16 Oct link
        // crawl, 3.7-4.5s each, all 302 to /wishlist/) while the Lakshmi
        // Ganesh category and /blog/ sat "Discovered - currently not indexed".
        // The wishlist page is per-visitor and has nothing to rank.
        'Disallow: /*?*add-to-wishlist=',
        'Disallow: /*?*add_to_wishlist=',
        'Disallow: /wishlist/',
        'Disallow: /*?*currency=',
        'Disallow: /*?*lang=',
        'Disallow: /*?*per_page=',
        'Disallow: /*?*layout=',
        // Deep tag pagination was the largest remaining crawl cost after the
        // filter URLs: the 2026-08-04 profile is full of /product-tag/*/page/N/
        // hits (page/22, page/13, page/11 ...) at ~2.2-4.2s each. Tag archives
        // duplicate the category listings, and page 2 onward carries no ranking
        // value the categories do not already have. Page 1 of each tag stays
        // crawlable, and every product remains discoverable through the
        // sitemap, so nothing drops out of the index by being unreachable.
        'Disallow: /product-tag/*/page/',
        // Feeds are rendered by PHP, are never cached, and bring no customers.
        'Disallow: /*/feed/',
        'Disallow: /*/embed/',
        // Ignored by Google (it paces itself from Search Console) but honoured
        // by Bing, Yandex and most well-behaved crawlers — the ones that were
        // arriving in bursts and stacking concurrent renders on a shared host.
        'Crawl-delay: 10',
        '',
        'Sitemap: https://theartframer.us/sitemap_index.xml',
    );
    return implode("\n", $lines) . "\n";
}
add_filter('robots_txt', function ($output, $public) {
    return af_seo_robots_txt();
}, 99, 2);

// 24c. Something outside the theme emits invalid hreflang alternates
// (relative hrefs, plus a Hindi variant with no real content) that
// dilute US geo-targeting. A single-language US store needs no
// hreflang at all, so strip every hreflang link tag from output.
add_action('template_redirect', function () {
    if (is_admin() || is_feed()) return;
    ob_start(function ($html) {
        if (stripos($html, 'hreflang') === false) return $html;
        return preg_replace('#<link\b[^>]*\bhreflang=[^>]*>\s*#i', '', $html);
    });
}, 0);

// 24c-2. PERF: restore lazy-loading at the HTML layer + repair two bugs
// found in the 2026-07-31 speed audit (homepage: 250 requests / 6.4 MB
// visible payload + ~8 hidden YouTube embeds, full load 13.7s).
//
// Background: LiteSpeed's JS lazy-loader (data-src placeholder swap) broke
// carousel/AJAX images, so deploy.yml force-disables it — which also left
// every below-fold image loading eagerly. Browser-native loading="lazy"
// is a different mechanism (no placeholder, no JS) and is already present
// on ~60 images per page via WP core without issues, so extending it is
// safe where the JS variant was not.
//
// What this buffer does:
//  1) Repairs the LiteSpeed-mangled Google Fonts link. It ships as
//     family=Instrument+Sans:<local-css-url>, which HTTP-400s — a wasted
//     render-blocking request AND the reason Instrument Sans never loads.
//     (The local CSS file glued into the URL is a 0-byte artifact; nothing
//     is lost by dropping it.)
//  2) Adds loading="lazy" to YouTube embed iframes. The Products In Motion
//     section hides its 8 playlist embeds behind the circle slider, but
//     they still download in full (~1 MB each). Lazy + hidden = the
//     browser never fetches them; if the slider ever fails to place, they
//     still load on scroll as a fallback.
//  3) Adds loading="lazy" decoding="async" to below-fold <img> tags.
//     Skips: the first 7 images (header logos + revslider hero — the LCP
//     must stay eager), anything already declaring loading=, slider-
//     managed images (revslider/swiper run their own loaders), and
//     data-URI placeholders.
add_action('template_redirect', function () {
    if (is_admin() || is_feed() || is_customize_preview()) return;
    // Insurance: skip AJAX-fragment-heavy WooCommerce pages (the original
    // LiteSpeed breakage was lazy images inside AJAX-replaced markup).
    if (function_exists('is_cart') && (is_cart() || is_checkout() || is_account_page())) return;
    ob_start(function ($html) {
        if (stripos($html, '</html>') === false) return $html; // only full pages

        // (1) broken Google Fonts href -> correct one
        $html = preg_replace(
            '#<link\b[^>]*href=["\'](?:https?:)?//fonts\.googleapis\.com/css2\?family=Instrument\+Sans:https?[^"\']*["\'][^>]*/?>#i',
            '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;600&display=swap">',
            $html
        );

        // (2) YouTube embeds
        $html = preg_replace_callback('#<iframe\b[^>]*>#i', function ($m) {
            $tag = $m[0];
            if (stripos($tag, 'youtube.com/embed') === false) return $tag;
            if (stripos($tag, 'loading=') !== false) return $tag;
            return '<iframe loading="lazy"' . substr($tag, 7);
        }, $html);

        // (3) below-fold images
        $seen = 0;
        $html = preg_replace_callback('#<img\b[^>]*>#i', function ($m) use (&$seen) {
            $tag = $m[0];
            $seen++;
            if ($seen <= 7) return $tag;                                   // logos + hero (LCP)
            if (stripos($tag, 'loading=') !== false) return $tag;          // already lazy/eager by choice
            if (stripos($tag, 'data-src') !== false || stripos($tag, 'data-lazy') !== false) return $tag;
            if (stripos($tag, '/revslider/') !== false) return $tag;       // slider's own loader
            if (preg_match('#class=["\'][^"\']*(?:\brs-|\bsr7|\btp-|swiper-slide-image)#i', $tag)) return $tag;
            if (stripos($tag, 'src="data:') !== false || stripos($tag, "src='data:") !== false) return $tag;
            $ins = ' loading="lazy"';
            if (stripos($tag, 'decoding=') === false) $ins .= ' decoding="async"';
            return '<img' . $ins . substr($tag, 4);
        }, $html);

        return $html;
    });
}, 0);

// 24c-3. PERF: preconnect to the font/icon CDNs the page actually uses —
// fonts.gstatic.com serves the Instrument Sans woff2 files (see 24c-2),
// cdnjs.cloudflare.com serves the chat launcher's Font Awesome. Shaves a
// DNS+TLS round-trip off each first fetch.
add_action('wp_head', function () {
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
    echo '<link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>' . "\n";
    // Correct Instrument Sans stylesheet, printed by us because LiteSpeed's
    // CSS optimizer runs AFTER the 24c-2 buffer (PHP buffers flush
    // innermost-first) and mangles the original link into an HTTP-400 URL
    // that the buffer therefore cannot see, let alone repair.
    // data-no-optimize keeps LiteSpeed's optimizer off this one.
    echo '<link rel="stylesheet" data-no-optimize="1" href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;600&display=swap">' . "\n";
}, 2);

// 24c-4. AJAX-rendered product cards (Trending Today / More Like This
// sections arrive via admin-ajax) bypass the 24c-2 output buffer, so give
// their images native lazy-loading here. AJAX fragments are injected after
// first paint and are never the LCP, so blanket lazy is safe there.
add_filter('wp_get_attachment_image_attributes', function ($attr) {
    if (wp_doing_ajax() && empty($attr['loading'])) {
        $attr['loading'] = 'lazy';
        if (empty($attr['decoding'])) $attr['decoding'] = 'async';
    }
    return $attr;
}, 20);

// 24c-5. QUICK VIEW RELIABILITY: the stock quick-view binder (inline
// script outside this theme) has a success handler but NO error handler,
// so when the shared host's process cap returns a transient 508 the modal
// sits on "Loading..." forever (user-reported 2026-07-31 with a screen
// recording; reproduced live — admin-ajax load_quick_product answered
// 508). Intercept the click in the capture phase (fires before jQuery's
// delegated handler), run the same loader with a timeout, retry up to 3
// attempts with backoff — the 508s are burst-transient — and fall back to
// a link to the full product page if the host stays saturated.
add_action('wp_footer', function () {
    ?>
<script>
(function () {
  if (!window.jQuery) return;
  var $ = window.jQuery;
  var AJAX_URL = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;

  function productUrl(btn) {
    var card = btn.closest('.product-card, li.product, .product');
    var a = card ? card.querySelector('a[href*="/product/"]') : null;
    if (a) return a.href;
    var pid = btn.getAttribute('data-product-id');
    return pid ? '/?post_type=product&p=' + encodeURIComponent(pid) : null;
  }

  function load(btn, attempt) {
    $.ajax({
      url: AJAX_URL,
      type: 'POST',
      timeout: 20000,
      data: { action: 'load_quick_product', product_id: btn.getAttribute('data-product-id') },
      success: function (response) {
        $('#quickViewContent').html(response);
        // same follow-up the stock handler performs
        setTimeout(function () {
          if (typeof $.fn.wc_product_gallery !== 'undefined') {
            $('.woocommerce-product-gallery').each(function () { $(this).wc_product_gallery(); });
          }
        }, 400);
      },
      error: function () {
        if (attempt < 3) {
          setTimeout(function () { load(btn, attempt + 1); }, 800 * attempt);
          return;
        }
        var url = productUrl(btn);
        $('#quickViewContent').html(
          '<div style="padding:28px;text-align:center">' +
          '<p style="margin:0 0 14px">Sorry &mdash; the quick preview could not load right now.</p>' +
          (url ? '<a href="' + url + '" style="display:inline-block;padding:10px 22px;background:#1f1f1f;color:#fff;border-radius:6px;text-decoration:none">Open the product page</a>' : '') +
          '</div>'
        );
      }
    });
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('.quick-view-btn') : null;
    if (!btn) return;
    e.preventDefault();
    e.stopImmediatePropagation(); // keep the error-blind stock handler out of the way
    $('#quickViewContent').html('Loading...');
    $('#quickViewModal').fadeIn();
    $('body').addClass('modal-open');
    load(btn, 1);
  }, true);
})();
</script>
    <?php
}, 99);

// 24d. REMOVED 2026-07-16 per request — the front-page H1 intro strip
// ("Custom Canvas Prints, Wall Art & Picture Framing") was removed at
// the user's request (see the wp_footer hider at the end of this
// file, added by the same request). Leaving the H1 in the HTML while
// JS hides it would be hidden text, so the injection is gone
// entirely. The homepage intentionally has no keyword H1.

// 24e. Most rendered images ship without alt text. Fall back to the
// attachment's parent (product) title, then the attachment title.
add_filter('wp_get_attachment_image_attributes', function ($attr, $attachment) {
    if (!empty($attr['alt']) || !($attachment instanceof WP_Post)) return $attr;
    $alt = (string) get_post_meta($attachment->ID, '_wp_attachment_image_alt', true);
    if ($alt === '' && $attachment->post_parent) $alt = (string) get_the_title($attachment->post_parent);
    if ($alt === '') $alt = trim(preg_replace('/[-_]+/', ' ', (string) $attachment->post_title));
    if ($alt !== '') $attr['alt'] = wp_strip_all_tags($alt);
    return $attr;
}, 20, 2);

// 24f. /about-us is a dead URL that still collects links — 301 it to
// the real About page.
add_action('template_redirect', function () {
    if (!is_404()) return;
    $uri  = !empty($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
    $path = strtolower(trim((string) parse_url($uri, PHP_URL_PATH), '/'));
    if ($path === 'about-us') {
        wp_redirect(home_url('/about/'), 301);
        exit;
    }
}, 1);

// ── 12j. Compare Products (spec §4) ──────────────────────────
// Client-side selection (localStorage, max 4) + floating bar + /compare/
// page that renders a side-by-side table from a JSON endpoint.

// Endpoint: product data for the compare table
function af_compare_data_handler() {
    $raw = isset($_POST['ids']) ? (array) $_POST['ids'] : array();
    $ids = array_slice(array_filter(array_map('absint', $raw)), 0, 4);
    $out = array();
    foreach ($ids as $id) {
        $p = wc_get_product($id);
        if (!$p || $p->get_status() !== 'publish') continue;
        $attr = function($tax) use ($p) {
            $terms = wc_get_product_terms($p->get_id(), $tax, array('fields' => 'names'));
            return $terms && !is_wp_error($terms) ? implode(', ', $terms) : '—';
        };
        $img = wp_get_attachment_image_url($p->get_image_id(), 'woocommerce_thumbnail');
        $out[] = array(
            'id'      => $p->get_id(),
            'title'   => html_entity_decode(wp_strip_all_tags($p->get_name()), ENT_QUOTES),
            'url'     => get_permalink($p->get_id()),
            'img'     => $img ?: wc_placeholder_img_src(),
            'price'   => wp_strip_all_tags($p->get_price_html()),
            'rating'  => $p->get_average_rating() > 0 ? number_format((float)$p->get_average_rating(), 1) . ' ★ (' . $p->get_review_count() . ')' : 'No reviews yet',
            'sizes'   => $attr('pa_size'),
            'frames'  => $attr('pa_frame'),
            'colours' => $attr('pa_colour'),
            'type'    => $p->is_type('variable') ? 'Multiple options' : 'Single option',
            'stock'   => $p->is_in_stock() ? 'In stock' : 'Out of stock',
            'digital' => $p->is_downloadable() ? 'Available' : '—',
        );
    }
    wp_send_json_success($out);
}
add_action('wp_ajax_af_compare_data',        'af_compare_data_handler');
add_action('wp_ajax_nopriv_af_compare_data', 'af_compare_data_handler');

// Compare toggle button on product cards — icon only, label shows as a
// native title tooltip, same as the wishlist/quick-view icons already use.
// The single-product "Add to Compare" CTA below keeps its full text; only
// the card-level icon is condensed.
add_action('woocommerce_after_shop_loop_item', function() {
    $product = af_wc_product();
    if (!$product) return;
    echo '<button type="button" class="af-cmp-btn" data-id="' . esc_attr($product->get_id())
        . '" title="Compare" aria-label="Add to compare">'
        . '<span class="af-cmp-icon" aria-hidden="true">⇄</span></button>';
}, 25);

// Compare toggle on single product pages
add_action('woocommerce_after_add_to_cart_button', function() {
    $product = af_wc_product();
    if (!$product) return;
    echo '<button type="button" class="af-cmp-btn af-cmp-single" data-id="' . esc_attr($product->get_id())
        . '">⇄ Add to Compare</button>';
}, 25);

// Shortcode: the compare page table
add_shortcode('af_compare', function() {
    return '<div id="afCompareWrap" data-ajax="' . esc_url(admin_url('admin-ajax.php')) . '">'
         . '<div class="af-cmp-empty" id="afCmpEmpty" style="display:none;text-align:center;padding:30px 10px;">'
         . '<span class="taf-ico" style="font-size:46px;">⇄</span>'
         . '<h3 style="margin:12px 0 8px;">Nothing to compare yet</h3>'
         . '<p>Browse the shop and tap <b>⇄ Compare</b> on up to four artworks.</p>'
         . '<p style="margin-top:14px;"><a class="taf-btn" href="/shop/">Browse Artworks</a></p></div>'
         . '<div id="afCmpTable"></div></div>';
});

// Site-wide compare JS: state, floating bar, page renderer
add_action('wp_footer', function() { ?>
<script id="af-compare" src="<?php echo esc_url(af_static_url('compare.js')); ?>" data-no-optimize="1"></script>
<?php }, 302);

// 24g. BreadcrumbList JSON-LD on single product pages ONLY — every
// other page type (categories, pages, posts) already gets a
// BreadcrumbList from elsewhere; products were the one gap.
add_action('wp_head', function () {
    if (is_admin() || is_front_page()) return;
    if (!function_exists('is_product') || !is_product()) return;
    $crumbs = array(array('Home', home_url('/')));
    $terms = get_the_terms(get_the_ID(), 'product_cat');
    if ($terms && !is_wp_error($terms)) {
        $t = $terms[0];
        if ($t->parent) {
            $p = get_term($t->parent, 'product_cat');
            if ($p && !is_wp_error($p)) $crumbs[] = array($p->name, get_term_link($p));
        }
        $link = get_term_link($t);
        if (!is_wp_error($link)) $crumbs[] = array($t->name, $link);
    }
    $crumbs[] = array(get_the_title(), get_permalink());
    if (count($crumbs) < 2) return;
    $items = array();
    foreach ($crumbs as $i => $c) {
        $items[] = array('@type' => 'ListItem', 'position' => $i + 1, 'name' => wp_strip_all_tags((string) $c[0]), 'item' => (string) $c[1]);
    }
    echo '<script type="application/ld+json">' . wp_json_encode(array(
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $items,
    )) . '</script>' . "\n";
}, 5);

// ─────────────────────────────────────────────────────────────
// PHASE 24 — Remove the "Custom Canvas Prints, Wall Art & Picture
// Framing" hero heading + subtitle (per request). Targeted by exact
// text so nothing else is affected; removes the tight wrapper that
// contains only this hero (heading + subtitle).
// ─────────────────────────────────────────────────────────────
add_action('wp_footer', function(){
    ?>
    <script>
    (function(){
      var HEAD = 'custom canvas prints, wall art & picture framing';
      var SUB  = 'museum-quality prints and handcrafted frames';
      function norm(s){ return (s||'').replace(/\s+/g,' ').trim().toLowerCase(); }
      function removeHero(matchFn, budget){
        var all = document.querySelectorAll('h1,h2,h3,p,span,div');
        for (var i=0;i<all.length;i++){
          var el = all[i];
          if (!matchFn(norm(el.textContent))) continue;
          // walk up while the wrapper still contains ONLY this hero text
          var target = el, node = el.parentElement;
          while (node && node !== document.body){
            if (norm(node.textContent).length <= budget){ target = node; node = node.parentElement; }
            else break;
          }
          target.style.display = 'none';   // hide (safe) rather than destroy
          target.setAttribute('data-af-removed','1');
          return true;
        }
        return false;
      }
      function run(){
        var budget = (HEAD.length + SUB.length) + 60;
        removeHero(function(t){ return t === HEAD || t.indexOf(HEAD) === 0; }, budget);
        removeHero(function(t){ return t.indexOf(SUB) === 0; }, budget);
      }
      if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run); else run();
      window.addEventListener('load', function(){ run(); setTimeout(run, 600); });
    })();
    </script>
    <?php
}, 105);

// ── 12k. Digital downloads: license acceptance + watermarked previews ─
// Spec §9: download limits (set by tools/enable-digital-downloads.php),
// license acceptance at checkout, watermarked product-page previews.

/**
 * Is this cart line bought as a file?
 *
 * NOT is_downloadable(). tools/enable-digital-downloads.php flags every
 * published product downloadable (see af_is_digital_download), so that check
 * answered "yes" for a rolled canvas with "You receive: Painting only", and
 * every order in the shop was asked to accept the Digital Download License and
 * refused if the box was left empty (Test Run 03, N-02). A line is a file when:
 *   - it carries af_digital: the download modal, or a product from a
 *     digital-download category (af_sells_as_digital)
 *   - "What you receive" is an option that ships nothing: Digital download
 *     (inc/kit-choices.php), which does not set af_digital
 *   - its product sits in a digital-download category, however it got here
 */
function af_cart_line_is_digital($item) {
    if (!empty($item['af_digital'])) return true;
    if (!empty($item['af_kit']) && function_exists('af_kit_ships') && !af_kit_ships($item['af_kit'])) return true;
    $pid = isset($item['product_id']) ? (int) $item['product_id'] : 0;
    $p = $pid ? wc_get_product($pid) : null;
    return $p ? af_is_digital_download($p) : false;
}

// Does the cart contain any digital download? The checkbox and the check that
// refuses the order both ask this, so they cannot disagree.
function af_cart_has_digital() {
    if (!function_exists('WC') || !WC()->cart) return false;
    foreach (WC()->cart->get_cart() as $item) {
        if (af_cart_line_is_digital($item)) return true;
    }
    return false;
}

// Checkout: required license checkbox when a digital item is in the cart.
// required + validate-required make the asterisk true in the markup as well:
// WooCommerce marks the row invalid before the request is sent, and screen
// readers announce it as required. The server check below still decides.
add_action('woocommerce_review_order_before_submit', function() {
    if (!af_cart_has_digital()) return;
    ?>
    <p class="form-row validate-required af-dl-license-row" style="background:#faf7f0;border:1px solid #e0d5b8;padding:14px 16px;">
        <label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox" style="display:flex;gap:10px;align-items:flex-start;">
            <input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox" name="af_dl_license" id="af_dl_license" required aria-required="true" style="margin-top:4px;">
            <span>Your order includes a digital download. I accept the
            <a href="/digital-download-license/" target="_blank" rel="noopener">Digital Download License</a>
            (personal use only — no resale or redistribution). <abbr class="required" title="required">*</abbr></span>
        </label>
    </p>
    <?php
});

add_action('woocommerce_checkout_process', function() {
    if (af_cart_has_digital() && empty($_POST['af_dl_license'])) {
        wc_add_notice(__('Please accept the Digital Download License to complete your purchase.'), 'error');
    }
});

add_action('woocommerce_checkout_create_order', function($order) {
    if (!empty($_POST['af_dl_license'])) {
        $order->update_meta_data('_af_dl_license_accepted', current_time('mysql'));
    }
}, 10, 1);

// ── Resized-copy quality ─────────────────────────────────────
// WordPress re-encodes every registered size at quality 82 by default. That is
// tuned for photographs; this catalogue is flat-colour, hard-edged artwork,
// where a hard re-encode leaves visible ringing along the colour boundaries.
// The original decision here was therefore quality 100, and the reasoning was
// right even though the number was not.
//
// 100 is a special case in JPEG, not simply "the best". It switches off almost
// all quantisation, so the file keeps coefficients no eye can resolve, and the
// cost is not marginal: measured on this catalogue, an 800x1423 card thumbnail
// came to 757 KB where the same image at 92 lands near 300 KB. One category
// page was shipping 5.6 MB of pictures, and that is the largest single reason
// the site feels slow.
//
// 92 keeps the intent. It is above the threshold where ringing appears on flat
// colour and hard edges — that begins in the 70s and 80s, which is why the
// default was wrong for this catalogue — while being roughly half the bytes of
// 100. The artwork stays clean; the page stops carrying pixels nobody can see.
//
// Only affects copies generated from here on. Existing derivatives keep their
// current size until they are re-encoded; tools/reencode-images.php does that.
add_filter('jpeg_quality',          function() { return (int) apply_filters('af_jpeg_quality', 92); }, 10, 0);
add_filter('wp_editor_set_quality', function() { return (int) apply_filters('af_jpeg_quality', 92); }, 10, 0);

// ── The size the cards actually need ─────────────────────────
// WooCommerce generates the catalogue thumbnail at 600px wide. A card on the
// three-up grid is about 380 CSS px across, which is comfortable on an
// ordinary screen and half of what a 2x phone or laptop asks for — so on
// every retina display the browser is stretching a 600px file across 760
// device pixels, and flat-colour artwork with hard edges shows that as soft,
// slightly furry outlines. 800px covers the 2x case with a little headroom.
//
// Width only. Height and crop are left exactly as WooCommerce has them,
// because those decide the SHAPE of every card on the site and this is a
// change about resolution, not layout. And only ever upward: if the setting
// is already larger than this, it is deliberate and stays.
// Only ever upward, width only, height scaled to match when the size is
// uncropped — the same rule for all three below, so none of them changes the
// SHAPE of anything, only how many pixels it is drawn from.
function af_bump_image_width( $size, $want ) {
    $want = (int) $want;
    if ( ! empty( $size['width'] ) && (int) $size['width'] >= $want ) return $size;
    // Scale the height by the same factor whether the size is cropped or not.
    // Skipping it for cropped sizes was a bug and precisely the thing this
    // helper exists to avoid: the gallery thumbnail ships as 100x100 cropped
    // square, and raising the width alone turned it into 200x100 — a square
    // thumbnail silently became a 2:1 letterbox. A cropped size's height is
    // what defines its shape, so it has to move with the width.
    if ( ! empty( $size['height'] ) && ! empty( $size['width'] ) ) {
        $size['height'] = (int) round( $size['height'] * ( $want / (int) $size['width'] ) );
    }
    $size['width'] = $want;
    return $size;
}

// ── The product page's own picture ───────────────────────────
// This is the one that was missed, and it is the one that matters most: the
// main image on a product page is woocommerce_single, NOT the catalogue
// thumbnail raised below. WooCommerce ships it at 600px wide. That image is
// drawn at roughly 570 CSS px on a desktop product page, so on any 2x screen
// the browser is stretching 600 pixels across about 1140 — nearly double —
// and this is precisely the picture a customer leans in to look at before
// spending eighty dollars.
//
// 1200 covers 2x with a little to spare and stays inside the masters, which
// the orientation scan measured at about 1600px on the long side, so nothing
// here is invented from pixels that do not exist.
add_filter('woocommerce_get_image_size_single', function ($size) {
    return af_bump_image_width( $size, (int) apply_filters('af_single_image_width', 1200) );
});

// The little strip beside it, likewise: shipped at 100px and shown at about
// that size, which is a blurred stamp on any modern screen.
add_filter('woocommerce_get_image_size_gallery_thumbnail', function ($size) {
    return af_bump_image_width( $size, (int) apply_filters('af_gallery_thumb_width', 200) );
});

add_filter('woocommerce_get_image_size_thumbnail', function ($size) {
    return af_bump_image_width( $size, (int) apply_filters('af_card_image_width', 800) );
});

// WordPress also silently downscales any upload wider or taller than 2560px
// on the way in, and that scaled-down copy — not the original — becomes the
// "full" size used everywhere (product pages, zoom, etc.); the true original
// is kept but never referenced. Disable that so uploaded images are used at
// their full uploaded resolution.
add_filter('big_image_size_threshold', '__return_false');

// ── Watermarked previews ─────────────────────────────────────
// For downloadable products, the product-page gallery serves a downscaled
// copy stamped with a diagonal watermark. Generated once with GD and
// cached in uploads/af-wm/. The paid file remains the clean original.
// Returns array( url, width, height ) for the cached preview, or false.
// Callers need the real dimensions: the gallery advertises them to the zoom,
// which upscales a smaller file if they describe the original master instead.
function af_wm_preview_url($attachment_id) {
    if (!function_exists('imagecreatetruecolor')) return false; // GD unavailable
    $src_path = get_attached_file($attachment_id);
    if (!$src_path || !file_exists($src_path)) return false;

    // Keep the preview in its source format. Re-encoding a WebP or PNG master
    // to JPEG adds ringing around the flat colour and hard edges this artwork
    // is built from, and silently flattens transparency onto black.
    $ext = strtolower(pathinfo($src_path, PATHINFO_EXTENSION));
    if     ($ext === 'webp' && function_exists('imagewebp')) $out = 'webp';
    elseif ($ext === 'png'  && function_exists('imagepng'))  $out = 'png';
    else                                                     $out = 'jpg';

    // Previews are shown at ~600 CSS px but zoomed to the full frame, and are
    // served to 2x/3x screens, so the cap has to leave the master untouched at
    // typical sizes rather than halving it.
    $max     = (int) apply_filters('af_wm_max_dimension', 1600);
    $quality = (int) apply_filters('af_wm_quality', 90);

    $up   = wp_get_upload_dir();
    $dir  = trailingslashit($up['basedir']) . 'af-wm';
    // The rendering settings are part of the cache key: previews are generated
    // once and never revisited, so without this every change below would only
    // reach products whose master image happens to change afterwards.
    $key  = 'v2|' . $max . '|' . $quality . '|' . $out . '|' . $src_path . '|' . filemtime($src_path);
    $name = 'wm-' . $attachment_id . '-' . substr(md5($key), 0, 8) . '.' . $out;
    $dest = $dir . '/' . $name;
    $url  = trailingslashit($up['baseurl']) . 'af-wm/' . $name;
    if (file_exists($dest)) {
        $sz = @getimagesize($dest);
        if ($sz) return array('url' => $url, 'width' => $sz[0], 'height' => $sz[1]);
        @unlink($dest); // unreadable/truncated cache entry — rebuild it
    }

    if (!wp_mkdir_p($dir)) return false;
    $raw = @file_get_contents($src_path);
    if (!$raw) return false;
    $img = @imagecreatefromstring($raw);
    if (!$img) return false;

    $w = imagesx($img); $h = imagesy($img);
    if ($w > $max || $h > $max) {
        $ratio = min($max / $w, $max / $h);
        $nw = (int) round($w * $ratio); $nh = (int) round($h * $ratio);
        $tmp = imagecreatetruecolor($nw, $nh);
        if ($out !== 'jpg') {
            imagealphablending($tmp, false);
            imagesavealpha($tmp, true);
            imagefill($tmp, 0, 0, imagecolorallocatealpha($tmp, 0, 0, 0, 127));
        }
        imagecopyresampled($tmp, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($img);
        $img = $tmp; $w = $nw; $h = $nh;
    }
    imagealphablending($img, true); // composite the stamp, don't overwrite pixels

    // Diagonal repeating watermark (built-in font — no TTF dependency).
    // imagestring() only speaks ASCII: a UTF-8 "©" or "·" arrives as its raw
    // bytes and prints as "Â©" / "Â·", so spell them out in ASCII instead.
    $text  = '(C) THE ART FRAMER - PREVIEW';
    $font  = 5;
    $tw    = imagefontwidth($font) * strlen($text);
    $th    = imagefontheight($font);
    $stamp = imagecreatetruecolor($tw + 40, $th + 24);
    imagesavealpha($stamp, true);
    imagefill($stamp, 0, 0, imagecolorallocatealpha($stamp, 0, 0, 0, 127));
    imagestring($stamp, $font, 20, 12, $text, imagecolorallocatealpha($stamp, 255, 255, 255, 78));
    imagestring($stamp, $font, 19, 11, $text, imagecolorallocatealpha($stamp, 20, 20, 20, 96));
    $rot = imagerotate($stamp, 30, imagecolorallocatealpha($stamp, 0, 0, 0, 127));
    imagesavealpha($rot, true);
    imagedestroy($stamp);
    $rw = imagesx($rot); $rh = imagesy($rot);
    for ($y = -$rh; $y < $h + $rh; $y += (int) ($rh * 1.6)) {
        for ($x = -$rw; $x < $w + $rw; $x += (int) ($rw * 1.15)) {
            imagecopy($img, $rot, $x, $y, 0, 0, $rw, $rh);
        }
    }
    imagedestroy($rot);

    if ($out !== 'jpg') imagesavealpha($img, true);
    switch ($out) {
        case 'webp': $ok = imagewebp($img, $dest, $quality); break;
        case 'png':  $ok = imagepng($img, $dest);            break; // lossless
        default:     $ok = imagejpeg($img, $dest, $quality); break;
    }
    imagedestroy($img);
    return $ok ? array('url' => $url, 'width' => $w, 'height' => $h) : false;
}

// Watermarked previews are OFF by default. They stamp the artwork and cost real
// resolution on every product page, and they protect nothing: WordPress serves
// the untouched master from its own public uploads URL, which this site already
// publishes in og:image, twitter:image and the product's JSON-LD. Every product
// is also currently flagged downloadable by tools/enable-digital-downloads.php,
// so leaving this on watermarks the whole catalogue of physical prints.
// Re-enable once genuine download-only products exist, and only for those:
//   wp option update af_wm_enabled yes
function af_wm_enabled() {
    return apply_filters('af_wm_enabled', get_option('af_wm_enabled', 'no') === 'yes');
}

/**
 * Watermarks belong on products SOLD as files, not on the whole catalogue
 * (every product is flagged downloadable for the add-on option, so
 * is_downloadable() cannot be the gate). Digital-download categories and
 * their children are the honest signal.
 */
function af_wm_applies($product) {
    // Was its own copy of the slug list, and that copy had gone stale: it never
    // included digital-downloads-2, so on the one category page that sells
    // files the watermark gate answered "no" for every product on it.
    return af_is_digital_download($product);
}

// Swap gallery images for watermarked previews on downloadable products
add_filter('woocommerce_single_product_image_thumbnail_html', function($html, $attachment_id) {
    if (!is_product()) return $html;
    if (!af_wm_enabled()) return $html;
    $product = af_wc_product();
    if (!$product || !af_wm_applies($product)) return $html;
    $wm = af_wm_preview_url($attachment_id);
    if (!$wm) return $html;
    // Point every size variant, zoom target, and lightbox link at the preview.
    // This must cover the thumbnail sizes too: they appear in data-thumb-srcset,
    // so leaving them out published the un-watermarked original right next to
    // the preview that exists to protect it.
    $urls = array();
    foreach (array(
        'full', 'large', 'woocommerce_single', 'medium_large', 'medium',
        'woocommerce_thumbnail', 'woocommerce_gallery_thumbnail', 'thumbnail',
    ) as $size) {
        $s = wp_get_attachment_image_src($attachment_id, $size);
        if ($s && !empty($s[0])) $urls[$s[0]] = true;
    }
    $html = str_replace(array_keys($urls), $wm['url'], $html);
    $html = preg_replace('/\ssrcset="[^"]*"/', '', $html);
    $html = preg_replace('/\ssizes="[^"]*"/', '', $html);
    // Restate the dimensions to match the file actually being served. WooCommerce's
    // zoom magnifies to data-large_image_*, so the master's numbers here make it
    // upscale the preview and render a blurred crop instead of refusing to zoom.
    $html = preg_replace('/\sdata-large_image_width="\d+"/',  ' data-large_image_width="'  . $wm['width']  . '"', $html);
    $html = preg_replace('/\sdata-large_image_height="\d+"/', ' data-large_image_height="' . $wm['height'] . '"', $html);
    $html = preg_replace('/\swidth="\d+"/',  ' width="'  . $wm['width']  . '"', $html, 1);
    $html = preg_replace('/\sheight="\d+"/', ' height="' . $wm['height'] . '"', $html, 1);
    return $html;
}, 20, 2);

// ─────────────────────────────────────────────────────────────
// PHASE 24h — Review request in the "order completed" email.
// Every future buyer gets asked to review what they bought;
// star (aggregateRating) schema appears automatically once real
// reviews exist. No fake reviews — that's an FTC/Google violation.
// ─────────────────────────────────────────────────────────────
add_action('woocommerce_email_after_order_table', function ($order, $sent_to_admin, $plain_text, $email) {
    if ($sent_to_admin || !$email || $email->id !== 'customer_completed_order') return;
    if (!($order instanceof WC_Order)) return;
    $items = array_slice($order->get_items(), 0, 3);
    if (!$items) return;
    if ($plain_text) {
        echo "\n== How did we do? ==\n";
        echo "Reviews from customers like you help other art lovers choose with confidence.\n";
        foreach ($items as $item) {
            $pid = $item->get_product_id();
            $url = get_permalink($pid);
            if ($url) echo '- Review "' . $item->get_name() . '": ' . $url . "#reviews\n";
        }
        echo "\n";
        return;
    }
    echo '<div style="margin:24px 0;padding:18px 20px;border:1px solid #e5e0d8;border-radius:10px;background:#faf8f4;">';
    echo '<h3 style="margin:0 0 8px;font-size:16px;color:#1a1a1a;">How did we do?</h3>';
    echo '<p style="margin:0 0 12px;color:#555;font-size:14px;">Reviews from customers like you help other art lovers choose with confidence — it takes under a minute.</p>';
    foreach ($items as $item) {
        $pid = $item->get_product_id();
        $url = get_permalink($pid);
        if (!$url) continue;
        echo '<p style="margin:6px 0;"><a href="' . esc_url($url . '#reviews') . '" style="color:#c9a84c;font-weight:600;text-decoration:none;">&#9733; Review &ldquo;' . esc_html($item->get_name()) . '&rdquo;</a></p>';
    }
    echo '</div>';
}, 10, 4);

// ─────────────────────────────────────────────────────────────
// PHASE 24i — Organization schema enrichment for US entity SEO.
// Adds sameAs (real social profiles published in the footer),
// areaServed and currenciesAccepted. Filters Rank Math's existing
// Organization node rather than emitting a competing one.
//
// The postal address is intentionally NOT hard-coded: it activates
// only once the real address is stored in the `af_business_address`
// option (see tools/set-business-address.php). Never invent one —
// a wrong NAP is worse than none for local SEO.
// ─────────────────────────────────────────────────────────────
add_filter('rank_math/json_ld', function ($data, $jsonld) {
    foreach ($data as $key => $node) {
        if (!isset($node['@type'])) continue;
        $type = is_array($node['@type']) ? $node['@type'] : array($node['@type']);
        if (!in_array('Organization', $type, true)) continue;

        if (empty($node['sameAs'])) {
            $data[$key]['sameAs'] = array(
                'https://www.facebook.com/theartframer',
                'https://www.instagram.com/theartframer136/',
                'https://www.youtube.com/@theartframer136',
            );
        }
        if (empty($node['areaServed'])) {
            $data[$key]['areaServed'] = array(
                array('@type' => 'Country', 'name' => 'United States'),
                array('@type' => 'Country', 'name' => 'Canada'),
            );
        }
        if (empty($node['currenciesAccepted'])) {
            $data[$key]['currenciesAccepted'] = 'USD, CAD';
        }

        $addr = get_option('af_business_address', array());
        if (is_array($addr) && !empty($addr['street']) && !empty($addr['city'])
            && !empty($addr['region']) && !empty($addr['postal'])) {
            $data[$key]['address'] = array(
                '@type'           => 'PostalAddress',
                'streetAddress'   => $addr['street'],
                'addressLocality' => $addr['city'],
                'addressRegion'   => $addr['region'],
                'postalCode'      => $addr['postal'],
                'addressCountry'  => 'US',
            );
        }
    }
    return $data;
}, 20, 2);

/* ============================================================
   PHASE 15 — Gift Cards: purchasable product + code redemption
   Table {prefix}af_gift_cards holds code, balance, recipient.
   Buy: amount + recipient fields on the gift-card product page.
   Redeem: code field in cart/checkout applies balance as a discount;
   balance decrements on order completion (partial use supported).
   ============================================================ */

function af_gc_table() { global $wpdb; return $wpdb->prefix . 'af_gift_cards'; }
function af_gc_product_id() { return (int) get_option('af_gc_product_id', 0); }
function af_gc_amounts() { return array(25, 50, 100, 200); }

add_action('init', function() {
    if (get_option('af_gc_db_ver') === '1') return;
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $t = af_gc_table();
    dbDelta("CREATE TABLE {$t} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        code VARCHAR(24) NOT NULL,
        initial_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
        balance DECIMAL(10,2) NOT NULL DEFAULT 0,
        currency VARCHAR(6) NOT NULL DEFAULT 'USD',
        buyer_email VARCHAR(190) DEFAULT '',
        recipient_email VARCHAR(190) DEFAULT '',
        recipient_name VARCHAR(120) DEFAULT '',
        message TEXT,
        order_id BIGINT UNSIGNED DEFAULT 0,
        status VARCHAR(12) NOT NULL DEFAULT 'active',
        created_at DATETIME NOT NULL,
        used_at DATETIME DEFAULT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY code (code),
        KEY status (status)
    ) " . $wpdb->get_charset_collate() . ";");
    update_option('af_gc_db_ver', '1');
}, 6);

function af_gc_generate_code() {
    global $wpdb;
    $t = af_gc_table();
    do {
        $raw  = strtoupper(substr(str_replace(array('0','O','1','I'), '', strtoupper(wp_generate_password(24, false, false))), 0, 12));
        $code = 'TAF-' . substr($raw, 0, 4) . '-' . substr($raw, 4, 4) . '-' . substr($raw, 8, 4);
        $hit  = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$t} WHERE code = %s", $code));
    } while ($hit);
    return $code;
}

function af_gc_get($code) {
    global $wpdb;
    $code = strtoupper(trim($code));
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM " . af_gc_table() . " WHERE code = %s", $code));
}

// ── Product page: amount + recipient fields ──────────────────
add_action('woocommerce_before_add_to_cart_button', function() {
    $product = af_wc_product();
    if (!$product || $product->get_id() !== af_gc_product_id()) return;
    ?>
    <div class="af-gc-fields">
      <p class="af-gc-label">Choose an amount</p>
      <div class="af-gc-amounts">
        <?php foreach (af_gc_amounts() as $i => $a) : ?>
          <label class="af-gc-amt">
            <input type="radio" name="af_gc_amount" value="<?php echo esc_attr($a); ?>" <?php checked($i, 1); ?>>
            <span>$<?php echo esc_html($a); ?></span>
          </label>
        <?php endforeach; ?>
        <label class="af-gc-amt af-gc-amt-custom">
          <input type="radio" name="af_gc_amount" value="custom">
          <span>Custom</span>
        </label>
      </div>
      <p class="af-gc-custom-wrap" style="display:none;">
        <label>Custom amount (USD 10–1000)
          <input type="number" name="af_gc_custom" min="10" max="1000" step="1" value="75">
        </label>
      </p>
      <div class="af-gc-row">
        <label>Recipient name <input type="text" name="af_gc_rname" maxlength="120" placeholder="Who is it for?"></label>
        <label>Recipient email <input type="email" name="af_gc_remail" maxlength="190" placeholder="Leave blank to send it to yourself"></label>
      </div>
      <label>Personal message (optional)
        <textarea name="af_gc_message" rows="3" maxlength="300" placeholder="Happy birthday! Pick something beautiful for your wall."></textarea>
      </label>
      <p class="af-gc-note">🎁 Delivered by email within a few hours · never expires · works on every product</p>
    </div>
    <script>
    (function(){
      var wrap = document.querySelector('.af-gc-custom-wrap');
      document.querySelectorAll('input[name="af_gc_amount"]').forEach(function(r){
        r.addEventListener('change', function(){
          wrap.style.display = (document.querySelector('input[name="af_gc_amount"]:checked').value === 'custom') ? 'block' : 'none';
        });
      });
    })();
    </script>
    <?php
}, 20);

// ── Cart: carry gift-card data + price ───────────────────────
add_filter('woocommerce_add_cart_item_data', function($data, $pid) {
    if ((int) $pid !== af_gc_product_id()) return $data;
    $sel = isset($_POST['af_gc_amount']) ? sanitize_text_field(wp_unslash($_POST['af_gc_amount'])) : '50';
    if ($sel === 'custom') {
        $amt = isset($_POST['af_gc_custom']) ? (float) $_POST['af_gc_custom'] : 0;
        $amt = min(1000, max(10, $amt));
    } else {
        $amt = in_array((int) $sel, af_gc_amounts(), true) ? (float) $sel : 50.0;
    }
    $data['af_gc'] = array(
        'amount'  => $amt,
        'rname'   => isset($_POST['af_gc_rname'])   ? sanitize_text_field(wp_unslash($_POST['af_gc_rname'])) : '',
        'remail'  => isset($_POST['af_gc_remail'])  ? sanitize_email(wp_unslash($_POST['af_gc_remail'])) : '',
        'message' => isset($_POST['af_gc_message']) ? sanitize_textarea_field(wp_unslash($_POST['af_gc_message'])) : '',
    );
    $data['unique_key'] = md5(microtime() . wp_rand());
    return $data;
}, 10, 2);

add_action('woocommerce_before_calculate_totals', function($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;
    foreach ($cart->get_cart() as $item) {
        if (!empty($item['af_gc']['amount'])) $item['data']->set_price((float) $item['af_gc']['amount']);
    }
}, 25);

add_filter('woocommerce_get_item_data', function($data, $item) {
    if (!empty($item['af_gc'])) {
        $data[] = array('name' => 'Gift card value', 'value' => wc_price($item['af_gc']['amount']));
        if (!empty($item['af_gc']['rname']))  $data[] = array('name' => 'For', 'value' => esc_html($item['af_gc']['rname']));
        if (!empty($item['af_gc']['remail'])) $data[] = array('name' => 'Send to', 'value' => esc_html($item['af_gc']['remail']));
    }
    return $data;
}, 10, 2);

add_action('woocommerce_checkout_create_order_line_item', function($item, $key, $values) {
    if (!empty($values['af_gc'])) $item->add_meta_data('_af_gc', $values['af_gc']);
}, 10, 3);

// ── Issue codes when the order is paid ───────────────────────
function af_gc_issue_for_order($order_id) {
    $order = wc_get_order($order_id);
    if (!$order || $order->get_meta('_af_gc_issued')) return;
    global $wpdb;
    $issued = 0;
    foreach ($order->get_items() as $item) {
        $gc = $item->get_meta('_af_gc');
        if (empty($gc['amount'])) continue;
        for ($i = 0; $i < max(1, $item->get_quantity()); $i++) {
            $code = af_gc_generate_code();
            $wpdb->insert(af_gc_table(), array(
                'code'            => $code,
                'initial_amount'  => (float) $gc['amount'],
                'balance'         => (float) $gc['amount'],
                'currency'        => $order->get_currency(),
                'buyer_email'     => $order->get_billing_email(),
                'recipient_email' => !empty($gc['remail']) ? $gc['remail'] : $order->get_billing_email(),
                'recipient_name'  => isset($gc['rname']) ? $gc['rname'] : '',
                'message'         => isset($gc['message']) ? $gc['message'] : '',
                'order_id'        => $order_id,
                'status'          => 'active',
                'created_at'      => current_time('mysql'),
            ));
            $to   = !empty($gc['remail']) ? $gc['remail'] : $order->get_billing_email();
            $name = !empty($gc['rname']) ? $gc['rname'] : 'there';
            $body  = "Hi {$name},\n\n";
            $body .= trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()) . " has sent you a The Art Framer gift card!\n\n";
            $body .= "Gift card code: {$code}\n";
            $body .= "Value: " . strip_tags(wc_price($gc['amount'], array('currency' => $order->get_currency()))) . "\n\n";
            if (!empty($gc['message'])) $body .= "Their message:\n\"{$gc['message']}\"\n\n";
            $body .= "How to use it: shop at " . home_url('/shop/') . ", then enter the code in the \"Have a gift card?\" box at checkout.\n";
            $body .= "It never expires, and any unused balance stays on the card.\n\nEnjoy!\nThe Art Framer\n";
            wp_mail($to, '🎁 You have received a The Art Framer gift card', $body);
            $issued++;
        }
    }
    if ($issued) {
        $order->update_meta_data('_af_gc_issued', current_time('mysql'));
        $order->add_order_note(sprintf('%d gift card code(s) generated and emailed.', $issued));
        $order->save();
    }
}
add_action('woocommerce_payment_complete', 'af_gc_issue_for_order');
add_action('woocommerce_order_status_completed', 'af_gc_issue_for_order');
add_action('woocommerce_order_status_processing', 'af_gc_issue_for_order');

// ── Redemption ───────────────────────────────────────────────
function af_gc_applied() {
    if (!function_exists('WC') || !WC()->session) return null;
    $code = WC()->session->get('af_gc_code');
    if (!$code) return null;
    $gc = af_gc_get($code);
    if (!$gc || $gc->status !== 'active' || (float) $gc->balance <= 0) {
        WC()->session->set('af_gc_code', null);
        return null;
    }
    return $gc;
}

function af_gc_apply_handler() {
    check_ajax_referer('af_gc_apply', 'nonce');
    $code = isset($_POST['code']) ? strtoupper(sanitize_text_field(wp_unslash($_POST['code']))) : '';
    if ($code === 'REMOVE') {
        WC()->session->set('af_gc_code', null);
        wp_send_json_success(array('message' => 'Gift card removed.'));
    }
    // Codes cannot be guessed by trying many: ten wrong ones an hour per address.
    $rl = 'gc|' . (function_exists('af_visitor_ip') ? af_visitor_ip() : '');
    if (function_exists('af_rl_over') && af_rl_over($rl, 10, HOUR_IN_SECONDS, false)) {
        wp_send_json_error(array('message' => 'Too many gift card attempts — please try again in an hour, or contact us.'));
    }
    $gc = af_gc_get($code);
    if (!$gc && function_exists('af_rl_over')) af_rl_over($rl, 10, HOUR_IN_SECONDS);
    if (!$gc)                          wp_send_json_error(array('message' => 'That gift card code was not found — please check and try again.'));
    if ($gc->status !== 'active')      wp_send_json_error(array('message' => 'This gift card is no longer active.'));
    if ((float) $gc->balance <= 0)     wp_send_json_error(array('message' => 'This gift card has no remaining balance.'));
    WC()->session->set('af_gc_code', $gc->code);
    wp_send_json_success(array('message' => 'Gift card applied — balance ' . strip_tags(wc_price($gc->balance)) . '.'));
}
add_action('wp_ajax_af_gc_apply',        'af_gc_apply_handler');
add_action('wp_ajax_nopriv_af_gc_apply', 'af_gc_apply_handler');

// Apply the balance as a negative fee (never below zero, never on gift cards themselves)
add_action('woocommerce_cart_calculate_fees', function($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;
    $gc = af_gc_applied();
    if (!$gc) return;
    $eligible = 0.0;
    foreach ($cart->get_cart() as $item) {
        if (!empty($item['af_gc'])) continue; // can't buy gift cards with gift cards
        $eligible += (float) $item['line_total'] + (float) $item['line_tax'];
    }
    if ($eligible <= 0) return;
    // Delivery and the oversize fee are part of what the shopper pays, so the
    // card covers them too; it used to stop at the goods and leave delivery
    // to be paid another way (checkout audit, 30 Sep). The oversize fee is
    // added at priority 10, before this.
    $eligible += (float) $cart->get_shipping_total() + (float) $cart->get_shipping_tax();
    foreach ($cart->fees_api()->get_fees() as $fee) {
        if ((float) $fee->amount > 0 && strpos((string) $fee->name, 'Gift card (') !== 0) $eligible += (float) $fee->amount;
    }
    $use = min((float) $gc->balance, round($eligible, 2));
    if ($use > 0) $cart->add_fee('Gift card (' . $gc->code . ')', -$use, false);
}, 20);

// Record the gift card on the order when it is created; take the balance only
// once the order is confirmed. The balance used to be taken here, at order
// creation - before the card payment - and the code cleared from the session,
// so a declined card left the order unpaid with the gift card already spent
// and nothing to retry with (checkout audit, 30 Sep).
add_action('woocommerce_checkout_create_order', function($order) {
    $gc = af_gc_applied();
    if (!$gc) return;
    $used = 0.0;
    foreach ($order->get_fees() as $fee) {
        if (strpos($fee->get_name(), 'Gift card (') === 0) $used += abs((float) $fee->get_total());
    }
    if ($used <= 0) return;
    $order->update_meta_data('_af_gc_code', $gc->code);
    $order->update_meta_data('_af_gc_used', $used);
    $order->update_meta_data('_af_gc_pending', '1');   // orders placed before this change never carry it
}, 20);

/** Take the balance when the order is confirmed; give it back if the order then fails or is cancelled. */
add_action('woocommerce_order_status_changed', function ($order_id, $from, $to, $order) {
    if (!$order instanceof WC_Order || $order->get_meta('_af_gc_pending') !== '1') return;
    $code = (string) $order->get_meta('_af_gc_code');
    $gc   = $code !== '' ? af_gc_get($code) : null;
    if (!$gc) return;
    global $wpdb;
    $taken = (float) $order->get_meta('_af_gc_taken');
    if (in_array($to, array('processing', 'on-hold', 'completed'), true) && $taken <= 0) {
        $want = (float) $order->get_meta('_af_gc_used');
        $use  = min((float) $gc->balance, $want);
        // Only two orders racing for the same card can get here short; say so
        // on the order so the studio can collect the difference.
        $short = $want - $use > 0.005 ? sprintf(' SHORT by %s: the card did not hold the full discount given on this order.', strip_tags(wc_price($want - $use))) : '';
        if ($use <= 0) { $order->add_order_note('Gift card ' . $code . ' had no balance left to take.' . $short); return; }
        $new = max(0, (float) $gc->balance - $use);
        $wpdb->update(af_gc_table(), array('balance' => $new, 'status' => $new <= 0 ? 'used' : 'active', 'used_at' => current_time('mysql')), array('id' => $gc->id));
        $order->update_meta_data('_af_gc_taken', $use);
        $order->save_meta_data();
        $order->add_order_note(sprintf('Gift card %s redeemed: %s (remaining %s).', $code, strip_tags(wc_price($use)), strip_tags(wc_price($new))) . $short);
    } elseif (in_array($to, array('cancelled', 'failed', 'refunded'), true) && $taken > 0) {
        $new = (float) $gc->balance + $taken;
        $wpdb->update(af_gc_table(), array('balance' => $new, 'status' => 'active'), array('id' => $gc->id));
        $order->update_meta_data('_af_gc_taken', 0);
        $order->save_meta_data();
        $order->add_order_note(sprintf('Gift card %s: %s given back (order %s; balance now %s).', $code, strip_tags(wc_price($taken)), $to, strip_tags(wc_price($new))));
    }
}, 20, 4);

// The shopper's gift card leaves their session once the order is through,
// not before: a failed payment keeps it applied for the retry.
add_action('woocommerce_thankyou', function () {
    if (function_exists('WC') && WC()->session) WC()->session->set('af_gc_code', null);
});

// Redemption UI on cart + checkout
add_action('woocommerce_cart_totals_before_order_total', 'af_gc_redeem_box');
add_action('woocommerce_review_order_before_order_total', 'af_gc_redeem_box');
function af_gc_redeem_box() {
    static $done = false;
    if ($done) return;
    $done = true;
    $gc = af_gc_applied();
    ?>
    <tr class="af-gc-redeem-row"><td colspan="2">
      <div class="af-gc-redeem" data-ajax="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
           data-nonce="<?php echo esc_attr(wp_create_nonce('af_gc_apply')); ?>">
        <?php if ($gc) : ?>
          <p class="af-gc-on">🎁 Gift card <b><?php echo esc_html($gc->code); ?></b> applied —
            balance <?php echo wp_kses_post(wc_price($gc->balance)); ?>
            <button type="button" class="af-gc-remove">Remove</button></p>
        <?php else : ?>
          <label class="af-gc-redeem-label">🎁 Have a gift card?</label>
          <div class="af-gc-redeem-row-inner">
            <input type="text" class="af-gc-input" placeholder="TAF-XXXX-XXXX-XXXX" autocomplete="off">
            <button type="button" class="af-gc-apply">Apply</button>
          </div>
        <?php endif; ?>
        <p class="af-gc-msg" role="status" aria-live="polite" style="display:none;"></p>
      </div>
    </td></tr>
    <script>
    (function(){
      // Bound once on the document, not on the box. WooCommerce redraws the
      // cart totals and the checkout review table over AJAX and drops inline
      // scripts when it does, so buttons bound to the old box went dead after
      // any cart update. And the box sits inside the checkout form: Enter in
      // it submitted the form and placed the order without the gift card.
      if (window.__afGcBound) return; window.__afGcBound = true;
      function send(box, code){
        var msg = box.querySelector('.af-gc-msg'), fd = new FormData();
        fd.append('action', 'af_gc_apply'); fd.append('nonce', box.dataset.nonce); fd.append('code', code);
        fetch(box.dataset.ajax, { method:'POST', credentials:'same-origin', body: fd })
          .then(function(r){ return r.json(); })
          .then(function(res){
            msg.style.display = 'block';
            msg.className = 'af-gc-msg ' + (res.success ? 'ok' : 'err');
            msg.textContent = res.data.message;
            if (res.success) location.reload();
          });
      }
      document.addEventListener('click', function(e){
        var a = e.target.closest('.af-gc-apply, .af-gc-remove'); if (!a) return;
        var box = a.closest('.af-gc-redeem'); if (!box) return;
        e.preventDefault();
        if (a.classList.contains('af-gc-remove')) send(box, 'REMOVE');
        else send(box, (box.querySelector('.af-gc-input') || {}).value ? box.querySelector('.af-gc-input').value.trim() : '');
      });
      document.addEventListener('keydown', function(e){
        if (e.key !== 'Enter' || !e.target.classList || !e.target.classList.contains('af-gc-input')) return;
        e.preventDefault();
        var box = e.target.closest('.af-gc-redeem'); if (box) send(box, e.target.value.trim());
      }, true);
    })();
    </script>
    <?php
}

// ── Admin: Gift Cards list ───────────────────────────────────
add_action('admin_menu', function() {
    add_menu_page('Gift Cards', 'Gift Cards', 'manage_woocommerce', 'af-gift-cards', 'af_gc_admin_page', 'dashicons-tickets-alt', 27);
});
function af_gc_admin_page() {
    if (!current_user_can('manage_woocommerce')) return;
    global $wpdb;
    $t = af_gc_table();
    if (isset($_GET['gc_action'], $_GET['id'], $_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'af_gc_' . (int) $_GET['id'])) {
        $id = (int) $_GET['id'];
        if ($_GET['gc_action'] === 'void')   $wpdb->update($t, array('status' => 'void'),   array('id' => $id));
        if ($_GET['gc_action'] === 'active') $wpdb->update($t, array('status' => 'active'), array('id' => $id));
        echo '<script>location.replace("' . esc_url_raw(admin_url('admin.php?page=af-gift-cards')) . '");</script>';
        return;
    }
    $rows  = $wpdb->get_results("SELECT * FROM {$t} ORDER BY id DESC LIMIT 100");
    $total = (float) $wpdb->get_var("SELECT SUM(balance) FROM {$t} WHERE status='active'");
    echo '<div class="wrap"><h1>Gift Cards</h1>';
    echo '<p>Outstanding active balance: <b>' . wp_kses_post(wc_price($total ?: 0)) . '</b></p>';
    if (!$rows) { echo '<p><em>No gift cards issued yet.</em></p></div>'; return; }
    echo '<table class="widefat striped"><thead><tr><th>Code</th><th>Value</th><th>Balance</th><th>Recipient</th><th>Order</th><th>Issued</th><th>Status</th><th>Action</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        $n = wp_create_nonce('af_gc_' . $r->id);
        $base = admin_url('admin.php?page=af-gift-cards&id=' . $r->id . '&_wpnonce=' . $n);
        echo '<tr><td><code>' . esc_html($r->code) . '</code></td>';
        echo '<td>' . wp_kses_post(wc_price($r->initial_amount)) . '</td>';
        echo '<td><b>' . wp_kses_post(wc_price($r->balance)) . '</b></td>';
        echo '<td>' . esc_html($r->recipient_name ? $r->recipient_name . ' <' . $r->recipient_email . '>' : $r->recipient_email) . '</td>';
        echo '<td>' . ($r->order_id ? '<a href="' . esc_url(admin_url('post.php?post=' . $r->order_id . '&action=edit')) . '">#' . (int) $r->order_id . '</a>' : '—') . '</td>';
        echo '<td>' . esc_html(mysql2date('M j, Y', $r->created_at)) . '</td>';
        echo '<td>' . esc_html($r->status) . '</td>';
        echo '<td>' . ($r->status === 'void'
            ? '<a href="' . esc_url($base . '&gc_action=active') . '">Reactivate</a>'
            : '<a href="' . esc_url($base . '&gc_action=void') . '" style="color:#b32d2e;">Void</a>') . '</td></tr>';
    }
    echo '</tbody></table></div>';
}

/* ============================================================
   PHASE 16 — country selector, infinite scroll, messages inbox, RMA
   ============================================================ */

// ── 16a. Flag-based country selector (spec §3 Layer 1) ───────
function af_countries() {
    return array(
        // the note follows af_shipping_copy(); it used to promise no-cost delivery
        // in four states, which is what the currency strip showed on the shop,
        // cart, about, FAQ and every product page
        'US' => array('flag' => '🇺🇸', 'name' => 'United States', 'cur' => 'USD',
                      'note' => function_exists('af_shipping_copy') ? af_shipping_copy()['short'] : 'Shipping cost shown at checkout'),
        'CA' => array('flag' => '🇨🇦', 'name' => 'Canada',        'cur' => 'CAD', 'note' => 'Shipping calculated at checkout'),
        'GB' => array('flag' => '🇬🇧', 'name' => 'United Kingdom','cur' => 'USD', 'note' => 'International — contact us for a quote'),
        'AU' => array('flag' => '🇦🇺', 'name' => 'Australia',     'cur' => 'USD', 'note' => 'International — contact us for a quote'),
        'IN' => array('flag' => '🇮🇳', 'name' => 'India',         'cur' => 'USD', 'note' => 'International — contact us for a quote'),
    );
}
function af_active_country() {
    $c = isset($_COOKIE['af_country']) ? strtoupper(sanitize_text_field(wp_unslash($_COOKIE['af_country']))) : '';
    return array_key_exists($c, af_countries()) ? $c : 'US';
}

add_shortcode('af_country_selector', function() {
    $list = af_countries();
    $cur  = af_active_country();
    $sel  = $list[$cur];
    ob_start(); ?>
<div class="af-cty" id="afCty">
  <button type="button" class="af-cty-btn" aria-haspopup="true" aria-expanded="false"
          title="Choose the country you are shipping to">
    <span class="af-cty-flag"><?php echo $sel['flag']; ?></span>
    <svg class="af-cty-globe" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><path d="M2 12h20"></path><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
    <span class="af-cty-label">Ship to</span>
    <span class="af-cty-code"><?php echo esc_html($cur); ?></span>
    <span class="af-cty-caret">▾</span>
  </button>
  <div class="af-cty-menu" role="menu">
    <p class="af-cty-head">Ship to</p>
    <?php foreach ($list as $code => $c) : ?>
      <button type="button" class="af-cty-item<?php echo $code === $cur ? ' on' : ''; ?>" role="menuitem"
              data-code="<?php echo esc_attr($code); ?>" data-cur="<?php echo esc_attr($c['cur']); ?>">
        <span class="af-cty-flag"><?php echo $c['flag']; ?></span>
        <span class="af-cty-nm"><?php echo esc_html($c['name']); ?><small><?php echo wp_kses_post($c['note']); ?></small></span>
        <span class="af-cty-cur"><?php echo esc_html($c['cur']); ?></span>
      </button>
    <?php endforeach; ?>
    <p class="af-cty-foot">Shipping elsewhere? <a href="/contact/">Ask for a quote →</a></p>
  </div>
</div>
<script id="af-country-selector" src="<?php echo esc_url(af_static_url('country-selector.js')); ?>" data-no-optimize="1"></script>
<style>
/* CRITICAL: the dropdown must stay CLOSED until its button is clicked, in any
   context it is relocated into. In the relocated header the base
   `.af-cty-menu{display:none}` was being overridden, leaving the panel open
   and covering the page — force it hidden unless the wrapper has .open. */
.af-cty .af-cty-menu{display:none !important;}
.af-cty.open .af-cty-menu{display:block !important;}

/* Match the My Account dropdown: a clean SOLID WHITE panel with dark text.
   Literal hex only — the CSS custom properties (--taf-dark/--taf-gold) do not
   resolve in the relocated Elementor header context, which is what made the
   text invisible before. */
.af-cty .af-cty-menu{background:#ffffff !important;border:1px solid #e6e2d8 !important;
  border-radius:6px !important;box-shadow:0 12px 32px rgba(0,0,0,.16) !important;
  opacity:1 !important;overflow:hidden !important;}
.af-cty .af-cty-head{color:#8a8a8a !important;background:#ffffff !important;}
.af-cty .af-cty-item{background:#ffffff !important;color:#1e1e1e !important;}
.af-cty .af-cty-item:hover{background:#f5f1e6 !important;}
.af-cty .af-cty-item.on{background:#f5f1e6 !important;}
.af-cty .af-cty-nm{color:#1e1e1e !important;}
.af-cty .af-cty-nm small{color:#777777 !important;}
.af-cty .af-cty-cur{color:#8a6d1f !important;}
.af-cty .af-cty-foot{color:#777777 !important;background:#ffffff !important;
  border-top:1px solid #e6e2d8 !important;}
.af-cty .af-cty-foot a{color:#8a6d1f !important;}

/* Relocated next to the social icons: align on the row and keep the dropdown
   anchored to the button rather than the old utility-bar position. */
.af-cty--inline{display:inline-flex !important;align-items:center;margin:0 6px;vertical-align:middle;
  flex:0 0 auto;white-space:nowrap;}
.af-cty--inline .af-cty-menu{right:0;left:auto;}
/* Match the plain-text look of the other header items (English, CA$ CAD,
   About Us …): drop the boxed border and inherit the row's font and colour
   instead of the utility-bar's boxed style. */
.af-cty--inline .af-cty-btn{border:none !important;background:transparent !important;
  color:inherit !important;font:inherit !important;font-weight:600 !important;
  line-height:1;white-space:nowrap;padding:0 !important;gap:4px;
  text-transform:none;letter-spacing:normal;cursor:pointer;}
.af-cty--inline .af-cty-btn:hover{color:var(--taf-gold) !important;border:none !important;}
.af-cty--inline .af-cty-code{font:inherit !important;font-weight:600 !important;}
.af-cty--inline .af-cty-caret{font-size:.8em;opacity:.75;}
/* On Windows/Chrome the flag emoji renders as its letters (e.g. "GB"), which
   next to the code reads as "GB GB". Drop the flag in the inline placement so
   it shows just the code + caret, and it's narrower — helping the one-line fit. */
.af-cty--inline .af-cty-flag{display:none !important;}
/* The chip read as faint grey "GB" on a dark bar: too low-contrast to notice
   and too terse to understand. Give it readable colour, a quiet pill outline so
   it looks clickable, a globe mark and the words "Ship to" — the label folds
   away on narrow screens where the row has no room for it. */
.af-cty--inline .af-cty-btn{
  color:#f4efe3 !important;
  padding:4px 10px !important;
  border:1px solid rgba(244,239,227,.35) !important;
  border-radius:999px !important;
  background:rgba(255,255,255,.07) !important;
  gap:6px !important;
  transition:border-color .2s,background .2s,color .2s;}
.af-cty--inline .af-cty-btn:hover,
.af-cty--inline .af-cty-btn:focus-visible{
  color:#fff !important;
  border-color:var(--taf-gold,#c9a84c) !important;
  background:rgba(201,168,76,.22) !important;}
.af-cty--inline .af-cty-globe{width:14px;height:14px;flex:0 0 auto;opacity:.9;}
.af-cty--inline .af-cty-label{font-weight:500;opacity:.85;}
.af-cty--inline .af-cty-code{letter-spacing:.02em;}
.af-cty--inline .af-cty-caret{opacity:.9;}
@media (max-width:1100px){
  .af-cty--inline .af-cty-label{display:none;}
  .af-cty--inline .af-cty-btn{padding:4px 8px !important;}
}
/* Last-resort corner placement if the contact row can't be found. */
.af-cty--fallback{position:fixed !important;top:8px;right:14px;z-index:100000;
  background:rgba(20,20,20,.9);border:1px solid rgba(201,168,76,.5);border-radius:6px;
  padding:2px 6px;}
.af-cty--fallback .af-cty-code,.af-cty--fallback .af-cty-caret{color:#e8e2cf;}
</style>
<?php return ob_get_clean();
});

// ── 16b. Infinite scroll on shop/category (spec §6) ──────────
add_action('wp_footer', function() {
    if (!function_exists('is_shop')) return;
    if (!is_shop() && !is_product_category() && !is_product_tag()) return;
    ?>
<script>
(function(){
  var loading = false, done = false;
  function nextUrl(){
    var n = document.querySelector('.woocommerce-pagination a.next, .next.page-numbers, a.next');
    return n ? n.href : null;
  }
  function grid(){
    return document.querySelector('ul.products') ||
           document.querySelector('.elementor-widget-wc-archive-products ul') ||
           document.querySelector('.products');
  }
  var g = grid();
  if (!g || !nextUrl()) return;

  var bar = document.createElement('div');
  bar.className = 'af-inf-bar';
  bar.innerHTML = '<button type="button" class="af-inf-btn">Load More Artworks</button>' +
                  '<span class="af-inf-status" style="display:none;">Loading more artworks…</span>';
  (g.parentElement || document.body).insertBefore(bar, g.nextSibling);
  var btn = bar.querySelector('.af-inf-btn'), status = bar.querySelector('.af-inf-status');

  function load(){
    if (loading || done) return;
    var url = nextUrl();
    if (!url) { done = true; bar.innerHTML = '<span class="af-inf-end">✦ You have seen every artwork in this collection ✦</span>'; return; }
    loading = true;
    btn.style.display = 'none'; status.style.display = 'inline';
    fetch(url, { credentials: 'same-origin' })
      .then(function(r){ return r.text(); })
      .then(function(html){
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var ng = doc.querySelector('ul.products') || doc.querySelector('.products');
        var items = ng ? ng.children : [];
        var gnow = grid();
        Array.prototype.slice.call(items).forEach(function(li){ gnow.appendChild(document.importNode(li, true)); });
        var oldPag = document.querySelector('.woocommerce-pagination'),
            newPag = doc.querySelector('.woocommerce-pagination');
        if (oldPag && newPag) oldPag.replaceWith(document.importNode(newPag, true));
        else if (oldPag) oldPag.remove();
        // hide native pagination; keep it in DOM as the next-URL source
        var p = document.querySelector('.woocommerce-pagination');
        if (p) p.style.display = 'none';
        loading = false;
        status.style.display = 'none';
        if (nextUrl()) { btn.style.display = 'inline-block'; }
        else { done = true; bar.innerHTML = '<span class="af-inf-end">✦ You have seen every artwork in this collection ✦</span>'; }
        document.dispatchEvent(new Event('af_products_appended'));
        if (window.jQuery) jQuery(document.body).trigger('wc_fragments_refreshed');
      })
      .catch(function(){ loading = false; status.style.display = 'none'; btn.style.display = 'inline-block'; });
  }
  btn.addEventListener('click', load);
  var pag = document.querySelector('.woocommerce-pagination');
  if (pag) pag.style.display = 'none';

  // Auto-load when the bar scrolls into view (after the first manual click)
  var auto = false;
  btn.addEventListener('click', function(){ auto = true; });
  if ('IntersectionObserver' in window) {
    new IntersectionObserver(function(entries){
      if (entries[0].isIntersecting && auto) load();
    }, { rootMargin: '400px' }).observe(bar);
  }
})();
</script>
<?php }, 40);

// ── 16c. My Account: Messages inbox (spec §3) ────────────────
// Threads live in the af_contact_messages table (direction in/out).
add_action('init', function() {
    if (get_option('af_contact_db_ver') === '2') return;
    global $wpdb;
    $t = af_contact_table();
    foreach (array(
        'user_id'   => "ALTER TABLE {$t} ADD COLUMN user_id BIGINT UNSIGNED DEFAULT 0",
        'parent_id' => "ALTER TABLE {$t} ADD COLUMN parent_id BIGINT UNSIGNED DEFAULT 0",
        'direction' => "ALTER TABLE {$t} ADD COLUMN direction VARCHAR(4) DEFAULT 'in'",
    ) as $col => $sql) {
        $has = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$t} LIKE %s", $col));
        if (!$has) $wpdb->query($sql);
    }
    update_option('af_contact_db_ver', '2');
}, 7);

add_action('init', function() { add_rewrite_endpoint('messages', EP_ROOT | EP_PAGES); }, 8);
add_action('init', function() { add_rewrite_endpoint('returns',  EP_ROOT | EP_PAGES); }, 8);

add_filter('woocommerce_account_menu_items', function($items) {
    $new = array();
    foreach ($items as $k => $v) {
        $new[$k] = $v;
        if ($k === 'orders') {
            $new['messages'] = 'Messages';
            $new['returns']  = 'Returns';
        }
    }
    if (!isset($new['messages'])) { $new['messages'] = 'Messages'; $new['returns'] = 'Returns'; }
    return $new;
});

add_action('woocommerce_account_messages_endpoint', function() {
    $uid   = get_current_user_id();
    $user  = wp_get_current_user();
    global $wpdb;
    $t = af_contact_table();
    // The account's own threads only. Matching on the email address as well
    // showed anyone who registered with a guest's address that guest's
    // contact-form messages and the studio's replies (security audit, 12 Oct);
    // contact-form threads stay in wp-admin, and replies to them go by email.
    $threads = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$t} WHERE user_id = %d AND user_id > 0 AND (parent_id = 0 OR parent_id IS NULL)
         ORDER BY id DESC LIMIT 25", $uid
    ));
    echo '<div class="taf-page" style="padding:0;">';
    echo '<p>Messages between you and our studio team. Replies also arrive by email at <b>' . esc_html($user->user_email) . '</b>.</p>';
    echo '<div class="af-msg-new taf-card" style="margin:18px 0 26px;">';
    echo '<h3 style="margin-top:0;">Send a new message</h3>';
    echo '<form class="taf-form" method="post" style="box-shadow:none;padding:0;border:0;background:transparent;">';
    wp_nonce_field('af_msg_send', 'af_msg_nonce');
    echo '<label>Subject<input type="text" name="af_msg_subject" required maxlength="120" placeholder="What is this about?"></label>';
    echo '<label>Message<textarea name="af_msg_body" rows="4" required maxlength="3000" placeholder="How can we help?"></textarea></label>';
    echo '<button type="submit" class="taf-form-submit" name="af_msg_send" value="1">Send Message</button>';
    echo '</form></div>';
    if (!$threads) {
        echo '<div class="taf-card" style="text-align:center;"><span class="taf-ico">💬</span><h3>No messages yet</h3><p>Send us a message above — we usually reply within 24 hours.</p></div>';
    } else {
        foreach ($threads as $th) {
            $replies = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$t} WHERE parent_id = %d ORDER BY id ASC", $th->id));
            echo '<div class="af-msg-thread taf-card" style="margin-bottom:16px;">';
            echo '<h3 style="margin-top:0;">' . esc_html($th->subject ?: 'Message') . '</h3>';
            echo '<div class="af-msg-bubble you"><b>You</b> · ' . esc_html(mysql2date('M j, g:ia', $th->created_at)) . '<p>' . nl2br(esc_html($th->message)) . '</p></div>';
            foreach ($replies as $r) {
                $cls = $r->direction === 'out' ? 'studio' : 'you';
                $who = $r->direction === 'out' ? 'The Art Framer' : 'You';
                echo '<div class="af-msg-bubble ' . $cls . '"><b>' . $who . '</b> · ' . esc_html(mysql2date('M j, g:ia', $r->created_at)) . '<p>' . nl2br(esc_html($r->message)) . '</p></div>';
            }
            echo '<form method="post" class="af-msg-reply">';
            wp_nonce_field('af_msg_send', 'af_msg_nonce');
            echo '<input type="hidden" name="af_msg_parent" value="' . (int) $th->id . '">';
            echo '<textarea name="af_msg_body" rows="2" required placeholder="Write a reply…"></textarea>';
            echo '<button type="submit" class="taf-form-submit" name="af_msg_send" value="1">Reply</button>';
            echo '</form></div>';
        }
    }
    echo '</div>';
});

// Handle customer message/reply submissions
add_action('template_redirect', function() {
    if (empty($_POST['af_msg_send']) || !is_user_logged_in()) return;
    if (!isset($_POST['af_msg_nonce']) || !wp_verify_nonce($_POST['af_msg_nonce'], 'af_msg_send')) return;
    $user = wp_get_current_user();
    $body = sanitize_textarea_field(wp_unslash($_POST['af_msg_body'] ?? ''));
    if (mb_strlen($body) < 2) { wc_add_notice('Please write a message.', 'error'); return; }
    $parent  = (int) ($_POST['af_msg_parent'] ?? 0);
    $subject = $parent ? 'Re: thread #' . $parent : sanitize_text_field(wp_unslash($_POST['af_msg_subject'] ?? 'Message'));
    global $wpdb;
    // A reply goes only into a thread this account started (any thread id was
    // accepted before), and an account sends at most 10 messages an hour.
    if ($parent && (int) $wpdb->get_var($wpdb->prepare("SELECT user_id FROM " . af_contact_table() . " WHERE id = %d AND (parent_id = 0 OR parent_id IS NULL)", $parent)) !== (int) $user->ID) {
        wc_add_notice('That conversation could not be found.', 'error'); return;
    }
    $rl = 'af_msg_rl_' . (int) $user->ID;
    if ((int) get_transient($rl) >= 10) { wc_add_notice('Too many messages in an hour — please try again later.', 'error'); return; }
    set_transient($rl, (int) get_transient($rl) + 1, HOUR_IN_SECONDS);
    $body    = mb_substr($body, 0, 3000);
    $subject = mb_substr($subject, 0, 120);
    $wpdb->insert(af_contact_table(), array(
        'created_at' => current_time('mysql'),
        'name'       => $user->display_name,
        'email'      => $user->user_email,
        'phone'      => '',
        'subject'    => $subject,
        'message'    => $body,
        'ip'         => af_visitor_ip(),
        'status'     => 'new',
        'user_id'    => $user->ID,
        'parent_id'  => $parent,
        'direction'  => 'in',
    ));
    wp_mail(get_option('admin_email'), '[The Art Framer] Customer message: ' . $subject,
        "From: {$user->display_name} <{$user->user_email}>\n\n{$body}\n\nReply in wp-admin → Contact Messages.");
    wc_add_notice('Message sent — we usually reply within 24 hours.', 'success');
    wp_safe_redirect(wc_get_account_endpoint_url('messages'));
    exit;
});

// Admin: reply to a customer message (adds an 'out' message + emails them)
add_action('admin_post_af_msg_reply', function() {
    if (!current_user_can('manage_options') && !current_user_can('af_view_contact_messages')) wp_die('Denied');
    check_admin_referer('af_msg_reply');
    $parent = (int) ($_POST['parent_id'] ?? 0);
    $body   = sanitize_textarea_field(wp_unslash($_POST['reply'] ?? ''));
    global $wpdb;
    $t   = af_contact_table();
    $src = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id = %d", $parent));
    if ($src && $body !== '') {
        $wpdb->insert($t, array(
            'created_at' => current_time('mysql'),
            'name'       => 'The Art Framer',
            'email'      => $src->email,
            'subject'    => 'Re: ' . $src->subject,
            'message'    => $body,
            'status'     => 'read',
            'user_id'    => (int) $src->user_id,
            'parent_id'  => $parent,
            'direction'  => 'out',
        ));
        $wpdb->update($t, array('status' => 'read'), array('id' => $parent));
        wp_mail($src->email, 'Re: ' . $src->subject . ' — The Art Framer',
            "Hi " . $src->name . ",\n\n{$body}\n\n— The Art Framer\nView the conversation: " . wc_get_account_endpoint_url('messages'));
    }
    wp_safe_redirect(admin_url('admin.php?page=af-contact-messages'));
    exit;
});

// ── 16d. Self-service RMA / returns portal (spec §12) ────────
function af_rma_table() { global $wpdb; return $wpdb->prefix . 'af_rma_requests'; }

add_action('init', function() {
    if (get_option('af_rma_db_ver') === '1') return;
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta("CREATE TABLE " . af_rma_table() . " (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        order_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        email VARCHAR(190) DEFAULT '',
        reason VARCHAR(60) NOT NULL,
        resolution VARCHAR(20) NOT NULL DEFAULT 'replacement',
        details TEXT,
        status VARCHAR(16) NOT NULL DEFAULT 'pending',
        admin_note TEXT,
        created_at DATETIME NOT NULL,
        updated_at DATETIME DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY order_id (order_id),
        KEY status (status)
    ) " . $wpdb->get_charset_collate() . ";");
    update_option('af_rma_db_ver', '1');
}, 9);

function af_rma_reasons() {
    return array(
        'damaged'   => 'Arrived damaged',
        'defect'    => 'Printing or framing defect',
        'wrong'     => 'Wrong item delivered',
        'not_as'    => 'Not as described',
        'other'     => 'Something else',
    );
}

add_action('woocommerce_account_returns_endpoint', function() {
    $uid = get_current_user_id();
    global $wpdb;
    $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . af_rma_table() . " WHERE user_id = %d ORDER BY id DESC", $uid));
    $orders = wc_get_orders(array('customer_id' => $uid, 'limit' => 20, 'status' => array('processing', 'completed')));
    echo '<div class="taf-page" style="padding:0;">';
    echo '<p>Request a replacement or refund for an order. See our <a href="/returns-exchanges/">Returns &amp; Exchanges</a> policy — damaged items never need to be shipped back.</p>';

    if ($rows) {
        echo '<h3>Your Requests</h3><table class="taf-table"><thead><tr><th>#</th><th>Order</th><th>Reason</th><th>Resolution</th><th>Status</th><th>Submitted</th></tr></thead><tbody>';
        $reasons = af_rma_reasons();
        foreach ($rows as $r) {
            $badge = array('pending' => '#8a6d1f', 'approved' => '#256d2c', 'rejected' => '#a13232', 'resolved' => '#256d2c');
            echo '<tr><td>RMA-' . (int) $r->id . '</td><td>#' . (int) $r->order_id . '</td>';
            echo '<td>' . esc_html($reasons[$r->reason] ?? $r->reason) . '</td>';
            echo '<td>' . esc_html(ucfirst($r->resolution)) . '</td>';
            echo '<td><b style="color:' . esc_attr($badge[$r->status] ?? '#555') . ';">' . esc_html(ucfirst($r->status)) . '</b>';
            if ($r->admin_note) echo '<br><small>' . esc_html($r->admin_note) . '</small>';
            echo '</td><td>' . esc_html(mysql2date('M j, Y', $r->created_at)) . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    echo '<h3 style="margin-top:26px;">Start a New Request</h3>';
    if (!$orders) {
        echo '<div class="taf-card"><span class="taf-ico">📦</span><h3>No eligible orders</h3><p>Returns can be requested on paid orders. <a href="/shop/">Browse the collection →</a></p></div></div>';
        return;
    }
    echo '<form method="post" class="taf-form">';
    wp_nonce_field('af_rma_submit', 'af_rma_nonce');
    echo '<div class="taf-form-row">';
    echo '<label>Order *<select name="af_rma_order" required>';
    foreach ($orders as $o) {
        echo '<option value="' . (int) $o->get_id() . '">#' . esc_html($o->get_order_number()) . ' — ' . esc_html(wc_format_datetime($o->get_date_created(), 'M j, Y')) . ' — ' . wp_strip_all_tags($o->get_formatted_order_total()) . '</option>';
    }
    echo '</select></label>';
    echo '<label>Reason *<select name="af_rma_reason" required>';
    foreach (af_rma_reasons() as $k => $v) echo '<option value="' . esc_attr($k) . '">' . esc_html($v) . '</option>';
    echo '</select></label></div>';
    echo '<label>Preferred resolution *<select name="af_rma_resolution" required><option value="replacement">Free replacement</option><option value="refund">Refund</option><option value="advice">Just need advice</option></select></label>';
    echo '<label>Tell us what happened *<textarea name="af_rma_details" rows="4" required maxlength="2000" placeholder="Describe the issue. For damage, mention which part is affected — we may ask for photos by email."></textarea></label>';
    echo '<button type="submit" class="taf-form-submit" name="af_rma_submit" value="1">Submit Request</button>';
    echo '</form></div>';
});

add_action('template_redirect', function() {
    if (empty($_POST['af_rma_submit']) || !is_user_logged_in()) return;
    if (!isset($_POST['af_rma_nonce']) || !wp_verify_nonce($_POST['af_rma_nonce'], 'af_rma_submit')) return;
    $user  = wp_get_current_user();
    if (function_exists('af_rl_over') && af_rl_over('rma|' . (int) $user->ID, 5, HOUR_IN_SECONDS)) {
        wc_add_notice('You have sent several return requests in the last hour — please wait a little, or email us.', 'error');
        return;
    }
    $oid   = (int) ($_POST['af_rma_order'] ?? 0);
    $order = wc_get_order($oid);
    if (!$order || (int) $order->get_customer_id() !== (int) $user->ID) {
        wc_add_notice('That order could not be verified.', 'error');
        return;
    }
    global $wpdb;
    $wpdb->insert(af_rma_table(), array(
        'order_id'   => $oid,
        'user_id'    => $user->ID,
        'email'      => $user->user_email,
        'reason'     => sanitize_text_field(wp_unslash($_POST['af_rma_reason'] ?? 'other')),
        'resolution' => sanitize_text_field(wp_unslash($_POST['af_rma_resolution'] ?? 'replacement')),
        'details'    => sanitize_textarea_field(wp_unslash($_POST['af_rma_details'] ?? '')),
        'status'     => 'pending',
        'created_at' => current_time('mysql'),
    ));
    $rid = (int) $wpdb->insert_id;
    $order->add_order_note(sprintf('Customer opened return request RMA-%d.', $rid));
    wp_mail(get_option('admin_email'), "[The Art Framer] New return request RMA-{$rid} (order #{$oid})",
        "Customer: {$user->display_name} <{$user->user_email}>\nOrder: #{$oid}\nReason: " . sanitize_text_field($_POST['af_rma_reason'] ?? '') .
        "\nWants: " . sanitize_text_field($_POST['af_rma_resolution'] ?? '') . "\n\n" . sanitize_textarea_field($_POST['af_rma_details'] ?? '') .
        "\n\nManage: " . admin_url('admin.php?page=af-returns'));
    wp_mail($user->user_email, "Return request received — RMA-{$rid}",
        "Hi {$user->display_name},\n\nWe've received your return request (RMA-{$rid}) for order #{$oid} and will review it within one business day.\n\nTrack it here: " . wc_get_account_endpoint_url('returns') . "\n\n— The Art Framer");
    wc_add_notice('Return request submitted — we will review it within one business day.', 'success');
    wp_safe_redirect(wc_get_account_endpoint_url('returns'));
    exit;
});

// Admin: RMA dashboard
add_action('admin_menu', function() {
    global $wpdb;
    $pending = (int) $wpdb->get_var("SELECT COUNT(*) FROM " . af_rma_table() . " WHERE status='pending'");
    $badge   = $pending ? " <span class='awaiting-mod'>{$pending}</span>" : '';
    add_menu_page('Returns (RMA)', 'Returns (RMA)' . $badge, 'manage_woocommerce', 'af-returns', 'af_rma_admin_page', 'dashicons-undo', 28);
});
function af_rma_admin_page() {
    if (!current_user_can('manage_woocommerce')) return;
    global $wpdb;
    $t = af_rma_table();
    if (isset($_POST['af_rma_admin_nonce']) && wp_verify_nonce($_POST['af_rma_admin_nonce'], 'af_rma_admin')) {
        $id     = (int) $_POST['rma_id'];
        $status = sanitize_text_field($_POST['status']);
        $note   = sanitize_textarea_field(wp_unslash($_POST['admin_note'] ?? ''));
        $row    = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id = %d", $id));
        $wpdb->update($t, array('status' => $status, 'admin_note' => $note, 'updated_at' => current_time('mysql')), array('id' => $id));
        if ($row && $row->email) {
            wp_mail($row->email, "Update on your return request RMA-{$id}",
                "Your return request RMA-{$id} is now: " . strtoupper($status) . "\n\n" . ($note ?: '') .
                "\n\nDetails: " . wc_get_account_endpoint_url('returns') . "\n\n— The Art Framer");
        }
        do_action('af_rma_admin_saved', $id);
        echo '<div class="notice notice-success"><p>RMA-' . $id . ' updated and the customer was emailed.</p></div>';
    }
    $rows    = $wpdb->get_results("SELECT * FROM {$t} ORDER BY FIELD(status,'pending','approved','rejected','resolved'), id DESC LIMIT 100");
    $reasons = af_rma_reasons();
    echo '<div class="wrap"><h1>Returns (RMA)</h1>';
    if (!$rows) { echo '<p><em>No return requests yet.</em></p></div>'; return; }
    echo '<table class="widefat striped"><thead><tr><th>RMA</th><th>Order</th><th>Customer</th><th>Reason</th><th>Wants</th><th>Details</th><th>Status</th><th>Action</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        echo '<tr' . ($r->status === 'pending' ? ' style="font-weight:600;"' : '') . '>';
        echo '<td>RMA-' . (int) $r->id . '<br><small>' . esc_html(mysql2date('M j', $r->created_at)) . '</small></td>';
        echo '<td><a href="' . esc_url(admin_url('post.php?post=' . $r->order_id . '&action=edit')) . '">#' . (int) $r->order_id . '</a></td>';
        echo '<td>' . esc_html($r->email) . '</td>';
        echo '<td>' . esc_html($reasons[$r->reason] ?? $r->reason) . '</td>';
        echo '<td>' . esc_html(ucfirst($r->resolution)) . '</td>';
        echo '<td style="max-width:280px;">' . esc_html(mb_strimwidth((string) $r->details, 0, 180, '…')) . '</td>';
        echo '<td>' . esc_html(ucfirst($r->status)) . '</td>';
        echo '<td><form method="post" style="display:flex;flex-direction:column;gap:5px;min-width:190px;">';
        wp_nonce_field('af_rma_admin', 'af_rma_admin_nonce');
        echo '<input type="hidden" name="rma_id" value="' . (int) $r->id . '">';
        echo '<select name="status">';
        foreach (array('pending', 'approved', 'rejected', 'resolved') as $s) {
            echo '<option value="' . $s . '"' . selected($r->status, $s, false) . '>' . ucfirst($s) . '</option>';
        }
        echo '</select>';
        echo '<textarea name="admin_note" rows="2" placeholder="Note emailed to customer">' . esc_textarea((string) $r->admin_note) . '</textarea>';
        do_action('af_rma_admin_extra', $r);
        echo '<button class="button button-primary">Update &amp; email</button>';
        echo '</form></td></tr>';
    }
    echo '</tbody></table></div>';
}

/* ============================================================
   PHASE 17 — video testimonials (§13) + maintenance mode (§4)
   ============================================================ */

// ── 17a. [af_video_testimonials] — customer video wall ───────
// Video pool: option 'af_testimonial_videos' (one YouTube URL/ID per
// line). Falls back to the cached channel pool. Rendered on the
// Reviews & Press page — deliberately NOT the homepage, where
// "Products In Motion" already covers video.
add_shortcode('af_video_testimonials', function() {
    $ids = array();
    $opt = trim((string) get_option('af_testimonial_videos', ''));
    if ($opt !== '') {
        foreach (preg_split('/[\r\n,]+/', $opt) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            if (preg_match('#(?:youtube(?:-nocookie)?\.com/(?:watch\?(?:[^&\s]*&)*v=|embed/|shorts/|v/)|youtu\.be/)([A-Za-z0-9_-]{11})#i', $line, $m)) {
                $ids[] = $m[1];
            } elseif (preg_match('/^[A-Za-z0-9_-]{11}$/', $line)) {
                $ids[] = $line;
            }
        }
    }
    if (empty($ids)) {
        $pool = get_transient('af_yt_ids3_UC_GX4vXRQrN4GsvSfgmZxYw');
        if (is_array($pool)) $ids = $pool;
    }
    $ids = array_slice(array_values(array_unique(array_filter($ids))), 0, 8);
    if (empty($ids)) return '';
    ob_start(); ?>
<div class="taf-vids" id="afVids">
  <div class="taf-vids-row">
    <?php foreach ($ids as $vid) : ?>
      <button type="button" class="taf-vid" data-vid="<?php echo esc_attr($vid); ?>" aria-label="Play customer video">
        <img loading="lazy" src="https://i.ytimg.com/vi/<?php echo esc_attr($vid); ?>/hqdefault.jpg" alt="Customer video testimonial">
        <span class="taf-vid-play"></span>
      </button>
    <?php endforeach; ?>
  </div>
  <p class="taf-vids-note">🎥 Real customers, real walls — filmed in their own homes.
    <a href="https://www.youtube.com/channel/UC_GX4vXRQrN4GsvSfgmZxYw" target="_blank" rel="noopener">See more on YouTube →</a></p>
</div>
<div class="taf-vid-lb" id="afVidLb">
  <button class="taf-vid-x" id="afVidX" aria-label="Close video">&times;</button>
  <iframe id="afVidFrame" allow="autoplay; encrypted-media" allowfullscreen title="Customer video"></iframe>
</div>
<script>
(function(){
  var lb = document.getElementById('afVidLb'), fr = document.getElementById('afVidFrame'), x = document.getElementById('afVidX');
  if (!lb) return;
  document.querySelectorAll('.taf-vid').forEach(function(b){
    b.addEventListener('click', function(){
      fr.src = 'https://www.youtube-nocookie.com/embed/' + b.dataset.vid + '?autoplay=1&rel=0&playsinline=1';
      lb.classList.add('open');
    });
  });
  function close(){ lb.classList.remove('open'); fr.src = ''; }
  x.onclick = close;
  lb.addEventListener('click', function(e){ if (e.target === lb) close(); });
  document.addEventListener('keydown', function(e){ if (e.key === 'Escape') close(); });
})();
</script>
<?php return ob_get_clean();
});

// ── 17b. Maintenance mode (spec §4) ──────────────────────────
// Toggle: option 'af_maintenance' = 'on'. Logged-in admins/shop
// managers always bypass it, so you can work on the live site.
// Optional end time: option 'af_maintenance_until' ("YYYY-MM-DD HH:MM").
/* ─────────────────────────────────────────────────────────────
   The bottom navigation bar already has its clearance, and it is not ours.
   An earlier commit in this branch added body{padding-bottom:66px} on phones
   on the reasoning that nothing reserved space for that bar. Measured on the
   live site afterwards, at 390px: footer#colophon already carries
   margin-bottom:65px from the parent theme, so the page had 131px of empty
   space under it and the extra 66px was pure dead scroll on every phone page.

   The rule is gone rather than reduced. The theme owns this clearance, it is
   already the right size, and a second one in this file would only drift from
   it. Kept as a note so the same reasoning does not produce the same rule
   again: if the bar is ever buried, check footer#colophon's margin first.
   ───────────────────────────────────────────────────────────── */



/* ─────────────────────────────────────────────────────────────
   Never serve the owner a cached page.

   This theme puts most of its CSS inline in the HTML, which means a cached
   PAGE carries a cached STYLESHEET with it. A deploy clears the server cache,
   but the copy already sitting in the owner's browser does not change, and a
   normal refresh will happily re-serve it. That is not a theory: it cost most
   of a morning on the header, with the live page measuring and photographing
   as one tidy line while the owner's screen kept showing three.

   So for anyone who can edit the shop — and only for them — the page is sent
   with no-store, and LiteSpeed is told not to cache it either. Shoppers are
   untouched, so none of this costs the site any speed where speed matters.
   ───────────────────────────────────────────────────────────── */
add_action('template_redirect', function () {
    if (is_admin() || wp_doing_ajax()) return;
    if (!is_user_logged_in()) return;
    if (!current_user_can('manage_woocommerce') && !current_user_can('edit_theme_options')) return;

    if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
    do_action('litespeed_control_set_nocache', 'signed-in shop manager: always show the current page');

    add_filter('nocache_headers', function ($headers) {
        $headers['Cache-Control'] = 'no-store, no-cache, must-revalidate, max-age=0';
        $headers['Pragma']        = 'no-cache';
        return $headers;
    });
    nocache_headers();
}, 1);

/* ─────────────────────────────────────────────────────────────
   The header bar, laid out by script, because the markup is not ours.

   The CSS below this has been photographed working: signed out, empty cart,
   and signed out with five items, at 390px, one line. The owner is SIGNED IN,
   and their bar still stacks. A signed-in visitor gets a different header
   group from the parent theme, and WooCommerce then replaces the cart's
   markup again by AJAX after the page loads. I cannot sign in from a test
   runner and I have not been given that markup, so this stops guessing at
   selectors.

   At phone width the script finds the four things a shopper sees — the menu
   toggle, the logo, the currency and the cart — wherever they are, finds the
   one container they all share, and lays that container out itself: one row,
   never wrapping, logo the only thing that gives way, controls on the right.
   Inline !important, which nothing in a stylesheet can override. Then it
   measures its own result and, if the four are still not on one line, shrinks
   the logo until they are. It runs again on resize and whenever the header's
   markup changes, which is what the cart fragment refresh does.

   It also prints what it found to the console, so a screenshot of that panel
   answers the next question without another round of guessing. Only with the
   debug switch on (?af_debug=1, see inc/debug-flag.php).
   ───────────────────────────────────────────────────────────── */
add_action('wp_footer', function () {
    if (is_admin()) return;
    ?>
<script id="af-header-row" src="<?php echo esc_url(af_static_url('header-row.js')); ?>" data-no-optimize="1"></script>
    <?php
}, 210);

/* ─────────────────────────────────────────────────────────────
   The header, on a phone.

   Measured on the live site at 390px (Check Header, run 1). header#masthead is
   63px tall and its mobile row is an Elementor flex container:

     hamburger            0 → 39     flush to the very edge, no gutter
     logo                39 → 183
     menu dropdown      193 → 228    35 x 24
     currency "$ USD"   253 → 303    50 x 19
     cart "(5)"         323 → 374    51 x 24

   Two things are wrong with that.

   The row is flex-wrap:wrap and every child is flex:0 0 auto, so the widths
   are fixed and they add up to exactly 390 at 390px. There is no slack. The
   moment anything grows — and the cart group is the thing that grows, because
   "(5)" is wider than an empty cart — the last group has nowhere to go but a
   second line, which is the "$ USD" sitting above the cart in the owner's
   screenshot. Wrapping is the wrong behaviour for a header bar: it should get
   tighter, not taller. So the row does not wrap, and the logo is made the one
   element allowed to give up width, since it is the only one that can shrink
   without becoming unusable.

   And every control is far under the ~40px a fingertip wants: the currency
   switcher is 19px tall, the cart 24px, the dropdown 24px. They are given a
   real target and centred in the bar.

   Written against the theme's and plugin's own class names rather than
   Elementor's hashed element ids, which change whenever someone edits the
   header in Elementor.

   The breakpoint is 880px, and it is measured rather than chosen. This header
   has two containers and the site swaps between them: the mobile row carries
   elementor-hidden-desktop + elementor-hidden-laptop, and the wide one carries
   elementor-hidden-mobile_extra — and Elementor's mobile_extra breakpoint is
   880px. Measured at 900px, the mobile row is not rendered at all and the wide
   container is the one on the page. So the mobile row is live from 880px down,
   and capping these rules at 767px left the 768-880 band — a small tablet, a
   split-screen window, a phone held sideways — showing the unstyled, wrapping
   bar. Above 880px this row is display:none and nothing here can reach the
   wide header.
   ───────────────────────────────────────────────────────────── */
add_action('wp_head', function () {
    if (is_admin()) return;
    ?>
<link rel="stylesheet" id="af-header-phone" href="<?php echo esc_url(af_static_url('header-phone.css')); ?>" data-no-optimize="1">
    <?php
}, 100);

add_action('template_redirect', function() {
    if (get_option('af_maintenance') !== 'on') return;
    if (is_admin() || wp_doing_ajax() || (defined('WP_CLI') && WP_CLI)) return;
    if (current_user_can('manage_options') || current_user_can('manage_woocommerce')) return;
    // Let people still log in
    if (function_exists('wc_get_page_id') && is_page(wc_get_page_id('myaccount'))) return;
    if (isset($GLOBALS['pagenow']) && $GLOBALS['pagenow'] === 'wp-login.php') return;

    $until = trim((string) get_option('af_maintenance_until', ''));
    $back  = '';
    if ($until !== '') {
        $d = date_create($until, wp_timezone());
        if ($d) $back = $d->format('l j F, g:ia T');
    }
    nocache_headers();
    header('HTTP/1.1 503 Service Temporarily Unavailable');
    header('Status: 503 Service Temporarily Unavailable');
    header('Retry-After: 3600');
    ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>We'll be right back — The Art Framer</title>
<style>
*{box-sizing:border-box;}
body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:30px;
  background:linear-gradient(135deg,#141414 0%,#2a2318 60%,#3a2f16 100%);color:#e9e4d6;
  font-family:'Fredoka',system-ui,-apple-system,'Segoe UI',sans-serif;text-align:center;}
.wrap{max-width:560px;}
.ico{font-size:64px;line-height:1;margin-bottom:18px;}
h1{font-size:38px;font-weight:800;color:#fff;margin:0 0 14px;line-height:1.15;}
.gold{color:#c9a84c;}
p{font-size:16px;line-height:1.75;color:#b8b2a2;margin:0 0 14px;}
.back{display:inline-block;background:rgba(201,168,76,.14);border:1px solid #8a6d1f;color:#e6d9ae;
  padding:11px 20px;font-size:14px;font-weight:700;margin:8px 0 22px;}
.links{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-top:8px;}
.btn{background:#c9a84c;color:#141414;text-decoration:none;font-weight:800;font-size:14px;padding:13px 26px;}
.btn:hover{background:#dcb85a;}
.btn-alt{background:transparent;color:#c9a84c;border:1px solid #c9a84c;text-decoration:none;font-weight:700;font-size:14px;padding:12px 26px;}
.btn-alt:hover{background:#c9a84c;color:#141414;}
.foot{margin-top:34px;font-size:12.5px;color:#8a8375;}
</style>
</head>
<body>
  <div class="wrap">
    <div class="ico">🖼️</div>
    <h1>We're hanging some<br><span class="gold">new art</span></h1>
    <p>The Art Framer is briefly offline for scheduled maintenance. Your orders and files are safe — we'll be back very soon.</p>
    <?php if ($back) : ?><p class="back">⏱ Expected back: <?php echo esc_html($back); ?></p><?php endif; ?>
    <p>Need something urgently? We're still reachable:</p>
    <div class="links">
      <a class="btn" href="mailto:theartframer136@gmail.com">Email Us</a>
      <a class="btn-alt" href="tel:+16104707280">Call +1 (610) 470-7280</a>
    </div>
    <p class="foot">© <?php echo date('Y'); ?> The Art Framer · theartframer.us</p>
  </div>
</body>
</html>
    <?php
    exit;
}, 0);

// Admin bar notice so maintenance mode is never left on by accident
add_action('admin_bar_menu', function($bar) {
    if (get_option('af_maintenance') !== 'on') return;
    if (!current_user_can('manage_options')) return;
    $bar->add_node(array(
        'id'    => 'af-maint',
        'title' => '⚠️ Maintenance mode ON',
        'href'  => admin_url('options-general.php?page=af-maintenance'),
        'meta'  => array('title' => 'The site is showing the maintenance page to visitors'),
    ));
}, 100);

// Settings screen: toggle + expected-return time
add_action('admin_menu', function() {
    add_options_page('Maintenance Mode', 'Maintenance Mode', 'manage_options', 'af-maintenance', function() {
        if (!current_user_can('manage_options')) return;
        if (isset($_POST['af_maint_nonce']) && wp_verify_nonce($_POST['af_maint_nonce'], 'af_maint')) {
            update_option('af_maintenance', isset($_POST['af_maintenance']) ? 'on' : 'off');
            update_option('af_maintenance_until', sanitize_text_field(wp_unslash($_POST['af_maintenance_until'] ?? '')));
            echo '<div class="notice notice-success"><p>Saved.</p></div>';
        }
        $on    = get_option('af_maintenance') === 'on';
        $until = (string) get_option('af_maintenance_until', '');
        ?>
        <div class="wrap">
          <h1>Maintenance Mode</h1>
          <p>When enabled, visitors see a branded "we'll be right back" page (HTTP 503, safe for SEO).
             Administrators and shop managers keep full access, so you can keep working on the live site.</p>
          <form method="post">
            <?php wp_nonce_field('af_maint', 'af_maint_nonce'); ?>
            <table class="form-table">
              <tr>
                <th scope="row">Maintenance mode</th>
                <td><label><input type="checkbox" name="af_maintenance" <?php checked($on); ?>>
                    Show the maintenance page to visitors</label></td>
              </tr>
              <tr>
                <th scope="row">Expected back (optional)</th>
                <td><input type="text" name="af_maintenance_until" class="regular-text"
                      value="<?php echo esc_attr($until); ?>" placeholder="2026-07-20 14:30">
                    <p class="description">Shown on the page as "Expected back: …". Leave blank to omit.</p></td>
              </tr>
            </table>
            <?php submit_button('Save'); ?>
          </form>
          <p><a href="<?php echo esc_url(home_url('/?af_maint_preview=1')); ?>" target="_blank">Preview the maintenance page →</a>
             <em>(admins bypass it, so use a private window to see it as a visitor)</em></p>
        </div>
        <?php
    });
});

// ─────────────────────────────────────────────────────────────
// PHASE 24j — "Shop related art" internal-link block on blog posts.
// The 17 long-form posts (4k-5k words) carried ZERO body links to
// products/categories, so their crawl authority never reached the
// commercial pages. This appends an editorial related-products strip
// (does not touch the post prose). Relevance = title-keyword overlap
// with product titles/categories, fallback to featured then recent.
// Result cached per-post in a transient (products change rarely).
// ─────────────────────────────────────────────────────────────
function af_related_products_for_post($post_id, $limit = 4) {
    $cache_key = 'af_relprod_' . $post_id;
    $cached = get_transient($cache_key);
    if (is_array($cached)) return $cached;

    if (!function_exists('wc_get_products')) return array();

    // Keywords from the post title (drop stopwords / short tokens).
    $title = get_the_title($post_id);
    $stop = array('the','and','for','your','with','how','you','are','from','that','this','when','what','their','our','of','to','in','a','an','on','it','by','or','is');
    $words = array_filter(preg_split('/[^a-z]+/', strtolower($title)), function ($w) use ($stop) {
        return strlen($w) >= 4 && !in_array($w, $stop, true);
    });

    $scored = array();
    if ($words) {
        $q = wc_get_products(array('status' => 'publish', 'limit' => 60, 'orderby' => 'date', 'order' => 'DESC'));
        foreach ($q as $p) {
            $hay = strtolower($p->get_name());
            foreach (wp_get_post_terms($p->get_id(), 'product_cat', array('fields' => 'names')) as $cn) {
                $hay .= ' ' . strtolower($cn);
            }
            $score = 0;
            foreach ($words as $w) {
                if (strpos($hay, $w) !== false) $score++;
            }
            if ($score > 0 && $p->get_image_id()) $scored[$p->get_id()] = $score;
        }
        arsort($scored);
    }

    $ids = array_slice(array_keys($scored), 0, $limit);

    // Fallback: featured, then most recent with an image.
    if (count($ids) < $limit) {
        $need = $limit - count($ids);
        $fb = wc_get_products(array(
            'status' => 'publish', 'limit' => $need + count($ids) + 4,
            'featured' => true, 'orderby' => 'date', 'order' => 'DESC',
        ));
        if (count($fb) < $need) {
            $fb = array_merge($fb, wc_get_products(array(
                'status' => 'publish', 'limit' => $need + count($ids) + 6,
                'orderby' => 'date', 'order' => 'DESC',
            )));
        }
        foreach ($fb as $p) {
            if (count($ids) >= $limit) break;
            if (!in_array($p->get_id(), $ids, true) && $p->get_image_id()) $ids[] = $p->get_id();
        }
    }

    set_transient($cache_key, $ids, DAY_IN_SECONDS);
    return $ids;
}

add_filter('the_content', function ($content) {
    if (is_admin() || !is_singular('post') || !in_the_loop() || !is_main_query()) return $content;
    if (strpos($content, 'af-relprod') !== false) return $content;
    if (!function_exists('wc_get_product')) return $content;

    $ids = af_related_products_for_post(get_the_ID(), 4);
    if (!$ids) return $content;

    $cards = '';
    foreach ($ids as $pid) {
        $p = wc_get_product($pid);
        if (!$p) continue;
        $img = wp_get_attachment_image($p->get_image_id(), 'woocommerce_thumbnail', false, array('loading' => 'lazy', 'alt' => esc_attr($p->get_name())));
        $cards .= '<a class="af-relprod-card" href="' . esc_url(get_permalink($pid)) . '">'
                . '<span class="af-relprod-img">' . $img . '</span>'
                . '<span class="af-relprod-name">' . esc_html($p->get_name()) . '</span>'
                . '<span class="af-relprod-price">' . wp_kses_post($p->get_price_html()) . '</span>'
                . '</a>';
    }
    if ($cards === '') return $content;

    $block  = '<section class="af-relprod" aria-label="Related art from The Art Framer">';
    $block .= '<h2 class="af-relprod-h">Shop Related Art</h2>';
    $block .= '<div class="af-relprod-grid">' . $cards . '</div>';
    $block .= '<p class="af-relprod-more"><a href="' . esc_url(get_permalink(wc_get_page_id('shop'))) . '">Browse the full collection &rarr;</a></p>';
    $block .= '</section>';

    return $content . $block;
}, 50);

add_action('wp_head', function () {
    if (!is_singular('post')) return;
    echo '<style>'
       . '.af-relprod{margin:42px 0 10px;padding-top:28px;border-top:1px solid #ece7dd;}'
       . '.af-relprod-h{font-size:22px;font-weight:700;color:#1a1a1a;margin:0 0 18px;}'
       . '.af-relprod-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;}'
       . '.af-relprod-card{display:flex;flex-direction:column;text-decoration:none;color:inherit;background:#fff;border:1px solid #ece7dd;border-radius:10px;overflow:hidden;transition:box-shadow .2s,transform .2s;}'
       . '.af-relprod-card:hover{box-shadow:0 8px 24px rgba(0,0,0,.1);transform:translateY(-3px);}'
       . '.af-relprod-img img{display:block;width:100%;height:auto;aspect-ratio:1/1;object-fit:cover;}'
       . '.af-relprod-name{padding:10px 12px 4px;font-size:14px;font-weight:600;line-height:1.35;}'
       . '.af-relprod-price{padding:0 12px 12px;font-size:14px;color:#c9a84c;font-weight:700;}'
       . '.af-relprod-more{margin:18px 0 0;}'
       . '.af-relprod-more a{color:#c9a84c;font-weight:600;text-decoration:none;}'
       . '@media(max-width:900px){.af-relprod-grid{grid-template-columns:repeat(2,1fr);}}'
       . '@media(max-width:480px){.af-relprod-grid{grid-template-columns:repeat(2,1fr);gap:12px;}.af-relprod-name{font-size:13px;}}'
       . '</style>';
});

// Bust the related-products cache whenever a product is saved/removed.
add_action('save_post_product', function () {
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_af_relprod\_%' OR option_name LIKE '\_transient\_timeout\_af_relprod\_%'");
});

// ─────────────────────────────────────────────────────────────
// PHASE 24k — Product schema: shipping, returns, brand.
// Values mirror the store's PUBLISHED policies (shipping: throughout the
// USA at the rate in af_shipping_cost, ~5 business days production + up to
// 10 days transit;
// refund page: 7-day return window). returnFees intentionally
// omitted — free returns are only promised for damaged/wrong-item,
// so claiming FreeReturn store-wide would overstate. Makes products
// eligible for "free shipping / returns" treatment in US results.
// Keep these in sync with the policy pages if they change.
// ─────────────────────────────────────────────────────────────
function af_offer_shipping_details() {
    // The rate follows af_shipping_copy(): telling Google "0" while the site
    // charges for shipping is a false free-shipping claim in Merchant results,
    // which is a policy problem as well as a factual one. With no rate
    // configured the amount is omitted rather than guessed at.
    //
    // Under the free policy the cost is the string "0", so Google is told a
    // rate of zero — which is what the shop now actually does, and what makes
    // the listings eligible for free-shipping treatment. The number still
    // comes from the same one source as every visible badge, so the feed and
    // the page cannot say different things.
    $cost = function_exists('af_shipping_copy') ? af_shipping_copy()['cost'] : '';
    $num  = ($cost !== '' && preg_match('/[\d.]+/', $cost, $m)) ? $m[0] : '';
    $rate = ($num !== '')
        ? array('@type' => 'MonetaryAmount', 'value' => $num, 'currency' => 'USD')
        : null;
    return array_filter(array(
        '@type' => 'OfferShippingDetails',
        'shippingRate' => $rate,
        'shippingDestination' => array('@type' => 'DefinedRegion', 'addressCountry' => 'US'),
        'deliveryTime' => array(
            '@type' => 'ShippingDeliveryTime',
            'handlingTime' => array('@type' => 'QuantitativeValue', 'minValue' => 1, 'maxValue' => 5, 'unitCode' => 'DAY'),
            'transitTime'  => array('@type' => 'QuantitativeValue', 'minValue' => 3, 'maxValue' => 10, 'unitCode' => 'DAY'),
        ),
    ), function($v){ return $v !== null; });
}
function af_merchant_return_policy() {
    return array(
        '@type' => 'MerchantReturnPolicy',
        'applicableCountry' => 'US',
        'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
        'merchantReturnDays' => 7,
        'returnMethod' => 'https://schema.org/ReturnByMail',
    );
}
function af_enrich_product_offer($offer) {
    if (empty($offer['shippingDetails'])) $offer['shippingDetails'] = af_offer_shipping_details();
    if (empty($offer['hasMerchantReturnPolicy'])) $offer['hasMerchantReturnPolicy'] = af_merchant_return_policy();
    return $offer;
}

// Rank Math pipeline (its graph carries the Product node on this site).
add_filter('rank_math/json_ld', function ($data, $jsonld) {
    if (!function_exists('is_product') || !is_product()) return $data;
    foreach ($data as $k => $node) {
        $types = isset($node['@type']) ? (array) $node['@type'] : array();
        if (!in_array('Product', $types, true)) continue;
        if (empty($node['brand'])) {
            $data[$k]['brand'] = array('@type' => 'Brand', 'name' => 'The Art Framer');
        }
        if (!empty($node['offers'])) {
            $offers = $node['offers'];
            if (isset($offers['@type'])) {
                $data[$k]['offers'] = af_enrich_product_offer($offers);
            } elseif (is_array($offers)) {
                foreach ($offers as $i => $o) {
                    if (is_array($o)) $offers[$i] = af_enrich_product_offer($o);
                }
                $data[$k]['offers'] = $offers;
            }
        }
    }
    return $data;
}, 20, 2);

// WooCommerce pipeline (covered too in case its JSON-LD is active).
add_filter('woocommerce_structured_data_product', function ($markup, $product) {
    if (empty($markup['brand'])) {
        $markup['brand'] = array('@type' => 'Brand', 'name' => 'The Art Framer');
    }
    if (!empty($markup['offers']) && is_array($markup['offers'])) {
        foreach ($markup['offers'] as $i => $o) {
            if (is_array($o)) $markup['offers'][$i] = af_enrich_product_offer($o);
        }
    }
    return $markup;
}, 20, 2);

// ═══════════════════════════════════════════════════════════════
// PHASE 25 — Security hardening (added 2026-07-18, security audit)
// Covers: REST user enumeration, software-version disclosure,
// XML-RPC amplification, and missing HTTP security headers.
// ═══════════════════════════════════════════════════════════════

// 25a. Block unauthenticated REST user enumeration.
// /wp/v2/users leaks real login names to anonymous callers. Logged-in
// users (Gutenberg author pickers, etc.) keep access; anon is removed.
add_filter('rest_endpoints', function ($endpoints) {
    if (!is_user_logged_in()) {
        unset($endpoints['/wp/v2/users']);
        unset($endpoints['/wp/v2/users/(?P<id>[\d]+)']);
    }
    return $endpoints;
});

// 25b. Stop software-version disclosure (harder to fingerprint CVEs).
remove_action('wp_head', 'wp_generator');
add_filter('the_generator', '__return_empty_string');

// 25c. XML-RPC: disable unless explicitly re-enabled via the
// 'af_xmlrpc_enabled' option (set to 'yes' if Jetpack / the WP mobile
// app ever needs it — no redeploy required). Pingback methods and the
// X-Pingback header are ALWAYS removed (DDoS-amplification vector,
// no legitimate use on this store).
if (get_option('af_xmlrpc_enabled') !== 'yes') {
    add_filter('xmlrpc_enabled', '__return_false');
}
add_filter('xmlrpc_methods', function ($methods) {
    unset($methods['pingback.ping'], $methods['pingback.extensions.getPingbacks']);
    return $methods;
});
add_filter('wp_headers', function ($headers) {
    unset($headers['X-Pingback']);
    return $headers;
});

// 25d. HTTP security headers.
// On this LiteSpeed+hcdn host, headers do NOT reach *cached* pages (LiteSpeed
// serves cache hits without running PHP, and does not bake PHP-set headers
// into the cache; the docroot-.htaccess mod_headers path also isn't honored
// here). So we set them via PHP for every UNCACHED / dynamic response —
// which is exactly where they matter: checkout, cart, my-account, login,
// search, REST, 404. Cached marketing pages (home/shop/product) need the
// header added at the LiteSpeed/CDN layer (hPanel) — tracked as a manual item.
// tools/set-security-headers.php still writes the .htaccess block in case a
// future server config honors it. (CSP deferred — Elementor needs report-only.)
// The same five headers also go on wp-login.php, admin-ajax.php and REST
// answers, which never reach send_headers (inc/security-hardening.php).
function af_send_security_headers() {
    if (headers_sent()) return;
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    // camera=(self) so the Try-On-Wall / Frame-The-Moment live camera preview
    // can request it (spec §8 "AR – live camera"); still blocked for iframes.
    header('Permissions-Policy: geolocation=(), camera=(self), microphone=()');
    if (is_ssl()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}
add_action('send_headers', function () {
    if (is_admin()) return;
    af_send_security_headers();
});

// 25e. Disable the in-dashboard theme/plugin file editor. If an admin
// account is ever compromised, this stops the attacker editing PHP to
// plant a backdoor straight from wp-admin.
if (!defined('DISALLOW_FILE_EDIT')) {
    define('DISALLOW_FILE_EDIT', true);
}

// 25f. Generic login error — never reveal whether a username exists
// (the 4 admin usernames are already known; don't confirm passwords too).
// The one message let through is the security check's (inc/turnstile.php):
// it says nothing about the account, and the visitor needs it to know what
// to do. WooCommerce runs its login errors through this filter as well.
add_filter('login_errors', function ($errors = '') {
    if (is_string($errors) && preg_match('#<span class="af-(?:ts|login)-msg">.*?</span>#s', $errors, $m)) return $m[0];
    return 'Invalid credentials. Please try again.';
});

/* ============================================================
   PHASE 18 — Product page 100% spec match (§7)
   Group chips + wall hints, variable-product bridge, dimension
   overlay toggle, frame-color preview swap, video labels + DIY,
   verified-buyers badge, recently viewed.
   ============================================================ */
add_action('wp_footer', function() {
    // Printed on every front-end page, not just the product page. The quick-view
    // modal is injected into whatever page the visitor is already on — the shop,
    // the homepage — so gating this to is_product() left the modal's Recently
    // Viewed strip with no .af-recent-row rule at all, which is why it came out
    // as a column of full-width images. Every selector is .af-* scoped and the
    // whole block is ~3 KB, so serving it site-wide is the cheap fix.
    if (is_admin()) return;
    ?>
<style>
.af-opt-sub{font-weight:400;color:var(--af-mute, #6b6b6b);font-size:11.5px;}
/* Only the "Custom ↗" pill still uses this — the S/M/L filters went with the
   size chip grid, since a five-item dropdown has nothing to filter. */
.af-chip-grp{background:#f5f0e4;border:1px solid #e0d5b8;color:#6b5a23;font-size:11.5px;font-weight:800;
  padding:5px 13px;cursor:pointer;border-radius:20px;text-decoration:none;display:inline-block;}
.af-chip-custom{border-style:dashed;}
.af-wall-hint{margin:2px 0 12px;font-size:12.5px;color:#8a6d1f;font-weight:600;}
.af-rec{display:block;font-size:9px;letter-spacing:.08em;text-transform:uppercase;color:#256d2c;font-weight:800;}
.af-color-tip{margin:2px 0 8px;font-size:11.5px;color:var(--af-mute, #6b6b6b);}
.af-live-mrp{color:var(--af-was, #6b6b6b);margin-left:10px;font-size:15px;text-decoration:line-through;text-decoration-color:var(--af-was, #6b6b6b);text-decoration-thickness:2px;}
.af-live-disc{margin-left:8px;font-size:13.5px;font-weight:700;color:var(--af-save, #2e7d32);}
.af-price-notes{margin:4px 0 10px;font-size:12.5px;color:#256d2c;font-weight:600;}
.af-dim-toggle{display:inline-flex;align-items:center;gap:7px;background:#fff;border:1px solid #c9a84c;color:#8a6d1f;
  font-size:12.5px;font-weight:700;padding:8px 14px;cursor:pointer;margin:10px 0 0;}
.af-dim-toggle.on{background:#141414;color:#c9a84c;border-color:#141414;}
.af-dim-overlay{position:absolute;inset:0;pointer-events:none;display:none;z-index:5;}
.af-dim-overlay.show{display:block;}
.af-dim-w{position:absolute;left:8%;right:8%;bottom:10px;border-bottom:2px solid #c9a84c;text-align:center;color:#fff;
  font-size:13px;font-weight:800;text-shadow:0 1px 4px #000;padding-bottom:3px;}
.af-dim-w::before,.af-dim-w::after{content:"";position:absolute;bottom:-6px;width:2px;height:14px;background:#c9a84c;}
.af-dim-w::before{left:0;} .af-dim-w::after{right:0;}
.af-dim-h{position:absolute;top:8%;bottom:8%;right:10px;border-right:2px solid #c9a84c;display:flex;align-items:center;}
.af-dim-h span{writing-mode:vertical-rl;color:#fff;font-size:13px;font-weight:800;text-shadow:0 1px 4px #000;padding-right:3px;}
.af-dim-h::before,.af-dim-h::after{content:"";position:absolute;right:-6px;height:2px;width:14px;background:#c9a84c;}
.af-dim-h::before{top:0;} .af-dim-h::after{bottom:0;}
.af-vid-caption{margin:10px 0 4px;font-size:13px;font-weight:700;color:#141414;}
.af-vid-caption span{color:#8a6d1f;}
.af-diy-strip{display:flex;gap:10px;margin:8px 0 0;flex-wrap:wrap;}
.af-diy-strip a{display:flex;align-items:center;gap:8px;border:1px solid #e0d5b8;background:#fdfcf9;padding:8px 13px;
  font-size:12.5px;font-weight:700;color:#6b5a23;text-decoration:none;}
.af-diy-strip a:hover{border-color:#c9a84c;}
.af-verified{display:inline-flex;align-items:center;gap:5px;background:#eef7ee;border:1px solid #bfe3c2;color:#256d2c;
  font-size:11.5px;font-weight:800;padding:3px 10px;border-radius:14px;margin-left:10px;vertical-align:middle;}
.af-recent{margin:34px 0 8px;}
.af-recent h3{font-size:20px;font-weight:800;margin:0 0 14px;}
.af-recent-row{display:flex;gap:14px;overflow-x:auto;padding-bottom:8px;}
.af-recent-row a{flex:0 0 150px;text-decoration:none;color:#141414;font-size:12.5px;font-weight:700;line-height:1.35;}
.af-recent-row img{width:150px;height:150px;object-fit:cover;display:block;border:1px solid #ece5d4;margin-bottom:7px;}
</style>
<script id="af-product-options" src="<?php echo esc_url(af_static_url('product-options.js')); ?>" data-no-optimize="1"></script>
<?php }, 45);

// ── Recently viewed (spec §7 post-tab), drawn in the browser ───
// DEF-04, 23 Sep. This used to be done on the server, twice over: two
// template_redirect hooks set af_recent and af_recently_viewed on every product
// page response, and two sections built "Recently Viewed" from those cookies.
// Product pages are served from LiteSpeed's page cache, and a cached copy keeps
// both. Measured from fresh browsers, every visitor to the Kerala Mural page
// was sent "Set-Cookie: af_recent=26084" with one and the same timestamp; and a
// copy built for a returning visitor would have stored their history in the
// page's HTML and cookie for everyone after them.
//
// So the server sends everyone the same empty placeholder, which is safe to
// cache, and the script below does the per-visitor part in the visitor's own
// browser: it reads af_recent (folding in the old af_recently_viewed once), asks
// the Store API for those products — published and visible ones only, so a
// hidden product drops out — draws the strip, and records the page being viewed.
// One strip, where there used to be two.
af_section(function() {
    if (!af_show_product_sections()) return;   // page + quick-view modal, nothing else
    $product = af_wc_product();
    if (!$product) return;
    echo '<div class="af-recent" data-af-recent data-af-current="' . (int) $product->get_id() . '" hidden></div>';
}, 22);

add_action('wp_footer', function () {
    if (is_admin()) return;
    ?>
<script>
(function(){
  var KEY = 'af_recent', OLD = 'af_recently_viewed', KEEP = 9, SHOW = 6;
  function read(name){
    var m = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
    return m ? decodeURIComponent(m[1]) : '';
  }
  function ids(s, sep){
    return String(s || '').split(sep).map(function(x){ return parseInt(x, 10); }).filter(function(n){ return n > 0; });
  }
  function history(){
    var seen = ids(read(KEY), ',');
    var old = ids(read(OLD), '|');
    if (old.length) {
      old.forEach(function(n){ if (seen.indexOf(n) < 0) seen.push(n); });
      document.cookie = OLD + '=; path=/; max-age=0';
    }
    return seen.slice(0, KEEP);
  }
  function remember(pid){
    var list = [pid].concat(history().filter(function(n){ return n !== pid; })).slice(0, KEEP);
    document.cookie = KEY + '=' + list.join(',') + '; path=/; max-age=' + (30 * 86400) + '; SameSite=Lax'
      + (location.protocol === 'https:' ? '; Secure' : '');
  }
  // The Store API sends names HTML-encoded ("&#8211;"). A textarea decodes
  // entities without running anything, and the result only ever goes into
  // textContent and alt.
  function plain(s){ var t = document.createElement('textarea'); t.innerHTML = String(s || ''); return t.value; }
  function short(s){ var w = s.split(/\s+/); return w.length > 7 ? w.slice(0, 7).join(' ') + '…' : s; }
  function fill(box){
    if (box.getAttribute('data-af-done')) return;
    box.setAttribute('data-af-done', '1');
    var cur = parseInt(box.getAttribute('data-af-current'), 10) || 0;
    var want = history().filter(function(n){ return n !== cur; });
    if (!want.length) return;
    fetch('/wp-json/wc/store/v1/products?per_page=' + want.length + '&include=' + want.join(','), { credentials: 'same-origin' })
      .then(function(r){ return r.ok ? r.json() : []; })
      .then(function(list){
        var byId = {};
        (list || []).forEach(function(p){ byId[p.id] = p; });
        var row = document.createElement('div');
        row.className = 'af-recent-row';
        var n = 0;
        want.forEach(function(id){
          var p = byId[id];
          if (!p || n >= SHOW || !p.permalink) return;
          var src = p.images && p.images[0] ? (p.images[0].thumbnail || p.images[0].src) : '';
          if (!src) return;
          var name = plain(p.name);
          var a = document.createElement('a');
          a.href = p.permalink;
          var img = document.createElement('img');
          img.loading = 'lazy'; img.src = src; img.alt = name;
          a.appendChild(img);
          a.appendChild(document.createTextNode(short(name)));
          row.appendChild(a);
          n++;
        });
        if (!n) return;
        var h = document.createElement('h3');
        h.textContent = 'Recently Viewed';
        box.appendChild(h);
        box.appendChild(row);
        box.hidden = false;
      })
      .catch(function(){});
  }
  function scan(root){ (root || document).querySelectorAll('.af-recent[data-af-recent]').forEach(fill); }
  function start(){
    scan(document);
    // Record the product page being viewed, after its own strip has read the
    // history, so a page never lists itself.
    var m = document.body && document.body.classList.contains('single-product')
      && (document.body.className.match(/(?:^|\s)postid-(\d+)/) || [])[1];
    if (m) remember(parseInt(m, 10));
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
  // The quick-view modal brings its own placeholder in later, on any page.
  if ('MutationObserver' in window) {
    new MutationObserver(function(muts){
      muts.forEach(function(mu){
        mu.addedNodes.forEach(function(node){
          if (node.nodeType !== 1) return;
          if (node.matches('.af-recent[data-af-recent]')) fill(node);
          else if (node.querySelector('.af-recent[data-af-recent]')) scan(node);
        });
      });
    }).observe(document.documentElement, { childList: true, subtree: true });
  }
})();
</script>
    <?php
}, 60);

// ── 18c. Variation strips on shop/category product cards ─────
// One batched endpoint tells the page which visible products get the
// size/frame/color engine and their from-price; JS injects a compact
// swatch strip on each eligible card, deep-linking to #af-opts.
/**
 * The frame-colour dots shown on every card. One definition, so the
 * server-rendered strip and the AJAX fallback can never drift apart.
 */
function af_card_vars_dots() {
    return array('Black' => '#1a1a1a', 'Silver' => '#c0c0c0', 'Gold' => '#d4af37', 'Rose Gold' => '#b76e79');
}

/**
 * "5 sizes · 1 frame" — the card's option summary. Counts only what the
 * product page will actually sell, so a card never advertises a size or a
 * frame the selector refuses. Shared by the rendered strip and the AJAX
 * fallback, which is why it is built in one place.
 */
function af_card_vars_label() {
    $sizes  = count(af_sizes_available());
    $frames = count(af_frames_in_stock());
    return $sizes . ' size' . ($sizes === 1 ? '' : 's')
         . ' &middot; ' . $frames . ' frame' . ($frames === 1 ? '' : 's');
}

/** The strip's markup for one product, or '' when the product has no options. */
function af_card_vars_html($product) {
    if (!$product || $product->get_status() !== 'publish') return '';
    if (!af_pricing_applies($product) || !$product->is_purchasable()) return '';

    $cfg  = af_pricing_config();
    $base = $product->is_type('variable')
        ? (float) $product->get_variation_price('min')
        : (float) wc_get_price_to_display($product);

    $dots = '';
    foreach (af_card_vars_dots() as $name => $hex) {
        $dots .= '<i title="' . esc_attr($name) . '" style="background:' . esc_attr($hex) . '"></i>';
    }
    return '<div class="af-card-vars"><a href="' . esc_url(get_permalink($product->get_id()) . '#af-opts') . '">'
         . '<span class="af-card-dots">' . $dots . '</span>'
         . '<span>' . af_card_vars_label() . '</span>'
         . '<span class="af-card-from">From ' . esc_html(wp_strip_all_tags(wc_price($base))) . '</span>'
         . '</a></div>';
}

// PERF: render the strip into the card at page-build time. The 2026-08-04
// profiler measured this endpoint at 43.1s of PHP across 33 calls (~1.3s each)
// in 15 minutes — and every one of those calls happened on a page LiteSpeed had
// already served from cache, because the markup was only ever built in the
// browser. Emitting it here folds the cost into the cached HTML: paid once per
// cache generation instead of once per visitor. Priority 13 puts it after the
// price (10) and the discount badge (12), matching where the JS inserted it.
// The JS skips any card that already carries .af-card-vars and makes no request
// at all when every card is covered, so it now only fires for cards that this
// hook cannot reach (Elementor-built homepage rows).
add_action('woocommerce_after_shop_loop_item_title', function() {
    $product = af_wc_product();
    if (!$product) return;
    echo af_card_vars_html($product); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the builder
}, 13);

function af_card_variations_handler() {
    $raw = isset($_POST['ids']) ? (array) $_POST['ids'] : array();
    $ids = array_slice(array_filter(array_map('absint', $raw)), 0, 48);
    $cfg = af_pricing_config();
    $meta = array('sizes' => count(af_sizes_available()), 'frames' => count(af_frames_in_stock()),
                  'label' => html_entity_decode(af_card_vars_label(), ENT_QUOTES, 'UTF-8'));
    $out = array();
    foreach ($ids as $id) {
        $p = wc_get_product($id);
        if (!$p || $p->get_status() !== 'publish') continue;
        // Corporate Printing: the rate card's size and quantity where the
        // canvas cards say "5 sizes · 2 frames" (inc/corporate-specs.php).
        $corp = function_exists('af_corp_spec') ? af_corp_spec($p) : null;
        if ($corp) {
            $out[$id] = array(
                'ok'   => 1,
                'corp' => implode(' · ', array_filter($corp)),
                'url'  => get_permalink($id),
                'code' => function_exists('af_get_art_code') ? af_get_art_code($p) : '',
            );
            continue;
        }
        if (!af_pricing_applies($p) || !$p->is_purchasable()) { $out[$id] = array('ok' => 0); continue; }
        $base = $p->is_type('variable') ? (float) $p->get_variation_price('min') : (float) wc_get_price_to_display($p);
        $out[$id] = array(
            'ok'   => 1,
            'from' => wp_strip_all_tags(wc_price($base)),
            'url'  => get_permalink($id) . '#af-opts',
            'code' => function_exists('af_get_art_code') ? af_get_art_code($p) : '',
        );
    }
    wp_send_json_success(array('meta' => $meta, 'items' => $out));
}
add_action('wp_ajax_af_card_variations',        'af_card_variations_handler');
add_action('wp_ajax_nopriv_af_card_variations', 'af_card_variations_handler');

add_action('wp_footer', function() {
    if (!function_exists('is_shop')) return;
    // The wishlist and cart "You may also like" rows print the same cards and
    // were left out, which is why they showed a price and then jumped straight
    // to the brochure button while every archive card carried the art code and
    // the colour strip between the two.
    if (!is_shop() && !is_product_category() && !is_product_tag() && !is_front_page()
        && !af_cards_secondary_page()) return;
    ?>
<style>
.af-card-vars{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin:7px 0 4px;font-size:11.5px;color:#8a6d1f;font-weight:700;}
.af-card-dots{display:inline-flex;gap:4px;}
.af-card-dots i{width:14px;height:14px;border-radius:50%;border:1.5px solid #fff;box-shadow:0 0 0 1px #d9d0bd;display:inline-block;}
.af-card-vars a{color:#8a6d1f;text-decoration:none;display:inline-flex;align-items:center;gap:8px;flex-wrap:wrap;}
.af-card-vars a:hover{color:#141414;}
.af-card-from{color:#141414;font-weight:800;}
</style>
<script>
(function(){
  var dots = {'Black':'#1a1a1a','Silver':'#c0c0c0','Gold':'#d4af37','Rose Gold':'#b76e79'};
  function inject(){
    var cards = {};
    document.querySelectorAll('a[data-product_id]').forEach(function(a){
      var card = a.closest('li.product, .type-product, .product');
      var id = parseInt(a.getAttribute('data-product_id'), 10);
      if (card && id && !card.querySelector('.af-card-vars') && !card.dataset.afVarsDone) { card.dataset.afVarsDone = '1'; cards[id] = card; }
    });
    var ids = Object.keys(cards);
    if (!ids.length) return;
    var fd = new FormData();
    fd.append('action', 'af_card_variations');
    ids.forEach(function(id){ fd.append('ids[]', id); });
    fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', { method:'POST', credentials:'same-origin', body: fd })
      .then(function(r){ return r.json(); })
      .then(function(res){
        if (!res || !res.success) return;
        var meta = res.data.meta, items = res.data.items || {};
        ids.forEach(function(id){
          var card = cards[id], info = items[id];
          card.dataset.afVarsDone = '1';
          if (!info || !info.ok) return;
          var strip = document.createElement('div');
          strip.className = 'af-card-vars';
          var d = Object.keys(dots).map(function(c){ return '<i title="' + c + '" style="background:' + dots[c] + '"></i>'; }).join('');
          if (info.corp) {
            // a banner, a standee or a pack of cards: its own size and quantity
            var sp = document.createElement('span'); sp.textContent = info.corp;
            var a = document.createElement('a'); a.href = info.url; a.appendChild(sp);
            strip.className += ' af-corp-spec'; strip.appendChild(a);
          } else
          strip.innerHTML = '<a href="' + info.url + '"><span class="af-card-dots">' + d + '</span>' +
            '<span>' + (meta.label || (meta.sizes + ' sizes · ' + meta.frames + ' frames')) + '</span>' +
            '<span class="af-card-from">From ' + info.from + '</span></a>';
          var anchor = card.querySelector('.price') || card.querySelector('.woocommerce-loop-product__title') || card;
          anchor.parentElement ? anchor.parentElement.insertBefore(strip, anchor.nextSibling) : card.appendChild(strip);

          // The art code belongs above the price, under the title. The PHP
          // hook that writes it never fires in this card template, so the
          // archives get it the same way: from here.
          if (info.code && !card.querySelector('.af-art-code')) {
            var title = card.querySelector('.woocommerce-loop-product__title, .product-title, h2, h3');
            var priceEl = card.querySelector('.price');
            if (title && priceEl && title.parentElement) {
              var c = document.createElement('span');
              c.className = 'af-art-code af-art-code--card';
              c.textContent = 'Art Code: ' + info.code;
              priceEl.parentElement.insertBefore(c, priceEl);
            }
          }
        });
      });
  }
  document.addEventListener('DOMContentLoaded', inject);
  window.addEventListener('load', inject);
  setTimeout(inject, 900); setTimeout(inject, 2200);
  document.addEventListener('af_products_appended', function(){ setTimeout(inject, 300); }); // infinite scroll
})();
</script>
<?php }, 46);

// ─────────────────────────────────────────────────────────────
// PHASE 25 — Shop by Collection: show ALL products per collection.
// The parent theme caps its homepage collection grid at 12 in TWO
// hardcoded WP_Query calls (initial render ~line 813 and the
// load_products AJAX tab handler ~line 186). Both are secondary
// queries, so pre_get_posts can lift the cap from the child theme
// without touching parent files. Strictly scoped:
//   • never main queries (shop archive keeps its own per-page)
//   • render: only front-page product queries with the exact
//     signature posts_per_page==12 + a product_cat tax filter
// The load_products case moved to inc/home-weight.php (M-09): asked
// from the homepage it answers with the theme's 12 and a "See all"
// link, because the homepage grid grew with the catalogue (74 cards
// for Radha Krishna); asked from anywhere else, every product, as here.
// ─────────────────────────────────────────────────────────────
add_action('pre_get_posts', function($q){
    if ($q->is_main_query()) return;
    // post_type may be a string or an array — accept both
    $pt = $q->get('post_type');
    $is_product = ($pt === 'product') || (is_array($pt) && in_array('product', $pt, true));
    if (!$is_product) return;

    // Case 2: initial homepage grid render — 12-per-page product query filtered
    // by category (tax_query OR the product_cat/category query-var shortcuts)
    if (!is_admin() && is_front_page() && (int) $q->get('posts_per_page') === 12) {
        $has_cat = false;
        $tax = $q->get('tax_query');
        if (is_array($tax)) {
            foreach ($tax as $t) {
                if (is_array($t) && isset($t['taxonomy']) && $t['taxonomy'] === 'product_cat') { $has_cat = true; break; }
            }
        }
        if (!$has_cat && ($q->get('product_cat') || $q->get('category_name'))) $has_cat = true;
        if ($has_cat) $q->set('posts_per_page', -1);
    }
});

// ─────────────────────────────────────────────────────────────
// PHASE 26 — "What Clients Say" review cards overlapped the next
// section's heading (Corporate Signages & Large Format Printing)
// on the homepage.
// ROOT CAUSE (from post-75 Elementor CSS): the container that
// wraps the reviews widget (elementor-element-2596335) carries
// --margin-bottom:-141px (desktop) / -110px (smaller breakpoint),
// which pulls the next section up underneath the cards. Neutralise
// that margin — scoped to this one container on page 75 only.
// Also keep a widget-scoped safety net (#g-review):
//   • CSS: let the swiper track/slides size to their content
//   • JS : if the widget (or its container) still reports less
//     height than its content occupies, pin min-height to the
//     real content height; re-check on resize and late loads
// ─────────────────────────────────────────────────────────────
add_action('wp_footer', function () {
    ?>
<style id="af-review-overlap-fix">
.elementor-75 .elementor-element.elementor-element-2596335,
.elementor-element.elementor-element-2596335 {
  --margin-bottom: 0px !important;
  margin-bottom: 0 !important;
}
@media (min-width: 601px) {
  #g-review .swiper,
  #g-review .swiper-container { height: auto !important; max-height: none !important; }
  #g-review .swiper-wrapper   { height: auto !important; align-items: stretch !important; }
  #g-review .swiper-slide     { height: auto !important; }
  #g-review .swiper-slide .g-review { height: 100% !important; }
}
</style>
<script id="af-review-overlap-fix-js">
(function () {
  function fit(el) {
    if (!el) return;
    el.style.removeProperty('min-height');
    var need = el.scrollHeight, have = el.clientHeight;
    if (need > have + 8) {
      el.style.setProperty('height', 'auto', 'important');
      el.style.setProperty('min-height', need + 'px', 'important');
    }
  }
  function run() {
    var w = document.getElementById('g-review');
    if (!w) return;
    fit(w.querySelector('.grwp_body'));
    fit(w);
    fit(w.closest('.e-con, .e-container, section.elementor-section'));
  }
  if (document.readyState === 'complete') run();
  else window.addEventListener('load', run);
  setTimeout(run, 1200);
  setTimeout(run, 3000);
  var rt;
  window.addEventListener('resize', function () { clearTimeout(rt); rt = setTimeout(run, 250); });
})();
</script>
    <?php
}, 99);

// ─────────────────────────────────────────────────────────────
// The review carousel's arrows did nothing when clicked.
//
// ROOT CAUSE (measured, tools/diag-review-slider.php): the widget's slider
// markup is on the page — <div class="swiper reviews_embedder_slider"> with
// its slides — but the served homepage references NO slider library at all
// ("none — no slider library is loaded", 90 scripts on the page). Embedder for
// Google Reviews enqueues its assets when it sees its shortcode in the post
// content; on this homepage the shortcode lives inside Elementor's JSON, so
// that check never matches and the script is never queued. The markup renders,
// the arrows render, and nothing is listening to them.
//
// The fix is to notice when the shortcode actually renders and load the
// plugin's own assets — its files, unmodified — so the slider initialises the
// way it always did. Scoped to pages that really render the widget, so nothing
// else on the site is affected. Footer scripts still run before
// DOMContentLoaded, so the plugin's own init listener fires normally.
// ─────────────────────────────────────────────────────────────
add_filter('do_shortcode_tag', function ($output, $tag) {
    if (!is_admin() && is_string($output) && $output !== ''
        && (strpos($output, 'id="g-review"') !== false || strpos($output, 'grwp_') !== false)) {
        $GLOBALS['af_grwp_rendered'] = true;
    }
    return $output;
}, 10, 2);

/**
 * Will the reviews widget appear on the page being served?
 *
 * The shortcode-render flag is the reliable signal, but it is only set once the
 * content has rendered, and Elementor can serve a widget from its own cache
 * without running the shortcode again — measured: the first attempt reported
 * "widget shortcode not detected" on a page that plainly shows the widget. So
 * also look at where the design is stored: if this page's Elementor data or
 * content asks for the reviews widget, its assets belong on the page.
 */
function af_grwp_page_has_widget() {
    if (is_admin()) return false;
    if (!empty($GLOBALS['af_grwp_rendered'])) return true;

    // Two attempts at inferring this failed against the live page: the
    // shortcode-render flag missed because Elementor can serve the widget from
    // its own cache, and matching the stored design missed because the
    // shortcode's own tag carries none of the words the widget's markup does.
    // Guessing the tag would be a third inference. The plugin's front-end
    // bundle is small and self-initialising, so when the plugin is active the
    // assets simply ship — a little weight on pages without the widget, in
    // exchange for arrows that work wherever the widget appears.
    static $active = null;
    if ($active === null) {
        // the plugin's front end moved into the theme (inc/ports/google-reviews.php)
        $active = defined('AF_GRWP_PORT');
        foreach ((array) get_option('active_plugins', array()) as $p) {
            if (strpos($p, 'embedder-for-google-reviews') === 0) { $active = true; break; }
        }
    }
    return $active;
}

add_action('wp_footer', function () {
    if (!af_grwp_page_has_widget()) return;

    $slug   = 'embedder-for-google-reviews';
    $loaded = array();

    // Print the plugin's files directly, and do it FIRST.
    //
    // The previous order asked WordPress to print the plugin's registered
    // handle instead, reasoning that this preserves its dependencies. Measured
    // on the live page that path reported success — state "enqueued", handle
    // "google-reviews" — while the served html still contained no plugin
    // script at all, so the arrows stayed dead. Whatever swallows that print,
    // a plain tag cannot be swallowed.
    //
    // Order matters here: the vendor swiper bundle must execute before the
    // plugin's own bundle asks it to build the carousel.
    $base = WP_PLUGIN_DIR . '/' . $slug;
    // the same files, copied byte for byte with the same layout, once the
    // plugin's front end lives in the theme (inc/ports/google-reviews.php)
    if (defined('AF_GRWP_PORT')) $base = untrailingslashit(GR_BASE_PATH);
    if (!is_dir($base)) { af_grwp_state('plugin-dir-missing', array()); return; }

    // Take every front-end file the plugin ships. An earlier version filtered
    // names to front/public/main/bundle/… and found nothing, because the
    // plugin does not name its files any of those things — the filter was a
    // guess about someone else's build output, and a guess is what left the
    // page with no slider library at all.
    $seen = array();
    foreach (array('js', 'css') as $ext) {
        $files = array();
        foreach (array('/*.', '/dist/*.', '/dist/*/*.', '/assets/*.', '/assets/*/*.',
                       '/public/*.', '/public/*/*.') as $pat) {
            $files = array_merge($files, (array) glob($base . $pat . $ext));
        }
        $files = array_values(array_unique(array_filter($files)));
        // vendor libraries first: the plugin's own bundle calls Swiper, and a
        // library that loads afterwards is a library that was not there when
        // the carousel tried to start
        usort($files, function ($a, $b) {
            $av = (int) (strpos($a, '/vendor/') !== false);
            $bv = (int) (strpos($b, '/vendor/') !== false);
            return $av === $bv ? strcmp($a, $b) : ($bv - $av);
        });
        foreach ($files as $file) {
            if (preg_match('#/(admin|backend|block|editor|gutenberg)#i', $file)) continue;
            $name = basename($file);
            if (isset($seen[$name])) continue;
            $seen[$name] = true;
            $href = defined('AF_GRWP_PORT')
                ? GR_PLUGIN_DIR_URL . substr($file, strlen($base) + 1)
                : plugins_url(substr($file, strlen($base) + 1), $base . '/' . $slug . '.php');
            $ver  = (int) @filemtime($file);
            if ($ext === 'js') {
                echo '<script src="' . esc_url($href) . '?ver=' . $ver . '"></script>' . "\n";
            } else {
                echo '<link rel="stylesheet" href="' . esc_url($href) . '?ver=' . $ver . '">' . "\n";
            }
            $loaded[] = $name;
        }
    }
    af_grwp_state($loaded ? 'from-disk' : 'nothing-found', $loaded);
}, 5);

/**
 * Say what the loader did, in a way that survives the page minifier.
 *
 * The first version reported itself in an HTML comment, and the served page
 * showed no comment at all — which read as "the loader never ran" when the
 * minifier had simply stripped it. An attribute on a real tag cannot be
 * dropped that way, so the deploy check can trust it.
 */
function af_grwp_state($state, $files) {
    printf('<script id="af-grwp-loader" data-state="%s" data-files="%s"></script>' . "\n",
        esc_attr($state), esc_attr(implode(' ', (array) $files)));
}

// ---------------------------------------------------------------------------
// PHASE 25 — Brochure "Art Code" (e.g. RK 01) shown on shop card + product page
// so an incoming order can be matched back to the printed collection book.
// Value lives in product meta `_taf_art_code` (set by tools/product-importer/
// apply_art_codes.py). Products without a code simply show nothing.
// ---------------------------------------------------------------------------
/**
 * The art code as the SHOP prints it — empty only when the product has none.
 *
 * The card, the product summary, the description (twice) and the
 * card-variations endpoint all call this, so what it returns is what a
 * shopper sees everywhere.
 *
 * Temporary codes are shown like any other. TMP-1000 … TMP-1305 go to the
 * products with no page in the collection book (tools/assign-temp-artcodes.php
 * issues them on every deploy, and they are the products' SKUs). From 21 Sep
 * they were hidden here, which left 198 products with no Art Code line at all;
 * the owner asked on 25 Sep for every product to show one, temporary included.
 */
function af_get_art_code($product = null) {
  if (!($product instanceof WC_Product)) { $product = af_wc_product($product); }
  if (!$product) return '';
  $code = get_post_meta($product->get_id(), '_taf_art_code', true);
  return is_string($code) ? trim($code) : '';
}

// Shop/archive card: small code line under the title. Always output the span
// (even when a product has no code) so every card reserves the same row
// height — otherwise cards with a code grow taller than their neighbours and
// the rows below (rating, price, description, buttons) fall out of alignment.
add_action('woocommerce_after_shop_loop_item_title', function() {
  global $product;
  $code = af_get_art_code($product);
  if ($code === '') {
    echo '<span class="af-art-code af-art-code--card af-art-code--empty" aria-hidden="true">&nbsp;</span>';
    return;
  }
  echo '<span class="af-art-code af-art-code--card">Art Code: '
     . esc_html($code) . '</span>';
}, 9);

// Single product page: code line in the summary, just under the title
add_action('woocommerce_single_product_summary', function() {
  global $product;
  $code = af_get_art_code($product);
  if ($code === '') return;
  // Say so, because the short-description filter below prints the same code
  // and cannot otherwise tell that this already has. Both land inside the
  // product summary — this at priority 6, the excerpt template at 20 — so the
  // page showed "Art Code: HD 15-GF" twice, once above the price and once
  // below it. The two existing dedupes could never catch each other: this one
  // echoes straight into the output buffer, and that one only inspects the
  // excerpt string it was handed.
  $GLOBALS['af_art_code_printed'] = true;
  echo '<p class="af-art-code af-art-code--single">Art Code: <strong>'
     . esc_html($code) . '</strong></p>';
}, 6);

// PHASE 25c — Art Code inside the product Description content as well, so the
// code is readable in the description tab/section (not only under the title).
add_filter('the_content', function($content) {
  if (is_admin()) return $content;
  if (get_post_type() !== 'product') return $content;
  $code = af_get_art_code(get_the_ID());
  if ($code === '') return $content;
  if (strpos($content, 'af-art-code--desc') !== false) return $content; // already added
  $line = '<p class="af-art-code af-art-code--desc">Art Code: <strong>'
        . esc_html($code) . '</strong></p>';
  return $line . $content;
}, 8);

// And at the top of the short description (summary excerpt) for themes that
// render only the excerpt on the product page.
add_filter('woocommerce_short_description', function($excerpt) {
  if (is_admin() || !function_exists('is_product') || !is_product()) return $excerpt;
  // Already shown under the title. Not deleted outright, because the quick-view
  // popup never fires woocommerce_single_product_summary — there this filter is
  // the only thing that puts the art code in front of the shopper, and the
  // latch is simply never set.
  if (!empty($GLOBALS['af_art_code_printed'])) return $excerpt;
  $code = af_get_art_code(get_the_ID());
  if ($code === '' || strpos((string)$excerpt, 'af-art-code') !== false) return $excerpt;
  return '<p class="af-art-code af-art-code--desc">Art Code: <strong>'
       . esc_html($code) . '</strong></p>' . $excerpt;
}, 8);

// Show the code on admin order line items so fulfilment can read it off an order.
add_filter('woocommerce_display_item_meta', function($html, $item, $args) {
  $pid = method_exists($item, 'get_product_id') ? $item->get_product_id() : 0;
  $code = $pid ? get_post_meta($pid, '_taf_art_code', true) : '';
  if ($code) {
    $html .= '<br><small>Art Code: ' . esc_html($code) . '</small>';
  }
  return $html;
}, 10, 3);

add_action('wp_head', function() { ?>
<style>
.af-art-code--card{display:block;margin:2px 0 4px;font-size:12px;letter-spacing:.04em;
  color:#8a6d3b;font-weight:600;text-transform:uppercase;}
.af-art-code--empty{visibility:hidden;}
.af-art-code--single{margin:.4em 0 .8em;font-size:14px;color:#6b6b6b;
  letter-spacing:.03em;}
.af-art-code--single strong{color:#8a6d3b;letter-spacing:.06em;}
.af-art-code--desc{margin:0 0 .9em;font-size:14px;color:#6b6b6b;
  letter-spacing:.03em;}
.af-art-code--desc strong{color:#8a6d3b;letter-spacing:.06em;}
</style>
<?php });

// Remove the "Themes" filter widget from the shop / category sidebar.
add_action('wp_footer', function() {
  if (!function_exists('is_shop')) return;
  if (!(is_shop() || is_product_taxonomy() || is_product_category() || is_product_tag())) return;
  ?>
<script>
(function(){
  function hideThemes(){
    var heads = document.querySelectorAll(
      '.widget-title, .widgettitle, .widget h2, .widget h3, .widget h4, aside h2, aside h3, aside h4, #secondary h2, #secondary h3, .elementor-heading-title'
    );
    heads.forEach(function(t){
      if (!/^\s*themes\s*$/i.test((t.textContent||'').trim())) return;
      var w = t.closest('.widget, li.widget, section.widget, .elementor-widget, .wp-block-group, .sidebar-widget');
      if (w) { w.style.setProperty('display','none','important'); return; }
      // fallback: hide the heading and the list that follows it
      t.style.setProperty('display','none','important');
      var nx = t.nextElementSibling; if (nx) nx.style.setProperty('display','none','important');
    });
  }
  document.addEventListener('DOMContentLoaded', hideThemes);
  window.addEventListener('load', hideThemes);
  [300,900,2000].forEach(function(d){ setTimeout(hideThemes, d); });
})();
</script>
  <?php
}, 50);

// PHASE 25b — product CARD Art Code via JS, SITE-WIDE (homepage carousels,
// related products, up-sells, search, category grids). The Postero loop and
// Elementor product widgets don't fire the standard WooCommerce card hook, so
// inject client-side. Runs on every front-end page; the query only executes on
// a LiteSpeed cache-miss, and the JS no-ops where there are no product cards.
/**
 * The map of every product's art code used to be printed inline here: 60 KB
 * of JSON in every page (the cart page was 950 KB of HTML, 16 Oct) and one
 * query over all 374k postmeta rows on every uncached view. It now lives in
 * uploads/af/art-codes.json, rebuilt when missing, older than six hours or
 * when an art code changes, and the browser caches it. The script below is
 * unchanged apart from loading the map before it runs.
 */
function af_art_code_map_file() {
    try {
        $up = wp_get_upload_dir();
        $dir = $up['basedir'] . '/af'; $path = $dir . '/art-codes.json'; $url = $up['baseurl'] . '/af/art-codes.json';
        $fresh = is_file($path) && filesize($path) > 20 && filemtime($path) > time() - 6 * HOUR_IN_SECONDS;
        if (!$fresh) {
            if (!wp_mkdir_p($dir)) return null;
            global $wpdb;
            $rows = $wpdb->get_results(
              "SELECT p.ID, p.post_name, m.meta_value
                 FROM {$wpdb->postmeta} m
                 JOIN {$wpdb->posts} p ON p.ID = m.post_id
                WHERE m.meta_key='_taf_art_code' AND m.meta_value<>''
                  AND p.post_status='publish' AND p.post_password=''");
            $map = array(); $slugmap = array();
            foreach ((array) $rows as $r) {
                $map[(string) $r->ID] = $r->meta_value;
                if ($r->post_name) $slugmap[$r->post_name] = $r->meta_value;
            }
            if (!$map) return null;
            $json = wp_json_encode(array('codes' => $map, 'slugs' => $slugmap));
            if (!$json) return null;
            $tmp = $path . '.' . getmypid() . '.tmp';
            if (file_put_contents($tmp, $json) === false || !@rename($tmp, $path)) { @unlink($tmp); return null; }
            if (!is_file($dir . '/index.html')) @file_put_contents($dir . '/index.html', '');
        }
        return array($path, $url, (int) @filemtime($path));
    } catch (\Throwable $e) {
        return null;
    }
}
// an art code saved, changed or removed: the next page view rebuilds the file
foreach (array('added_post_meta', 'updated_post_meta', 'deleted_post_meta') as $af_ac_hook) {
    add_action($af_ac_hook, function ($mid, $pid, $key) {
        if ($key !== '_taf_art_code') return;
        $up = wp_get_upload_dir(); @unlink($up['basedir'] . '/af/art-codes.json');
    }, 10, 3);
}
unset($af_ac_hook);

add_action('wp_footer', function() {
  if (is_admin()) return;
  $af_map = af_art_code_map_file();
  if (!$af_map) return;
  ?>
<script>
(function(){
  var CODES = {}, SLUGS = {};
  function place(card, code){
    if (card.getAttribute('data-af-code')) return;
    // Dedupe at the visual-card level: if the nearest product container
    // already shows an Art Code (from the PHP hook or a previous run), skip —
    // never add a second one.
    var box = card.closest('li.product, .product-card, .trending-card, .product, article') || card;
    if (box.querySelector('.af-art-code--card')) { card.setAttribute('data-af-code','1'); box.setAttribute('data-af-code','1'); return; }
    card.setAttribute('data-af-code','1');
    var span = document.createElement('span');
    if (code) {
      span.className = 'af-art-code af-art-code--card';
      span.textContent = 'Art Code: ' + code;
    } else {
      // No code on this product — insert an empty line of the same class so
      // it still reserves the row's height. Without this, cards that DO have
      // a code grow taller than their neighbours and every row below (rating,
      // price, description, buttons) drifts out of alignment across the row.
      span.className = 'af-art-code af-art-code--card af-art-code--empty';
      span.setAttribute('aria-hidden', 'true');
      span.innerHTML = '&nbsp;';
    }
    span.style.setProperty('display','block','important');
    // prefer to sit right under the product title, else after the card's link
    var title = card.querySelector('.woocommerce-loop-product__title, .product-title, .trending-title, h2, h3, h4, .elementor-heading-title');
    if (title && title.parentNode) { title.parentNode.insertBefore(span, title.nextSibling); return; }
    card.appendChild(span);
  }
  // Resolve the Art Code from any link in the card whose URL ends in a known
  // product slug — works for /product/<slug>/ and flat /<slug>/ permalinks.
  // SLUGS only holds real product post_names, so a false match is impossible.
  function slugFrom(card){
    var links = card.querySelectorAll('a[href]');
    for (var i=0;i<links.length;i++){
      var href = links[i].getAttribute('href') || '';
      var path = href.split('#')[0].split('?')[0].replace(/\/+$/,'');
      var seg = path.substring(path.lastIndexOf('/')+1);
      if (seg && SLUGS[seg]) return SLUGS[seg];
    }
    return null;
  }
  function inject(root){
    root = root || document;
    // Cast a wide net: standard Woo cards, the homepage "Shop by Collection"
    // slider tiles (.product-card), any element carrying a post-<id> class
    // (Elementor product widgets, carousel slides), and any add-to-cart button
    // that exposes data-product_id.
    var cards = root.querySelectorAll(
      'li.product, .product-card, .trending-card, [class*="type-product"], [class*="post-"], [data-product_id]'
    );
    cards.forEach(function(card){
      if (card.getAttribute('data-af-code')) return;
      // [class*="post-"] is a SUBSTRING match, and a shop archive's own <body>
      // carries both "post-type-archive-product" and "has-post-thumbnail". So
      // <body> matched, no numeric id could be read off it, and the slug
      // fallback below then searched every link on the page and returned the
      // FIRST product's code — which is how a bare "ART CODE: TP - 0504" came
      // to be printed under the heading "All Our Wall Art", as if the whole
      // catalogue were one artwork.
      if (card === document.body || card === document.documentElement) return;
      var id = null;
      var m = (typeof card.className === 'string' ? card.className : '').match(/(?:^|\s)post-(\d+)/);
      if (m) id = m[1];
      if (!id && card.hasAttribute('data-product_id')) id = card.getAttribute('data-product_id');
      if (!id) {
        var b = card.querySelector('[data-product_id]');
        if (b) id = b.getAttribute('data-product_id');
      }
      if (!id) {                       // fallback: add-to-cart URL
        var lk = card.querySelector('a[href*="add-to-cart="]');
        if (lk) { var mm = lk.href.match(/add-to-cart=(\d+)/); if (mm) id = mm[1]; }
      }
      // The slug fallback reads whatever product link it finds first, so it is
      // only safe on something that is ONE product. A numeric post id proves
      // that; otherwise the element has to be a card by its own class, not by
      // happening to contain the letters "post-".
      var looksLikeCard = !!id;
      if (!looksLikeCard) {
        try { looksLikeCard = card.matches('li.product, .product-card, .trending-card, [class*="type-product"]'); }
        catch (e) { looksLikeCard = false; }
      }
      if (!looksLikeCard) return;
      var code = (id && CODES[id]) ? CODES[id] : slugFrom(card);   // slug fallback
      if (!code && !id) return;   // couldn't confirm this is a real product card — skip
      // climb to the nearest sensible card container so the label sits with
      // the title, not buried inside a button
      var host = card;
      if ((card.hasAttribute('data-product_id') || card.tagName === 'A')) {
        host = card.closest('li.product, .product-card, .trending-card, .product, [class*="post-"], article, .elementor-widget') || card;
      }
      place(host, code || null);
    });
  }
  function run(){ inject(document); }
  function start(){
    run();
    document.addEventListener('DOMContentLoaded', run);
    window.addEventListener('load', run);
    [400,1000,2200,4000].forEach(function(d){ setTimeout(run, d); });
    // catch carousels / AJAX / lazy-loaded product cards
    try {
      var obs = new MutationObserver(function(muts){
        for (var i=0;i<muts.length;i++){ if (muts[i].addedNodes && muts[i].addedNodes.length){ run(); break; } }
      });
      obs.observe(document.body, {childList:true, subtree:true});
    } catch(e){}
  }
  // the map is a cached file (see af_art_code_map_file), not 60 KB of inline JSON
  if (!window.fetch) return;
  fetch(<?php echo wp_json_encode($af_map[1] . '?v=' . $af_map[2]); ?>, {credentials:'omit'})
    .then(function(r){ return r.ok ? r.json() : null; })
    .then(function(j){ if (!j || !j.codes) return; CODES = j.codes; SLUGS = j.slugs || {}; start(); })
    .catch(function(){});
})();
</script>
  <?php
}, 210);

// ─────────────────────────────────────────────────────────────
// PHASE 27 — "Frame The Moment" (/frame-the-moment/)
// The Elementor version rendered a dead preview panel (an empty grey
// box) and an unstyled browser file input. Replace it with a working,
// styled builder — same proven approach as /try-on-wall/:
//   • upload your own photo (drag & drop or click)
//   • pick product type, size, frame type and frame colour
//   • LIVE realistic preview: bevelled moulding + mat + glass glare,
//     true-to-size aspect ratio, shown on a wall
//   • live price from af_pricing_config()
//   • download the preview, or send the whole spec via WhatsApp
// ─────────────────────────────────────────────────────────────
add_action('template_redirect', function () {
    if (!function_exists('is_page') || !is_page(array('frame-the-moment','frame-the-moments'))) return;

    $cfg = function_exists('af_pricing_config') ? af_pricing_config() : array(
        'sizes'  => array('2×3 ft (24×36 in)'=>1.0),
        'frames' => array('Without Frame'=>0,'Fibre Frame'=>25,'Floating Frame'=>40,'Aluminium Frame'=>55),
        'colors' => array('Black'=>0,'Silver'=>0,'Gold'=>10,'Rose Gold'=>10),
    );
    $sym  = function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '$';
    $base = 179.0;   // starting price for a customer-supplied photo print

    // Product types this service prints on
    $types = array(
        'Canvas Print'        => 0,
        'Framed Canvas'       => 20,
        'Gallery Print'       => 15,
        'Personalised Gift'   => 10,
    );
    $wa = '16104707280';

    get_header();
    af_preview_share_assets();
    ?>
    <div class="af-ftm-wrap">
      <div class="af-ftm-card">
        <a class="af-ftm-home" href="<?php echo esc_url(home_url('/')); ?>">← Back to Home</a>
        <span class="af-ftm-badge">Your Photo · Our Craft</span>
        <h1 class="af-ftm-title">Frame The Moment</h1>
        <p class="af-ftm-sub">Upload a photo you love, choose the size and frame, and see it become gallery-quality wall art — previewed instantly, before you order.</p>

        <div class="af-ftm-grid">
          <!-- ── controls ── -->
          <div class="af-ftm-panel">
            <div class="af-ftm-step"><span class="af-ftm-num">1</span><span class="af-ftm-steptitle">Upload your photo</span></div>
            <label for="ftm-file" class="af-ftm-drop" id="ftm-drop">
              <input type="file" id="ftm-file" accept="image/*" hidden>
              <span class="af-ftm-dropic">🖼️</span>
              <strong id="ftm-dropmain">Click to choose a photo</strong>
              <small id="ftm-dropsub">or drag &amp; drop it here · JPG or PNG</small>
            </label>
            <p class="af-ftm-hint" id="ftm-quality"></p>

            <div class="af-ftm-step"><span class="af-ftm-num">2</span><span class="af-ftm-steptitle">Choose your print</span></div>
            <label>Product Type</label>
            <select id="ftm-type">
              <?php foreach ($types as $t => $fee): ?>
                <option value="<?php echo esc_attr($t); ?>" data-fee="<?php echo esc_attr($fee); ?>"><?php echo esc_html($t); ?><?php if($fee>0) echo ' (+'.$sym.$fee.')'; ?></option>
              <?php endforeach; ?>
            </select>

            <label>Frame Size</label>
            <select id="ftm-size">
              <?php $ftm_sizes = function_exists('af_sizes_available') ? af_sizes_available() : array_keys($cfg['sizes']); ?>
              <?php foreach ($ftm_sizes as $i => $s): ?>
                <option value="<?php echo esc_attr($s); ?>" data-mult="<?php echo esc_attr($cfg['sizes'][$s]); ?>"<?php echo $i===0?' selected':''; ?>><?php echo esc_html($s); ?></option>
              <?php endforeach; ?>
            </select>

            <div class="af-ftm-step"><span class="af-ftm-num">3</span><span class="af-ftm-steptitle">Style the frame</span></div>
            <label>Frame Type</label>
            <select id="ftm-frame">
              <?php $ftm_def = function_exists('af_frame_default') ? af_frame_default() : array_key_first($cfg['frames']); ?>
              <?php foreach ($cfg['frames'] as $f => $fee): $oos = function_exists('af_frame_is_in_stock') && !af_frame_is_in_stock($f); ?>
                <?php $ftm_sq = isset($cfg['frame_sqft'][$f]) ? (float) $cfg['frame_sqft'][$f] : 0; ?>
                <option value="<?php echo esc_attr($f); ?>" data-fee="<?php echo esc_attr($fee); ?>" data-sqft="<?php echo esc_attr($ftm_sq); ?>"<?php echo $oos?' disabled':''; ?><?php echo $f===$ftm_def?' selected':''; ?>><?php echo esc_html($f); ?><?php if($ftm_sq>0) echo ' (+'.$sym.$ftm_sq.'/sq ft)'; elseif($fee>0) echo ' (+'.$sym.$fee.')'; ?><?php if($oos) echo ' — Out of stock'; ?></option>
              <?php endforeach; ?>
            </select>

            <label>Frame Colour</label>
            <div class="af-ftm-swatches" id="ftm-swatches"></div>
            <select id="ftm-color" class="af-ftm-hidden">
              <?php foreach ($cfg['colors'] as $c => $fee): ?>
                <option value="<?php echo esc_attr($c); ?>" data-fee="<?php echo esc_attr($fee); ?>"><?php echo esc_html($c); ?></option>
              <?php endforeach; ?>
            </select>

            <label>Wall Layout</label>
            <div class="af-ftm-layouts" id="ftm-layouts">
              <button type="button" class="af-ftm-lay on" data-n="1"><span>Single</span></button>
              <button type="button" class="af-ftm-lay" data-n="2"><span>2 Panels</span></button>
              <button type="button" class="af-ftm-lay" data-n="4"><span>4 Panels</span></button>
            </div>

            <!-- In the panel for the same reason as Try On Wall's: the copy in
                 the calibration overlay is only on screen while the camera is
                 aligning. See the note there. -->
            <label>Wall height</label>
            <div class="af-ftm-wallh" id="ftm-wallh">
              <button type="button" data-ft="8">8 ft</button>
              <button type="button" data-ft="9">9 ft</button>
              <button type="button" data-ft="10" class="on">10 ft</button>
            </div>

            <p class="af-sharelabel">Keep it &amp; share it</p>
            <div class="af-share">
              <button type="button" id="ftm-saveacct">💾 Save to my account</button>
            </div>
            <div class="af-share">
              <button type="button" id="ftm-share-wa" class="af-share-wa">WhatsApp</button>
              <button type="button" id="ftm-share-mail">Email</button>
              <button type="button" id="ftm-share-copy">Copy link</button>
            </div>
            <p class="af-ftm-fine">
              Saved previews live in your account.
              <a href="#" id="ftm-acctlink" style="display:none">View saved previews →</a>
            </p>
          </div>

          <!-- ── live preview ── -->
          <div class="af-ftm-stagewrap">
            <div id="ftm-stage" class="af-ftm-stage">
              <img id="ftm-wall" class="af-ftm-wall" alt="" src="<?php echo esc_url( wp_get_upload_dir()['baseurl'] . '/mockups/Radha-Krishna-Canvas-Wall-Art-Placement_Living-Room-blankwall.jpg' ); ?>">
              <div id="ftm-empty" class="af-ftm-empty">
                <span class="af-ftm-emptyic">📷</span>
                <strong>Your photo appears here</strong>
                <small>Upload a photo to see it framed on a wall, true to size.</small>
              </div>
              <video id="ftm-camv" class="af-ftm-camv" autoplay playsinline muted></video>
              <button type="button" id="ftm-camstop" class="af-ftm-camstop" style="display:none" aria-label="Stop camera">✕<span class="af-ftm-camstop-t"> Stop camera</span></button>
              <!-- live-camera calibration, the same measured-scale tool the
                   Try On Wall page uses: fit the wall between ceiling and floor
                   into the rectangle and it turns green, at which point
                   pixels-per-foot is measured rather than assumed -->
              <div id="ftm-cal" class="af-ftm-cal" style="display:none">
                <div id="ftm-calbox" class="af-ftm-calbox">
                  <span class="af-ftm-calcorner tl"></span><span class="af-ftm-calcorner tr"></span>
                  <span class="af-ftm-calcorner bl"></span><span class="af-ftm-calcorner br"></span>
                </div>
                <div id="ftm-calmsg" class="af-ftm-calmsg"><span class="af-ftm-calmsg-long">Step back or forward until the <strong>ceiling line</strong> touches the top edge and the <strong>floor line</strong> touches the bottom edge</span><span class="af-ftm-calmsg-short"><strong>Ceiling</strong> to the top edge, <strong>floor</strong> to the bottom</span></div>
                <div id="ftm-calh" class="af-ftm-calh">
                  <span>Wall height</span>
                  <button type="button" data-ft="8">8 ft</button>
                  <button type="button" data-ft="9">9 ft</button>
                  <button type="button" data-ft="10" class="on">10 ft</button>
                </div>
              </div>
              <button type="button" id="ftm-recal" class="af-ftm-recal" style="display:none">📐 True scale locked · tap to recalibrate</button>
              <div id="ftm-framebox" class="af-ftm-framebox" style="display:none">
                <div id="ftm-panels" class="af-ftm-panels"></div>
              </div>
            </div>
            <p class="af-ftm-tip" id="ftm-tip">Shown true to scale on a 10&nbsp;ft wall — change the size to compare.</p>

            <!-- The room switcher and the price belong beside the preview they
                 describe. Left in the rail they pushed it 650px past the
                 bottom of the wall image, which read as a broken page. -->
            <div class="af-ftm-under">
              <div class="af-ftm-panel af-ftm-flat">
                <div class="af-ftm-step"><span class="af-ftm-num">4</span><span class="af-ftm-steptitle">Set the room</span></div>
                <label>Room Scene</label>
                <div class="af-ftm-scenes" id="ftm-scenes"></div>
                <button type="button" id="ftm-cambtn" class="af-ftm-cambtn">🎥 Use live camera <em>point it at your wall</em></button>
              </div>
              <div class="af-ftm-panel af-ftm-flat">
                <div class="af-ftm-price"><span>Your price</span><strong id="ftm-price">—</strong></div>
                <p class="af-ftm-notes">✓ Inclusive of all taxes &nbsp;·&nbsp; 📦 Free secure packaging</p>
                <div class="af-ftm-actions">
                  <button type="button" id="ftm-save" class="af-ftm-btn ghost">⤓ Save Preview</button>
                  <a href="#" id="ftm-send" class="af-ftm-btn solid" target="_blank" rel="noopener">Confirm &amp; Send</a>
                </div>
                <p class="af-ftm-fine">“Confirm &amp; Send” opens WhatsApp with your choices ready — just attach the saved preview and hit send.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <script>
    (function(){
      var $ = function(id){ return document.getElementById(id); };
      var SYM = <?php echo wp_json_encode($sym); ?>;
      var BASE = <?php echo (float) $base; ?>;
      var WA = <?php echo wp_json_encode($wa); ?>;
      var SWATCH = {'Black':'#1e1e1e','Silver':'#c0c0c0','Gold':'#d4af37','Rose Gold':'#b76e79'};
      var MAT = {
        'Black':'linear-gradient(135deg,#4a4a4a 0%,#1c1c1c 42%,#050505 58%,#333 100%)',
        'Silver':'linear-gradient(135deg,#fdfdfd 0%,#c4c4c4 42%,#8f8f8f 58%,#e6e6e6 100%)',
        'Gold':'linear-gradient(135deg,#fbe7ad 0%,#d8b445 42%,#a67c1e 58%,#f2d879 100%)',
        'Rose Gold':'linear-gradient(135deg,#f7d3c8 0%,#dca596 42%,#b76e79 58%,#efbcae 100%)'
      };
      // Mitred corner seam + polished-metal specular glint over the material
      // gradient — same treatment as /try-on-wall/'s frameBG(), matched to
      // reference photos of the actual aluminium mouldings (buffed sheen +
      // hard highlight, not a brushed/sandy texture).
      function frameBG(mat){
        var hi='rgba(255,255,255,.45)', lo='rgba(0,0,0,.5)';
        return [
          // mitred corner grooves: a lit inner edge then a dark outer edge per
          // quadrant, so each corner reads as a carved cut rather than a
          // printed line
          'linear-gradient(to bottom right, transparent calc(50% - 2.5px), '+hi+' calc(50% - 1.5px), '+lo+' calc(50% + .5px), transparent calc(50% + 2px)) left top/50% 50% no-repeat',
          'linear-gradient(to bottom left,  transparent calc(50% - 2.5px), '+hi+' calc(50% - 1.5px), '+lo+' calc(50% + .5px), transparent calc(50% + 2px)) right top/50% 50% no-repeat',
          'linear-gradient(to top right,    transparent calc(50% - 2.5px), '+hi+' calc(50% - 1.5px), '+lo+' calc(50% + .5px), transparent calc(50% + 2px)) left bottom/50% 50% no-repeat',
          'linear-gradient(to top left,     transparent calc(50% - 2.5px), '+hi+' calc(50% - 1.5px), '+lo+' calc(50% + .5px), transparent calc(50% + 2px)) right bottom/50% 50% no-repeat',
          // polished-metal glint: a narrow, hard-edged bright streak swept
          // around the ring so the top/left face catches a sharp specular
          // highlight the way buffed aluminium does, with a fainter second
          // glint opposite it
          'conic-gradient(from 300deg at 50% 50%, rgba(255,255,255,0) 0deg, rgba(255,255,255,.85) 16deg, rgba(255,255,255,0) 38deg, rgba(255,255,255,0) 175deg, rgba(255,255,255,.5) 196deg, rgba(255,255,255,0) 218deg, rgba(255,255,255,0) 360deg)',
          // broad wraparound light/shadow so each of the 4 faces reads as its
          // own lit or shaded plane (upper-left key light), pushed to higher
          // contrast so the moulding reads as metal, not painted flat colour
          'conic-gradient(from 315deg at 50% 50%, rgba(255,255,255,.5) 0deg, rgba(255,255,255,0) 95deg, rgba(0,0,0,.55) 180deg, rgba(0,0,0,0) 265deg, rgba(255,255,255,.5) 360deg)',
          mat
        ].join(',');
      }
      var photoURL = null, photoW = 0, photoH = 0, photoName = '';

      // ── real photographic rooms (same cleaned blank-wall mockups the
      //    Try-On-Wall tool uses). focusY = where the wall centre sits, so the
      //    frame always hangs on clean wall above the furniture.
      var MOCK = <?php echo wp_json_encode( wp_get_upload_dir()['baseurl'] . '/mockups/' ); ?>;
      var SCENES = [
        { name:'Living Room', file:'Radha-Krishna-Canvas-Wall-Art-Placement_Living-Room-blankwall.jpg', focus:'52% 50%', top:'40%', left:'52%' },
        { name:'Lounge',      file:'Radha-Krishna_Wall-Art_-Living-Room-blankwall.jpg',                 focus:'51% 50%', top:'38%', left:'51%' },
        { name:'Bright Loft', file:'Radha-Krishna-Abstract-Wall-Art_living-room-blankwall.jpg',         focus:'56% 50%', top:'40%', left:'56%' },
        { name:'Studio',      file:'TAF-RADHA-KRISHNA-18530-room-1-blankwall.jpg',                      focus:'48% 50%', top:'44%', left:'48%' }
      ];
      var scene = SCENES[0];
      (function(){
        var wrap = $('ftm-scenes');
        SCENES.forEach(function(s, i){
          var b = document.createElement('button');
          b.type = 'button';
          b.className = 'af-ftm-scene' + (i === 0 ? ' on' : '');
          b.style.backgroundImage = 'url("' + MOCK + s.file + '")';
          b.title = s.name;
          b.innerHTML = '<span>' + s.name + '</span>';
          b.addEventListener('click', function(){
            wrap.querySelectorAll('.af-ftm-scene').forEach(function(x){ x.classList.remove('on'); });
            b.classList.add('on');
            setScene(i);
          });
          wrap.appendChild(b);
        });
      })();
      function setScene(i){
        scene = SCENES[i];
        if (typeof stopCam === 'function') stopCam();
        var im = $('ftm-wall');
        im.src = MOCK + scene.file;
        im.style.objectPosition = scene.focus;
        var box = $('ftm-framebox');
        box.style.top  = scene.top;
        box.style.left = scene.left;
        render();
      }

      // ── colour swatches drive the hidden select ──
      (function(){
        var sel = $('ftm-color'), wrap = $('ftm-swatches');
        Array.prototype.forEach.call(sel.options, function(o, i){
          var b = document.createElement('button');
          b.type = 'button';
          b.className = 'af-ftm-sw' + (i===0 ? ' on' : '');
          b.style.background = SWATCH[o.value] || '#1a1a1a';
          b.title = o.textContent;
          b.innerHTML = '<span>' + o.value + '</span>';
          b.addEventListener('click', function(){
            wrap.querySelectorAll('.af-ftm-sw').forEach(function(x){ x.classList.remove('on'); });
            b.classList.add('on'); sel.selectedIndex = i; render();
          });
          wrap.appendChild(b);
        });
      })();

      // ── size → real feet, for true-to-scale rendering ──
      function sizeFeet(label){
        var m = String(label).match(/([\d.]+)\s*[x×]\s*([\d.]+)\s*ft/i);
        if (m) return { h: parseFloat(m[1]), w: parseFloat(m[2]) };
        var inch = String(label).match(/(\d+)\s*[x×]\s*(\d+)\s*in/i);
        if (inch) return { h: parseFloat(inch[1])/12, w: parseFloat(inch[2])/12 };
        return { h: 3, w: 2 };
      }

      function price(){
        // data-mult now carries the rate card's absolute USD price per size —
        // the size IS the price (matches af_calc_price); BASE no longer
        // multiplies into it
        var sizePrice = parseFloat($('ftm-size').selectedOptions[0].dataset.mult) || BASE;
        // no frame means no frame-finish surcharge (matches af_calc_price)
        var noFrame = $('ftm-frame').value === 'Without Frame';
        // Aluminium is $4 a square foot of the size (matches af_frame_fee)
        var fo = $('ftm-frame').selectedOptions[0], perSq = parseFloat(fo.dataset.sqft) || 0;
        var sqm = ($('ftm-size').value || '').match(/\((\d+(?:\.\d+)?)\s*[x×]\s*(\d+(?:\.\d+)?)\s*in\)/);
        var fee  = ((perSq && sqm) ? Math.round(sqm[1] * sqm[2] / 144 * perSq * 100) / 100 : (parseFloat(fo.dataset.fee) || 0))
                 + (noFrame ? 0 : (parseFloat($('ftm-color').selectedOptions[0].dataset.fee) || 0))
                 + (parseFloat($('ftm-type').selectedOptions[0].dataset.fee)  || 0);
        return Math.round((sizePrice + fee) * 100) / 100;
      }

      // ── 1 / 2 / 4 panel layouts (spec §8) ──
      var LAYOUT = 1;
      $('ftm-layouts').addEventListener('click', function(e){
        var b = e.target.closest('.af-ftm-lay'); if (!b) return;
        this.querySelectorAll('.af-ftm-lay').forEach(function(x){ x.classList.remove('on'); });
        b.classList.add('on');
        LAYOUT = parseInt(b.getAttribute('data-n'), 10) || 1;
        // the crop only depends on the print ratio, so switching layout reuses it
        render();
      });

      // Cover-crop the photo once to the frame's aspect ratio so multi-panel
      // slices are distortion-free (CSS percentage slicing would stretch).
      var cropKey = '', cropURL = null, cropImg = null;
      function ensureCrop(ratio, cb){
        var key = photoURL ? (photoURL.length + ':' + photoW + 'x' + photoH + ':' + ratio.toFixed(4)) : '';
        if (key && key === cropKey && cropURL) { cb(); return; }
        var im = new Image();
        im.onload = function(){
          var sw = im.naturalWidth, sh = im.naturalHeight;
          var tw = Math.min(sw, 1600), th = Math.round(tw * ratio);
          var cv = document.createElement('canvas'); cv.width = tw; cv.height = th;
          var cx = cv.getContext('2d');
          var s = Math.max(tw / sw, th / sh);
          cx.drawImage(im, (tw - sw * s) / 2, (th - sh * s) / 2, sw * s, sh * s);
          cropURL = cv.toDataURL('image/jpeg', 0.92);
          cropImg = new Image(); cropImg.src = cropURL;
          cropKey = key;
          cb();
        };
        im.src = photoURL;
      }

      function stylePanels(){
        var frame = $('ftm-frame').value, color = $('ftm-color').value;
        var prof, matw, matbg = '#f6f1e6';
        if (frame === 'Without Frame')          { prof = 0; matw = 0; }
        else if (frame === 'Floating Frame')    { prof = 5; matw = 6; matbg = '#161616'; }
        else if (frame === 'Aluminium Frame')   { prof = 4; matw = 7; }
        else                                    { prof = 7; matw = 9; }
        if (LAYOUT > 1) { matw = Math.max(0, matw - 4); }
        var wrap = $('ftm-panels');
        wrap.querySelectorAll('.af-ftm-pframe').forEach(function(m){
          // Same fix as /try-on-wall/: a bare percentage shrinks to a sliver on
          // small previews, too thin for the material gradient or bevel to read.
          m.style.padding      = prof ? 'max(11px, ' + prof + '%)' : '0';
          m.style.background   = prof ? frameBG(MAT[color] || MAT['Black']) : 'transparent';
          m.style.borderRadius = (frame === 'Aluminium Frame') ? '3px' : '2px';
          m.style.boxShadow    = prof
            ? 'inset 2px 2px 3px rgba(255,255,255,.5), inset -3px -3px 6px rgba(0,0,0,.6), inset 0 0 0 1px rgba(0,0,0,.3), 0 1px 3px rgba(0,0,0,.35)'
            : 'none';
        });
        wrap.querySelectorAll('.af-ftm-pmat').forEach(function(m){
          m.style.padding    = matw ? 'max(8px, ' + matw + '%)' : '0';
          m.style.background = matw ? matbg : 'transparent';
          m.style.boxShadow  = matw ? 'inset 0 0 8px rgba(0,0,0,.28)' : 'none';
        });
      }

      // The rendered box is taller than the artwork alone (moulding + mat), and a
      // 2/4-panel set has a different overall ratio again, so nudge the width
      // until the box really occupies `wantH` on the wall. Idempotent — once the
      // height matches it is a no-op — and sequenced so only the newest wins.
      var wantH = 0, scaleSeq = 0;
      function fitToWall(){
        if (!(wantH > 0)) return;
        var boxEl = $('ftm-framebox'), stage = $('ftm-stage').getBoundingClientRect();
        var targetH = wantH, seq = ++scaleSeq;
        requestAnimationFrame(function(){
          if (seq !== scaleSeq) return;
          var b = boxEl.getBoundingClientRect();
          if (b.height > 0 && b.width > 0) {
            boxEl.style.width = Math.max(70, Math.min(b.width * (targetH / b.height), stage.width * 0.92)) + 'px';
          }
        });
      }

      function render(){
        var p = price();
        $('ftm-price').textContent = SYM + p.toFixed(2);
        buildSendLink(p);
        if (!photoURL) return;

        $('ftm-empty').style.display = 'none';
        $('ftm-framebox').style.display = 'block';

        var ft = sizeFeet($('ftm-size').value);
        var ratio = ft.h / ft.w;

        // Work out the true-to-scale target BEFORE building the panels: when the
        // crop is already cached ensureCrop() calls back synchronously, and
        // fitToWall() would otherwise still be aiming at the previous size.
        var stage = $('ftm-stage').getBoundingClientRect();
        // The visitor's chosen wall height (CAL.wallFt); WALL_FT is only the
        // fallback, including on the first render before CAL is assigned.
        var WALL_FT = 10;      // fallback wall height for the room photos
        var WALL_FRAC = 0.78;  // …of which this much of the stage is wall, not floor
        var wallFt = (typeof CAL !== 'undefined' && CAL && CAL.wallFt) || WALL_FT;
        var h, w;
        if (typeof CAL !== 'undefined' && camOn && CAL.locked && CAL.pxPerFt > 0) {
          // the wall has been measured through the camera: use the real
          // pixels-per-foot rather than the assumption baked into the room
          // photos, so the print is shown at its true size and rescales as
          // the visitor walks towards or away from the wall
          h = ft.h * CAL.pxPerFt;
          w = ft.w * CAL.pxPerFt;
        } else {
          h = stage.height * WALL_FRAC * (ft.h / wallFt);
          w = h * (ft.w / ft.h);
        }
        // Cap generously, so a larger print never renders smaller than a smaller
        // one just because it hit the limit first on a narrow phone.
        var maxW = stage.width * 0.92, maxH = stage.height * 0.86;
        if (w > maxW) { h *= maxW / w; w = maxW; }
        if (h > maxH) { w *= maxH / h; h = maxH; }
        $('ftm-framebox').style.width = Math.max(70, w) + 'px';
        wantH = h;

        ensureCrop(ratio, function(){
          // (re)build the panel set
          var wrap = $('ftm-panels'); wrap.innerHTML = '';
          for (var i = 0; i < LAYOUT; i++){
            var panel = document.createElement('div'); panel.className = 'af-ftm-wpanel';
            var fr = document.createElement('div'); fr.className = 'af-ftm-pframe';
            var mt = document.createElement('div'); mt.className = 'af-ftm-pmat';
            var art = document.createElement('div'); art.className = 'af-ftm-part';
            art.style.backgroundImage = 'url("' + cropURL + '")';
            if (LAYOUT === 1){
              art.style.backgroundSize = '100% 100%';
              art.style.backgroundPosition = '0 0';
            } else {
              art.style.backgroundSize = (LAYOUT * 100) + '% 100%';
              art.style.backgroundPosition = (i / (LAYOUT - 1) * 100) + '% 0';
            }
            art.style.paddingBottom = (ratio * LAYOUT * 100) + '%';
            var glass = document.createElement('span'); glass.className = 'af-ftm-glass';
            mt.appendChild(art); mt.appendChild(glass);
            fr.appendChild(mt); panel.appendChild(fr); wrap.appendChild(panel);
          }
          stylePanels();
          fitToWall();     // panels exist now, so the box can be measured
        });

        // Which scale is actually in force — see the note in Try On Wall's
        // applyScale(). The old text said "on a 10 ft wall" in every state,
        // ignoring the height the visitor picked and claiming true scale with
        // the camera running but not yet measured.
        var tipMeasured = ( typeof CAL !== 'undefined' && CAL && camOn && CAL.locked && CAL.pxPerFt > 0 );
        var ftmWallPhrase = ( wallFt === 8 ? 'an ' : 'a ' ) + wallFt + ' ft wall';
        var tipLead = tipMeasured
          ? 'True to scale, measured against your own wall — '
          : ( camOn
              ? 'Not to scale yet — drawn against ' + ftmWallPhrase + ' until you fit the rectangle to yours. '
              : 'Shown true to scale on ' + ftmWallPhrase + ' — ' );
        $('ftm-tip').textContent = tipLead +
          ft.h.toFixed(1).replace(/\.0$/,'') + '×' + ft.w.toFixed(1).replace(/\.0$/,'') + ' ft print' +
          (LAYOUT > 1
            ? ' as a ' + LAYOUT + '-panel set. Split sets are quoted on WhatsApp — the price above is for the single print.'
            : '.');
      }

      // ── live camera backdrop (spec §8) ──
      var camOn = false, camStream = null, camSeq = 0, camStarting = false;
      function stopCam(){
        camSeq++; camStarting = false;          // cancels any start still in flight
        if (camStream){ camStream.getTracks().forEach(function(t){ t.stop(); }); camStream = null; }
        camOn = false;
        calStop();
        var v = $('ftm-camv'); v.srcObject = null; v.style.display = 'none';
        $('ftm-camstop').style.display = 'none';
        $('ftm-wall').style.display = 'block';
        // re-highlight the room thumbnail that matches the photo now showing
        var idx = SCENES.indexOf(scene);
        document.querySelectorAll('#ftm-scenes .af-ftm-scene').forEach(function(x, j){ x.classList.toggle('on', j === idx); });
        camLabel();
      }
      // the button is a toggle, so it has to say which way it will go
      function camLabel(){
        $('ftm-cambtn').innerHTML = (camOn || camStarting)
          ? '⏹ Stop live camera <em>back to the room photo</em>'
          : '🎥 Use live camera <em>point it at your wall</em>';
      }
      function startCam(){
        if (camOn || camStarting) return;        // ignore impatient double clicks
        if (!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia)){
          alert('Camera is not supported on this browser.'); return;
        }
        var seq = ++camSeq; camStarting = true;
        navigator.mediaDevices.getUserMedia({ video:{ facingMode:'environment', width:{ideal:1920}, height:{ideal:1080} }, audio:false })
          .then(function(stream){
            camStarting = false;
            // the visitor picked a room while the permission prompt was open
            if (seq !== camSeq){ stream.getTracks().forEach(function(t){ t.stop(); }); return; }
            camStream = stream; camOn = true;
            stream.getTracks().forEach(function(t){
              t.addEventListener('ended', function(){ if (camStream === stream) stopCam(); });
            });
            var v = $('ftm-camv'); v.srcObject = stream; v.style.display = 'block';
            $('ftm-wall').style.display = 'none';
            $('ftm-camstop').style.display = 'block';
            document.querySelectorAll('#ftm-scenes .af-ftm-scene').forEach(function(x){ x.classList.remove('on'); });
            camLabel();
            calStart();
            render();   // same reason as Try On Wall: camOn has just changed
          })
          .catch(function(err){
            camStarting = false; camLabel();
            if (seq !== camSeq) return;
            alert(err && err.name === 'NotAllowedError' ? 'Camera permission was denied.' : 'Could not start the camera.');
          });
      }
      $('ftm-cambtn').addEventListener('click', function(){
        if (camOn || camStarting) stopCam(); else startCam();
        camLabel();
      });
      $('ftm-camstop').addEventListener('click', stopCam);
      window.addEventListener('pagehide', stopCam);

      // ── LIVE-CAMERA CALIBRATION — measured true scale ─────────────────
      // Identical in behaviour to the Try On Wall page (owner request,
      // 2026-08-18). The rectangle over the feed stands for the wall between
      // ceiling and floor. The visitor steps back or forward until both lines
      // sit on its edges; an edge detector watches the feed and flips the
      // rectangle red → amber → green, and at the lock pixels-per-foot becomes
      // MEASURED (rectangle height ÷ chosen wall height) rather than assumed.
      // After the lock the detector keeps tracking the two lines, so walking
      // closer or farther rescales the print exactly as a real one would grow
      // or shrink in view. If the lines are lost the scale simply holds.
      // The visitor's wall height, set by the Wall height buttons. Starts at the
      // height render() draws the room photos against, so the lit button and the
      // picture agree before anything is touched. See the note on AFCal.
      var CAL = { locked:false, wallFt:10, base:0, pxPerFt:0, factor:1,
                  streak:0, spanLock:0, timer:null };
      window.AFCalFTM = CAL;   // read-only view for the live verifier
      var CAL_TOP = 0.16, CAL_BOT = 0.84;   // rectangle edges, fractions of stage height
      var calCv = document.createElement('canvas');
      calCv.width = 120; calCv.height = 90;
      var calCx = calCv.getContext('2d', { willReadFrequently:true });

      // where the rectangle's edges land in detector-row space, allowing for
      // the object-fit:cover crop of the feed inside the stage
      function calRows(){
        var v = $('ftm-camv'); if (!(v.videoWidth > 0)) return null;
        var st = $('ftm-stage').getBoundingClientRect();
        var sc = Math.max(st.width / v.videoWidth, st.height / v.videoHeight);
        var cropY = (v.videoHeight - st.height / sc) / 2;
        var toRow = function(frac){
          return (cropY + (frac * st.height) / sc) / v.videoHeight * calCv.height;
        };
        return { top: toRow(CAL_TOP), bot: toRow(CAL_BOT) };
      }

      // the strongest horizontal edge in the upper and the lower half of the
      // feed — on a wall shot those are the ceiling line and the floor line
      function calSample(){
        var v = $('ftm-camv'); if (!(v.videoWidth > 0)) return null;
        var d;
        try {
          calCx.drawImage(v, 0, 0, calCv.width, calCv.height);
          d = calCx.getImageData(0, 0, calCv.width, calCv.height).data;
        } catch(e){ return null; }
        var W = calCv.width, H = calCv.height,
            x0 = Math.round(W * 0.15), x1 = Math.round(W * 0.85);
        function lum(x, y){ var i = (y * W + x) * 4; return d[i]*0.299 + d[i+1]*0.587 + d[i+2]*0.114; }
        var rows = [];
        for (var y = 1; y < H - 1; y++){
          var acc = 0;
          for (var x = x0; x < x1; x++){ acc += Math.abs(lum(x, y+1) - lum(x, y-1)); }
          rows[y] = acc;
        }
        var sorted = rows.filter(function(v2){ return v2 != null; }).sort(function(a,b){ return a-b; });
        var med = sorted[Math.floor(sorted.length/2)] || 1;
        function pick(a, b){
          var best = -1, by = 0;
          for (var y = Math.max(1, Math.round(a)); y < Math.min(H-1, Math.round(b)); y++){
            if (rows[y] > best){ best = rows[y]; by = y; }
          }
          return { y: by, ok: best > med * 2.2 };
        }
        return { top: pick(H*0.03, H*0.48), bot: pick(H*0.52, H*0.97) };
      }

      function calTick(){
        if (!camOn) return;
        var sm = calSample(); if (!sm) return;
        if (!CAL.locked){
          var r = calRows(); if (!r) return;
          var tol = calCv.height * 0.055;
          var hit = sm.top.ok && sm.bot.ok &&
                    Math.abs(sm.top.y - r.top) < tol && Math.abs(sm.bot.y - r.bot) < tol;
          $('ftm-calbox').classList.toggle('near', hit);
          CAL.streak = hit ? CAL.streak + 1 : 0;
          if (CAL.streak >= 5) calLock(sm);        // ~0.8s of steady alignment
        } else {
          if (!(sm.top.ok && sm.bot.ok)) return;   // lines lost: hold the scale
          var span = sm.bot.y - sm.top.y; if (span <= 4) return;
          var f = span / CAL.spanLock; if (!(f > 0.35 && f < 2.8)) return;
          CAL.factor += (f - CAL.factor) * 0.25;   // smooth, no jitter
          var want = CAL.base * CAL.factor;
          if (CAL.pxPerFt > 0 && Math.abs(want - CAL.pxPerFt) / CAL.pxPerFt > 0.015){
            CAL.pxPerFt = want; render();
          }
        }
      }

      function calLock(sm){
        var st = $('ftm-stage').getBoundingClientRect();
        CAL.base = ((CAL_BOT - CAL_TOP) * st.height) / CAL.wallFt;   // MEASURED px per ft
        CAL.factor = 1; CAL.pxPerFt = CAL.base;
        CAL.spanLock = sm.bot.y - sm.top.y;
        CAL.locked = true; CAL.streak = 0;
        $('ftm-calbox').classList.add('locked');
        $('ftm-calmsg').textContent = '✓ Wall locked — your print is now shown at its real size';
        if (navigator.vibrate) try { navigator.vibrate(60); } catch(e){}
        setTimeout(function(){
          if (CAL.locked){ $('ftm-cal').style.display = 'none'; $('ftm-recal').style.display = 'block'; }
        }, 1100);
        render();
      }

      function calStart(){
        CAL.locked = false; CAL.streak = 0; CAL.factor = 1;
        $('ftm-cal').style.display = 'block';
        $('ftm-recal').style.display = 'none';
        $('ftm-calbox').classList.remove('locked','near');
        $('ftm-calmsg').innerHTML = '<span class="af-ftm-calmsg-long">Step back or forward until the <strong>ceiling line</strong> touches the top edge and the <strong>floor line</strong> touches the bottom edge</span><span class="af-ftm-calmsg-short"><strong>Ceiling</strong> to the top edge, <strong>floor</strong> to the bottom</span>';
        if (!CAL.timer) CAL.timer = setInterval(calTick, 160);
      }
      function calStop(){
        if (CAL.timer){ clearInterval(CAL.timer); CAL.timer = null; }
        CAL.locked = false;
        $('ftm-cal').style.display = 'none';
        $('ftm-recal').style.display = 'none';
        render();                                  // back to the room-photo scale
      }
      $('ftm-recal').addEventListener('click', calStart);

      /** The one place the wall height changes — see the matching note in Try
       *  On Wall. Both rows land here, so the panel's and the overlay's can
       *  never disagree, and the redraw is unconditional. */
      function af_ftm_set_wall_ft( n ) {
        CAL.wallFt = parseInt( n, 10 ) || 10;
        [ 'ftm-wallh', 'ftm-calh' ].forEach(function(id){
          var row = $(id); if ( ! row ) { return; }
          row.querySelectorAll('button[data-ft]').forEach(function(x){
            x.classList.toggle('on', parseInt(x.getAttribute('data-ft'),10) === CAL.wallFt);
          });
        });
        if (CAL.locked){                           // re-derive, keep tracking
          var st = $('ftm-stage').getBoundingClientRect();
          CAL.base = ((CAL_BOT - CAL_TOP) * st.height) / CAL.wallFt;
          CAL.pxPerFt = CAL.base * CAL.factor;
        }
        render();
      }
      [ 'ftm-wallh', 'ftm-calh' ].forEach(function(id){
        var row = $(id); if ( ! row ) { return; }
        row.addEventListener('click', function(e){
          var b = e.target.closest('button[data-ft]'); if ( ! b ) { return; }
          af_ftm_set_wall_ft( b.getAttribute('data-ft') );
        });
      });

      function buildSendLink(p){
        var msg = 'Hi The Art Framer! I would like to order a *Frame The Moment* custom print:%0A%0A' +
          '• Product: ' + encodeURIComponent($('ftm-type').value) + '%0A' +
          '• Size: '    + encodeURIComponent($('ftm-size').value) + '%0A' +
          '• Frame: '   + encodeURIComponent($('ftm-frame').value) + '%0A' +
          '• Colour: '  + encodeURIComponent($('ftm-color').value) + '%0A' +
          '• Layout: '  + encodeURIComponent(LAYOUT > 1 ? LAYOUT + '-panel split set (please confirm the price for a split set)' : 'Single print') + '%0A' +
          '• Price: '   + encodeURIComponent(SYM + p.toFixed(2)) + '%0A' +
          (photoName ? ('• Photo: ' + encodeURIComponent(photoName) + '%0A') : '') +
          '%0A(I will attach my photo here.)';
        $('ftm-send').href = 'https://wa.me/' + WA + '?text=' + msg;
      }

      // ── upload: click, change and drag & drop ──
      function loadFile(file){
        if (!file || !/^image\//.test(file.type)) { alert('Please choose an image file (JPG or PNG).'); return; }
        // a different photo means the saved image is no longer what is on screen;
        // set here rather than on the picker so drag-and-drop is covered too
        savedURL = '';
        photoName = file.name;
        var r = new FileReader();
        r.onload = function(e){
          photoURL = e.target.result;
          var im = new Image();
          im.onload = function(){
            photoW = im.naturalWidth; photoH = im.naturalHeight;
            var mp = (photoW * photoH) / 1000000;
            $('ftm-quality').textContent = photoW + '×' + photoH + 'px · ' +
              (mp >= 4 ? '✓ great quality for large prints'
                       : mp >= 1.5 ? '✓ good for prints up to 3 ft'
                                   : '⚠ low resolution — best kept small');
            $('ftm-quality').className = 'af-ftm-hint' + (mp < 1.5 ? ' warn' : ' ok');
            render();
          };
          im.src = photoURL;
          $('ftm-dropmain').textContent = file.name.length > 34 ? file.name.slice(0,31) + '…' : file.name;
          $('ftm-dropsub').textContent  = 'Click to choose a different photo';
          $('ftm-drop').classList.add('has');
        };
        r.readAsDataURL(file);
      }
      $('ftm-file').addEventListener('change', function(){ loadFile(this.files && this.files[0]); });
      var drop = $('ftm-drop');
      ['dragenter','dragover'].forEach(function(ev){
        drop.addEventListener(ev, function(e){ e.preventDefault(); drop.classList.add('over'); });
      });
      ['dragleave','drop'].forEach(function(ev){
        drop.addEventListener(ev, function(e){ e.preventDefault(); drop.classList.remove('over'); });
      });
      drop.addEventListener('drop', function(e){
        if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]) loadFile(e.dataTransfer.files[0]);
      });

      ['ftm-type','ftm-size','ftm-frame','ftm-color'].forEach(function(id){
        $(id).addEventListener('change', render);
      });
      window.addEventListener('resize', render);
      // The stage stretches to match the control rail, so it can change height
      // without the window resizing — the wall maths reads that height.
      if (window.ResizeObserver) {
        new ResizeObserver(function(){ render(); }).observe($('ftm-stage'));
      }

      // ── compose the preview: the whole wall, not just the frames ──
      // done(dataURL) receives the finished PNG; used by download, save-to-account
      // and share alike. Composition is async because the crop may still be loading.
      function composePreview(done){
        if (!photoURL) { alert('Please upload a photo first.'); return; }
        var boxEl = $('ftm-framebox');
        var panels = boxEl.querySelectorAll('.af-ftm-wpanel');
        var box = boxEl.getBoundingClientRect();
        if (!panels.length || !(box.width > 0 && box.height > 0)) {
          alert('The preview is still rendering — please try again in a moment.'); return;
        }
        var stageEl = $('ftm-stage'), st = stageEl.getBoundingClientRect();
        var scale = Math.max(1, 1200 / st.width);
        var cv = document.createElement('canvas');
        cv.width = Math.round(st.width * scale); cv.height = Math.round(st.height * scale);
        var ctx = cv.getContext('2d');
        function rel(r){ return { x:(r.left-st.left)*scale, y:(r.top-st.top)*scale, w:r.width*scale, h:r.height*scale }; }
        // cover-fit the backdrop exactly as CSS object-fit/object-position do
        function cover(src, nw, nh, pos){
          if (!(nw > 0 && nh > 0)) return;
          var sc = Math.max(cv.width / nw, cv.height / nh), dw = nw * sc, dh = nh * sc;
          var m = String(pos || '50% 50%').match(/([\d.]+)%\s+([\d.]+)%/);
          var px = m ? parseFloat(m[1]) / 100 : 0.5, py = m ? parseFloat(m[2]) / 100 : 0.5;
          ctx.drawImage(src, (cv.width - dw) * px, (cv.height - dh) * py, dw, dh);
        }

        var hex = SWATCH[$('ftm-color').value] || '#1a1a1a';
        var N = panels.length || 1;
        function draw(img){
          // backdrop first: the live camera frame, or the room photo
          if (camOn) {
            var v = $('ftm-camv');
            cover(v, v.videoWidth, v.videoHeight, '50% 50%');
          } else {
            var wall = $('ftm-wall');
            cover(wall, wall.naturalWidth, wall.naturalHeight, getComputedStyle(wall).objectPosition);
          }
          // every panel's shadow first, so none falls across an adjacent frame
          ctx.save();
          ctx.shadowColor = 'rgba(0,0,0,.34)'; ctx.shadowBlur = 22 * scale;
          ctx.shadowOffsetX = 10 * scale; ctx.shadowOffsetY = 16 * scale;
          ctx.fillStyle = hex;
          panels.forEach(function(panel){
            var m = rel(panel.querySelector('.af-ftm-pframe').getBoundingClientRect());
            ctx.fillRect(m.x, m.y, m.w, m.h);
          });
          ctx.restore();
          var idx = 0;
          panels.forEach(function(panel){
            var frEl = panel.querySelector('.af-ftm-pframe');
            var mtEl = panel.querySelector('.af-ftm-pmat');
            var arEl = panel.querySelector('.af-ftm-part');
            var m = rel(frEl.getBoundingClientRect());
            ctx.fillStyle = hex; ctx.fillRect(m.x, m.y, m.w, m.h);
            var matStyle = getComputedStyle(mtEl);
            if (parseFloat(matStyle.paddingTop) > 0) {
              var mt = rel(mtEl.getBoundingClientRect());
              ctx.fillStyle = (matStyle.backgroundColor && matStyle.backgroundColor !== 'rgba(0, 0, 0, 0)') ? matStyle.backgroundColor : '#f6f1e6';
              ctx.fillRect(mt.x, mt.y, mt.w, mt.h);
            }
            var a = rel(arEl.getBoundingClientRect());
            var siw = img.width / N;
            ctx.drawImage(img, idx * siw, 0, siw, img.height, a.x, a.y, a.w, a.h);
            idx++;
          });
          var url;
          try { url = cv.toDataURL('image/png'); }
          catch (err) {
            // the room photo came from another host without CORS headers, so the
            // canvas is tainted and cannot be exported
            alert('Could not save automatically — right-click the preview and choose “Save image as…”.');
            return;
          }
          done(url);
        }
        // use the cover-cropped image so panel slices match the preview exactly
        if (cropImg && cropImg.complete && cropImg.naturalWidth > 0) { draw(cropImg); }
        else {
          var img = new Image();
          img.onload = function(){ draw(img); };
          img.onerror = function(){ alert('The preview is still rendering — please try again in a moment.'); };
          img.src = cropURL || photoURL;
        }
      }

      $('ftm-save').addEventListener('click', function(){
        composePreview(function(url){
          var link = document.createElement('a');
          link.download = 'frame-the-moment-preview.png';
          link.href = url;
          document.body.appendChild(link); link.click(); link.remove();
        });
      });

      // ── SAVE TO ACCOUNT + SHARE (spec §8) ────────────────────────────
      // Cleared whenever the configuration changes, so sharing never sends the
      // image of a preview the visitor has already moved on from.
      var savedURL = '';
      ['ftm-type','ftm-size','ftm-frame','ftm-color'].forEach(function(id){
        $(id).addEventListener('change', function(){ savedURL = ''; });
      });
      $('ftm-layouts').addEventListener('click', function(){ savedURL = ''; });
      $('ftm-file').addEventListener('change', function(){ savedURL = ''; });
      function shareTarget(){ return savedURL || location.href.split('#')[0]; }
      function shareText(){
        return 'My photo framed by The Art Framer — ' + $('ftm-type').value + ', ' + $('ftm-size').value +
               (LAYOUT > 1 ? ', ' + LAYOUT + '-panel set' : '');
      }
      $('ftm-saveacct').addEventListener('click', function(){
        var btn = this;
        composePreview(function(url){
          btn.disabled = true;
          AFPreview.save(url, {
            product: 0, source: 'frame-the-moment',
            size: $('ftm-size').value, frame: $('ftm-frame').value,
            color: $('ftm-color').value, layout: LAYOUT
          }, function(ok, msg, saved, fallback){
            btn.disabled = false;
            var link = $('ftm-acctlink');
            if (ok) {
              savedURL = saved || '';
              if (link && fallback){ link.href = fallback; link.style.display = 'inline'; }
              alert('✓ ' + msg);
            } else {
              if (fallback && !AFPreview.cfg.logged && link) {
                link.href = AFPreview.withRedirect(fallback);
                link.textContent = 'Sign in →';
                link.style.display = 'inline';
              }
              alert(msg);
            }
          });
        });
      });
      $('ftm-share-wa').addEventListener('click', function(){
        if (AFPreview.native('The Art Framer', shareText(), shareTarget())) return;
        window.open(AFPreview.whatsapp(shareText(), shareTarget()), '_blank', 'noopener');
      });
      $('ftm-share-mail').addEventListener('click', function(){
        location.href = AFPreview.email('My framed photo — The Art Framer', shareText(), shareTarget());
      });
      $('ftm-share-copy').addEventListener('click', function(){
        AFPreview.copy(shareTarget(), function(ok){
          alert(ok ? '✓ Link copied' : 'Could not copy — please copy the address bar link.');
        });
      });

      render();
    })();
    </script>

    <link rel="stylesheet" id="af-frame-the-moment" href="<?php echo esc_url(af_static_url('frame-the-moment.css')); ?>" data-no-optimize="1">
    <?php
    get_footer();
    exit;
}, 1);


/* ================================================================
 * PHASE 29 — CART, CHECKOUT & PAYMENTS  (requirements §11)
 * Abandoned cart recovery, address validation, fraud screening and
 * invoice / packing-slip generation. Kept in inc/ so this file does
 * not grow another few thousand lines.
 * ================================================================ */
foreach (array('artcode-book', 'abandoned-cart', 'address-validation', 'fraud-detection', 'documents', 'marketplace', 'shipping', 'shipping-distance', 'quantity-limits', 'csp', 'schema-product', 'page-headings', 'deity-links', 'post-faq-schema', 'robots-noindex', 'debug-flag', 'jquery-migrate', 'price-filter', 'price-sort', 'placeholder-products', 'product-pictures', 'kit-choices', 'deals-page', 'deals-live', 'gold-foil', 'goldfoil-collection', 'goldfoil-autosync', 'reels', 'cookie-consent', 'masonry', 'card-actions', 'orientation-filter', 'blog-hub', 'analytics', 'meta-pixel', 'chatbot', 'sales-count', 'review-enhancements', 'artist-profiles', 'banner-links', 'about-page', 'image-guard', 'fatal-recorder', 'sku', 'goldfoil-promo', 'promo-hide', 'new-arrivals-rule', 'motion-glide', 'carousel-off', 'daily-shuffle', 'search-all', 'demo-guard', 'cache-warm', 'taf-tables', 'audit-fixes', 'corporate-collection', 'home-weight', 'wishlist-guest', 'stretcher-bar-pricing', 'aluminium-frame-pricing', 'duplicate-listings', 'retired-products', 'checkout-fixes', 'corporate-specs', 'category-display', 'turnstile', 'digital-masters', 'digital-account', 'security-hardening') as $af_mod) {
    $af_path = get_stylesheet_directory() . '/inc/' . $af_mod . '.php';
    if (file_exists($af_path)) require_once $af_path;
}
unset($af_mod, $af_path);

/* ================================================================
 * PHASE 28 — SAVE PREVIEW TO ACCOUNT + SHARE  (spec §8, and the
 * "Save and share preview" line of the §7 AR block)
 *
 * The AR pages can compose a preview of the artwork on a wall. This
 * stores that composition against the customer's account so they can
 * come back to it, and gives them WhatsApp / Email / copy-link share
 * targets. Guests can still share their configuration — the link
 * carries size / frame / colour, which the product page pre-selects.
 * ================================================================ */

// Saved previews live as a private CPT owned by the customer, with the
// composed PNG as its featured image.
add_action('init', function() {
    register_post_type('af_preview', array(
        'labels' => array(
            'name'          => 'Saved Previews',
            'singular_name' => 'Saved Preview',
        ),
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => 'edit.php?post_type=product',
        'capability_type'     => 'post',
        'map_meta_cap'        => true,
        'supports'            => array('title', 'author', 'thumbnail'),
        'exclude_from_search' => true,
        'has_archive'         => false,
        'rewrite'             => false,
    ));
    add_rewrite_endpoint('saved-previews', EP_ROOT | EP_PAGES);
}, 8);

// A new endpoint only resolves once rewrites are rebuilt. Deploy does this, but
// flush once per version here too so the account link can never quietly 404.
add_action('wp_loaded', function() {
    $ver = '1';
    if (get_option('af_preview_rewrite_ver') === $ver) return;
    flush_rewrite_rules(false);
    update_option('af_preview_rewrite_ver', $ver);
});

function af_preview_max_per_user() { return 60; }

// Store a composed preview sent from the AR pages.
function af_save_preview_handler() {
    check_ajax_referer('af_preview', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(array('code' => 'login', 'message' => 'Please sign in to save previews to your account.'), 401);
    }
    $uid = get_current_user_id();

    $raw = isset($_POST['image']) ? wp_unslash($_POST['image']) : '';
    if (!preg_match('#^data:image/(png|jpeg);base64,#i', $raw, $m)) {
        wp_send_json_error(array('message' => 'That preview could not be read.'));
    }
    $base64 = substr($raw, strpos($raw, ',') + 1);
    if (strlen($base64) > 12 * 1024 * 1024) {
        wp_send_json_error(array('message' => 'That preview is too large to save.'));
    }
    $bin = base64_decode($base64, true);
    if ($bin === false || strlen($bin) < 512) {
        wp_send_json_error(array('message' => 'That preview could not be read.'));
    }
    // Confirm the bytes really are an image, not just a well-formed data URL
    $probe = @getimagesizefromstring($bin);
    if (!$probe || !in_array($probe[2], array(IMAGETYPE_PNG, IMAGETYPE_JPEG), true)) {
        wp_send_json_error(array('message' => 'That preview could not be read.'));
    }
    // A few compressed megabytes can still decode to hundreds of megapixels and
    // exhaust the worker when thumbnails are generated, so bound the raster too.
    $w = (int) $probe[0]; $h = (int) $probe[1];
    if ($w < 1 || $h < 1 || $w > 6000 || $h > 6000 || ($w * $h) > 30000000) {
        wp_send_json_error(array('message' => 'That preview is too large to save.'));
    }
    // Trust the decoded bytes for the type, never the client's data-URL header
    $ext  = ($probe[2] === IMAGETYPE_PNG) ? 'png' : 'jpg';
    $mime = ($probe[2] === IMAGETYPE_PNG) ? 'image/png' : 'image/jpeg';

    $count = (int) count_user_posts($uid, 'af_preview', true);
    if ($count >= af_preview_max_per_user()) {
        wp_send_json_error(array('message' => 'You have reached ' . af_preview_max_per_user() . ' saved previews — please delete one first.'));
    }

    // Saving is cheap for the customer and expensive for us, so cap the rate as
    // well as the total — a stuck script should not be able to fill the disk.
    $bucket = (array) get_transient('af_preview_rate_' . $uid);
    $bucket = array_values(array_filter($bucket, function($t) { return $t > (time() - 600); }));
    if (count($bucket) >= 12) {
        wp_send_json_error(array('message' => 'That is a lot of previews at once — please wait a few minutes and try again.'), 429);
    }
    $bucket[] = time();
    set_transient('af_preview_rate_' . $uid, $bucket, 900);

    $pid = isset($_POST['product']) ? absint($_POST['product']) : 0;
    // only a real, published product — otherwise a crafted id would pull the
    // title of someone's draft into this customer's account page
    if ($pid && (get_post_type($pid) !== 'product' || get_post_status($pid) !== 'publish')) {
        $pid = 0;
    }
    $source = isset($_POST['source']) && $_POST['source'] === 'frame-the-moment' ? 'frame-the-moment' : 'try-on-wall';
    $size   = isset($_POST['size'])   ? sanitize_text_field(wp_unslash($_POST['size']))   : '';
    $frame  = isset($_POST['frame'])  ? sanitize_text_field(wp_unslash($_POST['frame']))  : '';
    $color  = isset($_POST['color'])  ? sanitize_text_field(wp_unslash($_POST['color']))  : '';
    $layout = isset($_POST['layout']) ? max(1, absint($_POST['layout'])) : 1;
    $title  = $pid ? get_the_title($pid) : 'My framed photo';
    if ($title === '') $title = 'Wall preview';

    // The uploads folder is public — it has to be, since sharing hands someone a
    // link to this image. So the filename carries the secrecy: a random suffix
    // makes previews unguessable rather than walkable by user id and timestamp.
    $file = wp_upload_bits('af-preview-' . $uid . '-' . wp_generate_password(24, false, false) . '.' . $ext, null, $bin);
    if (!empty($file['error'])) {
        wp_send_json_error(array('message' => 'Could not save that preview right now.'));
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    $att_id = wp_insert_attachment(array(
        'post_mime_type' => $mime,
        'post_title'     => $title . ' — wall preview',
        // NOT 'inherit'. An orphaned inherit attachment is public: it renders at
        // ?attachment_id=N and, worse, the anonymous /wp-json/wp/v2/media listing
        // hands out every attachment's source_url. Attachment ids are sequential,
        // so that walks straight past the unguessable filename — and a Frame The
        // Moment preview contains the customer's own photograph. 'private' keeps
        // it out of both, while the direct file URL still works, which is all the
        // share links and the account thumbnails actually use.
        'post_status'    => 'private',
        'post_author'    => $uid,
    ), $file['file']);
    if (is_wp_error($att_id) || !$att_id) {
        @unlink($file['file']);
        wp_send_json_error(array('message' => 'Could not save that preview right now.'));
    }
    wp_update_attachment_metadata($att_id, wp_generate_attachment_metadata($att_id, $file['file']));

    $post_id = wp_insert_post(array(
        'post_type'   => 'af_preview',
        'post_status' => 'publish',
        'post_title'  => $title,
        'post_author' => $uid,
    ), true);
    if (is_wp_error($post_id)) {
        wp_delete_attachment($att_id, true);
        wp_send_json_error(array('message' => 'Could not save that preview right now.'));
    }
    set_post_thumbnail($post_id, $att_id);
    update_post_meta($post_id, '_af_preview_product', $pid);
    update_post_meta($post_id, '_af_preview_source',  $source);
    update_post_meta($post_id, '_af_preview_size',    $size);
    update_post_meta($post_id, '_af_preview_frame',   $frame);
    update_post_meta($post_id, '_af_preview_color',   $color);
    update_post_meta($post_id, '_af_preview_layout',  $layout);

    wp_send_json_success(array(
        'message' => 'Saved to your account.',
        'url'     => wp_get_attachment_url($att_id),
        'account' => af_preview_account_url(),
    ));
}
add_action('wp_ajax_af_save_preview',        'af_save_preview_handler');
add_action('wp_ajax_nopriv_af_save_preview', 'af_save_preview_handler');

/** Where the Saved Previews list lives (falls back if WooCommerce is off). */
function af_preview_account_url() {
    if (function_exists('wc_get_endpoint_url') && function_exists('wc_get_page_permalink')) {
        return wc_get_endpoint_url('saved-previews', '', wc_get_page_permalink('myaccount'));
    }
    return home_url('/my-account/');
}

// Delete one of my own saved previews (posted from the account page).
add_action('template_redirect', function() {
    if (empty($_POST['af_preview_delete'])) return;
    $id = absint($_POST['af_preview_delete']);
    if (!$id || !is_user_logged_in()) return;

    // Say so rather than appearing to work — an expired nonce is common on a
    // page left open, and a silent no-op looks like the delete button is broken.
    if (!isset($_POST['af_preview_nonce']) || !wp_verify_nonce(wp_unslash($_POST['af_preview_nonce']), 'af_preview_delete_' . $id)) {
        wp_safe_redirect(add_query_arg('af_deleted', 'expired', af_preview_account_url()));
        exit;
    }
    $post = get_post($id);
    if (!$post || $post->post_type !== 'af_preview' || (int) $post->post_author !== get_current_user_id()) {
        wp_safe_redirect(add_query_arg('af_deleted', 'denied', af_preview_account_url()));
        exit;
    }
    $thumb = get_post_thumbnail_id($id);
    if ($thumb) wp_delete_attachment($thumb, true);
    wp_delete_post($id, true);
    wp_safe_redirect(add_query_arg('af_deleted', '1', af_preview_account_url()));
    exit;
});

// ── My Account → Saved Previews ──────────────────────────────────
add_filter('woocommerce_account_menu_items', function($items) {
    $new = array();
    foreach ($items as $k => $v) {
        $new[$k] = $v;
        if ($k === 'orders') $new['saved-previews'] = 'Saved Previews';
    }
    if (!isset($new['saved-previews'])) $new['saved-previews'] = 'Saved Previews';
    return $new;
}, 20);

// Track Order and Help live in My Account rather than the top utility bar:
// Track Order directly under Orders, Help directly under Account details.
// Runs last so each lands immediately after its anchor row, and both point at
// standalone pages rather than account endpoints.
add_filter('woocommerce_account_menu_items', function($items) {
    $after = array(
        'orders'       => array('af-track-order', 'Track Order'),
        'edit-account' => array('af-help',        'Help'),
    );
    $new = array();
    foreach ($items as $k => $v) {
        $new[$k] = $v;
        if (isset($after[$k]) && !isset($items[$after[$k][0]])) {
            $new[$after[$k][0]] = $after[$k][1];
        }
    }
    // Anchor row missing (menus vary) — still surface the entry.
    foreach ($after as $add) {
        if (!isset($new[$add[0]])) $new[$add[0]] = $add[1];
    }
    return $new;
}, 30);

add_filter('woocommerce_get_endpoint_url', function($url, $endpoint) {
    if ($endpoint === 'af-track-order') return home_url('/track-your-order/');
    if ($endpoint === 'af-help')        return home_url('/help-support/');
    return $url;
}, 10, 2);

// The header's "My Account" dropdown is rendered by the theme, not by
// woocommerce_account_menu_items, so the filter above never reaches it. Insert
// the same entries into that dropdown client-side, each cloned from the row it
// follows so they inherit the dropdown's own styling.
add_action('wp_footer', function() {
    if (is_admin()) return;
    ?>
<script>
(function(){
  var ROWS = [
    { cls: 'af-track-order-item', label: 'Track Order',
      url: <?php echo wp_json_encode(home_url('/track-your-order/')); ?>,
      match: function(t, h){ return t === 'orders' || t === 'my orders'
                                    || /\/my-account\/orders\/?$/.test(h); } },
    { cls: 'af-help-item', label: 'Help',
      url: <?php echo wp_json_encode(home_url('/help-support/')); ?>,
      match: function(t, h){ return t === 'account details' || t === 'account detail'
                                    || /\/my-account\/edit-account\/?$/.test(h); } }
  ];
  function tidy(u){ return (u || '').split('?')[0].split('#')[0].replace(/\/+$/, ''); }

  function hasRow(list, row){
    var target = tidy(row.url);
    var want   = row.label.toLowerCase();
    var links  = list.querySelectorAll('a[href]');
    for (var k = 0; k < links.length; k++) {
      if (tidy(links[k].getAttribute('href')) === target) return true;
      if ((links[k].textContent || '').replace(/\s+/g, ' ').trim().toLowerCase() === want) return true;
    }
    return false;
  }

  function add(){
    var anchors = document.querySelectorAll('a[href]');
    for (var i = 0; i < anchors.length; i++) {
      var a = anchors[i];
      var t = (a.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
      var h = (a.getAttribute('href') || '').split('?')[0];
      for (var r = 0; r < ROWS.length; r++) {
        var row = ROWS[r];
        if (!row.match(t, h)) continue;
        var li = a.closest('li');
        if (!li || !li.parentNode) continue;
        var list = li.parentNode;
        // Already there? This looked only for OUR clone's class, so it never
        // saw the copy the PHP menu filter adds on the My Account page under
        // its own class — and that page listed Track Order and Help twice
        // (owner screenshot, 2026-08-21). Test what a person would instead:
        // does this menu already link to that page, or already carry that
        // label, however it got there?
        if (hasRow(list, row)) continue;
        // Only a dropdown/account menu — must sit alongside other account rows.
        var all = (list.textContent || '').toLowerCase();
        if (all.indexOf('log out') === -1 && all.indexOf('logout') === -1
            && all.indexOf('dashboard') === -1 && all.indexOf('account details') === -1) continue;
        var clone = li.cloneNode(true);
        clone.className = (li.className || '') + ' ' + row.cls;
        clone.removeAttribute('id');
        var link = clone.querySelector('a');
        if (!link) continue;
        link.setAttribute('href', row.url);
        // replace the label text but keep any icon markup the theme adds
        var replaced = false;
        (function walk(node){
          for (var n = 0; n < node.childNodes.length; n++) {
            var c = node.childNodes[n];
            if (c.nodeType === 3 && c.nodeValue.trim()) {
              if (!replaced) { c.nodeValue = row.label; replaced = true; }
              else { c.nodeValue = ''; }
            } else if (c.nodeType === 1) { walk(c); }
          }
        })(link);
        if (!replaced) link.textContent = row.label;
        li.parentNode.insertBefore(clone, li.nextSibling);
      }
    }
  }
  document.addEventListener('DOMContentLoaded', add);
  window.addEventListener('load', add);
  [400, 1200, 2500].forEach(function(d){ setTimeout(add, d); });
  try {
    new MutationObserver(function(m){
      for (var i = 0; i < m.length; i++) {
        if (m[i].addedNodes && m[i].addedNodes.length) { add(); break; }
      }
    }).observe(document.body, { childList: true, subtree: true });
  } catch (e) {}
})();
</script>
<?php }, 65);

add_action('woocommerce_account_saved-previews_endpoint', function() {
    // WP_Query drops an author clause of 0, which would list everyone's previews
    if (!is_user_logged_in()) { echo '<p>Please sign in to see your saved previews.</p>'; return; }
    $previews = get_posts(array(
        'post_type'      => 'af_preview',
        'author'         => get_current_user_id(),
        'posts_per_page' => af_preview_max_per_user(),
        'orderby'        => 'date',
        'order'          => 'DESC',
    ));

    if (!empty($_GET['af_deleted'])) {
        $what = sanitize_key(wp_unslash($_GET['af_deleted']));
        if ($what === 'expired') {
            echo '<div class="woocommerce-error" role="alert">That page had been open a while, so the delete was not accepted. Please try again.</div>';
        } elseif ($what === 'denied') {
            echo '<div class="woocommerce-error" role="alert">That preview could not be deleted.</div>';
        } else {
            echo '<div class="woocommerce-message" role="alert">Preview deleted.</div>';
        }
    }

    echo '<p>Previews you saved from <a href="' . esc_url(home_url('/try-on-wall/')) . '">Try It On Your Wall</a> and '
       . '<a href="' . esc_url(home_url('/frame-the-moment/')) . '">Frame The Moment</a>.</p>';

    if (!$previews) {
        echo '<div class="af-sp-empty"><p>You have not saved any previews yet.</p>'
           . '<p><a class="button" href="' . esc_url(home_url('/try-on-wall/')) . '">Try art on your wall</a></p></div>';
        return;
    }

    echo '<div class="af-sp-grid">';
    foreach ($previews as $p) {
        $img   = get_the_post_thumbnail_url($p->ID, 'large');
        $full  = get_the_post_thumbnail_url($p->ID, 'full');
        $pid   = (int) get_post_meta($p->ID, '_af_preview_product', true);
        $size  = get_post_meta($p->ID, '_af_preview_size',  true);
        $frame = get_post_meta($p->ID, '_af_preview_frame', true);
        $color = get_post_meta($p->ID, '_af_preview_color', true);
        $lay   = (int) get_post_meta($p->ID, '_af_preview_layout', true);
        $link  = $pid ? get_permalink($pid) : home_url('/frame-the-moment/');
        if ($pid && ($size || $frame || $color)) {
            $link = add_query_arg(array(
                'af_size'  => rawurlencode($size),
                'af_frame' => rawurlencode($frame),
                'af_color' => rawurlencode($color),
            ), $link);
        }
        $src   = get_post_meta($p->ID, '_af_preview_source', true);
        $cta   = ($pid && $src !== 'frame-the-moment') ? 'View product' : 'Open in Frame The Moment';
        $bits  = array_filter(array($size, $frame, $color, $lay > 1 ? $lay . '-panel set' : ''));
        $wa   = 'https://wa.me/?text=' . rawurlencode(get_the_title($p) . ' — my wall preview: ' . $full);
        $mail = 'mailto:?subject=' . rawurlencode('My wall preview — ' . get_the_title($p))
              . '&body=' . rawurlencode("Here is the preview I saved:\n" . $full . "\n\n" . $link);

        echo '<div class="af-sp-card">';
        if ($img) echo '<a href="' . esc_url($full) . '" target="_blank" rel="noopener"><img src="' . esc_url($img) . '" alt="' . esc_attr(get_the_title($p)) . '"></a>';
        echo '<div class="af-sp-body">';
        echo '<strong>' . esc_html(get_the_title($p)) . '</strong>';
        if ($bits) echo '<small>' . esc_html(implode(' · ', $bits)) . '</small>';
        echo '<small>' . esc_html(get_the_date('', $p)) . '</small>';
        echo '<div class="af-sp-actions">';
        echo '<a class="af-sp-btn" href="' . esc_url($link) . '">' . esc_html($cta) . '</a>';
        echo '<a class="af-sp-btn" href="' . esc_url($full) . '" download>Download</a>';
        echo '<a class="af-sp-btn" href="' . esc_url($wa) . '" target="_blank" rel="noopener">WhatsApp</a>';
        echo '<a class="af-sp-btn" href="' . esc_url($mail) . '">Email</a>';
        echo '</div>';
        echo '<form method="post" onsubmit="return confirm(\'Delete this saved preview?\');">';
        wp_nonce_field('af_preview_delete_' . $p->ID, 'af_preview_nonce');
        echo '<button type="submit" name="af_preview_delete" value="' . esc_attr($p->ID) . '" class="af-sp-del">Delete</button>';
        echo '</form>';
        echo '</div></div>';
    }
    echo '</div>';

    echo '<style>
    .af-sp-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:18px;margin-top:18px;}
    .af-sp-card{border:1px solid #ece4cf;border-radius:14px;overflow:hidden;background:#fffdf8;display:flex;flex-direction:column;}
    .af-sp-card img{width:100%;height:170px;object-fit:cover;display:block;}
    .af-sp-body{padding:12px 14px 14px;display:flex;flex-direction:column;gap:4px;}
    .af-sp-body strong{font-size:14px;line-height:1.35;}
    .af-sp-body small{font-size:11.5px;color:#8a8170;}
    .af-sp-actions{display:flex;flex-wrap:wrap;gap:6px;margin:8px 0 4px;}
    .af-sp-btn{font-size:11.5px;font-weight:700;padding:6px 10px;border-radius:8px;background:#f2ecdd;color:#5a5140;text-decoration:none;}
    .af-sp-btn:hover{background:#e9e0cc;color:#3d342a;}
    .af-sp-del{background:none;border:none;padding:0;font-size:11.5px;color:#a33;cursor:pointer;text-align:left;}
    .af-sp-empty{padding:22px 0;}
    </style>';
});

/**
 * Shared front-end helper for the AR pages: saving a composed canvas to
 * the account, and the WhatsApp / Email / copy-link share targets.
 * Echoed inside the page templates, before their own scripts run.
 */
function af_preview_share_assets() {
    $account = function_exists('wc_get_endpoint_url')
        ? wc_get_endpoint_url('saved-previews', '', wc_get_page_permalink('myaccount'))
        : home_url('/my-account/');
    $login = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/my-account/');
    ?>
    <script>
    window.AFPreview = (function(){
      var CFG = {
        ajax:    <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,
        nonce:   <?php echo wp_json_encode(wp_create_nonce('af_preview')); ?>,
        logged:  <?php echo is_user_logged_in() ? 'true' : 'false'; ?>,
        account: <?php echo wp_json_encode($account); ?>,
        login:   <?php echo wp_json_encode($login); ?>
      };
      // POST a composed canvas to the account. cb(ok, message, url)
      function save(dataURL, meta, cb){
        if(!CFG.logged){
          cb(false, 'Please sign in to save previews to your account.', null, CFG.login);
          return;
        }
        var body = new URLSearchParams();
        body.set('action','af_save_preview');
        body.set('nonce', CFG.nonce);
        body.set('image', dataURL);
        Object.keys(meta||{}).forEach(function(k){ body.set(k, meta[k]); });
        fetch(CFG.ajax, { method:'POST', credentials:'same-origin', body:body })
          .then(function(r){ return r.json().catch(function(){ return {success:false,data:{message:'Could not save that preview.'}}; }); })
          .then(function(j){
            if(j && j.success) cb(true, (j.data&&j.data.message)||'Saved to your account.', j.data&&j.data.url, (j.data&&j.data.account)||CFG.account);
            else cb(false, (j&&j.data&&j.data.message)||'Could not save that preview.', null, CFG.login);
          })
          .catch(function(){ cb(false, 'Could not reach the server — please try again.', null, CFG.login); });
      }
      // Send them back to where they were after signing in. Plain permalinks put
      // a query string on the account URL already, so pick the separator.
      function withRedirect(url){
        return url + (url.indexOf('?') > -1 ? '&' : '?') + 'redirect_to=' + encodeURIComponent(location.href);
      }
      function whatsapp(text, url){ return 'https://wa.me/?text=' + encodeURIComponent(text + ' ' + url); }
      function email(subject, text, url){
        return 'mailto:?subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(text + '\n\n' + url);
      }
      function copy(url, cb){
        if(navigator.clipboard && navigator.clipboard.writeText){
          navigator.clipboard.writeText(url).then(function(){ cb(true); }, function(){ cb(false); });
          return;
        }
        try{
          var t=document.createElement('textarea'); t.value=url; t.setAttribute('readonly','');
          t.style.position='fixed'; t.style.opacity='0'; document.body.appendChild(t);
          t.select(); document.execCommand('copy'); t.remove(); cb(true);
        }catch(err){ cb(false); }
      }
      // Native share sheet on phones, when the browser offers one
      function native(title, text, url){
        if(!navigator.share) return false;
        navigator.share({ title:title, text:text, url:url }).catch(function(){});
        return true;
      }
      return { save:save, whatsapp:whatsapp, email:email, copy:copy, native:native, withRedirect:withRedirect, cfg:CFG };
    })();
    </script>
    <style>
    .af-share{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px;}
    /* flex:1 1 0 with min-width:0 made these three exactly equal and let them
       shrink below their own words, and with nowrap and no overflow rule the
       text simply ran out through the border — "WHATSAPP" and "COPY LINK"
       hanging past their own boxes on a phone. Basis auto starts each button
       at the width of its label, so when three no longer fit the ROW wraps to
       a second line and every label stays inside its box; they still grow to
       fill the line they land on. The fluid size buys a little room first, so
       the wrap only happens when it is really needed, and the ellipsis means
       even an inherited transform cannot push text past the border. */
    .af-share button,.af-share a{flex:1 1 auto;display:flex;align-items:center;justify-content:center;gap:6px;
      height:40px;padding:0 10px;border-radius:10px;border:1.5px solid #e2d9c4;background:#fffdf8;color:#5a5140;
      font-size:clamp(11px,3vw,12px);font-weight:700;cursor:pointer;text-decoration:none;
      white-space:nowrap;overflow:hidden;text-overflow:ellipsis;transition:border-color .15s,background .2s;}
    .af-share button:hover,.af-share a:hover{border-color:#c9a84c;background:#fdf9ef;color:#5a5140;}
    .af-share .af-share-wa{border-color:#25a366;color:#1e8b56;}
    .af-share .af-share-wa:hover{background:#eefaf3;border-color:#25a366;color:#1e8b56;}
    .af-sharelabel{margin:16px 0 0;font-size:11.5px;font-weight:800;color:#6b6250;text-transform:uppercase;letter-spacing:.05em;}
    /* Touch targets, phones and tablets only — the last four controls on these
       pages still under 44px, and now the only ones. 40px is not far off, and
       they are wide, so a miss is unlikely; the reason to close it is that
       everything beside them is already 44 and a row that mixes the two reads
       as unfinished. Height only: the widths come from the flex row, which is
       what makes the three-across wrap to two lines at narrow widths, and that
       behaviour is deliberate. Above 781px they stay at 40. */
    @media (max-width:781px){
      .af-share button,.af-share a{height:44px;}
    }
    </style>
    <?php
}

// ── PHASE 28 — Admin-only Inventory Management page (/inventory-management/) ──
// Standalone stock dashboard rendered over a real WP page, same pattern as the
// try-on-wall / frame-the-moment builders. Lists every product with its stock
// state and lets an administrator edit quantities inline. Restricted to
// manage_options (site administrators) — shop managers do NOT get in.

// Named staff who get the inventory tool without being site administrators.
// Kept as an explicit email allowlist rather than granting these accounts the
// administrator role: the role would also hand over plugins, users, theme
// editing and everything else, when all that was asked for is this one page.
function af_inv_allowed_emails() {
    return array(
        'sushovanberawinquest452@gmail.com',
        'yashwinquest@gmail.com',
        'dinesh@winquestonline.com',
    );
}

// The TOP-LEVEL storefront categories a product belongs to, matching the
// category menu on the site (e.g. "Direct from Artists"), not the sub-category
// under it (e.g. an individual artist). Each assigned term is rolled up to its
// root ancestor so the dashboard groups the way the shop menu does. A product
// under two different top-level categories appears under each — accurate, not
// a duplicate.
function af_inv_product_cats($pid) {
    $terms = get_the_terms($pid, 'product_cat');
    if (!$terms || is_wp_error($terms)) return array();
    $names = array();
    foreach ($terms as $t) {
        $root = $t;
        if ($t->parent) {
            $anc = get_ancestors($t->term_id, 'product_cat'); // nearest-first ... root-last
            if ($anc) {
                $root_id = end($anc);
                $rt = get_term($root_id, 'product_cat');
                if ($rt && !is_wp_error($rt)) $root = $rt;
            }
        }
        $names[] = html_entity_decode(wp_strip_all_tags($root->name));
    }
    return array_values(array_unique($names));
}

// Top-level product categories in the storefront's own display order (the same
// order shown in the shop's category menu), so the dashboard's sections line up
// with what the owner sees on the site rather than a plain alphabetical list.
function af_inv_category_order() {
    $terms = get_terms(array(
        'taxonomy'   => 'product_cat',
        'parent'     => 0,
        'hide_empty' => false,
        'orderby'    => 'menu_order', // honours the manual product-category order
        'order'      => 'ASC',
    ));
    if (!$terms || is_wp_error($terms)) return array();
    $names = array();
    foreach ($terms as $t) {
        $names[] = html_entity_decode(wp_strip_all_tags($t->name));
    }
    return $names;
}

function af_inv_can_access() {
    if (!is_user_logged_in()) return false;
    if (current_user_can('manage_options')) return true;

    // An account's email alone used to be enough (security audit, 12 Oct):
    // registration is open and WooCommerce lets anyone sign up or change to
    // an address without proving they own it, so an allowlisted address with
    // no account could be claimed. The address must now belong to an account
    // that was given the af_manage_stock capability on purpose.
    if (!current_user_can('af_manage_stock')) return false;
    $user    = wp_get_current_user();
    $email   = strtolower(trim((string) $user->user_email));
    $allowed = array_map('strtolower', af_inv_allowed_emails());
    return $email !== '' && in_array($email, $allowed, true);
}

// Endpoint: write a new stock quantity for one product.
function af_inventory_save_handler() {
    if (!af_inv_can_access()) {
        wp_send_json_error(array('message' => 'Permission denied.'), 403);
    }
    check_ajax_referer('af_inventory', 'nonce');

    $pid = isset($_POST['id']) ? absint($_POST['id']) : 0;
    $product = $pid ? wc_get_product($pid) : null;
    if (!$product) {
        wp_send_json_error(array('message' => 'Product not found.'), 404);
    }

    // An empty qty means "stop tracking a number for this product" and fall
    // back to the plain in/out-of-stock flag, which is how WooCommerce itself
    // treats a blank stock field.
    $raw = isset($_POST['qty']) ? trim(wp_unslash($_POST['qty'])) : '';
    if ($raw === '') {
        $product->set_manage_stock(false);
        $status = isset($_POST['status']) && $_POST['status'] === 'outofstock' ? 'outofstock' : 'instock';
        $product->set_stock_status($status);
        $qty = null;
    } else {
        $qty = (int) $raw;
        if ($qty < 0) $qty = 0;
        $product->set_manage_stock(true);
        $product->set_stock_quantity($qty);
        $product->set_stock_status($qty > 0 ? 'instock' : 'outofstock');
    }
    $product->save();

    if (function_exists('wc_delete_product_transients')) wc_delete_product_transients($pid);

    wp_send_json_success(array(
        'id'     => $pid,
        'qty'    => $qty,
        'status' => $product->get_stock_status(),
    ));
}
add_action('wp_ajax_af_inventory_save', 'af_inventory_save_handler');

add_action('template_redirect', function(){
    if (!function_exists('is_page') || !is_page(array('inventory-management','inventory'))) return;
    if (!function_exists('wc_get_products')) return;

    if (!af_inv_can_access()) {
        wp_die(
            'You do not have permission to view this page.',
            'Access Denied',
            array('response' => 403, 'back_link' => true)
        );
    }

    $ids = wc_get_products(array('status' => array('publish','draft'), 'limit' => -1, 'return' => 'ids'));
    $rows = array();
    foreach ($ids as $pid) {
        $p = wc_get_product($pid);
        if (!$p) continue;
        $rows[] = array(
            'id'      => $pid,
            'title'   => html_entity_decode(wp_strip_all_tags($p->get_name())),
            'sku'     => $p->get_sku(),
            'price'   => (float) $p->get_price(),
            'managed' => (bool) $p->get_manage_stock(),
            'qty'     => $p->get_manage_stock() ? (int) $p->get_stock_quantity() : null,
            'status'  => $p->get_stock_status(),
            'draft'   => $p->get_status() !== 'publish',
            'img'     => get_the_post_thumbnail_url($pid, 'thumbnail') ?: wc_placeholder_img_src('thumbnail'),
            // The console's own edit screen, not wp-admin's — see af_pe_url().
            'edit'    => af_pe_url($pid),
            'wpedit'  => get_edit_post_link($pid, 'raw'),
            'view'    => get_permalink($pid),
            'cats'    => af_inv_product_cats($pid),
        );
    }
    usort($rows, function($a, $b){ return strcasecmp($a['title'], $b['title']); });

    $cfg = array(
        'ajax'     => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('af_inventory'),
        'sym'      => function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '$',
        'low'      => 5, // at or below this counts as "low stock"
        'catOrder' => af_inv_category_order(),
    );

    get_header();
    ?>
    <div class="af-inv-wrap">
      <header class="af-inv-head">
        <div>
          <p class="af-inv-eyebrow">Admin only</p>
          <h1>Inventory Management</h1>
          <p class="af-inv-sub">Edit a stock number and press Enter (or click away) to save. Leave it blank to stop tracking a count for that product.</p>
        </div>
        <a class="af-inv-home" href="<?php echo esc_url(home_url('/')); ?>">Back to site</a>
      </header>

      <div class="af-inv-stats" id="inv-stats"></div>

      <div class="af-inv-toolbar">
        <input type="search" id="inv-search" class="af-inv-search" placeholder="Search by name or SKU&hellip;" autocomplete="off">
        <div class="af-inv-filters" id="inv-filters">
          <button type="button" class="af-inv-chip is-on" data-filter="all">All</button>
          <button type="button" class="af-inv-chip" data-filter="instock">In stock</button>
          <button type="button" class="af-inv-chip" data-filter="low">Low stock</button>
          <button type="button" class="af-inv-chip" data-filter="outofstock">Out of stock</button>
          <button type="button" class="af-inv-chip" data-filter="untracked">Not tracked</button>
        </div>
        <label class="af-inv-groupToggle"><input type="checkbox" id="inv-groupby" checked> Group by category</label>
      </div>

      <div class="af-inv-layout" id="inv-layout">
        <nav class="af-inv-catrail" id="inv-catrail" aria-label="Categories"></nav>
        <div class="af-inv-main">
          <div id="inv-groups"></div>
          <p class="af-inv-empty" id="inv-empty" hidden>No products match that search.</p>
        </div>
      </div>
    </div>

    <script>
    (function(){
      var ROWS = <?php echo wp_json_encode($rows); ?>;
      var CFG  = <?php echo wp_json_encode($cfg); ?>;
      var $ = function(id){ return document.getElementById(id); };
      var filter = 'all', term = '', groupBy = true, activeCat = 'all';

      function esc(s){ return (s==null?'':String(s)).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
      function money(n){ return CFG.sym + (Math.round(n*100)/100).toFixed(2); }

      // A product's bucket drives both the filter chips and the status pill.
      function bucket(r){
        if (!r.managed) return 'untracked';
        if (r.status === 'outofstock' || r.qty <= 0) return 'outofstock';
        if (r.qty <= CFG.low) return 'low';
        return 'instock';
      }
      var LABEL = { instock:'In stock', low:'Low stock', outofstock:'Out of stock', untracked:'Not tracked' };

      function visible(){
        return ROWS.filter(function(r){
          if (filter !== 'all') {
            var b = bucket(r);
            // "In stock" should include low-stock items; "Low stock" narrows it.
            if (filter === 'instock' ? (b !== 'instock' && b !== 'low') : b !== filter) return false;
          }
          if (!term) return true;
          var hay = (r.title + ' ' + (r.sku||'')).toLowerCase();
          return hay.indexOf(term) !== -1;
        });
      }

      function renderStats(){
        var c = { instock:0, low:0, outofstock:0, untracked:0 };
        ROWS.forEach(function(r){ c[bucket(r)]++; });
        $('inv-stats').innerHTML =
          stat('Products', ROWS.length, '') +
          stat('In stock', c.instock, 'ok') +
          stat('Low stock', c.low, 'warn') +
          stat('Out of stock', c.outofstock, 'bad') +
          stat('Not tracked', c.untracked, 'mute');
      }
      function stat(label, n, kind){
        return '<div class="af-inv-stat af-inv-stat--'+kind+'"><span class="af-inv-statn">'+n+'</span><span class="af-inv-statl">'+label+'</span></div>';
      }

      var UNCAT = 'Uncategorized';

      function rowHtml(r){
        var b = bucket(r);
        return '<tr data-id="'+r.id+'">'
          + '<td class="af-inv-tdimg"><img src="'+esc(r.img)+'" alt="" loading="lazy"></td>'
          + '<td class="af-inv-tdname"><a href="'+esc(r.view)+'" target="_blank" rel="noopener">'+esc(r.title)+'</a>'
            + (r.draft ? ' <span class="af-inv-draft">Draft</span>' : '') + '</td>'
          + '<td class="af-inv-tdsku">'+(r.sku ? esc(r.sku) : '<span class="af-inv-dash">&mdash;</span>')+'</td>'
          + '<td class="af-inv-tdprice">'+money(r.price)+'</td>'
          + '<td class="af-inv-tdstock"><input type="number" min="0" step="1" class="af-inv-qty" '
            + 'value="'+(r.managed && r.qty !== null ? r.qty : '')+'" placeholder="&mdash;" aria-label="Stock quantity"></td>'
          + '<td class="af-inv-tdstatus"><span class="af-inv-pill af-inv-pill--'+b+'">'+LABEL[b]+'</span></td>'
          + '<td class="af-inv-tdact"><a class="af-inv-edit" href="'+esc(r.edit)+'">Edit</a></td>'
          + '</tr>';
      }

      // A category's products fill the available screen height and scroll
      // inside for the rest — so you can read down to the last product without
      // the page itself growing. The header row stays pinned (sticky thead) so
      // the columns stay labelled while scrolling.
      function tableHtml(rows){
        return '<div class="af-inv-tablewrap is-scroll"><table class="af-inv-table"><thead><tr>'
          + '<th class="af-inv-thimg"></th><th>Product</th><th class="af-inv-thsku">SKU</th>'
          + '<th class="af-inv-thprice">Price</th><th class="af-inv-thstock">Stock</th>'
          + '<th class="af-inv-thstatus">Status</th><th class="af-inv-thact"></th>'
          + '</tr></thead><tbody>' + rows.map(rowHtml).join('') + '</tbody></table></div>';
      }

      // Bucket the (filtered) products by top-level category.
      function groupByCat(list){
        var groups = {};
        list.forEach(function(r){
          var cats = (r.cats && r.cats.length) ? r.cats : [UNCAT];
          cats.forEach(function(c){ (groups[c] = groups[c] || []).push(r); });
        });
        return groups;
      }

      // Category names ordered to match the storefront's own category menu
      // (CFG.catOrder). Anything not in that list (a stray category, or
      // Uncategorized) trails after, with Uncategorized always last.
      function orderedCatNames(groups){
        var order = CFG.catOrder || [];
        var rank = {};
        order.forEach(function(n, i){ rank[n] = i; });
        return Object.keys(groups).sort(function(a, b){
          if (a === UNCAT) return 1;
          if (b === UNCAT) return -1;
          var ra = (a in rank) ? rank[a] : 9998;
          var rb = (b in rank) ? rank[b] : 9998;
          if (ra !== rb) return ra - rb;
          return a.localeCompare(b);
        });
      }

      // Vertical category rail down the left — the same categories as the shop
      // menu, in the same order, each a click-to-filter entry.
      function renderRail(){
        var rail = $('inv-catrail');
        if (!groupBy) { rail.hidden = true; return; }
        rail.hidden = false;

        var groups = groupByCat(ROWS); // counts reflect the whole catalogue
        var names = orderedCatNames(groups);
        var html = '<button type="button" class="af-inv-catlink' + (activeCat === 'all' ? ' is-on' : '')
          + '" data-cat="all">All categories <span class="af-inv-catn">' + ROWS.length + '</span></button>';
        html += names.map(function(name){
          return '<button type="button" class="af-inv-catlink' + (activeCat === name ? ' is-on' : '')
            + '" data-cat="' + esc(name) + '">' + esc(name)
            + ' <span class="af-inv-catn">' + groups[name].length + '</span></button>';
        }).join('');
        rail.innerHTML = html;
      }

      // Size each scrollable product table so it stops at the bottom of the
      // viewport — the table fits the page and its extra rows scroll inside,
      // instead of the whole page growing taller than the screen.
      //
      // EXCEPT when a single category is on screen. Owner, 2026-09-10, with a
      // recording of Christian Art (6 products): the rail lists thirty-odd
      // categories and runs well past the fold, while the table beside it
      // stopped at the fold and left a tall empty gap down the right of the
      // page. Sizing to the viewport is the right rule for a stack of
      // categories and the wrong one for a single table with a long rail
      // beside it, so in that one case the table is matched to the rail and
      // the two columns end together. Both min and max are set: a short table
      // has to GROW to meet the rail, and a long one still scrolls inside.
      function fitTables(){
        var wraps = document.querySelectorAll('.af-inv-tablewrap.is-scroll');
        var rail  = document.getElementById('inv-catrail');
        // Not on mobile: there the rail is a static row of chips above the
        // table, not a column beside it, so there is no height to match.
        var matchRail = (wraps.length === 1 && rail && window.innerWidth > 820);
        var railBox   = matchRail ? rail.getBoundingClientRect() : null;
        for (var i = 0; i < wraps.length; i++) {
          var w = wraps[i];
          var box = w.getBoundingClientRect();
          var top = box.top;                       // distance from viewport top
          if (matchRail) {
            // The rail starts higher than the table, which sits below its
            // category title — so take that offset off the rail's height to
            // have the two finish on the same line.
            var h = Math.round(railBox.height - Math.max(0, top - railBox.top));
            if (h < 220) h = 220;                  // always show a few rows
            w.style.minHeight = h + 'px';
            w.style.maxHeight = h + 'px';
            continue;
          }
          w.style.minHeight = '';                  // clear a previous match
          var avail = Math.floor(window.innerHeight - top - 20);
          if (avail < 220) avail = 220; // always show a few rows
          w.style.maxHeight = avail + 'px';
        }
      }

      function render(){
        renderRail();
        var list = visible();

        if (!groupBy) {
          $('inv-empty').hidden = list.length > 0;
          $('inv-groups').innerHTML = list.length ? tableHtml(list) : '';
          fitTables();
          return;
        }

        var groups = groupByCat(list);
        var names = orderedCatNames(groups);
        if (activeCat !== 'all') names = names.filter(function(n){ return n === activeCat; });

        $('inv-empty').hidden = names.length > 0;
        $('inv-groups').innerHTML = names.map(function(name){
          var rows = groups[name];
          return '<section class="af-inv-cat">'
            + '<h2 class="af-inv-cattitle">' + esc(name)
            + ' <span class="af-inv-catcount">' + rows.length + '</span></h2>'
            + tableHtml(rows) + '</section>';
        }).join('');
        fitTables();
      }

      function save(tr, input){
        var id = parseInt(tr.getAttribute('data-id'), 10);
        var row = ROWS.filter(function(r){ return r.id === id; })[0];
        if (!row) return;
        var raw = input.value.trim();
        var prev = (row.managed && row.qty !== null) ? String(row.qty) : '';
        if (raw === prev) return; // nothing actually changed

        tr.classList.add('is-saving');
        var body = new URLSearchParams();
        body.set('action', 'af_inventory_save');
        body.set('nonce', CFG.nonce);
        body.set('id', id);
        body.set('qty', raw);

        fetch(CFG.ajax, { method:'POST', credentials:'same-origin', body:body })
          .then(function(res){ return res.json(); })
          .then(function(json){
            tr.classList.remove('is-saving');
            if (!json || !json.success) throw new Error((json && json.data && json.data.message) || 'Save failed');
            row.managed = json.data.qty !== null;
            row.qty     = json.data.qty;
            row.status  = json.data.status;
            flash(tr, 'ok');
            renderStats();
            // In grouped view the same product can appear under several
            // category headings, so sync every row for this id — not just the
            // one edited — so the copies never disagree.
            var b = bucket(row);
            var all = document.querySelectorAll('.af-inv-table tr[data-id="'+row.id+'"]');
            Array.prototype.forEach.call(all, function(t){
              var pill = t.querySelector('.af-inv-pill');
              if (pill) { pill.className = 'af-inv-pill af-inv-pill--'+b; pill.textContent = LABEL[b]; }
              if (t !== tr) {
                var qi = t.querySelector('.af-inv-qty');
                if (qi) qi.value = (row.managed && row.qty !== null) ? row.qty : '';
              }
            });
          })
          .catch(function(err){
            tr.classList.remove('is-saving');
            flash(tr, 'bad');
            input.value = prev; // put the old number back so the table never lies
            alert('Could not save: ' + err.message);
          });
      }
      function flash(tr, kind){
        tr.classList.add('af-inv-flash-'+kind);
        setTimeout(function(){ tr.classList.remove('af-inv-flash-'+kind); }, 1200);
      }

      $('inv-groups').addEventListener('change', function(e){
        if (!e.target.classList.contains('af-inv-qty')) return;
        save(e.target.closest('tr'), e.target);
      });
      $('inv-groups').addEventListener('keydown', function(e){
        if (e.key === 'Enter' && e.target.classList.contains('af-inv-qty')) { e.preventDefault(); e.target.blur(); }
      });
      $('inv-search').addEventListener('input', function(){ term = this.value.trim().toLowerCase(); render(); });
      $('inv-filters').addEventListener('click', function(e){
        var btn = e.target.closest('.af-inv-chip');
        if (!btn) return;
        filter = btn.getAttribute('data-filter');
        Array.prototype.forEach.call(this.querySelectorAll('.af-inv-chip'), function(b){ b.classList.toggle('is-on', b === btn); });
        render();
      });
      $('inv-groupby').addEventListener('change', function(){ groupBy = this.checked; render(); });
      $('inv-catrail').addEventListener('click', function(e){
        var btn = e.target.closest('.af-inv-catlink');
        if (!btn) return;
        activeCat = btn.getAttribute('data-cat');
        render();
      });
      // Keep the table fitting the viewport as the window is resized.
      var rzT;
      window.addEventListener('resize', function(){ clearTimeout(rzT); rzT = setTimeout(fitTables, 120); });

      // Open on the first real category rather than every category at once,
      // so only the selected category's products show. "All categories" in the
      // rail is still there if the whole list is wanted.
      (function pickInitialCat(){
        var names = orderedCatNames(groupByCat(ROWS));
        var firstReal = names.filter(function(n){ return n !== UNCAT; })[0];
        activeCat = firstReal || names[0] || 'all';
      })();

      renderStats();
      render();
    })();
    </script>

    <style>
    .af-inv-wrap{max-width:1180px;margin:0 auto;padding:34px 18px 70px;
      background:linear-gradient(180deg,#f6f1e6 0%,#efe7d6 100%);}
    .af-inv-head{display:flex;flex-wrap:wrap;gap:16px;align-items:flex-start;justify-content:space-between;margin:0 0 22px;}
    .af-inv-eyebrow{margin:0 0 6px;font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;
      color:#a8801f;background:#f3ead2;border:1px solid #e6d7ad;border-radius:999px;display:inline-block;padding:4px 11px;}
    .af-inv-head h1{margin:0 0 8px;font-family:'Playfair Display',Georgia,serif;font-size:34px;color:#1a1a1a;line-height:1.15;}
    .af-inv-sub{margin:0;font-size:13.5px;color:#6b6250;max-width:56ch;line-height:1.6;}
    .af-inv-home{align-self:center;background:#1a1a1a;color:#fff;text-decoration:none;font-size:12.5px;font-weight:700;
      padding:11px 18px;border-radius:9px;transition:background .2s;white-space:nowrap;}
    .af-inv-home:hover{background:#c9a84c;color:#fff;}

    .af-inv-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin:0 0 22px;}
    .af-inv-stat{background:#fffdf8;border:1px solid #efe6d2;border-radius:12px;padding:14px 16px;
      box-shadow:0 2px 10px rgba(70,54,26,.05);}
    .af-inv-statn{display:block;font-family:'Playfair Display',Georgia,serif;font-size:27px;color:#1a1a1a;line-height:1.1;}
    .af-inv-statl{display:block;margin-top:3px;font-size:11px;font-weight:700;letter-spacing:.05em;
      text-transform:uppercase;color:#8a8170;}
    .af-inv-stat--ok .af-inv-statn{color:#1e8b56;}
    .af-inv-stat--warn .af-inv-statn{color:#a8801f;}
    .af-inv-stat--bad .af-inv-statn{color:#b4453a;}
    .af-inv-stat--mute .af-inv-statn{color:#8a8170;}

    .af-inv-toolbar{display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;margin:0 0 14px;}
    .af-inv-search{flex:1 1 260px;min-width:0;height:42px;padding:0 14px;border:1.5px solid #e2d9c4;border-radius:10px;
      background:#fffdf8;color:#1a1a1a;font-size:14px;}
    .af-inv-search:focus{outline:none;border-color:#c9a84c;}
    .af-inv-filters{display:flex;flex-wrap:wrap;gap:7px;}
    .af-inv-chip{height:36px;padding:0 14px;border:1.5px solid #e2d9c4;border-radius:999px;background:#fffdf8;
      color:#6b6250;font-size:12.5px;font-weight:700;cursor:pointer;transition:border-color .15s,background .2s,color .2s;}
    .af-inv-chip:hover{border-color:#c9a84c;background:#fdf9ef;}
    .af-inv-chip.is-on{background:#1a1a1a;border-color:#1a1a1a;color:#fff;}
    .af-inv-groupToggle{display:flex;align-items:center;gap:7px;font-size:12.5px;font-weight:700;
      color:#6b6250;cursor:pointer;user-select:none;white-space:nowrap;}
    .af-inv-groupToggle input{width:16px;height:16px;accent-color:#c9a84c;cursor:pointer;}

    /* Two-column layout: a vertical category rail on the left, product
       sections on the right — mirroring the shop's category menu. */
    .af-inv-layout{display:grid;grid-template-columns:230px 1fr;gap:22px;align-items:start;}
    .af-inv-main{min-width:0;}
    .af-inv-catrail{position:sticky;top:16px;display:flex;flex-direction:column;gap:4px;
      background:#fffdf8;border:1px solid #efe6d2;border-radius:14px;padding:10px;
      box-shadow:0 4px 18px rgba(70,54,26,.07);}
    .af-inv-catlink{display:flex;align-items:center;justify-content:space-between;gap:8px;width:100%;
      text-align:left;padding:10px 12px;border:none;border-radius:9px;background:transparent;
      color:#3a352a;font-size:13px;font-weight:700;cursor:pointer;transition:background .15s,color .15s;}
    .af-inv-catlink:hover{background:#f7f0df;color:#1a1a1a;}
    .af-inv-catlink.is-on{background:#1a1a1a;color:#fff;}
    .af-inv-catn{flex:0 0 auto;font-size:11px;font-weight:800;color:#a8801f;background:#f3ead2;
      border-radius:999px;padding:2px 8px;line-height:1.6;}
    .af-inv-catlink.is-on .af-inv-catn{background:#c9a84c;color:#1a1a1a;}
    @media(max-width:820px){
      .af-inv-layout{grid-template-columns:1fr;}
      .af-inv-catrail{position:static;flex-direction:row;flex-wrap:wrap;}
      .af-inv-catlink{width:auto;}
    }

    .af-inv-cat{margin:0 0 26px;}
    .af-inv-cattitle{margin:0 0 10px;font-family:'Playfair Display',Georgia,serif;font-size:20px;color:#1a1a1a;
      display:flex;align-items:center;gap:10px;padding-bottom:8px;border-bottom:2px solid #e6d7ad;}
    .af-inv-catcount{font-family:inherit;font-size:11px;font-weight:800;color:#a8801f;background:#f3ead2;
      border:1px solid #e6d7ad;border-radius:999px;padding:2px 9px;line-height:1.6;}

    .af-inv-tablewrap{background:#fffdf8;border:1px solid #efe6d2;border-radius:14px;overflow-x:auto;
      box-shadow:0 4px 18px rgba(70,54,26,.07);}
    /* Let a category's table use most of the screen height, showing products
       to the end of that space and scrolling within for the rest. A short
       category simply doesn't reach the cap, so it never scrolls. The sticky
       thead keeps the column labels visible while scrolling. */
    .af-inv-tablewrap.is-scroll{max-height:72vh;overflow-y:auto;}
    .af-inv-table{width:100%;border-collapse:collapse;font-size:13.5px;min-width:760px;}
    .af-inv-table thead th{position:sticky;top:0;background:#f3ead2;color:#6b6250;text-align:left;
      font-size:11px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;padding:12px 14px;
      border-bottom:1px solid #e6d7ad;z-index:1;}
    .af-inv-table tbody td{padding:11px 14px;border-bottom:1px solid #f0e9da;vertical-align:middle;color:#1a1a1a;}
    .af-inv-table tbody tr:last-child td{border-bottom:none;}
    .af-inv-table tbody tr:hover{background:#fdf9ef;}
    .af-inv-thimg,.af-inv-tdimg{width:54px;}
    .af-inv-tdimg img{width:40px;height:40px;object-fit:cover;border-radius:7px;border:1px solid #ece4cf;display:block;}
    .af-inv-tdname a{color:#1a1a1a;text-decoration:none;font-weight:600;}
    .af-inv-tdname a:hover{color:#c9a84c;}
    .af-inv-draft{display:inline-block;margin-left:6px;font-size:10px;font-weight:800;text-transform:uppercase;
      letter-spacing:.04em;color:#8a8170;background:#f0e9da;border-radius:4px;padding:2px 6px;vertical-align:middle;}
    .af-inv-tdsku,.af-inv-thsku{color:#6b6250;font-size:12.5px;white-space:nowrap;}
    .af-inv-dash{color:#b8b0a0;}
    .af-inv-tdprice,.af-inv-thprice{white-space:nowrap;font-weight:600;}
    .af-inv-thstock,.af-inv-tdstock{width:104px;}
    .af-inv-qty{width:84px;height:36px;padding:0 9px;border:1.5px solid #e2d9c4;border-radius:8px;background:#fff;
      color:#1a1a1a;font-size:13.5px;font-weight:600;text-align:center;}
    .af-inv-qty:focus{outline:none;border-color:#c9a84c;background:#fffdf8;}
    .af-inv-pill{display:inline-block;padding:4px 10px;border-radius:999px;font-size:11px;font-weight:800;
      letter-spacing:.03em;white-space:nowrap;}
    .af-inv-pill--instock{background:#e6f5ed;color:#1e8b56;border:1px solid #c4e6d5;}
    .af-inv-pill--low{background:#f3ead2;color:#a8801f;border:1px solid #e6d7ad;}
    .af-inv-pill--outofstock{background:#fbe9e7;color:#b4453a;border:1px solid #f0cdc8;}
    .af-inv-pill--untracked{background:#f0e9da;color:#8a8170;border:1px solid #e2d9c4;}
    .af-inv-edit{color:#6b6250;text-decoration:none;font-size:12px;font-weight:700;border:1.5px solid #e2d9c4;
      border-radius:8px;padding:7px 12px;transition:border-color .15s,color .2s,background .2s;white-space:nowrap;}
    .af-inv-edit:hover{border-color:#c9a84c;color:#a8801f;background:#fdf9ef;}
    .af-inv-empty{margin:0;padding:34px 16px;text-align:center;color:#8a8170;font-size:14px;}

    .af-inv-table tbody tr.is-saving{opacity:.55;}
    .af-inv-table tbody tr.af-inv-flash-ok{background:#e6f5ed;}
    .af-inv-table tbody tr.af-inv-flash-bad{background:#fbe9e7;}

    @media(max-width:640px){
      .af-inv-wrap{padding:24px 12px 54px;}
      .af-inv-head h1{font-size:27px;}
    }

    /* ── PHONES AND SMALL TABLETS ─────────────────────────────────────────
       Owner, 2026-09-10, with a recording at 420px: the product names came
       out one letter per line — "P R O D U C T" stacked vertically — and the
       table was unreadable.

       The cause is a global rule this theme prints for every page at 781px and
       under (the af-mobile-responsive block): `table{display:block;width:100%;
       max-width:100%}`. It exists so a wide content table scrolls inside its
       own box instead of stretching the page, and for prose tables it is
       right. On this one it is fatal: display:block takes the table out of
       table layout, the seven columns collapse toward zero, and
       overflow-wrap:break-word — set on td/th by the same block — then breaks
       every word mid-character to fit.

       Fighting that rule to restore a seven-column table would only win a
       horizontal scrollbar on a 420px screen. A row of this table is really
       one product, so on a phone it becomes one card: picture, name, code,
       then price, stock, status and Edit laid out underneath.

       781px is not a round number by choice: it is exactly the width at which
       that global block switches on, so the card layout takes over on the same
       pixel the table stops working. A gap between the two would leave tablet
       portrait showing the crushed table this fixes.

       The desktop table above is untouched — this whole block is inside a
       media query and nothing in it applies above 781px. ─────────────────── */
    @media(max-width:781px){
      /* fitTables() sets max-height (and, for a lone category, min-height)
         inline to match the rail or the fold. Both are desktop ideas: here the
         cards simply flow and the page scrolls. !important is what beats an
         inline style. */
      .af-inv-tablewrap{max-height:none!important;min-height:0!important;
        overflow:visible!important;border-radius:12px;}
      .af-inv-table{display:block!important;width:100%!important;min-width:0!important;
        max-width:100%!important;overflow:visible!important;font-size:13px;}
      .af-inv-table thead{display:none!important;}   /* labels live on the cards */
      .af-inv-table tbody{display:block!important;}

      .af-inv-table tbody tr{
        display:grid!important;
        grid-template-columns:52px minmax(0,1fr) auto;
        grid-template-rows:auto auto auto auto;
        gap:6px 12px;align-items:center;
        padding:13px 14px;border-bottom:1px solid #f2ebda;background:transparent;}
      .af-inv-table tbody tr:last-child{border-bottom:0;}
      .af-inv-table td{display:block!important;border:0!important;padding:0!important;
        white-space:normal!important;}

      .af-inv-tdimg{grid-column:1;grid-row:1/span 2;align-self:start;width:auto!important;}
      .af-inv-tdimg img{width:52px!important;height:52px!important;}

      .af-inv-tdname{grid-column:2/-1;grid-row:1;font-weight:700;line-height:1.4;}
      .af-inv-tdname a{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;
        overflow:hidden;font-size:13px;}

      /* A SKU can be forty characters of hyphenated slug, so this is the one
         cell that may break anywhere. */
      .af-inv-tdsku{grid-column:2/-1;grid-row:2;font-size:11px;color:#8a8172;
        word-break:break-all;}
      .af-inv-tdsku::before{content:"SKU ";font-weight:800;color:#a8801f;}

      .af-inv-tdprice{grid-column:1/3;grid-row:3;font-weight:800;font-size:14px;}
      .af-inv-tdstock{grid-column:3;grid-row:3;justify-self:end;}
      .af-inv-tdstock::before{content:"Stock";display:inline-block;margin-right:7px;
        font-size:11px;font-weight:800;color:#8a8172;vertical-align:middle;}
      .af-inv-qty{width:70px!important;height:34px!important;}

      .af-inv-tdstatus{grid-column:1/3;grid-row:4;}
      .af-inv-tdact{grid-column:3;grid-row:4;justify-self:end;}
      .af-inv-edit{display:inline-block;padding:7px 14px;}

      /* The toolbar: search on its own line, the filter chips scrolling
         sideways under it rather than stacking five rows deep. */
      .af-inv-toolbar{gap:10px;}
      .af-inv-search{flex:1 1 100%;}
      .af-inv-filters{flex:1 1 100%;flex-wrap:nowrap;overflow-x:auto;
        -webkit-overflow-scrolling:touch;padding-bottom:2px;}
      .af-inv-filters::-webkit-scrollbar{display:none;}
      .af-inv-chip{flex:0 0 auto;}
      .af-inv-groupToggle{flex:1 1 100%;}

      .af-inv-cattitle{font-size:17px;}
      .af-inv-cat{margin:0 0 18px;}
    }
    </style>
    <?php
    get_footer();
    exit;
}, 1);

function af_inv_url() {
    $page = get_page_by_path('inventory-management');
    return $page ? get_permalink($page) : home_url('/inventory-management/');
}

function af_log_url() {
    $page = get_page_by_path('activity-log');
    return $page ? get_permalink($page) : home_url('/activity-log/');
}

// The activity log is administrators only (unlike Inventory, which also admits
// the allowlisted staff), matching the manage_options gate on the page itself.
function af_log_can_access() {
    return is_user_logged_in() && current_user_can('manage_options');
}

function af_console_url() {
    $page = get_page_by_path('admin-console');
    return $page ? get_permalink($page) : home_url('/admin-console/');
}

// The Admin Console is reachable by anyone who can open at least one of the
// tools inside it — administrators see every tool, allowlisted inventory staff
// see just Inventory. Each tool still enforces its own gate.
function af_console_can_access() {
    return (function_exists('af_inv_can_access') && af_inv_can_access()) || af_log_can_access();
}

// ── Admin Console hub page /admin-console/ ──────────────────────────────────
// A panel that gathers the admin tools (Inventory Management, Audit) in one
// place. Each tool is shown only if the viewer can actually open it.
add_action('template_redirect', function(){
    if (!function_exists('is_page') || !is_page(array('admin-console','admin'))) return;
    if (!af_console_can_access()) {
        wp_die('You do not have permission to view this page.', 'Access Denied',
            array('response' => 403, 'back_link' => true));
    }
    $can_inv = (function_exists('af_inv_can_access') && af_inv_can_access());
    $can_log = af_log_can_access();

    $cards = array();
    if ($can_inv) {
        $cards[] = array(
            'url'   => af_inv_url(),
            'icon'  => '&#128230;', // package
            'title' => 'Inventory Management',
            'desc'  => 'View and edit product stock, grouped by category.',
        );
    }
    if ($can_log) {
        $cards[] = array(
            'url'   => af_log_url(),
            'icon'  => '&#128220;', // scroll
            'title' => 'Audit',
            'desc'  => 'Timestamped log of what signed-in visitors do, with IP and email.',
        );
    }

    get_header();
    ?>
    <div class="af-console-wrap">
      <header class="af-console-head">
        <div>
          <p class="af-console-eyebrow">Admin only</p>
          <h1>Admin Console</h1>
          <p class="af-console-sub">Your staff tools in one place. Open a tool to manage the store.</p>
        </div>
        <a class="af-console-home" href="<?php echo esc_url(home_url('/')); ?>">Back to site</a>
      </header>

      <div class="af-console-grid">
        <?php foreach ($cards as $c) : ?>
          <a class="af-console-card" href="<?php echo esc_url($c['url']); ?>">
            <span class="af-console-cardicon"><?php echo $c['icon']; ?></span>
            <span class="af-console-cardtitle"><?php echo esc_html($c['title']); ?></span>
            <span class="af-console-carddesc"><?php echo esc_html($c['desc']); ?></span>
            <span class="af-console-cardgo">Open &rarr;</span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <style>
    .af-console-wrap{max-width:960px;margin:0 auto;padding:34px 18px 70px;
      background:linear-gradient(180deg,#f6f1e6 0%,#efe7d6 100%);}
    .af-console-head{display:flex;flex-wrap:wrap;gap:16px;align-items:flex-start;justify-content:space-between;margin:0 0 26px;}
    .af-console-eyebrow{margin:0 0 6px;font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;
      color:#a8801f;background:#f3ead2;border:1px solid #e6d7ad;border-radius:999px;display:inline-block;padding:4px 11px;}
    .af-console-head h1{margin:0 0 8px;font-family:'Playfair Display',Georgia,serif;font-size:34px;color:#1a1a1a;line-height:1.15;}
    .af-console-sub{margin:0;font-size:13.5px;color:#6b6250;max-width:56ch;line-height:1.6;}
    .af-console-home{align-self:center;background:#1a1a1a;color:#fff;text-decoration:none;font-size:12.5px;font-weight:700;
      padding:11px 18px;border-radius:9px;transition:background .2s;white-space:nowrap;}
    .af-console-home:hover{background:#c9a84c;color:#fff;}
    .af-console-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:18px;}
    .af-console-card{display:flex;flex-direction:column;gap:8px;padding:24px 22px;text-decoration:none;
      background:#fffdf8;border:1px solid #efe6d2;border-radius:16px;box-shadow:0 4px 18px rgba(70,54,26,.07);
      transition:border-color .15s,transform .15s,box-shadow .2s;}
    .af-console-card:hover{border-color:#c9a84c;transform:translateY(-2px);box-shadow:0 10px 26px rgba(70,54,26,.13);}
    .af-console-cardicon{font-size:30px;line-height:1;}
    .af-console-cardtitle{font-family:'Playfair Display',Georgia,serif;font-size:20px;color:#1a1a1a;}
    .af-console-carddesc{font-size:13px;color:#6b6250;line-height:1.55;}
    .af-console-cardgo{margin-top:6px;font-size:12.5px;font-weight:800;letter-spacing:.02em;color:#a8801f;}
    @media(max-width:640px){ .af-console-wrap{padding:24px 12px 54px;} .af-console-head h1{font-size:27px;} }
    </style>
    <?php
    get_footer();
    exit;
}, 1);

// Admin-only "Inventory" link in the main nav, sitting after Contact Us.
// The menu itself is managed in WP, so rather than editing it there (where the
// item would be visible to everyone) the link is injected at render time and
// only for users who can actually open the page.
//
// Admin-bar entry: works no matter how the header is built, so there is always
// one reliable way in even if the theme renders its nav without wp_nav_menu().
// The console is the parent; the individual tools hang off it as children.
add_action('admin_bar_menu', function($bar) {
    if (!af_console_can_access()) return;
    $bar->add_node(array(
        'id'    => 'af-admin-console',
        'title' => 'Admin Console',
        'href'  => af_console_url(),
        'meta'  => array('title' => 'Open the admin console'),
    ));
    if (function_exists('af_inv_can_access') && af_inv_can_access()) {
        $bar->add_node(array(
            'id'     => 'af-inventory',
            'parent' => 'af-admin-console',
            'title'  => 'Inventory',
            'href'   => af_inv_url(),
        ));
    }
}, 80);

// This adds the item to EVERY menu carrying a Contact Us link, not just the
// first. The first version stopped after one menu to avoid repeating it, but
// themes commonly emit a hidden mobile/off-canvas copy of the nav ahead of the
// desktop one — so the single insert landed in markup that is never visible on
// desktop. Adding to each matching menu also means the link works on mobile.
add_filter('wp_nav_menu_items', function($items, $args) {
    // Contact Us is removed from the header nav, so match on what remains
    // there too — otherwise this link would disappear along with it.
    if (stripos($items, 'contact') === false
        && stripos($items, 'about') === false
        && stripos($items, 'blog') === false) return $items;
    if (!af_console_can_access()) return $items;
    if (strpos($items, 'af-console-navitem') !== false) return $items;

    return $items . '<li class="menu-item af-console-navitem"><a href="' . esc_url(af_console_url()) . '">Admin Console</a></li>';
}, 20, 2);

add_action('wp_head', function() {
    if (!af_console_can_access()) return;
    ?>
    <style>
    /* Marks the injected link as an admin-only tool so it reads as distinct
       from the customer-facing nav items it sits beside. */
    .af-console-navitem > a{position:relative;color:#c9a84c !important;}
    .af-console-navitem > a::after{content:'ADMIN';margin-left:6px;font-size:8.5px;font-weight:800;
      letter-spacing:.06em;vertical-align:super;opacity:.75;}
    /* The item must sit INLINE with its siblings. The theme gives its own
       menu lis their inline layout through theme-specific classes this
       injected li does not carry, so without these rules it falls back to a
       full-width block li and lands alone on a second nav row. */
    .af-console-navitem{display:inline-flex !important;align-items:center !important;
      width:auto !important;max-width:none !important;flex:0 0 auto !important;
      float:none !important;clear:none !important;white-space:nowrap !important;
      vertical-align:middle !important;}
    .af-console-navitem > a{display:inline-block !important;width:auto !important;
      white-space:nowrap !important;}
    /* Displaying inline is not enough on its own: the nav row is a wrapping
       flex line already filled by the shop's own items, so a seventh one at
       their size still breaks to a second row. Make this item the compact
       one — it is a tool, not a shop link, so it need not match them — which
       is what lets it share the row. (The script also holds the row to one
       line where there is room; it measures first, so a row that cannot take
       the item wraps as before rather than overflowing the header.) */
    .af-console-navitem > a{font-size:11px !important;letter-spacing:.03em !important;
      padding-left:10px !important;padding-right:0 !important;}
    .af-console-navitem > a::after{font-size:7.5px;margin-left:4px;}
    </style>
    <?php
}, 99);

// Last-resort DOM fallback. Headers built by Elementor or a page builder can
// render their nav without ever passing through wp_nav_menu_items, in which
// case the PHP filter above adds nothing. This checks the rendered page and,
// only if no Inventory item is present, appends one to the nav list that
// actually holds the Contact link — matching what the visitor really sees
// rather than what the theme was assumed to output.
add_action('wp_footer', function() {
    if (!af_console_can_access()) return;
    ?>
    <script>
    (function(){
      var ITEMS = [{cls:'af-console-navitem', label:'Admin Console', href:<?php echo wp_json_encode(af_console_url()); ?>}];

      function findNav(){
        var lists = document.querySelectorAll('ul');
        var best = null, bestScore = 0;
        for (var i = 0; i < lists.length; i++) {
          var ul = lists[i];
          var links = ul.querySelectorAll(':scope > li > a');
          if (links.length < 2) continue; // not a real nav bar
          // Contact Us no longer sits in the header nav, so score on the
          // items that remain there.
          var markers = 0;
          for (var j = 0; j < links.length; j++) {
            if (/contact|about|blog/i.test(links[j].textContent || '')) markers++;
          }
          if (markers < 2) continue;
          // Prefer the visible nav: a hidden mobile copy has no layout box.
          var visible = ul.getBoundingClientRect().width > 0;
          var score = links.length + (visible ? 1000 : 0);
          if (score > bestScore) { bestScore = score; best = ul; }
        }
        return best;
      }

      function inject(){
        var best = null;
        ITEMS.forEach(function(item){
          if (document.querySelector('.' + item.cls)) return; // PHP filter already added it
          if (!best) best = findNav();
          if (!best) return;
          var first = best.querySelector(':scope > li');
          var li = document.createElement('li');
          li.className = (first ? first.className + ' ' : 'menu-item ') + item.cls;
          var a = document.createElement('a');
          a.href = item.href;
          a.textContent = item.label;
          li.appendChild(a);
          best.appendChild(li);
        });
      }
      // Hold the row the item ended up in to a single line. The stylesheet
      // says the same thing with :has(), which every current browser
      // supports; this repeats it on the element so the item never drops to
      // a second row on an older one, and so it applies to whichever list
      // actually received it — the theme's menu or the one found above.
      function pinRow(){
        document.querySelectorAll('.af-console-navitem').forEach(function(li){
          var ul = li.parentElement;
          if (!ul) return;
          ul.style.removeProperty('flex-wrap');
          if (window.innerWidth < 1100) return;
          if (getComputedStyle(ul).display.indexOf('flex') === -1) return;
          ul.style.setProperty('flex-wrap', 'nowrap', 'important');
          // Only hold the line while the line can actually hold it. Forcing
          // nowrap on a row with no room left does not save the item — it
          // pushes the whole nav past its container, which is worse than the
          // second row it was there to prevent. Measure, and stand down.
          if (ul.scrollWidth > ul.clientWidth + 1) ul.style.removeProperty('flex-wrap');
        });
      }
      function run(){ inject(); pinRow(); }
      if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run);
      else run();
      window.addEventListener('load', run);
      var _navTimer = null;
      window.addEventListener('resize', function(){
        clearTimeout(_navTimer); _navTimer = setTimeout(pinRow, 150);
      });
    })();
    </script>
    <?php
}, 99);

// ── PHASE 29 — Visitor activity log (logged-in users only) ──────────────────
// Records, for signed-in visitors ONLY, a timestamp + what they did + their IP
// into a dedicated table. Admin-only viewer at /activity-log/. Nothing here runs
// on guest requests, so cached anonymous traffic keeps paying zero extra cost —
// the log never touches the per-visitor budget we spent effort cutting.

function af_activity_log_table() {
    global $wpdb;
    return $wpdb->prefix . 'af_activity_log';
}

// Create/upgrade the table once, guarded by an option so dbDelta never runs on
// an ordinary request. Safe to call repeatedly.
function af_activity_log_ensure_table() {
    // Version 2 adds the ua column. dbDelta ALTERs an existing table rather
    // than replacing it, so the 1303 entries already recorded are kept — they
    // simply have no user agent, and the viewer shows them as unknown.
    if (get_option('af_activity_log_db_ver') === '2') return;
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table   = af_activity_log_table();
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        created_at DATETIME NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        user_login VARCHAR(120) NOT NULL DEFAULT '',
        ip VARCHAR(45) NOT NULL DEFAULT '',
        action VARCHAR(255) NOT NULL DEFAULT '',
        url VARCHAR(255) NOT NULL DEFAULT '',
        ua VARCHAR(255) NOT NULL DEFAULT '',
        PRIMARY KEY (id),
        KEY created_at (created_at),
        KEY user_id (user_id)
    ) {$charset};";
    dbDelta($sql);
    update_option('af_activity_log_db_ver', '2');
}

/**
 * Which kind of device made a request, from its User-Agent string.
 *
 * The device cannot be read from the IP address — an IP identifies a network,
 * not a machine, and the same phone and laptop on one home wifi share it. The
 * User-Agent is what actually carries this, so that is what is stored and
 * classified here.
 *
 * Order matters. Every Android tablet also says "Android", and an iPad in
 * desktop mode says "Macintosh", so the broad tests have to come after the
 * specific ones or everything lands in the wrong bucket.
 *
 * Returns one of: bot, tablet, mobile, desktop, or '' when nothing was stored
 * (every entry written before this column existed).
 */
function af_activity_device($ua) {
    $ua = trim((string) $ua);
    if ($ua === '') return '';
    $s = strtolower($ua);

    // Crawlers first: many name a platform further along and would otherwise
    // be counted as somebody's phone.
    if (preg_match('/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|headlesschrome|lighthouse|pingdom|gtmetrix|curl\/|wget|python-requests|axios|postman/i', $s)) {
        return 'bot';
    }
    // Names that state the device outright are checked before any guessing.
    // Tablet names first, because an iPad in desktop mode also says
    // "Macintosh" and would otherwise be counted as a computer.
    if (strpos($s, 'ipad') !== false
        || strpos($s, 'tablet') !== false
        || strpos($s, 'kindle') !== false
        || strpos($s, 'silk') !== false
        || strpos($s, 'playbook') !== false) {
        return 'tablet';
    }
    if (strpos($s, 'iphone') !== false
        || strpos($s, 'ipod') !== false
        || strpos($s, 'windows phone') !== false
        || strpos($s, 'blackberry') !== false
        || strpos($s, 'bb10') !== false
        || strpos($s, 'opera mini') !== false
        || strpos($s, 'iemobile') !== false) {
        return 'mobile';
    }
    // Only now the Android guess, and it IS a guess: Android says nothing about
    // screen size, so the convention is that a phone adds "Mobile" and a tablet
    // does not. It has to come after the names above — Opera Mini reports
    // "Android" with no "Mobile" and was being filed as a tablet.
    if (strpos($s, 'android') !== false) {
        return strpos($s, 'mobile') !== false ? 'mobile' : 'tablet';
    }
    if (strpos($s, 'mobile') !== false) return 'mobile';
    if (strpos($s, 'windows') !== false
        || strpos($s, 'macintosh') !== false
        || strpos($s, 'mac os x') !== false
        || strpos($s, 'cros') !== false
        || strpos($s, 'x11') !== false
        || strpos($s, 'linux') !== false) {
        return 'desktop';
    }
    // Something real but unrecognised — a new browser, an app webview. Saying
    // "unknown" is honest; guessing desktop would quietly invent a fact.
    return '';
}

/** The label shown beside the device icon, and searched on. */
function af_activity_device_label($kind) {
    $map = array(
        'mobile'  => 'Phone',
        'tablet'  => 'Tablet',
        'desktop' => 'Computer',
        'bot'     => 'Bot',
    );
    return isset($map[$kind]) ? $map[$kind] : 'Unknown';
}

// The address ranges Cloudflare's proxy connects to a site from, as published
// at https://www.cloudflare.com/ips-v4 and /ips-v6 (read 3 Oct 2026; the API at api.cloudflare.com/client/v4/ips gave the same).
function af_cloudflare_ranges() {
    return array(
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
        '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
        '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
        '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
        '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
    );
}

// Whether $ip lies in the range $cidr ("173.245.48.0/20", "2400:cb00::/32").
function af_ip_in_cidr($ip, $cidr) {
    $parts = explode('/', (string) $cidr, 2);
    $a = @inet_pton((string) $ip);
    $b = @inet_pton($parts[0]);
    if ($a === false || $b === false || strlen($a) !== strlen($b)) return false;
    $bits = isset($parts[1]) ? (int) $parts[1] : strlen($a) * 8;
    $whole = intdiv($bits, 8);
    if (substr($a, 0, $whole) !== substr($b, 0, $whole)) return false;
    $rest = $bits % 8;
    if ($rest === 0) return true;
    $mask = (0xFF << (8 - $rest)) & 0xFF;
    return (ord($a[$whole]) & $mask) === (ord($b[$whole]) & $mask);
}

// The visitor's address. Behind Cloudflare's proxy every connection comes
// from one of Cloudflare's servers, and the visitor's own address arrives in
// the CF-Connecting-IP header. That header is believed only when the
// connection really comes from Cloudflare: anyone reaching the server
// directly could send it with any address they liked, and the per-address
// limits on the contact form, the chatbot and the newsletter rely on it.
// Without Cloudflare, or where the host already puts the visitor's address in
// REMOTE_ADDR, this is REMOTE_ADDR.
function af_visitor_ip() {
    $remote = isset($_SERVER['REMOTE_ADDR']) ? trim((string) $_SERVER['REMOTE_ADDR']) : '';
    $cf = isset($_SERVER['HTTP_CF_CONNECTING_IP']) ? trim((string) $_SERVER['HTTP_CF_CONNECTING_IP']) : '';
    if ($cf !== '' && filter_var($cf, FILTER_VALIDATE_IP)) {
        foreach (af_cloudflare_ranges() as $range) {
            if (af_ip_in_cidr($remote, $range)) return $cf;
        }
    }
    return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '';
}

// The address the activity log records: the visitor's, as af_visitor_ip()
// reads it. (It used to take CF-Connecting-IP, X-Forwarded-For or X-Real-IP
// from anyone, so a visitor could write any address into the log.)
function af_activity_client_ip() {
    return af_visitor_ip();
}

// Write one entry. Only ever called for logged-in users.
function af_activity_log_record($action, $user = null) {
    $action = trim((string) $action);
    if ($action === '') return;
    af_activity_log_ensure_table();
    global $wpdb;

    if ($user === null) $user = wp_get_current_user();
    $uid   = ($user && $user->ID) ? (int) $user->ID : 0;
    if (!$uid) return; // logged-in users only
    $login = ($user && $user->user_login) ? $user->user_login : '';
    $url   = isset($_SERVER['REQUEST_URI']) ? esc_url_raw($_SERVER['REQUEST_URI']) : '';

    $wpdb->insert(
        af_activity_log_table(),
        array(
            'created_at' => current_time('mysql'),
            'user_id'    => $uid,
            'user_login' => substr($login, 0, 120),
            'ip'         => substr(af_activity_client_ip(), 0, 45),
            'action'     => substr($action, 0, 255),
            'url'        => substr($url, 0, 255),
            'ua'         => substr(isset($_SERVER['HTTP_USER_AGENT'])
                                   ? wp_strip_all_tags((string) $_SERVER['HTTP_USER_AGENT']) : '', 0, 255),
        ),
        array('%s', '%d', '%s', '%s', '%s', '%s', '%s')
    );

    // Occasionally prune entries older than 90 days so the table stays bounded.
    if (mt_rand(1, 40) === 1) {
        $cutoff = date('Y-m-d H:i:s', current_time('timestamp') - 90 * DAY_IN_SECONDS);
        $wpdb->query($wpdb->prepare(
            'DELETE FROM ' . af_activity_log_table() . ' WHERE created_at < %s', $cutoff
        ));
    }
}

// Whether the current front-end request is a real page view worth logging.
function af_activity_should_log_request() {
    if (!is_user_logged_in()) return false;
    if (is_admin()) return false;
    if (defined('DOING_AJAX') && DOING_AJAX) return false;
    if (defined('DOING_CRON') && DOING_CRON) return false;
    if (defined('REST_REQUEST') && REST_REQUEST) return false;
    if (function_exists('wp_is_json_request') && wp_is_json_request()) return false;
    if (function_exists('is_feed') && is_feed()) return false;
    // Don't log admins opening our own tool pages — keeps the log about real
    // storefront activity rather than filling with self-views.
    if (function_exists('is_page') && is_page(array('activity-log','user-activity-log','inventory-management','inventory','admin-console','admin'))) return false;
    return true;
}

// A human-readable label for the current front-end page.
function af_activity_page_label() {
    if (is_front_page() || is_home()) return 'Visited homepage';
    if (function_exists('is_product') && is_product()) {
        return 'Viewed product: ' . html_entity_decode(wp_strip_all_tags(get_the_title()));
    }
    if (function_exists('is_product_category') && is_product_category()) {
        $o = get_queried_object();
        return 'Viewed category: ' . ($o ? html_entity_decode(wp_strip_all_tags($o->name)) : '');
    }
    if (function_exists('is_product_tag') && is_product_tag()) {
        $o = get_queried_object();
        return 'Viewed tag: ' . ($o ? html_entity_decode(wp_strip_all_tags($o->name)) : '');
    }
    if (function_exists('is_shop') && is_shop()) return 'Viewed shop';
    if (is_search()) return 'Searched: "' . html_entity_decode(wp_strip_all_tags(get_search_query())) . '"';
    if (function_exists('is_cart') && is_cart()) return 'Viewed cart';
    if (function_exists('is_checkout') && is_checkout()) {
        return (function_exists('is_order_received_page') && is_order_received_page())
            ? 'Reached order confirmation' : 'Viewed checkout';
    }
    if (function_exists('is_account_page') && is_account_page()) return 'Viewed my account';
    if (is_page()) return 'Viewed page: ' . html_entity_decode(wp_strip_all_tags(get_the_title()));
    if (is_singular()) return 'Viewed: ' . html_entity_decode(wp_strip_all_tags(get_the_title()));
    if (is_category() || is_tag() || is_tax()) {
        $o = get_queried_object();
        return 'Browsed: ' . ($o && isset($o->name) ? html_entity_decode(wp_strip_all_tags($o->name)) : '');
    }
    return 'Viewed a page';
}

// Log the page view once the main query is resolved (so conditional tags work).
add_action('template_redirect', function(){
    if (!af_activity_should_log_request()) return;
    af_activity_log_record(af_activity_page_label());
}, 9999);

// Key WooCommerce / auth events — the "what a user does" beyond plain views.
add_action('woocommerce_add_to_cart', function($key, $product_id, $quantity){
    if (!is_user_logged_in()) return;
    $name = html_entity_decode(wp_strip_all_tags(get_the_title($product_id)));
    af_activity_log_record('Added to cart: ' . $name . ' (x' . (int) $quantity . ')');
}, 10, 3);

add_action('woocommerce_checkout_order_processed', function($order_id){
    if (!is_user_logged_in()) return;
    $order = function_exists('wc_get_order') ? wc_get_order($order_id) : null;
    $total = $order ? html_entity_decode(wp_strip_all_tags(wc_price($order->get_total()))) : '';
    af_activity_log_record('Placed order #' . (int) $order_id . ($total ? ' (' . $total . ')' : ''));
}, 10, 1);

add_action('wp_login', function($user_login, $user){
    af_activity_log_record('Logged in', $user);
}, 10, 2);

add_action('wp_logout', function($user_id){
    $u = $user_id ? get_user_by('id', $user_id) : null;
    if ($u) af_activity_log_record('Logged out', $u);
}, 10, 1);

// Admin-bar shortcut to the log, nested under the Admin Console parent node.
add_action('admin_bar_menu', function($bar){
    if (!af_log_can_access()) return;
    $bar->add_node(array(
        'id'     => 'af-activity-log',
        'parent' => 'af-admin-console',
        'title'  => 'Audit',
        'href'   => af_log_url(),
        'meta'   => array('title' => 'View the audit log'),
    ));
}, 81);

// ── Admin-only viewer page /activity-log/ ───────────────────────────────────
add_action('template_redirect', function(){
    if (!function_exists('is_page') || !is_page(array('activity-log','user-activity-log'))) return;
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        wp_die('You do not have permission to view this page.', 'Access Denied',
            array('response' => 403, 'back_link' => true));
    }
    af_activity_log_ensure_table();
    global $wpdb;
    $table = af_activity_log_table();
    $limit = 1000;
    $raw = $wpdb->get_results($wpdb->prepare(
        "SELECT created_at, user_login, user_id, ip, action, url, ua FROM {$table} ORDER BY id DESC LIMIT %d",
        $limit
    ), ARRAY_A);
    $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

    // Resolve each user's email at display time (not stored), cached per id so
    // a user who appears in many rows is only looked up once. Falls back to
    // empty if the account was since deleted.
    $email_cache = array();
    $rows = array();
    foreach ((array) $raw as $r) {
        $ts  = strtotime($r['created_at']);
        $uid = (int) $r['user_id'];
        if ($uid && !array_key_exists($uid, $email_cache)) {
            $u = get_userdata($uid);
            $email_cache[$uid] = ($u && $u->user_email) ? $u->user_email : '';
        }
        $kind = af_activity_device(isset($r['ua']) ? $r['ua'] : '');
        $rows[] = array(
            'time'  => $ts ? date_i18n('M j, Y g:i a', $ts) : $r['created_at'],
            'user'  => $r['user_login'] !== '' ? $r['user_login'] : ('#' . $uid),
            'email' => $uid ? $email_cache[$uid] : '',
            'ip'    => $r['ip'],
            'act'   => $r['action'],
            'url'   => $r['url'],
            'dev'   => $kind,
            'devl'  => af_activity_device_label($kind),
            // The full string on hover: the icon is a summary, and when it
            // looks wrong this is the evidence for why.
            'ua'    => isset($r['ua']) ? $r['ua'] : '',
        );
    }

    get_header();
    ?>
    <div class="af-log-wrap">
      <header class="af-log-head">
        <div>
          <p class="af-log-eyebrow">Admin only</p>
          <h1>Audit</h1>
          <p class="af-log-sub">Timestamped record of what signed-in visitors do on the site. Showing the most recent <?php echo (int) count($rows); ?> of <?php echo (int) $total; ?> entries. Entries older than 90 days are pruned automatically.</p>
        </div>
        <a class="af-log-home" href="<?php echo esc_url(home_url('/')); ?>">Back to site</a>
      </header>

      <div class="af-log-toolbar">
        <input type="search" id="log-search" class="af-log-search" placeholder="Search user, email, IP, device or action&hellip;" autocomplete="off">
      </div>

      <div class="af-log-tablewrap">
        <table class="af-log-table">
          <thead><tr>
            <th class="af-log-thtime">Time</th>
            <th class="af-log-thuser">User</th>
            <th class="af-log-themail">Email</th>
            <th class="af-log-thip">IP address</th>
            <th class="af-log-thdev">Device</th>
            <th>Action</th>
          </tr></thead>
          <tbody id="log-body"></tbody>
        </table>
        <p class="af-log-empty" id="log-empty" hidden>No entries match that search.</p>
      </div>
    </div>

    <script>
    (function(){
      var ROWS = <?php echo wp_json_encode($rows); ?>;
      var $ = function(id){ return document.getElementById(id); };
      var term = '';
      // Quotes too: these values land inside title="…" and other attributes,
      // and a User-Agent is whatever the visitor's client says it is.
      function esc(s){ return (s==null?'':String(s)).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }

      // One 16px glyph per kind, drawn rather than fetched: an icon font or a
      // sprite would be four more requests on a page that is one table.
      var ICON = {
        mobile:  '<rect x="6" y="2" width="12" height="20" rx="2.5"/><line x1="10.5" y1="18.6" x2="13.5" y2="18.6"/>',
        tablet:  '<rect x="3.5" y="3" width="17" height="18" rx="2.5"/><line x1="10.5" y1="17.8" x2="13.5" y2="17.8"/>',
        desktop: '<rect x="2.5" y="4" width="19" height="12.5" rx="2"/><line x1="8" y1="20.5" x2="16" y2="20.5"/><line x1="12" y1="16.5" x2="12" y2="20.5"/>',
        bot:     '<rect x="4" y="8" width="16" height="11" rx="3"/><line x1="12" y1="4" x2="12" y2="8"/><circle cx="9" cy="13" r="1.2"/><circle cx="15" cy="13" r="1.2"/>'
      };
      function deviceCell(r){
        var g = ICON[r.dev];
        if (!g) {
          // Nothing was recorded — every entry from before this column existed.
          // A dash, not a guessed icon.
          return '<span class="af-log-dash" title="No user agent was recorded for this entry">&mdash;</span>';
        }
        var tip = r.devl + (r.ua ? ' — ' + r.ua : '');
        return '<span class="af-log-dev af-log-dev-' + esc(r.dev) + '" title="' + esc(tip) + '">'
             + '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"'
             + ' stroke-width="1.7" stroke-linecap="round" aria-hidden="true">' + g + '</svg>'
             + '<span class="af-log-devl">' + esc(r.devl) + '</span></span>';
      }

      function render(){
        var list = ROWS.filter(function(r){
          if (!term) return true;
          return (r.user + ' ' + (r.email||'') + ' ' + r.ip + ' ' + (r.devl||'') + ' ' + r.act)
                 .toLowerCase().indexOf(term) !== -1;
        });
        $('log-empty').hidden = list.length > 0;
        $('log-body').innerHTML = list.map(function(r){
          var act = r.url
            ? '<a href="' + esc(r.url) + '" target="_blank" rel="noopener">' + esc(r.act) + '</a>'
            : esc(r.act);
          return '<tr>'
            + '<td class="af-log-tdtime">' + esc(r.time) + '</td>'
            + '<td class="af-log-tduser">' + esc(r.user) + '</td>'
            + '<td class="af-log-tdemail">' + (r.email ? esc(r.email) : '<span class="af-log-dash">&mdash;</span>') + '</td>'
            + '<td class="af-log-tdip">' + (r.ip ? esc(r.ip) : '<span class="af-log-dash">&mdash;</span>') + '</td>'
            + '<td class="af-log-tddev">' + deviceCell(r) + '</td>'
            + '<td class="af-log-tdact">' + act + '</td>'
            + '</tr>';
        }).join('');
      }
      $('log-search').addEventListener('input', function(){ term = this.value.trim().toLowerCase(); render(); });
      render();
    })();
    </script>

    <style>
    .af-log-wrap{max-width:1080px;margin:0 auto;padding:34px 18px 70px;
      background:linear-gradient(180deg,#f6f1e6 0%,#efe7d6 100%);}
    .af-log-head{display:flex;flex-wrap:wrap;gap:16px;align-items:flex-start;justify-content:space-between;margin:0 0 22px;}
    .af-log-eyebrow{margin:0 0 6px;font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;
      color:#a8801f;background:#f3ead2;border:1px solid #e6d7ad;border-radius:999px;display:inline-block;padding:4px 11px;}
    .af-log-head h1{margin:0 0 8px;font-family:'Playfair Display',Georgia,serif;font-size:32px;color:#1a1a1a;line-height:1.15;}
    .af-log-sub{margin:0;font-size:13px;color:#6b6250;max-width:64ch;line-height:1.6;}
    .af-log-home{align-self:center;background:#1a1a1a;color:#fff;text-decoration:none;font-size:12.5px;font-weight:700;
      padding:11px 18px;border-radius:9px;transition:background .2s;white-space:nowrap;}
    .af-log-home:hover{background:#c9a84c;color:#fff;}
    .af-log-toolbar{margin:0 0 14px;}
    .af-log-search{width:100%;max-width:420px;height:42px;padding:0 14px;border:1.5px solid #e2d9c4;border-radius:10px;
      background:#fffdf8;color:#1a1a1a;font-size:14px;}
    .af-log-search:focus{outline:none;border-color:#c9a84c;}
    .af-log-tablewrap{background:#fffdf8;border:1px solid #efe6d2;border-radius:14px;overflow:auto;max-height:72vh;
      box-shadow:0 4px 18px rgba(70,54,26,.07);}
    .af-log-table{width:100%;border-collapse:collapse;font-size:13px;min-width:820px;}
    .af-log-table thead th{position:sticky;top:0;background:#f3ead2;color:#6b6250;text-align:left;
      font-size:11px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;padding:11px 14px;
      border-bottom:1px solid #e6d7ad;z-index:1;}
    .af-log-table tbody td{padding:9px 14px;border-bottom:1px solid #f0e9da;vertical-align:top;color:#1a1a1a;}
    .af-log-table tbody tr:last-child td{border-bottom:none;}
    .af-log-table tbody tr:hover{background:#fdf9ef;}
    .af-log-thtime,.af-log-tdtime{white-space:nowrap;color:#6b6250;}
    .af-log-thuser,.af-log-tduser{white-space:nowrap;font-weight:700;}
    .af-log-themail,.af-log-tdemail{white-space:nowrap;color:#5a5140;font-size:12.5px;}
    .af-log-thip,.af-log-tdip{white-space:nowrap;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12px;color:#5a5140;}
    .af-log-tdact a{color:#1a1a1a;text-decoration:none;}
    .af-log-tdact a:hover{color:#c9a84c;}
    .af-log-dash{color:#b8b0a0;}
    /* Device: the icon carries the meaning, the word backs it up. Below 900px
       the word is dropped rather than the icon — a glyph survives a narrow
       column, a word only wraps. */
    .af-log-thdev,.af-log-tddev{white-space:nowrap;}
    .af-log-dev{display:inline-flex;align-items:center;gap:7px;color:#6b6250;font-size:12.5px;}
    .af-log-dev svg{flex:0 0 auto;}
    .af-log-dev-bot{color:#a8801f;}
    @media (max-width:900px){ .af-log-devl{display:none;} }
    .af-log-empty{margin:0;padding:34px 16px;text-align:center;color:#8a8170;font-size:14px;}
    @media(max-width:640px){ .af-log-wrap{padding:24px 12px 54px;} .af-log-head h1{font-size:26px;} }
    </style>
    <?php
    get_footer();
    exit;
}, 1);

// ---------------------------------------------------------------------------
// PHASE 30 — "Download Brochure" (printed collection book, PDF)
// Added 2026-08-08. One canonical PDF in the media library, linked from every
// product card, the single-product page, the shop/category header and the main
// menu, so a visitor can always grab the printed catalogue.
//
// The source file supplied was 1.1 GB (358 full-bleed pages) — far too large to
// serve to visitors. It is re-encoded to ~41 MB with no visible quality loss
// before upload (PyMuPDF re-render at 1.05x, JPEG q62). To replace the brochure
// later: upload the new PDF and set the `taf_brochure_url` option (or hook the
// filter of the same name) — nothing else here needs to change.
// ---------------------------------------------------------------------------
define('TAF_BROCHURE_FALLBACK',
  'https://theartframer.us/wp-content/uploads/2026/08/TheArtFramer-Collection-Brochure.pdf');
define('TAF_BROCHURE_SIZE', '41 MB');

function taf_brochure_url() {
  $url = get_option('taf_brochure_url', '');
  if (!$url) { $url = TAF_BROCHURE_FALLBACK; }
  return esc_url(apply_filters('taf_brochure_url', $url));
}

/**
 * Brochure anchor. Always an <a>, never a <button>: a global rule elsewhere in
 * the site collapses padding on bare <button> elements, which would squash it.
 * `download` asks the browser to save the 41 MB file instead of opening it
 * inline in the PDF viewer.
 */
function taf_brochure_link($variant = 'card') {
  $url = taf_brochure_url();
  if (!$url) return '';
  // an open book, not a download arrow — the button views, it does not save
  $icon = '<svg class="taf-broch-ico" viewBox="0 0 24 24" width="15" height="15" aria-hidden="true" focusable="false">'
        . '<path fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"'
        . ' d="M2 4.5A2.5 2.5 0 0 1 4.5 2H11v17.5H4.5A2.5 2.5 0 0 0 2 22V4.5zM22 4.5A2.5 2.5 0 0 0 19.5 2H13v17.5h6.5A2.5 2.5 0 0 1 22 22V4.5zM11 19.5v.01M13 19.5v.01"/></svg>';
  // "View", not "Download": the link opens the PDF in a browser tab (no
  // download attribute), so the visitor reads the collection book without a
  // 41 MB file landing in their downloads folder. Saving stays their choice
  // from the viewer.
  $label = 'View Brochure';
  return '<a class="taf-broch taf-broch--' . esc_attr($variant) . '" href="' . $url . '"'
       . ' target="_blank" rel="noopener"'
       . ' title="View the printed collection book (PDF, ' . esc_attr(TAF_BROCHURE_SIZE) . ')">'
       . $icon . '<span class="taf-broch-txt">' . $label . '</span></a>';
}

// 1. Single product page — button under the add-to-cart area.
add_action('woocommerce_single_product_summary', function() {
  echo '<div class="taf-broch-wrap">' . taf_brochure_link('single')
     . '<span class="taf-broch-note">PDF &middot; ' . esc_html(TAF_BROCHURE_SIZE)
     . ' &middot; 358 pages</span></div>';
}, 35);

// 2. Standard WooCommerce loop cards (shop, category, tag, related, up-sells).
add_action('woocommerce_after_shop_loop_item', function() {
  echo taf_brochure_link('card');
}, 15);

// 3. Shop / product-archive header strip.
add_action('woocommerce_before_shop_loop', function() {
  echo '<div class="taf-broch-banner"><span class="taf-broch-banner-txt">'
     . 'Browse all 358 pages of our printed collection book &mdash; every artwork with its Art Code.'
     . '</span>' . taf_brochure_link('banner') . '</div>';
}, 8);

// 4. Main-menu item, so the brochure is one click away from any page.
add_filter('wp_nav_menu_items', function($items, $args) {
  $loc = isset($args->theme_location) ? $args->theme_location : '';
  if (!in_array($loc, array('primary', 'menu-1', 'main', 'header'), true)) return $items;
  if (strpos($items, 'taf-broch-nav') !== false) return $items;
  return $items . '<li class="menu-item taf-broch-nav"><a href="' . taf_brochure_url()
       . '" target="_blank" rel="noopener">Brochure</a></li>';
}, 10, 2);

add_action('wp_head', function() { ?>
<style>
/* Matches the store's existing button language exactly — pill radius 50px,
   uppercase, 2px tracking, gold #c9a84c border with #8a6d1f text — so the
   brochure reads as a native secondary action next to "Show Dimensions"
   rather than a bolted-on box. Never a solid fill: the solid gold #ca9236 is
   reserved for Add to Cart and a second solid button would fight it. */
.taf-broch{display:inline-flex!important;align-items:center;justify-content:center;
  gap:8px;text-decoration:none;font-weight:700;text-transform:uppercase;
  letter-spacing:2px;line-height:1.15;border-radius:50px;
  border:.8px solid #c9a84c;background:#fff;color:#8a6d1f;
  box-shadow:none;text-align:center;
  transition:background .18s,border-color .18s,color .18s,box-shadow .18s;}
.taf-broch:hover,.taf-broch:focus{background:#c9a84c;border-color:#c9a84c;
  color:#fff;box-shadow:0 2px 12px rgba(201,168,76,.32);}
.taf-broch .taf-broch-ico{flex:0 0 auto;opacity:.85;}
.taf-broch:hover .taf-broch-ico{opacity:1;}
/* card — a full-width bar under Add to Cart and Quick View, squared off and
   in its own colour so the three actions read as three distinct things:
   gold buys, black previews, teal downloads the book. Width and side margins
   match the card's Add to Cart exactly (calc(100% - 28px) / 0 14px 14px), so
   the three line up as one stack whatever section rendered the card. */
.taf-broch-cardwrap{display:block;}
.taf-broch--card{display:flex!important;width:calc(100% - 28px);box-sizing:border-box;
  font-size:11px;padding:11px 12px;margin:0 14px 14px;border-radius:0;
  background:transparent;border:1.5px solid #c9a84c;color:#8a6d1f;}
.taf-broch--card:hover,.taf-broch--card:focus{background:#c9a84c;border-color:#c9a84c;
  color:#fff;box-shadow:0 2px 12px rgba(201,168,76,.32);}
.taf-broch--card .taf-broch-ico{width:13px;height:13px;opacity:1;}
/* single product page — sits with Show Dimensions, size as a caption below */
/* ── PHONES ─────────────────────────────────────────────────────────────
   Measured 2026-09-10 at 420px: the card's Add to Cart and Compare buttons
   came out 36x36 and this brochure bar's label 11px. A finger wants about
   40px, and 12px is the floor for a line of type someone is meant to read at
   arm's length. Both are nudged up on phones only — the desktop card is
   tuned to its own grid and is left exactly as it is. */
@media(max-width:600px){
  .taf-broch--card{font-size:12px;padding:12px;}
  .taf-broch--card .taf-broch-ico{width:14px;height:14px;}
  li.product .af-icon-corner > *,
  li.product a.add_to_cart_button,
  li.product .af-cmp-btn{min-width:40px!important;min-height:40px!important;}
}
.taf-broch-wrap{margin:16px 0 12px;display:flex;flex-direction:column;
  align-items:flex-start;gap:7px;}
.taf-broch--single{font-size:12.5px;padding:14px 26px;}
.taf-broch-note{font-size:11px;letter-spacing:1.4px;text-transform:uppercase;
  color:var(--af-note, #6f675a);font-weight:600;}
/* archive banner */
.taf-broch-banner{display:flex;flex-wrap:wrap;align-items:center;gap:12px 20px;
  justify-content:space-between;margin:0 0 24px;padding:16px 22px;
  border:.8px solid rgba(201,168,76,.55);border-radius:14px;
  background:linear-gradient(180deg,rgba(201,168,76,.08),rgba(201,168,76,.02));}
.taf-broch-banner-txt{font-size:14px;line-height:1.45;color:#5a5140;flex:1 1 280px;}
.taf-broch--banner{font-size:12px;padding:13px 24px;white-space:nowrap;}
@media(max-width:600px){
  .taf-broch-banner{padding:14px 16px;}
  .taf-broch-wrap{align-items:stretch;}
  .taf-broch--banner,.taf-broch--single{width:100%;}
  .taf-broch-note{text-align:center;}
}
</style>
<?php });

// 5. Cards rendered outside the standard Woo loop (Postero homepage carousels,
//    Elementor product widgets, search results). Same reason as PHASE 25b: those
//    templates never fire `woocommerce_after_shop_loop_item`, so inject in JS.
//    Unlike the Art Code this needs no per-product lookup — the link is the same
//    for every product, so it only has to recognise a product card.
add_action('wp_footer', function() {
  if (is_admin()) return; ?>
<script>
(function(){
  var HTML   = <?php echo wp_json_encode(taf_brochure_link('card')); ?>;
  var BANNER = <?php echo wp_json_encode(taf_brochure_link('banner')); ?>;
  var BLURB  = 'Browse all 358 pages of our printed collection book — every artwork with its Art Code.';
  var URL    = <?php echo wp_json_encode(taf_brochure_url()); ?>;
  if (!HTML) return;
  function add(card){
    if (card.getAttribute('data-taf-broch')) return;
    card.setAttribute('data-taf-broch','1');
    if (card.querySelector('.taf-broch--card')) return;   // PHP hook already handled it
    var w = document.createElement('div');
    w.className = 'taf-broch-cardwrap';
    w.innerHTML = HTML;
    card.appendChild(w);
  }
  function run(){
    var cards = document.querySelectorAll('li.product, .product-card, .trending-card, [class*="type-product"]');
    Array.prototype.forEach.call(cards, function(c){
      if (document.body.classList.contains('single-product') && c.closest('.summary')) return;
      if (c.querySelector('.taf-broch--single')) return;  // this is the single-product body
      if (!c.querySelector('a[href]')) return;            // not a real card
      add(c);
    });
    banner();
    navItem();
  }
  // Archive banner. `woocommerce_before_shop_loop` never fires on the Postero
  // shop template, so place it in front of the products grid client-side.
  function banner(){
    if (!BANNER || document.querySelector('.taf-broch-banner')) return;
    var b = document.body.className || '';
    if (!/(post-type-archive-product|tax-product_cat|tax-product_tag|woocommerce-shop|search-results)/.test(b)) return;
    var grid = document.querySelector('ul.products, ul.postero-products, .products');
    if (!grid || !grid.parentNode) return;
    var d = document.createElement('div');
    d.className = 'taf-broch-banner';
    d.innerHTML = '<span class="taf-broch-banner-txt">' + BLURB + '</span>' + BANNER;
    grid.parentNode.insertBefore(d, grid);
  }
  // Primary menu item. The header menu is built by Elementor, so it carries no
  // theme_location and the PHP `wp_nav_menu_items` filter never matches it.
  // Append to the FIRST top-level menu inside the header nav only — never the
  // sub-menus or the footer menu, or the link would appear several times.
  function navItem(){
    if (document.querySelector('.taf-broch-nav')) return;
    var ul = document.querySelector('nav.main-navigation ul.menu, .primary-navigation > ul.menu');
    if (!ul || ul.closest('.sub-menu')) return;
    var li = document.createElement('li');
    li.className = 'menu-item taf-broch-nav';
    li.innerHTML = '<a href="' + URL + '"'
                 + ' target="_blank" rel="noopener"><span class="menu-title">Brochure</span></a>';
    ul.appendChild(li);
  }
  document.addEventListener('DOMContentLoaded', run);
  window.addEventListener('load', run);
  [400,1200,2500].forEach(function(d){ setTimeout(run,d); });
  try {
    var obs = new MutationObserver(function(m){
      for (var i=0;i<m.length;i++){ if (m[i].addedNodes && m[i].addedNodes.length){ run(); break; } }
    });
    obs.observe(document.body,{childList:true,subtree:true});
  } catch(e){}
})();
</script>
<?php }, 60);

// ─────────────────────────────────────────────────────────────
// THE WISHLIST PAGE
//
// It shipped as the plugin's bare table: one hairline-bordered row per saved
// piece, a date column nobody asked for, and an "add to cart" control that
// rendered as a shopping-bag EMOJI because the button carries no label of its
// own. There was nothing to browse afterwards either — a saved piece was a
// dead end rather than the start of a visit.
//
// Three things happen here, all scoped to this page so no other template
// changes: the row becomes a card that matches the rest of the store, the
// emoji becomes a proper Add to Cart button in the house gold, and a "You may
// also like" row is appended, built from the categories of whatever is
// actually saved (falling back to the best sellers when nothing is).
// ─────────────────────────────────────────────────────────────

/** Is the page being rendered the wishlist? */
function af_is_wishlist_page() {
    if (is_admin()) return false;
    if (function_exists('YITH_WCWL') && method_exists(YITH_WCWL(), 'is_wishlist_page')) {
        if (YITH_WCWL()->is_wishlist_page()) return true;
    }
    if (function_exists('is_page') && (is_page('wishlist') || is_page('Wishlist'))) return true;
    $path = trim((string) wp_parse_url(add_query_arg(array()), PHP_URL_PATH), '/');
    return $path !== '' && strpos($path, 'wishlist') === 0;
}

/**
 * Pages that print product cards without being an archive: the cart and the
 * wishlist, both of which carry a "You may also like" row built from the same
 * template. The card modules were all written against the archives and each
 * one repeated the same four is_*() calls, so these two pages fell through
 * every time and their cards came out missing rows.
 */
function af_cards_secondary_page() {
    if (!function_exists('is_shop')) return false;
    if (function_exists('is_cart') && is_cart()) return true;
    if (function_exists('af_is_wishlist_page') && af_is_wishlist_page()) return true;
    return false;
}

function af_wl_related_html($markup) {
    if (!function_exists('wc_get_products')) return '';

    $ids = array();
    if (preg_match_all('/add-to-cart=(\d+)/', $markup, $m)) $ids = array_map('intval', $m[1]);
    if (preg_match_all('/data-product-id=["\'](\d+)["\']/', $markup, $m2)) {
        $ids = array_merge($ids, array_map('intval', $m2[1]));
    }
    $ids = array_values(array_unique(array_filter($ids)));

    // categories of what is saved — "related" should mean related to THIS
    // wishlist, not a generic carousel
    $cats = array();
    foreach ($ids as $pid) {
        $terms = get_the_terms($pid, 'product_cat');
        if (!$terms || is_wp_error($terms)) continue;
        foreach ($terms as $t) {
            if (in_array($t->slug, array('uncategorized'), true)) continue;
            $cats[$t->slug] = true;
        }
    }

    $args = array('status' => 'publish', 'limit' => 8, 'orderby' => 'popularity',
                  'visibility' => 'catalog', 'exclude' => $ids);
    if ($cats) $args['category'] = array_keys($cats);
    $picks = wc_get_products($args);
    if (count($picks) < 4) {                       // thin category: top up with best sellers
        $more = wc_get_products(array('status' => 'publish', 'limit' => 8, 'orderby' => 'popularity',
                                      'visibility' => 'catalog',
                                      'exclude' => array_merge($ids, wp_list_pluck($picks, 'id'))));
        $picks = array_merge($picks, $more);
    }
    if (!$picks) return '';
    $picks = array_slice($picks, 0, 8);

    ob_start();
    // The wrapper carries the woocommerce class deliberately: the shop's card
    // styling is written as ".woocommerce ul.products li.product …", so without
    // it these cards fall back to unstyled defaults and need hand-written css —
    // which is exactly what went wrong here before.
    echo '<section class="af-wl-related woocommerce"><h2>You may also like</h2>';
    echo '<ul class="products columns-4">';
    $keep = isset($GLOBALS['post']) ? $GLOBALS['post'] : null;
    foreach ($picks as $p) {
        $po = get_post($p->get_id());
        if (!$po) continue;
        $GLOBALS['post'] = $po;
        setup_postdata($GLOBALS['post']);
        wc_get_template_part('content', 'product');
    }
    $GLOBALS['post'] = $keep;
    wp_reset_postdata();
    echo '</ul></section>';
    return ob_get_clean();
}

// ─────────────────────────────────────────────────────────────
// CROSS-SELL ON THIN CATEGORY PAGES
// A category holding one product renders one card against an acre of empty
// grid — "Showing the single result" and nothing to look at. The visitor
// arrived wanting art of that kind, so rather than leaving them to hit Back,
// show what else the studio has that is close to it.
//
// "Close to it" means siblings first: the categories sharing this one's
// parent, plus whatever else the products already on the page are filed
// under. Only when that comes up short does it fall back to the shop's
// popular pieces, so the row is a recommendation before it is filler.
// ─────────────────────────────────────────────────────────────

/** A full row of cards. Fewer than this on an archive and the page looks broken. */
function af_xsell_min_cards() {
    return (int) apply_filters('af_xsell_min_cards', 4);
}

/**
 * The tags the visitor actually chose, however they chose them: a
 * /product-tag/ archive, or the "Filter by Tag" bar, which filters in place
 * with ?product_tag= instead of navigating away.
 */
function af_xsell_chosen_tags() {
    $slugs = array();
    $term  = get_queried_object();
    if ($term && !empty($term->taxonomy) && $term->taxonomy === 'product_tag' && !empty($term->slug)) {
        $slugs[$term->slug] = true;
    }
    if (!empty($_GET['product_tag'])) {
        foreach (explode(',', (string) wp_unslash($_GET['product_tag'])) as $one) {
            $one = sanitize_title($one);
            if ($one !== '') $slugs[$one] = true;
        }
    }
    return array_keys($slugs);
}

/**
 * Tags that read as "more like this": the chosen ones first, then the tags
 * the products already on the page also carry.
 *
 * The second half is what makes a one-result tag useful. Filter to
 * "krishna wall art", find two pieces, and the tag itself has nothing left to
 * give — but those two are also tagged Spiritual, Hindu Deities, Canvas Art,
 * and that is a far better answer than the shop's bestsellers.
 */
function af_xsell_related_tags($shown_ids) {
    $slugs = array();
    foreach (af_xsell_chosen_tags() as $chosen) $slugs[$chosen] = true;
    foreach ((array) $shown_ids as $pid) {
        $terms = get_the_terms($pid, 'product_tag');
        if (!$terms || is_wp_error($terms)) continue;
        foreach ($terms as $t) $slugs[$t->slug] = true;
    }
    return array_slice(array_keys($slugs), 0, 12);
}

/** Category slugs that read as "similar" to the archive currently being viewed. */
function af_xsell_related_slugs($shown_ids) {
    $slugs = array();
    $term  = get_queried_object();

    if ($term && !empty($term->term_id) && !empty($term->taxonomy) && $term->taxonomy === 'product_cat') {
        // Siblings under the same parent. A top-level category has no siblings
        // worth pairing with, so use its own children instead.
        $sibs = get_terms(array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => true,
            'parent'     => (int) $term->parent,
            'exclude'    => array((int) $term->term_id),
        ));
        if (is_wp_error($sibs) || !$sibs) {
            $sibs = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => true,
                                    'parent' => (int) $term->term_id));
        }
        if (!is_wp_error($sibs)) foreach ($sibs as $s) $slugs[$s->slug] = true;
    }

    // Whatever the products already on the page are filed under — a lone
    // penguin poster in a thin category is still the best clue we have.
    foreach ((array) $shown_ids as $pid) {
        $terms = get_the_terms($pid, 'product_cat');
        if (!$terms || is_wp_error($terms)) continue;
        foreach ($terms as $t) {
            if ($t->slug === 'uncategorized') continue;
            if ($term && !empty($term->slug) && $t->slug === $term->slug) continue;
            $slugs[$t->slug] = true;
        }
    }
    return array_slice(array_keys($slugs), 0, 12);
}

/**
 * Walk the stages narrowest-first and take products until the row is full,
 * never repeating one that is already on the page or already picked.
 *
 * Each stage is a fragment merged into a wc_get_products() call — array('tag'
 * => …), array('category' => …), or array() for the whole shop.
 */
function af_xsell_fill($stages, $exclude, $want) {
    $picks = array();
    $have  = (array) $exclude;
    foreach ((array) $stages as $narrow) {
        if (count($picks) >= $want) break;
        $found = wc_get_products(array_merge(array(
            'status' => 'publish', 'limit' => $want - count($picks),
            'orderby' => 'popularity', 'visibility' => 'catalog', 'exclude' => $have,
        ), (array) $narrow));
        foreach ((array) $found as $f) {
            if (count($picks) >= $want) break;
            $id = $f->get_id();
            if (in_array($id, $have, true)) continue;   // a stage may overlap the last
            $picks[] = $f;
            $have[]  = $id;
        }
    }
    return $picks;
}

/**
 * Print the row. Runs at most once per request — an archive with no products
 * fires woocommerce_no_products_found, one with a few fires
 * woocommerce_after_shop_loop, and a theme may well fire both.
 */
function af_xsell_render() {
    static $done = false;
    if ($done) return;
    if (!function_exists('wc_get_products') || !function_exists('is_product_category')) return;
    // A tag chosen from the "Filter by Tag" bar filters the listing in place
    // with ?product_tag=, so the page can still be the plain shop archive —
    // which is exactly where a thin result most needs the row.
    $tag_filtered = !empty($_GET['product_tag']);
    $on_listing   = is_product_category() || is_product_tag()
                    || ($tag_filtered && function_exists('is_shop') && is_shop());
    if (!$on_listing) return;
    if (is_paged()) return;                       // page 2+ is not a thin page

    global $wp_query;
    $shown_ids = array();
    if (isset($wp_query->posts) && is_array($wp_query->posts)) {
        foreach ($wp_query->posts as $p) {
            if (isset($p->ID)) $shown_ids[] = (int) $p->ID;
        }
    }
    if (count($shown_ids) >= af_xsell_min_cards()) return;   // the grid stands on its own

    // Fill the row in order of how closely each source answers what the
    // visitor asked for, and stop as soon as it is full. Ordering the sources
    // rather than merging them is the point: a single query over chosen tags
    // AND co-occurring tags AND sibling categories is an OR, so the shop's
    // bestsellers can outrank the one other piece in the tag that was
    // actually chosen. Graded stages cannot do that — nothing from a broader
    // source is looked at while a narrower one still has something to give.
    $want   = 8;
    $chosen = af_xsell_chosen_tags();
    $stages = array();
    if ($chosen)                              $stages[] = array('tag' => $chosen);
    if ($cotags = af_xsell_related_tags($shown_ids)) $stages[] = array('tag' => $cotags);
    if ($cats   = af_xsell_related_slugs($shown_ids)) $stages[] = array('category' => $cats);
    $stages[] = array();                      // whatever the studio sells best

    $picks = af_xsell_fill($stages, $shown_ids, $want);

    if (!$picks) return;
    $picks = array_slice($picks, 0, $want);
    $done  = true;

    // Say what the row IS. "You may also like" under a tag the visitor picked
    // themselves reads as filler; naming the tag says these were chosen.
    $title = 'You may also like';
    $sub   = 'More from the studio, close to what you were looking at.';
    if ($chosen) {
        $ct = get_term_by('slug', $chosen[0], 'product_tag');
        if ($ct && !is_wp_error($ct)) {
            $title = 'More in ' . $ct->name;
            $sub   = 'Other pieces tagged ' . $ct->name . ', and a few close to them.';
        }
    }

    echo '<section class="af-xsell"><h2>' . esc_html($title) . '</h2>'
       . '<p class="af-xsell-sub">' . esc_html($sub) . '</p>'
       . '<ul class="products columns-4">';
    $keep = isset($GLOBALS['post']) ? $GLOBALS['post'] : null;
    foreach ($picks as $p) {
        $po = get_post($p->get_id());
        if (!$po) continue;
        $GLOBALS['post'] = $po;
        setup_postdata($GLOBALS['post']);
        wc_get_template_part('content', 'product');
    }
    $GLOBALS['post'] = $keep;
    wp_reset_postdata();
    echo '</ul></section>';
    ?>
<style id="af-xsell-style">
.af-xsell{max-width:1200px;margin:40px auto 56px;padding:0 16px;clear:both}
.af-xsell h2{font-size:22px;margin:0 0 4px}
.af-xsell .af-xsell-sub{margin:0 0 18px;font-size:13.5px;opacity:.7}
.af-xsell ul.products{display:grid!important;grid-template-columns:repeat(4,1fr)!important;gap:20px!important;margin:0!important;padding:0!important;list-style:none!important}
.af-xsell ul.products::before,.af-xsell ul.products::after{display:none!important}
.af-xsell ul.products li.product{width:100%!important;margin:0!important;float:none!important}
.af-xsell ul.products li.product img{width:100%!important;height:auto!important;aspect-ratio:1/1;object-fit:cover!important}
@media (max-width:1024px){.af-xsell ul.products{grid-template-columns:repeat(3,1fr)!important}}
@media (max-width:760px){.af-xsell ul.products{grid-template-columns:repeat(2,1fr)!important}}
</style>
    <?php
}
// Three entry points because a thin archive and an empty one fire different
// hooks, and a theme that renders its own loop may fire neither — the static
// guard inside means the extra hooks cost nothing when an earlier one lands.
add_action('woocommerce_after_shop_loop', 'af_xsell_render', 30);
add_action('woocommerce_no_products_found', 'af_xsell_render', 30);
add_action('woocommerce_after_main_content', 'af_xsell_render', 30);

// Path 1: the wishlist arrives via a shortcode. Match ANY shortcode whose name
// mentions the wishlist — the first version accepted only names starting
// yith_wcwl, and the owner's recording proved the page renders through
// something else entirely.
add_filter('do_shortcode_tag', function ($output, $tag) {
    if (is_admin() || stripos((string) $tag, 'wishlist') === false && strpos((string) $tag, 'wcwl') === false && strpos((string) $tag, 'tinvwl') === false) return $output;
    if (!is_string($output) || $output === '' || strpos($output, 'af-wl-related') !== false) return $output;
    if (!af_is_wishlist_page()) return $output;
    $extra = af_wl_related_html($output);
    return $extra === '' ? $output : $output . $extra;
}, 20, 2);

// Path 2: whatever rendered the items, they end up in the page content. After
// the shortcodes have run (priority 99), append the row if path 1 did not.
add_filter('the_content', function ($content) {
    if (is_admin() || !is_string($content)) return $content;
    if (!af_is_wishlist_page() || !in_the_loop() || !is_main_query()) return $content;
    if (strpos($content, 'af-wl-related') !== false) return $content;
    $extra = af_wl_related_html($content);
    return $extra === '' ? $content : $content . $extra;
}, 99);

add_action('wp_footer', function () {
    if (!af_is_wishlist_page()) return;
    ?>
<link rel="stylesheet" id="af-wishlist-style" href="<?php echo esc_url(af_static_url('wishlist.css')); ?>" data-no-optimize="1">
<script id="af-wishlist-controls" src="<?php echo esc_url(af_static_url('wishlist-controls.js')); ?>" data-no-optimize="1"></script>
    <?php
}, 20);

// ─────────────────────────────────────────────────────────────
// "You may also like" on the CART page too (owner request, video
// 2026-08-12). Same row the wishlist got, built from the categories of what
// is in the cart. WooCommerce's native cross-sells slot would cover this only
// if every product had cross-sells picked by hand; none do.
// ─────────────────────────────────────────────────────────────
add_action('woocommerce_after_cart', function () {
    if (!function_exists('WC') || !WC()->cart || WC()->cart->is_empty()) return;
    $ids = array();
    foreach (WC()->cart->get_cart() as $item) {
        if (!empty($item['product_id'])) $ids[] = (int) $item['product_id'];
    }
    if (!$ids) return;
    // af_wl_related_html() reads ids out of markup; feed it the cart's ids in
    // the same shape rather than duplicating its category logic
    $html = af_wl_related_html(implode(' ', array_map(function ($id) {
        return 'add-to-cart=' . $id;
    }, array_unique($ids))));
    if ($html === '') return;
    // the wishlist's row styling, printed once here since the wishlist-page
    // footer block does not run on the cart
    echo '<style id="af-cart-related-style">
.af-wl-related{max-width:1200px;margin:44px auto 56px;padding:0 16px}
.af-wl-related h2{font-size:24px;margin:0 0 18px;color:#1a1a1a}
.af-wl-related ul.products{display:grid!important;grid-template-columns:repeat(4,1fr)!important;gap:20px!important;margin:0!important;padding:0!important;list-style:none!important}
.af-wl-related ul.products::before,.af-wl-related ul.products::after{display:none!important}
/* Uniform image box, same measurement the shop uses. The card script builds a
   fixed 300px box (260 under 520px) only for cards shipping TWO images, main
   plus hover. A single-image card never gets that box, so it keeps the natural
   ratio and the row goes ragged. Give those cards the same box; images already
   inside a built box stay under the script control. */
.af-wl-related li.product > a:first-of-type > img:not(.af-main-img):not(.af-hover-img){
  height:300px!important;width:100%!important;object-fit:cover!important;display:block!important}
@media (max-width:520px){
  .af-wl-related li.product > a:first-of-type > img:not(.af-main-img):not(.af-hover-img){height:260px!important}}
@media (max-width:900px){.af-wl-related ul.products{grid-template-columns:repeat(2,1fr)!important}}
</style>';
    echo $html;
}, 30);

// ─────────────────────────────────────────────────────────────
// Cart totals breakdown. "Subtotal $80 / Total $80" tells a buyer nothing and
// reads like a placeholder — the same two numbers twice, with no working shown.
// These rows show the arithmetic instead: how many pieces, what they list at
// before the discount, what that discount is worth, and what is still to be
// added at checkout.
//
// The reference price uses af_mrp_multiplier(), the same per-product figure
// behind the struck-through price on the card and the product page, so the
// saving quoted here is the one the shopper was shown on the way in. It is
// derived from the live line totals rather than stored, because the size and
// frame engine sets the price at add-to-cart time.
// ─────────────────────────────────────────────────────────────
function af_cart_reference_total() {
    if (!function_exists('WC') || !WC()->cart) return array(0, 0.0, 0.0);
    $items = 0; $ref = 0.0;
    foreach (WC()->cart->get_cart() as $item) {
        if (empty($item['product_id'])) continue;
        $qty    = isset($item['quantity']) ? (int) $item['quantity'] : 1;
        $line   = isset($item['line_subtotal']) ? (float) $item['line_subtotal'] : 0.0;
        // A gift card sells at face value and is not a piece to count.
        if (!empty($item['af_gc'])) { $ref += $line; continue; }
        $items += $qty;
        $ref   += $line * af_cart_line_reference_multiplier($item);
    }
    $sub = (float) WC()->cart->get_subtotal();
    return array($items, round($ref, 2), $sub);
}

/**
 * The "before discount" factor for one cart line, the same one its own page
 * shows. Bars and aluminium frame kits carry their listed regular price, and
 * downloads use af_digital_was(); every other piece keeps the per-product
 * af_mrp_multiplier(). One factor for every line made a $48 bar kit read
 * "$68.57 before" in the cart against "$96" on its page (checkout audit, 30 Sep).
 */
function af_cart_line_reference_multiplier($item) {
    $pid   = isset($item['product_id']) ? (int) $item['product_id'] : 0;
    $price = isset($item['af_price']) ? (float) $item['af_price'] : 0.0;
    foreach (array('af_bar_regular', 'af_alu_regular') as $k) {
        if (!empty($item[$k]) && $price > 0 && (float) $item[$k] >= $price) return (float) $item[$k] / $price;
    }
    if (!empty($item['af_digital']) && function_exists('af_digital_was') && function_exists('af_digital_price')) {
        $now = (float) af_digital_price($pid);
        if ($now > 0) return max(1.0, (float) af_digital_was($pid) / $now);
    }
    return af_mrp_multiplier($pid);
}

// Drawn before the delivery row when there is one, otherwise just before the
// total. WooCommerce only fires the "before shipping" hook when it shows a
// delivery row, so these rows vanished for a shopper who had not entered an
// address yet and for a download-only cart (checkout audit, 30 Sep).
function af_cart_breakdown_rows() {
    static $done = false;
    if ($done) return;
    if (!function_exists('af_mrp_multiplier') || !function_exists('WC') || !WC()->cart) return;
    list($items, $ref, $sub) = af_cart_reference_total();
    if ($items < 1) return;
    $done = true;
    $save = $ref - $sub;
    $pct  = $ref > 0 ? (int) round($save / $ref * 100) : 0;
    ?>
  <tr class="af-ct-items">
    <th><?php echo esc_html($items === 1 ? 'Item' : 'Items'); ?></th>
    <td data-title="Items"><?php echo esc_html($items); ?> <?php echo esc_html($items === 1 ? 'piece' : 'pieces'); ?></td>
  </tr>
    <?php if ($save > 0.01): ?>
  <tr class="af-ct-was">
    <th>Price before discount</th>
    <td data-title="Price before discount"><s><?php echo wp_kses_post(wc_price($ref)); ?></s></td>
  </tr>
  <tr class="af-ct-save">
    <th>You save</th>
    <td data-title="You save">&minus;<?php echo wp_kses_post(wc_price($save)); ?> <span class="af-ct-pct">(<?php echo esc_html($pct); ?>% off)</span></td>
  </tr>
    <?php endif; ?>
    <?php
}
add_action('woocommerce_cart_totals_before_shipping', 'af_cart_breakdown_rows', 5);
add_action('woocommerce_cart_totals_before_order_total', 'af_cart_breakdown_rows', 1);

/**
 * The delivery line under the cart total, true to what the total holds.
 * It always said "Shipping cost shown at checkout", even when the delivery
 * row sat right above it and was already added into the total, and even
 * for a download-only cart that has nothing to deliver (checkout audit,
 * 30 Sep). A free-shipping setting keeps its own wording.
 */
function af_cart_delivery_note() {
    $ship = function_exists('af_shipping_copy') ? af_shipping_copy() : array('short' => 'Shipping cost shown at checkout', 'free' => false);
    if (!empty($ship['free']) || !function_exists('WC') || !WC()->cart) return $ship['short'];
    $cart = WC()->cart;
    if (!$cart->needs_shipping()) return 'Digital download: nothing to deliver, no delivery charge';
    if ($cart->show_shipping()) {
        foreach (WC()->shipping()->get_packages() as $pkg) {
            if (!empty($pkg['rates'])) return 'Delivery to your address is included in the total';
        }
    }
    return $ship['short'];
}

/**
 * The truck row in the box under the cart total. It repeated the delivery
 * note printed just above it word for word, so a new guest read "Shipping
 * cost shown at checkout" twice (purchase QA, 14 Oct). It now says the one
 * thing that note does not: how long delivery takes.
 */
function af_cart_delivery_time() {
    if (function_exists('WC') && WC()->cart && !WC()->cart->needs_shipping()) return af_cart_delivery_note();
    return apply_filters('af_cart_delivery_time', 'Made to order and delivered across the USA in 7–14 days');
}

/**
 * The payment methods this cart can actually use. af_payment_methods() lists
 * every gateway switched on, so the cart promised "Cash App Pay" and "Square
 * Gift Card" while checkout offered Zelle, Credit Card and Cash on delivery
 * (purchase QA, 14 Oct). Checkout builds its list from the available
 * gateways, so the cart now reads the same list.
 */
function af_cart_payment_copy() {
    $names = array();
    if (function_exists('WC') && WC()->payment_gateways) {
        foreach (WC()->payment_gateways->get_available_payment_gateways() as $gw) {
            $t = trim(wp_strip_all_tags((string) $gw->get_title()));
            if ($t !== '') $names[] = $t;
        }
    }
    $names = array_values(array_unique($names));
    if (!$names) return af_payment_copy();
    if (count($names) === 1) return $names[0];
    $last = array_pop($names);
    return implode(', ', $names) . ' & ' . $last;
}

// Below the total: what is NOT yet in that number, said plainly. A buyer who
// cannot tell whether $80 is the final figure abandons the cart.
add_action('woocommerce_cart_totals_after_order_total', function () {
    if (!function_exists('af_shipping_copy')) return;
    ?>
  <tr class="af-ct-note">
    <td colspan="2">
      <span>Inclusive of all taxes</span>
      <span><?php echo esc_html(af_cart_delivery_note()); ?></span>
    </td>
  </tr>
    <?php
}, 5);

// ─────────────────────────────────────────────────────────────
// Cart totals box (owner: "give some more"). The box held only Subtotal, the
// gift-card field and Total. Add the reassurance a buyer looks for at exactly
// this moment — shipping terms, secure checkout, a human to call — using the
// site's single sources (af_shipping_copy / af_studio_contact) so nothing
// here can drift from the rest of the site. Scoped to the cart page.
// ─────────────────────────────────────────────────────────────
add_action('woocommerce_after_cart_totals', function () {
    if (!function_exists('af_shipping_copy') || !function_exists('af_studio_contact')) return;
    $ship = af_shipping_copy();
    $c    = af_studio_contact();
    ?>
<div class="af-ct-extra">
  <div class="af-ct-row">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
    <span><?php echo esc_html(af_cart_delivery_time()); ?></span>
  </div>
  <div class="af-ct-row">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
    <span>Secure SSL checkout &mdash; <?php echo esc_html(af_cart_payment_copy()); ?></span>
  </div>
  <div class="af-ct-row">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
    <span>Questions? <a href="<?php echo esc_attr($c['tel']); ?>"><?php echo esc_html($c['phone']); ?></a></span>
  </div>
  <div class="af-ct-row">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 12v10H4V12"></path><path d="M2 7h20v5H2z"></path><path d="M12 22V7"></path><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"></path><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"></path></svg>
    <span>Gallery-wrapped &amp; carefully packed</span>
  </div>
</div>
<style id="af-ct-style">
.cart_totals, .cart-collaterals .cart_totals{border-radius:14px!important}
.af-ct-extra{margin-top:18px;padding-top:16px;border-top:1px solid #eee;display:flex;flex-direction:column;gap:11px}
.af-ct-row{display:flex;align-items:flex-start;gap:10px;font-size:13.5px;color:#444;line-height:1.45}
.af-ct-row svg{width:18px;height:18px;flex:0 0 auto;margin-top:1px;color:#c9a84c}
.af-ct-row a{color:#8b6a2b;font-weight:600;text-decoration:none}
.af-ct-row a:hover{text-decoration:underline}
/* Breakdown rows. Colours are inherited from the totals box so this reads the
   same on the dark cart as on a light one; only the saving and the struck
   reference price are tinted, because those two carry the meaning. */
.cart_totals .af-ct-items th,.cart_totals .af-ct-was th,.cart_totals .af-ct-save th{font-weight:600;opacity:.85}
.cart_totals .af-ct-was td s{opacity:.6;text-decoration-thickness:1.5px}
.cart_totals .af-ct-save th,.cart_totals .af-ct-save td{color:var(--af-save, #2e7d32)!important}
.cart_totals .af-ct-save td .amount{color:var(--af-save, #2e7d32)!important}
.cart_totals .af-ct-pct{font-weight:700}
.cart_totals .af-ct-note td{padding-top:4px!important;border-top:0!important}
.cart_totals .af-ct-note span{display:block;font-size:12px;opacity:.7;line-height:1.5}
</style>
    <?php
}, 20);

// ─────────────────────────────────────────────────────────────
// Related-row cards are SHOP-PAGE cards. They come from the same template
// (wc_get_template_part content-product), so the site's own card machinery —
// the uniform image box, the hover second image, the button styling — already
// treats them correctly. Earlier passes here added their own image sizing and
// moved the hover block around by hand, which fought that machinery and
// produced the tall white gaps under the artwork. Nothing does that now: the
// row only lays the cards out in a grid, and the cards style themselves.
// ─────────────────────────────────────────────────────────────
add_action('wp_footer', function () {
    ?>
<style id="af-wl-related-stray">
/* An art code printed outside a card is noise between sections. The earlier
   rule was scoped inside .af-wl-related, but the stray line sits BEFORE that
   section, so it kept showing above the heading. Hide art codes that are not
   inside a product card, on the wishlist and cart pages only. Codes inside
   cards are untouched. */
.woosw-list .af-art-code,
.entry-content > .af-art-code,
.entry-content > * > .af-art-code:not(li .af-art-code),
.af-wl-related > .af-art-code{display:none!important}
</style>
    <?php
}, 21);

// ─────────────────────────────────────────────────────────────
// SIDEBAR CATEGORIES = THE HEADER'S CATEGORY MENU
// The sidebar listed all 91 product categories — including frame sizes and
// frame colours, which are attribute values that happen to be terms, and
// several that hold nothing. The header menu already answers "what does this
// shop sell" in ten lines. The sidebar now shows that same list, in the same
// order, worded the same way — so a section added to the header menu has to be
// added here too, or it silently goes missing from the shop sidebar.
//
// Kept as slugs with fallbacks rather than IDs: slugs survive a re-import,
// and the site has near-duplicate terms (digital-downloads vs
// digital-downloads-2, home-decor-space vs home-decor-by-space) where either
// may be the live one.
// ─────────────────────────────────────────────────────────────
function af_sidebar_cat_menu() {
    return array(
        array('label' => 'Digital Canvas Prints', 'slugs' => array('digital-canvas-prints', 'digital-canvas-print')),
        array('label' => 'Framed Canvases',       'slugs' => array('framed-canvases')),
        array('label' => 'Direct from Artists',   'slugs' => array('direct-from-artists', 'from-artists')),
        array('label' => 'Art Accessories',       'slugs' => array('art-accessories')),
        array('label' => 'Banners & Signage',     'slugs' => array('banners-signage', 'banners-and-signage')),
        // A category of its own, sitting directly after Banners & Signage in
        // the header, so it belongs here in the same place. Empty for now,
        // which this allow-list handles the same way it handles Gold Foiled
        // & UV below.
        array('label' => 'Corporate Printing',    'slugs' => array('corporate-printing')),
        array('label' => 'Digital Downloads',     'slugs' => array('digital-downloads', 'digital-downloads-2')),
        array('label' => 'Home Decor Space',      'slugs' => array('home-decor-space', 'home-decor-by-space', 'home-decor')),
        array('label' => 'Personalised Prints',   'slugs' => array('personalised-prints', 'personalized-prints')),
        array('label' => 'Gifts',                 'slugs' => array('gifts')),
        // The header's category menu gained this section, and this list exists
        // to mirror that menu — so it belongs here too. It carries no products
        // yet, which does not matter: this widget is an allow-list with
        // hide_empty off, and several of the rows above are empty for the same
        // reason.
        // Renamed from "Gold Foiled & UV". The slug stays, because
        // inc/gold-foil.php hardcodes it and styles the archive by it; only the
        // wording moved. This widget keeps its own copy of the label, so it has
        // to move with the category or the sidebar keeps saying the old name.
        array('label' => 'Embossed Prints',       'slugs' => array('gold-foiled-uv')),
    );
}

/** term_id => the header's wording, in the header's order. Empty if none resolve. */
function af_sidebar_cat_terms() {
    static $map = null;
    if ($map !== null) return $map;
    $map = array();
    foreach (af_sidebar_cat_menu() as $row) {
        foreach ($row['slugs'] as $slug) {
            $t = get_term_by('slug', $slug, 'product_cat');
            if ($t && !is_wp_error($t)) { $map[(int) $t->term_id] = $row['label']; break; }
        }
    }
    return $map;
}

/**
 * The nine parents plus their DIRECT children, in display order.
 *
 * Direct children only, on purpose. Going deeper would drag the whole tree
 * back in — art-accessories → frame-sizes → "2x3 ft", "4x6 ft" and the rest
 * are grandchildren, and those are attribute values wearing category
 * costumes. One level down is the shop's structure; two is its plumbing.
 */
function af_sidebar_cat_tree_ids() {
    $ids = array();
    foreach (array_keys(af_sidebar_cat_terms()) as $parent_id) {
        $ids[] = (int) $parent_id;
        $kids = get_terms(array(
            'taxonomy'   => 'product_cat',
            'parent'     => (int) $parent_id,
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
            'fields'     => 'ids',
        ));
        if (!is_wp_error($kids)) foreach ($kids as $k) $ids[] = (int) $k;
    }
    return array_values(array_unique($ids));
}

// The widget builds its list through wp_list_categories; include only these
// terms, in this order. orderby=include is what keeps the header's order
// rather than falling back to alphabetical, and hierarchical nests the
// children under the parent they belong to.
add_filter('woocommerce_product_categories_widget_args', function ($args) {
    $ids = af_sidebar_cat_tree_ids();
    if (!$ids) return $args;                       // resolved nothing — leave the widget alone
    $args['include']      = implode(',', $ids);
    $args['orderby']      = 'include';
    $args['hierarchical'] = 1;
    $args['depth']        = 2;                     // parents and their children, no deeper
    $args['hide_empty']   = 0;                     // several are empty; the cross-sell row covers that
    return $args;
}, 20);

// Use the header's wording where the term's own name differs. WooCommerce's
// widget renders through its own walker, which fires list_product_cats and
// never list_cats — filtering only the latter is why "Home Decor Space" was
// still coming out as the term's own "Home Decor By Space". Both are hooked
// so it does not matter which walker is in play.
function af_sidebar_cat_label($name, $category = null) {
    if (!$category || !is_object($category) || empty($category->term_id)) return $name;
    $terms = af_sidebar_cat_terms();
    return isset($terms[(int) $category->term_id]) ? $terms[(int) $category->term_id] : $name;
}
add_filter('list_cats', 'af_sidebar_cat_label', 20, 2);
add_filter('list_product_cats', 'af_sidebar_cat_label', 20, 2);

// ─────────────────────────────────────────────────────────────
// CURRENCY SWITCHER — say what the money is
// The switcher lists "$ USD" and "CA$ CAD". That is a symbol and a code and
// no words: it tells you how the price will be punctuated, not which
// country's dollars you are about to be charged in — which is the one thing
// a shopper actually needs to know before switching.
//
// The list now reads "CA$ CAD — Canadian Dollar". The button keeps the short
// form, because it sits in a header row that has to stay on one line.
//
// Relabelled in the browser: the switcher is a plugin's, and its markup
// differs between the header dropdown and the <select> it falls back to.
// ─────────────────────────────────────────────────────────────
add_action('wp_footer', function () {
    if (is_admin()) return;
    $names = array();
    foreach (af_allowed_currencies() as $code) {
        $names[$code] = array(af_currency_symbol_for($code), af_currency_name_for($code));
    }
    ?>
<script>
(function(){
  var CUR = <?php echo wp_json_encode($names); ?>;
  // "$ USD", "CA$ CAD", "USD", "CA$" — the shapes the plugin prints
  var RE = /^\s*(?:(CA\$|C\$|US\$|\$)\s*)?(USD|CAD)\s*$/i;

  function label(code, sym){ return sym + ' ' + code + ' — ' + CUR[code][1]; }

  function relabel(el, text){
    var m = RE.exec(text);
    if (!m) return false;
    var code = m[2].toUpperCase();
    if (!CUR[code]) return false;
    var sym = m[1] || CUR[code][0];
    var full = label(code, sym);
    if (el.tagName === 'OPTION') { el.textContent = full; return true; }
    // only the dropdown list is spelled out — the trigger stays short so the
    // header row it lives in keeps to one line
    var inList = el.closest('ul, ol, [class*="dropdown"], [class*="menu"], [class*="list"]');
    var isTrigger = el.closest('[aria-haspopup], [class*="current"], [class*="selected"], [class*="toggle"], [class*="active"]');
    if (!inList || isTrigger) return false;
    el.textContent = full;
    return true;
  }

  function scan(){
    document.querySelectorAll('option').forEach(function(o){
      if (o.dataset.afCur) return;
      if (relabel(o, o.textContent)) o.dataset.afCur = '1';
    });
    // leaf elements only, so a wrapper is never rewritten over its own child
    document.querySelectorAll('a, span, li, button, div').forEach(function(el){
      if (el.dataset.afCur || el.children.length) return;
      var t = el.textContent || '';
      if (t.length > 12) return;                 // cheap reject before the regex
      if (relabel(el, t)) el.dataset.afCur = '1';
    });
  }
  if (document.readyState !== 'loading') scan();
  else document.addEventListener('DOMContentLoaded', scan);
  window.addEventListener('load', scan);
  var t = null;
  try {
    new MutationObserver(function(){ clearTimeout(t); t = setTimeout(scan, 150); })
      .observe(document.body, { childList:true, subtree:true });
  } catch(e){}
})();
</script>
    <?php
}, 44);

// ─────────────────────────────────────────────────────────────
// TEMPORARY, owner-only: visit the homepage with ?af_debug_circles=1 and a
// small panel reports what a circle click actually does — how many circles
// were wired, which slug the click resolved, which container was chosen for
// the swap, and what the ajax answered. Ten days of this regression have been
// diagnosed from server logs, which cannot see a browser click; this can.
// Prints nothing for normal visitors.
// ─────────────────────────────────────────────────────────────
add_action('wp_footer', function () {
    if (!isset($_GET['af_debug_circles']) || !current_user_can('manage_options')) return;
    ?>
<style>#af-circdbg{position:fixed;left:10px;bottom:10px;z-index:99999;background:#111;color:#9f9;
font:12px/1.5 monospace;padding:10px 14px;border-radius:8px;max-width:460px;max-height:45vh;overflow:auto}</style>
<div id="af-circdbg">circle debug on…</div>
<script>
(function(){
  var box = document.getElementById('af-circdbg');
  window.afdbg = function(m){ box.innerHTML += '<br>' + m; };
  window.addEventListener('load', function(){
    setTimeout(function(){
      var strips = document.querySelectorAll('#subcategorySlider, .subcategory-slider, ul.postero-scroll-content').length;
      var items = document.querySelectorAll('a.pf-value, li.cat-item, .sub-cat').length;
      var pf = document.querySelectorAll('a.pf-value').length;
      var grids = ['#productGrid','.product-slider','.custom-product-track','ul.products']
        .map(function(sel){ return sel + ':' + document.querySelectorAll(sel).length; }).join('  ');
      afdbg('delegated handler on document  strips:' + strips + '  circle items:' + items + '  pf-value anchors:' + pf);
      afdbg('grids ' + grids);
    }, 1500);
  });
})();
</script>
    <?php
}, 9998);

// ─────────────────────────────────────────────────────────────
// Related row: uniform artwork height, measured from the DOM.
// The shop-card script builds its fixed image box only for cards shipping a
// main AND a hover image; a single-image card keeps its natural ratio and sits
// visibly shorter than the row. Two stylesheet attempts at this missed because
// they assumed where the img lives in the card; this finds it wherever it is
// and sizes it inline, which no stylesheet can override. Cards the script
// already boxed (.af-main-img) are left to the script. Runs only where the
// related row exists.
// ─────────────────────────────────────────────────────────────
add_action('wp_footer', function () {
    ?>
<script>
(function(){
  function even(){
    var cards = document.querySelectorAll('.af-wl-related li.product');
    if (!cards.length) return;
    var H = (window.innerWidth <= 520) ? 260 : 300;
    cards.forEach(function(card){
      var img = card.querySelector('img');
      if (!img || img.classList.contains('af-main-img') || img.classList.contains('af-hover-img')) return;
      img.style.setProperty('height', H + 'px', 'important');
      img.style.setProperty('width', '100%', 'important');
      img.style.setProperty('object-fit', 'cover', 'important');
      img.style.setProperty('display', 'block', 'important');
      var box = img.parentElement;
      if (box && box !== card) {
        box.style.setProperty('display', 'block', 'important');
        box.style.setProperty('height', H + 'px', 'important');
        box.style.setProperty('overflow', 'hidden', 'important');
      }
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', even);
  else even();
  window.addEventListener('load', even);
  [600, 1500, 3000].forEach(function(d){ setTimeout(even, d); });
  var rt; window.addEventListener('resize', function(){ clearTimeout(rt); rt = setTimeout(even, 200); });
})();
</script>
    <?php
}, 22);

// ─────────────────────────────────────────────────────────────
// Cart page LAYOUT only (owner request, video 2026-08-13). Three things, all
// measured in the browser and applied to this page alone. No cart behaviour,
// totals, coupon handling or checkout logic is touched.
//
//   1. Three different left edges: the items table, the coupon row and the
//      related row each sat on their own margin. They are put on one edge.
//   2. Product titles broke mid-word ("Wa / ll Art", "Pre / mium") because a
//      break-anywhere rule applies in the narrow name column. Words wrap at
//      word boundaries again.
//   3. An art code printed inside the Cart totals box, where it belongs to no
//      product. Hidden, exactly as on the wishlist page.
// ─────────────────────────────────────────────────────────────
add_action('wp_footer', function () {
    if (!function_exists('is_cart') || !is_cart()) return;
    ?>
<script>
(function(){
  function titles(){
    document.querySelectorAll('.woocommerce-cart-form .product-name a, .woocommerce-cart-form .product-name')
      .forEach(function(el){
        el.style.setProperty('word-break', 'normal', 'important');
        el.style.setProperty('overflow-wrap', 'break-word', 'important');
        el.style.setProperty('hyphens', 'manual', 'important');
      });
  }
  function strayCodes(){
    document.querySelectorAll('.af-art-code').forEach(function(el){
      if (el.closest('li.product') || el.closest('tr.cart_item')) return;  // inside a product: keep
      el.style.setProperty('display', 'none', 'important');
    });
  }
  // one shared left/right edge for every block on the page
  function align(){
    var form = document.querySelector('.woocommerce-cart-form');
    var rel  = document.querySelector('.af-wl-related');
    if (!form) return;
    var host = form.parentElement;
    if (!host) return;
    var hostBox = host.getBoundingClientRect();
    var formBox = form.getBoundingClientRect();
    if (!formBox.width || !hostBox.width) return;
    // The cart is two columns: the items form on the left, the totals box on
    // the right. Aligning the related row to the FORM alone made it stop at the
    // form edge and leave the whole totals column empty beside it — the gap in
    // the recording. The content width is the union of both columns, so the
    // related row spans from the form's left edge to the totals' right edge.
    var totals = document.querySelector('.cart-collaterals, .cart_totals');
    var totalsBox = totals ? totals.getBoundingClientRect() : null;
    var contentLeft  = formBox.left;
    var contentRight = formBox.right;
    if (totalsBox && totalsBox.width) {
      contentLeft  = Math.min(contentLeft,  totalsBox.left);
      contentRight = Math.max(contentRight, totalsBox.right);
    }
    var left  = Math.round(contentLeft  - hostBox.left);
    var right = Math.round(hostBox.right - contentRight);
    if (left < 0 || right < 0) return;
    var targets = [];
    if (rel) targets.push(rel);
    targets.forEach(function(el){
      el.style.setProperty('box-sizing', 'border-box', 'important');
      el.style.setProperty('max-width', 'none', 'important');
      el.style.setProperty('margin-left', left + 'px', 'important');
      el.style.setProperty('margin-right', right + 'px', 'important');
      el.style.setProperty('padding-left', '0', 'important');
      el.style.setProperty('padding-right', '0', 'important');
    });
  }
  function run(){ titles(); strayCodes(); align(); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run);
  else run();
  window.addEventListener('load', run);
  [400, 1200, 2500].forEach(function(d){ setTimeout(run, d); });
  var rt; window.addEventListener('resize', function(){ clearTimeout(rt); rt = setTimeout(run, 200); });
  // the cart re-renders its contents over ajax on quantity changes
  if (window.jQuery) jQuery(document.body).on('updated_cart_totals updated_wc_div', run);
})();
</script>
    <?php
}, 23);

// ─────────────────────────────────────────────────────────────
// Related products as a slider (owner request 2026-08-13): one row that
// scrolls, with next/previous buttons, instead of two stacked rows of four.
// Runs wherever the related row exists — wishlist and cart. Layout only: the
// cards themselves, their links, buttons and prices are untouched.
// ─────────────────────────────────────────────────────────────
add_action('wp_footer', function () {
    ?>
<style id="af-rel-slider-css">
/* Owner-only probe: /cart/?af_debug_cards=1 reports what each card actually
   ships, since a browser click cannot be inspected from the server. */
.af-rel-vp{position:relative;width:100%;overflow:hidden}
.af-rel-vp ul.products{scrollbar-width:none;-ms-overflow-style:none}
.af-rel-vp ul.products::-webkit-scrollbar{display:none}
.af-rel-nav{position:absolute;top:38%;transform:translateY(-50%);z-index:5;
  width:44px;height:44px;border-radius:50%;border:0;cursor:pointer;
  background:#c9a84c;color:#fff;display:flex;align-items:center;justify-content:center;
  box-shadow:0 2px 10px rgba(0,0,0,.22);transition:background .2s,opacity .2s}
.af-rel-nav:hover{background:#8b6a2b}
.af-rel-nav[hidden]{display:none!important}
.af-rel-prev{left:-6px}
.af-rel-next{right:-6px}
/* the theme's global button rules flatten inline svg; state the icon size and
   colour here too, so the chevrons survive even before the script runs */
.af-rel-nav svg{width:20px!important;height:20px!important;display:block!important;
  fill:none!important;stroke:currentColor!important;stroke-width:2.5!important;opacity:1!important}
.af-rel-nav *{color:#fff!important}
@media (max-width:600px){.af-rel-nav{width:38px;height:38px}}
</style>
<script id="af-carousel-perview" src="<?php echo esc_url(af_static_url('carousel-perview.js')); ?>" data-no-optimize="1"></script>
    <?php
}, 24);

// ─────────────────────────────────────────────────────────────
// "Filter by Tag" — show only tags that actually have products here.
//
// The filter itself works (clicking #Canvas Art applies ?product_tag=canvas-art
// and the grid changes). The complaint is the tags that lead nowhere: the
// widget lists every tag in the store, including ones with no product in the
// category being browsed, so clicking them empties the page. Work out which
// tags have at least one product in this context and hide the rest. The filter
// logic, the widget and the links are untouched — only dead chips disappear.
//
// Two queries, cached for six hours per category.
// ─────────────────────────────────────────────────────────────
function af_live_tag_slugs() {
    if (!function_exists('is_shop')) return array();
    $term = (function_exists('is_product_category') && is_product_category())
          ? get_queried_object() : null;
    $key  = 'af_live_tags_' . (($term && !is_wp_error($term)) ? (int) $term->term_id : 'shop');

    $cached = get_transient($key);
    if (is_array($cached)) return $cached;

    $args = array('post_type' => 'product', 'post_status' => 'publish',
                  'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true);
    if ($term && !is_wp_error($term)) {
        $args['tax_query'] = array(array(
            'taxonomy' => 'product_cat', 'field' => 'term_id',
            'terms' => (int) $term->term_id, 'include_children' => true,
        ));
    }
    $ids = get_posts($args);
    $slugs = array();
    if ($ids) {
        $tags = wp_get_object_terms($ids, 'product_tag', array('fields' => 'all'));
        if (!is_wp_error($tags)) {
            foreach ($tags as $t) $slugs[$t->slug] = true;
        }
    }
    $slugs = array_keys($slugs);
    set_transient($key, $slugs, 6 * HOUR_IN_SECONDS);
    return $slugs;
}

add_action('wp_footer', function () {
    if (is_admin()) return;
    if (!function_exists('is_shop')) return;
    if (!is_shop() && !(function_exists('is_product_category') && is_product_category())) return;
    $live = af_live_tag_slugs();
    if (!$live) return;
    ?>
<script>
(function(){
  var LIVE = <?php echo wp_json_encode(array_values($live)); ?>;
  var ok = {};
  LIVE.forEach(function(s){ ok[s] = 1; });

  function slugOf(a){
    var d = a.getAttribute('data-val');
    if (d) return d;
    var href = a.getAttribute('href') || '';
    var m = href.match(/[?&]product_tag=([^&#]+)/);
    if (m) return decodeURIComponent(m[1]);
    m = href.match(/\/product-tag\/([^\/?#]+)/);
    return m ? decodeURIComponent(m[1]) : '';
  }
  function prune(){
    document.querySelectorAll('a[href*="product_tag="], a[href*="/product-tag/"], a.pf-value[data-val]')
      .forEach(function(a){
        // only inside a tag filter list — never touch tags printed on a product
        if (a.closest('li.product, .product-card, .woosw-item, .af-wl-related')) return;
        var slug = slugOf(a);
        if (!slug || ok[slug]) return;
        var chip = a.closest('li') || a;
        chip.style.setProperty('display', 'none', 'important');
      });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', prune);
  else prune();
  window.addEventListener('load', prune);
  [400, 1200, 2500].forEach(function(d){ setTimeout(prune, d); });
  try {
    new MutationObserver(function(m){
      for (var i = 0; i < m.length; i++) { if (m[i].addedNodes && m[i].addedNodes.length) { prune(); break; } }
    }).observe(document.body, {childList:true, subtree:true});
  } catch(e){}
})();
</script>
    <?php
}, 25);

// ─────────────────────────────────────────────────────────────
// Shop and category archives: three cards to a row (owner request,
// 2026-08-18). Four across made each card narrow enough that the titles
// wrapped to three lines and the art itself was the smallest thing on the
// page. Three gives the artwork room without leaving gaps.
//
// Scoped to the ARCHIVES only. The homepage sliders, the "You may also like"
// row on cart and wishlist, and the cross-sell row keep their own layouts —
// those were tuned separately and are not touched here.
// ─────────────────────────────────────────────────────────────
add_filter('loop_shop_columns', function () { return 3; }, 20);

add_action('wp_head', function () {
    if (is_admin()) return;
    if (!function_exists('is_shop')) return;
    if (!is_shop() && !is_product_category() && !is_product_tag() && !is_post_type_archive('product')) return;
    ?>
<style id="af-archive-3col">
/* the grid itself — written against every column class WooCommerce may emit.
   Every breakpoint below repeats this selector list VERBATIM. That is not
   duplication for its own sake: the list carries two :not() arguments, and a
   Level 4 :not() takes the specificity of its most specific argument, so this
   list scores (0,6,4). The narrower rules used to be written short —
   "body.woocommerce ul.products", (0,2,2) — and lost to this one on
   specificity even though they came later and even though both sides are
   !important. The phone and tablet layouts existed in the file and had never
   once applied. Keep the lists identical and source order decides, which is
   what was meant all along. */
body.woocommerce ul.products:not(.af-wl-related ul.products):not(.af-xsell ul.products),
body.woocommerce-page ul.products:not(.af-wl-related ul.products):not(.af-xsell ul.products),
.woocommerce ul.products.columns-1,
.woocommerce ul.products.columns-2,
.woocommerce ul.products.columns-3,
.woocommerce ul.products.columns-4,
.woocommerce ul.products.columns-5,
.woocommerce ul.products.columns-6 {
  display: grid !important;
  /* minmax(0,1fr), not a bare 1fr. A bare 1fr is minmax(auto,1fr), and that
     auto floors each column at its content's min-content width — which for a
     title styled display:-webkit-box is the whole title on ONE line. Measured
     in a fixture built from these same declarations: with a card whose
     overflow is visible, three such columns demand 623px and the page scrolls
     sideways on any phone. The live card happens to clip its own overflow,
     which per the Grid spec drops that automatic minimum to zero, so the page
     did not scroll — it crushed the cards to 83px at 320px instead. A zero
     floor states the intent outright rather than depending on a clip
     elsewhere staying where it is. */
  grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
  gap: 22px !important;
  margin: 0 !important;
  padding: 0 !important;
  list-style: none !important;
}
.woocommerce ul.products::before,
.woocommerce ul.products::after { display: none !important; }

/* the cards: the theme floats them at a percentage width, which fights a grid */
.woocommerce ul.products li.product,
.woocommerce-page ul.products li.product {
  width: 100% !important;
  max-width: 100% !important;
  /* the same min-content floor, one level down — a grid item's automatic
     minimum size is its content's, unless it is told otherwise */
  min-width: 0 !important;
  margin: 0 !important;
  float: none !important;
  clear: none !important;
}

/* the related/cross-sell rows keep their own four-across layout */
.af-wl-related ul.products,
.af-xsell ul.products {
  grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
  gap: 20px !important;
}

/* Tablet — two across. */
@media (max-width: 1024px) {
  body.woocommerce ul.products:not(.af-wl-related ul.products):not(.af-xsell ul.products),
  body.woocommerce-page ul.products:not(.af-wl-related ul.products):not(.af-xsell ul.products),
  .woocommerce ul.products.columns-1,
  .woocommerce ul.products.columns-2,
  .woocommerce ul.products.columns-3,
  .woocommerce ul.products.columns-4,
  .woocommerce ul.products.columns-5,
  .woocommerce ul.products.columns-6 {
    grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    gap: 18px !important;
  }
  .af-wl-related ul.products,
  .af-xsell ul.products { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; }
}

/* Phone — still two across, with a tighter gutter.
   Three across gave each card a 115px track on a 420px screen, measured: the
   artwork was a sliver, the title lost its third line mid-letter and the art
   code wrapped onto a second line in some cards and not others, which is what
   knocked every row out of alignment. One across is the other extreme — this
   catalogue runs to hundreds of pieces and a shopper would scroll a screen per
   product. Two gives a 158px card at 360px and 188px at 420px: the art reads,
   the title fits on two lines, and the page is still a shop you can scan. */
@media (max-width: 600px) {
  body.woocommerce ul.products:not(.af-wl-related ul.products):not(.af-xsell ul.products),
  body.woocommerce-page ul.products:not(.af-wl-related ul.products):not(.af-xsell ul.products),
  .woocommerce ul.products.columns-1,
  .woocommerce ul.products.columns-2,
  .woocommerce ul.products.columns-3,
  .woocommerce ul.products.columns-4,
  .woocommerce ul.products.columns-5,
  .woocommerce ul.products.columns-6 {
    grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    gap: 12px !important;
  }
  .af-wl-related ul.products,
  .af-xsell ul.products { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; gap: 12px !important; }
}
</style>
<link rel="stylesheet" id="af-archive-phone" href="<?php echo esc_url(af_static_url('archive-phone.css')); ?>" data-no-optimize="1">
    <?php
}, 99);


// ─────────────────────────────────────────────────────────────
// Filters must say the same thing as the product page.
//
// Frames: the product page has offered only Without Frame and Aluminium
// Frame since af_frames_in_stock() was introduced — Fibre and Floating are
// shown struck through and cannot be picked. Sizes: the page sells the five
// in af_sizes_offered() and no others. Neither restriction reached the
// filters, which listed all four frames and all fourteen sizes as equal,
// live choices with a count beside each — so a shopper could filter down to
// 337 products in a size or frame that is unbuyable on every one of them.
//
// The same two lists drive the filters and the product page, so putting an
// option back on sale is still one edit to af_frames_in_stock() or
// af_sizes_offered() and nothing else.
//
// Three routes in, because these lists appear in markup we own and markup we
// do not:
//   • WooCommerce's own layered-nav widget, via its term-html filter;
//   • our archive toolbar's Frame Type and Size dropdowns, where they are built;
//   • anything else — a theme widget, a filter block — by matching the label
//     text on the page, which is the only handle on markup this theme builds
//     in Elementor and does not expose a hook for.
//
// These options are HIDDEN, not struck through. Striking them was the first
// answer and it was wrong twice over: ten struck rows out of fifteen made the
// Size filter unreadable, a wall of greyed text with three live options lost
// in it, and a filter is a way to narrow a list — an option that narrows to
// nothing has no place in it. The product page is where a size gets explained
// (and it already drops the ones it does not sell); the sidebar just stops
// offering them.
// ─────────────────────────────────────────────────────────────

/**
 * WooCommerce's layered-nav widget: take the term out of the list.
 *
 * The widget wraps whatever this returns in its own <li>, and that <li> is not
 * ours to remove — so returning nothing would leave an empty row. Return a
 * marker instead, which the stylesheet below hides along with the <li> holding
 * it. Emitting the marker rather than relying on the row measuring "empty"
 * matters: an empty row is a guess about whitespace, and a marker is a fact.
 */
add_filter('woocommerce_layered_nav_term_html', function ($html, $term, $link, $count) {
    if (!function_exists('af_filter_label_is_oos')) return $html;
    $name = is_object($term) && isset($term->name) ? $term->name : '';
    if (!af_filter_label_is_oos($name)) return $html;
    return '<span class="af-fx-gone" data-af-oos="1" aria-hidden="true"></span>';
}, 10, 4);

add_action('wp_footer', function () {
    if (is_admin()) return;
    $oos = function_exists('af_filter_oos_labels') ? af_filter_oos_labels() : array();
    if (!$oos) return;
    ?>
<style id="af-filter-oos-css">
/* Hidden in the stylesheet, so the row is never painted — a row removed by
   script after first paint flashes on screen first, and on a fifteen-row Size
   filter that flash is the whole list jumping. The :has() rule takes the <li>
   the widget wrapped around our marker; the script below is the fallback for
   anything that route does not reach. */
.af-fx-gone{display:none !important;}
li:has(> .af-fx-gone),
.af-fx-oos{display:none !important;}
</style>
<script id="af-filter-oos">
(function(){
  var OOS = <?php echo wp_json_encode(array_map('strtolower', $oos)); ?>;
  if (!OOS.length) return;

  // Where a filter can plausibly live. Deliberately broad, because the match
  // itself is exact — an element only gets touched when its whole text is the
  // name of an option we cannot sell, so a wide net costs nothing.
  var SCOPE = '.widget, aside, .sidebar, .widget-area, .elementor-widget-woocommerce-products,' +
              '[class*="layered"], [class*="filter"], [class*="facet"], [class*="attribute"]';
  // …except the product page's own picker, which already says all this and
  // says it better.
  var SKIP  = '.af-chips, .af-opt-group, form.cart, .af-ftm-panel';

  function clean(t){
    return (t || '').replace(/\s+/g, ' ')
                    .replace(/\(\s*\d+\s*\)\s*$/, '')   // the "(337)" count
                    .trim().toLowerCase();
  }
  function isOos(el){ return OOS.indexOf(clean(el.textContent)) !== -1; }

  // Take the row off the page. Hidden rather than removed: these widgets
  // re-render themselves and count their own children, and deleting nodes out
  // from under a script that owns them is how you get a widget that breaks on
  // the second refine.
  function hide(row){
    row.setAttribute('data-af-fx-oos', '1');
    row.classList.add('af-fx-oos');
    row.setAttribute('aria-hidden', 'true');
    row.style.setProperty('display', 'none', 'important');
    // Hidden is not the same as unreachable: a display:none input is still
    // submitted with its form, and still focusable in some browsers.
    var kids = row.querySelectorAll('a, input, button');
    for (var i = 0; i < kids.length; i++) {
      var k = kids[i];
      k.setAttribute('tabindex', '-1');
      k.setAttribute('aria-hidden', 'true');
      if (k.tagName === 'INPUT' || k.tagName === 'BUTTON') k.disabled = true;
    }
  }

  function sweep(){
    var scopes = document.querySelectorAll(SCOPE);
    for (var s = 0; s < scopes.length; s++) {
      var sc = scopes[s];
      if (sc.closest && sc.closest(SKIP)) continue;

      // Rows the PHP hook already emptied. The <li> around our marker belongs
      // to the widget, so this is the only way to reach it if :has() does not
      // apply — and it must run BEFORE the label pass, which cannot see these
      // rows at all now that their text is gone.
      var gone = sc.querySelectorAll('.af-fx-gone');
      for (var g = 0; g < gone.length; g++) {
        var grow = gone[g].closest('li') || gone[g].parentElement;
        if (grow && !grow.hasAttribute('data-af-fx-oos')) hide(grow);
      }

      var cand = sc.querySelectorAll('li, label, a, span');
      for (var i = 0; i < cand.length; i++) {
        var el = cand[i];
        if (el.closest('[data-af-fx-oos]')) continue;   // this row is done
        if (el.closest(SKIP)) continue;
        if (!isOos(el)) continue;
        // Prefer the whole row, so the checkbox and the count go with it —
        // but only if the row is JUST this option and not a list that happens
        // to contain it.
        var row = el.closest('li');
        if (!row || !isOos(row)) row = el;
        hide(row);
      }
    }
  }

  var t = null, busy = false;
  function soon(){
    if (busy) return;
    clearTimeout(t);
    t = setTimeout(function(){
      busy = true;                 // our own DOM writes must not re-trigger us
      sweep();
      setTimeout(function(){ busy = false; }, 80);
    }, 100);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', soon);
  else soon();
  // Filter widgets re-render themselves after an AJAX refine, which drops the
  // marks — so watch for it rather than assuming one pass is enough.
  try { new MutationObserver(soon).observe(document.body, {childList:true, subtree:true}); } catch (e) {}
  [400, 1500].forEach(function(d){ setTimeout(soon, d); });
})();
</script>
    <?php
}, 98);

// ─────────────────────────────────────────────────────────────
// Product page: the picture stays put while the details scroll.
//
// The gallery is short and the buy column is long — size, frame, colour,
// price, delivery, the lot. Scrolling to read any of it used to carry the
// artwork off the top of the screen, so by the time someone was choosing a
// frame they could no longer see the thing they were framing. Pinning the
// gallery keeps the product in view for the whole decision.
//
// CSS alone cannot do this here. `position: sticky` is silently cancelled by
// any ancestor with a clipping overflow, and by a flex parent that stretches
// its children to full height — both of which this theme does, in markup the
// child theme does not own. So the rule is applied, and then the handful of
// conditions that would cancel it are cleared on the ancestors between the
// gallery and the row. Nothing else on the page is touched.
// ─────────────────────────────────────────────────────────────
add_action('wp_footer', function () {
    if (!function_exists('is_product') || !is_product()) return;
    ?>
<style id="af-sticky-gallery-css">
/* Only where there are genuinely two columns. Stacked on a phone, a sticky
   gallery would pin the image over the details underneath it. */
@media (min-width: 993px) {
  body.single-product .af-sticky-gallery {
    position: sticky !important;
    align-self: flex-start !important;   /* a stretched item is as tall as the
                                            row and can never stick */
    z-index: 2;
  }
}
/* Full size at rest; smaller only once the page has actually scrolled, so
   the picture is never permanently reduced just to make pinning possible.
   The size is eased rather than switched, because a picture that snaps to a
   new size mid-scroll reads as the page glitching. */
@media (min-width: 993px) {
  body.single-product .af-sticky-gallery .af-sg-shrink {
    transition: max-height .35s cubic-bezier(.22,.61,.36,1) !important;
    max-height: none;
  }
  body.single-product .af-sticky-gallery.af-sg-compact .af-sg-shrink {
    max-height: var(--af-sg-max, none) !important;
    height: auto !important;
    width: auto !important;
    max-width: 100% !important;
    object-fit: contain !important;
    margin-left: auto !important;
    margin-right: auto !important;
  }
}
@media (prefers-reduced-motion: reduce) {
  /* Sticky is not an animation, but it does mean the page moves in two
     speeds. Anyone who has asked for less of that gets the plain layout. */
  body.single-product .af-sticky-gallery { position: static !important; }
  /* …and with nothing pinned, shrinking the picture buys nothing at all —
     it would just be a smaller picture for no reason. */
  body.single-product .af-sticky-gallery .af-sg-shrink,
  body.single-product .af-sticky-gallery.af-sg-compact .af-sg-shrink {
    max-height: none !important;
    transition: none !important;
  }
}
</style>
<script id="af-sticky-gallery" src="<?php echo esc_url(af_static_url('sticky-gallery.js')); ?>" data-no-optimize="1"></script>
    <?php
}, 97);

// ─────────────────────────────────────────────────────────────
// The offers ticker: a single line of promises that scrolls under the header.
//
// Modelled on the reference recording, with one deliberate difference. The
// recording is a competitor's bar and its claims are theirs — "Free Shipping
// Across India", "Trusted by 1000+ Happy Customers", "Extra 5% OFF on Your
// First Order". Repeating those here would put statements on this shop that
// are either about another country or simply not established, and this file
// already carries the scar from that: af_shipping_copy() exists because the
// site once promised "Free Shipping across the USA" while its own popup
// listed four states. So the shipping line is taken from that one source
// rather than written again, and every other line is something the site
// already tells customers on its own product pages.
//
// The list is filterable. Anything the owner can stand behind can go in it,
// and nothing here has to be argued with to change it.
// ─────────────────────────────────────────────────────────────
function af_ticker_items() {
    $ship = function_exists('af_shipping_copy') ? af_shipping_copy() : array('label' => 'Shipping throughout the USA');
    return array_values( array_filter( (array) apply_filters('af_ticker_items', array(
        '🚚 ' . $ship['label'],
        '🖼️ Gallery-wrapped and ready to hang',
        '💎 Fade-resistant archival inks on museum-quality canvas',
        '✓ Inclusive of all taxes',
        '📦 Free secure packaging',
        '📐 Custom sizes available',
    )) ) );
}

// The offers ticker is a shopfront thing: it talks to customers about framing
// and shipping. On the staff tool pages it is noise running through work, so
// it is skipped there — only there. Everywhere a customer can go, it still
// runs exactly as before.
function af_ticker_is_staff_page() {
    if ( ! function_exists( 'is_page' ) ) { return false; }
    return is_page( apply_filters( 'af_ticker_hidden_pages', array(
        'admin-console', 'admin',
        'inventory-management', 'inventory',
        'activity-log', 'user-activity-log',
    ) ) );
}

/**
 * Where the ticker runs: the HOME PAGE, and nowhere else.
 *
 * It used to run on every page a customer could reach. On the home page it is
 * a welcome — six things the studio promises, moving past once. On a category
 * page it lands between the section's own description and the products, a
 * black band across the middle of the page saying things that have nothing to
 * do with what the shopper came to look at. The owner asked for it back on the
 * home page only, and that is the right place for it.
 *
 * Filterable, so widening it again later is one line and not an edit here.
 */
function af_ticker_should_run() {
    $ok = function_exists('is_front_page') ? (bool) is_front_page() : false;
    if ($ok && af_ticker_is_staff_page()) $ok = false;
    return (bool) apply_filters('af_ticker_should_run', $ok);
}

add_action('wp_footer', function () {
    if (is_admin()) return;
    if (!af_ticker_should_run()) return;
    $items = af_ticker_items();
    if (!$items) return;
    $run = '';
    foreach ($items as $it) {
        $run .= '<span class="af-tk-item">' . esc_html($it) . '</span><span class="af-tk-dot" aria-hidden="true">•</span>';
    }
    ?>
<style id="af-ticker-css">
.af-ticker{
  width:100%;
  overflow:hidden;
  background:#161616;
  border-top:1px solid rgba(255,255,255,.06);
  border-bottom:1px solid rgba(255,255,255,.06);
  padding:9px 0;
  position:relative;
  z-index:5;
}
/* The same seam rule as the video row: spacing belongs to the ITEM as a
   margin, never to the track as a flex gap. With gap, a run of n items is
   (n × item) + (n−1 × gap), so sliding by half the track lands half a gap
   short of where the second copy starts and the line twitches once a loop. */
.af-ticker-track{
  display:flex;
  flex-wrap:nowrap;
  width:max-content;
  will-change:transform;
  animation:af-tk-run var(--af-tk-dur,42s) linear infinite;
}
@keyframes af-tk-run{
  from{ transform:translate3d(0,0,0); }
  to  { transform:translate3d(-50%,0,0); }
}
.af-ticker:hover .af-ticker-track{ animation-play-state:paused; }
.af-tk-item,.af-tk-dot{
  flex:0 0 auto;
  white-space:nowrap;
  font-size:13px;
  line-height:1.2;
  color:#e8e2d4;
  letter-spacing:.01em;
}
.af-tk-item{ margin-right:26px; font-weight:600; }
.af-tk-dot { margin-right:26px; color:#c9a84c; }
@media (max-width:768px){
  .af-tk-item,.af-tk-dot{ font-size:11.5px; }
  .af-tk-item,.af-tk-dot{ margin-right:16px; }
  .af-ticker{ padding:7px 0; }
}
/* A line of text sliding sideways is exactly what "reduce motion" is about:
   hold it still and let it be scrolled by hand instead. */
@media (prefers-reduced-motion: reduce){
  .af-ticker-track{ animation:none; }
  .af-ticker{ overflow-x:auto; }
}
</style>

<div class="af-ticker" id="afTicker" role="complementary" aria-label="Store offers">
  <div class="af-ticker-track" id="afTickerTrack">
    <?php echo $run; ?>
    <?php /* The second run exists only to make the loop seamless — it must not
             be read out twice. */ ?>
    <span aria-hidden="true" style="display:contents"><?php echo $run; ?></span>
  </div>
</div>

<script id="af-ticker-js">
(function(){
  var bar = document.getElementById('afTicker');
  var track = document.getElementById('afTickerTrack');
  if (!bar || !track) return;

  // Sit it directly under the header, above whatever the page opens with.
  // The header is Elementor's, not ours, so it is found on the page rather
  // than assumed: the last header-ish element that actually sits at the top.
  function place(){
    if (bar.dataset.placed) return;
    var best = null;
    var cands = document.querySelectorAll(
      '.elementor-location-header, header.site-header, header#masthead, header[role="banner"], header'
    );
    for (var i = 0; i < cands.length; i++) {
      var h = cands[i];
      if (h.contains(bar)) continue;
      var r = h.getBoundingClientRect();
      if (r.height < 40) continue;                 // a stray inner <header>
      if (r.top > 400) continue;                   // not the page's own header
      if (!best || r.bottom > best.getBoundingClientRect().bottom) best = h;
    }
    if (!best) return;
    best.insertAdjacentElement('afterend', bar);
    bar.dataset.placed = '1';
  }

  // Speed, not duration: the run gets longer as items are added, so tying the
  // duration to the width keeps the pace the same however much is in it.
  function pace(){
    var w = track.scrollWidth / 2;               // one copy
    if (!w) return;
    var pxPerSec = 60;
    track.style.setProperty('--af-tk-dur', Math.max(18, Math.round(w / pxPerSec)) + 's');
  }

  // ── Make sure the bar occupies its own space ───────────────────────
  // Being placed after the header is not the same as having room. Two ways
  // this page takes the room back: the header can be taken out of normal flow
  // (a transparent header laid over the hero is a common build), which leaves
  // the bar out of flow with it and floating over the artwork; and the section
  // below can be pulled up under it with a negative margin, which a slider
  // does routinely.
  //
  // Rather than guess which, measure the result and push whatever follows down
  // by however much is actually covered.
  function outOfFlow(){
    for (var n = bar.parentElement; n && n !== document.body; n = n.parentElement) {
      var pos = window.getComputedStyle(n).position;
      if (pos === 'fixed' || pos === 'absolute') return true;
    }
    return false;
  }

  function room(){
    var next = bar.nextElementSibling;
    // Clear any room made earlier before measuring, or the measurement is of
    // the gap we ourselves opened and the bar keeps pushing the page down.
    if (next && next.dataset.afTkRoom) {
      next.style.removeProperty('margin-top');
      delete next.dataset.afTkRoom;
    }
    if (document.body.dataset.afTkRoom) {
      document.body.style.removeProperty('padding-top');
      delete document.body.dataset.afTkRoom;
    }

    var b = bar.getBoundingClientRect();
    if (!b.height) return;

    // Out of flow: the bar takes no space at all, so the page needs the whole
    // of its height back at the top.
    if (outOfFlow()) {
      document.body.style.setProperty('padding-top', Math.ceil(b.height) + 'px', 'important');
      document.body.dataset.afTkRoom = '1';
      return;
    }
    // In flow, but something below is reaching up under it.
    if (!next) return;
    var overlap = b.bottom - next.getBoundingClientRect().top;
    if (overlap > 1) {
      // ADD to whatever margin the theme already set, never replace it. The
      // section is usually reaching up because of a negative margin of its
      // own, so writing the overlap straight in throws that value away and
      // opens a gap exactly as big as the overlap used to be — the same
      // mistake in the other direction.
      var cur = parseFloat(window.getComputedStyle(next).marginTop) || 0;
      next.style.setProperty('margin-top', Math.ceil(cur + overlap) + 'px', 'important');
      next.dataset.afTkRoom = '1';
    }
  }

  function go(){ place(); pace(); room(); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', go);
  else go();
  window.addEventListener('load', go);
  window.addEventListener('resize', function(){ pace(); room(); });
  try { if (document.fonts && document.fonts.ready) document.fonts.ready.then(pace); } catch (e) {}
  [300, 900, 2000].forEach(function(d){ setTimeout(go, d); });
})();
</script>
    <?php
}, 96);

// ─────────────────────────────────────────────────────────────
// (Removed) Product page: the translateY version of the sticky gallery.
//
// It followed the scroll by writing g.style.transform on every scroll frame.
// Scroll paints first and the correction lands a frame later, which the eye
// reads as the column JUMPING — the fault the v2 block above was written to
// cure. v2 landed but this one was never taken out, and because it is later
// in the document its transform ran last and won, so the jump survived the
// fix. Deleted rather than disabled: two implementations of one behaviour on
// one element is how this page got here.
// ─────────────────────────────────────────────────────────────


// ─────────────────────────────────────────────────────────────
// Icons for the My Account rows.
//
// The list reads as a wall of words — Dashboard, Orders, Track Order,
// Downloads, Account details, Help, Log out — and a customer scanning it has
// nothing to aim at. One small mark per row makes each one findable at a
// glance without changing a single label.
//
// The rows live in two different places: the My Account page nav (WooCommerce
// markup, one class per endpoint) and the header account dropdown (theme
// markup, no useful classes at all). The endpoint classes carry the page nav;
// the dropdown rows are tagged client-side from their own label text.
//
// The icons are inline SVG masks, not images: the mark is painted in
// currentColor, so it takes the row's own colour on the dark dropdown and on
// the light page alike, and follows hover states for free. Nothing is fetched.
// ─────────────────────────────────────────────────────────────
function af_account_icons() {
    // 24x24, stroked, no fill — the shapes read at 16px, which is the size
    // they are actually drawn at next to the text.
    return array(
        'dashboard' => '<rect x="3" y="3" width="7.5" height="7.5" rx="1.6"/><rect x="13.5" y="3" width="7.5" height="7.5" rx="1.6"/><rect x="3" y="13.5" width="7.5" height="7.5" rx="1.6"/><rect x="13.5" y="13.5" width="7.5" height="7.5" rx="1.6"/>',
        'orders' => '<path d="M20.5 7.8v8.4a1.8 1.8 0 0 1-1 1.6l-6.6 3a2 2 0 0 1-1.8 0l-6.6-3a1.8 1.8 0 0 1-1-1.6V7.8"/><path d="M3.8 7.2 12 3.3l8.2 3.9L12 11.1z"/><path d="M12 11.1V21"/>',
        'track-order' => '<path d="M3 6.5h10.5v9H3z"/><path d="M13.5 9.5H17l3.5 3.2v2.8h-7z"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>',
        'downloads' => '<path d="M12 3v11.5"/><path d="m7.5 10.5 4.5 4.5 4.5-4.5"/><path d="M4 20.2h16"/>',
        'edit-account' => '<circle cx="12" cy="8" r="4"/><path d="M4.2 20.8c0-3.9 3.5-5.9 7.8-5.9s7.8 2 7.8 5.9"/>',
        'help' => '<circle cx="12" cy="12" r="9"/><path d="M9.4 9.3a2.7 2.7 0 1 1 3.5 2.6c-.7.3-1 .9-1 1.6v.4"/><path d="M11.95 17.2h.1"/>',
        'logout' => '<path d="M14.5 3.8H18a2 2 0 0 1 2 2v12.4a2 2 0 0 1-2 2h-3.5"/><path d="M9.5 8 5.5 12l4 4"/><path d="M5.5 12h9"/>',
        'messages' => '<path d="M20 4.8H4a1.2 1.2 0 0 0-1.2 1.2v9.6A1.2 1.2 0 0 0 4 16.8h3v4l5-4h8a1.2 1.2 0 0 0 1.2-1.2V6A1.2 1.2 0 0 0 20 4.8z"/>',
        'returns' => '<path d="M9 4.2 4.2 9 9 13.8"/><path d="M4.2 9h9.3a6.3 6.3 0 1 1 0 12.6H8.5"/>',
        'saved-previews' => '<rect x="3" y="4.5" width="18" height="15" rx="2"/><circle cx="8.6" cy="10" r="1.8"/><path d="m3.6 17.4 5-4.8 3.9 3.8 2.9-2 5 4.4"/>',
        'edit-address' => '<path d="M12 21.2s6.8-5.4 6.8-10.6a6.8 6.8 0 1 0-13.6 0c0 5.2 6.8 10.6 6.8 10.6z"/><circle cx="12" cy="10.2" r="2.6"/>',
        'payment-methods' => '<rect x="2.8" y="5.2" width="18.4" height="13.6" rx="2"/><path d="M2.8 10h18.4"/>',
        'wishlist' => '<path d="M12 20.4 4.9 13.5a4.6 4.6 0 0 1 6.4-6.6l.7.7.7-.7a4.6 4.6 0 1 1 6.4 6.6z"/>',
    );
}

// Which selectors each icon answers to. The page nav gives us an endpoint
// class; the dropdown gives us only the tag the script below writes on.
function af_account_icon_selectors( $key ) {
    $endpoints = array(
        'dashboard'       => array( 'dashboard' ),
        'orders'          => array( 'orders' ),
        'track-order'     => array( 'af-track-order' ),
        'downloads'       => array( 'downloads' ),
        'edit-account'    => array( 'edit-account' ),
        'help'            => array( 'af-help' ),
        'logout'          => array( 'customer-logout' ),
        'messages'        => array( 'messages' ),
        'returns'         => array( 'returns' ),
        'saved-previews'  => array( 'saved-previews' ),
        'edit-address'    => array( 'edit-address', 'edit-addresses' ),
        'payment-methods' => array( 'payment-methods' ),
        'wishlist'        => array( 'wishlist' ),
    );
    $sel = array( 'a[data-af-acc-icon="' . $key . '"]' );
    foreach ( ( isset( $endpoints[ $key ] ) ? $endpoints[ $key ] : array() ) as $ep ) {
        $sel[] = '.woocommerce-MyAccount-navigation li.woocommerce-MyAccount-navigation-link--' . $ep . ' > a';
        $sel[] = '.woocommerce-MyAccount-navigation li.woocommerce-MyAccount-navigation-link--' . $ep . ' > a > span';
    }
    return $sel;
}

add_action( 'wp_footer', function () {
	if ( is_admin() ) {
		return;
	}
	// The icons mark the account dropdown and the My Account navigation, which
	// only a signed-in visitor (or the account page itself) has. The 24 KB of
	// inline SVG styles used to be printed for every guest page view as well
	// (16 Oct page-weight profile).
	if ( ! is_user_logged_in() && ! ( function_exists( 'is_account_page' ) && is_account_page() ) ) {
		return;
	}
	$icons = af_account_icons();
	$all   = array();
	$rules = '';
	foreach ( $icons as $key => $paths ) {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" '
		     . 'stroke="#000" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">'
		     . $paths . '</svg>';
		$sel = af_account_icon_selectors( $key );
		$all = array_merge( $all, $sel );
		$rules .= implode( ",\n", $sel ) . " {\n  --af-acc-icon: url(\"data:image/svg+xml;charset=utf-8,"
		        . rawurlencode( $svg ) . "\");\n}\n";
	}
	// Every selector names the element that carries the mark, so a variant is
	// built by appending to it rather than by rewriting the middle of it.
	$with = function ( $suffix ) use ( $all ) {
		$out = array();
		foreach ( $all as $s ) { $out[] = $s . $suffix; }
		return implode( ",\n", $out );
	};
	// One number moves the words and the mark together, because the mark is
	// sized in em: at 1.1 the row is 10% larger and the icon grows with it,
	// keeping their proportions exactly as they were.
	$scale = (float) apply_filters( 'af_account_row_scale', 1.12 );
	$icon  = (float) apply_filters( 'af_account_icon_scale', 1.15 );
	?>
<style id="af-account-icons-css">
<?php echo $rules; // built above from the icon table ?>
<?php echo $with( '' ); ?> {
  font-size: <?php echo esc_html( number_format( $scale, 3, '.', '' ) ); ?>em;
}
<?php echo $with( '::before' ); ?> {
  content: "" !important;
  display: inline-block !important;
  width: <?php echo esc_html( number_format( $icon, 3, '.', '' ) ); ?>em !important;
  height: <?php echo esc_html( number_format( $icon, 3, '.', '' ) ); ?>em !important;
  margin-right: .55em !important;
  vertical-align: -.18em !important;
  background-color: currentColor !important;
  opacity: .8;
  -webkit-mask-image: var(--af-acc-icon);
          mask-image: var(--af-acc-icon);
  -webkit-mask-repeat: no-repeat;
          mask-repeat: no-repeat;
  -webkit-mask-position: center;
          mask-position: center;
  -webkit-mask-size: contain;
          mask-size: contain;
}
/* The mark belongs to the row, so it brightens with it rather than staying
   a flat grey while the words light up. */
<?php echo $with( ':hover::before' ); ?> {
  opacity: 1;
}
/* Without mask support there is no way to tint the shape to the row's colour,
   and a black mark on a dark dropdown would be worse than none. */
@supports not ((-webkit-mask-image: none) or (mask-image: none)) {
  <?php echo $with( '::before' ); ?> { display: none !important; }
}
</style>
<script id="af-account-icons">
(function(){
  // The rows are identified by the words the customer reads, because the
  // dropdown is the theme's own markup and carries nothing else to match on.
  // Matching is exact on the trimmed label — a substring test would tag
  // "Order history" as the logout row on any site that renames things.
  var MAP = {
    'dashboard': 'dashboard',
    'orders': 'orders', 'my orders': 'orders',
    'track order': 'track-order', 'track your order': 'track-order',
    'downloads': 'downloads',
    'account details': 'edit-account', 'account detail': 'edit-account',
    'help': 'help', 'help & support': 'help', 'help and support': 'help',
    'log out': 'logout', 'logout': 'logout', 'sign out': 'logout',
    'messages': 'messages',
    'returns': 'returns',
    'saved previews': 'saved-previews',
    'addresses': 'edit-address', 'address': 'edit-address',
    'payment methods': 'payment-methods',
    'wishlist': 'wishlist'
  };

  // The first version looked for ul > li > a. That is the WooCommerce page
  // nav's shape, not the header dropdown's — the dropdown showed no icons at
  // all (owner screenshot, 2026-08-20). The Track Order and Help rows this
  // theme does show are injected by a script that walks ANCHORS and ignores
  // the surrounding structure, which is the proof that anchors are the thing
  // to match. So: walk anchors, and let the markup around them be whatever it
  // is. The mark is drawn on the anchor itself.
  function inAccountMenu(a){
    // "Log out" is the giveaway no other menu on the site carries — but only
    // when it is read from the MENU, not from the page. A first attempt walked
    // plain ancestors and reached <body>, where the words exist somewhere, so
    // an unrelated menu with an "Orders" link got tagged too. So: climb to the
    // nearest thing that is actually a menu container and test only that.
    for (var n = a.parentElement, i = 0; n && n !== document.body && i < 8; n = n.parentElement, i++) {
      var tag = n.tagName;
      var cls = (typeof n.className === 'string' ? n.className : '').toLowerCase();
      var isMenu = tag === 'UL' || tag === 'OL' || tag === 'NAV'
                || /(^|[\s_-])(menu|dropdown|submenu|nav|account)([\s_-]|$)/.test(cls);
      if (!isMenu) continue;
      var t = (n.textContent || '').toLowerCase();
      if (t.indexOf('log out') !== -1 || t.indexOf('logout') !== -1
          || t.indexOf('sign out') !== -1 || t.indexOf('account details') !== -1) {
        return true;
      }
    }
    return false;
  }

  function tag(){
    var anchors = document.querySelectorAll('a[href]:not([data-af-acc-icon])');
    for (var i = 0; i < anchors.length; i++) {
      var a = anchors[i];
      var label = (a.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
      var key = MAP[label];
      if (!key) continue;
      if (!inAccountMenu(a)) continue;
      a.setAttribute('data-af-acc-icon', key);
    }
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', tag);
  else tag();
  // The dropdown is often built (or cloned into) after load, and the Track
  // Order / Help rows are added by our own later script.
  [400, 1200, 2500].forEach(function(d){ setTimeout(tag, d); });
  document.addEventListener('click', function(){ setTimeout(tag, 60); }, true);
  document.addEventListener('mouseover', function(e){
    // Menus that build themselves on hover would otherwise never be tagged.
    if (e.target && e.target.closest && e.target.closest('a,li,nav')) setTimeout(tag, 60);
  }, true);
})();
</script>
	<?php
}, 95 );

// ─────────────────────────────────────────────────────────────
// The Edit screen behind Inventory Management's "Edit" button.
//
// That button used to open wp-admin's product editor in a new tab: a large,
// unfamiliar screen with dozens of fields, ninety per cent of which nobody
// managing stock will ever touch. This is the small version — the handful of
// things this shop actually edits, in the console's own styling, on the page
// they were already on.
//
// It lives at /inventory-management/?af_edit=<id> rather than a page of its
// own on purpose: the slug already exists, already has the right permission
// gate, and "Back to inventory" is then genuinely back rather than sideways.
// The escape hatch to the full WordPress editor is still there for the rare
// field this screen deliberately leaves out.
//
// Who may change what follows the same split as the rest of the console:
// anyone who can open Inventory may edit stock, because that is the job the
// page exists for; the rest — title, prices, art code, published state — is
// administrators only, and the fields are simply not rendered for others.
// ─────────────────────────────────────────────────────────────

function af_pe_can_edit_all() {
	return is_user_logged_in() && current_user_can( 'manage_options' );
}

function af_pe_url( $pid ) {
	return add_query_arg( 'af_edit', (int) $pid, af_inv_url() );
}

add_action( 'wp_ajax_af_product_edit_save', function () {
	if ( ! function_exists( 'af_inv_can_access' ) || ! af_inv_can_access() ) {
		wp_send_json_error( array( 'message' => 'You do not have permission to edit products.' ), 403 );
	}
	check_ajax_referer( 'af_product_edit', 'nonce' );

	$pid     = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
	$product = $pid ? wc_get_product( $pid ) : null;
	if ( ! $product ) {
		wp_send_json_error( array( 'message' => 'That product no longer exists.' ), 404 );
	}

	$full    = af_pe_can_edit_all();
	$changed = array();
	$notes   = array();

	// ── Stock: the part anyone with console access may change ───────────────
	if ( isset( $_POST['manage_stock'] ) ) {
		$manage = $_POST['manage_stock'] === '1';
		$product->set_manage_stock( $manage );
		if ( $manage ) {
			$qty = isset( $_POST['stock_qty'] ) ? (int) $_POST['stock_qty'] : 0;
			$product->set_stock_quantity( max( 0, $qty ) );
			// Let the count decide the status while a count is being kept —
			// a tracked product showing "in stock" at zero is how oversells
			// happen.
			$product->set_stock_status( $qty > 0 ? 'instock' : 'outofstock' );
			$changed[] = 'stock count';
		} else {
			$product->set_stock_quantity( null );
			$status = isset( $_POST['stock_status'] ) ? sanitize_text_field( wp_unslash( $_POST['stock_status'] ) ) : 'instock';
			if ( ! in_array( $status, array( 'instock', 'outofstock', 'onbackorder' ), true ) ) { $status = 'instock'; }
			$product->set_stock_status( $status );
			$changed[] = 'stock status';
		}
	}

	// ── Everything else: administrators only ────────────────────────────────
	if ( $full ) {
		if ( isset( $_POST['title'] ) ) {
			$title = sanitize_text_field( wp_unslash( $_POST['title'] ) );
			if ( $title === '' ) {
				wp_send_json_error( array( 'message' => 'A product needs a name.' ), 400 );
			}
			if ( $title !== $product->get_name() ) {
				$product->set_name( $title );
				$changed[] = 'name';
			}
		}

		if ( isset( $_POST['sku'] ) ) {
			$sku = trim( sanitize_text_field( wp_unslash( $_POST['sku'] ) ) );
			if ( $sku !== (string) $product->get_sku() ) {
				$holder = $sku !== '' ? (int) wc_get_product_id_by_sku( $sku ) : 0;
				if ( $holder && $holder !== $pid ) {
					wp_send_json_error( array(
						'message' => 'SKU "' . $sku . '" is already used by ' . get_the_title( $holder )
						           . ' (#' . $holder . '). Two products cannot share one.',
					), 409 );
				}
				$product->set_sku( $sku );
				$changed[] = 'SKU';
			}
		}

		foreach ( array( 'regular_price', 'sale_price' ) as $field ) {
			if ( ! isset( $_POST[ $field ] ) ) { continue; }
			$raw = trim( (string) wp_unslash( $_POST[ $field ] ) );
			if ( $raw !== '' && ! is_numeric( $raw ) ) {
				wp_send_json_error( array( 'message' => str_replace( '_', ' ', $field ) . ' must be a number.' ), 400 );
			}
			$product->{ 'set_' . $field }( $raw === '' ? '' : wc_format_decimal( $raw ) );
		}
		$reg  = (float) $product->get_regular_price();
		$sale = $product->get_sale_price();
		if ( $sale !== '' && (float) $sale > 0 && $reg > 0 && (float) $sale >= $reg ) {
			wp_send_json_error( array(
				'message' => 'The sale price must be below the regular price, or a customer sees a discount that is not one.',
			), 400 );
		}
		$changed[] = 'price';

		if ( isset( $_POST['status'] ) ) {
			$status = wp_unslash( $_POST['status'] ) === 'publish' ? 'publish' : 'draft';
			if ( $status !== $product->get_status() ) {
				$product->set_status( $status );
				$changed[] = $status === 'publish' ? 'published' : 'moved to draft';
			}
		}
	}

	$product->save();

	if ( $full && isset( $_POST['art_code'] ) ) {
		$code = preg_replace( '/\s+/', ' ', trim( sanitize_text_field( wp_unslash( $_POST['art_code'] ) ) ) );
		$was  = (string) get_post_meta( $pid, '_taf_art_code', true );
		if ( $code !== $was ) {
			if ( $code === '' ) {
				delete_post_meta( $pid, '_taf_art_code' );
			} else {
				update_post_meta( $pid, '_taf_art_code', $code );
			}
			$changed[] = 'art code';
			// The SKU pass takes its cue from this marker; clearing it lets the
			// next deploy reconsider this product with its new code.
			delete_post_meta( $pid, '_af_sku_artcode' );
			$notes[] = 'The SKU will follow the new art code on the next deploy, if that code is not already in use elsewhere.';
		}
	}

	if ( function_exists( 'wc_delete_product_transients' ) ) { wc_delete_product_transients( $pid ); }

	$fresh = wc_get_product( $pid );
	wp_send_json_success( array(
		'message' => $changed ? 'Saved — ' . implode( ', ', array_unique( $changed ) ) . '.' : 'Nothing had changed.',
		'notes'   => $notes,
		'product' => array(
			'title'  => html_entity_decode( wp_strip_all_tags( $fresh->get_name() ) ),
			'sku'    => (string) $fresh->get_sku(),
			'price'  => (string) $fresh->get_price(),
			'qty'    => $fresh->get_manage_stock() ? (int) $fresh->get_stock_quantity() : null,
			'status' => $fresh->get_stock_status(),
			'state'  => $fresh->get_status(),
		),
	) );
} );

// The screen. Rendered in place of the inventory table when ?af_edit=<id> is
// present, so the permission gate, the header and the styling are already the
// ones the console uses.
add_action( 'template_redirect', function () {
	if ( ! function_exists( 'is_page' ) || ! is_page( array( 'inventory-management', 'inventory' ) ) ) { return; }
	if ( ! isset( $_GET['af_edit'] ) ) { return; }
	if ( ! function_exists( 'af_inv_can_access' ) || ! af_inv_can_access() ) {
		wp_die( 'You do not have permission to view this page.', 'Access Denied',
			array( 'response' => 403, 'back_link' => true ) );
	}

	$pid     = (int) $_GET['af_edit'];
	$product = $pid ? wc_get_product( $pid ) : null;
	if ( ! $product ) {
		wp_safe_redirect( af_inv_url() );
		exit;
	}

	$full   = af_pe_can_edit_all();
	$code   = (string) get_post_meta( $pid, '_taf_art_code', true );
	$img    = get_the_post_thumbnail_url( $pid, 'medium' ) ?: wc_placeholder_img_src( 'medium' );
	$sym    = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$';
	$manage = (bool) $product->get_manage_stock();

	get_header();
	?>
<div class="af-inv-wrap af-pe-wrap">
  <a class="af-pe-back" href="<?php echo esc_url( af_inv_url() ); ?>">&larr; Back to inventory</a>

  <div class="af-pe-head">
    <div>
      <span class="af-inv-badge">ADMIN ONLY</span>
      <h1>Edit product</h1>
      <p class="af-inv-sub">
        <?php echo $full
          ? 'Change what a customer sees, then save. Anything not here is in the full WordPress editor.'
          : 'You can set the stock for this product. Names, prices and codes are administrators only.'; ?>
      </p>
    </div>
    <div class="af-pe-headact">
      <a class="af-inv-back" href="<?php echo esc_url( get_permalink( $pid ) ); ?>" target="_blank" rel="noopener">View on site</a>
      <a class="af-inv-back" href="<?php echo esc_url( get_edit_post_link( $pid, 'raw' ) ); ?>" target="_blank" rel="noopener">Full editor</a>
    </div>
  </div>

  <form class="af-pe-form" id="af-pe-form" data-id="<?php echo (int) $pid; ?>">
    <div class="af-pe-grid">
      <div class="af-pe-media">
        <img src="<?php echo esc_url( $img ); ?>" alt="">
        <span class="af-pe-pid">#<?php echo (int) $pid; ?></span>
      </div>

      <div class="af-pe-fields">
        <?php if ( $full ) : ?>
          <label class="af-pe-field">
            <span class="af-pe-label">Product name</span>
            <input type="text" name="title" value="<?php echo esc_attr( html_entity_decode( wp_strip_all_tags( $product->get_name() ) ) ); ?>" maxlength="200">
          </label>

          <div class="af-pe-row">
            <label class="af-pe-field">
              <span class="af-pe-label">Art code</span>
              <input type="text" name="art_code" value="<?php echo esc_attr( $code ); ?>" placeholder="RK 01">
              <span class="af-pe-hint">The code from the collection book.</span>
            </label>
            <label class="af-pe-field">
              <span class="af-pe-label">SKU</span>
              <input type="text" name="sku" value="<?php echo esc_attr( (string) $product->get_sku() ); ?>">
              <span class="af-pe-hint">Must be unique across the shop.</span>
            </label>
          </div>

          <div class="af-pe-row">
            <label class="af-pe-field">
              <span class="af-pe-label">Regular price (<?php echo esc_html( $sym ); ?>)</span>
              <input type="text" inputmode="decimal" name="regular_price" value="<?php echo esc_attr( (string) $product->get_regular_price() ); ?>">
            </label>
            <label class="af-pe-field">
              <span class="af-pe-label">Sale price (<?php echo esc_html( $sym ); ?>)</span>
              <input type="text" inputmode="decimal" name="sale_price" value="<?php echo esc_attr( (string) $product->get_sale_price() ); ?>">
              <span class="af-pe-hint">Leave empty for no sale.</span>
            </label>
          </div>
        <?php else : ?>
          <div class="af-pe-readonly">
            <span class="af-pe-label">Product</span>
            <strong><?php echo esc_html( html_entity_decode( wp_strip_all_tags( $product->get_name() ) ) ); ?></strong>
            <span class="af-pe-hint">SKU <?php echo esc_html( $product->get_sku() ?: '—' ); ?></span>
          </div>
        <?php endif; ?>

        <fieldset class="af-pe-stock">
          <legend class="af-pe-label">Stock</legend>
          <label class="af-pe-check">
            <input type="checkbox" name="manage_stock" id="af-pe-manage" <?php checked( $manage ); ?>>
            <span>Keep a count for this product</span>
          </label>

          <div class="af-pe-row af-pe-stockrow">
            <label class="af-pe-field" id="af-pe-qtyfield" <?php echo $manage ? '' : 'hidden'; ?>>
              <span class="af-pe-label">Units in stock</span>
              <input type="number" min="0" step="1" name="stock_qty"
                     value="<?php echo esc_attr( $manage ? (int) $product->get_stock_quantity() : 0 ); ?>">
              <span class="af-pe-hint">Zero marks it out of stock.</span>
            </label>

            <label class="af-pe-field" id="af-pe-statusfield" <?php echo $manage ? 'hidden' : ''; ?>>
              <span class="af-pe-label">Availability</span>
              <select name="stock_status">
                <?php foreach ( array( 'instock' => 'In stock', 'outofstock' => 'Out of stock', 'onbackorder' => 'On backorder' ) as $v => $l ) : ?>
                  <option value="<?php echo esc_attr( $v ); ?>" <?php selected( $product->get_stock_status(), $v ); ?>><?php echo esc_html( $l ); ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>
        </fieldset>

        <?php if ( $full ) : ?>
          <label class="af-pe-check af-pe-publish">
            <input type="checkbox" name="status" <?php checked( $product->get_status(), 'publish' ); ?>>
            <span>Visible in the shop</span>
          </label>
        <?php endif; ?>

        <div class="af-pe-actions">
          <button type="submit" class="af-pe-save">Save changes</button>
          <a class="af-pe-cancel" href="<?php echo esc_url( af_inv_url() ); ?>">Cancel</a>
          <span class="af-pe-msg" id="af-pe-msg" role="status" aria-live="polite"></span>
        </div>
      </div>
    </div>
  </form>
</div>

<style>
.af-pe-wrap{max-width:1000px;}
.af-pe-back{display:inline-block;margin-bottom:14px;color:#6b6250;text-decoration:none;font-size:13px;font-weight:700;}
.af-pe-back:hover{color:#a8801f;}
.af-pe-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;flex-wrap:wrap;margin-bottom:20px;}
.af-pe-headact{display:flex;gap:10px;flex-wrap:wrap;}
.af-pe-form{background:#fffdf8;border:1px solid #efe6d2;border-radius:14px;padding:22px;
  box-shadow:0 4px 18px rgba(70,54,26,.07);}
.af-pe-grid{display:grid;grid-template-columns:220px 1fr;gap:26px;align-items:start;}
.af-pe-media{position:relative;}
.af-pe-media img{width:100%;border-radius:11px;border:1px solid #ece4cf;display:block;background:#f7f0df;}
.af-pe-pid{position:absolute;left:10px;bottom:10px;background:rgba(26,26,26,.82);color:#fff;font-size:11px;
  font-weight:800;letter-spacing:.03em;border-radius:6px;padding:3px 8px;}
.af-pe-fields{min-width:0;display:flex;flex-direction:column;gap:16px;}
.af-pe-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.af-pe-field{display:flex;flex-direction:column;gap:6px;min-width:0;}
/* The browser's own [hidden] rule is display:none, which a class-based
   display:flex outranks — so hiding a field by attribute alone left it on
   screen. Restate it at higher specificity. */
.af-pe-field[hidden]{display:none;}
.af-pe-label{font-size:11px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;color:#6b6250;}
.af-pe-field input,.af-pe-field select{height:40px;padding:0 11px;border:1.5px solid #e2d9c4;border-radius:9px;
  background:#fff;color:#1a1a1a;font-size:14px;font-weight:600;width:100%;box-sizing:border-box;}
.af-pe-field input:focus,.af-pe-field select:focus{outline:none;border-color:#c9a84c;background:#fffdf8;}
.af-pe-hint{font-size:11.5px;color:#8a8170;}
.af-pe-readonly{display:flex;flex-direction:column;gap:4px;}
.af-pe-stock{border:1px solid #efe6d2;border-radius:11px;padding:14px 16px;margin:0;background:#fffefb;
  display:flex;flex-direction:column;gap:12px;}
.af-pe-stock legend{padding:0 6px;}
.af-pe-check{display:flex;align-items:center;gap:9px;font-size:13.5px;font-weight:600;color:#1a1a1a;cursor:pointer;}
.af-pe-check input{width:17px;height:17px;accent-color:#c9a84c;}
.af-pe-publish{padding-top:2px;}
.af-pe-actions{display:flex;align-items:center;gap:14px;flex-wrap:wrap;padding-top:4px;}
.af-pe-save{height:42px;padding:0 22px;border:none;border-radius:9px;background:#1a1a1a;color:#fff;
  font-size:13.5px;font-weight:800;letter-spacing:.02em;cursor:pointer;}
.af-pe-save:hover{background:#c9a84c;color:#1a1a1a;}
.af-pe-save[disabled]{opacity:.55;cursor:default;}
.af-pe-cancel{color:#6b6250;text-decoration:none;font-size:13px;font-weight:700;}
.af-pe-cancel:hover{color:#a8801f;}
.af-pe-msg{font-size:13px;font-weight:700;}
.af-pe-msg.is-ok{color:#1e8b56;}
.af-pe-msg.is-bad{color:#b4453a;}
.af-pe-note{margin:10px 0 0;font-size:12.5px;color:#8a8170;}
@media(max-width:760px){
  .af-pe-grid{grid-template-columns:1fr;}
  .af-pe-row{grid-template-columns:1fr;}
}
</style>

<script>
(function(){
  var form = document.getElementById('af-pe-form');
  if (!form) return;
  var msg    = document.getElementById('af-pe-msg');
  var manage = document.getElementById('af-pe-manage');
  var qty    = document.getElementById('af-pe-qtyfield');
  var stat   = document.getElementById('af-pe-statusfield');
  var AJAX   = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
  var NONCE  = <?php echo wp_json_encode( wp_create_nonce( 'af_product_edit' ) ); ?>;

  // A count and an availability dropdown are two ways of saying the same
  // thing, so only the one in use is shown.
  function sync(){
    if (!manage) return;
    if (qty)  qty.hidden  = !manage.checked;
    if (stat) stat.hidden =  manage.checked;
  }
  if (manage) manage.addEventListener('change', sync);
  sync();

  function say(text, ok){
    msg.textContent = text;
    msg.className = 'af-pe-msg ' + (ok ? 'is-ok' : 'is-bad');
  }

  form.addEventListener('submit', function(e){
    e.preventDefault();
    var btn = form.querySelector('.af-pe-save');
    btn.disabled = true;
    say('Saving…', true);

    var body = new URLSearchParams();
    body.set('action', 'af_product_edit_save');
    body.set('nonce', NONCE);
    body.set('id', form.dataset.id);
    form.querySelectorAll('input[name], select[name]').forEach(function(el){
      if (el.type === 'checkbox') { body.set(el.name, el.checked ? '1' : '0'); }
      else { body.set(el.name, el.value); }
    });

    fetch(AJAX, { method:'POST', credentials:'same-origin', body: body })
      .then(function(r){ return r.json().catch(function(){ throw new Error('The server did not answer in a way we could read.'); }); })
      .then(function(res){
        if (!res || !res.success) {
          throw new Error((res && res.data && res.data.message) || 'That did not save.');
        }
        say(res.data.message, true);
        if (res.data.notes && res.data.notes.length) {
          var n = document.createElement('p');
          n.className = 'af-pe-note';
          n.textContent = res.data.notes.join(' ');
          form.querySelector('.af-pe-actions').insertAdjacentElement('afterend', n);
        }
      })
      .catch(function(err){ say(err.message, false); })
      .then(function(){ btn.disabled = false; });
  });
})();
</script>
	<?php
	get_footer();
	exit;
}, 0 );

// ─────────────────────────────────────────────────────────────
// Search results: twelve per page.
// The results grid is three across (loop_shop_columns is filtered to 3), and
// Settings > Reading's default of ten ends every page on a ragged 3/3/3/1.
// Twelve divides evenly by three, two and one — the grid's three breakpoints.
//
// This cannot live in search.php: pre_get_posts fires before the template is
// chosen. Priority 21 puts it after inc/search-all.php's own pre_get_posts at
// 20; that module's widening filter rewrites only the WHERE section of the
// statement, so LIMIT and SQL_CALC_FOUND_ROWS are untouched and found_posts
// and max_num_pages stay correct.
// ─────────────────────────────────────────────────────────────
add_action('pre_get_posts', function ($q) {
    if (is_admin() || !$q->is_main_query() || !$q->is_search()) return;
    $q->set('posts_per_page', 12);
}, 21);

// ─────────────────────────────────────────────────────────────
// Elementor's settings object on pages Elementor did not set up itself.
// About, Blog, Try on Wall and Frame the Moment threw "elementorFrontendConfig
// is not defined" (purchase QA smoke pass, 14 Oct): elementor-frontend.js was
// on the page, pulled in as a dependency of the ported add-ons (Header Footer
// Elementor, Essential Addons), but Elementor never ran its own
// enqueue_scripts() there, which is what prints elementorFrontendConfig
// beside the script. The only data printed with it was EAELImageMaskingConfig,
// so the script started without its settings and Elementor's widgets did not
// initialise.
//
// Just before footer scripts print, when elementor-frontend is queued and its
// settings are not, let Elementor do what it does on its own pages. A page
// where Elementor already printed them is left alone.
// ─────────────────────────────────────────────────────────────
add_action('wp_print_footer_scripts', function () {
    if (!class_exists('\Elementor\Plugin') || empty(\Elementor\Plugin::$instance->frontend)
        || !is_callable(array(\Elementor\Plugin::$instance->frontend, 'enqueue_scripts'))) return;
    $s = wp_scripts();
    if (!wp_script_is('elementor-frontend', 'enqueued') || wp_script_is('elementor-frontend', 'done')) return;
    $data   = (string) $s->get_data('elementor-frontend', 'data');
    $before = $s->get_data('elementor-frontend', 'before');
    $before = is_array($before) ? implode("\n", array_filter($before, 'is_string')) : (string) $before;
    if (strpos($data . $before, 'elementorFrontendConfig') !== false) return;
    try {
        \Elementor\Plugin::$instance->frontend->enqueue_scripts();
    } catch (\Throwable $e) {
        // leave the page as it was rather than break the footer
    }
}, 1);
