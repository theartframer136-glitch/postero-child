<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Tests af_sku_code_part() — the art code turned into the SKU a customer sees
 * on an order line.
 *
 * Written because the catalogue is moving onto the book's six-digit numbering
 * and this function matched exactly four digits. Six-digit codes still came
 * out right, but by accident: "090001" matched as "0900" with "01" falling
 * into the TRAILING group and getting concatenated back on. That group is the
 * one carrying the "-GF" the Gold Foil importer adds, and it is trimmed and
 * upper-cased on the way through — so the accident would have held only until
 * someone did anything to it.
 *
 * Runs the real functions, lifted out by name so they cannot drift from what
 * ships:
 *
 *     php tools/test-sku-code-part.php
 */
$pass = 0; $fail = 0;
function check($label, $got, $want) {
    global $pass, $fail;
    if ($got === $want) { $pass++; printf("  OK    %s\n", $label); }
    else { $fail++; printf("  FAIL  %s\n        got  %s\n        want %s\n",
                           $label, var_export($got, true), var_export($want, true)); }
}

// inc/sku.php is a WordPress module; take the one pure function out of it.
$src = file_get_contents(__DIR__ . '/../inc/sku.php');
if (!preg_match('/\nfunction af_sku_code_part\( \$code \) \{.*?\n\}\n/s', $src, $m)) {
    fwrite(STDERR, "could not find af_sku_code_part() in inc/sku.php\n");
    exit(2);
}
eval($m[0]);

// The copy in the tool, used only if the theme is not loaded. It must agree.
$tsrc = file_get_contents(__DIR__ . '/sku-to-artcode.php');
if (!preg_match('/\n\tif \( preg_match.*?\n\treturn strtoupper\( str_replace\( \' \', \'-\', \$s \) \);/s', $tsrc, $tm)) {
    fwrite(STDERR, "could not find the fallback body in tools/sku-to-artcode.php\n");
    exit(2);
}
eval('function af_sku_fallback($code) { $s = preg_replace("/\s+/", " ", trim((string) $code)); if ($s === "") { return ""; }' . $tm[0] . ' }');

echo "=== the numbering the catalogue is moving to ===\n";
check('LB - 090001',    af_sku_code_part('LB - 090001'),    'LB-090001');
check('LI - 190051',    af_sku_code_part('LI - 190051'),    'LI-190051');
check('RK - 010097',    af_sku_code_part('RK - 010097'),    'RK-010097');
check('with -GF',       af_sku_code_part('HD - 080014-GF'), 'HD-080014-GF');

echo "\n=== the numbering it is moving from, still readable ===\n";
check('LB - 0901',      af_sku_code_part('LB - 0901'),      'LB-0901');
check('HD - 0814-GF',   af_sku_code_part('HD - 0814-GF'),   'HD-0814-GF');

echo "\n=== and the oldest labels, which a few products still hold ===\n";
check('LB 01',          af_sku_code_part('LB 01'),          'LB-01');
check('LB 1 pads',      af_sku_code_part('LB 1'),           'LB-01');
check('leading zeros',  af_sku_code_part('LB 0001'),        'LB-01');

echo "\n=== THE POINT: six digits are read as one number, not four plus a tail ===\n";
// If the trailing group is doing the work, these break. Each takes a six-digit
// code and puts something in the tail that the tail-handling would mangle.
check('a space before -GF is not lost',
      af_sku_code_part('LB - 090001 -GF'), 'LB-090001-GF');
check('a lower-case suffix is upper-cased, not swallowed',
      af_sku_code_part('LB - 090001-gf'), 'LB-090001-GF');
check('the digits themselves survive a suffix that starts with one',
      af_sku_code_part('LB - 090001-2'), 'LB-090001-2');

echo "\n=== the two copies of this rule agree ===\n";
foreach (array('LB - 090001', 'LB - 0901', 'HD - 080014-GF', 'LB 01', 'LB 1') as $c) {
    check('both spell ' . $c . ' the same', af_sku_fallback($c), af_sku_code_part($c));
}

echo "\n=== nothing sensible turns into nonsense ===\n";
check('empty stays empty', af_sku_code_part(''), '');
check('whitespace only',   af_sku_code_part('   '), '');
check('no double hyphens anywhere', (bool) preg_match('/--/',
      af_sku_code_part('LB - 090001')), false);

printf("\n%d passed, %d failed\n", $pass, $fail);
exit($fail ? 1 : 0);
