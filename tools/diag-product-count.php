<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * READ-ONLY product count. Makes no changes.
 * Run: wp eval-file tools/diag-product-count.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$counts = wp_count_posts( 'product' );
echo "=== PRODUCT COUNT ===\n";
$total = 0;
foreach ( $counts as $status => $n ) {
    $n = (int) $n;
    if ( $n === 0 ) continue;
    echo str_pad( $status, 12 ) . ": {$n}\n";
    $total += $n;
}
echo "TOTAL (all statuses): {$total}\n";
echo "PUBLISHED (live on the store): " . (int) $counts->publish . "\n";

global $wpdb;
$by_type = $wpdb->get_results(
    "SELECT t.name AS type, COUNT(*) AS n
       FROM {$wpdb->posts} p
       JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
       JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'product_type'
       JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
      WHERE p.post_type = 'product' AND p.post_status = 'publish'
      GROUP BY t.name
      ORDER BY n DESC"
);
if ( $by_type ) {
    echo "\n=== PUBLISHED, BY PRODUCT TYPE ===\n";
    foreach ( $by_type as $row ) {
        echo str_pad( $row->type, 12 ) . ": {$row->n}\n";
    }
}
echo "\n=== DONE ===\n";
