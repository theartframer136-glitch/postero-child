<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Tests af_visitor_ip(): the address the contact form, the chatbot, the
 * newsletter and the activity log take for a visitor, with and without
 * Cloudflare's proxy in front of the site.
 *
 * Behind Cloudflare every connection comes from one of Cloudflare's servers
 * and the visitor's address is in CF-Connecting-IP. Reading REMOTE_ADDR there
 * would put every visitor arriving through one Cloudflare server under one
 * rate limit (the contact form allows 5 messages an hour per address).
 * Believing the header from anyone would let a visitor reaching the server
 * directly choose their own address, and so step around the limits.
 *
 * Runs the REAL functions, lifted out of functions.php. Plain `php`:
 *
 *     php tools/test-visitor-ip.php
 */
foreach (array('af_cloudflare_ranges', 'af_ip_in_cidr', 'af_visitor_ip', 'af_activity_client_ip') as $fn) {
    if (!preg_match('/\nfunction ' . $fn . '\(.*?\n\}\n/s', file_get_contents(__DIR__ . '/../functions.php'), $m)) {
        fwrite(STDERR, "could not find {$fn}() in functions.php\n");
        exit(2);
    }
    eval($m[0]);
}

$pass = 0; $fail = 0;
function check($what, $got, $want) {
    global $pass, $fail;
    if ($got === $want) { $pass++; echo "  OK    $what\n"; }
    else { $fail++; echo "  FAIL  $what: got " . var_export($got, true) . ", want " . var_export($want, true) . "\n"; }
}
function visit($remote, $cf = null, $xff = null) {
    $_SERVER = array();
    if ($remote !== null) $_SERVER['REMOTE_ADDR'] = $remote;
    if ($cf !== null) $_SERVER['HTTP_CF_CONNECTING_IP'] = $cf;
    if ($xff !== null) $_SERVER['HTTP_X_FORWARDED_FOR'] = $xff;
    return af_visitor_ip();
}

echo "=== the ranges ===\n";
$ranges = af_cloudflare_ranges();
$v4 = array_values(array_filter($ranges, function ($r) { return strpos($r, ':') === false; }));
$v6 = array_values(array_filter($ranges, function ($r) { return strpos($r, ':') !== false; }));
check('IPv4 and IPv6 ranges both listed', count($v4) > 10 && count($v6) > 5, true);
$bad = array();
foreach ($ranges as $r) { list($a, $b) = explode('/', $r) + array(1 => ''); if (@inet_pton($a) === false || !ctype_digit($b)) $bad[] = $r; }
check('every range is an address and a prefix length', $bad, array());

echo "\n=== af_ip_in_cidr ===\n";
check('173.245.48.1 is in 173.245.48.0/20', af_ip_in_cidr('173.245.48.1', '173.245.48.0/20'), true);
check('173.245.63.255 is its last address', af_ip_in_cidr('173.245.63.255', '173.245.48.0/20'), true);
check('173.245.64.0 is past it', af_ip_in_cidr('173.245.64.0', '173.245.48.0/20'), false);
check('2606:4700:10::6816:1 is in 2606:4700::/32', af_ip_in_cidr('2606:4700:10::6816:1', '2606:4700::/32'), true);
check('2606:4701::1 is not', af_ip_in_cidr('2606:4701::1', '2606:4700::/32'), false);
check('an IPv4 address is never in an IPv6 range', af_ip_in_cidr('104.16.0.1', '2606:4700::/32'), false);
check('junk is in nothing', af_ip_in_cidr('not-an-ip', '104.16.0.0/13'), false);

echo "\n=== behind Cloudflare ===\n";
check('from a Cloudflare server: the visitor\'s address from CF-Connecting-IP', visit('172.68.10.5', '203.0.113.7'), '203.0.113.7');
check('the same over IPv6', visit('2a06:98c0:3600::103', '2001:db8::7'), '2001:db8::7');
check('two visitors through one Cloudflare server stay apart',
    array(visit('162.158.1.1', '198.51.100.1'), visit('162.158.1.1', '198.51.100.2')), array('198.51.100.1', '198.51.100.2'));
check('a junk header from Cloudflare falls back to the connection', visit('172.68.10.5', 'x<script>'), '172.68.10.5');

echo "\n=== reaching the server directly ===\n";
check('no header: the connection\'s address', visit('203.0.113.9'), '203.0.113.9');
check('a header sent by the visitor themselves is ignored', visit('203.0.113.9', '1.2.3.4'), '203.0.113.9');
check('so is X-Forwarded-For', visit('203.0.113.9', null, '1.2.3.4'), '203.0.113.9');
check('a host that already restores the visitor\'s address: unchanged', visit('198.51.100.20', '198.51.100.20'), '198.51.100.20');
check('no address at all: empty', visit(null), '');
check('a junk REMOTE_ADDR: empty', visit('garbage'), '');

echo "\n=== the activity log ===\n";
visit('203.0.113.9', '1.2.3.4', '5.6.7.8');
check('records what af_visitor_ip() reads, not a header anyone can send', af_activity_client_ip(), '203.0.113.9');

printf("\n%d passed, %d failed\n", $pass, $fail);
exit($fail ? 1 : 0);
