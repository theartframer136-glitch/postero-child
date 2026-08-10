<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * READ-ONLY consolidated Art Code report, to reconcile the website against the
 * printed brochure. For every published product it shows, grouped by top-level
 * category: the current _taf_art_code, a code guessed from the featured image
 * filename (the source files were named with their art code), the title, and
 * the filename. Plus overall coverage and per-prefix highest-number summary.
 * Makes no changes.
 *
 * Run: wp eval-file tools/diag-artcode-report.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

function afr_featured_file( $pid ) {
    $t = get_post_thumbnail_id( $pid );
    if ( ! $t ) return '';
    $f = get_post_meta( $t, '_wp_attached_file', true );
    return $f ? basename( $f ) : '';
}

// First "XX 00" style token found in the filename/slug/title, normalised to
// "XX 0" (prefix + un-padded number), or '' if none.
function afr_guess_code( $hay ) {
    if ( preg_match( '/\b([A-Za-z]{2,3})[\s\-_]?(\d{1,3})\b/', $hay, $m ) ) {
        return strtoupper( $m[1] ) . ' ' . ltrim( $m[2], '0' );
    }
    return '';
}

// Top-level category name for a product (rolls a sub-category up to its root),
// matching how the storefront groups them.
function afr_top_cat( $pid ) {
    $terms = get_the_terms( $pid, 'product_cat' );
    if ( ! $terms || is_wp_error( $terms ) ) return 'Uncategorized';
    $t = $terms[0];
    if ( $t->parent ) {
        $anc = get_ancestors( $t->term_id, 'product_cat' );
        if ( $anc ) { $rt = get_term( end( $anc ), 'product_cat' ); if ( $rt && ! is_wp_error( $rt ) ) $t = $rt; }
    }
    return html_entity_decode( wp_strip_all_tags( $t->name ) );
}

$ids = get_posts( array( 'post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids' ) );

$rows = array();
$with = 0; $without = 0;
$pref = array(); // prefix => max number among existing codes

foreach ( $ids as $pid ) {
    $code = trim( (string) get_post_meta( $pid, '_taf_art_code', true ) );
    $file = afr_featured_file( $pid );
    $slug = get_post_field( 'post_name', $pid );
    $title = html_entity_decode( wp_strip_all_tags( get_the_title( $pid ) ) );
    $guess = afr_guess_code( $file . ' ' . $slug . ' ' . get_post_meta( $pid, '_sku', true ) );

    if ( $code !== '' ) {
        $with++;
        if ( preg_match( '/^([A-Za-z]+)\s*(\d+)/', $code, $m ) ) {
            $k = strtoupper( $m[1] );
            $pref[ $k ] = isset( $pref[ $k ] ) ? max( $pref[ $k ], (int) $m[2] ) : (int) $m[2];
        }
    } else {
        $without++;
    }

    $rows[] = array(
        'cat'   => afr_top_cat( $pid ),
        'pid'   => $pid,
        'code'  => $code,
        'guess' => $guess,
        'title' => $title,
        'file'  => $file,
    );
}

echo "=== ART CODE REPORT ===\n";
echo "published products: " . count( $ids ) . " | with code: {$with} | WITHOUT code: {$without}\n\n";

echo "=== prefixes in use (existing codes) ===\n";
ksort( $pref );
foreach ( $pref as $k => $mx ) echo "  {$k}: highest existing number {$mx}\n";

// Group by category, ordered by category name; within a category order by
// existing code (then guessed) so sequences read naturally.
$by_cat = array();
foreach ( $rows as $r ) { $by_cat[ $r['cat'] ][] = $r; }
ksort( $by_cat );

foreach ( $by_cat as $cat => $list ) {
    usort( $list, function( $a, $b ) {
        $ka = $a['code'] !== '' ? $a['code'] : ( $a['guess'] !== '' ? $a['guess'] : 'zzz' );
        $kb = $b['code'] !== '' ? $b['code'] : ( $b['guess'] !== '' ? $b['guess'] : 'zzz' );
        return strnatcasecmp( $ka, $kb );
    } );
    echo "\n\n########## {$cat}  (" . count( $list ) . " products) ##########\n";
    foreach ( $list as $r ) {
        $code  = $r['code'] !== '' ? $r['code'] : 'NONE';
        $guess = $r['guess'] !== '' ? $r['guess'] : '-';
        $flag  = ( $r['code'] !== '' && $r['guess'] !== '' && strcasecmp( preg_replace('/\s+/','',$r['code']), preg_replace('/\s+/','',$r['guess']) ) !== 0 ) ? '  <<MISMATCH vs filename' : '';
        echo "  code=" . str_pad( $code, 8 ) . " | filename-guess=" . str_pad( $guess, 8 ) . " | #{$r['pid']} | " . $r['title'] . "\n";
        echo "        file: " . ( $r['file'] !== '' ? $r['file'] : '(no featured image)' ) . $flag . "\n";
    }
}

echo "\n=== DONE ===\n";
