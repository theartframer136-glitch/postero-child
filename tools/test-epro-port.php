<?php
/**
 * Tests inc/ports/elementor-pro.php and its widgets (inc/ports/epro/): what
 * they print, against what Elementor Pro printed on the live pages on 5 Oct
 * (tools/probe-elementor-pro-markup.mjs), and that the port stays out of the
 * way while Elementor Pro is active.
 *
 * Runs the REAL files with WordPress and Elementor stubbed. The hero's
 * settings are #80f8de4's as saved (tools/diag-elementor-pro-widgets.php).
 * Plain `php`:
 *
 *     php tools/test-epro-port.php
 */
namespace {
    /* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
    define('ABSPATH', '/nowhere/');
    $GLOBALS['HOOKS'] = array(); $GLOBALS['REG'] = array(); $GLOBALS['RTL'] = false;
    function add_action($h, $cb, $prio = 10, $n = 1) { $GLOBALS['HOOKS'][$h][] = $cb; $GLOBALS['PRIO'][$h][] = $prio; }
    function get_stylesheet_directory_uri() { return 'https://theartframer.us/wp-content/themes/postero-child'; }
    function wp_register_style($h, $src, $deps = array(), $ver = false) { $GLOBALS['REG']['style'][$h] = $src; $GLOBALS['STYLEDEPS'][$h] = $deps; }
    function wp_style_is($h, $list = 'enqueued') { return $list === 'registered' ? array_key_exists($h, $GLOBALS['REG']['style'] ?? array()) : in_array($h, $GLOBALS['QSTYLE'] ?? array(), true); }
    function wp_enqueue_script($h) { $GLOBALS['QSCRIPT'][] = $h; }
    function wp_register_script($h, $src, $deps = array(), $ver = false, $foot = false) { $GLOBALS['REG']['script'][$h] = array($src, $deps); }
    function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
    function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
    function esc_url($s) { return (string) $s; }
    function wp_kses_post($s) { return (string) $s; }
    function is_rtl() { return $GLOBALS['RTL']; }
    function woocommerce_breadcrumb() { echo '<nav class="woocommerce-breadcrumb" aria-label="Breadcrumb"><a href="https://theartframer.us">Home</a>&nbsp;/&nbsp;Shop</nav>'; }
}

namespace Elementor {
    class Controls_Manager {
        const COLOR = 'color'; const MEDIA = 'media'; const SELECT = 'select'; const TEXT = 'text'; const TEXTAREA = 'textarea';
        const URL = 'url'; const REPEATER = 'repeater'; const SLIDER = 'slider'; const SWITCHER = 'switcher'; const NUMBER = 'number';
        const CHOOSE = 'choose'; const TAB_STYLE = 'style';
    }
    class Repeater { public $c = array(); function add_control($n, $a) { $this->c[$n] = $a; } function get_controls() { return $this->c; } }
    class Group_Control_Typography { static function get_type() { return 'typography'; } }
    class Icons_Manager {
        static function render_icon($icon, $attrs = array()) {
            echo '<svg aria-hidden="true" class="e-font-icon-svg e-' . $icon['value'] . '"></svg>';
        }
    }
    class Experiments { function is_feature_active($f) { return $f === 'e_optimized_markup'; } }
    class Plugin { public static $instance; public $experiments; }
    Plugin::$instance = new Plugin(); Plugin::$instance->experiments = new Experiments();
    abstract class Widget_Base {
        public $controls = array(); public $settings = array();
        function start_controls_section($n, $a = array()) {} function end_controls_section() {}
        function add_control($n, $a) { $this->controls[$n] = $a; }
        function add_responsive_control($n, $a) { $this->controls[$n] = $a + array('responsive' => true); }
        function add_group_control($t, $a) { $this->controls[$a['name']] = array('group' => $t) + $a; }
        function get_settings_for_display() { return $this->settings; }
        function boot() { $this->register_controls(); return $this; }
        function html() { ob_start(); $this->render(); return preg_replace('/>\s+</', '><', trim(ob_get_clean())); }
    }
}

namespace Elementor\Core\DynamicTags { class Dynamic_CSS {} }

namespace {
    $fail = 0; $pass = 0;
    function check($what, $got, $want) {
        global $fail, $pass;
        if ($got === $want) { $pass++; echo "  ok    $what\n"; }
        else { $fail++; echo "  FAIL  $what: got " . var_export($got, true) . ", want " . var_export($want, true) . "\n"; }
    }
    $root = dirname(__DIR__);

    echo "With Elementor Pro active the port does nothing\n";
    // Run the port in a separate PHP with Elementor Pro's constant defined: it must hook nothing.
    $probe = 'define("ABSPATH", "/x/"); define("ELEMENTOR_PRO_VERSION", "3.28.0"); $GLOBALS["n"] = 0;'
        . ' function add_action() { $GLOBALS["n"]++; } function get_stylesheet_directory_uri() { return ""; }'
        . ' require ' . var_export($root . '/inc/ports/elementor-pro.php', true) . '; echo $GLOBALS["n"], defined("AF_EPRO_PORT_VERSION") ? " defined" : " undefined";';
    check('with ELEMENTOR_PRO_VERSION defined: no hooks, nothing defined', trim((string) shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($probe))), '0 undefined');
    check('it is in the port list for the elementor-pro folder', strpos(file_get_contents($root . '/inc/plugin-ports.php'), "'elementor-pro'     => 'elementor-pro'") !== false, true);

    echo "\nWith Elementor Pro off\n";
    require $root . '/inc/ports/elementor-pro.php';
    check('it hooks widget registration', count($GLOBALS['HOOKS']['elementor/widgets/register'] ?? array()), 1);
    // after Elementor's Pro placeholders and the parent theme's widgets (its last widget hook is at 99)
    check('it registers after everything else (priority above 99)', ($GLOBALS['PRIO']['elementor/widgets/register'][0] ?? 0) > 99, true);
    check('it hooks element CSS (custom CSS)', count($GLOBALS['HOOKS']['elementor/element/parse_css'] ?? array()), 1);
    foreach ($GLOBALS['HOOKS']['elementor/frontend/after_register_styles'] as $cb) $cb();
    foreach ($GLOBALS['HOOKS']['elementor/frontend/after_register_scripts'] as $cb) $cb();
    check('slides stylesheet registered', $GLOBALS['REG']['style']['af-epro-slides'] ?? '', 'https://theartframer.us/wp-content/themes/postero-child/assets/ports/epro/slides.css');
    check('slides script registered after elementor-frontend', $GLOBALS['REG']['script']['af-epro-slides'][1] ?? array(), array('elementor-frontend'));
    // Pages served from Elementor's element cache load only the stored asset
    // list, which names Pro's "widget-slides" style: a stand-in brings ours.
    check('"widget-slides" stand-in: no file of its own', array_key_exists('widget-slides', $GLOBALS['REG']['style']) ? $GLOBALS['REG']['style']['widget-slides'] : 'missing', false);
    check('"widget-slides" stand-in brings the port stylesheet', $GLOBALS['STYLEDEPS']['widget-slides'] ?? null, array('af-epro-slides'));
    $GLOBALS['REG']['style'] = array('widget-slides' => 'pro.css'); $GLOBALS['STYLEDEPS'] = array();
    foreach ($GLOBALS['HOOKS']['elementor/frontend/after_register_styles'] as $cb) $cb();
    check('a "widget-slides" already registered is left alone', $GLOBALS['REG']['style']['widget-slides'], 'pro.css');
    $footer = $GLOBALS['HOOKS']['wp_footer'] ?? array();
    check('handler hook in the footer, before footer scripts print (20)', count($footer) === 1 && ($GLOBALS['PRIO']['wp_footer'][0] ?? 99) < 20, true);
    $runFooter = function ($queued) use ($footer) { $GLOBALS['QSTYLE'] = $queued; $GLOBALS['QSCRIPT'] = array(); foreach ($footer as $cb) $cb(); return $GLOBALS['QSCRIPT']; };
    check('no slides style on the page: no handler', $runFooter(array('widget-heading')), array());
    check('stored list (Pro\'s "widget-slides"): handler added', $runFooter(array('widget-slides')), array('af-epro-slides'));
    check('fresh render (port stylesheet): handler added', $runFooter(array('af-epro-slides')), array('af-epro-slides'));
    check('slides.css exists', is_readable($root . '/assets/ports/epro/slides.css'), true);
    check('slides.js exists', is_readable($root . '/assets/ports/epro/slides.js'), true);
    // As Pro's page: the slide showing is marked elementor-ken-burns--active
    // once the slider is up (the whole-site check of 5 Oct counted 17 plain
    // slide pictures plus that one, and the port's 18 copies match Pro's).
    $js = (string) @file_get_contents($root . '/assets/ports/epro/slides.js');
    check('slides.js: marks the slide showing (Ken Burns marker)', strpos($js, "classList.add('elementor-ken-burns--active')") !== false && strpos($js, 'slideChange: function () { kenBurns(this); }') !== false, true);
    check('slides.js: marks once the slider is up, not before', (bool) preg_match('/if \(!swiper\) return;\s*kenBurns\(swiper\);/', $js), true);
    check('slides.js: looping copies as Pro (loopedSlides = slide count)', strpos($js, 'if (opts.loop) opts.loopedSlides = count;') !== false, true);

    $registered = array();
    $mgr = new class($registered) { public $w = array(); function register($w) { $this->w[$w->get_name()] = $w; } };
    foreach ($GLOBALS['HOOKS']['elementor/widgets/register'] as $cb) $cb($mgr);
    check('widgets registered under Elementor Pro\'s names', array_keys($mgr->w), array('slides', 'woocommerce-breadcrumb'));

    echo "\nSlides: controls under Elementor Pro's names and selectors\n";
    $s = $mgr->w['slides']->boot();
    $c = $s->controls;
    check('slides repeater: picture -> .swiper-slide-bg background-image', $c['slides']['fields']['background_image']['selectors']['{{WRAPPER}} {{CURRENT_ITEM}} .swiper-slide-bg'] ?? '', 'background-image: url({{URL}})');
    check('slides repeater: size defaults to cover', $c['slides']['fields']['background_size']['default'] ?? '', 'cover');
    check('height -> .swiper-slide, responsive', array($c['slides_height']['selectors']['{{WRAPPER}} .swiper-slide'] ?? '', !empty($c['slides_height']['responsive'])), array('height: {{SIZE}}{{UNIT}};', true));
    check('autoplay speed -> slide transition-duration', $c['autoplay_speed']['selectors']['{{WRAPPER}} .swiper-slide'] ?? '', 'transition-duration: calc({{VALUE}}ms*1.2)');
    check('content width default 66%', $c['content_max_width']['default'] ?? array(), array('size' => '66', 'unit' => '%'));
    check('arrows size -> .elementor-swiper-button font-size', $c['arrows_size']['selectors']['{{WRAPPER}} .elementor-swiper-button'] ?? '', 'font-size: {{SIZE}}{{UNIT}};');
    check('arrows colour -> button colour and svg fill', array_values($c['arrows_color']['selectors'] ?? array()), array('color: {{VALUE}};', 'fill: {{VALUE}};'));
    check('dots size -> bullets, progress bar, fraction', count($c['dots_size']['selectors'] ?? array()), 3);
    foreach (array('navigation', 'autoplay', 'autoplay_speed', 'infinite', 'transition', 'transition_speed', 'pause_on_hover', 'pause_on_interaction') as $k) {
        check("$k reaches data-settings", !empty($c[$k]['frontend_available']), true);
    }
    check('position prefix classes as on the live page', array($c['slides_horizontal_position']['prefix_class'] . $c['slides_horizontal_position']['default'], $c['slides_vertical_position']['prefix_class'] . $c['slides_vertical_position']['default'], $c['arrows_position']['prefix_class'] . $c['arrows_position']['default'], $c['dots_position']['prefix_class'] . $c['dots_position']['default']), array('elementor--h-position-center', 'elementor--v-position-middle', 'elementor-arrows-position-inside', 'elementor-pagination-position-inside'));
    check('scripts: Elementor\'s swiper and the handler', $s->get_script_depends(), array('swiper', 'af-epro-slides'));
    check('no widget container with optimised markup (as live)', $s->has_widget_inner_wrapper(), false);

    echo "\nSlides: the hero #80f8de4 as saved\n";
    $ids = array('35910b4', '68187ef', '538586f', '1a49d25', 'f2e5614', 'd05023d');
    $s->settings = array('slides_name' => 'Slides', 'navigation' => 'both', 'content_animation' => 'fadeInUp', 'slides' => array());
    foreach ($ids as $id) $s->settings['slides'][] = array('_id' => $id, 'heading' => '', 'description' => '', 'button_text' => '', 'background_color' => '', 'background_image' => array('id' => 1, 'url' => 'x.webp'));
    $h = $s->html();
    check('wrapper as on the live page', (bool) preg_match('#^<div class="elementor-slides-wrapper elementor-main-swiper swiper" role="region" aria-roledescription="carousel" aria-label="Slides" dir="ltr" data-animation="fadeInUp"><div class="swiper-wrapper elementor-slides">#', $h), true);
    check('six slides, in order, with their repeater ids', preg_match_all('#<div class="elementor-repeater-item-([0-9a-f]{7}) swiper-slide" role="group" aria-roledescription="slide">#', $h, $m) ? $m[1] : array(), $ids);
    check('each slide: picture box, inner box, empty contents', substr_count($h, '<div class="swiper-slide-bg" role="img"></div><div class="swiper-slide-inner"><div class="swiper-slide-contents"></div></div>'), 6);
    check('dots', substr_count($h, '<div class="swiper-pagination"></div>'), 1);
    check('arrows with the eicon chevrons', array(strpos($h, '<div class="elementor-swiper-button elementor-swiper-button-prev" role="button" tabindex="0"><svg aria-hidden="true" class="e-font-icon-svg e-eicon-chevron-left">') !== false, strpos($h, '<div class="elementor-swiper-button elementor-swiper-button-next" role="button" tabindex="0"><svg aria-hidden="true" class="e-font-icon-svg e-eicon-chevron-right">') !== false), array(true, true));

    echo "\nSlides: other settings\n";
    $s->settings['navigation'] = 'dots';
    $h = $s->html();
    check('dots only: no arrows', array(strpos($h, 'swiper-pagination') !== false, strpos($h, 'elementor-swiper-button') !== false), array(true, false));
    $s->settings['navigation'] = '';
    check('no navigation: neither', preg_match('/swiper-pagination|elementor-swiper-button/', $s->html()), 0);
    $s->settings['navigation'] = 'both';
    $s->settings['slides'] = array_slice($s->settings['slides'], 0, 1);
    check('one slide: no arrows or dots', preg_match('/swiper-pagination|elementor-swiper-button/', $s->html()), 0);
    $s->settings['slides'] = array(array('_id' => 'abc1234', 'heading' => 'Big <b>sale</b>', 'description' => 'Now', 'button_text' => 'Shop', 'link' => array('url' => 'https://theartframer.us/shop/'), 'link_click' => 'slide'));
    $h = $s->html();
    check('linked slide: the inner box is the link', strpos($h, '<a class="swiper-slide-inner" href="https://theartframer.us/shop/">') !== false, true);
    check('title, description, button', strpos($h, '<div class="swiper-slide-contents"><div class="elementor-slide-heading">Big <b>sale</b></div><div class="elementor-slide-description">Now</div><div class="elementor-button elementor-slide-button elementor-size-sm">Shop</div></div>') !== false, true);
    $s->settings['slides'][0]['link_click'] = 'button';
    $h = $s->html();
    check('button-only link: the button is the link', array(strpos($h, '<a class="elementor-button elementor-slide-button elementor-size-sm" href="https://theartframer.us/shop/">Shop</a>') !== false, strpos($h, '<div class="swiper-slide-inner">') !== false), array(true, true));
    $s->settings['slides'] = array();
    check('no slides: nothing', $s->html(), '');
    $GLOBALS['RTL'] = true; $s->settings['slides'] = array_fill(0, 2, array('_id' => 'aaaaaaa'));
    $h = $s->html();
    check('right-to-left: dir=rtl, prev arrow points right', array(strpos($h, 'dir="rtl"') !== false, strpos($h, 'elementor-swiper-button-prev" role="button" tabindex="0"><svg aria-hidden="true" class="e-font-icon-svg e-eicon-chevron-right"') !== false), array(true, true));
    $GLOBALS['RTL'] = false;

    echo "\nBreadcrumbs\n";
    $b = $mgr->w['woocommerce-breadcrumb']->boot();
    check('prints WooCommerce\'s breadcrumb, as on the live shop page', $b->html(), '<nav class="woocommerce-breadcrumb" aria-label="Breadcrumb"><a href="https://theartframer.us">Home</a>&nbsp;/&nbsp;Shop</nav>');
    check('text colour -> .woocommerce-breadcrumb', $b->controls['text_color']['selectors']['{{WRAPPER}} .woocommerce-breadcrumb'] ?? '', 'color: {{VALUE}}');
    check('link colour -> .woocommerce-breadcrumb > a', $b->controls['link_color']['selectors']['{{WRAPPER}} .woocommerce-breadcrumb > a'] ?? '', 'color: {{VALUE}}');
    check('typography and alignment', array(isset($b->controls['text_typography']), isset($b->controls['alignment'])), array(true, true));

    echo "\nCustom CSS, as Elementor Pro added it\n";
    $sheet = new class { public $raw = array(); function add_raw_css($c) { $this->raw[] = $c; } };
    $css = new class($sheet) { public $s; function __construct($s) { $this->s = $s; } function get_element_unique_selector($e) { return '.elementor-2592 .elementor-element.elementor-element-' . $e->id; } function get_stylesheet() { return $this->s; } };
    $el = new class { public $id = 'bec7134'; public $set = array(); function get_settings() { return $this->set; } function get_name() { return 'container'; } function get_unique_selector() { return '.elementor-element-' . $this->id; } };
    $hook = $GLOBALS['HOOKS']['elementor/element/parse_css'][0];
    $el->set = array('custom_css' => "selector {\n    position: fixed;\n    width: 100%;\n    bottom: 0;\n    z-index: 9999;\n}");
    $hook($css, $el);
    check('footer bar: selector made the element\'s own, with Pro\'s comments', $sheet->raw[0] ?? '', "/* Start custom CSS for container, class: .elementor-element-bec7134 */.elementor-2592 .elementor-element.elementor-element-bec7134 {\n    position: fixed;\n    width: 100%;\n    bottom: 0;\n    z-index: 9999;\n}/* End custom CSS */");
    $sheet->raw = array(); $el->set = array('custom_css' => "   \n "); $hook($css, $el);
    $el->set = array(); $hook($css, $el);
    check('blank or no custom CSS: nothing added', $sheet->raw, array());
    $hook(new \Elementor\Core\DynamicTags\Dynamic_CSS(), $el);
    check('dynamic CSS files are left alone', $sheet->raw, array());

    echo "\n" . ($fail ? "$fail FAILED, $pass passed\n" : "all $pass passed\n");
    exit($fail ? 1 : 0);
}
