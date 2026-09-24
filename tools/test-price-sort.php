<?php
/**
 * Tests for inc/price-sort.php — run with: php tools/test-price-sort.php
 *
 * The module rewrites the ORDER BY that WooCommerce writes for a price sort.
 * Its clauses are copied here from WooCommerce itself
 * (WC_Query::order_by_price_asc_post_clauses and ..._desc_...), so a change
 * to either side shows up as a failure here rather than on the shop.
 */
define('ABSPATH', __DIR__);
function add_filter() {}
function add_action() {}
require __DIR__ . '/../inc/price-sort.php';

$pass = 0; $fail = 0;
function ok($cond, $what) {
    global $pass, $fail;
    if ($cond) { $pass++; echo "  ok   $what\n"; }
    else       { $fail++; echo "  FAIL $what\n"; }
}

echo "is this listing sorted by price?\n";
ok(af_price_sort_requested(array('orderby' => 'price'), 'menu_order'), 'orderby=price is');
ok(af_price_sort_requested(array('orderby' => 'price-desc'), 'menu_order'), 'orderby=price-desc is');
ok(af_price_sort_requested(array('orderby' => 'PRICE'), 'menu_order'), 'whatever the case');
ok(af_price_sort_requested(array(), 'price'), 'no sort in the URL, and the shop\'s default is price: it is');
foreach (array('menu_order', 'popularity', 'rating', 'date', 'rand', 'relevance') as $o) {
    ok(!af_price_sort_requested(array('orderby' => $o), 'price'), "orderby=$o is not, even when the default is price");
}
ok(af_price_sort_requested(array('orderby' => ''), 'price'), 'an empty orderby= falls back to the default, as WooCommerce does');
ok(!af_price_sort_requested(array('orderby' => ''), 'menu_order'), 'and is not a price sort when the default is not');
ok(!af_price_sort_requested(array('orderby' => 'date'), 'price'), 'a sort in the URL wins over the default');
ok(!af_price_sort_requested(array(), 'menu_order'), 'no sort anywhere: not');

// WooCommerce's own clauses, verbatim.
$asc  = array('where' => " AND wp_posts.post_type = 'product'", 'join' => ' LEFT JOIN wp_wc_product_meta_lookup wc_product_meta_lookup ON wp_posts.ID = wc_product_meta_lookup.product_id ',
              'orderby' => ' wc_product_meta_lookup.min_price ASC, wc_product_meta_lookup.product_id ASC ');
$desc = array('where' => $asc['where'], 'join' => $asc['join'],
              'orderby' => ' wc_product_meta_lookup.max_price DESC, wc_product_meta_lookup.product_id DESC ');

echo "\nlow to high\n";
$a = af_price_sort_clauses($asc, 'wp_posts', 'wp_postmeta');
ok(strpos($a['orderby'], '( COALESCE(wc_product_meta_lookup.max_price, 0) <= 0 ) ASC, ') === 1,
   'the first key is "has no price", false (0) before true (1): priced products first');
ok(strpos($a['orderby'], 'wc_product_meta_lookup.min_price ASC, wc_product_meta_lookup.product_id ASC') !== false,
   "WooCommerce's own order follows, unchanged");
ok($a['where'] === $asc['where'], 'nothing is removed: the WHERE is untouched');
ok($a['join'] === $asc['join'], 'and so is the JOIN');

echo "\nhigh to low\n";
$d = af_price_sort_clauses($desc, 'wp_posts', 'wp_postmeta');
ok(strpos($d['orderby'], '( COALESCE(wc_product_meta_lookup.max_price, 0) <= 0 ) ASC, ') === 1,
   'the same first key, still ASC: products without a price are last in this direction too');
ok(strpos($d['orderby'], 'wc_product_meta_lookup.max_price DESC') !== false, 'then highest price first');
ok($d['where'] === $desc['where'], 'the WHERE is untouched');

echo "\nsomeone else's price sort\n";
$other = array('where' => " AND wp_posts.post_type = 'product'", 'orderby' => 'wp_postmeta.meta_value+0 ASC');
$o = af_price_sort_clauses($other, 'wp_posts', 'wp_postmeta');
ok($o['orderby'] === $other['orderby'], 'no lookup table in the ORDER BY: the order is not touched');
ok(strpos($o['where'], "afps.meta_key = '_price' AND afps.meta_value + 0 > 0") !== false,
   'and products without a price are left out, as the first fix did, so page 1 never opens on them');
ok(strpos($o['where'], 'afps.post_id = wp_posts.ID') !== false, 'the subquery is tied to the outer product');
$p = substr_count($o['where'], '(') - substr_count($o['where'], ')');
ok($p === 0, 'parentheses balance');

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
