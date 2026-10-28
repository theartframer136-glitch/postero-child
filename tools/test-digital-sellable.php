<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Tests for inc/digital-sellable.php, outside WordPress: a download is sold
 * only where there is a print master, cash on delivery is not offered for a
 * basket of downloads alone, and the buyer is told when and where the
 * download opens.
 *
 * Run: php tools/test-digital-sellable.php
 */
define('ABSPATH', __DIR__ . '/');

// ── the little of WordPress / WooCommerce the module touches ──
$GLOBALS['hooks'] = array(); $GLOBALS['opts'] = array(); $GLOBALS['notices'] = array();
$GLOBALS['r2'] = true; $GLOBALS['masters'] = array(); $GLOBALS['digital_req'] = false; $GLOBALS['digital_cat'] = array();
$GLOBALS['admin'] = false; $GLOBALS['ajax'] = false; $GLOBALS['cart'] = array(); $GLOBALS['gateways'] = array(); $GLOBALS['orders'] = array();
function add_filter($h, $f, $p = 10, $n = 1) { $GLOBALS['hooks'][$h][] = $f; }
function add_action($h, $f, $p = 10, $n = 1) { $GLOBALS['hooks'][$h][] = $f; }
function apply($h, ...$a) { foreach ($GLOBALS['hooks'][$h] ?? array() as $f) $a[0] = $f(...$a); return $a[0]; }
function run($h, ...$a) { ob_start(); foreach ($GLOBALS['hooks'][$h] ?? array() as $f) $f(...$a); return ob_get_clean(); }
function apply_filters($h, $v) { return $v; }
function get_option($k, $d = false) { return array_key_exists($k, $GLOBALS['opts']) ? $GLOBALS['opts'][$k] : $d; }
function is_admin() { return $GLOBALS['admin']; }
function wp_doing_ajax() { return $GLOBALS['ajax']; }
function wc_add_notice($m, $t = 'success') { $GLOBALS['notices'][] = $m; }
function get_the_title($id) { return 'Piece ' . $id; }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function esc_url($s) { return (string) $s; }
function wp_strip_all_tags($s) { return strip_tags((string) $s); }
function home_url($p = '') { return 'https://shop.test' . $p; }
function wc_get_account_endpoint_url($e) { return 'https://shop.test/my-account/' . $e . '/'; }
class WC_Product { public $id; function __construct($id) { $this->id = $id; }
    function get_id() { return $this->id; } function get_name() { return 'Piece ' . $this->id; } function get_parent_id() { return 0; } }
function wc_get_product($id) { return $id ? new WC_Product((int) $id) : null; }
function wc_get_order($id) { return $GLOBALS['orders'][$id] ?? null; }
// the bucket and the master index (inc/digital-masters.php)
function af_r2_config() { return $GLOBALS['r2'] ? array('account' => 'a') : null; }
function af_r2_master_for_buyer($p) { return !empty($GLOBALS['masters'][$p->get_id()]) ? array('key' => 'x.jpg') : null; }
// functions.php
function af_sells_as_digital($pid) { return $GLOBALS['digital_req'] || in_array((int) $pid, $GLOBALS['digital_cat'], true); }
function af_is_digital_download($p) { return in_array($p->get_id(), $GLOBALS['digital_cat'], true); }
function af_cart_line_is_digital($i) { return !empty($i['af_digital']); }
function af_cart_has_digital() { foreach ($GLOBALS['cart'] as $i) if (!empty($i['af_digital'])) return true; return false; }
function af_kit_ships($k) { return $k !== 'digital'; }
class FakeGateway { private $t; function __construct($t) { $this->t = $t; } function get_title() { return $this->t; } }
class FakeGateways { function get_available_payment_gateways() { return $GLOBALS['gateways']; } }
class FakeCart { function get_cart() { return $GLOBALS['cart']; } }
class FakeWC { public $cart, $payment_gateways; function __construct() { $this->cart = new FakeCart(); $this->payment_gateways = new FakeGateways(); } }
function WC() { static $w = null; return $w ?: ($w = new FakeWC()); }
class FakeItem { private $m; function __construct($m) { $this->m = $m; } function get_meta($k) { return $this->m[$k] ?? ''; } }
class FakeOrder { private $items, $paid;
    function __construct($items, $paid) { $this->items = $items; $this->paid = $paid; }
    function get_items() { return $this->items; } function is_paid() { return $this->paid; }
    function get_status() { return $this->paid ? 'processing' : 'on-hold'; } function get_billing_email() { return 'buyer@example.com'; } }

