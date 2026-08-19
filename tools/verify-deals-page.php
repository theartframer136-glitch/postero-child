<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Prove /clearance/ shows exactly the 40%-plus products, as the shop page.
 *
 * Counts the qualifying ids, spot-checks their arithmetic against _price and
 * _regular_price, fetches the live /clearance/ page and confirms it renders
 * shop cards with the right title, and confirms a product BELOW the threshold
 * is not on it. Flushes rewrite rules first so the pretty URL resolves after
 * deploy. Read-only apart from that flush.
 *
 * Run: wp eval-file tools/verify-deals-page.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }

echo "=== VERIFY CLEARANCE PAGE ===\n";
$fail = 0;

flush_rewrite_rules(false);
echo "  rewrite rules flushed\n";
delete_transient('af_deal_ids_40');

if (!function_exists('af_deal_ids')) {
    echo "  inc/deals-page.php not loaded  FAIL\n=== DONE ===\n"; return;
}

$ids = af_deal_ids(40);
printf("\n  products at 40%%+ off: %d\n", count($ids));
if (!$ids) { echo "  (none qualify — the page will show the shop's empty state)\n"; }

// spot-check three: the arithmetic must hold on the product's own meta
foreach (array_slice($ids, 0, 3) as $pid) {
    $p = wc_get_product($pid);
    if (!$p) continue;
    $price = (float) $p->get_price();
    $reg   = (float) $p->get_regular_price();
    if ($reg <= 0 && $p->is_type('variable')) {
        $reg   = (float) $p->get_variation_regular_price('max');
        $price = (float) $p->get_variation_price('min');
    }
    $pct = $reg > 0 ? ($reg - $price) / $reg * 100 : 0;
    printf("  #%-6d %-46s $%s of $%s = %d%%  %s\n", $pid,
        mb_strimwidth($p->get_name(), 0, 46, '…'), $price, $reg, round($pct),
        $pct + 0.5 >= 40 ? 'OK' : 'BELOW THRESHOLD');
    if ($pct + 0.5 < 40) $fail++;
}

// one product below threshold, to prove exclusion
global $wpdb;
$below = null;
foreach ($wpdb->get_col("SELECT ID FROM {$wpdb->posts}
        WHERE post_type='product' AND post_status='publish' LIMIT 400") as $pid) {
    if (in_array((int) $pid, $ids, true)) continue;
    $below = (int) $pid; break;
}

$url = home_url('/clearance/');
$r = wp_remote_get(add_query_arg('afverify', time(), $url),
    array('timeout' => 60, 'sslverify' => false, 'headers' => array('User-Agent' => 'Mozilla/5.0 AF-Verify')));
if (is_wp_error($r)) {
    printf("\n  %s FETCH FAILED: %s\n", $url, $r->get_error_message()); $fail++;
} else {
    $html = wp_remote_retrieve_body($r);
    $code = (int) wp_remote_retrieve_response_code($r);
    $cards = substr_count($html, 'woocommerce-loop-product__title');
    $titled = strpos($html, 'Stock Clearance') !== false;
    printf("\n  %s -> HTTP %d, cards on page 1: %d, titled: %s\n",
        $url, $code, $cards, $titled ? 'OK' : 'MISSING');
    if ($code !== 200) $fail++;
    if ($ids && $cards < 1) { echo "  qualifying products exist but none rendered  FAIL\n"; $fail++; }
    if (!$titled) $fail++;
    if ($below) {
        $bp = wc_get_product($below);
        $on = $bp && strpos($html, 'data-product_id="' . $below . '"') !== false;
        printf("  below-threshold product #%d on the page: %s\n", $below, $on ? 'YES — FAIL' : 'no  OK');
        if ($on) $fail++;
    }
    // the shop chrome that makes it "same as the shop page"
    foreach (array('Filter by Tag' => 'Filter by Tag', 'three-across grid' => 'af-archive-3col') as $what => $needle) {
        $ok = strpos($html, $needle) !== false;
        printf("  %-22s %s\n", $what, $ok ? 'OK' : 'missing (informational)');
    }
}

// Ground truth for the banner: what does the served homepage actually hold
// around the Stock Clearance section? Raw bytes, scripts included, so the next
// fix targets real markup instead of an assumption about it.
echo "\n-- homepage markup around the Stock Clearance banner --\n";
$hr = wp_remote_get(add_query_arg('afverify', time(), home_url('/')),
    array('timeout' => 60, 'sslverify' => false, 'headers' => array('User-Agent' => 'Mozilla/5.0 AF-Verify')));
if (!is_wp_error($hr)) {
    $hb = wp_remote_retrieve_body($hr);
    $pos = stripos($hb, 'stock clearance');
    if ($pos === false) $pos = stripos($hb, 'clearance');
    if ($pos !== false) {
        echo "  " . trim(preg_replace('/\s+/', ' ',
            mb_strimwidth(substr($hb, max(0, $pos - 300), 2600), 0, 2600))) . "\n";
    } else {
        echo "  no clearance text in the served homepage\n";
    }
    printf("  banner click script present: %s\n",
        strpos($hb, 'af-deal-banner-link') !== false ? 'OK' : 'MISSING');
}

printf("\n=== %s ===\n", $fail ? "{$fail} CHECK(S) FAILED" : 'ALL CHECKS PASSED');
echo "=== DONE ===\n";
