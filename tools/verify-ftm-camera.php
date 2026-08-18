<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Frame The Moment must offer the same live-camera calibration as Try On Wall.
 *
 * Fetches both pages and compares the pieces that make the measured-scale tool
 * work: the video element, the calibration rectangle with its four corners,
 * the wall-height buttons, the recalibrate button and the detector wiring.
 * A missing piece is named rather than summarised.
 *
 * Read-only. Run: wp eval-file tools/verify-ftm-camera.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }

echo "=== VERIFY FRAME-THE-MOMENT LIVE CAMERA ===\n";
$fail = 0;

function af_ftm_fetch($url) {
    $r = wp_remote_get(add_query_arg('afverify', time(), $url),
        array('timeout' => 60, 'sslverify' => false,
              'headers' => array('User-Agent' => 'Mozilla/5.0 AF-Verify')));
    return is_wp_error($r) ? '' : wp_remote_retrieve_body($r);
}

$pages = array(
    'try-on-wall'       => array(home_url('/try-on-wall/'), 'tow'),
    'frame-the-moment'  => array(home_url('/frame-the-moment/'), 'ftm'),
);

$results = array();
foreach ($pages as $name => $info) {
    list($url, $p) = $info;
    $html = af_ftm_fetch($url);
    printf("\n-- %s (%d bytes) --\n", $name, strlen($html));
    if ($html === '') { echo "  FETCH FAILED\n"; $fail++; continue; }

    $checks = array(
        'camera button'        => 'id="' . $p . '-cambtn"',
        'video element'        => 'id="' . $p . '-camv"',
        'stop button'          => 'id="' . $p . '-camstop"',
        'calibration overlay'  => 'id="' . $p . '-cal"',
        'calibration box'      => 'id="' . $p . '-calbox"',
        'corner marks'         => 'calcorner',
        'guidance message'     => 'id="' . $p . '-calmsg"',
        'wall height buttons'  => 'id="' . $p . '-calh"',
        'recalibrate button'   => 'id="' . $p . '-recal"',
        'getUserMedia'         => 'getUserMedia',
        'edge detector'        => 'calSample',
        'lock routine'         => 'calLock',
    );
    // the try-on-wall video id differs (tow-cam, not tow-camv)
    if ($p === 'tow') $checks['video element'] = 'id="tow-cam"';

    foreach ($checks as $what => $needle) {
        $ok = strpos($html, $needle) !== false;
        printf("  %-22s %s\n", $what, $ok ? 'OK' : 'MISSING');
        if (!$ok && $name === 'frame-the-moment') $fail++;
        $results[$name][$what] = $ok;
    }
    printf("  wall-height options: %d\n", substr_count($html, 'data-ft="'));
}

// the point of the exercise: parity
if (isset($results['try-on-wall'], $results['frame-the-moment'])) {
    echo "\n-- parity --\n";
    $missing = array();
    foreach ($results['try-on-wall'] as $what => $ok) {
        if ($ok && empty($results['frame-the-moment'][$what])) $missing[] = $what;
    }
    if ($missing) {
        echo "  Frame The Moment is missing: " . implode(', ', $missing) . "\n";
        $fail++;
    } else {
        echo "  Frame The Moment has every piece Try On Wall has  OK\n";
    }
}

printf("\n=== %s ===\n", $fail ? "{$fail} CHECK(S) FAILED" : 'ALL CHECKS PASSED');
echo "=== DONE ===\n";
