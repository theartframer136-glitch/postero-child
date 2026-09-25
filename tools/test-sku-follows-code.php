<?php
/**
 * Tests af_sku_belongs_to_code() — whether tools/sku-to-artcode.php leaves a
 * product's SKU alone or re-issues it from the product's current art code.
 *
 * The SKU pass marked a product done once and skipped it after that. Products
 * whose temporary code was re-issued later kept the SKU of the earlier number:
 * #30093 shows art code TMP-1290 (on its page, and in the SKU its order lines
 * carry) with product SKU TMP-1143. Done now means done for this code.
 *
 * Runs the REAL functions, lifted out of the tool and inc/sku.php rather than
 * copied. Plain `php` runs it; no WordPress needed.
 *
 *     php tools/test-sku-follows-code.php
 */
foreach (array(
    array(__DIR__ . '/sku-to-artcode.php', 'af_sku_belongs_to_code'),
    array(__DIR__ . '/../inc/sku.php', 'af_sku_code_part'),
) as $want) {
    list($file, $fn) = $want;
    if (!preg_match('/\nfunction ' . $fn . '\(.*?\n\}\n/s', file_get_contents($file), $m)) {
        fwrite(STDERR, "could not find {$fn}() in {$file}\n");
        exit(2);
    }
    eval($m[0]);
}

$pass = 0; $fail = 0;
function belongs($sku, $code, $want, $note) {
    global $pass, $fail;
    $base = af_sku_code_part($code);
    $got  = af_sku_belongs_to_code($sku, $base);
    if ($got === $want) { $pass++; printf("  OK    %-5s %-18s %-20s %s\n", $want ? 'keep' : 'MOVE', $sku === '' ? '(none)' : $sku, '[' . $code . ']', $note); }
    else { $fail++; printf("  FAIL  want %-5s got %-5s  sku %s  code %s (%s)  %s\n", $want ? 'keep' : 'move', $got ? 'keep' : 'move', $sku, $code, $base, $note); }
}

echo "=== the products this fixes ===\n";
belongs('TMP-1143', 'TMP-1290', false, '#30093: SKU from an earlier temporary number is re-issued');
belongs('TMP-1136', 'TMP-1284', false, '#29456, the same');
belongs('TMP-1290', 'TMP-1290', true, 'and once it is TMP-1290 it stays');

echo "\n=== SKUs that must not move ===\n";
belongs('RK-010033-3050A', 'RK - 010033-3050', true, 'a twin keeps its letter');
belongs('RK-010033-3050B', 'RK - 010033-3050', true, 'and so does the other twin');
belongs('TP-050013-5030C', 'TP - 050013-5030', true, 'a C twin as well');
belongs('HD-080001-5030', 'HD - 080001-5030', true, '#26875: a twin whose SKU is the plain code is left alone');
belongs('RK-010074-5030', 'RK - 010074-5030', true, 'a brochure code stored with spaces');
belongs('AL-01', 'AL 01', true, 'an AL code');
belongs('HD-15-GF', 'HD 15-GF', true, 'a Gold Foil code');
belongs('tmp-1290', 'TMP-1290', true, 'case does not matter (MySQL compares SKUs without it)');

echo "\n=== SKUs that name some other code ===\n";
belongs('TMP-1290', 'TMP-129', false, 'more digits is a different number, not a letter');
belongs('RK-010033-3050', 'RK - 010033-30', false, 'a longer code is not this one');
belongs('HD-15', 'HD 15-GF', false, 'the plain code where the Gold Foil one is wanted');
belongs('HD-15-GF', 'HD 15', false, 'and the other way round');
belongs('RK-01ABC', 'RK 01', false, 'three letters is not a twin letter');
belongs('LB-090001-3050A', 'LB - 090002-3050', false, 'a code corrected to another page');
belongs('', 'TMP-1290', false, 'a product marked done with no SKU at all gets one');
belongs('TAF-CRUCIFIXION-SCENE-053', 'LI - 190008-5030', false, 'a machine-made SKU left from before the art codes');

printf("\n%d passed, %d failed\n", $pass, $fail);
exit($fail ? 1 : 0);
