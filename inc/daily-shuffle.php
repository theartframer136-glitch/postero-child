<?php
/**
 * A different running order every day, on a seven-day loop.
 *
 * The catalogue is large and its natural order — newest first — means the same
 * pieces sit at the top of every listing until something newer is added, and
 * the rest are never seen. This gives each day of the week its own shuffle:
 * seven distinct orders, and the eighth day is the first one again.
 *
 * ── Why a seed, and not just rand() ────────────────────────────────────────
 *
 * The order has to be the SAME for everybody for the whole of one day.
 *
 *   - pagination. With a fresh random order per query, page 2 is drawn from a
 *     different shuffle than page 1: some products appear twice and others
 *     never appear at all. A seeded order is one fixed sequence that pages cut
 *     cleanly.
 *   - the page cache. Two visitors served the same cached HTML must be seeing
 *     an order that was correct for both of them.
 *   - and it is what makes "loop back" true rather than approximate: the seed
 *     is a function of the day, so the seventh day is followed by the first.
 *
 * ── What it does NOT touch ─────────────────────────────────────────────────
 *
 *   - a sort the shopper chose. ?orderby=price is their instruction, not ours.
 *   - search results, where relevance is the point.
 *   - the homepage rows. "New Arrivals" and "Trending Today" mean what their
 *     names say; shuffling them would make them lie.
 *   - related products and cross-sells, which are chosen for a reason.
 *
 * It is the product LISTING that rotates: the shop, the category pages and the
 * tag pages — the places a visitor browses rather than searches.
 *
 * ── The cache ──────────────────────────────────────────────────────────────
 *
 * The order changes at midnight, but a full-page cache would go on serving
 * yesterday's HTML until its TTL expired. af_shuffle_rollover purges just
 * after midnight so the new order is actually the one visitors get.
 *
 * @see tools/verify-daily-shuffle.php  proves the seven orders differ, that the
 *                                      eighth day repeats the first, and that
 *                                      no product is lost or duplicated
 */
if (!defined('ABSPATH')) exit;

/** How many days are in the loop. Seven, so a shopper's week never repeats. */
function af_shuffle_period() { return 7; }

/**
 * Which day of the loop today is: 0 to 6.
 *
 * Counted in the SITE's timezone, not UTC, so the order turns over at the
 * shop's midnight rather than at some hour of its own.
 */
function af_shuffle_day_index($when = null) {
    $ts = $when !== null ? (int) $when : (int) current_time('timestamp');
    return (int) floor($ts / DAY_IN_SECONDS) % af_shuffle_period();
}

/**
 * The seed for a given day of the loop.
 *
 * Any seven distinct numbers would do; these are spread out so consecutive
 * days do not produce near-identical orders, and the base keeps them clear of
 * the small seeds anything else might use.
 */
function af_shuffle_seed_for_day($day) {
    $day = ((int) $day % af_shuffle_period() + af_shuffle_period()) % af_shuffle_period();
    return 1000003 + $day * 7919;
}

/** Today's seed. */
function af_shuffle_seed($when = null) {
    return af_shuffle_seed_for_day(af_shuffle_day_index($when));
}

/**
 * Is this the query whose order we rotate?
 *
 * The main product listing, and only when the shopper has not asked for
 * something else.
 */
function af_shuffle_applies($q) {
    if (is_admin()) return false;
    if (!is_a($q, 'WP_Query') || !$q->is_main_query()) return false;
    if (!function_exists('is_shop')) return false;
    if (is_search()) return false;                       // relevance, not novelty
    if (!(is_shop() || is_product_taxonomy())) return false;
    if (!empty($_GET['orderby'])) return false;          // their sort wins
    return true;
}

add_action('woocommerce_product_query', function ($q) {
    if (!af_shuffle_applies($q)) return;
    $q->set('af_daily_shuffle', 1);
});

/**
 * Replace the ORDER BY with the day's seeded shuffle.
 *
 * Done here rather than through 'orderby' => 'rand' because WordPress's rand
 * emits an unseeded RAND(), which is the every-query-is-different behaviour
 * the docblock above explains we cannot have.
 */
add_filter('posts_orderby', function ($orderby, $q) {
    if (!is_a($q, 'WP_Query') || !$q->get('af_daily_shuffle')) return $orderby;
    return 'RAND(' . af_shuffle_seed() . ')';
}, 10, 2);

// ── The rollover ───────────────────────────────────────────────────────────

/** 00:05 tomorrow, site time, as the UTC stamp wp_schedule_event wants. */
function af_shuffle_next_rollover() {
    $local = strtotime('tomorrow 00:05', (int) current_time('timestamp'));
    return $local - (int) (get_option('gmt_offset') * HOUR_IN_SECONDS);
}

add_action('init', function () {
    if (!wp_next_scheduled('af_shuffle_rollover')) {
        wp_schedule_event(af_shuffle_next_rollover(), 'daily', 'af_shuffle_rollover');
    }
});

/**
 * Let the new day's order actually reach people.
 *
 * The query already returns it; this is only about the cached HTML in front of
 * it. Best effort by nature — WP-Cron fires on traffic, so on a quiet night the
 * purge happens with the first visitor rather than on the stroke of midnight.
 */
add_action('af_shuffle_rollover', function () {
    // Listing pages only. This used to empty the entire page cache and the
    // object cache with it, every single day, for a change that shows up on
    // the shop, the category pages and the tag pages and nowhere else. On a
    // host that returns 508 when its resource limit is reached, that made
    // every page on the site a cold render at once, with nothing to warm it
    // — the condition the deploy workflow carries a guarded re-warm step to
    // avoid. A shopper landing on a cold category page then waits out a full
    // build behind a queue of other cold builds.
    if (function_exists('af_purge_listing_pages')) {
        af_purge_listing_pages();
        return;
    }
    do_action('litespeed_purge_all');   // theme not loaded (WP-CLI, say)
});
