<?php
/**
 * Postero Child Theme - functions.php
 * The Art Framer - theartframer.us
 */

// 1. Enqueue parent and child theme styles
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('postero-parent', get_template_directory_uri() . '/style.css');
    wp_enqueue_style('postero-child', get_stylesheet_uri(), array('postero-parent'), '1.0.0');
    wp_enqueue_style('postero-child-custom', get_stylesheet_directory_uri() . '/assets/css/custom.css', array('postero-child'), '1.2.9');
    wp_enqueue_script('postero-child-custom-js', get_stylesheet_directory_uri() . '/assets/js/custom.js', array('jquery'), '1.0.9', true);
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
/* Target the actual subcategory slider container */
#subcategorySlider,
.subcategory-slider {
    display: flex !important;
    flex-direction: row !important;
    flex-wrap: nowrap !important;
    overflow-x: auto !important;
    overflow-y: visible !important;
    gap: 16px !important;
    width: 100% !important;
    height: auto !important;
    max-height: none !important;
    padding: 4px 0 8px !important;
    scrollbar-width: none !important;
    -ms-overflow-style: none !important;
    grid-template-columns: unset !important;
    grid-template-rows: unset !important;
}
#subcategorySlider::-webkit-scrollbar,
.subcategory-slider::-webkit-scrollbar { display: none !important; }
#subcategorySlider > *,
.subcategory-slider > * {
    flex: 0 0 auto !important;
    width: auto !important;
    display: flex !important;
    flex-direction: column !important;
    align-items: center !important;
    position: static !important;
    transform: none !important;
}
#subcategoryContainer,
.subcategory-container {
    overflow: visible !important;
    height: auto !important;
    max-height: none !important;
    width: 100% !important;
}
/* Also fix the UL-based version */
ul.postero-scroll-content {
    display: flex !important;
    flex-direction: row !important;
    flex-wrap: nowrap !important;
    overflow-x: auto !important;
    height: auto !important;
    max-height: none !important;
    transform: none !important;
    scrollbar-width: none !important;
}
ul.postero-scroll-content > li.cat-item {
    flex: 0 0 auto !important;
    position: static !important;
    transform: none !important;
    width: auto !important;
}
div.list-wrapper.postero-scroll {
    overflow: visible !important;
    height: auto !important;
    max-height: none !important;
}
</style>
<script>
(function() {
    var _busy = false;

    function fixEl(el) {
        if (!el) return;
        _busy = true;
        el.style.setProperty('display',               'flex',    'important');
        el.style.setProperty('flex-direction',        'row',     'important');
        el.style.setProperty('flex-wrap',             'nowrap',  'important');
        el.style.setProperty('overflow-x',            'auto',    'important');
        el.style.setProperty('height',                'auto',    'important');
        el.style.setProperty('max-height',            'none',    'important');
        el.style.setProperty('width',                 '100%',    'important');
        el.style.setProperty('grid-template-columns', 'unset',   'important');
        el.style.setProperty('grid-template-rows',    'unset',   'important');
        Array.from(el.children).forEach(function(child) {
            child.style.setProperty('flex',      '0 0 auto', 'important');
            child.style.setProperty('position',  'static',   'important');
            child.style.setProperty('width',     'auto',     'important');
            child.style.setProperty('transform', 'none',     'important');
        });
        if (el.parentElement) {
            el.parentElement.style.setProperty('overflow',   'visible', 'important');
            el.parentElement.style.setProperty('height',     'auto',    'important');
            el.parentElement.style.setProperty('max-height', 'none',    'important');
        }
        setTimeout(function() { _busy = false; }, 50);
    }

    function init() {
        // Primary target: #subcategorySlider (confirmed display:grid, the actual circles container)
        var slider = document.getElementById('subcategorySlider');
        if (slider) fixEl(slider);

        // Fallback: UL with cat-items
        var catItems = document.querySelectorAll('li.cat-item');
        if (catItems.length && catItems[0].parentElement) {
            fixEl(catItems[0].parentElement);
        }

        // MutationObserver — skip re-entry while we are mid-fix
        var mo = new MutationObserver(function(mutations) {
            if (_busy) return;
            var relevant = mutations.some(function(m) {
                return m.target === slider || (slider && slider.contains(m.target));
            });
            if (relevant) fixEl(slider);
        });
        if (slider) {
            mo.observe(slider, { attributes: true, attributeFilter: ['style', 'class'] });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    window.addEventListener('load', function() {
        init();
        setTimeout(init, 300);
        setTimeout(init, 1000);
    });
}());
</script>
<?php }, 9999);


