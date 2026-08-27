<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Keep the premium finish in its own section, and nowhere else.
 *
 * The importer files each new piece under Gold Foiled & UV alone. Two LATER
 * passes in the same deploy then undo that without meaning to:
 * populate-all-art-prints.php appends "All Art Prints" to every canvas
 * product, and assign-deity-categories.php appends a theme category based on
 * the title. Both sweep every published product, and a gold-foil piece looks
 * like any other canvas to them.
 *
 * The result is the same artwork listed twice in the ordinary categories at
 * two different prices — which reads as a duplicated catalogue rather than a
 * premium line, and is exactly what the section was created to avoid.
 *
 * So this runs after them and puts the invariant back: a product carrying
 * _af_goldfoil belongs to Gold Foiled & UV and to nothing else. It is
 * idempotent, it touches only products the importer created, and it reports
 * every change, so a future pass that starts adding a category again shows up
 * in the deploy log instead of quietly reappearing in the shop.
 *
 * "Nothing else" means nothing OUTSIDE the section. The section's own
 * sub-collections — the children of gold-foiled-uv that
 * tools/goldfoil-subcategories.php builds so the homepage's circle row has
 * something to show under the tab — are part of it, not an escape from it:
 * they carry the section's price (af_is_goldfoil reads the whole subtree),
 * they live under its archive, and a piece in one is not listed anywhere a
 * shopper could meet it at the ordinary price. Stripping them, which this
 * pass did until the row was asked for, would have quietly undone that work
 * on the very next deploy.
 *
 * Run: wp eval-file tools/enforce-goldfoil-category.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
if (!function_exists('af_goldfoil_slug')) { echo "ABORT: the gold-foil module is not loaded.\n"; return; }

echo "=== KEEP GOLD FOILED & UV IN ITS OWN SECTION ===\n";

$term = get_term_by('slug', af_goldfoil_slug(), 'product_cat');
if (!$term || is_wp_error($term)) { echo "  the category does not exist yet.\n=== DONE ===\n"; return; }
$tid = (int) $term->term_id;

global $wpdb;
$ids = $wpdb->get_col($wpdb->prepare(
    "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_af_goldfoil' AND meta_value = %s", 'yes'));
printf("  premium pieces: %d\n", count($ids));

// The section's own sub-collections, which a piece is allowed to be in.
$mine = get_terms(array('taxonomy' => 'product_cat', 'child_of' => $tid,
                        'hide_empty' => false, 'fields' => 'ids'));
$mine = is_wp_error($mine) ? array() : array_map('intval', $mine);
$allowed = array_merge(array($tid), $mine);
printf("  sub-collections of its own: %d\n", count($mine));

$fixed = 0;
foreach ($ids as $pid) {
    $pid  = (int) $pid;
    $have = wp_get_post_terms($pid, 'product_cat', array('fields' => 'ids'));
    if (is_wp_error($have)) continue;
    $have = array_map('intval', $have);

    $extra = array_values(array_diff($have, $allowed));
    if (!$extra && in_array($tid, $have, true)) continue;    // already correct

    $names = array();
    foreach ($extra as $x) {
        $t = get_term($x, 'product_cat');
        $names[] = ($t && !is_wp_error($t)) ? $t->slug : ('#' . $x);
    }
    // Keep the section, keep whichever of its own sub-collections this piece
    // is in, drop everything else.
    $keep = array_values(array_unique(array_merge(array($tid), array_intersect($have, $mine))));
    wp_set_object_terms($pid, $keep, 'product_cat');         // replace, not append

    // The term relationship is gone, but an SEO plugin's "primary category" is
    // POST META and survives untouched — which is why a product's breadcrumb
    // went on reading "All Art Prints" long after it had been taken out of that
    // category. Nothing garbage-collects that key, and Rank Math only rewrites
    // it when the post is saved through the editor, which wp eval-file never
    // does. Point it at the only category this product now has.
    if ((int) get_post_meta($pid, 'rank_math_primary_product_cat', true) !== $tid) {
        update_post_meta($pid, 'rank_math_primary_product_cat', $tid);
    }
    delete_post_meta($pid, '_yoast_wpseo_primary_product_cat');   // cheap insurance
    clean_post_cache($pid);

    printf("  #%-7d removed from: %s\n", $pid, $names ? implode(', ', $names) : '(nothing — put back into its section)');
    $fixed++;
}

if ($fixed) {
    if (function_exists('wc_delete_product_transients')) wc_delete_product_transients();
    wp_update_term_count_now(array_merge(array($tid), $mine), 'product_cat');
    clean_term_cache(array_merge(array($tid), $mine), 'product_cat');
    // The deploy's own cache purge runs BEFORE this pass, so without this the
    // corrected page could sit behind a copy cached from before the fix.
    do_action('litespeed_purge_all');
    printf("\n  put back into their own section: %d\n", $fixed);
} else {
    echo "  nothing to correct — every premium piece is filed only here.\n";
}
echo "=== DONE ===\n";
