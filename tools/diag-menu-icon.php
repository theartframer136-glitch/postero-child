<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/** Where does the little icon before each category name come from? */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$menu  = wp_get_nav_menu_object( 'Category' );
$items = wp_get_nav_menu_items( $menu->term_id );

$want = array();
foreach ( $items as $i ) {
    if ( (int) $i->menu_item_parent !== 6810 ) continue;
    $want[] = $i;
}

foreach ( $want as $i ) {
    printf( "\n=== menu item #%d  \"%s\"  (term %d) ===\n", $i->ID, html_entity_decode( $i->title ), $i->object_id );
    foreach ( get_post_meta( $i->ID ) as $k => $v ) {
        $val = is_array( $v ) ? ( $v[0] ?? '' ) : $v;
        if ( is_serialized( $val ) ) $val = wp_json_encode( maybe_unserialize( $val ) );
        printf( "    meta %-34s = %s\n", $k, substr( (string) $val, 0, 90 ) );
    }
    $tid = (int) $i->object_id;
    if ( $tid ) {
        $thumb = get_term_meta( $tid, 'thumbnail_id', true );
        printf( "    TERM thumbnail_id                  = %s%s\n", $thumb ?: '(none)',
            $thumb ? '  -> ' . wp_get_attachment_url( $thumb ) : '' );
    }
}
