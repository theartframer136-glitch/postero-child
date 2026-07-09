<?php
/**
 * Restore the ORIGINAL theme (Elementor) About page design from a revision,
 * undoing the earlier custom-content replacement. Reports what it finds so we
 * know whether recovery succeeded.
 * Run: wp eval-file tools/restore-about.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$about = get_page_by_path('about');
if (!$about) { $about = get_page_by_path('about-us'); }
if (!$about) { echo "About page not found\n"; exit; }
$pid = $about->ID;

echo "=== RESTORE ABOUT PAGE (#{$pid}) ===\n";

// Look through revisions (newest first) for saved Elementor data
$revs = wp_get_post_revisions($pid, array('numberposts'=>200, 'orderby'=>'date', 'order'=>'DESC'));
echo "Revisions found: " . count($revs) . "\n";

$restored = false;
foreach ($revs as $rev) {
    $raw = get_metadata('post', $rev->ID, '_elementor_data', true);
    if (!empty($raw) && $raw !== '[]' && $raw !== 'null') {
        // Restore Elementor design + content from this revision
        update_metadata('post', $pid, '_elementor_data', wp_slash($raw));
        $rpc = get_metadata('post', $rev->ID, '_elementor_page_settings', true);
        if (!empty($rpc)) update_metadata('post', $pid, '_elementor_page_settings', wp_slash(maybe_serialize($rpc)));
        // also restore the revision's post_content (Elementor mirrors rendered HTML there)
        wp_update_post(array('ID'=>$pid, 'post_content'=>get_post_field('post_content', $rev->ID)));

        update_post_meta($pid, '_elementor_edit_mode', 'builder');
        update_post_meta($pid, '_elementor_template_type', 'wp-page');
        if (defined('ELEMENTOR_VERSION')) update_post_meta($pid, '_elementor_version', ELEMENTOR_VERSION);
        delete_post_meta($pid, '_elementor_css'); // force CSS regen

        $restored = true;
        echo "Restored Elementor design from revision #{$rev->ID} (" . get_post_time('Y-m-d H:i', false, $rev->ID) . ")\n";
        break;
    }
}

// Remove our custom-content marker so nothing re-applies it
delete_post_meta($pid, '_taf_about_v');

// Regenerate Elementor CSS / clear cache
if ($restored && class_exists('\\Elementor\\Plugin')) {
    try { \Elementor\Plugin::$instance->files_manager->clear_cache(); } catch (\Throwable $e) {}
}
clean_post_cache($pid);

if ($restored) {
    echo "RESULT: Original theme About page restored. (Edit text in Elementor to match your site.)\n";
} else {
    echo "RESULT: No Elementor revision with data found — original design could not be auto-recovered.\n";
    echo "        The page currently has our custom content; we can rebuild the design instead.\n";
}
echo "=== DONE ===\n";
