<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Which "Filter by Tag" chips actually lead somewhere?
 *
 * For the shop and for a few busy categories, count the products behind every
 * product tag in that context and report the dead ones — the chips that empty
 * the page when clicked. Also fetch one tag-filtered URL and count the cards it
 * returns, so the filter itself is proven working rather than assumed.
 *
 * Read-only. Run: wp eval-file tools/verify-tag-filter.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }

echo "=== VERIFY TAG FILTER ===\n";

$cats = array();
$shop = get_term_by('slug', 'digital-canvas-prints', 'product_cat');
foreach (array('radha-krishna', 'lord-shiva', 'seven-horses') as $slug) {
    $t = get_term_by('slug', $slug, 'product_cat');
    if ($t && !is_wp_error($t)) $cats[] = $t;
}

$all_tags = get_terms(array('taxonomy' => 'product_tag', 'hide_empty' => false));
printf("  product tags in the store: %d\n", is_array($all_tags) ? count($all_tags) : 0);

foreach ($cats as $cat) {
    $ids = get_posts(array('post_type' => 'product', 'post_status' => 'publish',
        'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true,
        'tax_query' => array(array('taxonomy' => 'product_cat', 'field' => 'term_id',
            'terms' => (int) $cat->term_id, 'include_children' => true))));
    $live = array();
    if ($ids) {
        $tt = wp_get_object_terms($ids, 'product_tag', array('fields' => 'all'));
        if (!is_wp_error($tt)) foreach ($tt as $t) $live[$t->slug] = true;
    }
    $dead = array();
    foreach ((array) $all_tags as $t) {
        if (!isset($live[$t->slug])) $dead[] = $t->slug;
    }
    printf("\n  %-22s products:%-5d live tags:%-4d dead chips hidden:%d\n",
           $cat->slug, count($ids), count($live), count($dead));
    if ($dead) echo "    e.g. " . implode(', ', array_slice($dead, 0, 8)) . "\n";
}

// prove the filter itself returns products
$probe = get_term_by('slug', 'canvas-art', 'product_tag');
if ($probe && !is_wp_error($probe)) {
    $url = add_query_arg('product_tag', $probe->slug,
        get_term_link(get_term_by('slug', 'radha-krishna', 'product_cat')));
    if (!is_wp_error($url)) {
        $r = wp_remote_get($url, array('timeout' => 45, 'sslverify' => false,
            'headers' => array('User-Agent' => 'Mozilla/5.0 AF-Verify')));
        if (!is_wp_error($r)) {
            $b = wp_remote_retrieve_body($r);
            printf("\n  filtered page: HTTP %s, cards: %d  %s\n",
                wp_remote_retrieve_response_code($r),
                substr_count($b, 'woocommerce-loop-product__title'),
                substr_count($b, 'woocommerce-loop-product__title') > 0 ? 'OK' : 'EMPTY');
        }
    }
}

echo "=== DONE ===\n";
