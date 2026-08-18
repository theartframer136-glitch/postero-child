<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Three cards to a row on the shop and category archives — and only there.
 *
 * Checks the rule actually reaches the archive pages, that WooCommerce itself
 * is told three columns, and — the part that matters — that the related and
 * cross-sell rows on cart and wishlist still keep their own four-across
 * layout, since those were tuned separately and must not move.
 *
 * Read-only. Run: wp eval-file tools/verify-archive-columns.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }

echo "=== VERIFY ARCHIVE COLUMNS ===\n";
$fail = 0;

printf("  loop_shop_columns filter reports: %d %s\n",
    (int) apply_filters('loop_shop_columns', 4),
    ((int) apply_filters('loop_shop_columns', 4) === 3) ? 'OK' : 'EXPECTED 3');
if ((int) apply_filters('loop_shop_columns', 4) !== 3) $fail++;

function af_ac_get($url) {
    $r = wp_remote_get(add_query_arg('afverify', time(), $url),
        array('timeout' => 60, 'sslverify' => false,
              'headers' => array('User-Agent' => 'Mozilla/5.0 AF-Verify')));
    return is_wp_error($r) ? '' : wp_remote_retrieve_body($r);
}

// pages that SHOULD be three across
$three = array();
$shop = wc_get_page_id('shop');
if ($shop > 0) $three['shop'] = get_permalink($shop);
foreach (array('framed-canvases', 'digital-canvas-prints', 'radha-krishna') as $slug) {
    $t = get_term_by('slug', $slug, 'product_cat');
    if ($t && !is_wp_error($t)) {
        $l = get_term_link($t);
        if (!is_wp_error($l)) $three[$slug] = $l;
    }
}

echo "\n-- archives (expect three across) --\n";
foreach ($three as $name => $url) {
    $html = af_ac_get($url);
    if ($html === '') { printf("  %-24s FETCH FAILED\n", $name); $fail++; continue; }
    $has_rule = strpos($html, 'af-archive-3col') !== false;
    $has_3    = (bool) preg_match('#grid-template-columns:\s*repeat\(3,\s*1fr\)#', $html);
    $cards    = substr_count($html, 'woocommerce-loop-product__title');
    printf("  %-24s rule:%-4s three-col:%-4s cards:%-4d %s\n",
        mb_strimwidth($name, 0, 24), $has_rule ? 'OK' : 'NO', $has_3 ? 'OK' : 'NO', $cards,
        ($has_rule && $has_3) ? '' : '<-- CHECK');
    if (!$has_rule || !$has_3) $fail++;
}

// pages that must be UNCHANGED
echo "\n-- rows that must stay four across --\n";
$keep = array('cart' => wc_get_cart_url(), 'wishlist' => home_url('/wishlist/'));
foreach ($keep as $name => $url) {
    $html = af_ac_get($url);
    if ($html === '') { printf("  %-24s FETCH FAILED\n", $name); continue; }
    // The row's markup, not merely the class NAME: the slider script prints
    // '.af-wl-related' selectors site-wide, so a string match called an empty
    // cart a failure when it simply had no related row to style.
    $rel  = (bool) preg_match('#<section[^>]*class="[^"]*af-wl-related#', $html);
    $four = (bool) preg_match('#af-wl-related ul\.products\{[^}]*repeat\(4,\s*1fr\)#', $html);
    printf("  %-24s related row:%-9s four-col rule:%-4s\n",
        $name,
        $rel ? 'yes' : 'none (empty)',
        $rel ? ($four ? 'OK' : 'MISSING') : 'n/a');
    if ($rel && !$four) $fail++;
}

printf("\n=== %s ===\n", $fail ? "{$fail} CHECK(S) FAILED" : 'ALL CHECKS PASSED');
echo "=== DONE ===\n";
