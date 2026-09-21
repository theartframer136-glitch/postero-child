<?php
/**
 * OFFLINE renderer for the "Try It On Your Wall" page.
 *
 * Extracts the page's template_redirect closure body and the
 * af_preview_share_assets() helper from functions.php (by markers, not line
 * numbers), wraps them in plain functions, runs them against WP/Woo stubs and
 * writes tools/tow-harness/www/index.html plus the placeholder images the page
 * references. No WordPress, no network.
 *
 *   php tools/tow-harness/render.php            (TOW_LOGGED=0 → logged-out CFG)
 */

$ROOT = dirname(__DIR__, 2);
$HERE = __DIR__;
$WWW  = $HERE . '/www';
$SRC  = $ROOT . '/functions.php';
// Relative, so the rendered page works on whatever port a run picks.
$BASE = '';

if (!is_dir($WWW)) mkdir($WWW, 0777, true);

// ── 1. locate the closure body and the share-assets helper ─────────────────
$lines = file($SRC, FILE_IGNORE_NEW_LINES);
if ($lines === false) { fwrite(STDERR, "cannot read $SRC\n"); exit(1); }
$n = count($lines);

$start = -1;
for ($i = 0; $i < $n - 1; $i++) {
    if (preg_match('/^\s*add_action\(\s*[\'"]template_redirect[\'"]\s*,\s*function\s*\(\s*\)\s*\{\s*$/', $lines[$i])
        && strpos($lines[$i + 1], "is_page(array('try-on-wall','try-it-on-your-wall'))") !== false) {
        $start = $i; break;
    }
}
if ($start < 0) { fwrite(STDERR, "closure start marker not found\n"); exit(1); }

// end: the first `}, 1);` after a `get_footer();` + `exit;` pair following the start
$end = -1;
for ($i = $start + 1; $i < $n; $i++) {
    if (preg_match('/^\s*\},\s*1\s*\);\s*$/', $lines[$i])) {
        // confirm the two lines before are exit; and get_footer();
        $prev = array_slice($lines, max($start, $i - 4), $i - max($start, $i - 4));
        $joined = implode("\n", $prev);
        if (strpos($joined, 'get_footer();') !== false && strpos($joined, 'exit;') !== false) { $end = $i; break; }
    }
}
if ($end < 0) { fwrite(STDERR, "closure end marker not found\n"); exit(1); }

$body = array_slice($lines, $start + 1, $end - $start - 1);   // inside the closure braces
// drop the trailing `exit;` (the last one after get_footer()) so the script survives
for ($i = count($body) - 1; $i >= 0; $i--) {
    if (preg_match('/^\s*exit;\s*$/', $body[$i])) { $body[$i] = '    /* exit; removed by tow-harness */'; break; }
}
$bodyText = implode("\n", $body);

$fnStart = -1; $fnEnd = -1;
for ($i = 0; $i < $n; $i++) {
    if ($fnStart < 0 && preg_match('/^function af_preview_share_assets\(\)\s*\{/', $lines[$i])) { $fnStart = $i; continue; }
    if ($fnStart >= 0 && preg_match('/^\/\/ ── PHASE 28/u', $lines[$i])) { $fnEnd = $i; break; }
}
if ($fnStart < 0 || $fnEnd < 0) { fwrite(STDERR, "af_preview_share_assets markers not found\n"); exit(1); }
$fnText = implode("\n", array_slice($lines, $fnStart, $fnEnd - $fnStart));

fwrite(STDERR, sprintf("closure: lines %d..%d (%d lines); share assets: lines %d..%d\n",
    $start + 1, $end + 1, count($body), $fnStart + 1, $fnEnd));

