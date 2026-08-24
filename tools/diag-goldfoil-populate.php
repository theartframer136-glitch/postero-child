<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * One question, asked once: what is already on THIS site that the Gold Foiled &
 * UV section could be built from?
 *
 * WHY IT IS ONE BIG SCRIPT
 * The live database can only be reached through a deploy, and a deploy is a
 * fifteen-minute round trip. Three narrow probes run one after another cost
 * three quarters of an hour and a lot of the owner's patience, so this asks
 * everything at once and prints the answers in one place. It writes nothing.
 *
 * WHAT IT IS FOR
 * The artwork the owner named lives at C:\Users\user\SynologyDrive\Personalised
 * — a folder on their own PC, which neither this server nor the deploy can
 * read. Four deploys have now confirmed it has never reached the site. But the
 * site is not empty: it holds ~2,000 products and 5,512 images, and one of its
 * categories is called "Personalised Prints". If the same artwork is already
 * here under another name, the section can be built today from products that
 * exist, with nothing at all asked of the owner. That is what this looks for.
 *
 * The evidence that settles it is FILENAMES: a product whose picture is called
 * something the owner would recognise from their own folder is the same
 * artwork. So every sample below prints the image file it actually uses.
 *
 * Read-only. Run: wp eval-file tools/diag-goldfoil-populate.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
global $wpdb;

echo "=== DIAG: WHAT COULD POPULATE GOLD FOILED & UV ===\n";

/** The picture a product actually shows, as a bare filename. */
function af_gfp_thumb_name($pid) {
    $att = get_post_thumbnail_id($pid);
    if (!$att) return '(no image)';
    $f = get_attached_file($att);
    return $f ? basename($f) : ('#' . $att . ' (file missing)');
}

/* ── A. the settings in force ─────────────────────────────────────────── */
echo "\nA. OPTIONS IN FORCE\n";
foreach (array('af_goldfoil_source', 'af_goldfoil_ratio', 'af_goldfoil_max',
               'af_goldfoil_default_size', 'af_goldfoil_fresh_since') as $opt) {
    $v = get_option($opt, null);
    printf("   %-26s %s\n", $opt, ($v === null || $v === false || $v === '') ? '(not set)' : (is_scalar($v) ? $v : gettype($v)));
}
if (function_exists('af_goldfoil_ratio')) {
    $r = af_goldfoil_ratio();
    printf("   effective price ratio      x%.2f  (%s a normal print)\n", $r,
        $r < 1 ? round($r * 100) . '% OF' : round(($r - 1) * 100) . '% MORE than');
}

/* ── B. every category, with a count that is actually true ────────────── */
// WooCommerce's stored term count is padded with sub-category totals and counts
// non-published posts in some states, so it answers a different question than
// "how many published products could I copy from here". One grouped query gets
// the real number for every term at once — 90 separate counts would be 90
// queries on a shared host that has hit its resource limit before.
echo "\nB. EVERY PRODUCT CATEGORY (published products filed DIRECTLY on the term)\n";
$real = array();
$rows = $wpdb->get_results(
    "SELECT tt.term_id AS term_id, COUNT(DISTINCT p.ID) AS n
       FROM {$wpdb->term_taxonomy} tt
       JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
       JOIN {$wpdb->posts} p ON p.ID = tr.object_id
      WHERE tt.taxonomy = 'product_cat'
        AND p.post_type = 'product'
        AND p.post_status = 'publish'
      GROUP BY tt.term_id");
foreach ($rows as $r) $real[(int) $r->term_id] = (int) $r->n;

$terms = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false));
if (is_wp_error($terms)) {
    echo "   could not read the categories: " . $terms->get_error_message() . "\n";
    $terms = array();
}
// biggest first: the useful sources are the ones with artwork in them
usort($terms, function ($a, $b) use ($real) {
    $x = isset($real[(int) $a->term_id]) ? $real[(int) $a->term_id] : 0;
    $y = isset($real[(int) $b->term_id]) ? $real[(int) $b->term_id] : 0;
    return $y - $x;
});
printf("   %d categories; showing every one with products, then the empty ones by name\n", count($terms));
$empties = array();
foreach ($terms as $t) {
    $n = isset($real[(int) $t->term_id]) ? $real[(int) $t->term_id] : 0;
    if ($n === 0) { $empties[] = $t->slug . ' (#' . $t->term_id . ')'; continue; }
    $parent = $t->parent ? (' child of #' . $t->parent) : ' top level';
    printf("   %5d  %-34s %-30s stored=%-5d real=%-5d%s\n",
        $t->term_id, substr($t->slug, 0, 34), substr($t->name, 0, 30),
        (int) $t->count, $n, $parent);
}
if ($empties) {
    echo "   empty: " . implode(', ', array_slice($empties, 0, 40)) . "\n";
    if (count($empties) > 40) printf("   ...and %d more empty categories\n", count($empties) - 40);
}

