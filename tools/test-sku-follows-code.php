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
    array(__DIR__ . '/sku-to-artcode.php', 'af_sku_plan_group'),
    array(__DIR__ . '/../inc/sku.php', 'af_sku_code_part'),
    array(__DIR__ . '/../inc/sku.php', 'af_sku_letter_seq'),
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

echo "\n=== a shared code: the brochure's product carries the code itself (owner, 25 Sep) ===\n";
function plan($label, $pids, $base, $issued, $primary, $want, $reserved = array()) {
    global $pass, $fail;
    $got = af_sku_plan_group($pids, $base, $issued, $primary, $reserved);
    ksort($got); ksort($want);
    $skus = array(); foreach ($got as $pid => $l) { $skus[] = $base . $l; }
    $unique = count(array_unique($skus)) === count($skus);
    if ($got === $want && $unique) { $pass++; printf("  OK    %s\n", $label); }
    else { $fail++; printf("  FAIL  %s\n        got  %s%s\n        want %s\n", $label, json_encode($got), $unique ? '' : '  (NOT UNIQUE)', json_encode($want)); }
}
$B = 'RK-010033-3050';
plan('p37: #14617 (the brochure\'s) drops its A, #31829 keeps B',
     array(14617, 31829), $B, array(14617 => array('A', $B), 31829 => array('B', $B)), 14617,
     array(14617 => '', 31829 => 'B'));
plan('p150: the other one keeps C, not moved to B',
     array(15730, 31890), 'TP-050013-5030', array(15730 => array('A', 'TP-050013-5030'), 31890 => array('C', 'TP-050013-5030')), 15730,
     array(15730 => '', 31890 => 'C'));
plan('p167: #8398 is the brochure\'s; #26875 keeps the B it was given today',
     array(26875, 8398), 'HD-080001-5030', array(8398 => array('A', 'HD-080001-5030'), 26875 => array('B', 'HD-080001-5030')), 8398,
     array(8398 => '', 26875 => 'B'));
plan('a third product later gets C: the retired A is never handed on',
     array(220, 7839, 40000), 'LB-090001-3050', array(220 => array('A', 'LB-090001-3050'), 7839 => array('B', 'LB-090001-3050')), 220,
     array(220 => '', 7839 => 'B', 40000 => 'C'));
plan('on a later deploy the A is gone from #220, but retired: a new third product still gets C',
     array(220, 7839, 40000), 'LB-090001-3050', array(7839 => array('B', 'LB-090001-3050')), 220,
     array(220 => '', 7839 => 'B', 40000 => 'C'), array('A'));
plan('no brochure product named: every product keeps its letter, as before',
     array(14617, 31829), $B, array(14617 => array('A', $B), 31829 => array('B', $B)), 0,
     array(14617 => 'A', 31829 => 'B'));
plan('a named product that no longer carries the code is ignored',
     array(14617, 31829), $B, array(14617 => array('A', $B), 31829 => array('B', $B)), 99999,
     array(14617 => 'A', 31829 => 'B'));
plan('new pairs with no letters yet: the brochure\'s plain, the other A',
     array(500, 600), $B, array(), 600,
     array(500 => 'A', 600 => ''));
plan('a letter issued under another code is not honoured here',
     array(500, 600), $B, array(500 => array('D', 'RK-010034-3050')), 600,
     array(500 => 'A', 600 => ''));
plan('ids as strings, as WordPress can hand them over',
     array('14617', '31829'), $B, array(14617 => array('A', $B), 31829 => array('B', $B)), '14617',
     array(14617 => '', 31829 => 'B'));

printf("\n%d passed, %d failed\n", $pass, $fail);
exit($fail ? 1 : 0);
