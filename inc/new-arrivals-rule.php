<?php
if (!defined('ABSPATH')) exit;
/**
 * New Arrivals: today's uploads, or a random pick.
 *
 * Owner, 2026-09-08: "if today i did not upload anything then show random
 * products".
 *
 * ── WHY THIS IS NOT THE RULE I WROTE YESTERDAY ────────────────────────────
 * That one hooked woocommerce_shortcode_products_query, which is the right
 * hook for a WooCommerce row and the wrong one for this row: New Arrivals is
 * the theme's own [new_arrival_products] shortcode, and it never consults a
 * WordPress query filter. So the rule was live, correct, and attached to a
 * row nobody was looking at — which is why the band went on showing the same
 * event photos day after day.
 *
 * This works where the promo filter already proved things can be worked: on
 * the HTML the shortcode returns. That holds however it fetches its products.
 *
 * ── WHAT IT DOES ──────────────────────────────────────────────────────────
 * Anything published TODAY, in the shop's own timezone, is what the band is
 * for; when it exists, the band shows those and nothing else. When nothing
 * was published today the cards are drawn at random instead, from the widest
 * pool the shortcode will give us — it is asked for a much larger set first,
 * and if it obliges, the random pick comes from that.
 *
 * It can never empty the band: every path that cannot do better returns the
 * original HTML untouched.
 */

/** Product ids published today, in the site's timezone. Cached for an hour. */
function af_na_today_ids() {
    $ids = get_transient('af_na_today_ids');
    if (is_array($ids)) return $ids;
    $start = function_exists('current_datetime')
        ? current_datetime()->setTime(0, 0, 0)->format('Y-m-d H:i:s')
        : date('Y-m-d 00:00:00', current_time('timestamp'));
    $q = new WP_Query(array(
        'post_type'              => 'product',
        'post_status'            => 'publish',
        'posts_per_page'         => 60,
        'fields'                 => 'ids',
        'no_found_rows'          => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
        'date_query'             => array(array('after' => $start, 'inclusive' => true)),
    ));
    $ids = array_map('intval', $q->posts);
    // Short life: "today" changes, and a band that is a day stale is the
    // complaint this rule exists to answer.
    set_transient('af_na_today_ids', $ids, 15 * MINUTE_IN_SECONDS);
    return $ids;
}

/** Every card in a block of shortcode HTML, as [id => outerHTML]. */
function af_na_cards($html) {
    if (!class_exists('DOMDocument') || stripos($html, 'product') === false) return array();
    $prev = libxml_use_internal_errors(true);
    $doc  = new DOMDocument();
    $ok   = $doc->loadHTML('<?xml encoding="utf-8" ?><div id="af-na-root">' . $html . '</div>',
                           LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    if (!$ok) { libxml_clear_errors(); libxml_use_internal_errors($prev); return array(); }
    $xp = new DOMXPath($doc);
    $nodes = $xp->query("//*[@data-product-id] | //a[contains(@href,'add-to-cart=')]");
    $out = array();
    if ($nodes) {
        foreach ($nodes as $node) {
            $id = (int) $node->getAttribute('data-product-id');
            if (!$id && preg_match('/add-to-cart=(\d+)/', (string) $node->getAttribute('href'), $m)) {
                $id = (int) $m[1];
            }
            if (!$id || isset($out[$id])) continue;
            // Climb to the element wrapping this product and no other — the
            // same rule the promo filter needed, and for the same reason: one
            // step too far and you are holding the whole row.
            $card = null;
            for ($n = $node; $n && $n->nodeType === XML_ELEMENT_NODE; $n = $n->parentNode) {
                if ($n->getAttribute('id') === 'af-na-root') break;
                $inside = $xp->query(".//*[@data-product-id] | .//a[contains(@href,'add-to-cart=')]", $n);
                $seen = array();
                if ($inside) foreach ($inside as $i2) {
                    $iid = (int) $i2->getAttribute('data-product-id');
                    if (!$iid && preg_match('/add-to-cart=(\d+)/', (string) $i2->getAttribute('href'), $m2)) $iid = (int) $m2[1];
                    if ($iid) $seen[$iid] = true;
                }
                if (count($seen) > 1) break;
                $cls = ' ' . strtolower($n->getAttribute('class')) . ' ';
                if (strpos($cls, 'card') !== false || strpos($cls, 'product-item') !== false
                    || strtolower($n->nodeName) === 'li') {
                    $card = $n;
                }
            }
            if ($card) $out[$id] = $doc->saveHTML($card);
        }
    }
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    return $out;
}

/** Swap the cards inside $html for $keep (id => html), preserving the wrapper. */
function af_na_replace_cards($html, $cards, $keep_ids) {
    $kept = array();
    foreach ($keep_ids as $id) {
        if (isset($cards[$id])) $kept[] = $cards[$id];
    }
    if (!$kept) return $html;
    // The wrapper is everything before the first card and after the last, so
    // the theme's own grid, classes and inline styles all survive untouched.
    $first = null;
    foreach ($cards as $c) { $first = $c; break; }
    $start = strpos($html, $first);
    if ($start === false) return $html;
    $last = end($cards);
    $endp = strrpos($html, $last);
    if ($endp === false) return $html;
    $head = substr($html, 0, $start);
    $tail = substr($html, $endp + strlen($last));
    return $head . implode('', $kept) . $tail;
}

add_filter('do_shortcode_tag', function ($output, $tag, $attr) {
    if ($tag !== 'new_arrival_products') return $output;
    static $busy = false;
    if ($busy) return $output;                      // the wider-pool render below

    try {
        $cards = af_na_cards($output);
        if (count($cards) < 2) return $output;      // nothing to choose between
        $want  = count($cards);

        // 1. Today's uploads, if there are any in this band.
        $today = af_na_today_ids();
        if ($today) {
            $here = array_values(array_intersect(array_keys($cards), $today));
            if ($here) return af_na_replace_cards($output, $cards, $here);
        }

        // 2. Nothing today: a random pick. Ask for a wider pool first, so the
        //    randomness spans more than the dozen newest pieces; if the
        //    shortcode ignores the request, shuffle what we already have.
        $pool = $cards;
        $busy = true;
        $wide = do_shortcode('[new_arrival_products limit="60" posts_per_page="60" number="60"]');
        $busy = false;
        if (is_string($wide) && $wide !== '') {
            $wide_cards = af_na_cards($wide);
            if (count($wide_cards) > count($cards)) {
                // The wider set is a different block of HTML, so the cards are
                // put back into THIS one — same wrapper, new contents.
                $ids = array_keys($wide_cards);
                shuffle($ids);
                $pick = array_slice($ids, 0, $want);
                $html = array();
                foreach ($pick as $id) $html[] = $wide_cards[$id];
                $first = reset($cards); $last = end($cards);
                $s = strpos($output, $first); $e = strrpos($output, $last);
                if ($s !== false && $e !== false) {
                    return substr($output, 0, $s) . implode('', $html) . substr($output, $e + strlen($last));
                }
            }
        }
        $ids = array_keys($pool);
        shuffle($ids);
        return af_na_replace_cards($output, $pool, $ids);
    } catch (\Throwable $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) error_log('af-new-arrivals: ' . $e->getMessage());
        return $output;                             // never break the band
    }
}, 25, 3);   // 25: after the promo filter at 20, so flagged pictures are gone first

add_action('save_post_product', function () { delete_transient('af_na_today_ids'); }, 10, 0);
