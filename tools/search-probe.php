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

/**
 * WHICH COPY of the module is running — printed at the END of this script.
 *
 * It was printed here, at the top, and could not be read: the log API returns
 * only the tail of a run and the product listings below it are longer than
 * that tail. The same lesson as the popup checker, learned twice.
 */
function af_probe_which_copy() {
    $mod = get_stylesheet_directory() . '/inc/search-all.php';
    echo "\n--- which copy of the module is running ---\n";
    if (file_exists($mod)) {
        echo '  file: ' . date('Y-m-d H:i:s', (int) filemtime($mod))
           . '  (' . filesize($mod) . " bytes)\n";
        echo '  searches a code whole: '
           . (strpos((string) file_get_contents($mod), 'never split') !== false
              ? "YES (the current file is on disk)\n"
              : "NO (this is an older file)\n");
    } else {
        echo "  the module file is not on the server at all\n";
    }
    if (function_exists('af_search_terms')) {
        foreach (array('PA - 1201', 'RK - 0118', 'radha krishna art') as $probe) {
            echo '  af_search_terms("' . $probe . '") = ['
               . implode(' | ', af_search_terms($probe)) . "]\n";
        }
        echo "  An art code must come back as ONE term. More than one means the\n"
           . "  loaded code is older than the file — PHP's opcache holding the\n"
           . "  previous version, which is invisible from outside.\n";
    }
}

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

// The module only acts on the MAIN query — a deliberate restriction, so that
// no widget's own WP_Query is quietly rewritten. A probe's WP_Query is
// therefore not touched by it, and the first version of this file measured
// stock WordPress while appearing to measure the fix: art codes returned
// nothing, exactly as they would with no module installed at all.
//
// So the clause is applied here explicitly, by calling the module's own
// builder. This tests the part that was actually in question — whether that
// SQL finds these products in this database — against the real catalogue.
add_filter('posts_search', function ($search, $q) {
    if (!$q->get('af_probe')) return $search;
    global $wpdb;
    $extra = af_search_meta_sql(af_search_terms($q->get('s')), $wpdb->prefix, $wpdb);
    if ($extra === '') return $search;
    $inner = preg_replace('/^\s*AND\s*/i', '', $search);
    if ($inner === '' || $inner === null) return ' AND (1=0 ' . $extra . ') ';
    return ' AND ( ' . $inner . $extra . ' ) ';
}, 10, 2);

foreach ($queries as $q) {
    $wpq = new WP_Query(array(
        's'              => $q,
        'af_probe'       => 1,
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

af_probe_which_copy();

echo "\nIf these find products, the search code works and the 302 from the CDN\n";
echo "is the only thing standing between a customer and these results.\n";
echo "=== DONE ===\n";
