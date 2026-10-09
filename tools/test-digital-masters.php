<?php
/**
 * Tests for inc/digital-masters.php (digital downloads from Cloudflare R2),
 * outside WordPress: the signature against AWS's own published example, the
 * art code => file matching, and the delivery hooks in the order WooCommerce
 * 11.1.2 calls them (filepath filter, checks, count, method filter, redirect).
 *
 * Run: php tools/test-digital-masters.php
 */
define('ABSPATH', __DIR__ . '/');

// ── the little of WordPress / WooCommerce the module touches ──
$GLOBALS['opts'] = array(); $GLOBALS['hooks'] = array(); $GLOBALS['meta'] = array(); $GLOBALS['titles'] = array();
$GLOBALS['remote'] = null;
function get_option($k, $d = false) { return array_key_exists($k, $GLOBALS['opts']) ? $GLOBALS['opts'][$k] : $d; }
function update_option($k, $v, $a = null) { $GLOBALS['opts'][$k] = $v; return true; }
function delete_option($k) { unset($GLOBALS['opts'][$k]); return true; }
function add_action($h, $f, $p = 10, $n = 1) { $GLOBALS['hooks'][$h][] = $f; }
function add_filter($h, $f, $p = 10, $n = 1) { $GLOBALS['hooks'][$h][] = $f; }
function apply($h, ...$args) { foreach ($GLOBALS['hooks'][$h] ?? array() as $f) $args[0] = $f(...$args); return $args[0]; }
function get_post_meta($id, $k, $s = false) { return $GLOBALS['meta'][$id][$k] ?? ''; }
function get_post_field($f, $id, $ctx = 'display') { return $f === 'post_title' ? ($GLOBALS['titles'][$id] ?? '') : ''; }
function wp_strip_all_tags($s) { return strip_tags($s); }
function wp_next_scheduled($h) { return true; }
function wp_remote_get($u, $a = array()) { $GLOBALS['last_url'] = $u; return $GLOBALS['remote']; }
function is_wp_error($x) { return false; }
function wp_remote_retrieve_response_code($r) { return $r['code']; }
function wp_remote_retrieve_body($r) { return $r['body']; }
function size_format($b, $d = 0) { $u = array('B', 'KB', 'MB', 'GB', 'TB'); $i = 0; while ($b >= 1000 && $i < 4) { $b /= 1000; $i++; } return number_format($b, $d) . ' ' . $u[$i]; }
class FakeWpdb { public $postmeta = 'wp_postmeta'; public $posts = 'wp_posts'; public $codes = array();
    function get_col($q) { return $this->codes; } }
$GLOBALS['wpdb'] = new FakeWpdb();
class FakeProduct { private $id, $sku; function __construct($id, $sku) { $this->id = $id; $this->sku = $sku; }
    function get_id() { return $this->id; } function get_sku() { return $this->sku; } }

require __DIR__ . '/../inc/digital-masters.php';

$pass = 0; $fail = 0;
function ok($cond, $what) { global $pass, $fail; if ($cond) { $pass++; echo "  OK    $what\n"; } else { $fail++; echo "  FAIL  $what\n"; } }

echo "=== signature: AWS's published presigned-URL example (S3 SigV4 docs, GET /test.txt) ===\n";
$u = af_r2_presign('examplebucket.s3.amazonaws.com', '/test.txt', 'AKIAIOSFODNN7EXAMPLE', 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
                   'us-east-1', 86400, array(), gmmktime(0, 0, 0, 5, 24, 2013));
ok(strpos($u, 'X-Amz-Signature=aeeed9bbccd4d02ee5c0109b86d86835f995330da4c265957d157751f604d404') !== false, 'signature matches AWS\'s example');
ok(strpos($u, 'X-Amz-Credential=AKIAIOSFODNN7EXAMPLE%2F20130524%2Fus-east-1%2Fs3%2Faws4_request') !== false, 'credential scope as AWS writes it');

