<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. Facts to settle before the theme copies of Hostinger, Social Feed
 * Gallery, Transposh, Currency Switcher (WOOCS) and Rank Math are built.
 * Nothing is written; secrets are masked (only lengths or key names print).
 *
 * Run: wp eval-file tools/diag-round3-prebuild.php --allow-root
 */
if (!defined('ABSPATH')) exit(1);
global $wpdb, $wp_filter;

$af_pl = WP_PLUGIN_DIR;
$af_parent = get_template_directory();
function af_r3_files($dir, $ext = 'php') {
    $out = array();
    if (!is_dir($dir)) return $out;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) if ($f->isFile() && ($ext === '*' || preg_match('/\.(' . $ext . ')$/i', $f->getFilename()))) $out[] = $f->getPathname();
    return $out;
}
function af_r3_grep($files, $re, $max = 25, $root = '') {
    $n = 0;
    foreach ($files as $f) {
        $s = @file_get_contents($f); if ($s === false || !preg_match_all($re, $s, $m, PREG_OFFSET_CAPTURE)) continue;
        foreach ($m[0] as $hit) {
            $line = substr_count(substr($s, 0, $hit[1]), "\n") + 1;
            $ls = explode("\n", $s); $txt = trim($ls[$line - 1]);
            if (strlen($txt) > 170) $txt = substr($txt, 0, 170) . '…';
            echo '    ' . str_replace($root, '', $f) . ":$line  $txt\n";
            if (++$n >= $max) { echo "    (stopped at $max)\n"; return $n; }
        }
    }
    if (!$n) echo "    none\n";
    return $n;
}
function af_r3_hdr($file) { $d = @get_file_data($file, array('v' => 'Version')); return $d ? $d['v'] : '?'; }

echo "=== environment\n";
echo '  php ' . PHP_VERSION . ' | wp ' . get_bloginfo('version') . ' | WPLANG=' . json_encode(get_option('WPLANG')) . ' | get_locale=' . get_locale() . "\n";
echo '  stylesheet dir ' . get_stylesheet_directory() . ' | normalized same=' . (wp_normalize_path(get_stylesheet_directory()) === get_stylesheet_directory() ? 'yes' : 'NO') . "\n";
echo '  DOCUMENT_ROOT(cli)=' . (isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '-') . ' WP_CONTENT_DIR=' . WP_CONTENT_DIR . "\n";
echo '  realpath(WP_CONTENT_DIR)=' . realpath(WP_CONTENT_DIR) . "\n";
foreach ((array) get_option('active_plugins') as $p) echo "  active: $p " . af_r3_hdr("$af_pl/$p") . "\n";
echo '  parent theme ' . $af_parent . ' ' . wp_get_theme(get_template())->get('Version') . "\n";

