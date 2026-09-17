<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Name every stylesheet and script the site loads, and say who owns it.
 *
 * The browser audit counted 75 stylesheets and 79 synchronous scripts on every
 * page. That is the loading time. But "75 stylesheets" is not actionable until
 * each one has a name and an owner, because the fix — not loading a thing on a
 * page that does not use it — is only safe when you know what the thing is.
 *
 * Read-only. Prints handles grouped by plugin, with size on disk, so the
 * biggest offenders and the obviously page-specific ones are visible.
 *
 * Run: wp eval-file tools/diag-assets.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

// Make WordPress believe it is rendering a front-end page so conditional
// enqueues fire the way they do for a visitor.
if ( ! did_action( 'wp_enqueue_scripts' ) ) {
    do_action( 'wp_enqueue_scripts' );
}

function af_as_owner( $src ) {
    if ( ! $src ) return '(inline/dependency)';
    if ( strpos( $src, '/wp-content/plugins/' ) !== false ) {
        preg_match( '#/wp-content/plugins/([^/]+)/#', $src, $m );
        return 'plugin: ' . ( $m[1] ?? '?' );
    }
    if ( strpos( $src, '/wp-content/themes/' ) !== false ) {
        preg_match( '#/wp-content/themes/([^/]+)/#', $src, $m );
        return 'theme: ' . ( $m[1] ?? '?' );
    }
    if ( strpos( $src, '/wp-includes/' ) !== false ) return 'core';
    if ( preg_match( '#^https?://#', $src ) ) return 'external';
    return 'other';
}

function af_as_bytes( $src ) {
    if ( ! $src || preg_match( '#^https?://#', $src ) && strpos( $src, home_url() ) !== 0 ) return 0;
    $rel  = preg_replace( '#^https?://[^/]+#', '', $src );
    $rel  = preg_replace( '#\?.*$#', '', $rel );
    $path = ABSPATH . ltrim( $rel, '/' );
    return file_exists( $path ) ? filesize( $path ) : 0;
}

foreach ( array( 'STYLES' => wp_styles(), 'SCRIPTS' => wp_scripts() ) as $kind => $reg ) {
    $rows = array();
    foreach ( $reg->queue as $handle ) {
        $done = array( $handle );
        // include dependencies, which is where the count really comes from
        $reg->all_deps( array( $handle ) );
    }
    $all = $reg->to_do ? $reg->to_do : $reg->queue;
    foreach ( $all as $handle ) {
        $o = $reg->registered[ $handle ] ?? null;
        if ( ! $o ) continue;
        $src = $o->src;
        if ( $src && strpos( $src, 'http' ) !== 0 ) $src = site_url( $src );
        $rows[] = array(
            'handle' => $handle,
            'owner'  => af_as_owner( $src ),
            'bytes'  => af_as_bytes( $src ),
            'src'    => $src ? preg_replace( '#^https?://[^/]+#', '', $src ) : '',
        );
    }

    usort( $rows, function ( $a, $b ) { return strcmp( $a['owner'], $b['owner'] ) ?: $b['bytes'] - $a['bytes']; } );

    $total = array_sum( wp_list_pluck( $rows, 'bytes' ) );
    echo "\n=== {$kind}: " . count( $rows ) . " enqueued, " . round( $total / 1024 ) . " KB on disk ===\n";
    $byOwner = array();
    foreach ( $rows as $r ) {
        $byOwner[ $r['owner'] ]['n'] = ( $byOwner[ $r['owner'] ]['n'] ?? 0 ) + 1;
        $byOwner[ $r['owner'] ]['b'] = ( $byOwner[ $r['owner'] ]['b'] ?? 0 ) + $r['bytes'];
    }
    uasort( $byOwner, function ( $a, $b ) { return $b['n'] - $a['n']; } );
    echo "  --- by owner ---\n";
    foreach ( $byOwner as $owner => $v ) {
        printf( "  %3d files  %7s KB  %s\n", $v['n'], round( $v['b'] / 1024 ), $owner );
    }
    echo "  --- every handle ---\n";
    foreach ( $rows as $r ) {
        printf( "  %-38s %7s KB  %-28s %s\n",
            substr( $r['handle'], 0, 38 ), $r['bytes'] ? round( $r['bytes'] / 1024 ) : '-',
            $r['owner'], substr( $r['src'], 0, 70 ) );
    }
}

echo "\n=== ACTIVE PLUGINS ===\n";
foreach ( get_option( 'active_plugins', array() ) as $p ) echo "  {$p}\n";

echo "\n=== IMAGE SIZES REGISTERED ===\n";
global $_wp_additional_image_sizes;
foreach ( array( 'thumbnail', 'medium', 'medium_large', 'large' ) as $s ) {
    echo sprintf( "  %-22s %sx%s\n", $s, get_option( $s . '_size_w' ), get_option( $s . '_size_h' ) );
}
foreach ( (array) $_wp_additional_image_sizes as $name => $v ) {
    echo sprintf( "  %-22s %sx%s%s\n", $name, $v['width'], $v['height'], ! empty( $v['crop'] ) ? ' (crop)' : '' );
}
echo "\n  woocommerce_thumbnail : " . wc_get_image_size( 'woocommerce_thumbnail' )['width'] . 'x' . wc_get_image_size( 'woocommerce_thumbnail' )['height'] . "\n";
echo "  shop catalog column   : " . ( function_exists('wc_get_loop_prop') ? '' : '' ) . get_option( 'woocommerce_thumbnail_image_width', '(unset)' ) . "\n";
