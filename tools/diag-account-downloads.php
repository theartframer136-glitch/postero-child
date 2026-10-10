<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * READ-ONLY. Do a digital buyer's downloads stay in their account?
 *
 * Owner, 10 Oct: "if a person purchase a digital product the product link stay
 * in their account which they have login in our website". Prints: the shop's
 * download and account settings, whether My Account has its Downloads tab, the
 * per-product download limit and expiry (tools/enable-digital-downloads.php set
 * 5 downloads / 30 days), what the buyers already have (permissions: limited,
 * expiring, expired, used up, guest or account), whether a guest's digital
 * order can reach an account later, and which theme templates draw the
 * Downloads tab. Counts only: no customer names or addresses (the log is
 * public).
 *
 * Run: wp eval-file tools/diag-account-downloads.php --allow-root
 */
if (!defined('ABSPATH')) exit(1);
global $wpdb;

echo "=== settings\n";
foreach (array('woocommerce_downloads_require_login', 'woocommerce_downloads_grant_access_after_payment', 'woocommerce_file_download_method',
               'woocommerce_myaccount_downloads_endpoint', 'woocommerce_enable_guest_checkout', 'woocommerce_enable_checkout_login_reminder',
               'woocommerce_enable_signup_and_login_from_checkout', 'woocommerce_enable_myaccount_registration',
               'woocommerce_registration_generate_password', 'woocommerce_registration_generate_username',
               'af_dl_limit', 'af_dl_expiry_days') as $o) {
    echo "  $o = " . json_encode(get_option($o, null)) . "\n";
}

echo "\n=== My Account menu\n";
$menu = function_exists('wc_get_account_menu_items') ? wc_get_account_menu_items() : array();
echo '  ' . implode(' | ', array_map(function ($k, $v) { return "$k: " . wp_strip_all_tags($v); }, array_keys($menu), $menu)) . "\n";
echo '  Downloads tab: ' . (isset($menu['downloads']) ? 'shown' : 'NOT shown') . ', link ' . wp_parse_url(wc_get_account_endpoint_url('downloads'), PHP_URL_PATH) . "\n";
foreach (array('woocommerce/myaccount/downloads.php', 'woocommerce/order/order-downloads.php', 'woocommerce/myaccount/dashboard.php') as $t) {
    $f = locate_template($t);
    echo "  template $t: " . ($f ? 'the theme\'s own (' . str_replace(get_theme_root() . '/', '', $f) . ')' : 'WooCommerce\'s') . "\n";
}

