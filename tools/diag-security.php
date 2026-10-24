<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * READ-ONLY security scan of the live site: signs of compromise (PHP in
 * uploads, altered core files, injected redirects, unknown administrators,
 * odd scheduled tasks, malware signatures), exposure (headers, xmlrpc, user
 * enumeration, REST users), hygiene (updates, permissions, debug flags) and
 * the TLS certificate. Changes nothing. Prints no secrets.
 *
 *   wp eval-file wp-content/themes/postero-child/tools/diag-security.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
global $wpdb;
$root = rtrim(ABSPATH, '/'); $wc = WP_CONTENT_DIR; $up = wp_get_upload_dir(); $updir = $up['basedir'];
$theme = get_stylesheet_directory();
$cli = function ($cmd) { try { if (!class_exists('WP_CLI')) return '(no WP_CLI)'; $r = WP_CLI::runcommand($cmd, array('return' => 'all', 'exit_error' => false, 'launch' => true)); return trim(((string) $r->stdout) . ($r->stderr ? "\n" . $r->stderr : '')); } catch (\Throwable $e) { return 'ERR ' . $e->getMessage(); } };

echo "=== 1. VERSIONS AND UPDATES\n";
echo "  WordPress " . get_bloginfo('version') . " | PHP " . PHP_VERSION . " | WooCommerce " . (defined('WC_VERSION') ? WC_VERSION : '?') . " | Elementor " . (defined('ELEMENTOR_VERSION') ? ELEMENTOR_VERSION : '?') . "\n";
echo "  core check-update: " . str_replace("\n", ' | ', $cli('core check-update --format=csv')) . "\n";
$pu = get_site_transient('update_plugins'); $n = isset($pu->response) ? count((array) $pu->response) : 0; echo "  plugin updates pending: $n" . ($n ? ' (' . implode(', ', array_keys((array) $pu->response)) . ')' : '') . "\n";
$tu = get_site_transient('update_themes'); $n = isset($tu->response) ? count((array) $tu->response) : 0; echo "  theme updates pending: $n\n";
echo "  active plugins: " . implode(', ', (array) get_option('active_plugins')) . "\n";
$inactive = array_diff(array_keys((array) get_plugins()), (array) get_option('active_plugins')); echo "  inactive plugins on disk: " . (count($inactive) ? implode(', ', $inactive) : 'none') . "\n";
echo "  mu-plugins: " . implode(', ', array_keys((array) get_mu_plugins())) ?: 'none'; echo "\n";
echo "  WP_DEBUG=" . (defined('WP_DEBUG') && WP_DEBUG ? 'ON' : 'off') . " display_errors=" . ini_get('display_errors') . " DISALLOW_FILE_EDIT=" . (defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT ? 'yes' : 'NO') . " DISALLOW_FILE_MODS=" . (defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS ? 'yes' : 'no') . "\n";

echo "\n=== 2. CORE AND PLUGIN FILE INTEGRITY\n";
$v = $cli('core verify-checksums'); echo "  core verify-checksums: " . (stripos($v, 'Success') !== false ? 'OK (' . trim(strtok($v, "\n")) . ')' : str_replace("\n", ' | ', mb_strimwidth($v, 0, 600, '…'))) . "\n";
$v = $cli('plugin verify-checksums --all'); echo "  plugin verify-checksums: " . str_replace("\n", ' | ', mb_strimwidth($v, 0, 600, '…')) . "\n";

echo "\n=== 3. USERS\n";
echo "  users_can_register=" . get_option('users_can_register') . " default_role=" . get_option('default_role') . " admin_email=" . preg_replace('/(?<=.{2}).(?=.*@)/', '*', get_option('admin_email')) . "\n";
foreach (get_users(array('role__in' => array('administrator', 'shop_manager', 'editor'), 'orderby' => 'ID')) as $u) {
    $sess = get_user_meta($u->ID, 'session_tokens', true); $last = 0; $ips = array();
    if (is_array($sess)) foreach ($sess as $s) { $last = max($last, (int) ($s['login'] ?? 0)); if (!empty($s['ip'])) $ips[] = preg_replace('/\.\d+$/', '.x', $s['ip']); }
    printf("  #%-4d %-22s %-14s registered %s | last login %s | active sessions %d | ips %s | app passwords %d\n", $u->ID, $u->user_login, implode(',', $u->roles), substr($u->user_registered, 0, 10), $last ? gmdate('Y-m-d H:i', $last) : 'none', is_array($sess) ? count($sess) : 0, implode(',', array_unique($ips)) ?: '-', count((array) WP_Application_Passwords::get_user_application_passwords($u->ID)));
}
$recent = get_users(array('date_query' => array(array('after' => '30 days ago')), 'number' => 50));
echo "  accounts created in the last 30 days: " . count($recent); $elev = 0; foreach ($recent as $u) if (array_intersect($u->roles, array('administrator', 'editor', 'shop_manager'))) $elev++; echo " ($elev with elevated roles)\n";
echo "  a user named 'admin' exists: " . (get_user_by('login', 'admin') ? 'YES' : 'no') . "\n";

