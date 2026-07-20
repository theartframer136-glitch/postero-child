<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Diagnose the frame / accessory products that show $0.00 and missing images
 * on the homepage, plus locate the stray "Academy of Alameda" demo image and
 * report subcategories with no thumbnail. Read-only.
 * Run: wp eval-file tools/diag-frames.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }
if ( ! function_exists( 'wc_get_product' ) ) { echo "WooCommerce not active\n"; exit(1); }

echo "=== FRAME / ACCESSORY PRODUCTS (price + image) ===\n";
$ids = get_posts(array('post_type'=>'product','post_status'=>'any','posts_per_page'=>-1,'fields'=>'ids',
    's'=>'Floating Frame'));
// also include Art Accessories category
$acc = get_posts(array('post_type'=>'product','post_status'=>'any','posts_per_page'=>-1,'fields'=>'ids',
    'tax_query'=>array(array('taxonomy'=>'product_cat','field'=>'slug',
    'terms'=>array('art-accessories','wooden-frames','premium-aluminium-frames','framed-canvases','framed-white-canvas','frame-sizes','frame-colors')))));
$ids = array_values(array_unique(array_merge($ids, $acc)));
echo "Found ".count($ids)." frame/accessory products\n\n";
$zero=0;$noimg=0;
foreach ($ids as $pid) {
    $p = wc_get_product($pid); if(!$p) continue;
    $price = $p->get_price();
    $reg   = $p->get_regular_price();
    $tid   = get_post_thumbnail_id($pid);
    $file  = $tid ? get_attached_file($tid) : '';
    $hasfile = ($file && @file_exists($file)) ? 'OK' : ($tid ? 'MISSING-FILE' : 'NO-THUMB');
    if ($price==='' || (float)$price==0) $zero++;
    if ($hasfile!=='OK') $noimg++;
    if ((float)$price==0 || $hasfile!=='OK') {
        echo sprintf("#%d price='%s' reg='%s' img=%s status=%s type=%s\n   %s\n",
            $pid, $price, $reg, $hasfile, $p->get_status(), $p->get_type(), $p->get_name());
    }
}
echo "\nSummary: {$zero} at \$0 price, {$noimg} with missing/no image (of ".count($ids).")\n";

echo "\n=== 'Academy of Alameda' / demo banner images in use ===\n";
global $wpdb;
$rows = $wpdb->get_results("SELECT ID, post_title, guid FROM {$wpdb->posts}
    WHERE post_type='attachment' AND (post_title LIKE '%academy%' OR post_title LIKE '%alameda%'
    OR guid LIKE '%academy%' OR guid LIKE '%alameda%') LIMIT 20");
if ($rows) foreach ($rows as $r) {
    echo "  attach #{$r->ID} '{$r->post_title}' {$r->guid}\n";
    // which products use it as featured image?
    $u = $wpdb->get_col($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta}
        WHERE meta_key='_thumbnail_id' AND meta_value=%d", $r->ID));
    foreach ($u as $upid) echo "      used by product #{$upid} ".get_the_title($upid)."\n";
} else echo "  (none found by title/guid)\n";

echo "\n=== product_cat subcategories with NO thumbnail ===\n";
$terms = get_terms(array('taxonomy'=>'product_cat','hide_empty'=>false));
foreach ($terms as $t) {
    $tid = get_term_meta($t->term_id,'thumbnail_id',true);
    $file = $tid ? get_attached_file($tid) : '';
    if (!$tid || !($file && @file_exists($file))) {
        if ($t->count>0) echo sprintf("  '%s' (slug=%s, count=%d) thumb=%s\n",$t->name,$t->slug,$t->count,$tid?'MISSING-FILE':'none');
    }
}
echo "\n=== DONE ===\n";
