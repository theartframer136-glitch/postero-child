<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Where does the homepage's "Shop by Collection" tab strip get its list from,
 * and why did Gold Foiled & UV never appear in it?
 *
 * The strip is the parent theme's markup (#topCatSlider > .top-cat-btn, with
 * .cat-nav arrows either side) and nothing in the child theme renders it, so
 * the answer is not in this repository. inc/goldfoil-collection.php closes the
 * gap from both ends without knowing the answer — a term filter for the case
 * where the strip is a term query, and a browser-side clone for every other
 * case. This says which of the two is doing the work, so the next deploy can
 * drop the one that is not needed.
 *
 * Read-only. Run: wp eval-file tools/diag-topcat-strip.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }

echo "=== SHOP BY COLLECTION — WHERE THE TABS COME FROM ===\n";

/* ── 1. the section's own state ───────────────────────────────────────── */
$slug = function_exists('af_goldfoil_slug') ? af_goldfoil_slug() : 'gold-foiled-uv';
$term = get_term_by('slug', $slug, 'product_cat');
if (!$term || is_wp_error($term)) {
    echo "  the section does not exist — tools/setup-gold-foil.php has not run.\n";
} else {
    printf("  term #%d  parent=%d  count=%d  thumb=%s\n", $term->term_id, $term->parent,
        (int) $term->count, get_term_meta($term->term_id, 'thumbnail_id', true) ?: 'none');
    $kids = get_terms(array('taxonomy' => 'product_cat', 'parent' => (int) $term->term_id, 'hide_empty' => false));
    if (is_wp_error($kids) || !$kids) echo "  sub-collections: none\n";
    else foreach ($kids as $k) printf("  sub-collection: %-22s #%-6d %d product(s)  thumb=%s\n",
        $k->name, $k->term_id, (int) $k->count, get_term_meta($k->term_id, 'thumbnail_id', true) ?: 'none');
}

/* ── 2. what a top-level term query returns, with and without our filter ── */
// The filter only fires on the front page, so a query made here shows the raw
// list — which is what the theme would get if it never ran on the homepage.
$top = get_terms(array('taxonomy' => 'product_cat', 'parent' => 0, 'hide_empty' => true));
echo "\n  top-level, hide_empty=1 (" . (is_wp_error($top) ? 'error' : count($top)) . "):\n";
if (!is_wp_error($top)) foreach ($top as $t) printf("    %-28s %-26s %d\n", $t->name, $t->slug, (int) $t->count);
$top0 = get_terms(array('taxonomy' => 'product_cat', 'parent' => 0, 'hide_empty' => false, 'fields' => 'slugs'));
echo "  top-level, hide_empty=0: " . (is_wp_error($top0) ? 'error' : implode(', ', $top0)) . "\n";

/* ── 3. the parent theme's own code for the strip ─────────────────────── */
$dir = get_template_directory();
$needles = array('topCatSlider', 'top-cat-btn', 'top-category-slider', 'top-category-container', 'subcategorySlider');
echo "\n=== parent theme lines that build the strip ({$dir}) ===\n";
$hits = 0;
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
foreach ($rii as $f) {
    if ($f->getExtension() !== 'php') continue;
    $lines = @file($f->getPathname());
    if (!$lines) continue;
    foreach ($lines as $i => $ln) {
        foreach ($needles as $n) {
            if (strpos($ln, $n) === false) continue;
            printf("  %s:%d  %s\n", str_replace($dir . '/', '', $f->getPathname()), $i + 1, trim(substr($ln, 0, 150)));
            $hits++;
            // the twenty lines above a strip line are where its list is chosen
            for ($j = max(0, $i - 20); $j < $i; $j++) {
                if (preg_match('/get_terms|WP_Term_Query|product_cat|hide_empty|\bnumber\b|parent|get_option|get_theme_mod|wp_get_nav_menu/', $lines[$j])) {
                    printf("      ^%d  %s\n", $j + 1, trim(substr($lines[$j], 0, 150)));
                }
            }
            break;
        }
    }
}
if (!$hits) echo "  none — the strip is not built in the parent theme's PHP (a plugin, or Elementor)\n";

/* ── 4. what the served homepage actually contains ────────────────────── */
echo "\n=== the served homepage ===\n";
$r = wp_remote_get(add_query_arg('afdiag', time(), home_url('/')), array(
    'timeout' => 60, 'sslverify' => false,
    'headers' => array('User-Agent' => 'Mozilla/5.0 AF-Diag-TopCat')));
if (is_wp_error($r)) {
    echo "  could not load: " . $r->get_error_message() . "\n";
} else {
    $b = wp_remote_retrieve_body($r);
    printf("  %d bytes, HTTP %d\n", strlen($b), wp_remote_retrieve_response_code($r));
    printf("  child module shipped (af-gf-collection-js): %s\n", strpos($b, 'af-gf-collection-js') !== false ? 'yes' : 'NO');
    $p = strpos($b, 'topCatSlider');
    if ($p === false) $p = strpos($b, 'top-cat-btn');
    if ($p === false) {
        echo "  the strip's markup is not in the served HTML (built in the browser)\n";
    } else {
        $seg = substr($b, max(0, $p - 400), 4000);
        // the tab labels, in order
        if (preg_match_all('/top-cat-btn[^>]*>\s*([^<]{2,60})/i', $seg, $m)) {
            echo "  tabs rendered server-side: " . implode(' | ', array_map('trim', $m[1])) . "\n";
        }
        printf("  the section is already a server-rendered tab: %s\n",
            stripos($seg, 'Gold Foiled') !== false ? 'yes (the term filter did it)' : 'no (the browser clone does it)');
    }
}
echo "=== DONE ===\n";
