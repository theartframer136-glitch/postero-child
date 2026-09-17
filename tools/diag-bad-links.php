<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Find the links the crawl said cannot work, in the content that holds them.
 *
 * The crawl reports what a visitor sees; it cannot say which post, widget or
 * Elementor block the href lives in. This does, so the fix edits the right
 * record instead of a guess.
 *
 * Looking for:
 *   - hrefs pointing at any host that is not this site and looks like a
 *     development copy (staging, localhost, .test, a bare IP, a hosting
 *     preview domain). Four were found on the homepage.
 *   - the empty-href buttons on the two pages the crawl named.
 *
 * Read-only.
 * Run: wp eval-file tools/diag-bad-links.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

global $wpdb;
$home_host = wp_parse_url( home_url(), PHP_URL_HOST );

echo "=== this site: {$home_host} ===\n";

/** Pull every href out of a blob and keep the ones pointing somewhere odd. */
function af_bl_scan( $blob ) {
    $out = array();
    if ( ! is_string( $blob ) || $blob === '' ) return $out;
    // Elementor stores JSON with escaped slashes; normalise before matching.
    $blob = str_replace( '\\/', '/', $blob );
    if ( preg_match_all( '#https?://([a-z0-9\.\-]+)[^"\'\s\\\\<>]*#i', $blob, $m, PREG_SET_ORDER ) ) {
        foreach ( $m as $hit ) $out[] = array( 'host' => strtolower( $hit[1] ), 'url' => $hit[0] );
    }
    return $out;
}

$suspect = array();
$seen_hosts = array();

$rows = $wpdb->get_results( "
    SELECT ID, post_title, post_type, post_status
    FROM {$wpdb->posts}
    WHERE post_status IN ('publish','draft','private')
      AND post_type NOT IN ('revision','attachment','product_variation')
" );

echo "scanning " . count( $rows ) . " posts plus their meta...\n\n";

foreach ( $rows as $r ) {
    $blobs = array( 'content' => get_post_field( 'post_content', $r->ID ) );
    foreach ( $wpdb->get_results( $wpdb->prepare(
        "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d", $r->ID ) ) as $mv ) {
        if ( is_string( $mv->meta_value ) && strlen( $mv->meta_value ) < 900000 ) {
            $blobs[ 'meta:' . $mv->meta_key ] = $mv->meta_value;
        }
    }
    foreach ( $blobs as $where => $blob ) {
        foreach ( af_bl_scan( $blob ) as $hit ) {
            $h = $hit['host'];
            $seen_hosts[ $h ] = ( $seen_hosts[ $h ] ?? 0 ) + 1;
            if ( $h === $home_host || $h === 'www.' . $home_host ) continue;
            $odd = preg_match( '#localhost|127\.0\.0\.1|^\d+\.\d+\.\d+\.\d+$|\.local$|\.test$|staging|^dev\.|\.dev$|hostingersite|wpengine|\.cloudwaysapps\.|tempurl|preview#i', $h );
            if ( $odd ) {
                $key = $r->ID . '|' . $where . '|' . $hit['url'];
                if ( ! isset( $suspect[ $key ] ) ) {
                    $suspect[ $key ] = array( 'id' => $r->ID, 'title' => $r->post_title,
                                              'type' => $r->post_type, 'where' => $where, 'url' => $hit['url'] );
                }
            }
        }
    }
}

echo "=== LINKS POINTING AT A DEVELOPMENT COPY (" . count( $suspect ) . ") ===\n";
if ( ! $suspect ) echo "  none found in post content or post meta\n";
foreach ( $suspect as $s ) {
    echo "  #{$s['id']}  [{$s['type']}]  " . substr( $s['title'], 0, 45 ) . "\n";
    echo "        in {$s['where']}\n";
    echo "        {$s['url']}\n";
}

echo "\n=== OTHER HOSTS LINKED (top 25, so a wrong one is visible) ===\n";
arsort( $seen_hosts );
$i = 0;
foreach ( $seen_hosts as $h => $n ) {
    if ( $h === $home_host ) continue;
    printf( "  %5d  %s\n", $n, $h );
    if ( ++$i >= 25 ) break;
}

echo "\n=== THE EMPTY-HREF BUTTONS THE CRAWL NAMED ===\n";
foreach ( array( 6030, 6032 ) as $pid ) {
    $p = get_post( $pid );
    if ( ! $p ) { echo "  #{$pid}: no such post\n"; continue; }
    echo "  #{$pid}  [{$p->post_type}/{$p->post_status}]  {$p->post_title}\n";
    echo "        permalink: " . get_permalink( $pid ) . "\n";
    $blob = $p->post_content;
    $em = get_post_meta( $pid, '_elementor_data', true );
    if ( $em ) { $blob .= ' ' . ( is_string( $em ) ? $em : wp_json_encode( $em ) ); echo "        built with Elementor\n"; }
    foreach ( array( 'Register Now', 'Sign In' ) as $label ) {
        $n = substr_count( $blob, $label );
        echo "        \"{$label}\" appears {$n} time(s)\n";
    }
    if ( preg_match_all( '#href\s*=\s*(\\\\?["\'])\s*\1#', $blob, $mm ) ) {
        echo "        empty href= occurrences in stored content: " . count( $mm[0] ) . "\n";
    }
    // Elementor keeps button links in a "link":{"url":""} structure.
    if ( $em && is_string( $em ) ) {
        $n = preg_match_all( '#"link"\s*:\s*\{\s*"url"\s*:\s*""#', $em, $m2 );
        echo "        Elementor buttons with an empty URL: " . (int) $n . "\n";
    }
}
