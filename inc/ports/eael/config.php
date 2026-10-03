<?php
/**
 * Essential Addons for Elementor 6.8.5, config.php, cut down to what this site
 * uses: the entries of the four widgets placed on it, copied unchanged and in
 * the plugin's order, and the Custom JS extension (its page-settings control
 * keeps any stored custom JS editable). Every other entry is left out, so
 * nothing tries to register a widget class or read an asset file the theme
 * does not carry.
 *
 * EAEL_PLUGIN_PATH points at assets/ports/eael/ (see inc/ports/essential-addons.php),
 * so the 'file' paths below resolve to the byte-for-byte copies there.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$config = [
    'elements' => [
        'adv-tabs' => [
            'class' => '\Essential_Addons_Elementor\Elements\Adv_Tabs',
            'dependency' => [
                'css' => [
                    [
                        'file' => EAEL_PLUGIN_PATH . 'assets/front-end/css/view/advanced-tabs.min.css',
                        'type' => 'self',
                        'context' => 'view',
                    ],
                ],
                'js' => [
                    [
                        'file' => EAEL_PLUGIN_PATH . 'assets/front-end/js/view/advanced-tabs.min.js',
                        'type' => 'self',
                        'context' => 'view',
                    ],
                ],
            ],
        ],
        'woo-add-to-cart' => [
            'class' => '\Essential_Addons_Elementor\Elements\Woo_Add_To_Cart',
            'dependency' => [
                'css' => [
                    [
                        'file' => EAEL_PLUGIN_PATH . 'assets/front-end/css/view/woo-add-to-cart.min.css',
                        'type' => 'self',
                        'context' => 'view',
                    ],
                ],
                'js' => [
                    [
                        'file' => EAEL_PLUGIN_PATH . 'assets/front-end/js/view/woo-add-to-cart.min.js',
                        'type' => 'self',
                        'context' => 'view',
                    ],
                ],
            ],
        ],
        'login-register' => [
            'class' => '\Essential_Addons_Elementor\Elements\Login_Register',
            'dependency' => [
                'css' => [
                    [
                        'file' => EAEL_PLUGIN_PATH . 'assets/front-end/css/view/login-register.min.css',
                        'type' => 'self',
                        'context' => 'view',
                    ],
                ],
                'js' => [
                    [
                        'file' => EAEL_PLUGIN_PATH . '/assets/front-end/js/lib-view/dom-purify/purify.min.js',
                        'type' => 'lib',
                        'context' => 'view',
                    ],
                    [
                        'file' => EAEL_PLUGIN_PATH . 'assets/front-end/js/view/login-register.min.js',
                        'type' => 'self',
                        'context' => 'view',
                    ],
                ],
            ],
        ],
        'woo-product-carousel' => [
            'class' => '\Essential_Addons_Elementor\Elements\Woo_Product_Carousel',
            'dependency' => [
                'css' => [
                    [
                        'file' => EAEL_PLUGIN_PATH . 'assets/front-end/css/view/quick-view.min.css',
                        'type' => 'self',
                        'context' => 'view',
                    ],
                    [
                        'file' => EAEL_PLUGIN_PATH . 'assets/front-end/css/view/woo-product-carousel.min.css',
                        'type' => 'self',
                        'context' => 'view',
                    ],
                ],
                'js'  => [
                    [
                        'file' => EAEL_PLUGIN_PATH . 'assets/front-end/js/view/quick-view.min.js',
                        'type' => 'self',
                        'context' => 'view',
                    ],
                    [
                        'file' => EAEL_PLUGIN_PATH . 'assets/front-end/js/view/woo-product-carousel.min.js',
                        'type' => 'self',
                        'context' => 'view',
                    ],
                ],
            ],
        ],
    ],
    'extensions' => [
        'custom-js' => [
            'class' => '\Essential_Addons_Elementor\Extensions\Custom_JS',
        ],
    ],
];

return $config;
