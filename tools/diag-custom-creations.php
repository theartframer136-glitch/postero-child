<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Why are the "Exclusively Customised Creations" photos in New Arrivals and
 * Trending Today?
 *
 * Those sections show the newest PRODUCTS, so the photos can only be there if
 * they exist as products. This prints what actually exists before anything is
 * hidden: the newest products with their categories and visibility, the terms
 * that look like the customised-creations bucket, and where the gallery
 * section itself gets its images from - so the fix removes them from the shop
 * WITHOUT emptying the gallery.
 *
 * Read-only. Run: wp eval-file tools/diag-custom-creations.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }

echo "=== CUSTOMISED CREATIONS - WHERE DO THEY LIVE ===\n";

echo "\n-- 1. The 24 newest products (what New Arrivals / Trending see) --\n";
$ps = wc_get_products(array('status' => 'publish', 'limit' => 24, 'orderby' => 'date', 'order' => 'DESC'));
foreach ($ps as $p) {
    $cats = wp_get_post_terms($p->get_id(), 'product_cat', array('fields' => 'names'));
    printf("  #%-6d %-45.45s  vis:%-8s  cats: %s\n",
        $p->get_id(), $p->get_name(), $p->get_catalog_visibility(), implode(', ', $cats));
}

echo "\n-- 2. Terms that look like the customised bucket --\n";
foreach (array('product_cat', 'product_tag') as $tax) {
    $terms = get_terms(array('taxonomy' => $tax, 'hide_empty' => false));
    if (is_wp_error($terms)) continue;
    foreach ($terms as $t) {
        if (preg_match('/customi|creation|exclusiv|submitted/i', $t->name . ' ' . $t->slug)) {
            printf("  %s: %-35s slug:%-30s count:%d (id %d)\n", $tax, $t->name, $t->slug, $t->count, $t->term_id);
        }
    }
}

echo "\n-- 3. Where the gallery section gets its pictures --\n";
global $wpdb;
$rows = $wpdb->get_results($wpdb->prepare(
    "SELECT ID, post_type, post_status, post_title FROM {$wpdb->posts}
      WHERE post_content LIKE %s AND post_status IN ('publish','draft') LIMIT 5",
    '%Exclusively Customised Creations%'));
foreach ($rows as $r) {
    printf("  found in %s #%d (%s) '%s'\n", $r->post_type, $r->ID, $r->post_status, $r->post_title);
    $c = get_post_field('post_content', $r->ID);
    $i = stripos($c, 'Exclusively Customised Creations');
    $win = substr($c, max(0, $i - 200), 2600);
    // What the widget is: gallery of media ids, or a products query.
    if (preg_match('/"widgetType":"([^"]+)"/', $win, $m)) printf("    nearest widgetType: %s\n", $m[1]);
    foreach (array('gallery', 'wc-products', 'woocommerce', 'image-carousel', 'loop-grid', 'shortcode') as $needle) {
        $n = substr_count($win, $needle);
        if ($n) printf("    '%s' x%d in the surrounding block\n", $needle, $n);
    }
    if (preg_match_all('/"id":(\d+),"url":"[^"]*uploads[^"]*"/', $win, $mm)) {
        printf("    media ids referenced nearby: %s\n", implode(',', array_slice($mm[1], 0, 12)));
    }
}
if (!$rows) echo "  (heading text not found in any post_content - it may be a heading widget elsewhere)\n";

