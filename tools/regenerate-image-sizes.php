<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Rebuild the resized copies of every product image, so the quality and size
 * settings this theme asks for actually apply to the catalogue that already
 * exists.
 *
 * WHY THIS IS NEEDED AT ALL
 * functions.php has asked for jpeg_quality 100 for a long time, and now asks
 * for a wider catalogue thumbnail. Neither setting is retroactive: WordPress
 * encodes the resized copies ONCE, when an image is uploaded. Every product
 * already in the shop is still being served the copies made under the old
 * settings — quality 82, 600px wide. Changing the filters without rebuilding
 * changes nothing anybody can see, which is exactly the kind of "shipped but
 * invisible" result this project has been bitten by before.
 *
 * WHY IT IS BATCHED, AND WHY THE BUDGET GREW
 * This is a shared host that has returned 508 under load during deploys.
 * Re-encoding a few thousand images in one pass is the most expensive thing
 * this repo could ask of it. So the run is bounded by a wall-clock budget and
 * an image cap, records which attachments it has finished, and picks up where
 * it left off next time. Several small deploys converge on a fully rebuilt
 * catalogue; no single deploy can hang or take the site down.
 *
 * The first budget was deliberately timid — 100 seconds, 120 images — and at
 * that rate about a fifth of the catalogue had been rebuilt after three full
 * deploys, with everything else still served at the old quality. The count
 * was never the binding constraint; the clock was, since 120 images took 105
 * seconds. So the clock is what moved. 240 seconds is still a hard ceiling,
 * the step still cannot fail the deploy, and it now gets through the
 * catalogue in a handful of runs instead of a dozen and a half.
 *
 * Safe to run repeatedly: an image already rebuilt at the current version is
 * skipped, so a finished catalogue costs one query and nothing else.
 *
 * Run: wp eval-file tools/regenerate-image-sizes.php --allow-root
 * Env: AF_IMG_SECONDS (default 240)  AF_IMG_MAX (default 400)
 *      AF_IMG_FORCE=1 rebuilds everything again, ignoring the version marker
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

// Bump this when a setting that affects the OUTPUT changes — quality, any of
// the widths, a new registered size. That is what makes a rebuild happen at
// all, and what stops one repeating forever once it is done. It is bumped now
// because the product page's own image size went from 600 to 1200: the v1
// pass raised only the catalogue thumbnail, so the picture a customer
// actually leans in to look at was never rebuilt any larger.
$VERSION = 'q100-card800-single1200-thumb200-v2';
$SECONDS = (int) ( getenv( 'AF_IMG_SECONDS' ) ?: 240 );
$MAX     = (int) ( getenv( 'AF_IMG_MAX' )     ?: 400 );
$FORCE   = (bool) getenv( 'AF_IMG_FORCE' );

echo "=== PRODUCT IMAGE REBUILD ===\n";
echo "target: {$VERSION}  |  budget: {$SECONDS}s or {$MAX} images per run\n";

if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
    require_once ABSPATH . 'wp-admin/includes/image.php';
}
if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
    echo "wp_generate_attachment_metadata() unavailable — cannot rebuild\n=== DONE ===\n";
    return;
}

// Product images only — the featured image and the gallery of every published
// product. The media library also holds page banners, icons and old uploads,
// and rebuilding those would spend the budget on images no shopper sees.
$ids = array();
foreach ( wc_get_products( array( 'status' => 'publish', 'limit' => -1, 'return' => 'ids' ) ) as $pid ) {
    $p = wc_get_product( $pid );
    if ( ! $p ) continue;
    $thumb = (int) $p->get_image_id();
    if ( $thumb ) $ids[ $thumb ] = true;
    foreach ( (array) $p->get_gallery_image_ids() as $g ) {
        $g = (int) $g;
        if ( $g ) $ids[ $g ] = true;
    }
}
$ids = array_keys( $ids );
$total = count( $ids );
echo "product images: {$total}\n";
if ( ! $total ) { echo "nothing to do\n=== DONE ===\n"; return; }

