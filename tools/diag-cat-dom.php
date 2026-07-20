<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Fetch the live category + shop + home pages from the server (loopback) and
 * report the product-grid DOM structure, body classes, and which of our
 * slider/hidden markers appear — so we can pinpoint what hides products.
 * Run: wp eval-file tools/diag-cat-dom.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$urls = array(
    'CATEGORY' => home_url('/product-category/digital-canvas-prints/radha-krishna/'),
    'SHOP'     => function_exists('wc_get_page_id') ? get_permalink(wc_get_page_id('shop')) : home_url('/shop/'),
    'HOME'     => home_url('/'),
);

$needles = array(
    'product-container','id="productGrid"','productGrid','class="product-slider"',
    'product-card','af-grid-hidden','af-shell','ul class="products','woocommerce-loop-product',
    'af-listing-toolbar','af-tagbar','elementor-widget-woocommerce-products','products elementor-grid',
);

foreach ($urls as $label => $url) {
    echo "\n===== {$label}: {$url} =====\n";
    $resp = wp_remote_get($url, array('timeout'=>20, 'sslverify'=>false, 'headers'=>array('User-Agent'=>'AF-Diag')));
    if (is_wp_error($resp)) { echo "  ERROR: ".$resp->get_error_message()."\n"; continue; }
    $code = wp_remote_retrieve_response_code($resp);
    $body = wp_remote_retrieve_body($resp);
    echo "  HTTP {$code}, ".strlen($body)." bytes\n";

    if (preg_match('/<body[^>]*class="([^"]*)"/i', $body, $m)) {
        echo "  body class: " . trim(preg_replace('/\s+/', ' ', $m[1])) . "\n";
    } else { echo "  body class: (not found)\n"; }

    foreach ($needles as $n) {
        $c = substr_count($body, $n);
        if ($c > 0) echo "  " . str_pad($n, 34) . " x{$c}\n";
    }
}
echo "\n=== DONE ===\n";
