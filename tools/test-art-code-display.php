<?php
/**
 * Tests what a shopper sees as a product's Art Code: af_get_art_code() and the
 * four places that print it (shop card, product summary, description, and the
 * short description the quick view shows).
 *
 * Runs the REAL code, lifted out of functions.php rather than copied, so it
 * cannot drift from what ships. Plain `php` runs it; WordPress is stubbed.
 *
 * Owner, 25 Sep: every product shows an art code, and a temporary TMP-… code is
 * shown like any other (they were hidden from 21 Sep, leaving 198 products with
 * no Art Code line).
 *
 *     php tools/test-art-code-display.php
 */
$src = file_get_contents(__DIR__ . '/../functions.php');
$a = strpos($src, "\nfunction af_get_art_code(");
$b = strpos($src, '// Show the code on admin order line items');
if ($a === false || $b === false || $b < $a) { fwrite(STDERR, "could not find the art-code block in functions.php\n"); exit(2); }
$block = substr($src, $a, $b - $a);

// ── WordPress / WooCommerce stubs ────────────────────────────────────────────
class WC_Product { public $id; function __construct($id) { $this->id = $id; } function get_id() { return $this->id; } }
$META = array();                  // pid => _taf_art_code as stored
$HOOKS = array();                 // hook => callbacks, in the order added
$CURRENT = 0;                     // get_the_ID()
$ADMIN = false; $IS_PRODUCT = true;
function get_post_meta($pid, $key, $single) { global $META; return $key === '_taf_art_code' && array_key_exists($pid, $META) ? $META[$pid] : ''; }
function af_wc_product($maybe = null) { if (is_numeric($maybe) && $maybe) return new WC_Product((int) $maybe); global $product; return $product instanceof WC_Product ? $product : null; }
function add_action($hook, $cb, $prio = 10, $args = 1) { global $HOOKS; $HOOKS[$hook][] = $cb; }
function add_filter($hook, $cb, $prio = 10, $args = 1) { global $HOOKS; $HOOKS[$hook][] = $cb; }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function is_admin() { global $ADMIN; return $ADMIN; }
function get_post_type() { return 'product'; }
function get_the_ID() { global $CURRENT; return $CURRENT; }
function is_product() { global $IS_PRODUCT; return $IS_PRODUCT; }

eval($block);

$pass = 0; $fail = 0;
function ok($cond, $what, $got = null) {
    global $pass, $fail;
    if ($cond) { $pass++; echo "  OK    $what\n"; }
    else { $fail++; echo "  FAIL  $what" . ($got !== null ? "\n        got: " . var_export($got, true) : '') . "\n"; }
}
function run_hook($hook, $arg = null) {
    global $HOOKS; ob_start(); $ret = null;
    foreach ($HOOKS[$hook] ?? array() as $cb) { $ret = $arg === null ? $cb() : $cb($arg); }
    $out = ob_get_clean();
    return $arg === null ? $out : $ret;
}
function as_product($pid) { global $product, $CURRENT; $product = new WC_Product($pid); $CURRENT = $pid; unset($GLOBALS['af_art_code_printed']); }

$META = array(
    27133 => 'TMP-1104',
    31588 => ' TMP-1305 ',        // stored with stray spaces
    8301  => 'RK-010001-3050',
    26145 => 'AL 01',
    99001 => '',                   // no code at all
    99002 => array('odd'),         // not a string
);

echo "=== af_get_art_code() ===\n";
ok(af_get_art_code(27133) === 'TMP-1104', 'a temporary code is returned, not hidden', af_get_art_code(27133));
ok(af_get_art_code(31588) === 'TMP-1305', 'and trimmed', af_get_art_code(31588));
ok(af_get_art_code(8301) === 'RK-010001-3050', 'a brochure code is returned as it is', af_get_art_code(8301));
ok(af_get_art_code(26145) === 'AL 01', 'an AL code is returned as it is', af_get_art_code(26145));
ok(af_get_art_code(99001) === '', 'a product with no code gets nothing', af_get_art_code(99001));
ok(af_get_art_code(99002) === '', 'a code that is not a string gets nothing', af_get_art_code(99002));
unset($GLOBALS['product']);
ok(af_get_art_code(0) === '', 'no product gets nothing', af_get_art_code(0));
ok(af_get_art_code(new WC_Product(27133)) === 'TMP-1104', 'a WC_Product works as well as an id');

echo "\n=== shop card ===\n";
as_product(27133);
$card = run_hook('woocommerce_after_shop_loop_item_title');
ok(strpos($card, 'Art Code: TMP-1104') !== false && strpos($card, 'af-art-code--empty') === false, 'a TMP product card shows "Art Code: TMP-1104"', $card);
as_product(99001);
$card = run_hook('woocommerce_after_shop_loop_item_title');
ok(strpos($card, 'af-art-code--empty') !== false && strpos($card, 'Art Code:') === false, 'a product with no code still gets the blank row that keeps cards level', $card);
as_product(8301);
ok(strpos(run_hook('woocommerce_after_shop_loop_item_title'), 'Art Code: RK-010001-3050') !== false, 'a brochure-coded card is unchanged');

echo "\n=== product page ===\n";
as_product(27133);
$summary = run_hook('woocommerce_single_product_summary');
ok(substr_count($summary, 'Art Code: <strong>TMP-1104</strong>') === 1, 'the summary shows the temporary code once', $summary);
$short = run_hook('woocommerce_short_description', '<p>About this print</p>');
ok(strpos($short, 'Art Code') === false, 'the short description does not repeat it after the summary printed it', $short);
$desc = run_hook('the_content', '<p>Description</p>');
ok(substr_count($desc, 'Art Code: <strong>TMP-1104</strong>') === 1, 'the description shows it once', $desc);
ok(run_hook('the_content', $desc) === $desc, 'and does not add it a second time');

echo "\n=== quick view (no summary hook, only the short description) ===\n";
as_product(31588);
$short = run_hook('woocommerce_short_description', '<p>About this print</p>');
ok(substr_count($short, 'Art Code: <strong>TMP-1305</strong>') === 1, 'the quick view shows the temporary code', $short);

echo "\n=== admin ===\n";
$ADMIN = true;
ok(run_hook('the_content', '<p>x</p>') === '<p>x</p>', 'the editor is left alone');
$ADMIN = false;

printf("\n%d passed, %d failed\n", $pass, $fail);
exit($fail ? 1 : 0);
