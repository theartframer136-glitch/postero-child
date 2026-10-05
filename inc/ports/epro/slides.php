<?php
/**
 * The Slides widget, as Elementor Pro drew it, with free parts (see
 * inc/ports/elementor-pro.php). Same name ("slides"), the controls the home
 * page hero uses under Elementor Pro's names and CSS selectors, so the saved
 * settings of #80f8de4 and #0971963 produce the same rules in post-75.css,
 * and the same markup, read from the live page on 5 Oct
 * (tools/probe-elementor-pro-markup.mjs):
 *
 *   div.elementor-slides-wrapper.elementor-main-swiper.swiper[role=region][data-animation]
 *     div.swiper-wrapper.elementor-slides
 *       div.elementor-repeater-item-<id>.swiper-slide[role=group]
 *         div.swiper-slide-bg[role=img]
 *         div|a.swiper-slide-inner > div.swiper-slide-contents (heading, description, button)
 *     div.swiper-pagination
 *     div.elementor-swiper-button.elementor-swiper-button-prev|next[role=button] > chevron icon
 */
defined('ABSPATH') || exit;

use Elementor\Controls_Manager;
use Elementor\Icons_Manager;
use Elementor\Repeater;
use Elementor\Widget_Base;

class AF_EPro_Slides extends Widget_Base {

    public function get_name() { return 'slides'; }
    public function get_title() { return 'Slides'; }
    public function get_icon() { return 'eicon-slides'; }
    public function get_categories() { return array('general'); }
    public function get_keywords() { return array('slides', 'carousel', 'image', 'title', 'slider'); }
    public function get_script_depends() { return array('swiper', 'af-epro-slides'); }
    public function get_style_depends() { return array('e-swiper', 'swiper', 'af-epro-slides'); }

    // No elementor-widget-container when Elementor's optimised markup is on,
    // as on the live page.
    public function has_widget_inner_wrapper(): bool {
        $e = \Elementor\Plugin::$instance;
        return !(isset($e->experiments) && $e->experiments->is_feature_active('e_optimized_markup'));
    }

