<?php
/**
 * Print the Products In Motion video ids, one per line - run by PHP directly,
 * not by wp-cli and not over HTTP.
 *
 * Both of the other routes failed on this server, and each failure cost a
 * deploy:
 *
 *   wp eval-file  -> "wp: command not found". ssh running a command string
 *                    picks wp up from the login shell; a `bash script.sh`
 *                    child shell does not.
 *   curl its own  -> "Operation timed out after 60001 ms with 0 bytes". The
 *   public URL       host will not loop a connection from the server back to
 *                    its own public address. Shared hosting usually will not.
 *
 * PHP loading WordPress in-process depends on neither: no PATH entry, no
 * network, no shell environment. It is the same thing wp-cli does, minus
 * wp-cli.
 *
 *   php pim-ids-cli.php            every id in the row
 *   php pim-ids-cli.php missing    only those with no local file yet
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Forbidden\n"); }

// tools -> postero-child -> themes -> wp-content -> public_html
$root = dirname(__DIR__, 4);
$load = $root . '/wp-load.php';
if (!is_readable($load)) {
    fwrite(STDERR, "ERROR: no wp-load.php at {$load}\n");
    exit(2);
}
define('WP_USE_THEMES', false);
require_once $load;

$channel = 'UC_GX4vXRQrN4GsvSfgmZxYw';
$ids = get_transient('af_yt_ids3_' . $channel);
if (!is_array($ids) || !$ids) $ids = get_option('af_yt_ids3_lastgood_' . $channel);
if (!is_array($ids)) {
    fwrite(STDERR, "ERROR: no video ids stored for the row\n");
    exit(3);
}

$ids = array_values(array_filter($ids, function ($v) {
    return is_string($v) && preg_match('/^[A-Za-z0-9_-]{11}$/', $v);
}));

if (isset($argv[1]) && $argv[1] === 'missing') {
    $have = get_option('af_pim_local');
    if (!is_array($have)) $have = array();
    $up  = wp_get_upload_dir();
    $dir = trailingslashit($up['basedir']) . 'pim/';
    $ids = array_values(array_filter($ids, function ($v) use ($have, $dir) {
        return !isset($have[$v]) && !file_exists($dir . $v . '.mp4');
    }));
}

// An empty list is a legitimate answer ("nothing missing") and must be
// distinguishable from a failure, so success is exit 0 with no output and
// every failure above exits non-zero with a reason on stderr.
foreach ($ids as $id) echo $id, "\n";
exit(0);
