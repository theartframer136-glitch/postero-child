<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * READ-ONLY. Gathers, for tools/picture-match.py, every product picture on the
 * site and the Cloudflare R2 masters asked for, into /tmp/af-match:
 *   img/<n>.<ext>     a link to WordPress's own ~600-800 px copy of each main
 *                     and gallery picture (no new files are made for these)
 *   masters/<key>     a 1000 px copy of each master (fetched, resized, temp)
 *   index.tsv         n, product, status, main/gallery, picture ID, art code, title
 * The workflow copies the folder off the server and deletes it. Nothing on the
 * site is changed. No server path is printed.
 *
 * Products of every status but auto-drafts are included (trash too), so a
 * picture that is only on a hidden listing is found as well.
 *
 * Run: AF_MASTERS="RK-010022-3050-DD.jpg RK-010005-3050-DD.jpg" wp eval-file tools/diag-picture-files.php --allow-root
 */
if (!defined('ABSPATH')) exit(1);

$dir = '/tmp/af-match';
if (is_dir($dir)) { foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) { $f->isDir() ? rmdir($f) : unlink($f); } rmdir($dir); }
mkdir($dir . '/img', 0700, true); mkdir($dir . '/masters', 0700, true);

$pick = function ($att) {
    $file = get_attached_file($att);
    if (!$file || !file_exists($file)) return '';
    $md = wp_get_attachment_metadata($att);
    foreach (array('woocommerce_single', 'medium_large', 'large', '1536x1536') as $s) {
        if (!empty($md['sizes'][$s]['file'])) { $p = dirname($file) . '/' . $md['sizes'][$s]['file']; if (file_exists($p)) return $p; }
    }
    return $file;
};

global $wpdb;
$ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status IN ('publish', 'draft', 'private', 'pending', 'future', 'trash') ORDER BY ID");
$tsv = fopen($dir . '/index.tsv', 'w');
$n = 0; $missing = 0; $by = array();
foreach ($ids as $pid) {
    $st = get_post_status($pid);
    $atts = array('main' => array((int) get_post_thumbnail_id($pid)), 'gallery' => array_map('intval', array_filter(explode(',', (string) get_post_meta($pid, '_product_image_gallery', true)))));
    foreach ($atts as $role => $list) {
        foreach ($list as $att) {
            if (!$att) continue;
            $p = $pick($att);
            if ($p === '') { $missing++; continue; }
            $n++;
            $name = $n . '.' . strtolower(pathinfo($p, PATHINFO_EXTENSION));
            symlink($p, $dir . '/img/' . $name);
            fputcsv($tsv, array($name, $pid, $st, $role, $att, (string) get_post_meta($pid, '_taf_art_code', true), str_replace(array("\t", "\n"), ' ', html_entity_decode((string) get_post_field('post_title', $pid, 'raw'), ENT_QUOTES, 'UTF-8'))), "\t");
            $by[$st] = ($by[$st] ?? 0) + 1;
        }
    }
}
fclose($tsv);
echo count($ids) . " products, $n pictures (" . implode(', ', array_map(function ($k, $v) { return "$k $v"; }, array_keys($by), $by)) . "), $missing missing on disk\n";

foreach (preg_split('/\s+/', trim((string) getenv('AF_MASTERS'))) as $key) {
    if ($key === '' || !function_exists('af_r2_object_url')) continue;
    $tmp = wp_tempnam('afm') . '.jpg';
    $r = wp_remote_get(af_r2_object_url($key, 600), array('timeout' => 300, 'stream' => true, 'filename' => $tmp));
    if (is_wp_error($r) || (int) wp_remote_retrieve_response_code($r) !== 200) {
        echo "master $key: could not fetch (" . (is_wp_error($r) ? $r->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code($r)) . ")\n"; @unlink($tmp); continue;
    }
    try {
        $im = new Imagick(); $im->pingImage($tmp); $W = $im->getImageWidth(); $H = $im->getImageHeight(); $im->clear();
        $s = 1000 / max($W, $H); $w = (int) round($W * $s); $h = (int) round($H * $s);
        $im = new Imagick(); $im->setOption('jpeg:size', $w . 'x' . $h); $im->readImage($tmp);
        $im->autoOrient(); $im->resizeImage($w, $h, Imagick::FILTER_LANCZOS, 1); $im->stripImage();
        $im->setImageFormat('jpeg'); $im->setImageCompressionQuality(85);
        file_put_contents($dir . '/masters/' . basename($key), $im->getImageBlob()); $im->clear();
        echo "master $key: {$W}x{$H} px, copied at {$w}x{$h}\n";
    } catch (\Throwable $e) { echo "master $key: " . $e->getMessage() . "\n"; }
    @unlink($tmp);
}
echo "=== END\n";
