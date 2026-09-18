<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. The Shop by Collection tab list is not built by the parent
 * theme's PHP, and it is not a plain term query either - it keeps empty
 * categories (Framed Canvases, Gifts, Direct from Artists all hold nothing)
 * while leaving out Corporate Printing, which holds 33 products. So it is a
 * hand-picked list, and Elementor is where the homepage's widgets live.
 *
 * Find the widget that holds it and print the setting, so the list can be
 * edited precisely rather than by pattern-matching a megabyte of JSON.
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$front = (int) get_option( 'page_on_front' );
echo "=== THE FRONT PAGE ===\n";
printf( "  page_on_front = %d  \"%s\"\n", $front, $front ? get_the_title( $front ) : '(none)' );
if ( ! $front ) { echo "ABORT: no static front page.\n"; return; }

$raw = get_post_meta( $front, '_elementor_data', true );
if ( ! $raw ) { echo "ABORT: that page has no _elementor_data.\n"; return; }
printf( "  _elementor_data: %d bytes\n", strlen( is_string( $raw ) ? $raw : wp_json_encode( $raw ) ) );

$data = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
if ( ! is_array( $data ) ) { echo "ABORT: could not decode it.\n"; return; }

// The tab captions, as the live strip shows them.
$WANT = array( 'digital-canvas-prints', 'art-accessories', 'banners-signage',
               'framed-canvases', 'direct-from-artists', 'digital-downloads-2',
               'home-decor-by-space', 'personalised-prints', 'gifts' );

$hits = array();
$walk = function ( $nodes, $path ) use ( &$walk, &$hits, $WANT ) {
    foreach ( (array) $nodes as $i => $n ) {
        if ( ! is_array( $n ) ) continue;
        $here = $path . '/' . $i . ( isset( $n['widgetType'] ) ? '(' . $n['widgetType'] . ')' : '' );
        $s = isset( $n['settings'] ) && is_array( $n['settings'] ) ? $n['settings'] : array();
        if ( $s ) {
            $blob = wp_json_encode( $s );
            $score = 0;
            foreach ( $WANT as $w ) if ( strpos( $blob, $w ) !== false ) $score++;
            if ( $score >= 3 ) {
                $hits[] = array( 'path' => $here, 'id' => $n['id'] ?? '?',
                    'type' => $n['widgetType'] ?? ( $n['elType'] ?? '?' ),
                    'score' => $score, 'settings' => $s );
            }
        }
        if ( ! empty( $n['elements'] ) ) $walk( $n['elements'], $here );
    }
};
$walk( $data, '' );

printf( "\n=== WIDGETS WHOSE SETTINGS NAME THE TAB SLUGS ===\n  %d found\n", count( $hits ) );
foreach ( $hits as $h ) {
    printf( "\n  --- %s   id=%s   type=%s   (matched %d of %d slugs)\n",
        $h['path'], $h['id'], $h['type'], $h['score'], count( $WANT ) );
    foreach ( $h['settings'] as $k => $v ) {
        $blob = is_scalar( $v ) ? (string) $v : wp_json_encode( $v );
        $names = 0;
        foreach ( $WANT as $w ) if ( strpos( $blob, $w ) !== false ) $names++;
        if ( ! $names && strlen( $blob ) > 160 ) continue;      // skip unrelated bulk
        printf( "      %-28s = %s%s\n", $k, substr( $blob, 0, 420 ),
            strlen( $blob ) > 420 ? ' ...' : '' );
    }
}
if ( ! $hits ) {
    echo "\n  Nothing in _elementor_data names those slugs. The list is held\n";
    echo "  somewhere else - a theme option, a plugin setting, or term meta.\n";
    echo "\n  Scanning options for the same slugs...\n";
    global $wpdb;
    $like = '%' . $wpdb->esc_like( 'framed-canvases' ) . '%';
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT option_name, LENGTH(option_value) AS len FROM {$wpdb->options}
          WHERE option_value LIKE %s LIMIT 25", $like ) );
    foreach ( (array) $rows as $r ) printf( "      option %-44s %d bytes\n", $r->option_name, $r->len );
}
