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
// widget into it, when it comes into view or its form is used. A "quiet" box
// (the newsletter forms) is drawn only when the form is used and stays
// invisible unless Cloudflare needs the visitor to tick it.
function af_turnstile_box($action = 'login', $echo = true, $quiet = false) {
    if (!af_turnstile_active() || af_turnstile_in_editor()) return '';
    // Logged-in customers are never asked, as the settings page promises, and
    // the script is not loaded for them. wp-login.php is the exception: it
    // loads the script for everyone and checks every login posted to it.
    if (is_user_logged_in() && (isset($GLOBALS['pagenow']) ? $GLOBALS['pagenow'] : '') !== 'wp-login.php') return '';
    $html = '<div class="af-ts' . ($quiet ? ' af-ts-quiet' : '') . '" data-action="' . esc_attr($action) . '"'
          . ($quiet ? ' data-on-use="1" data-appearance="interaction-only"' : '')
          . ' role="group" aria-label="' . esc_attr__('Security check', 'postero-child') . '">'
          . '<noscript><p class="af-ts-note af-ts-bad">' . esc_html__('Please turn on JavaScript to continue.', 'postero-child') . '</p></noscript></div>';
    if ($echo) echo $html;
    return $html;
}

// For the theme's own AJAX forms (contact, newsletter): answers the form's
// script in the shape it reads, and ends the request, when the check fails.
function af_turnstile_require_ajax($action) {
    // logged-in customers get no box and no script, so they are not asked
    // (until 22 Oct they were refused on the contact form, the newsletters
    // and the photo previews)
    if (!af_turnstile_active() || is_user_logged_in() || af_turnstile_verify($action)) return;
    wp_send_json_error(array('message' => af_turnstile_message(false)));
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

/* ---- blog comments and product reviews, from visitors who are not logged in ---- */
// core fires comment_form_after_fields for logged-out visitors only, after the
// name / email / website fields and before the button; WooCommerce's review
// form is the same comment_form()
add_action('comment_form_after_fields', function () { af_turnstile_box('comment'); });
add_filter('preprocess_comment', function ($data) {
    if (!af_turnstile_active() || is_user_logged_in()) return $data;
    // only the comment and review forms, which post to wp-comments-post.php:
    // not pingbacks, not the reviews plugin's own AJAX questions and answers
    if (!isset($GLOBALS['pagenow']) || $GLOBALS['pagenow'] !== 'wp-comments-post.php') return $data;
    $type = isset($data['comment_type']) ? (string) $data['comment_type'] : '';
    if ($type !== '' && $type !== 'comment' && $type !== 'review') return $data;
    if (af_turnstile_verify('comment')) return $data;
    wp_die(esc_html(af_turnstile_message(false)), esc_html__('Security check', 'postero-child'), array('response' => 403, 'back_link' => true));
}, 5);

/* ---- checkout: Place order (22 Oct) ----
 * Guests only. A quiet box (invisible unless Cloudflare is unsure) inside
 * form.checkout, after the address fields and outside the parts WooCommerce
 * redraws on every change, so it survives address and shipping updates.
 *
 * Checked once per real attempt, in WooCommerce's own validation and only
 * when every field is fine: a shopper fixing a typo keeps the same token.
 * Tokens are single-use, so routes that run this validation without placing
 * an order are left out: Square's card form first posts the order form to
 * its own "checkout_handler" to validate it (it ends before any order or
 * payment), then the real Place order follows with the same token. Square's
 * Apple Pay / Google Pay route needs a real wallet, so it is not asked.
 *
 * Behind it, a brake on card testing: 8 attempts that reach payment in an
 * hour from one address pause checkout there for an hour.
 *
 * Switch: option af_turnstile_checkout, on or off (turnstile.yml
 * checkout-on / checkout-off), covers everything here at checkout: the box,
 * the brake, the REST checkout and the email capture; logins stay checked.
 * Off until switched on, so a deploy changes nothing at checkout: the
 * checkout-on run switches it on and checks it live in the same run.
 */
// The checkout box is invisible for most buyers, so "the check above the
// button" would point at nothing: checkout says what to do instead.
function af_turnstile_message_checkout() {
    $missing = isset($GLOBALS['af_ts_reason']) && $GLOBALS['af_ts_reason'] === 'missing';
    $text = $missing
        ? __('The security check had not finished yet. Please wait a moment and press Place order again. If this keeps happening, your browser may be blocking challenges.cloudflare.com: allow it, or try another browser.', 'postero-child')
        : __('The security check did not go through. Please press Place order again.', 'postero-child');
    return '<span class="af-ts-msg">' . esc_html($text) . '</span>';
}
function af_turnstile_checkout_on() {
    return af_turnstile_active() && get_option('af_turnstile_checkout', 'off') === 'on';
}
function af_turnstile_checkout_skip_route() {
    foreach ((array) (isset($GLOBALS['wp_current_filter']) ? $GLOBALS['wp_current_filter'] : array()) as $h) {
        if (preg_match('/^wc_ajax_[a-z0-9_]+_checkout_handler$/', (string) $h)) return true;  // validation only
        if ($h === 'wc_ajax_square_digital_wallet_process_checkout') return true;              // Apple / Google Pay
    }
    return false;
}
function af_checkout_attempt_key($ip) { return 'co|' . $ip; }
function af_checkout_paused() {
    $ip = function_exists('af_visitor_ip') ? af_visitor_ip() : '';
    if ($ip === '' || (function_exists('af_ip_is_cloudflare') && af_ip_is_cloudflare($ip))) return false;
    return function_exists('af_rl_over') && af_rl_over(af_checkout_attempt_key($ip), 8, HOUR_IN_SECONDS, false);
}
add_action('woocommerce_checkout_after_customer_details', function () {
    if (af_turnstile_checkout_on()) af_turnstile_box('checkout', true, true);
});
add_action('woocommerce_after_checkout_validation', function ($data, $errors) {
    if (!is_wp_error($errors) || !af_turnstile_checkout_on() || af_turnstile_checkout_skip_route()) return;
    if (af_checkout_paused()) {
        $errors->add('af_checkout_paused', __('Several payment attempts came from this connection in the last hour, so checkout is paused here for an hour to protect cardholders. Please try again later, or contact us and we will help you place the order.', 'postero-child'));
        return;
    }
    // not while anything else is wrong (field errors, or notices such as the
    // download licence): the token is kept for the attempt that can order
    if (is_user_logged_in() || $errors->has_errors() || (function_exists('wc_notice_count') && wc_notice_count('error') > 0)) return;
    if (!af_turnstile_verify('checkout')) $errors->add('af_turnstile', af_turnstile_message_checkout());
}, 20, 2);
// every attempt that reaches payment counts, whatever the gateway answers
add_action('woocommerce_checkout_order_processed', function () {
    if (!af_turnstile_checkout_on()) return;
    $ip = function_exists('af_visitor_ip') ? af_visitor_ip() : '';
    if ($ip === '' || (function_exists('af_ip_is_cloudflare') && af_ip_is_cloudflare($ip)) || !function_exists('af_rl_over')) return;
    af_rl_over(af_checkout_attempt_key($ip), 8, HOUR_IN_SECONDS);
});

// WooCommerce's REST checkout (/wp-json/wc/store/v1/checkout). The shop uses
// the classic checkout and no order has ever come this way (22 Oct), so a
// guest must send a passed token in an X-AF-Turnstile header, which today
// nothing does: the second door to the same payment is closed to bots.
add_filter('rest_pre_dispatch', function ($result, $server, $request) {
    if ($result !== null || !af_turnstile_checkout_on() || is_user_logged_in()) return $result;
    if (!in_array($request->get_method(), array('POST', 'PUT', 'PATCH'), true)) return $result;
    if (!preg_match('#^/wc/store(?:/v\d+)?/checkout(?:/|$)#', (string) $request->get_route())) return $result;
    $tok = trim((string) $request->get_header('x_af_turnstile'));
    if ($tok !== '') {
        $_POST['cf-turnstile-response'] = $tok;
        if (af_turnstile_verify('checkout')) return $result;
    }
    return new WP_Error('af_turnstile', af_turnstile_message(false), array('status' => 403));
}, 10, 3);

/* ---- order tracking (/track-your-order/) ----
 * Order number + billing email shows an order's items and addresses, and
 * order numbers run in sequence. Guests pass the check; everyone gets 20
 * lookups an hour per address. On a refusal the form comes back with what
 * was typed and the reason, and no order is looked up.
 */
add_action('woocommerce_order_tracking_form', function () { af_turnstile_box('track'); });
add_filter('pre_do_shortcode_tag', function ($out, $tag, $attr) {
    if ($out !== false || $tag !== 'woocommerce_order_tracking' || !isset($_REQUEST['orderid'])) return $out;
    $ip = function_exists('af_visitor_ip') ? af_visitor_ip() : '';
    $why = '';
    if (function_exists('af_rl_over') && $ip !== '' && af_rl_over('track|' . $ip, 20, HOUR_IN_SECONDS)) {
        $why = __('Too many order lookups from this connection. Please try again in an hour, or contact us.', 'postero-child');
    } elseif (af_turnstile_active() && !is_user_logged_in() && !af_turnstile_verify('track')) {
        $why = af_turnstile_message(false);
    }
    if ($why === '') return $out;
    // without its nonce WooCommerce shows the form again, filled in, and looks nothing up
    unset($_REQUEST['woocommerce-order-tracking-nonce'], $_POST['woocommerce-order-tracking-nonce'], $_GET['woocommerce-order-tracking-nonce'], $_REQUEST['_wpnonce'], $_POST['_wpnonce'], $_GET['_wpnonce']);
    ob_start();
    wc_print_notice(esc_html($why), 'error');
    echo WC_Shortcodes::order_tracking((array) $attr);
    return ob_get_clean();
}, 10, 3);

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
      . '.af-ts.af-ts-quiet{min-height:0;margin:0}'
      . '.af-ts.af-ts-quiet:not(.af-ts-ready){border:0;background:none}'
      . '.af-ts.af-ts-quiet.af-ts-shown{margin:8px 0 0}'
      . '.af-f-newsform{flex-wrap:wrap}.af-f-newsform .af-ts{flex:0 0 100%}'
      // comment and review forms: the theme floats / inlines the form's children, and an empty floated box is 0 px wide
      . '.comment-form .af-ts,#commentform .af-ts{display:block;float:none;clear:both;width:100%;max-width:100%;box-sizing:border-box}'
      . '.af-ts iframe{max-width:100%}'
      . '.af-ts-note{margin:6px 0 0;font-size:13px;line-height:1.45;color:#555}'
      . '.af-ts-note:empty{display:none}'
      . '.af-ts-note.af-ts-bad{color:#b32d2e}'
      . '#login form .af-ts{margin:6px 0 16px}'
      // checkout's email capture: its own check, off-screen; skipped when Cloudflare would ask for a tick
      . '.af-ts-offscreen{position:absolute!important;left:-10000px!important;top:auto!important;width:300px!important;height:1px!important;overflow:hidden!important}'
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

// Settings → Cloudflare Turnstile: the keys and the switch, for the owner.
require_once __DIR__ . '/turnstile-admin.php';
