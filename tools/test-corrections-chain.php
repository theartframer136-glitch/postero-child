<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Can tools/apply-artcode-corrections.php move a RUN of products along by one
 * page — and does it still refuse the clashes that matter?
 *
 * This is not a hypothetical. The Radha Krishna audit found twenty-nine
 * products each sitting one page past the picture they actually show:
 *
 *     #20349 holds RK 29, is the painting on RK 28
 *     #15135 holds RK 28, is the painting on RK 27
 *     #13722 holds RK 27, ...
 *
 * Every one of those is a chain link — the page it must move to is held by the
 * next product in the file. The pass read the catalogue into an ownership map
 * once and never let a product out of its old code, so row one was "REFUSED —
 * RK 28 already belongs to #15135" and the whole section was unfixable. In
 * either file order. That is what this guards.
 *
 * What must hold:
 *
 *   a chain moves          a run of N products each shifting one page applies
 *                          in full, from the free end
 *   a real clash refuses   a code held by a product that is NOT in the file is
 *                          still protected
 *   one code, one product  two rows cannot both claim the same code
 *   the dry run tells      the truth: it refuses exactly what an apply refuses
 *   a cleared code frees   NONE hands the page to a later row
 *
 * The tool is a WP-CLI script, so the catalogue is faked: get_posts,
 * get_post_meta and the rest are defined here and the file is run against them.
 *
 *     php tools/test-corrections-chain.php
 */
$pass = 0; $fail = 0;
function check($label, $got, $want) {
    global $pass, $fail;
    if ($got === $want) { $pass++; printf("  OK    %s\n", $label); }
    else { $fail++; printf("  FAIL  %s\n        got  %s\n        want %s\n",
                           $label, var_export($got, true), var_export($want, true)); }
}

define('ABSPATH', '/nowhere/');
require __DIR__ . '/../inc/artcode-book.php';

// ── the fake catalogue ──────────────────────────────────────────────────────
$GLOBALS['DB'] = array();   // pid => array(code, title)
$GLOBALS['META'] = array(); // pid => key => value

function get_posts($a = array())      { return array_keys($GLOBALS['DB']); }
function get_post($pid)               { return isset($GLOBALS['DB'][$pid]) ? (object) array('post_type' => 'product') : null; }
function get_the_title($pid)          { return isset($GLOBALS['DB'][$pid]) ? $GLOBALS['DB'][$pid][1] : ''; }
function wp_strip_all_tags($s)        { return $s; }
function get_post_meta($pid, $k, $s = false) {
    if ($k === '_taf_art_code') { return isset($GLOBALS['DB'][$pid]) ? $GLOBALS['DB'][$pid][0] : ''; }
    return isset($GLOBALS['META'][$pid][$k]) ? $GLOBALS['META'][$pid][$k] : '';
}
function update_post_meta($pid, $k, $v) {
    if ($k === '_taf_art_code') { $GLOBALS['DB'][$pid][0] = $v; return true; }
    $GLOBALS['META'][$pid][$k] = $v; return true;
}
function delete_post_meta($pid, $k) {
    if ($k === '_taf_art_code') { $GLOBALS['DB'][$pid][0] = ''; return true; }
    unset($GLOBALS['META'][$pid][$k]); return true;
}
function wc_delete_product_transients($pid) {}

