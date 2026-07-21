<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Report exactly what the shop/category sidebar filters currently offer for
 * Size / Frame Color / Frame Type, versus the product-page (brochure) selector
 * options, so we can align them. Read-only.
 * Run: wp eval-file tools/diag-shopfilters.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

echo "=== ATTRIBUTE TERMS (what filters can list) ===\n";
foreach (array('pa_size'=>'Size','pa_colors'=>'Frame Color','pa_frame'=>'Frame Type') as $tax=>$label) {
    $terms = get_terms(array('taxonomy'=>$tax,'hide_empty'=>false));
    if (is_wp_error($terms)) { echo "  {$label}: taxonomy missing\n"; continue; }
    echo "  {$label} ({$tax}): " . count($terms) . " terms\n";
    foreach ($terms as $t) echo "      - {$t->name}  (count={$t->count})\n";
}

echo "\n=== PRODUCT-PAGE (brochure) selector options from af_pricing_config ===\n";
if (function_exists('af_pricing_config')) {
    $cfg = af_pricing_config();
    echo "  Sizes  (" . count($cfg['sizes'])  . "): " . implode(' | ', array_keys($cfg['sizes']))  . "\n";
    echo "  Frames (" . count($cfg['frames']) . "): " . implode(' | ', array_keys($cfg['frames'])) . "\n";
    echo "  Colors (" . count($cfg['colors']) . "): " . implode(' | ', array_keys($cfg['colors'])) . "\n";
} else echo "  af_pricing_config() not available\n";

echo "\n=== CATEGORY sidebar: which filter blocks render ===\n";
$url = home_url('/product-category/digital-canvas-prints/radha-krishna/');
$r = wp_remote_get($url, array('timeout'=>25,'sslverify'=>false,'headers'=>array('User-Agent'=>'AF-Diag')));
if (!is_wp_error($r)) {
    $b = wp_remote_retrieve_body($r);
    foreach (array('Size','Frame Color','Frame Type','Filter By Price','filter_size','filter_colors','filter_frame','af-lt-drop','widget_layered_nav') as $needle) {
        echo "  '" . $needle . "' x" . substr_count($b, $needle) . "\n";
    }
    // dump the sidebar filter option labels near frame color/type
    if (preg_match('/Frame Color[\s\S]{0,900}/i', $b, $m)) {
        $seg = preg_replace('/\s+/', ' ', strip_tags($m[0]));
        echo "  [Frame Color region]: " . substr($seg, 0, 300) . "\n";
    }
    if (preg_match('/Frame Type[\s\S]{0,900}/i', $b, $m)) {
        $seg = preg_replace('/\s+/', ' ', strip_tags($m[0]));
        echo "  [Frame Type region]: " . substr($seg, 0, 300) . "\n";
    }
} else echo "  fetch failed: " . $r->get_error_message() . "\n";
echo "\n=== DONE ===\n";
