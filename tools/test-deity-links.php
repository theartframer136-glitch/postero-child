<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Tests the category link rows in inc/deity-links.php with WordPress stubbed:
 *     php tools/test-deity-links.php
 */
define('ABSPATH', '/nowhere/');
function add_action() {} function add_filter() {}
function apply_filters($h, $v) { return $v; }
function esc_html($s) { return htmlspecialchars($s, ENT_QUOTES); }
function esc_attr($s) { return htmlspecialchars($s, ENT_QUOTES); }
function esc_url($s) { return $s; }
function is_wp_error($x) { return false; }
// The categories as they stood on 29 Oct 2026: slug => [id, products, parent].
$GLOBALS['cats'] = array(
    'hindu-deities' => array(10, 40, 0), 'radha-krishna' => array(11, 30, 10), 'buddha' => array(12, 20, 0),
    'abstract-art' => array(20, 50, 0), 'diy-floating-frames' => array(21, 8, 0), 'canvas-stretcher-bars' => array(22, 3, 0),
    'rolled-canvas' => array(23, 4, 0), 'standard-framed-canvas' => array(24, 0, 30), 'extra-large-canvas-paintings' => array(25, 0, 30),
    'framed-canvases' => array(30, 0, 0), 'premium-aluminium-frames' => array(26, 0, 31), 'aluminium-sizes' => array(27, 5, 26),
);
function get_term_by($f, $slug) {
    if (!isset($GLOBALS['cats'][$slug])) return false;
    $c = $GLOBALS['cats'][$slug];
    return (object) array('term_id' => $c[0], 'count' => $c[1], 'slug' => $slug);
}
function get_term_link($t) { return 'https://x.test/c/' . $t->slug . '/'; }
function get_terms($a) {   // child_of with hide_empty: ids of non-empty descendants
    $out = array(); $want = array($a['child_of']);
    for ($i = 0; $i < 3; $i++) foreach ($GLOBALS['cats'] as $c) if (in_array($c[2], $want, true)) { $want[] = $c[0]; if ($c[1] > 0) $out[] = $c[0]; }
    return $out;
}
require __DIR__ . '/../inc/deity-links.php';

$pass = 0; $fail = 0;
function same($label, $got, $want) { global $pass, $fail;
    if ($got === $want) { $pass++; echo "  OK    $label\n"; }
    else { $fail++; echo "  FAIL  $label\n        got  " . var_export($got, true) . "\n        want " . var_export($want, true) . "\n"; } }

$html = af_deity_links_html(0);
echo "=== the two rows ===\n";
same('the deity row is still there', strpos($html, '<h2>Shop by Deity &amp; Vastu</h2>') !== false, true);
same('the canvas and frames row is added', strpos($html, '<h2>Shop Canvas &amp; Frames</h2>') !== false, true);
same('the style is printed once', substr_count($html, '<style id="af-deity-links">'), 1);
same('Abstract Canvas Art links to its category', strpos($html, '<a href="https://x.test/c/abstract-art/">Abstract Canvas Art</a>') !== false, true);
same('Floating Frames for Canvas links to its category', strpos($html, '<a href="https://x.test/c/diy-floating-frames/">Floating Frames for Canvas</a>') !== false, true);
same('Canvas Stretcher Bars links to its category', strpos($html, 'canvas-stretcher-bars/">Canvas Stretcher Bars</a>') !== false, true);

echo "\n=== categories with nothing to sell ===\n";
same('empty Framed Canvas Prints is skipped', strpos($html, 'Framed Canvas Prints'), false);
same('empty Extra Large Canvas is skipped', strpos($html, 'Extra Large Canvas'), false);
same('a parent with products only under it still counts', strpos($html, 'Aluminium Canvas Frames') !== false, true);
same('Rolled Canvas is not in the row', strpos($html, 'rolled-canvas'), false);
same('af_term_has_products: a category with products', af_term_has_products(get_term_by('slug', 'buddha')), true);
same('af_term_has_products: an empty category', af_term_has_products(get_term_by('slug', 'standard-framed-canvas')), false);

echo "\n=== on a category's own page ===\n";
$own = af_deity_links_html(20);
same('Abstract Art is marked, not linked to itself', strpos($own, '<span aria-current="page">Abstract Canvas Art</span>') !== false && strpos($own, 'c/abstract-art/') === false, true);

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
