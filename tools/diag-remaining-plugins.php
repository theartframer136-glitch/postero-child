<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. Live settings and front-end hooks of the plugins still active:
 * Rank Math, Transposh, Currency Switcher (WOOCS), Hostinger, af-crawl-guard,
 * Social Feed Gallery. Secrets and long opaque strings are masked.
 *
 * Run: wp eval-file tools/diag-remaining-plugins.php --allow-root
 */
if (!defined('ABSPATH')) exit(1);
global $wpdb, $wp_filter;
function af_rp_mask($v, $k = '') {
    if (is_array($v) || is_object($v)) { $o = array(); foreach ((array) $v as $kk => $vv) $o[$kk] = af_rp_mask($vv, (string) $kk); return $o; }
    if (!is_string($v)) return $v;
    if ($k !== '' && preg_match('/token|secret|passw|api_?key|key$|bypass|auth|license|nonce|salt|client_id|refresh/i', $k)) return '[masked, ' . strlen($v) . ' chars]';
    return preg_replace('/[A-Za-z0-9_\-]{40,}/', '[masked]', $v);
}
function af_rp_j($v, $max = 1500) { $s = json_encode(af_rp_mask($v), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); return strlen($s) > $max ? substr($s, 0, $max) . '…(' . strlen($s) . ' chars)' : $s; }
function af_rp_opts($like, $max = 1500, $limit = 80) {
    global $wpdb;
    foreach ($wpdb->get_results($wpdb->prepare("SELECT option_name, LENGTH(option_value) AS len, autoload FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name NOT LIKE %s ORDER BY option_name LIMIT %d", $like, '%transient%', $limit)) as $r) {
        echo "  {$r->option_name} ({$r->len} b, {$r->autoload}) = " . af_rp_j(maybe_unserialize(get_option($r->option_name)), $max) . "\n";
    }
}
function af_rp_hooks($needle) {
    global $wp_filter; $n = 0; $by = array();
    foreach ($wp_filter as $tag => $h) { if (!($h instanceof WP_Hook)) continue;
        foreach ($h->callbacks as $prio => $cbs) foreach ($cbs as $cb) {
            $f = $cb['function']; $file = '';
            try { if ($f instanceof Closure) $file = (new ReflectionFunction($f))->getFileName(); elseif (is_array($f)) $file = (new ReflectionMethod($f[0], $f[1]))->getFileName(); elseif (is_string($f) && function_exists($f)) $file = (new ReflectionFunction($f))->getFileName(); } catch (Throwable $e) {}
            if (strpos((string) $file, $needle) === false) continue;
            $name = is_string($f) ? $f : (is_array($f) ? (is_object($f[0]) ? get_class($f[0]) : $f[0]) . '::' . $f[1] : 'closure');
            $by[] = "$tag@$prio $name"; $n++;
        }
    }
    echo "  hooks registered now ($n): " . implode(' | ', array_slice($by, 0, 140)) . "\n";
}
echo "=== RANK MATH\n";
af_rp_opts('rank_math_modules'); af_rp_opts('rank-math-options-%', 2500); af_rp_opts('rank_math_%', 300, 60);
foreach (array('rank_math_redirections', 'rank_math_404_logs', 'rank_math_internal_links', 'rank_math_analytics_objects') as $t) { $tbl = $wpdb->prefix . $t; if ($wpdb->get_var("SHOW TABLES LIKE '$tbl'") === $tbl) echo "  table $t rows: " . $wpdb->get_var("SELECT COUNT(*) FROM $tbl") . "\n"; }
echo '  postmeta rank_math_* keys: ' . af_rp_j($wpdb->get_results("SELECT meta_key, COUNT(*) n FROM {$wpdb->postmeta} WHERE meta_key LIKE 'rank\\_math\\_%' GROUP BY meta_key ORDER BY n DESC LIMIT 40"), 3000) . "\n";
echo '  termmeta rank_math_* keys: ' . af_rp_j($wpdb->get_results("SELECT meta_key, COUNT(*) n FROM {$wpdb->termmeta} WHERE meta_key LIKE 'rank\\_math\\_%' GROUP BY meta_key ORDER BY n DESC LIMIT 20"), 1500) . "\n";
af_rp_hooks('/plugins/seo-by-rank-math/');
echo "\n=== TRANSPOSH\n";
af_rp_opts('transposh%', 3000);
foreach (array('translations', 'translations_log') as $t) { $tbl = $wpdb->prefix . $t; if ($wpdb->get_var("SHOW TABLES LIKE '$tbl'") === $tbl) echo "  table $t rows: " . $wpdb->get_var("SELECT COUNT(*) FROM $tbl") . ($t === 'translations' ? ' by lang: ' . af_rp_j($wpdb->get_results("SELECT lang, COUNT(*) n FROM $tbl GROUP BY lang")) : '') . "\n"; }
af_rp_hooks('/plugins/transposh-translation-filter-for-wordpress/');
echo "\n=== CURRENCY SWITCHER (WOOCS)\n";
af_rp_opts('woocs%', 2500, 120);
af_rp_hooks('/plugins/woocommerce-currency-switcher/');
echo "\n=== HOSTINGER\n";
af_rp_opts('hostinger%', 600, 120); af_rp_opts('hts_%', 400, 40);
af_rp_hooks('/plugins/hostinger/');
echo '  mu-plugin hooks: '; af_rp_hooks('/mu-plugins/');
echo "\n=== AF CRAWL GUARD\n";
af_rp_opts('af_crawl%', 800); af_rp_opts('afcg%', 800);
af_rp_hooks('/plugins/af-crawl-guard/');
echo "\n=== SOCIAL FEED GALLERY\n";
af_rp_hooks('/plugins/insta-gallery/');
echo "\n=== object cache: " . (wp_using_ext_object_cache() ? 'external (' . (file_exists(WP_CONTENT_DIR . '/object-cache.php') ? 'object-cache.php drop-in' : '?') . ')' : 'none') . "\n";
echo "=== END\n";
