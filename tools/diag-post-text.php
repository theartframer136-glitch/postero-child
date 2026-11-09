<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. The stored HTML of the blog posts named below, so new copy can
 * agree with them and edits can match their markup, and the blog posts' slugs.
 */
if (!defined('ABSPATH')) exit;
foreach (array('how-to-maintain-and-clean-canvas-wall-art') as $slug) {
    $p = get_page_by_path($slug, OBJECT, 'post');
    if (!$p) { echo "$slug: not found\n\n"; continue; }
    echo "=== #{$p->ID} " . get_permalink($p) . " ({$p->post_status}, modified {$p->post_modified})\n";
    echo "title: {$p->post_title}\n";
    echo "seo title: " . get_post_meta($p->ID, 'rank_math_title', true) . "\n";
    echo "seo description: " . get_post_meta($p->ID, 'rank_math_description', true) . "\n";
    echo "--- content (" . strlen($p->post_content) . " bytes)\n" . $p->post_content . "\n--- end\n\n";
}
foreach (get_posts(array('post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 40)) as $q) echo "  /{$q->post_name}/  {$q->post_title}\n";
