<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Warm the Products In Motion video row so it always shows the full set.
 * The PHP-rendered section reads the transient 'af_yt_ids3_<channel>' and a
 * durable option 'af_yt_ids3_lastgood_<channel>'. Build the full ID list from
 * the homepage Elementor content + the channel RSS (+ a known seed if YouTube
 * blocks the fetch), DELETE any stale small transient, then seed both keys.
 * Idempotent. Run: wp eval-file tools/warm-yt-feed.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$channel = 'UC_GX4vXRQrN4GsvSfgmZxYw';
$ids = array();

// 1) IDs embedded in the homepage Elementor content
$fp  = (int) get_option('page_on_front');
$raw = $fp ? get_post_meta($fp, '_elementor_data', true) : '';
if ($raw) {
    $flat = str_replace('\\/', '/', $raw);
    if (preg_match_all('#(?:youtube(?:-nocookie)?\.com/(?:watch\?(?:[^"&]*&)*v=|embed/|shorts/|v/)|youtu\.be/)([A-Za-z0-9_-]{11})#i', $flat, $m)) $ids = array_merge($ids, $m[1]);
    if (preg_match_all('#[?&]v=([A-Za-z0-9_-]{11})#', $flat, $m2)) $ids = array_merge($ids, $m2[1]);
}

// 2) Channel RSS (latest uploads)
$resp = wp_remote_get('https://www.youtube.com/feeds/videos.xml?channel_id=' . $channel, array('timeout'=>15,'sslverify'=>false));
$rss = 0;
if (!is_wp_error($resp) && (int) wp_remote_retrieve_response_code($resp) === 200) {
    $xml = @simplexml_load_string(wp_remote_retrieve_body($resp));
    if ($xml) {
        foreach ($xml->entry as $entry) {
            if (preg_match('/video:([A-Za-z0-9_-]{11})/', (string)$entry->id, $mm)) { $ids[] = $mm[1]; $rss++; }
        }
    }
}
echo "Elementor+RSS produced " . count(array_unique($ids)) . " ids (rss={$rss}).\n";

// 3) If YouTube blocked us and we still have almost nothing, fall back to the
//    known-good ID list captured from earlier diagnostics, then to any saved
//    last-good option.
$seed = array('az-5B9YjO-U','wJGiXVAtndk','U_a3R44j_S4','dQOjUUm9TMo','bGRPG8WYVC4','Jnu1ToBccwk','Rwa4niCLtLw','mOTIniTk3hE','XHOmBV4js_E');
if (count(array_unique(array_filter($ids))) < 3) {
    $saved = get_option('af_yt_ids3_lastgood_' . $channel);
    if (is_array($saved) && count($saved) >= 3) { $ids = array_merge($ids, $saved); echo "Used saved last-good (" . count($saved) . ").\n"; }
    else { $ids = array_merge($ids, $seed); echo "Used known seed list (" . count($seed) . ").\n"; }
}

$ids = array_values(array_unique(array_filter($ids)));
$ids = array_slice($ids, 0, 30);

// Delete any stale small transient, then seed the correct keys.
delete_transient('af_yt_ids3_' . $channel);
if ($ids) {
    set_transient('af_yt_ids3_' . $channel, $ids, HOUR_IN_SECONDS);
    update_option('af_yt_ids3_lastgood_' . $channel, $ids, false);
    echo "Seeded af_yt_ids3_ transient + last-good option with " . count($ids) . " video IDs.\n";
} else {
    echo "No IDs available to seed.\n";
}

// VERIFY: purge the page cache and fetch a fresh homepage; count video circles.
if (function_exists('do_action')) do_action('litespeed_purge_all');
sleep(2);
$hr = wp_remote_get(home_url('/'), array('timeout'=>25,'sslverify'=>false,'headers'=>array('User-Agent'=>'AF-Warm')));
if (!is_wp_error($hr)) {
    $hb = wp_remote_retrieve_body($hr);
    echo "VERIFY home fresh: " . strlen($hb) . " bytes | af-pim-circle x" . substr_count($hb, 'af-pim-circle')
       . " | af-pim-wrap x" . substr_count($hb, 'id="afPimWrap"') . "\n";
} else {
    echo "VERIFY fetch failed: " . $hr->get_error_message() . "\n";
}
echo "=== DONE ===\n";
