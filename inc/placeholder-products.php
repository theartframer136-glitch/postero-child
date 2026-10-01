<?php
/**
 * Take placeholder products off public sale. Test Run 03, N-01; and, from
 * 25 Sep, the five leftover theme demo posters the owner asked to remove.
 * On 26 Sep the owner asked for all six to be deleted permanently: see
 * af_placeholder_products_delete() below. The same once-per-deploy pass
 * also puts a hidden product back in the shop: af_placeholder_products_show().
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
 * id => the art code it must still carry (from revision 7; until then, the
 * name). Bump the revision after changing this.
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
 *
 * Revision 4, 26 Sep: all six move to the delete list below, so this one is
 * empty until something else needs taking off sale.
 *
 * Revision 6, 1 Oct: the owner, with a screenshot of TMP-1246: "remove this".
 * #25240 "Vaishnava Symbols Trio Canvas Wall Art" (TMP-1246, $80, in Hindu
 * Deities) goes private the same way: gone from every public view, nothing
 * deleted, one click to publish again. Its stored name carries "&amp;" where
 * the shop prints "&", so names are compared with entities decoded
 * (af_placeholder_same_name).
 *
 * Revision 7, 1 Oct: the owner listed seven temporary codes, TMP-1246 among
 * them, with "don't delete them, make them private". All seven were
 * published, $80 each, none in the Canva brochure. The owner names them by
 * their code, so from this revision a product is matched by the art code it
 * must still carry (af_placeholder_code_key), as the show list below already
 * is: a TMP code belongs to one product and stays with it, while a long
 * title can be edited at any time. A product whose code has changed since
 * is left alone and logged. #25240 went private in revision 6 and reads
 * "already private".
 *
 * Revision 8, 1 Oct: the owner listed 45 more temporary codes, TMP-1124 to
 * TMP-1310, "make them private too". All 45 were published, $80 canvas
 * prints, none in the Canva brochure. The seven from revision 7 stay on the
 * list and read "already private". Two of the 45 are where old addresses of
 * listings deleted on 29 Sep lead (inc/retired-products.php): #31456 for
 * #30905, and #28962 for #28839 and #27695. inc/duplicate-listings.php only
 * redirects to a listing that is still published, so those three old
 * addresses answer 404 from now on, as any address of a private product does.
 *
 * Revision 9, 1 Oct: the owner, with five rows of the temporary-code sheet,
 * "remove this ... from the website": TMP-1216 (#8711, the family photo
 * collage print), TMP-1218 (#8869, the 3-panel living room set), TMP-1256
 * (#26628), TMP-1295 (#30836) and TMP-1309 (#8805, the cafe set). All five
 * published; nothing in this theme links to them by id. Private the same way,
 * nothing deleted. The 52 already on the list read "already private".
 *
 * Revision 10, 1 Oct: the owner, with one more row of the sheet, "remove this
 * also": TMP-1046 (#8669, the wooden portrait canvas frame, a custom photo
 * product, out of stock). Published; nothing in this theme links to it by
 * id. Private the same way, nothing deleted. The 57 already on the list read
 * "already private".
 */
define('AF_PLACEHOLDER_PRODUCTS_REV', '10');

