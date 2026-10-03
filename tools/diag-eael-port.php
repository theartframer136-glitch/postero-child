<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. The settings the Essential Addons theme port
 * (inc/ports/essential-addons.php) relies on but tools/diag-elementor-addons.php
 * did not read: the asset print methods, the Login | Register options, the
 * per-document widget lists and custom JS EA keeps in post meta, page-level EA
 * extension switches, users EA holds back from logging in, and the generated
 * files in uploads/essential-addons-elementor/. Secrets are reported only as
 * set / not set.
 *
 * What the port expects (anything else needs a look before switching):
 *   - no document with eael_ext_reading_progress / eael_ext_table_of_content /
 *     eael_ext_scroll_to_top = yes (the port does not print those footers);
 *   - _eael_widget_elements values made only of adv-tabs, login-register,
 *     woo-add-to-cart, woo-product-carousel (anything else gets no CSS/JS).
 *
 * Run: wp eval-file tools/diag-eael-port.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
global $wpdb;

echo "=== plugin\n";
echo '  EAEL_PLUGIN_VERSION=' . (defined('EAEL_PLUGIN_VERSION') ? EAEL_PLUGIN_VERSION : '-') . ' theme port=' . (defined('AF_EAEL_PORT') ? 'loaded' : 'dormant') . "\n";

echo "\n=== options\n";
foreach (array('elementor_css_print_method', 'eael_js_print_method', 'users_can_register', 'default_role', 'woocommerce_cart_redirect_after_add',
               'eael_recaptcha_language', 'eael_recaptcha_badge_hide', 'eael_custom_profile_fields', 'eael_custom_profile_fields_text', 'eael_custom_profile_fields_img',
               'eael_lr_admin_approval', 'eael_version', 'eael_editor_updated_at') as $o) {
    $v = get_option($o, null);
    echo "  $o = " . ($v === null ? '(absent)' : json_encode($v, JSON_UNESCAPED_SLASHES)) . "\n";
}
foreach (array('eael_recaptcha_sitekey', 'eael_recaptcha_secret', 'eael_recaptcha_sitekey_v3', 'eael_recaptcha_secret_v3', 'eael_cloudflare_turnstile_sitekey', 'eael_cloudflare_turnstile_secretkey') as $o) {
    $v = get_option($o, '');
    echo "  $o = " . ($v !== '' && $v !== false ? 'SET' : 'not set') . "\n";
}
$mya = get_option('eael_lr_my_account_fields', array());
echo '  eael_lr_my_account_fields = ' . json_encode($mya) . "\n";
$n = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE 'eael\\_%error%' OR option_name LIKE 'eael\\_%success%'");
echo "  eael_*error*/eael_*success* transient-style options: $n\n";

echo "\n=== _eael_widget_elements (per-document widget lists)\n";
$rows = $wpdb->get_results("SELECT m.post_id, m.meta_value, p.post_type, p.post_status FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = '_eael_widget_elements'");
$known = array('adv-tabs', 'login-register', 'woo-add-to-cart', 'woo-product-carousel');
$by = array();
foreach ($rows as $r) {
    $v = maybe_unserialize($r->meta_value);
    $k = is_array($v) ? implode(',', array_values($v)) : (string) $v;
    $by[$k][] = "#{$r->post_id}/{$r->post_type}/{$r->post_status}";
}
ksort($by);
foreach ($by as $k => $ids) {
    $extra = $k === '' ? array() : array_diff(explode(',', $k), $known);
    echo '  [' . ($k === '' ? '(empty)' : $k) . '] x' . count($ids) . ($extra ? '  <-- NOT PORTED: ' . implode(',', $extra) : '') . '  ' . implode(' ', array_slice($ids, 0, 12)) . (count($ids) > 12 ? ' ...' : '') . "\n";
}

echo "\n=== _eael_custom_js (printed in the footer by Asset_Builder)\n";
foreach ($wpdb->get_results("SELECT post_id, LENGTH(meta_value) AS len FROM {$wpdb->postmeta} WHERE meta_key = '_eael_custom_js' AND meta_value <> ''") as $r) {
    echo "  #{$r->post_id} " . get_post_type($r->post_id) . " {$r->len} bytes\n";
}

echo "\n=== page settings with EA keys (_elementor_page_settings)\n";
foreach ($wpdb->get_results("SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_page_settings' AND meta_value LIKE '%eael_%'") as $r) {
    $s = maybe_unserialize($r->meta_value);
    if (!is_array($s)) continue;
    $hit = array();
    foreach ($s as $k => $v) if (strpos($k, 'eael_') === 0) $hit[$k] = is_scalar($v) ? (strlen((string) $v) > 60 ? substr((string) $v, 0, 60) . '...' : $v) : '[' . gettype($v) . ']';
    if ($hit) echo "  #{$r->post_id} " . get_post_type($r->post_id) . ' ' . get_post_status($r->post_id) . ' ' . json_encode($hit, JSON_UNESCAPED_SLASHES) . "\n";
}

echo "\n=== other EA post meta\n";
foreach (array('_eael_checkout_fields_settings', '_eael_post_view_count') as $k) {
    $n = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s", $k));
    echo "  $k: $n posts\n";
}

echo "\n=== users EA holds back\n";
foreach (array('_eael_otp_pending' => '1', 'eael_registration_status' => null) as $k => $want) {
    $rows = $wpdb->get_results($wpdb->prepare("SELECT meta_value, COUNT(*) AS n FROM {$wpdb->usermeta} WHERE meta_key = %s GROUP BY meta_value", $k));
    foreach ($rows as $r) echo "  $k=" . $r->meta_value . ": {$r->n}\n";
    if (!$rows) echo "  $k: none\n";
}

echo "\n=== uploads/essential-addons-elementor\n";
$dir = wp_upload_dir()['basedir'] . '/essential-addons-elementor';
if (!is_dir($dir)) {
    echo "  (no folder)\n";
} else {
    foreach (scandir($dir) as $f) {
        if ($f === '.' || $f === '..') continue;
        $p = "$dir/$f";
        echo '  ' . $f . ' ' . filesize($p) . ' bytes md5=' . md5_file($p) . ' ' . gmdate('Y-m-d H:i', filemtime($p)) . "\n";
    }
}
echo "\n=== END\n";
