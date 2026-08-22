<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Why does one item in the Categories menu have no icon?
 *
 * Guessing at this costs a deploy per guess, so this reports the two things
 * that could be driving the icons, side by side, for every item under the
 * "Categories" menu entry:
 *
 *   1. the product_cat term's own thumbnail (thumbnail_id term meta) — what
 *      WooCommerce category images use, and what tools/fill-category-thumbs.php
 *      fills in
 *   2. every scrap of meta on the nav-menu item itself, plus its CSS classes —
 *      which is where a theme or plugin keeps a per-item icon
 *
 * Read the columns: whatever the items WITH icons have and the new one lacks is
 * the mechanism.
 *
 * Read-only. Run: wp eval-file tools/diag-menu-icons.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }

echo "=== DIAG: CATEGORY MENU ICONS ===\n";

$menus = wp_get_nav_menus();
if (!$menus) { echo "  no nav menus registered\n=== DONE ===\n"; return; }

foreach ($menus as $menu) {
    $items = wp_get_nav_menu_items($menu->term_id);
    if (!$items) continue;

    // the "Categories" entry, and everything filed under it
    $parent = 0;
    foreach ($items as $it) {
        if (preg_match('/^\s*categor/i', wp_strip_all_tags($it->title))) { $parent = (int) $it->ID; break; }
    }
    if (!$parent) continue;

    printf("\nMENU: %s (#%d)  — \"Categories\" is item #%d\n", $menu->name, $menu->term_id, $parent);
    echo str_repeat('-', 78) . "\n";

    $kids = array();
    foreach ($items as $it) if ((int) $it->menu_item_parent === $parent) $kids[] = $it;
    if (!$kids) { echo "  (no child items — the mega menu may be built from the taxonomy, not the menu)\n"; }

    foreach ($kids as $it) {
        $term_thumb = '-';
        $term_note  = '';
        if ($it->type === 'taxonomy' && $it->object === 'product_cat') {
            $tid = (int) get_term_meta((int) $it->object_id, 'thumbnail_id', true);
            if ($tid) {
                $file = get_attached_file($tid);
                $term_thumb = '#' . $tid . (($file && @file_exists($file)) ? '' : ' (FILE MISSING)');
            } else {
                $term_thumb = 'NONE';
            }
            $t = get_term((int) $it->object_id, 'product_cat');
            if ($t && !is_wp_error($t)) $term_note = $t->count . ' products';
        }

        printf("\n  %-34s  term thumbnail: %-22s %s\n",
            wp_strip_all_tags($it->title), $term_thumb, $term_note);
        printf("    type=%s object=%s object_id=%s\n", $it->type, $it->object, $it->object_id);

        $classes = array_filter((array) $it->classes);
        printf("    classes: %s\n", $classes ? implode(' ', $classes) : '(none)');

        // every custom field on the menu item, minus WordPress's own plumbing
        $meta = get_post_meta((int) $it->ID);
        $own  = array();
        foreach ($meta as $k => $v) {
            if (strpos($k, '_menu_item_') === 0 && !preg_match('/icon|image|thumb|svg|glyph/i', $k)) continue;
            $own[] = $k . '=' . substr(is_array($v) ? (string) reset($v) : (string) $v, 0, 60);
        }
        printf("    item meta: %s\n", $own ? implode('  |  ', $own) : '(nothing icon-ish)');
    }
}

/* ── the same question from the taxonomy side ─────────────────────────────
 * If the mega menu is generated from product_cat rather than from menu items,
 * the child items above will be empty and THIS is the list that matters.
 */
echo "\n" . str_repeat('=', 78) . "\n";
echo "TOP-LEVEL PRODUCT CATEGORIES — thumbnail present?\n";
$terms = get_terms(array('taxonomy' => 'product_cat', 'parent' => 0, 'hide_empty' => false,
                         'orderby' => 'menu_order', 'order' => 'ASC'));
if ($terms && !is_wp_error($terms)) {
    foreach ($terms as $t) {
        $tid  = (int) get_term_meta($t->term_id, 'thumbnail_id', true);
        $file = $tid ? get_attached_file($tid) : '';
        $ok   = $tid && $file && @file_exists($file);
        printf("  %-32s %-9s %-14s %s\n",
            html_entity_decode($t->name),
            $t->count . ' prod',
            $ok ? ('thumb #' . $tid) : ($tid ? 'thumb BROKEN' : 'NO THUMB'),
            $t->slug);
    }
}
echo "=== DONE ===\n";
