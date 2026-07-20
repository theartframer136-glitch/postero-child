<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Publish all draft WooCommerce products.
 *
 * Run via WP-CLI:
 *   wp eval-file wp-content/themes/postero-child/tools/publish-draft-products.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    fwrite( STDERR, "Run via: wp eval-file ...\n" );
    exit( 1 );
}
if ( ! function_exists( 'wc_get_product' ) ) {
    WP_CLI::error( 'WooCommerce is not active.' );
}

$drafts = get_posts( array(
    'post_type'      => 'product',
    'post_status'    => 'draft',
    'posts_per_page' => -1,
    'fields'         => 'ids',
) );

if ( empty( $drafts ) ) {
    WP_CLI::success( 'No draft products found.' );
    return;
}

WP_CLI::log( 'Found ' . count( $drafts ) . ' draft product(s). Publishing...' );
WP_CLI::log( '' );

$published = 0;
$failed    = 0;

foreach ( $drafts as $pid ) {
    $product = wc_get_product( $pid );
    if ( ! $product ) {
        WP_CLI::warning( "Could not load product #{$pid}" );
        $failed++;
        continue;
    }

    $result = wp_update_post( array(
        'ID'          => $pid,
        'post_status' => 'publish',
    ), true );

    if ( is_wp_error( $result ) ) {
        WP_CLI::warning( "FAILED #{$pid} ({$product->get_name()}): " . $result->get_error_message() );
        $failed++;
    } else {
        WP_CLI::log( "PUBLISHED #{$pid}: " . $product->get_name() );
        $published++;
    }
}

WP_CLI::log( '' );
WP_CLI::success( "Done. Published: {$published} | Failed: {$failed}" );
