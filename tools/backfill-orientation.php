<?php
/**
 * Re-derive every product's orientation from the artwork rather than its photo.
 *
 * Measured before this ran: of 391 published products, 174 carried a stored
 * orientation that contradicted their own title — "3x4 Feet" pieces filed as
 * landscape because the scene photograph is wide. Anyone filtering the shop to
 * portrait was shown the wrong pieces and, worse, not shown the right ones.
 *
 * _af_orientation is a derived field: it holds nothing a human typed, and it is
 * recomputed on every save. Rewriting it restores agreement with the product;
 * it destroys no information, because the information was never here — it is in
 * the title and the size attribute, which are untouched.
 *
 * Pass DRY=1 in the environment to see the counts without writing anything.
 */
if (!defined('ABSPATH')) exit;

$dry = getenv('DRY') === '1';
echo $dry ? "=== DRY RUN: nothing will be written ===\n\n" : "=== Re-deriving orientation ===\n\n";

if (!function_exists('af_orientation_compute')) {
    echo "af_orientation_compute() is not loaded — the theme on this server is\n";
    echo "older than this script. Deploy first, then run it again.\n";
    return;
}

$ids = get_posts(array(
    'post_type'      => 'product',
    'post_status'    => array('publish', 'private', 'draft'),
    'posts_per_page' => -1,
    'fields'         => 'ids',
));

$changed = $same = $cleared = 0;
$moves = array();
foreach ($ids as $id) {
    $was = (string) get_post_meta($id, '_af_orientation', true);
    $now = (string) af_orientation_compute($id);
    if ($was === $now) { $same++; continue; }
    if ($now === '') {
        $cleared++;
        if (!$dry) delete_post_meta($id, '_af_orientation');
    } else {
        $changed++;
        $key = ($was === '' ? '(none)' : $was) . ' -> ' . $now;
        $moves[$key] = isset($moves[$key]) ? $moves[$key] + 1 : 1;
        if (!$dry) update_post_meta($id, '_af_orientation', $now);
    }
}

echo "products examined: " . count($ids) . "\n";
echo "  already correct: $same\n";
echo "  corrected:       $changed\n";
echo "  cleared:         $cleared\n\n";
if ($moves) {
    arsort($moves);
    echo "what moved where:\n";
    foreach ($moves as $k => $n) echo sprintf("  %-24s %d\n", $k, $n);
    echo "\n";
}

global $wpdb;
$after = $wpdb->get_results(
    "SELECT meta_value AS o, COUNT(*) AS n
       FROM {$wpdb->postmeta} pm
       JOIN {$wpdb->posts} p ON p.ID = pm.post_id
      WHERE pm.meta_key = '_af_orientation'
        AND p.post_type = 'product' AND p.post_status = 'publish'
      GROUP BY meta_value ORDER BY n DESC");
echo ($dry ? "unchanged (dry run) — published products by orientation:\n"
           : "now stored on published products:\n");
foreach ($after as $r) echo sprintf("  %-10s %d\n", $r->o, $r->n);
