<?php
/**
 * Cloudflare Turnstile — the "Verify you are human" check on every form where
 * someone logs in, signs up or asks for a password reset (owner, 8 Oct).
 *
 * Where it applies, and where each form is checked on the server:
 *   - /login/ and /sign-up/ (Login | Register widget, theme copy in
 *     inc/ports/eael): its own handlers, which ask af_turnstile_verify();
 *   - My Account login / register / lost password, and the checkout's
 *     "Returning customer? Login": WooCommerce's own error filters;
 *   - the header's login popup (parent theme, AJAX postero_login): the
 *     postero_ajax_verify_captcha hook it fires before anything else;
 *   - wp-login.php login / register / lost password: core's filters, only
 *     for posts to wp-login.php itself, so REST, application passwords,
 *     WP-CLI and wp-admin's own calls are never touched.
 *
 * Switched off until both keys are stored and af_turnstile_mode is "on"
 * (.github/workflows/turnstile.yml writes them from the repository secrets);
 * off, it prints nothing, loads nothing and checks nothing. define
 * AF_TURNSTILE_OFF in wp-config.php switches it off whatever the options say.
 *
 * A visitor's token is always checked: missing, invalid, expired or reused
 * means no. When Cloudflare cannot be asked at all (network, 5xx, a reply
 * that is not Cloudflare's) or refuses our secret, the form goes through and
 * the problem is recorded: an outage at Cloudflare must not lock customers
 * out, and neither can be brought about by a visitor.
 */
if (!defined('ABSPATH')) exit;

function af_turnstile_keys() {
    return array(
        'site'   => trim((string) get_option('af_turnstile_site_key', '')),
        'secret' => trim((string) get_option('af_turnstile_secret_key', '')),
    );
}

function af_turnstile_active() {
    if (defined('AF_TURNSTILE_OFF') && AF_TURNSTILE_OFF) return false;
    if (get_option('af_turnstile_mode', 'off') !== 'on') return false;
    $k = af_turnstile_keys();
    return $k['site'] !== '' && $k['secret'] !== '';
}

// Cloudflare's published test secrets (1x… always passes, 2x… always fails,
// 3x… yields a duplicate): their replies do not carry this site's hostname.
function af_turnstile_is_test_secret($secret) {
    return (bool) preg_match('/^[123]x0+AA$/', (string) $secret);
}

// The hostnames a genuine token may come from: the site's own, with and
// without www.
function af_turnstile_hosts() {
    $hosts = array();
    foreach (array(home_url('/'), site_url('/')) as $url) {
        $h = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        if ($h === '') continue;
        $hosts[] = $h;
        $hosts[] = strpos($h, 'www.') === 0 ? substr($h, 4) : 'www.' . $h;
    }
    return array_values(array_unique($hosts));
}

// A problem on Cloudflare's side or in our configuration, never a visitor's
// failed check: kept for tools/diag-turnstile.php and the PHP error log.
function af_turnstile_problem($kind, $detail) {
    $detail = substr((string) $detail, 0, 300);
    set_transient('af_turnstile_last_error', array('when' => gmdate('Y-m-d H:i:s') . ' UTC', 'kind' => $kind, 'detail' => $detail), WEEK_IN_SECONDS);
    error_log('[af-turnstile] ' . $kind . ': ' . $detail . ' (the form was let through)');
}

/**
 * Whether this request's form passed the check. $action is the form's
 * (login, register, lostpassword); a token made for another form is refused.
 * One siteverify call per token: a token can be checked only once, and a
 * request may ask twice (the Login | Register widget and WooCommerce both
 * look at a login, for example).
 */
