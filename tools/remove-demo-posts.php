<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Trash the theme's 2023 demo blog posts.
 *
 * Twelve posts dated 15 Jun 2023 came with the Postero demo ("Interview With
 * Designer Donald Allen", "All-new Product Lines Coming Spring 2023", ...),
 * all 314 words of the same filler, in the demo categories Fitness,
 * Streetwear, Lifestyle and Fashion Tips. They were still live on 16 Oct 2026
 * and show up in Google and on the blog page beside the real articles.
 *
 * They go to the trash, not away: WordPress keeps them 30 days and the owner
 * can restore any from Posts → Trash. A post is only trashed when its ID,
 * its 2023 date and its title all match the list below, so nothing the owner
 * wrote can be caught. The four demo categories are deleted only once they
 * hold no posts at all.
 *
 * Idempotent: a second run reports 0 trashed.
 */
if (!defined('ABSPATH')) exit;
$log = function ($m) { echo "[demo-posts] $m\n"; };

$demo = array(
    26 => 'The Art of Collaboration',
    28 => 'Artistic Expressions',
    30 => 'Breaking Boundaries',
    32 => 'The Rise of Digital Art',
    34 => 'Branding Through The Eyes of an Expert',
    36 => 'Interview With Designer Donald Allen',
    38 => 'All-new Product Lines Coming Spring 2023',
    40 => 'The Lense Behind All Of Our Little Lives',
    42 => 'Design posters: Direct from the artist to you',
    44 => 'Posters for the living room',
    46 => 'Conceptual Art Movement',
    48 => 'Guide: Inspiration for your personal picture wall',
);
$n = 0;
foreach ($demo as $id => $title_start) {
    $p = get_post($id);
    if (!$p || $p->post_type !== 'post' || $p->post_status !== 'publish') continue;
    if (substr($p->post_date, 0, 4) !== '2023' || stripos($p->post_title, $title_start) !== 0) { $log("left #$id: not the demo post expected"); continue; }
    if (wp_trash_post($id)) { $n++; $log("trashed #$id " . $p->post_title); }
}
foreach (array('fitness', 'streetwear', 'lifestyle', 'fashion-tips') as $slug) {
    $t = get_term_by('slug', $slug, 'category');
    if (!$t || is_wp_error($t)) continue;
    wp_update_term_count_now(array($t->term_id), 'category');
    $t = get_term($t->term_id, 'category');
    if ($t && !is_wp_error($t) && (int) $t->count === 0 && (int) get_option('default_category') !== (int) $t->term_id) {
        wp_delete_term($t->term_id, 'category');
        $log("deleted empty demo category: {$t->name}");
    }
}
if ($n) { do_action('litespeed_purge_all'); if (class_exists('RankMath\\Sitemap\\Cache')) { try { \RankMath\Sitemap\Cache::invalidate_storage(); } catch (\Throwable $e) {} } }
$log("trashed: $n");
