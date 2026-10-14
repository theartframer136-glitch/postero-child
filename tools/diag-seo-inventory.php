<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * READ-ONLY. The SEO inventory an SEO plan for the USA market needs from
 * inside the site, in one run:
 *
 *   1. every product category: slug, name, parent, product count, the Rank
 *      Math title and description it carries today, and whether it has an
 *      archive intro (term description);
 *   2. the front page, shop page and blog hub: current Rank Math title and
 *      description;
 *   3. Rank Math's site-wide title templates and knowledge-graph settings
 *      (names only for anything that looks like a key);
 *   4. products: how many carry a Rank Math title / description / focus
 *      keyword, how many descriptions still say "free shipping" (the owner's
 *      rule is that nothing on the site may say so), and a sample;
 *   5. posts: how many are published, the ten newest titles and slugs;
 *   6. the business address option, present or absent (never its value).
 *
 * Prints no key or secret. Makes no changes.
 *
 *   wp eval-file wp-content/themes/postero-child/tools/diag-seo-inventory.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
global $wpdb;

$clip = function ($s, $n = 110) { $s = trim(preg_replace('/\s+/u', ' ', (string) $s)); return mb_strlen($s) > $n ? mb_substr($s, 0, $n - 1) . '…' : $s; };

echo "=== 1. PRODUCT CATEGORIES (slug | name | parent | products | rm_title | rm_desc | intro chars)\n";
$terms = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'count', 'order' => 'DESC'));
if (is_wp_error($terms)) { echo "  error: " . $terms->get_error_message() . "\n"; $terms = array(); }
$by_id = array(); foreach ($terms as $t) $by_id[$t->term_id] = $t;
foreach ($terms as $t) {
    $parent = ($t->parent && isset($by_id[$t->parent])) ? $by_id[$t->parent]->slug : '-';
    $rt = (string) get_term_meta($t->term_id, 'rank_math_title', true);
    $rd = (string) get_term_meta($t->term_id, 'rank_math_description', true);
    echo sprintf("  %-32s | %-34s | %-22s | %4d | %s | %s | %d\n",
        $t->slug, $clip($t->name, 34), $parent, (int) $t->count,
        $rt === '' ? '(none)' : $clip($rt, 70), $rd === '' ? '(none)' : $clip($rd, 60),
        mb_strlen(wp_strip_all_tags((string) $t->description)));
}
echo "  total categories: " . count($terms) . "\n";

echo "\n=== 2. KEY PAGES\n";
$pages = array('front' => (int) get_option('page_on_front'), 'shop' => function_exists('wc_get_page_id') ? (int) wc_get_page_id('shop') : 0);
foreach (array('about', 'contact', 'faqs', 'shipping-delivery', 'blog') as $slug) { $p = get_page_by_path($slug); if ($p) $pages[$slug] = $p->ID; }
foreach ($pages as $label => $id) {
    if (!$id) { echo "  $label: (no page)\n"; continue; }
    echo sprintf("  %-18s #%-6d title=%s\n  %-18s         desc=%s\n", $label, $id,
        $clip((string) get_post_meta($id, 'rank_math_title', true), 90) ?: '(none)', '',
        $clip((string) get_post_meta($id, 'rank_math_description', true), 160) ?: '(none)');
}
echo "  home page H1 policy: see functions.php (no keyword H1 by design)\n";

echo "\n=== 3. RANK MATH SITE SETTINGS\n";
$titles = get_option('rank-math-options-titles', array());
foreach (array('title_separator', 'homepage_title', 'homepage_description', 'pt_product_title', 'pt_product_description',
               'tax_product_cat_title', 'tax_product_cat_description', 'pt_post_title', 'pt_page_title',
               'knowledgegraph_type', 'knowledgegraph_name', 'url', 'local_business_type', 'local_address',
               'social_url_facebook', 'twitter_author_names', 'open_graph_image') as $k) {
    if (!isset($titles[$k])) { echo "  $k: (unset)\n"; continue; }
    $v = $titles[$k];
    echo "  $k: " . (is_scalar($v) ? $clip((string) $v, 120) : wp_json_encode($v)) . "\n";
}
$general = get_option('rank-math-options-general', array());
foreach (array('breadcrumbs', 'strip_category_base', 'wc_remove_product_base', 'wc_remove_category_base', 'wc_remove_category_parent_slugs') as $k) {
    echo "  general.$k: " . (isset($general[$k]) ? (is_scalar($general[$k]) ? $general[$k] : wp_json_encode($general[$k])) : '(unset)') . "\n";
}
$sitemap = get_option('rank-math-options-sitemap', array());
foreach (array('pt_product_sitemap', 'tax_product_cat_sitemap', 'tax_product_tag_sitemap', 'pt_post_sitemap') as $k) {
    echo "  sitemap.$k: " . (isset($sitemap[$k]) ? (is_scalar($sitemap[$k]) ? $sitemap[$k] : wp_json_encode($sitemap[$k])) : '(unset)') . "\n";
}
echo "  woocommerce_permalinks: " . wp_json_encode(get_option('woocommerce_permalinks')) . "\n";
echo "  permalink_structure: " . get_option('permalink_structure') . "\n";