require __DIR__ . '/../inc/digital-sellable.php';

$pass = 0; $fail = 0;
function ok($cond, $what) { global $pass, $fail; if ($cond) { $pass++; echo "  OK    $what\n"; } else { $fail++; echo "  FAIL  $what\n"; } }
function reset_state() { $GLOBALS['notices'] = array(); $GLOBALS['digital_req'] = false; }

echo "=== which pieces can be sold as a download ===\n";
$GLOBALS['opts']['af_r2_index'] = array('built' => 1, 'map' => array('RK-1' => array('key' => 'RK-1.jpg')));
$GLOBALS['masters'] = array(101 => true);
ok(af_digital_available(101) === true, 'a piece with its master in the bucket: yes');
ok(af_digital_available(new WC_Product(101)) === true, 'the same, given the product rather than its ID');
ok(af_digital_available(102) === false, 'a piece without a master: no (it would get the 430 KB web image)');
ok(af_digital_available(0) === false && af_digital_available('x') === false, 'not a product: no');
$GLOBALS['opts']['af_dd_require_master'] = 'no';
ok(af_digital_available(102) === true, 'switched off (af_dd_require_master = no): every piece again');
unset($GLOBALS['opts']['af_dd_require_master']);
$GLOBALS['r2'] = false;
ok(af_digital_available(102) === true, 'bucket not connected: nothing hidden, as before');
$GLOBALS['r2'] = true; $saved = $GLOBALS['opts']['af_r2_index']; $GLOBALS['opts']['af_r2_index'] = array('built' => 1, 'map' => array());
ok(af_digital_available(102) === true, 'bucket never read (empty index): nothing hidden, as before');
$GLOBALS['opts']['af_r2_index'] = $saved;

echo "\n=== the cart refuses what cannot be delivered ===\n";
reset_state(); $GLOBALS['digital_req'] = true;
ok(apply('woocommerce_add_to_cart_validation', true, 102, 1) === false, 'the modal or "Digital download" on a piece without a master: refused');
ok(count($GLOBALS['notices']) === 1 && strpos($GLOBALS['notices'][0], 'not available as a print-ready download') !== false, 'with one notice saying why');
reset_state(); $GLOBALS['digital_req'] = true;
ok(apply('woocommerce_add_to_cart_validation', true, 101, 1) === true && !$GLOBALS['notices'], 'a piece with its master: added, no notice');
reset_state();
ok(apply('woocommerce_add_to_cart_validation', true, 102, 1) === true, 'a canvas of the same piece: unaffected');
reset_state(); $GLOBALS['digital_req'] = true;
ok(apply('woocommerce_add_to_cart_validation', false, 101, 1) === false && !$GLOBALS['notices'], 'already refused by something else: left refused, no second notice');

echo "\n=== a product that is only ever a file ===\n";
$GLOBALS['digital_cat'] = array(102, 101);
ok(apply('woocommerce_is_purchasable', true, new WC_Product(102)) === false, 'no master: cannot be bought (card shows Read more)');
ok(apply('woocommerce_is_purchasable', true, new WC_Product(101)) === true, 'with its master: can be bought');
ok(apply('woocommerce_is_purchasable', true, new WC_Product(103)) === true, 'a canvas: unaffected');
ok(apply('woocommerce_is_purchasable', false, new WC_Product(101)) === false, 'not purchasable for another reason: left so');
$GLOBALS['digital_cat'] = array();

echo "\n=== a basket made before the master went ===\n";
reset_state();
$GLOBALS['cart'] = array(array('af_digital' => '1', 'product_id' => 102, 'data' => new WC_Product(102)), array('product_id' => 103, 'data' => new WC_Product(103)));
run('woocommerce_check_cart_items');
ok(count($GLOBALS['notices']) === 1 && strpos($GLOBALS['notices'][0], 'remove it from your cart') !== false, 'checkout names the line that cannot be delivered');
reset_state(); $GLOBALS['cart'] = array(array('af_digital' => '1', 'product_id' => 101, 'data' => new WC_Product(101)));
run('woocommerce_check_cart_items');
ok(!$GLOBALS['notices'], 'a download with its master: nothing said');

