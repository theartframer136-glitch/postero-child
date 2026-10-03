<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Tests inc/product-pictures.php: the picture it sets, and what it leaves
 * alone.
 *
 * Owner, 3 Oct, with the Personal Pic file CO-240006-0000.jpg: "update the
 * code and picture too", for #33294 only. #33294 takes the file as its main
 * picture while it carries TMP-1166 or CO-240006-0000; the old main picture
 * leaves the gallery but stays in the media library; it happens once per
 * revision, and never twice for one product.
 *
 * Runs the REAL file with WordPress and WooCommerce stubbed, and the REAL
 * picture in assets/product-pictures/. Plain `php`:
 *
 *     php tools/test-product-pictures.php
 */
define('ABSPATH', '/nowhere/');
$GLOBALS['THEME'] = dirname(__DIR__);
$GLOBALS['HOOKS'] = array(); $GLOBALS['OPT'] = array(); $GLOBALS['P'] = array(); $GLOBALS['META'] = array();
$GLOBALS['FIRED'] = array(); $GLOBALS['UPLOADS'] = array(); $GLOBALS['NEXT'] = 50000;
function add_action($h, $cb, $prio = 10) { $GLOBALS['HOOKS'][$h][] = $cb; }
function do_action($h) { $GLOBALS['FIRED'][] = $h; }
function get_option($k, $d = false) { return array_key_exists($k, $GLOBALS['OPT']) ? $GLOBALS['OPT'][$k] : $d; }
function update_option($k, $v, $autoload = null) { $GLOBALS['OPT'][$k] = $v; return true; }
function add_option($k, $v = '', $dep = '', $autoload = 'yes') { if (array_key_exists($k, $GLOBALS['OPT'])) return false; $GLOBALS['OPT'][$k] = $v; return true; }
function get_stylesheet_directory() { return $GLOBALS['THEME']; }
function get_post_meta($id, $k, $single = false) { return isset($GLOBALS['META'][$id][$k]) ? $GLOBALS['META'][$id][$k] : ''; }
function update_post_meta($id, $k, $v) { $GLOBALS['META'][$id][$k] = $v; return true; }
function get_post_thumbnail_id($id) { return (int) get_post_meta($id, '_thumbnail_id'); }
function wp_strip_all_tags($s) { return strip_tags($s); }
function is_wp_error($x) { return false; }
function wc_get_page_permalink($p) { return 'https://theartframer.us/shop/'; }
function wp_check_filetype($f) { return array('ext' => 'jpg', 'type' => 'image/jpeg'); }
function wp_upload_bits($name, $dep, $bits) {
    $GLOBALS['UPLOADS'][] = array($name, strlen($bits));
    return array('file' => '/uploads/2026/10/' . $name, 'url' => 'https://theartframer.us/wp-content/uploads/2026/10/' . $name, 'error' => false);
}
function wp_insert_attachment($a, $file, $parent, $err = false) {
    $id = $GLOBALS['NEXT']++;
    $GLOBALS['ATT'][$id] = array('file' => $file, 'parent' => $parent, 'title' => $a['post_title'], 'mime' => $a['post_mime_type']);
    return $id;
}
function wp_generate_attachment_metadata($id, $file) {
    $s = getimagesize($GLOBALS['THEME'] . '/assets/product-pictures/' . basename($file));
    return array('width' => $s[0], 'height' => $s[1], 'file' => $file);
}
function wp_update_attachment_metadata($id, $m) { $GLOBALS['META'][$id]['_wp_attachment_metadata'] = $m; }
class Fake_Product {
    public $id; function __construct($id) { $this->id = $id; }
    function get_name() { return $GLOBALS['P'][$this->id]['name']; }
    function get_gallery_image_ids() { return $GLOBALS['P'][$this->id]['gallery']; }
    function set_image_id($a) { $this->img = $a; }
    function set_gallery_image_ids($g) { $this->gal = $g; }
    function save() {
        if (isset($this->img)) { $GLOBALS['META'][$this->id]['_thumbnail_id'] = $this->img; }
        if (isset($this->gal)) { $GLOBALS['P'][$this->id]['gallery'] = $this->gal; }
        if (!empty($GLOBALS['THROW'])) { throw new RuntimeException('database went away'); }
    }
}
function wc_get_product($id) { return isset($GLOBALS['P'][$id]) ? new Fake_Product($id) : false; }

require __DIR__ . '/../inc/product-pictures.php';

$pass = 0; $fail = 0;
function check($what, $got, $want) {
    global $pass, $fail;
    if ($got === $want) { $pass++; echo "  OK    $what\n"; }
    else { $fail++; echo "  FAIL  $what: got " . var_export($got, true) . ", want " . var_export($want, true) . "\n"; }
}
/** A fresh site: #33294 with the given code, main picture 900, gallery 900 and 901; then one request. */
function request($code, $opt = array(), $extra = array()) {
    $GLOBALS['OPT'] = $opt; $GLOBALS['FIRED'] = array(); $GLOBALS['UPLOADS'] = array(); $GLOBALS['ATT'] = array();
    $GLOBALS['P'] = array(33294 => array('name' => 'Dance Duet On Stage Canvas Wall Art 3x4 Feet &amp; More', 'gallery' => array(900, 901)));
    $GLOBALS['META'] = array(33294 => array('_taf_art_code' => $code, '_thumbnail_id' => 900));
    foreach ($extra as $id => $m) { $GLOBALS['META'][$id] = $m + (isset($GLOBALS['META'][$id]) ? $GLOBALS['META'][$id] : array()); }
    foreach ($GLOBALS['HOOKS']['wp_loaded'] as $cb) { $cb(); }
    return (string) get_option('af_product_pictures', '');
}
$file = dirname(__DIR__) . '/assets/product-pictures/co-240006-0000.jpg';
$src = 'co-240006-0000.jpg ' . md5(file_get_contents($file));

