<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * READ-ONLY. The veteran QA pass (16 Oct) reported 246 of 331 home-page
 * images "broken", naming these three. Are the files on disk, and are they
 * the sizes WordPress recorded? Makes no changes.
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
$up = wp_get_upload_dir();
foreach (array('2026/09/KR-180004-4030-586x1024.jpg', '2026/09/own-57-1024x768.jpg', '2026/09/WL-170010-5030-585x1024.jpg') as $rel) {
    $f = $up['basedir'] . '/' . $rel;
    printf("  %-40s exists=%s size=%s\n", $rel, file_exists($f) ? 'yes' : 'NO', file_exists($f) ? filesize($f) : '-');
    $orig = preg_replace('/-\d+x\d+(\.\w+)$/', '$1', $f);
    printf("    original %s exists=%s\n", basename($orig), file_exists($orig) ? 'yes' : 'NO');
}
$dir = $up['basedir'] . '/2026/09';
$n = 0; $miss = 0;
foreach (glob($dir . '/*-[0-9]*x[0-9]*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: array() as $f) $n++;
echo "  resized files in 2026/09: $n\n=== DONE\n";
