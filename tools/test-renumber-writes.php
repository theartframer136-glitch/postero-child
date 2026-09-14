<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Tests what tools/renumber-artcodes.php will actually WRITE onto a product.
 *
 * That pass runs with AF_APPLY=1 on every deploy and sku-to-artcode.php stamps
 * the result onto the SKU straight after, so the one line deciding what it
 * writes reaches live products and invoices without a dry run in front of it.
 * It now writes the whole code the page prints, aspect included:
 *
 *     LB - 0901  ->  LB - 090001-3050
 *
 * The properties that must hold while several hundred SKUs are rewritten:
 *
 *   the page does not move    section and page number survive; only the shape
 *                             widens and the aspect is appended
 *   it settles                a second deploy writes nothing, so the code does
 *                             not grow a tail on every push
 *   nothing gains a code      a refusal stays a refusal
 *   the SKU follows           what the customer sees is the same string
 *
 *     php tools/test-renumber-writes.php
 */
define('ABSPATH', '/nowhere/');
require __DIR__ . '/../inc/artcode-book.php';

// The one line from renumber-artcodes.php that decides what is written.
$src = file_get_contents(__DIR__ . '/renumber-artcodes.php');
if (strpos($src, '$new = af_artcode_full_code( $code );') === false) {
    fwrite(STDERR, "renumber-artcodes.php no longer writes af_artcode_full_code() — this test is stale\n");
    exit(2);
}
function what_the_deploy_writes($code) { return af_artcode_full_code($code); }

// And the SKU built from it.
$sku = file_get_contents(__DIR__ . '/../inc/sku.php');
preg_match('/\nfunction af_sku_code_part\( \$code \) \{.*?\n\}\n/s', $sku, $m) or exit(2);
eval($m[0]);

$pass = 0; $fail = 0;
function check($label, $got, $want) {
    global $pass, $fail;
    if ($got === $want) { $pass++; printf("  OK    %s\n", $label); }
    else { $fail++; printf("  FAIL  %s\n        got  %s\n        want %s\n",
                           $label, var_export($got, true), var_export($want, true)); }
}

echo "=== what a real product's code becomes ===\n";
// Left column is what the catalogue holds today, taken from the deploy's own
// RENUMBERED.txt of 2026-09-12. The RIGHT column's aspect is read from the
// book, not typed from memory — four of these were wrong when written by hand
// and the code was right each time.
$real = array(
    'LB - 090001' => 'LB - 090001-3050',   // #220, #7839
    'LI - 190002' => 'LI - 190002-3050',   // #141
    'RK - 010010' => 'RK - 010010-5030',   // #223
    'TP - 050004' => 'TP - 050004-5030',   // #229, after the old TP gap
    'HD - 080004' => 'HD - 080004-5030',   // #3362
    'SH - 040001' => 'SH - 040001-3050',   // #232
    'TA - 210003' => 'TA - 210003-3060',   // the one 3x6 page
);
foreach ($real as $held => $want) check($held, what_the_deploy_writes($held), $want);

echo "\n=== the older shapes a few products still hold ===\n";
check('four digits',  what_the_deploy_writes('LB - 0901'), 'LB - 090001-3050');
check('the oldest label', what_the_deploy_writes('LB 01'),  'LB - 090001-3050');
check('across the TP gap', what_the_deploy_writes('TP 05'), 'TP - 050004-5030');
check('the Gold Foil copy', what_the_deploy_writes('HD 15-GF'), 'HD - 080014-5030-GF');

echo "\n=== IT SETTLES: a second deploy must write nothing ===\n";
// The pass compares its answer to what the product holds and only writes when
// they differ. If this were not a fixed point, every deploy would rewrite every
// product forever and the code would grow.
$unsettled = array();
foreach (af_artcode_book() as $pre => $sec) {
    for ($n = 1; $n <= $sec['legacy']; $n++) {
        $first  = what_the_deploy_writes(sprintf('%s - %02d%02d', $pre, $sec['no'], $n));
        $second = what_the_deploy_writes($first);
        $third  = what_the_deploy_writes($second);
        if ($first !== $second || $second !== $third) $unsettled[] = "$first -> $second -> $third";
    }
}
check('all 340 codes are unchanged by a second and third pass', $unsettled, array());

echo "\n=== the page does not move ===\n";
$moved = array(); $n_done = 0;
foreach (af_artcode_book() as $pre => $sec) {
    for ($n = 1; $n <= $sec['legacy']; $n++) {
        $held = sprintf('%s - %02d%02d', $pre, $sec['no'], $n);
        $out  = what_the_deploy_writes($held);
        // the six digits in the middle must be this section and this page
        if (!preg_match('/^([A-Z]{2}) - (\d{2})(\d{4})-(\d{4})$/', $out, $m)) { $moved[] = "$held -> $out (shape)"; continue; }
        if ($m[1] !== $pre || (int) $m[2] !== $sec['no'] || (int) $m[3] !== $n) { $moved[] = "$held -> $out"; continue; }
        if ($m[4] !== af_artcode_page_size($pre, $n)) { $moved[] = "$held -> $out (wrong aspect)"; continue; }
        $n_done++;
    }
}
check('not one of the 340 names a different page', $moved, array());
check('and every one of them got a code', $n_done, 340);

echo "\n=== nothing gains a code ===\n";
foreach (array('TP 04', 'HD 14', 'AL 05', 'LR 32', 'LI 48', 'LI - 190048', 'hello', '') as $c) {
    check(($c === '' ? '(empty)' : $c) . ' is still refused', what_the_deploy_writes($c), '');
}

echo "\n=== the SKU the customer sees follows the code ===\n";
check('LB - 090001-3050',    af_sku_code_part('LB - 090001-3050'),    'LB-090001-3050');
check('TA - 210003-3060',    af_sku_code_part('TA - 210003-3060'),    'TA-210003-3060');
check('with the Gold Foil suffix',
      af_sku_code_part('HD - 080014-5030-GF'), 'HD-080014-5030-GF');
$ugly = 0;
foreach (af_artcode_book() as $pre => $sec) {
    for ($n = 1; $n <= $sec['legacy']; $n++) {
        $s = af_sku_code_part(what_the_deploy_writes(sprintf('%s - %02d%02d', $pre, $sec['no'], $n)));
        if (!preg_match('/^[A-Z]{2}-\d{6}-\d{4}$/', $s)) $ugly++;
    }
}
check('all 340 SKUs come out as PREFIX-SSNNNN-WWHH', $ugly, 0);

printf("\n%d passed, %d failed\n", $pass, $fail);
exit($fail ? 1 : 0);
