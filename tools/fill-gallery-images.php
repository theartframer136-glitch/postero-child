<?php
/**
 * Ensure every published product has at least one gallery image so the theme's
 * card hover swap never shows its "Gallery image not present" placeholder.
 * For products with an EMPTY gallery, sets the gallery to the featured image.
 * Never touches products that already have a gallery. Idempotent.
 * Run: wp eval-file tools/fill-gallery-images.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$ids = get_posts(array(
    'post_type'      => 'product',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'fields'         => 'ids',
));

echo "=== FILL EMPTY PRODUCT GALLERIES (" . count($ids) . " products) ===\n";
$filled = 0; $had = 0; $noimg = 0;
foreach ($ids as $pid) {
    $gallery = get_post_meta($pid, '_product_image_gallery', true);
    if (!empty($gallery)) { $had++; continue; }
    $thumb = get_post_thumbnail_id($pid);
    if (!$thumb) { $noimg++; continue; }
    update_post_meta($pid, '_product_image_gallery', (string) $thumb);
    clean_post_cache($pid);
    if (function_exists('wc_delete_product_transients')) wc_delete_product_transients($pid);
    $filled++;
    echo "  #{$pid} gallery <= featured image #{$thumb}\n";
}
echo "\nFilled: {$filled} | already had gallery: {$had} | no featured image: {$noimg}\n=== DONE ===\n";
