<?php
/**
 * Tests tools/port-preview-dropin.php (the preview file put in place as
 * wp-content/db.php by tools/port-preview-ctl.sh), each case in its own PHP
 * process, with WordPress's hook functions stubbed and the file included the
 * way WordPress includes db.php (inside require_wp_db()):
 *   - the copy in the theme (not filled in) does nothing, even with a guess
 *     at the placeholder as the secret
 *   - filled in: no secret, a wrong one, or the right one after the end time
 *     do nothing; nothing is hooked and no $wpdb is set
 *   - the right secret (as ?af_pv= or as the header) leaves the named plugins
 *     out of active_plugins and only those, prints the marker, turns the
 *     element cache off, makes Elementor print CSS inline, and stops every
 *     _elementor post meta and Elementor option write while letting others by
 *
 * Run: php tools/test-port-preview-dropin.php
 */
$src = __DIR__ . '/port-preview-dropin.php';
$TOKEN = str_repeat('ab12', 12);

if (isset($argv[1]) && $argv[1] === 'case') {
    // ---- child: one request ----
    list(, , $file, $get, $server) = $argv;
    $_GET = json_decode($get, true);
    $_SERVER = array_merge($_SERVER, json_decode($server, true));
    $GLOBALS['hooks'] = array();
    function add_filter($h, $cb, $p = 10, $n = 1) { $GLOBALS['hooks'][$h][] = $cb; }
    function add_action($h, $cb, $p = 10, $n = 1) { $GLOBALS['hooks'][$h][] = $cb; }
    function esc_attr($s) { return htmlspecialchars($s, ENT_QUOTES); }
    function apply($h, ...$args) { foreach ($GLOBALS['hooks'][$h] ?? array() as $cb) { $args[0] = $cb(...$args); } return $args[0]; }
    function require_wp_db() { global $wpdb; require_once $GLOBALS['dropin']; return isset($wpdb); }
    $GLOBALS['dropin'] = $file;
    $out = array('wpdb' => require_wp_db(), 'hooks' => array_keys($GLOBALS['hooks']));
    if ($GLOBALS['hooks']) {
        $out['active'] = apply('option_active_plugins', array('elementor/elementor.php', 'elementor-pro/elementor-pro.php', 'woocommerce/woocommerce.php', 'elementor-pro-extra/x.php'));
        $out['element_cache'] = apply('pre_option_elementor_experiment-e_element_cache', false);
        $out['element_cache_ttl'] = apply('pre_option_elementor_element_cache_ttl', false);
        $out['print_method'] = apply('pre_option_elementor_css_print_method', false);
        $out['meta_css'] = apply('update_post_metadata', null, 75, '_elementor_css', array());
        $out['meta_cache'] = apply('add_post_metadata', null, 75, '_elementor_element_cache', 'x');
        $out['meta_del'] = apply('delete_post_metadata', null, 75, '_elementor_css', '');
        $out['meta_other'] = apply('update_post_metadata', null, 75, '_price', '10');
        $out['opt_el'] = apply('pre_update_option', 'new', 'elementor_css_print_method', 'old');
        $out['opt_el2'] = apply('pre_update_option', 'new', '_elementor_global_css', 'old');
        $out['opt_other'] = apply('pre_update_option', 'new', 'woocommerce_version', 'old');
        ob_start(); foreach ($GLOBALS['hooks']['wp_head'] as $cb) $cb(); $out['head'] = trim(ob_get_clean());
        $out['donotcache'] = defined('DONOTCACHEPAGE') && defined('LSCACHE_NO_CACHE');
    }
    echo json_encode($out);
    exit;
}

// ---- parent ----
$fails = 0; $n = 0;
$check = function ($what, $ok) use (&$fails, &$n) { $n++; if (!$ok) $fails++; echo ($ok ? 'ok   ' : 'FAIL ') . $what . "\n"; };
$fill = function ($token, $until) use ($src) {
    $f = tempnam(sys_get_temp_dir(), 'afpv');
    file_put_contents($f, str_replace(array('__AF_PREVIEW_TOKEN__', '__AF_PREVIEW_UNTIL__'), array($token, (string) $until), file_get_contents($src)));
    return $f;
};
$run = function ($file, $get = array(), $server = array()) {
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' case ' . escapeshellarg($file) . ' ' . escapeshellarg(json_encode((object) $get)) . ' ' . escapeshellarg(json_encode((object) $server)) . ' 2>&1';
    $o = shell_exec($cmd);
    $j = json_decode((string) $o, true);
    if (!is_array($j)) { echo "  child said: $o\n"; return array('hooks' => array('?'), 'wpdb' => null); }
    return $j;
};
$inert = function ($r) { return $r['hooks'] === array() && $r['wpdb'] === false; };

$check('the file carries the mark port-preview-ctl.sh looks for', strpos(file_get_contents($src), 'AF-PORT-PREVIEW-DROPIN') !== false);
$lint = shell_exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($src) . ' 2>&1');
$check('the file parses', strpos((string) $lint, 'No syntax errors') !== false);

