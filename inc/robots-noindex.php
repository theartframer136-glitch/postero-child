<?php
/**
 * Noindex the personal and thin pages, and take them out of the sitemap.
 *
 * DEF-10. Measured on the live site before writing this (tools/probe-robots.mjs):
 *
 *     page        robots meta (one tag each)            in sitemap
 *     wishlist    follow, index, max-snippet:-1, …      yes
 *     login       follow, index, max-snippet:-1, …      yes
 *     sign-up     follow, index, max-snippet:-1, …      yes
 *     compare     follow, index, max-snippet:-1, …      yes
 *     dashboard   follow, index, max-snippet:-1, …      yes
 *     cart        noindex, follow                       yes   <- also wrong
 *     checkout    noindex, follow                       yes   <- also wrong
 *     home, shop  follow, index, …                      yes   (controls)
 *
 * No X-Robots-Tag header anywhere. All five pages the report names exist.
 *
 * TWO HALVES, BOTH NEEDED. The meta alone is not the fix. A URL that says
 * noindex but is still submitted in the sitemap is its own Search Console
 * error, "Submitted URL marked noindex". Cart and checkout are in exactly
 * that state today. So every page noindexed here also leaves the sitemap,
 * and cart, checkout and the account page leave it too.
 *
 * ONE TAG. Rank Math prints the robots meta and removes WordPress's own, so
 * the page is changed through Rank Math's robots filter. That edits the tag
 * already printed; it does not add a second. wp_robots gets the same change
 * so the pages stay noindexed if Rank Math is ever switched off. While Rank
 * Math is on, that filter has nothing to print into.
 *
 * FOLLOW IS KEPT. Links on these pages still count. Only 'index' changes.
 *
 * MATCHED BY SLUG, pages only. Products, categories, posts and the home and
 * shop pages can never match. Home and shop are also excluded by ID, because
 * noindexing the shop is the worst thing this file could do.
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Page slugs that should not be in search results. */
function af_noindex_slugs() {
    $slugs = array('wishlist', 'login', 'sign-up', 'compare', 'dashboard');
    return (array) apply_filters('af_noindex_slugs', $slugs);
}

/**
 * WooCommerce's own cart, checkout and account pages. They are already
 * noindex and are only listed here to take them out of the sitemap.
 */
function af_noindex_wc_page_ids() {
    static $ids = null;
    if ($ids !== null) {
        return $ids;
    }
    $ids = array();
    if (function_exists('wc_get_page_id')) {
        foreach (array('cart', 'checkout', 'myaccount') as $key) {
            $id = (int) wc_get_page_id($key);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
    }
    return $ids;
}

/** Pages that must stay indexable whatever their slug. */
function af_noindex_protected_ids() {
    $ids = array((int) get_option('page_on_front'));
    if (function_exists('wc_get_page_id')) {
        $ids[] = (int) wc_get_page_id('shop');
    }
    return array_filter($ids);
}

/**
 * Whether this page should be noindexed. Takes a WP_Post, or the stdClass
 * rows that sitemap generators read straight from the database.
 */
function af_noindex_post($post) {
    if (!is_object($post) || empty($post->ID)) {
        return false;
    }
    $id = (int) $post->ID;
    if (in_array($id, af_noindex_protected_ids(), true)) {
        return false;
    }
    $type = isset($post->post_type) ? $post->post_type : get_post_type($id);
    if ($type !== 'page') {
        return false;
    }
    if (in_array($id, af_noindex_wc_page_ids(), true)) {
        return true;
    }
    $name = isset($post->post_name) ? $post->post_name : get_post_field('post_name', $id);
    return in_array((string) $name, af_noindex_slugs(), true);
}

/** The page being viewed, if it is one of them. */
function af_noindex_current() {
    if (!is_singular('page')) {
        return false;
    }
    return af_noindex_post(get_queried_object());
}

// Rank Math: the tag that is actually printed.
add_filter('rank_math/frontend/robots', function ($robots) {
    try {
        if (is_array($robots) && af_noindex_current()) {
            $robots['index'] = 'noindex';
        }
    } catch (\Throwable $e) {
        // Leave the tag as it was.
    }
    return $robots;
}, 20);

// WordPress's own tag: printed only when Rank Math is not.
add_filter('wp_robots', function ($robots) {
    try {
        if (is_array($robots) && af_noindex_current()) {
            unset($robots['index']);
            $robots['noindex'] = true;
            if (empty($robots['nofollow'])) {
                $robots['follow'] = true;
            }
        }
    } catch (\Throwable $e) {
    }
    return $robots;
}, 20);

// Rank Math sitemap: returning false drops the URL.
add_filter('rank_math/sitemap/entry', function ($url, $type = '', $object = null) {
    try {
        // Terms and users have IDs too. Only look at posts.
        if (is_object($object) && ($type === 'post' || isset($object->post_type)) && af_noindex_post($object)) {
            return false;
        }
    } catch (\Throwable $e) {
    }
    return $url;
}, 20, 3);

/**
 * Rank Math caches the sitemap and rebuilds it only when content is saved.
 * A deploy saves nothing, so without this the old sitemap would keep listing
 * these pages. Cleared once per revision. Bump the number to clear it again.
 * The option records which method ran, or that none was available.
 */
define('AF_NOINDEX_SITEMAP_REV', '1');

add_action('wp_loaded', function () {
    try {
        if (get_option('af_noindex_sitemap_rev') === AF_NOINDEX_SITEMAP_REV) {
            return;
        }
        // Record first, so a failure below cannot repeat on every request.
        update_option('af_noindex_sitemap_rev', AF_NOINDEX_SITEMAP_REV, true);
        $how = 'none available';
        if (is_callable(array('RankMath\Sitemap\Cache', 'invalidate_storage'))) {
            \RankMath\Sitemap\Cache::invalidate_storage();
            $how = 'Cache::invalidate_storage';
        } elseif (is_callable(array('RankMath\Sitemap\Cache_Watcher', 'invalidate'))) {
            \RankMath\Sitemap\Cache_Watcher::invalidate('page');
            $how = 'Cache_Watcher::invalidate(page)';
        }
        update_option('af_noindex_sitemap_flush', $how . ' @ ' . gmdate('c'), false);
    } catch (\Throwable $e) {
        update_option('af_noindex_sitemap_flush', 'failed: ' . substr($e->getMessage(), 0, 120), false);
    }
}, 99);
