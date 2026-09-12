<?php
/**
 * Keep the pages people actually land on out of the cold.
 *
 * ── The measurement this exists for ────────────────────────────────────────
 *
 * Check Timing, run against the live site on 2026-09-12:
 *
 *     /                        cold  3.83s  3.31s  3.46s      warm  0.11s
 *     /shop/                   cold  2.55s  2.30s  2.28s      warm  0.06s
 *     .../lord-shiva/          cold  2.40s  4.76s  2.48s      warm  0.06s
 *     .../swaminarayan/        cold  2.35s  2.68s  2.34s
 *
 * All of it time to FIRST BYTE — the server thinking, not the download. A
 * cached page answers in about a twentieth of a second; the same page,
 * expired, takes two and a half to nearly five seconds to build, and that is
 * on an idle box. Behind a queue of other cold builds it is whatever the
 * queue is: four recordings caught a shopper waiting eleven, twelve and
 * twenty-three seconds on a category page.
 *
 * Nothing about that is category-specific. The homepage is the slowest of the
 * four. It is simply what an uncached page costs here.
 *
 * ── Why warming, and why this is close to free ─────────────────────────────
 *
 * The deploy warms a list once and then stops, so every page drifts back out
 * of the cache on its own TTL and the next visitor pays full price. Warming
 * on a timer fixes that, and it costs almost nothing — because a request for
 * a page that is STILL CACHED is served by LiteSpeed without PHP running at
 * all. That is the 0.06s column above.
 *
 * So each tick is: a handful of cheap cache hits, and occasionally one real
 * render — the one a shopper would otherwise have been sitting through. The
 * cost is not "N renders per tick", it is "one render per page per TTL",
 * moved off the visitor and onto a background request.
 *
 * ── The shape of it ───────────────────────────────────────────────────────
 *
 * Every five minutes, three URLs, round-robin through the list, so the whole
 * list is covered steadily rather than in a burst that could itself be load.
 * The list is the entrances: the homepage, the shop, and the category pages
 * the homepage's circle row links to — the first click a real shopper makes,
 * and the ones the recordings caught cold.
 *
 * Requests are non-blocking, short-timeout, and carry the browser headers the
 * crawl guard expects, so a warm hits the same cache variant a visitor does.
 * Nothing is written except a rotating cursor.
 *
 * Turn it off with:  update_option('af_warm_enabled', 'no')
 */

if (!defined('ABSPATH')) exit;

/** Warm this many URLs per tick. Small on purpose — see the docblock. */
function af_warm_batch_size() {
    return (int) apply_filters('af_warm_batch_size', 3);
}

function af_warm_enabled() {
    return get_option('af_warm_enabled', 'yes') !== 'no';
}

/**
 * The entrances, in the order they matter.
 *
 * Cached for an hour: building it touches the taxonomy, and the answer only
 * changes when categories do. Bounded so a taxonomy that grows cannot turn
 * the warmer into its own load problem.
 */
function af_warm_urls() {
    $cached = get_transient('af_warm_urls');
    if (is_array($cached) && $cached) return $cached;

    $urls = array(home_url('/'));

    if (function_exists('wc_get_page_id')) {
        $shop = wc_get_page_id('shop');
        if ($shop > 0) {
            $u = get_permalink($shop);
            if ($u) $urls[] = $u;
        }
    }

    // The homepage's circle row is the children of digital-canvas-prints.
    // These are small categories, so they never appear in a "biggest first"
    // list — which is exactly why they were the ones found cold.
    $parent = get_term_by('slug', 'digital-canvas-prints', 'product_cat');
    if ($parent && !is_wp_error($parent)) {
        $kids = get_terms(array(
            'taxonomy'   => 'product_cat',
            'parent'     => (int) $parent->term_id,
            'hide_empty' => true,
            'number'     => 30,
        ));
        if (!is_wp_error($kids)) {
            foreach ($kids as $t) {
                $u = get_term_link($t);
                if ($u && !is_wp_error($u)) $urls[] = $u;
            }
        }
    }

    // Then the biggest categories, for anyone arriving from search.
    $top = get_terms(array(
        'taxonomy'   => 'product_cat',
        'hide_empty' => true,
        'number'     => 12,
        'orderby'    => 'count',
        'order'      => 'DESC',
    ));
    if (!is_wp_error($top)) {
        foreach ($top as $t) {
            $u = get_term_link($t);
            if ($u && !is_wp_error($u)) $urls[] = $u;
        }
    }

    $urls = array_values(array_unique(array_filter($urls)));
    set_transient('af_warm_urls', $urls, HOUR_IN_SECONDS);
    return $urls;
}