$check('unfilled copy: nothing with no secret', $inert($run($src)));
$check('unfilled copy: nothing with the placeholder as the secret', $inert($run($src, array('af_pv' => '__AF_PREVIEW_TOKEN__', 'af_skip' => 'elementor-pro'))));
$half = tempnam(sys_get_temp_dir(), 'afpv');
file_put_contents($half, str_replace('__AF_PREVIEW_UNTIL__', (string) (time() + 600), file_get_contents($src)));
$check('end time filled, secret not: nothing with the placeholder as the secret', $inert($run($half, array('af_pv' => '__AF_PREVIEW_TOKEN__', 'af_skip' => 'elementor-pro'))));

$live = $fill($TOKEN, time() + 600);
$check('filled: nothing for a visitor (no secret)', $inert($run($live)));
$check('filled: nothing for a wrong secret', $inert($run($live, array('af_pv' => strrev($TOKEN), 'af_skip' => 'elementor-pro'))));
$check('filled: nothing for an empty secret', $inert($run($live, array('af_pv' => '', 'af_skip' => 'elementor-pro'))));
$old = $fill($TOKEN, time() - 5);
$check('filled: nothing after the end time, even with the secret', $inert($run($old, array('af_pv' => $TOKEN, 'af_skip' => 'elementor-pro'))));

$r = $run($live, array('af_pv' => $TOKEN, 'af_skip' => 'elementor-pro'));
$check('secret: hooks are added', count($r['hooks']) >= 8);
$check('secret: no $wpdb set (WordPress opens its own)', $r['wpdb'] === false);
$check('secret: elementor-pro left out, and only it', ($r['active'] ?? null) === array('elementor/elementor.php', 'woocommerce/woocommerce.php', 'elementor-pro-extra/x.php'));
$check('secret: the marker names it', ($r['head'] ?? '') === '<meta name="af-port-preview" content="elementor-pro">');
$check('secret: never cached', !empty($r['donotcache']));
$check('secret: element cache off (Elementor 4: its setting reads "disable")', ($r['element_cache_ttl'] ?? '') === 'disable');
$check('secret: element cache off (the old experiment option too)', ($r['element_cache'] ?? '') === 'inactive');
$check('secret: Elementor prints CSS inline (writes no file)', ($r['print_method'] ?? '') === 'internal');
$check('secret: the CSS record is not written', ($r['meta_css'] ?? null) === false);
$check('secret: the element cache is not written', ($r['meta_cache'] ?? null) === false);
$check('secret: the CSS record is not deleted', ($r['meta_del'] ?? null) === false);
$check('secret: other post meta goes through', array_key_exists('meta_other', $r) && $r['meta_other'] === null);
$check('secret: Elementor options keep their value', ($r['opt_el'] ?? '') === 'old' && ($r['opt_el2'] ?? '') === 'old');
$check('secret: other options go through', ($r['opt_other'] ?? '') === 'new');

$r = $run($live, array('af_pv' => $TOKEN, 'af_skip' => 'elementor-pro', 'af_cache' => 'keep'));
$check('secret, af_cache=keep: the element cache is left as visitors get it', ($r['element_cache_ttl'] ?? null) === false && ($r['element_cache'] ?? null) === false);
$check('secret, af_cache=keep: still nothing written', ($r['meta_cache'] ?? null) === false && ($r['meta_css'] ?? null) === false && ($r['print_method'] ?? '') === 'internal');
$check('secret, af_cache=keep: plugins still left out', ($r['active'] ?? null) === array('elementor/elementor.php', 'woocommerce/woocommerce.php', 'elementor-pro-extra/x.php'));

$r = $run($live, array('af_pv' => $TOKEN, 'af_skip' => 'none'));
$check('secret, skip none: every plugin stays', ($r['active'] ?? null) === array('elementor/elementor.php', 'elementor-pro/elementor-pro.php', 'woocommerce/woocommerce.php', 'elementor-pro-extra/x.php'));
$check('secret, skip none: marker "none"', ($r['head'] ?? '') === '<meta name="af-port-preview" content="none">');

$r = $run($live, array(), array('HTTP_X_AF_PORT_PREVIEW' => $TOKEN, 'HTTP_X_AF_PORT_SKIP' => 'elementor-pro,woocommerce'));
$check('secret as headers: both named plugins left out', ($r['active'] ?? null) === array('elementor/elementor.php', 'elementor-pro-extra/x.php'));

$r = $run($live, array('af_pv' => $TOKEN, 'af_skip' => '<script>elementor-pro'));
$check('skip list is cleaned to plugin-folder characters', ($r['head'] ?? '') === '<meta name="af-port-preview" content="scriptelementor-pro">');

foreach (array($half, $live, $old) as $f) @unlink($f);
echo "\n" . ($n - $fails) . "/$n passed\n";
exit($fails ? 1 : 0);
