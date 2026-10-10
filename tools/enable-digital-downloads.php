<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Make products downloadable so a paid Digital Download delivers a file,
 * kept in the buyer's account with no expiry and no download limit.
 * Uses raw post meta (avoids WC_Product::save() download-URL validation that
 * can fatal) + per-product error handling. Idempotent.
 * Run: wp eval-file tools/enable-digital-downloads.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }
if ( ! function_exists( 'wc_get_product' ) ) { echo "WooCommerce not active\n"; exit(1); }

// Grant downloads on payment; allow files from the uploads dir
update_option('woocommerce_downloads_grant_access_after_payment', 'yes');
// Owner, 10 Oct 2026: a purchase stays in the buyer's account, forever and
// unlimited, and opens only for the buyer, logged in (inc/digital-account.php
// makes the account at checkout and keeps every permission unlimited).
update_option('woocommerce_downloads_require_login', 'yes');

// Per-purchase limits — -1 is WooCommerce's "unlimited" / "never expires".
// Tune via options without redeploying.
$limit  = (int) get_option('af_dl_limit', -1);        // downloads per purchase
$expiry = (int) get_option('af_dl_expiry_days', -1);  // days until the link expires

$ids = get_posts(array('post_type'=>'product','post_status'=>'publish','posts_per_page'=>-1,'fields'=>'ids'));
echo "=== ENABLE DIGITAL DOWNLOADS (" . count($ids) . " products) ===\n";
echo 'Limits: ' . ($limit < 0 ? 'unlimited downloads' : "{$limit} downloads") . ', ' . ($expiry < 0 ? 'never expires' : "{$expiry}-day expiry") . " (options af_dl_limit / af_dl_expiry_days), login required to download\n";

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

// The Digital Download License page promised "5 downloads ... 30 days". Only
// that paragraph is replaced, once, where the old wording is still there, so
// nothing else on the page (or any edit made in WordPress) is touched.
$lic = get_page_by_path('digital-download-license');
if ($lic) {
    $old_p = '/<h2[^>]*>\s*Download Limits\s*<\/h2>\s*<p[^>]*>.*?<\/p>/s';
    $new_p = "<h2>Your Downloads</h2>\n  <p>Your file is kept in <b>your account</b>, under <b>My Account → Downloads</b>: download it again whenever you need it, with no expiry and no download limit. It opens only while you're logged in to the account you bought it with. Can't log in? Reset your password from the login page, or contact us with your order number.</p>";
    $content = $lic->post_content;
    if (preg_match($old_p, $content)) {
        $content = preg_replace($old_p, $new_p, $content, 1);
        $content = str_replace('Last updated: 16 July 2026', 'Last updated: 10 October 2026', $content);
        // written as is: wp_update_post() would run the whole page through the
        // HTML filter (no user under WP-CLI) and could alter the rest of it
        global $wpdb;
        $r = $wpdb->update($wpdb->posts, array('post_content' => $content, 'post_modified' => current_time('mysql'), 'post_modified_gmt' => current_time('mysql', true)), array('ID' => $lic->ID));
        clean_post_cache($lic->ID);
        do_action('litespeed_purge_post', $lic->ID);
        echo 'License page: ' . ($r === false ? 'NOT updated: ' . $wpdb->last_error : 'download limits paragraph replaced (no expiry, no limit, in your account)') . "\n";
    } elseif (stripos($content, '5 downloads') !== false || stripos($content, 'Download Limits') !== false) {
        echo "License page: NOT updated: it still mentions download limits, in wording this script does not recognise (edit it in WordPress)\n";
    } else {
        echo "License page: no old download-limits paragraph (already updated)\n";
    }
}
echo "=== DONE ===\n";
