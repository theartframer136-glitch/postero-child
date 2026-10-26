<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. Is the shop ready for Google Merchant Center?
 *
 *   1  store settings: currency, selling and shipping countries, indexing
 *   2  shipping zones, methods and costs (Merchant Center needs these to match)
 *   3  the policy pages Google looks for, and the contact details on them
 *   4  the Google feed (inc/marketplace.php) built here, checked item by item:
 *      images, prices, titles, descriptions, brand, ids, the word "free",
 *      names of licensed characters and brands, digital items
 *   5  feed price against the price range the product page offers
 *   6  whether Google has ever fetched the feed (the sync log)
 * Prints no key: the feed key is reported by length only.
 */
if (!defined('ABSPATH')) exit;

echo "=== 1 store settings\n";
echo '  currency: ' . get_woocommerce_currency() . "\n";
echo '  base location: ' . get_option('woocommerce_default_country') . ', ' . get_option('woocommerce_store_city') . ' ' . get_option('woocommerce_store_postcode') . "\n";
echo '  store address line: ' . get_option('woocommerce_store_address') . "\n";
echo '  sells to: ' . get_option('woocommerce_allowed_countries') . ' ' . implode(',', (array) get_option('woocommerce_specific_allowed_countries')) . "\n";
echo '  ships to: ' . get_option('woocommerce_ship_to_countries') . ' ' . implode(',', (array) get_option('woocommerce_specific_ship_to_countries')) . "\n";
echo '  taxes on: ' . get_option('woocommerce_calc_taxes') . ', prices include tax: ' . get_option('woocommerce_prices_include_tax') . "\n";
echo '  search engines allowed (blog_public): ' . get_option('blog_public') . "\n";
echo '  guest checkout: ' . get_option('woocommerce_enable_guest_checkout') . "\n";
echo '  site url: ' . home_url('/') . "\n";

echo "\n=== 2 shipping\n";
if (class_exists('WC_Shipping_Zones')) {
    $zones = WC_Shipping_Zones::get_zones();
    $zones[] = array('zone_name' => '(rest of world)', 'zone_locations' => array(), 'shipping_methods' => (new WC_Shipping_Zone(0))->get_shipping_methods());
    foreach ($zones as $z) {
        $locs = array();
        foreach ((array) $z['zone_locations'] as $l) $locs[] = is_object($l) ? $l->type . ':' . $l->code : '';
        echo '  zone "' . $z['zone_name'] . '" [' . implode(' ', array_slice($locs, 0, 12)) . (count($locs) > 12 ? ' +' . (count($locs) - 12) : '') . "]\n";
        foreach ((array) $z['shipping_methods'] as $m) {
            $cost = isset($m->instance_settings['cost']) ? $m->instance_settings['cost'] : '';
            $min = isset($m->instance_settings['min_amount']) ? ' min_amount=' . $m->instance_settings['min_amount'] : '';
            echo '    - ' . $m->id . ' "' . $m->get_title() . '" enabled=' . $m->enabled . ' cost=' . $cost . $min . "\n";
        }
    }
}

echo "\n=== 3 policy and contact pages\n";
$ids = array(
    'terms (Woo)'   => (int) get_option('woocommerce_terms_page_id'),
    'privacy (WP)'  => (int) get_option('wp_page_for_privacy_policy'),
    'refund (Woo)'  => (int) get_option('woocommerce_refund_returns_page_id'),
);
global $wpdb;
foreach ($wpdb->get_results("SELECT ID, post_name FROM {$wpdb->posts} WHERE post_type='page' AND post_status='publish' AND post_name REGEXP 'ship|deliver|return|refund|contact|about|terms|privacy|policy|faq'") as $p) {
    $ids['slug ' . $p->post_name] = (int) $p->ID;
}
$phone_re = '~610\D{0,3}470\D{0,2}7280~';
foreach ($ids as $label => $id) {
    if (!$id) { echo "  $label: (not set)\n"; continue; }
    $post = get_post($id);
    if (!$post) { echo "  $label: #$id missing\n"; continue; }
    $text = wp_strip_all_tags(do_shortcode($post->post_content) . ' ' . (string) get_post_meta($id, '_elementor_data', true));
    $words = str_word_count($text);
    echo sprintf("  %-26s #%d %s %s  words=%d  phone=%s  Hockessin=%s  'free'=%d  days-words=%s\n",
        $label, $id, $post->post_status, get_permalink($id), $words,
        preg_match($phone_re, $text) ? 'yes' : 'no',
        stripos($text, 'hockessin') !== false ? 'yes' : 'no',
        preg_match_all('~\bfree\b~i', $text),
        preg_match('~\b\d+\s*(?:-|to|–)?\s*\d*\s*(?:business\s+)?days?\b~i', $text, $mm) ? trim($mm[0]) : 'none');
}
// Address and phone as the footer / contact block prints them
$footer = '';
foreach (array('get_hfe_footer_id') as $fn) if (function_exists($fn)) $footer = (string) get_post_meta((int) call_user_func($fn), '_elementor_data', true);
echo '  footer: phone=' . (preg_match($phone_re, $footer) ? 'yes' : 'no') . ' Hockessin=' . (stripos($footer, 'hockessin') !== false ? 'yes' : 'no') . ' email=' . (preg_match('~[\w.+-]+@[\w-]+\.[\w.]+~', $footer, $em) ? $em[0] : 'none') . "\n";

