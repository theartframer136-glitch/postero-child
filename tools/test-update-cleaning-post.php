<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Tests tools/update-cleaning-post.php with WordPress stubbed:
 *     php tools/test-update-cleaning-post.php [article.html]
 * With a file argument the stored article (from tools/diag-post-text.php) is
 * used instead of the built-in copy of its FAQ markup.
 */
define('ABSPATH', '/nowhere/'); define('OBJECT', 'OBJECT');
function add_action() {} function add_filter() {}
function esc_html($s) { return htmlspecialchars($s, ENT_QUOTES); }
function wp_strip_all_tags($s) { return strip_tags($s); }
function get_page_by_path() { return null; }
ob_start();
require __DIR__ . '/update-cleaning-post.php';
require __DIR__ . '/../inc/post-faq-schema.php';
ob_end_clean();

// The article's FAQ markup as stored (Nov 2026), shortened to the parts the script touches.
$fixture = <<<'HTML'
<h3 data-section-id="1329ug4">Final Thoughts</h3>
<p>With proper care, your canvas wall art can remain a stunning centerpiece.</p>

<h3>1. How do you clean canvas wall art safely?</h3>
<p data-start="7314">The safest way is a <strong>dry microfiber cloth or soft brush</strong>.</p>

<hr data-start="7512" />

<h3 data-section-id="658yjr">7. What is the best cloth to clean canvas art?</h3>
<p>A <strong>soft microfiber cloth</strong> is the best option.</p>

<hr data-start="827" />

<h4 data-section-id="ehlwf" data-start="832" data-end="871">8. Can mold grow on canvas wall art?</h4>
<p data-start="872">Yes, mold can develop on canvas art if it is exposed to <strong>high humidity</strong>.</p>

<hr data-start="1073" />

<h4 data-section-id="7nf8vh" data-start="1078" data-end="1127">9. How do I protect canvas wall art from dust?</h4>
<p data-start="1128" data-end="1160">To protect canvas art from dust:</p>

<ul data-start="1162" data-end="1336">
 	<li><p>Clean it regularly with a microfiber cloth</p></li>
 	<li><p>Maintain proper room ventilation</p></li>
</ul>
<p data-start="1338" data-end="1393">These steps help keep artwork clean for longer periods.</p>

<hr data-start="1395" />

<h3 data-section-id="scot01">13. How do I prevent canvas artwork from fading?</h3>
<p data-start="2138" data-end="2156">To prevent fading:</p>

<ul data-start="2158" data-end="2289">
 	<li><p>Avoid direct sunlight</p></li>
</ul>
<p data-start="2291" data-end="2346">These steps help preserve the colors of canvas artwork.</p>

<hr data-start="2348" />

<h4>Store Artwork Properly</h4>
<ul><li><p>Wrap it in acid-free paper.</p></li></ul>

<h3 data-section-id="1n1fz32">15. Can canvas wall art be repaired if damaged?</h3>
<p data-start="2587">Yes, minor damage can often be repaired by <strong>professional art restorers</strong>.</p>
HTML;
$html = isset($argv[1]) ? file_get_contents($argv[1]) : $fixture;
$all  = isset($argv[1]);

$pass = 0; $fail = 0;
function same($label, $got, $want) { global $pass, $fail;
    if ($got === $want) { $pass++; echo "  OK    $label\n"; }
    else { $fail++; echo "  FAIL  $label\n        got  " . var_export($got, true) . "\n        want " . var_export($want, true) . "\n"; } }

$out   = af_clean_post_html($html);
$pairs = af_post_faq_pairs($out);
$qs    = array_column($pairs, 0);
$byQ   = array_combine($qs, array_column($pairs, 1));

echo "=== the FAQ section ===\n";
same('one FAQ heading is added', substr_count($out, '<h2>Frequently asked questions</h2>'), 1);
same('it sits right above question 1', strpos($out, "<h2>Frequently asked questions</h2>\n<h3>1. How do you clean") !== false, true);
same('the storage tip no longer says free', strpos($out, 'Wrap it in archival tissue paper.') !== false, true);
same('nothing else above the FAQ changes', substr($out, 0, strpos($out, '<h2>')), str_replace('acid-free paper', 'archival tissue paper', substr($html, 0, strpos($html, '<h3>1. How do you clean'))));
same('questions 8 and 9 are h3 now', preg_match('~<h4[^>]*>\s*[89]\.~', $out), 0);
same('their attributes are kept', strpos($out, '<h3 data-section-id="ehlwf" data-start="832" data-end="871">8. Can mold') !== false, true);
same('FAQ data reads every question (' . ($all ? '15' : '6') . ' old + 6 new)', count($pairs), $all ? 21 : 12);
same('answer 9 is a full sentence, not a lead-in', strpos($byQ['9. How do I protect canvas wall art from dust?'], 'Dust it with a dry microfiber cloth') === 0, true);
same('answer 13 is a full sentence, not a lead-in', strpos($byQ['13. How do I prevent canvas artwork from fading?'], 'Hang it out of direct sunlight') === 0, true);
same('their old lists are gone', strpos($out, 'To protect canvas art from dust:') === false && strpos($out, 'To prevent fading:') === false, true);
same('answer 15 is untouched', strpos(strip_tags($out), 'repaired by professional art restorers') !== false, true);

echo "\n=== the new questions ===\n";
foreach (array('16. Can you freeze a canvas?', '17. How do you renew or refresh an old canvas print?', '18. How do you protect a canvas print?',
               '19. Can a canvas painting be washed?', '20. How do you clean a framed canvas or a framed art print?',
               '21. How should I care for a used or second-hand canvas?') as $q)
    same("$q has an answer", isset($byQ[$q]) && strlen($byQ[$q]) > 80, true);
same('they come after question 15', strpos($out, '16. Can you freeze') > strpos($out, '15. Can canvas wall art be repaired'), true);
same('no text says the word free (owner\'s rule)', preg_match('~\bfree\b~i', strip_tags($out)), 0);
same('no prices are promised', preg_match('~\$\d~', $out), 0);

echo "\n=== running it again ===\n";
same('a second run changes nothing', af_clean_post_html($out), $out);

echo "\n=== search title and description ===\n";
same('search title fits Google (≤ 70 characters)', mb_strlen(AF_CLEAN_SEO_TITLE) <= 70, true);
same('description fits Google (≤ 160 characters)', mb_strlen(AF_CLEAN_SEO_DESC) <= 160, true);
same('title and description never say free', preg_match('~\bfree\b~i', AF_CLEAN_POST_TITLE . AF_CLEAN_SEO_TITLE . AF_CLEAN_SEO_DESC), 0);

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
