<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. Which resized picture copies in uploads/ the site still uses.
 *
 * WordPress writes a picture's resized copies (name-300x300.jpg …) once and
 * records them in the attachment's metadata. Rebuilding the copies
 * (tools/regenerate-image-sizes.php) writes new files and rewrites the
 * metadata, but never deletes the files the old metadata named. So uploads/
 * holds three kinds of resized file:
 *   - named by some attachment's metadata: the site uses it — keep;
 *   - named by no metadata but written into page text, Elementor data or an
 *     option by URL: something links to it directly — keep;
 *   - named by neither: a leftover nobody can reach through WordPress.
 * Also: bytes per registered size, and sizes recorded in metadata that no
 * longer exist as a registered size.
 */
if (!defined('ABSPATH')) exit;
global $wpdb;
@set_time_limit(280);
$gb = function ($b) { return $b >= 1073741824 ? round($b / 1073741824, 2) . ' GB' : round($b / 1048576, 1) . ' MB'; };
$up = rtrim(wp_get_upload_dir()['basedir'], '/');

echo "=== registered sizes\n";
$reg = wp_get_registered_image_subsizes();
foreach ($reg as $k => $s) echo "  $k {$s['width']}x{$s['height']}" . ($s['crop'] ? ' crop' : '') . "\n";

// 1. Every file the metadata names.
$used = array(); $bySize = array(); $unreg = array();
$last = 0;
do {
    $rows = $wpdb->get_results($wpdb->prepare("SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key='_wp_attachment_metadata' AND post_id > %d ORDER BY post_id LIMIT 1000", $last));
    foreach ($rows as $r) {
        $last = (int) $r->post_id;
        $m = @unserialize($r->meta_value);
        if (!is_array($m) || empty($m['file'])) continue;
        $dir = dirname($m['file']); $dir = $dir === '.' ? '' : $dir . '/';
        $used[$m['file']] = 1;
        if (!empty($m['original_image'])) $used[$dir . $m['original_image']] = 1;
        foreach ((array) ($m['sizes'] ?? array()) as $name => $s) {
            if (empty($s['file'])) continue;
            $used[$dir . $s['file']] = $name;
            if (!isset($reg[$name])) $unreg[$name] = ($unreg[$name] ?? 0) + 1;
        }
    }
} while ($rows);
echo "\nfiles named by attachment metadata: " . count($used) . "\n";

// 2. Every resized file name written anywhere in the database by URL.
$linked = array();
$re = '~([A-Za-z0-9_@.\-]+-\d{2,5}x\d{2,5}\.(?:jpe?g|png|webp|gif))~i';
foreach (array(
    array("SELECT ID id, post_content v FROM {$wpdb->posts} WHERE ID > %d AND post_content REGEXP '-[0-9]+x[0-9]+[.]' ORDER BY ID LIMIT 300", 'ID'),
    array("SELECT meta_id id, meta_value v FROM {$wpdb->postmeta} WHERE meta_id > %d AND meta_key <> '_wp_attachment_metadata' AND meta_value REGEXP '-[0-9]+x[0-9]+[.]' ORDER BY meta_id LIMIT 300", 'meta_id'),
    array("SELECT option_id id, option_value v FROM {$wpdb->options} WHERE option_id > %d AND option_value REGEXP '-[0-9]+x[0-9]+[.]' ORDER BY option_id LIMIT 300", 'option_id'),
    array("SELECT meta_id id, meta_value v FROM {$wpdb->termmeta} WHERE meta_id > %d AND meta_value REGEXP '-[0-9]+x[0-9]+[.]' ORDER BY meta_id LIMIT 300", 'meta_id'),
) as $q) {
    $id = 0;
    do {
        $rows = $wpdb->get_results($wpdb->prepare($q[0], $id));
        foreach ($rows as $r) { $id = (int) $r->id; if (preg_match_all($re, stripslashes((string) $r->v), $mm)) foreach ($mm[1] as $f) $linked[strtolower($f)] = 1; }
    } while ($rows);
}
echo "resized file names linked from page text / settings: " . count($linked) . "\n";

// 3. Walk uploads.
$t = array('used' => array(0, 0), 'linked' => array(0, 0), 'leftover' => array(0, 0));
$leftDirs = array(); $leftDims = array(); $sample = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($up, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile()) continue;
    $name = $f->getFilename();
    if (!preg_match('~-(\d{2,5})x(\d{2,5})\.(jpe?g|png|webp|gif)$~i', $name, $dm)) continue;
    $rel = substr($f->getPathname(), strlen($up) + 1);
    $sz = $f->getSize();
    if (isset($used[$rel])) { $k = 'used'; $n = $used[$rel]; if ($n !== 1) { $bySize[$n][0] = ($bySize[$n][0] ?? 0) + 1; $bySize[$n][1] = ($bySize[$n][1] ?? 0) + $sz; } }
    elseif (isset($linked[strtolower($name)])) $k = 'linked';
    else {
        $k = 'leftover';
        $d = dirname($rel); $leftDirs[$d] = ($leftDirs[$d] ?? 0) + $sz;
        $dim = $dm[1] . 'x' . $dm[2]; $leftDims[$dim] = ($leftDims[$dim] ?? 0) + $sz;
        if (count($sample) < 12) $sample[] = $rel;
    }
    $t[$k][0]++; $t[$k][1] += $sz;
}
echo "\n=== resized files in uploads\n";
foreach ($t as $k => $v) echo sprintf("  %-9s %7d files  %s\n", $k, $v[0], $gb($v[1]));

echo "\n=== bytes the site's own copies take, per size\n";
uasort($bySize, function ($a, $b) { return $b[1] <=> $a[1]; });
foreach ($bySize as $n => $v) echo sprintf("  %-28s %6d files  %10s%s\n", $n, $v[0], $gb($v[1]), isset($reg[$n]) ? '' : '   (no longer a registered size)');

echo "\n=== leftovers by dimensions (top 15)\n";
arsort($leftDims); foreach (array_slice($leftDims, 0, 15, true) as $k => $v) echo "  $k  " . $gb($v) . "\n";
echo "\n=== leftovers by folder (top 10)\n";
arsort($leftDirs); foreach (array_slice($leftDirs, 0, 10, true) as $k => $v) echo "  $k  " . $gb($v) . "\n";
echo "\n=== sample leftovers\n"; foreach ($sample as $s) echo "  $s\n";
