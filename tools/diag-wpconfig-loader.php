<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. Why the crawl guard's wp-config.php loader could not be put in
 * place (30 Sep deploy: "rename wp-config.php.af-tmp -> wp-config.php failed",
 * the real reason hidden behind an unrelated translation notice), and whether
 * wp-config.php can be written IN PLACE instead. With the loader in
 * wp-config.php the guard runs before WordPress, and the "AF Crawl Guard
 * loader" plugin is no longer needed.
 *
 * Nothing of the site is changed: wp-config.php is only opened (no write), and
 * the rename test uses two scratch files of its own, deleted at the end.
 * Secrets in wp-config.php are never printed (only line numbers of defines).
 *
 * Run: wp eval-file tools/diag-wpconfig-loader.php --allow-root
 */
if (!defined('ABSPATH')) exit(1);

$root = rtrim(ABSPATH, '/\\');
$cands = array($root . '/wp-config.php', dirname($root) . '/wp-config.php');
if (isset($_SERVER['DOCUMENT_ROOT'])) $cands[] = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/wp-config.php';
$cands = array_values(array_unique($cands));

function af_wl_owner($uid) { if (function_exists('posix_getpwuid')) { $p = @posix_getpwuid($uid); if ($p) return $p['name'] . "($uid)"; } return (string) $uid; }
function af_wl_stat($p) {
    if (!file_exists($p) && !is_link($p)) return "  $p: does not exist\n";
    $ls = @lstat($p); $st = @stat($p);
    $o = "  $p\n";
    $o .= '    is_link=' . (is_link($p) ? 'yes -> ' . readlink($p) : 'no') . ' realpath=' . (realpath($p) ?: '-') . "\n";
    if ($ls) $o .= '    lstat: mode ' . substr(sprintf('%o', $ls['mode']), -4) . ' owner ' . af_wl_owner($ls['uid']) . ' group ' . $ls['gid'] . ' size ' . $ls['size'] . ' mtime ' . gmdate('Y-m-d H:i', $ls['mtime']) . "\n";
    if ($st && $ls && $st['ino'] !== $ls['ino']) $o .= '    target: mode ' . substr(sprintf('%o', $st['mode']), -4) . ' owner ' . af_wl_owner($st['uid']) . ' size ' . $st['size'] . "\n";
    $o .= '    is_writable=' . (is_writable($p) ? 'yes' : 'no') . ' is_readable=' . (is_readable($p) ? 'yes' : 'no') . "\n";
    return $o;
}
function af_wl_sh($cmd) {
    foreach (array('shell_exec', 'exec') as $f) if (function_exists($f) && !in_array($f, array_map('trim', explode(',', (string) ini_get('disable_functions'))), true)) {
        $out = $f === 'shell_exec' ? @shell_exec($cmd . ' 2>&1') : (function () use ($cmd) { @exec($cmd . ' 2>&1', $a); return implode("\n", $a); })();
        return trim((string) $out);
    }
    return '(shell functions disabled)';
}

echo "=== process\n";
echo '  php ' . PHP_VERSION . ' uid=' . (function_exists('posix_geteuid') ? af_wl_owner(posix_geteuid()) : '?') . ' | ABSPATH=' . ABSPATH . ' | DOCUMENT_ROOT=' . ($_SERVER['DOCUMENT_ROOT'] ?? '-') . "\n";
echo '  disable_functions=' . (ini_get('disable_functions') ?: '(none)') . "\n";

echo "\n=== wp-config.php candidates\n";
$wpc = null;
foreach ($cands as $c) { echo af_wl_stat($c); if ($wpc === null && file_exists($c)) $wpc = $c; }
if ($wpc === null) { echo "  no wp-config.php found\n=== END\n"; return; }

echo "\n=== the directory that holds it\n";
$dir = dirname($wpc);
echo af_wl_stat($dir);
$ds = @stat($dir);
if ($ds) echo '    sticky bit=' . (($ds['mode'] & 01000) ? 'YES (rename over a file owned by someone else is refused here)' : 'no') . "\n";
echo '    lsattr: ' . str_replace("\n", ' | ', af_wl_sh('lsattr -d ' . escapeshellarg($dir) . '; lsattr ' . escapeshellarg($wpc))) . "\n";
echo '    stat: ' . str_replace("\n", ' | ', af_wl_sh('stat -c "%A %U:%G %i %N" ' . escapeshellarg($wpc) . ' ' . escapeshellarg($dir))) . "\n";
echo '    mount: ' . af_wl_sh('df -P ' . escapeshellarg($dir) . ' | tail -1') . "\n";

