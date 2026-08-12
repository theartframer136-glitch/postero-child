<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Check the wishlist page serves what it should.
 *
 * A wishlist viewed by a logged-out fetch is normally empty, so this does not
 * assert that saved items appear — it asserts what must be true either way:
 * the page's own styling ships, the add-to-cart control gets a real label
 * instead of a bare emoji, and the "you may also like" row is built.
 *
 * Read-only. Run: wp eval-file tools/verify-wishlist-page.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }

echo "=== VERIFY WISHLIST PAGE ===\n";

// The owner's recording is of a shared-link view (/wishlist/<token>/), which
// renders with items in it even for an anonymous fetch — exactly what the
// styling has to handle. Use the token from the recording when it still
// resolves; fall back to the bare page.
$url = home_url('/wishlist/4TIY7Q/');
$probe = wp_remote_get($url, array('timeout' => 45, 'sslverify' => false));
if (is_wp_error($probe) || (int) wp_remote_retrieve_response_code($probe) >= 400) {
    $url = home_url('/wishlist/');
}
$res = wp_remote_get(add_query_arg('afverify', time(), $url),
    array('timeout' => 60, 'sslverify' => false, 'headers' => array('User-Agent' => 'Mozilla/5.0 AF-Verify')));
if (is_wp_error($res)) { echo "  FETCH FAILED: " . $res->get_error_message() . "\n=== DONE ===\n"; return; }
$html = wp_remote_retrieve_body($res);
printf("  %s -> HTTP %s, %d bytes\n", $url, wp_remote_retrieve_response_code($res), strlen($html));

$checks = array(
    'page styling shipped'        => strpos($html, 'af-wishlist-style') !== false,
    'add-to-cart labeller shipped'=> strpos($html, 'af-wl-cart-ico') !== false,
    'related products section'    => strpos($html, 'af-wl-related') !== false,
    'related products have cards' => (bool) preg_match('#af-wl-related.{0,20000}?woocommerce-loop-product__title#s', $html),
);
$fail = 0;
foreach ($checks as $what => $ok) {
    printf("  %-30s %s\n", $what, $ok ? 'OK' : 'MISSING');
    if (!$ok) $fail++;
}

// the emoji is the symptom the owner reported; make sure it is not the whole
// button any more
if (preg_match('#<a[^>]*add-to-cart=\d+[^>]*>(.{0,80}?)</a>#s', $html, $m)) {
    $inner = trim(wp_strip_all_tags($m[1]));
    printf("  add-to-cart control reads: %s\n", $inner === '' ? '(empty — labelled in the browser)' : $inner);
}

printf("\n=== %s ===\n", $fail ? "{$fail} CHECK(S) FAILED" : 'ALL CHECKS PASSED');

// ── the markup that actually renders the saved items ──
// The styling passed its marker checks while the owner's recording showed the
// page unchanged, which means the selectors were written for markup this
// plugin does not produce. Print the real thing — the table/list that holds
// the items, plus the shortcode stored in the page — so the CSS can be aimed
// at what exists instead of at an assumption. Also fetch a shared-link view:
// it renders through a different code path than /wishlist/.
echo "\n-- stored page content (shortcode) --\n";
$pg = get_page_by_path('wishlist');
if ($pg) {
    echo "  page #{$pg->ID}: " . trim(mb_strimwidth(preg_replace('/\s+/', ' ', $pg->post_content), 0, 400, '…')) . "\n";
} else {
    echo "  no page with slug 'wishlist'\n";
}

echo "\n-- markup that holds the items --\n";
// search the DOCUMENT BODY with style/script stripped: the first version
// matched its own css text inside a <style> block and printed that instead of
// the markup, which is how the wrong plugin's classes went unnoticed
$dom = preg_replace('#<(style|script)\b[^>]*>.*?</\1>#is', '', $html);
$pos = false;
foreach (array('woosw-item', 'woosw', 'wishlist_table', 'wishlist-items', 'yith-wcwl', 'tinvwl') as $marker) {
    $pos = stripos($dom, $marker);
    if ($pos !== false) { echo "  found by: {$marker}\n"; break; }
}
if ($pos !== false) {
    $start = max(0, $pos - 600);
    echo "  " . trim(preg_replace('/\s+/', ' ', mb_strimwidth(substr($dom, $start, 3500), 0, 3500))) . "\n";
} else {
    echo "  no wishlist markup found in the document body\n";
}

// active wishlist-ish plugins — which plugin owns this page decides the markup
echo "\n-- active wishlist plugins --\n";
foreach ((array) get_option('active_plugins', array()) as $p) {
    if (preg_match('#wishlist|wcwl|tinv#i', $p)) echo "  {$p}\n";
}

echo "=== DONE ===\n";
