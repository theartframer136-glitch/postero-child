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
 * Here a widget in the header, footer or before-footer template is not built
 * when its own switches together with those of the sections around it hide
 * it at every width Elementor has switched on — the same test the browser
 * check makes, done on the settings. Only plain display widgets are left out
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

/** The devices hidden by an element's own switches, added to those inherited. */
function af_hw_merge_hidden(array $inherited, $element, array $devices) {
    foreach ($devices as $d) {
        if (!empty($inherited[$d])) continue;
        $v = is_object($element) && method_exists($element, 'get_settings') ? $element->get_settings('hide_' . $d) : '';
        if (!empty($v)) $inherited[$d] = true;
    }
    return $inherited;
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

function af_hw_active() {
    if (is_admin() || !class_exists('\Elementor\Plugin') || !function_exists('af_home_elementor_devices')) return false;
    $el = \Elementor\Plugin::$instance;
    if ((isset($el->editor) && $el->editor->is_edit_mode()) || (isset($el->preview) && $el->preview->is_preview_mode())) return false;
    $doc = isset($el->documents) ? $el->documents->get_current() : null;
    return $doc && in_array((int) $doc->get_main_id(), af_hw_template_ids(), true);
}

/** What the sections being built around the current element hide, innermost last. */
function af_hw_stack($op = null, $value = null) {
    static $stack = array();
    if ($op === 'push') $stack[] = $value;
    elseif ($op === 'pop') array_pop($stack);
    return $stack ? end($stack) : array();
}

/** Ids left out on this page, for the note at the end of it. */
function af_hw_not_sent($id = null) {
    static $ids = array();
    if ($id !== null) $ids[] = (string) $id;
    return $ids;
}

add_action('elementor/frontend/before_render', function ($element) {
    if (!af_hw_active()) return;
    af_hw_stack('push', af_hw_merge_hidden(af_hw_stack(), $element, af_home_elementor_devices()));
}, 1);
add_action('elementor/frontend/after_render', function ($element) {
    if (!af_hw_active()) return;
    af_hw_stack('pop');
}, 999);

add_filter('elementor/frontend/widget/should_render', function ($should, $element) {
    if (!$should || !af_hw_active() || !is_object($element) || !method_exists($element, 'get_name')) return $should;
    if (!in_array((string) $element->get_name(), af_hw_safe_types(), true)) return $should;
    $devices = af_home_elementor_devices();
    if (!af_hw_hidden_everywhere(af_hw_merge_hidden(af_hw_stack(), $element, $devices), $devices)) return $should;
    af_hw_not_sent($element->get_id());
    return false;
}, 20, 2);

add_action('wp_footer', function () {
    $ids = af_hw_not_sent();
    if ($ids) echo "\n<!-- af-header-weight: not sent, hidden at every width: " . esc_html(implode(' ', $ids)) . " -->\n";
}, 99);
