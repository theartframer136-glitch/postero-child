<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Tests the Google Merchant Center rules in inc/marketplace.php with
 * WordPress and WooCommerce stubbed:
 *     php tools/test-merchant-feed.php
 */
define('ABSPATH', '/nowhere/');
function add_action() {} function add_filter() {}
function apply_filters($h, $v) { return $v; }

// The size selector as it stood on 26 Oct 2026: five sizes, $60-$100.
function af_pricing_applies($p) { return $p->sized; }
function af_pricing_config($id) { return array('sizes' => array('2x3' => 60, '3x2' => 60, '2.5x3' => 65, '3x4' => 80, '3x5' => 100, '4x4' => 110)); }
function af_sizes_available() { return array('2x3', '3x2', '2.5x3', '3x4', '3x5'); }

class P {
    public $sized = true;
    function __construct($id, $name, $price, $regular, $imgs = array(1, 1)) { $this->id = $id; $this->name = $name; $this->price = $price; $this->regular = $regular; $this->imgs = $imgs; }
    function get_id() { return $this->id; }
    function get_name() { return $this->name; }
    function get_regular_price() { return $this->regular; }
    function get_image_id() { return $this->imgs[0]; }
    function get_gallery_image_ids() { return array_slice($this->imgs, 1); }
    function get_description() { return 'A canvas.'; }
    function get_short_description() { return ''; }
    function get_sku() { return 'SKU-' . $this->id; }
    function is_in_stock() { return true; }
    function get_stock_quantity() { return null; }
    function is_purchasable() { return true; }
    function get_catalog_visibility() { return 'visible'; }
}
$GLOBALS['products'] = array();
function wc_get_price_to_display($p) { return $p->price; }
function wc_get_product($id) { return $GLOBALS['products'][$id]; }
function get_posts($a) { return array_keys($GLOBALS['products']); }
function get_post_meta() { return ''; }
function wp_get_attachment_image_url($id) { return 'https://x.test/img' . $id . '.jpg'; }
function wp_get_post_terms() { return array('All Art Prints'); }
function wp_strip_all_tags($s) { return strip_tags($s); }
function wp_trim_words($s) { return $s; }
function get_permalink($id) { return 'https://x.test/p/' . $id; }
function get_woocommerce_currency() { return 'USD'; }
function get_bloginfo() { return 'The ART FRAMER'; }
function home_url($p = '') { return 'https://x.test' . $p; }
function esc_url($u) { return $u; }
function get_option() { return ''; }
require __DIR__ . '/../inc/marketplace.php';

$pass = 0; $fail = 0;
function same($label, $got, $want) { global $pass, $fail;
    if ($got === $want) { $pass++; echo "  OK    $label\n"; }
    else { $fail++; echo "  FAIL  $label\n        got  " . var_export($got, true) . "\n        want " . var_export($want, true) . "\n"; } }

echo "=== what Google is not sent ===\n";
same('a canvas at a price the page sells: sent', af_mk_google_withheld(new P(1, 'Buddha Lotus Canvas', 100, 161.29)), '');
same('Batman: held back', af_mk_google_withheld(new P(2, 'Batman Comic Art Canvas Wall Art', 80, 100)), 'licensed character');
same('Iron Man: held back', af_mk_google_withheld(new P(3, 'Iron Man Portrait Canvas', 80, 100)), 'licensed character');
same('"Ironman" written as one word: held back', af_mk_google_withheld(new P(4, 'Ironman Poster', 80, 100)), 'licensed character');
same('"Safari Friends" is not a brand: sent', af_mk_google_withheld(new P(5, 'Safari Friends Nursery Canvas', 80, 100)), '');
same('a word that only contains "batman" is not matched', af_mk_google_withheld(new P(6, 'Batmanesque Abstract', 80, 100)), '');
same('a gift card: held back', af_mk_google_withheld(new P(7, 'The Art Framer Gift Card', 50, 50)), 'gift card');
same('$110 for a 48x48 the selector does not offer: held back', af_mk_google_withheld(new P(8, 'Maa Durga 48x48', 110, 144.74)), 'price not offered on the page');
$acc = new P(9, 'Stretcher Bars Kit', 28.63, 30); $acc->sized = false;
same('no size selector (an accessory): its own price stands', af_mk_google_withheld($acc), '');

echo "\n=== the Google feed ===\n";
$GLOBALS['products'] = array(1 => new P(1, 'Buddha Lotus Canvas', 100, 161.29, array(1, 1, 2)), 2 => new P(2, 'Batman Comic Art', 80, 100), 8 => new P(8, 'Maa Durga 48x48', 110, 144.74));
ob_start(); af_mk_render_feed('google'); $g = ob_get_clean();
same('one item: Batman and the unsellable price left out', substr_count($g, '<item>'), 1);
same('the price is the one the shopper pays', strpos($g, '<g:price>100.00 USD</g:price>') !== false, true);
same('no crossed-out price', strpos($g, 'sale_price'), false);
same('the made-to-order time is stated', strpos($g, '<g:min_handling_time>3</g:min_handling_time>') !== false && strpos($g, '<g:max_handling_time>5</g:max_handling_time>') !== false, true);
same('the main picture is not repeated as an extra one', substr_count($g, 'img1.jpg'), 1);
same('a different gallery picture is still sent', strpos($g, '<g:additional_image_link>https://x.test/img2.jpg</g:additional_image_link>') !== false, true);
same('the feed parses', simplexml_load_string($g) !== false, true);

echo "\n=== the other channels are unchanged ===\n";
ob_start(); af_mk_render_feed('meta'); $m = ob_get_clean();
same('Meta still gets every item', substr_count($m, '<item>'), 3);
same('Meta still gets regular and sale price', strpos($m, '<g:price>161.29 USD</g:price>') !== false && strpos($m, '<g:sale_price>100.00 USD</g:sale_price>') !== false, true);
same('Meta gets no handling time', strpos($m, 'handling_time'), false);

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
