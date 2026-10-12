<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. The facts the security audit (12 Oct) needs from inside the
 * site, and nothing else. Prints no secret: keys, passwords and tokens are
 * reported as present / absent with their length only; email addresses of
 * customers are counted, never listed (staff accounts are listed).
 *
 *  - who can do what: administrators, shop managers, staff roles, the stock
 *    editor allowlist and whether each allowlisted address has an account;
 *  - application passwords held, recent registrations, default role;
 *  - versions and pending updates of core and plugins;
 *  - wp-config / uploads permissions, file-edit / file-mods switches, debug;
 *  - PHP files under uploads (there should be none), the fatals log, the
 *    profiler's request log, the root report files deployed with the theme;
 *  - where the snippet shortcodes [try_on_wall] / [frame_the_moment] are used;
 *  - Transposh: may anonymous visitors save translations?
 *  - marketplace: inbound stock writes ever logged (which key route is used);
 *  - abandoned-cart queue size and how many distinct addresses it mails;
 *  - checkout page: block checkout or the classic shortcode;
 *  - Turnstile and crawl-guard state.
 *
 * Run: wp eval-file tools/diag-security.php --allow-root
 */
if (!defined('ABSPATH')) exit(1);
global $wpdb;
$len = function ($v) { return (is_string($v) && $v !== '') ? 'present (' . strlen($v) . ' chars)' : 'absent'; };

echo "=== people and permissions\n";
foreach (array('administrator', 'shop_manager', 'editor', 'author', 'af_order_manager', 'af_support') as $role) {
    $users = get_users(array('role' => $role, 'fields' => array('ID', 'user_login', 'user_email', 'user_registered')));
    echo "  $role: " . count($users) . "\n";
    foreach ($users as $u) echo '    #' . $u->ID . ' ' . $u->user_login . ' <' . $u->user_email . '> since ' . substr($u->user_registered, 0, 10) . "\n";
}
$roles = wp_roles()->roles;
echo '  roles defined: ' . implode(', ', array_keys($roles)) . "\n";
foreach ($roles as $k => $r) {
    $danger = array_intersect(array_keys(array_filter((array) $r['capabilities'])), array('manage_options', 'edit_users', 'promote_users', 'install_plugins', 'activate_plugins', 'edit_plugins', 'edit_theme_options', 'unfiltered_html', 'manage_woocommerce'));
    if ($danger && $k !== 'administrator') echo "  role $k holds: " . implode(', ', $danger) . "\n";
}
echo '  customers: ' . count(get_users(array('role' => 'customer', 'fields' => 'ID'))) . ' | users total: ' . (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}") . "\n";
echo '  registered in the last 30 days: ' . (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->users} WHERE user_registered >= %s", gmdate('Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS))) . "\n";
echo '  users_can_register=' . json_encode(get_option('users_can_register')) . ' default_role=' . json_encode(get_option('default_role')) . ' woocommerce_enable_myaccount_registration=' . json_encode(get_option('woocommerce_enable_myaccount_registration')) . "\n";
echo '  admin_email domain: ' . substr(strrchr((string) get_option('admin_email'), '@'), 1) . "\n";
$ap = $wpdb->get_results("SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = '_application_passwords'");
echo '  application passwords held:';
$n = 0;
foreach ($ap as $r) { $list = maybe_unserialize($r->meta_value); if (is_array($list) && $list) { $u = get_userdata($r->user_id); echo ' #' . $r->user_id . '(' . ($u ? implode('/', $u->roles) : '?') . ')x' . count($list); $n++; } }
echo $n ? "\n" : " none\n";
if (function_exists('af_inv_allowed_emails')) {
    echo "  stock-editor allowlist (af_inv_allowed_emails):\n";
    foreach (af_inv_allowed_emails() as $e) {
        $u = get_user_by('email', $e);
        echo '    ' . $e . ' -> ' . ($u ? '#' . $u->ID . ' ' . implode('/', $u->roles) . ' since ' . substr($u->user_registered, 0, 10) : 'NO ACCOUNT (anyone could register it)') . "\n";
    }
}

echo "\n=== versions\n";
global $wp_version;
echo '  WordPress ' . $wp_version . ' | PHP ' . PHP_VERSION . "\n";
$core = get_site_transient('update_core');
if ($core && !empty($core->updates)) foreach ($core->updates as $u) if (!empty($u->response) && $u->response !== 'latest') echo '  core update offered: ' . $u->current . " ({$u->response})\n";
if (!function_exists('get_plugins')) require_once ABSPATH . 'wp-admin/includes/plugin.php';
$upd = get_site_transient('update_plugins');
foreach ((array) get_option('active_plugins', array()) as $p) {
    $data = get_plugin_data(WP_PLUGIN_DIR . '/' . $p, false, false);
    $new = ($upd && isset($upd->response[$p])) ? ' -> UPDATE ' . $upd->response[$p]->new_version : '';
    echo '  ' . $p . ' ' . ($data['Version'] ?? '?') . $new . "\n";
}
$inactive = array_diff(array_keys(get_plugins()), (array) get_option('active_plugins', array()));
echo '  inactive plugins still installed: ' . count($inactive) . ($inactive ? ' (' . implode(', ', array_map(function ($p) { return dirname($p) === '.' ? $p : dirname($p); }, array_slice($inactive, 0, 40))) . ')' : '') . "\n";
echo '  auto_update_plugins=' . json_encode(get_site_option('auto_update_plugins')) . ' auto_update_themes=' . json_encode(get_site_option('auto_update_themes')) . "\n";
echo '  mu-plugins: ' . implode(', ', array_keys(get_mu_plugins())) . ' | drop-ins: ' . implode(', ', array_keys(get_dropins())) . "\n";