function af_placeholder_products() {
    return array(
        25240 => 'TMP-1246',   // Vaishnava Symbols Trio
        22747 => 'TMP-1233',   // Ganesh Pop Art
        22016 => 'TMP-1229',   // Horses in Color Field
        29342 => 'TMP-1134',   // Kashi Vishwanath Gold Spire
        28422 => 'TMP-1120',   // Raas Leela Miniature
        23789 => 'TMP-1078',   // Radha Krishna Color Duet
        22383 => 'TMP-1071',   // Krishna Rainbow Splash
        // revision 8: the 45, in the order the owner listed them
        27325 => 'TMP-1310',   // Radha Krishna on the Branch
        31527 => 'TMP-1304',   // Krishna Cowherd Modern Art
        31456 => 'TMP-1303',   // Balaji Abstract Gold
        31395 => 'TMP-1302',   // Shiva Smoke and Trident
        31273 => 'TMP-1301',   // Palace in the Grove
        31150 => 'TMP-1299',   // Shiva Family Harmony
        31088 => 'TMP-1298',   // Vaikuntha Celestial Court
        30966 => 'TMP-1297',   // Vishnu on Shesha
        29751 => 'TMP-1289',   // Sacred Cow Relief Art
        30093 => 'TMP-1290',   // Horses of the Dust Plains
        30154 => 'TMP-1291',   // Ganesha Dawn Silhouette
        30276 => 'TMP-1292',   // Red Sun Winter Tree
        30338 => 'TMP-1293',   // Shiva Parivar in Clouds
        30775 => 'TMP-1294',   // Marigold Dreams Portrait
        29159 => 'TMP-1280',   // Horse Studies Collage
        29220 => 'TMP-1281',   // Vishnu Cosmic Lotus
        29281 => 'TMP-1282',   // Balaji Divine Collage
        29395 => 'TMP-1283',   // Pichwai Ganesha Fountains
        29456 => 'TMP-1284',   // Maratha Pride with Lion
        29517 => 'TMP-1285',   // Lone Tree Between Worlds
        29578 => 'TMP-1286',   // Twin Faces of Serenity
        28473 => 'TMP-1273',   // Quiet Harbor Minimal
        28534 => 'TMP-1274',   // Kirtan Celebration
        28717 => 'TMP-1275',   // Vishnu in Golden Garlands
        28962 => 'TMP-1278',   // Savanna Golden Hour
        27133 => 'TMP-1257',   // Geometric Falls Sunrise
        27194 => 'TMP-1258',   // Murmuration at Dusk
        27264 => 'TMP-1259',   // Nataraja Bronze Glory
        27388 => 'TMP-1260',   // Krishna Minimal Splash
        27449 => 'TMP-1261',   // Buddha Offering Lotus
        27510 => 'TMP-1262',   // Cubist Buddha Visage
        27572 => 'TMP-1263',   // Two Horses Cubist
        27633 => 'TMP-1264',   // Flight Path Reverie
        27750 => 'TMP-1266',   // Krishna and the Monkeys Folk
        27811 => 'TMP-1267',   // Buddha Among Pink Lotuses
        27981 => 'TMP-1268',   // Crimson Veil Portrait
        28103 => 'TMP-1269',   // Krishna Serene Face
        28164 => 'TMP-1270',   // Temple Bells and Cows
        28225 => 'TMP-1271',   // Nandi and the Jyotirlingas
        30032 => 'TMP-1142',   // Devotion in Color Mist
        30409 => 'TMP-1147',   // Krishna's Temple Gardens
        30714 => 'TMP-1148',   // Krishna Sudama Friendship
        31027 => 'TMP-1153',   // Radha Krishna Graphite Duet
        28656 => 'TMP-1124',   // Radha's Mirror of Krishna
        29084 => 'TMP-1130',   // Shiva of the Ghats
        // revision 9: five more from the temporary-code sheet
        8711  => 'TMP-1216',   // Stunning Personalized Family Photo Collage
        8869  => 'TMP-1218',   // Modern Living Room Wall Decor Canvas Set, 3 panel
        26628 => 'TMP-1256',   // Floral Arch Wall Art
        30836 => 'TMP-1295',   // Veena Player with Peacock
        8805  => 'TMP-1309',   // Modern Cafe Decor Wall Art Set
        // revision 10: one more from the sheet
        8669  => 'TMP-1046',   // Premium Wooden Portrait Canvas Frame
    );
}

/**
 * An art code as written, reduced so that "HD - 080004-5030", "HD-080004-5030"
 * and "HD – 080004-5030" compare equal: spaces, hyphens and en dashes out,
 * upper case.
 */
function af_placeholder_code_key($code) {
    return strtoupper(preg_replace('/[\s\x{2013}-]+/u', '', (string) $code));
}

/**
 * Does a product still carry the name it was listed under? Case and runs of
 * spaces aside, and with HTML entities decoded on both sides: WordPress may
 * store a title's "&" as "&amp;" (#25240 does) or a dash as "&#8211;", and
 * that must not decide whether a product is touched.
 */
function af_placeholder_same_name($actual, $listed) {
    $norm = function ($s) {
        $s = html_entity_decode((string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $s));
    };
    return strcasecmp($norm($actual), $norm($listed)) === 0;
}

