<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * "All Art Prints" says "Every print in the studio, one collection" and holds
 * five leftover Postero demo posters. This puts the studio's actual artwork in
 * it, so the category means what it says.
 *
 * Membership is decided by af_pricing_applies() — the same predicate the
 * pricing engine uses — so accessories, banners and the gift card are left
 * out, and every canvas/print product is included. The term is APPENDED, so a
 * product keeps every category it already had; nothing is moved or removed.
 * Idempotent: a second run reports 0 added.
 *
 * It also reports (never deletes) the demo posters: products whose featured
 * image is missing or is a Postero placeholder. Deleting catalogue entries is
 * the owner's call, not a deploy script's.
 *
 * Run: wp eval-file tools/populate-all-art-prints.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }

echo "=== ALL ART PRINTS — POPULATE ===\n";

$term = get_term_by('slug', 'all-art-prints', 'product_cat');
if (!$term || is_wp_error($term)) {
    // fall back to the visible name before giving up
    $term = get_term_by('name', 'All Art Prints', 'product_cat');
}
if (!$term || is_wp_error($term)) {
    echo "  category 'all-art-prints' NOT FOUND — nothing done\n=== DONE ===\n";
    return;
}
$tid = (int) $term->term_id;
printf("  category: %s (#%d), currently %d product(s)\n", $term->name, $tid, (int) $term->count);

$page = 1; $seen = 0; $added = 0; $already = 0; $skipped = 0;
$demo = array(); $noimage = array();

while (true) {
    $ids = wc_get_products(array(
        'status' => 'publish', 'limit' => 80, 'page' => $page,
        'return' => 'ids', 'orderby' => 'ID', 'order' => 'ASC',
    ));
    if (!$ids) break;
    foreach ($ids as $pid) {
        $p = wc_get_product($pid);
        if (!$p) continue;
        $seen++;
        if (!af_pricing_applies($p)) { $skipped++; continue; }   // accessories, banners, gift card

        // flag the theme's demo leftovers for the owner to decide on
        $img_id = $p->get_image_id();
        if (!$img_id) {
            $noimage[] = "#{$pid} " . $p->get_name();
        } else {
            $src = (string) wp_get_attachment_url($img_id);
            if (stripos($src, 'postero') !== false || stripos($src, 'placeholder') !== false) {
                $demo[] = "#{$pid} " . $p->get_name();
            }
        }

        if (has_term($tid, 'product_cat', $pid)) { $already++; continue; }
        $res = wp_set_object_terms($pid, array($tid), 'product_cat', true);  // append
        if (is_wp_error($res)) { echo "  FAILED #{$pid}: " . $res->get_error_message() . "\n"; continue; }
        $added++;
    }
    $page++;
}

wp_update_term_count_now(array($tid), 'product_cat');
clean_term_cache(array($tid), 'product_cat');
$fresh = get_term($tid, 'product_cat');

echo "  products seen:            {$seen}\n";
echo "  added to the category:    {$added}\n";
echo "  already in it:            {$already}\n";
echo "  skipped (not art):        {$skipped}\n";
printf("  category now holds:       %d product(s)\n", ($fresh && !is_wp_error($fresh)) ? (int) $fresh->count : -1);

if ($demo) {
    echo "\n  Postero demo placeholders still in the catalogue (" . count($demo) . ") —\n";
    echo "  these show a grey 'Postero' image and were never real products:\n";
    foreach (array_slice($demo, 0, 12) as $d) echo "    {$d}\n";
    echo "  (reported only; deleting products is the owner's call)\n";
}
if ($noimage) {
    echo "\n  products with no featured image (" . count($noimage) . "):\n";
    foreach (array_slice($noimage, 0, 8) as $d) echo "    {$d}\n";
}

if ($added > 0) {
    if (function_exists('af_mk_flush_feeds')) af_mk_flush_feeds();
    if (function_exists('wc_delete_product_transients')) wc_delete_product_transients();
    echo "\n  feeds and product transients flushed\n";
}
echo "=== DONE ===\n";
