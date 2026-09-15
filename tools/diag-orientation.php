<?php
/**
 * Does the orientation filter return the right pieces?
 *
 * The owner filtered to "square" and got canvases described as 3x4 Feet,
 * which are portrait by any reading. Orientation here is derived from the
 * FEATURED IMAGE's pixel dimensions (inc/orientation-filter.php), and on this
 * shop the featured image is usually a scene mockup — a frame on a wall, a
 * print on a table — whose own proportions say nothing about the artwork's.
 *
 * This asks the database three questions:
 *   1. how many products carry each stored orientation
 *   2. for a sample, what the stored value is, what the image measured, and
 *      what the title and size attribute actually say
 *   3. how often those two disagree
 *
 * Read-only: it counts and prints. Nothing is written.
 */
if (!defined('ABSPATH')) exit;

echo "=== ORIENTATION: what is stored, and is it right? ===\n\n";

global $wpdb;
$counts = $wpdb->get_results(
    "SELECT meta_value AS o, COUNT(*) AS n
       FROM {$wpdb->postmeta} pm
       JOIN {$wpdb->posts} p ON p.ID = pm.post_id
      WHERE pm.meta_key = '_af_orientation'
        AND p.post_type = 'product' AND p.post_status = 'publish'
      GROUP BY meta_value ORDER BY n DESC");
echo "stored on published products:\n";
$total = 0;
foreach ($counts as $c) { echo sprintf("  %-10s %d\n", $c->o, $c->n); $total += (int) $c->n; }

$all = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->posts}
      WHERE post_type='product' AND post_status='publish'");
echo "  (none)     " . ($all - $total) . "\n";
echo "  total published products: $all\n\n";

/** The shape the artwork itself claims, read from its words. */
function af_diag_shape_from_words($title, $size) {
    $hay = strtolower($title . ' ' . $size);
    // "3x4 Feet", "36 x 60 Inches", "5×3 Feet" — first pair of numbers wins.
    if (preg_match('/(\d+(?:\.\d+)?)\s*(?:x|×)\s*(\d+(?:\.\d+)?)/i', $hay, $m)) {
        $a = (float) $m[1]; $b = (float) $m[2];
        if ($a && $b) {
            if ($b > $a * 1.08) return 'portrait';
            if ($a > $b * 1.08) return 'landscape';
            return 'square';
        }
    }
    return '';
}

$ids = $wpdb->get_col(
    "SELECT ID FROM {$wpdb->posts}
      WHERE post_type='product' AND post_status='publish'
      ORDER BY ID DESC LIMIT 400");

$agree = $disagree = $nowords = 0;
$examples = array();
foreach ($ids as $id) {
    $stored = get_post_meta($id, '_af_orientation', true);
    if (!$stored) continue;
    $p = wc_get_product($id);
    if (!$p) continue;
    $size  = '';
    foreach (array('pa_size', 'size', 'pa_dimensions') as $a) {
        $v = $p->get_attribute($a);
        if ($v) { $size = $v; break; }
    }
    $words = af_diag_shape_from_words($p->get_name(), $size);
    if (!$words) { $nowords++; continue; }
    if ($words === $stored) { $agree++; continue; }
    $disagree++;
    if (count($examples) < 12) {
        $thumb = get_post_thumbnail_id($id);
        $m = $thumb ? wp_get_attachment_metadata($thumb) : array();
        $examples[] = sprintf(
            "  #%d  stored:%-9s words:%-9s  image %sx%s\n     %s\n     size attr: %s",
            $id, $stored, $words,
            isset($m['width']) ? $m['width'] : '?',
            isset($m['height']) ? $m['height'] : '?',
            mb_substr($p->get_name(), 0, 76),
            $size !== '' ? mb_substr($size, 0, 54) : '(none)');
    }
}

echo "of the 400 newest published products that carry an orientation:\n";
echo "  the image and the artwork's own words AGREE:    $agree\n";
echo "  they DISAGREE:                                  $disagree\n";
echo "  the words give no size to compare:              $nowords\n\n";

if ($examples) {
    echo "where they disagree — stored from the image, words from the piece:\n";
    echo implode("\n", $examples) . "\n";
}
