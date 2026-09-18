<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * The empty-href fix wrote post_content on pages 6030 and 6032 and reported
 * success, yet the live pages still serve href="". Those pages are built with
 * Elementor, which renders from the _elementor_data meta and treats
 * post_content as a stale mirror. So: where, exactly, do the empty anchors
 * live in _elementor_data, and in which widget type?
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }
foreach ( array( 6030, 6032 ) as $pid ) {
    $raw = get_post_meta( $pid, '_elementor_data', true );
    $edit = get_post_meta( $pid, '_elementor_edit_mode', true );
    printf( "=== #%d %s  elementor edit_mode=%s  _elementor_data=%s bytes ===\n", $pid, get_the_title( $pid ), $edit ?: '(none)', is_string( $raw ) ? strlen( $raw ) : gettype( $raw ) );
    if ( ! is_string( $raw ) || $raw === '' ) { echo "  no _elementor_data — the page is NOT Elementor-rendered\n\n"; continue; }
    // count empty-href anchors in the escaped JSON, and show each with its widget
    $n = preg_match_all( '#href=\\\\"\\\\"|href=\\\\\'\\\\\'|href=""|href=\'\'#', $raw, $mm, PREG_OFFSET_CAPTURE );
    echo "  empty href occurrences inside _elementor_data: {$n}\n";
    foreach ( $mm[0] as $m ) {
        $pos = $m[1];
        // nearest widgetType before this position
        $before = substr( $raw, max( 0, $pos - 4000 ), min( 4000, $pos ) );
        preg_match_all( '#"widgetType":"([^"]+)"#', $before, $w );
        $wt = $w[1] ? end( $w[1] ) : '(unknown)';
        $ctx = str_replace( '\\/', '/', substr( $raw, max( 0, $pos - 60 ), 160 ) );
        printf( "    widget=%-18s ...%s...\n", $wt, preg_replace( '/\s+/', ' ', $ctx ) );
    }
    // and does post_content still contain any? (it should not, after the write)
    $pc = get_post_field( 'post_content', $pid );
    echo "  empty hrefs left in post_content: " . preg_match_all( '#<a\b[^>]*href=(["\'])\1#i', $pc ) . "\n\n";
}