echo "\n=== 4. FILES THAT SHOULD NOT BE THERE\n";
$php_in_uploads = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($updir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
$scanned = 0;
foreach ($it as $f) { if (!$f->isFile()) continue; $scanned++; $name = $f->getFilename(); if (preg_match('/\.(php\d?|phtml|phar|pl|cgi|sh)$/i', $name) || preg_match('/\.(ico|txt|jpg|png)\.php$/i', $name)) $php_in_uploads[] = str_replace($updir, 'uploads', $f->getPathname()) . ' (' . gmdate('Y-m-d', $f->getMTime()) . ')'; }
echo "  uploads scanned: $scanned files | executable files found: " . count($php_in_uploads) . "\n"; foreach (array_slice($php_in_uploads, 0, 15) as $p) echo "    $p\n";
echo "  wp-content root php files: " . implode(', ', array_map('basename', glob($wc . '/*.php') ?: array())) . "\n";
echo "  docroot files: "; foreach (glob($root . '/*') ?: array()) { if (is_file($_ = $f = $root . '/' . basename($f))) {} } 
$docfiles = array(); foreach (scandir($root) as $n) { if ($n[0] === '.' && $n !== '.htaccess') continue; $p = "$root/$n"; if (is_file($p)) $docfiles[] = $n . '(' . gmdate('m-d', filemtime($p)) . ')'; } echo implode(' ', $docfiles) . "\n";
$known = array('index.php','wp-config.php','wp-config-sample.php','.htaccess','license.txt','readme.html','xmlrpc.php','wp-login.php','wp-cron.php','wp-settings.php','wp-load.php','wp-blog-header.php','wp-activate.php','wp-comments-post.php','wp-links-opml.php','wp-mail.php','wp-signup.php','wp-trackback.php','robots.txt','ads.txt','.user.ini','php.ini','error_log','favicon.ico','.well-known','google-site-verification.html','default.php');
$odd = array(); foreach (scandir($root) as $n) { if ($n === '.' || $n === '..') continue; if (is_file("$root/$n") && !in_array($n, $known, true) && !preg_match('/^google[0-9a-f]+\.html$/', $n)) $odd[] = $n; } echo "  unexpected docroot files: " . ($odd ? implode(', ', $odd) : 'none') . "\n";
$recentmod = array(); $cut = time() - 14 * DAY_IN_SECONDS;
foreach (array($root => false, $wc => false, $wc . '/plugins' => true) as $d => $rec) {
    $iter = $rec ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS)) : new DirectoryIterator($d);
    foreach ($iter as $f) { if (!$f->isFile() || !preg_match('/\.(php|htaccess|ini|js)$/i', $f->getFilename()) && $f->getFilename() !== '.htaccess') continue; if ($f->getMTime() > $cut) $recentmod[] = str_replace($root . '/', '', $f->getPathname()) . ' ' . gmdate('m-d H:i', $f->getMTime()); }
}
echo "  php/js/htaccess files changed in 14 days outside the theme and uploads: " . count($recentmod) . "\n"; foreach (array_slice($recentmod, 0, 20) as $r) echo "    $r\n";

