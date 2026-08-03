<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Auto-correct EXIF image rotation on product photos (featured + gallery).
 * Some uploads carry an EXIF Orientation tag (e.g. "rotate 90") that the
 * browser's full-size view respects but WordPress's cropped/resized card
 * thumbnails don't always honour, so the image looks tilted/sideways on
 * product cards even though the original file "looks fine" elsewhere.
 *
 * Uses Imagick::autoOrientImage() — a purpose-built, well-tested routine —
 * rather than computing rotation angles by hand, to avoid the risk of
 * rotating the wrong direction. Only touches JPEGs with a non-normal EXIF
 * orientation tag; every other image is left untouched. Before overwriting
 * a file, a ".pre-rotate-backup" copy is written next to it so the change
 * is reversible. After correcting, WordPress's registered thumbnail sizes
 * are regenerated from the corrected full image.
 *
 * Run: wp eval-file tools/fix-image-orientation.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

if ( ! extension_loaded( 'imagick' ) || ! class_exists( 'Imagick' ) ) {
    echo "Imagick is not available on this server — aborting without changes.\n";
    echo "(Refusing to hand-compute rotation angles via GD; too easy to rotate the wrong way.)\n";
    exit(0);
}
require_once ABSPATH . 'wp-admin/includes/image.php';

function taf_image_ids_for_product( $pid ) {
    $ids = array();
    $thumb = get_post_thumbnail_id( $pid );
    if ( $thumb ) $ids[] = (int) $thumb;
    $gal = get_post_meta( $pid, '_product_image_gallery', true );
    if ( $gal ) {
        foreach ( explode( ',', $gal ) as $gid ) {
            $gid = (int) trim( $gid );
            if ( $gid ) $ids[] = $gid;
        }
    }
    return array_unique( $ids );
}

$product_ids = get_posts( array(
    'post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids',
) );

$image_to_products = array();
foreach ( $product_ids as $pid ) {
    foreach ( taf_image_ids_for_product( $pid ) as $iid ) {
        $image_to_products[ $iid ][] = $pid;
    }
}

echo "=== IMAGE ORIENTATION FIX ===\n";
echo "products checked: " . count( $product_ids ) . " | distinct images: " . count( $image_to_products ) . "\n\n";

$checked = 0; $fixed = 0; $skipped_not_jpeg = 0; $errors = 0;
foreach ( $image_to_products as $iid => $pids ) {
    $path = get_attached_file( $iid );
    if ( ! $path || ! file_exists( $path ) ) continue;
    $type = wp_check_filetype( $path );
    if ( empty( $type['type'] ) || strpos( $type['type'], 'jpeg' ) === false ) { $skipped_not_jpeg++; continue; }
    $checked++;

    try {
        $im = new Imagick( $path );
        $orientation = $im->getImageOrientation();
        if ( $orientation === Imagick::ORIENTATION_TOPLEFT || $orientation === Imagick::ORIENTATION_UNDEFINED ) {
            $im->clear(); $im->destroy();
            continue; // already normal, nothing to do
        }

        $backup = $path . '.pre-rotate-backup';
        if ( ! file_exists( $backup ) ) copy( $path, $backup );

        $im->autoOrientImage();
        $im->writeImage( $path );
        $im->clear(); $im->destroy();

        // Regenerate registered thumbnail sizes from the corrected full image.
        $meta = wp_generate_attachment_metadata( $iid, $path );
        if ( $meta ) wp_update_attachment_metadata( $iid, $meta );

        $fixed++;
        $titles = array_map( function ( $p ) { return '#' . $p; }, $pids );
        echo "  FIXED attachment #{$iid} (orientation was {$orientation}) — used by: " . implode( ', ', $titles ) . "\n";
    } catch ( Exception $e ) {
        $errors++;
        echo "  ERROR attachment #{$iid}: " . $e->getMessage() . "\n";
    }
}

echo "\nJPEGs checked: {$checked} | fixed: {$fixed} | non-JPEG skipped: {$skipped_not_jpeg} | errors: {$errors}\n";
if ( function_exists( 'wc_delete_product_transients' ) ) wc_delete_product_transients();
echo "=== DONE ===\n";
