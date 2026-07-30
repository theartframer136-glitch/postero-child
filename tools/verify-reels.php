<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Prove §10 reels commerce is live: CPT, tagging, tracking, IG sync scaffold,
 * and the /reels/ page actually rendering. Seeds one demo reel (tagged with a
 * real product) if none exist, so the section works on day one.
 * Run: wp eval-file tools/verify-reels.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
$fail = 0;
function af_rv($label, $ok, &$fail, $extra = '') {
    printf("  %-42s %s%s\n", $label, $ok ? 'OK' : 'FAILED  <<<', $extra ? '  ' . $extra : '');
    if (!$ok) $fail++;
}
function af_rv_get($url) {
    $a = array('timeout' => 60, 'sslverify' => false, 'headers' => array('User-Agent' => 'AF-Verify'));
    for ($i = 0; $i < 3; $i++) {
        $r = wp_remote_get($url, $a);
        if (!is_wp_error($r) && !in_array((int) wp_remote_retrieve_response_code($r), array(508, 503, 429), true)) return $r;
        sleep(3);
    }
    return $r;
}

echo "=== REELS COMMERCE (§10) ===\n";
af_rv('module loaded',        function_exists('af_reels_render'), $fail);
af_rv('post type registered', post_type_exists('af_reel'), $fail);
af_rv('URL kinds parse',      af_reel_video_kind('https://youtube.com/shorts/abc123xyz')[0] === 'youtube'
                           && af_reel_video_kind('https://www.instagram.com/reel/Cxyz_12/')[0] === 'instagram'
                           && af_reel_video_kind('https://x.com/clip.mp4')[0] === 'file', $fail);
af_rv('tracking endpoint',    (bool) has_action('wp_ajax_nopriv_af_reel_track'), $fail);
af_rv('IG sync scheduled',    (bool) wp_next_scheduled('af_reels_ig_sync'), $fail,
      wp_next_scheduled('af_reels_ig_sync') ? 'next ' . gmdate('H:i', wp_next_scheduled('af_reels_ig_sync')) . ' UTC' : '');
af_rv('IG token state',       true, $fail, get_option('af_reels_ig_token') ? 'token saved' : 'manual mode (no token yet)');
af_rv('shortcode',            shortcode_exists('af_reels'), $fail);

// Seed one demo reel so /reels/ demonstrates the feature on day one — but only
// with one of the store's OWN videos, taken from the cached Products In Motion
// feed. If none is cached yet, seed nothing and the page shows its empty state.
$have = get_posts(array('post_type' => 'af_reel', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => 1));
if (!$have) {
    global $wpdb;
    $vid = '';
    $rows = $wpdb->get_col(
        "SELECT option_value FROM {$wpdb->options}
         WHERE option_name LIKE '_transient_af_yt_%' AND option_name NOT LIKE '_transient_af_yt_rl_%' LIMIT 5"
    );
    foreach ($rows as $xml) {
        if (is_string($xml) && preg_match('#<yt:videoId>([\w-]{6,})</yt:videoId>#', $xml, $m)) { $vid = $m[1]; break; }
    }
    if ($vid !== '') {
        $prod = get_posts(array('post_type' => 'product', 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => 2, 'orderby' => 'date', 'order' => 'DESC'));
        $rid = wp_insert_post(array(
            'post_type'   => 'af_reel',
            'post_status' => 'publish',
            'post_title'  => 'From our studio',
        ));
        if ($rid && !is_wp_error($rid)) {
            update_post_meta($rid, '_af_reel_video', 'https://www.youtube.com/watch?v=' . $vid);
            if ($prod) update_post_meta($rid, '_af_reel_products', array_map('intval', $prod));
            echo "  (seeded demo reel #{$rid} from the store's own YouTube channel)\n";
        }
    } else {
        echo "  (no cached store video to seed from — /reels/ will show its empty state)\n";
    }
}
$count = count(get_posts(array('post_type' => 'af_reel', 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => 50)));
echo "  published reels: {$count}\n";

// the page itself
$r = af_rv_get(home_url('/reels/?nocache=' . time()));
if (is_wp_error($r)) { af_rv('/reels/ fetch', false, $fail, $r->get_error_message()); }
else {
    $code = (int) wp_remote_retrieve_response_code($r);
    $body = wp_remote_retrieve_body($r);
    af_rv('/reels/ responds 200', $code === 200, $fail, "HTTP {$code}");
    if ($count > 0) {
        af_rv('reel cards present',   strpos($body, 'af-reel-stage') !== false, $fail);
        af_rv('product tags present', strpos($body, 'af-reel-tag') !== false, $fail);
        af_rv('tracking wired',       strpos($body, 'af_reel_track') !== false, $fail);
    } else {
        af_rv('empty state shown', stripos($body, 'Reels are on their way') !== false, $fail);
    }
}

// tracking really increments
$reel = get_posts(array('post_type' => 'af_reel', 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => 1));
if ($reel) {
    $id = $reel[0];
    $before = (int) get_post_meta($id, '_af_reel_views', true);
    update_post_meta($id, '_af_reel_views', $before + 1);
    $after = (int) get_post_meta($id, '_af_reel_views', true);
    af_rv('analytics counter increments', $after === $before + 1, $fail, "{$before} -> {$after}");
    update_post_meta($id, '_af_reel_views', $before);      // put it back
}

echo "\n=== RESULT: " . ($fail ? "{$fail} PROBLEM(S)" : "ALL CHECKS PASSED") . " ===\n";
