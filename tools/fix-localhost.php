<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * The homepage hero slider's buttons link to the demo author's development
 * machine: http://localhost/wordpress/postero/shop/. Twenty-seven rows across
 * three Slider Revolution columns, stored as JSON with escaped slashes.
 *
 * Replaces the demo PREFIX, http://localhost/wordpress/postero/, with this
 * site's own, so /shop/ becomes /shop/ here. Both the JSON-escaped and plain
 * spellings are handled. Every distinct address the change produces is
 * printed, so an unexpected mapping is seen before it is written.
 *
 * Run: DRY=1 wp eval-file tools/fix-localhost.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }
global $wpdb;
$dry  = (bool) getenv( 'DRY' );
$home = untrailingslashit( home_url() ) . '/';                        // https://theartframer.us/
$pairs = array(
    array( 'http:\\/\\/localhost\\/wordpress\\/postero\\/', str_replace( '/', '\\/', $home ) ),   // JSON-escaped
    array( 'http://localhost/wordpress/postero/',          $home ),                            // plain
);
echo '=== DEMO LOCALHOST LINKS ' . ( $dry ? '(DRY RUN)' : '(WRITING)' ) . " ===\n  replacing prefix -> {$home}\n\n";
$total = 0; $seen = array();
foreach ( $wpdb->get_col( 'SHOW TABLES' ) as $t ) {
    if ( stripos( $t, 'revslider' ) === false ) continue;
    foreach ( $wpdb->get_results( "SHOW COLUMNS FROM `{$t}`" ) as $c ) {
        if ( ! preg_match( '/char|text|blob|json/i', $c->Type ) ) continue;
        foreach ( $pairs as list( $bad, $good ) ) {
            $like = '%' . $wpdb->esc_like( $bad ) . '%';
            $n = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$t}` WHERE `{$c->Field}` LIKE %s", $like ) );
            if ( ! $n ) continue;
            // what will the addresses become?
            foreach ( $wpdb->get_col( $wpdb->prepare( "SELECT `{$c->Field}` FROM `{$t}` WHERE `{$c->Field}` LIKE %s LIMIT 20", $like ) ) as $v ) {
                if ( preg_match_all( '#' . preg_quote( $bad, '#' ) . '[^"\']*#', $v, $m ) )
                    foreach ( $m[0] as $u ) $seen[ str_replace( '\\/', '/', str_replace( $bad, $good, $u ) ) ] = true;
            }
            printf( "  %-26s %-8s %2d row(s)  %s\n", $t, $c->Field, $n, $dry ? 'WOULD rewrite' : 'rewriting' );
            $total += $n;
            if ( ! $dry ) {
                $wpdb->query( $wpdb->prepare( "UPDATE `{$t}` SET `{$c->Field}` = REPLACE(`{$c->Field}`, %s, %s) WHERE `{$c->Field}` LIKE %s", $bad, $good, $like ) );
            }
        }
    }
}
echo "\n  every address this produces:\n";
foreach ( array_keys( $seen ) as $u ) echo "      {$u}\n";
printf( "\n  %d row(s) %s\n", $total, $dry ? 'would change' : 'changed' );
if ( ! $dry && $total ) {
    // Slider Revolution keeps no output cache in the options table for v6, but
    // the page that embeds it is cached by LiteSpeed.
    if ( function_exists( 'do_action' ) ) do_action( 'litespeed_purge_all' );
    echo "  LiteSpeed cache purged.\n";
}
