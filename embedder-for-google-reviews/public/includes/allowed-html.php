<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

global $allowed_html;

$allowed_html = [
    'img' => [
        'title'             => [],
        'src'               => [],
        'alt'               => [],
        'width'             => [],
        'height'            => [],
        'class'             => [],
        'data-imgtype'      => [],
        'referrerpolicy'    => [],
    ],
    'style'                     => [],
    'div'                        => [
        'class'                  => [],
        'id'                     => [],
        'data-swiper-autoplay'   => [],
        'data-grwp-show-more-rows' => [],
        'data-grwp-load-more-rows' => [],
        'data-grwp-marquee'      => [],
    ],
    'a' => [
        'href'      => [],
        'target'    => [],
	    'class'     => [],
	    'rel'       => [],
	    'aria-label' => [],
    ],
    'p' => [
        'id'    => [],
        'class' => [],
        'style' => [],
    ],
    'span' => [
        'class' => [],
        'id'    => [],
    ],
    'strong' => [],
    'h4' => [],
	'h3' => [
		'class' => []
	],
    'br' => [],
    'iframe' => [
        'src'       => [],
        'width'     => [],
        'height'    => [],
        'style'     => [],
        'allow'     => [],
        'id'     => []

    ],
    'input' => [
        'class'     => [],
        'id'        => [],
        'type'      => [],
        'name'      => [],
        'checked'   => [],
        'value'     => [],
        'min'       => [],
        'max'       => [],
        'step'      => [],
	    'disabled'  => [],
	    'title'     => [],
        'readonly'  => [],
        'placeholder' => [],
    ],
    'textarea' => [
        'id'        => [],
        'name'      => [],
        'value'     => [],
        'rows'      => [],
        'cols'      => [],
	    'disabled'  => []
    ],
    'fieldset' => [
        'class' => [],
    ],
    'button' => [
        'class' => [],
        'type'  => [],
    ],
];
