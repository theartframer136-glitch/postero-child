<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Full list (no truncation) of published products missing an Art Code,
 * with their product category, so the list can be cross-referenced against
 * the TheARTFramer Canva collection book (which is organised by category).
 * Read-only. Run: wp eval-file tools/diag-artcode-missing-full.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$ids = get_posts(array(
    'post_type'      => 'product',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'orderby'        => 'title',
    'order'          => 'ASC',
));

$with = 0; $missing = array();
foreach ($ids as $pid) {
    $code = get_post_meta($pid, '_taf_art_code', true);
    if (is_string($code) && trim($code) !== '') { $with++; continue; }
    $terms = get_the_terms($pid, 'product_cat');
    $cats = $terms && !is_wp_error($terms) ? implode('/', wp_list_pluck($terms, 'name')) : '(none)';
    $missing[] = array('id' => $pid, 'title' => html_entity_decode(wp_strip_all_tags(get_the_title($pid))), 'cats' => $cats);
}

echo "=== ART CODE: FULL MISSING LIST ===\n";
echo "published: " . count($ids) . " | with code: {$with} | missing: " . count($missing) . "\n\n";

// Group by category so it lines up with the Canva collection book's structure
$by_cat = array();
foreach ($missing as $row) { $by_cat[$row['cats']][] = $row; }
ksort($by_cat);
foreach ($by_cat as $cat => $rows) {
    echo "--- {$cat} (" . count($rows) . ") ---\n";
    foreach ($rows as $row) {
        echo "  #{$row['id']}  {$row['title']}\n";
    }
}
echo "\n=== DONE ===\n";
