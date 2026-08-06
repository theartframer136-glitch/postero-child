<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * READ-ONLY scan for product images that are NOT at their originally
 * uploaded quality/resolution. Makes no changes.
 *
 * WordPress silently downscales any upload wider or taller than 2560px on
 * the way in (the "big image size threshold"). The downscaled copy becomes
 * the attachment's "full" size — the one used on product pages, zoom, etc.
 * The true original is kept on disk but never referenced again. That
 * behaviour has now been disabled for future uploads (see functions.php),
 * but anything uploaded before that change is still affected.
 *
 * This script finds every such attachment by checking for WordPress's own
 * 'original_image' metadata key (it writes that key precisely when it has
 * performed this downscale), and reports the resolution actually being
 * served today vs. the true original sitting unused alongside it.
 *
 * Run: wp eval-file tools/diag-image-quality-scan.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

function taf_iqs_dims( $path ) {
    if ( ! $path || ! file_exists( $path ) ) return null;
    $s = @getimagesize( $path );
    return $s ? array( $s[0], $s[1] ) : null;
}

$ids = get_posts( array(
    'post_type'      => 'product',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'fields'         => 'ids',
) );

echo "=== IMAGE QUALITY SCAN (originals downscaled on upload) ===\n";
echo "published products checked: " . count( $ids ) . "\n\n";

$flagged = 0;
$checked_attachments = 0;

foreach ( $ids as $pid ) {
    $att_ids = array();
    $thumb = get_post_thumbnail_id( $pid );
    if ( $thumb ) $att_ids[ $thumb ] = 'featured';

    $gallery_raw = get_post_meta( $pid, '_product_image_gallery', true );
    if ( $gallery_raw ) {
        foreach ( explode( ',', $gallery_raw ) as $gid ) {
            $gid = (int) trim( $gid );
            if ( $gid && ! isset( $att_ids[ $gid ] ) ) $att_ids[ $gid ] = 'gallery';
        }
    }

    foreach ( $att_ids as $aid => $role ) {
        $checked_attachments++;
        $meta = wp_get_attachment_metadata( $aid );
        if ( empty( $meta['original_image'] ) ) continue; // never downscaled on upload

        $current_path  = get_attached_file( $aid );
        $original_path = trailingslashit( dirname( $current_path ) ) . $meta['original_image'];

        $current_dims  = taf_iqs_dims( $current_path );
        $original_dims = taf_iqs_dims( $original_path );

        if ( ! $current_dims ) continue; // can't read current file, skip

        $flagged++;
        $title = html_entity_decode( wp_strip_all_tags( get_the_title( $pid ) ) );
        echo "DOWNSCALED: product #{$pid} \"{$title}\" — {$role} image #{$aid}\n";
        echo "  currently served at: {$current_dims[0]}x{$current_dims[1]}\n";
        if ( $original_dims ) {
            echo "  true original is:    {$original_dims[0]}x{$original_dims[1]} (" . $meta['original_image'] . ", still on disk, unused)\n";
        } else {
            echo "  true original file (" . $meta['original_image'] . ") could not be read — may have been removed\n";
        }
        echo "  edit: " . admin_url( 'post.php?post=' . $pid . '&action=edit' ) . "\n";
    }
}

echo "\nattachments checked: {$checked_attachments}\n";
echo "{$flagged} image(s) currently served below their originally uploaded resolution.\n";
echo "=== DONE ===\n";
