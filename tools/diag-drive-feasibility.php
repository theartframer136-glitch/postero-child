<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Can this host serve a file that lives on Google Drive, and do the products
 * carry the art codes the filenames would be keyed on?
 *
 * Two facts decide the whole design and neither can be assumed:
 *   1. Art code coverage. "Name the file after the art code" only works for
 *      products that HAVE an art code. Partial coverage means a partial system.
 *   2. What the server can do with a remote file: stream it through PHP, how
 *      long it may run, and how much memory it may use. Those limits decide
 *      whether the buyer's download can be proxied or has to be a redirect,
 *      and that choice decides whether the Drive link stays private.
 *
 * Read-only.
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

echo "=== ART CODE COVERAGE ON DOWNLOADABLE PRODUCTS ===\n";
$dl = get_posts( array(
    'post_type' => 'product', 'post_status' => array( 'publish', 'draft', 'private' ),
    'posts_per_page' => -1, 'fields' => 'ids',
    'meta_query' => array( array( 'key' => '_downloadable', 'value' => 'yes' ) ),
) );
$with = 0; $without = 0; $codes = array(); $dupes = array(); $examples = array();
foreach ( $dl as $pid ) {
    $c = trim( (string) get_post_meta( $pid, '_taf_art_code', true ) );
    if ( $c === '' ) {
        $without++;
        if ( count( $examples ) < 8 ) $examples[] = $pid . '  ' . substr( get_the_title( $pid ), 0, 52 );
        continue;
    }
    $with++;
    if ( isset( $codes[ $c ] ) ) $dupes[ $c ] = ( $dupes[ $c ] ?? 1 ) + 1;
    $codes[ $c ] = $pid;
}
printf( "  downloadable products : %d\n", count( $dl ) );
printf( "  with an art code      : %d\n", $with );
printf( "  WITHOUT an art code   : %d   <-- these could not be matched by filename\n", $without );
printf( "  duplicate art codes   : %d   <-- two products claiming one file\n", count( $dupes ) );
if ( $examples ) { echo "  examples with no code:\n"; foreach ( $examples as $e ) echo "      #{$e}\n"; }
if ( $dupes )    { echo "  duplicated codes:\n"; $i=0; foreach ( $dupes as $c => $n ) { echo "      {$c} x{$n}\n"; if (++$i>=6) break; } }

echo "\n=== ART CODE COVERAGE ACROSS ALL PRODUCTS ===\n";
global $wpdb;
$tot  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish'" );
$have = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key='_taf_art_code' AND meta_value<>''" );
printf( "  published products %d, of which %d carry an art code (%d%%)\n", $tot, $have, $tot ? round( $have / $tot * 100 ) : 0 );

echo "\n=== WHAT THIS SERVER CAN DO WITH A REMOTE FILE ===\n";
printf( "  curl extension        : %s\n", function_exists( 'curl_init' ) ? 'yes' : 'NO' );
printf( "  allow_url_fopen       : %s\n", ini_get( 'allow_url_fopen' ) ? 'on' : 'off' );
printf( "  max_execution_time    : %s s%s\n", ini_get( 'max_execution_time' ),
    ( (int) ini_get( 'max_execution_time' ) && (int) ini_get( 'max_execution_time' ) < 300 ) ? '   <-- a 100 MB stream may not finish' : '' );
printf( "  memory_limit          : %s\n", ini_get( 'memory_limit' ) );
printf( "  output_buffering      : %s\n", ini_get( 'output_buffering' ) );
printf( "  zlib.output_compression: %s\n", ini_get( 'zlib.output_compression' ) ? 'ON  <-- breaks large streamed downloads' : 'off' );
printf( "  fastcgi finish req    : %s\n", function_exists( 'fastcgi_finish_request' ) ? 'yes' : 'no' );
printf( "  WP cron               : %s\n", ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) ? 'disabled' : 'enabled' );
printf( "  openssl               : %s\n", extension_loaded( 'openssl' ) ? 'yes' : 'NO — cannot talk to Google over https' );

echo "\n  can it reach Google at all?\n";
$r = wp_remote_head( 'https://www.googleapis.com/', array( 'timeout' => 15, 'sslverify' => true ) );
if ( is_wp_error( $r ) ) printf( "      NO — %s\n", $r->get_error_message() );
else printf( "      yes — HTTP %d from googleapis.com\n", wp_remote_retrieve_response_code( $r ) );

echo "\n=== HOW WOOCOMMERCE IS SET TO DELIVER ===\n";
printf( "  download method       : %s\n", get_option( 'woocommerce_file_download_method', 'force' ) );
printf( "  require login         : %s\n", get_option( 'woocommerce_downloads_require_login', 'no' ) );
printf( "  access expiry (days)  : %s\n", get_option( 'woocommerce_downloads_expiry', '' ) ?: '(never)' );
printf( "  download limit        : %s\n", get_option( 'woocommerce_limit_downloads', '' ) ?: '(unlimited)' );
printf( "  grant access after    : %s\n", get_option( 'woocommerce_downloads_grant_access_after_payment', 'no' ) );

echo "\n=== IS A GOOGLE INTEGRATION ALREADY PRESENT? ===\n";
$found = false;
foreach ( get_option( 'active_plugins', array() ) as $p ) {
    if ( preg_match( '/google|drive|gdrive|cloud|s3|offload|media-?library/i', $p ) ) { echo "  {$p}\n"; $found = true; }
}
if ( ! $found ) echo "  none — nothing on this site talks to Google Drive today\n";
