<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. Every backup-like thing inside the site folder and the server's
 * temporary folder: folders named like backup/bak/old/copy, archives (.wpress,
 * .zip, .tar, .gz, .sql, .bak, .rar, .7z) and files whose name says backup,
 * with size and date, biggest first.
 */
if (!defined('ABSPATH')) exit;
$gb = function ($b) { return $b >= 1073741824 ? round($b / 1073741824, 2) . ' GB' : ($b >= 1048576 ? round($b / 1048576, 1) . ' MB' : round($b / 1024, 1) . ' KB'); };
$dirsize = function ($d) { $b = 0; $n = 0; try { foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS)) as $f) { if ($f->isFile()) { $b += $f->getSize(); $n++; } } } catch (\Throwable $e) {} return array($b, $n); };
$root = rtrim(ABSPATH, '/');
$hits = array(); $dirs = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
foreach ($it as $f) {
    $name = $f->getFilename(); $path = substr($f->getPathname(), strlen($root) + 1);
    if ($f->isDir()) {
        if (preg_match('~backup|^bak$|[-_.]bak$|[-_]old$|^old[-_]|[-_]copy$|ai1wm|updraft|backwpup|duplicator|wpvivid|snapshot|quarantine|upgrade-temp~i', $name)) $dirs[$path] = 1;
        continue;
    }
    if (preg_match('~\.(wpress|zip|tar|tgz|gz|sql|bak|rar|7z|old|orig)$~i', $name) || preg_match('~backup|[-_.]bak[-_.]|-orig\.|\.backup~i', $name)) {
        $hits[$path] = array($f->getSize(), $f->getMTime());
    }
}
// Skip archives that live inside a reported folder: the folder line covers them.
echo "=== backup-like folders (size, files)\n";
$rows = array(); foreach (array_keys($dirs) as $d) { $rows[$d] = $dirsize($root . '/' . $d); }
uasort($rows, function ($a, $b) { return $b[0] <=> $a[0]; });
foreach ($rows as $d => $s) echo sprintf("  %10s %7d files  %s\n", $gb($s[0]), $s[1], $d);
if (!$rows) echo "  none\n";
echo "\n=== backup-like files (biggest first; first 60)\n";
uasort($hits, function ($a, $b) { return $b[0] <=> $a[0]; });
$tot = 0; $i = 0;
foreach ($hits as $p => $h) { $tot += $h[0]; if ($i++ < 60) echo sprintf("  %10s  %s  %s\n", $gb($h[0]), date('Y-m-d', $h[1]), $p); }
echo '  ' . count($hits) . ' files, ' . $gb($tot) . " in all\n";
echo "\n=== server temporary folder\n";
foreach (glob('/tmp/af-backup-*') ?: array() as $p) { $s = $dirsize($p); echo sprintf("  %10s %7d files  %s\n", $gb($s[0]), $s[1], $p); }
