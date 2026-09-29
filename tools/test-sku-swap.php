<?php
/**
 * Tests tools/sku-to-artcode.php when two products trade SKUs.
 *
 * 29 Sep: the brochure showed #232 and #7662 (the two white-horse pages) and
 * #34015 and #34099 (two Krishna shringar pages) each carrying the other's
 * code. Correcting the codes is easy; the SKUs are not, because each product's
 * new SKU is the one the other still holds and WooCommerce refuses a duplicate.
 * Before the fix the first write failed, the second failed too, and the pair
 * stayed swapped on every deploy. Now the holder gives its SKU up first,
 * but only when it is sure to take its own new one later in the same pass.
 *
 * Runs the REAL pass against a stubbed WordPress whose set_sku() refuses a SKU
 * another product holds, as WooCommerce does. Plain `php` runs it:
 *
 *     php tools/test-sku-swap.php
 */
define('ABSPATH', '/nowhere/');
foreach (array('af_sku_code_part', 'af_sku_letter_seq') as $fn) {
    if (!preg_match('/\nfunction ' . $fn . '\(.*?\n\}\n/s', file_get_contents(__DIR__ . '/../inc/sku.php'), $m)) {
        fwrite(STDERR, "could not find {$fn}() in inc/sku.php\n"); exit(2);
    }
    eval($m[0]);
}

// ── a WordPress just big enough for the pass ────────────────────────────────
$GLOBALS['META'] = array();
function get_posts($a = array()) { $ids = array_keys($GLOBALS['META']); sort($ids); return $ids; }
function get_post_meta($pid, $k, $single = false) { return isset($GLOBALS['META'][$pid][$k]) ? $GLOBALS['META'][$pid][$k] : ''; }
function update_post_meta($pid, $k, $v) { $GLOBALS['META'][$pid][$k] = $v; return true; }
function delete_post_meta($pid, $k) { unset($GLOBALS['META'][$pid][$k]); return true; }
function get_the_title($pid) { return 'Product ' . $pid; }
function wp_strip_all_tags($s) { return $s; }
function wc_delete_product_transients($pid) {}
function wc_get_product_id_by_sku($sku) {
    foreach ($GLOBALS['META'] as $pid => $m) {
        if (isset($m['_sku']) && $m['_sku'] !== '' && strcasecmp($m['_sku'], $sku) === 0) { return $pid; }
    }
    return 0;
}
class AF_Test_Product {
    private $id; private $sku;
    function __construct($id) { $this->id = $id; $this->sku = get_post_meta($id, '_sku'); }
    function get_sku() { return $this->sku; }
    function set_sku($sku) {
        $holder = $sku === '' ? 0 : wc_get_product_id_by_sku($sku);
        if ($holder && $holder !== $this->id) { throw new Exception('Invalid or duplicated SKU.'); }
        $this->sku = $sku;
    }
    function save() { $GLOBALS['META'][$this->id]['_sku'] = $this->sku; }
}
function wc_get_product($pid) { return isset($GLOBALS['META'][$pid]) ? new AF_Test_Product($pid) : false; }

function run_pass($first) {
    $src = file_get_contents(__DIR__ . '/sku-to-artcode.php');
    $src = preg_replace('/^<\?php\s*/', '', $src, 1);
    $src = preg_replace('#^/\* AF-WEB-GUARD \*/.*$#m', '', $src, 1);
    $src = str_replace("if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, \"Run via wp eval-file\\n\" ); exit(1); }", '', $src);
    if (!$first) { $src = preg_replace('/\nfunction [a-z_]+\(.*?\n\}\n/s', "\n", $src); }
    ob_start(); eval($src); return ob_get_clean();
}

// ── the catalogue ───────────────────────────────────────────────────────────
// pid => art code, SKU. Every SKU was written by the pass for an earlier code.
$V = 'artcode-sku-v4-unique';
foreach (array(
    232   => array('SH-040002-3050', 'SH-040001-3050'),   // swapped with 7662
    7662  => array('SH-040001-3050', 'SH-040002-3050'),
    34015 => array('RK-010075-5030', 'RK-010059-5030'),   // swapped with 34099
    34099 => array('RK-010059-5030', 'RK-010075-5030'),
    501   => array('AA-200002-5030', 'AA-200001-4030'),   // wants 502's SKU...
    502   => array('AA-200003-5030', 'AA-200002-5030'),   // ...which is moving on
    601   => array('LB-090002-3050', 'LB-090001-3050'),   // wants a SKU 602 keeps
    602   => array('',               'LB-090002-3050'),   // no code: not moving
    701   => array('HD-080001-5030', 'HD-080001-5030'),   // nothing to do
) as $pid => $cs) {
    $GLOBALS['META'][$pid] = array('_taf_art_code' => $cs[0], '_sku' => $cs[1], '_af_sku_artcode' => $V);
    if ($cs[0] === '') { unset($GLOBALS['META'][$pid]['_taf_art_code'], $GLOBALS['META'][$pid]['_af_sku_artcode']); }
}

