<?php
/**
 * Postero Child Theme - functions.php
 * The Art Framer - theartframer.us
 */

// 1. Enqueue parent and child theme styles
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('postero-parent', get_template_directory_uri() . '/style.css');
    wp_enqueue_style('postero-child', get_stylesheet_uri(), array('postero-parent'), '1.0.0');
    wp_enqueue_style('postero-child-custom', get_stylesheet_directory_uri() . '/assets/css/custom.css', array('postero-child'), '1.2.4');
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
<script>
(function() {
    function sp(el, prop, val) { el.style.setProperty(prop, val, 'important'); }

    function initSlider(container) {
        if (container.dataset.afSlider) return;
        var viewport = container.querySelector('#productGrid, .product-slider');
        if (!viewport) return;
        var cards = viewport.querySelectorAll('.product-card');
        if (!cards.length) return;
        container.dataset.afSlider = '1';

        // Create inner track div — viewport clips, track slides
        var innerTrack = document.createElement('div');
        innerTrack.className = 'af-inner-track';
        while (viewport.firstChild) { innerTrack.appendChild(viewport.firstChild); }
        viewport.appendChild(innerTrack);

        var prevBtn = container.querySelector('.prev-prod');
        var nextBtn = container.querySelector('.next-prod');
        var GAP = 16;
        var idx = 0;

        function visible() {
            var w = window.innerWidth;
            return w <= 576 ? 1 : w <= 991 ? 3 : 5;
        }

        function styleButtons() {
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
                sp(btn, 'line-height', '1');
                sp(btn, 'cursor', 'pointer');
                sp(btn, 'padding', '0');
                sp(btn, 'box-shadow', '0 2px 8px rgba(0,0,0,0.25)');
            });
            if (prevBtn) prevBtn.innerHTML = '&#8249;';
            if (nextBtn) nextBtn.innerHTML = '&#8250;';
        }

        function applyLayout() {
            var vis = visible();

            // Container: flex row
            sp(container, 'display', 'flex');
            sp(container, 'align-items', 'center');
            sp(container, 'gap', '8px');
            sp(container, 'width', '100%');

            // Viewport: overflow hidden, fills remaining width
            sp(viewport, 'overflow', 'hidden');
            sp(viewport, 'flex', '1 1 auto');
            sp(viewport, 'min-width', '0');
            sp(viewport, 'display', 'block');
            sp(viewport, 'padding', '4px 0 12px');

            // Measure viewport width, calculate card size
            var viewW = viewport.offsetWidth;
            var cw = Math.floor((viewW - GAP * (vis - 1)) / vis);

            // Inner track: flex row, no wrap — this is what slides
            sp(innerTrack, 'display', 'flex');
            sp(innerTrack, 'flex-direction', 'row');
            sp(innerTrack, 'flex-wrap', 'nowrap');
            sp(innerTrack, 'gap', GAP + 'px');
            sp(innerTrack, 'transition', 'transform 0.4s ease');
            sp(innerTrack, 'transform', 'translateX(' + -(idx * (cw + GAP)) + 'px)');

            // Each card: fixed width
            viewport.querySelectorAll('.product-card').forEach(function(card) {
                sp(card, 'flex', '0 0 ' + cw + 'px');
                sp(card, 'width', cw + 'px');
                sp(card, 'min-width', cw + 'px');
                sp(card, 'float', 'none');
                sp(card, 'margin', '0');
            });

            styleButtons();
        }

        function go(n) {
            var allCards = viewport.querySelectorAll('.product-card');
            var vis = visible();
            var viewW = viewport.offsetWidth;
            var cw = Math.floor((viewW - GAP * (vis - 1)) / vis);
            idx = Math.max(0, Math.min(n, allCards.length - vis));
            sp(innerTrack, 'transform', 'translateX(' + -(idx * (cw + GAP)) + 'px)');
            sp(innerTrack, 'transition', 'transform 0.4s ease');
        }

        applyLayout();
        if (prevBtn) prevBtn.addEventListener('click', function() { go(idx - visible()); });
        if (nextBtn) nextBtn.addEventListener('click', function() { go(idx + visible()); });
        window.addEventListener('resize', function() { idx = 0; applyLayout(); });
    }

    function initAll() {
        document.querySelectorAll('.product-container').forEach(initSlider);
    }

    window.addEventListener('load', function() {
        initAll();
        setTimeout(initAll, 500);
        setTimeout(initAll, 1500);
    });
}());
</script>
<?php }, 10000);