echo "\n=== 4. PRODUCTS\n";
$total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish'");
$cnt = function ($key) use ($wpdb) {
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT pm.post_id) FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID=pm.post_id
         WHERE p.post_type='product' AND p.post_status='publish' AND pm.meta_key=%s AND pm.meta_value<>''", $key));
};
echo "  published products: $total\n";
echo "  with rank_math_title: " . $cnt('rank_math_title') . " | rank_math_description: " . $cnt('rank_math_description') . " | rank_math_focus_keyword: " . $cnt('rank_math_focus_keyword') . "\n";
$free = $wpdb->get_results(
    "SELECT pm.post_id, pm.meta_value FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID=pm.post_id
     WHERE p.post_type='product' AND p.post_status='publish' AND pm.meta_key='rank_math_description'
       AND LOWER(pm.meta_value) LIKE '%free%shipping%' LIMIT 500");
echo "  product meta descriptions saying free shipping: " . count($free) . "\n";
foreach (array_slice($free, 0, 5) as $r) echo "    #{$r->post_id}: " . $clip($r->meta_value, 150) . "\n";
$free_content = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('product','page','post') AND post_status='publish' AND (LOWER(post_content) LIKE '%free shipping%' OR LOWER(post_excerpt) LIKE '%free shipping%')");
echo "  products/pages/posts whose content says free shipping: $free_content\n";
echo "  sample of product SEO meta (newest 12):\n";
$ids = get_posts(array('post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => 12, 'fields' => 'ids', 'orderby' => 'date', 'order' => 'DESC'));
foreach ($ids as $pid) {
    $cats = wp_get_post_terms($pid, 'product_cat', array('fields' => 'slugs'));
    echo sprintf("    #%-6d name=%s\n           cats=%s\n           title=%s\n           desc=%s\n           kw=%s\n", $pid,
        $clip(get_the_title($pid), 90), implode(',', is_array($cats) ? $cats : array()),
        $clip((string) get_post_meta($pid, 'rank_math_title', true), 90) ?: '(none)',
        $clip((string) get_post_meta($pid, 'rank_math_description', true), 160) ?: '(none)',
        $clip((string) get_post_meta($pid, 'rank_math_focus_keyword', true), 60) ?: '(none)');
}
$tags = get_terms(array('taxonomy' => 'product_tag', 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC', 'number' => 25));
if (!is_wp_error($tags)) { echo "  top product tags: " . implode(', ', array_map(function ($t) { return $t->slug . '(' . $t->count . ')'; }, $tags)) . "\n"; }

echo "\n=== 5. POSTS\n";
$posts = get_posts(array('post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 10, 'orderby' => 'date', 'order' => 'DESC'));
echo "  published posts: " . (int) wp_count_posts('post')->publish . "\n";
foreach ($posts as $p) echo "    " . $p->post_date . "  /" . $p->post_name . "/  " . $clip($p->post_title, 80) . "\n";
$cats = get_categories(array('hide_empty' => true));
echo "  post categories: " . implode(', ', array_map(function ($c) { return $c->slug . '(' . $c->count . ')'; }, $cats)) . "\n";

echo "\n=== 6. BUSINESS ADDRESS OPTION\n";
$addr = get_option('af_business_address', array());
echo "  af_business_address: " . ((is_array($addr) && !empty($addr['city'])) ? 'present (city/region set)' : 'absent') . "\n";
echo "  af_shipping_copy: " . (function_exists('af_shipping_copy') ? wp_json_encode(af_shipping_copy()) : '(function missing)') . "\n";

echo "\n=== DONE ===\n";
