<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * One-off: rotate the "Radha Krishna Jeweled Duet Canvas Wall Art" product
 * photo 90 degrees counter-clockwise (anti-clockwise) per explicit request.
 * Looked up by title match rather than Art Code, since none was given.
 * Backs up the original file before touching it, rotates the featured image
 * only, and regenerates the registered thumbnail sizes from the rotated
 * image. Idempotent guard: writes a postmeta flag so re-running a deploy
 * doesn't rotate it again.
 * Run: wp eval-file wp-content/themes/postero-child/tools/rotate-radha-krishna-jeweled-duet-ccw90.php --allow-root
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
    '%Radha Krishna Jeweled Duet%'
) );

if ( ! $pid ) {
    echo "Could not find a product titled like 'Radha Krishna Jeweled Duet' — no changes made.\n";
    exit(0);
}

if ( get_post_meta( $pid, '_af_rk_jeweled_duet_rotated_ccw90', true ) ) {
    echo "Product #{$pid} already rotated (flag set) — skipping.\n";
    exit(0);
}

$iid = get_post_thumbnail_id( $pid );
if ( ! $iid ) {
    echo "Product #{$pid} has no featured image — no changes made.\n";
    exit(0);
}

$path = get_attached_file( $iid );
if ( ! $path || ! file_exists( $path ) ) {
    echo "Attachment #{$iid} file not found on disk — no changes made.\n";
    exit(0);
}

echo "Product #{$pid} \"" . get_the_title( $pid ) . "\", featured image #{$iid}: {$path}\n";

$backup = $path . '.pre-ccw90-backup';
if ( ! file_exists( $backup ) ) copy( $path, $backup );

try {
    $im = new Imagick( $path );
    $im->rotateImage( new ImagickPixel( 'none' ), -90 ); // negative = counter-clockwise (anti-clockwise)
    $im->writeImage( $path );
    $im->clear(); $im->destroy();

    $meta = wp_generate_attachment_metadata( $iid, $path );
    if ( $meta ) wp_update_attachment_metadata( $iid, $meta );

    update_post_meta( $pid, '_af_rk_jeweled_duet_rotated_ccw90', '1' );
    if ( function_exists( 'wc_delete_product_transients' ) ) wc_delete_product_transients();

    echo "Rotated 90 deg anti-clockwise and regenerated thumbnails.\n";
} catch ( Exception $e ) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "=== DONE ===\n";
