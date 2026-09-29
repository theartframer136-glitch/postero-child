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
 *   #19453, #24836, #7781, #8424,    -> NOT deleted: DIFFERENT artworks that were
 *   #8494, #8474                        given the same art code (0 of 3 reviewers
 *                                       said same). They need their own codes.
 *
 * The listing that stays is the one holding the plain SKU, the brochure's
 * product in the owner's sheet. None of the ten deleted here has a review;
 * the two kept listings that do (#220 and #229, five each) stay.
 *
 * REMOVED FROM THE SITE as PRIVATE. The owner asked for a permanent delete;
 * this session's safety check does not allow an irreversible delete, so they
 * are made private: gone from the shop, categories, search, the Store API,
 * the sitemap and Google, while the owner can delete them permanently in
 * wp-admin (Products > Private). If one is deleted there later, its old link
 * still redirects. Orders keep their line items. None of the ten has a review.
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

define('AF_DUPLICATE_LISTINGS_REV', '2');   // bump after changing the list

/** id to delete => array(its lettered SKU, the id that stays, its address as read live 28 Sep) */
function af_duplicate_listings() {
    return array(
        17212 => array('LR-070004-5030A', 34243, 'beautiful-lord-krishna-statue-canvas-wall-art'),  // "Beautiful Lord Krishna Statue" = Ram Lalla in Pink Silk
        11541 => array('RK-010002-3050A', 11577, 'premium-radha-krishna-love-wall-art-36x60-inches-spiritual-digital-canvas-print'),  // Radha Krishna Divine Love
        11617 => array('RK-010002-3050B', 11577, 'radha-krishna-canvas-wall-art-24x36-inches-floating-frame-premium-digital'),  // Radha Krishna Divine Love
        24592 => array('RK-010063-4030A', 34855, 'veena-under-violet-moon-canvas-wall-art'),  // Radha Krishna Moonlit Melody
        14678 => array('RK-010090-5030A', 34147, 'golden-krishna-temple-idol-canvas-wall-art'),  // Krishna Bells and Lamps Aarti
        26875 => array('HD-080001-5030B', 8398, 'mahavatar-babaji-canvas-wall-art'),   // Mahavatar Babaji
        7839  => array('LB-090001-3050B', 220, 'buddha-lotus-serenity-canvas-wall-art'),    // Serene Buddha with Lotus
        17543 => array('LR-070005-5030B', 15913, 'lord-balaji-idol-canvas-wall-art'),  // Lord Vishnu Statue (Balaji)
        31829 => array('RK-010033-3050B', 14617, 'krishna-flute-panorama-canvas-wall-art'),  // Krishna Playing Flute
        31890 => array('TP-050013-5030C', 15730, 'lord-vishnu-golden-halo-canvas-wall-art'),  // Vishnu Blue Form
    );
}

add_action('wp_loaded', function () {
    try {
        if (get_option('af_duplicate_listings_rev') === AF_DUPLICATE_LISTINGS_REV) return;
        if (!function_exists('wc_get_product')) return;
        // Record first, so a failure below cannot repeat on every request.
        update_option('af_duplicate_listings_rev', AF_DUPLICATE_LISTINGS_REV, true);

        $log = array();
        $deleted = 0;
        // Every listing's address is known in advance (the list above), and
        // each redirect is saved BEFORE its delete, so a run that stops
        // partway, or two runs at once, can never lose an old link.
        $save_redirect = function ($slug, $id, $keep) {
            $slug = strtolower(trim((string) $slug));
            if ($slug === '') return;
            $map = get_option('af_duplicate_redirects');
            if (!is_array($map)) $map = array();
            $map[$slug] = array((int) $id, (int) $keep);
            update_option('af_duplicate_redirects', $map, true);
        };
        $norm = function ($s) { return strtoupper(trim((string) $s)); };

        foreach (af_duplicate_listings() as $id => $row) {
            list($sku, $keep, $known_slug) = $row;
            $p = wc_get_product($id);
            if (!$p) {
                $save_redirect($known_slug, $id, $keep);   // already gone: its link still leads on
                $log[] = $id . ' not found';
                continue;
            }
            if ($norm($p->get_sku()) !== $norm($sku)) {
                $log[] = $id . ' left alone: SKU is now "' . substr((string) $p->get_sku(), 0, 30) . '"';
                continue;
            }
            $k = wc_get_product($keep);
            if (!$k || $k->get_status() !== 'publish') {
                $log[] = $id . ' left alone: #' . $keep . ' (the listing that stays) is ' . ($k ? $k->get_status() : 'missing');
                continue;
            }
            // Its address now, the one read live, and any it had before.
            $save_redirect($known_slug, $id, $keep);
            $save_redirect(get_post_field('post_name', $id), $id, $keep);
            foreach ((array) get_post_meta($id, '_wp_old_slug') as $old) $save_redirect($old, $id, $keep);

            $was = $p->get_status();
            if ($was === 'trash') { $log[] = $id . ' already in trash'; continue; }
            // Revision 2, owner 28 Sep: "at least move them to trash".
            // wp_trash_post keeps it restorable (Products > Trash) until the
            // trash is emptied.
            do_action('litespeed_purge_post', $id);
            wp_trash_post($id);
            $log[] = $id . ' ' . $was . ' -> ' . get_post_status($id) . ' (stays: #' . $keep . ')';
            $deleted++;
        }

        if ($deleted) {
            // The shop, categories and tags list products; the home page
            // bands do too. Purged by name, not the whole site (see
            // af_purge_listing_pages for why).
            do_action('litespeed_purge_posttype', 'product');
            if (function_exists('af_purge_listing_pages')) af_purge_listing_pages();
            do_action('litespeed_purge_url', home_url('/'));
            if (function_exists('af_placeholder_sitemap_clear')) $log[] = 'sitemap: ' . af_placeholder_sitemap_clear();
        }
        update_option('af_duplicate_listings', $deleted . ' moved to trash; ' . implode('; ', $log) . ' @ ' . gmdate('c'), false);
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
    if (preg_match('#(?:^|/)product/([^/]+)$#i', $path, $m)) $slug = strtolower(rawurldecode($m[1]));
    $qid = isset($_GET['p']) ? (int) $_GET['p'] : 0;
    foreach ($map as $s => $pair) {
        list($id, $keep) = $pair;
        if ($slug !== (string) $s && $qid !== (int) $id) continue;
        $st = get_post_status($id);
        if (($st && !in_array($st, array('private', 'trash'), true)) || get_post_status($keep) !== 'publish') continue;
        $to = get_permalink($keep);
        if (!$to) return;
        nocache_headers();
        wp_safe_redirect($to, 301, 'The Art Framer duplicate listing');
        exit;
    }
}, 1);
