<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. The Elementor Pro settings the whole-site preview of 5 Oct
 * (Preview Switch, run 37295746111) found the theme's port missing, on the
 * home page only:
 *   - the hero slides' Ken Burns zoom (elementor-ken-burns--active gone)
 *   - the phone header: stuck on load and the page 72px taller, with the
 *     parent theme's own sticky scripts (elementor-sticky.js, sticky.js)
 *     loaded in place of Elementor Pro's
 *
 * Prints, for every published Elementor document (pages, templates, header
 * and footer templates):
 *   - each element with Pro's sticky or motion-effect settings (sticky,
 *     sticky_on, sticky_offset, sticky_effects_offset, motion_fx_*), its
 *     type, where it sits, its hide-on settings, and the values
 *   - each slides widget's per-slide Ken Burns settings (background_ken_burns,
 *     zoom_direction) and any other setting the port's widget does not know
 *
 * Run: wp eval-file tools/diag-elementor-pro-motion.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
global $wpdb;

$docs = $wpdb->get_results("SELECT p.ID, p.post_type, p.post_title, m.meta_value AS data FROM {$wpdb->posts} p
    JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_elementor_data'
    WHERE p.post_status IN ('publish','private') ORDER BY p.ID");
$known = array('slides', 'slides_height', 'slides_name', 'navigation', 'autoplay', 'pause_on_hover', 'pause_on_interaction', 'autoplay_speed',
    'infinite', 'transition', 'transition_speed', 'content_animation', 'content_max_width', 'slides_horizontal_position', 'slides_vertical_position',
    'slides_text_align', 'button_hover_text_color', 'arrows_position', 'arrows_size', 'arrows_color', 'dots_position', 'dots_size', 'dots_color');
$walk = function ($els, $doc, $path) use (&$walk, $known) {
    foreach ((array) $els as $el) {
        if (!is_array($el)) continue;
        $s = isset($el['settings']) && is_array($el['settings']) ? $el['settings'] : array();
        $type = isset($el['widgetType']) ? $el['widgetType'] : (isset($el['elType']) ? $el['elType'] : '?');
        $id = isset($el['id']) ? $el['id'] : '?';
        $motion = array();
        foreach ($s as $k => $v) {
            if (preg_match('/^(sticky|motion_fx_|_?ken_burns|background_ken|background_slideshow_ken)/', $k) && $v !== '' && $v !== null && $v !== array()) $motion[$k] = $v;
        }
        if ($motion) {
            $hide = array();
            foreach (array('hide_desktop', 'hide_laptop', 'hide_tablet', 'hide_mobile', 'hide_mobile_extra') as $h) if (!empty($s[$h])) $hide[] = $h;
            echo "  $doc  $path/#$id $type" . ($hide ? '  (' . implode(' ', $hide) . ')' : '') . "\n      " . substr(json_encode($motion), 0, 600) . "\n";
        }
        if ($type === 'slides') {
            echo "  $doc  $path/#$id slides: " . count((array) ($s['slides'] ?? array())) . " slides\n";
            foreach ((array) ($s['slides'] ?? array()) as $i => $slide) {
                $kb = array();
                foreach ((array) $slide as $k => $v) if (preg_match('/ken|zoom/i', $k)) $kb[$k] = $v;
                echo "      slide $i #" . ($slide['_id'] ?? '?') . ': ' . ($kb ? json_encode($kb) : 'no Ken Burns setting') . "\n";
            }
            $unknown = array_diff(array_keys($s), $known);
            $unknown = array_values(array_filter($unknown, function ($k) { return strpos($k, '_') !== 0 && !preg_match('/_(laptop|tablet|mobile|mobile_extra|tablet_extra|widescreen)$/', $k); }));
            if ($unknown) echo "      other settings: " . implode(', ', $unknown) . "\n";
        }
        if (!empty($el['elements'])) $walk($el['elements'], $doc, $path . '/' . $type . '#' . $id);
    }
};
echo "=== Pro sticky / motion effects / Ken Burns settings, and the slides widgets ===\n";
foreach ($docs as $d) {
    $data = json_decode($d->data, true);
    if (is_array($data)) $walk($data, '#' . $d->ID . ' ' . $d->post_type . ' "' . mb_substr($d->post_title, 0, 24) . '"', '');
}

// The parent theme's sticky: when does it load, and on what.
echo "\n=== parent theme sticky scripts ===\n";
foreach (array('elementor-sticky', 'sticky', 'postero-sticky') as $h) {
    $reg = wp_scripts()->query($h, 'registered');
    echo "  $h: " . ($reg ? 'registered, src ' . preg_replace('#^https?://[^/]+#', '', (string) $reg->src) . ', deps ' . json_encode($reg->deps) : 'not registered (yet)') . "\n";
}
$theme = get_template_directory();
foreach (array('elementor-sticky.js', 'sticky.js') as $f) {
    $hits = array();
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($theme . '/inc', FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (substr($file, -4) !== '.php') continue;
        foreach (file($file) as $n => $line) if (strpos($line, $f) !== false) $hits[] = substr($file, strlen($theme) + 1) . ':' . ($n + 1) . '  ' . trim($line);
    }
    echo "  $f is loaded from: " . ($hits ? "\n    " . implode("\n    ", array_slice($hits, 0, 6)) : 'nowhere in inc/') . "\n";
}
echo "done\n";
