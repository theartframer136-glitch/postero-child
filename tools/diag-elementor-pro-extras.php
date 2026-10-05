<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. The parts of Elementor Pro a page can lean on without placing a
 * Pro widget, which would quietly stop working when Elementor Pro is switched
 * off (owner, 5 Oct: drop Elementor Pro, keep everything free):
 *   - dynamic tags (a setting filled from the post, the product, the site),
 *     by tag name, and in which documents
 *   - links that open a popup or another Pro action (#elementor-action)
 *   - element and page custom CSS (a Pro control), printed in full so it can
 *     move into the theme
 *   - Pro's custom fonts, custom icons and Custom Code snippets
 *
 * Run: wp eval-file tools/diag-elementor-pro-extras.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
global $wpdb;

// Dynamic tags Elementor Pro registers (the free plugin registers a handful of its own).
$proTags = array();
if (class_exists('\Elementor\Plugin') && isset(\Elementor\Plugin::$instance->dynamic_tags)) {
    foreach ((array) \Elementor\Plugin::$instance->dynamic_tags->get_tags() as $name => $info) {
        $cls = is_array($info) && isset($info['class']) ? $info['class'] : '';
        $proTags[$name] = strpos(ltrim($cls, '\\'), 'ElementorPro\\') === 0 ? 'pro' : 'free';
    }
}
echo 'dynamic tags registered: ' . count($proTags) . ' (' . count(array_filter($proTags, function ($k) { return $k === 'pro'; })) . " from Elementor Pro)\n";

$docs = $wpdb->get_results("SELECT p.ID, p.post_type, p.post_title, m.meta_value AS data FROM {$wpdb->posts} p
    JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_elementor_data'
    WHERE p.post_status IN ('publish','private')");
$tags = array(); $actions = array(); $css = array();
$walk = function ($els, $doc) use (&$walk, &$tags, &$actions, &$css) {
    foreach ((array) $els as $el) {
        if (!is_array($el)) continue;
        $s = isset($el['settings']) && is_array($el['settings']) ? $el['settings'] : array();
        if (!empty($s['__dynamic__']) && is_array($s['__dynamic__'])) {
            foreach ($s['__dynamic__'] as $ctl => $val) {
                if (preg_match_all('/name="([^"]+)"/', (string) $val, $m)) foreach ($m[1] as $n) $tags[$n][$doc . ' #' . $el['id'] . ' (' . $ctl . ')'] = true;
            }
        }
        $flat = json_encode($s);
        if (strpos($flat, 'elementor-action') !== false) $actions[$doc . ' #' . $el['id']] = true;
        if (!empty($s['custom_css'])) $css[] = array($doc . ' #' . $el['id'] . ' ' . (isset($el['widgetType']) ? $el['widgetType'] : $el['elType']), (string) $s['custom_css']);
        if (!empty($el['elements'])) $walk($el['elements'], $doc);
    }
};
foreach ($docs as $d) {
    $data = json_decode($d->data, true);
    if (is_array($data)) $walk($data, '#' . $d->ID . ' ' . $d->post_type . ' "' . mb_substr($d->post_title, 0, 30) . '"');
}

echo "\n=== dynamic tags in use ===\n";
if (!$tags) echo "  none\n";
foreach ($tags as $n => $where) {
    echo '  ' . str_pad($n, 28) . ' ' . (isset($proTags[$n]) ? $proTags[$n] : 'not registered now') . ', ' . count($where) . " use(s)\n";
    foreach (array_slice(array_keys($where), 0, 5) as $w) echo "      $w\n";
}

echo "\n=== links to a Pro action (#elementor-action: popups etc.) ===\n";
echo $actions ? '  ' . implode("\n  ", array_keys($actions)) . "\n" : "  none\n";

echo "\n=== element custom CSS (Pro) ===\n";
if (!$css) echo "  none\n";
foreach ($css as $c) echo '  ' . $c[0] . ":\n" . preg_replace('/^/m', '      ', trim($c[1])) . "\n";

echo "\n=== page custom CSS (Pro), in document settings ===\n";
$n = 0;
foreach ($wpdb->get_results("SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_page_settings'") as $r) {
    $v = maybe_unserialize($r->meta_value);
    if (is_array($v) && !empty($v['custom_css'])) {
        $n++;
        $p = get_post($r->post_id);
        echo '  #' . $r->post_id . ' ' . ($p ? $p->post_type . ' "' . mb_substr($p->post_title, 0, 30) . '" ' . $p->post_status : '(gone)') . ":\n" . preg_replace('/^/m', '      ', trim($v['custom_css'])) . "\n";
    }
}
if (!$n) echo "  none\n";

echo "\n=== Pro custom fonts, custom icons, Custom Code ===\n";
foreach (array('elementor_font' => 'custom fonts', 'elementor_icons' => 'custom icon sets', 'elementor_snippet' => 'Custom Code snippets') as $pt => $label) {
    $rows = $wpdb->get_results($wpdb->prepare("SELECT ID, post_title, post_status FROM {$wpdb->posts} WHERE post_type = %s", $pt));
    echo '  ' . $label . ': ' . count($rows) . "\n";
    foreach ($rows as $r) echo '      #' . $r->ID . ' "' . $r->post_title . '" ' . $r->post_status . "\n";
}
echo "done\n";
