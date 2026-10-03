<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. Why Elementor Pro does not update (3 Oct). WP-CLI's download of
 * Elementor Pro 4.3.1 from Elementor's server was answered "Unauthorized",
 * while the licence data stored on the site reads "license valid, expires
 * 01.01.2030". Elementor writes that data from its own server's answer; a
 * copy of the plugin that writes it itself (with a fixed date) has no licence
 * Elementor's server knows, and can never update from it.
 *
 * This prints, never any key or token:
 *   - the installed Elementor Pro version and where its updates come from
 *     (the download address's host and path only)
 *   - the stored key's shape (length, hex or not), not the key
 *   - every line in Elementor Pro's own files, the must-use plugins and the
 *     theme that writes the licence options or carries the 01.01.2030 date
 *   - any Code Snippets snippet that does
 *
 * Run: wp eval-file tools/diag-elementor-pro-licence.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
global $wpdb;
require_once ABSPATH . 'wp-admin/includes/plugin.php';

$plugins = get_plugins();
$pro = isset($plugins['elementor-pro/elementor-pro.php']) ? $plugins['elementor-pro/elementor-pro.php'] : null;
echo "Elementor Pro installed: " . ($pro ? $pro['Version'] . (is_plugin_active('elementor-pro/elementor-pro.php') ? ' (active)' : ' (inactive)') : 'no') . "\n";
if ($pro) {
    echo "  header: Plugin URI " . $pro['PluginURI'] . " | Author " . $pro['Author'] . "\n";
}

$t = get_site_transient('update_plugins');
if ($t && isset($t->response['elementor-pro/elementor-pro.php'])) {
    $u = $t->response['elementor-pro/elementor-pro.php'];
    $p = isset($u->package) ? wp_parse_url((string) $u->package) : array();
    echo "  update offered: " . (isset($u->new_version) ? $u->new_version : '?') . ' from ' . (isset($p['host']) ? $p['host'] : '(no download address)') . (isset($p['path']) ? preg_replace('#/[A-Za-z0-9_\-]{20,}#', '/[masked]', $p['path']) : '') . "\n";
}

$key = (string) get_option('elementor_pro_license_key');
echo "  stored key: " . ($key === '' ? 'none' : 'length ' . strlen($key) . (ctype_xdigit($key) ? ', hex' : ', not hex')) . "\n";
foreach (array('_elementor_pro_license_v2_data', '_elementor_pro_license_data') as $o) {
    $d = get_option($o);
    if (!is_array($d)) { echo "  $o: not stored\n"; continue; }
    $v = isset($d['value']) ? json_decode($d['value'], true) : $d;
    $keys = is_array($v) ? array_keys($v) : array();
    echo "  $o: fields " . implode(', ', $keys) . "\n";
}

// Lines that write the licence options or carry the fixed date.
$pat = '/01\.01\.2030|_elementor_pro_license_(v2_)?data|elementor_pro_license_key/';
$dirs = array(WP_PLUGIN_DIR . '/elementor-pro', WPMU_PLUGIN_DIR, get_stylesheet_directory(), get_template_directory());
$hits = 0;
foreach (array_unique($dirs) as $dir) {
    if (!is_dir($dir)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (substr($f->getFilename(), -4) !== '.php' || $f->getSize() > 2000000) continue;
        $lines = @file($f->getPathname());
        if (!$lines) continue;
        foreach ($lines as $n => $line) {
            if (!preg_match($pat, $line)) continue;
            // Only lines that set something or carry the date; reads are many and say little.
            if (!preg_match('/01\.01\.2030|update_option|add_option|set_transient|update_site_option|=>\s*[\'"]valid/', $line)) continue;
            $hits++;
            if ($hits > 40) break 3;
            $show = preg_replace('/[A-Za-z0-9]{24,}/', '[masked]', trim($line));
            echo '  ' . str_replace(ABSPATH, '', $f->getPathname()) . ':' . ($n + 1) . ': ' . substr($show, 0, 200) . "\n";
        }
    }
}
echo "lines found that set the licence or carry 01.01.2030: $hits" . ($hits > 40 ? ' (first 40 shown)' : '') . "\n";

// Code Snippets keeps its snippets in the database.
$tbl = $wpdb->prefix . 'snippets';
if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tbl)) === $tbl) {
    $rows = $wpdb->get_results("SELECT id, name, active FROM `$tbl` WHERE code LIKE '%01.01.2030%' OR code LIKE '%_elementor_pro_license%'");
    echo "Code Snippets snippets touching the licence: " . count($rows) . "\n";
    foreach ($rows as $r) echo "  #" . $r->id . ' ' . $r->name . ($r->active ? ' (active)' : ' (inactive)') . "\n";
}
echo "done\n";