echo "\n=== per product: download limit and expiry (published products)\n";
foreach (array('_download_limit' => 'limit (downloads per purchase)', '_download_expiry' => 'expiry (days after the order)') as $k => $label) {
    $rows = $wpdb->get_results($wpdb->prepare("SELECT COALESCE(NULLIF(m.meta_value, ''), '(none)') v, COUNT(*) n FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s WHERE p.post_type = 'product' AND p.post_status = 'publish' GROUP BY v ORDER BY n DESC", $k));
    echo "  $label: " . implode(', ', array_map(function ($r) { return ($r->v === '-1' ? 'unlimited (-1)' : $r->v) . " on {$r->n}"; }, $rows)) . "\n";
}
echo '  downloadable: ' . (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_downloadable' AND m.meta_value = 'yes' WHERE p.post_type = 'product' AND p.post_status = 'publish'") . " published products\n";

echo "\n=== what buyers already have (download permissions)\n";
$t = $wpdb->prefix . 'woocommerce_downloadable_product_permissions';
$q = function ($where) use ($wpdb, $t) { return (int) $wpdb->get_var("SELECT COUNT(*) FROM $t WHERE $where"); };
printf("  %d in all: %d tied to an account, %d to a guest (email only)\n", $q('1=1'), $q('user_id > 0'), $q('user_id = 0'));
printf("  expiry: %d never expire, %d expire later, %d already expired\n", $q('access_expires IS NULL'), $q('access_expires > UTC_TIMESTAMP()'), $q('access_expires <= UTC_TIMESTAMP()'));
printf("  limit: %d unlimited, %d with downloads left, %d used up (0 left)\n", $q("downloads_remaining = ''"), $q("downloads_remaining <> '' AND downloads_remaining > 0"), $q("downloads_remaining = '0'"));
printf("  used at least once: %d; most downloads of one: %d\n", $q('download_count > 0'), (int) $wpdb->get_var("SELECT MAX(download_count) FROM $t"));
$first = $wpdb->get_var("SELECT MIN(access_granted) FROM $t");
echo '  oldest granted: ' . ($first ?: 'none') . "\n";

echo "\n=== orders with a digital line\n";
$ids = $wpdb->get_col("SELECT DISTINCT i.order_id FROM {$wpdb->prefix}woocommerce_order_items i JOIN {$wpdb->prefix}woocommerce_order_itemmeta m ON m.order_item_id = i.order_item_id WHERE (m.meta_key = 'Format' AND m.meta_value = 'Digital Download') OR m.meta_key = 'af_digital'");
$by = array(); $guest = 0; $guest_has_account = 0; $acct = 0; $paid = 0; $perm = 0;
foreach ($ids as $oid) {
    $o = wc_get_order($oid); if (!$o) continue;
    $by[$o->get_status()] = ($by[$o->get_status()] ?? 0) + 1;
    if ($o->is_paid()) $paid++;
    if ($o->get_customer_id()) { $acct++; } else { $guest++; if (email_exists($o->get_billing_email())) $guest_has_account++; }
    if ((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $t WHERE order_id = %d", $oid))) $perm++;
}
printf("  %d orders (%s); paid %d; with a download permission %d\n", count($ids), implode(', ', array_map(function ($k, $v) { return "$k $v"; }, array_keys($by), $by)), $paid, $perm);
printf("  bought logged in: %d; as a guest: %d, of whom %d have an account with the same email now\n", $acct, $guest, $guest_has_account);

echo "\n=== does a guest's order reach an account later?\n";
echo '  wc_update_new_customer_past_orders(): ' . (function_exists('wc_update_new_customer_past_orders') ? 'exists' : 'missing') . "\n";
foreach (array('woocommerce_created_customer', 'woocommerce_new_customer', 'user_register') as $h) {
    echo "  on $h: " . (has_action($h, 'wc_update_new_customer_past_orders') !== false ? 'links past guest orders' : 'not hooked') . "\n";
}
if (defined('WC_ABSPATH')) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(WC_ABSPATH . 'includes', FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->getExtension() !== 'php') continue;
        foreach (file($f->getPathname()) as $n => $l) {
            if (strpos($l, 'wc_update_new_customer_past_orders') !== false && strpos($l, 'function wc_update_new_customer_past_orders') === false) {
                echo '  called in ' . str_replace(WC_ABSPATH, '', $f->getPathname()) . ':' . ($n + 1) . ': ' . trim(substr($l, 0, 140)) . "\n";
            }
        }
    }
}
echo "\n=== the account-created email and express pay buttons\n";
$na = get_option('woocommerce_customer_new_account_settings', array());
echo '  "Your account has been created" email: ' . (is_array($na) && ($na['enabled'] ?? 'yes') === 'yes' ? 'on' : 'OFF') . "\n";
$sq = get_option('woocommerce_square_credit_card_settings', array());
echo '  Square express pay (Apple Pay / Google Pay): ' . (is_array($sq) && ($sq['enable_digital_wallets'] ?? 'no') === 'yes' ? 'ON' : 'off') . "\n";
foreach ((array) WC()->payment_gateways()->get_available_payment_gateways() as $id => $g) echo "  gateway on: $id\n";
echo '  checkout page uses: ' . (has_block('woocommerce/checkout', (int) wc_get_page_id('checkout')) ? 'the Checkout block' : 'the classic [woocommerce_checkout]') . "\n";

echo "\n=== published text that promises a download limit\n";
$hits = $wpdb->get_results("SELECT ID, post_type, post_title, post_content FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ('page', 'post', 'product') AND post_content REGEXP '([0-9]+|five|ten) downloads|30 days|30-day|expire' AND post_content LIKE '%download%'");
foreach ($hits as $h) {
    preg_match_all('/[^.<>]{0,80}(\b\d+ downloads|five downloads|30 days|30-day|expire)[^.<>]{0,80}/i', wp_strip_all_tags($h->post_content), $m);
    $m = array_slice(array_unique(array_map('trim', $m[0])), 0, 3);
    if ($m) echo "  #{$h->ID} {$h->post_type} \"" . mb_substr($h->post_title, 0, 50) . '": ' . implode(' | ', $m) . "\n";
}
if (!$hits) echo "  none\n";
echo "=== END\n";
