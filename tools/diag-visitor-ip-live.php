<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only (one counter touched). Does PHP see each visitor's own address
 * behind the CDN in front of the site, or the CDN's?
 *
 * The per-address limits (logins, contact form, gift-card guesses, and from
 * 22 Oct the checkout brake: 8 attempts an hour, and order tracking: 20 an
 * hour) count af_visitor_ip(). If that were a CDN server's address, every
 * visitor arriving through it would share one count, and 8 orders an hour
 * across the whole shop would pause checkout for everyone.
 *
 *  1. This server's own public addresses (IPv4 and IPv6), as outside
 *     services see them.
 *  2. For each: one request from this server to the public site, forced over
 *     that address family, through its CDN: a made-up gift card code, which
 *     only adds 1 to the gift-card guess count of the address PHP sees (10 an
 *     hour; nothing else is written). The response headers show the CDN.
 *  3. Was that count written under this server's own address? Then PHP sees
 *     the visitor's own address behind the CDN.
 *  4. The web server's access log line for the probe, if one is readable.
 *  5. Past orders: WooCommerce's recorded address against the theme's
 *     (how many agree; no address is printed, only the first two parts).
 *
 *   wp eval-file tools/diag-visitor-ip-live.php
 */
$mask = function ($ip) { $p = preg_split('/[.:]/', (string) $ip); return $ip === '' ? '(none)' : $p[0] . (isset($p[1]) ? '.' . $p[1] : '') . '.x.x'; };

echo "=== 1. this server's public addresses\n";
$fam = array('v4' => '', 'v6' => '');
foreach (array('v4' => array('https://api.ipify.org', 'https://ipv4.icanhazip.com'), 'v6' => array('https://api6.ipify.org', 'https://ipv6.icanhazip.com')) as $f => $urls) {
    foreach ($urls as $u) {
        $r = wp_remote_get($u, array('timeout' => 15));
        $v = is_wp_error($r) ? '' : trim(wp_remote_retrieve_body($r));
        if (filter_var($v, FILTER_VALIDATE_IP)) { $fam[$f] = $v; break; }
    }
    echo "  IP{$f}: " . ($fam[$f] !== '' ? $mask($fam[$f]) : 'none') . "\n";
}
if ($fam['v4'] === '' && $fam['v6'] === '') { echo "  could not learn this server's public address\n=== END\n"; return; }

