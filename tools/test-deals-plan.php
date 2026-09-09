<?php
/**
 * Tests the one rule that can lose the owner's work: af_deals_plan() must never
 * propose removing a product this file did not put in the category.
 * Runs the real function from inc/deals-live.php, with WordPress stubbed out.
 */
define('ABSPATH', '/nowhere/');
function add_action() {}
function add_filter() {}

require '/home/user/postero-child/inc/deals-live.php';

$pass = 0; $fail = 0;
function check($label, $got, $want) {
    global $pass, $fail;
    sort($got); sort($want);
    if ($got === $want) { $pass++; printf("  OK    %s\n", $label); }
    else { $fail++; printf("  FAIL  %s\n        got  %s\n        want %s\n",
                           $label, json_encode($got), json_encode($want)); }
}

echo "=== af_deals_plan ===\n";

// The first run: the category is empty, everything on sale goes in.
$p = af_deals_plan(array(1,2,3), array(), array());
check('empty category: add all three', $p['add'], array(1,2,3));
check('empty category: remove nothing', $p['remove'], array());

// Steady state: nothing has changed, so nothing is written.
$p = af_deals_plan(array(1,2,3), array(1,2,3), array(1,2,3));
check('no change: add nothing', $p['add'], array());
check('no change: remove nothing', $p['remove'], array());

// A product comes off sale, and we put it there, so we take it back.
$p = af_deals_plan(array(1,2), array(1,2,3), array(1,2,3));
check('off sale and ours: removed', $p['remove'], array(3));

// THE GUARANTEE: a full-price product someone added by hand is not ours, and
// survives even though it is not on sale.
$p = af_deals_plan(array(1,2), array(1,2,99), array(1,2));
check('hand-picked, full price: NOT removed', $p['remove'], array());
check('hand-picked: not re-added either', $p['add'], array());

// Mixed: one of ours goes, one of theirs stays, one new arrives.
$p = af_deals_plan(array(1,4), array(1,3,99), array(1,3));
check('mixed: adds the new one', $p['add'], array(4));
check('mixed: removes only ours', $p['remove'], array(3));

// A hand-picked product that IS on sale stays put and is not double-added.
$p = af_deals_plan(array(1,99), array(1,99), array(1));
check('hand-picked and on sale: left alone', $p['add'], array());
check('hand-picked and on sale: not removed', $p['remove'], array());

// Duplicates and string ids must not produce duplicate writes.
$p = af_deals_plan(array('7','7',8), array(8,8), array(8));
check('duplicate/string ids: one add', $p['add'], array(7));
check('duplicate/string ids: no remove', $p['remove'], array());

// Nothing on sale at all: the category empties of ours, keeps theirs.
$p = af_deals_plan(array(), array(5,99), array(5));
check('no sales: ours removed', $p['remove'], array(5));
check('no sales: theirs kept', $p['add'], array());

printf("\n%d passed, %d failed\n", $pass, $fail);
exit($fail ? 1 : 0);
