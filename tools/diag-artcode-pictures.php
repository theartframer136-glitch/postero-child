<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * READ-ONLY. Every published product's main picture at 320 px, base64 JPEG,
 * one line each, for finding the same artwork listed under different art
 * codes. diag-artcode-thumbs.php gives 120 px, which the deploy log carries;
 * at that size a painting shown small inside another photo (a gift card, an
 * event photo) is missed, so this one is larger and runs on its own.
 *
 * Output: @@P|<pid>|<base64 jpeg, or empty>   then   @@PICTURES DONE n=<count>
 * Nothing on the site is changed: each resized copy is a temp file, deleted.
 *
 * Run: wp eval-file tools/diag-artcode-pictures.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$MAX = 320;
$Q   = 60;

$ids = get_posts( array( 'post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC' ) );
$n = 0;
foreach ( $ids as $pid ) {
    $b64 = '';
    $att = get_post_thumbnail_id( $pid );
    $path = $att ? get_attached_file( $att ) : '';
    if ( $path && file_exists( $path ) ) {
        $ed = wp_get_image_editor( $path );
        if ( ! is_wp_error( $ed ) ) {
            $s = $ed->get_size();
            if ( ! empty( $s['width'] ) && ( $s['width'] > $MAX || $s['height'] > $MAX ) ) {
                if ( $s['width'] >= $s['height'] ) { $ed->resize( $MAX, null, false ); } else { $ed->resize( null, $MAX, false ); }
            }
            $ed->set_quality( $Q );
            $tmp = wp_tempnam( 'afp' ) . '.jpg';
            $saved = $ed->save( $tmp, 'image/jpeg' );
            if ( ! is_wp_error( $saved ) && ! empty( $saved['path'] ) && file_exists( $saved['path'] ) ) {
                $b64 = base64_encode( (string) file_get_contents( $saved['path'] ) );
                @unlink( $saved['path'] );
            }
            if ( file_exists( $tmp ) ) @unlink( $tmp );
            $base = preg_replace( '/\.jpg$/', '', $tmp );
            if ( $base !== $tmp && file_exists( $base ) ) @unlink( $base ); // wp_tempnam's own empty file
        }
    }
    echo "@@P|{$pid}|{$b64}\n";
    $n++;
}
echo "@@PICTURES DONE n={$n}\n";
