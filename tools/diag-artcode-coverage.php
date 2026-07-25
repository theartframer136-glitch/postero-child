<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Audit _taf_art_code coverage: how many published products have an art code
 * vs none, list the ones MISSING a code, and specifically check the products
 * seen in the homepage sliders. Read-only.
 * Run: wp eval-file tools/diag-artcode-coverage.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$ids = get_posts(array('post_type'=>'product','post_status'=>'publish','posts_per_page'=>-1,'fields'=>'ids'));
$with=0; $without=0; $missing=array();
foreach ($ids as $pid){
    $code = get_post_meta($pid, '_taf_art_code', true);
    if (is_string($code) && trim($code)!=='') { $with++; }
    else { $without++; $missing[] = $pid; }
}
echo "=== ART CODE COVERAGE ===\n";
echo "published products: ".count($ids)."\n";
echo "  with code:    {$with}\n";
echo "  WITHOUT code: {$without}\n";

echo "\n=== first 40 products WITHOUT a code ===\n";
foreach (array_slice($missing,0,40) as $pid){
    echo "  #{$pid}  ".get_the_title($pid)."\n";
}

echo "\n=== products seen in the video sliders ===\n";
$names = array('Kodanda Rama','Krishna Cowherd','Balaji Abstract Gold','Radha in Bridal','Shiva Smoke');
foreach ($names as $n){
    $q = get_posts(array('post_type'=>'product','post_status'=>'publish','posts_per_page'=>1,'s'=>$n,'fields'=>'ids'));
    if (!$q) { echo "  '{$n}' => not found by search\n"; continue; }
    $pid = $q[0]; $code = get_post_meta($pid, '_taf_art_code', true);
    echo "  '{$n}' => #{$pid} code '".($code?:'(NONE)')."'\n";
}
echo "\n=== DONE ===\n";
