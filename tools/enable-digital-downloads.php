<?php
/**
 * Make products downloadable so a paid Digital Download delivers a file.
 * Attaches the product's full-size image as the downloadable file, sets
 * unlimited access, and enables "grant access after payment".
 * WooCommerce then gates the download behind payment automatically.
 * SAFE: keeps products shippable (not virtual); idempotent.
 * Run: wp eval-file tools/enable-digital-downloads.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }
if ( ! function_exists( 'wc_get_product' ) ) { echo "WooCommerce not active\n"; exit(1); }

// Downloads are granted as soon as payment completes (processing/complete)
update_option('woocommerce_downloads_grant_access_after_payment', 'yes');
update_option('woocommerce_downloads_require_login', 'no');

$ids = get_posts(array('post_type'=>'product','post_status'=>'publish','posts_per_page'=>-1,'fields'=>'ids'));
echo "=== ENABLE DIGITAL DOWNLOADS (" . count($ids) . " products) ===\n\n";

$done = 0; $skip = 0;
foreach ($ids as $pid) {
    $p = wc_get_product($pid);
    if (!$p || !$p->is_type('simple')) { $skip++; continue; }
    $img = $p->get_image_id() ? wp_get_attachment_url($p->get_image_id()) : '';
    if (!$img) { $skip++; continue; }

    $p->set_downloadable(true);
    $dl = new WC_Product_Download();
    $dl->set_name('High-Resolution Digital File');
    $dl->set_id(md5('afdl' . $pid));
    $dl->set_file($img);
    $p->set_downloads(array($dl->get_id() => $dl));
    $p->set_download_limit(-1);   // unlimited downloads
    $p->set_download_expiry(-1);  // never expires
    // keep shippable for physical purchases (do NOT set virtual here)
    $p->save();
    $done++;
}
echo "Made {$done} products downloadable, skipped {$skip}.\n";
echo "Grant-after-payment: ON. Downloads appear in My Account after payment.\n";
echo "=== DONE ===\n";
