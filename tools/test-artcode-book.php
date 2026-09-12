<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Tests inc/artcode-book.php — the section map, and the two properties that
 * make moving the whole catalogue onto the book's new numbering safe.
 *
 * WHAT IS BEING DONE. The brochure prints six digits now ("LB - 090001"); the
 * catalogue holds four ("LB - 0901"). The owner asked for every product to
 * carry what the book prints, so af_artcode_book_label() writes six and
 * renumber-artcodes.php — which runs with AF_APPLY=1 on every deploy, with
 * sku-to-artcode.php stamping the result onto the SKU straight after — carries
 * the catalogue over on the next push. There is no dry run in front of it.
 *
 * SO THE TEST IS NOT "does it map correctly". It is the two things that must
 * hold while several hundred live SKUs are rewritten:
 *
 *   1. THE PAGE DOES NOT MOVE. Every code that resolved before still resolves
 *      to the same section and the same page number — only the padding widens.
 *      This is a reformat, not a renumbering, and a difference here is a
 *      product being moved onto a different painting.
 *
 *   2. NOTHING GAINS A CODE. The book grew by 33 pages this round and the
 *      owner has said the new ones went in a mix of places, so which page a
 *      number names is not provable from the number. Every code the book
 *      refused before must still be refused: no product may land on one of
 *      those 33 pages by arithmetic.
 *
 * Both are checked by replaying every code the catalogue could hold, in every
 * shape it could hold it, against the behaviour recorded before the change.
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

echo "\n=== GUARANTEE 1: the page does not move ===\n";
// Replay the translation EXACTLY as it behaved before this change — four-digit
// output, bounded by 'legacy' — and require the new answer to name the same
// section and the same page. Only the padding may differ. Anything else is a
// product being moved onto a different painting.
function book_code_before_the_reformat($code) {
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

/** ('LB', 9, 1, '-GF') from either width, so the two can be compared. */
function code_parts($code) {
    if ($code === '') return null;
    if (!preg_match('/^([A-Z]{2}) - (\d{2})(\d{2,4})(.*)$/', $code, $m)) return 'UNPARSEABLE: ' . $code;
    return array($m[1], (int) $m[2], (int) $m[3], $m[4]);
}

$moved = array(); $gained = array(); $lost = array(); $tried = 0;
foreach ($book as $pre => $sec) {
    // every shape the catalogue could hold, past the end of both numberings,
    // plus the -GF suffix the Gold Foil importer adds and a lower-case spelling
    for ($n = 1; $n <= $sec['count'] + 5; $n++) {
        $forms = array(
            sprintf('%s %02d', $pre, $n),                          // the oldest labels
            sprintf('%s - %02d%02d', $pre, $sec['no'], $n),        // what the shop holds
            sprintf('%s - %02d%04d', $pre, $sec['no'], $n),        // what it is moving to
            sprintf('%s %02d-GF', $pre, $n),
            sprintf('%s - %02d%02d-GF', $pre, $sec['no'], $n),
            strtolower(sprintf('%s-%02d', $pre, $n)),
        );
        foreach ($forms as $f) {
            $tried++;
            $was = book_code_before_the_reformat($f);
            $now = af_artcode_book_code($f);

            // A six-digit input had no meaning before the reformat, so the old
            // path refusing it proves nothing. Judge it against the four-digit
            // spelling of the same page instead.
            if ($was === '' && strlen(af_artcode_split($f)['digits']) === 6) {
                $was = book_code_before_the_reformat(
                    sprintf('%s - %02d%02d%s', $pre, $sec['no'], $n, (strpos($f, '-GF') !== false ? '-GF' : '')));
            }

            if ($was === '' && $now !== '') { $gained[] = "$f -> $now"; continue; }
            if ($was !== '' && $now === '') { $lost[]   = "$f (was $was)";  continue; }
            if ($was === '' && $now === '') continue;
            if (code_parts($was) !== code_parts($now)) $moved[] = "$f: $was -> $now";
        }
    }
}
printf("  %d codes replayed across all 21 sections\n", $tried);
check('not one names a different page than it did', $moved, array());
check('not one stopped resolving',                  $lost,  array());

echo "\n=== GUARANTEE 2: nothing gains a code ===\n";
check('no code the book refused now resolves', $gained, array());

echo "\n=== and it really is six digits now ===\n";
$widened = 0;
foreach ($book as $pre => $sec) {
    for ($n = 1; $n <= $sec['legacy']; $n++) {
        $out = af_artcode_book_code(sprintf('%s - %02d%02d', $pre, $sec['no'], $n));
        if (preg_match('/^[A-Z]{2} - \d{6}$/', $out)) $widened++;
    }
}
check('every page the shop can hold widens to six digits', $widened, 340);

echo "\n=== the refusals that must stay refused ===\n";
check('TP 04 never had a page',  af_artcode_book_code('TP 04'), '');
check('HD 14 never had a page',  af_artcode_book_code('HD 14'), '');
check('AL is not a section',     af_artcode_book_code('AL 05'), '');
check('LR 32 is past the end',   af_artcode_book_code('LR 32'), '');
check('a page the book only gained now is still refused',
      af_artcode_book_code('LI 48'), '');
check('nor by its six-digit spelling',
      af_artcode_book_code('LI - 190048'), '');
check('not a code at all',       af_artcode_book_code('hello'), '');

echo "\n=== the gap arithmetic still shifts what follows it ===\n";
check('TP 05 -> TP - 050004', af_artcode_book_code('TP 05'), 'TP - 050004');
check('TP 16 -> TP - 050015', af_artcode_book_code('TP 16'), 'TP - 050015');
check('HD 15 -> HD - 080014', af_artcode_book_code('HD 15'), 'HD - 080014');
check('HD 28 -> HD - 080027', af_artcode_book_code('HD 28'), 'HD - 080027');
check('LB 01 -> LB - 090001', af_artcode_book_code('LB 01'), 'LB - 090001');

echo "\n=== the reformat itself ===\n";
check('LB - 0901 widens to LB - 090001', af_artcode_book_code('LB - 0901'), 'LB - 090001');
check('LI - 1932 widens to LI - 190032', af_artcode_book_code('LI - 1932'), 'LI - 190032');
check('HD - 0814 widens to HD - 080014', af_artcode_book_code('HD - 0814'), 'HD - 080014');
check('the Gold Foil suffix survives the widening',
      af_artcode_book_code('HD - 0814-GF'), 'HD - 080014-GF');
check('and survives from the oldest labels too',
      af_artcode_book_code('HD 15-GF'), 'HD - 080014-GF');
check('six digits map to themselves, so a second deploy does nothing',
      af_artcode_book_code('LB - 090001'), 'LB - 090001');
check('and again with a suffix',
      af_artcode_book_code('HD - 080014-GF'), 'HD - 080014-GF');

echo "\n=== the book's own page labels ===\n";
check('LB page 1',   af_artcode_page_label('LB', 1),  'LB - 090001');
check('LI page 51',  af_artcode_page_label('LI', 51), 'LI - 190051');
check('RK page 97',  af_artcode_page_label('RK', 97), 'RK - 010097');
check('past the end of the section', af_artcode_page_label('LI', 52), '');
check('page zero',   af_artcode_page_label('LI', 0),  '');
check('unknown prefix', af_artcode_page_label('ZZ', 1), '');
// It reads the book, so it reaches the 33 new pages the writing path will not.
check('it reaches a page the writing path refuses',
      af_artcode_page_label('LI', 48), 'LI - 190048');

printf("\n%d passed, %d failed\n", $pass, $fail);
exit($fail ? 1 : 0);