// the request goes out over one address family at a time
$force = null;
add_action('http_api_curl', function ($h) use (&$force) {
    if ($force === 'v4' && defined('CURL_IPRESOLVE_V4')) curl_setopt($h, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    if ($force === 'v6' && defined('CURL_IPRESOLVE_V6')) curl_setopt($h, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V6);
});
$count = function ($ip) {
    if ($ip === '') return -1;
    $k = 'af_rl_' . md5('gc|' . $ip);
    wp_cache_delete($k, 'transient'); wp_cache_delete('_transient_' . $k, 'options'); wp_cache_delete('alloptions', 'options');
    $v = get_transient($k);
    return is_array($v) ? (int) $v[0] : 0;
};
wp_set_current_user(0);
$nonce = wp_create_nonce('af_gc_apply');
$ua = 'Mozilla/5.0 (af visitor-ip probe ' . substr(md5((string) microtime(true)), 0, 8) . ')';
$verdicts = array();
foreach (array('v4', 'v6') as $f) {
    if ($fam[$f] === '') continue;
    echo "\n=== 2/3. one request over IP{$f}, through the public site\n";
    $b4 = $count($fam['v4']); $b6 = $count($fam['v6']);
    $force = $f;
    $r = wp_remote_post(admin_url('admin-ajax.php'), array('timeout' => 30, 'headers' => array('User-Agent' => $ua, 'Cache-Control' => 'no-cache'), 'body' => array('action' => 'af_gc_apply', 'nonce' => $nonce, 'code' => 'TAF-QAQA-QAQA-QAQA')));
    $force = null;
    if (is_wp_error($r)) { echo '  request failed: ' . $r->get_error_message() . "\n"; continue; }
    $h = wp_remote_retrieve_headers($r);
    $cdn = !empty($h['x-hcdn-request-id']) || !empty($h['cf-ray']) || (isset($h['server']) && preg_match('/hcdn|cloudflare/i', (string) $h['server']));
    echo '  HTTP ' . wp_remote_retrieve_response_code($r) . ' | through a CDN: ' . ($cdn ? 'yes (' . (isset($h['server']) ? $h['server'] : '') . ')' : 'NO') . ' | answer: ' . substr(preg_replace('/\s+/', ' ', wp_remote_retrieve_body($r)), 0, 70) . "\n";
    $a4 = $count($fam['v4']); $a6 = $count($fam['v6']);
    echo "  count under this server's IPv4: {$b4} -> {$a4} | under its IPv6: {$b6} -> {$a6}\n";
    $hit = ($a4 > $b4 && $a4 >= 0) || ($a6 > $b6 && $a6 >= 0);
    $verdicts[] = $hit;
    echo '  RESULT: ' . ($hit ? 'PHP counted this server\'s own address: the visitor\'s address reaches PHP through the CDN.' : 'the count is under NEITHER of this server\'s addresses: PHP saw another address (the CDN\'s?).') . "\n";
}

echo "\n=== 4. the web server's access log for the probe\n";
$home = getenv('HOME') ?: (function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['dir'] : '');
$found = 0;
foreach (array_merge((array) glob($home . '/logs/*'), (array) glob($home . '/.logs/*'), (array) glob($home . '/websites/*/logs/*'), (array) glob($home . '/domains/*/logs/*'), (array) glob(ABSPATH . '../logs/*'), (array) glob($home . '/access-logs/*')) as $lf) {
    if (!is_file($lf) || !is_readable($lf) || filesize($lf) > 400 * 1048576) continue;
    $tail = @file_get_contents($lf, false, null, max(0, filesize($lf) - 2 * 1048576));
    if ($tail === false) continue;
    foreach (preg_split('/\n/', $tail) as $line) {
        if (strpos($line, $ua) === false) continue;
        $found++;
        $first = strtok($line, ' ');
        echo '  ' . basename($lf) . ': client field ' . $mask($first) . ' | is this server: ' . (in_array($first, $fam, true) ? 'yes' : 'no') . "\n";
    }
}
if (!$found) echo "  no access log line for the probe found (looked in ~/logs, ~/.logs, ~/websites/*/logs, ~/domains/*/logs)\n";
$ls = array(); foreach (array($home . '/logs', $home . '/.logs', $home . '/access-logs') as $d) if (is_dir($d)) $ls[] = $d . ': ' . implode(', ', array_slice(array_map('basename', (array) glob($d . '/*')), 0, 8));
if ($ls) echo '  log folders: ' . implode(' | ', $ls) . "\n";

echo "\n=== 4b. can this script see counters web requests write?\n";
global $wpdb;
echo '  persistent object cache: ' . (wp_using_ext_object_cache() ? 'YES (' . (defined('LSCWP_OBJECT_CACHE') ? 'LiteSpeed' : 'other') . ')' : 'no (counters live in the options table)') . "\n";
$recent = 0; $rows = $wpdb->get_results("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_af\\_rl\\_%' ORDER BY option_id DESC LIMIT 400");
foreach ($rows as $r) { $v = maybe_unserialize($r->option_value); if (is_array($v) && isset($v[1]) && (int) $v[1] > time() - 300) $recent++; }
echo '  per-address counters in the options table: ' . count($rows) . ' | started in the last 5 minutes: ' . $recent . "\n";

echo "\n=== 6. the addresses PHP recorded for real visitors (first two parts only)\n";
$tally = function ($list) use ($mask) { $c = array(); foreach ($list as $ip) { if ($ip === '' || $ip === null) continue; $k = $mask($ip); $c[$k] = ($c[$k] ?? 0) + 1; } arsort($c); return $c; };
$cips = $wpdb->get_col("SELECT comment_author_IP FROM {$wpdb->comments} WHERE comment_type IN ('comment','review','') AND user_id = 0 ORDER BY comment_ID DESC LIMIT 60");
$t = $tally($cips);
echo '  guest comments and reviews (REMOTE_ADDR as WordPress saw it): ' . count($cips) . ' | distinct prefixes: ' . count($t) . ' | ' . substr(wp_json_encode(array_slice($t, 0, 8)), 0, 300) . "\n";
$srv = array(); foreach ($fam as $f => $ip) if ($ip !== '') $srv[] = $mask($ip);
echo '  of them in this server\'s own prefixes (' . implode(', ', $srv) . '): ' . array_sum(array_intersect_key($t, array_flip($srv))) . "\n";
$act = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}af_activity_log'") ? $wpdb->get_col("SELECT ip FROM {$wpdb->prefix}af_activity_log ORDER BY id DESC LIMIT 60") : array();
if ($act) echo '  activity log entries span: ' . $wpdb->get_var("SELECT MIN(created_at) FROM (SELECT created_at FROM {$wpdb->prefix}af_activity_log ORDER BY id DESC LIMIT 60) x") . ' .. ' . $wpdb->get_var("SELECT MAX(created_at) FROM {$wpdb->prefix}af_activity_log") . "\n";
echo '  newest guest comment: ' . $wpdb->get_var("SELECT MAX(comment_date) FROM {$wpdb->comments} WHERE user_id = 0") . "\n";
$t2 = $tally($act);
echo '  activity log (af_visitor_ip of signed-in users): ' . count($act) . ' | distinct prefixes: ' . count($t2) . ' | ' . substr(wp_json_encode(array_slice($t2, 0, 8)), 0, 300) . "\n";
$oips = array();
if (function_exists('wc_get_orders')) foreach (wc_get_orders(array('limit' => 40, 'type' => 'shop_order')) as $o) $oips[] = (string) $o->get_customer_ip_address();
$t3 = $tally($oips);
echo '  orders (WooCommerce reads X-Real-IP / X-Forwarded-For first): ' . count($oips) . ' | distinct prefixes: ' . count($t3) . ' | ' . substr(wp_json_encode(array_slice($t3, 0, 8)), 0, 300) . "\n";
$over = 0; foreach ($cips as $ip) foreach ($oips as $oi) if ($ip !== '' && $ip === $oi) $over++;
echo '  a guest comment address equal to an order address: ' . $over . "\n";

