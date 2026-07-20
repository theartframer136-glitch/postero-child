<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Audit a WooCommerce product — dumps all fields to CLI output.
 * Usage: wp eval-file tools/audit-product.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

// ── Find the product by slug ──────────────────────────────────
$slug    = 'radha-krishna-sacred-bond-canvas-art';
$posts   = get_posts( array( 'name' => $slug, 'post_type' => 'product', 'post_status' => 'any', 'numberposts' => 1 ) );
if ( empty( $posts ) ) { WP_CLI::error( "No product found with slug: $slug" ); }
$post    = $posts[0];
$pid     = $post->ID;
$product = wc_get_product( $pid );
if ( ! $product ) { WP_CLI::error( "wc_get_product($pid) returned false" ); }

$sep = str_repeat( '─', 60 );

WP_CLI::log( '' );
WP_CLI::log( '═══ PRODUCT AUDIT: ' . $slug . ' (ID #' . $pid . ') ═══' );
WP_CLI::log( '' );

// Core fields
WP_CLI::log( '── CORE FIELDS ──' );
WP_CLI::log( 'Title       : ' . $post->post_title );
WP_CLI::log( 'Slug        : ' . $post->post_name );
WP_CLI::log( 'Status      : ' . $post->post_status );
WP_CLI::log( 'SKU         : ' . $product->get_sku() );
WP_CLI::log( 'Regular $   : ' . $product->get_regular_price() );
WP_CLI::log( 'Sale $      : ' . $product->get_sale_price() );
WP_CLI::log( 'Stock       : ' . $product->get_stock_status() );
WP_CLI::log( 'Visibility  : ' . $product->get_catalog_visibility() );
WP_CLI::log( '' );

// Featured image
$thumb_id = $product->get_image_id();
WP_CLI::log( '── FEATURED IMAGE ──' );
WP_CLI::log( 'Attachment ID : ' . $thumb_id );
if ( $thumb_id ) {
    WP_CLI::log( 'URL           : ' . wp_get_attachment_url( $thumb_id ) );
    WP_CLI::log( 'Alt           : ' . get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ) );
    $att = get_post( $thumb_id );
    WP_CLI::log( 'Title         : ' . $att->post_title );
    WP_CLI::log( 'Caption       : ' . $att->post_excerpt );
    WP_CLI::log( 'Description   : ' . $att->post_content );
}
WP_CLI::log( '' );

// Gallery images
$gallery = $product->get_gallery_image_ids();
WP_CLI::log( '── GALLERY IMAGES (' . count($gallery) . ') ──' );
foreach ( $gallery as $gid ) { WP_CLI::log( '  #' . $gid . ' : ' . wp_get_attachment_url($gid) ); }
WP_CLI::log( '' );

// Categories & Tags
$cats = wp_get_post_terms( $pid, 'product_cat', array( 'fields' => 'names' ) );
$tags = wp_get_post_terms( $pid, 'product_tag', array( 'fields' => 'names' ) );
WP_CLI::log( '── CATEGORIES ──' );
WP_CLI::log( implode( ', ', $cats ) );
WP_CLI::log( '── TAGS ──' );
WP_CLI::log( implode( ', ', $tags ) );
WP_CLI::log( '' );

// Short description
WP_CLI::log( '── SHORT DESCRIPTION (post_excerpt) ──' );
WP_CLI::log( $post->post_excerpt );
WP_CLI::log( '' );

// Long description
WP_CLI::log( '── LONG DESCRIPTION (post_content) ──' );
WP_CLI::log( $post->post_content );
WP_CLI::log( '' );

// Attributes
WP_CLI::log( '── ATTRIBUTES ──' );
$attrs = $product->get_attributes();
if ( empty( $attrs ) ) {
    WP_CLI::log( '  (none)' );
} else {
    foreach ( $attrs as $key => $attr ) {
        WP_CLI::log( '  [' . $key . ']' );
        WP_CLI::log( '    Name    : ' . $attr->get_name() );
        WP_CLI::log( '    Options : ' . implode( ' | ', $attr->get_options() ) );
        WP_CLI::log( '    Visible : ' . ( $attr->get_visible() ? 'yes' : 'no' ) );
    }
}
WP_CLI::log( '' );

// SEO meta
WP_CLI::log( '── SEO META ──' );
$seo_keys = array(
    '_yoast_wpseo_title', '_yoast_wpseo_metadesc', '_yoast_wpseo_focuskw',
    'rank_math_title', 'rank_math_description', 'rank_math_focus_keyword',
    '_yoast_wpseo_opengraph-title', '_yoast_wpseo_opengraph-description',
    '_yoast_wpseo_twitter-title', '_yoast_wpseo_twitter-description',
);
foreach ( $seo_keys as $k ) {
    $v = get_post_meta( $pid, $k, true );
    if ( $v ) WP_CLI::log( '  ' . $k . ' : ' . $v );
}
WP_CLI::log( '' );

// All post meta (raw)
WP_CLI::log( '── ALL POST META KEYS ──' );
$all_meta = get_post_meta( $pid );
ksort( $all_meta );
foreach ( $all_meta as $k => $v ) {
    if ( strpos($k, '_') === 0 && ! in_array($k, $seo_keys) ) continue; // skip private wc internals
    WP_CLI::log( '  ' . $k . ' : ' . ( is_array($v) ? json_encode($v[0]) : $v ) );
}

WP_CLI::log( '' );
WP_CLI::success( 'Audit complete.' );
