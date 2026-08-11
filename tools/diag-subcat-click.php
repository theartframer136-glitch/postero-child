<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * What is supposed to happen when a subcategory circle is clicked?
 *
 * The report is that clicking a circle no longer shows that subcategory's
 * products in the slider. Two behaviours are possible and they are mutually
 * exclusive: the parent theme filters the slider in place over AJAX, or the
 * circle is a link to the category archive. The child theme added a click
 * handler that navigates, and if the parent was filtering in place, that
 * handler replaced the behaviour being asked for.
 *
 * This prints the parent theme's own wiring — the JS that binds those circles
 * and whatever it calls — plus the markup the circles actually ship with, so
 * the original behaviour can be restored exactly rather than reinvented.
 * Changes nothing.
 *
 * Run: wp eval-file tools/diag-subcat-click.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }

echo "=== SUBCATEGORY CIRCLE CLICK DIAGNOSIS ===\n";

$parent = get_template_directory();
echo "  parent theme: {$parent}\n";

// ── 1. where the parent theme mentions the circles or the ajax action ──
$needles = array('subcategorySlider', 'cat-item', 'load_products', 'subcategory');
$files = array();
foreach (array('/*.php', '/assets/js/*.js', '/js/*.js', '/assets/*.js', '/template-parts/*.php', '/inc/*.php') as $g) {
    $files = array_merge($files, (array) glob($parent . $g));
}
echo "  scanned files: " . count($files) . "\n";

foreach ($needles as $needle) {
    echo "\n-- \"{$needle}\" in the parent theme --\n";
    $found = 0;
    foreach ($files as $f) {
        $src = @file_get_contents($f);
        if ($src === false || stripos($src, $needle) === false) continue;
        $lines = explode("\n", $src);
        foreach ($lines as $i => $line) {
            if (stripos($line, $needle) === false) continue;
            printf("  %s:%d  %s\n", basename($f), $i + 1,
                   trim(preg_replace('/\s+/', ' ', mb_strimwidth($line, 0, 150, '…'))));
            if (++$found >= 12) break 2;
        }
    }
    if (!$found) echo "  not found\n";
}

// ── 2. the click handler in full, wherever it lives ──
echo "\n-- click handler bound to the circles (parent theme) --\n";
$printed = 0;
foreach ($files as $f) {
    $src = @file_get_contents($f);
    if ($src === false) continue;
    if (!preg_match_all('#[^\n]*(?:cat-item|subcategorySlider|category-circle|circle-item)[^\n]*\.(?:on|addEventListener)\s*\(\s*[\'"]click[\'"][^\n]*#i',
                        $src, $m)) continue;
    foreach ($m[0] as $hit) {
        echo "  " . basename($f) . ":  " . trim(preg_replace('/\s+/', ' ', mb_strimwidth($hit, 0, 220, '…'))) . "\n";
        if (++$printed >= 8) break 2;
    }
}
if (!$printed) echo "  no direct click binding found by pattern\n";

// ── 3. what the ajax endpoint expects ──
echo "\n-- load_products ajax --\n";
$fn = $parent . '/functions.php';
$src = @file_get_contents($fn);
if ($src && preg_match('/function\s+load_products\s*\([^)]*\)\s*\{(.*?)\n\}/s', $src, $fm)) {
    if (preg_match_all('/\$_(?:POST|REQUEST|GET)\[[\'"]([^\'"]+)[\'"]\]/', $fm[1], $km)) {
        echo "  request keys: " . implode(', ', array_unique($km[1])) . "\n";
    }
    echo "  body length: " . strlen($fm[1]) . " chars\n";
} else {
    echo "  load_products() not found in the parent functions.php\n";
}
echo "  registered ajax hooks: ";
global $wp_filter;
$hooks = array();
foreach (array('wp_ajax_load_products', 'wp_ajax_nopriv_load_products') as $h) {
    if (isset($wp_filter[$h])) $hooks[] = $h;
}
echo ($hooks ? implode(', ', $hooks) : 'none') . "\n";

// ── 4. the markup the circles actually ship with ──
echo "\n-- circle markup on the live homepage --\n";
$res = wp_remote_get(add_query_arg('afdiag', time(), home_url('/')),
    array('timeout' => 60, 'sslverify' => false, 'headers' => array('User-Agent' => 'Mozilla/5.0 AF-Diag')));
if (is_wp_error($res)) { echo "  FETCH FAILED\n=== DONE ===\n"; return; }
$html = wp_remote_retrieve_body($res);
$pos = strpos($html, 'subcategorySlider');
if ($pos === false) $pos = strpos($html, 'cat-item');
if ($pos === false) {
    echo "  the circle strip is not in the served html\n";
} else {
    $chunk = substr($html, max(0, $pos - 200), 2600);
    echo "  " . trim(preg_replace('/\s+/', ' ', $chunk)) . "\n";
}

echo "=== DONE ===\n";