    protected function register_controls() {
        $this->start_controls_section('section_slides', array('label' => 'Slides'));

        $repeater = new Repeater();
        $repeater->add_control('background_color', array(
            'label' => 'Color', 'type' => Controls_Manager::COLOR,
            'selectors' => array('{{WRAPPER}} {{CURRENT_ITEM}} .swiper-slide-bg' => 'background-color: {{VALUE}}'),
        ));
        $repeater->add_control('background_image', array(
            'label' => 'Image', 'type' => Controls_Manager::MEDIA,
            'selectors' => array('{{WRAPPER}} {{CURRENT_ITEM}} .swiper-slide-bg' => 'background-image: url({{URL}})'),
        ));
        $repeater->add_control('background_size', array(
            'label' => 'Size', 'type' => Controls_Manager::SELECT, 'default' => 'cover',
            'options' => array('cover' => 'Cover', 'contain' => 'Contain', 'auto' => 'Auto'),
            'selectors' => array('{{WRAPPER}} {{CURRENT_ITEM}} .swiper-slide-bg' => 'background-size: {{VALUE}}'),
            'conditions' => array('terms' => array(array('name' => 'background_image[url]', 'operator' => '!=', 'value' => ''))),
        ));
        $repeater->add_control('heading', array('label' => 'Title', 'type' => Controls_Manager::TEXT, 'default' => '', 'label_block' => true));
        $repeater->add_control('description', array('label' => 'Description', 'type' => Controls_Manager::TEXTAREA, 'default' => ''));
        $repeater->add_control('button_text', array('label' => 'Button Text', 'type' => Controls_Manager::TEXT, 'default' => ''));
        $repeater->add_control('link', array('label' => 'Link', 'type' => Controls_Manager::URL));
        $repeater->add_control('link_click', array(
            'label' => 'Apply Link On', 'type' => Controls_Manager::SELECT, 'default' => 'slide',
            'options' => array('slide' => 'Whole Slide', 'button' => 'Button Only'),
        ));

        $this->add_control('slides', array(
            'label' => 'Slides', 'type' => Controls_Manager::REPEATER, 'show_label' => true,
            'fields' => $repeater->get_controls(), 'title_field' => '{{{ heading }}}',
        ));
        $this->add_responsive_control('slides_height', array(
            'label' => 'Height', 'type' => Controls_Manager::SLIDER,
            'range' => array('px' => array('min' => 100, 'max' => 1000)),
            'default' => array('size' => 400, 'unit' => 'px'),
            'size_units' => array('px', 'vh', 'em'),
            'selectors' => array('{{WRAPPER}} .swiper-slide' => 'height: {{SIZE}}{{UNIT}};'),
        ));
        $this->add_control('slides_name', array('label' => 'Slides Name', 'type' => Controls_Manager::TEXT, 'default' => 'Slides'));
        $this->end_controls_section();

        $this->start_controls_section('section_slider_options', array('label' => 'Slider Options'));
        $this->add_control('navigation', array(
            'label' => 'Navigation', 'type' => Controls_Manager::SELECT, 'default' => 'both', 'frontend_available' => true,
            'options' => array('' => 'None', 'both' => 'Arrows and Dots', 'arrows' => 'Arrows', 'dots' => 'Dots'),
        ));
        $this->add_control('autoplay', array('label' => 'Autoplay', 'type' => Controls_Manager::SWITCHER, 'default' => 'yes', 'frontend_available' => true));
        $this->add_control('pause_on_hover', array('label' => 'Pause on Hover', 'type' => Controls_Manager::SWITCHER, 'default' => 'yes', 'frontend_available' => true, 'condition' => array('autoplay!' => '')));
        $this->add_control('pause_on_interaction', array('label' => 'Pause on Interaction', 'type' => Controls_Manager::SWITCHER, 'default' => 'yes', 'frontend_available' => true, 'condition' => array('autoplay!' => '')));
        $this->add_control('autoplay_speed', array(
            'label' => 'Autoplay Speed', 'type' => Controls_Manager::NUMBER, 'default' => 5000, 'frontend_available' => true,
            'condition' => array('autoplay' => 'yes'),
            'selectors' => array('{{WRAPPER}} .swiper-slide' => 'transition-duration: calc({{VALUE}}ms*1.2)'),
        ));
        $this->add_control('infinite', array('label' => 'Infinite Loop', 'type' => Controls_Manager::SWITCHER, 'default' => 'yes', 'frontend_available' => true));
        $this->add_control('transition', array(
            'label' => 'Transition', 'type' => Controls_Manager::SELECT, 'default' => 'slide', 'frontend_available' => true,
            'options' => array('slide' => 'Slide', 'fade' => 'Fade'),
        ));
        $this->add_control('transition_speed', array('label' => 'Transition Speed (ms)', 'type' => Controls_Manager::NUMBER, 'default' => 500, 'frontend_available' => true));
        $this->add_control('content_animation', array(
            'label' => 'Content Animation', 'type' => Controls_Manager::SELECT, 'default' => 'fadeInUp',
            'options' => array('' => 'None', 'fadeInDown' => 'Down', 'fadeInUp' => 'Up', 'fadeInRight' => 'Right', 'fadeInLeft' => 'Left', 'zoomIn' => 'Zoom'),
        ));
        $this->end_controls_section();

        $this->start_controls_section('section_style_slides', array('label' => 'Slides', 'tab' => Controls_Manager::TAB_STYLE));
        $this->add_responsive_control('content_max_width', array(
            'label' => 'Content Width', 'type' => Controls_Manager::SLIDER,
            'range' => array('px' => array('min' => 0, 'max' => 1000), '%' => array('min' => 0, 'max' => 100)),
            'size_units' => array('%', 'px'),
            'default' => array('size' => '66', 'unit' => '%'),
            'selectors' => array('{{WRAPPER}} .swiper-slide-contents' => 'max-width: {{SIZE}}{{UNIT}};'),
        ));
        $this->add_control('slides_horizontal_position', array(
            'label' => 'Horizontal Position', 'type' => Controls_Manager::CHOOSE, 'default' => 'center',
            'options' => array('left' => array('title' => 'Left', 'icon' => 'eicon-h-align-left'), 'center' => array('title' => 'Center', 'icon' => 'eicon-h-align-center'), 'right' => array('title' => 'Right', 'icon' => 'eicon-h-align-right')),
            'prefix_class' => 'elementor--h-position-',
        ));
        $this->add_control('slides_vertical_position', array(
            'label' => 'Vertical Position', 'type' => Controls_Manager::CHOOSE, 'default' => 'middle',
            'options' => array('top' => array('title' => 'Top', 'icon' => 'eicon-v-align-top'), 'middle' => array('title' => 'Middle', 'icon' => 'eicon-v-align-middle'), 'bottom' => array('title' => 'Bottom', 'icon' => 'eicon-v-align-bottom')),
            'prefix_class' => 'elementor--v-position-',
        ));
        $this->add_control('slides_text_align', array(
            'label' => 'Text Align', 'type' => Controls_Manager::CHOOSE, 'default' => 'center',
            'options' => array('left' => array('title' => 'Left', 'icon' => 'eicon-text-align-left'), 'center' => array('title' => 'Center', 'icon' => 'eicon-text-align-center'), 'right' => array('title' => 'Right', 'icon' => 'eicon-text-align-right')),
            'selectors' => array('{{WRAPPER}} .swiper-slide-inner' => 'text-align: {{VALUE}}'),
        ));
        $this->add_control('button_hover_text_color', array(
            'label' => 'Button Hover Text Color', 'type' => Controls_Manager::COLOR,
            'selectors' => array('{{WRAPPER}} .elementor-slide-button:hover' => 'color: {{VALUE}};'),
        ));
        $this->end_controls_section();

        $this->start_controls_section('section_style_navigation', array('label' => 'Navigation', 'tab' => Controls_Manager::TAB_STYLE));
        $this->add_control('arrows_position', array(
            'label' => 'Arrows Position', 'type' => Controls_Manager::SELECT, 'default' => 'inside',
            'options' => array('inside' => 'Inside', 'outside' => 'Outside'), 'prefix_class' => 'elementor-arrows-position-',
        ));
        $this->add_responsive_control('arrows_size', array(
            'label' => 'Arrows Size', 'type' => Controls_Manager::SLIDER, 'range' => array('px' => array('min' => 20, 'max' => 60)),
            'selectors' => array('{{WRAPPER}} .elementor-swiper-button' => 'font-size: {{SIZE}}{{UNIT}};'),
        ));
        $this->add_control('arrows_color', array(
            'label' => 'Arrows Color', 'type' => Controls_Manager::COLOR,
            'selectors' => array('{{WRAPPER}} .elementor-swiper-button' => 'color: {{VALUE}};', '{{WRAPPER}} .elementor-swiper-button svg' => 'fill: {{VALUE}};'),
        ));
        $this->add_control('dots_position', array(
            'label' => 'Dots Position', 'type' => Controls_Manager::SELECT, 'default' => 'inside',
            'options' => array('outside' => 'Outside', 'inside' => 'Inside'), 'prefix_class' => 'elementor-pagination-position-',
        ));
        $this->add_responsive_control('dots_size', array(
            'label' => 'Dots Size', 'type' => Controls_Manager::SLIDER, 'range' => array('px' => array('min' => 5, 'max' => 15)),
            'selectors' => array(
                '{{WRAPPER}} .swiper-pagination-bullet' => 'height: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}}',
                '{{WRAPPER}} .swiper-horizontal .swiper-pagination-progressbar' => 'height: {{SIZE}}{{UNIT}}',
                '{{WRAPPER}} .swiper-pagination-fraction' => 'font-size: {{SIZE}}{{UNIT}}',
            ),
        ));
        $this->add_control('dots_color', array(
            'label' => 'Dots Color', 'type' => Controls_Manager::COLOR,
            'selectors' => array('{{WRAPPER}} .swiper-pagination-bullet-active' => 'background-color: {{VALUE}};'),
        ));
        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        if (empty($settings['slides']) || !is_array($settings['slides'])) {
            return;
        }
        $slides = array();
        foreach ($settings['slides'] as $slide) {
            $url        = !empty($slide['link']['url']) ? $slide['link']['url'] : '';
            $on_slide   = $url !== '' && (!isset($slide['link_click']) || $slide['link_click'] !== 'button');
            $link_attrs = '';
            if ($url !== '') {
                $link_attrs = ' href="' . esc_url($url) . '"'
                    . (!empty($slide['link']['is_external']) ? ' target="_blank"' : '')
                    . (!empty($slide['link']['nofollow']) ? ' rel="nofollow"' : '');
            }
            $contents = '';
            if (isset($slide['heading']) && $slide['heading'] !== '') {
                $contents .= '<div class="elementor-slide-heading">' . wp_kses_post($slide['heading']) . '</div>';
            }
            if (isset($slide['description']) && $slide['description'] !== '') {
                $contents .= '<div class="elementor-slide-description">' . wp_kses_post($slide['description']) . '</div>';
            }
            if (isset($slide['button_text']) && $slide['button_text'] !== '') {
                $tag = ($url !== '' && !$on_slide) ? 'a' : 'div';
                $contents .= '<' . $tag . ' class="elementor-button elementor-slide-button elementor-size-sm"' . ($tag === 'a' ? $link_attrs : '') . '>' . esc_html($slide['button_text']) . '</' . $tag . '>';
            }
            $inner_tag = $on_slide ? 'a' : 'div';
            $slides[] = '<div class="elementor-repeater-item-' . esc_attr($slide['_id']) . ' swiper-slide" role="group" aria-roledescription="slide">'
                . '<div class="swiper-slide-bg" role="img"></div>'
                . '<' . $inner_tag . ' class="swiper-slide-inner"' . ($on_slide ? $link_attrs : '') . '>'
                . '<div class="swiper-slide-contents">' . $contents . '</div>'
                . '</' . $inner_tag . '>'
                . '</div>';
        }
        $navigation = isset($settings['navigation']) ? $settings['navigation'] : 'both';
        $dots   = in_array($navigation, array('dots', 'both'), true);
        $arrows = in_array($navigation, array('arrows', 'both'), true);
        ?>
        <div class="elementor-slides-wrapper elementor-main-swiper swiper" role="region" aria-roledescription="carousel" aria-label="<?php echo esc_attr(isset($settings['slides_name']) ? $settings['slides_name'] : 'Slides'); ?>" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>" data-animation="<?php echo esc_attr(isset($settings['content_animation']) ? $settings['content_animation'] : ''); ?>">
            <div class="swiper-wrapper elementor-slides">
                <?php echo implode('', $slides); // phpcs:ignore WordPress.Security.EscapeOutput -- built and escaped above ?>
            </div>
            <?php if (count($slides) > 1) : ?>
                <?php if ($dots) : ?>
                    <div class="swiper-pagination"></div>
                <?php endif; ?>
                <?php if ($arrows) : ?>
                    <div class="elementor-swiper-button elementor-swiper-button-prev" role="button" tabindex="0">
                        <?php $this->render_swiper_button('previous'); ?>
                    </div>
                    <div class="elementor-swiper-button elementor-swiper-button-next" role="button" tabindex="0">
                        <?php $this->render_swiper_button('next'); ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    // The chevron Elementor Pro used: eicon-chevron-left/right, which Elementor
    // prints as an inline SVG (e-font-icon-svg) or an <i>, as it is set up.
    private function render_swiper_button($type) {
        $side = ('next' === $type) !== is_rtl() ? 'right' : 'left';
        Icons_Manager::render_icon(array('library' => 'eicons', 'value' => 'eicon-chevron-' . $side), array('aria-hidden' => 'true'));
    }
}
