<?php
/**
 * Tests for inc/home-weight.php — run with: php tools/test-home-weight.php
 *
 * The pure helpers, then the three hooks run against stand-ins for WordPress
 * and Elementor: how many products load_products asks for depending on where
 * the request came from, the "see all" marker it carries, and which Elementor
 * elements are left out of the homepage.
 */
namespace {
define('ABSPATH', __DIR__);

$GLOBALS['hooks'] = array();
function add_action($h, $cb, $p = 10, $n = 1) { $GLOBALS['hooks'][$h][] = $cb; }
function add_filter($h, $cb, $p = 10, $n = 1) { $GLOBALS['hooks'][$h][] = $cb; }
function apply_filters($h, $v) { return $v; }
function run_hook($h) { $args = array_slice(func_get_args(), 1); $r = null; foreach ($GLOBALS['hooks'][$h] as $cb) $r = call_user_func_array($cb, $args); return $r; }

$GLOBALS['ajax'] = true; $GLOBALS['front'] = false; $GLOBALS['admin'] = false;
function wp_doing_ajax() { return $GLOBALS['ajax']; }
function is_front_page() { return $GLOBALS['front']; }
function is_admin() { return $GLOBALS['admin']; }
function home_url($p = '') { return 'https://theartframer.us' . $p; }
function wp_unslash($v) { return $v; }
function get_option($k) { return $k === 'page_on_front' ? '75' : false; }
function is_wp_error($x) { return false; }
function esc_html($s) { return htmlspecialchars($s, ENT_QUOTES); }
function get_term_by($field, $value, $tax) {
    $terms = array('radha-krishna' => array(20, 'Radha Krishna'), 'wildlife' => array(21, 'Wildlife'));
    if ($field === 'slug' && isset($terms[$value])) return (object) array('term_id' => $terms[$value][0], 'slug' => $value, 'name' => $terms[$value][1]);
    return false;
}
function get_term_link($t) { return 'https://theartframer.us/product-category/' . $t->slug . '/'; }

class WP_Query {
    public $vars = array(); public $main = false; public $found_posts = 0; public $post_count = 0;
    function __construct($v = array(), $main = false) { $this->vars = $v; $this->main = $main; }
    function get($k) { return isset($this->vars[$k]) ? $this->vars[$k] : ''; }
    function set($k, $v) { $this->vars[$k] = $v; }
    function is_main_query() { return $this->main; }
}

require __DIR__ . '/../inc/home-weight.php';

$pass = 0; $fail = 0;
function ok($cond, $what) {
    global $pass, $fail;
    if ($cond) { $pass++; echo "  ok   $what\n"; }
    else       { $fail++; echo "  FAIL $what\n"; }
}
}

namespace Elementor {
class Plugin { public static $instance; public $breakpoints, $editor, $preview, $documents; }
}

