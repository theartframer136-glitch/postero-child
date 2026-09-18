<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * The Login page (6030) and Sign-up page (6032) carry buttons with href="".
 * "Register Now" belongs on the sign-up page; "Sign In" on the login page.
 * Only anchors whose href is EMPTY and whose text is exactly one of those two
 * labels are touched; the label itself is captured and kept.
 * Run: DRY=1 wp eval-file tools/fix-empty-hrefs.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }
$dry = (bool) getenv( 'DRY' );
$map = array( 'Register Now' => home_url( '/sign-up/' ), 'Sign In' => home_url( '/login/' ) );
echo '=== EMPTY HREF BUTTONS ' . ( $dry ? '(DRY RUN)' : '(WRITING)' ) . " ===\n";
foreach ( array( 6030, 6032 ) as $pid ) {
    $p = get_post( $pid ); if ( ! $p ) { echo "  #{$pid}: missing\n"; continue; }
    $c = $p->post_content; $n = 0;
    foreach ( $map as $label => $url ) {
        //         <a  attrs-before   href=""   attrs-after >  optional inner tags   LABEL
        $re = '#<a\b([^>]*?)href=(["\'])\2([^>]*)>(\s*(?:<[^>]+>\s*)*)(' . preg_quote( $label, '#' ) . ')#i';
        $c = preg_replace_callback( $re, function ( $m ) use ( $url, &$n ) {
            $n++;
            return '<a' . $m[1] . 'href="' . esc_url( $url ) . '"' . $m[3] . '>' . $m[4] . $m[5];
        }, $c );
    }
    $left = preg_match_all( '#<a\b[^>]*href=(["\'])\1#i', $c );
    printf( "  #%d %-8s : %d repointed, %d empty href(s) still left (other buttons)\n", $pid, $p->post_title, $n, $left );
    if ( ! $dry && $n ) { wp_update_post( array( 'ID' => $pid, 'post_content' => $c ) ); echo "      written\n"; }
}
if ( ! $dry && function_exists( 'do_action' ) ) do_action( 'litespeed_purge_all' );
