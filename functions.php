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

// 9. Subcategory circles fix
add_action('wp_footer', function() { ?>
<style id="artframer-subcat-override">
ul.postero-scroll-content,
.postero-scroll-content {
    display: flex !important;
    flex-direction: row !important;
    flex-wrap: nowrap !important;
    overflow-x: auto !important;
    overflow-y: visible !important;
    gap: 12px !important;
    width: 100% !important;
    height: auto !important;
    max-height: none !important;
    padding: 4px 0 8px !important;
    margin: 0 !important;
    list-style: none !important;
    scrollbar-width: none !important;
    -ms-overflow-style: none !important;
    transform: none !important;
    transition: none !important;
    position: static !important;
    left: auto !important;
    top: auto !important;
}
ul.postero-scroll-content::-webkit-scrollbar,
.postero-scroll-content::-webkit-scrollbar { display: none !important; }
ul.postero-scroll-content > li,
ul.postero-scroll-content > li.cat-item,
.postero-scroll-content > li,
.postero-scroll-content > li.cat-item {
    display: flex !important;
    flex-direction: column !important;
    align-items: center !important;
    flex: 0 0 auto !important;
    width: auto !important;
    min-width: 80px !important;
    float: none !important;
    margin: 0 !important;
    padding: 0 !important;
    position: static !important;
    left: auto !important;
    top: auto !important;
    transform: none !important;
}
div.list-wrapper.postero-scroll,
.postero-scroll,
.subcategory-section,
.widget_product_categories .list-wrapper {
    overflow: visible !important;
    height: auto !important;
    max-height: none !important;
    transform: none !important;
    position: static !important;
}
</style>
<script>
(function() {
    var _fixed = false;

    function deepLog(el, label) {
        var cs = getComputedStyle(el);
        var r = el.getBoundingClientRect();
        console.warn('[ArtFramer] ' + label,
            '| tag:', el.tagName,
            '| id:', el.id || '-',
            '| class:', el.className,
            '| children:', el.children.length,
            '| rect:', Math.round(r.width) + 'x' + Math.round(r.height),
            '| display:', cs.display,
            '| flex-wrap:', cs.flexWrap,
            '| overflow-x:', cs.overflowX,
            '| height:', cs.height,
            '| position:', cs.position,
            '| transform:', cs.transform
        );
    }

    function fixEl(el) {
        el.style.setProperty('display',         'flex',    'important');
        el.style.setProperty('flex-direction',  'row',     'important');
        el.style.setProperty('flex-wrap',       'nowrap',  'important');
        el.style.setProperty('overflow-x',      'auto',    'important');
        el.style.setProperty('height',          'auto',    'important');
        el.style.setProperty('max-height',      'none',    'important');
        el.style.setProperty('width',           '100%',    'important');
        el.style.setProperty('position',        'static',  'important');
        el.style.setProperty('transform',       'none',    'important');
        el.style.setProperty('top',             'auto',    'important');
        el.style.setProperty('left',            'auto',    'important');
        Array.from(el.children).forEach(function(li) {
            li.style.setProperty('flex',        '0 0 auto', 'important');
            li.style.setProperty('position',    'static',   'important');
            li.style.setProperty('width',       'auto',     'important');
            li.style.setProperty('display',     'flex',     'important');
            li.style.setProperty('transform',   'none',     'important');
            li.style.setProperty('top',         'auto',     'important');
            li.style.setProperty('left',        'auto',     'important');
        });
        if (el.parentElement) {
            el.parentElement.style.setProperty('overflow',   'visible', 'important');
            el.parentElement.style.setProperty('height',     'auto',    'important');
            el.parentElement.style.setProperty('max-height', 'none',    'important');
            el.parentElement.style.setProperty('transform',  'none',    'important');
        }
        if (el.parentElement && el.parentElement.parentElement) {
            el.parentElement.parentElement.style.setProperty('overflow',   'visible', 'important');
            el.parentElement.parentElement.style.setProperty('height',     'auto',    'important');
            el.parentElement.parentElement.style.setProperty('transform',  'none',    'important');
        }
    }

    function findAndFix() {
        // Strategy 1: find by cat-item parent
        var catItems = document.querySelectorAll('li.cat-item');
        if (catItems.length) {
            var parent = catItems[0].parentElement;
            deepLog(parent, 'cat-item PARENT');
            deepLog(parent.parentElement, 'cat-item GRANDPARENT');
            if (parent.parentElement) deepLog(parent.parentElement.parentElement || parent.parentElement, 'cat-item GREAT-GRANDPARENT');
            fixEl(parent);
            _fixed = true;
        }

        // Strategy 2: by class selector
        var ul = document.querySelector('ul.postero-scroll-content, .postero-scroll-content');
        if (ul) {
            deepLog(ul, 'postero-scroll-content');
            fixEl(ul);
        }

        // Log ALL elements with "scroll" or "subcat" in class
        document.querySelectorAll('[class*="scroll"], [class*="subcat"], [class*="sub-cat"]').forEach(function(el, i) {
            if (i < 10) deepLog(el, 'scroll/subcat[' + i + ']');
        });
    }

    function init() {
        findAndFix();

        // Re-run after any DOM mutation (catches slider JS re-positioning items)
        var mo = new MutationObserver(function() {
            findAndFix();
        });
        mo.observe(document.body, { attributes: true, subtree: true, attributeFilter: ['style', 'class'] });

        // Belt-and-suspenders: poll for 5 seconds after load
        var ticks = 0;
        var iv = setInterval(function() {
            findAndFix();
            ticks++;
            if (ticks >= 10) clearInterval(iv);
        }, 500);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    window.addEventListener('load', function() { setTimeout(findAndFix, 100); });
}());
</script>
<?php }, 9999);
