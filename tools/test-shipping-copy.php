<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Tests af_shipping_copy() — the one place the site says how shipping works.
 *
 * This function exists because the site once promised "Free Shipping across
 * the USA" on one surface while another listed free delivery in four states
 * only. The owner's rule (2026-09-10, restated 2026-10-23) is that nothing on
 * the site is free and the word is not to appear anywhere. The test that
 * matters, therefore, is not that the words are right: it is that NOTHING
 * the function returns can say "free", whatever the option holds.
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

echo "=== THE POLICY: the shop does not ship free anywhere ===\n";
// Owner, 2026-09-10, correcting an earlier instruction the same day: "we do
// not ship free for any place". The live site has never had this option set,
// so THIS is the case that runs in production, and it is the one that must
// never claim free.
$c = copy_with(null);                       // option not set, as on the live site
check('not free by default', $c['free'], false);
foreach (array('label', 'short', 'blurb') as $k) {
    check("default: {$k} does not say 'free'", stripos($c[$k], 'free') !== false, false);
}
has('default: says the cost is shown at checkout', $c['short'], 'shown at checkout');
check('default: the feed omits the rate rather than claiming zero', $c['cost'], '');

echo "\n=== there is no free branch: 'free', '0' and '$0' fall through to the default ===\n";
// The function used to switch to "Free Shipping Across the USA" for these
// values. The owner ruled that nothing is free, so a stray option value must
// not be able to put the word back on the site.
foreach (array('free', 'Free', 'FREE', '0', '$0', '  free  ') as $v) {
    $c = copy_with($v);
    check("af_shipping_cost = " . var_export($v, true) . ": free flag is false", $c['free'], false);
    foreach (array('label', 'short', 'blurb') as $k) {
        check("af_shipping_cost = " . var_export($v, true) . ": {$k} does not say 'free'", stripos($c[$k], 'free') !== false, false);
    }
    has("af_shipping_cost = " . var_export($v, true) . ": says the cost is shown at checkout", $c['short'], 'shown at checkout');
    check("af_shipping_cost = " . var_export($v, true) . ": the feed omits the rate rather than claiming zero", $c['cost'], '');
}

echo "\n=== THE GUARANTEE: no state ever claims free ===\n";
// Every surface reads label/short/blurb. None of them may contain the word —
// that is the whole reason this function exists.
foreach (array('$15', 'from $12', '12.50', 'Flat $9.95') as $v) {
    $c = copy_with($v);
    check("cost $v: free flag is false", $c['free'], false);
    foreach (array('label', 'short', 'blurb') as $k) {
        check("cost $v: {$k} does not say 'free'", stripos($c[$k], 'free') !== false, false);
    }
    has("cost $v: the figure is stated", $c['short'], $v);
    check("cost $v: the feed gets that figure, not zero", $c['cost'], $v);
}

echo "\n=== blank is the same as unset: the safe fallback ===\n";
$c = copy_with('');
check('blank: free flag is false', $c['free'], false);
foreach (array('label', 'short', 'blurb') as $k) {
    check("blank: {$k} does not say 'free'", stripos($c[$k], 'free') !== false, false);
}
has('blank: says the cost is shown at checkout', $c['short'], 'shown at checkout');
check('blank: the feed omits the rate rather than guessing', $c['cost'], '');

echo "\n=== whitespace around the option value is not a different policy ===\n";
check('"   " is blank',  copy_with('   ')['short'], copy_with('')['short']);
check('" $15 " is $15', copy_with(' $15 ')['cost'], '$15');

echo "\n=== every state returns the full shape callers rely on ===\n";
foreach (array(null, '', '$15', 'free') as $v) {
    $c = copy_with($v);
    $shape = array_keys($c);
    sort($shape);
    check('keys for ' . var_export($v, true), $shape, array('blurb', 'cost', 'free', 'label', 'short'));
    check('free is a real bool for ' . var_export($v, true), is_bool($c['free']), true);
}

printf("\n%d passed, %d failed\n", $pass, $fail);
exit($fail ? 1 : 0);
