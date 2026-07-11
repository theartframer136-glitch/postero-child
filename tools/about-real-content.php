<?php
/**
 * Make the (restored) theme Elementor About page show REAL Art Framer content
 * instead of the demo "Postero / Copenhagen" placeholder text — WITHOUT
 * changing the theme's layout or design. It only rewrites the text inside
 * existing heading / text-editor widgets in _elementor_data.
 *
 * Idempotent via a version marker. Re-running is safe.
 * Run: wp eval-file tools/about-real-content.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$VERSION = '1';

$about = get_page_by_path('about');
if (!$about) $about = get_page_by_path('about-us');
if (!$about) { echo "About page not found\n"; exit; }
$pid = $about->ID;

$raw = get_post_meta($pid, '_elementor_data', true);
if (empty($raw) || $raw === '[]') {
    echo "No Elementor data on About page — nothing to rewrite (design not present).\n";
    exit;
}

if (get_post_meta($pid, '_taf_about_real_v', true) === $VERSION) {
    echo "About real-content already at v{$VERSION} (skip)\n";
    exit;
}

$data = json_decode($raw, true);
if (!is_array($data)) { echo "Could not decode _elementor_data\n"; exit; }

// Brand / location replacements applied to any text value.
$SUBS = array(
    'Postero Copenhagen' => 'The Art Framer',
    'Postero'            => 'The Art Framer',
    'Copenhagen, Denmark'=> 'Delaware, USA',
    'Copenhagen'         => 'Delaware, USA',
    'Denmark'            => 'USA',
);

// Real paragraphs used to replace lorem/demo body copy (rotated in order).
$PARAS = array(
    'The Art Framer crafts premium canvas wall art, framed prints, digital downloads and custom décor — spiritual, cultural and modern pieces made with archival, fade-resistant inks and ready to inspire your space.',
    'Born from a love of art and craftsmanship, we believe beautiful, meaningful art should be accessible to everyone. From devotional Radha Krishna and Seven Horses canvases to modern abstracts and personalised prints, every piece is produced with premium materials and a gallery-grade finish.',
    'Operating from Delaware, USA, we serve customers nationwide with free shipping, secure checkout and dedicated support by phone, email and WhatsApp — turning everyday walls into spaces that inspire.',
);
$pi = 0;

function af_is_demo($text) {
    $t = strtolower(wp_strip_all_tags($text));
    return (strpos($t, 'lorem ipsum') !== false || strpos($t, 'dolor sit amet') !== false);
}

$stats = array('brand'=>0, 'para'=>0);

function af_walk(&$node, $SUBS, $PARAS, &$pi, &$stats) {
    if (!is_array($node)) return;
    if (isset($node['settings']) && is_array($node['settings'])) {
        foreach (array('title','editor','description_text','text') as $key) {
            if (!isset($node['settings'][$key]) || !is_string($node['settings'][$key])) continue;
            $val = $node['settings'][$key];
            if (af_is_demo($val)) {
                // Replace whole demo paragraph/heading with real copy.
                $node['settings'][$key] = ($key === 'title')
                    ? 'About The Art Framer'
                    : '<p>' . $PARAS[$pi % count($PARAS)] . '</p>';
                if ($key !== 'title') $pi++;
                $stats['para']++;
            } else {
                $new = strtr($val, $SUBS);
                if ($new !== $val) { $node['settings'][$key] = $new; $stats['brand']++; }
            }
        }
    }
    if (isset($node['elements']) && is_array($node['elements'])) {
        foreach ($node['elements'] as &$child) { af_walk($child, $SUBS, $PARAS, $pi, $stats); }
        unset($child);
    }
}

foreach ($data as &$section) { af_walk($section, $SUBS, $PARAS, $pi, $stats); }
unset($section);

$new_raw = wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
update_post_meta($pid, '_elementor_data', wp_slash($new_raw));
delete_post_meta($pid, '_elementor_css'); // force CSS regen
if (class_exists('\\Elementor\\Plugin')) {
    try { \Elementor\Plugin::$instance->files_manager->clear_cache(); } catch (\Throwable $e) {}
}
update_post_meta($pid, '_taf_about_real_v', $VERSION);
clean_post_cache($pid);

echo "About page (#{$pid}) real-content applied. Brand replacements: {$stats['brand']}, demo paragraphs replaced: {$stats['para']}.\n";
echo "=== DONE ===\n";
