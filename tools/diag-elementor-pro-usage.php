<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. What the site uses from Elementor Pro (3 Oct). The installed
 * Elementor Pro 3.28.0 writes its own licence ("activated", "valid, expires
 * 01.01.2030": elementor-pro.php lines 17-18), so Elementor's server refuses
 * it updates; the owner: "keep everything free". Doing without Elementor Pro
 * means replacing what the live site uses from it, so this lists:
 *   - every Elementor Pro widget used in a published page, post, product or
 *     template, how many times and where
 *   - the Pro theme-builder templates (header, footer, single, archive,
 *     product, popup, loop item) and whether each has display conditions
 *   - Pro-only settings on elements: custom CSS, motion effects, sticky,
 *     display conditions, custom attributes
 *
 * Run: wp eval-file tools/diag-elementor-pro-usage.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
global $wpdb;
if (!class_exists('\Elementor\Plugin')) { echo "Elementor is not loaded\n"; return; }

// Which widget types Elementor Pro registers: their classes live in ElementorPro\.
$pro = array();
foreach (\Elementor\Plugin::$instance->widgets_manager->get_widget_types() as $name => $w) {
    if (strpos(get_class($w), 'ElementorPro\\') === 0) $pro[$name] = $w->get_title();
}
echo 'Elementor Pro registers ' . count($pro) . " widget types\n";

$docs = $wpdb->get_results("SELECT p.ID, p.post_type, p.post_title, p.post_status, m.meta_value AS data
    FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_elementor_data'
    WHERE p.post_status IN ('publish','private') AND p.post_type NOT IN ('revision')");
echo 'documents built with Elementor: ' . count($docs) . "\n";

$use = array(); $where = array();
$settings = array('custom CSS' => 0, 'motion effects' => 0, 'sticky' => 0, 'display conditions' => 0, 'custom attributes' => 0);
$settingsWhere = array();
$walk = function ($els, $doc) use (&$walk, $pro, &$use, &$where, &$settings, &$settingsWhere) {
    foreach ((array) $els as $el) {
        if (!is_array($el)) continue;
        $s = isset($el['settings']) && is_array($el['settings']) ? $el['settings'] : array();
        if (isset($el['widgetType']) && isset($pro[$el['widgetType']])) {
            $t = $el['widgetType'];
            $use[$t] = (isset($use[$t]) ? $use[$t] : 0) + 1;
            $where[$t][$doc] = true;
        }
        $flags = array(
            'custom CSS' => !empty($s['custom_css']),
            'motion effects' => (bool) array_filter(array_keys($s), function ($k) use ($s) { return strpos($k, 'motion_fx_motion_fx_') !== false && $s[$k] === 'yes'; }),
            'sticky' => !empty($s['sticky']) || !empty($s['_sticky']),
            'display conditions' => !empty($s['e_display_conditions']),
            'custom attributes' => !empty($s['_attributes']) || !empty($s['custom_attributes']),
        );
        foreach ($flags as $k => $on) if ($on) { $settings[$k]++; $settingsWhere[$k][$doc] = true; }
        if (!empty($el['elements'])) $walk($el['elements'], $doc);
    }
};
foreach ($docs as $d) {
    $data = json_decode($d->data, true);
    if (!is_array($data)) continue;
    $walk($data, '#' . $d->ID . ' ' . $d->post_type . ' "' . mb_substr($d->post_title, 0, 40) . '"' . ($d->post_status !== 'publish' ? ' (' . $d->post_status . ')' : ''));
}

echo "\n=== Elementor Pro widgets in use ===\n";
if (!$use) echo "  none\n";
arsort($use);
foreach ($use as $t => $n) {
    $docsFor = array_keys($where[$t]);
    echo '  ' . str_pad($t, 28) . ' ' . str_pad($n . 'x', 5) . ' in ' . count($docsFor) . ' document(s) (' . $pro[$t] . ")\n";
    foreach (array_slice($docsFor, 0, 6) as $doc) echo "      $doc\n";
    if (count($docsFor) > 6) echo '      ... and ' . (count($docsFor) - 6) . " more\n";
}

echo "\n=== Elementor Pro theme-builder templates ===\n";
$tpls = $wpdb->get_results("SELECT p.ID, p.post_title, p.post_status, t.meta_value AS type, c.meta_value AS cond
    FROM {$wpdb->posts} p
    JOIN {$wpdb->postmeta} t ON t.post_id = p.ID AND t.meta_key = '_elementor_template_type'
    LEFT JOIN {$wpdb->postmeta} c ON c.post_id = p.ID AND c.meta_key = '_elementor_conditions'
    WHERE p.post_type = 'elementor_library' AND p.post_status = 'publish'
    ORDER BY t.meta_value, p.ID");
$themeTypes = array('header', 'footer', 'single', 'single-post', 'single-page', 'archive', 'search-results', 'error-404', 'product', 'product-archive', 'popup', 'loop-item');
$n = 0;
foreach ($tpls as $t) {
    if (!in_array($t->type, $themeTypes, true)) continue;
    $n++;
    $cond = maybe_unserialize($t->cond);
    echo '  #' . $t->ID . ' ' . str_pad($t->type, 16) . ' "' . mb_substr($t->post_title, 0, 40) . '"  ' . ($cond ? 'shown on: ' . implode(', ', (array) $cond) : 'no display conditions (not shown by itself)') . "\n";
}
if (!$n) echo "  none\n";

echo "\n=== Pro-only settings on elements ===\n";
foreach ($settings as $k => $c) {
    echo '  ' . str_pad($k, 20) . ' ' . $c . ' element(s)' . ($c ? ' in ' . count($settingsWhere[$k]) . ' document(s)' : '') . "\n";
    if ($c) foreach (array_slice(array_keys($settingsWhere[$k]), 0, 4) as $doc) echo "      $doc\n";
}

$kit = (int) get_option('elementor_active_kit');
$kitCss = $kit ? get_post_meta($kit, '_elementor_page_settings', true) : array();
echo "\nsite-wide custom CSS in the Elementor kit (a Pro feature): " . (is_array($kitCss) && !empty($kitCss['custom_css']) ? strlen($kitCss['custom_css']) . ' characters' : 'none') . "\n";
echo "done\n";