echo "\n=== 5. MALWARE SIGNATURES\n";
$sig = '/\beval\s*\(\s*(base64_decode|gzinflate|gzuncompress|str_rot13|strrev)\s*\(|\bassert\s*\(\s*\$_(POST|GET|REQUEST)|preg_replace\s*\(\s*[\'"][^\'"]*\/[a-z]*e[a-z]*[\'"]|\bcreate_function\s*\(\s*[\'"]|\$_(POST|GET|COOKIE|REQUEST)\s*\[[^\]]+\]\s*\(\s*\$|GIF89a\s*<\?php|<\?php\s+@?eval|\bsystem\s*\(\s*\$_(GET|POST)|shell_exec\s*\(\s*\$_|move_uploaded_file\s*\(\s*\$_FILES\[[^\]]+\]\[.tmp_name.\]\s*,\s*\$_(GET|POST)/i';
$hits = array(); $files = 0;
foreach (array($updir, $wc . '/plugins', $theme, $wc, $root) as $i => $d) {
    $iter = in_array($i, array(3, 4), true) ? new DirectoryIterator($d) : new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS));
    foreach ($iter as $f) {
        if (!$f->isFile() || !preg_match('/\.(php\d?|phtml|inc|ico|txt|js)$/i', $f->getFilename()) || $f->getSize() > 2000000) continue;
        if (strpos($f->getPathname(), '/node_modules/') !== false) continue;
        $files++; $c = @file_get_contents($f->getPathname()); if ($c === false) continue;
        if (preg_match($sig, $c, $m)) $hits[] = str_replace($root . '/', '', $f->getPathname()) . ' :: ' . preg_replace('/\s+/', ' ', mb_strimwidth($m[0], 0, 60));
        if (count($hits) > 40) break 2;
    }
}
echo "  files scanned: $files | signature hits: " . count($hits) . "\n"; foreach ($hits as $h) echo "    $h\n";

