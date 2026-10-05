<?php
/**
 * The WooCommerce Breadcrumbs widget, as Elementor Pro drew it, with free
 * parts (see inc/ports/elementor-pro.php). Same name ("woocommerce-breadcrumb")
 * and the same output, read from the live shop and category pages on 5 Oct
 * (tools/probe-elementor-pro-markup.mjs): WooCommerce's own breadcrumb,
 *
 *   <nav class="woocommerce-breadcrumb" aria-label="Breadcrumb"><a href="…">Home</a>&nbsp;/&nbsp;Shop</nav>
 *
 * with its colour, link colour, typography and alignment controls under
 * Elementor Pro's names and selectors, so the saved settings of the six
 * breadcrumb templates (#1853's grey text and links, the others' 20px bottom
 * margin) produce the same CSS.
 */
defined('ABSPATH') || exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

class AF_EPro_Woo_Breadcrumb extends Widget_Base {

    public function get_name() { return 'woocommerce-breadcrumb'; }
    public function get_title() { return 'WooCommerce Breadcrumbs'; }
    public function get_icon() { return 'eicon-product-breadcrumbs'; }
    public function get_categories() { return array('general'); }
    public function get_keywords() { return array('woocommerce', 'shop', 'store', 'breadcrumbs', 'internal links', 'product'); }

    public function has_widget_inner_wrapper(): bool {
        $e = \Elementor\Plugin::$instance;
        return !(isset($e->experiments) && $e->experiments->is_feature_active('e_optimized_markup'));
    }

    protected function register_controls() {
        $this->start_controls_section('section_product_rating_style', array('label' => 'Style', 'tab' => Controls_Manager::TAB_STYLE));
        $this->add_control('text_color', array(
            'label' => 'Text Color', 'type' => Controls_Manager::COLOR,
            'selectors' => array('{{WRAPPER}} .woocommerce-breadcrumb' => 'color: {{VALUE}}'),
        ));
        $this->add_control('link_color', array(
            'label' => 'Link Color', 'type' => Controls_Manager::COLOR,
            'selectors' => array('{{WRAPPER}} .woocommerce-breadcrumb > a' => 'color: {{VALUE}}'),
        ));
        if (class_exists('Elementor\Group_Control_Typography')) {
            $this->add_group_control(Group_Control_Typography::get_type(), array(
                'name' => 'text_typography', 'selector' => '{{WRAPPER}} .woocommerce-breadcrumb',
            ));
        }
        $this->add_responsive_control('alignment', array(
            'label' => 'Alignment', 'type' => Controls_Manager::CHOOSE,
            'options' => array(
                'left' => array('title' => 'Left', 'icon' => 'eicon-text-align-left'),
                'center' => array('title' => 'Center', 'icon' => 'eicon-text-align-center'),
                'right' => array('title' => 'Right', 'icon' => 'eicon-text-align-right'),
            ),
            'selectors' => array('{{WRAPPER}} .woocommerce-breadcrumb' => 'text-align: {{VALUE}}'),
        ));
        $this->end_controls_section();
    }

    protected function render() {
        woocommerce_breadcrumb();
    }

    public function render_plain_content() {}
}
