<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * One-off: restore the 4 product images found by
 * tools/diag-image-quality-scan.php to their true originally-uploaded
 * resolution. WordPress had silently downscaled each of these on upload
 * (before big_image_size_threshold was disabled in functions.php) and kept
 * serving the downscaled copy as the attachment's "full" size ever since,
 * while the real original sat unused on disk right next to it.
 *
 * For each attachment:
 *   1. Back up the currently-served (downscaled) file.
 *   2. Copy the true original's bytes over the current file, keeping the
 *      same filename/URL so nothing else on the site has to change.
 *   3. Regenerate every registered thumbnail size from the now full-res
 *      file, at quality 100 (functions.php), and with no further
 *      downscaling (big_image_size_threshold is now disabled) — so both
 *      the served "full" size and every derived size reflect the original.
 * Idempotent per-image via its own postmeta flag, same pattern as the
 * rotate-*.php one-offs.
 * Run: wp eval-file wp-content/themes/postero-child/tools/restore-original-image-quality.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

require_once ABSPATH . 'wp-admin/includes/image.php';

// product_id => attachment_id, from the 2026-08-06 diag-image-quality-scan.php run
$targets = array(
    14600 => 10388, // The Art Framer Gift Card — featured image
    11560 => 11566, // Divine Radha Krishna Canvas Wall Art 60x36 — gallery image
    8301  => 8331,  // Radha Krishna Peacock Canvas Art 36x60 — featured image
    232   => 7539,  // Powerful Running White Horses Canvas Wall Art 36x60 — featured image
);

$restored = 0; $skipped = 0; $errors = 0;

foreach ( $targets as $pid => $iid ) {
    $title = get_the_title( $pid );
    $flag  = '_af_quality_restored_v1';

    if ( get_post_meta( $iid, $flag, true ) ) {
        echo "Attachment #{$iid} (product #{$pid} \"{$title}\"): already restored — skipping.\n";
        $skipped++;
        continue;
    }

    $meta = wp_get_attachment_metadata( $iid );
    if ( empty( $meta['original_image'] ) ) {
        echo "Attachment #{$iid} (product #{$pid} \"{$title}\"): no 'original_image' recorded — nothing to restore, skipping.\n";
        $skipped++;
        continue;
    }

    $current_path  = get_attached_file( $iid );
    if ( ! $current_path || ! file_exists( $current_path ) ) {
        echo "Attachment #{$iid} (product #{$pid} \"{$title}\"): current file missing on disk — skipping.\n";
        $errors++;
        continue;
    }
    $original_path = trailingslashit( dirname( $current_path ) ) . $meta['original_image'];
    if ( ! file_exists( $original_path ) ) {
        echo "Attachment #{$iid} (product #{$pid} \"{$title}\"): original file '" . $meta['original_image'] . "' missing on disk — skipping.\n";
        $errors++;
        continue;
    }

    $backup = $current_path . '.pre-quality-restore-backup';
    if ( ! file_exists( $backup ) ) copy( $current_path, $backup );

    if ( ! copy( $original_path, $current_path ) ) {
        echo "Attachment #{$iid} (product #{$pid} \"{$title}\"): ERROR copying original over current file.\n";
        $errors++;
        continue;
    }

    $new_meta = wp_generate_attachment_metadata( $iid, $current_path );
    if ( ! $new_meta ) {
        echo "Attachment #{$iid} (product #{$pid} \"{$title}\"): ERROR regenerating thumbnails after restore.\n";
        $errors++;
        continue;
    }
    wp_update_attachment_metadata( $iid, $new_meta );
    update_post_meta( $iid, $flag, current_time( 'mysql' ) );

    $restored++;
    $w = isset( $new_meta['width'] ) ? $new_meta['width'] : '?';
    $h = isset( $new_meta['height'] ) ? $new_meta['height'] : '?';
    echo "Attachment #{$iid} (product #{$pid} \"{$title}\"): restored to {$w}x{$h} and regenerated thumbnails.\n";
}

if ( $restored && function_exists( 'wc_delete_product_transients' ) ) wc_delete_product_transients();

echo "\nRestored: {$restored} | already done: {$skipped} | errors: {$errors}\n";
echo "=== DONE ===\n";
