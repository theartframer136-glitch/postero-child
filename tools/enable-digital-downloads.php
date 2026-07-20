<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Make products downloadable so a paid Digital Download delivers a file.
 * Uses raw post meta (avoids WC_Product::save() download-URL validation that
 * can fatal) + per-product error handling. Idempotent.
 * Run: wp eval-file tools/enable-digital-downloads.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }
if ( ! function_exists( 'wc_get_product' ) ) { echo "WooCommerce not active\n"; exit(1); }

// Grant downloads on payment; allow files from the uploads dir
update_option('woocommerce_downloads_grant_access_after_payment', 'yes');
update_option('woocommerce_downloads_require_login', 'no');

// Per-purchase limits (spec §9) — tune via options without redeploying
$limit  = (int) get_option('af_dl_limit', 5);        // downloads per purchase
$expiry = (int) get_option('af_dl_expiry_days', 30); // days until link expires

$ids = get_posts(array('post_type'=>'product','post_status'=>'publish','posts_per_page'=>-1,'fields'=>'ids'));
echo "=== ENABLE DIGITAL DOWNLOADS (" . count($ids) . " products) ===\n";
echo "Limits: {$limit} downloads, {$expiry}-day expiry (options af_dl_limit / af_dl_expiry_days)\n";

$done = 0; $skip = 0; $err = 0;
foreach ($ids as $pid) {
    try {
        $imgid = (int) get_post_thumbnail_id($pid);
        if (!$imgid) { $skip++; continue; }
        $url = wp_get_attachment_url($imgid);
        if (!$url) { $skip++; continue; }

        $key = md5('afdl' . $pid);
        $files = array(
            $key => array(
                'id'                => $key,
                'name'              => 'High-Resolution Digital File',
                'file'              => $url,
                'previous_hash'     => '',
            ),
        );
        update_post_meta($pid, '_downloadable', 'yes');
        update_post_meta($pid, '_downloadable_files', $files);
        update_post_meta($pid, '_download_limit', $limit);
        update_post_meta($pid, '_download_expiry', $expiry);

        clean_post_cache($pid);
        if (function_exists('wc_delete_product_transients')) wc_delete_product_transients($pid);
        $done++;
    } catch (Throwable $e) {
        $err++;
        echo "  ERR #{$pid}: " . $e->getMessage() . "\n";
    }
}
echo "\nMade downloadable: {$done} | skipped(no image/not simple): {$skip} | errors: {$err}\n";
echo "Grant-after-payment: ON. Downloads appear in My Account after payment.\n";
echo "=== DONE ===\n";
