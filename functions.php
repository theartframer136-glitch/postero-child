<?php
/**
 * Postero Child Theme - functions.php
 * The Art Framer - theartframer.us
 */

// 1. Enqueue parent and child theme styles
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('postero-parent', get_template_directory_uri() . '/style.css');
    wp_enqueue_style('postero-child', get_stylesheet_uri(), array('postero-parent'), '1.0.0');
});

// 2. Force USD as default currency
add_filter('woocommerce_currency', function() {
    return 'USD';
});
add_filter('woocommerce_currency_symbol', function($symbol, $currency) {
    if ($currency === 'USD') return '$';
    return $symbol;
}, 10, 2);

// 3. Enable WooCommerce registration on my-account page
add_action('init', function() {
    update_option('woocommerce_enable_myaccount_registration', 'yes');
    update_option('woocommerce_enable_checkout_login_reminder', 'yes');
    update_option('woocommerce_registration_generate_password', 'yes');
    update_option('woocommerce_registration_generate_username', 'no');
});

// 4. Fix Login and Register page URLs
add_filter('login_url', function() {
    return home_url('/my-account/');
});
add_filter('register_url', function() {
    return home_url('/my-account/?action=register');
});
add_filter('logout_url', function() {
    return home_url('/my-account/');
});

// 5. Fix footer demo links
add_filter('wp_nav_menu_items', function($items, $args) {
    $items = str_replace('demo2wpopal.b-cdn.net/postero', 'theartframer.us', $items);
    $items = str_replace('chocolate-chicken-365829.hostingersite.com', 'theartframer.us', $items);
    return $items;
}, 10, 2);

// 6. Enqueue WooCommerce price slider scripts
add_action('wp_enqueue_scripts', function() {
    if (is_shop() || is_product_category() || is_product_tag()) {
        wp_enqueue_script('wc-price-slider');
        wp_enqueue_style('woocommerce-layout');
        wp_enqueue_style('woocommerce-smallscreen');
        wp_enqueue_style('woocommerce-general');
    }
});

// 7. Fix price filter FILTER button submission
add_action('wp_footer', function() {
    if (is_shop() || is_product_category()) { ?>
    <script>
    jQuery(document).ready(function($) {
        $(document).on('click', '.price_slider_wrapper .button, .widget_price_filter .button', function(e) {
            e.preventDefault();
            var form = $(this).closest('form');
            if (form.length) {
                form.submit();
            } else {
                var url = new URL(window.location.href);
                var minPrice = $('.price_slider_amount #min_price').val();
                var maxPrice = $('.price_slider_amount #max_price').val();
                if (minPrice) url.searchParams.set('min_price', minPrice);
                if (maxPrice) url.searchParams.set('max_price', maxPrice);
                window.location.href = url.toString();
            }
        });
    });
    </script>
    <?php }
});

// 8. Hide Themes widget from sidebar
add_action('wp_head', function() {
    echo '<style>
    .widget_postero_product_themes,
    .postero-product-themes,
    aside .widget:has(a[href*="product-themes"]) {
        display: none !important;
    }
    </style>';
}, 999);


// Enqueue custom CSS
add_action('wp_enqueue_scripts', function() {
  wp_enqueue_style('postero-child-custom', get_stylesheet_directory_uri() . '/assets/css/custom.css', array(), '1.0.0');
}, 20);

// Override parent theme currency - force USD on all hooks
remove_all_filters('woocommerce_currency');
remove_all_filters('woocommerce_currency_symbol');
add_filter('woocommerce_currency', function() { return 'USD'; }, 999);
add_filter('woocommerce_currency_symbol', function($symbol, $currency) {
    return '$';
}, 999, 2);

// Fix Shop by Collection - category image grid
add_action('wp_head', function() {
    echo '<style>
    /* Category image grid fix */
    .elementor-widget-postero_product_categories .postero-cats-wrap,
    .postero-cats-wrap {
        display: grid !important;
        grid-template-columns: repeat(5, 1fr) !important;
        gap: 12px !important;
        width: 100% !important;
    }
    .postero-cats-wrap .cat-item,
    .elementor-widget-postero_product_categories .cat-item {
        width: 100% !important;
        float: none !important;
        margin: 0 !important;
    }
    .postero-cats-wrap .slick-list,
    .postero-cats-wrap .slick-track {
        display: grid !important;
        grid-template-columns: repeat(5, 1fr) !important;
        width: 100% !important;
        transform: none !important;
    }
    .postero-cats-wrap .slick-slide {
        float: none !important;
        width: auto !important;
    }
    @media (max-width: 768px) {
        .postero-cats-wrap,
        .elementor-widget-postero_product_categories .postero-cats-wrap {
            grid-template-columns: repeat(3, 1fr) !important;
        }
    }
    </style>';
}, 999);
