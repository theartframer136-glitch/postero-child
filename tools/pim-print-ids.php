<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Print the Products In Motion video ids, one per line, and nothing else.
 *
 * A file, deliberately. The deploy step used to carry this same PHP inline
 * through YAML, bash, ssh and a remote shell — four levels of quoting — and it
 * arrived mangled: zero ids, an instant exit, and a step that looked like it
 * had succeeded. Every other step in this pipeline uses eval-file for exactly
 * this reason.
 *
 * Run: wp eval-file tools/pim-print-ids.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }

$channel = 'UC_GX4vXRQrN4GsvSfgmZxYw';
$ids = get_transient('af_yt_ids3_' . $channel);
if (!is_array($ids) || !$ids) $ids = get_option('af_yt_ids3_lastgood_' . $channel);
if (!is_array($ids)) return;
foreach ($ids as $id) {
    $id = trim((string) $id);
    if (preg_match('/^[A-Za-z0-9_-]{11}$/', $id)) echo $id, "\n";
}
