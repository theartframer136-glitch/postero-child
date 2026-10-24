<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/** READ-ONLY. The one docroot file the previous check skipped: what setup-ssh-key.php does and whether the web reaches it. Prints no secrets. */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
$root = rtrim(ABSPATH, '/');
foreach (array($root . '/setup-ssh-key.php', $root . '/.user.ini', $root . '/php.ini', WP_CONTENT_DIR . '/debug.log') as $f) {
    if (!file_exists($f)) { echo "  " . basename($f) . ": absent\n"; continue; }
    $c = (string) file_get_contents($f, false, null, 0, 20000);
    printf("  %s | %d bytes | modified %s | perms %s\n", basename($f), filesize($f), gmdate('Y-m-d H:i', filemtime($f)), substr(sprintf('%o', fileperms($f)), -4));
    if (basename($f) === 'debug.log') { $lines = preg_split('/\r?\n/', trim(substr($c, -3000))); echo "    last lines: " . implode(' | ', array_map(function ($l) { return mb_strimwidth($l, 0, 120, '…'); }, array_slice($lines, -3))) . "\n"; continue; }
    $flags = array(); foreach (array('authorized_keys', 'file_put_contents', 'fopen', 'fwrite', 'exec(', 'shell_exec', 'system(', 'passthru', 'eval(', 'base64_decode', 'unlink(__FILE__', '$_GET', '$_POST', '$_REQUEST', 'hash_equals', 'token', 'secret', 'password', 'ssh-ed25519', 'ssh-rsa', 'mkdir', 'chmod', 'wp_set_auth_cookie') as $k) if (stripos($c, $k) !== false) $flags[] = $k;
    echo "    contains: " . implode(', ', $flags) . "\n";
    $head = preg_replace('/[A-Za-z0-9+\/=_-]{24,}/', '[blanked]', implode("\n", array_slice(preg_split('/\r?\n/', $c), 0, 40)));
    echo "    ---- first lines ----\n" . preg_replace('/^/m', '    | ', mb_strimwidth($head, 0, 2400, '…')) . "\n";
    $r = wp_remote_head(home_url('/' . basename($f)), array('timeout' => 20, 'sslverify' => true, 'redirection' => 0, 'headers' => array('User-Agent' => 'af-diag/1.0')));
    echo "    reachable from the web: " . (is_wp_error($r) ? 'ERR' : 'HTTP ' . wp_remote_retrieve_response_code($r)) . "\n";
}
echo "  authorized_keys entries on this account: " . (is_readable(getenv('HOME') . '/.ssh/authorized_keys') ? count(array_filter(file(getenv('HOME') . '/.ssh/authorized_keys'), 'trim')) : 'not readable') . "\n";
echo "=== DONE ===\n";
