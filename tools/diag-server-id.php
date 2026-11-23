<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/** Read-only. Which server the deploy SSH reaches, and the WordPress install there. No secrets printed. */
if (!defined('ABSPATH')) exit;
echo "hostname: " . gethostname() . "\n";
echo "php: " . PHP_VERSION . ", os: " . php_uname('s') . ' ' . php_uname('r') . "\n";
echo "abspath: " . ABSPATH . "\n";
echo "siteurl: " . get_option('siteurl') . ", home: " . get_option('home') . "\n";
echo "wp: " . get_bloginfo('version') . ", theme: " . get_stylesheet() . "\n";
$f = get_stylesheet_directory() . '/inc/add-to-cart-links.php';
echo "theme files current (add-to-cart-links.php): " . (file_exists($f) ? date('Y-m-d H:i', filemtime($f)) : 'missing') . "\n";
global $wpdb;
echo "db host: " . (strpos(DB_HOST, 'localhost') !== false || strpos(DB_HOST, '127.0.0.1') === 0 ? 'local' : 'remote') . ", products: " . (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish'") . "\n";
echo "latest order: " . (string) $wpdb->get_var("SELECT MAX(post_date) FROM {$wpdb->posts} WHERE post_type IN ('shop_order','shop_order_placehold')") . "\n";
