<?php
/**
 * Postero Child Theme - functions.php
 * The Art Framer - theartframer.us
 */

// 1. Enqueue parent and child theme styles
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('postero-parent', get_template_directory_uri() . '/style.css');
    wp_enqueue_style('postero-child', get_stylesheet_uri(), array('postero-parent'), '1.0.0');
    wp_enqueue_style('postero-child-custom', get_stylesheet_directory_uri() . '/assets/css/custom.css', array('postero-child'), '1.2.5');
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
        var cards = Array.from(viewport.querySelectorAll('.product-card'));
        if (!cards.length) return;
        container.dataset.afSlider = '1';

        // Wrap cards in inner track so viewport can clip and track can slide
        var track = document.createElement('div');
        track.className = 'af-track';
        cards.forEach(function(c) { track.appendChild(c); });
        viewport.appendChild(track);

        var prevBtn = container.querySelector('.prev-prod');
        var nextBtn = container.querySelector('.next-prod');
        var GAP = 16;
        var idx = 0;

        function visCount() {
            var w = window.innerWidth;
            return w <= 576 ? 1 : w <= 991 ? 3 : 5;
        }

        function layout() {
            var vis = visCount();
            var allCards = Array.from(track.querySelectorAll('.product-card'));

            // 1. Make container a flex row
            sp(container, 'display', 'flex');
            sp(container, 'align-items', 'center');
            sp(container, 'gap', '8px');
            sp(container, 'width', '100%');

            // 2. Style buttons
            [prevBtn, nextBtn].forEach(function(btn) {
                if (!btn) return;
                sp(btn, 'display',         'flex');
                sp(btn, 'align-items',     'center');
                sp(btn, 'justify-content', 'center');
                sp(btn, 'flex-shrink',     '0');
                sp(btn, 'width',           '44px');
                sp(btn, 'height',          '44px');
                sp(btn, 'min-width',       '44px');
                sp(btn, 'border-radius',   '50%');
                sp(btn, 'background',      '#c9a84c');
                sp(btn, 'border',          'none');
                sp(btn, 'color',           '#fff');
                sp(btn, 'font-size',       '28px');
                sp(btn, 'cursor',          'pointer');
                sp(btn, 'padding',         '0');
                sp(btn, 'box-shadow',      '0 2px 8px rgba(0,0,0,0.22)');
            });
            if (prevBtn) prevBtn.innerHTML = '&#8249;';
            if (nextBtn) nextBtn.innerHTML = '&#8250;';

            // 3. Viewport: clips overflow
            sp(viewport, 'display',   'block');
            sp(viewport, 'overflow',  'hidden');
            sp(viewport, 'flex',      '1 1 auto');
            sp(viewport, 'min-width', '0');
            sp(viewport, 'padding',   '4px 0 12px');

            // 4. Measure width now that container is flex
            var w = viewport.getBoundingClientRect().width;
            if (!w) w = container.getBoundingClientRect().width - 96;
            var cw = Math.floor((w - GAP * (vis - 1)) / vis);

            // 5. Track: flex row, slides
            sp(track, 'display',         'flex');
            sp(track, 'flex-direction',  'row');
            sp(track, 'flex-wrap',       'nowrap');
            sp(track, 'gap',             GAP + 'px');
            sp(track, 'transition',      'transform 0.4s ease');
            sp(track, 'transform',       'translateX(' + -(idx * (cw + GAP)) + 'px)');

            // 6. Each card: fixed width
            allCards.forEach(function(c) {
                sp(c, 'flex',      '0 0 ' + cw + 'px');
                sp(c, 'width',     cw + 'px');
                sp(c, 'min-width', cw + 'px');
                sp(c, 'margin',    '0');
                sp(c, 'float',     'none');
            });
        }

        function go(n) {
            var vis = visCount();
            var allCards = track.querySelectorAll('.product-card');
            var w = viewport.getBoundingClientRect().width;
            var cw = Math.floor((w - GAP * (vis - 1)) / vis);
            idx = Math.max(0, Math.min(n, allCards.length - vis));
            sp(track, 'transform', 'translateX(' + -(idx * (cw + GAP)) + 'px)');
            sp(track, 'transition', 'transform 0.4s ease');
        }

        layout();
        // Re-layout after paint to get accurate widths
        requestAnimationFrame(function() { layout(); });

        if (prevBtn) prevBtn.addEventListener('click', function() { go(idx - visCount()); });
        if (nextBtn) nextBtn.addEventListener('click', function() { go(idx + visCount()); });
        window.addEventListener('resize', function() { idx = 0; layout(); });
    }

    function initAll() {
        document.querySelectorAll('.product-container').forEach(initSlider);
    }

    window.addEventListener('load', function() {
        initAll();
        requestAnimationFrame(function() { initAll(); });
        setTimeout(initAll, 800);
    });
}());
</script>
<?php }, 10000);
