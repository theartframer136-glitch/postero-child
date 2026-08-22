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

/* ── 3. the icon ──────────────────────────────────────────────────────── */
// The category menu draws each entry's icon from the term's own thumbnail, and
// tools/fill-category-thumbs.php fills those from the first product in the
// category — which cannot work for a category that has no products yet. So a
// stand-in is chosen here from artwork the studio already sells, preferring a
// piece that is itself gold, and it is MARKED as a stand-in: the moment real
// gold-foil artwork is imported, the importer replaces it with the real thing.
$thumb = (int) get_term_meta($term->term_id, 'thumbnail_id', true);
$file  = $thumb ? get_attached_file($thumb) : '';
if ($thumb && $file && @file_exists($file)) {
    printf("  icon: already set (attachment #%d)%s\n", $thumb,
        get_term_meta($term->term_id, '_af_goldfoil_thumb_auto', true) ? ' — a stand-in' : '');
} else {
    $pick = 0;
    foreach (array('golden', 'gold', '') as $needle) {
        $q = array('post_type' => 'product', 'post_status' => 'publish',
                   'posts_per_page' => 12, 'fields' => 'ids', 'no_found_rows' => true,
                   'orderby' => 'date', 'order' => 'DESC');
        if ($needle !== '') $q['s'] = $needle;
        foreach (get_posts($q) as $cand) {
            $att = get_post_thumbnail_id($cand);
            $f   = $att ? get_attached_file($att) : '';
            if ($att && $f && @file_exists($f)) { $pick = (int) $att; break 2; }
        }
    }
    if ($pick) {
        update_term_meta($term->term_id, 'thumbnail_id', $pick);
        update_term_meta($term->term_id, '_af_goldfoil_thumb_auto', 1);
        printf("  icon: stand-in set (attachment #%d) — replaced by the first gold-foil artwork imported\n", $pick);
    } else {
        echo "  icon: NONE — no product image on the site to stand in with\n";
    }
}

/* ── 4. one entry in the menu, under "Categories" ─────────────────────── */
// The section is only a section if it is reachable. The site's category menu is
// a WordPress nav menu, so the new term needs an item of its own — placed as a
// child of whichever item is called "Categories", which is where every other
// category already lives.
// $placed_id and $sibling_ids are carried into the icon step below: the icon
// lives on the menu ITEM, so both which item this is and which items to copy
// its shape from have to come out of this search.
$placed_id   = 0;
$sibling_ids = array();
$menus       = wp_get_nav_menus();
foreach ($menus as $menu) {
    $items = wp_get_nav_menu_items($menu->term_id);
    if (!$items) continue;

    // find the "Categories" parent to hang it under
    $parent = 0;
    foreach ($items as $it) {
        if (preg_match('/^\s*categor/i', wp_strip_all_tags($it->title))) { $parent = (int) $it->ID; break; }
    }
    if (!$parent) continue;

    // already there? (search this menu only — the icon must come from the
    // siblings of the item that actually exists, not from another menu's)
    $mine = 0;
    foreach ($items as $it) {
        if ($it->type === 'taxonomy' && $it->object === 'product_cat'
            && (int) $it->object_id === (int) $term->term_id) { $mine = (int) $it->ID; break; }
    }

    if (!$mine) {
        $new = wp_update_nav_menu_item($menu->term_id, 0, array(
            'menu-item-title'     => $name,
            'menu-item-object'    => 'product_cat',
            'menu-item-object-id' => (int) $term->term_id,
            'menu-item-type'      => 'taxonomy',
            'menu-item-status'    => 'publish',
            'menu-item-parent-id' => $parent,
        ));
        if (is_wp_error($new) || !$new) continue;
        $mine = (int) $new;
        printf("  menu: added to \"%s\" under item #%d\n", $menu->name, $parent);
    } else {
        printf("  menu: already in \"%s\" (item #%d)\n", $menu->name, $mine);
    }

    $placed_id = $mine;
    foreach ($items as $it) {
        if ((int) $it->menu_item_parent === $parent && (int) $it->ID !== $mine) {
            $sibling_ids[] = (int) $it->ID;
        }
    }
    break;
}
if (!$placed_id) {
    echo "  menu: no \"Categories\" item found — the category is live at "
       . get_term_link($term) . " but is not linked from the menu yet\n";
}

