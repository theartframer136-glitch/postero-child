<?php
/**
 * Tests for inc/digital-account.php (a digital purchase stays in the buyer's
 * account: no expiry, no download count, an account made at checkout for a
 * guest with a digital download in the cart), outside WordPress.
 *
 * Run: php tools/test-digital-account.php
 */
define('ABSPATH', __DIR__ . '/');

$GLOBALS['hooks'] = array(); $GLOBALS['logged_in'] = false; $GLOBALS['digital_cart'] = false;
function add_filter($h, $f, $p = 10, $n = 1) { $GLOBALS['hooks'][$h][] = $f; }
function add_action($h, $f, $p = 10, $n = 1) { $GLOBALS['hooks'][$h][] = $f; }
function apply($h, ...$args) { foreach ($GLOBALS['hooks'][$h] ?? array() as $f) $args[0] = $f(...$args); return $args[0]; }
function run($h) { ob_start(); foreach ($GLOBALS['hooks'][$h] ?? array() as $f) $f(); return ob_get_clean(); }
function is_user_logged_in() { $GLOBALS['login_asked'] = true; return $GLOBALS['logged_in']; }
function af_cart_has_digital() { $GLOBALS['cart_asked'] = true; return $GLOBALS['digital_cart']; }
class FakeDownload { public $remaining = 5, $expires = 1999999999;
    function set_downloads_remaining($v) { $this->remaining = $v; } function set_access_expires($v) { $this->expires = $v; } }

require __DIR__ . '/../inc/digital-account.php';

$pass = 0; $fail = 0;
function ok($cond, $what) { global $pass, $fail; if ($cond) { $pass++; echo "  OK    $what\n"; } else { $fail++; echo "  FAIL  $what\n"; } }

echo "=== a purchase never runs out ===\n";
$d = apply('woocommerce_downloadable_file_permission', new FakeDownload(), null, null, 1, null);
ok($d->remaining === '', 'no download count (a product set to 5 downloads included)');
ok($d->expires === null, 'no expiry (a product set to 30 days included)');
ok(apply('woocommerce_downloadable_file_permission', 'not a download') === 'not a download', 'anything else passes through untouched');

echo "\n=== a guest with a digital download in the cart ===\n";
$GLOBALS['logged_in'] = false; $GLOBALS['digital_cart'] = true;
ok(apply('woocommerce_checkout_registration_required', false) === true, 'an account is required');
ok(apply('woocommerce_checkout_registration_enabled', false) === true, 'and offered, though sign-up at checkout is off shop-wide');
ok(apply('option_woocommerce_registration_generate_username', 'no') === 'yes', 'no username to type: made from the email');
ok(strpos(run('woocommerce_before_checkout_registration_form'), 'My Account → Downloads') !== false, 'the checkout says where the download will be');

echo "\n=== everyone else checks out as before ===\n";
$GLOBALS['logged_in'] = false; $GLOBALS['digital_cart'] = false;
ok(apply('woocommerce_checkout_registration_required', false) === false, 'a guest buying a print: no account required');
ok(apply('woocommerce_checkout_registration_enabled', false) === false, 'and none offered');
ok(apply('option_woocommerce_registration_generate_username', 'no') === 'no', 'the username setting is left alone');
ok(run('woocommerce_before_checkout_registration_form') === '', 'no note');
$GLOBALS['logged_in'] = true; $GLOBALS['digital_cart'] = true;
ok(apply('woocommerce_checkout_registration_required', false) === false, 'logged in with a digital download: nothing to make');
ok(run('woocommerce_before_checkout_registration_form') === '', 'and no note');

echo "\n=== the login state is asked only once a digital cart is there ===\n";
$GLOBALS['digital_cart'] = false; $GLOBALS['cart_asked'] = false; $GLOBALS['login_asked'] = false;
apply('option_woocommerce_registration_generate_username', 'no');
ok($GLOBALS['cart_asked'] && !$GLOBALS['login_asked'], 'no digital cart (or none yet, early in a request): the login state is not asked');

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