/* ── C. the categories most likely to hold the owner's artwork ────────── */
// "Personalised" is the name of the owner's own folder, so any category whose
// slug carries that word is the first place to look; the largest categories
// follow, because that is where this site's artwork actually lives.
echo "\nC. SAMPLES FROM THE LIKELIEST SOURCES (filenames are the evidence)\n";
$cands = array();
foreach ($terms as $t) {
    if (preg_match('/person|custom|gold|foil/i', $t->slug . ' ' . $t->name)) $cands[] = $t;
}
$shown = array();
foreach ($cands as $t) $shown[(int) $t->term_id] = true;
foreach ($terms as $t) {                       // top up with the biggest categories
    if (count($cands) >= 8) break;
    $n = isset($real[(int) $t->term_id]) ? $real[(int) $t->term_id] : 0;
    if ($n > 0 && empty($shown[(int) $t->term_id])) { $cands[] = $t; $shown[(int) $t->term_id] = true; }
}

foreach ($cands as $t) {
    $n = isset($real[(int) $t->term_id]) ? $real[(int) $t->term_id] : 0;
    printf("\n   [%s] #%d \"%s\" — %d published product(s) directly on it\n",
        $t->slug, $t->term_id, $t->name, $n);

    // its children, since a parent often holds nothing itself
    $kids = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false,
                            'parent' => (int) $t->term_id));
    if (!is_wp_error($kids) && $kids) {
        $bits = array();
        foreach ($kids as $k) {
            $bits[] = $k->slug . '(' . (isset($real[(int) $k->term_id]) ? $real[(int) $k->term_id] : 0) . ')';
        }
        echo "      sub-categories: " . implode(', ', $bits) . "\n";
    }
    if (!$n) { echo "      (nothing filed directly here)\n"; continue; }

    $ids = get_posts(array(
        'post_type' => 'product', 'post_status' => 'publish',
        'posts_per_page' => 12, 'fields' => 'ids', 'no_found_rows' => true,
        'orderby' => 'date', 'order' => 'DESC',
        'tax_query' => array(array('taxonomy' => 'product_cat', 'field' => 'term_id',
            'terms' => array((int) $t->term_id), 'include_children' => false)),
    ));
    foreach ($ids as $pid) {
        $p = function_exists('wc_get_product') ? wc_get_product($pid) : null;
        $gal = get_post_meta($pid, '_product_image_gallery', true);
        $ngal = $gal ? count(array_filter(explode(',', $gal))) : 0;
        printf("      #%-7d %-44s %-9s gallery=%-3d %s\n",
            $pid,
            substr(get_the_title($pid), 0, 44),
            $p ? ('$' . $p->get_price()) : '?',
            $ngal,
            af_gfp_thumb_name($pid));
    }
    if ($n > count($ids)) printf("      ...and %d more not shown\n", $n - count($ids));
}

/* ── D. the section itself ────────────────────────────────────────────── */
echo "\nD. THE GOLD FOILED & UV SECTION RIGHT NOW\n";
$gf = function_exists('af_goldfoil_slug') ? get_term_by('slug', af_goldfoil_slug(), 'product_cat') : null;
if (!$gf || is_wp_error($gf)) {
    echo "   the category does not exist\n";
} else {
    printf("   term #%d, stored count %d, real count %d\n", $gf->term_id, (int) $gf->count,
        isset($real[(int) $gf->term_id]) ? $real[(int) $gf->term_id] : 0);
    $mine = get_posts(array('post_type' => 'product', 'post_status' => array('publish', 'draft'),
        'posts_per_page' => 20, 'fields' => 'ids', 'no_found_rows' => true,
        'tax_query' => array(array('taxonomy' => 'product_cat', 'field' => 'term_id',
            'terms' => array((int) $gf->term_id), 'include_children' => true))));
    if (!$mine) {
        echo "   no products in it yet\n";
    } else {
        foreach ($mine as $pid) {
            printf("     #%-7d %-46s src=%s\n", $pid, substr(get_the_title($pid), 0, 46),
                get_post_meta($pid, '_af_goldfoil_src', true) ?: '(none)');
        }
    }
    // anything flagged as gold-foil that somehow is NOT in the category
    $flagged = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_af_goldfoil' AND meta_value = 'yes'");
    printf("   products carrying the _af_goldfoil flag: %d\n", $flagged);
}

