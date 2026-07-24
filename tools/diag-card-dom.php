<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Dump the rendered markup of the first product card on a category archive so
 * we can see exactly how the star rating / art code / price / swatches are
 * nested and fix their horizontal alignment precisely. Read-only.
 * Run: wp eval-file tools/diag-card-dom.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$url = home_url('/product-category/digital-canvas-prints/lord-shiva/');
$r = wp_remote_get($url, array('timeout'=>30,'sslverify'=>false,'headers'=>array('User-Agent'=>'AF-Diag')));
if (is_wp_error($r)) { echo "fetch failed: ".$r->get_error_message()."\n"; exit; }
$b = wp_remote_retrieve_body($r);

$p = strpos($b, 'li class="');
// find first <li ... class contains product ...>
if (preg_match('/<li[^>]*class="[^"]*\bproduct\b[^"]*"[\s\S]*?<\/li>/i', $b, $m)) {
    $card = $m[0];
    // collapse whitespace, cap length
    $card = preg_replace('/>\s+</', '><', $card);
    echo "=== FIRST PRODUCT CARD (".strlen($card)." chars) ===\n";
    echo substr($card, 0, 4000) . "\n";
    echo "\n=== rating-ish substrings ===\n";
    foreach (array('star-rating','woocommerce-product-rating','rating','review') as $needle) {
        $i = stripos($card, $needle);
        echo "  '$needle' first@".var_export($i,true);
        if ($i!==false) echo "  ...".substr($card, max(0,$i-60), 180);
        echo "\n";
    }
} else {
    echo "no li.product found\n";
}
echo "\n=== DONE ===\n";