echo "\n=== 7. a check that does not read the cache: 11 made-up codes in a row\n";
// the gift card box allows 10 wrong codes an hour per address; if the 11th
// is refused as "too many", every probe was counted under ONE address (no
// gift card code has ever been issued, so nobody is kept from redeeming one)
$force = 'v4'; $when = 0;
for ($i = 1; $i <= 11; $i++) {
    $r = wp_remote_post(admin_url('admin-ajax.php'), array('timeout' => 30, 'headers' => array('User-Agent' => $ua, 'Cache-Control' => 'no-cache'), 'body' => array('action' => 'af_gc_apply', 'nonce' => $nonce, 'code' => 'TAF-QAQA-QAQA-QA' . sprintf('%02d', $i))));
    $b = is_wp_error($r) ? '' : wp_remote_retrieve_body($r);
    if (stripos($b, 'Too many') !== false) { $when = $i; break; }
}
$force = null;
echo '  ' . ($when ? "refused as too many at attempt {$when}: these requests were counted together under one address" : 'never refused as too many: the 11 requests were NOT counted under one address') . "\n";

echo "\n=== 5. past orders: WooCommerce's address against the theme's\n";
if (function_exists('wc_get_orders')) {
    $agree = 0; $differ = 0; $only = 0; $pref = array();
    foreach (wc_get_orders(array('limit' => 40, 'orderby' => 'date', 'order' => 'DESC', 'type' => 'shop_order')) as $o) {
        $wc = (string) $o->get_customer_ip_address(); $af = (string) $o->get_meta('_af_visitor_ip');
        if ($af === '') { $only++; continue; }
        if ($wc === $af) $agree++; else $differ++;
        $pref[$mask($af)] = ($pref[$mask($af)] ?? 0) + 1;
    }
    echo "  orders with the theme's address: " . ($agree + $differ) . " | same as WooCommerce's: {$agree} | different: {$differ} | without: {$only}\n";
    arsort($pref);
    echo '  theme addresses by first two parts (count): ' . wp_json_encode($pref) . "\n";
}
echo "=== END\n";
