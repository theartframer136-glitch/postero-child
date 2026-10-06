<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. Does the crawl guard still need wp-content/plugins/af-crawl-guard?
 *
 * Owner, 6 Oct: the switched-off plugin folders go after a three-day wait
 * (tools/remove-plugins.sh). The crawl guard now loads from wp-config.php
 * (ops/harden-crawl-budget.php retired its loader plugin and kept the folder
 * for rollback), so the old loader plugin's folder is on that list. Before it
 * goes, this shows: what wp-config.php's AF-CRAWL-GUARD block loads, whether
 * the guard file or any PHP prepend reaches into the plugin folder, whether
 * the loader plugin is switched on, what the folder holds, and whether the
 * guard answers a request today with the plugin off.
 *
 * Nothing is written: files are only read, and the one probe is a GET for a
 * made-up image under wp-content (the guard answers it before WordPress).
 * Secrets in wp-config.php are never printed: only lines naming the guard or
 * the plugins folder, and never one that names a key, salt or database.
 *
 * Run: wp eval-file tools/diag-crawl-guard-folder.php --allow-root
 */
if (!defined('ABSPATH')) exit(1);

$root    = rtrim(ABSPATH, '/\\');
$content = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : $root . '/wp-content';
$plugdir = defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : $content . '/plugins';
$folder  = $plugdir . '/af-crawl-guard';
$guard   = $content . '/af-crawl-guard.php';

function af_cg_rel($p) {
    global $root;
    $p = str_replace($root . '/', '<wp-root>/', (string) $p);
    return preg_replace('#/home/[^/]+/#', '/home/…/', $p);
}
function af_cg_into_folder($s) {
    return (bool) preg_match('#plugins/af-crawl-guard|WP_PLUGIN_DIR|plugin_dir_path|plugins_url#i', (string) $s);
}
$verdict = array();

echo "=== wp-config.php\n";
$wpc = null;
foreach (array($root . '/wp-config.php', dirname($root) . '/wp-config.php') as $c) if (is_file($c)) { $wpc = $c; break; }
if ($wpc === null) { echo "  not found\n"; $verdict[] = 'wp-config.php not found'; }
else {
    $src = (string) @file_get_contents($wpc);
    echo '  ' . af_cg_rel($wpc) . ' (' . strlen($src) . " bytes)\n";
    if (!preg_match('#/\* BEGIN AF-CRAWL-GUARD \*/(.*?)/\* END AF-CRAWL-GUARD \*/#s', $src, $m)) {
        echo "  AF-CRAWL-GUARD block: MISSING\n";
        $verdict[] = 'wp-config.php has no crawl-guard loader';
    } else {
        echo '  AF-CRAWL-GUARD block: ' . af_cg_rel(trim($m[1])) . "\n";
        preg_match_all("#(?:require|include)(?:_once)?\s*\(?\s*'([^']+)'#", $m[1], $req);
        foreach ($req[1] as $t) {
            echo '    loads ' . af_cg_rel($t) . ': ' . (is_file($t) ? 'exists' : 'MISSING') . (af_cg_into_folder($t) ? '  <- INSIDE THE PLUGIN FOLDER' : '') . "\n";
            if (af_cg_into_folder($t)) $verdict[] = 'wp-config.php loads from the plugin folder';
            if (!is_file($t)) $verdict[] = 'the file wp-config.php loads is missing';
        }
        if (!$req[1]) { echo "    loads nothing it can name\n"; $verdict[] = 'could not read what the loader loads'; }
        if (realpath($req[1][0] ?? '') !== realpath($guard)) echo '    note: not ' . af_cg_rel($guard) . "\n";
    }
    foreach (explode("\n", $src) as $i => $l) {
        if (preg_match('/DB_|KEY|SALT|PASSWORD|SECRET|AUTH|NONCE|TOKEN/i', $l)) continue;
        if (strpos($l, 'AF-CRAWL-GUARD') !== false) continue;
        if (preg_match('#af-crawl-guard|WP_PLUGIN_DIR|WP_CONTENT_DIR|/plugins#i', $l)) {
            echo '  line ' . ($i + 1) . ': ' . af_cg_rel(trim(substr($l, 0, 200))) . "\n";
            if (af_cg_into_folder($l)) $verdict[] = 'wp-config.php line ' . ($i + 1) . ' names the plugin folder';
        }
    }
}

