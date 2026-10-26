<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/** Read-only. What a category guide may say: the category's own text and its products (name, price, sizes in the name). */
if (!defined('ABSPATH')) exit;
foreach (array('seven-horses', 'indian-culture') as $slug) {
    $t = get_term_by('slug', $slug, 'product_cat');
    if (!$t) { echo "$slug: missing\n"; continue; }
    $parent = $t->parent ? get_term($t->parent)->slug : '-';
    echo "=== $slug (#{$t->term_id}, name '{$t->name}', parent $parent, count {$t->count})\n";
    echo '  description: ' . mb_substr(wp_strip_all_tags($t->description), 0, 600) . "\n";
    echo '  rank_math_title: ' . get_term_meta($t->term_id, 'rank_math_title', true) . "\n";
    echo '  rank_math_description: ' . get_term_meta($t->term_id, 'rank_math_description', true) . "\n";
    echo '  link: ' . get_term_link($t) . "\n";
    $ids = get_posts(array('post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids',
        'tax_query' => array(array('taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $t->term_id))));
    $sizes = array();
    foreach ($ids as $id) {
        $p = wc_get_product($id);
        if (!$p) continue;
        if (preg_match_all('~(\d+(?:\.\d+)?)\s*[x×]\s*(\d+(?:\.\d+)?)\s*(ft|feet|foot|in|inch|inches|")~iu', $p->get_name(), $m, PREG_SET_ORDER)) foreach ($m as $mm) { $k = $mm[1] . '×' . $mm[2] . ' ' . strtolower($mm[3]); $sizes[$k] = (isset($sizes[$k]) ? $sizes[$k] : 0) + 1; }
        $cats = wp_get_post_terms($id, 'product_cat', array('fields' => 'slugs'));
        echo sprintf("  #%d $%s  %s  [%s]\n", $id, wc_get_price_to_display($p), mb_substr($p->get_name(), 0, 110), implode(',', array_slice($cats, 0, 6)));
    }
    arsort($sizes); echo '  sizes named in titles: ' . json_encode($sizes, JSON_UNESCAPED_UNICODE) . "\n";
    if (function_exists('af_sizes_available')) echo '  sizes the selector sells: ' . json_encode(af_sizes_available(), JSON_UNESCAPED_UNICODE) . "\n";
}
// Does any blog post already cover these topics?
global $wpdb;
foreach ($wpdb->get_results("SELECT ID, post_title, post_name FROM {$wpdb->posts} WHERE post_type='post' AND post_status='publish' AND (post_title LIKE '%horse%' OR post_title LIKE '%India%' OR post_title LIKE '%Vastu%')") as $p) echo "  post #{$p->ID} /{$p->post_name}/ {$p->post_title}\n";
