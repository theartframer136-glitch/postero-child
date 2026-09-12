<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Tests inc/artcode-book.php — the section map, and the one property that
 * makes updating it safe to deploy.
 *
 * THE PROPERTY. tools/renumber-artcodes.php runs with AF_APPLY=1 on every
 * deploy, and sku-to-artcode.php stamps its result onto the SKU immediately
 * after. So a change to this map does not wait to be asked: it reaches live
 * SKUs on the next push. The map was widened on 2026-09-12 to today's book
 * (340 pages -> 373) while the catalogue still holds codes written against the
 * old one, and the owner has said the new pages were added in a mix of places
 * — so which page a number names is no longer provable from the number.
 *
 * Therefore the test is not "does it map correctly". It is: DOES ANY CODE THE
 * SHOP HOLDS COME OUT DIFFERENTLY THAN IT DID BEFORE. The answer must be no,
 * for every code in every section, or the widened map is rewriting paintings.
 *
 *     php tools/test-artcode-book.php
 */
define('ABSPATH', '/nowhere/');
require __DIR__ . '/../inc/artcode-book.php';

$pass = 0; $fail = 0;
function check($label, $got, $want) {
    global $pass, $fail;
    if ($got === $want) { $pass++; printf("  OK    %s\n", $label); }
    else { $fail++; printf("  FAIL  %s\n        got  %s\n        want %s\n",
                           $label, var_export($got, true), var_export($want, true)); }
}

$book = af_artcode_book();

echo "=== the map is today's book ===\n";
check('21 sections', count($book), 21);
check('373 product pages', af_artcode_book_pages(), 373);
check('Living Room has 51', af_artcode_book_pages('LI'), 51);
check('Radha Krishna has 97', af_artcode_book_pages('RK'), 97);
check('an unknown prefix has none', af_artcode_book_pages('ZZ'), 0);

// Page N of the book sits at Canva page 4 + N, and the sections follow one
// another with nothing in between. Both were measured against the design.
$expected_first = 5;
$ok_pages = true; $ok_order = true;
foreach ($book as $pre => $sec) {
    if ($sec['pages'][0] !== $expected_first) { $ok_order = false; }
    if ($sec['pages'][1] - $sec['pages'][0] + 1 !== $sec['count']) { $ok_pages = false; }
    $expected_first = $sec['pages'][1] + 1;
}
check('each section spans exactly its own page count', $ok_pages, true);
check('the sections run back to back, 5 through 377', $ok_order, true);
check('the last page is 377', $expected_first - 1, 377);

echo "\n=== section numbers are 1..21 in printed order ===\n";
check('numbered in order', array_values(array_map(
    function ($s) { return $s['no']; }, $book)), range(1, 21));

echo "\n=== THE GUARANTEE: the wider map moves nothing the shop holds ===\n";
// Replay the translation with the OLD map — count == legacy, which is what
// the file held before it was widened — and require identical answers. Any
// difference is a product whose code the next deploy would rewrite.
function book_code_under_old_map($code) {
    $parts = af_artcode_split($code);
    if (!$parts) return '';
    $sec = af_artcode_section($parts['prefix']);
    if (!$sec) return '';
    $digits = $parts['digits'];
    if (strlen($digits) === 4) {
        $s = (int) substr($digits, 0, 2);
        $n = (int) substr($digits, 2, 2);
        if ($s === $sec['no'] && $n >= 1 && $n <= $sec['legacy']) {
            return sprintf('%s - %02d%02d', $parts['prefix'], $sec['no'], $n) . $parts['suffix'];
        }
    }
    $label  = (int) $digits;
    $absent = $sec['absent'];
    $max    = $sec['legacy'] + count($absent);
    if ($label < 1 || $label > $max) return '';
    if (in_array($label, $absent, true)) return '';
    $shift = 0;
    foreach ($absent as $gap) { if ($gap < $label) $shift++; }
    return sprintf('%s - %02d%02d', $parts['prefix'], $sec['no'], $label - $shift) . $parts['suffix'];
}

$differs = array();
$tried   = 0;
foreach ($book as $pre => $sec) {
    // every old-style label, every new-style one, past the end of both, and
    // the Gold Foil suffix the importer creates
    for ($n = 1; $n <= $sec['count'] + 5; $n++) {
        $forms = array(
            sprintf('%s %02d', $pre, $n),
            sprintf('%s - %02d%02d', $pre, $sec['no'], $n),
            sprintf('%s %02d-GF', $pre, $n),
            strtolower(sprintf('%s-%02d', $pre, $n)),
        );
        foreach ($forms as $f) {
            $tried++;
            if (af_artcode_book_code($f) !== book_code_under_old_map($f)) $differs[] = $f;
        }
    }
}
printf("  %d codes replayed across all 21 sections\n", $tried);
check('not one of them comes out differently', $differs, array());

echo "\n=== the refusals that must stay refused ===\n";
check('TP 04 never had a page',  af_artcode_book_code('TP 04'), '');
check('HD 14 never had a page',  af_artcode_book_code('HD 14'), '');
check('AL is not a section',     af_artcode_book_code('AL 05'), '');
check('LR 32 is past the end',   af_artcode_book_code('LR 32'), '');
check('a page the book only gained now is still refused',
      af_artcode_book_code('LI 48'), '');
check('not a code at all',       af_artcode_book_code('hello'), '');

echo "\n=== the gap arithmetic still shifts what follows it ===\n";
check('TP 05 -> TP - 0504', af_artcode_book_code('TP 05'), 'TP - 0504');
check('TP 16 -> TP - 0515', af_artcode_book_code('TP 16'), 'TP - 0515');
check('HD 15 -> HD - 0814', af_artcode_book_code('HD 15'), 'HD - 0814');
check('HD 28 -> HD - 0827', af_artcode_book_code('HD 28'), 'HD - 0827');
check('LB 01 -> LB - 0901', af_artcode_book_code('LB 01'), 'LB - 0901');
check('the Gold Foil suffix survives', af_artcode_book_code('HD 15-GF'), 'HD - 0814-GF');
check('a code already in shape maps to itself',
      af_artcode_book_code('LB - 0901'), 'LB - 0901');

echo "\n=== today's six-digit label, for reading and matching only ===\n";
check('LB page 1',   af_artcode_page_label('LB', 1),  'LB - 090001');
check('LI page 51',  af_artcode_page_label('LI', 51), 'LI - 190051');
check('RK page 97',  af_artcode_page_label('RK', 97), 'RK - 010097');
check('past the end of the section', af_artcode_page_label('LI', 52), '');
check('page zero',   af_artcode_page_label('LI', 0),  '');
check('unknown prefix', af_artcode_page_label('ZZ', 1), '');

// The point of keeping it separate: it must not be what gets written.
check('the writing path still returns four digits',
      af_artcode_book_code('LB 01'), 'LB - 0901');

printf("\n%d passed, %d failed\n", $pass, $fail);
exit($fail ? 1 : 0);
