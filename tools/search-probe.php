<?php
/**
 * Ask the live WordPress what a search returns — from inside the server.
 *
 * Run with: wp eval-file wp-content/themes/postero-child/tools/search-probe.php
 *
 * WHY THIS EXISTS. The search cannot be checked over HTTP from outside. Every
 * request to https://theartframer.us/?s=... is answered with
 *
 *   HTTP/2 302   location: /   server: hcdn
 *
 * by Hostinger's CDN, before WordPress is reached at all. Measured 2026-09-09
 * on nine different queries, including ones carrying an explicit post_type.
 * So an outside check can only ever report the homepage, whatever the search
 * code does. This runs the same query WordPress itself would run, in the same
 * database, with the theme loaded — the one place the answer is visible.
 *
 * Read-only: it runs queries and prints them. It writes nothing.
 */

if (!defined('ABSPATH')) { echo "not running inside WordPress\n"; exit(1); }

$queries = array('RK - 0118', 'RK-0118', 'rk0118', 'PA - 1201', 'Krishna', 'Blue');

echo "=== SEARCH PROBE (inside WordPress, past the CDN) ===\n";
echo 'theme search module loaded: '
   . (function_exists('af_search_meta_sql') ? "YES\n" : "NO — the module is not active\n");

// What the art codes in the database actually look like, so a query that finds
// nothing can be told apart from a code that is not stored the way it is shown.
global $wpdb;
$sample = $wpdb->get_col(
    "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key='_taf_art_code'"
    . " AND meta_value<>'' ORDER BY post_id DESC LIMIT 5"
);
echo 'art codes stored, newest 5: ' . ($sample ? implode(' | ', $sample) : '(none)') . "\n";
$total = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key='_taf_art_code' AND meta_value<>''"
);
echo "products carrying an art code: {$total}\n\n";

foreach ($queries as $q) {
    // is_search() must be true for the module's filters to apply, so this goes
    // through WP_Query with 's' rather than calling the builder directly — the
    // point is to exercise the path a visitor's request takes.
    $wpq = new WP_Query(array(
        's'              => $q,
        'post_type'      => array('product', 'post', 'page'),
        'post_status'    => 'publish',
        'posts_per_page' => 5,
        'no_found_rows'  => false,
    ));
    printf("  %-12s %d result(s)\n", '"' . $q . '"', (int) $wpq->found_posts);
    foreach ($wpq->posts as $p) {
        $code = get_post_meta($p->ID, '_taf_art_code', true);
        printf("       - [%s] %s%s\n", $p->post_type,
            mb_substr($p->post_title, 0, 58), $code ? "   ({$code})" : '');
    }
    if (!$wpq->found_posts) {
        echo "         nothing matched\n";
    }
    wp_reset_postdata();
}

echo "\nIf these find products, the search code works and the 302 from the CDN\n";
echo "is the only thing standing between a customer and these results.\n";
echo "=== DONE ===\n";
