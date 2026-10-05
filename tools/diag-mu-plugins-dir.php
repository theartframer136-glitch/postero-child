<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. Why Preview Ports and Preview Look cannot put their one-run file
 * in place (5 Oct): every "scp … wp-content/mu-plugins/af-port-preview.php"
 * since at least 3 Oct ends "Permission denied", so both sides of each
 * comparison were the live site with every plugin loaded.
 *
 * Prints, with home and site paths shortened to ~ and ABSPATH:
 *   - where WordPress looks for must-use plugins (WPMU_PLUGIN_DIR), whether
 *     it is a symlink and where to, who owns it, its mode, and whether this
 *     user can write there
 *   - the same for wp-content and wp-content/plugins, for comparison
 *   - the files in the must-use folder (names, owner, size), and which load
 *   - the user this runs as
 *
 * Run: wp eval-file tools/diag-mu-plugins-dir.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }

$home = getenv('HOME') ?: '';
$short = function ($p) use ($home) {
    $p = (string) $p;
    if ($home !== '' && strpos($p, $home) === 0) $p = '~' . substr($p, strlen($home));
    return preg_replace('#/(u|home/u)\d+/#', '/<user>/', $p);
};
// getmyuid() and posix_* are disabled on this host; the SSH user (the one
// the workflows log in as) just copied this file into the theme.
$self = get_stylesheet_directory() . '/tools/diag-mu-plugins-dir.php';
$me = function_exists('posix_geteuid') ? posix_geteuid() : (file_exists($self) ? fileowner($self) : -1);
$who = function ($uid) use ($me) {
    return $uid === $me ? 'this user' : ($uid === 0 ? 'root' : 'another user (uid ' . $uid . ')');
};
echo 'this user: ' . ($me === 0 ? 'root' : 'uid ' . $me) . (function_exists('posix_geteuid') ? '' : ' (owner of this file)') . "\n";

$show = function ($label, $p) use ($short, $who) {
    echo "\n$label: " . $short($p) . "\n";
    if (!file_exists($p) && !is_link($p)) { echo "  does not exist\n"; return; }
    if (is_link($p)) echo '  symlink to ' . (function_exists('readlink') ? $short(readlink($p)) : '?') . "\n";
    $real = function_exists('realpath') ? realpath($p) : false;
    if ($real && $real !== $p) echo '  real path ' . $short($real) . "\n";
    $st = @stat($p);
    if ($st) echo '  owner ' . $who($st['uid']) . ', mode ' . substr(sprintf('%o', $st['mode']), -4) . "\n";
    echo '  writable by this user: ' . (is_writable($p) ? 'yes' : 'NO') . "\n";
    $parent = dirname($real ?: $p);
    $pst = @stat($parent);
    if ($pst) echo '  its folder ' . $short($parent) . ': owner ' . $who($pst['uid']) . ', mode ' . substr(sprintf('%o', $pst['mode']), -4) . ', writable ' . (is_writable($parent) ? 'yes' : 'NO') . "\n";
};

$show('must-use folder (WPMU_PLUGIN_DIR)', WPMU_PLUGIN_DIR);
echo '  WPMU_PLUGIN_DIR is ' . (rtrim(WPMU_PLUGIN_DIR, '/') === rtrim(WP_CONTENT_DIR, '/') . '/mu-plugins' ? 'the default (wp-content/mu-plugins)' : 'set elsewhere') . "\n";
$show('wp-content', WP_CONTENT_DIR);
$show('plugins', WP_PLUGIN_DIR);
$show('ABSPATH', ABSPATH);
$show('the theme', get_stylesheet_directory());

echo "\nfiles in the must-use folder:\n";
$loaded = array_map('basename', function_exists('wp_get_mu_plugins') ? wp_get_mu_plugins() : array());
$items = @scandir(WPMU_PLUGIN_DIR);
if ($items === false) echo "  (cannot list)\n";
else foreach ($items as $f) {
    if ($f === '.' || $f === '..') continue;
    $p = WPMU_PLUGIN_DIR . '/' . $f;
    $st = function_exists('lstat') ? @lstat($p) : @stat($p);
    echo '  ' . str_pad($f, 44) . ' ' . (is_link($p) ? 'symlink to ' . (function_exists('readlink') ? $short(readlink($p)) : '?') : (is_dir($p) ? 'folder' : ($st ? $st['size'] . ' bytes' : '?')))
        . ($st ? ', owner ' . $who($st['uid']) . ', mode ' . substr(sprintf('%o', $st['mode']), -4) : '')
        . (in_array($f, $loaded, true) ? ', loaded' : '') . "\n";
}
echo "done\n";
