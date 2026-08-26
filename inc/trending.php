<?php
if (!defined('ABSPATH')) exit;
/**
 * "Trending Today" — rank the section by what is actually selling.
 *
 * Owner, 2026-08-26: "here will be show those products which sales is trending
 * on today".
 *
 * WHAT IT WAS DOING
 * The section is the [trending_now_cards] shortcode, which lists the 20 newest
 * products — the ids it printed came out in strict descending order, so the
 * pieces added an hour earlier sat at the top and nothing about it was
 * trending. That shortcode is not in this theme (it lives in a snippet plugin),
 * so this does NOT replace it: replacing it would mean re-creating its markup
 * and its stylesheet, and any difference would show up as a broken section.
 * Instead the shortcode still renders exactly what it always rendered, and only
 * the QUERY behind it is reordered, while it runs.
 *
 * HOW "TRENDING" IS SCORED
 * Recency is the whole point of the word, so a sale today has to outweigh a
 * sale last month rather than being averaged into it:
 *
 *     score = 100 x units sold in the last 24h
 *           +  20 x units sold in the last 7 days
 *           +   5 x units sold in the last 30 days
 *
 * A piece bought twice today therefore beats one bought five times a fortnight
 * ago, which is what a shopper reads "trending today" to mean.
 *
 * WHY THERE IS A FALLBACK, AND WHY IT IS NOT A FUDGE
 * This store has three orders in its whole history, all older than a month, one
 * of them literally called "test", and total_sales is 0 on all 385 products.
 * Scored honestly, "trending today" is empty — and an empty band on the
 * homepage is worse than the newest-first list it replaces.
 *
 * So when no sale falls in the window, the ranking falls through to the view
 * counter the theme already keeps (_eael_post_view_count), which is real
 * interest recorded from real visitors — 841 views on the Vishnu piece, 535 on
 * the Radha Krishna Angel Embrace. That is the closest true signal available
 * until orders exist, and the moment they do, sales outrank every view because
 * the sales weights are far larger than a normalised view score.
 *
 * Nothing here is hardcoded to this store's current emptiness: the same code
 * ranks by sales the day the first order lands.
 */

/** How long a computed ranking is reused. Trending does not need to be live. */
function af_trending_ttl() {
    return (int) apply_filters('af_trending_ttl', HOUR_IN_SECONDS);
}

/**
 * Units sold per product inside a window, as product_id => units.
 *
 * Uses wc_get_orders rather than hand-written SQL against
 * woocommerce_order_items: the store may move to HPOS, where those tables stop
 * being the source of truth, and this keeps working either way.
 */
function af_trending_units_since($days) {
    $after = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
    $orders = wc_get_orders(array(
        'limit'       => 500,
        'status'      => array('wc-processing', 'wc-completed', 'wc-on-hold'),
        'date_created' => '>' . $after,
        'return'      => 'objects',
    ));
    $units = array();
    if (!is_array($orders)) return $units;
    foreach ($orders as $order) {
        if (!is_a($order, 'WC_Order')) continue;
        foreach ($order->get_items() as $item) {
            $pid = (int) $item->get_product_id();
            if ($pid <= 0) continue;
            $units[$pid] = ($units[$pid] ?? 0) + (int) $item->get_quantity();
        }
    }
    return $units;
}

/**
 * The ranked product ids, best first. Cached, because this runs on the
 * homepage and the homepage is the most cached-and-hammered page on the site.
 */
function af_trending_product_ids($limit = 20) {
    $limit = max(1, (int) $limit);
    $key = 'af_trending_ids_' . $limit;
    $hit = get_transient($key);
    if (is_array($hit) && $hit) return $hit;

    $score = array();
    foreach (array(1 => 100, 7 => 20, 30 => 5) as $days => $weight) {
        foreach (af_trending_units_since($days) as $pid => $units) {
            $score[$pid] = ($score[$pid] ?? 0) + ($units * $weight);
        }
    }

    // Only products a shopper can actually buy belong in the band.
    $sellable = wc_get_products(array(
        'status'       => 'publish',
        'limit'        => -1,
        'stock_status' => 'instock',
        'return'       => 'ids',
    ));
    $sellable = is_array($sellable) ? array_map('intval', $sellable) : array();
    if (!$sellable) return array();
    $allowed = array_flip($sellable);
    foreach (array_keys($score) as $pid) {
        if (!isset($allowed[$pid])) unset($score[$pid]);
    }

    // No sale in the window: rank by recorded interest instead of showing a
    // band that is either empty or, worse, silently newest-first again.
    if (!$score) {
        foreach ($sellable as $pid) {
            $v = (int) get_post_meta($pid, '_eael_post_view_count', true);
            if ($v > 0) $score[$pid] = $v;
        }
    }
    if (!$score) return array();

    arsort($score);
    $ids = array_slice(array_keys($score), 0, $limit);
    $ids = array_map('intval', $ids);
    set_transient($key, $ids, af_trending_ttl());
    return $ids;
}

/* ── Reorder the shortcode's query, and nothing else ────────────────────────
 * The flag is raised only for the duration of [trending_now_cards], so no other
 * query on the page — the sliders, the collection rows, the search — can be
 * caught by it.
 */
add_filter('pre_do_shortcode_tag', function ($return, $tag) {
    if ($tag === 'trending_now_cards') $GLOBALS['af_in_trending_shortcode'] = true;
    return $return;
}, 10, 2);

add_filter('do_shortcode_tag', function ($output, $tag) {
    if ($tag === 'trending_now_cards') $GLOBALS['af_in_trending_shortcode'] = false;
    return $output;
}, 10, 2);

add_action('pre_get_posts', function ($q) {
    if (empty($GLOBALS['af_in_trending_shortcode'])) return;
    if (is_admin() && !wp_doing_ajax()) return;

    $pt = $q->get('post_type');
    $is_product = ($pt === 'product') || (is_array($pt) && in_array('product', $pt, true));
    if (!$is_product) return;

    $want = (int) $q->get('posts_per_page');
    if ($want < 1) $want = 20;
    $ids = af_trending_product_ids($want);
    if (!$ids) return;                       // nothing to say: leave it alone

    $q->set('post__in', $ids);
    $q->set('orderby', 'post__in');          // keep OUR order, not the DB's
    $q->set('order', 'ASC');
    $q->set('ignore_sticky_posts', true);
}, 999);

/* ── Keep it honest when something sells ──────────────────────────────────
 * Without this the band would go on showing an hour-old ranking after an order
 * lands, which is the one moment it should visibly change.
 */
add_action('woocommerce_thankyou', function () {
    foreach (array(4, 8, 12, 20, 24) as $n) delete_transient('af_trending_ids_' . $n);
}, 10, 0);
