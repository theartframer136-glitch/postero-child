<?php
if (!defined('ABSPATH')) exit;
/**
 * "Trending Today" — rank the section by what is actually selling.
 *
 * Owner, 2026-08-26: "here will be show those products which sales is trending
 * on today".
 *
 * WHAT IT WAS DOING
 * The section is the [trending_now_cards] shortcode, which lists the 20 NEWEST
 * products — the ids it printed came out in strict descending order, so pieces
 * added an hour earlier sat across the whole band and nothing about it was
 * trending. That shortcode is not in this theme (it lives in a snippet plugin),
 * so this does NOT replace it: replacing it would mean re-creating its markup
 * and its stylesheet, and any difference would land as a broken section.
 * The shortcode still renders exactly what it always rendered; only the QUERY
 * behind it is reordered, and only while it runs.
 *
 * HOW "TRENDING" IS SCORED
 * Recency is the whole point of the word, so a sale today has to outweigh a
 * sale last month rather than being averaged into it:
 *
 *     score = 100 x units sold in the last 24h
 *           +  20 x units sold in the last 7 days
 *           +   5 x units sold in the last 30 days
 *
 * WHY THERE IS A FALLBACK
 * This store has three orders in its whole history, all older than a month, one
 * of them literally named "test", and total_sales is 0 on all 385 products.
 * Scored honestly, "trending today" is EMPTY — and an empty band on the
 * homepage is worse than the newest-first list it replaces. So when no sale
 * falls in the window it ranks by the view counter the theme already keeps
 * (_eael_post_view_count): real interest from real visitors. The moment an
 * order lands, sales outrank every view — 100 points a unit dwarfs a view
 * count — and the thank-you page drops the cache so the band moves that minute.
 *
 * ── WHY THE RANKING IS BUILT WHERE IT IS ──────────────────────────────────
 * The first version of this file computed the ranking INSIDE pre_get_posts.
 * That recursed: building the list calls wc_get_orders() and wc_get_products(),
 * each of those runs its own WP_Query, each of those fires pre_get_posts again
 * while the shortcode flag is still up — and round it goes. It returned HTTP
 * 500 on the homepage until the module was unhooked. /shop was untouched,
 * because the shortcode only runs on the front page.
 *
 * The fix is structural, not a guard: the ranking is built in
 * pre_do_shortcode_tag, BEFORE the flag is raised, so the queries it runs
 * cannot be caught by the very hook that raising the flag arms. By the time
 * pre_get_posts sees anything the answer is already a plain array of ids, and
 * that handler runs no queries at all. The static re-entry guard below is
 * belt-and-braces on top of that, not the mechanism.
 */

/** How long a computed ranking is reused. Trending does not need to be live. */
function af_trending_ttl() {
    return (int) apply_filters('af_trending_ttl', HOUR_IN_SECONDS);
}

/**
 * Units sold per product inside a window, as product_id => units.
 *
 * wc_get_orders rather than hand-written SQL against woocommerce_order_items:
 * the store may move to HPOS, where those tables stop being the source of
 * truth, and this keeps working either way.
 */
function af_trending_units_since($days) {
    if (!function_exists('wc_get_orders')) return array();
    $after  = gmdate('Y-m-d H:i:s', time() - ((int) $days * DAY_IN_SECONDS));
    $orders = wc_get_orders(array(
        'limit'        => 500,
        'status'       => array('wc-processing', 'wc-completed', 'wc-on-hold'),
        'date_created' => '>' . $after,
        'return'       => 'objects',
    ));
    $units = array();
    if (!is_array($orders)) return $units;
    foreach ($orders as $order) {
        if (!is_a($order, 'WC_Order')) continue;
        foreach ($order->get_items() as $item) {
            $pid = (int) $item->get_product_id();
            if ($pid > 0) {
                $units[$pid] = (isset($units[$pid]) ? $units[$pid] : 0) + (int) $item->get_quantity();
            }
        }
    }
    return $units;
}

