<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * The Login page (6030) and Sign-up page (6032) carry buttons with href="".
 * "Register Now" belongs on the sign-up page; "Sign In" on the login page.
 * Only anchors with an EMPTY href and exactly those labels are touched.
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
        // href="" or href='' immediately on an <a ...> whose visible text is the label
        $re = '#<a\b([^>]*?)href=(["\'])\2([^>]*)>(\s*(?:<[^>]+>\s*)*)' . preg_quote( $label, '#' ) . '#i';
        $c = preg_replace_callback( $re, function ( $m ) use ( $url, &$n ) { $n++; return '<a' . $m[1] . 'href="' . esc_url( $url ) . '"' . $m[3] . '>' . $m[4] . ''; }, $c );
        // the callback dropped the label text; put it back
        $c = preg_replace( '#(<a\b[^>]*href="' . preg_quote( $url, '#' ) . '"[^>]*>(?:\s*<[^>]+>\s*)*)(?!' . preg_quote( $label, '#' ) . ')#i', '$1', $c );
    }
    printf( "  #%d %-10s : %d button(s) would be repointed\n", $pid, $p->post_title, $n );
    if ( ! $dry && $n ) { wp_update_post( array( 'ID' => $pid, 'post_content' => $c ) ); echo "      written\n"; }
}
