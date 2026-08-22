<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Create the Gold Foiled & UV category and put it in the menu.
 *
 * Idempotent: creating the term, describing it, and adding one nav-menu entry
 * under the existing "Categories" item all check for their own result first, so
 * running this on every deploy changes nothing after the first time.
 *
 * Run: wp eval-file tools/setup-gold-foil.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
if (!function_exists('af_goldfoil_slug')) { echo "ABORT: the child theme's gold-foil module is not loaded.\n"; return; }

$slug = af_goldfoil_slug();
$name = af_goldfoil_name();
echo "=== SET UP GOLD FOILED & UV ===\n";

/* ── 1. the category term ─────────────────────────────────────────────── */
$term = get_term_by('slug', $slug, 'product_cat');
if ($term && !is_wp_error($term)) {
    printf("  category: exists (#%d, %s)\n", $term->term_id, $term->name);
} else {
    $r = wp_insert_term($name, 'product_cat', array(
        'slug'        => $slug,
        'description' => 'Gold foil detailing sealed under a UV-cured coat — the studio\'s premium finish, in the same sizes and frames as the rest of the collection.',
    ));
    if (is_wp_error($r)) {
        // a term with this name may already exist under another slug
        $existing = isset($r->error_data['term_exists']) ? (int) $r->error_data['term_exists'] : 0;
        if ($existing) {
            $term = get_term($existing, 'product_cat');
            printf("  category: reused existing term #%d\n", $existing);
        } else {
            echo '  category: FAILED — ' . $r->get_error_message() . "\n";
            echo "=== DONE ===\n";
            return;
        }
    } else {
        $term = get_term((int) $r['term_id'], 'product_cat');
        printf("  category: created (#%d)\n", $term->term_id);
    }
}

/* ── 2. show it in the shop even before the images land ───────────────── */
// An empty category is hidden by WooCommerce's own "hide empty" defaults; the
// display type is set so the archive behaves exactly like every other category
// page (products, not a wall of sub-category tiles).
update_term_meta($term->term_id, 'display_type', '');

/* ── 3. one entry in the menu, under "Categories" ─────────────────────── */
// The section is only a section if it is reachable. The site's category menu is
// a WordPress nav menu, so the new term needs an item of its own — placed as a
// child of whichever item is called "Categories", which is where every other
// category already lives.
$placed = false;
$menus  = wp_get_nav_menus();
foreach ($menus as $menu) {
    $items = wp_get_nav_menu_items($menu->term_id);
    if (!$items) continue;

    // already there?
    foreach ($items as $it) {
        if ($it->type === 'taxonomy' && $it->object === 'product_cat'
            && (int) $it->object_id === (int) $term->term_id) {
            printf("  menu: already in \"%s\"\n", $menu->name);
            $placed = true;
            break 2;
        }
    }

    // find the "Categories" parent to hang it under
    $parent = 0;
    foreach ($items as $it) {
        if (preg_match('/^\s*categor/i', wp_strip_all_tags($it->title))) { $parent = (int) $it->ID; break; }
    }
    if (!$parent) continue;

    $new = wp_update_nav_menu_item($menu->term_id, 0, array(
        'menu-item-title'     => $name,
        'menu-item-object'    => 'product_cat',
        'menu-item-object-id' => (int) $term->term_id,
        'menu-item-type'      => 'taxonomy',
        'menu-item-status'    => 'publish',
        'menu-item-parent-id' => $parent,
    ));
    if (!is_wp_error($new) && $new) {
        printf("  menu: added to \"%s\" under item #%d\n", $menu->name, $parent);
        $placed = true;
        break;
    }
}
if (!$placed) {
    echo "  menu: no \"Categories\" item found — the category is live at "
       . get_term_link($term) . " but is not linked from the menu yet\n";
}

/* ── 4. report the price rule in force ────────────────────────────────── */
$ratio = af_goldfoil_ratio();
printf("\n  price ratio: x%.2f  (%s a normal print)\n", $ratio,
    $ratio < 1 ? sprintf('%d%% OF', round($ratio * 100))
               : sprintf('%d%% MORE than', round(($ratio - 1) * 100)));
$base = af_pricing_config_base();
$show = 0;
foreach ($base['sizes'] as $label => $usd) {
    if ($show++ >= 4) break;
    printf("    %-22s normal $%-6s gold foil $%s\n", $label, $usd, af_goldfoil_scale($usd, $ratio));
}
echo "  change it with:  wp option update af_goldfoil_ratio 1.4   (or 0.4)\n";

$count = (int) get_term($term->term_id, 'product_cat')->count;
printf("  products in the section: %d\n", $count);
if (!$count) {
    $src  = (string) get_option('af_goldfoil_source', '');
    $up   = wp_get_upload_dir();
    $conv = trailingslashit($up['basedir']) . 'gold-foil';
    if ($src !== '')            echo "  image folder set to {$src} — the next deploy imports it\n";
    elseif (is_dir($conv))      echo "  artwork folder found at {$conv} — the next deploy imports it\n";
    else                        echo "  waiting on artwork: drop the images into {$conv}\n"
                                   . "  (or name another folder with: wp option update af_goldfoil_source '/path')\n";
}
echo "=== DONE ===\n";
