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
echo "style block present: " . ($style !== false ? "YES @{$style}" : 'NO') . "\n";
// Inspect the block's own content (whitespace/minifier-insensitive)
$block = $style !== false ? substr($b, $style, 4000) : '';
$has_sel  = strpos($block, 'elementor-element-2596335') !== false;
$has_zero = (bool) preg_match('/--margin-bottom:\s*0px\s*!important/', $block);
echo "override rule in block: " . ($has_sel ? 'YES' : 'NO') . "\n";
echo "--margin-bottom zero in block: " . ($has_zero ? 'YES' : 'NO') . "\n";
// The -141px must still exist in Elementor's CSS (we override, not edit it)
echo "elementor -141px still present: " . (preg_match('/--margin-bottom:\s*-141px/', $b) ? 'YES (overridden by our rule)' : 'no longer in page') . "\n";
echo "js safety net present: " . (strpos($b, 'af-review-overlap-fix-js') !== false ? 'YES' : 'NO') . "\n";
echo "=== DONE ===\n";
