<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * §9 digital downloads: give every downloadable product sane protection
 * defaults — a download limit and an expiry — where none was set. License
 * acceptance at checkout already exists; watermarks stay off deliberately
 * (every product is flagged downloadable, so enabling would stamp the whole
 * physical catalogue, and the public master URLs make it theatre anyway).
 * Run: wp eval-file tools/harden-downloads.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }

$LIMIT  = (int) apply_filters('af_dl_default_limit', 5);      // downloads per purchase
$EXPIRY = (int) apply_filters('af_dl_default_expiry', 30);    // days

$ids = get_posts(array('post_type' => 'product', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids'));
$dl = 0; $set_l = 0; $set_e = 0;
foreach ($ids as $id) {
    $p = wc_get_product($id);
    if (!$p || !$p->is_downloadable()) continue;
    $dl++;
    if ((string) get_post_meta($id, '_download_limit', true) === '' || (int) get_post_meta($id, '_download_limit', true) === -1) {
        update_post_meta($id, '_download_limit', $LIMIT); $set_l++;
    }
    if ((string) get_post_meta($id, '_download_expiry', true) === '' || (int) get_post_meta($id, '_download_expiry', true) === -1) {
        update_post_meta($id, '_download_expiry', $EXPIRY); $set_e++;
    }
}
echo "=== DOWNLOAD HARDENING ===\n";
printf("downloadable products: %d | limit set on %d (=> %d per purchase) | expiry set on %d (=> %d days)\n",
    $dl, $set_l, $LIMIT, $set_e, $EXPIRY);
echo "license checkbox at checkout: " . (has_action('woocommerce_review_order_before_submit') ? 'present' : 'CHECK') . "\n";
echo "watermarks: " . (function_exists('af_wm_enabled') && af_wm_enabled() ? 'ON' : 'off (deliberate — see tool header)') . "\n";
echo "=== DONE ===\n";
