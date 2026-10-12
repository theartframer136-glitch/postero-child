<?php
/**
 * Security audit (12 Oct), second batch: the medium findings that need a
 * place of their own rather than an edit where the code already lives.
 *
 * 1. A SHARED RATE LIMIT. af_rl_over($bucket, $limit, $window) counts hits in
 *    a fixed window and says when the limit is reached. Used by the gift
 *    card, reel tracking and return-request handlers in functions.php and
 *    inc/reels.php, and by the login throttle below.
 *
 * 2. LOGIN THROTTLING. Ten failed sign-ins for one address + one user name in
 *    15 minutes, or thirty from one address for any names, block that pair
 *    (or that address) for 15 minutes. The visitor sees why and what to do.
 *    It sits on WordPress's own authenticate filter and wp_login_failed
 *    action, so it covers every login form at once: wp-login.php, My Account
 *    and checkout (WooCommerce), /login/ (the Login | Register widget) and
 *    the header popup (parent theme), all of which end in wp_signon() or
 *    wp_authenticate(). A failed Turnstile check is not a password guess and
 *    is not counted. While blocked, even the right password is refused, or
 *    the block would tell an attacker when they had guessed it. The address
 *    is af_visitor_ip(), which nobody can set with a header.
 *
 * 3. APPLICATION PASSWORDS only for administrators. They were available to
 *    every account; the owner's upload scripts use an administrator's.
 *
 * 4. THE FIVE SECURITY HEADERS on wp-login.php, admin-ajax.php and REST
 *    answers, which never reach the send_headers hook that the pages use
 *    (af_send_security_headers() in functions.php).
 *
 * 5. ARRAY QUERY PARAMETERS. ?product_tag[]=x and ?filter_colors[]=x gave
 *    HTTP 500, because the theme passes them to functions that take a
 *    string. Every parameter in the list below is a single value wherever
 *    the site uses it, so an array value is dropped before anything reads it.
 */
if (!defined('ABSPATH')) exit;

/* ── 1. rate limit ──────────────────────────────────────────────────── */

/**
 * Count one hit for $bucket and say whether its limit was already reached.
 * $limit hits are allowed in $window seconds, counted from the first hit.
 * $hit = false only asks. Returns true when the caller should refuse.
 */
function af_rl_over($bucket, $limit, $window, $hit = true) {
    $k   = 'af_rl_' . md5((string) $bucket);
    $now = time();
    $v   = get_transient($k);
    if (!is_array($v) || count($v) !== 2 || (int) $v[1] + (int) $window <= $now) $v = array(0, $now);
    if ((int) $v[0] >= (int) $limit) return true;
    if ($hit) {
        $v[0] = (int) $v[0] + 1;
        set_transient($k, $v, max(60, (int) $v[1] + (int) $window - $now));
    }
    return false;
}

/* ── 2. login throttling ────────────────────────────────────────────── */

define('AF_LOGIN_WINDOW', 15 * MINUTE_IN_SECONDS);
define('AF_LOGIN_PER_NAME', 10);
define('AF_LOGIN_PER_IP', 30);

// One key per account however it was typed: an email address that belongs to
// an account counts against that account's login name.
function af_login_name_key($username) {
    $u = strtolower(trim((string) $username));
    if ($u !== '' && is_email($u)) {
        $user = get_user_by('email', $u);
        if ($user) $u = strtolower($user->user_login);
    }
    return $u;
}

function af_login_block_keys($username) {
    $ip = function_exists('af_visitor_ip') ? af_visitor_ip() : '';
    return array(
        'name' => 'af_lb_n_' . md5($ip . '|' . af_login_name_key($username)),
        'ip'   => 'af_lb_i_' . md5($ip),
        'ipv'  => $ip,
    );
}

// Whether $ip is one of Cloudflare's own addresses. af_visitor_ip() hands one
// back only if a request came through Cloudflare without the visitor's
// address header, or Cloudflare's address list in functions.php is out of
// date. Then every visitor behind that edge shares the address, and an
// address-wide limit would stop all of them at once, so it steps aside; the
// limit per address + account still applies.
function af_ip_is_cloudflare($ip) {
    if ($ip === '' || !function_exists('af_cloudflare_ranges') || !function_exists('af_ip_in_cidr')) return false;
    foreach (af_cloudflare_ranges() as $range) {
        if (af_ip_in_cidr($ip, $range)) return true;
    }
    return false;
}