/**
 * One tick: the next few URLs, then move the cursor on.
 *
 * Non-blocking, because nothing here needs the answer — the point is that
 * LiteSpeed stored it, not that we read it. A five second ceiling so a tick
 * can never sit on a worker if the host is already struggling.
 */
function af_warm_tick() {
    if (!af_warm_enabled()) return;

    $urls = af_warm_urls();
    if (!$urls) return;

    $size   = max(1, af_warm_batch_size());
    $cursor = (int) get_option('af_warm_cursor', 0);
    $total  = count($urls);

    for ($i = 0; $i < $size && $i < $total; $i++) {
        $url = $urls[($cursor + $i) % $total];
        wp_remote_get($url, array(
            'timeout'     => 5,
            'blocking'    => false,
            'sslverify'   => false,
            'redirection' => 0,
            // The crawl guard turns away clients with no Sec-Fetch headers,
            // and this host serves a different page to a bare request than to
            // a browser — so a warm without these would fill the cache with a
            // variant no visitor is ever served.
            'headers'     => array(
                'Accept'                    => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language'           => 'en-US,en;q=0.9',
                'Sec-Fetch-Dest'            => 'document',
                'Sec-Fetch-Mode'            => 'navigate',
                'Sec-Fetch-Site'            => 'none',
                'Sec-Fetch-User'            => '?1',
                'Upgrade-Insecure-Requests' => '1',
                'User-Agent'                => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 AF-CacheWarmer',
            ),
        ));
    }

    update_option('af_warm_cursor', ($cursor + $size) % $total, false);
}
add_action('af_warm_tick', 'af_warm_tick');

/** Five minutes — WordPress ships no schedule shorter than hourly. */
add_filter('cron_schedules', function ($s) {
    if (!isset($s['af_five_minutes'])) {
        $s['af_five_minutes'] = array(
            'interval' => 5 * MINUTE_IN_SECONDS,
            'display'  => 'Every five minutes (cache warm)',
        );
    }
    return $s;
});

add_action('init', function () {
    if (!af_warm_enabled()) {
        $next = wp_next_scheduled('af_warm_tick');
        if ($next) wp_unschedule_event($next, 'af_warm_tick');
        return;
    }
    if (!wp_next_scheduled('af_warm_tick')) {
        wp_schedule_event(time() + MINUTE_IN_SECONDS, 'af_five_minutes', 'af_warm_tick');
    }
});

/**
 * A change to what the entrances ARE invalidates the list, not the pages.
 * Cheap, and it means a new category joins the rotation without waiting out
 * the hour.
 */
add_action('created_product_cat', function () { delete_transient('af_warm_urls'); });
add_action('edited_product_cat',  function () { delete_transient('af_warm_urls'); });
add_action('delete_product_cat',  function () { delete_transient('af_warm_urls'); });

/**
 * After a purge there is nothing left to hit, so start the rotation at the
 * top rather than wherever it happened to be. af_purge_listing_pages() is the
 * only thing in this theme that drops listing pages now.
 */
add_action('af_listing_pages_purged', function () {
    update_option('af_warm_cursor', 0, false);
});

/**
 * Stop shipping the world's address book on every page.
 *
 * Check Weight, against the live product page on 2026-09-12:
 *
 *     51246 B  script  <script id="wc-country-select-js-extra">
 *
 * That is WooCommerce's wc_country_select_params — every country, and every
 * state, province and region inside them — serialised into the HTML. It was
 * the single largest thing on the page, ahead of any stylesheet, and it was
 * on a PRODUCT page, where nothing asks a visitor for an address.
 *
 * It is needed by three screens: the cart's shipping calculator, checkout,
 * and the address forms in My Account. Everywhere else it is 51 KB of HTML
 * that no script on the page reads, plus the PHP that builds the array to
 * print it, paid on every uncached render.
 *
 * Checked before cutting: the theme's own "Ship to" control is the
 * af_country_selector shortcode, which carries its own short list and has no
 * relationship to this script; nothing in the theme or inc/ enqueues, depends
 * on, or localises wc-country-select. The two modules that touch countries at
 * all — shipping.php and fraud-detection.php — read them off the ORDER,
 * server-side, after checkout.
 *
 * Deliberately generous about where it stays: any cart, checkout, account or
 * WooCommerce endpoint page keeps it, and af_needs_country_select filters the
 * decision for anything this misses.
 */
add_action('wp_enqueue_scripts', function () {
    if (is_admin() || !function_exists('is_cart')) return;

    $needed = is_cart() || is_checkout() || is_account_page()
           || (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url());

    if (apply_filters('af_needs_country_select', $needed)) return;

    // country-select carries the 51 KB payload; address-i18n is its partner
    // and is equally unused away from an address form.
    wp_dequeue_script('wc-country-select');
    wp_dequeue_script('wc-address-i18n');
}, 99);
