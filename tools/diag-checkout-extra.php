<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. Settings the 22 Oct checkout review depends on:
 *  - Square card: saved cards ("Customer Profiles" / tokenization), which
 *    opens My Account > Add payment method to signed-in visitors;
 *  - Square digital wallets: on which pages the buttons show;
 *  - account sign-up: My Account registration, registration at checkout,
 *    whether a digital item makes checkout create an account;
 *  - the Login | Register widget (EAEL port): email codes (OTP) on sign-up
 *    or log-in, in any Elementor page that uses the widget;
 *  - accounts: how many, how many have ever paid (no names, no emails).
 *
 *   wp eval-file tools/diag-checkout-extra.php
 */
global $wpdb;
$yn = function ($b) { return $b ? 'yes' : 'no'; };

echo "=== Square card\n";
$sq = get_option('woocommerce_square_credit_card_settings', array());
foreach (array('enabled', 'tokenization', 'enable_customer_profiles', 'enable_digital_wallets', 'digital_wallets_button_type', 'digital_wallets_apple_pay_button_color', 'transaction_type', 'charge_virtual_orders') as $k) {
    if (is_array($sq) && array_key_exists($k, $sq)) echo "  {$k} = " . (is_array($sq[$k]) ? implode('|', $sq[$k]) : $sq[$k]) . "\n";
}
if (function_exists('WC')) {
    $g = WC()->payment_gateways()->payment_gateways();
    if (isset($g['square_credit_card'])) {
        $gw = $g['square_credit_card'];
        echo '  supports tokenization: ' . $yn($gw->supports('tokenization')) . ' | add_payment_method: ' . $yn($gw->supports('add_payment_method')) . "\n";
        if (method_exists($gw, 'tokenization_enabled')) echo '  tokenization_enabled(): ' . $yn($gw->tokenization_enabled()) . "\n";
    }
    $dw = class_exists('\WooCommerce\Square\Gateway\Digital_Wallet') ? 'class present' : 'class absent';
    echo '  digital wallet: ' . $dw . ' | pages filter: ' . implode(', ', (array) apply_filters('wc_square_display_digital_wallet_on_pages', array('product', 'cart', 'checkout'), null)) . "\n";
}

echo "\n=== account sign-up\n";
foreach (array('woocommerce_enable_myaccount_registration', 'woocommerce_enable_signup_and_login_from_checkout', 'woocommerce_enable_guest_checkout', 'woocommerce_registration_generate_password', 'woocommerce_registration_generate_username', 'users_can_register') as $o) echo "  {$o} = " . get_option($o, '(unset)') . "\n";
echo '  inc/digital-account.php present: ' . $yn(file_exists(get_stylesheet_directory() . '/inc/digital-account.php')) . "\n";

echo "\n=== Login | Register widget (email codes)\n";
$rows = $wpdb->get_results("SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_data' AND meta_value LIKE '%eael-login-register%' LIMIT 20");
if (!$rows) echo "  no Elementor page uses the widget\n";
foreach ($rows as $r) {
    $p = get_post($r->post_id);
    if (!$p || $p->post_status !== 'publish') continue;
    $flags = array();
    foreach (array('enable_register_otp', 'enable_login_otp', 'register_user_role', 'enable_cloudflare_turnstile', 'enable_cloudflare_turnstile_on_register', 'enable_cloudflare_turnstile_on_login', 'show_register_link', 'default_form_type') as $k) {
        if (preg_match('/"' . preg_quote($k, '/') . '":"([^"]*)"/', $r->meta_value, $m)) $flags[] = $k . '=' . $m[1];
    }
    echo "  #{$r->post_id} /{$p->post_name}/: " . ($flags ? implode(', ', $flags) : '(defaults)') . "\n";
}

echo "\n=== accounts\n";
$total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}");
$paying = (int) $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key = 'paying_customer' AND meta_value = '1'");
$recent = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->users} WHERE user_registered >= %s", gmdate('Y-m-d', time() - 30 * DAY_IN_SECONDS)));
echo "  accounts: {$total} | ever paid: {$paying} | registered in the last 30 days: {$recent}\n";
$roles = array(); foreach (get_users(array('fields' => array('ID'), 'number' => 500)) as $u) { $uu = get_userdata($u->ID); foreach ((array) $uu->roles as $r) $roles[$r] = ($roles[$r] ?? 0) + 1; }
echo '  by role: ' . wp_json_encode($roles) . "\n";
$tok = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_payment_tokens");
echo "  saved payment tokens: {$tok}\n";
echo "=== END\n";
