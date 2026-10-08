<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. Cloudflare Turnstile on the login / sign-up / password-reset
 * forms (inc/turnstile.php): its state, and everything that decides whether
 * switching it on is safe:
 *  - module loaded, switched on or not, keys present (yes/no + length only,
 *    never the values), the last Cloudflare problem it logged;
 *  - the callbacks on the hooks it uses, in run order, with their files;
 *  - LiteSpeed's JavaScript optimisation settings (defer / delay / combine),
 *    and which exclusion markers LiteSpeed's own code honours;
 *  - the pages as a visitor gets them (cached copies): which login / sign-up
 *    forms each one carries, whether the Turnstile box and script are in
 *    them, and forms that post to wp-login.php;
 *  - Elementor content with a login or registration widget other than the
 *    Essential Addons one, and wp_login_form() calls in the themes;
 *  - the WooCommerce templates actually used for the login, register and
 *    lost-password forms, and whether they fire the hooks the box needs.
 *
 * Run: wp eval-file tools/diag-turnstile.php --allow-root
 */
if (!defined('ABSPATH')) exit(1);
global $wpdb, $wp_filter;

function af_dt_cb($f) {
    try {
        if ($f instanceof Closure) $r = new ReflectionFunction($f);
        elseif (is_string($f) && strpos($f, '::') !== false) $r = new ReflectionMethod($f);
        elseif (is_array($f) && isset($f[0], $f[1])) $r = new ReflectionMethod($f[0], $f[1]);
        elseif (is_string($f) && function_exists($f)) $r = new ReflectionFunction($f);
        else return '?';
        $file = wp_normalize_path((string) $r->getFileName());
        foreach (array(get_stylesheet_directory() => 'child', get_template_directory() => 'parent', WP_PLUGIN_DIR => 'plugins', ABSPATH => 'core') as $d => $l) {
            $d = wp_normalize_path($d);
            if ($file !== '' && strpos($file, $d) === 0) { $file = $l . substr($file, strlen($d)); break; }
        }
        $n = is_string($f) ? $f : (is_array($f) ? (is_object($f[0]) ? get_class($f[0]) : $f[0]) . '::' . $f[1] : 'closure');
        return $n . ' (' . $file . ':' . $r->getStartLine() . ')';
    } catch (Throwable $e) { return '?'; }
}

echo "=== module\n";
$loaded = function_exists('af_turnstile_active');
echo '  inc/turnstile.php loaded: ' . ($loaded ? 'yes' : 'no') . "\n";
foreach (array('af_turnstile_site_key', 'af_turnstile_secret_key') as $o) {
    $v = get_option($o);
    echo "  $o: " . (is_string($v) && $v !== '' ? 'present (' . strlen($v) . ' chars' . (preg_match('/^[123]x0{10}/', $v) ? ', a Cloudflare TEST key' : '') . ')' : 'absent') . "\n";
}
echo '  af_turnstile_mode = ' . json_encode(get_option('af_turnstile_mode', '(unset)')) . "\n";
echo '  AF_TURNSTILE_OFF defined: ' . (defined('AF_TURNSTILE_OFF') ? 'YES' : 'no') . "\n";
if ($loaded) echo '  af_turnstile_active() = ' . (af_turnstile_active() ? 'YES (switched on)' : 'no (switched off)') . "\n";
$le = get_transient('af_turnstile_last_error');
echo '  last Cloudflare problem: ' . ($le ? json_encode($le, JSON_UNESCAPED_SLASHES) : 'none recorded') . "\n";
foreach (array('eael_cloudflare_turnstile_sitekey', 'eael_cloudflare_turnstile_secretkey') as $o) {
    $v = get_option($o);
    echo "  $o as EAEL reads it: " . (is_string($v) && $v !== '' ? 'present (' . strlen($v) . ' chars)' : 'empty') . "\n";
}
$home = wp_parse_url(home_url('/'), PHP_URL_HOST);
echo '  home host: ' . $home . ' | siteurl host: ' . wp_parse_url(site_url('/'), PHP_URL_HOST) . "\n";

echo "\n=== callbacks, in run order (file:line)\n";
foreach (array('authenticate', 'login_errors', 'registration_errors', 'lostpassword_post', 'woocommerce_process_login_errors', 'woocommerce_process_registration_errors', 'postero_ajax_verify_captcha', 'login_form', 'register_form', 'lostpassword_form', 'login_form_middle', 'woocommerce_login_form', 'woocommerce_register_form', 'woocommerce_lostpassword_form', 'login_enqueue_scripts', 'af_csp_policy', 'pre_option_eael_cloudflare_turnstile_sitekey') as $h) {
    if (empty($wp_filter[$h])) { echo "  $h: none\n"; continue; }
    $list = array();
    $cbs = $wp_filter[$h]->callbacks; ksort($cbs);
    foreach ($cbs as $prio => $set) foreach ($set as $cb) $list[] = "@$prio " . af_dt_cb($cb['function']);
    echo "  $h:\n      " . implode("\n      ", array_slice($list, 0, 20)) . "\n";
}

