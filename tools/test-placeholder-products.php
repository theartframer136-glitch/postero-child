<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Tests inc/placeholder-products.php: what it takes off sale, and what it
 * leaves alone.
 *
 * Owner, 1 Oct: TMP-1246, TMP-1233, TMP-1229, TMP-1134, TMP-1120, TMP-1078 and
 * TMP-1071, "don't delete them, make them private" (revision 7); then 45 more,
 * TMP-1124 to TMP-1310, "make them private too" (revision 8); then five from
 * the temporary-code sheet, "remove this ... from the website" (revision 9).
 * Each goes private
 * while it still carries that art code, however the code is spaced or dashed;
 * one whose code has changed since is left alone, and nothing is ever deleted.
 *
 * Runs the REAL file with WordPress and WooCommerce stubbed. Plain `php`:
 *
 *     php tools/test-placeholder-products.php
 */
define('ABSPATH', '/nowhere/');
$GLOBALS['HOOKS'] = array(); $GLOBALS['OPT'] = array(); $GLOBALS['P'] = array(); $GLOBALS['META'] = array(); $GLOBALS['FIRED'] = array();
function add_action($h, $cb, $prio = 10) { $GLOBALS['HOOKS'][$h][] = $cb; }
function do_action($h) { $GLOBALS['FIRED'][] = $h; }
function get_option($k, $d = false) { return array_key_exists($k, $GLOBALS['OPT']) ? $GLOBALS['OPT'][$k] : $d; }
function update_option($k, $v, $autoload = null) { $GLOBALS['OPT'][$k] = $v; return true; }
function get_post_status($id) { return isset($GLOBALS['P'][$id]) ? $GLOBALS['P'][$id]['status'] : false; }
function get_post($id) { return isset($GLOBALS['P'][$id]) ? (object) array('ID' => $id) : null; }
function clean_post_cache($id) {}
function get_post_meta($id, $k, $single = false) { return isset($GLOBALS['META'][$id][$k]) ? $GLOBALS['META'][$id][$k] : ''; }
function wc_get_page_permalink($p) { return 'https://theartframer.us/shop/'; }
class Fake_Product {
    public $id; function __construct($id) { $this->id = $id; }
    function get_status() { return $GLOBALS['P'][$this->id]['status']; }
    function get_name() { return $GLOBALS['P'][$this->id]['name']; }
    function set_status($s) { $GLOBALS['P'][$this->id]['status'] = $s; }
    function get_catalog_visibility() { return $GLOBALS['P'][$this->id]['vis']; }
    function set_catalog_visibility($v) { $GLOBALS['P'][$this->id]['vis'] = $v; }
    function save() {}
    function delete($force) { unset($GLOBALS['P'][$this->id]); }
}
function wc_get_product($id) { return isset($GLOBALS['P'][$id]) ? new Fake_Product($id) : false; }

require __DIR__ . '/../inc/placeholder-products.php';

$pass = 0; $fail = 0;
function check($what, $got, $want) {
    global $pass, $fail;
    if ($got === $want) { $pass++; echo "  OK    $what\n"; }
    else { $fail++; echo "  FAIL  $what: got " . var_export($got, true) . ", want " . var_export($want, true) . "\n"; }
}
/** A fresh site: the given products and art codes, the option from the last deploy, then one request. */
function request($products, $codes, $rev = '8') {
    $GLOBALS['P'] = $products; $GLOBALS['FIRED'] = array();
    $GLOBALS['OPT'] = $rev === null ? array() : array('af_placeholder_products_rev' => $rev);
    $GLOBALS['META'] = array(3362 => array('_taf_art_code' => 'HD - 080004-5030'));
    foreach ($codes as $id => $c) { $GLOBALS['META'][$id]['_taf_art_code'] = $c; }
    foreach ($GLOBALS['HOOKS']['wp_loaded'] as $cb) { $cb(); }
    return (string) get_option('af_placeholder_products', '');
}
$SEVEN = array(25240 => 'TMP-1246', 22747 => 'TMP-1233', 22016 => 'TMP-1229', 29342 => 'TMP-1134',
               28422 => 'TMP-1120', 23789 => 'TMP-1078', 22383 => 'TMP-1071');
// revision 8, in the order the owner listed them
$MORE = array(27325 => 'TMP-1310', 31527 => 'TMP-1304', 31456 => 'TMP-1303', 31395 => 'TMP-1302',
               31273 => 'TMP-1301', 31150 => 'TMP-1299', 31088 => 'TMP-1298', 30966 => 'TMP-1297',
               29751 => 'TMP-1289', 30093 => 'TMP-1290', 30154 => 'TMP-1291', 30276 => 'TMP-1292',
               30338 => 'TMP-1293', 30775 => 'TMP-1294', 29159 => 'TMP-1280', 29220 => 'TMP-1281',
               29281 => 'TMP-1282', 29395 => 'TMP-1283', 29456 => 'TMP-1284', 29517 => 'TMP-1285',
               29578 => 'TMP-1286', 28473 => 'TMP-1273', 28534 => 'TMP-1274', 28717 => 'TMP-1275',
               28962 => 'TMP-1278', 27133 => 'TMP-1257', 27194 => 'TMP-1258', 27264 => 'TMP-1259',
               27388 => 'TMP-1260', 27449 => 'TMP-1261', 27510 => 'TMP-1262', 27572 => 'TMP-1263',
               27633 => 'TMP-1264', 27750 => 'TMP-1266', 27811 => 'TMP-1267', 27981 => 'TMP-1268',
               28103 => 'TMP-1269', 28164 => 'TMP-1270', 28225 => 'TMP-1271', 30032 => 'TMP-1142',
               30409 => 'TMP-1147', 30714 => 'TMP-1148', 31027 => 'TMP-1153', 28656 => 'TMP-1124',
               29084 => 'TMP-1130');
