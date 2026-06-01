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
ul.artframer-subcat-row {
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
ul.artframer-subcat-row::-webkit-scrollbar { display: none !important; }
ul.artframer-subcat-row > li.cat-item {
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
ul.artframer-subcat-row .cat-item img {
    width: 90px !important;
    height: 90px !important;
    border-radius: 50% !important;
    object-fit: cover !important;
    border: 3px solid #c9a84c !important;
    margin: 0 auto 8px !important;
    display: block !important;
}
ul.artframer-subcat-row .cat-item span,
ul.artframer-subcat-row .cat-item a {
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

        var allItems = ul.querySelectorAll('li.cat-item');
        if (allItems.length < 3) return;

        // Build a brand-new UL with only the first 21 unique items
        var newUl = document.createElement('ul');
        newUl.dataset.artframerFixed = '1';
        // Give the new UL a different class so the parent slider JS ignores it
        newUl.className = 'artframer-subcat-row';

        var count = 0;
        for (var i = 0; i < allItems.length && count < 21; i++) {
            var liClone = allItems[i].cloneNode(true);
            // Strip all inline styles the slider applied
            liClone.removeAttribute('style');
            liClone.style.setProperty('display',        'flex',     'important');
            liClone.style.setProperty('flex-direction', 'column',   'important');
            liClone.style.setProperty('align-items',    'center',   'important');
            liClone.style.setProperty('flex',           '0 0 auto', 'important');
            liClone.style.setProperty('position',       'static',   'important');
            liClone.style.setProperty('width',          'auto',     'important');
            liClone.style.setProperty('min-width',      '100px',    'important');
            liClone.style.setProperty('float',          'none',     'important');
            liClone.style.setProperty('margin',         '0',        'important');
            liClone.style.setProperty('transform',      'none',     'important');
            liClone.style.setProperty('left',           'auto',     'important');
            liClone.style.setProperty('top',            'auto',     'important');
            newUl.appendChild(liClone);
            count++;
        }

        // Style the new UL as a single scrollable flex row
        newUl.style.setProperty('display',         'flex',    'important');
        newUl.style.setProperty('flex-direction',  'row',     'important');
        newUl.style.setProperty('flex-wrap',       'nowrap',  'important');
        newUl.style.setProperty('overflow-x',      'auto',    'important');
        newUl.style.setProperty('overflow-y',      'visible', 'important');
        newUl.style.setProperty('gap',             '16px',    'important');
        newUl.style.setProperty('width',           '100%',    'important');
        newUl.style.setProperty('height',          'auto',    'important');
        newUl.style.setProperty('position',        'static',  'important');
        newUl.style.setProperty('transform',       'none',    'important');
        newUl.style.setProperty('list-style',      'none',    'important');
        newUl.style.setProperty('padding',         '0 0 8px 0','important');
        newUl.style.setProperty('margin',          '0',       'important');
        newUl.style.setProperty('scrollbar-width', 'none',    'important');

        // Hide the old slider UL and insert our clean one before it
        ul.style.setProperty('display', 'none', 'important');
        ul.dataset.artframerFixed = '1'; // prevent re-entry
        ul.parentNode.insertBefore(newUl, ul);

        // Fix parent overflow
        var parent = newUl.parentElement;
        if (parent) {
            parent.style.setProperty('overflow',   'visible', 'important');
            parent.style.setProperty('height',     'auto',    'important');
            parent.style.setProperty('max-height', 'none',    'important');
        }

        console.warn('Subcat: new flex UL inserted with', count, 'items.');
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
