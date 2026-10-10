<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * §9 digital downloads: every downloadable product gets the shop's download
 * limit and expiry. Owner, 10 Oct 2026: a purchase stays in the buyer's
 * account forever, unlimited, opening only for the buyer, logged in (inc/
 * digital-account.php, tools/enable-digital-downloads.php), so both are -1
 * (unlimited / never). This used to read -1 as "not set" and put 5 downloads
 * / 30 days back on every deploy, after enable-digital-downloads.php. License
 * acceptance at checkout already exists; watermarks stay off deliberately
 * (every product is flagged downloadable, so enabling would stamp the whole
 * physical catalogue, and the public master URLs make it theatre anyway).
 * Run: wp eval-file tools/harden-downloads.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }

$LIMIT  = (int) apply_filters('af_dl_default_limit', (int) get_option('af_dl_limit', -1));         // downloads per purchase, -1 unlimited
$EXPIRY = (int) apply_filters('af_dl_default_expiry', (int) get_option('af_dl_expiry_days', -1));  // days, -1 never

$ids = get_posts(array('post_type' => 'product', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids'));
$dl = 0; $set_l = 0; $set_e = 0;
foreach ($ids as $id) {
    $p = wc_get_product($id);
    if (!$p || !$p->is_downloadable()) continue;
    $dl++;
    if ((string) get_post_meta($id, '_download_limit', true) !== (string) $LIMIT) {
        update_post_meta($id, '_download_limit', $LIMIT); $set_l++;
    }
    if ((string) get_post_meta($id, '_download_expiry', true) !== (string) $EXPIRY) {
        update_post_meta($id, '_download_expiry', $EXPIRY); $set_e++;
    }
}
echo "=== DOWNLOAD HARDENING ===\n";
printf("downloadable products: %d | limit changed on %d (=> %s) | expiry changed on %d (=> %s) | login to download: %s\n",
    $dl, $set_l, $LIMIT < 0 ? 'unlimited' : "$LIMIT per purchase", $set_e, $EXPIRY < 0 ? 'never expires' : "$EXPIRY days",
    get_option('woocommerce_downloads_require_login'));
echo "license checkbox at checkout: " . (has_action('woocommerce_review_order_before_submit') ? 'present' : 'CHECK') . "\n";
echo "watermarks: " . (function_exists('af_wm_enabled') && af_wm_enabled() ? 'ON' : 'off (deliberate — see tool header)') . "\n";
echo "=== DONE ===\n";
