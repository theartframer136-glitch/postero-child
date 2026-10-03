<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. For every plugin that has a theme port: is it active, which
 * version is installed, and which version the port was copied from. A port
 * must match the installed version before its plugin is switched off.
 *
 * Run: wp eval-file tools/diag-port-versions.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
if (!function_exists('get_plugins')) require_once ABSPATH . 'wp-admin/includes/plugin.php';
$port = array( // plugin folder => version the theme copy was made from
    'wpc-smart-messages' => '4.3.4', 'yith-woocommerce-featured-video' => '1.58.0', 'woo-smart-quick-view' => '4.4.1',
    'woo-smart-wishlist' => '6.2.0', 'embedder-for-google-reviews' => '2.1.3', 'language-switcher-for-transposh' => '2.0.6',
    'essential-addons-for-elementor-lite' => '6.8.5', 'premium-addons-for-elementor' => '4.11.110', 'insta-gallery' => '5.0.9',
    'header-footer-elementor' => '2.9.5', 'customer-reviews-woocommerce' => '5.123.0', 'revslider' => '6.7.40',
);
$active = (array) get_option('active_plugins', array());
foreach (get_plugins() as $file => $d) {
    $dir = dirname($file);
    if (!isset($port[$dir])) continue;
    $ok = version_compare($d['Version'], $port[$dir], '==') ? 'same' : 'DIFFERENT';
    printf("  %-40s %-8s installed %-10s port %-10s %s\n", $dir, in_array($file, $active, true) ? 'active' : 'off', $d['Version'], $port[$dir], $ok);
}
echo "=== END\n";
