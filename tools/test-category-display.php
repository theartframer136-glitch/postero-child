<?php
/**
 * Tests inc/category-display.php: Banners & Signage lists products, not tiles
 * for its subcategories, and nothing else changes.
 *
 * Runs the real filter against a stubbed WordPress, the way WooCommerce's
 * woocommerce_get_loop_display_mode() asks for it: get_term_meta($id,
 * 'display_type', true). Plain `php` runs it:
 *
 *     php tools/test-category-display.php
 */
define('ABSPATH', '/nowhere/');
$GLOBALS['FILTERS'] = array();
function add_filter($hook, $cb, $prio = 10, $args = 1) { $GLOBALS['FILTERS'][$hook][] = array($cb, $args); return true; }
function is_wp_error($x) { return $x instanceof WP_Error; }
class WP_Error {}
$GLOBALS['TERMS'] = array(
    101 => (object) array('term_id' => 101, 'slug' => 'banners-signage', 'taxonomy' => 'product_cat'),
    102 => (object) array('term_id' => 102, 'slug' => 'art-accessories', 'taxonomy' => 'product_cat'),
    103 => (object) array('term_id' => 103, 'slug' => 'vinyl-banners', 'taxonomy' => 'product_cat'),
    104 => (object) array('term_id' => 104, 'slug' => 'banners-signage', 'taxonomy' => 'post_tag'),
);
function get_term($id) { return isset($GLOBALS['TERMS'][$id]) ? $GLOBALS['TERMS'][$id] : null; }
// The stored settings, as the admin saved them.
$GLOBALS['STORED'] = array(101 => 'both', 102 => 'both', 103 => 'subcategories', 104 => 'both');
// get_term_meta() as WordPress runs it: the filter first, the stored value if the filter passes.
function get_term_meta($id, $key, $single = false) {
    $check = null;
    foreach ($GLOBALS['FILTERS']['get_term_metadata'] as $f) { $check = call_user_func($f[0], $check, $id, $key, $single); }
    if ($check !== null) { return ($single && is_array($check)) ? $check[0] : $check; }
    $v = ($key === 'display_type' && isset($GLOBALS['STORED'][$id])) ? $GLOBALS['STORED'][$id] : '';
    return $single ? $v : ($v === '' ? array() : array($v));
}

require __DIR__ . '/../inc/category-display.php';

$pass = 0; $fail = 0;
function check($what, $got, $want) {
    global $pass, $fail;
    if ($got === $want) { $pass++; echo "  OK    $what\n"; }
    else { $fail++; echo "  FAIL  $what: got " . var_export($got, true) . ", want " . var_export($want, true) . "\n"; }
}

check('the filter is hooked, taking all four arguments', isset($GLOBALS['FILTERS']['get_term_metadata'][0]) ? $GLOBALS['FILTERS']['get_term_metadata'][0][1] : null, 4);
check('Banners & Signage lists products, though its setting says both', get_term_meta(101, 'display_type', true), 'products');
check('the same as a list, when asked for every value', get_term_meta(101, 'display_type', false), array('products'));
check('Art Accessories keeps its own setting', get_term_meta(102, 'display_type', true), 'both');
check('a subcategory of Banners & Signage keeps its own setting', get_term_meta(103, 'display_type', true), 'subcategories');
check('a tag with the same slug is not a product category', get_term_meta(104, 'display_type', true), 'both');
check('another meta key on Banners & Signage passes through', get_term_meta(101, 'thumbnail_id', true), '');
check('a term that does not exist passes through', get_term_meta(999, 'display_type', true), '');

printf("\n%d passed, %d failed\n", $pass, $fail);
exit($fail ? 1 : 0);
