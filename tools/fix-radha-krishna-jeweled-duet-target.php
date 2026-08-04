<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Correction: the 90-degree anti-clockwise rotation requested for "Radha
 * Krishna Jeweled Duet" was applied to the wrong photo (the featured
 * image); it was meant for the second photo in the product's gallery.
 *
 * This script:
 *   1. Reverts the featured image by restoring it from the backup written
 *      by rotate-radha-krishna-jeweled-duet-ccw90.php (.pre-ccw90-backup),
 *      rather than rotating it back — an exact byte-for-byte restore.
 *   2. Rotates the SECOND image in the product's gallery
 *      (_product_image_gallery, zero-indexed position 1) 90 degrees
 *      anti-clockwise, the same way the featured image was (mistakenly)
 *      rotated.
 * Both steps are independently idempotent via their own postmeta flags.
 * Run: wp eval-file wp-content/themes/postero-child/tools/fix-radha-krishna-jeweled-duet-target.php --allow-root
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
echo "Product #{$pid} \"" . get_the_title( $pid ) . "\"\n";

// ── Step 1: revert the featured image from its pre-rotation backup ─────────
if ( get_post_meta( $pid, '_af_rk_jeweled_duet_featured_reverted', true ) ) {
    echo "Featured image already reverted (flag set) — skipping.\n";
} else {
    $iid = get_post_thumbnail_id( $pid );
    if ( ! $iid ) {
        echo "Product has no featured image — nothing to revert.\n";
    } else {
        $path = get_attached_file( $iid );
        $backup = $path ? $path . '.pre-ccw90-backup' : null;
        if ( ! $path || ! $backup || ! file_exists( $backup ) ) {
            echo "No backup found for featured image #{$iid} — cannot safely revert; leaving as-is.\n";
        } else {
            if ( ! copy( $backup, $path ) ) {
                echo "ERROR: could not restore {$path} from backup.\n";
            } else {
                $meta = wp_generate_attachment_metadata( $iid, $path );
                if ( $meta ) wp_update_attachment_metadata( $iid, $meta );
                update_post_meta( $pid, '_af_rk_jeweled_duet_featured_reverted', '1' );
                echo "Featured image #{$iid} reverted from backup and thumbnails regenerated.\n";
            }
        }
    }
}

// ── Step 2: rotate the SECOND gallery image 90 deg anti-clockwise ──────────
if ( get_post_meta( $pid, '_af_rk_jeweled_duet_gallery2_rotated_ccw90', true ) ) {
    echo "Second gallery image already rotated (flag set) — skipping.\n";
    echo "=== DONE ===\n";
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

    update_post_meta( $pid, '_af_rk_jeweled_duet_gallery2_rotated_ccw90', '1' );
    if ( function_exists( 'wc_delete_product_transients' ) ) wc_delete_product_transients();

    echo "Second gallery image rotated 90 deg anti-clockwise and thumbnails regenerated.\n";
} catch ( Exception $e ) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "=== DONE ===\n";
