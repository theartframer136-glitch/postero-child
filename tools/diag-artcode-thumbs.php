<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * READ-ONLY. For every published product, emit a tiny base64-encoded JPEG
 * thumbnail of its featured image, one product per line, so the artwork can be
 * inspected off-server (the deploy log is the only channel out). Used to
 * reconcile each product's artwork against the printed brochure's art codes.
 * Makes no changes.
 *
 * Output line format (one per product), easy to grep/split:
 *   @@T|<pid>|<code-or->|<base64 jpeg, or empty if no/failed image>
 * A trailing "@@THUMBS DONE n=<count>" marks the end.
 *
 * Run: wp eval-file tools/diag-artcode-thumbs.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$MAX = 120;   // longest edge, px
$Q   = 55;    // jpeg quality

function aft_top_cat( $pid ) {
    $terms = get_the_terms( $pid, 'product_cat' );
    if ( ! $terms || is_wp_error( $terms ) ) return 'Uncategorized';
    $t = $terms[0];
    if ( $t->parent ) {
        $anc = get_ancestors( $t->term_id, 'product_cat' );
        if ( $anc ) { $rt = get_term( end( $anc ), 'product_cat' ); if ( $rt && ! is_wp_error( $rt ) ) $t = $rt; }
    }
    return html_entity_decode( wp_strip_all_tags( $t->name ) );
}

// Return a base64 JPEG thumbnail (longest edge $MAX) of the attachment's
// original file, or '' on any failure.
function aft_thumb_b64( $pid, $MAX, $Q ) {
    $att = get_post_thumbnail_id( $pid );
    if ( ! $att ) return '';
    $path = get_attached_file( $att );
    if ( ! $path || ! file_exists( $path ) ) return '';

    $ed = wp_get_image_editor( $path );
    if ( is_wp_error( $ed ) ) return '';
    $size = $ed->get_size();
    if ( empty( $size['width'] ) || empty( $size['height'] ) ) return '';
    // resize so the longest edge is $MAX (no upscaling)
    $w = (int) $size['width']; $h = (int) $size['height'];
    if ( $w > $MAX || $h > $MAX ) {
        if ( $w >= $h ) { $ed->resize( $MAX, null, false ); }
        else            { $ed->resize( null, $MAX, false ); }
    }
    $ed->set_quality( $Q );
    $tmp = wp_tempnam( 'aft' ) . '.jpg';
    $saved = $ed->save( $tmp, 'image/jpeg' );
    if ( is_wp_error( $saved ) || empty( $saved['path'] ) || ! file_exists( $saved['path'] ) ) {
        if ( file_exists( $tmp ) ) @unlink( $tmp );
        return '';
    }
    $bytes = file_get_contents( $saved['path'] );
    @unlink( $saved['path'] );
    if ( $tmp !== $saved['path'] && file_exists( $tmp ) ) @unlink( $tmp );
    return $bytes ? base64_encode( $bytes ) : '';
}

$ids = get_posts( array( 'post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids' ) );

// Order by category then current code so related artworks print together.
$meta = array();
foreach ( $ids as $pid ) {
    $meta[ $pid ] = array(
        'code' => trim( (string) get_post_meta( $pid, '_taf_art_code', true ) ),
        'cat'  => aft_top_cat( $pid ),
    );
}
usort( $ids, function( $a, $b ) use ( $meta ) {
    $c = strcasecmp( $meta[$a]['cat'], $meta[$b]['cat'] );
    if ( $c !== 0 ) return $c;
    $ka = $meta[$a]['code'] !== '' ? $meta[$a]['code'] : 'zzz';
    $kb = $meta[$b]['code'] !== '' ? $meta[$b]['code'] : 'zzz';
    return strnatcasecmp( $ka, $kb );
} );

$n = 0;
foreach ( $ids as $pid ) {
    $code = $meta[$pid]['code'] !== '' ? preg_replace( '/\s+/', '', $meta[$pid]['code'] ) : '-';
    $b64  = aft_thumb_b64( $pid, $MAX, $Q );
    echo "@@T|{$pid}|{$code}|{$b64}\n";
    $n++;
}
echo "@@THUMBS DONE n={$n}\n";
