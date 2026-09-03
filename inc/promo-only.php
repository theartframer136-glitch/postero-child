<?php
if (!defined('ABSPATH')) exit;
/**
 * Keep the advertising pictures out of the selling sections.
 *
 * Owner, 2026-09-03: "all the image showing are present on the Exclusively
 * Customised Creations they should not be shown on New Arrivals and Trending
 * Today because this images are not products they are the images for
 * advertisement".
 *
 * The nine photographs behind Customised Creations are products only because
 * that band is built from a category — the shortcode needs something to list.
 * They are not artwork anyone should be sold, so they are marked
 * _af_promo_only=yes and this removes them from the two bands that exist to
 * sell: [new_arrival_products] and [trending_now_cards]. Customised Creations
 * is untouched and still shows all of them.
 *
 * WHY IT IS WRITTEN THIS WAY
 * On 2026-08-26 an earlier attempt at exactly this shape of problem took the
 * home page down for forty minutes: it BUILT its id list inside pre_get_posts,
 * and building it ran queries, and each of those re-entered pre_get_posts until
 * PHP died. So the order here is deliberate and is the whole safety argument:
 *
 *   1. pre_do_shortcode_tag  — the list is queried HERE, before the flag is up
 *   2. pre_get_posts         — reads that finished array. Runs NO query at all
 *   3. do_shortcode_tag      — flag down
 *
 * Step 2 must never gain a query. If it needs one, the answer belongs in step 1.
 */

/** The bands that sell. Customised Creations is deliberately absent. */
function af_promo_excluded_shortcodes() {
    return (array) apply_filters('af_promo_excluded_shortcodes',
        array('new_arrival_products', 'trending_now_cards'));
}

/**
 * Ids of the advertising pictures. Cached, and — importantly — only ever called
 * from pre_do_shortcode_tag, never from inside a query hook.
 */
function af_promo_only_ids() {
    $ids = get_transient('af_promo_only_ids');
    if (!is_array($ids)) {
        $q = new WP_Query(array(
            'post_type'              => 'product',
            'post_status'            => 'any',
            'posts_per_page'         => 200,
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'ignore_sticky_posts'    => true,
            'update_post_term_cache' => false,
            'meta_query'             => array(array(
                'key'   => '_af_promo_only',
                'value' => 'yes',
            )),
        ));
        $ids = array_map('intval', $q->posts);
        set_transient('af_promo_only_ids', $ids, HOUR_IN_SECONDS);
    }
    return $ids;
}

/* 1. Before the shortcode runs: work out the list, THEN raise the flag. */
add_filter('pre_do_shortcode_tag', function ($return, $tag) {
    if (!in_array($tag, af_promo_excluded_shortcodes(), true)) return $return;
    $GLOBALS['af_promo_hide'] = af_promo_only_ids();   // queried while the flag is DOWN
    return $return;
}, 10, 2);

/* 2. While it runs: subtract them. No query here, by design. */
add_action('pre_get_posts', function ($q) {
    if (empty($GLOBALS['af_promo_hide'])) return;
    if (is_admin() && !wp_doing_ajax()) return;
    $pt = $q->get('post_type');
    if ($pt !== 'product' && !(is_array($pt) && in_array('product', $pt, true))) return;
    $q->set('post__not_in', array_merge(
        (array) $q->get('post__not_in'), $GLOBALS['af_promo_hide']));
}, 999);

/* 3. After it runs: flag down, so no other query on the page is affected. */
add_filter('do_shortcode_tag', function ($output, $tag) {
    if (in_array($tag, af_promo_excluded_shortcodes(), true)) {
        $GLOBALS['af_promo_hide'] = array();
    }
    return $output;
}, 10, 2);

/* Marking a picture (or un-marking it) takes effect on the next page view. */
add_action('save_post_product', function () { delete_transient('af_promo_only_ids'); }, 10, 0);
