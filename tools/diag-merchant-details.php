<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. The details behind tools/diag-merchant-readiness.php:
 *   1  every sentence with the word "free" on the policy pages and in the feed
 *   2  every sentence that sets a return / refund window, page by page
 *   3  feed prices that a shopper cannot pick on the product page
 *   4  how the product page itself shows the price (regular struck through?)
 *   5  the shipping bands and what a typical order is charged
 */
if (!defined('ABSPATH')) exit;

$sentences = function ($text, $re) {
    $out = array();
    foreach (preg_split('~(?<=[.!?])\s+|\n+~', $text) as $s) {
        $s = trim(preg_replace('~\s+~', ' ', $s));
        if ($s !== '' && preg_match($re, $s)) $out[] = mb_substr($s, 0, 220);
    }
    return array_values(array_unique($out));
};
$page_text = function ($id) {
    $p = get_post($id);
    if (!$p) return '';
    $el = (string) get_post_meta($id, '_elementor_data', true);
    if ($el !== '') {
        $d = json_decode($el, true);
        $bits = array();
        array_walk_recursive($d, function ($v, $k) use (&$bits) { if (is_string($v) && in_array($k, array('editor', 'title', 'text', 'description', 'tab_content', 'item_description', 'content'), true)) $bits[] = $v; });
        $el = implode("\n", $bits);
    }
    return html_entity_decode(wp_strip_all_tags(str_replace(array('</p>', '<br>', '<br />', '</li>', '</h2>', '</h3>'), "\n", do_shortcode($p->post_content) . "\n" . $el)), ENT_QUOTES, 'UTF-8');
};
$pages = array(157 => 'faqs', 11652 => 'shipping-delivery', 11653 => 'returns-exchanges', 11654 => 'refund-policy', 11655 => 'terms-conditions', 32260 => 'terms-of-use', 32261 => 'terms-of-service', 3 => 'privacy-policy', 159 => 'contact', 146 => 'about');

echo "=== 1 'free' on the pages\n";
foreach ($pages as $id => $slug) foreach ($sentences($page_text($id), '~\bfree\b~i') as $s) echo "  [$slug] $s\n";
echo "\n=== 1b 'free' in the 4 feed items\n";
foreach (af_mk_products('google') as $p) {
    $r = af_mk_row($p, 'google');
    foreach ($sentences($r['title'] . ". " . $r['description'], '~\bfree\b~i') as $s) echo "  #{$p->get_id()} $s\n";
}

echo "\n=== 2 return / refund windows\n";
foreach ($pages as $id => $slug) foreach ($sentences($page_text($id), '~\b\d+\s*(?:[-–]|to)?\s*\d*\s*(?:business\s+|calendar\s+)?days?\b~i') as $s) echo "  [$slug] $s\n";

echo "\n=== 3 feed price not one the page offers\n";
$bad = 0; $total = 0; $ex = array();
foreach (af_mk_products('google') as $p) {
    if (!function_exists('af_pricing_applies') || !af_pricing_applies($p)) continue;
    $cfg = af_pricing_config($p->get_id());
    $offer = array();
    foreach (af_sizes_available() as $label) if (isset($cfg['sizes'][$label])) $offer[$label] = (float) $cfg['sizes'][$label];
    $price = (float) wc_get_price_to_display($p);
    $total++;
    if (!in_array(round($price, 2), array_map(function ($v) { return round($v, 2); }, $offer), true)) {
        $bad++;
        if (count($ex) < 10) $ex[] = sprintf('#%d feed %.2f, page offers %s  %s', $p->get_id(), $price, json_encode($offer), mb_substr($p->get_name(), 0, 50));
    }
}
echo "  $bad of $total sized products\n";
foreach ($ex as $e) echo "  $e\n";
echo '  sizes offered: ' . json_encode(af_sizes_available()) . "\n";

echo "\n=== 4 the product page price, as WooCommerce prints it (#141, #7718)\n";
foreach (array(141, 7718) as $id) {
    $p = wc_get_product($id);
    if (!$p) continue;
    echo "  #$id regular=" . $p->get_regular_price() . ' sale=' . $p->get_sale_price() . ' on_sale=' . var_export($p->is_on_sale(), true) . ' sale dates=' . ($p->get_date_on_sale_from() ? $p->get_date_on_sale_from()->date('Y-m-d') : '-') . '..' . ($p->get_date_on_sale_to() ? $p->get_date_on_sale_to()->date('Y-m-d') : '-') . "\n";
    echo '    price_html: ' . trim(wp_strip_all_tags($p->get_price_html())) . "\n";
}
global $wpdb;
echo '  products with a sale price set: ' . $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key='_sale_price' AND meta_value<>''") . "\n";

echo "\n=== 5 shipping bands\n";
if (function_exists('af_distance_tiers')) {
    echo '  tiers option set by owner: ' . (get_option('af_distance_tiers') ? 'yes' : 'no (built-in placeholders)') . "\n";
    foreach (af_distance_tiers() as $t) echo '  up to ' . $t['mi'] . ' mi: base $' . $t['base'] . ' + $' . $t['per_lb'] . '/lb' . (isset($t['label']) ? '  (' . $t['label'] . ')' : '') . "\n";
}
