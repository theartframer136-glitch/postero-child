<?php
/**
 * Set to DRAFT any published product that has NO image anywhere
 * (no featured, no gallery, no image in content). This removes blank
 * cards from New Arrivals / listings. Fully reversible: re-publish the
 * product after uploading an image.
 * Run: wp eval-file tools/draft-imageless.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }
if ( ! function_exists( 'wc_get_product' ) ) { echo "WooCommerce not active\n"; exit(1); }

$ids = get_posts(array(
    'post_type'=>'product','post_status'=>'publish',
    'posts_per_page'=>-1,'fields'=>'ids',
));

echo "=== DRAFT IMAGELESS PRODUCTS ===\n\n";
$drafted = 0;
foreach ( $ids as $pid ) {
    $product = wc_get_product( $pid );
    if ( ! $product ) continue;

    if ( $product->get_image_id() ) continue;                 // has featured
    if ( ! empty( $product->get_gallery_image_ids() ) ) continue; // has gallery
    $content = get_post_field( 'post_content', $pid );
    if ( $content && preg_match( '/<img[\s>]/i', $content ) ) continue; // has inline img

    // No image anywhere -> draft it
    wp_update_post(array('ID'=>$pid,'post_status'=>'draft'));
    update_post_meta( $pid, '_taf_drafted_no_image', '1' );
    $drafted++;
    echo "DRAFTED #{$pid}  " . ( $product->get_name() ?: '(no title)' ) . "\n";
}
echo "\n=== DONE. Drafted {$drafted} imageless product(s). ===\n";
echo "Re-publish after uploading an image (Products -> filter Draft).\n";