/**
 * The ranked product ids, best first. Cached — this is the homepage, on a
 * CPU-capped host.
 *
 * MUST NOT be called from inside pre_get_posts: it runs queries of its own.
 * See the note at the top of this file.
 */
function af_trending_product_ids($limit = 20) {
    if (!function_exists('wc_get_products')) return array();
    $limit = max(1, (int) $limit);
    $key   = 'af_trending_ids_' . $limit;

    $hit = get_transient($key);
    if (is_array($hit) && $hit) return $hit;

    static $building = false;
    if ($building) return array();        // belt-and-braces; see file header
    $building = true;

    try {
        $score = array();
        foreach (array(1 => 100, 7 => 20, 30 => 5) as $days => $weight) {
            foreach (af_trending_units_since($days) as $pid => $units) {
                $score[$pid] = (isset($score[$pid]) ? $score[$pid] : 0) + ($units * $weight);
            }
        }

        // Only products a shopper can actually buy belong in the band.
        $sellable = wc_get_products(array(
            'status'       => 'publish',
            'limit'        => 500,
            'stock_status' => 'instock',
            'return'       => 'ids',
        ));
        $sellable = is_array($sellable) ? array_map('intval', $sellable) : array();
        if (!$sellable) return array();

        $allowed = array_flip($sellable);
        foreach (array_keys($score) as $pid) {
            if (!isset($allowed[$pid])) unset($score[$pid]);
        }

        // No sale in the window: rank by recorded interest rather than show a
        // band that is empty, or silently newest-first again.
        if (!$score) {
            foreach ($sellable as $pid) {
                $v = (int) get_post_meta($pid, '_eael_post_view_count', true);
                if ($v > 0) $score[$pid] = $v;
            }
        }
        if (!$score) return array();

        arsort($score);
        $ids = array_map('intval', array_slice(array_keys($score), 0, $limit));
        set_transient($key, $ids, af_trending_ttl());
        return $ids;
    } finally {
        $building = false;
    }
}

/* ── Reorder that one shortcode's query, and nothing else ─────────────────── */

add_filter('pre_do_shortcode_tag', function ($return, $tag) {
    if ($tag !== 'trending_now_cards') return $return;
    // Build the ranking FIRST, while the flag is still down: the queries it
    // runs must not be caught by the hook that raising the flag arms.
    $GLOBALS['af_trending_ids'] = af_trending_product_ids(20);
    $GLOBALS['af_in_trending_shortcode'] = true;
    return $return;
}, 10, 2);

add_filter('do_shortcode_tag', function ($output, $tag) {
    if ($tag === 'trending_now_cards') {
        $GLOBALS['af_in_trending_shortcode'] = false;
        unset($GLOBALS['af_trending_ids']);
    }
    return $output;
}, 10, 2);

add_action('pre_get_posts', function ($q) {
    if (empty($GLOBALS['af_in_trending_shortcode'])) return;
    if (empty($GLOBALS['af_trending_ids']) || !is_array($GLOBALS['af_trending_ids'])) return;
    if (is_admin() && !wp_doing_ajax()) return;

    $pt = $q->get('post_type');
    $is_product = ($pt === 'product') || (is_array($pt) && in_array('product', $pt, true));
    if (!$is_product) return;

    // Reads an array that is already built. Runs no queries, so it cannot
    // re-enter itself.
    $q->set('post__in', $GLOBALS['af_trending_ids']);
    $q->set('orderby', 'post__in');        // keep OUR order, not the DB's
    $q->set('order', 'ASC');
    $q->set('ignore_sticky_posts', true);
}, 999);

/* Keep it honest the moment something sells. */
add_action('woocommerce_thankyou', function () {
    foreach (array(4, 8, 10, 12, 16, 20, 24) as $n) {
        delete_transient('af_trending_ids_' . $n);
    }
}, 10, 0);