echo "\n=== 4 Google feed\n";
if (!function_exists('af_mk_render_feed')) { echo "  inc/marketplace.php not loaded\n"; return; }
echo '  feed key length: ' . strlen((string) get_option('af_mk_key')) . "\n";
$t0 = microtime(true);
ob_start();
af_mk_render_feed('google');
$xml = ob_get_clean();
echo '  built in ' . round(microtime(true) - $t0, 1) . ' s, ' . round(strlen($xml) / 1024) . " KB\n";
libxml_use_internal_errors(true);
$doc = simplexml_load_string($xml);
if (!$doc) { echo "  FEED DOES NOT PARSE: " . (libxml_get_errors() ? trim(libxml_get_errors()[0]->message) : '?') . "\n"; return; }
$items = $doc->channel->item;
$published = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish'");
echo '  items in feed: ' . count($items) . " (published products: $published)\n";

$tm = array('batman','iron ?man','marvel','avengers','spider-?man','superman','wonder woman','joker','dc comics','disney','mickey','minnie','frozen','pixar','star wars','yoda','darth','harry potter','hogwarts','pokemon','pikachu','naruto','one piece','dragon ball','goku','demon slayer','attack on titan','hello kitty','barbie','lego','nike','adidas','ferrari','lamborghini','porsche','bmw','mercedes','audi','rolex','gucci','louis vuitton','chanel','coca-?cola','f1','formula 1','nba','nfl','fifa','messi','ronaldo','beatles','elvis','marilyn monroe','game of thrones','breaking bad','friends','simpsons','minecraft','fortnite','mario','sonic','transformers','hulk','thor','captain america','deadpool','venom','thanos','groot','shrek','minion','peppa','paw patrol','looney','tom and jerry','scooby','sponge ?bob','garfield','snoopy','studio ghibli','totoro','kaws','banksy','picasso','van gogh');
$counts = array('no_image' => 0, 'zero_price' => 0, 'title_long' => 0, 'title_short' => 0, 'desc_short' => 0, 'free' => 0, 'out_of_stock' => 0, 'sale' => 0, 'gtin' => 0, 'gcat' => 0, 'img_not_https' => 0, 'dup_id' => 0);
$brands = $types = $ids_seen = $tm_hits = $free_hits = $prices = array();
foreach ($items as $it) {
    $g = $it->children('http://base.google.com/ns/1.0');
    $id = (string) $g->id; $title = (string) $g->title; $desc = (string) $g->description;
    if (isset($ids_seen[$id])) $counts['dup_id']++;
    $ids_seen[$id] = 1;
    if ((string) $g->image_link === '') $counts['no_image']++;
    if (stripos((string) $g->image_link, 'https://') !== 0) $counts['img_not_https']++;
    $pr = (float) $g->price; $prices[] = $pr;
    if ($pr <= 0) $counts['zero_price']++;
    if (mb_strlen($title) > 150) $counts['title_long']++;
    if (mb_strlen($title) < 15) $counts['title_short']++;
    if (mb_strlen($desc) < 50) $counts['desc_short']++;
    if ((string) $g->availability !== 'in_stock') $counts['out_of_stock']++;
    if ((string) $g->sale_price !== '') $counts['sale']++;
    if ((string) $g->gtin !== '') $counts['gtin']++;
    if ((string) $g->google_product_category !== '') $counts['gcat']++;
    if (preg_match('~\bfree\b~i', $title . ' ' . $desc)) { $counts['free']++; if (count($free_hits) < 8) $free_hits[] = "$id $title"; }
    foreach ($tm as $w) if (preg_match('~\b' . $w . '\b~i', $title)) { $tm_hits[$w][] = "$id $title"; }
    $b = (string) $g->brand; $brands[$b] = (isset($brands[$b]) ? $brands[$b] : 0) + 1;
    $ty = explode(' > ', (string) $g->product_type)[0]; $types[$ty] = (isset($types[$ty]) ? $types[$ty] : 0) + 1;
}
foreach ($counts as $k => $v) echo "  $k: $v\n";
sort($prices);
echo '  feed prices: min ' . ($prices ? $prices[0] : '-') . ', median ' . ($prices ? $prices[(int) (count($prices) / 2)] : '-') . ', max ' . ($prices ? end($prices) : '-') . "\n";
arsort($brands); echo '  brands: '; foreach ($brands as $b => $n) echo "\"$b\"=$n "; echo "\n";
arsort($types); echo "  first product_type level:\n"; foreach ($types as $b => $n) echo "    $n  $b\n";
if ($free_hits) { echo "  'free' in title/description (first 8):\n"; foreach ($free_hits as $h) echo "    $h\n"; }
echo '  titles naming characters or brands owned by others: ' . array_sum(array_map('count', $tm_hits)) . "\n";
foreach ($tm_hits as $w => $list) { echo "    [$w] " . count($list) . "\n"; foreach (array_slice($list, 0, 4) as $h) echo "      $h\n"; }
echo "  sample item:\n";
if (count($items)) foreach (explode("\n", $items[0]->asXML()) as $line) echo '    ' . mb_substr($line, 0, 200) . "\n";

