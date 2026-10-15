<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * READ-ONLY. Are all 13 deity / Vastu categories in the category sitemap
 * Google is given, and does anything on the term stop indexing? Search
 * Console reported radha-krishna, lord-shiva, swaminarayan and indian-culture
 * as "not in sitemap" on 15 Oct.
 *
 *   wp eval-file wp-content/themes/postero-child/tools/diag-sitemap-deities.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
$slugs = array('hindu-deities','radha-krishna','lakshmi-ganesha','lord-shiva','seven-horses','vaastu-art','tirupati-balaji','lord-rama','buddha','sikh-art','swaminarayan','pichwai-art','indian-culture');

echo "=== CATEGORY SITEMAP (fetched from this server)\n";
$r = wp_remote_get(home_url('/product_cat-sitemap.xml'), array('timeout' => 40, 'sslverify' => false, 'headers' => array('User-Agent' => 'af-diag/1.0')));
$xml = is_wp_error($r) ? '' : (string) wp_remote_retrieve_body($r);
echo "  HTTP " . (is_wp_error($r) ? 'ERR ' . $r->get_error_message() : wp_remote_retrieve_response_code($r)) . " | " . strlen($xml) . " bytes | <url> entries: " . substr_count($xml, '<url>') . "\n";
$extra = array();
foreach (array('product_cat-sitemap2.xml') as $more) {
    $r2 = wp_remote_get(home_url('/' . $more), array('timeout' => 40, 'sslverify' => false));
    if (!is_wp_error($r2) && wp_remote_retrieve_response_code($r2) == 200) { $xml .= (string) wp_remote_retrieve_body($r2); echo "  + $more appended\n"; }
}

echo "\n=== THE 13 TERMS\n";
printf("  %-17s %-5s %-8s %-10s %-9s %-11s %s\n", 'slug', 'count', 'in map', 'lastmod', 'rm-index', 'noindex-rm', 'url');
foreach ($slugs as $s) {
    $t = get_term_by('slug', $s, 'product_cat');
    if (!$t) { echo "  $s NOT FOUND\n"; continue; }
    $url = get_term_link($t);
    $in  = strpos($xml, '<loc>' . $url . '</loc>') !== false ? 'yes' : (strpos($xml, '/' . $s . '/') !== false ? 'yes*' : 'NO');
    $lm  = '';
    if (preg_match('#<loc>' . preg_quote($url, '#') . '</loc>\s*<lastmod>([^<]+)</lastmod>#', $xml, $m)) $lm = substr($m[1], 0, 10);
    $meta = get_term_meta($t->term_id, 'rank_math_robots', true);
    $rob  = is_array($meta) ? implode(',', $meta) : (string) $meta;
    // what Rank Math prints for this term
    $noindex = '';
    if (class_exists('RankMath\\Helper')) {
        try { $noindex = \RankMath\Helper::is_term_indexable($t) ? 'indexable' : 'NOINDEX'; } catch (\Throwable $e) { $noindex = '?'; }
    }
    printf("  %-17s %-5d %-8s %-10s %-9s %-11s %s\n", $s, $t->count, $in, $lm, $rob ?: '-', $noindex, $url);
}

echo "\n=== RANK MATH SITEMAP SETTINGS\n";
$sm = get_option('rank-math-options-sitemap', array());
foreach (array('tax_product_cat_sitemap', 'exclude_terms', 'exclude_posts', 'items_per_page') as $k) echo "  $k: " . (isset($sm[$k]) ? (is_array($sm[$k]) ? json_encode($sm[$k]) : $sm[$k]) : '(unset)') . "\n";
$t = get_option('rank-math-options-titles', array());
foreach (array('tax_product_cat_robots', 'tax_product_cat_custom_robots', 'noindex_empty_taxonomies') as $k) echo "  titles.$k: " . (isset($t[$k]) ? (is_array($t[$k]) ? json_encode($t[$k]) : $t[$k]) : '(unset)') . "\n";

echo "\n=== LIVE ROBOTS TAG ON THE FOUR NOT INDEXED\n";
foreach (array('lakshmi-ganesha','tirupati-balaji','lord-shiva','swaminarayan') as $s) {
    $t = get_term_by('slug', $s, 'product_cat'); if (!$t) continue;
    $r = wp_remote_get(get_term_link($t), array('timeout' => 40, 'sslverify' => false, 'headers' => array('User-Agent' => 'af-diag/1.0')));
    if (is_wp_error($r)) { echo "  $s: ERROR\n"; continue; }
    $b = (string) wp_remote_retrieve_body($r);
    preg_match('#<meta name="robots" content="([^"]*)"#i', $b, $m);
    preg_match('#<link rel="canonical" href="([^"]*)"#i', $b, $c);
    preg_match('#<title>([^<]*)</title>#i', $b, $ti);
    printf("  %-17s HTTP %s | robots: %s | canonical: %s | title: %s | products listed: %d\n", $s, wp_remote_retrieve_response_code($r), $m[1] ?? '(none)', $c[1] ?? '(none)', $ti[1] ?? '', substr_count($b, 'class="product-'));
}
echo "\n=== DONE ===\n";
