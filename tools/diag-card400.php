<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/** Read-only. How far the 400px card copy (af_card_400) has got through the product pictures. */
if (!defined('ABSPATH')) exit;
global $wpdb;
$rows = $wpdb->get_col("SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_af_img_rebuilt'");
$by = array_count_values($rows);
foreach ($by as $v => $n) echo "rebuilt marker {$v}: {$n}\n";
$has = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attachment_metadata' AND meta_value LIKE '%af_card_400%'");
echo "attachments with a 400px card copy: {$has}\n";
$s = wp_get_registered_image_subsizes();
echo 'registered af_card_400: ' . (isset($s['af_card_400']) ? json_encode($s['af_card_400']) : 'NO') . "\n";
$t = get_transient('af_mf_card_srcsets');
echo 'card srcset cache entries: ' . (is_array($t) ? count($t) : 0) . "\n";
