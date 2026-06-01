<?php
/**
 * Postero Child Theme - functions.php
 * The Art Framer - theartframer.us
 */

// 1. Enqueue parent and child theme styles
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('postero-parent', get_template_directory_uri() . '/style.css');
    wp_enqueue_style('postero-child', get_stylesheet_uri(), array('postero-parent'), '1.0.0');
    wp_enqueue_style('postero-child-custom', get_stylesheet_directory_uri() . '/assets/css/custom.css', array('postero-child'), '1.3.3');
    wp_enqueue_script('postero-child-custom-js', get_stylesheet_directory_uri() . '/assets/js/custom.js', array('jquery'), '1.1.2', true);
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
/* Our fully-owned slider shell — parent theme never touches these classes */
.af-shell {
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
    width: 100% !important;
    overflow: visible !important;
    box-sizing: border-box !important;
}
.af-shell-btn {
    flex: 0 0 44px !important;
    width: 44px !important;
    height: 44px !important;
    min-width: 44px !important;
    border-radius: 50% !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    background: #c9a84c !important;
    border: none !important;
    color: #fff !important;
    font-size: 28px !important;
    line-height: 1 !important;
    cursor: pointer !important;
    padding: 0 !important;
    box-shadow: 0 2px 8px rgba(0,0,0,.25) !important;
    z-index: 10 !important;
    flex-shrink: 0 !important;
}
.af-shell-btn:hover { background: #a8872e !important; }
.af-shell-vp {
    flex: 1 1 auto !important;
    min-width: 0 !important;
    overflow: hidden !important;
    position: relative !important;
}
.af-shell-track {
    display: flex !important;
    flex-direction: row !important;
    flex-wrap: nowrap !important;
    gap: 16px !important;
    padding: 4px 0 12px !important;
    margin: 0 !important;
    list-style: none !important;
    transition: transform 0.4s ease !important;
    will-change: transform !important;
    width: max-content !important;
    grid-template-columns: unset !important;
}
.af-shell-track .product-card {
    flex-shrink: 0 !important;
    float: none !important;
    margin: 0 !important;
}
/* Hide the original grid once we've taken its cards */
.af-grid-hidden {
    display: none !important;
}
/* Keep the outer product-container invisible to layout */
.product-container {
    display: block !important;
}
</style>
<script>
(function() {
    function sp(el, p, v) { el.style.setProperty(p, v, 'important'); }

    function run() {
        var container = document.querySelector('.product-container');
        if (!container || container.dataset.afDone) return;

        // Find the original grid
        var grid = container.querySelector('#productGrid') || container.querySelector('.product-slider');
        if (!grid) return;

        var cards = Array.from(grid.querySelectorAll('.product-card'));
        if (!cards.length) return;

        container.dataset.afDone = '1';

        // Find the prev/next buttons (they live inside .product-container)
        var prevBtn = container.querySelector('.prev-prod');
        var nextBtn = container.querySelector('.next-prod');

        // ── Build our own slider shell ──────────────────────────────
        var shell = document.createElement('div');
        shell.className = 'af-shell';

        var btnPrev = document.createElement('button');
        btnPrev.className = 'af-shell-btn';
        btnPrev.innerHTML = '&#8249;';
        btnPrev.setAttribute('aria-label', 'Previous');

        var btnNext = document.createElement('button');
        btnNext.className = 'af-shell-btn';
        btnNext.innerHTML = '&#8250;';
        btnNext.setAttribute('aria-label', 'Next');

        var vp = document.createElement('div');
        vp.className = 'af-shell-vp';

        var track = document.createElement('div');
        track.className = 'af-shell-track';

        // Move every product-card from the original grid into our track
        cards.forEach(function(c) { track.appendChild(c); });

        vp.appendChild(track);
        shell.appendChild(btnPrev);
        shell.appendChild(vp);
        shell.appendChild(btnNext);

        // Insert shell after the original grid, then hide the grid
        grid.parentNode.insertBefore(shell, grid.nextSibling);
        grid.classList.add('af-grid-hidden');

        // Also hide original nav buttons if present
        if (prevBtn) sp(prevBtn, 'display', 'none');
        if (nextBtn) sp(nextBtn, 'display', 'none');

        // ── Slider logic ────────────────────────────────────────────
        var GAP = 16;
        var currentIndex = 0;

        function visCount() {
            return window.innerWidth <= 600 ? 1 : window.innerWidth <= 768 ? 2 : 4;
        }

        function cardWidth() {
            var vw = vp.getBoundingClientRect().width;
            var vis = visCount();
            return Math.floor((vw - GAP * (vis - 1)) / vis);
        }

        function sizeCards() {
            var cw = cardWidth();
            cards.forEach(function(c) {
                sp(c, 'flex',      '0 0 ' + cw + 'px');
                sp(c, 'width',     cw + 'px');
                sp(c, 'min-width', cw + 'px');
                sp(c, 'max-width', cw + 'px');
            });
            return cw;
        }

        function slideTo(idx) {
            var vis = visCount();
            var max = Math.max(0, cards.length - vis);
            currentIndex = Math.max(0, Math.min(idx, max));
            var cw = cardWidth();
            sp(track, 'transform', 'translateX(' + (-(currentIndex * (cw + GAP))) + 'px)');
        }

        sizeCards();
        requestAnimationFrame(sizeCards);

        btnPrev.addEventListener('click', function() {
            sizeCards();
            slideTo(currentIndex - visCount());
        });
        btnNext.addEventListener('click', function() {
            sizeCards();
            slideTo(currentIndex + visCount());
        });
        window.addEventListener('resize', function() {
            currentIndex = 0;
            sizeCards();
            slideTo(0);
        });
    }

    window.addEventListener('load', function() {
        run();
        setTimeout(run, 600);
        setTimeout(run, 1500);
    });
}());
</script>
<?php }, 10000);
