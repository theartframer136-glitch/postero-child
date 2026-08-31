<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Find a MOVING preview for each card, and remember where it lives.
 *
 * YouTube generates the short animation you see when hovering a thumbnail on
 * youtube.com, and serves it from its image CDN as an animated WebP. It is a
 * picture as far as a browser is concerned - so it autoplays, loops, and
 * carries no player, no title, no buttons and no spinner. That is the whole
 * of what was asked for, and it needs nothing installed and nothing uploaded.
 *
 * WHY A PROBE RATHER THAN A GUESSED URL
 * The animation is published under several names depending on how the video
 * was uploaded, and a Short is not the same shape as an ordinary upload. A
 * guessed URL that 404s would leave a blank card, and I have shipped enough
 * guesses on this row. So the site ASKS: for each video it tries the
 * candidates in quality order and keeps the first that answers 200 with real
 * bytes.
 *
 * This runs on the server, through WordPress's own HTTP layer - which is
 * demonstrably able to reach the internet, since every deploy's verifiers
 * fetch live pages with it. The shell on that machine cannot (curl to the
 * site's own domain times out, and the yt-dlp download failed in a second),
 * which is why this is PHP and not another shell script.
 *
 * Result: option af_pim_anim, video id => URL. Re-run refreshes it; a video
 * with no animation available simply keeps the cross-fading stills.
 *
 * Run: wp eval-file tools/pim-anim-probe.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }

$channel = 'UC_GX4vXRQrN4GsvSfgmZxYw';
$ids = get_transient('af_yt_ids3_' . $channel);
if (!is_array($ids) || !$ids) $ids = get_option('af_yt_ids3_lastgood_' . $channel);
if (!is_array($ids)) { echo "no ids\n"; return; }
$ids = array_values(array_filter($ids, function ($v) {
    return is_string($v) && preg_match('/^[A-Za-z0-9_-]{11}$/', $v);
}));

echo "=== PROBE: MOVING PREVIEWS FOR THE ROW ===\n";
printf("  videos: %d\n", count($ids));

// Best quality first. The card is 290px wide and 516 tall, so a taller source
// is worth more than a wider one: these are vertical reels, and a 16:9 still
// has to be cropped hard to fill a 9:16 card.
$patterns = array(
    'https://i.ytimg.com/an_webp/%s/mqdefault_6s.webp?du=3000',
    'https://i.ytimg.com/an_webp/%s/mqdefault_6s.webp',
    'https://i.ytimg.com/an_webp/%s/hqdefault_6s.webp',
    'https://i.ytimg.com/an_webp/%s/sddefault_6s.webp',
    'https://i.ytimg.com/an/%s/mqdefault_6s.gif',
);

$prev = get_option('af_pim_anim');
if (!is_array($prev)) $prev = array();
$map = array();
$hit = 0; $miss = 0;
$tried_report = array();

foreach ($ids as $n => $vid) {
    $found = '';
    foreach ($patterns as $pi => $pat) {
        $url = sprintf($pat, $vid);
        $r = wp_remote_get($url, array(
            'timeout'    => 12,
            'sslverify'  => false,
            'headers'    => array(
                // Without a browser-ish UA the CDN answers 404 for these even
                // when the file exists.
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36',
                'Referer'    => 'https://www.youtube.com/',
            ),
        ));
        if (is_wp_error($r)) {
            if ($n === 0) $tried_report[] = sprintf('  [%d] %s -> %s', $pi, basename(parse_url($url, PHP_URL_PATH)), $r->get_error_message());
            continue;
        }
        $code = (int) wp_remote_retrieve_response_code($r);
        $len  = strlen((string) wp_remote_retrieve_body($r));
        if ($n === 0) $tried_report[] = sprintf('  [%d] %-24s -> HTTP %d, %d bytes', $pi, basename(parse_url($url, PHP_URL_PATH)), $code, $len);
        // A real animation is tens of kilobytes; an error page is not.
        if ($code === 200 && $len > 8000) { $found = $url; break; }
    }
    if ($found !== '') { $map[$vid] = $found; $hit++; }
    else               { $miss++; }
}

if ($tried_report) {
    echo "  what the first video answered, per candidate:\n";
    foreach ($tried_report as $l) echo $l . "\n";
}
printf("\n  moving preview found : %d\n", $hit);
printf("  none available       : %d\n", $miss);

if ($map) {
    update_option('af_pim_anim', $map, false);
    echo "  saved\n";
    $k = array_keys($map);
    printf("  e.g. %s -> %s\n", $k[0], $map[$k[0]]);
} else {
    // Deliberately NOT cleared: a probe that fails because the CDN was slow
    // must not wipe a working map and blank the row.
    printf("  nothing found - keeping the %d previously saved\n", count($prev));
}
echo "=== DONE ===\n";