/* ── E. the newest uploads, used or not ───────────────────────────────── */
// The older probe listed only images NOTHING references, which is precisely the
// wrong filter for catching a fresh upload: WordPress attaches an image to
// whatever post it was uploaded from, so a picture dragged in while editing a
// page is "used" and would never appear. This lists the newest uploads outright.
echo "\nE. NEWEST MEDIA LIBRARY UPLOADS (whether or not anything uses them)\n";
$total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='attachment' AND post_mime_type LIKE 'image/%'");
$week  = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='attachment' AND post_mime_type LIKE 'image/%%' AND post_date >= %s",
    gmdate('Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS)));
printf("   images in the library: %d      uploaded in the last 7 days: %d\n", $total, $week);
$newest = $wpdb->get_results(
    "SELECT ID, post_date, post_title, guid, post_parent
       FROM {$wpdb->posts}
      WHERE post_type='attachment' AND post_mime_type LIKE 'image/%'
      ORDER BY post_date DESC LIMIT 30");
foreach ($newest as $a) {
    printf("     #%-8d %-16s parent=%-7d %s\n", $a->ID, substr($a->post_date, 0, 16),
        (int) $a->post_parent, basename(parse_url($a->guid, PHP_URL_PATH)));
}

/* ── F. the uploads tree, deeper than one level ───────────────────────── */
// The older probe read only the top level of uploads/, so a folder uploaded
// inside another folder — which is exactly what unzipping or an FTP client
// does — was invisible to it. This walks the tree, skipping the year folders
// and the plugin caches that hold the site's own 5,512 images, and stops after
// a bounded number of entries so it cannot become the thing that overloads a
// shared host.
echo "\nF. FOLDERS IN uploads/ THAT HOLD IMAGES (recursive, year + plugin dirs aside)\n";
$up      = wp_get_upload_dir();
$base    = $up['basedir'];
$EXTS    = array('jpg', 'jpeg', 'png', 'webp', 'gif');
$SKIP    = array('af-wm', 'elementor', 'essential-addons-elementor', 'premium-addons-elementor',
                 'revslider', 'rank-math', 'wpo', 'lsft', 'firebox', 'pum', 'merlin-wp',
                 'transposh', 'woocommerce_uploads', 'wc-logs', 'dfg-logs', 'insta-gallery-logs',
                 'taf-reports', 'wpcf7_uploads', 'wpallimport', 'wc-imports', 'gold-foil-unpacked');
$counts  = array();
$seen    = 0;
$CAP     = 40000;
$walk = function ($dir, $depth) use (&$walk, &$counts, &$seen, $CAP, $EXTS, $SKIP, $base) {
    if ($seen > $CAP || $depth > 4) return;
    $entries = @scandir($dir);
    if (!$entries) return;
    foreach ($entries as $e) {
        if ($e === '.' || $e === '..') continue;
        if ($seen++ > $CAP) return;
        $p = $dir . '/' . $e;
        if (is_dir($p)) {
            if ($depth === 0 && (preg_match('/^\d{4}$/', $e) || in_array($e, $SKIP, true))) continue;
            $walk($p, $depth + 1);
        } elseif (in_array(strtolower(pathinfo($e, PATHINFO_EXTENSION)), $EXTS, true)) {
            // WordPress's own resized copies are not separate artworks
            if (preg_match('/-\d{2,4}x\d{2,4}\.[a-z]+$/i', $e)) continue;
            $rel = str_replace($base, '', $dir);
            $counts[$rel] = isset($counts[$rel]) ? $counts[$rel] + 1 : 1;
        }
    }
};
$walk($base, 0);
arsort($counts);
if (!$counts) {
    echo "   (no images outside the year folders and plugin caches)\n";
} else {
    $i = 0;
    foreach ($counts as $rel => $n) {
        if ($i++ >= 25) { printf("   ...and %d more folders\n", count($counts) - 25); break; }
        printf("   %-56s %d image(s)%s\n", ($rel === '' ? '/' : $rel), $n,
            preg_match('/person|gold|foil|synolog/i', $rel) ? '   <-- LOOKS LIKE IT' : '');
    }
}
printf("   (%d directory entries examined, cap %d)\n", $seen, $CAP);

echo "\n=== DONE ===\n";