function af_turnstile_verify($action = '') {
    static $memo = array();
    if (!af_turnstile_active()) return true;

    $token = isset($_POST['cf-turnstile-response']) && is_string($_POST['cf-turnstile-response'])
        ? trim(wp_unslash($_POST['cf-turnstile-response'])) : '';
    if ($token === '' || strlen($token) > 2048) {
        $GLOBALS['af_ts_reason'] = 'missing';
        return false;
    }
    $mk = md5($token);
    if (isset($memo[$mk])) return $memo[$mk];

    $k = af_turnstile_keys();
    $body = array('secret' => $k['secret'], 'response' => $token, 'idempotency_key' => wp_generate_uuid4());
    $ip = function_exists('af_visitor_ip') ? af_visitor_ip() : (isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '');
    if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) $body['remoteip'] = $ip;

    // Cloudflare answers JSON even to a refusal (400 for a bad secret); only
    // a reply without it is an outage. One retry, same idempotency key, so
    // the retry cannot spend the token twice.
    $res = null; $err = '';
    for ($try = 0; $try < 2 && $res === null; $try++) {
        $r = wp_remote_post('https://challenges.cloudflare.com/turnstile/v0/siteverify', array('timeout' => 6, 'body' => $body));
        if (is_wp_error($r)) { $err = $r->get_error_message(); continue; }
        $json = json_decode((string) wp_remote_retrieve_body($r), true);
        if (is_array($json) && array_key_exists('success', $json)) { $res = $json; break; }
        $code = (int) wp_remote_retrieve_response_code($r);
        $err = 'HTTP ' . $code . ' without a siteverify reply';
        if ($code > 0 && $code < 500 && $code !== 429) break;
    }
    if ($res === null) {
        af_turnstile_problem('unreachable', $err);
        return $memo[$mk] = true;
    }

    $codes = isset($res['error-codes']) && is_array($res['error-codes']) ? array_map('strval', $res['error-codes']) : array();
    if (empty($res['success'])) {
        if (array_intersect($codes, array('missing-input-secret', 'invalid-input-secret'))) {
            af_turnstile_problem('secret', implode(',', $codes) . ' — the secret key stored on the site is not accepted');
            return $memo[$mk] = true;
        }
        $GLOBALS['af_ts_reason'] = 'failed';
        return $memo[$mk] = false;
    }
    if (!af_turnstile_is_test_secret($k['secret'])) {
        $host = strtolower(isset($res['hostname']) ? (string) $res['hostname'] : '');
        if (!in_array($host, af_turnstile_hosts(), true)) {
            $GLOBALS['af_ts_reason'] = 'failed';
            return $memo[$mk] = false;
        }
    }
    if ($action !== '' && !empty($res['action']) && (string) $res['action'] !== $action) {
        $GLOBALS['af_ts_reason'] = 'failed';
        return $memo[$mk] = false;
    }
    return $memo[$mk] = true;
}

// What the visitor is told when the check did not pass. The span lets the
// generic login message (functions.php 25f) pass this one through: it says
// nothing about the account, and the visitor needs it to know what to do.
function af_turnstile_message($html = true) {
    $text = (isset($GLOBALS['af_ts_reason']) && $GLOBALS['af_ts_reason'] === 'missing')
        ? __('Please complete the security check above the button, then try again.', 'postero-child')
        : __('The security check did not go through. Please try again.', 'postero-child');
    return $html ? '<span class="af-ts-msg">' . esc_html($text) . '</span>' : $text;
}

function af_turnstile_in_editor() {
    if (isset($_GET['elementor-preview'])) return true;
    if (class_exists('\Elementor\Plugin') && isset(\Elementor\Plugin::$instance->editor) && \Elementor\Plugin::$instance->editor->is_edit_mode()) return true;
    return false;
}

// The box a form shows. Empty until af-turnstile.js renders Cloudflare's
// widget into it, when it comes into view or its form is used.
function af_turnstile_box($action = 'login', $echo = true) {
    if (!af_turnstile_active() || af_turnstile_in_editor()) return '';
    $html = '<div class="af-ts" data-action="' . esc_attr($action) . '" role="group" aria-label="' . esc_attr__('Security check', 'postero-child') . '">'
          . '<noscript><p class="af-ts-note af-ts-bad">' . esc_html__('Please turn on JavaScript to continue.', 'postero-child') . '</p></noscript></div>';
    if ($echo) echo $html;
    return $html;
}

/* ---- the boxes ---- */
add_action('woocommerce_login_form', function () { af_turnstile_box('login'); });
// after the privacy-policy line (20), so the box sits right above the button
add_action('woocommerce_register_form', function () { af_turnstile_box('register'); }, 30);
add_action('woocommerce_lostpassword_form', function () { af_turnstile_box('lostpassword'); });
add_action('login_form', function () { af_turnstile_box('login'); });
add_action('register_form', function () { af_turnstile_box('register'); });
add_action('lostpassword_form', function () { af_turnstile_box('lostpassword'); });
add_filter('login_form_middle', function ($html) { return $html . af_turnstile_box('login', false); });

