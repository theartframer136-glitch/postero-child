<?php
/**
 * Elementor Pro: what this site uses from it, done with free parts.
 *
 * Owner, 5 Oct: "keep everything free", "Drop Elementor Pro (free)". The
 * installed Elementor Pro 3.28.0 writes its own licence (elementor-pro.php
 * lines 17-18: key "activated", "valid, expires 01.01.2030"), so Elementor's
 * server refuses it every update (tools/diag-elementor-pro-licence.php).
 *
 * Read from the live database and pages (tools/diag-elementor-pro-usage.php,
 * diag-elementor-pro-widgets.php, diag-elementor-pro-extras.php,
 * probe-elementor-pro-markup.mjs, probe-slides-style.mjs), what a visitor sees
 * from Elementor Pro is three things:
 *
 *  1. Slides: the home page hero, #80f8de4 (desktop) and #0971963 (phones),
 *     six picture-only slides each, arrows and dots, autoplay every 5 s,
 *     looping. inc/ports/epro/slides.php is a widget of the same name with
 *     the controls those two use, under the same names and with the same CSS
 *     selectors, so Elementor writes the same rules into post-75.css, and the
 *     same markup (elementor-slides-wrapper > swiper-wrapper elementor-slides
 *     > elementor-repeater-item-<id> swiper-slide > swiper-slide-bg +
 *     swiper-slide-inner), which the theme's own CSS and arrow script
 *     (functions.php 20d, assets/css/custom.css) are written against.
 *     assets/ports/epro/slides.js starts it with the Swiper Elementor itself
 *     ships (elementorFrontend.utils.swiper), with the options Elementor Pro's
 *     handler gave it; assets/ports/epro/slides.css is the layout of
 *     Elementor Pro's slides stylesheet.
 *  2. WooCommerce Breadcrumbs: the six breadcrumb templates (#1853, #3014,
 *     #3377, #3425, #5660, #5661). inc/ports/epro/woo-breadcrumb.php prints
 *     woocommerce_breadcrumb() as Elementor Pro did, with its colour,
 *     typography and alignment controls under the same names and selectors.
 *  3. Custom CSS on an element: the footer bar container bec7134 (#2592),
 *     "position: fixed; bottom: 0" for the bottom bar on phones and tablets.
 *     Elementor Pro added each element's custom_css to its document's CSS
 *     file with "selector" replaced by the element's selector; the hook below
 *     does the same.
 *
 * Not needed: the three Gallery widgets and the nested carousel on the home
 * page sit in sections hidden at every width, which inc/home-weight.php does
 * not send; the popups (#5457, #5458, #5651) and the loop item #9012 (its
 * dynamic tags, its custom CSS) have no display conditions and are never shown;
 * no page uses Pro's popups links, motion effects, sticky, display conditions,
 * custom attributes, custom fonts, icons or Custom Code.
 *
 * While Elementor Pro is active this file does nothing.
 */
defined('ABSPATH') || exit;

if (defined('ELEMENTOR_PRO_VERSION') || class_exists('ElementorPro\Plugin', false)) {
    return; // the plugin is active and does the work
}

define('AF_EPRO_PORT_VERSION', '1');
define('AF_EPRO_PORT_URL', get_stylesheet_directory_uri() . '/assets/ports/epro/');

// The two widgets, under Elementor Pro's names, so the saved pages use them.
// Registered last: a name registered again replaces the earlier one, and
// with Elementor Pro off two others claim these names on the same hook
// after the child theme (tools/diag-elementor-widget-owners.php, 5 Oct):
// Elementor's Promotions module puts a "get Pro" placeholder under every Pro
// widget name ("slides" among them), and the parent theme registers its own
// "woocommerce-breadcrumb" ("Home Page", an arrow icon, a page title). Its
// last widget hook runs at 99.
add_action('elementor/widgets/register', function ($widgets_manager) {
    require_once __DIR__ . '/epro/slides.php';
    require_once __DIR__ . '/epro/woo-breadcrumb.php';
    $widgets_manager->register(new AF_EPro_Slides());
    if (function_exists('woocommerce_breadcrumb')) {
        $widgets_manager->register(new AF_EPro_Woo_Breadcrumb());
    }
}, 1000);

// The slides' stylesheet and handler; Elementor loads them on the pages that
// carry the widget (get_style_depends / get_script_depends).
add_action('elementor/frontend/after_register_styles', function () {
    wp_register_style('af-epro-slides', AF_EPRO_PORT_URL . 'slides.css', array(), AF_EPRO_PORT_VERSION);
});
add_action('elementor/frontend/after_register_scripts', function () {
    wp_register_script('af-epro-slides', AF_EPRO_PORT_URL . 'slides.js', array('elementor-frontend'), AF_EPRO_PORT_VERSION, true);
});

// Elementor Pro's Custom CSS (modules/custom-css): each element's custom_css,
// with "selector" made the element's own, appended to its document's CSS.
add_action('elementor/element/parse_css', function ($post_css, $element) {
    if ($post_css instanceof \Elementor\Core\DynamicTags\Dynamic_CSS) {
        return;
    }
    $settings = $element->get_settings();
    if (empty($settings['custom_css']) || !is_string($settings['custom_css'])) {
        return;
    }
    $css = trim($settings['custom_css']);
    if ($css === '') {
        return;
    }
    $css = str_replace('selector', $post_css->get_element_unique_selector($element), $css);
    $css = sprintf('/* Start custom CSS for %s, class: %s */', $element->get_name(), $element->get_unique_selector()) . $css . '/* End custom CSS */';
    $post_css->get_stylesheet()->add_raw_css($css);
}, 10, 2);
