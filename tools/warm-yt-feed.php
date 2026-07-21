<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Warm the Products In Motion YouTube feed cache so the full video row shows
 * immediately: fetch the channel RSS server-side, seed the transient AND the
 * durable last-known-good option. If YouTube blocks the fetch and no last-good
 * exists yet, seed from the known video-ID list so the section is never empty.
 * Idempotent. Run: wp eval-file tools/warm-yt-feed.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$CHANNEL = 'UC_GX4vXRQrN4GsvSfgmZxYw';
$url = 'https://www.youtube.com/feeds/videos.xml?channel_id=' . $CHANNEL;

$videos = array();
$resp = wp_remote_get($url, array('timeout' => 15));
if (!is_wp_error($resp) && (int) wp_remote_retrieve_response_code($resp) === 200) {
    $xml = simplexml_load_string(wp_remote_retrieve_body($resp));
    if ($xml) {
        foreach ($xml->entry as $entry) {
            $yt = $entry->children('http://www.youtube.com/xml/schemas/2015');
            $vid = (string) ($yt->videoId ?? '');
            if (!$vid) { preg_match('/video:([A-Za-z0-9_-]{11})/', (string) $entry->id, $m); $vid = $m[1] ?? ''; }
            if ($vid) {
                $videos[] = array(
                    'id'    => $vid,
                    'title' => (string) ($entry->title ?? ''),
                    'thumb' => 'https://img.youtube.com/vi/' . $vid . '/hqdefault.jpg',
                );
            }
        }
    }
}

if ($videos) {
    set_transient('af_yt_' . $CHANNEL, $videos, 2 * HOUR_IN_SECONDS);
    update_option('af_yt_lastgood_' . $CHANNEL, $videos, false);
    echo "Feed fetched OK: " . count($videos) . " videos cached + saved as last-good.\n";
} else {
    echo "Feed fetch FAILED (YouTube blocked or bad XML).\n";
    $existing = get_option('af_yt_lastgood_' . $CHANNEL);
    if (is_array($existing) && $existing) {
        echo "Existing last-good present (" . count($existing) . " videos) — section stays populated.\n";
    } else {
        // Seed from known-good IDs captured in earlier diagnostics
        $ids = array('az-5B9YjO-U','wJGiXVAtndk','U_a3R44j_S4','dQOjUUm9TMo','bGRPG8WYVC4','Jnu1ToBccwk','Rwa4niCLtLw','mOTIniTk3hE');
        $seed = array();
        foreach ($ids as $v) { $seed[] = array('id'=>$v,'title'=>'','thumb'=>'https://img.youtube.com/vi/'.$v.'/hqdefault.jpg'); }
        update_option('af_yt_lastgood_' . $CHANNEL, $seed, false);
        set_transient('af_yt_' . $CHANNEL, $seed, 2 * HOUR_IN_SECONDS);
        echo "Seeded last-good with " . count($seed) . " known video IDs.\n";
    }
}
echo "=== DONE ===\n";
