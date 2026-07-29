<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Confirm the live-camera AR + 1/2/4 panel wall layouts actually shipped to
 * the two preview pages, and that the Permissions-Policy header allows the
 * camera. Fetches the pages the same way a browser would.
 * Read-only.
 * Run: wp eval-file tools/verify-ar-layouts.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$checks = array(
    'try-on-wall' => array(
        'url'    => home_url('/try-on-wall/?nocache=' . time()),
        'markers'=> array(
            'layout chips'      => 'id="tow-layouts"',
            '2-panel chip'      => 'data-n="2"',
            '4-panel chip'      => 'data-n="4"',
            'panel wrapper'     => 'id="tow-panels"',
            'camera button'     => 'id="tow-cambtn"',
            'camera video'      => 'id="tow-cam"',
            'camera stop'       => 'id="tow-camstop"',
            'getUserMedia call' => 'getUserMedia',
            'rear camera'       => "facingMode:'environment'",
            'panel CSS'         => '.af-tow-panels{',
            'camera CSS'        => '.af-tow-cam{',
        ),
        'gone'   => array(
            'old single art node' => 'id="tow-art"',
        ),
    ),
    'frame-the-moment' => array(
        'url'    => home_url('/frame-the-moment/?nocache=' . time()),
        'markers'=> array(
            'layout chips'      => 'id="ftm-layouts"',
            '2-panel chip'      => 'data-n="2"',
            '4-panel chip'      => 'data-n="4"',
            'panel wrapper'     => 'id="ftm-panels"',
            'camera button'     => 'id="ftm-cambtn"',
            'camera video'      => 'id="ftm-camv"',
            'camera stop'       => 'id="ftm-camstop"',
            'getUserMedia call' => 'getUserMedia',
            'rear camera'       => "facingMode:'environment'",
            'cover-crop'        => 'function ensureCrop',
            'panel CSS'         => '.af-ftm-panels{',
            'camera CSS'        => '.af-ftm-camv{',
        ),
        'gone'   => array(
            'old single art node' => 'id="ftm-art"',
        ),
    ),
);

$fail = 0;
foreach ($checks as $label => $spec) {
    echo "\n=== {$label} ===\n";
    $r = wp_remote_get($spec['url'], array('timeout'=>60,'sslverify'=>false,'headers'=>array('User-Agent'=>'AF-Verify')));
    if (is_wp_error($r)) { echo "  FETCH FAILED: ".$r->get_error_message()."\n"; $fail++; continue; }
    $code = wp_remote_retrieve_response_code($r);
    $body = wp_remote_retrieve_body($r);
    echo "  HTTP {$code}, ".number_format(strlen($body)/1024, 0)." KB\n";

    // header check
    $pp = wp_remote_retrieve_header($r, 'permissions-policy');
    if (is_array($pp)) $pp = implode('; ', $pp);
    $camok = ($pp === '' || $pp === null) ? null : (strpos($pp, 'camera=(self)') !== false);
    if ($camok === null)      echo "  Permissions-Policy: (not sent)\n";
    elseif ($camok)           echo "  Permissions-Policy: camera=(self) OK  [{$pp}]\n";
    else { echo "  Permissions-Policy: CAMERA BLOCKED  [{$pp}]\n"; $fail++; }

    // whitespace-insensitive presence test (minifiers collapse spaces)
    foreach ($spec['markers'] as $name => $needle) {
        $pat = '/' . preg_replace('/\s+/', '\\s*', preg_quote($needle, '/')) . '/i';
        $hit = (bool) preg_match($pat, $body);
        printf("  %-20s %s\n", $name, $hit ? 'OK' : 'MISSING  <<<');
        if (!$hit) $fail++;
    }
    foreach ($spec['gone'] as $name => $needle) {
        $hit = strpos($body, $needle) !== false;
        printf("  %-20s %s\n", $name, $hit ? 'STILL PRESENT  <<<' : 'removed OK');
        if ($hit) $fail++;
    }
}

echo "\n=== RESULT: " . ($fail ? "{$fail} PROBLEM(S)" : "ALL CHECKS PASSED") . " ===\n";
