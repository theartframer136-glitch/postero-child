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
 *   SAME artwork, 3 of 3 reviewers  -> hidden here (10 listings)
 *   #7824 Bal Krishna Flute          -> NOT hidden: reviewers split 2 to 1, and it
 *                                       holds the pair's only reviews (2); the
 *                                       product that would stay has none
 *   #19453, #24836, #7781, #8424,    -> NOT hidden: DIFFERENT artworks that were
 *   #8494, #8474                        given the same art code (0 of 3 reviewers
 *                                       said same). They need their own codes.
 *
 * The listing that stays is the one holding the plain SKU, the brochure's
 * product in the owner's sheet. None of the ten hidden here has a review;
 * the two kept listings that do (#220 and #229, five each) stay.
 *
 * PRIVATE, not deleted, the same as inc/placeholder-products.php: gone from
 * the shop, categories, search, the Store API and the sitemap; nothing is
 * lost; publishing it again in wp-admin is one click. Orders that include one
 * keep their line items.
 *
 * A listing is hidden only while it still carries its lettered SKU, is still
 * published, and the listing that stays is published too, so an artwork can
 * never disappear from the shop altogether and an id that later holds another
 * piece is never touched. Anyone following an old link to a hidden listing is
 * sent (301) to the listing that stays. Runs once per revision, on the first
 * request after the deploy; what happened is recorded in the
 * af_duplicate_listings option.
 */
if (!defined('ABSPATH')) exit;

define('AF_DUPLICATE_LISTINGS_REV', '1');

/** hidden id => array(its lettered SKU, the id that stays) */
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
            // Remembered even if it is already private, so its old link still
            // leads somewhere.
            $slug = get_post_field('post_name', $id);
            if ($slug) $redirects[$slug] = array((int) $id, (int) $keep);

            if ($p->get_status() !== 'publish') {
                $log[] = $id . ' already ' . $p->get_status();
                continue;
            }
            $p->set_status('private');
            $p->save();
            do_action('litespeed_purge_post', $id);
            $log[] = $id . ' publish -> ' . get_post_status($id) . ' (stays: #' . $keep . ')';
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
        update_option('af_duplicate_listings', $hidden . ' hidden; ' . implode('; ', $log) . ' @ ' . gmdate('c'), false);
    } catch (\Throwable $e) {
        update_option('af_duplicate_listings', 'failed: ' . substr($e->getMessage(), 0, 160), false);
    }
}, 99);

/**
 * An old link to a hidden listing goes to the listing that stays, instead of
 * a "page not found". Only while the hidden one is still private and the one
 * that stays is still published, so publishing a listing again in wp-admin
 * brings its own page straight back.
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
        if (get_post_status($id) !== 'private' || get_post_status($keep) !== 'publish') return;
        $to = get_permalink($keep);
        if (!$to) return;
        nocache_headers();
        wp_safe_redirect($to, 301, 'The Art Framer duplicate listing');
        exit;
    }
}, 1);
