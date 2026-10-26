<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * The owner's rule: no text on the site contains the word "free". Five
 * product descriptions still did (found 26 Oct 2026 while checking the Google
 * Merchant Center feed, tools/diag-merchant-details.php). None is an offer —
 * "glare-free", "tool-free" — so each is reworded to say the same thing.
 *
 * Only these exact phrases, only in products' description and short
 * description. Idempotent: a product without them is not touched.
 * Run: wp eval-file tools/fix-free-wording.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }

$phrases = array(
    'glare-free viewing'             => 'non-glare viewing',
    'wrinkle-free canvas stretching' => 'smooth, tight canvas stretching',
    'easy tool-free assembly'        => 'easy assembly without tools',
    'Curl-free 440gsm banner media'  => 'Flat-lying 440gsm banner media',
    'splash free from the bubbles'   => 'splash out from the bubbles',
);

global $wpdb;
$where = array();
foreach (array_keys($phrases) as $p) {
    $like = '%' . $wpdb->esc_like($p) . '%';
    $where[] = $wpdb->prepare('post_content LIKE %s OR post_excerpt LIKE %s', $like, $like);
}
$ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND (" . implode(' OR ', $where) . ')');

echo "=== Reword 'free' in product descriptions ===\n";
foreach ($ids as $id) {
    $post = get_post((int) $id);
    $content = strtr($post->post_content, $phrases);
    $excerpt = strtr($post->post_excerpt, $phrases);
    if ($content === $post->post_content && $excerpt === $post->post_excerpt) continue;
    wp_update_post(wp_slash(array('ID' => $post->ID, 'post_content' => $content, 'post_excerpt' => $excerpt)));
    echo "  #{$post->ID} {$post->post_title}: reworded\n";
}
echo '  ' . count($ids) . " product(s) checked\n";
