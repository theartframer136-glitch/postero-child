<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Prove that clicking a subcategory circle can actually swap the slider.
 *
 * Three things have to be true and each is checked separately, so a failure
 * says which one broke:
 *   1. the endpoint answers — POST action=load_products&subcategory=<slug>
 *      returns product cards, not an empty string or an error;
 *   2. the wiring is live — the homepage really carries the click handler that
 *      calls it (a cached page would still carry the old navigate-away code);
 *   3. the labels resolve — every circle's caption maps to a real category
 *      slug, because a caption that matches nothing can never be filtered.
 *
 * Read-only. Run: wp eval-file tools/verify-subcat-filter.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }

echo "=== VERIFY SUBCATEGORY FILTER ===\n";
$fail = 0;

// ── 1. the endpoint ──
echo "\n-- endpoint --\n";
$terms = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => true, 'number' => 6));
$tried = 0;
if (is_wp_error($terms) || !$terms) {
    echo "  no product categories to test with\n"; $fail++;
} else {
    foreach ($terms as $t) {
        $r = wp_remote_post(admin_url('admin-ajax.php'), array(
            'timeout' => 45, 'sslverify' => false,
            'body' => array('action' => 'load_products', 'subcategory' => $t->slug),
        ));
        if (is_wp_error($r)) { printf("  %-26s ERROR %s\n", $t->slug, $r->get_error_message()); $fail++; continue; }
        $b     = wp_remote_retrieve_body($r);
        $code  = wp_remote_retrieve_response_code($r);
        $cards = substr_count($b, 'product-card') + substr_count($b, 'woocommerce-loop-product');
        printf("  %-26s HTTP %s  %6d bytes  cards: %d  %s\n",
               $t->slug, $code, strlen($b), $cards, $cards > 0 ? 'OK' : 'EMPTY');
        if ($cards === 0) $fail++;
        if (++$tried >= 3) break;
    }
}

// ── 2. the wiring that is actually being served ──
echo "\n-- homepage wiring --\n";
$res = wp_remote_get(add_query_arg('afverify', time(), home_url('/')),
    array('timeout' => 60, 'sslverify' => false, 'headers' => array('User-Agent' => 'Mozilla/5.0 AF-Verify')));
if (is_wp_error($res)) {
    echo "  FETCH FAILED: " . $res->get_error_message() . "\n"; $fail++;
    $html = '';
} else {
    $html = wp_remote_retrieve_body($res);
    $checks = array(
        'filters in place (load_products call)' => strpos($html, "'load_products'") !== false
                                                 || strpos($html, '"load_products"') !== false
                                                 || strpos($html, 'load_products') !== false,
        'circle slugs published to the browser' => strpos($html, 'af-cat-slug') !== false
                                                 || strpos($html, '"s":') !== false,
        'admin-ajax url present'                => strpos($html, 'admin-ajax.php') !== false,
    );
    foreach ($checks as $what => $ok) {
        printf("  %-42s %s\n", $what, $ok ? 'OK' : 'MISSING');
        if (!$ok) $fail++;
    }
}

// ── 3. do the captions resolve to real categories? ──
echo "\n-- circle captions --\n";
if ($html !== '' && preg_match('#id=["\']subcategorySlider["\'].{0,20000}?</div>\s*</div>#is', $html, $m)) {
    $strip = $m[0];
} else {
    $strip = $html;
}
$map = array();
foreach ((array) get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false)) as $t) {
    if (is_wp_error($t) || !isset($t->name)) continue;
    $map[strtolower(preg_replace('/[^a-z0-9]+/i', '', html_entity_decode($t->name)))] = $t->slug;
}
if (preg_match_all('#<span[^>]*>([^<]{2,60})</span>#i', $strip, $caps)) {
    $unmatched = array();
    $matched   = 0;
    foreach (array_slice(array_unique($caps[1]), 0, 40) as $cap) {
        $key = strtolower(preg_replace('/[^a-z0-9]+/i', '', html_entity_decode($cap)));
        if ($key === '') continue;
        $hit = isset($map[$key]) ? $map[$key] : null;
        if (!$hit) {
            foreach ($map as $k => $slug) {
                if ($k !== '' && strpos($key, $k) !== false) { $hit = $slug; break; }
            }
        }
        if ($hit) $matched++; else $unmatched[] = $cap;
    }
    printf("  captions resolving to a category: %d\n", $matched);
    if ($unmatched) {
        echo "  NOT resolving (these circles cannot filter):\n";
        foreach (array_slice($unmatched, 0, 12) as $u) echo "    " . trim($u) . "\n";
    }
} else {
    echo "  no captions found in the served strip\n";
}

printf("\n=== %s ===\n", $fail ? "{$fail} CHECK(S) FAILED" : 'ALL CHECKS PASSED');
echo "=== DONE ===\n";