/**
 * id => the name it must still carry: products to DELETE PERMANENTLY.
 *
 * Owner, 26 Sep: remove permanently the six products made private above,
 * #11491 "test" (TMP-1055) and the five theme demo posters (TMP-1000 to
 * TMP-1004). Deleted outright, not trashed: WooCommerce's own delete, so the
 * product lookup tables and transients go with the post. Their pictures stay
 * in the Media Library (the posters share the theme's stand-in picture).
 * Orders that ever included one keep their line items: WooCommerce stores the
 * name, quantity and price on the order itself.
 *
 * A product is deleted only if it is still PRIVATE and still carries this
 * exact name. One that has been published again, or renamed, since the owner
 * asked is left alone and the log says so; deleting cannot be undone, so it
 * only ever removes what was already off the site.
 *
 * Revision 4 deleted all six at 2026-09-26 08:16:36 UTC (deploy 1365; the
 * health check read "deleted permanently" six times and 569 products). The
 * list is empty from revision 5, so later revisions do not report them again.
 */
function af_placeholder_products_delete() {
    return array();
}

/**
 * id => the art code it must still carry: products to put BACK in the shop,
 * catalog visibility "Shop and search results".
 *
 * Owner, 26 Sep: make #3362 visible. It is the brochure's product for Canva
 * page 170, HD-080004-5030 "Divine Lord Ganesha": published, in stock, $100,
 * its page and Add to Cart working, but set to "Catalog visibility: Hidden",
 * so the shop, its categories and search never showed it. It is the only
 * one of the 569 products set that way, since it was created on 29 Jun 2023,
 * and nothing in this theme or the deploy writes it; it was set by hand.
 *
 * Changed only while it is still published, still hidden, and still carries
 * this art code (matched without spaces or dash style), so an id that later
 * holds another piece is never touched.
 */
function af_placeholder_products_show() {
    return array(
        3362 => 'HD-080004-5030',
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
        foreach (af_placeholder_products() as $id => $code) {
            $product = wc_get_product($id);
            if (!$product) {
                $log[] = $id . ' not found';
                continue;
            }
            $status = $product->get_status();
            $has = (string) get_post_meta($id, '_taf_art_code', true);
            if (af_placeholder_code_key($has) !== af_placeholder_code_key($code)) {
                $log[] = $id . ' left alone: art code is now "' . substr($has, 0, 30) . '"';
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
        $deleted = 0;
        foreach (af_placeholder_products_delete() as $id => $name) {
            $product = wc_get_product($id);
            if (!$product) {
                $log[] = $id . ' not found (already deleted)';
                continue;
            }
            $status = $product->get_status();
            $actual = trim((string) $product->get_name());
            if (!af_placeholder_same_name($actual, $name)) {
                $log[] = $id . ' left alone: now named "' . substr($actual, 0, 60) . '"';
                continue;
            }
            if ($status !== 'private') {
                $log[] = $id . ' left alone: ' . $status . ', not private';
                continue;
            }
            $product->delete(true);
            clean_post_cache($id);
            if (get_post($id)) {
                $log[] = $id . ' delete FAILED, still ' . get_post_status($id);
                continue;
            }
            $log[] = $id . ' deleted permanently';
            $deleted++;
        }
        $shown = 0;
        foreach (af_placeholder_products_show() as $id => $code) {
            $product = wc_get_product($id);
            if (!$product) {
                $log[] = $id . ' not found';
                continue;
            }
            $has = (string) get_post_meta($id, '_taf_art_code', true);
            if (af_placeholder_code_key($has) !== af_placeholder_code_key($code)) {
                $log[] = $id . ' left alone: art code is now "' . substr($has, 0, 30) . '"';
                continue;
            }
            if ($product->get_status() !== 'publish') {
                $log[] = $id . ' left alone: ' . $product->get_status() . ', not published';
                continue;
            }
            $was = $product->get_catalog_visibility();
            if ($was === 'visible') {
                $log[] = $id . ' already visible';
                continue;
            }
            $product->set_catalog_visibility('visible');
            $product->save();
            $log[] = $id . ' catalog visibility ' . $was . ' -> ' . wc_get_product($id)->get_catalog_visibility();
            $shown++;
        }
        if ($deleted || $shown) {
            do_action('litespeed_purge_posttype', 'product');
            if (function_exists('wc_get_page_permalink')) {
                do_action('litespeed_purge_url', wc_get_page_permalink('shop'));
            }
        }
        if ($hidden || $deleted || $shown) {
            $log[] = 'sitemap: ' . af_placeholder_sitemap_clear();
        }
        update_option('af_placeholder_products', implode('; ', $log) . ' @ ' . gmdate('c'), false);
    } catch (\Throwable $e) {
        update_option('af_placeholder_products', 'failed: ' . substr($e->getMessage(), 0, 120), false);
    }
}, 99);
