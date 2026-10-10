<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * READ-ONLY. Every product's art code with its main picture, for matching the
 * Master Brochure's pages to the shop by picture (owner, 10 Oct 2026: "properly
 * update the art code by matching products from the canva to website products").
 *
 * Output, one line each:
 *   @@R|<pid>|<status>|<art code>|<sku>|<title>       every product but trash
 *   @@I|<pid>|<base64 jpeg, 300 px, or empty>          published products only
 *   @@SHEET DONE n=<products> pictures=<pictures>
 *
 * The pictures are WordPress's own resized copies, re-encoded small in a temp
 * file that is deleted; nothing on the site is changed. The products are on the
 * public site already, so their pictures may sit in a public log.
 *
 * Run: wp eval-file tools/diag-artcode-sheet.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }

$MAX = 300;
$Q   = 60;

$pick = function ($att) {
    $file = get_attached_file($att);
    if (!$file || !file_exists($file)) return '';
    $md = wp_get_attachment_metadata($att);
    foreach (array('woocommerce_single', 'medium_large', 'large') as $s) {
        if (!empty($md['sizes'][$s]['file'])) { $p = dirname($file) . '/' . $md['sizes'][$s]['file']; if (file_exists($p)) return $p; }
    }
    return $file;
};

global $wpdb;
$ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status IN ('publish', 'private', 'draft', 'pending') ORDER BY ID");
$n = 0; $pics = 0;
foreach ($ids as $pid) {
    $st = get_post_status($pid);
    $title = html_entity_decode((string) get_post_field('post_title', $pid, 'raw'), ENT_QUOTES, 'UTF-8');
    echo '@@R|' . $pid . '|' . $st . '|' . trim((string) get_post_meta($pid, '_taf_art_code', true)) . '|' . trim((string) get_post_meta($pid, '_sku', true)) . '|' . str_replace(array('|', "\n", "\r"), ' ', $title) . "\n";
    $n++;
    if ($st !== 'publish') continue;
    $b64 = '';
    $path = ($att = get_post_thumbnail_id($pid)) ? $pick($att) : '';
    if ($path) {
        $ed = wp_get_image_editor($path);
        if (!is_wp_error($ed)) {
            $ed->resize($MAX, $MAX, false);
            $ed->set_quality($Q);
            $tmp = wp_tempnam('afs') . '.jpg';
            $saved = $ed->save($tmp, 'image/jpeg');
            if (is_array($saved) && !empty($saved['path']) && file_exists($saved['path'])) {
                $b64 = base64_encode((string) file_get_contents($saved['path']));
                @unlink($saved['path']);
            }
            @unlink($tmp);
        }
    }
    if ($b64 !== '') $pics++;
    echo '@@I|' . $pid . '|' . $b64 . "\n";
}
echo "@@SHEET DONE n={$n} pictures={$pics}\n";
