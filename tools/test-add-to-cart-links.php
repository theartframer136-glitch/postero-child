<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Tests inc/add-to-cart-links.php with WordPress stubbed:
 *     php tools/test-add-to-cart-links.php
 */
define('ABSPATH', '/nowhere/');
$GLOBALS['hooks'] = array();
function add_action($h, $f, $p = 10) { $GLOBALS['hooks'][] = array($h, $p); }
function absint($v) { return abs((int) $v); }
function home_url($p = '') { return 'https://x.test' . $p; }
function is_admin() { return !empty($GLOBALS['admin']); }
function wp_doing_ajax() { return !empty($GLOBALS['ajax']); }
function get_permalink($id) { return 'https://x.test/product/p' . $id . '/'; }
class P { public $id, $type, $parent, $status;
    function __construct($id, $type, $parent = 0, $status = 'publish') { $this->id = $id; $this->type = $type; $this->parent = $parent; $this->status = $status; }
    function is_type($t) { return $this->type === $t; } function get_parent_id() { return $this->parent; }
    function get_status() { return $this->status; } function get_id() { return $this->id; } }
function wc_get_product($id) {
    $all = array(8866 => new P(8866, 'simple'), 501 => new P(501, 'variation', 500), 500 => new P(500, 'variable'), 77 => new P(77, 'simple', 0, 'draft'));
    return isset($all[$id]) ? $all[$id] : false;
}
require __DIR__ . '/../inc/add-to-cart-links.php';

$pass = 0; $fail = 0;
function same($label, $got, $want) { global $pass, $fail;
    if ($got === $want) { $pass++; echo "  OK    $label\n"; }
    else { $fail++; echo "  FAIL  $label\n        got  " . var_export($got, true) . "\n        want " . var_export($want, true) . "\n"; } }
function req($method, $get) { $_SERVER['REQUEST_METHOD'] = $method; $_GET = $get; $GLOBALS['ajax'] = false; $GLOBALS['admin'] = false; }

echo "=== which requests are turned away ===\n";
req('GET', array('add-to-cart' => '8866', 'layout' => 'list'));
same('a GET link with add-to-cart is', af_atc_is_direct_link(), true);
req('HEAD', array('add-to-cart' => '8866'));
same('a HEAD of the same link is', af_atc_is_direct_link(), true);
req('POST', array());
$_GET = array(); $_POST = array('add-to-cart' => '8866');
same("the product page's posted form is not", af_atc_is_direct_link(), false);
req('POST', array('add-to-cart' => '8866'));
same('a post to an address that also carries add-to-cart is not', af_atc_is_direct_link(), false);
req('GET', array('add-to-cart' => '8866')); $GLOBALS['ajax'] = true;
same('an admin-ajax request is not', af_atc_is_direct_link(), false);
req('GET', array('add-to-cart' => '8866', 'wc-ajax' => 'add_to_cart'));
same('a wc-ajax request is not', af_atc_is_direct_link(), false);
req('GET', array('add-to-cart' => '8866')); $GLOBALS['admin'] = true;
same('wp-admin is not', af_atc_is_direct_link(), false);
req('GET', array('layout' => 'list'));
same('a page without add-to-cart is not', af_atc_is_direct_link(), false);
req('GET', array('add-to-cart' => ''));
same('an empty add-to-cart is not', af_atc_is_direct_link(), false);

echo "\n=== where they go ===\n";
same('a product: its own page', af_atc_link_target('8866'), 'https://x.test/product/p8866/');
same("a variation: its product's page", af_atc_link_target('501'), 'https://x.test/product/p500/');
same('a draft product: home', af_atc_link_target('77'), 'https://x.test/');
same('no such product: home', af_atc_link_target('999999'), 'https://x.test/');
same('not a number: home', af_atc_link_target('abc'), 'https://x.test/');

echo "\n=== it runs before WooCommerce adds to the cart ===\n";
same('hooked on wp_loaded before priority 20', in_array(array('wp_loaded', 1), $GLOBALS['hooks'], true), true);

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