echo "\n=== LiteSpeed JavaScript settings\n";
$rows = $wpdb->get_results("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'litespeed.conf.optm-js%' OR option_name IN ('litespeed.conf.optm-guest_only', 'litespeed.conf.optm-ucss', 'litespeed.conf.cache-ttl_pub', 'litespeed.conf.esi', 'litespeed.conf.guest', 'litespeed.conf.optm-dns_prefetch_ctrl') ORDER BY option_name");
foreach ($rows as $r) echo '  ' . $r->option_name . ' = ' . substr(preg_replace('/\s+/', ' ', (string) $r->option_value), 0, 300) . "\n";
if (!$rows) echo "  (no litespeed.conf.optm-js* options)\n";
$ls = WP_PLUGIN_DIR . '/litespeed-cache/src';
if (is_dir($ls)) {
    echo "  exclusion markers in LiteSpeed's code (file:line):\n";
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ls, FilesystemIterator::SKIP_DOTS));
    $n = 0;
    foreach ($it as $f) {
        if (!$f->isFile() || substr($f->getFilename(), -4) !== '.php') continue;
        $src = @file_get_contents($f->getPathname()); if ($src === false) continue;
        if (!preg_match_all('/data-no-defer|data-no-optimize|data-no-delay|litespeed_optm_js_defer_exc|litespeed_optimize_js_excludes|litespeed_optm_js_delay_inc|litespeed_optm_gm_js_exc|js_delay_inc|js_defer_exc/', $src, $m, PREG_OFFSET_CAPTURE)) continue;
        foreach ($m[0] as $hit) {
            $line = substr_count(substr($src, 0, $hit[1]), "\n") + 1;
            $text = trim(strtok(substr($src, strrpos(substr($src, 0, $hit[1]), "\n") + 1), "\n"));
            echo '    ' . substr($f->getPathname(), strlen($ls) + 1) . ":$line  " . substr($text, 0, 150) . "\n";
            if (++$n >= 30) break 2;
        }
    }
}

echo "\n=== pages as a visitor gets them\n";
$ma = (int) get_option('woocommerce_myaccount_page_id');
$pages = array(
    'home'          => home_url('/'),
    'login'         => home_url('/login/'),
    'sign-up'       => home_url('/sign-up/'),
    'my-account'    => $ma ? get_permalink($ma) : home_url('/my-account/'),
    'lost-password' => function_exists('wc_lostpassword_url') ? wc_lostpassword_url() : home_url('/my-account/lost-password/'),
    'shop'          => function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/'),
);
foreach ($pages as $label => $url) {
    $t0 = microtime(true);
    $r = wp_remote_get($url, array('timeout' => 25, 'redirection' => 3, 'headers' => array('User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36', 'Accept' => 'text/html')));
    $ms = (int) ((microtime(true) - $t0) * 1000);
    if (is_wp_error($r)) { echo "  $label $url: ERROR " . $r->get_error_message() . "\n"; continue; }
    $b = (string) wp_remote_retrieve_body($r);
    $cache = wp_remote_retrieve_header($r, 'x-litespeed-cache');
    $c = function ($re) use ($b) { return preg_match_all($re, $b); };
    printf("  %-13s %s  HTTP %s  %d ms  cache=%s  %d KB\n", $label, $url, wp_remote_retrieve_response_code($r), $ms, $cache ? $cache : '-', strlen($b) / 1024);
    printf("      header popup form: %d | woo login: %d | woo register: %d | woo lost-pw: %d | EAEL forms: %d | forms -> wp-login.php: %d\n",
        $c('/class="[^"]*postero-login-form-ajax/'), $c('/woocommerce-form-login/'), $c('/woocommerce-form-register/'), $c('/woocommerce-ResetPassword|lost_reset_password/'),
        $c('/<form[^>]+(eael-login-form|eael-register-form|eael-lostpassword-form)/'), $c('/<form[^>]+action="[^"]*wp-login\.php/'));
    printf("      turnstile box: %d | af-turnstile.js: %d | cloudflare api.js: %d | cf-turnstile div: %d\n",
        $c('/class="af-ts[" ]/'), $c('/af-turnstile(\.min)?\.js/'), $c('/challenges\.cloudflare\.com\/turnstile/'), $c('/class=.cf-turnstile/'));
    if ($label === 'home' && preg_match('/<form class="postero-login-form-ajax".*?<\/form>/s', $b, $m)) {
        echo "      popup form markup:\n        " . str_replace("\n", "\n        ", substr(preg_replace("/\n\s*\n/", "\n", preg_replace('/value="[0-9a-f]{10}"/', 'value="(nonce)"', $m[0])), 0, 2500)) . "\n";
    }
}

