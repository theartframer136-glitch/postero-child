<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Give the Products In Motion row a local video for every card it can.
 *
 * The row's cards each paid YouTube's negotiation before their first frame —
 * seconds of black, then the embed's own title text over the artwork. A card
 * playing a file from this site starts in one request and shows no chrome at
 * all. This decides, per video, which local file that is.
 *
 * Two sources, in order:
 *
 *   1. uploads/pim/<videoid>.mp4 — what the deploy's mirror step downloads.
 *   2. an mp4 ALREADY in the Media Library whose title or filename matches the
 *      video's title. The studio uploads its own reels; when a match exists
 *      there is nothing to download and the card can be local today.
 *
 * The result is written to the af_pim_local option as videoid => URL, which is
 * what the row reads. Read-mostly: it writes that one option and nothing else.
 *
 * Run: wp eval-file tools/pim-local-video.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
global $wpdb;

$channel = 'UC_GX4vXRQrN4GsvSfgmZxYw';
echo "=== PRODUCTS IN MOTION — LOCAL VIDEO SOURCES ===\n";

/* ── the videos the row shows ─────────────────────────────────────────── */
$ids = get_transient('af_yt_ids3_' . $channel);
if (!is_array($ids) || !$ids) $ids = get_option('af_yt_ids3_lastgood_' . $channel);
$ids = is_array($ids) ? array_values(array_filter($ids)) : array();
printf("  videos in the row: %d\n", count($ids));
if (!$ids) { echo "  nothing to map.\n=== DONE ===\n"; return; }

$titles = get_option('af_yt_titles_' . $channel);
if (!is_array($titles)) $titles = array();

/* ── what is already mirrored ─────────────────────────────────────────── */
$up   = wp_get_upload_dir();
$dir  = trailingslashit($up['basedir']) . 'pim/';
$url  = trailingslashit($up['baseurl']) . 'pim/';
if (!is_dir($dir)) @wp_mkdir_p($dir);
$mirrored = array();
foreach ($ids as $vid) {
    if (file_exists($dir . $vid . '.mp4')) $mirrored[$vid] = $url . $vid . '.mp4';
}
printf("  already mirrored in uploads/pim: %d\n", count($mirrored));

/* ── videos already in the Media Library ──────────────────────────────── */
$atts = $wpdb->get_results(
    "SELECT ID, post_title, guid FROM {$wpdb->posts}
      WHERE post_type = 'attachment' AND post_mime_type LIKE 'video/%'
      ORDER BY post_date DESC");
printf("  video files in the Media Library: %d\n", count($atts));
foreach (array_slice($atts, 0, 20) as $a) {
    printf("    #%-8d %-44s %s\n", $a->ID,
        substr((string) $a->post_title, 0, 44),
        basename(parse_url($a->guid, PHP_URL_PATH)));
}

/** Words only, lowercased — so "Radha Krishna Flute Melody 💙 | Divine Love"
 *  and "radha-krishna-flute-melody.mp4" compare as the same thing. */
function af_pim_norm($s) {
    $s = strtolower(html_entity_decode((string) $s, ENT_QUOTES, 'UTF-8'));
    $s = preg_replace('/\.[a-z0-9]{2,4}$/', '', $s);      // drop a file extension
    $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
    return trim(preg_replace('/\s+/', ' ', $s));
}

/** How many leading words two strings share — a cheap, order-respecting
 *  similarity that will not marry two unrelated reels on one common word. */
function af_pim_lead_match($a, $b) {
    $wa = explode(' ', $a); $wb = explode(' ', $b);
    $n = 0;
    while ($n < count($wa) && $n < count($wb) && $wa[$n] === $wb[$n]) $n++;
    return $n;
}

/* ── match what is left ───────────────────────────────────────────────── */
$map = $mirrored;
$matched = 0; $unmatched = array();
foreach ($ids as $vid) {
    if (isset($map[$vid])) continue;
    $t = isset($titles[$vid]) ? af_pim_norm($titles[$vid]) : '';
    if ($t === '') { $unmatched[] = $vid . ' (no title known)'; continue; }

    $best = null; $bestScore = 0;
    foreach ($atts as $a) {
        $cand = max(
            af_pim_lead_match($t, af_pim_norm($a->post_title)),
            af_pim_lead_match($t, af_pim_norm(basename(parse_url($a->guid, PHP_URL_PATH))))
        );
        if ($cand > $bestScore) { $bestScore = $cand; $best = $a; }
    }
    // Four leading words in common is a deliberate title, not a coincidence.
    // Two would marry every "Radha Krishna ..." reel to the first one found.
    if ($best && $bestScore >= 4) {
        $map[$vid] = wp_get_attachment_url($best->ID);
        $matched++;
        printf("  MATCH %s -> #%d %s (%d words)\n", $vid, $best->ID,
            basename(parse_url($best->guid, PHP_URL_PATH)), $bestScore);
    } else {
        $unmatched[] = $vid . (isset($titles[$vid]) ? ' — ' . substr($titles[$vid], 0, 46) : '');
    }
}

update_option('af_pim_local', $map, false);

printf("\n  local sources now available: %d of %d\n", count($map), count($ids));
printf("    from uploads/pim : %d\n", count($mirrored));
printf("    from the library : %d\n", $matched);
if ($unmatched) {
    printf("  still on the YouTube embed: %d\n", count($unmatched));
    foreach (array_slice($unmatched, 0, 12) as $u) echo "    " . $u . "\n";
}
echo "=== DONE ===\n";