/** Run the real tool over a fake catalogue and a fake corrections file. */
function run_pass($catalogue, $csv_rows, $apply = true) {
    $GLOBALS['DB'] = array();
    $GLOBALS['META'] = array();
    foreach ($catalogue as $pid => $c) { $GLOBALS['DB'][$pid] = array($c, "product $pid"); }

    $tmp = sys_get_temp_dir() . '/af-corr-test-' . getmypid() . '.csv';
    $fh = fopen($tmp, 'w');
    fputcsv($fh, array('product_id', 'new_art_code', 'current_art_code', 'title', 'why'));
    foreach ($csv_rows as $r) { fputcsv($fh, array($r[0], $r[1], '', '', 'test')); }
    fclose($fh);

    putenv('AF_APPLY=' . ($apply ? '1' : '0'));
    putenv('AF_CORRECTIONS=' . $tmp);

    ob_start();
    // The tool is written to be included once; run it in a fresh process image
    // by eval'ing its body with the guard line and the ABSPATH check removed.
    $src = file_get_contents(__DIR__ . '/apply-artcode-corrections.php');
    $src = preg_replace('/^<\?php\s*/', '', $src, 1);
    $src = preg_replace('#^/\* AF-WEB-GUARD \*/.*$#m', '', $src, 1);
    $src = str_replace("if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, \"Run via wp eval-file\\n\" ); exit(1); }", '', $src);
    // The tool declares af_corr_book_code(). It is run several times here, so
    // the declaration is taken out after the first — the point is to exercise
    // the SHIPPED body, and that body is what stays.
    foreach (array('af_corr_book_code', 'af_corr_page_key') as $fn) {
        if (function_exists($fn)) {
            $src = preg_replace('/\nfunction ' . $fn . '\( \$code \) \{.*?\n\}\n/s', "\n", $src, 1);
        }
    }
    eval($src);
    $out = ob_get_clean();
    unlink($tmp);
    $codes = array();
    foreach ($GLOBALS['DB'] as $pid => $c) { $codes[$pid] = $c[0]; }
    return array($out, $codes);
}

// Where the tool reads its file from.
$toolsrc = file_get_contents(__DIR__ . '/apply-artcode-corrections.php');
if (strpos($toolsrc, 'AF_CORRECTIONS') === false) {
    fwrite(STDERR, "apply-artcode-corrections.php does not honour AF_CORRECTIONS — this test cannot point it at a fixture\n");
    exit(2);
}

echo "=== A CHAIN MOVES: five products each one page past their picture ===\n";
// Each holds the page above the one it belongs on. Written from the free end.
$cat = array(
    101 => 'RK 25', 102 => 'RK 26', 103 => 'RK 27', 104 => 'RK 28', 105 => 'RK 29',
    900 => 'RK 40',   // not in the file, and not in the way
);
$rows = array(
    array(101, 'RK 24'), array(102, 'RK 25'), array(103, 'RK 26'),
    array(104, 'RK 27'), array(105, 'RK 28'),
);
list($out, $codes) = run_pass($cat, $rows);
check('#101 RK 25 -> RK 24', $codes[101], af_artcode_book_code('RK 24'));
check('#102 RK 26 -> RK 25', $codes[102], af_artcode_book_code('RK 25'));
check('#103 RK 27 -> RK 26', $codes[103], af_artcode_book_code('RK 26'));
check('#104 RK 28 -> RK 27', $codes[104], af_artcode_book_code('RK 27'));
check('#105 RK 29 -> RK 28', $codes[105], af_artcode_book_code('RK 28'));
check('nothing was refused', (bool) preg_match('/REFUSED/', $out), false);
check('the product not in the file is untouched', $codes[900], 'RK 40');
check('RK 29 is left empty, as it should be', in_array(af_artcode_book_code('RK 29'), $codes, true), false);

echo "\n=== A REAL CLASH IS STILL REFUSED ===\n";
// #201 is NOT in the file. Nothing may be given its code.
$cat = array(201 => 'RK 30', 202 => 'RK 31');
list($out, $codes) = run_pass($cat, array(array(202, 'RK 30')));
check('refused, because #201 is not moving', (bool) preg_match('/REFUSED/', $out), true);
check('#202 keeps what it had', $codes[202], 'RK 31');
check('#201 keeps what it had', $codes[201], 'RK 30');

echo "\n=== TWO ROWS CANNOT CLAIM ONE CODE ===\n";
$cat = array(301 => 'RK 40', 302 => 'RK 41');
list($out, $codes) = run_pass($cat, array(array(301, 'RK 50'), array(302, 'RK 50')));
check('the second is refused', substr_count($out, 'REFUSED'), 1);
check('the first got it', $codes[301], af_artcode_book_code('RK 50'));
check('the second kept its own', $codes[302], 'RK 41');

