<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Tests inc/placeholder-products.php: what it takes off sale, and what it
 * leaves alone.
 *
 * Owner, 1 Oct, with a screenshot of TMP-1246: "remove this". #25240 goes
 * private. Its stored name carries "&amp;" where the shop prints "&", so the
 * name check must not depend on how WordPress stored the ampersand, while a
 * product renamed since, or one no longer published, is still left alone.
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
/** A fresh site: the given products, the option from the last deploy, then one request. */
function request($products, $rev = '5') {
    $GLOBALS['P'] = $products; $GLOBALS['FIRED'] = array();
    $GLOBALS['OPT'] = $rev === null ? array() : array('af_placeholder_products_rev' => $rev);
    $GLOBALS['META'] = array(3362 => array('_taf_art_code' => 'HD - 080004-5030'));
    foreach ($GLOBALS['HOOKS']['wp_loaded'] as $cb) { $cb(); }
    return (string) get_option('af_placeholder_products', '');
}
$STORED = 'Vaishnava Symbols Trio Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room &amp; Home Spiritual Wall Décor';
$ganesha = array('status' => 'publish', 'name' => 'Divine Lord Ganesha', 'vis' => 'visible');

echo "=== #25240 as WordPress stores it, \"&amp;\" in the name ===\n";
$log = request(array(25240 => array('status' => 'publish', 'name' => $STORED, 'vis' => 'visible'), 3362 => $ganesha));
check('#25240 goes private', get_post_status(25240), 'private');
check('the log says so', strpos($log, '25240 publish -> private') !== false, true);
check('the product pages and the shop are purged', in_array('litespeed_purge_posttype', $GLOBALS['FIRED'], true), true);
check('the sitemap is rebuilt', strpos($log, 'sitemap: ') !== false, true);
check('nothing is deleted', isset($GLOBALS['P'][25240]), true);
check('#3362 stays in the shop', $GLOBALS['P'][3362]['vis'], 'visible');

echo "\n=== the same name stored with a plain \"&\" and &#8211; dashes ===\n";
request(array(25240 => array('status' => 'publish', 'name' => str_replace(array('&amp;', '–'), array('&', '&#8211;'), $STORED), 'vis' => 'visible')));
check('#25240 goes private', get_post_status(25240), 'private');

echo "\n=== renamed since the owner asked ===\n";
$log = request(array(25240 => array('status' => 'publish', 'name' => 'Something Else Canvas Wall Art', 'vis' => 'visible')));
check('left published', get_post_status(25240), 'publish');
check('the log says why', strpos($log, '25240 left alone: now named') !== false, true);

echo "\n=== already private (taken off by hand) ===\n";
$log = request(array(25240 => array('status' => 'private', 'name' => $STORED, 'vis' => 'visible')));
check('stays private', get_post_status(25240), 'private');
check('the log reads "already private"', strpos($log, '25240 already private') !== false, true);

echo "\n=== gone ===\n";
$log = request(array());
check('the log reads "not found"', strpos($log, '25240 not found') !== false, true);

echo "\n=== a second request after the same deploy does nothing ===\n";
request(array(25240 => array('status' => 'publish', 'name' => $STORED, 'vis' => 'visible')), AF_PLACEHOLDER_PRODUCTS_REV);
check('left published', get_post_status(25240), 'publish');
check('no purge', $GLOBALS['FIRED'], array());

echo "\n=== af_placeholder_same_name ===\n";
check('"&amp;" and "&" are the same', af_placeholder_same_name('A &amp; B', 'A & B'), true);
check('case and runs of spaces aside', af_placeholder_same_name("  a   &  b ", 'A & B'), true);
check('a different name is not', af_placeholder_same_name('A & C', 'A & B'), false);
check('the delete list is still empty', af_placeholder_products_delete(), array());

printf("\n%d passed, %d failed\n", $pass, $fail);
exit($fail ? 1 : 0);