namespace {
echo "is this the homepage?\n";
$home = 'https://theartframer.us/';
ok(af_home_is_home_url('https://theartframer.us/', $home), 'the homepage is');
ok(af_home_is_home_url('https://theartframer.us', $home), 'without the slash too');
ok(af_home_is_home_url('https://theartframer.us/?utm_source=ig&x=1', $home), 'with a query string');
ok(af_home_is_home_url('https://theartframer.us/#shop', $home), 'with a fragment');
ok(af_home_is_home_url('https://TheArtFramer.us/', $home), 'whatever the case of the host');
ok(!af_home_is_home_url('https://theartframer.us/product-category/radha-krishna/', $home), 'a category page is not');
ok(!af_home_is_home_url('https://theartframer.us/shop/', $home), 'the shop is not');
ok(af_home_is_home_url('https://theartframer.us/?s=krishna', $home), 'a search on / has the homepage path (no collection grid there asks, so no harm)');
ok(!af_home_is_home_url('https://evil.example/', $home), 'another host is not');
ok(!af_home_is_home_url('', $home), 'no referer is not');
ok(!af_home_is_home_url(null, $home), 'nor is a missing one');
ok(!af_home_is_home_url('not a url', $home), 'nor is rubbish');
ok(af_home_is_home_url('https://example.com/shop/?a=1', 'https://example.com/shop'), 'a site in a subfolder: its own root is the homepage');
ok(!af_home_is_home_url('https://example.com/', 'https://example.com/shop'), 'and the domain root is not');

echo "\nwhich category does a tax_query ask for?\n";
ok(af_home_collection_term_ref(array(array('taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => 'radha-krishna'))) === array('slug', 'radha-krishna'), "the theme's own shape: slug, one string");
ok(af_home_collection_term_ref(array(array('taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => array('wildlife')))) === array('slug', 'wildlife'), 'one slug in an array');
ok(af_home_collection_term_ref(array(array('taxonomy' => 'product_cat', 'terms' => 20))) === array('id', '20'), 'no field means term_id');
ok(af_home_collection_term_ref(array('relation' => 'AND', array('taxonomy' => 'product_visibility', 'terms' => 'x'), array('taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => 'wildlife'))) === array('slug', 'wildlife'), 'found among other clauses');
ok(af_home_collection_term_ref(array(array('taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => array('a', 'b')))) === null, 'several categories: none (no single page to send people to)');
ok(af_home_collection_term_ref(array(array('taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => ''))) === null, 'an empty slug: none');
ok(af_home_collection_term_ref(array(array('taxonomy' => 'product_tag', 'field' => 'slug', 'terms' => 'x'))) === null, 'a tag is not a category');
ok(af_home_collection_term_ref('') === null, 'no tax_query: none');

echo "\nthe marker\n";
$m = af_home_collection_more_html('https://theartframer.us/product-category/radha-krishna/', 74, 12, 'Radha Krishna');
ok(strpos($m, 'class="af-coll-more"') !== false && strpos($m, 'data-count="74"') !== false, 'says how many there are');
ok(strpos($m, 'data-url="https://theartframer.us/product-category/radha-krishna/"') !== false, 'and where they all are');
ok(strpos($m, 'data-name="Radha Krishna"') !== false, 'and the collection');
ok(strpos($m, 'display:none!important') !== false && strpos($m, ' hidden ') !== false, 'and can never take a place in the grid');
ok(af_home_collection_more_html('https://x/', 12, 12, 'X') === '', 'nothing when every piece is shown');
ok(af_home_collection_more_html('https://x/', 5, 5, 'X') === '', 'nor for a small collection');
ok(af_home_collection_more_html('', 74, 12, 'X') === '', 'nor without a page to link to');
$e = af_home_collection_more_html('https://x/?a=1&b="2"', 20, 12, 'Rock & "Roll" <b>');
ok(strpos($e, '&amp;b=&quot;2&quot;') !== false && strpos($e, 'Rock &amp; &quot;Roll&quot; &lt;b&gt;') !== false, 'attributes are escaped');

echo "\ndoes a tax_query filter by collection?\n";
ok(af_home_has_product_cat(array(array('taxonomy' => 'product_cat', 'terms' => array('a', 'b')))), 'several categories: yes');
ok(af_home_has_product_cat(array('relation' => 'AND', array('taxonomy' => 'product_visibility', 'terms' => 'x'), array('taxonomy' => 'product_cat', 'terms' => 'y'))), 'among other clauses: yes');
ok(!af_home_has_product_cat(array(array('taxonomy' => 'product_visibility', 'terms' => 'x'))), 'visibility only: no');
ok(!af_home_has_product_cat(''), 'none: no');

echo "\nhidden at every width?\n";
$all = array('mobile', 'mobile_extra', 'tablet', 'tablet_extra', 'laptop', 'desktop');
$h = array_fill_keys($all, ''); foreach ($all as $d) $h[$d] = 'hidden-' . $d;
ok(af_home_hidden_everywhere($h, $all), 'hidden on all six: yes');
$h2 = $h; $h2['tablet'] = '';
ok(!af_home_hidden_everywhere($h2, $all), 'shown on one (tablet): no');
$h3 = $h; unset($h3['laptop']);
ok(!af_home_hidden_everywhere($h3, $all), 'a width it says nothing about counts as shown');
ok(!af_home_hidden_everywhere($h, array_merge($all, array('widescreen'))), 'a width switched on later (widescreen) and not hidden: no');
ok(!af_home_hidden_everywhere($h, array()), 'no widths known: no');
ok(!af_home_hidden_everywhere(array(), $all), 'no settings: no');

echo "\nload_products: how many it asks for\n";
$theme_args = function ($slug) { return array('post_type' => 'product', 'posts_per_page' => 12,
    'tax_query' => array(array('taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => $slug))); };
$_REQUEST['action'] = 'load_products';
$_SERVER['HTTP_REFERER'] = 'https://theartframer.us/';
$q = new WP_Query($theme_args('radha-krishna')); run_hook('pre_get_posts', $q);
ok($q->get('posts_per_page') === 12 && $q->get('af_home_collection') === 1, 'from the homepage: the theme\'s 12, marked');
$_SERVER['HTTP_REFERER'] = 'https://theartframer.us/?utm_source=ig';
$q = new WP_Query($theme_args('radha-krishna')); run_hook('pre_get_posts', $q);
ok($q->get('posts_per_page') === 12, 'from the homepage with a query string: 12');
$_SERVER['HTTP_REFERER'] = 'https://theartframer.us/product-category/hindu-deities/';
$q = new WP_Query($theme_args('radha-krishna')); run_hook('pre_get_posts', $q);
ok($q->get('posts_per_page') === -1 && $q->get('af_home_collection') === '', 'from a category page: every product, as before');
unset($_SERVER['HTTP_REFERER']);
$q = new WP_Query($theme_args('radha-krishna')); run_hook('pre_get_posts', $q);
ok($q->get('posts_per_page') === -1, 'with no referer: every product, as before');
$_SERVER['HTTP_REFERER'] = 'https://theartframer.us/';
$_REQUEST['action'] = 'load_subcategories';
$q = new WP_Query($theme_args('radha-krishna')); run_hook('pre_get_posts', $q);
ok($q->get('posts_per_page') === 12 && $q->get('af_home_collection') === '', 'another admin-ajax action: untouched');
$_REQUEST['action'] = 'load_products';
$q = new WP_Query(array('post_type' => 'post', 'posts_per_page' => 12)); run_hook('pre_get_posts', $q);
ok($q->get('posts_per_page') === 12, 'a query for posts, not products: untouched');
$q = new WP_Query(array('post_type' => array('product', 'product_variation'), 'posts_per_page' => 12, 'tax_query' => $theme_args('wildlife')['tax_query'])); run_hook('pre_get_posts', $q);
ok($q->get('posts_per_page') === 12 && $q->get('af_home_collection') === 1, 'post_type as an array with product in it: counts');
$q = new WP_Query(array('post_type' => 'product', 'posts_per_page' => 4)); run_hook('pre_get_posts', $q);
ok($q->get('posts_per_page') === 4 && $q->get('af_home_collection') === '', 'from the homepage, a product query with no collection filter: keeps its own size');
$_SERVER['HTTP_REFERER'] = 'https://theartframer.us/product-category/hindu-deities/';
$q = new WP_Query(array('post_type' => 'product', 'posts_per_page' => 4)); run_hook('pre_get_posts', $q);
ok($q->get('posts_per_page') === -1, 'from a category page, the same query: every product, exactly as PHASE 25 did');
$_SERVER['HTTP_REFERER'] = 'https://theartframer.us/';
$q = new WP_Query($theme_args('radha-krishna'), true); run_hook('pre_get_posts', $q);
ok($q->get('af_home_collection') === '', 'the main query: untouched');
$GLOBALS['ajax'] = false;
$q = new WP_Query($theme_args('radha-krishna')); run_hook('pre_get_posts', $q);
ok($q->get('af_home_collection') === '', 'not an admin-ajax request: untouched');
$GLOBALS['ajax'] = true;

echo "\nload_products: the marker after the cards\n";
$end = function ($q) { ob_start(); run_hook('loop_end', $q); return ob_get_clean(); };
$q = new WP_Query($theme_args('radha-krishna')); $q->set('af_home_collection', 1); $q->found_posts = 74; $q->post_count = 12;
$out = $end($q);
ok(strpos($out, 'data-count="74"') !== false && strpos($out, '/product-category/radha-krishna/') !== false && strpos($out, 'data-name="Radha Krishna"') !== false, 'Radha Krishna, 12 of 74: "see all 74", its page, its name');
$q = new WP_Query($theme_args('wildlife')); $q->set('af_home_collection', 1); $q->found_posts = 9; $q->post_count = 9;
ok($end($q) === '', 'a collection of 9, all shown: no marker');
$q = new WP_Query($theme_args('radha-krishna')); $q->found_posts = 74; $q->post_count = 74;
ok($end($q) === '', 'a query this module did not cap (a category page): no marker');
$q = new WP_Query($theme_args('no-such-collection')); $q->set('af_home_collection', 1); $q->found_posts = 40; $q->post_count = 12;
ok($end($q) === '', 'a slug that is not a category: no marker, no error');

echo "\nElementor: which elements the homepage leaves out\n";
class FakeBp { function get_active_devices_list() { return array('mobile', 'mobile_extra', 'tablet', 'tablet_extra', 'laptop', 'desktop'); } }
class FakeMode { public $on = false; function is_edit_mode() { return $this->on; } function is_preview_mode() { return $this->on; } }
class FakeDoc { public $id; function __construct($id) { $this->id = $id; } function get_main_id() { return $this->id; } }
class FakeDocs { public $cur; function get_current() { return $this->cur; } }
class FakeEl {
    public $id, $s;
    function __construct($id, $hidden) { $this->id = $id; $this->s = array(); foreach ($hidden as $d) $this->s['hide_' . $d] = 'hidden-' . $d; }
    function get_settings($k) { return isset($this->s[$k]) ? $this->s[$k] : ''; }
    function get_id() { return $this->id; }
}
$E = new \Elementor\Plugin(); \Elementor\Plugin::$instance = $E;
$E->breakpoints = new FakeBp(); $E->editor = new FakeMode(); $E->preview = new FakeMode(); $E->documents = new FakeDocs();
$E->documents->cur = new FakeDoc(75);
$GLOBALS['front'] = true; $GLOBALS['ajax'] = false;
$six = array('mobile', 'mobile_extra', 'tablet', 'tablet_extra', 'laptop', 'desktop');
ok(af_home_skip_hidden_everywhere(true, new FakeEl('a0f69ef', $six)) === false, 'hidden at all six, in the homepage: left out');
ok(in_array('a0f69ef', af_home_not_sent(), true), 'and named in the note at the end of the page');
ok(af_home_skip_hidden_everywhere(true, new FakeEl('ok1', array('mobile', 'mobile_extra'))) === true, 'hidden on phones only: sent');
ok(af_home_skip_hidden_everywhere(true, new FakeEl('ok2', array('desktop', 'laptop', 'tablet_extra', 'tablet', 'mobile_extra'))) === true, 'shown on the smallest phones only: sent');
ok(af_home_skip_hidden_everywhere(true, new FakeEl('ok3', array())) === true, 'shown everywhere: sent');
ok(af_home_skip_hidden_everywhere(false, new FakeEl('x', array())) === false, 'something else already said no: still no');
$E->documents->cur = new FakeDoc(443);
ok(af_home_skip_hidden_everywhere(true, new FakeEl('f1648db', $six)) === true, 'hidden everywhere but in the header template: sent (it is on every page)');
$E->documents->cur = null;
ok(af_home_skip_hidden_everywhere(true, new FakeEl('y', $six)) === true, 'no current document: sent');
$E->documents->cur = new FakeDoc(75);
$E->editor->on = true;
ok(af_home_skip_hidden_everywhere(true, new FakeEl('z', $six)) === true, 'in the Elementor editor: sent, so the owner can still find it');
$E->editor->on = false; $E->preview->on = true;
ok(af_home_skip_hidden_everywhere(true, new FakeEl('z', $six)) === true, 'in its preview: sent');
$E->preview->on = false;
$GLOBALS['front'] = false;
ok(af_home_skip_hidden_everywhere(true, new FakeEl('z', $six)) === true, 'on any other page: sent');
$GLOBALS['front'] = true; $GLOBALS['admin'] = true;
ok(af_home_skip_hidden_everywhere(true, new FakeEl('z', $six)) === true, 'in wp-admin: sent');
$GLOBALS['admin'] = false;
ok(af_home_skip_hidden_everywhere(true, new stdClass()) === true, 'something that is not an element: sent');
ok(count($GLOBALS['hooks']['elementor/frontend/container/should_render']) === 1
   && count($GLOBALS['hooks']['elementor/frontend/section/should_render']) === 1
   && count($GLOBALS['hooks']['elementor/frontend/widget/should_render']) === 1
   && count($GLOBALS['hooks']['elementor/frontend/column/should_render']) === 1, 'hooked for containers, sections, columns and widgets');

echo "\n" . ($fail ? "FAILED: $fail of " . ($pass + $fail) : "all $pass passed") . "\n";
exit($fail ? 1 : 0);
}
