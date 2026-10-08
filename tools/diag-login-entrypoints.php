<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. Every place a visitor (or a bot) can log in, sign up or ask for
 * a password reset, before Cloudflare Turnstile is added to them:
 *  - the Login | Register widget on the Login and Sign-up pages: its saved
 *    settings (AJAX or not, which forms, Turnstile / reCAPTCHA switches);
 *  - WordPress and WooCommerce registration switches;
 *  - the WooCommerce My Account page (login_url points there) and whether a
 *    guest is sent elsewhere;
 *  - every callback on the login / registration hooks, and every guest AJAX
 *    action, with the file it comes from;
 *  - the parent theme's own login / sign-up code.
 * Key options are reported as present / absent only.
 *
 * Run: wp eval-file tools/diag-login-entrypoints.php --allow-root
 */
if (!defined('ABSPATH')) exit(1);
global $wpdb, $wp_filter;

function af_le_cb_file($f) {
    try {
        if ($f instanceof Closure) $r = new ReflectionFunction($f);
        elseif (is_string($f) && strpos($f, '::') !== false) $r = new ReflectionMethod($f);
        elseif (is_array($f) && isset($f[0], $f[1])) $r = new ReflectionMethod($f[0], $f[1]);
        elseif (is_string($f) && function_exists($f)) $r = new ReflectionFunction($f);
        else return '?';
        $file = (string) $r->getFileName();
        foreach (array(get_stylesheet_directory() => 'child', get_template_directory() => 'parent', WP_PLUGIN_DIR => 'plugins', ABSPATH => 'core') as $d => $l) {
            if ($file !== '' && strpos($file, $d) === 0) return $l . substr($file, strlen($d)) . ':' . $r->getStartLine();
        }
        return $file . ':' . $r->getStartLine();
    } catch (Throwable $e) { return '?'; }
}
function af_le_cb_name($f) {
    if (is_string($f)) return $f;
    if (is_array($f)) return (is_object($f[0]) ? get_class($f[0]) : $f[0]) . '::' . $f[1];
    if ($f instanceof Closure) return 'closure';
    return gettype($f);
}
function af_le_find_widgets($els, &$out) {
    foreach ((array) $els as $e) {
        if (!is_array($e)) continue;
        if (($e['widgetType'] ?? '') === 'eael-login-register') $out[] = $e;
        if (!empty($e['elements'])) af_le_find_widgets($e['elements'], $out);
    }
}

echo "=== Login | Register widgets in Elementor content\n";
$ids = $wpdb->get_col("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_data' AND meta_value LIKE '%eael-login-register%'");
foreach ($ids as $pid) {
    $p = get_post($pid);
    $data = json_decode((string) get_post_meta($pid, '_elementor_data', true), true);
    $w = array(); af_le_find_widgets($data, $w);
    printf("  post #%d [%s] %s %s  widgets: %d\n", $pid, $p ? $p->post_type : '?', $p ? $p->post_status : '?', $p ? get_permalink($p) : '', count($w));
    foreach ($w as $e) {
        $s = (array) ($e['settings'] ?? array());
        $keep = array();
        foreach ($s as $k => $v) {
            if (preg_match('/^(default_form_type|show_log_in_link|show_register_link|show_lost_password|enable_ajax|enable_cloudflare_turnstile.*|cloudflare_turnstile_theme|enable_.*recaptcha.*|login_register_recaptcha_version|enable_.*otp.*|redirect_after_login|redirect_after_register|register_user_role|register_action|enable_register_fields_.*|login_form_title|register_form_title)$/', $k)) {
                $keep[$k] = is_scalar($v) ? $v : json_encode($v);
            }
        }
        echo '    widget ' . ($e['id'] ?? '?') . ': ' . json_encode($keep, JSON_UNESCAPED_SLASHES) . "\n";
    }
}

