<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. Which of WordPress's drop-ins (files in wp-content that load
 * before any plugin) the site has, and whose they are. The must-use folder
 * is Hostinger's, shared and read-only (tools/diag-mu-plugins-dir.php, 5 Oct),
 * so the previews need another place that loads before the plugins.
 *
 * Prints, for every drop-in WordPress knows (_get_dropins()): whether the
 * file is there, its size, owner, and the name in its header or first
 * comment; WP_CACHE; and whether a persistent object cache is in use.
 *
 * Run: wp eval-file tools/diag-dropins.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
require_once ABSPATH . 'wp-admin/includes/plugin.php';

$self = get_stylesheet_directory() . '/tools/diag-dropins.php';
$me = file_exists($self) ? fileowner($self) : -1;
echo 'WP_CACHE: ' . (defined('WP_CACHE') ? var_export(WP_CACHE, true) : 'not defined') . "\n";
echo 'persistent object cache: ' . (wp_using_ext_object_cache() ? 'yes' : 'no') . "\n\n";
$known = function_exists('_get_dropins') ? _get_dropins() : array();
foreach ($known as $file => $info) {
    $p = WP_CONTENT_DIR . '/' . $file;
    if (!file_exists($p) && !is_link($p)) { echo '  ' . str_pad($file, 22) . " not there\n"; continue; }
    $head = (string) @file_get_contents($p, false, null, 0, 4096);
    $name = '';
    if (preg_match('/^[ \t\/*#@]*(?:Plugin Name|Name):\s*(.+)$/mi', $head, $m)) $name = trim($m[1]);
    elseif (preg_match('#/\*+\s*\n?\s*\*?\s*([^\n*]{3,80})#', $head, $m)) $name = trim($m[1]);
    echo '  ' . str_pad($file, 22) . ' THERE, ' . filesize($p) . ' bytes, owner ' . (fileowner($p) === $me ? 'this user' : (fileowner($p) === 0 ? 'root' : 'another user')) . (is_link($p) ? ', symlink' : '') . ($name !== '' ? ', "' . $name . '"' : '') . "\n";
}
$drop = get_dropins();
echo "\nWordPress lists as active drop-ins: " . ($drop ? implode(', ', array_keys($drop)) : 'none') . "\n";
echo "done\n";
