<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * READ-ONLY. Names every published product, page or post whose content or
 * excerpt still says "free shipping" / "free delivery" (any capitalisation),
 * with the exact wording around each match, so tools/rewrite-free-shipping.php
 * can be checked against what is actually there. Also lists Elementor data
 * still carrying the phrase. Makes no changes, prints no secrets.
 *
 *   wp eval-file wp-content/themes/postero-child/tools/diag-free-shipping-list.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
global $wpdb;

$pat = '/free\s*(usa\s*)?(shipping|delivery)/i';
$ctx = function ($text) use ($pat) {
    $out = array();
    $text = wp_strip_all_tags((string) $text);
    if (preg_match_all($pat, $text, $m, PREG_OFFSET_CAPTURE)) {
        foreach ($m[0] as $hit) {
            $s = max(0, $hit[1] - 60);
            $out[] = '…' . trim(preg_replace('/\s+/u', ' ', mb_strcut($text, $s, 160))) . '…  [exact: "' . $hit[0] . '"]';
        }
    }
    return $out;
};

echo "=== CONTENT / EXCERPT (published product, page, post)\n";
$rows = $wpdb->get_results("SELECT ID, post_type, post_name, post_title, post_content, post_excerpt FROM {$wpdb->posts}
    WHERE post_type IN ('product','page','post') AND post_status='publish'
      AND (LOWER(post_content) REGEXP 'free[[:space:]]*(usa[[:space:]]*)?(shipping|delivery)'
        OR LOWER(post_excerpt) REGEXP 'free[[:space:]]*(usa[[:space:]]*)?(shipping|delivery)')");
foreach ($rows as $r) {
    echo sprintf("#%d %s /%s/  \"%s\"\n", $r->ID, $r->post_type, $r->post_name, $r->post_title);
    foreach (array_merge($ctx($r->post_content), $ctx($r->post_excerpt)) as $c) echo "    $c\n";
}
echo "  total: " . count($rows) . "\n";

echo "\n=== ELEMENTOR DATA (any status)\n";
$el = $wpdb->get_results("SELECT pm.post_id, p.post_type, p.post_status, p.post_name FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id
    WHERE pm.meta_key='_elementor_data' AND LOWER(pm.meta_value) REGEXP 'free[[:space:]]*(usa[[:space:]]*)?(shipping|delivery)' LIMIT 50");
foreach ($el as $r) echo sprintf("#%d %s %s /%s/\n", $r->post_id, $r->post_type, $r->post_status, $r->post_name);
echo "  total: " . count($el) . "\n=== DONE\n";
