<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Repair product SEO meta (run on deploy via wp eval-file).
 *
 * The product importer wrote SEO meta that fails Rank Math's checks
 * and, worse, is sometimes plain wrong:
 *   - titles 145-165 chars (Google shows ~60, so they truncate)
 *   - titles built from the WRONG name — e.g. the Lord Balaji product
 *     was titled "3x5 V Canvas Wall Art ...", and Shiva in Moonlit
 *     Cave was titled "Shiva Meditation Art ..."
 *   - descriptions hard-cut at ~155 chars mid-word ("Multiple f")
 *   - focus keyword set to the ENTIRE 150-char product title, which
 *     can never match the slug/title/content -> low score
 *
 * This rebuilds title / description / focus keyword from the product's
 * OWN name, and only where the current value fails a quality check —
 * hand-written meta that is already good is left alone.
 *
 * Product NAMES (the customer-facing H1) are NOT touched; that is a
 * merchandising decision for the owner, not an SEO script.
 *
 * USA-MARKET PASS (Oct 2026). Three more rules, each limited to copy this
 * script itself wrote, so hand-written meta is still left alone:
 *   - a description may never say "free shipping" (owner's rule: the shop
 *     does not ship free, the cost is shown at checkout). The old tail that
 *     said so is gone, and any description still carrying it is rewritten;
 *   - a product in a Hindu deity or Vastu category gets a description that
 *     names what the piece is and where it hangs ("Radha Krishna canvas art
 *     for the living room or pooja room ... Made in India, ships across the
 *     USA") instead of the generic canvas tail;
 *   - the SEO title gains "Canvas Wall Art" when the product name does not
 *     already say canvas and the result still fits in 60 characters.
 *
 * Idempotent.
 */

if (!defined('ABSPATH')) exit;

$log = function ($m) { echo "[pseo] $m\n"; };

/** Core product name: drop size/variant/marketing tail. */
$core_name = function ($title) {
    $t = html_entity_decode(wp_strip_all_tags((string) $title), ENT_QUOTES | ENT_HTML5);
    $t = str_replace(array('×', '–', '—'), array('x', '-', '-'), $t);
    // cut at the first size token ("3x4 Feet", "36x60 Inches", "3 x 4")
    if (preg_match('/\s\d+\s*x\s*\d+\s*(feet|foot|inch|inches|in|ft|cm|mm)?\b/i', $t, $m, PREG_OFFSET_CAPTURE)) {
        $t = substr($t, 0, $m[0][1]);
    }
    // then cut at the first " - " separator (marketing tail)
    $parts = preg_split('/\s+-\s+/', $t);
    if ($parts && trim($parts[0]) !== '') $t = $parts[0];
    $t = trim(preg_replace('/\s{2,}/', ' ', $t), " \t\n\r\0\x0B-–—|,");
    return $t;
};

/** Trim to $max without cutting a word; never leaves dangling punctuation. */
$trim_words = function ($text, $max) {
    $text = trim((string) $text);
    if (mb_strlen($text) <= $max) return $text;
    $cut = mb_substr($text, 0, $max);
    $sp  = mb_strrpos($cut, ' ');
    if ($sp !== false && $sp > 0) $cut = mb_substr($cut, 0, $sp);
    return rtrim($cut, " ,.;:-–—") . '.';
};

/**
 * What a piece in a deity or Vastu category is, and where it hangs, for the
 * meta description. Keyed by product_cat slug; the first match in this order
 * wins, so a Lakshmi-Ganesha piece that is also under Hindu Deities reads as
 * Lakshmi Ganesh. Every phrase is written for an Indian family in the USA.
 */
$cat_tails = array(
    'radha-krishna'   => 'Radha Krishna canvas art for the living room, bedroom or pooja room.',
    'lakshmi-ganesha' => 'Lakshmi Ganesh canvas art for the pooja room, entrance or Diwali gifting.',
    'seven-horses'    => '7 running horses painting as per Vastu for the living room or office.',
    'vaastu-art'      => 'Vastu painting for the living room, office or entrance.',
    'lord-shiva'      => 'Lord Shiva canvas art for the living room, meditation space or pooja room.',
    'tirupati-balaji' => 'Tirupati Balaji canvas art for the pooja room or living room.',
    'lord-rama'       => 'Lord Ram canvas art for the pooja room or living room.',
    'buddha'          => 'Buddha canvas art for the living room, meditation room or yoga space.',
    'sikh-art'        => 'Sikh canvas art for the living room or prayer room.',
    'swaminarayan'    => 'Swaminarayan canvas art for the pooja room or living room.',
    'pichwai-art'     => 'Pichwai canvas art for the living room or pooja room.',
    'hindu-deities'   => 'Hindu god canvas art for the living room or pooja room.',
    'indian-culture'  => 'Indian canvas art for the living room or entrance.',
    'christian-art'   => 'Christian canvas art for the living room or prayer corner.',
);
// The generic tails every earlier run could have written; a description
// equal to core + one of these is this script's own and may be improved.
$generic_tails = array(
    'premium canvas wall art with archival, fade-resistant inks. Multiple sizes and frame options, gallery-wrapped and ready to hang.',
    'premium canvas wall art, archival inks, multiple sizes and frames. Gallery-wrapped and ready to hang.',
    'premium canvas wall art, archival inks and ready to hang. Free US shipping.',
    'premium canvas wall art, archival inks and ready to hang. Ships across the USA.',
    'premium canvas wall art, ready to hang.',
);
/** Categories whose products are art (not frames, bars, banners, certificates). */
$art_roots = array('digital-canvas-prints', 'all-art-prints', 'gallery-prints', 'canvas-prints', 'wall-art', 'framed-canvases', 'personalised-prints');

$products = get_posts(array(
    'post_type'      => 'product',
    'post_status'    => array('publish', 'draft'),
    'posts_per_page' => -1,
    'fields'         => 'ids',
));
$log('products found: ' . count($products));

$n_title = $n_desc = $n_kw = $n_ok = 0;

foreach ($products as $pid) {
    $post = get_post($pid);
    if (!$post) continue;

    $core = $core_name($post->post_title);
    if ($core === '') continue;
    $core_lc = mb_strtolower($core);

    $slugs = wp_get_post_terms($pid, 'product_cat', array('fields' => 'slugs'));
    $slugs = is_array($slugs) ? $slugs : array();
    $cat_tail = null;
    foreach ($cat_tails as $slug => $phrase) { if (in_array($slug, $slugs, true)) { $cat_tail = $phrase; break; } }
    $is_art = $cat_tail !== null || (bool) array_intersect($slugs, $art_roots);
    // Earlier runs derived the name without its "Canvas Wall Art" tail; both
    // forms count as this script's own when deciding what may be improved.
    $core_short = trim(preg_replace('/\s+canvas\s+wall\s+art$/iu', '', $core));

    // ── focus keyword ────────────────────────────────────────────
    // Must be short and actually present in slug/title/content.
    $kw_cur = (string) get_post_meta($pid, 'rank_math_focus_keyword', true);
    $kw_bad = ($kw_cur === '')
        || mb_strlen($kw_cur) > 60
        || mb_stripos($post->post_title, $kw_cur) === false;
    if ($kw_bad && $kw_cur !== $core_lc) {
        update_post_meta($pid, 'rank_math_focus_keyword', $core_lc);
        $n_kw++;
    }

    // ── SEO title ────────────────────────────────────────────────
    // Target <= 60 chars, must lead with the product's real name. A name
    // that does not say "canvas" gets " Canvas Wall Art" (the head term
    // every deity and Vastu search carries) when the result still fits.
    $t_cur  = (string) get_post_meta($pid, 'rank_math_title', true);
    $brand  = ' | The Art Framer';
    $core_t = $core;
    if ($is_art && mb_stripos($core, 'canvas') === false && mb_stripos($core, 'frame') === false
        && mb_strlen($core . ' Canvas Wall Art') <= 60) {
        $core_t = $core . ' Canvas Wall Art';
    }
    if (mb_strlen($core_t . $brand) <= 60)          $t_new = $core_t . $brand;
    elseif (mb_strlen($core_t) <= 60)               $t_new = $core_t;
    elseif (mb_strlen($core_short . $brand) <= 60)  $t_new = $core_short . $brand;
    else                                            $t_new = $trim_words($core_short, 60);
    $t_lead = mb_strtolower(mb_substr($core_short, 0, 18));
    $t_mine = ($t_cur === $core . $brand || $t_cur === $core_short . $brand); // patterns earlier runs wrote
    $t_bad  = ($t_cur === '')
        || mb_strlen($t_cur) > 65
        || ($t_lead !== '' && mb_stripos($t_cur, $t_lead) !== 0)          // wrong/mismatched name
        || ($t_mine && $is_art && mb_stripos($t_cur, 'canvas') === false); // ours, and missing the head term
    if ($t_bad && $t_cur !== $t_new) {
        update_post_meta($pid, 'rank_math_title', $t_new);
        $n_title++;
    }

    // ── meta description ─────────────────────────────────────────
    // 120-155 chars, keyword first, complete sentences, no mid-word cut,
    // and never "free shipping". A deity or Vastu piece says what it is
    // and where it hangs; everything else keeps the canvas tail.
    $d_cur = (string) get_post_meta($pid, 'rank_math_description', true);
    if ($cat_tail !== null) {
        // The tail already says "canvas art", so the name drops its own
        // "Canvas Wall Art" to make room for where the piece hangs.
        $d_lead = $core_short . ': ';
        $tails  = array(
            $cat_tail . ' Museum-grade canvas, floating frame, ready to hang. Made in India, ships across the USA.',
            $cat_tail . ' Floating frame, ready to hang. Made in India, ships across the USA.',
            $cat_tail . ' Ready to hang. Ships across the USA.',
            $cat_tail,
        );
    } elseif ($is_art) {
        $d_lead = $core . ' — ';
        $tails  = array(
            'premium canvas wall art with archival, fade-resistant inks. Multiple sizes and frame options, gallery-wrapped and ready to hang.',
            'premium canvas wall art, archival inks, multiple sizes and frames. Gallery-wrapped and ready to hang.',
            'premium canvas wall art, archival inks and ready to hang. Ships across the USA.',
            'premium canvas wall art, ready to hang.',
        );
    } else {
        // Frames, bars, rolls, banners, certificates: not wall art.
        $d_lead = $core . ' — ';
        $tails  = array(
            'made to order by The Art Framer, a Delaware studio, and shipped across the USA. Shipping cost shown at checkout.',
            'made to order by The Art Framer and shipped across the USA.',
            'from The Art Framer, USA.',
        );
    }
    $d_new = '';
    foreach ($tails as $tail) {
        $cand = $d_lead . $tail;
        if (mb_strlen($cand) <= 155) { $d_new = $cand; break; }
    }
    if ($d_new === '') $d_new = $trim_words($core_short . ' — ready to hang, ships across the USA.', 155);

    $d_len  = mb_strlen($d_cur);
    $d_last = $d_cur === '' ? '' : mb_substr(rtrim($d_cur), -1);
    $d_mine = false; // written by an earlier run of this script, so ours to improve
    foreach ($generic_tails as $g) { if ($d_cur === $core . ' — ' . $g || $d_cur === $core_short . ' — ' . $g) { $d_mine = true; break; } }
    $d_bad  = ($d_cur === '')
        || $d_len > 160
        || $d_len < 110
        || !in_array($d_last, array('.', '!', '?'), true) // truncated mid-word
        || preg_match('/free\s+(us\s+|usa\s+)?(shipping|delivery)/i', $d_cur) // owner's rule
        || ($d_mine && ($cat_tail !== null || !$is_art)); // ours, and a better tail now exists
    if ($d_bad && $d_cur !== $d_new) {
        update_post_meta($pid, 'rank_math_description', $d_new);
        $n_desc++;
    }

    if (!$t_bad && !$d_bad && !$kw_bad) $n_ok++;
}

$log("titles rewritten: $n_title");
$log("descriptions rewritten: $n_desc");
$log("focus keywords set: $n_kw");
$log("already healthy: $n_ok");
$log('done');
