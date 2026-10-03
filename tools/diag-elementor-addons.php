<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. What the Elementor add-on plugins (Premium Addons, Essential
 * Addons, Header Footer Elementor / UAE) and Slider Revolution and Customer
 * Reviews actually do on this site: every element that uses one of their
 * widgets or extension settings in a published document, with the saved
 * values; their global module switches. Long opaque strings are masked.
 *
 * Run: wp eval-file tools/diag-elementor-addons.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
global $wpdb;

function af_ea_mask($v, $k = '') {
    if (is_array($v) || is_object($v)) { $o = array(); foreach ((array) $v as $kk => $vv) $o[$kk] = af_ea_mask($vv, (string) $kk); return $o; }
    if (!is_string($v)) return $v;
    if ($k !== '' && preg_match('/token|secret|passw|api_?key|bypass|auth|license|nonce|salt/i', $k)) return '[masked]';
    return preg_replace('/[A-Za-z0-9_\-]{48,}/', '[masked]', $v);
}
function af_ea_j($v) { return json_encode(af_ea_mask($v), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); }

$prefixes = array('premium_' => 'premium-addons', 'eael_' => 'essential-addons', 'hfe_' => 'hfe/uae', 'uae_' => 'hfe/uae');
$widgets  = '/^(eael-|premium-|hfe-|site-logo|navigation-menu|retina|copyright|page-title|site-title|site-tagline|cart|search-button|breadcrumbs-widget|scroll-to-top|slider_revolution|uael-)/';
$counts = array(); $ext = array();
$docs = $wpdb->get_results("SELECT p.ID, p.post_type, p.post_status, p.post_title, m.meta_value FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_elementor_data' WHERE p.post_status IN ('publish','private') AND p.post_type NOT IN ('revision') ORDER BY p.ID");
echo "=== published Elementor documents: " . count($docs) . "\n";
foreach ($docs as $d) {
    $data = json_decode($d->meta_value, true); if (!is_array($data)) continue;
    $stack = $data;
    while ($stack) {
        $el = array_shift($stack);
        if (!empty($el['elements'])) foreach ($el['elements'] as $c) $stack[] = $c;
        $s = isset($el['settings']) && is_array($el['settings']) ? $el['settings'] : array();
        $wt = $el['widgetType'] ?? '';
        $tag = "#{$d->ID} {$d->post_type} \"" . mb_substr($d->post_title, 0, 40) . "\" {$el['elType']}" . ($wt ? ":$wt" : '') . " id={$el['id']}";
        if ($wt && preg_match($widgets, $wt)) {
            $counts[$wt] = ($counts[$wt] ?? 0) + 1;
            echo "WIDGET $tag\n  " . af_ea_j($s) . "\n";
            continue;
        }
        $hit = array();
        foreach ($s as $k => $v) foreach ($prefixes as $pre => $owner) if (strpos($k, $pre) === 0) { $hit[$k] = $v; $ext[$owner][$k] = ($ext[$owner][$k] ?? 0) + 1; }
        if ($hit) echo "EXT $tag\n  " . af_ea_j($hit) . "\n";
    }
}
echo "\n=== widget counts\n"; ksort($counts); foreach ($counts as $k => $n) echo "  $k x$n\n";
echo "\n=== extension keys (how many elements carry each)\n"; foreach ($ext as $o => $ks) { ksort($ks); echo "  [$o] " . af_ea_j($ks) . "\n"; }

echo "\n=== module switches\n";
foreach (array('pa_save_settings', 'pa_pro_save_settings', 'eael_save_settings', 'eael_global_settings', 'hfe_settings', 'uae_widgets', '_hfe_db_version', 'uael_widgets', 'pa_maps_save_settings', 'premium_addons_version') as $o) {
    $v = get_option($o, null); if ($v === null) continue;
    echo "  $o = " . af_ea_j(maybe_unserialize($v)) . "\n";
}
echo "\n=== HFE templates (elementor-hf)\n";
foreach (get_posts(array('post_type' => 'elementor-hf', 'post_status' => 'any', 'numberposts' => -1)) as $p) {
    $m = array(); foreach (get_post_meta($p->ID) as $k => $v) if (strpos($k, 'ehf_') === 0) $m[$k] = maybe_unserialize($v[0]);
    echo "  #{$p->ID} {$p->post_status} \"{$p->post_title}\" " . af_ea_j($m) . "\n";
}
echo "\n=== Slider Revolution\n";
foreach (array('revslider_sliders', 'revslider7_sliders') as $t) {
    $tbl = $wpdb->prefix . $t;
    if ($wpdb->get_var("SHOW TABLES LIKE '$tbl'") !== $tbl) continue;
    foreach ($wpdb->get_results("SELECT id, title, alias, type FROM $tbl") as $r) echo "  $t #{$r->id} \"{$r->title}\" alias={$r->alias} type={$r->type}\n";
}
foreach (array('revslider-global-settings', 'revslider_update_version', 'revslider-update-check', 'revslider-library-check', 'revslider_table_version') as $o) { $v = get_option($o, null); if ($v !== null) echo "  $o = " . mb_substr(af_ea_j(maybe_unserialize($v)), 0, 600) . "\n"; }
echo "\n=== Customer Reviews (ivole_*) options with a value\n";
foreach ($wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'ivole\\_%' OR option_name LIKE 'cr\\_%' ORDER BY option_name LIMIT 400") as $n) {
    $v = maybe_unserialize(get_option($n)); if ($v === '' || $v === array() || $v === null) continue;
    echo "  $n = " . mb_substr(af_ea_j($v), 0, 300) . "\n";
}
echo "\n=== END\n";
