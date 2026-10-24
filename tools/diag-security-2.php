<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * READ-ONLY follow-up to diag-security.php: what the unexpected files in
 * the document root do and whether the web can reach them, how wp-cron.php
 * differs from the official copy, the debug constants, and which published
 * content embeds an external script. Prints no secrets, changes nothing.
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
global $wpdb; $root = rtrim(ABSPATH, '/');

echo "=== UNEXPECTED FILES IN THE DOCUMENT ROOT\n";
foreach (glob($root . '/create_autologin_*.php') + array($root . '/setup-ssh-key.php', $root . '/.htaccess.bk') as $f) {
    if (!is_file($f)) continue;
    $c = (string) file_get_contents($f);
    printf("  %s | %d bytes | modified %s | perms %s | owner uid %d\n", basename($f), strlen($c), gmdate('Y-m-d H:i', filemtime($f)), substr(sprintf('%o', fileperms($f)), -4), fileowner($f));
    $flags = array();
    foreach (array('wp_set_auth_cookie', 'wp_signon', 'authorized_keys', 'file_put_contents', 'fopen', 'exec(', 'shell_exec', 'system(', 'passthru', 'eval(', 'base64_decode', 'unlink(__FILE__', '$_GET', '$_POST', 'hash_equals', 'token', 'secret', 'wp_create_user', 'set_role', 'mkdir') as $k) if (stripos($c, $k) !== false) $flags[] = $k;
    echo "    contains: " . implode(', ', $flags) . "\n";
    // first lines, with anything that looks like a key or hash blanked
    $head = preg_replace('/[A-Za-z0-9+\/=_-]{24,}/', '[blanked]', implode("\n", array_slice(preg_split('/\r?\n/', $c), 0, 25)));
    echo "    ---- first lines ----\n" . preg_replace('/^/m', '    | ', mb_strimwidth($head, 0, 1600, '…')) . "\n";
    $url = home_url('/' . basename($f));
    $r = wp_remote_head($url, array('timeout' => 20, 'sslverify' => true, 'redirection' => 0, 'headers' => array('User-Agent' => 'af-diag/1.0')));
    echo "    reachable from the web: " . (is_wp_error($r) ? 'ERR ' . $r->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code($r)) . "\n";
}

echo "\n=== wp-cron.php VERSUS THE OFFICIAL COPY\n";
$local = (string) file_get_contents($root . '/wp-cron.php');
$r = wp_remote_get('https://raw.githubusercontent.com/WordPress/WordPress/' . get_bloginfo('version') . '/wp-cron.php', array('timeout' => 30));
if (is_wp_error($r) || wp_remote_retrieve_response_code($r) != 200) echo "  could not fetch the official copy (" . (is_wp_error($r) ? $r->get_error_message() : wp_remote_retrieve_response_code($r)) . ")\n";
else {
    $off = (string) wp_remote_retrieve_body($r);
    $a = preg_split('/\r?\n/', $off); $b = preg_split('/\r?\n/', $local);
    $added = array_values(array_diff($b, $a)); $removed = array_values(array_diff($a, $b));
    echo "  official " . strlen($off) . " bytes, live " . strlen($local) . " bytes | lines added: " . count($added) . " | lines removed: " . count($removed) . "\n";
    foreach (array_slice($added, 0, 25) as $l) echo "    + " . mb_strimwidth($l, 0, 140, '…') . "\n";
    foreach (array_slice($removed, 0, 10) as $l) echo "    - " . mb_strimwidth($l, 0, 140, '…') . "\n";
}
echo "  wp-cron.php reachable: " . (($r2 = wp_remote_head(home_url('/wp-cron.php'), array('timeout' => 20, 'sslverify' => true))) && !is_wp_error($r2) ? 'HTTP ' . wp_remote_retrieve_response_code($r2) : 'ERR') . " | DISABLE_WP_CRON=" . (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON ? 'true' : 'false') . "\n";

echo "\n=== DEBUG CONSTANTS\n";
foreach (array('WP_DEBUG', 'WP_DEBUG_DISPLAY', 'WP_DEBUG_LOG', 'SCRIPT_DEBUG', 'WP_ENVIRONMENT_TYPE', 'FORCE_SSL_ADMIN', 'DISALLOW_UNFILTERED_HTML') as $k) echo "  $k = " . (defined($k) ? var_export(constant($k), true) : '(undefined)') . "\n";
echo "  wp-config.php sets WP_DEBUG: " . (preg_match("/define\(\s*'WP_DEBUG'\s*,\s*([^)]+)\)/", (string) file_get_contents($root . '/wp-config.php'), $m) ? trim($m[1]) : '(not in wp-config)') . "\n";
echo "  debug.log present: " . (file_exists(WP_CONTENT_DIR . '/debug.log') ? filesize(WP_CONTENT_DIR . '/debug.log') . ' bytes' : 'no') . "\n";

echo "\n=== PUBLISHED CONTENT EMBEDDING AN EXTERNAL SCRIPT OR IFRAME\n";
$rows = $wpdb->get_results("SELECT ID, post_type, post_title, post_content FROM {$wpdb->posts} WHERE post_status='publish' AND post_type IN ('post','page','product') AND (post_content LIKE '%<script src=\"http%' OR post_content LIKE '%<iframe src=\"http%' OR post_content LIKE '%eval(%') LIMIT 10");
foreach ($rows as $p) { preg_match_all('#<(script|iframe)[^>]+src="([^"]+)"#i', $p->post_content, $mm); echo "  #{$p->ID} {$p->post_type} '" . mb_strimwidth($p->post_title, 0, 50, '…') . "': " . implode(', ', array_map(function ($u) { return preg_replace('#^(https?://[^/]+).*#', '$1', $u); }, array_unique($mm[2]))) . (strpos($p->post_content, 'eval(') !== false ? ' + eval(' : '') . "\n"; }
if (!$rows) echo "  none\n";

echo "\n=== REGISTRATION\n";
echo "  users_can_register=" . get_option('users_can_register') . " | woocommerce_enable_myaccount_registration=" . get_option('woocommerce_enable_myaccount_registration') . " | woocommerce_enable_signup_and_login_from_checkout=" . get_option('woocommerce_enable_signup_and_login_from_checkout') . " | woocommerce_enable_guest_checkout=" . get_option('woocommerce_enable_guest_checkout') . "\n";
echo "  subscribers: " . count_users()['avail_roles']['subscriber'] ?? 0; echo " | customers: " . (count_users()['avail_roles']['customer'] ?? 0) . "\n";
$r = wp_remote_get(home_url('/wp-login.php?action=register'), array('timeout' => 20, 'sslverify' => true, 'redirection' => 0)); echo "  wp-login.php?action=register: HTTP " . (is_wp_error($r) ? 'ERR' : wp_remote_retrieve_response_code($r)) . "\n";
echo "\n=== DONE ===\n";
