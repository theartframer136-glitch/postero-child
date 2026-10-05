<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. The saved settings of every Elementor Pro widget the live site
 * uses (tools/diag-elementor-pro-usage.php, 5 Oct: slides x2, gallery x3,
 * nested-carousel x1 on the home page; woocommerce-breadcrumb x6 in the
 * theme's breadcrumb templates), so each can be rebuilt with free parts
 * before Elementor Pro is switched off (owner: "keep everything free",
 * "Drop Elementor Pro (free)").
 *
 * For each instance: the document, the top-level section it sits in, the
 * element id, and its settings with the defaults left out. Long text is
 * shortened; pictures show their attachment id and file name. For a nested
 * carousel, the elements inside each slide.
 *
 * Run: wp eval-file tools/diag-elementor-pro-widgets.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
global $wpdb;
$want = array('slides', 'gallery', 'nested-carousel', 'woocommerce-breadcrumb');

function af_epw_short($v) {
    if (is_array($v)) {
        if (isset($v['url']) && array_key_exists('id', $v) && count($v) <= 5) return '#' . $v['id'] . ' ' . basename((string) $v['url']);
        $o = array();
        foreach ($v as $k => $x) $o[$k] = af_epw_short($x);
        return $o;
    }
    if (is_string($v)) { $v = preg_replace('/\s+/', ' ', strip_tags($v)); return mb_strlen($v) > 90 ? mb_substr($v, 0, 90) . '…' : $v; }
    return $v;
}
function af_epw_tree($els, $depth = 0) {
    $out = '';
    foreach ((array) $els as $el) {
        $t = isset($el['widgetType']) ? $el['widgetType'] : (isset($el['elType']) ? $el['elType'] : '?');
        $s = isset($el['settings']) ? $el['settings'] : array();
        $hint = '';
        foreach (array('title', 'editor', 'text', 'image', 'link', 'shortcode', 'html') as $k) if (!empty($s[$k])) { $hint .= ' ' . $k . '=' . json_encode(af_epw_short($s[$k]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); }
        $out .= str_repeat('  ', $depth + 3) . $t . ' #' . (isset($el['id']) ? $el['id'] : '?') . mb_substr($hint, 0, 220) . "\n";
        if (!empty($el['elements'])) $out .= af_epw_tree($el['elements'], $depth + 1);
    }
    return $out;
}

$docs = $wpdb->get_results("SELECT p.ID, p.post_type, p.post_title, m.meta_value AS data FROM {$wpdb->posts} p
    JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_elementor_data'
    WHERE p.post_status IN ('publish','private')");
$walk = function ($els, $doc, $top) use (&$walk, $want) {
    foreach ((array) $els as $el) {
        if (!is_array($el)) continue;
        $t = $top === null ? (isset($el['id']) ? $el['id'] : '?') : $top;
        if (isset($el['widgetType']) && in_array($el['widgetType'], $want, true)) {
            echo "\n=== " . $el['widgetType'] . ' #' . $el['id'] . ' in ' . $doc . ', top-level section ' . $t . " ===\n";
            $s = isset($el['settings']) ? $el['settings'] : array();
            echo json_encode(af_epw_short($s), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
            if ($el['widgetType'] === 'nested-carousel' && !empty($el['elements'])) {
                echo "  slides (" . count($el['elements']) . "):\n" . af_epw_tree($el['elements']);
            }
        }
        if (!empty($el['elements']) && !(isset($el['widgetType']) && $el['widgetType'] === 'nested-carousel')) $walk($el['elements'], $doc, $t);
    }
};
foreach ($docs as $d) {
    $data = json_decode($d->data, true);
    if (is_array($data)) $walk($data, '#' . $d->ID . ' ' . $d->post_type . ' "' . mb_substr($d->post_title, 0, 40) . '"', null);
}

// Where the theme's breadcrumb templates are used.
echo "\n=== postero-breadcrumb templates ===\n";
foreach ($wpdb->get_results("SELECT ID, post_title, post_status FROM {$wpdb->posts} WHERE post_type = 'postero-breadcrumb' ORDER BY ID") as $p) {
    $meta = $wpdb->get_results($wpdb->prepare("SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key NOT LIKE '\\_elementor%%' AND meta_key NOT LIKE '\\_edit%%' AND meta_key NOT LIKE '\\_wp\\_%%'", $p->ID));
    $m = array();
    foreach ($meta as $r) $m[] = $r->meta_key . '=' . mb_substr((string) $r->meta_value, 0, 60);
    echo '  #' . $p->ID . ' "' . $p->post_title . '" ' . $p->post_status . ($m ? '  ' . implode('; ', $m) : '') . "\n";
}
echo "done\n";