$FIVE = array(8711 => 'TMP-1216', 8869 => 'TMP-1218', 26628 => 'TMP-1256', 30836 => 'TMP-1295', 8805 => 'TMP-1309');
$ALL = $SEVEN + $MORE + $FIVE;
$EARLIER = $SEVEN + $MORE;   // private since revisions 6-8
$ganesha = array('status' => 'publish', 'name' => 'Divine Lord Ganesha', 'vis' => 'visible');
$live = function ($status = 'publish') use ($ALL) {
    $p = array();
    foreach ($ALL as $id => $c) { $p[$id] = array('status' => $status, 'name' => "product $id Living Room &amp; Home", 'vis' => 'visible'); }
    return $p;
};

echo "=== the list is the owner's 7 + 45 + 5 codes ===\n";
check('57 products', count(af_placeholder_products()), 57);
check('each with the code the owner named, in order', af_placeholder_products(), $ALL);
check('no code twice', count(array_unique(af_placeholder_products())), 57);

echo "\n=== after revision 8: the 52 private, the five published ===\n";
$site = $live(); foreach ($EARLIER as $id => $c) { $site[$id]['status'] = 'private'; } $site[3362] = $ganesha;
$log = request($site, $ALL);
$priv = 0; foreach ($ALL as $id => $c) { if (get_post_status($id) === 'private') { $priv++; } }
check('all 57 are private', $priv, 57);
check('the 52 read "already private"', substr_count($log, 'already private'), 52);
check('the five read publish -> private', substr_count($log, 'publish -> private'), 5);
foreach ($FIVE as $id => $c) { check("#$id ($c) is private", get_post_status($id), 'private'); }
check('#31456 (where #30905 led) is private', get_post_status(31456), 'private');
check('#28962 (where #28839 and #27695 led) is private', get_post_status(28962), 'private');
check('nothing is deleted', count(array_intersect_key($GLOBALS['P'], $ALL)), 57);
check('the product pages and the shop are purged', in_array('litespeed_purge_posttype', $GLOBALS['FIRED'], true), true);
check('the sitemap is rebuilt', strpos($log, 'sitemap: ') !== false, true);
check('#3362 stays in the shop', $GLOBALS['P'][3362]['vis'], 'visible');

echo "\n=== the code as the renumber pass may space it ===\n";
request(array(22747 => array('status' => 'publish', 'name' => 'x', 'vis' => 'visible')), array(22747 => 'TMP - 1233'));
check('"TMP - 1233" is TMP-1233', get_post_status(22747), 'private');

echo "\n=== a product whose code has changed since the owner asked ===\n";
$log = request(array(22016 => array('status' => 'publish', 'name' => 'x', 'vis' => 'visible')), array(22016 => 'SH-040011-3050'));
check('left published', get_post_status(22016), 'publish');
check('the log says why', strpos($log, '22016 left alone: art code is now "SH-040011-3050"') !== false, true);

echo "\n=== the name no longer matters ===\n";
request(array(29342 => array('status' => 'publish', 'name' => 'Renamed by the owner', 'vis' => 'visible')), array(29342 => 'TMP-1134'));
check('a renamed product with its code goes private', get_post_status(29342), 'private');

echo "\n=== gone ===\n";
$log = request(array(), array());
$nf = 0; foreach ($ALL as $id => $c) { $nf += substr_count($log, $id . ' not found'); }
check('the log reads "not found" for each of the 57', $nf, 57);

echo "\n=== a second request after the same deploy does nothing ===\n";
request($live(), $SEVEN, AF_PLACEHOLDER_PRODUCTS_REV);
check('all left published', array_unique(array_map('get_post_status', array_keys($ALL))), array('publish'));
check('no purge', $GLOBALS['FIRED'], array());

echo "\n=== af_placeholder_code_key and af_placeholder_same_name ===\n";
check('spaces, hyphens and en dashes aside', af_placeholder_code_key('HD – 080004-5030'), af_placeholder_code_key('hd-080004-5030'));
check('a different code is not the same', af_placeholder_code_key('TMP-1233') === af_placeholder_code_key('TMP-1234'), false);
check('"&amp;" and "&" are the same name', af_placeholder_same_name('A &amp; B', 'A & B'), true);
check('a different name is not', af_placeholder_same_name('A & C', 'A & B'), false);
check('the delete list is still empty', af_placeholder_products_delete(), array());

printf("\n%d passed, %d failed\n", $pass, $fail);
exit($fail ? 1 : 0);