// Digital, virtual, gift cards in the feed
$kinds = array('downloadable' => 0, 'virtual' => 0, 'variable' => 0, 'simple' => 0, 'other' => 0, 'giftcard' => 0, 'pricing_applies' => 0);
foreach (af_mk_products('google') as $p) {
    if ($p->is_downloadable()) $kinds['downloadable']++;
    if ($p->is_virtual()) $kinds['virtual']++;
    $t = $p->get_type();
    if (isset($kinds[$t])) $kinds[$t]++; else $kinds['other']++;
    if (preg_match('~gift\s*card~i', $p->get_name())) $kinds['giftcard']++;
    if (function_exists('af_pricing_applies') && af_pricing_applies($p)) $kinds['pricing_applies']++;
}
echo '  in feed by kind: '; foreach ($kinds as $k => $v) echo "$k=$v "; echo "\n";

echo "\n=== 5 feed price against the page's price range (first 12 products with a size choice)\n";
$n = 0;
foreach (af_mk_products('google') as $p) {
    if (!function_exists('af_product_price_range')) break;
    $r = af_product_price_range($p);
    if (!$r) continue;
    $row = af_mk_row($p, 'google');
    echo sprintf("  #%d feed price=%.2f regular=%.2f  page range=%.2f-%.2f (%d sizes)  %s\n", $p->get_id(), $row['price'], $row['regular'], $r['low'], $r['high'], $r['count'], mb_substr($p->get_name(), 0, 60));
    if (++$n >= 12) break;
}

echo "\n=== 6 has anyone fetched the feeds?\n";
$t = af_mk_table();
if ($wpdb->get_var("SHOW TABLES LIKE '" . esc_sql($t) . "'") === $t) {
    foreach ($wpdb->get_results("SELECT channel, direction, action, COUNT(*) n, MAX(created_at) last FROM {$t} GROUP BY channel, direction, action ORDER BY last DESC LIMIT 20") as $r) {
        echo "  {$r->channel} {$r->direction} {$r->action}: {$r->n}, last {$r->last}\n";
    }
} else echo "  no sync log table\n";
if (!function_exists('is_plugin_active')) require_once ABSPATH . 'wp-admin/includes/plugin.php';
foreach (array('google-listings-and-ads/google-listings-and-ads.php', 'woocommerce-product-feeds/woocommerce-gpf.php', 'product-feed-pro/woocommerce-sea.php', 'facebook-for-woocommerce/facebook-for-woocommerce.php') as $pl) {
    echo "  plugin $pl: " . (is_plugin_active($pl) ? 'ACTIVE' : (file_exists(WP_PLUGIN_DIR . '/' . $pl) ? 'installed, off' : 'not installed')) . "\n";
}
