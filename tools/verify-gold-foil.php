<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * The Gold Foiled & UV section must agree with itself: the price the grid
 * shows, the price the product page's selector opens on, and the price the cart
 * would charge all come from different code paths, and a ratio applied in only
 * some of them is worse than no ratio at all.
 *
 * Read-only. Run: wp eval-file tools/verify-gold-foil.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }

echo "=== VERIFY GOLD FOILED & UV ===\n";
$fail = 0;
$note = function ($what, $ok, $detail = '') use (&$fail) {
    printf("  %-34s %s%s\n", $what, $ok ? 'OK' : 'FAIL', $detail !== '' ? '  ' . $detail : '');
    if (!$ok) $fail++;
};

/* ── the module ───────────────────────────────────────────────────────── */
$loaded = function_exists('af_goldfoil_ratio') && function_exists('af_goldfoil_factor')
       && function_exists('af_is_goldfoil') && function_exists('af_pricing_config_base');
$note('module loaded', $loaded);
if (!$loaded) { echo "=== DONE ===\n"; return; }

$ratio = af_goldfoil_ratio();
$note('ratio in range', $ratio >= 0.05 && $ratio <= 5,
    sprintf('x%.2f (%s a normal print)', $ratio,
        $ratio < 1 ? round($ratio * 100) . '% OF' : round(($ratio - 1) * 100) . '% MORE than'));

/* ── the category ─────────────────────────────────────────────────────── */
$term = get_term_by('slug', af_goldfoil_slug(), 'product_cat');
$note('category exists', (bool) ($term && !is_wp_error($term)),
    $term && !is_wp_error($term) ? '#' . $term->term_id . ', ' . (int) $term->count . ' product(s)' : '');
if (!$term || is_wp_error($term)) { echo "=== DONE ===\n"; return; }

/* ── a normal product is untouched ────────────────────────────────────── */
$base = af_pricing_config_base();
$plain = af_pricing_config(0);
$note('normal prices unchanged', $plain['sizes'] === $base['sizes']);

$normal_id = 0;
foreach (wc_get_products(array('status' => 'publish', 'limit' => 25, 'return' => 'ids')) as $cand) {
    $p = wc_get_product($cand);
    if ($p && af_pricing_applies($p) && !af_is_goldfoil($p)) { $normal_id = $cand; break; }
}
if ($normal_id) {
    $cfg = af_pricing_config($normal_id);
    $note('normal product unscaled', $cfg['sizes'] === $base['sizes'], '#' . $normal_id);
}

/* ── a gold-foil product is scaled, everywhere ────────────────────────── */
$ids = get_posts(array('post_type' => 'product', 'post_status' => 'publish',
    'posts_per_page' => 3, 'fields' => 'ids', 'no_found_rows' => true,
    'tax_query' => array(array('taxonomy' => 'product_cat', 'field' => 'term_id',
        'terms' => array((int) $term->term_id), 'include_children' => true))));

if (!$ids) {
    echo "  section is empty — nothing priced yet.\n";
    $src = (string) get_option('af_goldfoil_source', '');
    echo $src === ''
        ? "  next step: set af_goldfoil_source to the image folder, then run tools/import-gold-foil.php\n"
        : "  image folder set to {$src} — run tools/import-gold-foil.php\n";
} else {
    foreach ($ids as $pid) {
        $p = wc_get_product($pid);
        $note('flagged gold foil #' . $pid, af_is_goldfoil($p), $p ? $p->get_name() : '');
        if (!$p) continue;

        // each product opens on the size in its OWN title, so the expected
        // figure is computed per product, not once for the section
        $size = function_exists('af_size_default') ? af_size_default($p) : array_key_first($base['sizes']);
        $want = af_goldfoil_scale($base['sizes'][$size], $ratio);

        // 1. the price book the product page hands to the selector
        $cfg = af_pricing_config($pid);
        $note('  selector price scaled', isset($cfg['sizes'][$size]) && abs($cfg['sizes'][$size] - $want) < 0.01,
            sprintf('%s: $%s (card $%s)', $size, isset($cfg['sizes'][$size]) ? $cfg['sizes'][$size] : '?', $base['sizes'][$size]));

        // 2. the calculator the cart charges from — unframed, so no surcharge
        $charged = af_calc_price(0, $size, 'Without Frame', 'Black', $pid);
        $note('  cart charges the same', abs($charged - $want) < 0.01, '$' . $charged);

        // 3. a frame surcharge must NOT be scaled
        $framed = af_calc_price(0, $size, 'Aluminium Frame', 'Gold', $pid);
        $addon  = $base['frames']['Aluminium Frame'] + $base['colors']['Gold'];
        $note('  frame fee not scaled', abs($framed - ($want + $addon)) < 0.01,
            sprintf('$%s = $%s + $%s', $framed, $want, $addon));

        // 4. the number on the grid is that same number
        $listed = (float) wc_get_price_to_display($p);
        $note('  listed price matches', abs($listed - $want) < 0.51, '$' . $listed);
    }
}

/* ── the page is reachable and says what it is ────────────────────────── */
$url = get_term_link($term);
if (!is_wp_error($url)) {
    $r = wp_remote_get(add_query_arg('afverify', time(), $url),
        array('timeout' => 60, 'sslverify' => false,
              'headers' => array('User-Agent' => 'Mozilla/5.0 AF-Verify')));
    if (is_wp_error($r)) {
        $note('category page loads', false, $r->get_error_message());
    } else {
        $code = wp_remote_retrieve_response_code($r);
        $html = wp_remote_retrieve_body($r);
        $note('category page loads', $code === 200, $url . ' (' . $code . ')');
        $note('page names the finish', stripos($html, 'Gold Foiled') !== false);
    }
}

printf("\n=== %s ===\n", $fail ? "{$fail} CHECK(S) FAILED" : 'ALL CHECKS PASSED');
echo "=== DONE ===\n";
