<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. Loads the product page the purchase QA uses, from the server
 * itself (as a visitor, no cookies), and reports what a shopper's browser
 * would get: status, time, size, and whether the options the theme builds
 * are in it (size select and its options, the live price, the kit and frame
 * choices, the add-to-cart form), plus the active plugins.
 *
 * Run: wp eval-file tools/diag-product-options.php --allow-root
 */
if (!defined('ABSPATH')) exit(1);
$url = home_url('/product/radha-krishna-moonlit-melody-canvas-wall-art-3x4-feet-floating-frame-premium-digital-canvas-print-living-room-home-spiritual-wall-decor/');
echo "active plugins: " . implode(', ', array_map('dirname', (array) get_option('active_plugins'))) . "\n\n";
foreach (array('', '?afdiag=' . time()) as $q) {
    $t = microtime(true);
    $r = wp_remote_get($url . $q, array('timeout' => 60, 'redirection' => 3, 'sslverify' => true, 'headers' => array('User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) Chrome/124 Safari/537.36')));
    $ms = round((microtime(true) - $t) * 1000);
    if (is_wp_error($r)) { echo "GET $q: error " . $r->get_error_message() . " ($ms ms)\n"; continue; }
    $b = wp_remote_retrieve_body($r);
    $code = wp_remote_retrieve_response_code($r);
    preg_match('#<select[^>]*id="af-size-select"[^>]*>(.*?)</select>#s', $b, $m);
    $opts = isset($m[1]) ? preg_match_all('#<option[^>]*value="([^"]*)"#', $m[1], $o) : 0;
    echo "GET " . ($q === '' ? '(cacheable)' : '(fresh)') . ": HTTP $code, $ms ms, " . strlen($b) . " bytes, cache " . wp_remote_retrieve_header($r, 'x-litespeed-cache') . "\n";
    echo "  size select: " . (isset($m[0]) ? "yes, $opts options" . ($opts ? ' (' . implode(' / ', $o[1]) . ')' : '') : 'MISSING') . "\n";
    foreach (array('af-live-price' => 'live price', 'af-kit-group' => 'kit choices', 'af-frame-chips' => 'frame choices', 'single_add_to_cart_button' => 'add-to-cart button', 'woocommerce-product-gallery' => 'picture gallery', 'There has been a critical error' => 'CRITICAL ERROR TEXT', 'Fatal error' => 'FATAL ERROR TEXT') as $needle => $label) {
        echo "  $label: " . (strpos($b, $needle) !== false ? 'yes' : 'no') . "\n";
    }
}
echo "done\n";
