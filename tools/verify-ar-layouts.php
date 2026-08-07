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
            'save to account'   => 'id="tow-saveacct"',
            'share whatsapp'    => 'id="tow-share-wa"',
            'share email'       => 'id="tow-share-mail"',
            'share copy'        => 'id="tow-share-copy"',
            'share helper'      => 'window.AFPreview',
            // the wall used to end 600px above the bottom of the options rail,
            // leaving the right-hand half of the page blank
            'room card under wall' => 'af-tow-panel af-tow-room',
            'room card styling'    => '.af-tow-room{',
            // the wall now stretches to match the control rail instead of
            // standing at a fixed height, so assert the elastic rule, not a number
            'photoreal moulding'   => 'function frameTexture',
            'mitred border-image'  => 'borderImage',
            'contact shadow'       => 'drop-shadow(0 2px 4px',
            'stage fills column'   => 'align-self:stretch',
            'stage flexes'         => 'flex:1 1 auto',
            'stage height capped'  => 'max-height:760px',
            'calibration rectangle'=> 'id="tow-calbox"',
            'wall-height chips'    => 'id="tow-calh"',
            'recalibrate chip'     => 'id="tow-recal"',
            'edge detector'        => 'function calSample',
            'measured px-per-ft'   => 'CAL.pxPerFt',
            'walk-tracking'        => 'CAL.spanLock',
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
            'save to account'   => 'id="ftm-saveacct"',
            'share whatsapp'    => 'id="ftm-share-wa"',
            'share email'       => 'id="ftm-share-mail"',
            'share copy'        => 'id="ftm-share-copy"',
            'share helper'      => 'window.AFPreview',
            'cards under wall'  => 'class="af-ftm-under"',
            'cards styling'     => '.af-ftm-under{',
            'stage fills column'=> 'align-self:stretch',
            'stage flexes'      => 'flex:1 1 auto',
            'stage height capped'=> 'max-height:760px',
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

// The Saved Previews account endpoint only resolves once rewrites are flushed.
echo "\n=== saved previews (my account) ===\n";
if (function_exists('wc_get_endpoint_url') && function_exists('wc_get_page_permalink')) {
    $url = wc_get_endpoint_url('saved-previews', '', wc_get_page_permalink('myaccount'));
    echo "  endpoint URL: {$url}\n";
    $r = wp_remote_get($url, array('timeout'=>45,'sslverify'=>false,'headers'=>array('User-Agent'=>'AF-Verify')));
    if (is_wp_error($r)) { echo "  FETCH FAILED: ".$r->get_error_message()."\n"; $fail++; }
    else {
        $code = wp_remote_retrieve_response_code($r);
        // logged out it renders the account login form, which is a 200 — a 404
        // means the rewrite rule never registered
        printf("  HTTP %d %s\n", $code, $code === 404 ? ' <<< endpoint not registered (needs rewrite flush)' : 'OK');
        if ($code === 404) $fail++;
    }
} else {
    echo "  WooCommerce helpers unavailable — skipped\n";
}
$menu = has_filter('woocommerce_account_menu_items') ? 'registered' : 'MISSING <<<';
echo "  account menu filter: {$menu}\n";
// Saved preview images must never be listed by the anonymous media endpoint —
// attachment ids are sequential, so that would walk past the secret filename.
$prev = get_posts(array('post_type'=>'af_preview','posts_per_page'=>20,'fields'=>'ids','post_status'=>'any'));
if ($prev) {
    $leaky = 0;
    foreach ($prev as $pv) {
        $att = get_post_thumbnail_id($pv);
        if ($att && get_post_status($att) !== 'private') $leaky++;
    }
    echo "  preview images private: " . ($leaky ? "{$leaky} of " . count($prev) . " PUBLIC  <<<" : 'all ' . count($prev) . ' OK') . "\n";
    if ($leaky) $fail++;

    $rest = wp_remote_get(home_url('/wp-json/wp/v2/media?per_page=100&search=wall+preview'),
                          array('timeout'=>45,'sslverify'=>false,'headers'=>array('User-Agent'=>'AF-Verify')));
    if (!is_wp_error($rest)) {
        $hits = json_decode(wp_remote_retrieve_body($rest), true);
        $n    = is_array($hits) ? count($hits) : 0;
        echo "  media REST leaks previews: " . ($n ? "{$n} EXPOSED  <<<" : 'none OK') . "\n";
        if ($n) $fail++;
    }
} else {
    echo "  preview images private: (none saved yet)\n";
}
echo "  CPT af_preview: " . (post_type_exists('af_preview') ? 'registered' : 'MISSING <<<') . "\n";
if (!post_type_exists('af_preview')) $fail++;
echo "  save handler: " . (has_action('wp_ajax_af_save_preview') ? 'registered' : 'MISSING <<<') . "\n";
if (!has_action('wp_ajax_af_save_preview')) $fail++;

echo "\n=== RESULT: " . ($fail ? "{$fail} PROBLEM(S)" : "ALL CHECKS PASSED") . " ===\n";