echo "\n=== HOSTINGER: Action Scheduler supplier\n";
if (class_exists('ActionScheduler_Versions')) {
    echo '  latest=' . ActionScheduler_Versions::instance()->latest_version();
    if (class_exists('ActionScheduler_SystemInformation') && method_exists('ActionScheduler_SystemInformation', 'active_source_path')) echo ' source=' . str_replace(ABSPATH, '', ActionScheduler_SystemInformation::active_source_path());
    echo "\n";
}
foreach (array('woocommerce/packages/action-scheduler/action-scheduler.php', 'hostinger/vendor/woocommerce/action-scheduler/action-scheduler.php', 'seo-by-rank-math/vendor/woocommerce/action-scheduler/action-scheduler.php') as $f) echo "  $f: " . (is_file("$af_pl/$f") ? af_r3_hdr("$af_pl/$f") : 'absent') . "\n";
$t = $wpdb->prefix . 'actionscheduler_actions'; $g = $wpdb->prefix . 'actionscheduler_groups';
if ($wpdb->get_var("SHOW TABLES LIKE '$t'") === $t) {
    foreach ($wpdb->get_results("SELECT g.slug, a.status, COUNT(*) n FROM $t a LEFT JOIN $g g ON g.group_id=a.group_id WHERE a.status IN ('pending','in-progress') GROUP BY g.slug, a.status ORDER BY n DESC LIMIT 20") as $r) echo "  AS {$r->slug} {$r->status}: {$r->n}\n";
    foreach ($wpdb->get_results("SELECT hook, COUNT(*) n FROM $t WHERE status IN ('pending','in-progress') AND (hook LIKE '%hostinger%' OR hook LIKE '%rank_math%' OR hook LIKE '%transposh%' OR hook LIKE '%woocs%' OR hook LIKE '%qligg%') GROUP BY hook") as $r) echo "  AS hook {$r->hook}: {$r->n}\n";
}
echo '  app passwords available now: ' . (wp_is_application_passwords_available() ? 'yes' : 'no') . ' | is_ssl(cli)=' . (is_ssl() ? 'yes' : 'no') . "\n";
echo '  users with application passwords: ' . (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key='_application_passwords' AND meta_value NOT IN ('','a:0:{}')") . "\n";

echo "\n=== TRANSPOSH\n";
$rr = get_option('rewrite_rules');
echo '  rewrite_rules: ' . (is_array($rr) ? count($rr) . ' rules, md5 ' . md5(serialize($rr)) : 'empty') . "\n";
if (is_array($rr)) {
    $n = 0; foreach (array_keys($rr) as $k) if (preg_match('#^\(?\(?(en|hi)\b|^\((?:[a-z]{2}\|)+#', $k)) { if ($n++ < 10) echo "    lang rule: $k\n"; }
    echo "    language-prefixed rules: $n\n";
    foreach (array_keys($rr) as $k) if (preg_match('#oauth|well-known|sitemap|llms#', $k)) echo "    rule: $k\n";
}
foreach (array(WP_LANG_DIR, WP_LANG_DIR . '/plugins', WP_LANG_DIR . '/themes') as $d) {
    $h = is_dir($d) ? preg_grep('/hi_IN/i', scandir($d)) : array();
    echo "  $d hi_IN files: " . ($h ? implode(', ', array_slice($h, 0, 12)) : 'none') . "\n";
}
foreach ((array) _get_cron_array() as $ts => $hooks) foreach ((array) $hooks as $hook => $ev) if (preg_match('/transposh|rank_math|woocs|qligg|hostinger|PN_WP_CRON/i', $hook)) echo '  cron ' . gmdate('Y-m-d H:i', $ts) . " $hook x" . count($ev) . "\n";
echo '  postmeta tp_language: ' . $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key='tp_language'") . ' | transposh_can_translate: ' . $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key='transposh_can_translate'") . ' | commentmeta tp_language: ' . $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->commentmeta} WHERE meta_key='tp_language'") . "\n";
$sw = get_option('sidebars_widgets'); $flat = array(); foreach ((array) $sw as $k => $v) if (is_array($v)) foreach ($v as $w) $flat[] = $w;
echo '  sidebar widgets (transposh/woocs/insta): ' . implode(',', preg_grep('/transposh|woocs|insta|qligg|lsft/i', $flat) ?: array('none')) . "\n";
$tr = 0; foreach (wp_roles()->roles as $rn => $r) if (!empty($r['capabilities']['translator'])) { echo "  role with translator cap: $rn\n"; $tr++; }
echo '  users with translator cap meta: ' . (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key='{$wpdb->prefix}capabilities' AND meta_value LIKE '%translator%'") . "\n";
echo '  transposh tables: ' . $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}translations") . ' translations, ' . $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}translations_log") . " log rows\n";
echo "  unguarded Transposh constant names defined elsewhere (plugins + parent theme, excluding transposh):\n";
$scan = array();
foreach (glob("$af_pl/*", GLOB_ONLYDIR) as $d) { if (strpos($d, 'transposh') !== false) continue; if (in_array(basename($d), array('woocommerce', 'woocommerce-square', 'woocommerce-currency-switcher', 'seo-by-rank-math', 'elementor', 'elementor-pro', 'litespeed-cache', 'hostinger', 'insta-gallery', 'language-switcher-for-transposh'), true)) $scan = array_merge($scan, af_r3_files($d)); }
$scan = array_merge($scan, af_r3_files($af_parent));
echo '    (' . count($scan) . " files)\n";
af_r3_grep($scan, '/define\s*\(\s*[\'"](DB_VERSION|HDOM_[A-Z_]+|TR_NONCE|TP_[A-Z_]+|LANG_PARAM|EDIT_PARAM|SPAN_PREFIX|TRANSLATOR|DEFAULT_TARGET_CHARSET|DEFAULT_BR_TEXT|DEFAULT_SPAN_TEXT|MAX_FILE_SIZE|FULL_PROTOCOL|NOTE_TEXT|NO_TRANSLATE_CLASS|ONLY_THISLANGUAGE_CLASS|TRANSPOSH_[A-Z_]+)[\'"]/', 25, $af_pl);
echo "  Transposh core constant list (to compare):\n";
$cf = "$af_pl/transposh-translation-filter-for-wordpress";
if (is_dir($cf)) { $names = array(); foreach (af_r3_files($cf) as $f) { if (preg_match_all('/^\s*define\s*\(\s*[\'"]([A-Z0-9_]+)[\'"]/m', file_get_contents($f), $m)) $names = array_merge($names, $m[1]); } $names = array_unique($names); sort($names); echo '    ' . implode(' ', $names) . "\n";
    $dup = array(); foreach ($names as $c) if (defined($c)) { $dup[] = $c; } echo '    defined right now: ' . count($dup) . "\n"; }

