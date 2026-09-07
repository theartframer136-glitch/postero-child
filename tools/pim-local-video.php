<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Point every Products In Motion card at a real local video where one exists,
 * and say plainly why a card has none.
 *
 * The matching itself lives in the theme (af_pim_build_local_map), because it
 * also has to run the instant an upload finishes — the owner drags their
 * downloaded reels into WordPress → Media and the cards must switch to those
 * files without a deploy. This file is the deploy's readable report of what
 * that matcher decided, plus the two facts that explain a failure to match:
 * what the row expects to be called, and what the library actually holds.
 *
 * Run: wp eval-file tools/pim-local-video.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }

$channel = 'UC_GX4vXRQrN4GsvSfgmZxYw';
echo "=== PRODUCTS IN MOTION — LOCAL VIDEO SOURCES ===\n";

if (!function_exists('af_pim_build_local_map')) {
    echo "  the theme's matcher is not loaded (deploy not landed yet)\n=== DONE ===\n";
    return;
}

$ids = get_transient('af_yt_ids3_' . $channel);
if (!is_array($ids) || !$ids) $ids = get_option('af_yt_ids3_lastgood_' . $channel);
$ids = is_array($ids) ? array_values(array_filter($ids)) : array();
$titles = get_option('af_yt_titles_' . $channel);
if (!is_array($titles)) $titles = array();

printf("  videos in the row: %d   (titles known: %d)\n", count($ids), count($titles));

$atts = af_pim_media_videos();
printf("  video files in the Media Library: %d\n", count($atts));
foreach (array_slice($atts, 0, 25) as $a) {
    printf("    #%-8d %-52.52s %s\n", $a['id'], $a['file'], substr($a['title'], 0, 40));
}

$report = array();
$map = af_pim_build_local_map($report);
echo "\n  -- what matched --\n";
foreach ($report as $line) echo '    ' . $line . "\n";
if (!$report) echo "    (nothing)\n";

$unmatched = array();
foreach ($ids as $vid) if (!isset($map[$vid])) $unmatched[] = $vid;

printf("\n  CARDS WITH A REAL LOCAL VIDEO: %d of %d\n", count($map), count($ids));
if ($unmatched) {
    echo "  still without one — the row's own titles, which a downloaded file\n";
    echo "  should resemble:\n";
    foreach ($unmatched as $vid) {
        printf("    %s  %s\n", $vid, isset($titles[$vid]) ? substr($titles[$vid], 0, 64) : '(title unknown)');
    }
}

// The other half of a failed match: files that ARE uploaded and were claimed by
// nothing. Printed beside the unclaimed titles above, the two lists together
// say whether the fix is a rename or something else entirely — without which
// "0 matched" is just as unreadable as it was for the reels.
$claimed = array_flip(array_map('strval', array_values($map)));
$spare = array();
foreach ($atts as $a) if (!isset($claimed[$a['url']])) $spare[] = $a;
if ($spare) {
    printf("\n  uploaded videos claimed by no card: %d\n", count($spare));
    foreach (array_slice($spare, 0, 20) as $a) {
        printf("    #%-8d %s\n", $a['id'], $a['file']);
    }
}

// A file too large for the server is the one failure that looks like the owner
// doing nothing, so it is stated before it can be misread as that.
printf("\n  server upload limits: upload_max_filesize=%s post_max_size=%s\n",
    ini_get('upload_max_filesize'), ini_get('post_max_size'));
echo "=== DONE ===\n";
