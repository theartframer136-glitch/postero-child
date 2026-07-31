<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Switch watermarked previews ON, now that the gate is category-based
 * (digital-download products only) instead of is_downloadable() (everything).
 * Reports exactly which products the watermark now covers.
 * Run: wp eval-file tools/enable-watermarks.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
if (!function_exists('af_wm_applies')) { echo "Watermark gate not loaded — skipped.\n"; return; }

update_option('af_wm_enabled', 'yes');
echo "af_wm_enabled: yes\n";

$ids = get_posts(array('post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids'));
$in = 0;
foreach ($ids as $id) {
    $p = wc_get_product($id);
    if ($p && af_wm_applies($p)) $in++;
}
printf("products whose galleries now watermark: %d of %d\n", $in, count($ids));
echo "(physical canvas products stay clean — the gate is the digital-download categories)\n";
echo "=== DONE ===\n";
