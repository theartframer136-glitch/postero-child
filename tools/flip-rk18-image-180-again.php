<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Requested follow-up: flip the "Radha Krishna Mosaic Art" (Art Code RK 18)
 * product photo 180 degrees again, on top of the earlier orientation fix
 * (rotate-rk18-image.php + rotate-rk18-image-180.php already straightened
 * it). 180 is direction-agnostic, so no left/right ambiguity here.
 * Backs up the current file before touching it, and regenerates the
 * registered thumbnail sizes.
 * Run: wp eval-file wp-content/themes/postero-child/tools/flip-rk18-image-180-again.php --allow-root
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
    "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_taf_art_code' AND meta_value=%s", 'RK 18'
) );
if ( $rows ) $pid = (int) $rows[0];

if ( ! $pid ) {
    echo "Could not find a product with Art Code 'RK 18' — no changes made.\n";
    exit(0);
}

if ( get_post_meta( $pid, '_af_rk18_flip_v2', true ) ) {
    echo "Product #{$pid} already flipped again (flag set) — skipping.\n";
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

$backup = $path . '.pre-flip-v2-backup';
if ( ! file_exists( $backup ) ) copy( $path, $backup );

try {
    $im = new Imagick( $path );
    $im->rotateImage( new ImagickPixel( 'none' ), 180 );
    $im->writeImage( $path );
    $im->clear(); $im->destroy();

    $meta = wp_generate_attachment_metadata( $iid, $path );
    if ( $meta ) wp_update_attachment_metadata( $iid, $meta );

    update_post_meta( $pid, '_af_rk18_flip_v2', '1' );
    if ( function_exists( 'wc_delete_product_transients' ) ) wc_delete_product_transients();

    echo "Flipped 180 deg (again) and regenerated thumbnails.\n";
} catch ( Exception $e ) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "=== DONE ===\n";
