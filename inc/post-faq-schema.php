<?php
/**
 * FAQPage structured data for blog posts that end with a
 * "Frequently asked questions" section.
 *
 * The USA-market articles (tools/seo-posts/) each close with
 *
 *     <h2>Frequently asked questions</h2>
 *     <h3>Question?</h3><p>Answer.</p>  ×3
 *
 * Google shows FAQ rich results for pages that carry FAQPage JSON-LD, and
 * Rank Math only emits it from its own FAQ block, which these articles do
 * not use. This reads the section out of the rendered content and adds a
 * FAQPage node to Rank Math's graph, or prints its own script when Rank
 * Math is not printing one. Nothing is added when the section is absent or
 * holds fewer than two pairs.
 *
 * Single posts only, main query only. The product-page FAQ has its own
 * schema ([af_faqs] in functions.php) and is not touched.
 */
if (!defined('ABSPATH')) exit;

/** [[question, answer], ...] from a post's content, or []. */
function af_post_faq_pairs($content) {
    try {
        $content = (string) $content;
        if (!preg_match('/<h2[^>]*>\s*Frequently asked questions\s*<\/h2>(.*)$/is', $content, $m)) return array();
        $section = $m[1];
        // Stop at the next h2, if any.
        $section = preg_split('/<h2[^>]*>/i', $section, 2)[0];
        if (!preg_match_all('/<h3[^>]*>(.*?)<\/h3>\s*((?:<p[^>]*>.*?<\/p>\s*)+)/is', $section, $mm, PREG_SET_ORDER)) return array();
        $pairs = array();
        foreach ($mm as $pair) {
            $q = trim(wp_strip_all_tags($pair[1]));
            $a = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags($pair[2])));
            if ($q === '' || $a === '') continue;
            $pairs[] = array(html_entity_decode($q, ENT_QUOTES, 'UTF-8'), html_entity_decode($a, ENT_QUOTES, 'UTF-8'));
        }
        return count($pairs) >= 2 ? $pairs : array();
    } catch (\Throwable $e) {
        return array();
    }
}

/** The FAQPage node for the post being viewed, or null. */
function af_post_faq_node() {
    static $node = false;
    if ($node !== false) return $node;
    $node = null;
    try {
        if (!is_singular('post')) return null;
        $post = get_queried_object();
        if (!$post || empty($post->post_content)) return null;
        $pairs = af_post_faq_pairs($post->post_content);
        if (!$pairs) return null;
        $entities = array();
        foreach ($pairs as $p) {
            $entities[] = array(
                '@type'          => 'Question',
                'name'           => $p[0],
                'acceptedAnswer' => array('@type' => 'Answer', 'text' => $p[1]),
            );
        }
        $node = array(
            '@type'      => 'FAQPage',
            '@id'        => get_permalink($post) . '#faq',
            'mainEntity' => $entities,
        );
    } catch (\Throwable $e) {
        $node = null;
    }
    return $node;
}

// Rank Math prints the graph on this site: add the node to it.
add_filter('rank_math/json_ld', function ($data, $jsonld) {
    try {
        $node = af_post_faq_node();
        if ($node && is_array($data) && !isset($data['af_faqpage'])) $data['af_faqpage'] = $node;
    } catch (\Throwable $e) {
    }
    return $data;
}, 25, 2);

// Without Rank Math, print the node on its own.
add_action('wp_head', function () {
    try {
        if (class_exists('RankMath')) return;
        $node = af_post_faq_node();
        if (!$node) return;
        $node = array('@context' => 'https://schema.org') + $node;
        echo '<script type="application/ld+json">' . wp_json_encode($node, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
    } catch (\Throwable $e) {
    }
}, 6);