echo "\n=== paying for a download ===\n";
$all = array('square_credit_card' => new FakeGateway('Credit Card'), 'zelle' => new FakeGateway('Pay via Zelle'), 'cod' => new FakeGateway('Cash on delivery'));
$GLOBALS['cart'] = array(array('af_digital' => '1', 'product_id' => 101));
$g = apply('woocommerce_available_payment_gateways', $all);
ok(!isset($g['cod']) && isset($g['zelle']) && isset($g['square_credit_card']), 'downloads only: no cash on delivery; card and Zelle stay');
$GLOBALS['cart'][] = array('product_id' => 103);
ok(isset(apply('woocommerce_available_payment_gateways', $all)['cod']), 'a canvas in the basket too: cash on delivery stays');
$GLOBALS['cart'] = array(array('af_digital' => '1', 'product_id' => 101)); $GLOBALS['admin'] = true;
ok(isset(apply('woocommerce_available_payment_gateways', $all)['cod']), 'the admin screens: untouched');
$GLOBALS['admin'] = false; $GLOBALS['cart'] = array();
ok(isset(apply('woocommerce_available_payment_gateways', $all)['cod']), 'an empty basket: untouched');

$GLOBALS['cart'] = array(array('af_digital' => '1', 'product_id' => 101));
$GLOBALS['gateways'] = array('square_credit_card' => new FakeGateway('Credit Card'), 'zelle' => new FakeGateway('Pay via Zelle'));
$note = run('woocommerce_review_order_before_payment');
ok(strpos($note, 'straight away') !== false && strpos($note, 'Paid by Zelle, it opens there as soon as we confirm') !== false, 'checkout says card opens it at once, Zelle once confirmed');
$GLOBALS['gateways'] = array('square_credit_card' => new FakeGateway('Credit Card'));
ok(strpos(run('woocommerce_review_order_before_payment'), 'confirm your payment') === false, 'card only on offer: no Zelle sentence');
$GLOBALS['cart'] = array(array('product_id' => 103));
ok(run('woocommerce_review_order_before_payment') === '', 'no download in the basket: no note');

echo "\n=== which order lines are downloads ===\n";
ok(af_order_item_is_digital(new FakeItem(array('Format' => 'Digital Download'))), 'the modal\'s line (Format: Digital Download)');
ok(af_order_item_is_digital(new FakeItem(array('_af_kit' => 'digital'))), '"What you receive: Digital download"');
ok(!af_order_item_is_digital(new FakeItem(array('_af_kit' => 'painting'))), 'a canvas: no');
ok(!af_order_item_is_digital(new FakeItem(array())), 'a line with nothing on it: no');

echo "\n=== telling the buyer where it is ===\n";
$GLOBALS['orders'][1] = new FakeOrder(array(new FakeItem(array('Format' => 'Digital Download'))), true);
$GLOBALS['orders'][2] = new FakeOrder(array(new FakeItem(array('Format' => 'Digital Download'))), false);
$GLOBALS['orders'][3] = new FakeOrder(array(new FakeItem(array('_af_kit' => 'painting'))), true);
$t1 = run('woocommerce_thankyou', 1);
ok(strpos($t1, 'Your download is in My Account → Downloads. Log in with buyer@example.com') !== false && strpos($t1, '/my-account/downloads/') !== false, 'paid by card: it is ready, log in with the order email, with a link');
ok(strpos(run('woocommerce_thankyou', 2), 'as soon as we confirm your payment') !== false, 'on hold (Zelle): opens once the payment is confirmed');
ok(run('woocommerce_thankyou', 3) === '', 'a canvas order: nothing added');
$e = function ($id) { $o = new stdClass(); $o->id = $id; return $o; };
ok(strpos(run('woocommerce_email_before_order_table', $GLOBALS['orders'][2], false, false, $e('customer_on_hold_order')), 'as soon as we confirm') !== false, 'the on-hold email says when it opens');
ok(strpos(run('woocommerce_email_before_order_table', $GLOBALS['orders'][1], false, false, $e('customer_processing_order')), 'Log in with') !== false, 'the processing email says to log in');
ok(strpos(run('woocommerce_email_before_order_table', $GLOBALS['orders'][1], false, true, $e('customer_completed_order')), "https://shop.test/my-account/downloads/\n") !== false, 'the plain-text email carries the address');
ok(run('woocommerce_email_before_order_table', $GLOBALS['orders'][1], true, false, $e('new_order')) === '', 'the shop\'s own email: nothing added');
ok(run('woocommerce_email_before_order_table', $GLOBALS['orders'][1], false, false, $e('customer_invoice')) === '', 'other customer emails: nothing added');
ok(run('woocommerce_email_before_order_table', $GLOBALS['orders'][3], false, false, $e('customer_processing_order')) === '', 'a canvas order\'s email: nothing added');

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
