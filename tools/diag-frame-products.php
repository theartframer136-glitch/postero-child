<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * READ-ONLY. What the frame products are, so they can be given prices:
 * every product in the frame categories with its type, price, SKU, size
 * and colour attributes, variations, and the category's own page text.
 * Makes no changes, prints no secrets.
 *
 *   wp eval-file wp-content/themes/postero-child/tools/diag-frame-products.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }

foreach (array('art-accessories', 'diy-floating-frames', 'premium-aluminium-frames', 'wooden-frames', 'frame-colors', 'frame-sizes', 'stretcher-bars', 'rolled-canvas') as $slug) {
    $t = get_term_by('slug', $slug, 'product_cat');
    if (!$t) { echo "=== $slug: not a category\n"; continue; }
    $kids = get_terms(array('taxonomy' => 'product_cat', 'parent' => $t->term_id, 'hide_empty' => false, 'fields' => 'slugs'));
    printf("=== %s #%d count=%d parent=%d children=[%s]\n  desc: %s\n", $slug, $t->term_id, $t->count, $t->parent,
        is_array($kids) ? implode(',', $kids) : '', mb_substr(trim(wp_strip_all_tags($t->description)), 0, 300));
}

echo "\n=== PRODUCTS IN diy-floating-frames (incl. children)\n";
$ids = wc_get_products(array('category' => array('diy-floating-frames'), 'limit' => 200, 'status' => array('publish', 'private', 'draft'), 'return' => 'ids'));
foreach ($ids as $id) {
    $p = wc_get_product($id);
    if (!$p) continue;
    $cats = wp_get_post_terms($id, 'product_cat', array('fields' => 'slugs'));
    printf("#%d [%s/%s] \"%s\" sku=%s price=%s regular=%s sale=%s stock=%s\n   cats=%s\n", $id, $p->get_type(), $p->get_status(), $p->get_name(), $p->get_sku(),
        var_export($p->get_price(), true), var_export($p->get_regular_price(), true), var_export($p->get_sale_price(), true), $p->get_stock_status(), implode(',', $cats));
    foreach ($p->get_attributes() as $a) {
        $opts = $a->is_taxonomy() ? wc_get_product_terms($id, $a->get_name(), array('fields' => 'names')) : $a->get_options();
        printf("   attr %s%s: %s\n", $a->get_name(), $a->get_variation() ? ' (variation)' : '', implode(' | ', (array) $opts));
    }
    if ($p->is_type('variable')) {
        foreach ($p->get_children() as $vid) {
            $v = wc_get_product($vid);
            if ($v) printf("   var #%d %s price=%s\n", $vid, wc_get_formatted_variation($v, true, false, false), var_export($v->get_price(), true));
        }
    }
    $short = trim(wp_strip_all_tags($p->get_short_description()));
    if ($short !== '') echo '   short: ' . mb_substr($short, 0, 200) . "\n";
    $long = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($p->get_description())));
    if ($long !== '') echo '   desc: ' . mb_substr($long, 0, 400) . "\n";
}

echo "\n=== PRICE RULES ALREADY IN THE SHOP\n";
printf("  af_bar_rate_sqft=%s  af_kit_prices=%s\n", var_export(get_option('af_bar_rate_sqft'), true), wp_json_encode(function_exists('af_kit_prices') ? af_kit_prices() : null));
foreach (array('af_frame_rate_sqft', 'af_frame_prices', 'af_frame_colour_fees', 'af_size_prices') as $o) printf("  %s=%s\n", $o, wp_json_encode(get_option($o)));
