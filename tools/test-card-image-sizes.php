<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Tests af_card_image_sizes_attr() in inc/card-image-sizes.php, WordPress stubbed:
 *     php tools/test-card-image-sizes.php
 * For each shape it also works out which file a browser would take from the
 * real copy widths and checks the picture still has a picture pixel for every
 * screen pixel in the cropped box (measured boxes: 172x259 phone, 309x300 laptop).
 */
define('ABSPATH', '/nowhere/');
function add_action() {} function add_filter() {}
function apply_filters($h, $v) { return $v; }
require __DIR__ . '/../inc/card-image-sizes.php';

$pass = 0; $fail = 0;
function same($label, $got, $want) { global $pass, $fail;
    if ($got === $want) { $pass++; echo "  OK    $label\n"; }
    else { $fail++; echo "  FAIL  $label\n        got  " . var_export($got, true) . "\n        want " . var_export($want, true) . "\n"; } }

/** The slot width a browser reads from sizes at viewport $vw. */
function slot($sizes, $vw) {
    foreach (explode(',', $sizes) as $part) {
        $part = trim($part);
        if (preg_match('~^\(max-width:\s*(\d+)px\)\s+(\d+)vw$~', $part, $m)) { if ($vw <= (int) $m[1]) return $vw * $m[2] / 100; continue; }
        if (preg_match('~^(\d+)px$~', $part, $m)) return (float) $m[1];
    }
    return 0;
}
/** Sharpness the browser ends up with: smallest copy covering the slot, drawn cropped into the box. */
function sharp($w, $h, $widths, $sizes, $vw, $dpr, $boxW, $boxH) {
    sort($widths);
    $need = slot($sizes, $vw) * $dpr; $pick = end($widths);
    foreach ($widths as $cw) if ($cw >= $need) { $pick = $cw; break; }
    $ph = $pick * $h / $w;
    return min($pick / ($boxW * $dpr), $ph / ($boxH * $dpr)) >= 1 ? 'sharp' : round(min($pick / ($boxW * $dpr), $ph / ($boxH * $dpr)), 2);
}

echo "=== the picture is never drawn softer than the screen ===\n";
$copies = array(150, 300, 400, 768, 800, 1024, 1200, 1536);
foreach (array('room scene 4:3' => array(1600, 1200), 'square' => array(1536, 1536), 'wide 16:9' => array(1536, 864), 'wide 3:2' => array(1536, 1024), 'tall 2:3' => array(1024, 1536), 'very tall 9:16' => array(864, 1536)) as $name => $d) {
    $s = af_card_image_sizes_attr($d[0] / $d[1]);
    same("$name, 390px phone 2x", sharp($d[0], $d[1], $copies, $s, 390, 2, 172, 259), 'sharp');
    same("$name, 390px phone 3x", sharp($d[0], $d[1], $copies, $s, 390, 3, 172, 259), 'sharp');
    same("$name, 1366px laptop 1x", sharp($d[0], $d[1], $copies, $s, 1366, 1, 309, 300), 'sharp');
    same("$name, 1920px 2x", sharp($d[0], $d[1], $copies, $s, 1920, 2, 333, 333), 'sharp');
}

echo "\n=== and tall pictures keep the lighter file ===\n";
same('tall 2:3 on a 2x phone asks for the card width only', af_card_image_sizes_attr(2 / 3), '(max-width: 767px) 48vw, (max-width: 1199px) 31vw, 340px');
same('a 4:3 scene asks for twice the card width on phones', strpos(af_card_image_sizes_attr(4 / 3), '(max-width: 767px) 96vw') === 0, true);
same('unknown shape assumes the widest case', af_card_image_sizes_attr(0), af_card_image_sizes_attr(2.0));

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
