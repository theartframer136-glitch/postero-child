<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * What the download limit and expiry ACTUALLY are.
 *
 * My earlier probe read get_option('woocommerce_downloads_expiry') and
 * get_option('woocommerce_limit_downloads'). Neither option exists in
 * WooCommerce. Both reads returned my own fallback strings, and I reported
 * "never expires, unlimited" as though it were a measurement. It was not a
 * measurement of anything.
 *
 * Limit and expiry are PER PRODUCT meta, and — the part that matters for
 * re-issuing files to past buyers — WooCommerce copies them into each
 * permission row at the moment access is granted. Changing the product later
 * does not touch rows already written. So this reads both: the product meta,
 * and the permission rows real customers hold.
 *
 * Read-only.
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }
global $wpdb;

echo "=== THE OPTIONS I WRONGLY READ ===\n";
foreach ( array( 'woocommerce_downloads_expiry', 'woocommerce_limit_downloads' ) as $o ) {
    $raw = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name=%s", $o ) );
    printf( "  %-32s %s\n", $o, $raw === null ? 'DOES NOT EXIST (my fallback text was fiction)' : $raw );
}

echo "\n=== REAL SETTINGS THAT DO EXIST ===\n";
foreach ( array( 'woocommerce_file_download_method', 'woocommerce_downloads_require_login',
                 'woocommerce_downloads_grant_access_after_payment',
                 'woocommerce_downloads_redirect_fallback_allowed' ) as $o ) {
    $v = get_option( $o, '(unset)' );
    printf( "  %-48s %s\n", $o, is_scalar( $v ) ? $v : gettype( $v ) );
}

echo "\n=== PER-PRODUCT LIMIT AND EXPIRY (downloadable products) ===\n";
$dl = get_posts( array( 'post_type' => 'product', 'post_status' => array( 'publish','draft','private' ),
    'posts_per_page' => -1, 'fields' => 'ids',
    'meta_query' => array( array( 'key' => '_downloadable', 'value' => 'yes' ) ) ) );
$lim = array(); $exp = array();
foreach ( $dl as $pid ) {
    $l = (string) get_post_meta( $pid, '_download_limit', true );
    $e = (string) get_post_meta( $pid, '_download_expiry', true );
    $lk = ( $l === '' || $l === '-1' ) ? 'unlimited' : $l;
    $ek = ( $e === '' || $e === '-1' ) ? 'never' : $e . ' days';
    $lim[ $lk ] = ( $lim[ $lk ] ?? 0 ) + 1;
    $exp[ $ek ] = ( $exp[ $ek ] ?? 0 ) + 1;
}
printf( "  across %d downloadable products\n", count( $dl ) );
echo "  download limit:\n";  foreach ( $lim as $k => $n ) printf( "      %-14s %d products\n", $k, $n );
echo "  expiry:\n";          foreach ( $exp as $k => $n ) printf( "      %-14s %d products\n", $k, $n );

echo "\n=== WHAT REAL CUSTOMERS ACTUALLY HOLD ===\n";
$t = $wpdb->prefix . 'woocommerce_downloadable_product_permissions';
$exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $t ) );
if ( ! $exists ) { echo "  no permissions table\n"; return; }
printf( "  permission rows : %d\n", (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t}" ) );
$rows = $wpdb->get_results( "
    SELECT downloads_remaining, access_expires, COUNT(*) n
    FROM {$t} GROUP BY downloads_remaining, access_expires ORDER BY n DESC LIMIT 15" );
foreach ( $rows as $r ) {
    printf( "      remaining=%-10s expires=%-22s %d rows\n",
        ( $r->downloads_remaining === '' || $r->downloads_remaining === null ) ? 'unlimited' : $r->downloads_remaining,
        $r->access_expires === null ? 'never' : $r->access_expires, $r->n );
}
$expired = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE access_expires IS NOT NULL AND access_expires < NOW()" );
$used    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE downloads_remaining='0'" );
printf( "\n  rows already EXPIRED            : %d   <-- these buyers cannot re-download\n", $expired );
printf( "  rows with 0 downloads remaining : %d\n", $used );
