<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/** Read-only. The text of the 7 Horses Vastu post (#35024), so category copy can agree with it, and the blog posts' slugs. */
if (!defined('ABSPATH')) exit;
$p = get_post(35024);
echo get_permalink($p) . "\n" . wordwrap(preg_replace('~\s+~', ' ', html_entity_decode(wp_strip_all_tags(str_replace(array('</p>', '</h2>', '</h3>', '</li>'), "\n", $p->post_content)), ENT_QUOTES, 'UTF-8')), 160) . "\n\n";
foreach (get_posts(array('post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 40)) as $q) echo "  /{$q->post_name}/  {$q->post_title}\n";
