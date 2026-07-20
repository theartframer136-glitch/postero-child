<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Enable product reviews store-wide (run on deploy via wp eval-file).
 * - WooCommerce review settings on (ratings, verified-owner label)
 * - comment_status = open on every published product
 * Idempotent. Does NOT create any reviews — star/aggregateRating
 * schema appears automatically once real customers leave them.
 */

if (!defined('ABSPATH')) exit;

$log = function ($m) { echo "[reviews] $m\n"; };

update_option('woocommerce_enable_reviews', 'yes');
update_option('woocommerce_enable_review_rating', 'yes');
update_option('woocommerce_review_rating_required', 'yes');
update_option('woocommerce_review_rating_verification_label', 'yes');
$log('WooCommerce review settings enabled');

$ids = get_posts(array(
    'post_type'      => 'product',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'fields'         => 'ids',
));
$opened = 0;
foreach ($ids as $pid) {
    $p = get_post($pid);
    if ($p && $p->comment_status !== 'open') {
        wp_update_post(array('ID' => $pid, 'comment_status' => 'open'));
        $opened++;
    }
}
$log('products checked: ' . count($ids) . ', reviews opened on: ' . $opened);
$log('done');