echo "\n=== switches\n";
foreach (array('users_can_register', 'default_role', 'woocommerce_enable_myaccount_registration', 'woocommerce_enable_signup_and_login_from_checkout', 'woocommerce_enable_checkout_login_reminder', 'woocommerce_registration_generate_username', 'woocommerce_registration_generate_password', 'woocommerce_myaccount_page_id') as $o) {
    echo "  $o = " . json_encode(get_option($o)) . "\n";
}
foreach (array('eael_cloudflare_turnstile_sitekey', 'eael_cloudflare_turnstile_secretkey', 'eael_recaptcha_sitekey', 'eael_recaptcha_secret', 'eael_recaptcha_sitekey_v3') as $o) {
    $v = get_option($o);
    echo "  $o: " . (is_string($v) && $v !== '' ? 'present (' . strlen($v) . ' chars)' : 'absent') . "\n";
}
$ma = (int) get_option('woocommerce_myaccount_page_id');
if ($ma) {
    $p = get_post($ma);
    echo "  My Account page #$ma " . get_permalink($ma) . ' uses Elementor: ' . (get_post_meta($ma, '_elementor_edit_mode', true) === 'builder' ? 'yes' : 'no')
        . ' | content has [woocommerce_my_account]: ' . ($p && strpos($p->post_content, 'woocommerce_my_account') !== false ? 'yes' : 'no') . "\n";
}
foreach (array('login', 'sign-up') as $slug) {
    $p = get_page_by_path($slug);
    echo "  page /$slug/: " . ($p ? '#' . $p->ID . ' ' . $p->post_status : 'none') . "\n";
}

echo "\n=== callbacks on login / registration hooks (file:line)\n";
foreach (array('login_url', 'register_url', 'lostpassword_url', 'login_init', 'login_form', 'register_form', 'lostpassword_form', 'authenticate', 'wp_authenticate_user', 'registration_errors', 'register_post', 'lostpassword_post', 'woocommerce_login_form', 'woocommerce_login_form_end', 'woocommerce_register_form', 'woocommerce_register_form_end', 'woocommerce_process_login_errors', 'woocommerce_process_registration_errors', 'woocommerce_register_post', 'woocommerce_lostpassword_form', 'template_redirect', 'wp_login', 'login_redirect') as $h) {
    if (empty($wp_filter[$h])) { echo "  $h: none\n"; continue; }
    $list = array();
    foreach ($wp_filter[$h]->callbacks as $prio => $cbs) foreach ($cbs as $cb) {
        $file = af_le_cb_file($cb['function']);
        if ($h === 'template_redirect' && !preg_match('/login|account|regist|redirect/i', af_le_cb_name($cb['function']) . $file)) continue;
        $list[] = "@$prio " . af_le_cb_name($cb['function']) . ' (' . $file . ')';
    }
    echo "  $h: " . ($list ? "\n      " . implode("\n      ", array_slice($list, 0, 25)) : 'none matching') . "\n";
}

echo "\n=== guest AJAX actions (wp_ajax_nopriv_*) that look like login / sign-up / password\n";
foreach ($wp_filter as $tag => $h) {
    if (strpos($tag, 'wp_ajax_nopriv_') !== 0 || !($h instanceof WP_Hook)) continue;
    foreach ($h->callbacks as $prio => $cbs) foreach ($cbs as $cb) {
        $n = af_le_cb_name($cb['function']); $file = af_le_cb_file($cb['function']);
        if (preg_match('/login|regist|signup|sign_up|password|lost|reset|otp|auth|user/i', $tag . $n)) echo "  $tag -> $n ($file)\n";
    }
}

echo "\n=== parent theme code that logs in / creates users\n";
$dir = get_template_directory();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
$n = 0;
foreach ($it as $f) {
    if (!$f->isFile() || !preg_match('/\.php$/', $f->getFilename())) continue;
    $src = @file_get_contents($f->getPathname()); if ($src === false) continue;
    if (!preg_match_all('/(wp_signon|wp_set_auth_cookie|wp_create_user|wp_insert_user|register_new_user|retrieve_password|wc_create_new_customer|wp_ajax_nopriv_[a-z0-9_]+)/i', $src, $m, PREG_OFFSET_CAPTURE)) continue;
    foreach ($m[0] as $hit) {
        $line = substr_count(substr($src, 0, $hit[1]), "\n") + 1;
        echo '  ' . substr($f->getPathname(), strlen($dir)) . ":$line  " . $hit[0] . "\n";
        if (++$n >= 40) break 2;
    }
}
if (!$n) echo "  none\n";
$tpl = $dir . '/woocommerce/myaccount';
echo '  parent overrides of woocommerce/myaccount: ' . (is_dir($tpl) ? implode(' ', array_slice(scandir($tpl), 2)) : 'none') . "\n";
echo "=== END\n";
