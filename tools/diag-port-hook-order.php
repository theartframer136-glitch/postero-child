<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. The order in which WordPress will run callbacks, for every hook
 * bucket (hook + priority) that holds a callback of Transposh, WOOCS, Rank
 * Math, Social Feed Gallery or Hostinger - whether it comes from the plugin
 * or from its theme copy. Run it once as is (plugins loaded) and once with
 * skip_plugins naming the plugins (their copies loaded); the two listings
 * must match line for line. Callbacks are named the same way on both sides:
 * plugin and copy files by their path inside the plugin, everything else by
 * where it comes from.
 *
 * Run: wp eval-file tools/diag-port-hook-order.php --allow-root [--skip-plugins=...]
 */
if (!defined('ABSPATH')) exit(1);

// copy files and dirs (under the theme) => plugin folder
$GLOBALS['af_ho_map'] = array(
    'inc/ports/transposh/'        => 'transposh-translation-filter-for-wordpress/',
    'inc/ports/transposh.php'     => 'transposh-translation-filter-for-wordpress/',
    'inc/ports/woocs/'            => 'woocommerce-currency-switcher/',
    'inc/ports/woocs.php'         => 'woocommerce-currency-switcher/',
    'inc/ports/rank-math/'        => 'seo-by-rank-math/',
    'inc/ports/rank-math.php'     => 'seo-by-rank-math/',
    'inc/ports/qligg/'            => 'insta-gallery/',
    'inc/ports/instagram-feed.php' => 'insta-gallery/',
    'inc/ports/hostinger.php'     => 'hostinger/',
);
$GLOBALS['af_ho_theme'] = wp_normalize_path(get_stylesheet_directory() . '/');
$GLOBALS['af_ho_plug']  = wp_normalize_path(WP_PLUGIN_DIR . '/');

// "name @origin"; plugin and copy callbacks get the same origin (P:<folder>/),
// and closures carry no line number (the copy's wrapper has other lines)
function af_ho_name($f, &$watched) {
    $theme = $GLOBALS['af_ho_theme']; $plug = $GLOBALS['af_ho_plug'];
    $file = '';
    try {
        if ($f instanceof Closure) $r = new ReflectionFunction($f);
        elseif (is_string($f) && strpos($f, '::') !== false) $r = new ReflectionMethod($f);
        elseif (is_array($f) && isset($f[0], $f[1])) $r = new ReflectionMethod($f[0], $f[1]);
        elseif (is_string($f) && function_exists($f)) $r = new ReflectionFunction($f);
        else $r = null;
        $file = ($r && $r->getFileName()) ? wp_normalize_path($r->getFileName()) : '';
    } catch (Throwable $e) {}
    $where = $file === '' ? 'php' : $file;
    foreach ($GLOBALS['af_ho_map'] as $cd => $pd) if (strpos($where, $theme . $cd) === 0) { $where = 'P:' . $pd; break; }
    if (strpos($where, $plug) === 0) $where = 'P:' . preg_replace('#/.*#', '/', substr($where, strlen($plug)));
    if (strpos($where, $theme) === 0) $where = 'theme:' . substr($where, strlen($theme));
    if (strpos($where, wp_normalize_path(ABSPATH)) === 0) $where = 'core';
    if (in_array($where, array('P:transposh-translation-filter-for-wordpress/', 'P:woocommerce-currency-switcher/', 'P:seo-by-rank-math/', 'P:insta-gallery/', 'P:hostinger/'), true)) $watched = true;
    if ($f instanceof Closure) $n = 'closure';
    elseif (is_string($f)) $n = $f;
    elseif (is_array($f)) $n = (is_object($f[0]) ? get_class($f[0]) . '->' : $f[0] . '::') . $f[1];
    else $n = is_object($f) ? get_class($f) : gettype($f);
    return $n . ' @' . $where;
}

$af_out = array();
foreach ($GLOBALS['wp_filter'] as $tag => $h) {
    if (!($h instanceof WP_Hook)) continue;
    foreach ($h->callbacks as $prio => $cbs) {
        $watched = false; $names = array();
        foreach ($cbs as $cb) $names[] = af_ho_name($cb['function'], $watched);
        if ($watched && count($names) > 1) $af_out[] = "$tag@$prio: " . implode(' | ', $names);
    }
}
sort($af_out);
echo '=== mode: ' . implode(' ', array_map(function ($p) { return $p . '=' . (in_array(realpath(WP_PLUGIN_DIR . '/' . $p), array_map('realpath', get_included_files()), true) ? 'plugin' : 'copy-or-none'); }, array('transposh-translation-filter-for-wordpress/transposh.php', 'woocommerce-currency-switcher/index.php', 'seo-by-rank-math/rank-math.php', 'insta-gallery/insta-gallery.php', 'hostinger/hostinger.php'))) . "\n";
echo '=== shared buckets: ' . count($af_out) . "\n";
foreach ($af_out as $l) echo strlen($l) > 900 ? substr($l, 0, 900) . "…\n" : "$l\n";
echo "=== END\n";
