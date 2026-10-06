<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. Before the switched-off plugins' folders are removed (the
 * owner, 5 Oct: convert every plugin that can be into theme code, then
 * delete them; 6 Oct: wait three days after the switch-offs first), for
 * every plugin folder:
 *
 *   - status, version, size, whether it has an uninstall.php (which the
 *     removal must never run: it can wipe settings the theme copies read)
 *   - whether that exact version can be downloaded again from WordPress.org
 *     (if so, wordpress.org is its permanent backup; if not, only the
 *     server's /tmp copy and Hostinger's own backups are)
 *   - anything that still loads a file from its folder: the child and parent
 *     theme's code (php, js, css), post content, post meta (Elementor data
 *     included), options, and the HTML of key pages as visitors get them
 *
 * Run: wp eval-file tools/diag-plugin-folders.php --allow-root
 */
if (!defined('ABSPATH')) exit(1);
require_once ABSPATH . 'wp-admin/includes/plugin.php';
global $wpdb;
$KEEP = array('woocommerce', 'woocommerce-square', 'elementor', 'litespeed-cache');
$active = (array) get_option('active_plugins');
$rows = array();
foreach (get_plugins() as $file => $d) {
    $slug = dirname($file) === '.' ? basename($file, '.php') : dirname($file);
    $rows[$slug] = array('file' => $file, 'version' => $d['Version'], 'active' => in_array($file, $active, true), 'refs' => array());
}
$size = function ($dir) {
    if (!is_dir($dir)) return 0; $n = 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $f) $n += $f->getSize();
    return $n;
};
$slugs = array_keys($rows);
$re = '#/wp-content/plugins/(' . implode('|', array_map(function ($s) { return preg_quote($s, '#'); }, $slugs)) . ')/#';
$note = function ($text, $where) use (&$rows, $re) {
    if (preg_match_all($re, $text, $m)) foreach (array_unique($m[1]) as $s) if (count($rows[$s]['refs']) < 6) $rows[$s]['refs'][] = $where;
};
// theme code
foreach (array_unique(array(get_stylesheet_directory(), get_template_directory())) as $dir) {
    $base = basename($dir);
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $f) {
        if (!preg_match('/\.(php|js|css)$/', $f->getFilename()) || $f->getSize() > 3000000) continue;
        $rel = $base . substr($f->getPathname(), strlen($dir));
        if (strpos($rel, '/tools/') !== false) continue;
        $note((string) file_get_contents($f->getPathname()), 'theme ' . $rel);
    }
}
// database, in batches
$scan = function ($sql_ids, $sql_one, $label) use ($wpdb, $note) {
    $last = 0;
    while ($ids = $wpdb->get_col($wpdb->prepare($sql_ids, $last))) {
        foreach ($ids as $id) { $r = $wpdb->get_row($wpdb->prepare($sql_one, $id), ARRAY_N); if ($r) $note((string) $r[1], sprintf($label, $r[0])); $last = $id; }
    }
};
$scan("SELECT ID FROM {$wpdb->posts} WHERE ID > %d AND post_content LIKE '%%wp-content/plugins/%%' AND post_status NOT IN ('trash','auto-draft','inherit') ORDER BY ID LIMIT 200",
      "SELECT CONCAT(post_type, ' #', ID), post_content FROM {$wpdb->posts} WHERE ID = %d", 'post content %s');
$scan("SELECT meta_id FROM {$wpdb->postmeta} WHERE meta_id > %d AND meta_value LIKE '%%wp-content/plugins/%%' ORDER BY meta_id LIMIT 200",
      "SELECT CONCAT(meta_key, ' of #', post_id), meta_value FROM {$wpdb->postmeta} WHERE meta_id = %d", 'post meta %s');
$scan("SELECT option_id FROM {$wpdb->options} WHERE option_id > %d AND option_value LIKE '%%wp-content/plugins/%%' AND option_name NOT LIKE '%%transient%%' ORDER BY option_id LIMIT 200",
      "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_id = %d", 'option %s');
// key pages as visitors get them
$pages = array('/', '/shop/', '/cart/', '/checkout/', '/my-account/', '/?lang=hi', '/?currency=CAD', '/about/', '/blog/',
    '/product/radha-krishna-moonlit-melody-canvas-wall-art-3x4-feet-floating-frame-premium-digital-canvas-print-living-room-home-spiritual-wall-decor/');
foreach ($pages as $u) {
    $r = wp_remote_get(home_url($u), array('timeout' => 45, 'headers' => array('User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) Chrome/124 Safari/537.36')));
    if (!is_wp_error($r)) $note(wp_remote_retrieve_body($r), 'page ' . $u);
}
echo "=== every plugin folder ===\n";
ksort($rows);
foreach ($rows as $slug => $r) {
    $kb = round($size(WP_PLUGIN_DIR . '/' . $slug) / 1024);
    $wporg = '';
    if (!$r['active']) {
        $h = wp_remote_head('https://downloads.wordpress.org/plugin/' . rawurlencode($slug) . '.' . rawurlencode($r['version']) . '.zip', array('timeout' => 20, 'redirection' => 0));
        $code = is_wp_error($h) ? 0 : (int) wp_remote_retrieve_response_code($h);
        $wporg = $code === 200 ? 'on wordpress.org (this version)' : 'NOT on wordpress.org (HTTP ' . $code . ')';
    }
    printf("%-46s %-9s %-10s %7s KB %s%s%s\n", $slug, $r['active'] ? 'ACTIVE' : 'inactive', $r['version'], number_format($kb),
        in_array($slug, $KEEP, true) ? 'KEEP ' : '', $wporg, file_exists(WP_PLUGIN_DIR . '/' . $slug . '/uninstall.php') ? ', has uninstall.php' : '');
    if (!$r['active'] && $r['refs']) echo "    still loaded from: " . implode(' | ', $r['refs']) . "\n";
}
echo "\nmust-use plugins (never touched): " . implode(', ', array_keys(get_mu_plugins())) . "\n";
echo "drop-ins (never touched): " . implode(', ', array_keys(get_dropins())) . "\n";
echo "done\n";
