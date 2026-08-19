<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * The product page must ship the sticky-gallery script and the two elements it
 * drives. Read-only. Run: wp eval-file tools/verify-sticky-gallery.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }

echo "=== VERIFY STICKY GALLERY ===\n";
$fail = 0;

$ids = wc_get_products(array('status' => 'publish', 'limit' => 1, 'return' => 'ids'));
if (!$ids) { echo "  no products\n=== DONE ===\n"; return; }
$url = get_permalink($ids[0]);
$r = wp_remote_get(add_query_arg('afverify', time(), $url),
    array('timeout' => 60, 'sslverify' => false, 'headers' => array('User-Agent' => 'Mozilla/5.0 AF-Verify')));
if (is_wp_error($r)) { echo "  FETCH FAILED\n=== DONE ===\n"; return; }
$html = wp_remote_retrieve_body($r);
printf("  %s (%d bytes)\n", $url, strlen($html));

foreach (array(
    'sticky script'   => 'af-sticky-gallery',
    'gallery element' => 'woocommerce-product-gallery',
    'summary column'  => 'summary',
    'tabs section'    => 'woocommerce-tabs',
) as $what => $needle) {
    $ok = strpos($html, $needle) !== false;
    printf("  %-18s %s\n", $what, $ok ? 'OK' : 'MISSING');
    if (!$ok) $fail++;
}

printf("\n=== %s ===\n", $fail ? "{$fail} CHECK(S) FAILED" : 'ALL CHECKS PASSED');
echo "=== DONE ===\n";
