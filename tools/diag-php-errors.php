<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. The most recent PHP fatal errors and uncaught exceptions from the
 * site's error logs (wp-content/debug.log, the php error_log), newest last,
 * with long opaque strings masked. Used to find why a page answered 500.
 *
 * Run: wp eval-file tools/diag-php-errors.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
$files = array_unique(array_filter(array(
    ABSPATH . 'wp-content/debug.log',
    (defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/debug.log' : ''),
    (string) ini_get('error_log'),
    ABSPATH . 'error_log',
    ABSPATH . 'wp-admin/error_log',
    dirname(ABSPATH) . '/logs/error.log',
)));
foreach ($files as $f) {
    if (!is_file($f) || !is_readable($f)) { echo "=== $f: (none)\n"; continue; }
    $size = filesize($f);
    echo "=== $f: " . round($size / 1048576, 1) . " MB, modified " . gmdate('Y-m-d H:i', filemtime($f)) . " UTC\n";
    $h = fopen($f, 'r');
    fseek($h, max(0, $size - 25 * 1048576)); // the last 25 MB is plenty
    $hits = array();
    while (($line = fgets($h)) !== false) {
        if (preg_match('/PHP (Fatal|Parse) error|Uncaught|Cannot redeclare|Cannot declare|Allowed memory size|Maximum execution time/i', $line)) {
            $hits[] = preg_replace('/[A-Za-z0-9_\-]{48,}/', '[masked]', rtrim($line));
            if (count($hits) > 400) array_shift($hits);
        }
    }
    fclose($h);
    // collapse repeats, keep the last 30 distinct messages with their count and last time
    $seen = array();
    foreach ($hits as $l) {
        $k = preg_replace('/^\[[^\]]*\]\s*/', '', $l);
        $seen[$k] = array(isset($seen[$k]) ? $seen[$k][0] + 1 : 1, substr($l, 0, 26));
    }
    foreach (array_slice($seen, -30, 30, true) as $k => $v) echo "  x{$v[0]} last {$v[1]} :: " . substr($k, 0, 700) . "\n";
}
echo "=== END\n";
