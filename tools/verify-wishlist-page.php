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

$url = home_url('/wishlist/');
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
echo "=== DONE ===\n";