echo "\n=== PARENT THEME references (postero)\n";
$pf = af_r3_files($af_parent, 'php|js');
echo "  transposh / lsft:\n"; af_r3_grep($pf, '/transposh|lsft_/i', 15, $af_parent);
echo "  WOOCS:\n"; af_r3_grep($pf, '/woocs_redirect|WOOCS_STARTER|\$WOOCS|woocs_show_money_signs|data-currency/i', 30, $af_parent);
echo "  Rank Math:\n"; af_r3_grep($pf, '/rank_?math/i', 10, $af_parent);
echo "  Instagram / qligg:\n"; af_r3_grep($pf, '/insta-gallery|qligg|insta_gallery/i', 10, $af_parent);
echo "  hostinger:\n"; af_r3_grep($pf, '/hostinger/i', 10, $af_parent);
echo "  is_plugin_active / class_exists on the five:\n"; af_r3_grep($pf, '/(is_plugin_active|class_exists|function_exists|defined)\s*\(\s*[\'"][^\'"]*(transposh|woocs|currency-switcher|rank|insta|qligg|hostinger)/i', 20, $af_parent);

echo "\n=== WOOCS\n";
foreach (array('woocommerce_currency', 'woocommerce_currency_pos', 'woocommerce_price_num_decimals', 'woocommerce_price_thousand_sep', 'woocommerce_price_decimal_sep') as $o) echo "  raw $o = " . json_encode($wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s", $o))) . "\n";
foreach ($wpdb->get_results("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'litespeed%' AND (option_value LIKE '%woocs%' OR option_value LIKE '%currency-switcher%' OR option_value LIKE '%transposh%' OR option_value LIKE '%seo-by-rank-math%' OR option_value LIKE '%insta-gallery%' OR option_value LIKE '%hostinger%' OR option_value LIKE '%qligg%')") as $r) {
    $v = maybe_unserialize($r->option_value); $s = is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_SLASHES);
    preg_match_all('/[^\s"\',\[\]]*(woocs|currency-switcher|transposh|seo-by-rank-math|rank-math|insta-gallery|qligg|hostinger)[^\s"\',\[\]]*/i', $s, $m);
    echo "  litespeed {$r->option_name}: " . implode(' ', array_slice(array_unique($m[0]), 0, 12)) . "\n";
}
foreach (array('litespeed.conf.cache-vary_cookies', 'litespeed.conf.cache-vary_group', 'litespeed.conf.optm-js_defer_exc', 'litespeed.conf.optm-js_exc', 'litespeed.conf.optm-css_exc', 'litespeed.conf.optm-js_comb', 'litespeed.conf.optm-css_comb', 'litespeed.conf.optm-ucss') as $o) echo "  $o = " . json_encode(get_option($o)) . "\n";
echo "  woocommerce-square callbacks on hooks WOOCS also uses:\n";
$woocs_hooks = array(); $sq = array();
foreach ($wp_filter as $tag => $h) { if (!($h instanceof WP_Hook)) continue;
    foreach ($h->callbacks as $prio => $cbs) foreach ($cbs as $cb) {
        $f = $cb['function']; $file = '';
        try { if ($f instanceof Closure) $file = (new ReflectionFunction($f))->getFileName(); elseif (is_array($f)) $file = (new ReflectionMethod($f[0], $f[1]))->getFileName(); elseif (is_string($f) && function_exists($f)) $file = (new ReflectionFunction($f))->getFileName(); } catch (Throwable $e) {}
        if (strpos((string) $file, '/woocommerce-currency-switcher/') !== false) $woocs_hooks["$tag@$prio"] = 1;
        if (strpos((string) $file, '/woocommerce-square/') !== false) $sq["$tag@$prio"] = 1;
    }
}
$both = array_intersect_key($sq, $woocs_hooks); echo '    same hook+priority: ' . ($both ? implode(' ', array_keys($both)) : 'none') . "\n";
$sqt = array(); foreach (array_keys($sq) as $k) $sqt[strtok($k, '@')] = 1; $wt = array(); foreach (array_keys($woocs_hooks) as $k) $wt[strtok($k, '@')] = 1;
echo '    same hook, any priority: ' . implode(' ', array_keys(array_intersect_key($sqt, $wt))) . "\n";