// The fix excludes the category from every products query that does not ask
// for it. That is a claim about a query, so it is settled by running one:
// exactly the arguments a "newest products" row uses, through the same filter,
// and printing what comes back. If a Personalised Prints piece appears in this
// list, the fix is not working - no screenshot needed to find out.
echo "\n-- 3b. What a generic 'newest products' row now returns --\n";
$args = apply_filters('woocommerce_shortcode_products_query', array(
    'post_type'      => 'product',
    'post_status'    => 'publish',
    'posts_per_page' => 12,
    'orderby'        => 'date',
    'order'          => 'DESC',
), array('limit' => 12), 'products');
$has_not_in = false;
if (!empty($args['tax_query'])) {
    foreach ($args['tax_query'] as $tq) {
        if (is_array($tq) && !empty($tq['operator']) && $tq['operator'] === 'NOT IN') $has_not_in = true;
    }
}
printf("  exclusion clause present: %s\n", $has_not_in ? 'YES' : 'NO - the filter did not fire');
$q = new WP_Query($args);
$leak = 0;
foreach ($q->posts as $p) {
    $cats = wp_get_post_terms($p->ID, 'product_cat', array('fields' => 'names'));
    $bad  = in_array('Personalised Prints', $cats, true);
    if ($bad) $leak++;
    printf("  %s #%-6d %-42.42s %s\n", $bad ? 'LEAK' : '    ', $p->ID,
        get_the_title($p->ID), implode(', ', $cats));
}
printf("  VERDICT: %s\n", $leak
    ? "$leak Personalised Prints piece(s) still reach a generic row"
    : 'no Personalised Prints pieces in a generic row');

// And the other half: the gallery asks FOR the category and must still get it.
echo "\n-- 3c. A row that asks for Personalised Prints still gets it --\n";
$args2 = apply_filters('woocommerce_shortcode_products_query', array(
    'post_type'      => 'product',
    'post_status'    => 'publish',
    'posts_per_page' => 4,
    'tax_query'      => array(array(
        'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => array('personalised-prints'),
    )),
), array('limit' => 4, 'category' => 'personalised-prints'), 'product_category');
$q2 = new WP_Query($args2);
printf("  returns %d product(s)%s\n", count($q2->posts), count($q2->posts) ? ':' : ' - THE GALLERY WOULD BE EMPTY');
foreach ($q2->posts as $p) printf("    #%-6d %.48s\n", $p->ID, get_the_title($p->ID));

// What the homepage rows actually ask for, recorded by the filter itself as
// each one runs. Guessing at which query drives which section is what emptied
// New Arrivals; this ends the guessing.
echo "\n-- 3d. Product-row queries seen on the live site --\n";
$seen = get_transient('af_sc_seen');
if (is_array($seen) && $seen) {
    foreach (array_keys($seen) as $sig) echo '    ' . $sig . "\n";
} else {
    echo "    (none recorded yet — the homepage has not been rendered since the deploy)\n";
}

echo "\n-- 3e. New Arrivals: today's uploads, else random --\n";
$start = function_exists('current_datetime')
    ? current_datetime()->setTime(0, 0, 0)->format('Y-m-d H:i:s')
    : date('Y-m-d 00:00:00', current_time('timestamp'));
printf("  site midnight: %s (site time now %s)\n", $start, current_time('mysql'));
$tq = new WP_Query(array(
    'post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => 12,
    'date_query' => array(array('after' => $start, 'inclusive' => true)),
    'fields' => 'ids', 'no_found_rows' => true,
));
printf("  products published today: %d -> the row shows %s\n",
    count($tq->posts), count($tq->posts) ? "today's uploads" : 'a random selection');
foreach (array_slice($tq->posts, 0, 8) as $pid) printf("    #%-6d %.48s\n", $pid, get_the_title($pid));

echo "\n-- 4. Attachments named like the event photos (newest 10 images) --\n";
$atts = get_posts(array('post_type' => 'attachment', 'post_mime_type' => 'image', 'numberposts' => 10,
    'orderby' => 'date', 'order' => 'DESC'));
foreach ($atts as $a) {
    $parent = $a->post_parent ? get_post_type($a->post_parent) . ' #' . $a->post_parent : 'unattached';
    printf("  att #%-6d %-40.40s parent: %s\n", $a->ID, basename((string) get_attached_file($a->ID)), $parent);
}
echo "=== DONE ===\n";