echo "\n=== THE DRY RUN REFUSES EXACTLY WHAT AN APPLY REFUSES ===\n";
$cat  = array(401 => 'RK 60', 402 => 'RK 61', 403 => 'RK 62');
$rows = array(array(402, 'RK 60'), array(403, 'RK 70'), array(401, 'RK 70'));
list($dry, $dcodes)  = run_pass($cat, $rows, false);
list($wet, $wcodes)  = run_pass($cat, $rows, true);
check('same number refused', substr_count($dry, 'REFUSED'), substr_count($wet, 'REFUSED'));
check('a dry run writes nothing', $dcodes, array(401 => 'RK 60', 402 => 'RK 61', 403 => 'RK 62'));

echo "\n=== A CLEARED CODE IS FREED FOR A LATER ROW ===\n";
$cat = array(501 => 'RK 80', 502 => 'RK 81');
list($out, $codes) = run_pass($cat, array(array(501, 'NONE'), array(502, 'RK 80')));
check('#501 cleared', $codes[501], '');
check('#502 took the freed page', $codes[502], af_artcode_book_code('RK 80'));
check('nothing refused', (bool) preg_match('/REFUSED/', $out), false);

echo "\n=== SHARE: still lets two products hold one code ===\n";
$cat = array(601 => 'RK 90', 602 => 'RK 91');
list($out, $codes) = run_pass($cat, array(array(602, 'SHARE: RK 90')));
check('#602 shares', $codes[602], af_artcode_book_code('RK 90'));
check('#601 keeps it too', $codes[601], 'RK 90');

echo "\n=== THE ASPECT renumber APPENDS DOES NOT CONFUSE THIS PASS ===\n";
// tools/renumber-artcodes.php writes RK - 010028-3050 onto every product on
// every deploy. A row here names a page. The two must agree about what page a
// product is standing on.
$cat = array(701 => 'RK - 010028-3050', 702 => 'RK - 010029-3050');
list($out, $codes) = run_pass($cat, array(array(701, 'RK 28')));
check('a product already on the page is left alone',
      (bool) preg_match('/already/', $out), true);
check('and its aspect is not stripped', $codes[701], 'RK - 010028-3050');
check('nothing was rewritten', $codes[702], 'RK - 010029-3050');

// The one that matters: the page is taken by a product carrying an aspect.
$cat = array(711 => 'RK - 010028-3050', 712 => 'RK 40');
list($out, $codes) = run_pass($cat, array(array(712, 'RK 28')));
check('a page held by an aspect-carrying product is still protected',
      (bool) preg_match('/REFUSED/', $out), true);
check('#712 keeps its own code', $codes[712], 'RK 40');

// And a chain still moves when every product carries one.
$cat = array(
    721 => 'RK - 010025-3050', 722 => 'RK - 010026-5030', 723 => 'RK - 010027-3050',
);
list($out, $codes) = run_pass($cat, array(array(721, 'RK 24'), array(722, 'RK 25'), array(723, 'RK 26')));
check('the chain moves with aspects on', (bool) preg_match('/REFUSED/', $out), false);
check('#721 -> RK 24', $codes[721], af_artcode_book_code('RK 24'));
check('#722 -> RK 25', $codes[722], af_artcode_book_code('RK 25'));
check('#723 -> RK 26', $codes[723], af_artcode_book_code('RK 26'));

echo "\n=== af_corr_page_key: what it keeps and what it drops ===\n";
check('the aspect goes',        af_corr_page_key('RK - 010028-3050'), 'RK - 010028');
check('a -GF suffix stays',     af_corr_page_key('HD - 080014-5030-GF'), 'HD - 080014-GF');
check('a bare page is unchanged', af_corr_page_key('RK - 010028'), 'RK - 010028');
check('the old numbering still reads', af_corr_page_key('RK 28'), 'RK - 010028');
check('a code in no section passes through', af_corr_page_key('AL 05'), 'AL 05');
check('NONE is left alone',     af_corr_page_key('NONE'), 'NONE');
check('empty stays empty',      af_corr_page_key(''), '');

printf("\n%d passed, %d failed\n", $pass, $fail);
exit($fail ? 1 : 0);
