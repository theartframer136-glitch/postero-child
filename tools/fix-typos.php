<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Fix copy typos in page content / Elementor data.
 * Currently: the homepage section heading reads "Products In Motation"
 * while its own description says "Product in Motion" — correct the heading.
 * Very targeted string replacements only, and idempotent (re-running does
 * nothing once the text is already correct).
 * Run: wp eval-file tools/fix-typos.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$fixes = array(
    'In Motation' => 'In Motion',
    'in Motation' => 'in Motion',
    'Motation'    => 'Motion',
);

$ids = get_posts(array(
    'post_type'      => array('page','post','product'),
    'post_status'    => array('publish','draft','private'),
    'posts_per_page' => -1,
    'fields'         => 'ids',
));

$changed = 0;
foreach ($ids as $pid) {
    $did = false;

    // 1) post_content
    $c = get_post_field('post_content', $pid);
    if (is_string($c) && $c !== '') {
        $n = $c;
        foreach ($fixes as $from => $to) { $n = str_replace($from, $to, $n); }
        if ($n !== $c) {
            wp_update_post(array('ID'=>$pid, 'post_content'=>$n));
            echo "  content  #{$pid} " . get_the_title($pid) . "\n";
            $did = true;
        }
    }

    // 2) Elementor data (JSON string in post meta)
    $e = get_post_meta($pid, '_elementor_data', true);
    if (is_string($e) && $e !== '') {
        $n = $e;
        foreach ($fixes as $from => $to) {
            // Elementor stores JSON — replace both raw and escaped forms
            $n = str_replace($from, $to, $n);
        }
        if ($n !== $e) {
            update_post_meta($pid, '_elementor_data', wp_slash($n));
            echo "  elementor #{$pid} " . get_the_title($pid) . "\n";
            $did = true;
        }
    }

    if ($did) $changed++;
}

echo "\n=== FIX TYPOS ===\nposts updated: {$changed}\n";

// Elementor caches rendered CSS/HTML — clear it so the fix shows immediately.
if (class_exists('\Elementor\Plugin')) {
    try { \Elementor\Plugin::$instance->files_manager->clear_cache(); echo "Elementor cache cleared.\n"; }
    catch (\Throwable $t) { echo "Elementor cache clear skipped: ".$t->getMessage()."\n"; }
}
if (function_exists('do_action')) do_action('litespeed_purge_all');
echo "=== DONE ===\n";
