<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Prove the cart's "You may also like" row can render.
 *
 * A logged-out fetch has an empty cart, and the row deliberately hides on an
 * empty cart, so fetching /cart/ proves nothing. Instead exercise the same
 * builder the hook calls, with a real product id, and confirm the hook is
 * attached. Read-only.
 *
 * Run: wp eval-file tools/verify-cart-related.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }

echo "=== VERIFY CART RELATED ROW ===\n";
$fail = 0;

printf("  hook attached to woocommerce_after_cart: %s\n",
    has_action('woocommerce_after_cart') ? 'OK' : 'MISSING');
if (!has_action('woocommerce_after_cart')) $fail++;

if (!function_exists('af_wl_related_html')) {
    echo "  af_wl_related_html missing\n"; $fail++;
} else {
    $ids = wc_get_products(array('status' => 'publish', 'limit' => 1, 'return' => 'ids'));
    if ($ids) {
        $html = af_wl_related_html('add-to-cart=' . $ids[0]);
        $cards = substr_count($html, 'woocommerce-loop-product__title')
               + substr_count($html, 'class="product');
        printf("  builder output for product #%d: %d bytes, ~%d cards  %s\n",
               $ids[0], strlen($html), $cards, ($html !== '' && $cards > 0) ? 'OK' : 'EMPTY');
        if ($html === '' || $cards === 0) $fail++;
    } else {
        echo "  no products to test with\n"; $fail++;
    }
}

printf("\n=== %s ===\n", $fail ? "{$fail} CHECK(S) FAILED" : 'ALL CHECKS PASSED');
echo "=== DONE ===\n";
