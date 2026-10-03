<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * What each plugin costs the server on one page view.
 *
 * Owner, 3 Oct: the plugins "take too much time to load", and wants them
 * replaced with custom code. Which ones cost what has only been guessed at.
 * This measures it, for one page, with the page cache out of the way:
 *
 *   1. boot: how long each plugin's files take to load (the time between
 *      one 'plugin_loaded' and the next), and the theme's own files
 *   2. work: how long each plugin's hook callbacks run while WordPress
 *      builds the page (every registered callback is wrapped in a timer;
 *      a callback's time excludes the callbacks it fires in turn)
 *   3. database: the queries each plugin's code asked for, and their time
 *   4. the number of callbacks each plugin registers on the page
 *
 * Read-only: it builds the page the way a visitor's request would and
 * throws the HTML away. It must run as plain PHP (not wp eval-file), so the
 * timers are in place before WordPress loads the plugins:
 *
 *   cd public_html && php wp-content/themes/postero-child/tools/profile-plugins.php /cart/ theartframer.us
 */
$af_path = isset($argv[1]) ? $argv[1] : '/';
$af_host = isset($argv[2]) ? $argv[2] : 'theartframer.us';
$_SERVER['HTTP_HOST'] = $af_host; $_SERVER['SERVER_NAME'] = $af_host; $_SERVER['HTTPS'] = 'on'; $_SERVER['SERVER_PORT'] = '443';
$_SERVER['REQUEST_URI'] = $af_path; $_SERVER['REQUEST_METHOD'] = 'GET'; $_SERVER['SCRIPT_NAME'] = '/index.php'; $_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = 'index.php'; $_SERVER['REMOTE_ADDR'] = '127.0.0.1'; $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36 AF-profile';
$_SERVER['HTTP_ACCEPT'] = 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';
define('SAVEQUERIES', true);
define('WP_USE_THEMES', true);

$af_dir = __DIR__;
for ($i = 0; $i < 8; $i++) { if (file_exists($af_dir . '/wp-load.php')) break; $af_dir = dirname($af_dir); }
if (!file_exists($af_dir . '/wp-load.php')) { fwrite(STDERR, "wp-load.php not found above " . __DIR__ . "\n"); exit(1); }
chdir($af_dir);

$AF = array('t0' => microtime(true), 'mark' => microtime(true), 'boot' => array(), 'work' => array(), 'hooks' => array(),
            'stack' => array(), 'wrapped' => 0, 'wrappers' => array(), 'ownerCache' => array());

function af_owner_of_file($f) {
    $f = str_replace('\\', '/', (string) $f);
    if (preg_match('#/wp-content/mu-plugins/([^/]+)#', $f, $m)) return 'mu-plugin: ' . preg_replace('/\.php$/', '', $m[1]);
    if (preg_match('#/wp-content/plugins/([^/]+)#', $f, $m)) return 'plugin: ' . preg_replace('/\.php$/', '', $m[1]);
    if (preg_match('#/wp-content/themes/([^/]+)#', $f, $m)) return 'theme: ' . $m[1];
    if (strpos($f, '/wp-includes/') !== false || strpos($f, '/wp-admin/') !== false || preg_match('#/wp-(settings|load|config)\.php$#', $f)) return 'core';
    if ($f === '') return 'unknown';
    return 'other: ' . basename(dirname($f));
}
function af_owner_of_callable($fn) {
    global $AF;
    try {
        if ($fn instanceof Closure) { $r = new ReflectionFunction($fn); return af_owner_of_file($r->getFileName()); }
        if (is_string($fn)) {
            if (strpos($fn, '::') !== false) { list($c, $m) = explode('::', $fn, 2); $r = new ReflectionMethod($c, $m); return af_owner_of_file($r->getFileName()); }
            if (isset($AF['ownerCache'][$fn])) return $AF['ownerCache'][$fn];
            if (!function_exists($fn)) return 'unknown';
            $r = new ReflectionFunction($fn); $o = af_owner_of_file($r->getFileName()); if (!$r->isInternal()) $AF['ownerCache'][$fn] = $o; return $o;
        }
        if (is_array($fn) && count($fn) === 2) { $r = new ReflectionMethod($fn[0], $fn[1]); return af_owner_of_file($r->getFileName()); }
        if (is_object($fn) && method_exists($fn, '__invoke')) { $r = new ReflectionMethod($fn, '__invoke'); return af_owner_of_file($r->getFileName()); }
    } catch (Throwable $e) {}
    return 'unknown';
}
// Every registered callback is replaced by a timer around it. Time spent in
// hooks a callback fires itself is subtracted, so each owner is charged for
// its own code only.
function af_wrap_all() {
    global $wp_filter, $AF;
    foreach ($wp_filter as $tag => $hook) {
        if (!($hook instanceof WP_Hook)) continue;
        if (in_array($tag, array('all', 'plugins_loaded', 'plugin_loaded', 'muplugins_loaded', 'setup_theme', 'after_setup_theme', 'shutdown'), true)) continue;
        foreach ($hook->callbacks as $prio => $cbs) {
            foreach ($cbs as $idx => $cb) {
                $fn = $cb['function'];
                if ($fn instanceof Closure && isset($AF['wrappers'][spl_object_id($fn)])) continue;
                $owner = af_owner_of_callable($fn);
                $AF['hooks'][$owner] = (isset($AF['hooks'][$owner]) ? $AF['hooks'][$owner] : 0) + 1;
                $w = function () use ($fn, $owner) {
                    global $AF;
                    $args = func_get_args();
                    $t = microtime(true); $AF['stack'][] = 0.0;
                    try { return call_user_func_array($fn, $args); }
                    finally {
                        $d = microtime(true) - $t; $kids = array_pop($AF['stack']);
                        if ($AF['stack']) $AF['stack'][count($AF['stack']) - 1] += $d;
                        if (!isset($AF['work'][$owner])) $AF['work'][$owner] = array('t' => 0.0, 'n' => 0);
                        $AF['work'][$owner]['t'] += ($d - $kids); $AF['work'][$owner]['n']++;
                    }
                };
                $AF['wrappers'][spl_object_id($w)] = true;
                $hook->callbacks[$prio][$idx]['function'] = $w;
                $AF['wrapped']++;
            }
        }
    }
}

// Timers that must exist before WordPress loads a single plugin: WordPress
// builds its hook table from a pre-filled $wp_filter.
$wp_filter = array();
$wp_filter['muplugins_loaded'][PHP_INT_MAX]['af_prof_mu'] = array('function' => function () { global $AF; $now = microtime(true); $AF['boot']['core + mu-plugins'] = $now - $AF['t0']; $AF['mark'] = $now; }, 'accepted_args' => 0);
$wp_filter['plugin_loaded'][PHP_INT_MAX]['af_prof_pl'] = array('function' => function ($plugin) { global $AF; $now = microtime(true); $o = af_owner_of_file($plugin); $AF['boot'][$o] = (isset($AF['boot'][$o]) ? $AF['boot'][$o] : 0) + ($now - $AF['mark']); $AF['mark'] = $now; }, 'accepted_args' => 1);
$wp_filter['plugins_loaded'][PHP_INT_MIN]['af_prof_pls0'] = array('function' => function () { global $AF; $AF['mark'] = microtime(true); af_wrap_all(); }, 'accepted_args' => 0);
$wp_filter['plugins_loaded'][PHP_INT_MAX]['af_prof_pls1'] = array('function' => function () { global $AF; $now = microtime(true); $AF['boot']['(plugins_loaded callbacks)'] = $now - $AF['mark']; $AF['mark'] = $now; }, 'accepted_args' => 0);
$wp_filter['setup_theme'][PHP_INT_MAX]['af_prof_st'] = array('function' => function () { global $AF; $AF['mark'] = microtime(true); }, 'accepted_args' => 0);
$wp_filter['after_setup_theme'][PHP_INT_MIN]['af_prof_ast'] = array('function' => function () { global $AF; $now = microtime(true); $AF['boot']['theme files (parent + child)'] = $now - $AF['mark']; $AF['mark'] = $now; af_wrap_all(); }, 'accepted_args' => 0);

$T = array();
$T['start'] = microtime(true);
require $af_dir . '/wp-load.php';
$T['loaded'] = microtime(true);
af_wrap_all();
wp();
$T['query'] = microtime(true);
af_wrap_all();
ob_start();
try { require ABSPATH . WPINC . '/template-loader.php'; } catch (Throwable $e) { echo "\n[render error] " . $e->getMessage() . "\n"; }
$html = ob_get_clean();
$T['render'] = microtime(true);

$ms = function ($s) { return sprintf('%7.1f ms', $s * 1000); };
echo "=== PROFILE {$af_path} (" . number_format(strlen($html) / 1024, 0) . " KB of HTML, peak memory " . number_format(memory_get_peak_usage() / 1048576, 1) . " MB, " . $AF['wrapped'] . " callbacks timed) ===\n";
echo "  load WordPress + plugins + theme: " . $ms($T['loaded'] - $T['start']) . "\n";
echo "  work out the page (query):        " . $ms($T['query'] - $T['loaded']) . "\n";
echo "  build the HTML (template):        " . $ms($T['render'] - $T['query']) . "\n";
echo "  TOTAL:                            " . $ms($T['render'] - $T['start']) . "\n";

echo "\n--- 1. boot: loading each plugin's files (before any page work) ---\n";
arsort($AF['boot']);
foreach ($AF['boot'] as $o => $t) printf("  %s  %s\n", $ms($t), $o);

echo "\n--- 2. work: time inside each owner's hook callbacks while building the page (own code only) ---\n";
uasort($AF['work'], function ($a, $b) { return $b['t'] <=> $a['t']; });
$sum = 0; foreach ($AF['work'] as $w) $sum += $w['t'];
foreach ($AF['work'] as $o => $w) if ($w['t'] >= 0.0005) printf("  %s  %5d calls  %s\n", $ms($w['t']), $w['n'], $o);
echo "  (" . $ms($sum) . " in all timed callbacks)\n";

echo "\n--- 3. database: queries by the owner that asked for them ---\n";
global $wpdb;
$byq = array(); $qt = 0; $qn = 0;
if (!empty($wpdb->queries)) {
    foreach ($wpdb->queries as $q) {
        $qn++; $qt += $q[1];
        $owner = 'core';
        foreach (array_reverse(explode(', ', (string) $q[2])) as $fnname) {
            $fnname = trim($fnname);
            if ($fnname === '' || strpos($fnname, 'require') === 0 || strpos($fnname, 'include') === 0 || $fnname === 'do_action' || $fnname === 'apply_filters' || strpos($fnname, 'WP_Hook') === 0 || strpos($fnname, '{closure}') !== false) continue;
            $cand = strpos($fnname, '->') !== false ? str_replace('->', '::', $fnname) : $fnname;
            $o = af_owner_of_callable($cand);
            if ($o !== 'unknown' && $o !== 'core') { $owner = $o; break; }
        }
        if (!isset($byq[$owner])) $byq[$owner] = array('n' => 0, 't' => 0.0);
        $byq[$owner]['n']++; $byq[$owner]['t'] += $q[1];
    }
}
uasort($byq, function ($a, $b) { return $b['t'] <=> $a['t']; });
foreach ($byq as $o => $v) printf("  %s  %4d queries  %s\n", $ms($v['t']), $v['n'], $o);
echo "  ($qn queries, " . $ms($qt) . " in all)\n";

echo "\n--- 4. callbacks registered on this page, by owner ---\n";
arsort($AF['hooks']);
foreach ($AF['hooks'] as $o => $n) printf("  %5d  %s\n", $n, $o);
// When no plugin loaded, say why (the numbers above then measure core alone).
if (empty($AF['boot']) || count($AF['boot']) < 4) {
    $act = get_option('active_plugins');
    echo "\n--- why so few owners? ---\n";
    echo '  wp_installing(): ' . var_export(function_exists('wp_installing') ? wp_installing() : null, true) . ' | WP_INSTALLING: ' . var_export(defined('WP_INSTALLING') ? WP_INSTALLING : '(undefined)', true) . ' | SHORTINIT: ' . var_export(defined('SHORTINIT') ? SHORTINIT : '(undefined)', true) . "\n";
    echo '  active_plugins option: ' . (is_array($act) ? count($act) . ' entries, first: ' . implode(', ', array_slice($act, 0, 3)) : var_export($act, true)) . "\n";
    echo '  active and valid now: ' . count(function_exists('wp_get_active_and_valid_plugins') ? wp_get_active_and_valid_plugins() : array()) . ' | paused: ' . var_export(function_exists('wp_paused_plugins') ? array_keys((array) wp_paused_plugins()->get_all()) : null, true) . "\n";
    echo '  template/stylesheet: ' . get_option('template') . ' / ' . get_option('stylesheet') . ' | theme files loaded: ' . (function_exists('af_review_rows') ? 'yes' : 'no') . "\n";
    echo '  DB_NAME set: ' . (defined('DB_NAME') ? 'yes (' . strlen(DB_NAME) . ' chars)' : 'no') . ' | table prefix: ' . $GLOBALS['table_prefix'] . ' | WP_CONTENT_DIR: ' . (defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : '?') . ' | WP_PLUGIN_DIR: ' . (defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : '?') . "\n";
    echo '  siteurl/home: ' . get_option('siteurl') . ' / ' . get_option('home') . ' | object cache drop-in: ' . var_export(wp_using_ext_object_cache(), true) . "\n";
    echo '  mu-plugins loaded: ' . implode(', ', array_map('basename', function_exists('wp_get_mu_plugins') ? wp_get_mu_plugins() : array())) . "\n";
    echo '  constants: WP_CLI ' . var_export(defined('WP_CLI'), true) . ', WP_ADMIN ' . var_export(defined('WP_ADMIN'), true) . ', DOING_CRON ' . var_export(defined('DOING_CRON'), true) . ', WP_RECOVERY? ' . var_export(function_exists('wp_is_recovery_mode') ? wp_is_recovery_mode() : null, true) . "\n";
}
echo "=== END PROFILE {$af_path} ===\n";
exit(0);
