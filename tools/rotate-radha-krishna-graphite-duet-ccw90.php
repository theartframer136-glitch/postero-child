<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * One-off: rotate BOTH photos on the "Radha Krishna Graphite Duet Canvas
 * Wall Art" product (its featured image and every gallery image) 90
 * degrees counter-clockwise (anti-clockwise) per explicit request. Looked
 * up by title match, since no Art Code was given.
 * Backs up each original file before touching it, and regenerates the
 * registered thumbnail sizes for each. Idempotent per-image: each rotated
 * attachment gets its own postmeta flag so re-running a deploy doesn't
 * rotate it again.
 * Run: wp eval-file wp-content/themes/postero-child/tools/rotate-radha-krishna-graphite-duet-ccw90.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

if ( ! extension_loaded( 'imagick' ) || ! class_exists( 'Imagick' ) ) {
    echo "Imagick is not available — aborting without changes.\n";
    exit(0);
}
require_once ABSPATH . 'wp-admin/includes/image.php';

global $wpdb;
$pid = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT ID FROM {$wpdb->posts} WHERE post_type='product' AND post_status IN ('publish','draft') AND post_title LIKE %s LIMIT 1",
    '%Radha Krishna Graphite Duet%'
) );

if ( ! $pid ) {
    echo "Could not find a product titled like 'Radha Krishna Graphite Duet' — no changes made.\n";
    exit(0);
}
echo "Product #{$pid} \"" . get_the_title( $pid ) . "\"\n";

$image_ids = array();
$thumb = get_post_thumbnail_id( $pid );
if ( $thumb ) $image_ids[] = (int) $thumb;
$gallery_raw = get_post_meta( $pid, '_product_image_gallery', true );
if ( $gallery_raw ) {
    foreach ( explode( ',', $gallery_raw ) as $gid ) {
        $gid = (int) trim( $gid );
        if ( $gid ) $image_ids[] = $gid;
    }
}
$image_ids = array_values( array_unique( $image_ids ) );

if ( ! $image_ids ) {
    echo "Product has no images — no changes made.\n";
    exit(0);
}

echo "Found " . count( $image_ids ) . " image(s): " . implode( ', ', $image_ids ) . "\n";

$rotated = 0; $skipped = 0; $errors = 0;
foreach ( $image_ids as $iid ) {
    $flag = '_af_rk_graphite_duet_rotated_ccw90_' . $iid;
    if ( get_post_meta( $pid, $flag, true ) ) {
        echo "  Attachment #{$iid}: already rotated (flag set) — skipping.\n";
        $skipped++;
        continue;
    }

    $path = get_attached_file( $iid );
    if ( ! $path || ! file_exists( $path ) ) {
        echo "  Attachment #{$iid}: file not found on disk — skipping.\n";
        continue;
    }

    $backup = $path . '.pre-graphite-ccw90-backup';
    if ( ! file_exists( $backup ) ) copy( $path, $backup );

    try {
        $im = new Imagick( $path );
        $im->rotateImage( new ImagickPixel( 'none' ), -90 ); // negative = counter-clockwise (anti-clockwise)
        $im->writeImage( $path );
        $im->clear(); $im->destroy();

        $meta = wp_generate_attachment_metadata( $iid, $path );
        if ( $meta ) wp_update_attachment_metadata( $iid, $meta );

        update_post_meta( $pid, $flag, '1' );
        $rotated++;
        echo "  Attachment #{$iid}: rotated 90 deg anti-clockwise and regenerated thumbnails.\n";
    } catch ( Exception $e ) {
        $errors++;
        echo "  Attachment #{$iid}: ERROR " . $e->getMessage() . "\n";
    }
}

if ( $rotated && function_exists( 'wc_delete_product_transients' ) ) wc_delete_product_transients();

echo "Rotated: {$rotated} | already done: {$skipped} | errors: {$errors}\n";
echo "=== DONE ===\n";
