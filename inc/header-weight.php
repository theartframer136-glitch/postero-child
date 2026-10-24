<?php
/**
 * The site header and footer send only what someone can see.
 *
 * The header template (Header Footer Elementor, inc/ports/hfe) holds a second
 * copy of several pieces for other screen sizes, nested so that each copy is
 * hidden at some widths by its section and at the rest by its own "Hide On"
 * switches: hidden at every width, yet sent on every page. Checked 24 Oct 2026
 * on /cart/ in a real browser at one width in each of the six device ranges
 * (tools/diag-never-visible.js, check-element.yml "never-visible"): 14 header
 * widgets, 77 KB of a 215 KB header, are drawn at none of them — a whole
 * second category menu (23 KB), a second search box with its category list
 * (22 KB), two logos, two contact lists and a social-icons block among them.
 *
 * Here a widget in the header, footer or before-footer template is taken out
 * of the finished page when its own switches together with those of the
 * sections around it hide it at every width Elementor has switched on — the
 * same test the browser check makes, done on the templates' saved settings.
 * It works on the finished HTML because Elementor serves these templates
 * from its element cache, where no per-widget render hook runs (found after
 * the first version, which used those hooks, left the header unchanged). Only plain display widgets are left out
 * (menus, search boxes, logos, icon lists, headings, images, text): never a
 * shortcode, HTML or template widget, the side-menu button, the currency
 * switcher or the cart/account icon group, which carry or answer scripts.
 * Showing a widget again at any width in Elementor brings it straight back.
 * inc/home-weight.php does the same for the home page's own sections.
 *
 * Tests: tools/test-header-weight.php.
 */
if (!defined('ABSPATH')) exit;

/** Widget types that only display something, and so can be left out when nobody can see them. */
function af_hw_safe_types() {
    return (array) apply_filters('af_hw_safe_types', array(
        'postero-nav-menu', 'navigation-menu', 'nav-menu', 'postero-search', 'site-logo', 'social-icons',
        'icon-list', 'icon-box', 'heading', 'image', 'text-editor', 'divider', 'spacer', 'button',
    ));
}

/** Hidden at every one of $devices, given the hide_<device> switches of the widget and its ancestors? */
function af_hw_hidden_everywhere(array $hidden, array $devices) {
    if (!$devices) return false;
    foreach ($devices as $d) if (empty($hidden[$d])) return false;
    return true;
}

/** The header, footer and before-footer template ids. */
function af_hw_template_ids() {
    static $ids = null;
    if ($ids !== null) return $ids;
    $ids = array();
    foreach (array('get_hfe_header_id', 'get_hfe_footer_id', 'hfe_get_before_footer_id') as $fn) {
        if (function_exists($fn)) { $id = (int) call_user_func($fn); if ($id) $ids[] = $id; }
    }
    return $ids;
}

/**
 * Ids of the widgets in an Elementor element tree that are hidden at every one
 * of $devices, counting the switches of the sections around them, and whose
 * type is in $safe.
 */
function af_hw_hidden_widget_ids(array $elements, array $devices, array $safe, array $inherited = array()) {
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
}

/** Those ids for the header/footer templates, remembered until a template is saved again. */
function af_hw_ids_to_remove() {
    static $ids = null;
    if ($ids !== null) return $ids;
    $ids = array();
    $tpl = af_hw_template_ids();
    if (!$tpl || !function_exists('af_home_elementor_devices') || !class_exists('\Elementor\Plugin')) return $ids;
    $devices = af_home_elementor_devices();
    $sig = array();
    foreach ($tpl as $t) $sig[] = $t . ':' . get_post_field('post_modified_gmt', $t);
    $key = 'af_hw_ids_' . md5(implode('|', $sig) . '|' . implode(',', $devices) . '|' . implode(',', af_hw_safe_types()));
    $hit = get_transient($key);
    if (is_array($hit)) return $ids = $hit;
    foreach ($tpl as $t) {
        $raw = get_post_meta($t, '_elementor_data', true);
        $data = is_string($raw) ? json_decode($raw, true) : $raw;
        if (is_array($data)) $ids = array_merge($ids, af_hw_hidden_widget_ids($data, $devices, af_hw_safe_types()));
    }
    set_transient($key, $ids, WEEK_IN_SECONDS);
    return $ids;
}

/**
 * $html without the Elementor widget whose data-id is $id: from its opening
 * <div> to the </div> that closes it, counting the <div>s in between. Left
 * untouched when the widget holds a <script>, <style>, <template>,
 * <textarea> or a comment (where a "<div" might not be a tag), or when the
 * closing tag cannot be found.
 */
function af_hw_strip_widget($html, $id) {
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
}

/** The page with those widgets taken out, inside the header and footer only. */
function af_hw_filter_page($html, array $ids) {
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
}

add_action('template_redirect', function () {
    if (is_admin() || is_feed() || (defined('REST_REQUEST') && REST_REQUEST) || (defined('DOING_AJAX') && DOING_AJAX)) return;
    if (class_exists('\Elementor\Plugin')) {
        $el = \Elementor\Plugin::$instance;
        if ((isset($el->editor) && $el->editor->is_edit_mode()) || (isset($el->preview) && $el->preview->is_preview_mode())) return;
    }
    if (isset($_GET['elementor-preview'])) return; // phpcs:ignore WordPress.Security.NonceVerification
    $ids = af_hw_ids_to_remove();
    if (!$ids) return;
    ob_start(function ($html) use ($ids) { return af_hw_filter_page($html, $ids); });
}, -97);
