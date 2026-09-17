<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * What the digital-download masters actually are, and what they cost to keep.
 *
 * Before proposing anything: how big are these files really, where do they
 * live, which of them a customer actually receives, and how much of the disk
 * they occupy. Every option here trades quality against storage, and that
 * trade cannot be discussed on estimates.
 *
 * Read-only.
 * Run: wp eval-file tools/diag-digital-storage.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

function af_ds_human( $b ) {
    $u = array( 'B', 'KB', 'MB', 'GB', 'TB' ); $i = 0;
    while ( $b >= 1024 && $i < 4 ) { $b /= 1024; $i++; }
    return sprintf( '%.1f %s', $b, $u[ $i ] );
}
function af_ds_dirsize( $dir, &$count = 0, &$biggest = array() ) {
    $total = 0;
    if ( ! is_dir( $dir ) ) return 0;
    $it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
                                         RecursiveIteratorIterator::LEAVES_ONLY );
    foreach ( $it as $f ) {
        if ( ! $f->isFile() ) continue;
        $s = $f->getSize(); $total += $s; $count++;
        if ( $s > 5 * 1024 * 1024 ) $biggest[ $f->getPathname() ] = $s;
    }
    return $total;
}

$up   = wp_upload_dir();
$base = rtrim( $up['basedir'], '/' );

echo "=== DISK ===\n";
echo "  uploads dir : {$base}\n";
$root = ABSPATH;
$free = @disk_free_space( $root );
$tot  = @disk_total_space( $root );
if ( $tot ) printf( "  filesystem  : %s free of %s (%.1f%% used)\n",
    af_ds_human( $free ), af_ds_human( $tot ), 100 - ( $free / $tot * 100 ) );
else echo "  filesystem  : quota not readable from PHP\n";

$count = 0; $biggest = array();
$uploads_total = af_ds_dirsize( $base, $count, $biggest );
printf( "  uploads     : %s across %d files\n", af_ds_human( $uploads_total ), $count );

$wcdir = $base . '/woocommerce_uploads';
$c2 = 0; $b2 = array();
$wc_total = af_ds_dirsize( $wcdir, $c2, $b2 );
printf( "  of which woocommerce_uploads : %s across %d files\n", af_ds_human( $wc_total ), $c2 );

echo "\n=== THE 25 LARGEST FILES ANYWHERE IN UPLOADS ===\n";
arsort( $biggest );
$i = 0;
foreach ( $biggest as $path => $size ) {
    printf( "  %10s  %s\n", af_ds_human( $size ), str_replace( $base . '/', '', $path ) );
    if ( ++$i >= 25 ) break;
}
if ( ! $biggest ) echo "  (nothing over 5 MB)\n";

echo "\n=== SIZE BANDS, ALL UPLOAD FILES ===\n";
$bands = array( 'under 1 MB' => 0, '1-5 MB' => 0, '5-20 MB' => 0, '20-60 MB' => 0, '60-200 MB' => 0, 'over 200 MB' => 0 );
$bandBytes = $bands;
$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ) );
foreach ( $it as $f ) {
    if ( ! $f->isFile() ) continue;
    $s = $f->getSize(); $mb = $s / 1048576;
    $k = $mb < 1 ? 'under 1 MB' : ( $mb < 5 ? '1-5 MB' : ( $mb < 20 ? '5-20 MB' :
         ( $mb < 60 ? '20-60 MB' : ( $mb < 200 ? '60-200 MB' : 'over 200 MB' ) ) ) );
    $bands[ $k ]++; $bandBytes[ $k ] += $s;
}
foreach ( $bands as $k => $n ) printf( "  %-14s %6d files  %10s\n", $k, $n, af_ds_human( $bandBytes[ $k ] ) );

echo "\n=== HOW DIGITAL DOWNLOADS ARE SET UP ===\n";
echo "  WC download method : " . get_option( 'woocommerce_file_download_method', 'force' ) . "\n";
echo "  access expiry days : " . ( get_option( 'woocommerce_downloads_expiry', '' ) ?: '(never)' ) . "\n";
echo "  download limit     : " . ( get_option( 'woocommerce_limit_downloads', '' ) ?: '(unlimited)' ) . "\n";
echo "  require login      : " . get_option( 'woocommerce_downloads_require_login', 'no' ) . "\n";

$dl = get_posts( array(
    'post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids',
    'meta_query' => array( array( 'key' => '_downloadable', 'value' => 'yes' ) ),
) );
printf( "\n  products flagged downloadable : %d\n", count( $dl ) );

$files_total = 0; $files_n = 0; $missing = 0; $rows = array();
foreach ( $dl as $pid ) {
    $p = wc_get_product( $pid );
    if ( ! $p ) continue;
    foreach ( $p->get_downloads() as $d ) {
        $file = $d->get_file();
        $rel  = preg_replace( '#^https?://[^/]+#', '', $file );
        $path = ABSPATH . ltrim( $rel, '/' );
        $size = file_exists( $path ) ? filesize( $path ) : 0;
        if ( ! $size ) { $missing++; continue; }
        $files_total += $size; $files_n++;
        $rows[] = array( 'pid' => $pid, 'name' => $p->get_name(), 'file' => basename( $path ), 'size' => $size );
    }
}
printf( "  attached download files       : %d, %s in total\n", $files_n, af_ds_human( $files_total ) );
printf( "  attached but missing on disk  : %d\n", $missing );
if ( $files_n ) printf( "  average per file              : %s\n", af_ds_human( $files_total / $files_n ) );

usort( $rows, function ( $a, $b ) { return $b['size'] - $a['size']; } );
echo "\n  the 10 largest attached downloads:\n";
foreach ( array_slice( $rows, 0, 10 ) as $r ) {
    printf( "    %10s  #%-6d %-42s %s\n", af_ds_human( $r['size'] ), $r['pid'],
        substr( wp_strip_all_tags( $r['name'] ), 0, 42 ), substr( $r['file'], 0, 40 ) );
}
if ( ! $rows ) echo "    (none — no product has a file attached)\n";

echo "\n=== THE DIGITAL DOWNLOADS CATEGORY ===\n";
foreach ( array( 'digital-downloads', 'digital-downloads-2' ) as $slug ) {
    $t = get_term_by( 'slug', $slug, 'product_cat' );
    if ( ! $t ) continue;
    printf( "  %-22s term #%-5d %d products\n", $t->slug, $t->term_id, $t->count );
}

echo "\n=== WHAT THE MODAL SELLS TODAY ===\n";
echo "  af_digital_price() exists : " . ( function_exists( 'af_digital_price' ) ? 'yes, ' . af_digital_price( 0 ) : 'no' ) . "\n";
echo "  (the preview modal offers an instant file; the check above shows whether\n";
echo "   any product actually has a file attached for WooCommerce to deliver)\n";
