<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Give a product a web-size copy of its Cloudflare R2 master (inc/digital-
 * masters.php) as its main picture, then publish it, so a digital buyer sees
 * the picture they will receive.
 *
 * Owner, 9 Oct: TP-050004-5030's master is the tall temple-arch Venkateswara
 * (#229's picture, trashed 30 Sep); its listing #8474 (SKU fixed to the plain
 * code by the owner) showed a different, wide close-up. Owner chose: "#8474 +
 * Cloudflare picture".
 *
 *   AF_MODE=check  what would change, with a 320 px preview of the new picture
 *                  (@@IMG|label|base64). Nothing is written.
 *   AF_MODE=apply  the copy (1600 px on the long side, no metadata) goes into
 *                  the Media Library and becomes the main picture; the old main
 *                  picture stays in the Media Library (its ID is kept in
 *                  _af_prev_thumbnail_id for an undo) and the gallery is left
 *                  as it is; the product is published. Run twice, the second
 *                  run finds the copy already in place and only publishes.
 *
 * Run: AF_PID=8474 AF_MODE=check wp eval-file tools/set-picture-from-master.php --allow-root
 */
if (!defined('ABSPATH')) exit(1);
require_once ABSPATH . 'wp-admin/includes/image.php';

$pid  = (int) getenv('AF_PID');
$mode = getenv('AF_MODE') === 'apply' ? 'apply' : 'check';
$LONG = 1600;
echo "=== #$pid, mode $mode\n";

$p = $pid ? wc_get_product($pid) : null;
if (!$p || get_post_type($pid) !== 'product') { echo "  not a product\n"; exit(1); }
if (!function_exists('af_r2_master_for_buyer')) { echo "  inc/digital-masters.php is not loaded\n"; exit(1); }
$m = af_r2_master_for_buyer($p);
$post = get_post($pid);
echo '  ' . $post->post_title . "\n";
echo '  status ' . $post->post_status . ', SKU ' . $p->get_sku() . ', art code ' . get_post_meta($pid, '_taf_art_code', true) . ', price ' . $p->get_price() . "\n";
if (!$m) { echo "  no master in Cloudflare reaches a buyer of this product (the SKU must be the art code): nothing done\n"; exit(1); }
echo '  master: ' . $m['key'] . ' (' . size_format($m['size'], 1) . ")\n";
$old = (int) get_post_thumbnail_id($pid);
$omd = $old ? wp_get_attachment_metadata($old) : array();
echo '  main picture now: ' . ($old ? '#' . $old . ' ' . basename((string) get_attached_file($old)) . ' (' . ($omd['width'] ?? '?') . 'x' . ($omd['height'] ?? '?') . ')' : 'none') . "\n";
$gal = array_filter(array_map('intval', explode(',', (string) get_post_meta($pid, '_product_image_gallery', true))));
echo '  gallery (left as it is): ' . ($gal ? implode(', ', array_map(function ($a) { return '#' . $a . ' ' . basename((string) get_attached_file($a)); }, $gal)) : 'empty') . "\n";

$done = $old && get_post_meta($old, '_af_from_master', true) === $m['key'];
if ($done) {
    echo "  the main picture is already the copy of this master\n";
} else {
    if (!class_exists('Imagick')) { echo "  Imagick is not available on the server: nothing done\n"; exit(1); }
    $tmp = wp_tempnam('afm') . '.jpg';
    $r = wp_remote_get(af_r2_object_url($m['key'], 600), array('timeout' => 300, 'stream' => true, 'filename' => $tmp));
    if (is_wp_error($r) || (int) wp_remote_retrieve_response_code($r) !== 200) {
        echo '  could not fetch the master: ' . (is_wp_error($r) ? $r->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code($r)) . "\n"; @unlink($tmp); exit(1);
    }
    try {
        $im = new Imagick();
        $im->pingImage($tmp); $W = $im->getImageWidth(); $H = $im->getImageHeight(); $im->clear();
        $s = $LONG / max($W, $H);
        $w = (int) round($W * $s); $h = (int) round($H * $s);
        $im = new Imagick();
        $im->setOption('jpeg:size', $w . 'x' . $h); // the JPEG decoder scales down while reading: little memory
        $im->readImage($tmp);
        $im->autoOrient();
        $im->resizeImage($w, $h, Imagick::FILTER_LANCZOS, 1);
        $im->transformImageColorspace(Imagick::COLORSPACE_SRGB);
        $im->stripImage();
        $im->setImageFormat('jpeg'); $im->setImageCompressionQuality(82); $im->setInterlaceScheme(Imagick::INTERLACE_PLANE);
        $jpg = $im->getImageBlob();
        $im->thumbnailImage(320, 320, true); $prev = $im->getImageBlob(); $im->clear();
    } catch (\Throwable $e) { echo '  could not make the copy: ' . $e->getMessage() . "\n"; @unlink($tmp); exit(1); }
    @unlink($tmp);
    echo "  master is {$W}x{$H} px; the copy is {$w}x{$h} px, " . size_format(strlen($jpg), 1) . "\n";
    echo '@@IMG|new main picture for #' . $pid . '|' . base64_encode($prev) . "\n";

    if ($mode === 'apply') {
        $name = sanitize_file_name(strtolower($m['code'] . '-' . preg_replace('/\s+[–-]\s+.*$/u', '', $post->post_title))) . '.jpg';
        $up = wp_upload_bits($name, null, $jpg);
        if (!empty($up['error'])) { echo '  upload failed: ' . $up['error'] . "\n"; exit(1); }
        $att = wp_insert_attachment(array('post_mime_type' => 'image/jpeg', 'post_title' => $post->post_title, 'post_status' => 'inherit'), $up['file'], $pid, true);
        if (is_wp_error($att)) { echo '  could not add it to the Media Library: ' . $att->get_error_message() . "\n"; exit(1); }
        wp_update_attachment_metadata($att, wp_generate_attachment_metadata($att, $up['file']));
        update_post_meta($att, '_wp_attachment_image_alt', $post->post_title);
        update_post_meta($att, '_af_from_master', $m['key']);
        if ($old) update_post_meta($pid, '_af_prev_thumbnail_id', $old);
        set_post_thumbnail($pid, $att);
        echo '  main picture set: #' . $att . ' ' . basename($up['file']) . ($old ? " (the old one, #$old, stays in the Media Library)" : '') . "\n";
    }
}

if ($mode === 'apply') {
    if ($post->post_status !== 'publish') {
        $res = wp_update_post(array('ID' => $pid, 'post_status' => 'publish'), true);
        echo '  publish: ' . (is_wp_error($res) ? 'FAILED ' . $res->get_error_message() : 'done') . "\n";
    } else {
        echo "  already published\n";
    }
    clean_post_cache($pid);
    if (function_exists('wc_delete_product_transients')) wc_delete_product_transients($pid);
    do_action('litespeed_purge_post', $pid);
    $p = wc_get_product($pid); $post = get_post($pid);
    echo "\n=== now\n";
    echo '  status ' . $post->post_status . ', main picture #' . get_post_thumbnail_id($pid) . ', link ' . get_permalink($pid) . "\n";
    $mm = af_r2_master_for_buyer($p);
    echo '  a digital buyer gets: ' . ($mm ? $mm['key'] . ' from Cloudflare' : 'NOT the master') . "\n";
} else {
    echo "\nCHECK ONLY: nothing was changed.\n";
}
echo "=== END\n";
