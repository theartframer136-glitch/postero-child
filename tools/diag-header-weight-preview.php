<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only preview of the new inc/header-weight.php on live data: which
 * header/footer widgets it finds hidden at every width, and what the cart
 * page becomes with them taken out. Carries a copy of the new functions.
 */
if (!defined('ABSPATH')) exit;
if (!function_exists('af_hw_hidden_widget_ids')) { function af_hw_hidden_widget_ids(array $elements, array $devices, array $safe, array $inherited = array()) {
    $out = array();
    foreach ($elements as $el) {
        if (!is_array($el)) continue;
        $s = isset($el['settings']) && is_array($el['settings']) ? $el['settings'] : array();
        $h = $inherited;
        foreach ($devices as $d) if (!empty($s['hide_' . $d])) $h[$d] = true;
        if (isset($el['elType']) && $el['elType'] === 'widget') {
            $type = isset($el['widgetType']) ? (string) $el['widgetType'] : '';
            if (isset($el['id']) && in_array($type, $safe, true) && af_hw_hidden_everywhere($h, $devices)) $out[] = (string) $el['id'];
        }
        if (!empty($el['elements']) && is_array($el['elements'])) {
            $out = array_merge($out, af_hw_hidden_widget_ids($el['elements'], $devices, $safe, $h));
        }
    }
    return $out;
} }
if (!function_exists('af_hw_strip_widget')) { function af_hw_strip_widget($html, $id) {
    if (!preg_match('~<div\b[^>]*\bdata-id="' . preg_quote($id, '~') . '"[^>]*\bdata-element_type="widget"[^>]*>~', $html, $m, PREG_OFFSET_CAPTURE)) return $html;
    $start = $m[0][1];
    $pos = $start + strlen($m[0][0]);
    $depth = 1;
    if (!preg_match_all('~<(/?)div\b[^>]*>~i', $html, $tags, PREG_OFFSET_CAPTURE | PREG_SET_ORDER, $pos)) return $html;
    $end = -1;
    foreach ($tags as $t) {
        $depth += $t[1][0] === '/' ? -1 : 1;
        if ($depth === 0) { $end = $t[0][1] + strlen($t[0][0]); break; }
    }
    if ($end < 0) return $html;
    $block = substr($html, $start, $end - $start);
    if (preg_match('~<(script|style|template|textarea)\b|<!--~i', $block)) return $html;
    return substr($html, 0, $start) . substr($html, $end);
} }
if (!function_exists('af_hw_filter_page')) { function af_hw_filter_page($html, array $ids) {
    if (!$ids || !is_string($html) || $html === '') return $html;
    $done = array();
    foreach (array('~<header id="masthead"[^>]*>.*?</header>~s', '~<footer\b[^>]*id="colophon"[^>]*>.*?</footer>~s') as $re) {
        $html = preg_replace_callback($re, function ($m) use ($ids, &$done) {
            $part = $m[0];
            foreach ($ids as $id) {
                $before = strlen($part);
                $part = af_hw_strip_widget($part, $id);
                if (strlen($part) < $before) $done[] = $id;
            }
            return $part;
        }, $html);
    }
    if ($done) $html = str_replace('</body>', "<!-- af-header-weight: not sent, hidden at every width: " . implode(' ', $done) . " -->\n</body>", $html);
    return $html;
} }
$devices = af_home_elementor_devices();
$ids = array();
foreach (af_hw_template_ids() as $t) {
    $raw = get_post_meta($t, '_elementor_data', true);
    $data = is_string($raw) ? json_decode($raw, true) : $raw;
    $found = is_array($data) ? af_hw_hidden_widget_ids($data, $devices, af_hw_safe_types()) : array();
    echo "template #$t: " . implode(' ', $found) . "\n";
    $ids = array_merge($ids, $found);
}
$r = wp_remote_get(home_url('/cart/?afhwp=' . mt_rand()), array('timeout' => 40, 'sslverify' => false, 'user-agent' => 'Mozilla/5.0 AF-hw-preview'));
$h = wp_remote_retrieve_body($r);
$t0 = microtime(true);
$o = af_hw_filter_page($h, $ids);
printf("cart: %d KB -> %d KB (%.1f ms to filter)\n", strlen($h) / 1024, strlen($o) / 1024, (microtime(true) - $t0) * 1000);
echo preg_match('~af-header-weight: not sent[^>]*~', $o, $m) ? $m[0] . "\n" : "no note\n";
// the page must still be whole: as many <div as </div in the header before and after differ by the same count
foreach (array($h, $o) as $i => $x) { preg_match('~<header id="masthead".*?</header>~s', $x, $hm); printf("  %s header: %d KB, <div %d, </div> %d\n", $i ? 'after ' : 'before', strlen($hm[0]) / 1024, preg_match_all('~<div\b~', $hm[0]), preg_match_all('~</div>~', $hm[0])); }
