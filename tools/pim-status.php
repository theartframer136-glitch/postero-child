<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * One short report: how many Products In Motion cards play from this site.
 *
 * It exists because of where it RUNS, not what it computes. The mapping step
 * runs early in the deploy, and this pipeline's log is long enough that the
 * Actions API only ever hands back the tail — so that step's output has been
 * unreadable three deploys running, and each time I have been reasoning about
 * a step I could not see. Printed last, it is always readable.
 *
 * Read-only. Run: wp eval-file tools/pim-status.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }

$channel = 'UC_GX4vXRQrN4GsvSfgmZxYw';
echo "=== PRODUCTS IN MOTION — HOW MANY CARDS ARE LOCAL ===\n";

$ids = get_transient('af_yt_ids3_' . $channel);
if (!is_array($ids) || !$ids) $ids = get_option('af_yt_ids3_lastgood_' . $channel);
$ids = is_array($ids) ? array_values(array_filter($ids)) : array();

$map = get_option('af_pim_local');
if (!is_array($map)) $map = array();

$up  = wp_get_upload_dir();
$dir = trailingslashit($up['basedir']) . 'pim/';
$files = is_dir($dir) ? array_values(array_diff((array) @scandir($dir), array('.', '..'))) : array();

printf("  videos in the row      : %d\n", count($ids));
printf("  mapped to a local file : %d\n", count($map));
printf("  files in uploads/pim   : %d\n", count($files));
if ($files) {
    foreach (array_slice($files, 0, 12) as $f) {
        printf("      %-24s %s\n", $f, size_format((int) @filesize($dir . $f)));
    }
}

$local = 0; $embed = array();
foreach ($ids as $vid) {
    if (file_exists($dir . $vid . '.mp4') || isset($map[$vid])) $local++;
    else $embed[] = $vid;
}
printf("\n  CARDS PLAYING FROM THIS SITE : %d of %d\n", $local, count($ids));
if ($embed) {
    printf("  still on the YouTube embed   : %d\n", count($embed));
    echo "    " . implode(', ', array_slice($embed, 0, 10)) . "\n";
}
// Videos already in the library at all — if this is 0, matching can never
// help and downloading is the only route.
global $wpdb;
$nvid = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->posts}
      WHERE post_type='attachment' AND post_mime_type LIKE 'video/%'");
printf("\n  video files in the Media Library: %d\n", $nvid);

// The moving previews are what the row actually shows when no mp4 exists, so
// their count belongs in the same summary as the mp4 count - otherwise
// "0 of 16 local" reads as "nothing works" when the row is in fact moving.
$anim = get_option('af_pim_anim');
if (!is_array($anim)) $anim = array();
$anim_hit = 0;
foreach ($ids as $vid) if (!empty($anim[$vid])) $anim_hit++;
printf("  cards with a MOVING preview     : %d of %d\n", $anim_hit, count($ids));
if ($anim_hit) {
    $k = array_keys($anim);
    printf("      e.g. %s\n", $anim[$k[0]]);
}

// The fetch step runs early and its own log sits outside the window the
// Actions API returns, so it is replayed here where it can actually be read.
// Three deploys were spent guessing at that step's behaviour; none needed to
// be.
$flog = $dir . 'last-fetch.log';
echo "\n  --- what the fetch step did (replayed) ---\n";
if (is_readable($flog)) {
    $txt = (string) file_get_contents($flog);
    foreach (array_slice(explode("\n", trim($txt)), -40) as $line) {
        echo '  ' . $line . "\n";
    }
} else {
    echo "  (no fetch log at {$flog} - the step did not reach its first write)\n";
}
echo "=== DONE ===\n";
