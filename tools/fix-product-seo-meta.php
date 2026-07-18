<?php
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
    // Target <= 60 chars, must lead with the product's real name.
    $t_cur  = (string) get_post_meta($pid, 'rank_math_title', true);
    $brand  = ' | The Art Framer';
    $t_new  = (mb_strlen($core . $brand) <= 60) ? $core . $brand : $trim_words($core, 60);
    $t_lead = mb_strtolower(mb_substr($core, 0, 18));
    $t_bad  = ($t_cur === '')
        || mb_strlen($t_cur) > 65
        || ($t_lead !== '' && mb_stripos($t_cur, $t_lead) !== 0); // wrong/mismatched name
    if ($t_bad && $t_cur !== $t_new) {
        update_post_meta($pid, 'rank_math_title', $t_new);
        $n_title++;
    }

    // ── meta description ─────────────────────────────────────────
    // 120-155 chars, keyword first, complete sentences, no mid-word cut.
    $d_cur = (string) get_post_meta($pid, 'rank_math_description', true);
    $tails = array(
        'premium canvas wall art with archival, fade-resistant inks. Multiple sizes and frame options, gallery-wrapped and ready to hang.',
        'premium canvas wall art, archival inks, multiple sizes and frames. Gallery-wrapped and ready to hang.',
        'premium canvas wall art, archival inks and ready to hang. Free US shipping.',
        'premium canvas wall art, ready to hang.',
    );
    $d_new = '';
    foreach ($tails as $tail) {
        $cand = $core . ' — ' . $tail;
        if (mb_strlen($cand) <= 155) { $d_new = $cand; break; }
    }
    if ($d_new === '') $d_new = $trim_words($core . ' — premium canvas wall art, ready to hang.', 155);

    $d_len  = mb_strlen($d_cur);
    $d_last = $d_cur === '' ? '' : mb_substr(rtrim($d_cur), -1);
    $d_bad  = ($d_cur === '')
        || $d_len > 160
        || $d_len < 110
        || !in_array($d_last, array('.', '!', '?'), true); // truncated mid-word
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