/* ── 5. the menu icon ─────────────────────────────────────────────────── */
// MEASURED, not guessed (tools/diag-menu-icons.php, deploy 805): every entry in
// the Categories mega menu carries a `postero_megamenu_item_data` meta field on
// the MENU ITEM, and its `icon` key holds a Postero icon-font class —
// postero-icon-typography, postero-icon-artists, postero-icon-gift-box and so
// on. It is not the category thumbnail: "Gifts" has no thumbnail and no
// products and still shows its icon. A new item created through
// wp_update_nav_menu_item() gets no such field, which is exactly why this one
// appeared blank.
//
// The field is a 14-key array belonging to the parent theme, so rather than
// inventing one, a sibling's is CLONED and only the icon swapped. That keeps
// every default the theme expects, whatever the other thirteen keys mean.
if ($placed_id) {
    $existing = get_post_meta($placed_id, 'postero_megamenu_item_data', true);
    if (!empty($existing) && is_array($existing) && !empty($existing['icon'])) {
        printf("  menu icon: already set (%s)\n", $existing['icon']);
    } else {
        // a sibling to copy the shape from
        $template = array();
        $used     = array();
        foreach ($sibling_ids as $sid) {
            $d = get_post_meta($sid, 'postero_megamenu_item_data', true);
            if (is_array($d) && !empty($d['icon'])) {
                if (!$template) $template = $d;
                $used[] = $d['icon'];
            }
        }

        // which icons the theme's font actually defines — picking a class that
        // does not exist would render nothing and look like this never worked
        $have = array();
        $dir  = get_template_directory();
        if (is_dir($dir)) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
            $scanned = 0;
            foreach ($it as $f) {
                if (!$f->isFile() || strtolower($f->getExtension()) !== 'css') continue;
                if ($scanned++ > 60) break;
                $css = @file_get_contents($f->getPathname());
                if ($css && preg_match_all('/\.(postero-icon-[a-z0-9\-]+)/i', $css, $m)) {
                    foreach ($m[1] as $cls) $have[strtolower($cls)] = true;
                }
            }
        }

        // what a gold-foiled, UV-coated piece should look like, best first
        $wish = array('postero-icon-star', 'postero-icon-diamond', 'postero-icon-crown',
                      'postero-icon-gem', 'postero-icon-award', 'postero-icon-medal',
                      'postero-icon-sparkle', 'postero-icon-shine', 'postero-icon-sun',
                      'postero-icon-brush', 'postero-icon-frame', 'postero-icon-picture',
                      'postero-icon-gallery', 'postero-icon-image', 'postero-icon-art');
        $icon = '';
        foreach ($wish as $w) { if (isset($have[$w])) { $icon = $w; break; } }
        // nothing from the wish list exists (or no CSS was readable): fall back
        // to an icon this very menu already proves is real
        if ($icon === '' && $used) $icon = $used[0];
        if ($icon === '') $icon = 'postero-icon-art';

        if (!$template) $template = array('icon' => '');
        $template['icon'] = $icon;
        update_post_meta($placed_id, 'postero_megamenu_item_data', $template);
        update_post_meta($placed_id, '_af_goldfoil_menu_icon', $icon);
        printf("  menu icon: set to %s  (%d icons found in the theme's CSS, %d siblings to copy from)\n",
            $icon, count($have), count($used));
        // The other thirteen keys are the parent theme's business; list them so
        // that if one of them turns out to carry per-item content rather than a
        // default, it is visible here instead of being a mystery on the page.
        printf("             cloned keys: %s\n", implode(', ', array_keys($template)));
        printf("             siblings use: %s\n", implode(', ', array_unique($used)));
    }
} else {
    echo "  menu icon: skipped — the menu item could not be located\n";
}

/* ── 6. report the price rule in force ────────────────────────────────── */
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
