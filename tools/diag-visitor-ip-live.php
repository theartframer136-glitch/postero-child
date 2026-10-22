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
 *  1. This server's own public address, as an outside service sees it.
 *  2. One request from this server to the public site, through its CDN: a
 *     made-up gift card code, which only adds 1 to the gift-card guess count
 *     of the address PHP sees (10 an hour; nothing else is written).
 *  3. Was that count written under this server's public address? Then PHP
 *     sees the visitor's own address behind the CDN.
 *  4. Did the request really go through the CDN (its response headers)?
 *  5. Past orders: WooCommerce's recorded address against the theme's
 *     (how many agree; no address is printed, only the first two parts).
 *
 *   wp eval-file tools/diag-visitor-ip-live.php
 */
$mask = function ($ip) { $p = preg_split('/[.:]/', (string) $ip); return $ip === '' ? '(none)' : $p[0] . (isset($p[1]) ? '.' . $p[1] : '') . '.x.x'; };

echo "=== 1. this server's public address\n";
$me = '';
foreach (array('https://api.ipify.org', 'https://icanhazip.com', 'https://ifconfig.me/ip') as $u) {
    $r = wp_remote_get($u, array('timeout' => 15));
    $v = is_wp_error($r) ? '' : trim(wp_remote_retrieve_body($r));
    if (filter_var($v, FILTER_VALIDATE_IP)) { $me = $v; echo "  {$u}: {$mask($me)}\n"; break; }
    echo "  {$u}: " . (is_wp_error($r) ? $r->get_error_message() : 'no address') . "\n";
}
if ($me === '') { echo "  could not learn this server's public address\n=== END\n"; return; }
echo '  is it one of Cloudflare\'s: ' . (function_exists('af_ip_is_cloudflare') && af_ip_is_cloudflare($me) ? 'yes' : 'no') . "\n";

echo "\n=== 2. one request to the public site\n";
$key = 'af_rl_' . md5('gc|' . $me);
$before = get_transient($key);
wp_set_current_user(0);
$nonce = wp_create_nonce('af_gc_apply');
$r = wp_remote_post(admin_url('admin-ajax.php'), array(
    'timeout' => 30,
    'headers' => array('User-Agent' => 'Mozilla/5.0 (af visitor-ip probe)', 'Cache-Control' => 'no-cache'),
    'body'    => array('action' => 'af_gc_apply', 'nonce' => $nonce, 'code' => 'TAF-QAQA-QAQA-QAQA'),
));
if (is_wp_error($r)) { echo '  request failed: ' . $r->get_error_message() . "\n=== END\n"; return; }
$body = wp_remote_retrieve_body($r);
echo '  HTTP ' . wp_remote_retrieve_response_code($r) . ' | answer: ' . substr(preg_replace('/\s+/', ' ', $body), 0, 120) . "\n";
$h = wp_remote_retrieve_headers($r);
$show = array();
foreach (array('server', 'platform', 'x-hcdn-request-id', 'x-hcdn-cache-status', 'x-hcdn-upstream-rt', 'cf-ray', 'x-litespeed-cache', 'via') as $n) if (!empty($h[$n])) $show[] = $n . ': ' . (is_array($h[$n]) ? implode(',', $h[$n]) : $h[$n]);
echo '  response headers: ' . ($show ? implode(' | ', $show) : '(none of the CDN ones)') . "\n";
$cdn = false;
foreach ($show as $s) if (preg_match('/^(x-hcdn-|cf-ray|via)/', $s) || preg_match('/hcdn|cloudflare/i', $s)) $cdn = true;
echo '  went through a CDN: ' . ($cdn ? 'yes' : 'NO (the server reached itself directly; step 3 then proves nothing about the CDN)') . "\n";

echo "\n=== 3. whose address PHP counted\n";
wp_cache_delete($key, 'transient');
wp_cache_delete('_transient_' . $key, 'options');
$after = get_transient($key);
$b = is_array($before) ? (int) $before[0] : 0;
$a = is_array($after) ? (int) $after[0] : 0;
echo "  gift-card guess count for this server's public address: before {$b}, after {$a}\n";
if ($a > $b) echo "  RESULT: PHP saw this server's own public address" . ($cdn ? ' through the CDN: each visitor is counted under their own address.' : ', but the request did not go through the CDN.') . "\n";
else echo "  RESULT: the count is NOT under this server's address: PHP saw another address (a CDN or proxy one?). Per-address limits would be shared.\n";

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
