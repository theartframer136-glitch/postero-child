<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Take back out the Gold Foiled & UV pieces that were COPIED from artwork the
 * studio already sells.
 *
 * WHY
 * The section was filled from the Hindu Deities category so it would not be
 * empty while the owner's own artwork was still on their PC. Seen on the live
 * site that was the wrong call: the same painting now appeared twice, the
 * premium copy carried an SEO title so long it wrapped over six lines, and its
 * breadcrumb still read "All Art Prints". The owner wants this section to hold
 * ONLY the artwork in their Personalised folder. So the copies go.
 *
 * WHAT IT WILL AND WILL NOT TOUCH
 * Only products carrying BOTH _af_goldfoil = yes AND an _af_goldfoil_src that
 * begins "product:" — the exact fingerprint of a copy made from another
 * product. A piece imported from a real FILE (src is a path or "att:<id>") is
 * left alone, so when the Personalised artwork lands, running this again
 * cannot touch it.
 *
 * The SOURCE products are never touched. Neither are the images: a copy reused
 * its source's attachment where it stood rather than duplicating the file, so
 * the pictures belong to the originals and must survive. Only the product post
 * is removed.
 *
 * Trash, not delete. Everything here is recoverable from Products → Trash for
 * as long as WordPress keeps it, which is the right default for a live shop.
 * Pass "purge" to delete outright once the result has been seen.
 *
 * Run: wp eval-file tools/remove-goldfoil-copies.php --allow-root
 *      wp eval-file tools/remove-goldfoil-copies.php --allow-root purge
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
global $wpdb;

$purge = (!empty($args) && is_array($args) && in_array('purge', array_map('strtolower', $args), true));

echo "=== REMOVE COPIED GOLD FOILED & UV PIECES ===\n";
printf("  mode: %s\n", $purge ? 'DELETE permanently' : 'move to Trash (recoverable)');

// Both conditions together. Meta-key-only would also catch a piece imported
// from a real file, which is precisely what must survive.
$ids = $wpdb->get_col(
    "SELECT p.ID
       FROM {$wpdb->posts} p
       JOIN {$wpdb->postmeta} flag ON flag.post_id = p.ID
                                  AND flag.meta_key = '_af_goldfoil'
                                  AND flag.meta_value = 'yes'
       JOIN {$wpdb->postmeta} src  ON src.post_id  = p.ID
                                  AND src.meta_key = '_af_goldfoil_src'
                                  AND src.meta_value LIKE 'product:%'
      WHERE p.post_type = 'product'
      ORDER BY p.ID ASC");

printf("  copies found: %d\n", count($ids));
if (!$ids) {
    // Say what IS there, so an empty result is never mistaken for a failure.
    $any = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key='_af_goldfoil' AND meta_value='yes'");
    printf("  nothing to remove. Pieces flagged gold-foil altogether: %d%s\n", $any,
        $any ? ' (all imported from real files — left alone, as intended)' : '');
    echo "=== DONE ===\n";
    return;
}

$gone = 0;
foreach ($ids as $pid) {
    $pid   = (int) $pid;
    $title = html_entity_decode(wp_strip_all_tags(get_the_title($pid)), ENT_QUOTES, 'UTF-8');
    $from  = (string) get_post_meta($pid, '_af_goldfoil_src', true);

    // The image belongs to the product this was copied from. Detaching the
    // thumbnail first makes that explicit rather than trusting that trashing a
    // post never reaches an attachment it did not create.
    delete_post_meta($pid, '_thumbnail_id');
    delete_post_meta($pid, '_product_image_gallery');

    $ok = $purge ? wp_delete_post($pid, true) : wp_trash_post($pid);
    if ($ok) {
        $gone++;
        printf("  - #%-7d %-52s (was %s)\n", $pid, substr($title, 0, 52), $from);
    } else {
        printf("  ! #%-7d could not be removed\n", $pid);
    }
}

if ($gone) {
    if (function_exists('wc_delete_product_transients')) wc_delete_product_transients();
    if (function_exists('af_goldfoil_slug')) {
        $t = get_term_by('slug', af_goldfoil_slug(), 'product_cat');
        if ($t && !is_wp_error($t)) {
            wp_update_term_count_now(array((int) $t->term_id), 'product_cat');
            clean_term_cache(array((int) $t->term_id), 'product_cat');
            $t = get_term($t->term_id, 'product_cat');
            printf("\n  section now holds: %d product(s)\n", (int) $t->count);
        }
    }
    delete_transient('af_deal_ids_40');
    printf("  removed: %d   caches cleared\n", $gone);
}
echo "=== DONE ===\n";
