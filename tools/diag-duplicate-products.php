<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * READ-ONLY duplicate/repeated product check. Makes no changes.
 * Flags products that share the same:
 *   A) exact title (normalized: lowercased, whitespace-collapsed)
 *   B) SKU (when set)
 *   C) main (featured) image file
 * Run: wp eval-file tools/diag-duplicate-products.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

function taf_norm_title( $t ) {
    $t = html_entity_decode( wp_strip_all_tags( (string) $t ), ENT_QUOTES );
    $t = strtolower( trim( $t ) );
    return preg_replace( '/\s+/', ' ', $t );
}

$ids = get_posts( array(
    'post_type'      => 'product',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'fields'         => 'ids',
) );

$rows = array();
foreach ( $ids as $pid ) {
    $thumb = get_post_thumbnail_id( $pid );
    $file  = $thumb ? basename( (string) get_post_meta( $thumb, '_wp_attached_file', true ) ) : '';
    $rows[ $pid ] = array(
        'id'    => $pid,
        'title' => html_entity_decode( wp_strip_all_tags( get_the_title( $pid ) ) ),
        'sku'   => trim( (string) get_post_meta( $pid, '_sku', true ) ),
        'file'  => $file,
        'edit'  => admin_url( 'post.php?post=' . $pid . '&action=edit' ),
    );
}

echo "=== DUPLICATE PRODUCT CHECK ===\n";
echo "published products checked: " . count( $rows ) . "\n";

function taf_report_groups( $label, $groups, $rows ) {
    $dups = array_filter( $groups, function ( $v ) { return count( $v ) > 1; } );
    echo "\n--- {$label}: " . count( $dups ) . " duplicate group(s) ---\n";
    if ( ! $dups ) { echo "  none found\n"; return; }
    uasort( $dups, function ( $a, $b ) { return count( $b ) - count( $a ); } );
    foreach ( $dups as $key => $pids ) {
        echo "  '{$key}' (" . count( $pids ) . " products):\n";
        foreach ( $pids as $pid ) {
            $r = $rows[ $pid ];
            echo "    #{$pid}  {$r['title']}  (sku='{$r['sku']}')\n";
        }
    }
}

// A) exact normalized title
$by_title = array();
foreach ( $rows as $pid => $r ) { $by_title[ taf_norm_title( $r['title'] ) ][] = $pid; }
taf_report_groups( 'A) Same title', $by_title, $rows );

// B) same SKU (ignore blanks)
$by_sku = array();
foreach ( $rows as $pid => $r ) { if ( $r['sku'] !== '' ) $by_sku[ strtoupper( $r['sku'] ) ][] = $pid; }
taf_report_groups( 'B) Same SKU', $by_sku, $rows );

// C) same main image file (ignore blanks)
$by_file = array();
foreach ( $rows as $pid => $r ) { if ( $r['file'] !== '' ) $by_file[ strtolower( $r['file'] ) ][] = $pid; }
taf_report_groups( 'C) Same main image', $by_file, $rows );

echo "\n=== DONE ===\n";
