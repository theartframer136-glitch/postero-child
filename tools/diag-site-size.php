<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. The whole hosting account by size and file count: the site root
 * and each folder in it, wp-content folder by folder, the uploads by year and
 * by kind of file, the database table by table, everything else in the
 * account's home folder, and the server's temporary backups. Hostinger plans
 * cap both disk space and the number of files (inodes), so both are counted.
 */
if (!defined('ABSPATH')) exit;
$gb = function ($b) { return $b >= 1073741824 ? round($b / 1073741824, 2) . ' GB' : round($b / 1048576, 1) . ' MB'; };
$size = function ($dir) {
    $n = 0; $b = 0;
    if (!is_dir($dir)) return array(is_file($dir) ? filesize($dir) : 0, 1);
    try {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($it as $f) { $n++; if ($f->isFile()) $b += $f->getSize(); }
    } catch (\Throwable $e) {}
    return array($b, $n);
};
$row = function ($label, $s) use ($gb) { echo sprintf("  %-46s %10s %9s files\n", $label, $gb($s[0]), number_format($s[1])); };
$list = function ($dir, $min = 1048576) use ($size, $row) {
    $rows = array();
    foreach (array_merge(glob($dir . '/*') ?: array(), glob($dir . '/.[!.]*') ?: array()) as $p) $rows[basename($p) . (is_dir($p) ? '/' : '')] = $size($p);
    uasort($rows, function ($a, $b) { return $b[0] <=> $a[0]; });
    $small = array(0, 0);
    foreach ($rows as $k => $s) { if ($s[0] >= $min) $row($k, $s); else { $small[0] += $s[0]; $small[1] += $s[1]; } }
    if ($small[1]) $row('(everything smaller)', $small);
};

$root = rtrim(ABSPATH, '/');
$home = getenv('HOME') ?: dirname(dirname(dirname($root)));

echo "\n=== the whole hosting account (home folder)\n";
$row('TOTAL ' . $home, $size($home));
$list($home);

echo "\n=== websites in the account\n";
foreach (glob($home . '/websites/*', GLOB_ONLYDIR) ?: array() as $w) $row(basename($w) . ($w . '/public_html' === $root ? '  <- theartframer.us' : ''), $size($w));
foreach (glob($home . '/domains/*', GLOB_ONLYDIR) ?: array() as $w) $row('domains/' . basename($w), $size($w));

echo "\n=== theartframer.us site folder\n";
$row('TOTAL ' . $root, $size($root));
$list($root);

echo "\n=== wp-content\n";
$list(WP_CONTENT_DIR);

echo "\n=== uploads by year / folder\n";
$up = wp_get_upload_dir()['basedir'];
$list($up, 512 * 1024);
echo "\n=== uploads/2026 by month\n";
$list($up . '/2026', 1048576);

echo "\n=== plugins (biggest)\n";
$list(WP_CONTENT_DIR . '/plugins', 5 * 1048576);

echo "\n=== database\n";
global $wpdb;
$t = $wpdb->get_results($wpdb->prepare("SELECT table_name n, data_length+index_length s, table_rows r FROM information_schema.tables WHERE table_schema=%s ORDER BY s DESC", DB_NAME));
$tot = 0; foreach ($t as $x) $tot += $x->s;
echo '  TOTAL ' . $gb($tot) . ' in ' . count($t) . " tables\n";
foreach (array_slice($t, 0, 15) as $x) echo sprintf("  %-46s %10s %12s rows\n", $x->n, $gb($x->s), number_format((int) $x->r));

echo "\n=== temporary backups on the server (/tmp)\n";
foreach (glob('/tmp/af-backup-*') ?: array() as $p) { $row($p, $size($p)); $list($p); }
if (!glob('/tmp/af-backup-*')) echo "  none\n";

echo "\n=== disk (as PHP sees it)\n";
$free = @disk_free_space($root); $all = @disk_total_space($root);
echo '  free ' . ($free ? $gb($free) : '?') . ' of ' . ($all ? $gb($all) : '?') . " on the shared disk (the plan's own limit is in hPanel)\n";
