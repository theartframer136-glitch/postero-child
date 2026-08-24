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

$fixed = 0;
foreach ($ids as $pid) {
    $pid  = (int) $pid;
    $have = wp_get_post_terms($pid, 'product_cat', array('fields' => 'ids'));
    if (is_wp_error($have)) continue;
    $have = array_map('intval', $have);
    if ($have === array($tid)) continue;                 // already correct

    $extra = array_diff($have, array($tid));
    $names = array();
    foreach ($extra as $x) {
        $t = get_term($x, 'product_cat');
        $names[] = ($t && !is_wp_error($t)) ? $t->slug : ('#' . $x);
    }
    wp_set_object_terms($pid, array($tid), 'product_cat');   // replace, not append
    printf("  #%-7d removed from: %s\n", $pid, implode(', ', $names));
    $fixed++;
}

if ($fixed) {
    if (function_exists('wc_delete_product_transients')) wc_delete_product_transients();
    wp_update_term_count_now(array($tid), 'product_cat');
    clean_term_cache(array($tid), 'product_cat');
    printf("\n  put back into their own section: %d\n", $fixed);
} else {
    echo "  nothing to correct — every premium piece is filed only here.\n";
}
echo "=== DONE ===\n";
