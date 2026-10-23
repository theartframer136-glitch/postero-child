<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Create the USA-market blog articles as DRAFTS (run on deploy via wp eval-file).
 *
 * Search Console, Jul–Oct 2026: the site earns almost no deity or Vastu
 * searches, and two blog posts bring most of its impressions. The articles
 * in tools/seo-posts/ are written for the searches the Indian community in
 * the USA actually makes ("7 horses painting vastu direction", "pooja room
 * wall ideas", "diwali gift ideas", ...) and each links to the category
 * that sells the piece.
 *
 * DRAFTS, NEVER PUBLISHED HERE. The owner reads each one in the editor and
 * presses Publish. A post is created once, by slug; a later run never
 * overwrites a post that exists, so edits made in the editor are safe.
 * Rank Math title, description and focus keyword are set on creation.
 *
 * The category "Vastu & Decor Guides" is created if missing.
 *
 * Idempotent: a second run reports 0 created.
 */
if (!defined('ABSPATH')) exit;

$log  = function ($m) { echo "[posts] $m\n"; };
$dir  = __DIR__ . '/seo-posts';
$spec = json_decode((string) @file_get_contents($dir . '/posts.json'), true);
if (!is_array($spec) || empty($spec['posts'])) { $log('posts.json missing or empty — nothing to do'); return; }

// The owner's rule: nothing on the site may say free shipping.
// Owner's rule (2026-10-23): no text on the site may contain the word "free" at all,
// not just "free shipping".
$says_free = function ($s) { return (bool) preg_match('/\bfree\b/i', (string) $s); };

// Category.
$cat_id = 0;
if (!empty($spec['category']['slug'])) {
    $c = $spec['category'];
    $term = get_term_by('slug', $c['slug'], 'category');
    if ($term && !is_wp_error($term)) {
        $cat_id = (int) $term->term_id;
    } else {
        $made = wp_insert_term($c['name'], 'category', array('slug' => $c['slug'], 'description' => isset($c['description']) ? $c['description'] : ''));
        if (!is_wp_error($made)) { $cat_id = (int) $made['term_id']; $log("category created: {$c['name']}"); }
        else $log('category not created: ' . $made->get_error_message());
    }
}

$author = 0;
foreach (get_users(array('role__in' => array('administrator', 'editor'), 'number' => 1, 'orderby' => 'ID')) as $u) $author = (int) $u->ID;

$n_created = $n_exists = $n_skipped = 0;
foreach ($spec['posts'] as $p) {
    $slug = sanitize_title($p['slug']);
    if (get_page_by_path($slug, OBJECT, 'post')) { $n_exists++; continue; }

    $file = $dir . '/' . basename($p['file']);
    $html = (string) @file_get_contents($file);
    if (trim($html) === '') { $log("SKIP $slug: body file missing"); $n_skipped++; continue; }
    if ($says_free($html) || $says_free($p['title']) || $says_free($p['rm_description']) || $says_free($p['excerpt'])) {
        $log("SKIP $slug: contains the word 'free', which the owner's rule forbids"); $n_skipped++; continue;
    }

    $id = wp_insert_post(array(
        'post_type'     => 'post',
        'post_status'   => 'draft',
        'post_title'    => $p['title'],
        'post_name'     => $slug,
        'post_content'  => $html,
        'post_excerpt'  => isset($p['excerpt']) ? $p['excerpt'] : '',
        'post_author'   => $author ?: 1,
        'post_category' => $cat_id ? array($cat_id) : array(),
        'comment_status'=> 'closed',
    ), true);
    if (is_wp_error($id)) { $log("FAILED $slug: " . $id->get_error_message()); $n_skipped++; continue; }

    update_post_meta($id, 'rank_math_title', $p['rm_title']);
    update_post_meta($id, 'rank_math_description', $p['rm_description']);
    update_post_meta($id, 'rank_math_focus_keyword', $p['focus_keyword']);
    update_post_meta($id, '_af_seo_post', '2026-10');
    $n_created++;
    $log("draft created #$id /$slug/");
}
$log("created: $n_created | already present: $n_exists | skipped: $n_skipped");
$log('drafts wait in Posts → Drafts for the owner to review and publish');
