<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * "How to Maintain and Clean Canvas Wall Art" (post #8637) is the blog post
 * Google shows most, and in Nov 2026 it ranked top 10 for care questions the
 * article never answered ("can you freeze canvas" #5, "how to renew canvas"
 * #10, "used canvas care instructions" #8). Its 15-question FAQ also sat
 * under no "Frequently asked questions" heading, so inc/post-faq-schema.php
 * gave it no FAQPage data, and two answers were a lead-in line and a list.
 *
 * This adds the FAQ heading, makes questions 8 and 9 h3 like the others,
 * turns answers 9 and 13 into one paragraph each, appends questions 16-21
 * for those searches, rewords "acid-free paper" (the owner's no-"free"
 * rule), fixes the title's grammar and sets a search title and
 * description. The rest of the article is left as written.
 *
 * Idempotent: each change is made only when it is missing.
 * Run: wp eval-file tools/update-cleaning-post.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }

const AF_CLEAN_POST_SLUG  = 'how-to-maintain-and-clean-canvas-wall-art';
const AF_CLEAN_POST_TITLE = 'How to Maintain and Clean Canvas Wall Art Yourself';
const AF_CLEAN_SEO_TITLE  = 'How to Clean Canvas Wall Art Safely: Care Tips & FAQ | The Art Framer';
const AF_CLEAN_SEO_DESC   = 'Clean canvas wall art without damage: dusting, spot cleaning, framed prints, protecting and storing canvas, and answers on freezing, washing and renewing it.';

/** The article's HTML with the FAQ changes made; unchanged parts are returned as they were. */
function af_clean_post_html($html) {
    // The owner's rule: no text on the site says "free"; the storage tip did.
    $html = str_replace('acid-free paper', 'archival tissue paper', $html);

    // Questions 8 and 9 were h4; every other question is h3.
    $html = preg_replace('~<h4([^>]*)>(\s*[89]\.\s[^<]*\?)\s*</h4>~', '<h3$1>$2</h3>', $html);

    // Answers 9 and 13 were a lead-in line and a list; FAQ data reads paragraphs only.
    $answers = array(
        '9. How do I protect canvas wall art from dust?' =>
            'Dust it with a dry microfiber cloth or a soft brush every 2–4 weeks, keep windows closed in dusty weather, keep the room well ventilated, and consider a floating frame, which shields the canvas edges where dust collects.',
        '13. How do I prevent canvas artwork from fading?' =>
            'Hang it out of direct sunlight, keep the room at a steady temperature and humidity, and swap the position of pieces in bright rooms now and then so no one artwork takes all the light. UV-protective coatings slow fading further.',
    );
    foreach ($answers as $q => $a) {
        $html = preg_replace(
            '~(<h3[^>]*>\s*' . preg_quote($q, '~') . '\s*</h3>)\s*<p[^>]*>[^<]*:</p>\s*<ul[^>]*>.*?</ul>\s*(?:<p[^>]*>[^<]*</p>)?~s',
            '$1' . "\n<p>" . esc_html($a) . "</p>\n",
            $html, 1);
    }

    // The heading inc/post-faq-schema.php looks for, above question 1.
    if (!preg_match('~<h2[^>]*>\s*Frequently asked questions\s*</h2>~i', $html)) {
        $html = preg_replace('~(<h3[^>]*>\s*1\.\s+How do you clean canvas wall art safely\?)~',
            "<h2>Frequently asked questions</h2>\n$1", $html, 1);
    }

    // Questions 16-21: the care searches the article shows for but did not answer.
    if (strpos($html, '16. Can you freeze a canvas?') === false) {
        $more = array(
            '16. Can you freeze a canvas?' =>
                'No. Freezing temperatures make the canvas and its wooden stretcher bars shrink unevenly, which can crack the paint or ink layer, and moisture condenses on the surface as it warms up again. Keep canvas art and stored canvases at normal room temperature, out of unheated garages, sheds and attics.',
            '17. How do you renew or refresh an old canvas print?' =>
                'Dust it front and back with a dry microfiber cloth or soft brush, spot-clean marks with a cloth barely dampened with distilled water, and let it air dry. If the canvas sags and its stretcher bars have small wooden wedges (keys) in the corners, tap them in gently to tighten it. A new frame also freshens an old piece. Cleaning cannot bring back faded colors; a faded print is best replaced.',
            '18. How do you protect a canvas print?' =>
                'Hang it away from direct sun, heat vents, fireplaces and damp rooms such as bathrooms, dust it every few weeks, and hold it by the stretcher bars or frame rather than the canvas. To store it, wrap it in archival tissue paper and keep it upright in a dry room, never in a damp basement or a hot attic.',
            '19. Can a canvas painting be washed?' =>
                'No. Never wash, soak or rinse a canvas painting or print, or put it in a washing machine. Water can lift the paint or ink, loosen the canvas on its stretcher bars, warp the wood and lead to mold. Spot-clean small marks with a barely damp cloth instead.',
            '20. How do you clean a framed canvas or a framed art print?' =>
                'For a framed canvas with no glass, clean the canvas with a dry microfiber cloth and wipe the frame separately. For a print behind glass, spray a little glass cleaner onto the cloth, never onto the glass, so no liquid runs under the glass and onto the print.',
            '21. How should I care for a used or second-hand canvas?' =>
                'Check the front and back for mold spots, tears, flaking paint and loose staples. Brush the dust off both sides with a soft dry brush, then let the canvas air for a few days in a dry, well-ventilated room before you hang it. If you find mold or flaking paint, take it to a professional restorer rather than cleaning it yourself.',
        );
        $add = '';
        foreach ($more as $q => $a) $add .= "\n\n<hr />\n\n<h3>" . esc_html($q) . "</h3>\n<p>" . esc_html($a) . '</p>';
        $html = rtrim($html) . $add . "\n";
    }
    return $html;
}

$post = get_page_by_path(AF_CLEAN_POST_SLUG, OBJECT, 'post');
echo "=== Cleaning article FAQ ===\n";
if (!$post) { echo "  post not found, nothing done\n"; return; }

$html = af_clean_post_html($post->post_content);
$update = array('ID' => $post->ID);
if ($html !== $post->post_content) $update['post_content'] = $html;
if ($post->post_title !== AF_CLEAN_POST_TITLE) $update['post_title'] = AF_CLEAN_POST_TITLE;
if (count($update) > 1) {
    wp_update_post(wp_slash($update));
    echo '  #' . $post->ID . ' updated: ' . implode(', ', array_slice(array_keys($update), 1)) . "\n";
} else {
    echo "  #{$post->ID} already up to date\n";
}
foreach (array('rank_math_title' => AF_CLEAN_SEO_TITLE, 'rank_math_description' => AF_CLEAN_SEO_DESC) as $k => $v) {
    if (get_post_meta($post->ID, $k, true) !== $v) { update_post_meta($post->ID, $k, $v); echo "  $k set\n"; }
}
if (function_exists('af_post_faq_pairs')) echo '  FAQ questions read for FAQ data: ' . count(af_post_faq_pairs(get_post($post->ID)->post_content)) . "\n";
