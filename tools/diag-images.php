<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * How big the pictures are, and how big they ought to be.
 *
 * The browser audit measured 5.6 MB of images on one category page, with
 * single card thumbnails at 661 KB and 905 KB. Those are 800px-wide
 * derivatives; an 800px photograph is normally 60-120 KB. So the files are not
 * too large in PIXELS, they are too large in BYTES — badly compressed, not
 * badly sized. That distinction decides the fix, so measure it rather than
 * assume it: this reports bytes per megapixel for the card-sized derivatives,
 * which is the number that separates the two.
 *
 * Also reports what the server can re-encode with, and what LiteSpeed Cache is
 * currently set to do, since the plugin that is already installed may be the
 * right tool rather than new code.
 *
 * Read-only.
 * Run: wp eval-file tools/diag-images.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

echo "=== WHAT THIS SERVER CAN RE-ENCODE WITH ===\n";
echo "  GD          : " . ( extension_loaded( 'gd' ) ? 'yes' : 'no' );
if ( function_exists( 'gd_info' ) ) {
    $g = gd_info();
    echo '  (webp ' . ( ! empty( $g['WebP Support'] ) ? 'yes' : 'no' )
       . ', jpeg ' . ( ! empty( $g['JPEG Support'] ) ? 'yes' : 'no' ) . ')';
}
echo "\n  Imagick     : " . ( extension_loaded( 'imagick' ) ? 'yes' : 'no' ) . "\n";
foreach ( array( 'cwebp', 'convert', 'jpegoptim', 'optipng' ) as $bin ) {
    $path = trim( (string) @shell_exec( 'command -v ' . escapeshellarg( $bin ) . ' 2>/dev/null' ) );
    echo "  {$bin}" . str_repeat( ' ', max( 1, 12 - strlen( $bin ) ) ) . ": " . ( $path !== '' ? $path : 'not available' ) . "\n";
}
echo "  WP editor   : " . ( class_exists( 'WP_Image_Editor' ) ? implode( ', ', array_filter( array(
        extension_loaded('imagick') ? 'Imagick' : '', extension_loaded('gd') ? 'GD' : '' ) ) ) : '?' ) . "\n";
echo "  JPEG quality filter currently returns: " . apply_filters( 'wp_editor_set_quality', 82, 'image/jpeg' ) . "\n";

echo "\n=== LITESPEED CACHE: WHAT IS TURNED ON ===\n";
$ls = get_option( 'litespeed.conf', array() );
if ( ! $ls ) {
    // Newer versions store each key separately.
    global $wpdb;
    $rows = $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'litespeed.conf.%'" );
    foreach ( $rows as $r ) $ls[ str_replace( 'litespeed.conf.', '', $r->option_name ) ] = $r->option_value;
}
$interesting = array(
    'optm-css_min' => 'minify CSS', 'optm-css_comb' => 'COMBINE CSS',
    'optm-js_min' => 'minify JS',  'optm-js_comb'  => 'COMBINE JS',
    'optm-js_defer' => 'defer JS', 'optm-css_async' => 'async CSS',
    'optm-qs_rm' => 'strip query strings', 'optm-ggfonts_async' => 'async Google fonts',
    'img_optm-auto' => 'IMAGE OPTIMISATION automatic', 'img_optm-ori' => 'optimise originals',
    'img_optm-webp' => 'serve WebP', 'img_optm-lossless' => 'lossless',
    'cache-browser' => 'browser cache', 'optm-emoji_rm' => 'remove emoji script',
    'media-lazy' => 'lazy-load images', 'guest' => 'guest mode',
);
if ( ! $ls ) echo "  (no LiteSpeed settings readable from the options table)\n";
foreach ( $interesting as $k => $label ) {
    if ( ! array_key_exists( $k, $ls ) ) continue;
    $v = $ls[ $k ];
    $shown = is_scalar( $v ) ? ( $v === '' ? "''" : (string) $v ) : gettype( $v );
    printf( "  %-32s %s\n", $label, ( $shown === '1' ? 'ON' : ( $shown === '0' ? 'off' : $shown ) ) );
}

echo "\n=== HOW HEAVY THE CARD-SIZED DERIVATIVES ARE ===\n";
echo "  A well-compressed photograph lands near 250-500 KB per megapixel as\n";
echo "  JPEG and lower as WebP. Anything far above that is compression, not size.\n\n";

$up   = wp_upload_dir();
$base = rtrim( $up['basedir'], '/' );

$ids = get_posts( array( 'post_type' => 'attachment', 'post_mime_type' => array( 'image/jpeg', 'image/webp', 'image/png' ),
                         'post_status' => 'inherit', 'posts_per_page' => 1200, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'DESC' ) );

$rows = array(); $totalBytes = 0; $totalOver = 0; $n = 0;
foreach ( $ids as $id ) {
    $meta = wp_get_attachment_metadata( $id );
    if ( empty( $meta['file'] ) || empty( $meta['sizes'] ) ) continue;
    $dir = trailingslashit( $base . '/' . dirname( $meta['file'] ) );
    foreach ( $meta['sizes'] as $name => $s ) {
        if ( strpos( $name, 'woocommerce_thumbnail' ) === false && $name !== 'medium_large' ) continue;
        $f = $dir . $s['file'];
        if ( ! file_exists( $f ) ) continue;
        $bytes = filesize( $f );
        $mp    = max( 0.01, ( (int) $s['width'] * (int) $s['height'] ) / 1000000 );
        $perMp = $bytes / $mp;
        $n++; $totalBytes += $bytes;
        if ( $perMp > 800000 ) $totalOver += $bytes;
        $rows[] = array( 'f' => $s['file'], 'b' => $bytes, 'w' => $s['width'], 'h' => $s['height'],
                         'mime' => $s['mime-type'] ?? '', 'perMp' => $perMp );
    }
}

usort( $rows, function ( $a, $b ) { return $b['b'] - $a['b']; } );
printf( "  measured %d card derivatives, %s MB in total\n", $n, number_format( $totalBytes / 1048576, 1 ) );
printf( "  of those, %s MB sit in files above 800 KB per megapixel\n\n", number_format( $totalOver / 1048576, 1 ) );

echo "  --- the 20 heaviest ---\n";
foreach ( array_slice( $rows, 0, 20 ) as $r ) {
    printf( "  %7s KB  %5dx%-5d %-11s %6s KB/MP  %s\n",
        round( $r['b'] / 1024 ), $r['w'], $r['h'], str_replace( 'image/', '', $r['mime'] ),
        round( $r['perMp'] / 1024 ), substr( $r['f'], 0, 58 ) );
}

$bands = array( '0-300 KB/MP (fine)' => 0, '300-800 (acceptable)' => 0, '800-2000 (heavy)' => 0, 'over 2000 (very heavy)' => 0 );
foreach ( $rows as $r ) {
    $k = $r['perMp'] / 1024;
    if ( $k < 300 ) $bands['0-300 KB/MP (fine)']++;
    elseif ( $k < 800 ) $bands['300-800 (acceptable)']++;
    elseif ( $k < 2000 ) $bands['800-2000 (heavy)']++;
    else $bands['over 2000 (very heavy)']++;
}
echo "\n  --- distribution ---\n";
foreach ( $bands as $k => $v ) printf( "  %-26s %5d files\n", $k, $v );