echo "\n=== the guard file wp-config.php loads\n";
if (!is_file($guard)) { echo '  ' . af_cg_rel($guard) . ": MISSING\n"; }
else {
    $g = (string) @file_get_contents($guard);
    $theme = get_stylesheet_directory() . '/ops/mu/af-crawl-guard.php';
    echo '  ' . af_cg_rel($guard) . ': ' . strlen($g) . ' bytes, md5 ' . md5($g) . ', modified ' . gmdate('Y-m-d H:i', filemtime($guard)) . " UTC\n";
    echo '  same as the theme copy (ops/mu/af-crawl-guard.php): ' . (is_file($theme) ? (md5_file($theme) === md5($g) ? 'yes' : 'no (the deploy strips the search rule when af_guard_no_search=' . json_encode(get_option('af_guard_no_search')) . ')') : 'theme copy missing') . "\n";
    $hits = 0;
    foreach (explode("\n", $g) as $i => $l) {
        $code = preg_replace('#^\s*(\*|//|/\*).*#', '', $l); // comment lines do not load anything
        if (preg_match('/\b(require|include)(_once)?\b/', $code) || af_cg_into_folder($code)) {
            echo '  line ' . ($i + 1) . ': ' . trim(substr($l, 0, 160)) . "\n"; $hits++;
            if (af_cg_into_folder($code)) $verdict[] = 'the guard file names the plugin folder (line ' . ($i + 1) . ')';
        }
    }
    if (!$hits) echo "  loads no other file and never names the plugins folder\n";
}

echo "\n=== anything PHP loads before WordPress\n";
echo '  auto_prepend_file=' . (ini_get('auto_prepend_file') ? af_cg_rel(ini_get('auto_prepend_file')) : '(none)') . "\n";
if (af_cg_into_folder(ini_get('auto_prepend_file'))) $verdict[] = 'auto_prepend_file points into the plugin folder';
foreach (array('.user.ini', 'php.ini', '.htaccess') as $f) {
    $p = $root . '/' . $f;
    if (!is_file($p)) { echo "  $f: none\n"; continue; }
    $n = 0;
    foreach (explode("\n", (string) @file_get_contents($p)) as $i => $l) if (stripos($l, 'af-crawl-guard') !== false || stripos($l, 'prepend') !== false) {
        echo "  $f line " . ($i + 1) . ': ' . af_cg_rel(trim(substr($l, 0, 160))) . "\n"; $n++;
        if (af_cg_into_folder($l)) $verdict[] = "$f names the plugin folder";
    }
    if (!$n) echo "  $f: nothing about the guard or a prepend\n";
}

echo "\n=== the loader plugin\n";
$active = (array) get_option('active_plugins', array());
$on = in_array('af-crawl-guard/af-crawl-guard.php', $active, true);
echo '  switched on: ' . ($on ? 'YES' : 'no') . ' (active plugins: ' . implode(', ', array_map(function ($p) { return dirname($p); }, $active)) . ")\n";
if ($on) $verdict[] = 'the loader plugin is still switched on';
if (!is_dir($folder)) { echo '  ' . af_cg_rel($folder) . ": not there\n"; }
else {
    echo '  ' . af_cg_rel($folder) . (is_link($folder) ? ' (a LINK)' : '') . ":\n";
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) echo '    ' . substr($f->getPathname(), strlen($folder) + 1) . ' ' . $f->getSize() . ' bytes, modified ' . gmdate('Y-m-d H:i', $f->getMTime()) . " UTC\n";
    $pf = $folder . '/af-crawl-guard.php';
    if (is_file($pf)) {
        $code = (string) @file_get_contents($pf);
        $body = trim(preg_replace('#/\*.*?\*/#s', '', preg_replace('#^<\?php#', '', $code)));
        echo '    af-crawl-guard.php without its header: ' . str_replace("\n", ' ', $body) . "\n";
        echo '    only loads ' . af_cg_rel($guard) . ': ' . (preg_match("#^\\\$af = WP_CONTENT_DIR \. '/af-crawl-guard\.php';\s*if \( is_file\( \\\$af \) \) \{ require \\\$af; \}$#", $body) ? 'yes (the stub ops/harden-crawl-budget.php writes)' : 'NOT the expected stub') . "\n";
    }
}

echo "\n=== does the guard answer today, with the plugin off?\n";
$url = home_url('/wp-content/af-guard-probe-' . substr(md5(uniqid('', true)), 0, 10) . '.png');
$r = wp_remote_get($url, array('timeout' => 20, 'redirection' => 0, 'headers' => array('Cache-Control' => 'no-cache')));
if (is_wp_error($r)) { echo '  probe failed: ' . $r->get_error_message() . "\n"; }
else {
    $mark = (string) wp_remote_retrieve_header($r, 'x-af-guard');
    echo '  GET a made-up image under wp-content: HTTP ' . wp_remote_retrieve_response_code($r) . ', X-AF-Guard: ' . ($mark !== '' ? $mark : '(none)') . "\n";
    echo '  ' . ($mark !== '' ? 'the guard answered before WordPress, loaded by wp-config.php' : 'no guard marker (the edge may have answered; not proof either way)') . "\n";
}

echo "\n=== verdict\n";
echo $verdict ? '  KEEP THE FOLDER: ' . implode('; ', array_unique($verdict)) . "\n"
              : "  The plugin folder is not needed: the guard loads from wp-config.php and nothing reaches into the folder.\n"
                . "  (If wp-config.php ever stops being writable, ops/harden-crawl-budget.php writes the loader plugin again.)\n";
echo "=== END\n";