echo "\n=== not set up: nothing changes ===\n";
$GLOBALS['meta'][101] = array('_taf_art_code' => 'RK-010008-3040');
ok(af_r2_config() === null, 'no settings: not connected');
ok(apply('woocommerce_download_product_filepath', 'https://theartframer.us/wp-content/uploads/a.jpg', 'e', null, new FakeProduct(101, 'RK-010008-3040'), null) === 'https://theartframer.us/wp-content/uploads/a.jpg', 'download path left as WooCommerce has it');
ok(apply('woocommerce_file_download_method', 'force', 101, 'https://theartframer.us/wp-content/uploads/a.jpg') === 'force', 'download method left alone');

echo "\n=== the bucket listing and the art code => file index ===\n";
update_option('af_r2_config', array('account' => 'acc123', 'bucket' => 'theartframer-masters', 'key' => 'AK', 'secret' => 'SK'));
$GLOBALS['wpdb']->codes = array('RK-010008-3040', 'SL-150004-5030', 'LI-190002-3050', 'HD-080018-5030', 'CO-240006-0000');
$GLOBALS['remote'] = array('code' => 200, 'body' => '<?xml version="1.0"?><ListBucketResult>'
    . '<Contents><Key>RK-010008-3040.tif</Key><Size>1200000000</Size></Contents>'
    . '<Contents><Key>RK-010008-3040 small.jpg</Key><Size>3000000</Size></Contents>'
    . '<Contents><Key>masters/SL-150004-5030 Translucent Poppy Trio.tif</Key><Size>900000000</Size></Contents>'
    . '<Contents><Key>LI-190002-30501.tif</Key><Size>5</Size></Contents>'
    . '<Contents><Key>hd-080018-5030_v2.psd</Key><Size>700</Size></Contents>'
    . '<Contents><Key>hd-080018-5030_v3.psd</Key><Size>800</Size></Contents>'
    . '<Contents><Key>masters/</Key><Size>0</Size></Contents>'
    . '<IsTruncated>false</IsTruncated></ListBucketResult>');
$idx = af_r2_rebuild_index();
ok(strpos($GLOBALS['last_url'], 'https://acc123.r2.cloudflarestorage.com/theartframer-masters?') === 0 && strpos($GLOBALS['last_url'], 'list-type=2') !== false, 'lists the bucket with a signed ListObjectsV2 request');
ok(($idx['RK-010008-3040']['key'] ?? '') === 'RK-010008-3040.tif', 'exact file name wins over "code + words"');
ok(($idx['SL-150004-5030']['key'] ?? '') === 'masters/SL-150004-5030 Translucent Poppy Trio.tif', 'code followed by words, inside a folder');
ok(!isset($idx['LI-190002-3050']), 'a longer number is not the code (LI-190002-30501)');
ok(($idx['HD-080018-5030']['key'] ?? '') === 'hd-080018-5030_v3.psd', 'lower case and "_" match; the larger file wins');
ok(!isset($idx['CO-240006-0000']), 'a code with no file has no master');
ok(get_option('af_r2_index')['objects'] === 6, 'folders are not counted as files');
ok(get_option('af_r2_index')['bytes'] === 2103001505, 'the bucket\'s total size is kept');
ok(af_r2_usage_line(get_option('af_r2_index')) === '2.1 GB of the free 10 GB used (21%).', 'shown against the free 10 GB');
ok(af_r2_usage_line(array('bytes' => 7252569649)) === '7.3 GB of the free 10 GB used (72%).', 'the size and the share count GB the same way (not "6.8 GB ... 72%")');
ok(strpos(af_r2_usage_line(array('bytes' => 9.5e9)), 'Nearly full') === 0, 'warns from 90%');
ok(strpos(af_r2_usage_line(array('bytes' => 10.2e9)), 'OVER THE FREE 10 GB') === 0, 'says so plainly above 10 GB');

