<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * READ-ONLY. The live WooCommerce code inc/digital-account.php leans on, so the
 * change is checked against what is installed, not against memory: where the
 * download permission is made (and its filter), the checkout's account rules
 * and fields, how a customer is created, the "log in to download" check, the
 * query behind My Account → Downloads, and the checkout template that carries
 * the registration hook (the theme's own copy, if it has one).
 *
 * Run: wp eval-file tools/diag-wc-account-flow.php --allow-root
 */
if (!defined('ABSPATH')) exit(1);
if (!defined('WC_ABSPATH')) { echo "WooCommerce not loaded\n"; return; }
echo '=== WooCommerce ' . WC_VERSION . "\n";

$show = function ($rel, $pattern, $after = 3, $max = 4) {
    $f = WC_ABSPATH . $rel;
    if (!is_file($f)) { echo "  $rel: not found\n"; return; }
    $L = file($f); $n = 0;
    foreach ($L as $i => $l) {
        if (!preg_match($pattern, $l)) continue;
        echo "  $rel:" . ($i + 1) . "\n";
        for ($j = $i; $j <= min($i + $after, count($L) - 1); $j++) echo '    ' . rtrim($L[$j]) . "\n";
        if (++$n >= $max) break;
    }
    if (!$n) echo "  $rel: no line matches $pattern\n";
};

echo "\n=== the permission and its filter\n";
$show('includes/wc-order-functions.php', '/function wc_downloadable_file_permission|woocommerce_downloadable_file_permission|set_downloads_remaining|set_access_expires/', 1, 6);

echo "\n=== checkout: account required / offered, and its fields\n";
$show('includes/class-wc-checkout.php', '/woocommerce_checkout_registration_required|woocommerce_checkout_registration_enabled|woocommerce_registration_generate_username|woocommerce_registration_generate_password/', 2, 6);
$show('includes/class-wc-checkout.php', '/function process_customer|wc_create_new_customer\(|email_exists|wc_set_customer_auth_cookie/', 2, 6);

echo "\n=== creating the customer\n";
$show('includes/wc-user-functions.php', '/function wc_create_new_customer\b|woocommerce_registration_generate_username|woocommerce_registration_generate_password|registration-error-invalid-username/', 2, 6);

echo "\n=== log in to download\n";
$show('includes/class-wc-download-handler.php', '/function check_download_login_required|woocommerce_downloads_require_login|download_file|is_user_logged_in/', 3, 5);

echo "\n=== My Account → Downloads (which permissions show)\n";
$show('includes/data-stores/class-wc-customer-download-data-store.php', '/function get_downloads_for_customer|downloads_remaining|access_expires/', 2, 5);

echo "\n=== whole functions\n";
$fn = function ($rel, $name, $max = 45) {
    $f = WC_ABSPATH . $rel; if (!is_file($f)) { echo "  $rel: not found\n"; return; }
    $L = file($f);
    foreach ($L as $i => $l) {
        if (!preg_match('/function\s+' . preg_quote($name, '/') . '\s*\(/', $l)) continue;
        echo "  $rel:" . ($i + 1) . "\n";
        $depth = 0; $open = false;
        for ($j = $i; $j < min($i + $max, count($L)); $j++) {
            $line = $L[$j];
            if (preg_match('/^\s*(\*|\/\/|\/\*)/', $line)) continue;
            echo '    ' . rtrim($line) . "\n";
            $depth += substr_count($line, '{') - substr_count($line, '}');
            if (strpos($line, '{') !== false) $open = true;
            if ($open && $depth <= 0) break;
        }
        return;
    }
    echo "  $rel: no function $name\n";
};
$fn('includes/class-wc-checkout.php', 'is_registration_required', 12);
$fn('includes/class-wc-checkout.php', 'is_registration_enabled', 12);
$fn('includes/class-wc-checkout.php', 'get_checkout_fields', 60);
$fn('includes/wc-user-functions.php', 'wc_create_new_customer', 40);
$fn('includes/class-wc-download-handler.php', 'check_download_login_required', 30);
echo "\n=== the checkout billing template (where the account section is)\n";
$tpl = function_exists('wc_locate_template') ? wc_locate_template('checkout/form-billing.php') : '';
echo '  used: ' . ($tpl ? (strpos($tpl, WC_ABSPATH) === 0 ? 'WooCommerce\'s own' : 'the theme\'s copy (' . str_replace(get_theme_root() . '/', '', $tpl) . ')') : '?') . "\n";
if ($tpl && is_file($tpl)) {
    foreach (file($tpl) as $i => $l) {
        if (preg_match('/is_registration_enabled|is_registration_required|woocommerce_before_checkout_registration_form|woocommerce_after_checkout_registration_form|createaccount|get_checkout_fields\( .account. \)/', $l)) {
            echo '  ' . ($i + 1) . ': ' . trim($l) . "\n";
        }
    }
}
echo "=== END\n";
