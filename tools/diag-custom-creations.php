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

echo "\n-- 4. Attachments named like the event photos (newest 10 images) --\n";
$atts = get_posts(array('post_type' => 'attachment', 'post_mime_type' => 'image', 'numberposts' => 10,
    'orderby' => 'date', 'order' => 'DESC'));
foreach ($atts as $a) {
    $parent = $a->post_parent ? get_post_type($a->post_parent) . ' #' . $a->post_parent : 'unattached';
    printf("  att #%-6d %-40.40s parent: %s\n", $a->ID, basename((string) get_attached_file($a->ID)), $parent);
}
echo "=== DONE ===\n";
