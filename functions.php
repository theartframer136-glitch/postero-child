<?php
/**
 * Postero Child Theme - functions.php
 * The Art Framer - theartframer.us
 */

// 1. Enqueue parent and child theme styles
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('postero-parent', get_template_directory_uri() . '/style.css');
    wp_enqueue_style('postero-child', get_stylesheet_uri(), array('postero-parent'), '1.0.0');
    wp_enqueue_style('postero-child-custom', get_stylesheet_directory_uri() . '/assets/css/custom.css', array('postero-child'), '1.0.3');
    wp_enqueue_script('postero-child-custom-js', get_stylesheet_directory_uri() . '/assets/js/custom.js', array('jquery'), '1.0.4', true);
}, 20);

// 2. Force USD as default currency
add_filter('woocommerce_currency', function() { return 'USD'; }, 999);
add_filter('woocommerce_currency_symbol', function($symbol, $currency) { return '$'; }, 999, 2);

// 3. Enable WooCommerce registration
add_action('init', function() {
    update_option('woocommerce_enable_myaccount_registration', 'yes');
    update_option('woocommerce_enable_checkout_login_reminder', 'yes');
    update_option('woocommerce_registration_generate_password', 'yes');
    update_option('woocommerce_registration_generate_username', 'no');
});

// 4. Fix Login and Register page URLs
add_filter('login_url', function() { return home_url('/my-account/'); });
add_filter('register_url', function() { return home_url('/my-account/?action=register'); });
add_filter('logout_url', function() { return home_url('/my-account/'); });

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
    }
});

// 7. Fix price filter button
add_action('wp_footer', function() {
    if (is_shop() || is_product_category()) { ?>
    <script>
    jQuery(document).ready(function($) {
        $(document).on('click', '.price_slider_wrapper .button, .widget_price_filter .button', function(e) {
            e.preventDefault();
            var form = $(this).closest('form');
            if (form.length) { form.submit(); }
            else {
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

// 8. Hide Themes widget from sidebar only
add_action('wp_head', function() {
    echo '<style>
    .widget_postero_product_themes { display: none !important; }
    </style>';
}, 999);

// 9. Inject subcategory fix CSS in wp_footer — runs AFTER Elementor body CSS
add_action('wp_footer', function() { ?>
<style id="artframer-subcat-fix">
ul.postero-scroll-content {
    display: flex !important;
    flex-direction: row !important;
    flex-wrap: nowrap !important;
    overflow-x: auto !important;
    overflow-y: visible !important;
    gap: 16px !important;
    width: 100% !important;
    max-width: 100% !important;
    padding: 0 0 8px 0 !important;
    margin: 0 !important;
    list-style: none !important;
    scrollbar-width: none !important;
    transform: none !important;
    transition: none !important;
    position: relative !important;
    height: auto !important;
    min-height: 0 !important;
}
ul.postero-scroll-content::-webkit-scrollbar { display: none !important; }
ul.postero-scroll-content > li.cat-item {
    display: flex !important;
    flex-direction: column !important;
    align-items: center !important;
    text-align: center !important;
    flex: 0 0 auto !important;
    width: auto !important;
    min-width: 100px !important;
    float: none !important;
    margin: 0 !important;
    padding: 0 !important;
    cursor: pointer !important;
    position: static !important;
    left: auto !important;
    top: auto !important;
    transform: none !important;
}
ul.postero-scroll-content .cat-item img {
    width: 90px !important;
    height: 90px !important;
    border-radius: 50% !important;
    object-fit: cover !important;
    border: 3px solid #c9a84c !important;
    margin: 0 auto 8px !important;
    display: block !important;
}
ul.postero-scroll-content .cat-item span,
ul.postero-scroll-content .cat-item a {
    font-size: 12px !important;
    font-weight: 600 !important;
    color: #333 !important;
    white-space: nowrap !important;
    display: block !important;
}
</style>
<script>
// Force subcategory row — runs after all Elementor JS settles
(function() {
    function applyFix() {
        var ul = document.querySelector('ul.postero-scroll-content');
        if (!ul || ul.dataset.artframerFixed) return;

        var items = ul.querySelectorAll('li.cat-item');
        if (items.length < 3) return; // not loaded yet

        // Clone the UL — this strips ALL slider event listeners
        var clone = ul.cloneNode(true);
        clone.dataset.artframerFixed = '1';

        // Keep only first 21 unique items, remove tripled clones
        var cloneItems = clone.querySelectorAll('li.cat-item');
        for (var i = 0; i < cloneItems.length; i++) {
            if (i >= 21) {
                clone.removeChild(cloneItems[i]);
            } else {
                // Strip any inline positioning the slider set
                var li = cloneItems[i];
                li.removeAttribute('style');
                li.style.setProperty('display',        'flex',     'important');
                li.style.setProperty('flex-direction', 'column',   'important');
                li.style.setProperty('align-items',    'center',   'important');
                li.style.setProperty('flex',           '0 0 auto', 'important');
                li.style.setProperty('position',       'static',   'important');
                li.style.setProperty('width',          'auto',     'important');
                li.style.setProperty('min-width',      '100px',    'important');
                li.style.setProperty('float',          'none',     'important');
                li.style.setProperty('margin',         '0',        'important');
                li.style.setProperty('transform',      'none',     'important');
            }
        }

        // Apply flex row to the clone
        clone.removeAttribute('style');
        clone.style.setProperty('display',         'flex',    'important');
        clone.style.setProperty('flex-direction',  'row',     'important');
        clone.style.setProperty('flex-wrap',       'nowrap',  'important');
        clone.style.setProperty('overflow-x',      'auto',    'important');
        clone.style.setProperty('overflow-y',      'visible', 'important');
        clone.style.setProperty('gap',             '16px',    'important');
        clone.style.setProperty('width',           '100%',    'important');
        clone.style.setProperty('height',          'auto',    'important');
        clone.style.setProperty('position',        'static',  'important');
        clone.style.setProperty('transform',       'none',    'important');
        clone.style.setProperty('scrollbar-width', 'none',    'important');

        // Swap the original with our clean clone
        ul.parentNode.replaceChild(clone, ul);

        // Also fix the wrapper's overflow
        var parent = clone.parentElement;
        if (parent) {
            parent.style.setProperty('overflow',   'visible', 'important');
            parent.style.setProperty('height',     'auto',    'important');
            parent.style.setProperty('max-height', 'none',    'important');
        }

        console.warn('Subcat carousel replaced with clean flex row — 21 items.');
    }

    // Apply repeatedly to beat any delayed JS that resets positions
    [0, 200, 500, 1000, 2000, 3500].forEach(function(ms) {
        setTimeout(applyFix, ms);
    });

    // Also intercept the parent theme slider interval if it runs
    window.addEventListener('load', function() {
        setTimeout(applyFix, 100);
        setTimeout(applyFix, 500);
        var count = 0;
        var iv = setInterval(function() {
            applyFix();
            if (++count >= 10) clearInterval(iv);
        }, 300);
    });
}());
</script>
<?php }, 9999);