function af_login_is_blocked($username) {
    $k = af_login_block_keys($username);
    if ($k['ipv'] === '') return false;
    if (!af_ip_is_cloudflare($k['ipv']) && get_transient($k['ip'])) return true;
    return (bool) get_transient($k['name']);
}

function af_login_block_message() {
    return '<span class="af-login-msg"><strong>Too many failed sign-in attempts.</strong> '
         . 'For your security, signing in from this connection is paused for 15 minutes. '
         . 'If you have forgotten your password, use “Lost your password?” to reset it.</span>';
}

// Late, after WordPress has checked the password (20) and the cookie (30),
// so nothing after it can overwrite the refusal.
add_filter('authenticate', function ($user, $username = '', $password = '') {
    if (trim((string) $username) === '') return $user; // nothing was submitted
    if (af_login_is_blocked($username)) {
        return new WP_Error('af_login_blocked', af_login_block_message());
    }
    return $user;
}, 999, 3);

add_action('wp_login_failed', function ($username, $error = null) {
    if ($error instanceof WP_Error) {
        $skip = array('af_login_blocked', 'af_turnstile', 'empty_username', 'empty_password');
        if (array_intersect($error->get_error_codes(), $skip)) return;
    }
    if (trim((string) $username) === '') return;
    $k = af_login_block_keys($username);
    if ($k['ipv'] === '') return;
    // A failure is counted; the hit that would pass the limit starts a block.
    if (af_rl_over('login-n|' . $k['name'], AF_LOGIN_PER_NAME - 1, AF_LOGIN_WINDOW)) {
        set_transient($k['name'], 1, AF_LOGIN_WINDOW);
    }
    if (!af_ip_is_cloudflare($k['ipv']) && af_rl_over('login-i|' . $k['ip'], AF_LOGIN_PER_IP - 1, AF_LOGIN_WINDOW)) {
        set_transient($k['ip'], 1, AF_LOGIN_WINDOW);
    }
}, 10, 2);

// A successful sign-in starts that account's count again, so a few typos
// earlier in the window do not carry over.
add_action('wp_login', function ($user_login) {
    $k = af_login_block_keys($user_login);
    if ($k['ipv'] !== '') delete_transient('af_rl_' . md5('login-n|' . $k['name']));
}, 10, 1);

/* ── 3. application passwords: administrators only ──────────────────── */

add_filter('wp_is_application_passwords_available_for_user', function ($available, $user) {
    return $available && $user instanceof WP_User && user_can($user, 'manage_options');
}, 10, 2);

/* ── 4. security headers outside the page hook ─────────────────────── */

add_action('login_init', function () {
    if (function_exists('af_send_security_headers')) af_send_security_headers();
});
// admin-ajax.php answers some requests (no action given) before admin_init,
// so the headers go out as soon as WordPress has loaded.
add_action('wp_loaded', function () {
    if (wp_doing_ajax() && function_exists('af_send_security_headers')) af_send_security_headers();
});
add_filter('rest_pre_serve_request', function ($served) {
    if (function_exists('af_send_security_headers')) af_send_security_headers();
    return $served;
});

/* ── 5. single-value query parameters ──────────────────────────────── */

if (!is_admin()) {
    foreach (array(
        'product_tag', 'product_cat', 'filter_colors', 'filter_size', 'filter_frame', 'orientation',
        'orderby', 'order', 'min_price', 'max_price', 'currency', 's', 'afsearch', 'paged', 'bpage',
        'topic', 'slug', 'pid', 'key', 'af_qv', 'af_restore', 'af_doc', 'af_digital', 'list',
        'af_feed', 'channel', 'af_ac_stop', 'print', 'p', 'id',
    ) as $af_sh_k) {
        if (isset($_GET[$af_sh_k]) && !is_scalar($_GET[$af_sh_k])) {
            unset($_GET[$af_sh_k]);
            if (isset($_REQUEST[$af_sh_k]) && !is_scalar($_REQUEST[$af_sh_k])) unset($_REQUEST[$af_sh_k]);
        }
    }
    unset($af_sh_k);
}
