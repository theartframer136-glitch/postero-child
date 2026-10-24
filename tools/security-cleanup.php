<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Security cleanup after the 24 Oct 2026 scan (tools/diag-security*.php).
 *
 * 1. Leftover scripts in the document root are removed. A copy of each goes
 *    to wp-content/af-quarantine/ (not web-reachable, .htaccess deny) so
 *    nothing is lost:
 *      create_autologin_<hash>.php  Hostinger's hPanel auto-login helper from
 *                                   23 Jan: it signs the browser in as a named
 *                                   account with no password. Hostinger deletes
 *                                   it after use; this one stayed nine months.
 *      setup-ssh-key.php            from 23 May, answered HTTP 200 to anyone:
 *                                   each request overwrote ~/.ssh/authorized_keys
 *                                   with the GitHub Actions deploy key and
 *                                   echoed the file. The key stays installed,
 *                                   so deploys keep working; only the script
 *                                   goes.
 *      .htaccess.bk                 a stale LiteSpeed block, harmless, clutter.
 * 2. Open registration on wp-login.php is switched off (users_can_register).
 *    Customers still register through WooCommerce's My Account, which has its
 *    own setting and stays on; WordPress's own form only makes spam accounts.
 * 3. debug.log is truncated when it has passed 50 MB. Hostinger's platform
 *    forces WP_DEBUG on (wp-config says false) and the log had reached 78 MB
 *    of deprecation notices; it cannot be read from the web, this is disk
 *    and I/O hygiene only.
 *
 * Idempotent: a second run reports nothing to do.
 */
if (!defined('ABSPATH')) exit;
$log = function ($m) { echo "[security] $m\n"; };
$root = rtrim(ABSPATH, '/');
$q = WP_CONTENT_DIR . '/af-quarantine';

$targets = array_merge(glob($root . '/create_autologin_*.php') ?: array(), array($root . '/setup-ssh-key.php', $root . '/.htaccess.bk'));
$moved = 0;
foreach ($targets as $f) {
    if (!is_file($f)) continue;
    if (!is_dir($q)) { wp_mkdir_p($q); @file_put_contents($q . '/.htaccess', "Require all denied\nDeny from all\n"); @file_put_contents($q . '/index.html', ''); }
    $dest = $q . '/' . gmdate('Ymd') . '-' . basename($f) . '.txt';   // .txt: never executable again
    if (@copy($f, $dest) && @unlink($f)) { $moved++; $log('removed ' . basename($f) . ' (copy kept in wp-content/af-quarantine/)'); }
    else $log('could not remove ' . basename($f) . ' (permissions?)');
}
if (!$moved) $log('no leftover scripts in the document root');

if ((string) get_option('users_can_register') !== '0') { update_option('users_can_register', 0); $log('users_can_register switched off (WooCommerce My Account registration unchanged)'); }

$dl = WP_CONTENT_DIR . '/debug.log';
clearstatcache(true, $dl);
if (is_file($dl) && filesize($dl) > 50 * 1024 * 1024) { $size = round(filesize($dl) / 1048576); if (@file_put_contents($dl, '') !== false) $log("debug.log truncated ({$size} MB)"); }

if ($moved) do_action('litespeed_purge_all');
