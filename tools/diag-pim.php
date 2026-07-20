<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Diagnostic for "Products In Motion": how many YouTube IDs are found from
 * (a) the homepage Elementor content and (b) the channel RSS. Read-only.
 * Run: wp eval-file tools/diag-pim.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$channel = 'UC_GX4vXRQrN4GsvSfgmZxYw';
$front = (int) get_option('page_on_front');
echo "=== PRODUCTS IN MOTION DIAGNOSTIC ===\n";
echo "Front page ID: {$front}\n\n";

// From Elementor content
$raw = get_post_meta($front, '_elementor_data', true);
$eids = array();
if ($raw) {
    $flat = str_replace('\\/', '/', $raw);
    if (preg_match_all('#(?:youtube(?:-nocookie)?\.com/(?:watch\?(?:[^"&]*&)*v=|embed/|shorts/|v/)|youtu\.be/)([A-Za-z0-9_-]{11})#i', $flat, $mm)) $eids = array_merge($eids, $mm[1]);
    if (preg_match_all('#[?&]v=([A-Za-z0-9_-]{11})#', $flat, $mm2)) $eids = array_merge($eids, $mm2[1]);
}
$eids = array_values(array_unique($eids));
echo "Elementor content: has _elementor_data=" . ($raw?'yes':'NO') . " | youtube IDs found=" . count($eids) . "\n";
echo "  sample: " . implode(', ', array_slice($eids,0,8)) . "\n\n";

// From RSS
$rids = array();
$resp = wp_remote_get('https://www.youtube.com/feeds/videos.xml?channel_id=' . $channel, ['timeout'=>8,'sslverify'=>false]);
if (is_wp_error($resp)) {
    echo "RSS: ERROR " . $resp->get_error_message() . "\n";
} else {
    $code = wp_remote_retrieve_response_code($resp);
    $body = wp_remote_retrieve_body($resp);
    echo "RSS: HTTP {$code}, body " . strlen($body) . " bytes\n";
    $xml = @simplexml_load_string($body);
    if ($xml) foreach ($xml->entry as $e) { if (preg_match('/video:([A-Za-z0-9_-]{11})/', (string)$e->id, $m)) $rids[]=$m[1]; }
    echo "  RSS IDs found=" . count($rids) . " | sample: " . implode(', ', array_slice($rids,0,8)) . "\n";
}

$all = array_values(array_unique(array_merge($eids,$rids)));
echo "\nTOTAL unique IDs available: " . count($all) . "\n";
echo (count($all) ? "RESULT: section WILL render videos.\n" : "RESULT: no IDs — section stays hidden (placeholder shows).\n");
