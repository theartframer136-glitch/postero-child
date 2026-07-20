<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Remove FAQ blocks from product post_content (both the theme-appended
 * <!--taf-faq--> block and any pre-existing "Frequently Asked Questions"
 * section). The FAQ is now rendered as a styled accordion via a theme
 * hook, so it must NOT live in the content anymore.
 * SAFE: only strips from the first FAQ heading/marker onward (FAQ sits at
 * the end of these product descriptions). Idempotent.
 * Run: wp eval-file tools/dedup-faq.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }
if ( ! function_exists( 'wc_get_product' ) ) { echo "WooCommerce not active\n"; exit(1); }

$ids = get_posts(array('post_type'=>'product','post_status'=>array('publish','draft'),'posts_per_page'=>-1,'fields'=>'ids'));
echo "=== DEDUP / STRIP FAQ FROM PRODUCT CONTENT (" . count($ids) . " products) ===\n\n";

global $wpdb;
$changed = 0;
foreach ( $ids as $pid ) {
    $content = get_post_field( 'post_content', $pid );
    if ( $content === '' || $content === null ) continue;
    $orig = $content;

    // 1) Remove our appended marker block (marker to end)
    $content = preg_replace( '/<!--taf-faq-->.*$/s', '', $content );

    // 2) Remove any remaining "Frequently Asked Questions" section (heading to end)
    $content = preg_replace( '/<h[1-6][^>]*>\s*Frequently Asked Questions.*$/is', '', $content );
    // 2b) Plain-text heading fallback (no wrapping tag)
    $content = preg_replace( '/Frequently Asked Questions.*$/s', '', $content );

    $content = rtrim( $content );

    if ( $content !== $orig ) {
        $wpdb->update( $wpdb->posts, array('post_content'=>$content), array('ID'=>$pid), array('%s'), array('%d') );
        clean_post_cache( $pid );
        wc_delete_product_transients( $pid );
        $changed++;
        echo "STRIPPED FAQ from #{$pid}\n";
    }
}
echo "\n=== DONE. Cleaned {$changed} product(s). FAQ now renders via theme hook. ===\n";