echo "\n=== other login / registration widgets in Elementor content\n";
$rows = $wpdb->get_results("SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_data' AND (meta_value LIKE '%login%' OR meta_value LIKE '%regist%')");
$types = array();
foreach ($rows as $r) {
    if (preg_match_all('/"widgetType":"([a-z0-9_-]*(?:login|regist|account)[a-z0-9_-]*)"/', (string) $r->meta_value, $m)) {
        foreach ($m[1] as $t) $types[$t][] = (int) $r->post_id;
    }
}
if (!$types) echo "  none\n";
foreach ($types as $t => $ids) {
    $ids = array_values(array_unique($ids));
    $desc = array();
    foreach (array_slice($ids, 0, 8) as $id) { $p = get_post($id); $desc[] = '#' . $id . ($p ? ' ' . $p->post_type . '/' . $p->post_status : ''); }
    echo "  $t: " . implode(', ', $desc) . (count($ids) > 8 ? ' …' : '') . "\n";
}

echo "\n=== wp_login_form() and forms posting to wp-login.php in theme code\n";
$n = 0;
foreach (array(get_template_directory() => 'parent', get_stylesheet_directory() => 'child') as $dir => $label) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile() || substr($f->getFilename(), -4) !== '.php') continue;
        if (strpos($f->getPathname(), '/tools/') !== false || strpos($f->getPathname(), '/node_modules/') !== false) continue;
        $src = @file_get_contents($f->getPathname()); if ($src === false) continue;
        if (!preg_match_all('/wp_login_form\s*\(|action=["\'][^"\']*wp-login\.php|site_url\(\s*[\'"]wp-login\.php[\'"]\s*,\s*[\'"]login_post/', $src, $m, PREG_OFFSET_CAPTURE)) continue;
        foreach ($m[0] as $hit) {
            $line = substr_count(substr($src, 0, $hit[1]), "\n") + 1;
            echo "  $label" . substr($f->getPathname(), strlen($dir)) . ":$line  " . substr($hit[0], 0, 80) . "\n";
            if (++$n >= 25) break 3;
        }
    }
}
if (!$n) echo "  none\n";

echo "\n=== WooCommerce templates in use\n";
if (function_exists('wc_locate_template')) {
    foreach (array('myaccount/form-login.php' => array('woocommerce_login_form', 'woocommerce_register_form'), 'global/form-login.php' => array('woocommerce_login_form'), 'myaccount/form-lost-password.php' => array('woocommerce_lostpassword_form')) as $tpl => $hooks) {
        $path = wc_locate_template($tpl);
        $src = @file_get_contents($path);
        $rel = str_replace(array(wp_normalize_path(get_stylesheet_directory()), wp_normalize_path(get_template_directory()), wp_normalize_path(WP_PLUGIN_DIR)), array('child', 'parent', 'plugins'), wp_normalize_path($path));
        $have = array();
        foreach ($hooks as $h) $have[] = $h . '=' . ($src !== false && strpos($src, "'" . $h . "'") !== false ? 'yes' : 'NO');
        echo "  $tpl -> $rel  " . implode(' ', $have) . "\n";
    }
}
echo '  checkout login reminder: ' . json_encode(get_option('woocommerce_enable_checkout_login_reminder')) . ' | users_can_register: ' . json_encode(get_option('users_can_register')) . ' | myaccount registration: ' . json_encode(get_option('woocommerce_enable_myaccount_registration')) . "\n";
echo '  transposh_get_current_language(): ' . (function_exists('transposh_get_current_language') ? 'available' : 'missing') . "\n";

echo "\n=== can this server reach Cloudflare's siteverify? (a dummy token, no secret)\n";
$t0 = microtime(true);
$r = wp_remote_post('https://challenges.cloudflare.com/turnstile/v0/siteverify', array('timeout' => 10, 'body' => array('secret' => 'x', 'response' => 'XXXX.DUMMY.TOKEN.XXXX')));
$ms = (int) ((microtime(true) - $t0) * 1000);
echo '  ' . (is_wp_error($r) ? 'ERROR ' . $r->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code($r) . ' in ' . $ms . ' ms: ' . substr((string) wp_remote_retrieve_body($r), 0, 200)) . "\n";
echo "=== END\n";
