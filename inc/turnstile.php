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
function af_turnstile_box($action = 'login', $echo = true, $quiet = false, $on_use = null) {
    if (!af_turnstile_active() || af_turnstile_in_editor()) return '';
    // Logged-in customers are never asked (checkout aside: accounts that have
    // never paid are, see af_checkout_needs_check), as the settings page says, and
    // the script is not loaded for them. wp-login.php is the exception: it
    // loads the script for everyone and checks every login posted to it.
    if (is_user_logged_in() && (isset($GLOBALS['pagenow']) ? $GLOBALS['pagenow'] : '') !== 'wp-login.php'
        && !(in_array($action, array('checkout', 'pay'), true) && function_exists('af_checkout_needs_check') && af_checkout_needs_check())) return '';
    $html = '<div class="af-ts' . ($quiet ? ' af-ts-quiet' : '') . '" data-action="' . esc_attr($action) . '"'
          . (($on_use === null ? $quiet : $on_use) ? ' data-on-use="1"' : '')
          . ($quiet ? ' data-appearance="interaction-only"' : '')
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
 * form.checkout, after the order summary and outside the parts WooCommerce
 * redraws on every change, so it survives address and shipping updates.
 *
 * Checked once per real attempt, in WooCommerce's own validation and only
 * when every field is fine: a shopper fixing a typo keeps the same token.
 * (Square's card form posts the form to its own checkout_handler only to
 * show errors after a card could not be read; that request always ends at
 * priority 10, before this check, so it never spends the token.)
 *
 * Square's Apple Pay / Google Pay route (wc-ajax=square_digital_wallet_
 * process_checkout) runs the same checkout on its own list of fields, and
 * the route is open whatever Square's wallet settings say. There: card only
 * (what Square's wallet script always sends; no cash-on-delivery, Zelle or
 * gift-card orders through it), a token checked when one is sent (the
 * script adds the wallet box's token), and posts without one allowed only
 * within a small hourly allowance for the whole shop, so a page from before
 * this change still works while a bot cannot use the route without end.
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
// Who is asked at checkout: guests, and signed-in accounts that have never
// paid (an account costs one check to make, and would then skip every check
// at checkout). Returning customers who have paid, and shop staff, are not.
function af_checkout_needs_check() {
    if (!is_user_logged_in()) return true;
    if (current_user_can('manage_woocommerce')) return false;
    return !get_user_meta(get_current_user_id(), 'paying_customer', true);
}
function af_turnstile_wallet_route() {
    return in_array('wc_ajax_square_digital_wallet_process_checkout', (array) (isset($GLOBALS['wp_current_filter']) ? $GLOBALS['wp_current_filter'] : array()), true);
}
function af_turnstile_posted_token() {
    return isset($_POST['cf-turnstile-response']) && is_string($_POST['cf-turnstile-response']) ? trim(wp_unslash($_POST['cf-turnstile-response'])) : '';
}
function af_checkout_paused_message() {
    return sprintf(
        /* translators: 1: "connection" or "account", 2: link to the contact page */
        __('Several card payments were tried from this %1$s in the last hour, so card payment is paused here for a while. You can choose another way to pay, try again later, or %2$s and we will help you place the order.', 'postero-child'),
        is_user_logged_in() ? __('account', 'postero-child') : __('connection', 'postero-child'),
        '<a href="' . esc_url(home_url('/contact/')) . '">' . esc_html__('contact us', 'postero-child') . '</a>'
    );
}
// One address for the per-address limits: IPv6 by its /64 (one machine is
// usually handed a whole /64), an IPv4 written as IPv6 as itself.
function af_rl_ip_group($ip) {
    $b = @inet_pton((string) $ip);
    if ($b === false || strlen($b) !== 16) return (string) $ip;
    if (substr($b, 0, 12) === str_repeat("\0", 10) . "\xff\xff") return inet_ntop(substr($b, 12));
    return inet_ntop(substr($b, 0, 8) . str_repeat("\0", 8)) . '/64';
}
// The brake's bucket: a signed-in customer by account (they skip the human
// check, and an account can move between addresses), a guest by address. A
// Cloudflare server connecting with no visitor behind it is not counted; a
// Cloudflare address given in the visitor header (a Worker) is.
function af_checkout_attempt_key($ip = null) {
    if (is_user_logged_in()) return 'co|u' . get_current_user_id();
    if ($ip === null) $ip = function_exists('af_visitor_ip') ? af_visitor_ip() : '';
    if ($ip === '') return '';
    $remote = isset($_SERVER['REMOTE_ADDR']) ? trim((string) $_SERVER['REMOTE_ADDR']) : '';
    if ($ip === $remote && function_exists('af_ip_is_cloudflare') && af_ip_is_cloudflare($ip)) return '';
    return 'co|' . af_rl_ip_group($ip);
}
// Card payments are what card testing uses; a paused buyer can still pay
// another way (Zelle, Cash App Pay, cash on delivery).
function af_checkout_is_card($method) { return (string) $method === 'square_credit_card'; }
function af_checkout_paused() {
    $k = af_checkout_attempt_key();
    return $k !== '' && function_exists('af_rl_over') && af_rl_over($k, 8, HOUR_IN_SECONDS, false);
}
// counts this attempt and answers whether it is past the limit, in one go
// (a separate read before the check would let parallel attempts all pass)
function af_checkout_brake_hit() {
    $k = af_checkout_attempt_key();
    return $k !== '' && function_exists('af_rl_over') && af_rl_over($k, 8, HOUR_IN_SECONDS);
}
// The visible "Verify you are human" box, as on the login and sign-up pages
// (owner's choice, 22 Oct), right under the Place order button: printed after
// the order summary, and moved by af-turnstile.js into the summary column
// (outside the parts WooCommerce redraws on every address change), where it
// is drawn as the page opens. (The script adds one there if a theme template
// has no such hook.)
add_action('woocommerce_checkout_after_order_review', function () {
    if (af_turnstile_checkout_on()) af_turnstile_box('checkout', true, false, true);
});
add_action('woocommerce_after_checkout_validation', function ($data, $errors) {
    if (!is_wp_error($errors) || !af_turnstile_checkout_on()) return;
    $method = isset($data['payment_method']) ? (string) $data['payment_method'] : '';
    $wallet = af_turnstile_wallet_route();
    if ($wallet && !af_checkout_is_card($method)) {
        $errors->add('af_wallet_card_only', __('Please place this order with the form on the checkout page.', 'postero-child'));
        return;
    }
    // not while anything else is wrong (field errors, or notices such as the
    // download licence): the token is kept for the attempt that can order,
    // and nothing is counted
    if ($errors->has_errors() || (function_exists('wc_notice_count') && wc_notice_count('error') > 0)) return;
    if (af_checkout_needs_check()) {
        if ($wallet) {
            if (af_turnstile_posted_token() !== '') {
                if (!af_turnstile_verify('checkout')) { $errors->add('af_turnstile', esc_html__('The security check did not go through. Please try Apple Pay or Google Pay again.', 'postero-child')); return; }
            } elseif (!is_user_logged_in() && (!empty($data['createaccount']) || (WC()->checkout() && WC()->checkout()->is_registration_required()))) {
                // an order that would make an account (a digital item) is never placed without the check
                $errors->add('af_wallet_card_only', __('Please place this order with the form on the checkout page.', 'postero-child'));
                return;
            } elseif (function_exists('af_rl_over') && af_rl_over('wallet-notoken', 20, HOUR_IN_SECONDS)) {
                $errors->add('af_wallet_busy', esc_html__('Apple Pay and Google Pay are paused here for a short while. Please pay with the form on the checkout page.', 'postero-child'));
                return;
            }
        } elseif (!af_turnstile_verify('checkout')) {
            $errors->add('af_turnstile', af_turnstile_message_checkout());
            return;
        }
    }
    // the brake: card attempts that go ahead to payment (WooCommerce's
    // "update totals" post never orders, so it is not one)
    if (af_checkout_is_card($method) && empty($data['woocommerce_checkout_update_totals']) && af_checkout_brake_hit()) {
        $errors->add('af_checkout_paused', af_checkout_paused_message());
    }
}, 20, 2);

// WooCommerce's REST checkout (/wp-json/wc/store/v1/checkout, and
// /checkout/{id}, which pays for an existing order). The shop uses the
// classic checkout and no order has ever come this way (22 Oct), so it is
// closed to everyone but shop managers: it was a second way to the same
// payment with no human check, and a GET there makes a draft order and hands
// back the key that pays for it. Recognised by the handler WordPress matched
// (WooCommerce's own Checkout / CheckoutOrder routes), so no spelling of the
// address (/Checkout, /CHECKOUT/12) and no /batch wrapper gets past it.
function af_is_store_checkout_handler($handler, $request) {
    $cb = isset($handler['callback']) ? $handler['callback'] : null;
    $obj = is_array($cb) && isset($cb[0]) && is_object($cb[0]) ? $cb[0] : null;
    foreach (array('Automattic\WooCommerce\StoreApi\Routes\V1\Checkout', 'Automattic\WooCommerce\StoreApi\Routes\V1\CheckoutOrder') as $c) {
        if ($obj && class_exists($c, false) && $obj instanceof $c) return true;
    }
    return (bool) preg_match('#^/wc/store(?:/v\d+)?/checkout(?:/|$)#i', (string) $request->get_route());
}
add_filter('rest_request_before_callbacks', function ($response, $handler, $request) {
    if (!af_turnstile_checkout_on() || !af_is_store_checkout_handler($handler, $request) || current_user_can('manage_woocommerce')) return $response;
    return new WP_Error('af_checkout_route_closed', __('This shop takes orders on its checkout page.', 'postero-child'), array('status' => 403));
}, 10, 3);

/* ---- the "pay for this order" page (/checkout/order-pay/{id}/) ----
 * WooCommerce takes the payment there directly (WC_Form_Handler::pay_action),
 * with none of checkout's hooks, so a failed order's link would otherwise be
 * a way to try card after card. The same rules, on its own: the brake by
 * address, at most 6 attempts an hour on one order whatever the address,
 * and Cloudflare's check for guests. A refusal is a notice: WooCommerce then
 * takes no payment and shows the page again with it.
 */
function af_turnstile_message_pay() {
    $missing = isset($GLOBALS['af_ts_reason']) && $GLOBALS['af_ts_reason'] === 'missing';
    return $missing
        ? __('The security check had not finished yet. Please wait a moment and press Pay for order again. If this keeps happening, your browser may be blocking challenges.cloudflare.com: allow it, or try another browser.', 'postero-child')
        : __('The security check did not go through. Please press Pay for order again.', 'postero-child');
}
add_action('woocommerce_pay_order_before_submit', function () {
    if (af_turnstile_checkout_on()) af_turnstile_box('pay', true, false, true);
});
add_action('woocommerce_before_pay_action', function ($order) {
    if (!af_turnstile_checkout_on() || !($order instanceof WC_Order)) return;
    if (!empty($_POST['terms-field']) && empty($_POST['terms'])) return; // WooCommerce stops this one itself
    $per_order = 'op|' . $order->get_id();
    $card = af_checkout_is_card(isset($_POST['payment_method']) ? wc_clean(wp_unslash($_POST['payment_method'])) : '');
    if (!function_exists('af_rl_over')) return;
    if (af_rl_over($per_order, 6, HOUR_IN_SECONDS, false)) {
        wc_add_notice(__('Several payments were tried for this order in the last hour, so it is paused for a while. Please try again later, or contact us and we will help.', 'postero-child'), 'error');
        return;
    }
    if (af_checkout_needs_check() && !af_turnstile_verify('pay')) {
        wc_add_notice(esc_html(af_turnstile_message_pay()), 'error');
        return;
    }
    if ($card && af_checkout_brake_hit()) {
        wc_add_notice(af_checkout_paused_message(), 'error');
        return;
    }
    af_rl_over($per_order, 6, HOUR_IN_SECONDS);
});

/* ---- My Account > Add payment method ----
 * With saved cards on, a signed-in account could have a card checked here
 * every 20 seconds (WooCommerce's own spacing) with no order at all. The
 * same card brake as checkout: 8 an hour per account.
 */
add_filter('woocommerce_add_payment_method_form_is_valid', function ($ok) {
    if (!$ok || !af_turnstile_checkout_on()) return $ok;
    if (af_checkout_brake_hit()) { wc_add_notice(af_checkout_paused_message(), 'error'); return false; }
    return $ok;
});

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
    if (function_exists('af_rl_over') && $ip !== '' && af_rl_over('track|' . af_rl_ip_group($ip), 20, HOUR_IN_SECONDS)) {
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
    wp_enqueue_script('af-turnstile', get_stylesheet_directory_uri() . '/assets/js/af-turnstile.js', array('jquery'), (string) (@filemtime($file) ?: '1'), true);
    $k = af_turnstile_keys();
    $lang = function_exists('transposh_get_current_language') ? (string) transposh_get_current_language() : '';
    wp_add_inline_script('af-turnstile', 'window.afTs=' . wp_json_encode(array(
        'sitekey' => $k['site'],
        'lang'    => ($lang === '' || $lang === 'en') ? 'auto' : $lang,
        'checkout' => af_turnstile_checkout_on(),
        'msg'     => array(
            'wait'    => __('One moment — finishing the security check…', 'postero-child'),
            'tick'    => __('Please tick the security check to continue.', 'postero-child'),
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
    if (!af_turnstile_active() || af_turnstile_in_editor()) return;
    // signed in: only an account still asked at checkout, and only where it
    // pays (checkout, the pay-for-order page, and the Apple / Google Pay buttons)
    if (is_user_logged_in() && !(af_turnstile_checkout_on() && af_checkout_needs_check()
        && ((function_exists('is_checkout') && is_checkout()) || (function_exists('is_cart') && is_cart()) || (function_exists('is_product') && is_product())))) return;
    af_turnstile_enqueue();
}, 20);
add_action('login_enqueue_scripts', function () {
    if (af_turnstile_active()) af_turnstile_enqueue();
});

// Settings → Cloudflare Turnstile: the keys and the switch, for the owner.
require_once __DIR__ . '/turnstile-admin.php';
