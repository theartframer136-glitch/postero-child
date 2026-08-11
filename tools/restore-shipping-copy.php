<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Put every page back exactly as it was before the free-shipping rewrite.
 *
 * Why this is needed: the rewrite saved content with wp_update_post(), and
 * WP-CLI runs with no logged-in user — so WordPress judged the request unable
 * to post unfiltered HTML and ran the content through wp_filter_post_kses,
 * which strips <style>, <script>, iframes and many builder attributes. The
 * wording changed and the page markup was quietly damaged with it.
 *
 * The restore writes with $wpdb->update directly, deliberately bypassing those
 * same filters, so what goes back is byte-for-byte what was saved. Backups are
 * kept, not deleted, so this can be run again safely.
 *
 * Run: wp eval-file tools/restore-shipping-copy.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
global $wpdb;

echo "=== RESTORE PAGES TO THEIR PRE-REWRITE STATE ===\n";

$restored = 0; $skipped = 0;

// ── post content and excerpt ──
$rows = $wpdb->get_results(
    "SELECT post_id, meta_value FROM {$wpdb->postmeta}
      WHERE meta_key = '_af_shipping_copy_backup'");
foreach ($rows as $row) {
    $data = json_decode($row->meta_value, true);
    if (!is_array($data) || !array_key_exists('content', $data)) { $skipped++; continue; }
    $ok = $wpdb->update(
        $wpdb->posts,
        array('post_content' => $data['content'], 'post_excerpt' => isset($data['excerpt']) ? $data['excerpt'] : ''),
        array('ID' => (int) $row->post_id)
    );
    if ($ok === false) { echo "  FAILED #{$row->post_id}\n"; continue; }
    clean_post_cache((int) $row->post_id);
    $restored++;
    printf("  content   #%-6d %s\n", $row->post_id,
           mb_strimwidth(get_the_title($row->post_id), 0, 52, '…'));
}

// ── Elementor designs ──
$er = $wpdb->get_results(
    "SELECT post_id, meta_value FROM {$wpdb->postmeta}
      WHERE meta_key = '_af_elementor_backup'");
$el = 0;
foreach ($er as $row) {
    if ($row->meta_value === '' || $row->meta_value === null) { $skipped++; continue; }
    // the backup was stored slashed for update_post_meta; unslash to get the
    // original bytes back before writing them straight into the row
    $original = wp_unslash($row->meta_value);
    $ok = $wpdb->update(
        $wpdb->postmeta,
        array('meta_value' => $original),
        array('post_id' => (int) $row->post_id, 'meta_key' => '_elementor_data')
    );
    if ($ok === false) { echo "  FAILED elementor #{$row->post_id}\n"; continue; }
    clean_post_cache((int) $row->post_id);
    $el++;
}
if ($el) printf("  elementor designs restored: %d\n", $el);

echo "\n  pages restored:   {$restored}\n";
echo "  designs restored: {$el}\n";
echo "  skipped:          {$skipped}\n";

if ($restored || $el) {
    if (class_exists('\\Elementor\\Plugin')) {
        try { \Elementor\Plugin::$instance->files_manager->clear_cache(); echo "  elementor cache cleared\n"; }
        catch (\Throwable $e) { echo "  (elementor cache clear skipped: " . $e->getMessage() . ")\n"; }
    }
    if (function_exists('wc_delete_product_transients')) wc_delete_product_transients();
    echo "  caches flushed\n";
}
// Record the outcome where it can always be read. The deploy log buries this
// step under a megabyte of image-rotation output, so "did the restore run?"
// was unanswerable from the log alone.
update_option('af_restore_summary', sprintf(
    'pages %d, designs %d, skipped %d, at %s UTC', $restored, $el, $skipped, gmdate('Y-m-d H:i')), false);

echo "=== DONE ===\n";
