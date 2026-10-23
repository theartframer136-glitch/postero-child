<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Publish the eight USA-market articles that tools/create-seo-posts.php
 * created as drafts on 15 Oct, each with a featured image.
 *
 * The owner asked on 16 Oct for every open audit item to be completed, the
 * unpublished drafts among them. Each article is published once: the meta
 * _af_seo_published records it, so an article the owner later unpublishes or
 * trashes stays that way on the next deploy. The featured image is the
 * thumbnail of the product category the article sells for (falling back to
 * the first product in it), so the blog cards and the article's social
 * preview show the artwork.
 *
 * wp_publish_post() writes the status straight to the table, so the article
 * body is not run through kses as a WP-CLI wp_update_post() would.
 *
 * Idempotent: a second run reports 0 published.
 */
if (!defined('ABSPATH')) exit;
$log = function ($m) { echo "[publish] $m\n"; };

// article slug → the product category whose thumbnail illustrates it
$pic = array(
    '7-horses-painting-vastu-direction'                => 'seven-horses',
    'pooja-room-wall-ideas-american-apartment'         => 'hindu-deities',
    'which-hindu-deity-painting-for-which-room'        => 'hindu-deities',
    'ganesha-painting-entrance-vastu-placement'        => 'lakshmi-ganesha',
    'diwali-gift-guide-deity-vastu-canvas-art'         => 'lakshmi-ganesha',
    'radha-krishna-painting-bedroom-meaning-placement' => 'radha-krishna',
    'canvas-size-guide-us-living-room-wall'            => 'living-room',
    'griha-pravesh-gift-ideas-indian-family-usa'       => 'vaastu-art',
);

$thumb_for = function ($cat_slug) {
    $t = get_term_by('slug', $cat_slug, 'product_cat');
    if (!$t || is_wp_error($t)) return 0;
    $id = (int) get_term_meta($t->term_id, 'thumbnail_id', true);
    if ($id && wp_attachment_is_image($id)) return $id;
    $prods = get_posts(array('post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => 5, 'fields' => 'ids', 'orderby' => 'date', 'order' => 'DESC',
        'tax_query' => array(array('taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => $cat_slug))));
    foreach ($prods as $pid) { $id = (int) get_post_thumbnail_id($pid); if ($id && wp_attachment_is_image($id)) return $id; }
    return 0;
};

$drafts = get_posts(array('post_type' => 'post', 'post_status' => 'draft', 'posts_per_page' => 20, 'fields' => 'ids',
    'meta_query' => array(array('key' => '_af_seo_post', 'compare' => 'EXISTS'))));
$n = 0; $skipped = 0;
foreach ($drafts as $pid) {
    if (get_post_meta($pid, '_af_seo_published', true)) { $skipped++; continue; }   // unpublished again by hand: leave it
    $slug = get_post_field('post_name', $pid);
    $body = (string) get_post_field('post_content', $pid);
    if (preg_match('/\bfree\b/i', $body)) { $log("SKIP $slug: contains the word 'free' (owner's rule: nothing is free)"); $skipped++; continue; }
    if (!has_post_thumbnail($pid)) {
        $thumb = isset($pic[$slug]) ? $thumb_for($pic[$slug]) : 0;
        if (!$thumb) $thumb = $thumb_for('hindu-deities');
        if ($thumb) set_post_thumbnail($pid, $thumb);
    }
    wp_publish_post($pid);
    update_post_meta($pid, '_af_seo_published', gmdate('c'));
    $n++;
    $log(sprintf('published #%d /%s/ (image #%d)', $pid, $slug, (int) get_post_thumbnail_id($pid)));
}
if ($n && class_exists('RankMath\\Sitemap\\Cache')) { try { \RankMath\Sitemap\Cache::invalidate_storage(); } catch (\Throwable $e) {} }
if ($n) do_action('litespeed_purge_all');
$log("published: $n, left alone: $skipped");
