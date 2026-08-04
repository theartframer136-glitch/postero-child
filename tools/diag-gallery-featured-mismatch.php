<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * READ-ONLY check for gallery photos that actually belong to a DIFFERENT
 * product. Makes no changes.
 *
 * "Some images don't match the featured image" can mean two very different
 * things: (a) a copy-paste/import mistake pulled in another product's photo,
 * or (b) a legitimate room-mockup/lifestyle shot that's SUPPOSED to look
 * different from the plain artwork close-up. This script can only detect
 * (a) reliably — by content hash, not filename, so a re-uploaded copy still
 * matches: for every product, hash each of its gallery images and check
 * whether that exact file is used as another product's FEATURED image. That
 * is a strong, low-noise signal of a real mix-up (the same pattern behind
 * the earlier Durga/Krishna finding: product #223 vs #7718).
 *
 * It does NOT flag "does this look like the same painting" — that needs a
 * human (or a vision model), not a hash comparison, and guessing wrong here
 * risks deleting legitimate lifestyle photos.
 *
 * Run: wp eval-file tools/diag-gallery-featured-mismatch.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

function taf_gfm_hash( $iid, &$cache ) {
    if ( isset( $cache[ $iid ] ) ) return $cache[ $iid ];
    $path = get_attached_file( $iid );
    $hash = ( $path && file_exists( $path ) ) ? md5_file( $path ) : null;
    return $cache[ $iid ] = $hash;
}

$ids = get_posts( array(
    'post_type'      => 'product',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'fields'         => 'ids',
) );

$hash_cache = array();
$featured_by_hash = array(); // hash => array(pid, title, iid)

foreach ( $ids as $pid ) {
    $thumb = get_post_thumbnail_id( $pid );
    if ( ! $thumb ) continue;
    $h = taf_gfm_hash( $thumb, $hash_cache );
    if ( $h ) $featured_by_hash[ $h ][] = array( 'pid' => $pid, 'iid' => $thumb );
}

echo "=== GALLERY vs FEATURED-IMAGE CROSS-PRODUCT CHECK ===\n";
echo "published products checked: " . count( $ids ) . " | distinct featured-image hashes: " . count( $featured_by_hash ) . "\n\n";

$flagged = 0;
foreach ( $ids as $pid ) {
    $thumb_hash = null;
    $thumb = get_post_thumbnail_id( $pid );
    if ( $thumb ) $thumb_hash = taf_gfm_hash( $thumb, $hash_cache );

    $gallery_raw = get_post_meta( $pid, '_product_image_gallery', true );
    if ( ! $gallery_raw ) continue;

    foreach ( explode( ',', $gallery_raw ) as $gid ) {
        $gid = (int) trim( $gid );
        if ( ! $gid ) continue;
        $gh = taf_gfm_hash( $gid, $hash_cache );
        if ( ! $gh || $gh === $thumb_hash ) continue; // no file, or same as own featured image (fine)

        if ( isset( $featured_by_hash[ $gh ] ) ) {
            foreach ( $featured_by_hash[ $gh ] as $owner ) {
                if ( $owner['pid'] === $pid ) continue; // it's this product's own featured image under another attachment id
                $flagged++;
                $title      = html_entity_decode( wp_strip_all_tags( get_the_title( $pid ) ) );
                $owner_title = html_entity_decode( wp_strip_all_tags( get_the_title( $owner['pid'] ) ) );
                echo "MISMATCH: product #{$pid} \"{$title}\" — gallery image #{$gid} is byte-identical to the FEATURED image of product #{$owner['pid']} \"{$owner_title}\"\n";
                echo "  edit #{$pid}: " . admin_url( 'post.php?post=' . $pid . '&action=edit' ) . "\n";
                echo "  edit #{$owner['pid']}: " . admin_url( 'post.php?post=' . $owner['pid'] . '&action=edit' ) . "\n";
            }
        }
    }
}

echo "\n{$flagged} cross-product mismatch(es) found.\n";
echo "=== DONE ===\n";
