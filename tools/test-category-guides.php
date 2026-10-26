<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Tests inc/category-guides.php with WordPress stubbed:
 *     php tools/test-category-guides.php
 */
define('ABSPATH', '/nowhere/'); define('OBJECT', 'OBJECT');
function add_action() {} function add_filter() {}
function esc_html($s) { return htmlspecialchars($s, ENT_QUOTES); }
function esc_url($s) { return $s; }
function home_url($p = '') { return 'https://x.test' . $p; }
function wp_strip_all_tags($s) { return strip_tags($s); }
function is_wp_error($x) { return false; }
// Categories and posts that exist; anything else does not.
function get_term_by($f, $slug) { return in_array($slug, array('diy-floating-frames', 'vaastu-art'), true) ? (object) array('slug' => $slug) : false; }
function get_term_link($t) { return 'https://x.test/c/' . $t->slug . '/'; }
function get_page_by_path($slug, $o, $type) {
    $live = array('7-horses-painting-vastu-direction' => 'publish', 'old-draft' => 'draft');
    return isset($live[$slug]) ? (object) array('post_name' => $slug, 'post_status' => $live[$slug]) : null;
}
function get_permalink($p) { return 'https://x.test/' . $p->post_name . '/'; }
require __DIR__ . '/../inc/category-guides.php';

$pass = 0; $fail = 0;
function same($label, $got, $want) { global $pass, $fail;
    if ($got === $want) { $pass++; echo "  OK    $label\n"; }
    else { $fail++; echo "  FAIL  $label\n        got  " . var_export($got, true) . "\n        want " . var_export($want, true) . "\n"; } }

echo "=== links in guide text ===\n";
same('a published blog post becomes a link', af_cat_guide_text('See [[post:7-horses-painting-vastu-direction|our guide]].'), 'See <a href="https://x.test/7-horses-painting-vastu-direction/">our guide</a>.');
same('a draft post stays plain text', af_cat_guide_text('[[post:old-draft|draft]]'), 'draft');
same('a missing post stays plain text', af_cat_guide_text('[[post:nope|gone]]'), 'gone');
same('category links still work', af_cat_guide_text('[[cat:diy-floating-frames|frames]]'), '<a href="https://x.test/c/diy-floating-frames/">frames</a>');
same('a missing category stays plain text', af_cat_guide_text('[[cat:nope|x]]'), 'x');
same('contact links still work', af_cat_guide_text('[[contact|Contact us]]'), '<a href="https://x.test/contact/">Contact us</a>');
same('plain mode (FAQ data) drops every link', af_cat_guide_text('A [[post:7-horses-painting-vastu-direction|guide]], [[cat:vaastu-art|art]] and [[contact|us]]', true), 'A guide, art and us');

echo "\n=== the guides ===\n";
$all = af_cat_guides();
foreach (array('seven-horses', 'indian-culture') as $slug) {
    same("$slug has a guide", isset($all[$slug]), true);
    $g = $all[$slug];
    $text = $g['heading'] . ' ' . $g['title'] . ' ' . implode(' ', $g['intro']);
    foreach ($g['faq'] as $f) $text .= ' ' . $f[0] . ' ' . $f[1];
    same("$slug never says the word free (owner's rule)", preg_match('~\bfree\b~i', $text), 0);
    same("$slug title fits in Google's result (≤ 70 characters)", mb_strlen($g['title']) <= 70, true);
    same("$slug has an FAQ", count($g['faq']) >= 4, true);
}
same('seven horses: the heading has the searched words', stripos($all['seven-horses']['heading'], 'Seven Horses Painting') !== false && stripos($all['seven-horses']['heading'], '7 Horses Wall Art') !== false, true);
same('seven horses: links the Vastu article', strpos(json_encode($all['seven-horses']), 'post:7-horses-painting-vastu-direction') !== false, true);
same('indian culture: the heading has "Indian Canvas Art"', stripos($all['indian-culture']['heading'], 'Indian Canvas Art') !== false, true);
same('indian culture: no Tanjore or Kathak (the shop has none)', preg_match('~tanjore|kathak~i', json_encode($all['indian-culture'])), 0);

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
