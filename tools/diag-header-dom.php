<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * READ-ONLY. Fetches the rendered home page server-side and prints the markup
 * around the header contact row (phone number, Facebook/Instagram links) plus
 * whether the country-selector markup is present. Used to see the real header
 * structure so the currency selector can be relocated reliably. No changes.
 *
 * Run: wp eval-file tools/diag-header-dom.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$url  = home_url( '/?afnocache=' . time() );
$resp = wp_remote_get( $url, array( 'timeout' => 25, 'sslverify' => false,
    'headers' => array( 'User-Agent' => 'af-header-diag' ) ) );

echo "=== HEADER DOM DIAG ===\n";
echo "url: {$url}\n";
if ( is_wp_error( $resp ) ) { echo "FETCH ERROR: " . $resp->get_error_message() . "\n=== DONE ===\n"; return; }
$code = wp_remote_retrieve_response_code( $resp );
$html = wp_remote_retrieve_body( $resp );
echo "http: {$code} | html length: " . strlen( $html ) . "\n";

foreach ( array(
    'afCty'              => 'country selector id',
    'af-cty'             => 'country selector class',
    'af-ub-hidden-host'  => 'hidden host wrapper',
    'af-cty--inline'     => 'inline (relocated) class',
) as $needle => $label ) {
    echo "contains {$needle} ({$label}): " . ( strpos( $html, $needle ) !== false ? 'YES' : 'NO' ) . "\n";
}

// Dump a window of markup around each landmark so the real structure is visible.
$targets = array( '470-7280', '4707280', 'tel:', 'facebook', 'instagram', 'mailto:' );
foreach ( $targets as $needle ) {
    $p = stripos( $html, $needle );
    echo "\n----- around '{$needle}' (pos " . ( $p === false ? 'NOT FOUND' : $p ) . ") -----\n";
    if ( $p !== false ) {
        $chunk = substr( $html, max( 0, $p - 450 ), 950 );
        // collapse whitespace so the deploy log stays readable
        echo preg_replace( '/\s+/', ' ', $chunk ) . "\n";
    }
}
echo "\n=== DONE ===\n";
