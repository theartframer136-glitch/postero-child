<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. How the live WooCommerce hands out a paid download, before the
 * Cloudflare R2 masters (inc/digital-masters.php) are switched on: the order
 * of the checks, the download count and the hooks in download_product(), the
 * download method, and the approved-directories rule a remote file URL must
 * pass. Prints WooCommerce's own code around those points; changes nothing.
 *
 * Run: wp eval-file tools/diag-wc-download-flow.php --allow-root
 */
if (!defined('ABSPATH')) exit(1);

echo "=== WooCommerce " . (defined('WC_VERSION') ? WC_VERSION : '?') . "\n";
$f = defined('WC_ABSPATH') ? WC_ABSPATH . 'includes/class-wc-download-handler.php' : '';
if (!$f || !is_file($f)) { echo "  download handler not found\n"; return; }
$L = file($f);

// download_product(), whole, with line numbers (it is short).
$start = $end = null;
foreach ($L as $i => $l) {
    if ($start === null && preg_match('/function\s+download_product\s*\(/', $l)) $start = $i;
    elseif ($start !== null && $i > $start && preg_match('/^\s*(public|private|protected)\s+static\s+function\s/', $l)) { $end = $i; break; }
}
echo "\n=== download_product() (lines " . ($start + 1) . "-" . $end . ")\n";
if ($start !== null) for ($i = $start; $i < min($end ?? $start + 140, $start + 160); $i++) echo sprintf('%4d ', $i + 1) . rtrim($L[$i]) . "\n";

echo "\n=== other places that matter\n";
foreach ($L as $i => $l) {
    if (preg_match('/woocommerce_file_download_method|woocommerce_file_download_path|function download_file_redirect|function download\s*\(|approved|is_file_path_approved|function check_/', $l)) {
        echo sprintf('%4d ', $i + 1) . trim(substr($l, 0, 170)) . "\n";
    }
}

echo "\n=== settings\n";
foreach (array('woocommerce_file_download_method', 'woocommerce_downloads_require_login', 'woocommerce_downloads_grant_access_after_payment',
               'woocommerce_downloads_redirect_fallback_allowed', 'woocommerce_downloads_add_hash_to_filename', 'woocommerce_downloads_deliver_inline') as $o) {
    echo '  ' . $o . ' = ' . json_encode(get_option($o)) . "\n";
}
try {
    if (function_exists('wc_get_container') && class_exists('Automattic\WooCommerce\Internal\ProductDownloads\ApprovedDirectories\Register')) {
        $reg = wc_get_container()->get('Automattic\WooCommerce\Internal\ProductDownloads\ApprovedDirectories\Register');
        echo '  approved directories mode = ' . $reg->get_mode() . "\n";
        $list = $reg->list(array('per_page' => 50));
        foreach ((array) ($list->approved_directories ?? array()) as $d) echo '    ' . ($d->is_enabled ? 'on ' : 'off') . ' ' . $d->url . "\n";
    }
} catch (\Throwable $e) { echo '  approved directories: ' . $e->getMessage() . "\n"; }

echo "\n=== what buyers have today\n";
global $wpdb;
$t = $wpdb->prefix . 'woocommerce_downloadable_product_permissions';
echo '  download permissions: ' . (int) $wpdb->get_var("SELECT COUNT(*) FROM $t") . ', with downloads left limited: ' . (int) $wpdb->get_var("SELECT COUNT(*) FROM $t WHERE downloads_remaining <> ''") . ', used at least once: ' . (int) $wpdb->get_var("SELECT COUNT(*) FROM $t WHERE download_count > 0") . "\n";
$lt = $wpdb->prefix . 'wc_download_log';
if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $lt))) echo '  download log rows: ' . (int) $wpdb->get_var("SELECT COUNT(*) FROM $lt") . "\n";
echo "=== END\n";