/* ---- the server checks ---- */
add_filter('woocommerce_process_login_errors', function ($error) {
    if (is_wp_error($error) && af_turnstile_active() && !af_turnstile_verify('login')) $error->add('af_turnstile', af_turnstile_message());
    return $error;
});
add_filter('woocommerce_process_registration_errors', function ($error) {
    if (is_wp_error($error) && af_turnstile_active() && !af_turnstile_verify('register')) $error->add('af_turnstile', af_turnstile_message());
    return $error;
});
// WooCommerce's lost-password form and wp-login.php's. The Login | Register
// widget checks its own form before it gets here.
add_action('lostpassword_post', function ($errors) {
    if (!is_wp_error($errors) || !af_turnstile_active()) return;
    if (empty($_POST['wc_reset_password']) && (isset($GLOBALS['pagenow']) ? $GLOBALS['pagenow'] : '') !== 'wp-login.php') return;
    if (!af_turnstile_verify('lostpassword')) $errors->add('af_turnstile', af_turnstile_message());
});
// After core has looked at the password (20) and the spam flag (99): an error
// returned earlier would be replaced by core's own result.
add_filter('authenticate', function ($user) {
    if ((isset($GLOBALS['pagenow']) ? $GLOBALS['pagenow'] : '') !== 'wp-login.php' || !isset($_POST['log'])) return $user;
    if (!af_turnstile_active() || af_turnstile_verify('login')) return $user;
    return new WP_Error('af_turnstile', af_turnstile_message());
}, 100);
add_filter('registration_errors', function ($errors) {
    if (!is_wp_error($errors) || (isset($GLOBALS['pagenow']) ? $GLOBALS['pagenow'] : '') !== 'wp-login.php') return $errors;
    if (af_turnstile_active() && !af_turnstile_verify('register')) $errors->add('af_turnstile', af_turnstile_message());
    return $errors;
});
// The header's login popup: answered in the shape its login.js reads.
add_action('postero_ajax_verify_captcha', function () {
    if (!af_turnstile_active() || af_turnstile_verify('login')) return;
    wp_send_json(array('status' => false, 'msg' => af_turnstile_message(false)));
});

/* ---- the Login | Register widget's own settings read these ---- */
add_filter('pre_option_eael_cloudflare_turnstile_sitekey', function () {
    if (!af_turnstile_active()) return '';
    $k = af_turnstile_keys();
    return $k['site'];
});
add_filter('pre_option_eael_cloudflare_turnstile_secretkey', function () {
    if (!af_turnstile_active()) return '';
    $k = af_turnstile_keys();
    return $k['secret'];
});

/* ---- payment pages' CSP report (inc/csp.php): Cloudflare's script and frame ---- */
add_filter('af_csp_policy', function ($policy) {
    if (!af_turnstile_active()) return $policy;
    foreach (array('script-src', 'frame-src') as $dir) {
        $policy = preg_replace_callback('/(^|;)\s*' . preg_quote($dir, '/') . '\s+([^;]*)/', function ($m) use ($dir) {
            if (strpos($m[2], 'challenges.cloudflare.com') !== false) return $m[0];
            return $m[1] . ' ' . $dir . ' ' . rtrim($m[2]) . ' https://challenges.cloudflare.com';
        }, (string) $policy, 1);
    }
    return $policy;
});

/* ---- the script and its look ---- */
function af_turnstile_enqueue() {
    $file = get_stylesheet_directory() . '/assets/js/af-turnstile.js';
    wp_enqueue_script('af-turnstile', get_stylesheet_directory_uri() . '/assets/js/af-turnstile.js', array(), (string) (@filemtime($file) ?: '1'), true);
    $k = af_turnstile_keys();
    $lang = function_exists('transposh_get_current_language') ? (string) transposh_get_current_language() : '';
    wp_add_inline_script('af-turnstile', 'window.afTs=' . wp_json_encode(array(
        'sitekey' => $k['site'],
        'lang'    => ($lang === '' || $lang === 'en') ? 'auto' : $lang,
        'msg'     => array(
            'wait'    => __('One moment — finishing the security check…', 'postero-child'),
            'tick'    => __('Please tick the security check above, then try again.', 'postero-child'),
            'blocked' => __('The security check could not load. Please allow challenges.cloudflare.com (an ad-blocker may be stopping it) or try another network, then reload the page.', 'postero-child'),
            'error'   => __('The security check ran into a problem. It will retry by itself; you can also reload the page.', 'postero-child'),
        ),
    )) . ';', 'before');
    wp_register_style('af-turnstile', false, array(), null);
    wp_enqueue_style('af-turnstile');
    wp_add_inline_style('af-turnstile',
        '.af-ts{margin:14px 0 16px;max-width:100%;min-height:65px;box-sizing:border-box}'
      . '.af-ts.af-ts-compact{min-height:140px}'
      . '.af-ts:not(.af-ts-ready){border:1px solid #e4e4e4;border-radius:4px;background:#f7f7f7}'
      . '.af-ts.af-ts-compact:not(.af-ts-ready){max-width:150px}'
      . '.af-ts iframe{max-width:100%}'
      . '.af-ts-note{margin:6px 0 0;font-size:13px;line-height:1.45;color:#555}'
      . '.af-ts-note:empty{display:none}'
      . '.af-ts-note.af-ts-bad{color:#b32d2e}'
      . '#login form .af-ts{margin:6px 0 16px}'
    );
}
add_action('wp_enqueue_scripts', function () {
    // logged-in visitors have no login or sign-up form to fill
    if (!af_turnstile_active() || is_user_logged_in() || af_turnstile_in_editor()) return;
    af_turnstile_enqueue();
}, 20);
add_action('login_enqueue_scripts', function () {
    if (af_turnstile_active()) af_turnstile_enqueue();
});
