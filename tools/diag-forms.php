<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. Every form a visitor who is not logged in can submit, before
 * Cloudflare Turnstile is added to the ones bots abuse: the contact form, the
 * newsletter forms, blog comments and product reviews. Reports:
 *  - where the contact form and the newsletter form are printed (which pages);
 *  - whether comments and reviews are open to guests, and how many posts /
 *    products accept them;
 *  - how many comments, reviews, contact messages and newsletter sign-ups the
 *    site received in the last 30 days, and how many of them are pending or
 *    spam (the size of the bot problem);
 *  - the hooks the comment form and the review form offer for the box.
 *
 * Run: wp eval-file tools/diag-forms.php --allow-root
 */
if (!defined('ABSPATH')) exit(1);
global $wpdb;

echo "=== comments and reviews\n";
foreach (array('default_comment_status', 'comment_registration', 'comment_moderation', 'comment_previously_approved', 'require_name_email', 'show_avatars', 'close_comments_for_old_posts', 'close_comments_days_old', 'woocommerce_enable_reviews', 'woocommerce_review_rating_verification_required', 'woocommerce_review_rating_verification_label', 'woocommerce_enable_review_rating', 'akismet_strictness') as $o) {
    echo "  $o = " . json_encode(get_option($o)) . "\n";
}
echo '  posts with comments open: ' . (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='post' AND post_status='publish' AND comment_status='open'") . ' of ' . (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='post' AND post_status='publish'") . "\n";
echo '  products with reviews open: ' . (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish' AND comment_status='open'") . ' of ' . (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish'") . "\n";
echo '  pages with comments open: ' . (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='page' AND post_status='publish' AND comment_status='open'") . "\n";
$since = gmdate('Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS);
$rows = $wpdb->get_results($wpdb->prepare("SELECT comment_type, comment_approved, COUNT(*) n FROM {$wpdb->comments} WHERE comment_date_gmt >= %s GROUP BY comment_type, comment_approved ORDER BY n DESC", $since));
echo "  comments in the last 30 days (type / approved / count):\n";
foreach ($rows as $r) echo '    ' . ($r->comment_type ?: 'comment') . ' / ' . $r->comment_approved . ' / ' . $r->n . "\n";
if (!$rows) echo "    none\n";
echo '  comments ever: ' . (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments}") . ' | spam: ' . (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved='spam'") . ' | pending: ' . (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved='0'") . "\n";
$last = $wpdb->get_results("SELECT comment_type, comment_approved, comment_author, comment_author_email, comment_date_gmt, LEFT(comment_content, 70) c, user_id FROM {$wpdb->comments} ORDER BY comment_ID DESC LIMIT 8");
echo "  last 8 comments (type / approved / author / email domain / when / user_id / text):\n";
foreach ($last as $r) echo '    ' . ($r->comment_type ?: 'comment') . ' / ' . $r->comment_approved . ' / ' . $r->comment_author . ' / ' . substr(strrchr((string) $r->comment_author_email, '@'), 1) . ' / ' . $r->comment_date_gmt . ' / ' . $r->user_id . ' / ' . str_replace("\n", ' ', $r->c) . "\n";

echo "\n=== contact messages and newsletter sign-ups\n";
foreach (array('af_contact_messages', 'af_newsletter', 'af_nl_subscribers') as $t) {
    $tbl = $wpdb->prefix . $t;
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tbl)) !== $tbl) { echo "  $tbl: no such table\n"; continue; }
    $cols = $wpdb->get_col("SHOW COLUMNS FROM {$tbl}", 0);
    $datecol = in_array('created_at', $cols, true) ? 'created_at' : (in_array('created', $cols, true) ? 'created' : null);
    echo "  $tbl: " . (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tbl}") . ' rows';
    if ($datecol) echo ', last 30 days: ' . (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tbl} WHERE {$datecol} >= %s", $since)) . ', last 7 days: ' . (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tbl} WHERE {$datecol} >= %s", gmdate('Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS)));
    echo "\n";
    if ($t === 'af_contact_messages' && $datecol) {
        $r = $wpdb->get_results("SELECT {$datecol} d, name, SUBSTRING_INDEX(email, '@', -1) dom, subject, LEFT(message, 60) m FROM {$tbl} ORDER BY id DESC LIMIT 6");
        foreach ($r as $x) echo '    ' . $x->d . ' | ' . $x->name . ' | ' . $x->dom . ' | ' . $x->subject . ' | ' . str_replace("\n", ' ', $x->m) . "\n";
    }
}
foreach ($wpdb->get_col("SHOW TABLES LIKE '{$wpdb->prefix}af_%'") as $t) echo "  theme table: $t\n";
$nl = get_option('af_newsletter_list');
if (is_array($nl)) echo '  af_newsletter_list option: ' . count($nl) . " entries\n";

echo "\n=== where the forms are printed\n";
$ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_status='publish' AND post_type IN ('page','post') AND (post_content LIKE '%af_contact_form%' OR post_content LIKE '%wpcf7%' OR post_content LIKE '%contact-form-7%' OR post_content LIKE '%mc4wp%' OR post_content LIKE '%af_newsletter%')");
foreach ($ids as $id) echo '  #' . $id . ' ' . get_post_type($id) . ' ' . get_permalink($id) . ' uses: ' . implode(',', array_filter(array('af_contact_form', 'wpcf7', 'contact-form-7', 'mc4wp', 'af_newsletter'), function ($s) use ($id) { return strpos(get_post_field('post_content', $id), $s) !== false; })) . "\n";
$el = $wpdb->get_col("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_elementor_data' AND (meta_value LIKE '%af_contact_form%' OR meta_value LIKE '%wpcf7%' OR meta_value LIKE '%\"form\"%' OR meta_value LIKE '%eael-contact-form%' OR meta_value LIKE '%mc4wp%')");
foreach ($el as $id) {
    $d = (string) get_post_meta($id, '_elementor_data', true);
    $types = array();
    if (preg_match_all('/"widgetType":"([a-z0-9_-]*(form|contact|mailchimp|subscribe|newsletter)[a-z0-9_-]*)"/', $d, $m)) $types = array_unique($m[1]);
    if (strpos($d, 'af_contact_form') !== false) $types[] = 'shortcode af_contact_form';
    if (strpos($d, 'wpcf7') !== false) $types[] = 'shortcode wpcf7';
    if ($types) echo '  #' . $id . ' ' . get_post_type($id) . '/' . get_post_status($id) . ' ' . get_permalink($id) . ' elementor: ' . implode(', ', $types) . "\n";
}
foreach (array('contact', 'contact-us', 'newsletter', 'blog') as $slug) { $p = get_page_by_path($slug); echo "  /$slug/: " . ($p ? '#' . $p->ID . ' ' . $p->post_status : 'none') . "\n"; }
echo '  shortcode mc4wp_form exists: ' . (shortcode_exists('mc4wp_form') ? 'yes' : 'no') . ' | af_contact_form: ' . (shortcode_exists('af_contact_form') ? 'yes' : 'no') . "\n";

echo "\n=== hooks the comment and review forms offer (callbacks already there)\n";
global $wp_filter;
foreach (array('comment_form_after_fields', 'comment_form_logged_in_after', 'comment_form_submit_field', 'comment_form_defaults', 'woocommerce_product_review_comment_form_args', 'preprocess_comment', 'pre_comment_approved', 'comment_post', 'woocommerce_review_order_before_submit') as $h) {
    $list = array();
    if (!empty($wp_filter[$h])) foreach ($wp_filter[$h]->callbacks as $p => $cbs) foreach ($cbs as $cb) {
        $f = $cb['function'];
        $list[] = "@$p " . (is_string($f) ? $f : (is_array($f) ? (is_object($f[0]) ? get_class($f[0]) : $f[0]) . '::' . $f[1] : 'closure'));
    }
    echo "  $h: " . ($list ? implode(' | ', $list) : 'none') . "\n";
}
echo '  comments template in use: ' . str_replace(array(get_stylesheet_directory(), get_template_directory()), array('child', 'parent'), (string) locate_template(array('comments.php'))) . "\n";
echo '  woocommerce single-product/review.php override: ' . (file_exists(get_stylesheet_directory() . '/woocommerce/single-product-reviews.php') ? 'child' : (file_exists(get_template_directory() . '/woocommerce/single-product-reviews.php') ? 'parent' : 'none (WooCommerce default)')) . "\n";
echo "\n=== the forms a visitor actually gets (home page and a blog post, as served)\n";
$post = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_type='post' AND post_status='publish' AND comment_status='open' ORDER BY post_date DESC LIMIT 1");
$prod = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish' AND comment_status='open' ORDER BY ID DESC LIMIT 1");
foreach (array('home' => home_url('/'), 'post' => $post ? get_permalink($post) : '', 'product' => $prod ? get_permalink($prod) : '') as $label => $url) {
    if (!$url) continue;
    $r = wp_remote_get($url, array('timeout' => 25, 'headers' => array('User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36', 'Accept' => 'text/html')));
    if (is_wp_error($r)) { echo "  $label: " . $r->get_error_message() . "\n"; continue; }
    $b = (string) wp_remote_retrieve_body($r);
    echo "  $label $url HTTP " . wp_remote_retrieve_response_code($r) . ' cache=' . wp_remote_retrieve_header($r, 'x-litespeed-cache') . "\n";
    if (preg_match_all('/<form\b[^>]*>/i', $b, $m)) foreach ($m[0] as $f) echo '    ' . substr(preg_replace('/\s+/', ' ', $f), 0, 200) . "\n";
    foreach (array('af-f-news', 'af_nl_email', 'mc4wp', 'newsletter', 'Subscribe', 'commentform', 'comment_form', 'af-ts', 'tab-reviews', 'reviews_tab', 'woocommerce-Reviews', 'comment-respond', 'respond') as $needle) {
        $n = substr_count($b, $needle); if ($n) echo "    '$needle' x$n\n";
    }
    if (preg_match('/<div id="respond".{0,300}/s', $b, $mm)) echo '    respond: ' . substr(preg_replace('/\s+/', ' ', $mm[0]), 0, 300) . "\n";
    if (preg_match('/.{0,400}id="commentform".{0,200}/s', $b, $mm)) echo '    around commentform: ' . substr(preg_replace('/\s+/', ' ', $mm[0]), 0, 600) . "\n";
}
echo "\n=== the parent theme's comment and review templates (how the form is wrapped)\n";
foreach (array('/comments.php', '/woocommerce/single-product-reviews.php', '/woocommerce/single-product/tabs/tabs.php', '/footer.php') as $f) {
    $path = get_template_directory() . $f;
    if (!file_exists($path)) { echo "  parent$f: none\n"; continue; }
    $src = file($path);
    echo "  parent$f (" . count($src) . " lines):\n";
    foreach ($src as $i => $line) {
        if (preg_match('/comment_form|comments_open|respond|display:\s*none|collapse|toggle|tab|get_sidebar|footer|newsletter|af_nl|do_action|get_template_part/i', $line)) echo '    ' . ($i + 1) . ': ' . trim(substr($line, 0, 150)) . "\n";
    }
}
echo "=== END\n";