// ── 2. stubs ────────────────────────────────────────────────────────────────
function esc_url($s)  { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function esc_attr($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function esc_html($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function home_url($p = '') { global $BASE; return $BASE . $p; }
function admin_url($p = '') { global $BASE; return $BASE . '/' . ltrim($p, '/'); }
function add_query_arg(...$a) {
    if (count($a) === 2 && is_array($a[0])) { $args = $a[0]; $url = $a[1]; }
    else { $args = array($a[0] => $a[1]); $url = $a[2] ?? ''; }
    return $url . (strpos($url, '?') !== false ? '&' : '?') . http_build_query($args);
}
function wp_json_encode($v, $f = 0) { return json_encode($v, $f | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); }
function wp_create_nonce($a = '') { return 'testnonce'; }
function is_user_logged_in() { return getenv('TOW_LOGGED') !== '0'; }
function is_page($x = null) { return true; }
function is_wp_error($x) { return false; }
function get_header() {
    echo "<!doctype html>\n<html lang=\"en\">\n<head>\n<meta charset=\"utf-8\">\n<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n<title>Try It On Your Wall — offline harness</title>\n<style>html,body{margin:0;padding:0;font-family:system-ui,sans-serif;background:#fff;}</style>\n</head>\n<body>\n";
}
function get_footer() { echo "\n</body>\n</html>\n"; }
function get_terms($args = array()) {
    $mk = function ($id, $slug, $name) { $o = new stdClass; $o->term_id = $id; $o->slug = $slug; $o->name = $name; $o->parent = 0; return $o; };
    return array($mk(11, 'living-room', 'Living Room'), $mk(12, 'bedroom', 'Bedroom'));
}
function wc_get_products($args = array()) { return array(101, 102); }
class AF_Tow_Stub_Product {
    private $id;
    public function __construct($id) { $this->id = (int)$id; }
    public function get_id() { return $this->id; }
    public function get_image_id() { return 5000 + $this->id; }
    public function get_name() { return $this->id === 101 ? 'Radha Krishna Canvas Wall Art 3x4 Feet' : 'Golden Lotus Abstract 2x3 Feet'; }
    public function get_price() { return 120.0; }
}
function wc_get_product($id) { return new AF_Tow_Stub_Product($id); }
function wp_get_attachment_image_url($att_id, $size = 'large') { $pid = (int)$att_id - 5000; return home_url('/art-' . $pid . '.png'); }
function wc_get_price_to_display($p, $a = array()) { return 120.0; }
function wp_get_post_terms($pid, $tax = '', $args = array()) { return array((int)$pid === 102 ? 'bedroom' : 'living-room'); }
function get_permalink($id = 0) { return home_url('/product/' . $id . '/'); }
function af_goldfoil_factor($pid = 0) { return 1; }
function get_woocommerce_currency_symbol() { return '$'; }
function wp_get_upload_dir() { return array('baseurl' => home_url('/uploads'), 'basedir' => __DIR__ . '/www/uploads'); }
// Mirrors af_frames_in_stock(): a plain list of frame labels. The real one
// currently returns only Without Frame + Aluminium Frame; the harness offers all
// four so the default selection is deterministic and every frame is testable.
function af_frames_in_stock() { return array('Fibre Frame', 'Floating Frame', 'Aluminium Frame', 'Without Frame'); }
// Mirrors af_sizes_available(): array_values of size labels in price-book order.
function af_sizes_available() { return array('2×3 ft (24×36 in)', '3×2 ft (36×24 in)', '2.5×3 ft (30×36 in)', '3×4 ft (36×48 in)', '3×5 ft (36×60 in)'); }
// Mirrors af_pricing_config(): sizes/groups/hints/frames/colors maps (USD).
function af_pricing_config($product_id = 0) {
    return array(
        'sizes' => array(
            '2×3 ft (24×36 in)' => 60, '3×2 ft (36×24 in)' => 60, '2×3.5 ft (24×42 in)' => 65, '2×4 ft (24×48 in)' => 70,
            '2×5 ft (24×60 in)' => 75, '2.5×3 ft (30×36 in)' => 65, '2.5×4 ft (30×48 in)' => 75, '2.5×5 ft (30×60 in)' => 85,
            '3×4 ft (36×48 in)' => 80, '3×5 ft (36×60 in)' => 100, '3×6 ft (36×72 in)' => 120, '4×3 ft (48×36 in)' => 80,
            '4×4 ft (48×48 in)' => 110, '4×5 ft (48×60 in)' => 130, '4×6 ft (48×72 in)' => 150,
        ),
        'groups' => array(
            'Small'  => array('2×3 ft (24×36 in)', '3×2 ft (36×24 in)', '2×3.5 ft (24×42 in)', '2.5×3 ft (30×36 in)'),
            'Medium' => array('2×4 ft (24×48 in)', '2.5×4 ft (30×48 in)', '3×4 ft (36×48 in)', '4×3 ft (48×36 in)'),
            'Large'  => array('2×5 ft (24×60 in)', '2.5×5 ft (30×60 in)', '3×5 ft (36×60 in)', '3×6 ft (36×72 in)', '4×4 ft (48×48 in)', '4×5 ft (48×60 in)', '4×6 ft (48×72 in)'),
        ),
        'hints'  => array('2×3 ft (24×36 in)' => 'Best for 6×8 ft walls & cozy corners', '3×4 ft (36×48 in)' => 'Best for 9×11 ft walls'),
        'frames' => array('Without Frame' => 0, 'Fibre Frame' => 25, 'Floating Frame' => 50, 'Aluminium Frame' => 55),
        'colors' => array('Black' => 0, 'Silver' => 0, 'Gold' => 10, 'Rose Gold' => 10),
    );
}
function wc_get_page_permalink($page = 'myaccount') { return home_url('/my-account/'); }
function wc_get_endpoint_url($endpoint, $value = '', $permalink = '') { return home_url('/my-account/saved-previews/'); }

// ── 3. placeholder images (GD) ──────────────────────────────────────────────
function tow_png_art($path, $w, $h, $rgbA, $rgbB, $label) {
    $im = imagecreatetruecolor($w, $h);
    for ($y = 0; $y < $h; $y++) {
        $t = $y / ($h - 1);
        $c = imagecolorallocate($im,
            (int)($rgbA[0] + ($rgbB[0] - $rgbA[0]) * $t),
            (int)($rgbA[1] + ($rgbB[1] - $rgbA[1]) * $t),
            (int)($rgbA[2] + ($rgbB[2] - $rgbA[2]) * $t));
        imageline($im, 0, $y, $w - 1, $y, $c);
    }
    $white = imagecolorallocate($im, 255, 255, 255);
    $dark  = imagecolorallocate($im, 30, 30, 30);
    imagefilledellipse($im, (int)($w / 2), (int)($h / 2), (int)($w * 0.55), (int)($w * 0.55), $white);
    imagefilledrectangle($im, 40, 40, $w - 41, $h - 41, imagecolorallocatealpha($im, 0, 0, 0, 127)); // no-op transparent, keeps API exercised
    imagerectangle($im, 20, 20, $w - 21, $h - 21, $dark);
    imagestring($im, 5, 30, 30, $label, $dark);
    imagepng($im, $path); imagedestroy($im);
}
function tow_jpg_room($path, $w, $h) {
    $im = imagecreatetruecolor($w, $h);
    $wall = imagecolorallocate($im, 226, 220, 205);
    $floor = imagecolorallocate($im, 150, 110, 75);
    imagefilledrectangle($im, 0, 0, $w, (int)($h * 0.78), $wall);
    imagefilledrectangle($im, 0, (int)($h * 0.78), $w, $h, $floor);
    imagejpeg($im, $path, 80); imagedestroy($im);
}
tow_png_art($WWW . '/art-101.png', 600, 800, array(40, 60, 140), array(210, 120, 60), 'ART 101');
tow_png_art($WWW . '/art-102.png', 600, 800, array(120, 30, 60), array(240, 200, 90), 'ART 102');
@mkdir($WWW . '/uploads/mockups', 0777, true);
foreach (array(
    'Radha-Krishna-Canvas-Wall-Art-Placement_Living-Room-blankwall.jpg',
    'Radha-Krishna_Wall-Art_-Living-Room-blankwall.jpg',
    'Radha-Krishna-Abstract-Wall-Art_living-room-blankwall.jpg',
    'TAF-RADHA-KRISHNA-18530-room-1-blankwall.jpg',
    'room.jpg',
) as $f) tow_jpg_room($WWW . '/uploads/mockups/' . $f, 1600, 1000);
// the ajax mock answers with this url as the saved image
tow_png_art($WWW . '/saved.png', 300, 200, array(80, 80, 80), array(120, 120, 120), 'SAVED');

// ── 4. assemble + lint + include ────────────────────────────────────────────
$tmp = $HERE . '/.tow-page.tmp.php';
$php = "<?php\n// generated by tools/tow-harness/render.php — do not edit\n"
     . "function af_tow_render() {\n" . $bodyText . "\n}\n\n" . $fnText . "\n";

file_put_contents($tmp, $php);

exec('/usr/bin/php -l ' . escapeshellarg($tmp) . ' 2>&1', $lintOut, $lintRc);
if ($lintRc !== 0) { fwrite(STDERR, "php -l failed on $tmp:\n" . implode("\n", $lintOut) . "\n"); exit(1); }

require $tmp;
ob_start();
af_tow_render();
$html = ob_get_clean();
if (strpos($html, '<script') === false || strpos($html, 'window.AFCal') === false) {
    fwrite(STDERR, "rendered page is missing its <script> / AFCal — stub gap?\n");
    file_put_contents($WWW . '/index.html', $html);
    exit(1);
}
// The phone stylesheet lives in a DIFFERENT wp_head hook (id="af-mobile-responsive"),
// so the closure alone renders a page that behaves like a desktop at every width.
// Splice that block in verbatim so <=781px rules (notably the 4:3 stage) apply.
$MOBILE_CSS_INJECTED = true;
$fnsrc = file_get_contents(__DIR__ . '/../../functions.php');
$mA = strpos($fnsrc, '<style id="af-mobile-responsive">');
if ($mA !== false) {
    $mB = strpos($fnsrc, '</style>', $mA);
    $mobile_css = substr($fnsrc, $mA, $mB - $mA + 8);
    $html = str_replace('</head>', $mobile_css . "\n</head>", $html, $cnt);
    if (!$cnt) { $html .= $mobile_css; }
}

file_put_contents($WWW . '/index.html', $html);
fwrite(STDERR, sprintf("wrote %s (%d bytes, logged=%s)\n", $WWW . '/index.html', strlen($html), is_user_logged_in() ? 'yes' : 'no'));
echo $WWW . "/index.html\n";