// What is left, before doing any work — so the log says how far along this is
// even on a run that rebuilds nothing.
$todo = array();
foreach ( $ids as $id ) {
    if ( ! $FORCE && get_post_meta( $id, '_af_img_rebuilt', true ) === $VERSION ) continue;
    $todo[] = $id;
}
$done_already = $total - count( $todo );
printf( "already at %s: %d  |  remaining: %d\n", $VERSION, $done_already, count( $todo ) );

if ( ! $todo ) {
    echo "\nthe whole catalogue is rebuilt at the current settings.\n";
    af_img_sample( $ids );
    echo "=== DONE ===\n";
    return;
}

$deadline = time() + $SECONDS;
$ok = $skipped = $failed = 0;
$started = time();

foreach ( $todo as $id ) {
    if ( $ok + $failed >= $MAX ) { echo "reached the {$MAX}-image cap for this run\n"; break; }
    if ( time() >= $deadline )   { echo "reached the {$SECONDS}s budget for this run\n"; break; }

    $file = get_attached_file( $id );
    if ( ! $file || ! file_exists( $file ) ) {
        // A missing master is a data problem, not something to retry every
        // deploy — mark it so the run moves past it and the count is honest.
        $skipped++;
        update_post_meta( $id, '_af_img_rebuilt', $VERSION );
        continue;
    }
    if ( ! wp_attachment_is_image( $id ) ) {
        $skipped++;
        update_post_meta( $id, '_af_img_rebuilt', $VERSION );
        continue;
    }

    $meta = wp_generate_attachment_metadata( $id, $file );
    if ( is_wp_error( $meta ) || ! is_array( $meta ) || empty( $meta['sizes'] ) ) {
        $failed++;
        continue;   // leave it unmarked so the next run tries again
    }
    wp_update_attachment_metadata( $id, $meta );
    update_post_meta( $id, '_af_img_rebuilt', $VERSION );
    $ok++;
}

$elapsed = time() - $started;
printf( "\nrebuilt %d  |  skipped %d  |  failed %d  |  %ds\n", $ok, $skipped, $failed, $elapsed );

$left = 0;
foreach ( $ids as $id ) {
    if ( get_post_meta( $id, '_af_img_rebuilt', true ) !== $VERSION ) $left++;
}
if ( $left ) {
    $runs = (int) ceil( $left / max( 1, $ok ?: $MAX ) );
    echo "still to do: {$left} — about {$runs} more deploy(s) at this rate.\n";
    echo "This is deliberate: the host has returned 508 under deploy load, and\n";
    echo "a bounded run that converges beats one long run that might not finish.\n";
} else {
    echo "the whole catalogue is now rebuilt at the current settings.\n";
}

af_img_sample( $ids );
echo "=== DONE ===\n";

/** Show what a card image actually is now: dimensions and weight on disk. */
function af_img_sample( $ids ) {
    echo "\nwhat the catalogue thumbnail looks like now (3 samples):\n";
    $shown = 0;
    foreach ( $ids as $id ) {
        if ( $shown >= 3 ) break;
        $full = wp_get_attachment_image_src( $id, 'full' );
        $thumb = wp_get_attachment_image_src( $id, 'woocommerce_thumbnail' );
        if ( ! $thumb ) continue;
        $path = str_replace( wp_get_upload_dir()['baseurl'], wp_get_upload_dir()['basedir'], $thumb[0] );
        $kb   = file_exists( $path ) ? round( filesize( $path ) / 1024 ) : 0;
        printf( "  #%-7d master %dx%d  →  card %dx%d  (%d KB)  %s\n",
            $id, $full ? $full[1] : 0, $full ? $full[2] : 0,
            $thumb[1], $thumb[2], $kb, basename( $thumb[0] ) );
        $shown++;
    }
    // A card is ~380 CSS px wide, so anything under 760 is being stretched on
    // a 2x screen. Saying the number beats saying "improved".
    echo "  a card is about 380 CSS px wide, so 760+ is what a 2x screen wants\n";
}
