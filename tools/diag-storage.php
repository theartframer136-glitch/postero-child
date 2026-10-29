<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. Where the hosting space goes: the All-in-One WP Migration backup
 * files (name, size, date, whether the web can reach them), the uploads folder
 * (size, file count, how much is resized copies), and the biggest folders.
 */
if (!defined('ABSPATH')) exit;
$gb = function ($b) { return round($b / 1073741824, 2) . ' GB'; };
$walk = function ($dir) {
    $n = 0; $b = 0;
    if (!is_dir($dir)) return array(0, 0);
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) if ($f->isFile()) { $n++; $b += $f->getSize(); }
    return array($n, $b);
};

echo "=== All-in-One WP Migration backups (wp-content/ai1wm-backups)\n";
$d = WP_CONTENT_DIR . '/ai1wm-backups';
if (is_dir($d)) {
    foreach (scandir($d) as $f) {
        if ($f === '.' || $f === '..') continue;
        $p = "$d/$f";
        echo sprintf("  %-70s %10s  %s\n", $f, is_file($p) ? $gb(filesize($p)) : '(dir)', date('Y-m-d H:i', filemtime($p)));
    }
    echo '  .htaccess blocks web access: ' . (is_file("$d/.htaccess") && stripos(file_get_contents("$d/.htaccess"), 'deny') !== false ? 'yes' : 'NO') . "\n";
    echo '  index.php present: ' . (is_file("$d/index.php") ? 'yes' : 'no') . "\n";
} else echo "  folder not found\n";
if (!function_exists('is_plugin_active')) require_once ABSPATH . 'wp-admin/includes/plugin.php';
foreach (array('all-in-one-wp-migration/all-in-one-wp-migration.php', 'amazon-s3-and-cloudfront/wordpress-s3.php', 'ilab-media-tools/ilab-media-tools.php', 'media-cloud/ilab-media-tools.php', 'bunnycdn/bunnycdn.php') as $pl)
    echo "  plugin $pl: " . (is_plugin_active($pl) ? 'ACTIVE' : (file_exists(WP_PLUGIN_DIR . '/' . $pl) ? 'installed, off' : 'not installed')) . "\n";

echo "\n=== uploads\n";
$up = wp_get_upload_dir()['basedir'];
list($n, $b) = $walk($up);
echo "  total: $n files, " . $gb($b) . "\n";
// Resized copies: name-123x456.ext
$rn = 0; $rb = 0; $on = 0; $ob = 0; $types = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($up, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile()) continue;
    $ext = strtolower(pathinfo($f->getFilename(), PATHINFO_EXTENSION));
    $types[$ext] = (isset($types[$ext]) ? $types[$ext] : 0) + $f->getSize();
    if (preg_match('~-\d+x\d+\.[a-z]+$~i', $f->getFilename())) { $rn++; $rb += $f->getSize(); }
    elseif (preg_match('~-(scaled|rotated)\.|\.bak$|-orig~i', $f->getFilename())) { $on++; $ob += $f->getSize(); }
}
echo "  resized copies (name-WxH): $rn files, " . $gb($rb) . "\n";
echo "  -scaled/-rotated/originals: $on files, " . $gb($ob) . "\n";
arsort($types); echo '  by type: '; foreach (array_slice($types, 0, 8, true) as $k => $v) echo "$k " . $gb($v) . '  '; echo "\n";
global $wpdb;
echo '  image attachments in the media library: ' . $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='attachment' AND post_mime_type LIKE 'image/%'") . "\n";
$top = array();
foreach (glob($up . '/*', GLOB_ONLYDIR) as $sd) { list($x, $y) = $walk($sd); $top[basename($sd)] = $y; }
arsort($top); echo '  biggest upload folders: '; foreach (array_slice($top, 0, 8, true) as $k => $v) echo "$k " . $gb($v) . '  '; echo "\n";

echo "\n=== other big folders under wp-content\n";
foreach (glob(WP_CONTENT_DIR . '/*', GLOB_ONLYDIR) as $sd) {
    if (in_array(basename($sd), array('uploads'), true)) continue;
    list($x, $y) = $walk($sd);
    if ($y > 100 * 1048576) echo '  ' . basename($sd) . ': ' . $gb($y) . "\n";
}
echo "\n=== database size\n";
echo '  ' . $gb((float) $wpdb->get_var($wpdb->prepare("SELECT SUM(data_length+index_length) FROM information_schema.tables WHERE table_schema=%s", DB_NAME))) . "\n";
