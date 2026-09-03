<?php
if (!defined('ABSPATH')) exit;
/**
 * Keep the advertising pictures out of the selling bands.
 *
 * Owner, 2026-09-03: "all the image showing are present on the Exclusively
 * Customised Creations they should not be shown on New Arrivals and Trending
 * Today because this images are not products they are the images for
 * advertisement".
 *
 * WHY THE FIRST ATTEMPT DID NOTHING
 * inc/promo-only.php excluded them through pre_get_posts. It deployed green and
 * changed nothing, because [trending_now_cards] does not build its list with
 * WP_Query — it goes to the database directly, so no WordPress query hook is
 * ever consulted. Filtering a query only works on things that run a query.
 *
 * So this works on what actually reaches the page: the rendered HTML of those
 * two shortcodes, with the cards for flagged pictures taken out. That holds
 * however either shortcode fetches its products, now or later.
 *
 * A card is found by the product id it already carries — data-product-id, an
 * add-to-wishlist link, or an add-to-cart link — and then the smallest
 * enclosing card element is removed. Parsed with DOMDocument rather than a
 * regular expression, because these cards nest several divs deep and a regex
 * cannot match a closing tag reliably.
 *
 * Customised Creations is deliberately NOT in the list: that band is where the
 * pictures are meant to be.
 */

function af_promo_hidden_shortcodes() {
    return (array) apply_filters('af_promo_hidden_shortcodes',
        array('new_arrival_products', 'trending_now_cards'));
}

/** Ids marked as advertising-only. Cached; never called from a query hook. */
function af_promo_hidden_ids() {
    $ids = get_transient('af_promo_hidden_ids');
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
        set_transient('af_promo_hidden_ids', $ids, HOUR_IN_SECONDS);
    }
    return $ids;
}

/**
 * Strip the cards for $ids out of a block of shortcode HTML.
 * Returns the original string untouched if anything at all goes wrong.
 */
function af_promo_strip_cards($html, $ids) {
    if (!$ids || stripos($html, 'product') === false) return $html;
    if (!class_exists('DOMDocument')) return $html;

    // Cheap pre-check: if no flagged id appears anywhere, do no parsing at all.
    $seen = false;
    foreach ($ids as $id) {
        if (strpos($html, (string) $id) !== false) { $seen = true; break; }
    }
    if (!$seen) return $html;

    $prev = libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $ok = $doc->loadHTML('<?xml encoding="utf-8" ?><div id="af-promo-root">' . $html . '</div>',
                         LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    if (!$ok) { libxml_clear_errors(); libxml_use_internal_errors($prev); return $html; }
    $xp = new DOMXPath($doc);

    $removed = 0;
    foreach ($ids as $id) {
        $id = (int) $id;
        $q = "//*[@data-product-id='$id']"
           . " | //a[contains(@href,'add-to-wishlist=$id')]"
           . " | //a[contains(@href,'add-to-cart=$id')]"
           . " | //*[contains(concat(' ',normalize-space(@class),' '),' post-$id ')]";
        $nodes = $xp->query($q);
        if (!$nodes) continue;
        foreach ($nodes as $node) {
            // climb to the smallest enclosing card
            $card = null;
            for ($n = $node; $n && $n->nodeType === XML_ELEMENT_NODE; $n = $n->parentNode) {
                if ($n->getAttribute('id') === 'af-promo-root') break;
                $cls = ' ' . strtolower($n->getAttribute('class')) . ' ';
                if (strpos($cls, 'card') !== false || strpos($cls, 'product-item') !== false
                    || strtolower($n->nodeName) === 'li') {
                    $card = $n;                    // keep climbing: take the OUTERMOST card
                }
            }
            if ($card && $card->parentNode) { $card->parentNode->removeChild($card); $removed++; }
        }
    }
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    if (!$removed) return $html;

    $root = $doc->getElementById('af-promo-root');
    if (!$root) return $html;
    $out = '';
    foreach ($root->childNodes as $child) $out .= $doc->saveHTML($child);
    return $out !== '' ? $out : $html;
}

add_filter('do_shortcode_tag', function ($output, $tag) {
    if (!in_array($tag, af_promo_hidden_shortcodes(), true)) return $output;
    try {
        return af_promo_strip_cards($output, af_promo_hidden_ids());
    } catch (\Throwable $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) error_log('af-promo-hide: ' . $e->getMessage());
        return $output;                            // never break a band over this
    }
}, 20, 2);

add_action('save_post_product', function () { delete_transient('af_promo_hidden_ids'); }, 10, 0);
