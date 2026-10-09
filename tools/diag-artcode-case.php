<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * READ-ONLY. One art code, every listing that carries it (any status): title,
 * SKU, price, status, when and by whom it was last changed or trashed, its
 * main picture; and the code's master in Cloudflare R2 (inc/digital-masters.php)
 * with its size in pixels. Each picture is printed as a 320 px JPEG
 * (@@IMG|label|base64) so the listings and the master can be compared by eye.
 *
 * The master is fetched to a temp file and deleted (a read, not a download a
 * buyer makes: nothing is counted). Nothing on the site is changed.
 *
 * Owner, 9 Oct: TP-050004-5030's master reaches no buyer: #229 is in the
 * trash and #8474 (SKU ...B) is a draft.
 *
 * Run: wp eval-file tools/diag-artcode-case.php --allow-root
 */
if (!defined('ABSPATH')) exit(1);

$CODE = 'TP-050004-5030';
$MAX  = 320;

$thumb = function ($path) use ($MAX) {
    if (!$path || !file_exists($path)) return '';
    if (class_exists('Imagick')) {
        try {
            $im = new Imagick();
            $im->setOption('jpeg:size', ($MAX * 2) . 'x' . ($MAX * 2)); // decode at reduced scale: little memory
            $im->readImage($path);
            $im->thumbnailImage($MAX, $MAX, true);
            $im->setImageFormat('jpeg'); $im->setImageCompressionQuality(60);
            $b = $im->getImageBlob(); $im->clear();
            return base64_encode($b);
        } catch (\Throwable $e) { echo '  (Imagick: ' . $e->getMessage() . ")\n"; }
    }
    $ed = wp_get_image_editor($path);
    if (is_wp_error($ed)) return '';
    $ed->resize($MAX, $MAX, false); $ed->set_quality(60);
    $tmp = wp_tempnam('afc') . '.jpg';
    $s = $ed->save($tmp, 'image/jpeg');
    $ok = is_array($s) && !empty($s['path']) && file_exists($s['path']);
    $b = $ok ? base64_encode((string) file_get_contents($s['path'])) : '';
    if ($ok) @unlink($s['path']);
    @unlink($tmp);
    return $b;
};

global $wpdb;
$ids = $wpdb->get_col($wpdb->prepare(
    "SELECT DISTINCT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
     WHERE p.post_type = 'product' AND ((m.meta_key = '_taf_art_code' AND UPPER(TRIM(m.meta_value)) = %s) OR (m.meta_key = '_sku' AND UPPER(m.meta_value) LIKE %s))
     ORDER BY p.ID", $CODE, $CODE . '%'));
echo "=== listings carrying $CODE (art code, or a SKU starting with it)\n";
foreach ($ids as $pid) {
    $p = get_post($pid); $wc = wc_get_product($pid);
    $u = get_userdata((int) get_post_meta($pid, '_edit_last', true));
    printf("  #%d %s  SKU %s  art code %s  price %s  type %s\n", $pid, $p->post_status, get_post_meta($pid, '_sku', true) ?: '(none)',
        get_post_meta($pid, '_taf_art_code', true) ?: '(none)', $wc ? $wc->get_price() : '?', $wc ? $wc->get_type() : '?');
    echo '    title: ' . $p->post_title . "\n";
    echo '    created ' . $p->post_date_gmt . ' UTC, last changed ' . $p->post_modified_gmt . ' UTC by ' . ($u ? $u->user_login : 'unknown') . "\n";
    if ($t = get_post_meta($pid, '_wp_trash_meta_time', true)) echo '    trashed ' . gmdate('Y-m-d H:i', (int) $t) . ' UTC (it was ' . get_post_meta($pid, '_wp_trash_meta_status', true) . " before)\n";
    echo '    link: ' . ($p->post_status === 'publish' ? get_permalink($pid) : $p->post_name . ' (not public)') . "\n";
    echo '    sold: ' . (int) get_post_meta($pid, 'total_sales', true) . ', reviews: ' . (int) get_comments(array('post_id' => $pid, 'count' => true)) . "\n";
    $cats = wp_get_post_terms($pid, 'product_cat', array('fields' => 'names'));
    echo '    categories: ' . (is_wp_error($cats) ? '?' : implode(', ', $cats)) . "\n";
    $att = (int) get_post_thumbnail_id($pid);
    $path = $att ? get_attached_file($att) : '';
    $md = $att ? wp_get_attachment_metadata($att) : array();
    echo '    main picture: ' . ($att ? '#' . $att . ' ' . basename((string) $path) . ' (' . ($md['width'] ?? '?') . 'x' . ($md['height'] ?? '?') . ')' : 'none') . "\n";
    echo "@@IMG|#$pid " . $p->post_status . '|' . $thumb($path) . "\n";
    foreach (array_filter(array_map('intval', explode(',', (string) get_post_meta($pid, '_product_image_gallery', true)))) as $g) {
        echo "    gallery #$g " . basename((string) get_attached_file($g)) . "\n";
        echo "@@IMG|#$pid gallery #$g|" . $thumb(get_attached_file($g)) . "\n";
    }
}
// revisions/changes logged by WooCommerce for these (who drafted #8474, and when)
echo "\n=== recent status changes in the last 3 days (any product)\n";
$rows = $wpdb->get_results("SELECT ID, post_status, post_modified_gmt, post_title FROM {$wpdb->posts} WHERE post_type = 'product' AND post_modified_gmt > UTC_TIMESTAMP() - INTERVAL 3 DAY AND post_status IN ('draft', 'trash', 'private', 'pending') ORDER BY post_modified_gmt DESC LIMIT 15");
foreach ($rows as $r) printf("  #%d %s, changed %s UTC: %s\n", $r->ID, $r->post_status, $r->post_modified_gmt, mb_substr($r->post_title, 0, 60));

echo "\n=== the master in Cloudflare R2\n";
$idx = get_option('af_r2_index');
$m = is_array($idx) ? ($idx['map'][$CODE] ?? null) : null;
if (!$m || !function_exists('af_r2_object_url')) { echo "  none\n"; } else {
    echo '  ' . $m['key'] . ' (' . size_format($m['size'], 1) . ")\n";
    $tmp = wp_tempnam('afm') . '.jpg';
    $r = wp_remote_get(af_r2_object_url($m['key'], 300), array('timeout' => 120, 'stream' => true, 'filename' => $tmp));
    if (is_wp_error($r) || (int) wp_remote_retrieve_response_code($r) !== 200) {
        echo '  could not fetch it: ' . (is_wp_error($r) ? $r->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code($r)) . "\n";
    } else {
        $sz = @getimagesize($tmp);
        echo '  ' . ($sz ? $sz[0] . 'x' . $sz[1] . ' px' : 'size unreadable') . "\n";
        echo '@@IMG|master ' . $m['key'] . '|' . $thumb($tmp) . "\n";
    }
    @unlink($tmp);
}
echo "=== END\n";
