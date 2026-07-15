<?php
/**
 * Give every non-empty product_cat term that has no thumbnail a sensible one:
 * the featured image of its first product. Fixes the broken/placeholder icons
 * in the homepage "Shop by Collection" subcategory row. Never overwrites an
 * existing thumbnail. Idempotent.
 * Run: wp eval-file tools/fill-category-thumbs.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$terms = get_terms(array('taxonomy'=>'product_cat','hide_empty'=>true));
echo "=== FILL CATEGORY THUMBNAILS ===\n";
$set=0;$had=0;$noimg=0;
foreach ($terms as $t) {
    $tid = get_term_meta($t->term_id,'thumbnail_id',true);
    $file = $tid ? get_attached_file($tid) : '';
    if ($tid && $file && @file_exists($file)) { $had++; continue; }

    // find first product in this term (incl. children) that has a valid image
    $ids = get_posts(array(
        'post_type'=>'product','post_status'=>'publish','posts_per_page'=>10,'fields'=>'ids',
        'tax_query'=>array(array('taxonomy'=>'product_cat','field'=>'term_id','terms'=>$t->term_id,'include_children'=>true)),
    ));
    $img=0;
    foreach ($ids as $pid) {
        $th = get_post_thumbnail_id($pid);
        $f  = $th ? get_attached_file($th) : '';
        if ($th && $f && @file_exists($f)) { $img=$th; break; }
    }
    if (!$img) { $noimg++; echo "  (no product image) {$t->name}\n"; continue; }
    update_term_meta($t->term_id,'thumbnail_id',$img);
    $set++;
    echo "  {$t->name} <= image #{$img}\n";
}
echo "\nSet: {$set} | already had: {$had} | no product image: {$noimg}\n=== DONE ===\n";
