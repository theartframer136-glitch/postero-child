<?php
/**
 * Products In Motion: pull reel videos from Cloudflare R2 onto the site.
 *
 * Since the move to the shared Cloud plan (23 Nov 2026) nothing can copy
 * files onto the server from outside. The GitHub workflow reels-to-r2.yml
 * downloads each reel the row is missing and stores it in our R2 bucket as
 * pim/<video id>.mp4; this module, once an hour, fetches any such file the
 * site does not have yet into uploads/pim/ and purges the home page, where
 * the row lives. The card code in functions.php already prefers
 * uploads/pim/<id>.mp4 over the built still-frame clip <id>.anim.mp4, so a
 * real reel replaces its clip with no other change.
 *
 * Downloads use a short-lived signed R2 link (inc/digital-masters.php), are
 * written to a .part file and kept only if they look like an MP4 (an "ftyp"
 * box near the start) and are a sensible size; anything else is deleted.
 * At most four reels per run, so one run never holds a PHP worker long.
 */
if (!defined('ABSPATH')) exit;

const AF_PIM_R2_PREFIX   = 'pim/';
const AF_PIM_R2_PER_RUN  = 4;
const AF_PIM_R2_MIN_SIZE = 50 * 1024;          // smaller than this is an error page, not a reel
const AF_PIM_R2_MAX_SIZE = 80 * 1024 * 1024;   // the workflow caps downloads at 60 MB

/** The row's video ids that have no real reel file on the site yet. */
function af_pim_r2_missing_ids($dir) {
    $channel = 'UC_GX4vXRQrN4GsvSfgmZxYw';
    $ids = get_transient('af_yt_ids3_' . $channel);
    if (!is_array($ids) || !$ids) $ids = get_option('af_yt_ids3_lastgood_' . $channel);
    if (!is_array($ids)) return array();
    $out = array();
    foreach ($ids as $v) {
        if (!is_string($v) || !preg_match('/^[A-Za-z0-9_-]{11}$/', $v)) continue;
        if (!file_exists($dir . $v . '.mp4')) $out[] = $v;
    }
    return $out;
}

/** Whether a downloaded file is a plausible MP4: size in range and an ftyp box at the start. */
function af_pim_r2_is_mp4($file) {
    if (!is_file($file)) return false;
    $size = filesize($file);
    if ($size < AF_PIM_R2_MIN_SIZE || $size > AF_PIM_R2_MAX_SIZE) return false;
    $head = (string) file_get_contents($file, false, null, 0, 64);
    return strpos($head, 'ftyp') !== false;
}

/** One run: fetch up to AF_PIM_R2_PER_RUN missing reels from R2. Returns a short report. */
function af_pim_r2_pull() {
    $report = array('checked' => 0, 'added' => array(), 'not_in_r2' => 0, 'rejected' => array());
    if (!function_exists('af_r2_object_url') || !function_exists('af_r2_config') || !af_r2_config()) {
        $report['error'] = 'R2 not set up';
        return $report;
    }
    $up  = wp_get_upload_dir();
    $dir = trailingslashit($up['basedir']) . 'pim/';
    if (!is_dir($dir) && !wp_mkdir_p($dir)) { $report['error'] = 'cannot create uploads/pim'; return $report; }

    // Only reels that are actually waiting in R2, so reels YouTube refused
    // never crowd out the ones that arrived.
    $missing = af_pim_r2_missing_ids($dir);
    $listed  = function_exists('af_r2_list_objects') ? af_r2_list_objects() : null;
    if (is_array($listed)) {
        $report['not_in_r2'] = count(array_filter($missing, function ($v) use ($listed) { return !isset($listed[AF_PIM_R2_PREFIX . $v . '.mp4']); }));
        $missing = array_values(array_filter($missing, function ($v) use ($listed) { return isset($listed[AF_PIM_R2_PREFIX . $v . '.mp4']); }));
    }
    foreach (array_slice($missing, 0, AF_PIM_R2_PER_RUN) as $vid) {
        $report['checked']++;
        $url = af_r2_object_url(AF_PIM_R2_PREFIX . $vid . '.mp4', 600);
        if ($url === '') continue;
        $part = $dir . $vid . '.mp4.part';
        $r = wp_remote_get($url, array('timeout' => 120, 'stream' => true, 'filename' => $part));
        $code = is_wp_error($r) ? 0 : (int) wp_remote_retrieve_response_code($r);
        if ($code === 404) { @unlink($part); $report['not_in_r2']++; continue; }
        if ($code !== 200 || !af_pim_r2_is_mp4($part)) {
            @unlink($part);
            $report['rejected'][] = $vid . ' (' . (is_wp_error($r) ? $r->get_error_message() : 'HTTP ' . $code) . ')';
            continue;
        }
        if (@rename($part, $dir . $vid . '.mp4')) {
            @chmod($dir . $vid . '.mp4', 0644);
            $report['added'][] = $vid;
        } else {
            @unlink($part);
            $report['rejected'][] = $vid . ' (could not save)';
        }
    }
    if ($report['added']) do_action('litespeed_purge_url', home_url('/'));
    update_option('af_pim_r2_last', array('time' => time()) + $report, false);
    return $report;
}

add_action('af_pim_r2_pull', 'af_pim_r2_pull');
add_action('init', function () {
    if (!wp_next_scheduled('af_pim_r2_pull')) wp_schedule_event(time() + 300, 'hourly', 'af_pim_r2_pull');
});
