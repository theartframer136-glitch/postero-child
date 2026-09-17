<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Re-encode the derivatives that were written at JPEG quality 100.
 *
 * Changing the quality filter only affects copies made from now on. The 2359
 * card-sized derivatives already on disk — 349 MB of them — keep the size they
 * were written at, and they are what a visitor downloads today. This re-encodes
 * them in place at the new quality.
 *
 * ONLY the resized copies. The uploaded original is never touched, so nothing
 * here is destructive in the sense that matters: every derivative can be
 * rebuilt from its original at any quality, by this script or by WordPress.
 *
 * Safety:
 *   - DRY=1 reports what it would do, file by file, and writes nothing.
 *   - A file is skipped unless the re-encode actually comes out smaller, so a
 *     second run cannot grind the same images down repeatedly.
 *   - LIMIT caps how many files one run touches. This host is CPU-bound; the
 *     whole catalogue in one pass is how a shop starts answering 508.
 *   - Writes to a temporary file and renames over the original only on success,
 *     so an interrupted run cannot leave a truncated image on the site.
 *
 * Run: DRY=1 wp eval-file tools/reencode-images.php --allow-root
 *      LIMIT=400 wp eval-file tools/reencode-images.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$dry     = (bool) getenv( 'DRY' );
$limit   = (int) ( getenv( 'LIMIT' ) ?: 400 );
$quality = (int) ( getenv( 'QUALITY' ) ?: apply_filters( 'af_jpeg_quality', 92 ) );
$minGain = 0.08;   // skip unless at least 8% smaller — not worth the rewrite
// Below this, a file is already lean enough that decoding it to find out is
// wasted work. Set from the measured result: quality-92 output landed around
// 250-300 KB per megapixel, quality-100 output at 480-800.
$skipUnderPerMp = (float) ( getenv( 'SKIP_UNDER' ) ?: 380000 );

if ( ! extension_loaded( 'imagick' ) ) { echo "ABORT: Imagick is not available.\n"; return; }

echo '=== RE-ENCODE DERIVATIVES ' . ( $dry ? '(DRY RUN — nothing is written)' : '(WRITING)' ) . " ===\n";
echo "  target quality : {$quality}\n";
echo "  file cap       : {$limit}\n";
printf( "  skip under     : %d KB per megapixel (already lean; not opened)\n\n", round( $skipUnderPerMp / 1024 ) );

$up   = wp_upload_dir();
$base = rtrim( $up['basedir'], '/' );

$ids = get_posts( array(
    'post_type' => 'attachment', 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit',
    'posts_per_page' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'DESC',
) );
echo "  " . count( $ids ) . " JPEG attachments in the library\n\n";

$done = 0; $skipped = 0; $saved = 0; $before = 0; $examined = 0;

foreach ( $ids as $id ) {
    if ( $done >= $limit ) break;
    $meta = wp_get_attachment_metadata( $id );
    if ( empty( $meta['file'] ) || empty( $meta['sizes'] ) ) continue;
    $dir = trailingslashit( $base . '/' . dirname( $meta['file'] ) );

    foreach ( $meta['sizes'] as $name => $s ) {
        if ( $done >= $limit ) break;
        if ( empty( $s['file'] ) ) continue;
        if ( ( $s['mime-type'] ?? '' ) !== 'image/jpeg' ) continue;
        $f = $dir . $s['file'];
        if ( ! file_exists( $f ) || ! is_writable( $f ) ) continue;

        $sizeBefore = filesize( $f );
        if ( $sizeBefore < 60000 ) { continue; }   // already small; leave it alone

        // Decide from the numbers before opening the file. Every later run was
        // decoding all 6000 derivatives just to discover it had already done
        // them, and that is what broke the connection twice: the work of
        // skipping cost as much as the work of converting. Bytes per megapixel
        // comes free from metadata already in hand, and a file already under
        // the threshold cannot gain 8% from a re-encode at this quality.
        $w = (int) ( $s['width'] ?? 0 );
        $h = (int) ( $s['height'] ?? 0 );
        if ( $w > 0 && $h > 0 ) {
            $perMp = $sizeBefore / max( 0.01, ( $w * $h ) / 1000000 );
            if ( $perMp < $skipUnderPerMp ) { $skipped++; continue; }
        }
        $examined++;

        try {
            $im = new Imagick( $f );
            $im->setImageFormat( 'jpeg' );
            $im->setImageCompressionQuality( $quality );
            $im->stripImage();                         // camera/editor metadata a browser never reads
            $im->setInterlaceScheme( Imagick::INTERLACE_PLANE );  // progressive: paints early
            $blob = $im->getImageBlob();
            $im->clear(); $im->destroy();
        } catch ( Throwable $e ) {
            echo "  !! {$s['file']}: " . $e->getMessage() . "\n";
            continue;
        }

        $sizeAfter = strlen( $blob );
        if ( $sizeAfter <= 0 || $sizeAfter > $sizeBefore * ( 1 - $minGain ) ) { $skipped++; continue; }

        $before += $sizeBefore; $saved += ( $sizeBefore - $sizeAfter ); $done++;
        printf( "  %s %7s KB -> %7s KB  (-%2d%%)  %s\n",
            $dry ? 'WOULD' : 'DONE ',
            round( $sizeBefore / 1024 ), round( $sizeAfter / 1024 ),
            round( ( 1 - $sizeAfter / $sizeBefore ) * 100 ), substr( $s['file'], 0, 62 ) );

        if ( $dry ) continue;

        // Write beside it, then rename over: an interrupted run leaves the old
        // file intact rather than half a new one.
        $tmp = $f . '.af-tmp';
        if ( file_put_contents( $tmp, $blob ) === false ) { echo "     write failed\n"; continue; }
        if ( ! @rename( $tmp, $f ) ) { @unlink( $tmp ); echo "     rename failed\n"; continue; }
        @chmod( $f, 0644 );
    }
}

echo "\n";
printf( "  examined %d derivatives over 60 KB\n", $examined );
printf( "  %s %d file(s)\n", $dry ? 'would re-encode' : 're-encoded', $done );
printf( "  %d skipped (already small enough that re-encoding gains under %d%%)\n", $skipped, (int) ( $minGain * 100 ) );
printf( "  %s: %s MB -> %s MB, saving %s MB (%d%%)\n",
    $dry ? 'projected' : 'actual',
    number_format( $before / 1048576, 1 ),
    number_format( ( $before - $saved ) / 1048576, 1 ),
    number_format( $saved / 1048576, 1 ),
    $before ? round( $saved / $before * 100 ) : 0 );

if ( ! $dry && $done ) {
    echo "\n  Re-run to continue: this pass stopped at the cap of {$limit}.\n";
    if ( function_exists( 'do_action' ) ) do_action( 'litespeed_purge_all' );
    echo "  Asked LiteSpeed to purge its cache.\n";
}