echo "\n=== configuration\n";
foreach (array('DISALLOW_FILE_EDIT', 'DISALLOW_FILE_MODS', 'WP_DEBUG', 'WP_DEBUG_DISPLAY', 'WP_DEBUG_LOG', 'SCRIPT_DEBUG', 'FORCE_SSL_ADMIN', 'AUTOMATIC_UPDATER_DISABLED', 'WP_AUTO_UPDATE_CORE') as $c) echo "  $c=" . (defined($c) ? json_encode(constant($c)) : 'undefined') . "\n";
echo '  ini display_errors=' . json_encode(ini_get('display_errors')) . ' expose_php=' . json_encode(ini_get('expose_php')) . ' allow_url_include=' . json_encode(ini_get('allow_url_include')) . "\n";
echo '  session.cookie_secure=' . json_encode(ini_get('session.cookie_secure')) . ' session.cookie_httponly=' . json_encode(ini_get('session.cookie_httponly')) . ' session.cookie_samesite=' . json_encode(ini_get('session.cookie_samesite')) . "\n";
$root = rtrim(ABSPATH, '/');
foreach (array($root . '/wp-config.php', dirname($root) . '/wp-config.php') as $f) if (file_exists($f)) echo '  ' . str_replace($root, 'ABSPATH', $f) . ' perms ' . substr(sprintf('%o', fileperms($f)), -4) . "\n";
$up = wp_upload_dir();
echo '  uploads perms ' . substr(sprintf('%o', fileperms($up['basedir'])), -4) . "\n";
foreach (array('readme.html', 'license.txt', 'wp-config-sample.php', '.htaccess', 'wp-content/debug.log', 'wp-content/af-fatals.log', 'wp-content/uploads/af-req-log.txt', 'wp-content/uploads/.htaccess') as $f) {
    $p = $root . '/' . $f;
    echo "  $f: " . (file_exists($p) ? 'exists, ' . filesize($p) . ' bytes, modified ' . gmdate('Y-m-d', filemtime($p)) : 'absent') . "\n";
}
echo '  uploads/.htaccess blocks php: ' . (file_exists($up['basedir'] . '/.htaccess') && preg_match('/php/i', (string) file_get_contents($up['basedir'] . '/.htaccess')) ? 'yes' : 'no') . "\n";

echo "\n=== executable files under uploads (there should be none)\n";
$n = 0;
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($up['basedir'], FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile()) continue;
    if (!preg_match('/\.(php\d?|phtml|phar|pht|phps|shtml|cgi|pl|py|sh)$/i', $f->getFilename())) continue;
    $rel = substr($f->getPathname(), strlen($up['basedir']));
    $head = substr((string) @file_get_contents($f->getPathname(), false, null, 0, 120), 0, 120);
    echo '  ' . $rel . ' ' . $f->getSize() . ' bytes ' . gmdate('Y-m-d', $f->getMTime()) . ' | ' . preg_replace('/\s+/', ' ', $head) . "\n";
    if (++$n >= 40) { echo "  (more…)\n"; break; }
}
if (!$n) echo "  none\n";

echo "\n=== theme files that should not be public (on the server now)\n";
$theme = get_stylesheet_directory();
foreach (array('APPLIED.txt', 'CODES.txt', 'SKU.txt', 'SKUCHECK.txt', 'README.md', 'docs', '.gitignore', 'BATCH_01.jpg', 'tools/.htaccess', 'ops/.htaccess') as $f) echo "  $f: " . (file_exists($theme . '/' . $f) ? 'present' : 'absent') . "\n";
$unguarded = array();
foreach (array('tools', 'ops') as $d) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($theme . '/' . $d, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile() || substr($f->getFilename(), -4) !== '.php') continue;
        $head = (string) @file_get_contents($f->getPathname(), false, null, 0, 400);
        if (strpos($head, 'AF-WEB-GUARD') === false) $unguarded[] = substr($f->getPathname(), strlen($theme) + 1);
    }
}
echo '  php in tools/ and ops/ without AF-WEB-GUARD: ' . count($unguarded) . ($unguarded ? ' (' . implode(', ', $unguarded) . ')' : '') . "\n";

