<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Tests inc/pim-r2-pull.php with WordPress and R2 stubbed:
 *     php tools/test-pim-r2-pull.php
 */
define('ABSPATH', '/nowhere/');
$GLOBALS['opt'] = array(); $GLOBALS['hooks'] = array(); $GLOBALS['purged'] = array(); $GLOBALS['r2'] = array();
$tmp = sys_get_temp_dir() . '/af-pim-test-' . getmypid();
@mkdir($tmp . '/pim', 0777, true);
function add_action($h, $f) { $GLOBALS['hooks'][] = $h; }
function do_action($h, $a = null) { if ($h === 'litespeed_purge_url') $GLOBALS['purged'][] = $a; }
function get_transient($k) { return false; }
function get_option($k) { return isset($GLOBALS['opt'][$k]) ? $GLOBALS['opt'][$k] : false; }
function update_option($k, $v) { $GLOBALS['opt'][$k] = $v; }
function wp_get_upload_dir() { return array('basedir' => $GLOBALS['tmp']); }
function trailingslashit($s) { return rtrim($s, '/') . '/'; }
function wp_mkdir_p($d) { return @mkdir($d, 0777, true) || is_dir($d); }
function home_url($p = '') { return 'https://x.test' . $p; }
function is_wp_error($x) { return false; }
function af_r2_config() { return array('ok' => 1); }
function af_r2_object_url($obj, $ttl) { return 'r2://' . $obj; }
function af_r2_list_objects() { if (!empty($GLOBALS['list_fails'])) return null; $o = array(); foreach ($GLOBALS['r2'] as $k => $v) $o[$k] = strlen($v); return $o; }
function wp_remote_retrieve_response_code($r) { return $r['code']; }
// Fake R2: object name => body (missing = 404).
function wp_remote_get($url, $args) {
    $obj = substr($url, 5);
    if (!isset($GLOBALS['r2'][$obj])) { file_put_contents($args['filename'], 'NoSuchKey'); return array('code' => 404); }
    file_put_contents($args['filename'], $GLOBALS['r2'][$obj]);
    return array('code' => 200);
}
require __DIR__ . '/../inc/pim-r2-pull.php';

$pass = 0; $fail = 0;
function same($label, $got, $want) { global $pass, $fail;
    if ($got === $want) { $pass++; echo "  OK    $label\n"; }
    else { $fail++; echo "  FAIL  $label\n        got  " . var_export($got, true) . "\n        want " . var_export($want, true) . "\n"; } }

$mp4 = "\0\0\0\x18ftypmp42" . str_repeat('x', 60 * 1024);
$ids = array('AAAAAAAAAAA', 'BBBBBBBBBBB', 'CCCCCCCCCCC', 'DDDDDDDDDDD', 'EEEEEEEEEEE', 'FFFFFFFFFFF', 'bad id');
$GLOBALS['opt']['af_yt_ids3_lastgood_UC_GX4vXRQrN4GsvSfgmZxYw'] = $ids;
file_put_contents("$tmp/pim/AAAAAAAAAAA.mp4", $mp4);              // already on the site
file_put_contents("$tmp/pim/BBBBBBBBBBB.anim.mp4", $mp4);         // only the built clip
$GLOBALS['r2'] = array(
    'pim/BBBBBBBBBBB.mp4' => $mp4,                                  // real reel waiting in R2
    'pim/CCCCCCCCCCC.mp4' => '<html>Access denied</html>' . str_repeat(' ', 60 * 1024), // not an MP4
    'pim/DDDDDDDDDDD.mp4' => "\0\0\0\x18ftypmp42tiny",              // too small
    'pim/FFFFFFFFFFF.mp4' => $mp4,                                  // 5th in line: still reached
);

echo "=== which reels it looks for ===\n";
same('missing = no real .mp4 yet (built clips still count as missing; bad ids skipped)',
    af_pim_r2_missing_ids("$tmp/pim/"), array('BBBBBBBBBBB', 'CCCCCCCCCCC', 'DDDDDDDDDDD', 'EEEEEEEEEEE', 'FFFFFFFFFFF'));

echo "\n=== one run ===\n";
$r = af_pim_r2_pull();
same('only tries reels waiting in R2 (four here)', $r['checked'], 4);
same('adds the real reels from R2, even 5th in the row', $r['added'], array('BBBBBBBBBBB', 'FFFFFFFFFFF'));
same('saved as uploads/pim/<id>.mp4', is_file("$tmp/pim/BBBBBBBBBBB.mp4"), true);
same('keeps the built clip (harmless, the card prefers the real reel)', is_file("$tmp/pim/BBBBBBBBBBB.anim.mp4"), true);
same('an error page saved as .mp4 is rejected', is_file("$tmp/pim/CCCCCCCCCCC.mp4"), false);
same('a too-small file is rejected', is_file("$tmp/pim/DDDDDDDDDDD.mp4"), false);
same('two rejected, named', count($r['rejected']), 2);
same('not in R2 yet: counted, nothing saved', array($r['not_in_r2'], is_file("$tmp/pim/EEEEEEEEEEE.mp4")), array(1, false));
same('no .part files left behind', glob("$tmp/pim/*.part"), array());
same('home page purged once, after adding', $GLOBALS['purged'], array('https://x.test/'));
same('last run stored for checking', isset($GLOBALS['opt']['af_pim_r2_last']['time']), true);

echo "\n=== nothing new ===\n";
$GLOBALS['purged'] = array(); $GLOBALS['r2'] = array();
$r = af_pim_r2_pull();
same('no purge when nothing was added', $GLOBALS['purged'], array());

echo "\n=== R2 listing fails ===\n";
$GLOBALS['list_fails'] = 1; $GLOBALS['r2'] = array('pim/EEEEEEEEEEE.mp4' => $mp4);
$r = af_pim_r2_pull();
same('still works by asking for each file', $r['added'], array('EEEEEEEEEEE'));

echo "\n=== hooks ===\n";
same('runs on its own hourly event', in_array('af_pim_r2_pull', $GLOBALS['hooks'], true), true);

array_map('unlink', glob("$tmp/pim/*")); @rmdir("$tmp/pim"); @rmdir($tmp);
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
