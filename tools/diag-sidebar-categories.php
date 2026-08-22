<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * The sidebar "Categories" list did not pick up the Gold Foiled & UV term.
 * There are two quite different reasons that could be true, and they need
 * opposite fixes, so this asks both questions instead of picking one:
 *
 *   A. The query is not returning the term. Then the fix is in the filter —
 *      this runs EXACTLY the query WooCommerce's own product-categories widget
 *      runs, through the same filter, and says whether the term comes back.
 *
 *   B. The query is fine but that list is not WooCommerce's widget at all —
 *      the theme or a page builder renders its own. Then the fix is somewhere
 *      else entirely, so this lists what is actually registered in each
 *      sidebar and prints the real markup of the list from a live page.
 *
 * Read-only. Run: wp eval-file tools/diag-sidebar-categories.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }

echo "=== DIAG: SIDEBAR CATEGORIES LIST ===\n";

$term = function_exists('af_goldfoil_slug') ? get_term_by('slug', af_goldfoil_slug(), 'product_cat') : null;
if (!$term || is_wp_error($term)) { echo "  gold-foil term missing\n=== DONE ===\n"; return; }
$tid = (int) $term->term_id;
printf("  looking for term #%d (%s), count=%d\n\n", $tid, $term->slug, (int) $term->count);

/* ── A. the widget's own query, through the same filter ───────────────── */
echo "A. THE QUERY WooCommerce's widget RUNS\n";
$args = array(
    'taxonomy'     => 'product_cat',
    'orderby'      => 'name',
    'order'        => 'ASC',
    'hide_empty'   => true,
    'hierarchical' => true,
    'pad_counts'   => true,
);
$args = apply_filters('woocommerce_product_categories_widget_args', $args);
printf("   hide_empty after filters: %s\n", var_export($args['hide_empty'], true));
$got = get_terms($args);
if (is_wp_error($got)) {
    echo '   get_terms error: ' . $got->get_error_message() . "\n";
} else {
    $ids = array();
    foreach ($got as $t) $ids[] = (int) $t->term_id;
    printf("   terms returned: %d\n", count($ids));
    printf("   our term present: %s\n", in_array($tid, $ids, true) ? 'YES' : 'NO');
    $names = array();
    foreach (array_slice($got, 0, 14) as $t) $names[] = $t->name . '(' . $t->count . ')';
    printf("   first few: %s\n", implode(', ', $names));
}

/* ── the same query WITHOUT hide_empty, as a control ──────────────────── */
$args2 = $args;
$args2['hide_empty'] = false;
$got2 = get_terms($args2);
if (!is_wp_error($got2)) {
    $ids2 = array();
    foreach ($got2 as $t) $ids2[] = (int) $t->term_id;
    printf("   control (hide_empty off): %d terms, ours present: %s\n",
        count($ids2), in_array($tid, $ids2, true) ? 'YES' : 'NO');
}

/* ── B. what is actually registered in the sidebars ───────────────────── */
echo "\nB. WIDGETS REGISTERED IN EACH SIDEBAR\n";
$sidebars = wp_get_sidebars_widgets();
foreach ($sidebars as $sb => $widgets) {
    if ($sb === 'wp_inactive_widgets' || empty($widgets)) continue;
    printf("   [%s]\n", $sb);
    foreach ((array) $widgets as $w) {
        $base = preg_replace('/-\d+$/', '', $w);
        $num  = (int) preg_replace('/^.*-/', '', $w);
        $opt  = get_option('widget_' . $base);
        $title = (is_array($opt) && isset($opt[$num]['title'])) ? $opt[$num]['title'] : '';
        printf("     %-38s %s\n", $w, $title !== '' ? '"' . $title . '"' : '');
    }
}

/* ── C. the markup a real page actually renders ───────────────────────── */
echo "\nC. THE LIST AS A LIVE PAGE RENDERS IT\n";
$other = null;
foreach (get_terms(array('taxonomy' => 'product_cat', 'parent' => 0, 'hide_empty' => true,
                         'number' => 4, 'orderby' => 'count', 'order' => 'DESC')) as $t) {
    if ((int) $t->term_id !== $tid) { $other = $t; break; }
}
if (!$other) { echo "   no other category to load\n=== DONE ===\n"; return; }
$url = get_term_link($other);
$r = wp_remote_get(add_query_arg('afdiag', time(), $url),
    array('timeout' => 60, 'sslverify' => false,
          'headers' => array('User-Agent' => 'Mozilla/5.0 AF-Verify')));
if (is_wp_error($r)) { echo '   fetch failed: ' . $r->get_error_message() . "\n=== DONE ===\n"; return; }
$html = wp_remote_retrieve_body($r);
printf("   %s (%d bytes)\n", $url, strlen($html));
printf("   contains cat-item-%d: %s\n", $tid, strpos($html, 'cat-item-' . $tid) !== false ? 'YES' : 'NO');
printf("   contains the slug %s: %s\n", $term->slug, strpos($html, $term->slug) !== false ? 'YES' : 'NO');

// the classes the list items actually carry
if (preg_match_all('/class="([^"]*cat-item[^"]*)"/i', $html, $m)) {
    $u = array_slice(array_unique($m[1]), 0, 8);
    echo "   cat-item classes seen: \n";
    foreach ($u as $c) echo '     ' . $c . "\n";
} else {
    echo "   NO cat-item classes at all — that list is not WooCommerce's widget\n";
}

// the markup around the sidebar heading, so an unfamiliar widget names itself
$pos = false;
foreach (array('>Categories<', 'Categories</h', 'Categories </h') as $needle) {
    $pos = stripos($html, $needle);
    if ($pos !== false) break;
}
if ($pos === false) {
    echo "   no \"Categories\" heading found in the page\n";
} else {
    $start = max(0, $pos - 400);
    $chunk = substr($html, $start, 1600);
    $chunk = preg_replace('/\s+/', ' ', $chunk);
    echo "   markup around the heading:\n";
    foreach (str_split($chunk, 150) as $line) echo '     ' . $line . "\n";
}
echo "=== DONE ===\n";