echo "\n=== snippet shortcodes in published content\n";
foreach (array('try_on_wall', 'frame_the_moment') as $sc) {
    $ids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_content LIKE %s", '%[' . $wpdb->esc_like($sc) . '%'));
    $eids = $wpdb->get_col($wpdb->prepare("SELECT m.post_id FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE p.post_status = 'publish' AND m.meta_key = '_elementor_data' AND m.meta_value LIKE %s", '%' . $wpdb->esc_like($sc) . '%'));
    $all = array_unique(array_merge($ids, $eids));
    echo "  [$sc]: " . ($all ? implode(', ', array_map(function ($id) { return '#' . $id . ' ' . get_permalink($id); }, $all)) : 'not used anywhere') . "\n";
}
foreach (array('tow_send', 'ftm_send') as $a) echo "  wp_ajax_nopriv_$a registered: " . (has_action('wp_ajax_nopriv_' . $a) ? 'yes' : 'no') . "\n";
foreach (array('preview-', 'frame-moment-') as $pre) {
    $files = glob($up['basedir'] . '/*/*/' . $pre . '*.png') ?: array();
    echo "  uploads/*/*/{$pre}*.png written by the snippets: " . count($files) . ($files ? ' (newest ' . gmdate('Y-m-d', max(array_map('filemtime', $files))) . ')' : '') . "\n";
}

echo "\n=== Transposh\n";
$tp = get_option(defined('TRANSPOSH_OPTIONS') ? TRANSPOSH_OPTIONS : 'transposh_options');
echo '  options stored: ' . (is_array($tp) ? 'yes' : 'no') . ' | allow_anonymous_translation=' . (is_array($tp) && array_key_exists('allow_anonymous_translation', $tp) ? json_encode($tp['allow_anonymous_translation']) : '(not stored: the default, 1, applies)') . "\n";
$tt = $wpdb->prefix . 'translations_log';
if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tt)) === $tt) {
    echo '  translation log rows: ' . (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tt}") . ' | by visitors not logged in (translated_by is an IP): ' . (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tt} WHERE translated_by REGEXP '^[0-9a-f.:]+$'") . "\n";
    $last = $wpdb->get_results("SELECT timestamp, lang, source, LEFT(translated, 60) t, translated_by FROM {$tt} ORDER BY timestamp DESC LIMIT 5");
    foreach ($last as $r) echo '    ' . $r->timestamp . ' ' . $r->lang . ' src=' . $r->source . ' by=' . (preg_match('/^[0-9a-f.:]+$/', (string) $r->translated_by) ? 'an IP' : $r->translated_by) . ' | ' . $r->t . "\n";
}

echo "\n=== marketplace\n";
echo '  af_mk_key: ' . $len(get_option('af_mk_key')) . "\n";
if (function_exists('af_mk_table')) {
    $t = af_mk_table();
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t)) === $t) {
        foreach ($wpdb->get_results("SELECT channel, direction, action, COUNT(*) n, MAX(created_at) last FROM {$t} GROUP BY channel, direction, action ORDER BY n DESC LIMIT 20") as $r) echo "  {$r->channel} {$r->direction} {$r->action}: {$r->n} (last {$r->last})\n";
    }
}

echo "\n=== abandoned carts\n";
if (function_exists('af_ac_table')) {
    $t = af_ac_table();
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t)) === $t) {
        $cols = $wpdb->get_col("SHOW COLUMNS FROM {$t}", 0);
        echo '  columns: ' . implode(',', $cols) . "\n";
        echo '  rows: ' . (int) $wpdb->get_var("SELECT COUNT(*) FROM {$t}") . ' | distinct emails: ' . (int) $wpdb->get_var("SELECT COUNT(DISTINCT email) FROM {$t}") . "\n";
        if (in_array('status', $cols, true)) foreach ($wpdb->get_results("SELECT status, COUNT(*) n FROM {$t} GROUP BY status") as $r) echo "    status {$r->status}: {$r->n}\n";
    }
}

echo "\n=== checkout, Turnstile, crawl guard\n";
$co = (int) get_option('woocommerce_checkout_page_id');
$content = $co ? (string) get_post_field('post_content', $co) : '';
echo '  checkout page #' . $co . ': ' . (has_block('woocommerce/checkout', $content) ? 'BLOCK checkout' : (strpos($content, '[woocommerce_checkout') !== false ? 'classic shortcode' : 'other (' . substr(preg_replace('/\s+/', ' ', $content), 0, 80) . ')')) . "\n";
echo '  Turnstile active: ' . (function_exists('af_turnstile_active') && af_turnstile_active() ? 'yes' : 'no') . "\n";
echo '  crawl guard file: ' . (file_exists(WP_CONTENT_DIR . '/af-crawl-guard.php') ? 'present' : 'absent') . ' | loaded this request: ' . (defined('AF_CRAWL_GUARD_RAN') ? 'yes' : 'no (CLI skips it)') . "\n";
echo '  SERVER_ADDR as PHP sees it: ' . ($_SERVER['SERVER_ADDR'] ?? '(not set under CLI)') . ' | this host resolves to: ' . gethostbyname(gethostname()) . "\n";
$r = wp_remote_get('https://api.ipify.org', array('timeout' => 8));
echo '  outbound address (what the site looks like when it calls out): ' . (is_wp_error($r) ? $r->get_error_message() : trim(wp_remote_retrieve_body($r))) . "\n";
echo "=== END\n";
