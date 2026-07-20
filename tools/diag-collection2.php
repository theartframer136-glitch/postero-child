<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Ground-truth the Shop by Collection 12-cap:
 *  1. Print the parent theme's load_products + homepage-grid code regions.
 *  2. Live-call the load_products AJAX (param names parsed from source) for
 *     radha-krishna and count product cards in the response.
 *  3. Purge LiteSpeed, fetch homepage fresh, count cards inside #productGrid.
 * Read-only. Run: wp eval-file tools/diag-collection2.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$file = get_template_directory() . '/functions.php';
$src  = file_get_contents($file);
$lines = explode("\n", $src);

echo "=== parent functions.php lines 150-240 ===\n";
for ($i=149; $i<240 && $i<count($lines); $i++) echo ($i+1).": ".rtrim($lines[$i])."\n";
echo "\n=== parent functions.php lines 770-870 ===\n";
for ($i=769; $i<870 && $i<count($lines); $i++) echo ($i+1).": ".rtrim($lines[$i])."\n";

// Parse $_POST/$_REQUEST keys used inside load_products
$keys = array();
if (preg_match('/function\s+load_products\s*\([^)]*\)\s*\{(.*?)\n\}/s', $src, $fm)) {
    if (preg_match_all('/\$_(?:POST|REQUEST|GET)\[[\'"]([^\'"]+)[\'"]\]/', $fm[1], $km)) {
        $keys = array_values(array_unique($km[1]));
    }
}
echo "\nload_products request keys: ".json_encode($keys)."\n";

// Resolve radha-krishna term
$term = get_term_by('slug', 'radha-krishna', 'product_cat');
$tid  = $term ? $term->term_id : 0;
echo "radha-krishna term_id: {$tid}\n";

// 2. Live AJAX test — try slug and id for every cat-ish key
$ajax = admin_url('admin-ajax.php');
$variants = array();
foreach ($keys as $k) {
    if ($k === 'action') continue;
    $variants[] = array($k => 'radha-krishna');
    if ($tid) $variants[] = array($k => (string)$tid);
}
if (!$variants) { $variants[] = array('category' => 'radha-krishna'); if ($tid) $variants[] = array('category' => (string)$tid); }
foreach ($variants as $v) {
    $body = array_merge(array('action' => 'load_products'), $v);
    $r = wp_remote_post($ajax, array('timeout'=>30,'sslverify'=>false,'body'=>$body));
    if (is_wp_error($r)) { echo "AJAX ".json_encode($v).": ERROR ".$r->get_error_message()."\n"; continue; }
    $b = wp_remote_retrieve_body($r);
    echo "AJAX ".json_encode($v).": ".strlen($b)." bytes | product-card x".substr_count($b,'product-card')
       ." | woocommerce-loop x".substr_count($b,'woocommerce-loop-product')."\n";
}

// 3. Fresh homepage: purge page cache then fetch, count inside #productGrid
if (function_exists('do_action')) do_action('litespeed_purge_all');
sleep(2);
$r = wp_remote_get(home_url('/'), array('timeout'=>30,'sslverify'=>false,'headers'=>array('User-Agent'=>'AF-Diag2')));
if (!is_wp_error($r)) {
    $b = wp_remote_retrieve_body($r);
    echo "\nHOME fresh: ".strlen($b)." bytes | total product-card x".substr_count($b,'product-card')."\n";
    $p = strpos($b, 'id="productGrid"');
    if ($p !== false) {
        // count within the grid's slice (up to the section end heuristic: next 'af-shell' or 300KB)
        $endMarkers = array('af-shell', 'Try It', 'trending-card');
        $end = strlen($b);
        foreach ($endMarkers as $m) { $q = strpos($b, $m, $p); if ($q !== false && $q < $end) $end = $q; }
        $slice = substr($b, $p, max(0,$end-$p));
        echo "#productGrid slice ".strlen($slice)." bytes | product-card x".substr_count($slice,'product-card')."\n";
    } else echo "#productGrid not found in fresh HTML\n";
}
echo "=== DONE ===\n";
