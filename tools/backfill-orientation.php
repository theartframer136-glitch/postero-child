<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Compute _af_orientation for every product from its featured image.
 * Idempotent — skips products whose stored value is already correct.
 * Run: wp eval-file tools/backfill-orientation.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
if (!function_exists('af_orientation_from_image')) { echo "Orientation module not loaded — skipped.\n"; return; }

$ids = get_posts(array('post_type' => 'product', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids'));
$set = array('portrait' => 0, 'landscape' => 0, 'square' => 0);
$none = 0; $changed = 0;
foreach ($ids as $id) {
    $o = af_orientation_from_image($id);
    if (!$o) { $none++; delete_post_meta($id, '_af_orientation'); continue; }
    if (get_post_meta($id, '_af_orientation', true) !== $o) { update_post_meta($id, '_af_orientation', $o); $changed++; }
    $set[$o]++;
}
echo "=== ORIENTATION BACKFILL ===\n";
printf("products: %d | portrait %d | landscape %d | square %d | no image %d | updated %d\n",
    count($ids), $set['portrait'], $set['landscape'], $set['square'], $none, $changed);
echo "=== DONE ===\n";
