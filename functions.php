<?php
/**
 * Postero Child Theme - functions.php
 * The Art Framer - theartframer.us
 */

// 1. Enqueue parent and child theme styles
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('postero-parent', get_template_directory_uri() . '/style.css');
    wp_enqueue_style('postero-child', get_stylesheet_uri(), array('postero-parent'), '1.0.0');
    wp_enqueue_style('postero-child-custom', get_stylesheet_directory_uri() . '/assets/css/custom.css', array('postero-child'), '1.0.4');
    wp_enqueue_script('postero-child-custom-js', get_stylesheet_directory_uri() . '/assets/js/custom.js', array('jquery'), '1.0.5', true);
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

// 9. Subcategory circles fix — CSS + MutationObserver to override any inline styles
add_action('wp_footer', function() { ?>
<style id="artframer-subcat-override">
ul.postero-scroll-content {
    display: flex !important;
    flex-direction: row !important;
    flex-wrap: nowrap !important;
    overflow-x: auto !important;
    overflow-y: visible !important;
    gap: 12px !important;
    width: 100% !important;
    height: auto !important;
    padding: 4px 0 8px !important;
    margin: 0 !important;
    list-style: none !important;
    scrollbar-width: none !important;
    -ms-overflow-style: none !important;
}
ul.postero-scroll-content::-webkit-scrollbar { display: none !important; }
ul.postero-scroll-content > li,
ul.postero-scroll-content > li.cat-item {
    display: flex !important;
    flex-direction: column !important;
    align-items: center !important;
    flex: 0 0 auto !important;
    width: auto !important;
    min-width: 90px !important;
    float: none !important;
    margin: 0 !important;
    padding: 0 !important;
    position: static !important;
}
div.list-wrapper.postero-scroll,
.postero-scroll {
    overflow: visible !important;
    height: auto !important;
    max-height: none !important;
}
</style>
<script>
(function() {
    function enforceSubcatLayout(ul) {
        // Force UL layout via setProperty (overrides inline styles)
        ul.style.setProperty('display',         'flex',    'important');
        ul.style.setProperty('flex-direction',  'row',     'important');
        ul.style.setProperty('flex-wrap',       'nowrap',  'important');
        ul.style.setProperty('overflow-x',      'auto',    'important');
        ul.style.setProperty('height',          'auto',    'important');
        ul.style.setProperty('width',           '100%',    'important');
        // Force items
        ul.querySelectorAll('li').forEach(function(li) {
            li.style.setProperty('flex',      '0 0 auto', 'important');
            li.style.setProperty('position',  'static',   'important');
            li.style.setProperty('width',     'auto',     'important');
            li.style.setProperty('display',   'flex',     'important');
        });
        // Force parent overflow
        if (ul.parentElement) {
            ul.parentElement.style.setProperty('overflow',   'visible', 'important');
            ul.parentElement.style.setProperty('height',     'auto',    'important');
        }
        console.warn('enforceSubcatLayout applied | UL computed display:', getComputedStyle(ul).display, 'flex-wrap:', getComputedStyle(ul).flexWrap, 'overflow-x:', getComputedStyle(ul).overflowX, 'height:', getComputedStyle(ul).height);
    }

    function init() {
        // Target ALL elements with postero-scroll-content class (any tag)
        var all = document.querySelectorAll('[class*="postero-scroll-content"], [class*="subcategor"], [class*="sub-cat-list"], [class*="scroll-content"]');
        console.warn('Total scroll-content elements found:', all.length);
        all.forEach(function(el, i) {
            var rect = el.getBoundingClientRect();
            console.warn('Element', i, '| tag:', el.tagName, '| class:', el.className,
                '| children:', el.children.length,
                '| visible:', rect.width > 0 && rect.height > 0,
                '| rect w:', Math.round(rect.width), 'h:', Math.round(rect.height),
                '| computed display:', getComputedStyle(el).display,
                '| flex-wrap:', getComputedStyle(el).flexWrap);
        });

        // Also find the actual visible circles container by looking for li.cat-item parents
        var catItems = document.querySelectorAll('li.cat-item, .cat-item');
        if (catItems.length) {
            var parent = catItems[0].parentElement;
            console.warn('cat-item parent | tag:', parent.tagName, '| class:', parent.className,
                '| computed display:', getComputedStyle(parent).display,
                '| flex-wrap:', getComputedStyle(parent).flexWrap,
                '| overflow-x:', getComputedStyle(parent).overflowX,
                '| width:', getComputedStyle(parent).width);
            // Apply fix to the ACTUAL parent of cat-items
            parent.style.setProperty('display',        'flex',    'important');
            parent.style.setProperty('flex-direction', 'row',     'important');
            parent.style.setProperty('flex-wrap',      'nowrap',  'important');
            parent.style.setProperty('overflow-x',     'auto',    'important');
            parent.style.setProperty('height',         'auto',    'important');
            parent.style.setProperty('width',          '100%',    'important');
            catItems.forEach(function(li) {
                li.style.setProperty('flex',      '0 0 auto', 'important');
                li.style.setProperty('position',  'static',   'important');
                li.style.setProperty('width',     'auto',     'important');
                li.style.setProperty('display',   'flex',     'important');
            });
            if (parent.parentElement) {
                parent.parentElement.style.setProperty('overflow', 'visible', 'important');
                parent.parentElement.style.setProperty('height',   'auto',    'important');
            }
        }

        var ul = document.querySelector('ul.postero-scroll-content');
        if (!ul) return;
        enforceSubcatLayout(ul);

        // Watch for ANY attribute change on the UL and re-enforce
        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(m) {
                if (m.attributeName === 'style' || m.attributeName === 'class') {
                    enforceSubcatLayout(ul);
                }
            });
        });
        observer.observe(ul, { attributes: true });

        // Also watch children
        var childObserver = new MutationObserver(function() {
            enforceSubcatLayout(ul);
        });
        childObserver.observe(ul, { childList: true, subtree: false });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    window.addEventListener('load', init);
    setTimeout(init, 500);
    setTimeout(init, 1500);
}());
</script>
<?php }, 9999);
