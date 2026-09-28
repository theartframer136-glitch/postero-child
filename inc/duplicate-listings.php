<?php
/**
 * Duplicate listings: one artwork, one product. Owner, 28 Sep: "remove the
 * duplicate products first", from theartframer-skus-with-a-letter.xlsx.
 *
 * That workbook lists 17 products whose SKU carries a letter (A/B/C) because
 * they share an art code with another listing. Not all 17 are duplicates.
 * Each pair's pictures were compared by three independent reviewers, and the
 * live pages were read on 28 Sep (tools/diag-duplicates.mjs):
 *
 *   SAME artwork, 3 of 3 reviewers  -> deleted here (10 listings)
 *   #7824 Bal Krishna Flute          -> NOT deleted: reviewers split 2 to 1, and it
 *                                       holds the pair's only reviews (2); the
 *                                       product that would stay has none
 *   #19453, #24836, #7781, #8424,    -> NOT hidden: DIFFERENT artworks that were
 *   #8494, #8474                        given the same art code (0 of 3 reviewers
 *                                       said same). They need their own codes.
 *
 * The listing that stays is the one holding the plain SKU, the brochure's
 * product in the owner's sheet. None of the ten deleted here has a review;
 * the two kept listings that do (#220 and #229, five each) stay.
 *
 * DELETED PERMANENTLY. Owner, 28 Sep: "no remove the duplicate product delete
 * permanently them". WooCommerce's own delete, the same as the demo posters in
 * inc/placeholder-products.php (26 Sep), so the lookup tables and transients
 * go with the post. Their pictures stay in the Media Library. Orders that ever
 * included one keep their line items: WooCommerce stores the name, quantity
 * and price on the order itself. None of the ten has a review.
 *
 * A listing is deleted only while it still carries its lettered SKU and the
 * listing that stays is published, so an artwork can never disappear from the
 * shop altogether and an id that later holds another piece is never touched.
 * Its address is recorded first, and anyone following an old link is sent
 * (301) to the listing that stays. Runs once per revision, on the first
 * request after the deploy; what happened is recorded in the
 * af_duplicate_listings option, which health-check.yml reads.
 */
if (!defined('ABSPATH')) exit;

define('AF_DUPLICATE_LISTINGS_REV', '1');   // bump after changing the list

/** id to delete => array(its lettered SKU, the id that stays) */
function af_duplicate_listings() {
    return array(
        17212 => array('LR-070004-5030A', 34243),  // "Beautiful Lord Krishna Statue" = Ram Lalla in Pink Silk
        11541 => array('RK-010002-3050A', 11577),  // Radha Krishna Divine Love
        11617 => array('RK-010002-3050B', 11577),  // Radha Krishna Divine Love
        24592 => array('RK-010063-4030A', 34855),  // Radha Krishna Moonlit Melody
        14678 => array('RK-010090-5030A', 34147),  // Krishna Bells and Lamps Aarti
        26875 => array('HD-080001-5030B', 8398),   // Mahavatar Babaji
        7839  => array('LB-090001-3050B', 220),    // Serene Buddha with Lotus
        17543 => array('LR-070005-5030B', 15913),  // Lord Vishnu Statue (Balaji)
        31829 => array('RK-010033-3050B', 14617),  // Krishna Playing Flute
        31890 => array('TP-050013-5030C', 15730),  // Vishnu Blue Form
    );
}

add_action('wp_loaded', function () {
    try {
        if (get_option('af_duplicate_listings_rev') === AF_DUPLICATE_LISTINGS_REV) return;
        if (!function_exists('wc_get_product')) return;
        // Record first, so a failure below cannot repeat on every request.
        update_option('af_duplicate_listings_rev', AF_DUPLICATE_LISTINGS_REV, true);

        $log = array();
        $hidden = 0;
        $redirects = get_option('af_duplicate_redirects');
        if (!is_array($redirects)) $redirects = array();
        $norm = function ($s) { return strtoupper(trim((string) $s)); };

        foreach (af_duplicate_listings() as $id => $row) {
            list($sku, $keep) = $row;
            $p = wc_get_product($id);
            if (!$p) { $log[] = $id . ' not found'; continue; }
            if ($norm($p->get_sku()) !== $norm($sku)) {
                $log[] = $id . ' left alone: SKU is now "' . substr((string) $p->get_sku(), 0, 30) . '"';
                continue;
            }
            $k = wc_get_product($keep);
            if (!$k || $k->get_status() !== 'publish') {
                $log[] = $id . ' left alone: #' . $keep . ' (the listing that stays) is ' . ($k ? $k->get_status() : 'missing');
                continue;
            }
            // Remembered before the post goes, so its old link still leads
            // somewhere afterwards.
            $slug = get_post_field('post_name', $id);
            if ($slug) $redirects[$slug] = array((int) $id, (int) $keep);

            // The page cache holds its page by id; drop it before the post goes.
            do_action('litespeed_purge_post', $id);
            $was = $p->get_status();
            $p->delete(true);
            clean_post_cache($id);
            if (get_post($id)) {
                $log[] = $id . ' delete FAILED, still ' . get_post_status($id);
                continue;
            }
            $log[] = $id . ' (' . $was . ') deleted permanently (stays: #' . $keep . ')';
            $hidden++;
        }
        update_option('af_duplicate_redirects', $redirects, true);

        if ($hidden) {
            // The shop, categories and tags list products; the home page
            // bands do too. Purged by name, not the whole site (see
            // af_purge_listing_pages for why).
            do_action('litespeed_purge_posttype', 'product');
            if (function_exists('af_purge_listing_pages')) af_purge_listing_pages();
            do_action('litespeed_purge_url', home_url('/'));
            if (function_exists('af_placeholder_sitemap_clear')) $log[] = 'sitemap: ' . af_placeholder_sitemap_clear();
        }
        update_option('af_duplicate_listings', $hidden . ' deleted; ' . implode('; ', $log) . ' @ ' . gmdate('c'), false);
    } catch (\Throwable $e) {
        update_option('af_duplicate_listings', 'failed: ' . substr($e->getMessage(), 0, 160), false);
    }
}, 99);

/**
 * An old link to a deleted listing goes to the listing that stays, instead of
 * a "page not found". Only while that id is really gone and the listing that
 * stays is still published.
 */
add_action('template_redirect', function () {
    if (!is_404()) return;
    $map = get_option('af_duplicate_redirects');
    if (!is_array($map) || !$map) return;
    $path = trim((string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    $slug = '';
    if (preg_match('#(?:^|/)product/([^/]+)$#', $path, $m)) $slug = rawurldecode($m[1]);
    $qid = isset($_GET['p']) ? (int) $_GET['p'] : 0;
    foreach ($map as $s => $pair) {
        list($id, $keep) = $pair;
        if ($slug !== $s && $qid !== (int) $id) continue;
        if (get_post($id) || get_post_status($keep) !== 'publish') continue;
        $to = get_permalink($keep);
        if (!$to) return;
        nocache_headers();
        wp_safe_redirect($to, 301, 'The Art Framer duplicate listing');
        exit;
    }
}, 1);
