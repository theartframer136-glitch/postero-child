<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. What has to be true before Customer Reviews for WooCommerce is
 * switched off and its theme port (inc/ports/customer-reviews.php) takes over:
 *  - no WooCommerce email text carries a [cusrev_…] shortcode (it would print raw);
 *  - no LiteSpeed rule names the plugin's asset paths (they move to the theme);
 *  - no content carries a cusrev block or shortcode other than [cusrev_reviews];
 *  - which ivole_* review meta and cr_qna comments exist (the port shows them);
 *  - no reminder events are scheduled (the port does not send reminders).
 *
 * Run: wp eval-file tools/diag-cusrev-port.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
global $wpdb;

echo "=== plugin\n";
echo '  active=' . (class_exists('Ivole', false) ? 'yes' : 'no') . ' port=' . (function_exists('af_cusrev_init') ? 'loaded' : 'dormant') . "\n";

echo "\n=== WooCommerce email settings mentioning cusrev / cr_ shortcodes\n";
$n = 0;
foreach ($wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'woocommerce\\_%\\_settings' AND (option_value LIKE '%[cusrev%' OR option_value LIKE '%[cr\\_%' OR option_value LIKE '%cusrev_review_button%')") as $o) {
    echo "  $o\n"; $n++;
}
echo "  ($n found)\n";

echo "\n=== LiteSpeed options naming the plugin\n";
$n = 0;
foreach ($wpdb->get_results("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'litespeed%' AND option_value LIKE '%customer-reviews%'") as $r) {
    $v = maybe_unserialize($r->option_value);
    $s = is_scalar($v) ? (string) $v : json_encode($v);
    preg_match_all('/[^\s"\',]*customer-reviews[^\s"\',]*/', $s, $m);
    echo "  {$r->option_name}: " . implode(' ', array_slice(array_unique($m[0]), 0, 8)) . "\n"; $n++;
}
echo "  ($n found)\n";

echo "\n=== content with cusrev blocks or shortcodes\n";
foreach (array('%<!-- wp:cusrev/%', '%[cusrev%', '%[cr\\_%', '%ivole%') as $like) {
    $ids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_status IN ('publish','private','future','draft') AND post_type NOT IN ('revision') AND (post_content LIKE %s OR post_excerpt LIKE %s) LIMIT 30", $like, $like));
    echo "  $like posts: " . ($ids ? implode(',', $ids) : 'none') . "\n";
    $ids = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_data' AND meta_value LIKE %s LIMIT 30", $like));
    echo "  $like elementor: " . ($ids ? implode(',', $ids) : 'none') . "\n";
}
$w = $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'widget\\_%' AND (option_value LIKE '%cusrev%' OR option_value LIKE '%[cr\\_%')");
echo '  widgets: ' . ($w ? implode(',', $w) : 'none') . "\n";

echo "\n=== review meta the port reads\n";
foreach ($wpdb->get_results("SELECT meta_key, COUNT(*) AS n FROM {$wpdb->commentmeta} WHERE meta_key LIKE 'ivole%' OR meta_key LIKE 'cr\\_%' OR meta_key LIKE '\\_cr\\_%' GROUP BY meta_key ORDER BY meta_key") as $r) echo "  {$r->meta_key}: {$r->n}\n";
echo '  cr_qna comments: ' . $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_type = 'cr_qna'") . "\n";
echo '  cr_tag terms: ' . $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = 'cr_tag'") . "\n";

echo "\n=== scheduled events (ivole / cr_)\n";
$n = 0;
foreach ((array) _get_cron_array() as $ts => $hooks) foreach ((array) $hooks as $hook => $ev) if (preg_match('/^(ivole|cr_)/', $hook)) { echo '  ' . gmdate('Y-m-d H:i', $ts) . " $hook x" . count($ev) . "\n"; $n++; }
if (function_exists('as_get_scheduled_actions')) {
    foreach (array('ivole_send_reminder', 'cr_send_reminder') as $h) {
        $c = count(as_get_scheduled_actions(array('hook' => $h, 'status' => 'pending', 'per_page' => 50), 'ids'));
        if ($c) { echo "  action-scheduler $h pending: $c\n"; $n++; }
    }
}
echo "  ($n found)\n";

echo "\n=== options that change the port's output\n";
foreach (array('ivole_enable', 'ivole_disable_lightbox', 'ivole_customer_consent', 'ivole_customer_consent_text', 'ivole_verified_owner', 'ivole_ajax_reviews', 'ivole_questions_answers', 'ivole_attach_image', 'ivole_reviews_histogram', 'ivole_reviews_voting') as $o) {
    $v = get_option($o, null);
    echo "  $o = " . ($v === null ? '(absent)' : json_encode($v, JSON_UNESCAPED_SLASHES)) . "\n";
}
echo "\n=== END\n";