echo "\n=== wp-config.php shape (no values printed)\n";
$src = @file_get_contents($wpc);
if ($src === false) { echo "  cannot read\n"; } else {
    $lines = explode("\n", $src);
    echo '  lines=' . count($lines) . ' bytes=' . strlen($src) . ' starts_with_php=' . (strpos($src, '<?php') === 0 ? 'yes' : 'NO (offset ' . var_export(strpos($src, '<?php'), true) . ')') . "\n";
    echo '  AF-CRAWL-GUARD marker present=' . (strpos($src, 'BEGIN AF-CRAWL-GUARD') !== false ? 'YES' : 'no') . "\n";
    foreach ($lines as $i => $l) {
        if (preg_match('/\b(require|include)(_once)?\b|wp-settings|ABSPATH|WP_CONTENT_DIR|WP_PLUGIN_DIR|table_prefix|WP_CACHE|WP_DEBUG|DISABLE_WP_CRON|AF-CRAWL/', $l)) {
            $shown = preg_replace("/(['\"])[^'\"]*(['\"])\s*\)\s*;/", '$1…$2);', $l);
            if (preg_match('/DB_|KEY|SALT|PASSWORD|SECRET/i', $l)) $shown = preg_replace('/define\s*\(.*/', 'define(…masked…);', $l);
            echo '  ' . ($i + 1) . ': ' . trim(substr($shown, 0, 160)) . "\n";
        }
    }
}

echo "\n=== can wp-config.php be written in place? (open for writing, no write, no truncate)\n";
$h = @fopen($wpc, 'c');
if ($h) { $lk = @flock($h, LOCK_EX | LOCK_NB); echo '  fopen(c)=yes flock=' . ($lk ? 'yes' : 'no') . "\n"; if ($lk) @flock($h, LOCK_UN); fclose($h); }
else { $e = error_get_last(); echo '  fopen(c)=NO: ' . ($e['message'] ?? 'unknown') . "\n"; }
clearstatcache(true, $wpc); $after = @stat($wpc);
echo '  wp-config.php unchanged after the open: ' . (($after && @md5_file($wpc) === md5((string) $src)) ? 'yes' : 'CHECK') . "\n";

echo "\n=== can a file in that directory be replaced by rename()? (scratch files only)\n";
$tag = '.af-probe-' . substr(md5(uniqid('', true)), 0, 8);
$a = "$dir/$tag.a"; $b = "$dir/$tag.b";
$wa = @file_put_contents($a, "a\n"); $wb = @file_put_contents($b, "b\n");
echo '  create scratch: a=' . ($wa === false ? 'NO' : 'ok') . ' b=' . ($wb === false ? 'NO' : 'ok') . "\n";
if ($wa !== false && $wb !== false) {
    error_clear_last();
    $r = @rename($a, $b); $e = error_get_last();
    echo '  rename(a -> b, b exists, both ours)=' . ($r ? 'ok' : 'NO: ' . ($e['message'] ?? 'unknown')) . "\n";
}
@unlink($a); @unlink($b);
echo '  scratch removed: ' . ((!file_exists($a) && !file_exists($b)) ? 'yes' : 'NO') . "\n";
// the exact failing step: a temp next to wp-config.php, renamed over it, is NOT
// repeated here (it would replace the file); what decides it is the dir's
// sticky bit + the owner of wp-config.php (above) and the lsattr flags.

echo "\n=== the guard today\n";
$content = (defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : $root . '/wp-content') . '/af-crawl-guard.php';
echo '  ' . $content . ': ' . (is_file($content) ? filesize($content) . ' bytes, md5 ' . md5_file($content) : 'MISSING') . "\n";
$active = (array) get_option('active_plugins', array());
echo '  loader plugin active=' . (in_array('af-crawl-guard/af-crawl-guard.php', $active, true) ? 'yes (position ' . (array_search('af-crawl-guard/af-crawl-guard.php', $active, true) + 1) . ' of ' . count($active) . ')' : 'no') . "\n";
echo '  af_guard_no_search option=' . json_encode(get_option('af_guard_no_search')) . "\n";
echo '  theme guard source md5=' . (is_file(get_stylesheet_directory() . '/ops/mu/af-crawl-guard.php') ? md5_file(get_stylesheet_directory() . '/ops/mu/af-crawl-guard.php') : '-') . "\n";
echo "=== END\n";