// 10. Product card slider
add_action('wp_footer', function() { ?>
<style>
.product-container {
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
    width: 100% !important;
}
.product-container .prod-nav {
    flex-shrink: 0 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 44px !important;
    height: 44px !important;
    min-width: 44px !important;
    border-radius: 50% !important;
    background: #c9a84c !important;
    border: none !important;
    color: #fff !important;
    font-size: 28px !important;
    line-height: 1 !important;
    cursor: pointer !important;
    padding: 0 !important;
    box-shadow: 0 2px 8px rgba(0,0,0,.22) !important;
}
.product-container .prod-nav:hover { background: #a8872e !important; }
.product-container #productGrid,
.product-container .product-slider {
    flex: 1 1 auto !important;
    min-width: 0 !important;
    overflow: hidden !important;
    display: flex !important;
    flex-direction: row !important;
    flex-wrap: nowrap !important;
    gap: 16px !important;
    padding: 4px 0 12px !important;
    scroll-behavior: smooth !important;
}
</style>
<script>
(function() {
    function sp(el, p, v) { el.style.setProperty(p, v, 'important'); }

    function run() {
        var container = document.querySelector('.product-container');
        if (!container) return;
        // Reset flag so re-runs always apply layout
        delete container.dataset.sliderReady;
        if (container.dataset.sliderReady) return;

        var grid = container.querySelector('#productGrid') || container.querySelector('.product-slider');
        if (!grid) return;
        var cards = grid.querySelectorAll('.product-card');
        if (!cards.length) return;
        container.dataset.sliderReady = '1';

        var prevBtn = container.querySelector('.prev-prod');
        var nextBtn = container.querySelector('.next-prod');
        var GAP = 16;

        function visCount() {
            return window.innerWidth <= 576 ? 1 : window.innerWidth <= 991 ? 2 : 4;
        }

        function applyAll() {
            var vis = visCount();

            // Force container as flex row
            sp(container, 'display', 'flex');
            sp(container, 'align-items', 'center');
            sp(container, 'gap', GAP + 'px');
            sp(container, 'width', '100%');

            // Force grid as flex overflow-hidden viewport
            sp(grid, 'display', 'flex');
            sp(grid, 'flex-direction', 'row');
            sp(grid, 'flex-wrap', 'nowrap');
            sp(grid, 'overflow', 'hidden');
            sp(grid, 'flex', '1 1 auto');
            sp(grid, 'min-width', '0');
            sp(grid, 'gap', GAP + 'px');
            sp(grid, 'padding', '4px 0 12px');
            sp(grid, 'scroll-behavior', 'smooth');

            // Style buttons
            [prevBtn, nextBtn].forEach(function(btn) {
                if (!btn) return;
                sp(btn, 'display', 'flex');
                sp(btn, 'align-items', 'center');
                sp(btn, 'justify-content', 'center');
                sp(btn, 'flex-shrink', '0');
                sp(btn, 'width', '44px');
                sp(btn, 'height', '44px');
                sp(btn, 'min-width', '44px');
                sp(btn, 'border-radius', '50%');
                sp(btn, 'background', '#c9a84c');
                sp(btn, 'border', 'none');
                sp(btn, 'color', '#fff');
                sp(btn, 'font-size', '28px');
                sp(btn, 'cursor', 'pointer');
                sp(btn, 'padding', '0');
                sp(btn, 'box-shadow', '0 2px 8px rgba(0,0,0,0.22)');
            });
            if (prevBtn) prevBtn.innerHTML = '&#8249;';
            if (nextBtn) nextBtn.innerHTML = '&#8250;';

            // Compute card width from window size (most reliable)
            var totalW = window.innerWidth;
            // Subtract page padding (approx 60px each side) + 2 buttons (44px each) + gaps
            var availW = totalW - 120 - 88 - (GAP * 3);
            var cw = Math.floor((availW - GAP * (vis - 1)) / vis);
            if (cw < 100) cw = Math.floor((totalW * 0.8) / vis);

            grid.querySelectorAll('.product-card').forEach(function(c) {
                sp(c, 'flex',      '0 0 ' + cw + 'px');
                sp(c, 'width',     cw + 'px');
                sp(c, 'min-width', cw + 'px');
                sp(c, 'float',     'none');
                sp(c, 'margin',    '0');
            });
            return cw;
        }

        var cw = applyAll();
        requestAnimationFrame(applyAll);

        if (prevBtn) prevBtn.addEventListener('click', function() {
            grid.scrollLeft -= (applyAll() + GAP) * visCount();
        });
        if (nextBtn) nextBtn.addEventListener('click', function() {
            grid.scrollLeft += (applyAll() + GAP) * visCount();
        });
        window.addEventListener('resize', function() { grid.scrollLeft = 0; applyAll(); });
    }

    window.addEventListener('load', function() { run(); setTimeout(run, 600); });
}());
</script>
<?php }, 10000);
