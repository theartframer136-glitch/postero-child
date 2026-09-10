<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Tests af_shipping_copy() — the one place the site says how shipping works.
 *
 * This function exists because the site once promised "Free Shipping across
 * the USA" on one surface while another listed free delivery in four states
 * only. The test that matters, therefore, is not that the words are right: it
 * is that NOTHING can say "free" unless the policy actually is free.
 *
 * Runs the REAL function, lifted out of functions.php by name rather than
 * copied, so it cannot drift from what ships:
 *
 *     php tools/test-shipping-copy.php
 */
$src = file_get_contents(__DIR__ . '/../functions.php');
if (!preg_match('/\nfunction af_shipping_copy\(\).*?\n\}\n/s', $src, $m)) {
    fwrite(STDERR, "could not find af_shipping_copy() in functions.php\n");
    exit(2);
}

// WordPress, reduced to the two things the function touches.
$GLOBALS['af_test_option'] = null;
function get_option($name, $default = false) {
    return $GLOBALS['af_test_option'] === null ? $default : $GLOBALS['af_test_option'];
}
function apply_filters($tag, $value) { return $value; }
eval($m[0]);

/** Run the function as if the option held $value (null = option never set). */
function copy_with($value) {
    $GLOBALS['af_test_option'] = $value;
    return af_shipping_copy();
}

$pass = 0; $fail = 0;
function check($label, $got, $want) {
    global $pass, $fail;
    if ($got === $want) { $pass++; printf("  OK    %s\n", $label); }
    else { $fail++; printf("  FAIL  %s\n        got  %s\n        want %s\n",
                           $label, var_export($got, true), var_export($want, true)); }
}
function has($label, $haystack, $needle) {
    check($label, stripos($haystack, $needle) !== false, true);
}

echo "=== the policy the owner stated: free on all US orders ===\n";
$c = copy_with(null);                       // option not set on the live site yet
check('free by default', $c['free'], true);
has('label says free',  $c['label'], 'Free Shipping');
has('short says free',  $c['short'], 'Free shipping on all orders');
has('blurb says free',  $c['blurb'], 'free on every order');
has('blurb says no minimum', $c['blurb'], 'no minimum');
check('cost is "0" so the Google feed states a real zero', $c['cost'], '0');

echo "\n=== asked for by name, and the obvious spellings of it ===\n";
foreach (array('free', 'Free', 'FREE', '0', '$0') as $v) {
    check("af_shipping_cost = " . var_export($v, true) . " is free", copy_with($v)['free'], true);
}

echo "\n=== THE GUARANTEE: nothing claims free unless the policy is free ===\n";
// Every surface reads label/short/blurb. If the policy is not free, none of
// them may contain the word — that is the whole reason this function exists.
foreach (array('$15', 'from $12', '12.50', 'Flat $9.95') as $v) {
    $c = copy_with($v);
    check("cost $v: free flag is false", $c['free'], false);
    foreach (array('label', 'short', 'blurb') as $k) {
        check("cost $v: {$k} does not say 'free'", stripos($c[$k], 'free') !== false, false);
    }
    has("cost $v: the figure is stated", $c['short'], $v);
    check("cost $v: the feed gets that figure, not zero", $c['cost'], $v);
}

echo "\n=== blank is NOT free — it is the safe fallback ===\n";
$c = copy_with('');
check('blank: free flag is false', $c['free'], false);
foreach (array('label', 'short', 'blurb') as $k) {
    check("blank: {$k} does not say 'free'", stripos($c[$k], 'free') !== false, false);
}
has('blank: says the cost is shown at checkout', $c['short'], 'shown at checkout');
check('blank: the feed omits the rate rather than guessing', $c['cost'], '');

echo "\n=== whitespace around the option value is not a different policy ===\n";
check('"  free  " is still free', copy_with('  free  ')['free'], true);
check('"   " is blank, not free',  copy_with('   ')['free'], false);

echo "\n=== every state returns the full shape callers rely on ===\n";
foreach (array(null, 'free', '$15', '') as $v) {
    $c = copy_with($v);
    $shape = array_keys($c);
    sort($shape);
    check('keys for ' . var_export($v, true), $shape, array('blurb', 'cost', 'free', 'label', 'short'));
    check('free is a real bool for ' . var_export($v, true), is_bool($c['free']), true);
}

printf("\n%d passed, %d failed\n", $pass, $fail);
exit($fail ? 1 : 0);
