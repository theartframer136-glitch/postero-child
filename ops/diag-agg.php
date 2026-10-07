<?php
/**
 * Aggregates the AF Request Profiler log (uploads/af-req-log.txt) into a
 * CPU-attribution report. Pure PHP, no WordPress needed:
 *   php ops/diag-agg.php /path/to/af-req-log.txt
 * Columns: time \t dur \t peakMB \t type \t ip \t method \t uri \t ua
 */
$f = $argv[1] ?? '';
$lines = @file( $f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
if ( ! $lines ) { echo "no log lines at $f\n"; exit( 0 ); }

$rows = array();
foreach ( $lines as $l ) {
    $p = explode( "\t", $l );
    if ( count( $p ) < 8 ) { continue; }
    $rows[] = array(
        'time' => $p[0], 'dur' => (float) $p[1], 'mem' => (int) $p[2],
        'type' => $p[3], 'ip' => $p[4], 'method' => $p[5],
        'uri' => $p[6], 'ua' => $p[7],
    );
}
if ( ! $rows ) { echo "no parseable rows\n"; exit( 0 ); }

$t0 = strtotime( 'today ' . $rows[0]['time'] . ' UTC' );
$t1 = strtotime( 'today ' . $rows[count( $rows ) - 1]['time'] . ' UTC' );
$window = max( 1, $t1 - $t0 );
$total_dur = 0.0;
foreach ( $rows as $r ) { $total_dur += $r['dur']; }

printf( "window: %s .. %s UTC (%d s)\n", $rows[0]['time'], $rows[count( $rows ) - 1]['time'], $window );
printf( "requests reaching PHP: %d  (%.1f/min)\n", count( $rows ), count( $rows ) * 60 / $window );
printf( "total PHP wall time: %.1f s  => avg concurrency ~%.2f workers busy\n", $total_dur, $total_dur / $window );
printf( "(sustained CPU%% from PHP in this window is roughly that concurrency x100, if CPU-bound)\n\n" );

// Normalize a URI to a bucket: path only, but keep ajax action / wc-ajax names.
function af_bucket( $r ) {
    $uri = $r['uri'];
    $path = parse_url( $uri, PHP_URL_PATH ) ?: $uri;
    $q = parse_url( $uri, PHP_URL_QUERY ) ?: '';
    parse_str( $q, $qs );
    if ( isset( $qs['wc-ajax'] ) )                   { return 'wc-ajax=' . $qs['wc-ajax']; }
    if ( strpos( $path, 'admin-ajax.php' ) !== false ) { return 'admin-ajax action=' . ( $qs['action'] ?? ( $r['method'] === 'POST' ? '(post-body)' : '(none)' ) ); }
    if ( isset( $qs['s'] ) )                          { return '(search ?s=)'; }
    if ( strpos( $path, '/product/' ) === 0 )         { return '/product/*'; }
    if ( strpos( $path, '/product-category/' ) === 0 ){ return '/product-category/*'; }
    if ( strpos( $path, '/wp-json/' ) === 0 ) {
        $seg = explode( '/', trim( $path, '/' ) );
        return '/wp-json/' . ( $seg[1] ?? '' ) . '/' . ( $seg[2] ?? '' );
    }
    if ( $q !== '' && $path === '/' )                 { return '/?' . implode( ',', array_keys( $qs ) ); }
    return $path;
}

function af_top( $rows, $keyfn, $n, $title ) {
    $agg = array();
    foreach ( $rows as $r ) {
        $k = $keyfn( $r );
        if ( ! isset( $agg[$k] ) ) { $agg[$k] = array( 0, 0.0 ); }
        $agg[$k][0]++;
        $agg[$k][1] += $r['dur'];
    }
    uasort( $agg, function ( $a, $b ) { return $b[1] <=> $a[1]; } );
    echo "-- $title (by summed PHP seconds) --\n";
    $i = 0;
    foreach ( $agg as $k => $v ) {
        if ( $i++ >= $n ) { break; }
        printf( "  %7.1fs  %5d req  avg %5.2fs  %s\n", $v[1], $v[0], $v[1] / $v[0], substr( $k, 0, 95 ) );
    }
    echo "\n";
}

af_top( $rows, function ( $r ) { return $r['type']; },       10, 'request type' );
af_top( $rows, function ( $r ) { return af_bucket( $r ); },  20, 'endpoint' );
af_top( $rows, function ( $r ) { return $r['ua']; },         15, 'user agent' );
af_top( $rows, function ( $r ) { return $r['ip']; },         12, 'client IP' );

// Slowest individual requests — the shape of the worst offenders.
usort( $rows, function ( $a, $b ) { return $b['dur'] <=> $a['dur']; } );
echo "-- slowest 12 individual requests --\n";
foreach ( array_slice( $rows, 0, 12 ) as $r ) {
    printf( "  %6.1fs %4dMB %-5s %s %s  %s\n", $r['dur'], $r['mem'], $r['type'], $r['method'], substr( $r['uri'], 0, 80 ), substr( $r['ua'], 0, 40 ) );
}
echo "\nAF-AGG-DONE\n";