echo "\n=== delivery, in WooCommerce 11.1.2's order ===\n";
$GLOBALS['titles'][101] = 'Sleeping Baby Krishna Canvas Wall Art – 30x40 Inches';
$file = apply('woocommerce_download_product_filepath', 'https://theartframer.us/wp-content/uploads/a.jpg', 'e', null, new FakeProduct(101, 'RK-010008-3040'), null);
ok(strpos($file, 'https://acc123.r2.cloudflarestorage.com/theartframer-masters/RK-010008-3040.tif?') === 0, 'step 1: the path WooCommerce hands out is the signed R2 link');
ok(strpos($file, 'X-Amz-Expires=300') !== false, 'the link works for 5 minutes');
ok(strpos(rawurldecode($file), 'filename="Sleeping Baby Krishna Canvas Wall Art RK-010008-3040.tif"') !== false, 'saved under the product name and art code');
$GLOBALS['titles'][101] = 'Radha Krishna Mosaic Art Canvas Wall Art 3&#215;4 Feet &#8211; Floating Frame &amp; More';
ok(af_r2_download_name(101, array('key' => 'RK-010018-3050-DD.jpg', 'code' => 'RK-010018-3050')) === 'Radha Krishna Mosaic Art Canvas Wall Art 3x4 Feet RK-010018-3050.jpg', 'web codes in the title are read as letters (3&#215;4 is 3x4, not 32154)');
$GLOBALS['titles'][101] = "Krishna's Flute & Peacock 3\u{00D7}4 Feet \u{2013} Floating Frame";
ok(strpos(rawurldecode(af_r2_object_url('a.jpg', 300, af_r2_download_name(101, array('key' => 'a.jpg', 'code' => 'RK-010008-3040')))), 'filename="Krishnas Flute Peacock 3x4 Feet RK-010008-3040.jpg"') !== false, 'a stored "\u{00D7}" is an x, and no double spaces are left behind');
$GLOBALS['titles'][101] = 'Sleeping Baby Krishna Canvas Wall Art \u{2013} 30x40 Inches';
// WooCommerce: checks, save, count and log happen here, between the two filters.
ok(apply('woocommerce_file_download_method', 'force', 101, $file) === 'redirect', 'step 2: an R2 link is a redirect, never streamed by the server');
ok(apply('woocommerce_file_download_method', 'force', 101, 'https://theartframer.us/wp-content/uploads/a.jpg') === 'force', 'other files keep the shop\'s method');
ok(apply('woocommerce_file_download_method', 'force', 101, 'https://evil.example/acc123.r2.cloudflarestorage.com/x') === 'force', 'only links on the bucket\'s own host count');
ok(empty($GLOBALS['hooks']['woocommerce_download_product']), 'nothing leaves before WooCommerce has counted the download');

echo "\n=== who gets the master ===\n";
$GLOBALS['meta'][102] = array('_taf_art_code' => 'SL-150004-5030');
$GLOBALS['meta'][103] = array('_taf_art_code' => 'SL-150004-5030');
ok(strpos(apply('woocommerce_download_product_filepath', 'orig', 'e', null, new FakeProduct(103, 'SL-150004-5030'), null), 'r2.cloudflarestorage.com') !== false, 'the product carrying the code (SKU = code) gets it');
ok(apply('woocommerce_download_product_filepath', 'orig', 'e', null, new FakeProduct(102, 'SL-150004-5030A'), null) === 'orig', 'a lettered SKU sharing the code (the gift card) keeps its own file');
$GLOBALS['meta'][104] = array('_taf_art_code' => 'CO-240006-0000');
ok(apply('woocommerce_download_product_filepath', 'orig', 'e', null, new FakeProduct(104, 'CO-240006-0000'), null) === 'orig', 'no master in the bucket: today\'s file');
ok(apply('woocommerce_download_product_filepath', 'orig', 'e', null, false, null) === 'orig', 'no product: unchanged');

echo "\n=== a failed listing ===\n";
$GLOBALS['remote'] = array('code' => 403, 'body' => '<Error><Code>AccessDenied</Code></Error>');
ok(af_r2_rebuild_index() === false, 'a refused listing is reported, not taken as "no files"');
ok(isset(get_option('af_r2_index')['map']['RK-010008-3040']), 'the last good index stays in place');
ok(strpos((string) get_option('af_r2_last_error'), 'HTTP 403') === 0, 'and the reason is kept for the admin page');

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