$pass = 0; $fail = 0;
function check($what, $got, $want) {
    global $pass, $fail;
    if ($got === $want) { $pass++; echo "  OK    $what\n"; }
    else { $fail++; echo "  FAIL  $what: got " . var_export($got, true) . ", want " . var_export($want, true) . "\n"; }
}
$sku = function ($pid) { return get_post_meta($pid, '_sku'); };

echo "=== first deploy ===\n";
$out = run_pass(true);
check('no write fails', substr_count($out, 'FAILED'), 0);
check('#232 takes SH-040002-3050', $sku(232), 'SH-040002-3050');
check('#7662 takes SH-040001-3050', $sku(7662), 'SH-040001-3050');
check('#34015 takes RK-010075-5030', $sku(34015), 'RK-010075-5030');
check('#34099 takes RK-010059-5030', $sku(34099), 'RK-010059-5030');
check('a chain: #501 takes the SKU #502 moves off', $sku(501), 'AA-200002-5030');
check('and #502 takes its own', $sku(502), 'AA-200003-5030');
check('a product that is not moving still refuses its SKU: #601 left as it was', $sku(601), 'LB-090001-3050');
check('and says so', substr_count($out, 'CLASH  #601'), 1);
check('#602 keeps LB-090002-3050', $sku(602), 'LB-090002-3050');
check('#701 untouched', $sku(701), 'HD-080001-5030');
check('the SKU each moved off is kept as its previous: #232', get_post_meta(232, '_af_sku_previous'), 'SH-040001-3050');
check('#7662, which gave its SKU up first', get_post_meta(7662, '_af_sku_previous'), 'SH-040002-3050');
check('#34099, likewise', get_post_meta(34099, '_af_sku_previous'), 'RK-010075-5030');
$left = 0; foreach ($GLOBALS['META'] as $m) { if (isset($m['_af_sku_vacated'])) { $left++; } }
check('no product is left holding a vacated note', $left, 0);
$all = array(); foreach ($GLOBALS['META'] as $m) { if ($m['_sku'] !== '') { $all[] = strtoupper($m['_sku']); } }
check('every SKU is distinct', count($all), count(array_unique($all)));

echo "\n=== a second deploy changes nothing ===\n";
$before = array(); foreach ($GLOBALS['META'] as $pid => $m) { $before[$pid] = $m['_sku']; }
$out2 = run_pass(false);
$after = array(); foreach ($GLOBALS['META'] as $pid => $m) { $after[$pid] = $m['_sku']; }
check('same SKUs', $after, $before);
check('nothing made room', substr_count($out2, 'made room'), 0);

echo "\n=== a run cut short after making room ===\n";
// #34015 gave its SKU up to #34099 and the run stopped before #34015's turn:
// it holds no SKU, and the next run gives it its new one.
$GLOBALS['META'][34015]['_sku'] = '';
$GLOBALS['META'][34015]['_af_sku_vacated'] = 'RK-010059-5030';
$GLOBALS['META'][34015]['_taf_art_code'] = 'RK-010075-5030';
$GLOBALS['META'][34099]['_sku'] = 'RK-010059-5030';
unset($GLOBALS['META'][34015]['_af_sku_previous']);
run_pass(false);
check('#34015 takes RK-010075-5030 on the next run', $sku(34015), 'RK-010075-5030');
check('with the SKU it gave up kept as its previous', get_post_meta(34015, '_af_sku_previous'), 'RK-010059-5030');
check('and the note is gone', get_post_meta(34015, '_af_sku_vacated'), '');