echo "\n=== RANK MATH\n";
foreach (array('%<!-- wp:rank-math/%', '%[rank_math%', '%rank-math-breadcrumb%') as $like) {
    $ids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_status IN ('publish','private','future','draft') AND post_type<>'revision' AND post_content LIKE %s LIMIT 20", $like));
    $e = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key='_elementor_data' AND meta_value LIKE %s LIMIT 20", $like));
    echo "  $like posts: " . ($ids ? implode(',', $ids) : 'none') . ' | elementor: ' . ($e ? implode(',', $e) : 'none') . "\n";
}
echo '  usermeta mcp_refresh_jti_%: ' . $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key LIKE 'mcp\\_refresh\\_jti\\_%'") . ' | other mcp%/oauth% usermeta: ' . $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key LIKE 'mcp%' OR meta_key LIKE '%oauth%'") . "\n";
foreach ($wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '%mcp%' OR option_name LIKE '%oauth%' LIMIT 20") as $o) echo "  option $o\n";
$home = untrailingslashit(get_home_path());
echo '  physical robots.txt: ' . (is_file("$home/robots.txt") ? 'YES' : 'no') . ' | physical sitemap_index.xml: ' . (is_file("$home/sitemap_index.xml") ? 'YES' : 'no') . ' | llms.txt: ' . (is_file("$home/llms.txt") ? 'YES' : 'no') . "\n";
$cd = get_option('rank_math_connect_data'); echo '  rank_math_connect_data keys: ' . (is_array($cd) ? implode(',', array_keys($cd)) . ' connected=' . json_encode(isset($cd['connected']) ? $cd['connected'] : null) : json_encode($cd)) . "\n";
$nt = get_option('rank_math_notifications'); echo '  rank_math_notifications: ' . (is_array($nt) ? count($nt) . ' items md5 ' . md5(serialize($nt)) : json_encode($nt)) . "\n";
echo '  rank_math_registration_skip=' . json_encode(get_option('rank_math_registration_skip')) . "\n";
$ik = get_option('rank_math_indexnow_api_key'); echo '  indexnow key: ' . (is_string($ik) && $ik !== '' ? strlen($ik) . ' chars' : 'none') . "\n";
foreach (array('seo-by-rank-math/vendor/composer/autoload_classmap.php', 'seo-by-rank-math/vendor/composer/autoload_files.php', 'seo-by-rank-math/vendor/composer/autoload_psr4.php') as $f) echo "  $f: " . (is_file("$af_pl/$f") ? count((array) include "$af_pl/$f") . ' entries' : 'absent') . "\n";
echo '  upload rank-math cache files: ' . count((array) glob(wp_upload_dir()['basedir'] . '/rank-math/*')) . "\n";

echo "\n=== SOCIAL FEED GALLERY\n";
$f = get_option('insta_gallery_feeds'); echo '  feeds: ' . (is_array($f) ? count($f) : json_encode($f)) . ' | accounts: ' . (is_array(get_option('insta_gallery_accounts')) ? count(get_option('insta_gallery_accounts')) : '-') . "\n";
foreach ((array) _get_cron_array() as $ts => $hooks) foreach ((array) $hooks as $hook => $ev) if (strpos($hook, 'qligg') !== false) echo '  cron ' . gmdate('Y-m-d H:i', $ts) . " $hook\n";
echo '  insta_gallery_settings: ' . json_encode(array_keys((array) get_option('insta_gallery_settings'))) . "\n";

echo "\n=== END\n";
