<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Tests inc/placeholder-products.php: what it takes off sale, and what it
 * leaves alone.
 *
 * Owner, 1 Oct: TMP-1246, TMP-1233, TMP-1229, TMP-1134, TMP-1120, TMP-1078 and
 * TMP-1071, "don't delete them, make them private". Each goes private while it
 * still carries that art code, however the code is spaced or dashed; one whose
 * code has changed since is left alone, and nothing is ever deleted.
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
function request($products, $codes, $rev = '6') {
    $GLOBALS['P'] = $products; $GLOBALS['FIRED'] = array();
    $GLOBALS['OPT'] = $rev === null ? array() : array('af_placeholder_products_rev' => $rev);
    $GLOBALS['META'] = array(3362 => array('_taf_art_code' => 'HD - 080004-5030'));
    foreach ($codes as $id => $c) { $GLOBALS['META'][$id]['_taf_art_code'] = $c; }
    foreach ($GLOBALS['HOOKS']['wp_loaded'] as $cb) { $cb(); }
    return (string) get_option('af_placeholder_products', '');
}
$SEVEN = array(25240 => 'TMP-1246', 22747 => 'TMP-1233', 22016 => 'TMP-1229', 29342 => 'TMP-1134',
               28422 => 'TMP-1120', 23789 => 'TMP-1078', 22383 => 'TMP-1071');
$ganesha = array('status' => 'publish', 'name' => 'Divine Lord Ganesha', 'vis' => 'visible');
$live = function ($status = 'publish') use ($SEVEN) {
    $p = array();
    foreach ($SEVEN as $id => $c) { $p[$id] = array('status' => $status, 'name' => "product $id Living Room &amp; Home", 'vis' => 'visible'); }
    return $p;
};

echo "=== the list is the owner's seven codes ===\n";
check('seven products', count(af_placeholder_products()), 7);
check('each with the code the owner named', af_placeholder_products(), $SEVEN);

echo "\n=== all seven published, #25240 already private from revision 6 ===\n";
$site = $live(); $site[25240]['status'] = 'private'; $site[3362] = $ganesha;
$log = request($site, $SEVEN);
foreach ($SEVEN as $id => $c) { check("#$id ($c) is private", get_post_status($id), 'private'); }
check('#25240 reads "already private"', strpos($log, '25240 already private') !== false, true);
check('the six read publish -> private', substr_count($log, 'publish -> private'), 6);
check('nothing is deleted', count(array_intersect_key($GLOBALS['P'], $SEVEN)), 7);
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
$nf = 0; foreach ($SEVEN as $id => $c) { $nf += substr_count($log, $id . ' not found'); }
check('the log reads "not found" for each of the seven', $nf, 7);

echo "\n=== a second request after the same deploy does nothing ===\n";
request($live(), $SEVEN, AF_PLACEHOLDER_PRODUCTS_REV);
check('all left published', array_unique(array_map('get_post_status', array_keys($SEVEN))), array('publish'));
check('no purge', $GLOBALS['FIRED'], array());

echo "\n=== af_placeholder_code_key and af_placeholder_same_name ===\n";
check('spaces, hyphens and en dashes aside', af_placeholder_code_key('HD – 080004-5030'), af_placeholder_code_key('hd-080004-5030'));
check('a different code is not the same', af_placeholder_code_key('TMP-1233') === af_placeholder_code_key('TMP-1234'), false);
check('"&amp;" and "&" are the same name', af_placeholder_same_name('A &amp; B', 'A & B'), true);
check('a different name is not', af_placeholder_same_name('A & C', 'A & B'), false);
check('the delete list is still empty', af_placeholder_products_delete(), array());

printf("\n%d passed, %d failed\n", $pass, $fail);
exit($fail ? 1 : 0);