echo "\n=== a product never gives its SKU up unless it is sure of its new one ===\n";
// Stepping aside for a product whose new SKU is blocked would leave it with
// no SKU at all, which is worse than the clash: each keeps what it has.
$GLOBALS['META'] = array();
foreach (array(
    801 => array('KR-180002-5030', 'KR-180001-5030'),   // wants 802's SKU...
    802 => array('KR-180003-5030', 'KR-180002-5030'),   // ...who wants 803's...
    803 => array('',               'KR-180003-5030'),   // ...who has no code: not moving
    901 => array('LI-190002-5030', 'LI-190001-5030'),   // wants 950's, which is not moving: clashes...
    950 => array('',               'LI-190002-5030'),
    902 => array('LI-190001-5030', 'LI-190009-5030'),   // ...then 902, later, wants what 901 still holds
) as $pid => $cs) {
    $GLOBALS['META'][$pid] = array('_taf_art_code' => $cs[0], '_sku' => $cs[1], '_af_sku_artcode' => $V);
    if ($cs[0] === '') { unset($GLOBALS['META'][$pid]['_taf_art_code'], $GLOBALS['META'][$pid]['_af_sku_artcode']); }
}
$out3 = run_pass(false);
check('a chain ending on a product not moving: #802 keeps its SKU', $sku(802), 'KR-180002-5030');
check('and #801 keeps its own', $sku(801), 'KR-180001-5030');
check('#803 untouched', $sku(803), 'KR-180003-5030');
check('a holder whose turn has passed: #901 keeps its SKU', $sku(901), 'LI-190001-5030');
check('and #902 keeps its own', $sku(902), 'LI-190009-5030');
check('nothing made room', substr_count($out3, 'made room'), 0);
$left = 0; foreach ($GLOBALS['META'] as $m) { if ($m['_sku'] === '') { $left++; } }
check('no product is left without a SKU', $left, 0);

echo "\n=== the brochure's product left alone on its code drops its letter ===\n";
// #18600 moves off RK-010044-5030, leaving #19453, which tools/artcode-primary.csv
// names for that page, alone on it with the letter it took while sharing.
$GLOBALS['META'] = array();
foreach (array(
    18600 => array('RK-010040-5030', 'RK-010044-5030', ''),
    19453 => array('RK-010044-5030', 'RK-010044-5030A', 'A'),
    1101  => array('LB-090005-3050', 'LB-090005-3050A', 'A'),   // not named for its page
) as $pid => $cs) {
    $GLOBALS['META'][$pid] = array('_taf_art_code' => $cs[0], '_sku' => $cs[1], '_af_sku_artcode' => $V);
    if ($cs[2] !== '') { $GLOBALS['META'][$pid]['_af_sku_letter'] = $cs[2]; $GLOBALS['META'][$pid]['_af_sku_letter_for'] = $cs[0]; }
}
$out4 = run_pass(false);
check('#18600 takes RK-010040-5030', $sku(18600), 'RK-010040-5030');
check('#19453 takes the plain RK-010044-5030', $sku(19453), 'RK-010044-5030');
check('its letter is gone', get_post_meta(19453, '_af_sku_letter'), '');
check('and retired, never handed out again', get_post_meta(19453, '_af_sku_letter_retired'), 'A');
check('a product not named for its page keeps the SKU on its invoices', $sku(1101), 'LB-090005-3050A');
check('nothing failed', substr_count($out4, 'FAILED') + substr_count($out4, 'CLASH'), 0);
$before = array(); foreach ($GLOBALS['META'] as $pid => $m) { $before[$pid] = $m['_sku']; }
run_pass(false);
$after = array(); foreach ($GLOBALS['META'] as $pid => $m) { $after[$pid] = $m['_sku']; }
check('a second run changes nothing', $after, $before);

echo "\n=== a product that gave its SKU up and is planned to have it back ===\n";
// A run stopped after #1201 made room, and by the next run the plan has come
// back round to the SKU it gave up: it is not "already done" with no SKU.
$GLOBALS['META'] = array(1201 => array('_taf_art_code' => 'LB-090007-3050', '_sku' => '',
    '_af_sku_vacated' => 'LB-090007-3050', '_af_sku_artcode' => $V));
run_pass(false);
check('#1201 has its SKU written again', $sku(1201), 'LB-090007-3050');
check('and the note is gone', get_post_meta(1201, '_af_sku_vacated'), '');

printf("\n%d passed, %d failed\n", $pass, $fail);
exit($fail ? 1 : 0);
