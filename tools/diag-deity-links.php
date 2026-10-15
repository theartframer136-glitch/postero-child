<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * READ-ONLY. Is the "Shop by Deity & Vastu" row (inc/deity-links.php) loaded
 * on the live site, and what does it render? Prints the module's presence,
 * the resolved categories with their live URLs, the rendered HTML, and a
 * fetch of one category page and one product page from the server itself
 * to confirm the row reaches the delivered HTML.
 *
 *   wp eval-file wp-content/themes/postero-child/tools/diag-deity-links.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }

echo "=== MODULE\n";
echo "  af_deity_links_html: " . (function_exists('af_deity_links_html') ? 'present' : 'MISSING (module not loaded)') . "\n";
echo "  af_post_faq_node:    " . (function_exists('af_post_faq_node') ? 'present' : 'MISSING') . "\n";
if (!function_exists('af_deity_links_html')) { echo "=== DONE ===\n"; return; }

echo "\n=== CATEGORIES RESOLVED\n";
foreach (af_deity_link_terms() as $slug => $label) {
    $t = get_term_by('slug', $slug, 'product_cat');
    echo sprintf("  %-18s %-18s %s\n", $slug, $label, ($t && !is_wp_error($t)) ? get_term_link($t) . " ({$t->count} products)" : 'NOT FOUND');
}

echo "\n=== RENDERED HTML (current = seven-horses)\n";
$sh = get_term_by('slug', 'seven-horses', 'product_cat');
$html = af_deity_links_html($sh ? (int) $sh->term_id : 0);
echo "  length: " . strlen($html) . " | links: " . substr_count($html, '<a href') . " | current chip: " . (strpos($html, 'aria-current="page"') !== false ? 'yes' : 'no') . "\n";
echo "  " . substr(strip_tags(str_replace('</li>', ' | ', $html)), 0, 400) . "\n";

echo "\n=== DELIVERED PAGES (fetched from this server)\n";
$pages = array(
    'category lakshmi-ganesha' => home_url('/product-category/digital-canvas-prints/lakshmi-ganesha/'),
    'shop'                     => home_url('/shop/'),
    'front page'               => home_url('/'),
);
$pid = get_posts(array('post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids', 'tax_query' => array(array('taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => 'radha-krishna'))));
if ($pid) $pages['product (radha-krishna)'] = get_permalink($pid[0]);
foreach ($pages as $label => $url) {
    $r = wp_remote_get($url, array('timeout' => 40, 'sslverify' => false, 'headers' => array('User-Agent' => 'af-diag/1.0')));
    if (is_wp_error($r)) { echo "  $label: ERROR " . $r->get_error_message() . "\n"; continue; }
    $body = (string) wp_remote_retrieve_body($r);
    $has  = strpos($body, 'class="af-deity-nav"') !== false;
    $n    = preg_match_all('#/product-category/digital-canvas-prints/(seven-horses|lord-shiva|lakshmi-ganesha|tirupati-balaji|lord-rama|radha-krishna|hindu-deities|vaastu-art)/#', $body);
    $free = preg_match('/free\s+(usa?\s+)?(shipping|delivery)/i', strip_tags($body));
    echo sprintf("  %-26s HTTP %s | nav row: %s | deity links in page: %d | says free shipping: %s\n", $label, wp_remote_retrieve_response_code($r), $has ? 'YES' : 'no', $n, $free ? 'YES' : 'no');
}

echo "\n=== SITEMAP (fetched from this server)\n";
foreach (array('sitemap_index.xml', 'product_cat-sitemap.xml') as $sm) {
    $r = wp_remote_get(home_url('/' . $sm), array('timeout' => 40, 'sslverify' => false, 'headers' => array('User-Agent' => 'af-diag/1.0')));
    if (is_wp_error($r)) { echo "  $sm: ERROR " . $r->get_error_message() . "\n"; continue; }
    $b = (string) wp_remote_retrieve_body($r);
    echo sprintf("  %-24s HTTP %s | %d bytes | product_cat-sitemap listed: %s | lakshmi-ganesha listed: %s | seven-horses listed: %s\n", $sm, wp_remote_retrieve_response_code($r), strlen($b),
        strpos($b, 'product_cat-sitemap') !== false ? 'yes' : 'no', strpos($b, '/lakshmi-ganesha/') !== false ? 'yes' : 'no', strpos($b, '/seven-horses/') !== false ? 'yes' : 'no');
}
$sm = get_option('rank-math-options-sitemap', array());
echo "  rank math tax_product_cat_sitemap: " . (isset($sm['tax_product_cat_sitemap']) ? $sm['tax_product_cat_sitemap'] : '(unset)') . "\n";
echo "\n=== DONE ===\n";
