<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. The home page's slideshow widgets as Elementor stores them: which
 * picture each slide uses, which screen sizes each widget is hidden on, and the
 * breakpoints. Used to preload the first banner (speed step 3).
 */
if (!defined('ABSPATH')) exit;
$front = (int) get_option('page_on_front');
echo "front page: {$front}\n";
$raw = get_post_meta($front, '_elementor_data', true);
$data = is_string($raw) ? json_decode($raw, true) : $raw;
if (!is_array($data)) { echo "no elementor data\n"; return; }
$walk = function ($els, $depth) use (&$walk) {
    foreach ($els as $el) {
        $t = isset($el['widgetType']) ? $el['widgetType'] : (isset($el['elType']) ? $el['elType'] : '?');
        $s = isset($el['settings']) ? $el['settings'] : array();
        if (in_array($t, array('slides', 'image', 'banner', 'image-carousel'), true) || $depth < 2) {
            $hide = array();
            foreach (array('hide_desktop', 'hide_tablet', 'hide_mobile') as $h) if (!empty($s[$h])) $hide[] = $h;
            echo str_repeat('  ', $depth) . "{$t} id={$el['id']} hide=" . implode(',', $hide) . "\n";
            if ($t === 'slides' && !empty($s['slides'])) {
                foreach ($s['slides'] as $i => $sl) {
                    $bg = isset($sl['background_image']) ? $sl['background_image'] : array();
                    echo str_repeat('  ', $depth + 1) . "slide {$i} _id=" . (isset($sl['_id']) ? $sl['_id'] : '') . ' img=' . (isset($bg['url']) ? $bg['url'] : '-') . ' id=' . (isset($bg['id']) ? $bg['id'] : '-') . ' size=' . (isset($sl['background_size']) ? $sl['background_size'] : '-') . "\n";
                    if (!empty($bg['id'])) {
                        $m = wp_get_attachment_metadata((int) $bg['id']);
                        if ($m) echo str_repeat('  ', $depth + 2) . "master {$m['width']}x{$m['height']} sizes: " . implode(' ', array_map(function ($k, $v) { return "{$k}={$v['width']}x{$v['height']}"; }, array_keys($m['sizes']), $m['sizes'])) . "\n";
                    }
                }
                foreach (array('slides_height', 'slides_height_tablet', 'slides_height_mobile') as $k) if (isset($s[$k])) echo str_repeat('  ', $depth + 1) . "{$k}=" . json_encode($s[$k]) . "\n";
            }
        }
        if (!empty($el['elements'])) $walk($el['elements'], $depth + 1);
    }
};
$walk($data, 0);
if (class_exists('\Elementor\Plugin') && isset(\Elementor\Plugin::$instance->breakpoints)) {
    foreach (\Elementor\Plugin::$instance->breakpoints->get_active_breakpoints() as $n => $bp) echo "breakpoint {$n}: " . $bp->get_value() . "\n";
}
