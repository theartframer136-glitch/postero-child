<?php
/**
 * Take placeholder products off public sale. Test Run 03, N-01; and, from
 * 25 Sep, the five leftover theme demo posters the owner asked to remove.
 *
 * Product 11491 is called "test". Measured 23 Sep as a first-time visitor: it
 * answers 200 at /product/test-canvas-wall-art/, is marked index,follow for
 * search engines, has an Add to Cart button, sells at $1.00 (38% off $1.61),
 * and is the first card on /shop/?orderby=price. Anyone sorting by price
 * lands on it first, and could buy it.
 *
 * It becomes PRIVATE, not draft or trash:
 *   - Shoppers, search, the Store API and the sitemap only see published
 *     products, so it goes from all of them. Every product query in this
 *     theme asks for 'publish' (checked 23 Sep), so none of the bands on the
 *     home page can pick it up either.
 *   - The owner, logged in, can still open it and buy it. A $1 product is
 *     the usual way to test a payment gateway with a real card, and that
 *     keeps working. Nothing is deleted. Publishing it again is one click.
 *
 * Runs once per revision, on the first request after the deploy, like the
 * sitemap clear in robots-noindex.php. A product is changed only if it is
 * still published AND still carries the placeholder name, so an id that
 * later belongs to a real piece is never touched, and nor is a product the
 * owner has since renamed. Whatever happened is recorded in the
 * af_placeholder_products option, where health-check.yml reads it.
 */
if (!defined('ABSPATH')) exit;

/**
 * id => the name it must still carry. Bump the revision after changing this.
 *
 * Revision 2, 23 Sep: revision 1 made 11491 private at 12:49:01 and closed 9
 * of the 10 ways in. The product sitemap still listed it, because Rank Math
 * serves a sitemap it built earlier and the save did not clear it (the copy
 * fetched past the page cache listed it too). Revision 2 clears that copy.
 *
 * Revision 3, 25 Sep: the owner asked for TMP-1000 to TMP-1004 to be removed
 * from the website. They are the Postero theme's demo posters, on the site
 * since the theme was installed (15 Jun 2023): four carry the theme's grey
 * "Postero" stand-in picture (see af_is_placeholder_image in functions.php)
 * and none is in the Canva brochure. Private for the same reasons as #11491:
 * gone from every public view, nothing deleted, one click to publish again.
 * #11491 is already private and reads "already private".
 */
define('AF_PLACEHOLDER_PRODUCTS_REV', '3');

function af_placeholder_products() {
    return array(
        11491 => 'test',
        // Theme demo posters (art codes TMP-1000 to TMP-1004), owner 25 Sep.
        115   => 'The Penguin Show Poster',
        123   => 'IL Lemone Poster',
        177   => 'Geometric Shapes poster',
        199   => 'Balance Poster',
        211   => 'Japanese Butterfly II Poster',
    );
}

/**
 * Make Rank Math build the product sitemap again, so a hidden product stops
 * being listed. Says which method ran, the way robots-noindex.php does.
 */
function af_placeholder_sitemap_clear() {
    if (is_callable(array('RankMath\Sitemap\Cache', 'invalidate_storage'))) {
        \RankMath\Sitemap\Cache::invalidate_storage('product');
        return 'Cache::invalidate_storage(product)';
    }
    if (is_callable(array('RankMath\Sitemap\Cache_Watcher', 'invalidate'))) {
        \RankMath\Sitemap\Cache_Watcher::invalidate('product');
        return 'Cache_Watcher::invalidate(product)';
    }
    return 'none available';
}

add_action('wp_loaded', function () {
    try {
        if (get_option('af_placeholder_products_rev') === AF_PLACEHOLDER_PRODUCTS_REV) {
            return;
        }
        if (!function_exists('wc_get_product')) {
            return;
        }
        // Record first, so a failure below cannot repeat on every request.
        update_option('af_placeholder_products_rev', AF_PLACEHOLDER_PRODUCTS_REV, true);
        $log = array();
        $hidden = 0;
        foreach (af_placeholder_products() as $id => $name) {
            $product = wc_get_product($id);
            if (!$product) {
                $log[] = $id . ' not found';
                continue;
            }
            $status = $product->get_status();
            $actual = trim((string) $product->get_name());
            if (strcasecmp($actual, $name) !== 0) {
                $log[] = $id . ' left alone: now named "' . substr($actual, 0, 60) . '"';
                continue;
            }
            if ($status !== 'publish') {
                $log[] = $id . ' already ' . $status;
                $hidden++;
                continue;
            }
            $product->set_status('private');
            $product->save();
            // The save purges the product itself. The shop and its sort
            // orders are separate cache entries, so purge those by name
            // rather than purging the whole site.
            do_action('litespeed_purge_posttype', 'product');
            if (function_exists('wc_get_page_permalink')) {
                do_action('litespeed_purge_url', wc_get_page_permalink('shop'));
            }
            $log[] = $id . ' publish -> ' . get_post_status($id);
            $hidden++;
        }
        if ($hidden) {
            $log[] = 'sitemap: ' . af_placeholder_sitemap_clear();
        }
        update_option('af_placeholder_products', implode('; ', $log) . ' @ ' . gmdate('c'), false);
    } catch (\Throwable $e) {
        update_option('af_placeholder_products', 'failed: ' . substr($e->getMessage(), 0, 120), false);
    }
}, 99);
