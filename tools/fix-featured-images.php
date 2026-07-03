<?php
/**
 * Fix products with no featured image.
 * SAFE: only sets a featured image where one is MISSING. Never replaces
 * an existing featured image. Sources (in order): first gallery image,
 * else first image found in the product description/content.
 * Reports products that still have zero usable images (need real uploads).
 * Run: wp eval-file tools/fix-featured-images.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }
if ( ! function_exists( 'wc_get_product' ) ) { echo "WooCommerce not active\n"; exit(1); }

$ids = get_posts(array(
    'post_type'=>'product','post_status'=>'publish',
    'posts_per_page'=>-1,'fields'=>'ids',
));

echo "=== FIX FEATURED IMAGES (" . count($ids) . " products) ===\n\n";

$fixed = 0; $ok = 0; $still_empty = array();

foreach ( $ids as $pid ) {
    $product = wc_get_product( $pid );
    if ( ! $product ) continue;

    // Already has a featured image? leave it.
    if ( $product->get_image_id() ) { $ok++; continue; }

    $new_thumb = 0;

    // 1) First gallery image
    $gallery = $product->get_gallery_image_ids();
    if ( ! empty( $gallery ) ) {
        $new_thumb = (int) $gallery[0];
    }

    // 2) First <img> attachment inside the product content
    if ( ! $new_thumb ) {
        $content = get_post_field( 'post_content', $pid );
        if ( $content && preg_match( '/wp-image-(\d+)/', $content, $m ) ) {
            $maybe = (int) $m[1];
            if ( wp_get_attachment_url( $maybe ) ) $new_thumb = $maybe;
        }
    }

    if ( $new_thumb ) {
        set_post_thumbnail( $pid, $new_thumb );
        clean_post_cache( $pid );
        wc_delete_product_transients( $pid );
        $fixed++;
        echo "FIXED  #{$pid}  <- attachment {$new_thumb}  ({$product->get_name()})\n";
    } else {
        $still_empty[] = sprintf( "#%d  %s", $pid, $product->get_name() ?: '(no title)' );
    }
}

echo "\n-------------------------------------------\n";
echo "Already had image : {$ok}\n";
echo "Fixed from gallery/content : {$fixed}\n";
echo "Still no image (need real uploads) : " . count($still_empty) . "\n";
if ( $still_empty ) {
    echo "\nProducts with NO usable image anywhere:\n";
    foreach ( $still_empty as $s ) echo "  " . $s . "\n";
    echo "\n(These need a real image uploaded, or should be set to draft.)\n";
}
echo "\n=== DONE ===\n";