echo "=== the list: #33294 only, the owner's file ===\n";
$list = af_product_pictures();
check('one product', array_keys($list), array(33294));
check('while it carries TMP-1166 or CO-240006-0000', $list[33294]['codes'], array('TMP-1166', 'CO-240006-0000'));
$s = getimagesize($file);
check('the picture is in the theme, a JPEG', $s['mime'], 'image/jpeg');
check('635 x 952, portrait like the photo it replaces', array($s[0], $s[1]), array(635, 952));

echo "\n=== the deploy that carries it, before the corrections pass (TMP-1166) ===\n";
$log = request('TMP-1166');
$att = get_post_thumbnail_id(33294);
check('one upload, the file itself', $GLOBALS['UPLOADS'], array(array('co-240006-0000.jpg', filesize($file))));
check('a new attachment of #33294 is its main picture', isset($GLOBALS['ATT'][$att]) ? $GLOBALS['ATT'][$att]['parent'] : null, 33294);
check('the old main picture is kept in _af_picture_before', get_post_meta(33294, '_af_picture_before'), 900);
check('the old main picture leaves the gallery; the rest stays', $GLOBALS['P'][33294]['gallery'], array(901));
check('alt text is the product name, decoded', get_post_meta($att, '_wp_attachment_image_alt'), 'Dance Duet On Stage Canvas Wall Art 3x4 Feet & More');
check('the attachment remembers the file it came from', get_post_meta($att, '_af_picture_source'), $src);
check('its sizes are made', get_post_meta($att, '_wp_attachment_metadata')['width'], 635);
check('the product page and the shop are purged', array_values(array_intersect(array('litespeed_purge_post', 'litespeed_purge_posttype', 'litespeed_purge_url'), $GLOBALS['FIRED'])), array('litespeed_purge_post', 'litespeed_purge_posttype', 'litespeed_purge_url'));
check('the revision is recorded', get_option('af_product_pictures_rev'), AF_PRODUCT_PICTURES_REV);
check('the log says what it did', strpos($log, '33294 main picture 900 -> ' . $att . ' (co-240006-0000.jpg, 635x952); gallery 2 -> 1') !== false, true);

echo "\n=== after the corrections pass (CO-240006-0000, however it is spaced) ===\n";
request('CO - 240006–0000');
check('applied', $GLOBALS['UPLOADS'] !== array() && get_post_thumbnail_id(33294) !== 900, true);

echo "\n=== an id that holds another piece by then ===\n";
$log = request('RK-010001-3050');
check('left alone', array(get_post_thumbnail_id(33294), $GLOBALS['UPLOADS']), array(900, array()));
check('the log says why', strpos($log, '33294 left alone: art code is now "RK-010001-3050"') !== false, true);

echo "\n=== a second request after the same deploy ===\n";
request('TMP-1166', array('af_product_pictures_rev' => AF_PRODUCT_PICTURES_REV));
check('does nothing', array(get_post_thumbnail_id(33294), $GLOBALS['UPLOADS']), array(900, array()));
echo "\n=== a request arriving while another holds the claim ===\n";
request('TMP-1166', array('af_product_pictures_claim_' . AF_PRODUCT_PICTURES_REV => '2026-10-03 08:00:00'));
check('does nothing', array(get_post_thumbnail_id(33294), $GLOBALS['UPLOADS']), array(900, array()));

echo "\n=== a later revision, the product already showing the same file ===\n";
$log = request('CO-240006-0000', array(), array(900 => array('_af_picture_source' => $src)));
check('no second copy', $GLOBALS['UPLOADS'], array());
check('the log says so', strpos($log, '33294 already shows co-240006-0000.jpg') !== false, true);

echo "\n=== the theme still arriving: the picture not there yet ===\n";
$GLOBALS['THEME'] = sys_get_temp_dir() . '/af-no-theme-' . getmypid();
request('TMP-1166');
check('nothing claimed, so the next request tries again', array(get_option('af_product_pictures_rev'), $GLOBALS['UPLOADS']), array(false, array()));
$GLOBALS['THEME'] = dirname(__DIR__);

echo "\n=== the product is gone ===\n";
$GLOBALS['P'] = array();
$GLOBALS['OPT'] = array(); $GLOBALS['UPLOADS'] = array();
foreach ($GLOBALS['HOOKS']['wp_loaded'] as $cb) { $cb(); }
check('the log reads "not found"', strpos((string) get_option('af_product_pictures'), '33294 not found') !== false, true);

echo "\n=== a failure part way ===\n";
$GLOBALS['THROW'] = true;
$log = request('TMP-1166');
$GLOBALS['THROW'] = false;
check('is logged', strpos($log, 'error: database went away') !== false, true);
check('and not repeated on every request', get_option('af_product_pictures_rev'), AF_PRODUCT_PICTURES_REV);

printf("\n%d passed, %d failed\n", $pass, $fail);
exit($fail ? 1 : 0);
