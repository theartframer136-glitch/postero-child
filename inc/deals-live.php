<?php
/**
 * Deals & Discounts — the category fills itself from what is actually on sale.
 *
 * Owner, 2026-09-09, with a screenshot of /product-category/deals-discounts/:
 * "No products were found matching your selection. add the discounted product
 * and the products which have deals going on".
 *
 * The category is real and linked from the main menu and the sidebar; it simply
 * has nothing in it, because nobody has ever hand-assigned products to it. Two
 * ways to fix that:
 *
 *   1. rewrite the query behind that URL to return on-sale products instead of
 *      the term's members
 *   2. put the on-sale products IN the term
 *
 * This is (2), deliberately. A rewritten query would have to unpick the term
 * archive WordPress has already parsed, and everything downstream that trusts a
 * category to behave like a category — the sidebar filters, the sort dropdown,
 * pagination, the breadcrumb, the product count, whatever plugin reads the term
 * next — would be reading one thing while the loop showed another. Putting the
 * products in the category costs a handful of writes a day and leaves the page
 * an ordinary category page that needs no special case anywhere.
 *
 * It keeps itself current: a daily pass adds what has gone on sale and removes
 * what has come off. Nothing else has to be remembered.
 *
 * IT WILL NOT TOUCH A HAND-PICKED PRODUCT. Every product this file adds is
 * marked with _af_deal_auto, and only marked products are ever removed. Put a
 * full-price piece in Deals & Discounts by hand and it stays there.
 */
if (!defined('ABSPATH')) exit;

/** The Deals & Discounts term, or 0 if the shop has no such category. */
function af_deals_term_id() {
    $t = get_term_by('slug', 'deals-discounts', 'product_cat');
    if ($t && !is_wp_error($t)) return (int) $t->term_id;
    // The slug is what the URL uses, so it is the reliable key. Fall back to
    // the name only if someone has renamed the slug out from under us.
    $t = get_term_by('name', 'Deals & Discounts', 'product_cat');
    return ($t && !is_wp_error($t)) ? (int) $t->term_id : 0;
}

/** The marker that says "this file put the product here, so it may take it back". */
function af_deals_meta_key() { return '_af_deal_auto'; }

/**
 * Products that belong in the category: anything selling below its regular
 * price, however small the cut.
 *
 * af_deal_ids() already computes exactly this from _price against
 * _regular_price, counts a variation's discount towards its parent, and caches
 * for fifteen minutes — it is what /clearance/ runs on. Reused rather than
 * rewritten so the two pages can never disagree about what a discount is.
 */
function af_deals_wanted_ids() {
    if (function_exists('af_deal_ids')) return array_map('intval', af_deal_ids(1));
    // deals-page.php missing or renamed: WooCommerce's own answer is close
    // enough to keep the category populated rather than let it empty itself.
    return function_exists('wc_get_product_ids_on_sale')
        ? array_map('intval', wc_get_product_ids_on_sale())
        : array();
}

/** Products currently sitting in the category. */
function af_deals_current_ids($term_id) {
    if ($term_id < 1) return array();
    return array_map('intval', get_posts(array(
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'tax_query'      => array(array(
            'taxonomy'         => 'product_cat',
            'field'            => 'term_id',
            'terms'            => $term_id,
            'include_children' => false,
        )),
    )));
}

/**
 * What to add and what to take away. Pure — no database, no WordPress — so the
 * one rule that matters can be tested directly: a product this file did not add
 * is never removed, no matter what its price is.
 *
 * @param int[] $wanted on sale now
 * @param int[] $have   in the category now
 * @param int[] $ours   in the category now AND marked as ours
 */
function af_deals_plan(array $wanted, array $have, array $ours) {
    $wanted = array_values(array_unique(array_map('intval', $wanted)));
    $have   = array_values(array_unique(array_map('intval', $have)));
    $ours   = array_values(array_unique(array_map('intval', $ours)));
    return array(
        'add'    => array_values(array_diff($wanted, $have)),
        // off sale, and ours to remove. A hand-picked product is in $have but
        // not in $ours, so it survives this line — that is the whole guarantee.
        'remove' => array_values(array_intersect(array_diff($have, $wanted), $ours)),
    );
}

/**
 * Bring the category in line with the sale prices.
 * Returns a one-line summary; writes nothing when nothing has changed.
 */
function af_deals_sync() {
    $term_id = af_deals_term_id();
    if ($term_id < 1) return 'Deals & Discounts: no such product category — nothing done.';

    $wanted = af_deals_wanted_ids();
    $have   = af_deals_current_ids($term_id);
    $key    = af_deals_meta_key();

    $ours = array();
    foreach ($have as $id) {
        if (get_post_meta($id, $key, true)) $ours[] = $id;
    }

    $plan = af_deals_plan($wanted, $have, $ours);

    foreach ($plan['add'] as $id) {
        wp_set_object_terms($id, array($term_id), 'product_cat', true);  // append
        update_post_meta($id, $key, 1);
    }
    foreach ($plan['remove'] as $id) {
        wp_remove_object_terms($id, array($term_id), 'product_cat');
        delete_post_meta($id, $key);
    }

    $changed = count($plan['add']) + count($plan['remove']);
    if ($changed) {
        // The count beside the category name is cached on the term.
        wp_update_term_count_now(array($term_id), 'product_cat');
        clean_term_cache(array($term_id), 'product_cat');
        // The listing that changed, plus the shop. Not the whole site: a
        // full purge on this host leaves every page cold and the box 508s
        // its way back, which is far more damage than a stale deals count.
        if (function_exists('af_purge_listing_pages')) {
            af_purge_listing_pages(array($term_id));
        } else {
            do_action('litespeed_purge_all');
        }
    }

    $summary = sprintf(
        'Deals & Discounts: %d on sale, %d in the category, +%d added, -%d removed%s',
        count($wanted), count($have), count($plan['add']), count($plan['remove']),
        $changed ? '' : ' (nothing to do)'
    );
    update_option('af_deals_sync_summary', gmdate('Y-m-d H:i') . ' UTC — ' . $summary, false);
    return $summary;
}

add_action('af_deals_sync', 'af_deals_sync');

/**
 * Daily, and once shortly after this file first arrives — otherwise the
 * category stays empty until tomorrow, which is the thing being fixed.
 */
add_action('init', function () {
    if (!wp_next_scheduled('af_deals_sync')) {
        wp_schedule_event(time() + 120, 'daily', 'af_deals_sync');
    }

    // FIRST RUN, without waiting for cron. WP-cron only fires on a page view,
    // and on a host where it has been turned off in favour of a system cron it
    // may not fire when expected at all — either way the category would sit
    // empty for hours after this deploys, which is the complaint. So the very
    // first pass runs inline, once, behind a lock so two simultaneous visitors
    // cannot both start it. Every pass after this one is the daily event.
    if (get_option('af_deals_sync_summary')) return;
    if (get_transient('af_deals_bootstrap')) return;
    set_transient('af_deals_bootstrap', 1, 10 * MINUTE_IN_SECONDS);
    af_deals_sync();
}, 20);

/**
 * A product going on or off sale is the event that matters, so react to it
 * rather than making the owner wait for the daily pass. Debounced through a
 * one-off event: editing forty products in a row schedules one sync, not forty.
 */
add_action('woocommerce_update_product', function ($product_id) {
    if (wp_next_scheduled('af_deals_sync_soon')) return;
    wp_schedule_single_event(time() + 300, 'af_deals_sync_soon');
});
add_action('af_deals_sync_soon', 'af_deals_sync');
