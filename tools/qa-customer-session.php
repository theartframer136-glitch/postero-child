<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * A throwaway customer account for the logged-in journey test
 * (tools/qa-customer.mjs), and its removal afterwards.
 *
 *   AF_QA_ACTION=create  wp eval-file tools/qa-customer-session.php
 *       Makes (or reuses) the customer qa-customer-test, email
 *       qa-customer-test@example.com, role customer, and prints a signed-in
 *       session for it as one line: @@SESSION@@{"name":..,"value":..}@@END@@.
 *       The session lasts two hours. Signing in this way stands in for the
 *       Cloudflare Turnstile box on the login form, which only a person can
 *       tick; everything after sign-in is the real site.
 *
 *   AF_QA_ACTION=delete  wp eval-file tools/qa-customer-session.php
 *       Deletes that account, its sessions, its saved cart and its wishlist.
 *       Touches nothing else: it refuses unless the account's email is the
 *       reserved example.com address, and refuses an account with orders.
 *
 * example.com is reserved for documentation (RFC 2606), so nothing sent to the
 * address can reach a person.
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }

const AF_QA_LOGIN = 'qa-customer-test';
const AF_QA_EMAIL = 'qa-customer-test@example.com';

$action = getenv('AF_QA_ACTION') ?: '';
$user   = get_user_by('login', AF_QA_LOGIN);

if ($action === 'create') {
    if ($user && $user->user_email !== AF_QA_EMAIL) {
        echo "REFUSED: an account called " . AF_QA_LOGIN . " exists with another email\n";
        exit(1);
    }
    if (!$user) {
        $id = wp_insert_user(array(
            'user_login'   => AF_QA_LOGIN,
            'user_email'   => AF_QA_EMAIL,
            'user_pass'    => wp_generate_password(32, true, true),
            'first_name'   => 'QA',
            'last_name'    => 'Customer Test',
            'display_name' => 'QA Customer Test',
            'role'         => 'customer',
        ));
        if (is_wp_error($id)) { echo 'REFUSED: ' . $id->get_error_message() . "\n"; exit(1); }
        $user = get_user_by('id', $id);
        echo "created customer #{$id}\n";
    } else {
        echo "reusing customer #{$user->ID}\n";
    }
    $exp   = time() + 2 * HOUR_IN_SECONDS;
    $token = WP_Session_Tokens::get_instance($user->ID)->create($exp);
    $value = wp_generate_auth_cookie($user->ID, $exp, 'logged_in', $token);
    echo '@@SESSION@@' . wp_json_encode(array(
        'name'    => LOGGED_IN_COOKIE,
        'value'   => $value,
        'domain'  => wp_parse_url(home_url(), PHP_URL_HOST),
        'path'    => COOKIEPATH ?: '/',
        'expires' => $exp,
        'user_id' => $user->ID,
    )) . "@@END@@\n";
    exit(0);
}

if ($action === 'delete') {
    if (!$user) { echo "nothing to delete\n"; exit(0); }
    if ($user->user_email !== AF_QA_EMAIL) { echo "REFUSED: email does not match, left alone\n"; exit(1); }
    if (function_exists('wc_get_orders') && wc_get_orders(array('customer_id' => $user->ID, 'limit' => 1, 'return' => 'ids'))) {
        echo "REFUSED: the test account has orders, left alone for a person to look at\n";
        exit(1);
    }
    WP_Session_Tokens::get_instance($user->ID)->destroy_all();
    global $wpdb;
    if (isset($wpdb->prefix)) {
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}woocommerce_sessions WHERE session_key = %s", (string) $user->ID));
    }
    require_once ABSPATH . 'wp-admin/includes/user.php';
    $ok = wp_delete_user($user->ID);
    echo $ok ? "deleted customer #{$user->ID}\n" : "delete failed for #{$user->ID}\n";
    exit($ok ? 0 : 1);
}

echo "Set AF_QA_ACTION=create or AF_QA_ACTION=delete\n";
exit(1);
