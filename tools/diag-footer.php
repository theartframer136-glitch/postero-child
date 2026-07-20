<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Diagnostic: does the child theme's footer/header markup actually render?
 * Simulates the wp_footer + wp_body_open hooks server-side and reports
 * which of our markers are present. Independent of page/browser caching.
 * Run: wp eval-file tools/diag-footer.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

echo "=== FOOTER / HEADER RENDER DIAGNOSTIC ===\n\n";

// Which theme is actually active?
$theme = wp_get_theme();
echo "Active theme : " . $theme->get('Name') . " (" . get_stylesheet() . ")\n";
echo "Template dir : " . get_stylesheet_directory() . "\n";
echo "functions.php: " . ( file_exists(get_stylesheet_directory().'/functions.php') ? 'present' : 'MISSING' ) . "\n";

// Confirm our block is in the deployed functions.php
$fn = @file_get_contents( get_stylesheet_directory().'/functions.php' );
echo "\n-- source markers in deployed functions.php --\n";
foreach ( array('af-footer','af-utilitybar','af-quickpanel','af-trustbar','PHASE 4') as $needle ) {
    echo "  " . str_pad($needle,16) . ( ($fn && strpos($fn,$needle)!==false) ? "FOUND" : "absent" ) . "\n";
}

// Simulate the footer output (sitewide blocks only; is_front_page/is_product
// are false in CLI, so page-specific sections won't render here — that's fine)
echo "\n-- simulated do_action('wp_footer') output --\n";
ob_start();
do_action('wp_footer');
$out = ob_get_clean();
echo "  output length : " . strlen($out) . " bytes\n";
foreach ( array('af-footer','af-utilitybar','af-quickpanel','af-f-logo','Customer Service') as $needle ) {
    echo "  " . str_pad($needle,16) . ( strpos($out,$needle)!==false ? "RENDERS" : "not rendered" ) . "\n";
}

// Does the parent/child theme even call wp_body_open?
echo "\n-- theme wp_body_open support --\n";
ob_start();
do_action('wp_body_open');
$bo = ob_get_clean();
echo "  wp_body_open output: " . strlen($bo) . " bytes\n";

echo "\n=== DIAGNOSTIC COMPLETE ===\n";
