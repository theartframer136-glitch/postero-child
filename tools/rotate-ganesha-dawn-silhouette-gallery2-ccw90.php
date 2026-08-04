<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * One-off: rotate the SECOND photo in the gallery of the "Ganesha Dawn
 * Silhouette" product (Art Code LS 11) 90 degrees counter-clockwise
 * (anti-clockwise) per explicit request. The featured image is untouched.
 * Backs up the original file before touching it, and regenerates the
 * registered thumbnail sizes. Idempotent guard: writes a postmeta flag so
 * re-running a deploy doesn't rotate it again.
 * Run: wp eval-file wp-content/themes/postero-child/tools/rotate-ganesha-dawn-silhouette-gallery2-ccw90.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

if ( ! extension_loaded( 'imagick' ) || ! class_exists( 'Imagick' ) ) {
    echo "Imagick is not available — aborting without changes.\n";
    exit(0);
}
require_once ABSPATH . 'wp-admin/includes/image.php';

global $wpdb;
$pid = 0;
$rows = $wpdb->get_col( $wpdb->prepare(
    "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_taf_art_code' AND meta_value=%s", 'LS 11'
) );
if ( $rows ) $pid = (int) $rows[0];

if ( ! $pid ) {
    echo "Could not find a product with Art Code 'LS 11' — no changes made.\n";
    exit(0);
}
echo "Product #{$pid} \"" . get_the_title( $pid ) . "\"\n";

if ( get_post_meta( $pid, '_af_ganesha_dawn_silhouette_gallery2_rotated_ccw90', true ) ) {
    echo "Second gallery image already rotated (flag set) — skipping.\n";
    exit(0);
}

$gallery_raw = get_post_meta( $pid, '_product_image_gallery', true );
$gallery_ids = array();
if ( $gallery_raw ) {
    foreach ( explode( ',', $gallery_raw ) as $gid ) {
        $gid = (int) trim( $gid );
        if ( $gid ) $gallery_ids[] = $gid;
    }
}

if ( count( $gallery_ids ) < 2 ) {
    echo "Product gallery has fewer than 2 images (" . count( $gallery_ids ) . ") — no second photo to rotate.\n";
    exit(0);
}

$second_iid = $gallery_ids[1];
$path = get_attached_file( $second_iid );
if ( ! $path || ! file_exists( $path ) ) {
    echo "Second gallery attachment #{$second_iid} file not found on disk — no changes made.\n";
    exit(0);
}

echo "Second gallery image #{$second_iid}: {$path}\n";

$backup = $path . '.pre-gallery2-ccw90-backup';
if ( ! file_exists( $backup ) ) copy( $path, $backup );

try {
    $im = new Imagick( $path );
    $im->rotateImage( new ImagickPixel( 'none' ), -90 ); // negative = counter-clockwise (anti-clockwise)
    $im->writeImage( $path );
    $im->clear(); $im->destroy();

    $meta = wp_generate_attachment_metadata( $second_iid, $path );
    if ( $meta ) wp_update_attachment_metadata( $second_iid, $meta );

    update_post_meta( $pid, '_af_ganesha_dawn_silhouette_gallery2_rotated_ccw90', '1' );
    if ( function_exists( 'wc_delete_product_transients' ) ) wc_delete_product_transients();

    echo "Second gallery image rotated 90 deg anti-clockwise and thumbnails regenerated.\n";
} catch ( Exception $e ) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "=== DONE ===\n";
