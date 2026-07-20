<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Update Radha Krishna Sacred Bond Canvas Art (#11578)
 * Content loaded from external HTML files to keep this PHP file small.
 * Uses $wpdb->update() directly to bypass all WP filters.
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$PRODUCT_ID    = 11578;
$ATTACHMENT_ID = 11576;
$DIR           = __DIR__;

echo "=== Sacred Bond Update START ===\n";

// ── Load HTML content from separate files ──────────────────────
$short_file = $DIR . '/sacred-bond-short.html';
$long_file  = $DIR . '/sacred-bond-long.html';

if ( ! file_exists( $short_file ) ) { echo "ERROR: $short_file not found\n"; exit(1); }
if ( ! file_exists( $long_file  ) ) { echo "ERROR: $long_file not found\n";  exit(1); }

$SHORT_DESC = trim( file_get_contents( $short_file ) );
$LONG_DESC  = trim( file_get_contents( $long_file  ) );

echo "SHORT_DESC: " . strlen($SHORT_DESC) . " bytes\n";
echo "LONG_DESC:  " . strlen($LONG_DESC)  . " bytes\n";

$TITLE = 'Radha Krishna Sacred Bond Canvas Wall Art | Divine Love Digital Canvas Print';
$SLUG  = 'radha-krishna-sacred-bond-canvas-art';

// ── 1. Direct DB update (bypasses all WP filters) ─────────────
global $wpdb;
echo "1. Writing post_title, post_content, post_excerpt to DB...\n";
$rows = $wpdb->update(
    $wpdb->posts,
    array(
        'post_title'   => $TITLE,
        'post_name'    => $SLUG,
        'post_content' => $LONG_DESC,
        'post_excerpt' => $SHORT_DESC,
        'post_status'  => 'publish',
        'post_type'    => 'product',
    ),
    array( 'ID' => $PRODUCT_ID ),
    array( '%s', '%s', '%s', '%s', '%s', '%s' ),
    array( '%d' )
);
echo "  rows updated: $rows  | last_error: " . ( $wpdb->last_error ?: 'none' ) . "\n";

// Verify
$row = $wpdb->get_row( $wpdb->prepare( "SELECT post_title, post_name, post_status FROM {$wpdb->posts} WHERE ID=%d", $PRODUCT_ID ) );
echo "  DB title now : " . $row->post_title . "\n";
echo "  DB slug now  : " . $row->post_name  . "\n";
echo "  DB status    : " . $row->post_status . "\n";

// ── 2. WooCommerce price / stock via product object ────────────
echo "2. Setting WooCommerce product fields...\n";
if ( function_exists( 'wc_get_product' ) ) {
    $product = wc_get_product( $PRODUCT_ID );
    if ( $product ) {
        $product->set_regular_price( '150' );
        $product->set_sale_price( '100' );
        $product->set_stock_status( 'instock' );
        $product->set_catalog_visibility( 'visible' );
        $product->save();
        echo "  WC product saved.\n";
    } else {
        echo "  WARNING: wc_get_product returned false.\n";
    }
} else {
    echo "  WARNING: WooCommerce not active.\n";
}

// ── 3. Tags ────────────────────────────────────────────────────
echo "3. Setting tags...\n";
$TAGS = array(
    'Radha Krishna','Radha Krishna canvas art','Radha Krishna wall art',
    'sacred bond canvas print','Krishna art','devotional canvas print',
    'spiritual wall art','Hindu wall art','religious canvas art',
    'Krishna home decor','Radha Krishna gift','divine love wall art',
    'Indian art print','pooja room decor','temple room art',
    'spiritual home decor','premium canvas print','gallery wrapped canvas',
    'archival canvas print','digital canvas print','luxury wall art',
    'wedding gift art','anniversary gift canvas','housewarming gift art',
    'living room wall art','bedroom wall art','meditation room decor',
    'Indian religious art','canvas wall decor','Janmashtami gift',
);
wp_set_object_terms( $PRODUCT_ID, $TAGS, 'product_tag' );
echo "  " . count($TAGS) . " tags set.\n";

// ── 4. SEO meta ────────────────────────────────────────────────
echo "4. Writing SEO meta...\n";
$SEO = array(
    '_yoast_wpseo_title'                  => 'Radha Krishna Sacred Bond Canvas Wall Art | Divine Love Print – The Art Framer',
    '_yoast_wpseo_metadesc'               => 'Celebrate the eternal sacred bond of Radha and Krishna with this premium canvas wall art. Archival inks, eco-friendly, ready to hang. Shop now at The Art Framer.',
    '_yoast_wpseo_focuskw'                => 'Radha Krishna canvas wall art',
    'rank_math_title'                     => 'Radha Krishna Sacred Bond Canvas Wall Art | Divine Love Print – The Art Framer',
    'rank_math_description'               => 'Celebrate the eternal sacred bond of Radha and Krishna with this premium canvas wall art. Archival inks, eco-friendly, ready to hang. Shop now at The Art Framer.',
    'rank_math_focus_keyword'             => 'Radha Krishna canvas wall art',
    '_yoast_wpseo_opengraph-title'        => 'Radha Krishna Sacred Bond Canvas Wall Art | The Art Framer',
    '_yoast_wpseo_opengraph-description'  => 'Premium Radha Krishna canvas wall art — archival quality, eco-friendly inks, ready to hang.',
    '_yoast_wpseo_twitter-title'          => 'Radha Krishna Sacred Bond Canvas Wall Art | The Art Framer',
    '_yoast_wpseo_twitter-description'    => 'Premium Radha Krishna canvas wall art — archival quality, eco-friendly inks, ready to hang.',
);
foreach ( $SEO as $k => $v ) { update_post_meta( $PRODUCT_ID, $k, $v ); }
echo "  " . count($SEO) . " SEO fields written.\n";

// ── 5. Image meta ──────────────────────────────────────────────
echo "5. Updating image meta...\n";
$wpdb->update(
    $wpdb->posts,
    array(
        'post_title'   => 'Radha Krishna Sacred Bond Canvas Wall Art',
        'post_excerpt' => 'Sacred Bond – Radha Krishna Canvas Wall Art | The Art Framer',
        'post_content' => 'A premium digital canvas print depicting the sacred and eternal bond of Radha and Krishna.',
    ),
    array( 'ID' => $ATTACHMENT_ID ),
    array( '%s', '%s', '%s' ),
    array( '%d' )
);
update_post_meta( $ATTACHMENT_ID, '_wp_attachment_image_alt', 'Radha Krishna Sacred Bond Canvas Wall Art – Premium Digital Canvas Print | The Art Framer' );
set_post_thumbnail( $PRODUCT_ID, $ATTACHMENT_ID );
echo "  Image meta updated. Featured image confirmed.\n";

// ── 6. Clear caches ────────────────────────────────────────────
echo "6. Clearing caches...\n";
clean_post_cache( $PRODUCT_ID );
if ( function_exists( 'wc_delete_product_transients' ) ) { wc_delete_product_transients( $PRODUCT_ID ); }
if ( function_exists( 'rocket_clean_post' ) ) { rocket_clean_post( $PRODUCT_ID ); }
echo "  Done.\n";

echo "=== Sacred Bond Update COMPLETE ===\n";