echo "\n=== 6. CONFIG, HTACCESS, CRON, OPTIONS\n";
$ht = @file_get_contents($root . '/.htaccess') ?: '';
echo "  .htaccess: " . strlen($ht) . " bytes, modified " . gmdate('Y-m-d H:i', @filemtime($root . '/.htaccess')) . " | external redirects: " . (preg_match_all('#Rewrite(Rule|Cond)[^\n]*https?://(?!(www\.)?theartframer\.us)[^\s]+#i', $ht, $mm) ?: 0) . " | base64/php_value auto_prepend: " . (preg_match('/base64|auto_prepend_file|auto_append_file/i', $ht) ? 'FOUND' : 'none') . " | security header block: " . (stripos($ht, 'Strict-Transport-Security') !== false ? 'present' : 'absent') . "\n";
$wpc = @file_get_contents($root . '/wp-config.php') ?: ''; echo "  wp-config.php: modified " . gmdate('Y-m-d H:i', @filemtime($root . '/wp-config.php')) . " | perms " . substr(sprintf('%o', fileperms($root . '/wp-config.php')), -4) . " | includes outside WP: " . (preg_match_all('/(require|include)(_once)?\s*\(?\s*[\'"]([^\'"]+)/i', $wpc, $im) ? implode(', ', array_map('basename', $im[3])) : 'none') . " | auto_prepend/base64: " . (preg_match('/base64_decode|auto_prepend/i', $wpc) ? 'FOUND' : 'none') . "\n";
echo "  index.php modified " . gmdate('Y-m-d', @filemtime($root . '/index.php')) . " | uploads dir perms " . substr(sprintf('%o', fileperms($updir)), -4) . " | uploads .htaccess: " . (file_exists($updir . '/.htaccess') ? 'present' : 'absent (php execution in uploads relies on the host)') . "\n";
echo "  siteurl=" . get_option('siteurl') . " home=" . get_option('home') . "\n";
$crons = _get_cron_array(); $hooks = array(); foreach ((array) $crons as $ts => $set) foreach ($set as $hook => $ev) $hooks[$hook] = ($hooks[$hook] ?? 0) + count($ev);
$suspicious_cron = array(); foreach ($hooks as $h => $c) if (preg_match('/eval|base64|\.php|http|[0-9a-f]{32}/i', $h) && strpos($h, 'action_scheduler') === false) $suspicious_cron[] = $h;
echo "  cron hooks: " . count($hooks) . " | suspicious names: " . ($suspicious_cron ? implode(', ', $suspicious_cron) : 'none') . "\n";
$optbad = $wpdb->get_results("SELECT option_name, LENGTH(option_value) len FROM {$wpdb->options} WHERE option_value LIKE '%eval(base64_decode%' OR option_value LIKE '%<script src=\"http%' OR option_value LIKE '%<script src=\\'http%' LIMIT 10");
echo "  options holding eval/base64 or an external <script src>: " . count($optbad); foreach ($optbad as $o) echo " [{$o->option_name} {$o->len}b]"; echo "\n";
$postbad = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status='publish' AND post_type IN ('post','page','product') AND (post_content LIKE '%<script src=\"http%' OR post_content LIKE '%<iframe src=\"http%' OR post_content LIKE '%eval(%')");
echo "  published posts/pages/products embedding an external script or iframe: $postbad\n";
$spam = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status='publish' AND post_type='post' AND (post_title LIKE '%casino%' OR post_title LIKE '%viagra%' OR post_title LIKE '%crypto%' OR post_title LIKE '%slot%' OR post_title LIKE '%betting%')");
echo "  spam-looking published posts (casino/pharma/slots): $spam\n";
echo "  login throttle buckets live right now: " . (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_af\\_rl\\_%'") . " | turnstile mode: " . get_option('af_turnstile_mode', '(unset)') . " | xmlrpc option: " . (get_option('af_xmlrpc_enabled') === 'yes' ? 'ENABLED' : 'disabled') . "\n";

echo "\n=== 7. WHAT THE OUTSIDE SEES\n";
$h = wp_remote_head(home_url('/'), array('timeout' => 30, 'sslverify' => true, 'redirection' => 3, 'headers' => array('User-Agent' => 'af-diag/1.0')));
if (!is_wp_error($h)) { $hd = wp_remote_retrieve_headers($h); foreach (array('strict-transport-security', 'x-frame-options', 'x-content-type-options', 'referrer-policy', 'permissions-policy', 'content-security-policy', 'content-security-policy-report-only', 'x-powered-by', 'server') as $k) echo "  $k: " . (isset($hd[$k]) ? mb_strimwidth(is_array($hd[$k]) ? implode(' ', $hd[$k]) : $hd[$k], 0, 90, '…') : '(none)') . "\n"; } else echo "  home HEAD error: " . $h->get_error_message() . "\n";
foreach (array('xmlrpc.php' => array('POST', '<?xml version="1.0"?><methodCall><methodName>system.listMethods</methodName></methodCall>'), '?author=1' => array('GET', ''), 'wp-json/wp/v2/users' => array('GET', ''), 'wp-login.php' => array('GET', ''), 'wp-content/debug.log' => array('GET', ''), 'wp-config.php.bak' => array('GET', ''), '.git/HEAD' => array('GET', ''), 'wp-content/uploads/' => array('GET', ''), 'readme.html' => array('GET', ''), '.env' => array('GET', '')) as $path => $m) {
    $r = wp_remote_request(home_url('/' . $path), array('method' => $m[0], 'body' => $m[1], 'timeout' => 20, 'sslverify' => true, 'redirection' => 0, 'headers' => array('User-Agent' => 'af-diag/1.0')));
    $code = is_wp_error($r) ? 'ERR' : wp_remote_retrieve_response_code($r); $loc = is_wp_error($r) ? '' : (string) wp_remote_retrieve_header($r, 'location');
    $body = is_wp_error($r) ? '' : (string) wp_remote_retrieve_body($r);
    $note = '';
    if ($path === '?author=1') $note = $loc ? ' -> ' . preg_replace('#https?://[^/]+#', '', $loc) . (preg_match('#/author/#', $loc) ? '  USERNAME EXPOSED' : '') : '';
    if ($path === 'wp-json/wp/v2/users') $note = ($code == 200 && strpos($body, '"slug"') !== false) ? '  USER LIST EXPOSED' : '';
    if ($path === 'xmlrpc.php') $note = ($code == 200 && strpos($body, 'methodResponse') !== false && strpos($body, 'faultCode') === false) ? '  XML-RPC OPEN' : '';
    if ($path === 'wp-content/uploads/') $note = ($code == 200 && stripos($body, 'Index of') !== false) ? '  DIRECTORY LISTING ON' : '';
    if (in_array($path, array('wp-content/debug.log', 'wp-config.php.bak', '.git/HEAD', '.env'), true)) $note = $code == 200 ? '  EXPOSED' : '';
    printf("  %-28s %s%s\n", $path, $code, $note);
}
$host = parse_url(home_url(), PHP_URL_HOST);
$ctx = stream_context_create(array('ssl' => array('capture_peer_cert' => true, 'verify_peer' => true, 'verify_peer_name' => true)));
$sock = @stream_socket_client("ssl://$host:443", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
if ($sock) { $cert = openssl_x509_parse(stream_context_get_params($sock)['options']['ssl']['peer_certificate']); $days = (int) floor(($cert['validTo_time_t'] - time()) / 86400); echo "  TLS certificate: issued by " . ($cert['issuer']['O'] ?? '?') . ", expires " . gmdate('Y-m-d', $cert['validTo_time_t']) . " ($days days)" . ($days < 14 ? '  RENEW SOON' : '') . "\n"; fclose($sock); } else echo "  TLS certificate: could not connect ($errstr)\n";
echo "\n=== DONE ===\n";
