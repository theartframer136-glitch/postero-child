<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Verify the reviews-overlap fix is live: the homepage must contain the
 * af-review-overlap-fix style block with the --margin-bottom:0 override for
 * elementor-element-2596335, rendered AFTER the Elementor post-75 CSS.
 * Read-only. Run: wp eval-file tools/verify-overlap-fix.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$r = wp_remote_get(home_url('/?afv=' . wp_rand()), array('timeout'=>30,'sslverify'=>false,'headers'=>array('User-Agent'=>'AF-Verify')));
if (is_wp_error($r)) { echo "fetch failed: " . $r->get_error_message() . "\n"; exit; }
$b = wp_remote_retrieve_body($r);
echo "home bytes: " . strlen($b) . "\n";

$style = strpos($b, 'af-review-overlap-fix');
$rule  = strpos($b, 'elementor-element-2596335');
$zero  = strpos($b, '--margin-bottom: 0px !important');
$eljs  = strpos($b, 'post-75.css');
echo "style block present: "   . ($style !== false ? "YES @{$style}" : 'NO') . "\n";
echo "2596335 override: "      . ($rule  !== false ? "YES" : 'NO') . "\n";
echo "--margin-bottom zero: "  . ($zero  !== false ? "YES @{$zero}" : 'NO') . "\n";
echo "post-75.css link: "      . ($eljs  !== false ? "@{$eljs}" : 'not found (may be inlined)') . "\n";
if ($zero !== false && $eljs !== false) {
    echo "order OK (override after Elementor CSS): " . ($zero > $eljs ? 'YES' : 'NO — CHECK') . "\n";
}
echo "js safety net present: " . (strpos($b, 'af-review-overlap-fix-js') !== false ? 'YES' : 'NO') . "\n";
echo "=== DONE ===\n";
