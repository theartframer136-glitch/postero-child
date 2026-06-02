<?php
/**
 * Postero Child Theme - functions.php
 * The Art Framer - theartframer.us
 */

// 1. Enqueue parent and child theme styles
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('postero-parent', get_template_directory_uri() . '/style.css');
    wp_enqueue_style('postero-child', get_stylesheet_uri(), array('postero-parent'), '1.0.0');
    wp_enqueue_style('postero-child-custom', get_stylesheet_directory_uri() . '/assets/css/custom.css', array('postero-child'), '1.4.0');
    wp_enqueue_script('postero-child-custom-js', get_stylesheet_directory_uri() . '/assets/js/custom.js', array('jquery'), '1.2.2', true);
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
.af-shell { box-sizing: border-box; }
.af-shell-btn {
    flex-shrink: 0 !important;
    border-radius: 50% !important;
    background: #c9a84c !important;
    border: none !important;
    color: #fff !important;
    font-size: 28px !important;
    line-height: 1 !important;
    cursor: pointer !important;
    padding: 0 !important;
    box-shadow: 0 2px 8px rgba(0,0,0,.25) !important;
}
.af-shell-btn:hover { background: #a8872e !important; }
.af-shell-track {
    display: flex !important;
    flex-direction: row !important;
    flex-wrap: nowrap !important;
    margin: 0 !important;
    padding: 4px 0 12px !important;
    list-style: none !important;
    transition: transform 0.4s ease !important;
    will-change: transform !important;
}
.af-shell-track .product-card {
    flex-shrink: 0 !important;
    float: none !important;
    margin: 0 !important;
    box-sizing: border-box !important;
}
.af-grid-hidden { display: none !important; }
</style>
<script>
(function() {
    function sp(el, p, v) { el.style.setProperty(p, v, 'important'); }

    var activeShell = null; // track current shell so we can tear it down

    function buildSlider(container, grid) {
        // Tear down previous shell — discard old cards entirely (don't return to grid)
        // The theme has already loaded new category cards into #productGrid while shell was active
        if (activeShell && activeShell.parentNode) {
            activeShell.parentNode.removeChild(activeShell);
            activeShell = null;
        }
        grid.classList.remove('af-grid-hidden');

        // Only grab cards currently in the grid (freshly loaded by the theme)
        var freshCards = Array.from(grid.querySelectorAll('.product-card'));
        if (!freshCards.length) return;

        var shell  = document.createElement('div');
        var btnP   = document.createElement('button');
        var vp     = document.createElement('div');
        var track  = document.createElement('div');
        var btnN   = document.createElement('button');

        shell.className  = 'af-shell';
        btnP.className   = 'af-shell-btn';  btnP.innerHTML = '&#8249;'; btnP.setAttribute('aria-label','Prev');
        btnN.className   = 'af-shell-btn';  btnN.innerHTML = '&#8250;'; btnN.setAttribute('aria-label','Next');
        track.className  = 'af-shell-track';

        freshCards.forEach(function(c) { track.appendChild(c); });
        vp.appendChild(track);
        shell.appendChild(btnP);
        shell.appendChild(vp);
        shell.appendChild(btnN);

        grid.parentNode.insertBefore(shell, grid.nextSibling);
        grid.classList.add('af-grid-hidden');
        activeShell = shell;

        // Hide original widget nav buttons
        var ob = container.querySelector('.prev-prod');
        var nb = container.querySelector('.next-prod');
        if (ob) sp(ob, 'display', 'none');
        if (nb) sp(nb, 'display', 'none');

        /* ── Layout engine ───────────────────────────────── */
        var idx = 0;
        var GAP = 12;
        var BTN = 44;

        function vis() {
            return window.innerWidth <= 600 ? 1 : window.innerWidth <= 768 ? 2 : 4;
        }

        // Step 1: set shell/vp/btn structure so vp gets its natural flex width
        function applyShellLayout() {
            sp(shell, 'display',     'flex');
            sp(shell, 'align-items', 'center');
            sp(shell, 'gap',         GAP + 'px');
            sp(shell, 'width',       '100%');
            sp(shell, 'box-sizing',  'border-box');

            [btnP, btnN].forEach(function(b) {
                sp(b, 'flex',            '0 0 ' + BTN + 'px');
                sp(b, 'width',           BTN + 'px');
                sp(b, 'height',          BTN + 'px');
                sp(b, 'display',         'flex');
                sp(b, 'align-items',     'center');
                sp(b, 'justify-content', 'center');
            });

            // Let vp grow naturally to fill remaining width
            sp(vp, 'flex',      '1 1 auto');
            sp(vp, 'min-width', '0');
            sp(vp, 'overflow',  'hidden');

            sp(track, 'display',        'flex');
            sp(track, 'flex-direction', 'row');
            sp(track, 'flex-wrap',      'nowrap');
            sp(track, 'gap',            GAP + 'px');
        }

        // Step 2: measure vp actual width, then size cards
        function sizeCards() {
            var vpW = vp.getBoundingClientRect().width;
            if (!vpW || vpW < 60) return 0;
            var v  = Math.min(vis(), freshCards.length || 1);
            var cw = Math.floor((vpW - GAP * (v - 1)) / v);
            if (cw < 60) return 0;
            freshCards.forEach(function(c) {
                sp(c, 'flex',      '0 0 ' + cw + 'px');
                sp(c, 'width',     cw + 'px');
                sp(c, 'min-width', cw + 'px');
                sp(c, 'max-width', cw + 'px');
            });
            return cw;
        }

        function go(newIdx) {
            var v   = Math.min(vis(), freshCards.length || 1);
            var max = Math.max(0, freshCards.length - v);
            idx     = Math.max(0, Math.min(newIdx, max));
            var cw  = sizeCards() || 200;
            sp(track, 'transform', 'translateX(' + (-(idx * (cw + GAP))) + 'px)');
        }

        applyShellLayout();
        // Measure after browser has done flex layout (two rAF passes)
        requestAnimationFrame(function() {
            requestAnimationFrame(function() {
                go(0);
            });
        });
        setTimeout(function() { go(0); }, 300);
        setTimeout(function() { go(0); }, 900);

        btnP.addEventListener('click', function() { go(idx - vis()); });
        btnN.addEventListener('click', function() { go(idx + vis()); });
        window.addEventListener('resize', function() { idx = 0; go(0); });
    }

    function init() {
        var container = document.querySelector('.product-container');
        if (!container) return;

        var grid = container.querySelector('#productGrid') || container.querySelector('.product-slider');
        if (!grid) return;

        // Initial build
        buildSlider(container, grid);

        // Watch for AJAX loads and show/hide category switches
        var rebuildTimer = null;
        var _building = false;
        var mo = new MutationObserver(function(mutations) {
            if (_building) return;
            // Only trigger when product-cards are ADDED (not removed, not attribute changes)
            var hasAddedCards = mutations.some(function(m) {
                if (m.type !== 'childList' || !m.addedNodes.length) return false;
                return Array.from(m.addedNodes).some(function(n) {
                    return n.nodeType === 1 && (
                        (n.classList && n.classList.contains('product-card')) ||
                        (n.querySelectorAll && n.querySelectorAll('.product-card').length > 0)
                    );
                });
            });
            if (!hasAddedCards) return;
            clearTimeout(rebuildTimer);
            // 500ms debounce — wait for all AJAX cards to finish inserting
            rebuildTimer = setTimeout(function() {
                if (_building) return;
                var newCards = Array.from(grid.querySelectorAll('.product-card'));
                if (!newCards.length) return; // grid not ready yet, skip
                _building = true;
                buildSlider(container, grid);
                setTimeout(function() { _building = false; }, 400);
            }, 500);
        });

        // Watch only childList (card additions/removals), not attribute changes
        mo.observe(grid, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else { init(); }
    window.addEventListener('load', function() {
        init();
        setTimeout(init, 500);
    });
}());
</script>
<?php }, 10000);


// 11. Products In Motion — circular video slider
add_action('wp_footer', function() { ?>
<style>
/* ── Wrapper that holds the circular slider ── */
.af-vid-shell {
    display: flex !important;
    align-items: center !important;
    gap: 12px !important;
    width: 100% !important;
    box-sizing: border-box !important;
    margin: 12px 0 !important;
}
.af-vid-btn {
    flex: 0 0 44px !important;
    width: 44px !important;
    height: 44px !important;
    border-radius: 50% !important;
    background: #c9a84c !important;
    border: none !important;
    color: #fff !important;
    font-size: 28px !important;
    line-height: 1 !important;
    cursor: pointer !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    box-shadow: 0 2px 8px rgba(0,0,0,.25) !important;
    flex-shrink: 0 !important;
    padding: 0 !important;
    z-index: 2 !important;
}
.af-vid-btn:hover { background: #a8872e !important; }
.af-vid-vp {
    flex: 1 1 auto !important;
    min-width: 0 !important;
    overflow: hidden !important;
}
.af-vid-track {
    display: flex !important;
    flex-direction: row !important;
    flex-wrap: nowrap !important;
    gap: 20px !important;
    transition: transform 0.4s ease !important;
    will-change: transform !important;
    align-items: center !important;
}
/* Each circle item */
.af-vid-item {
    flex: 0 0 auto !important;
    width: 200px !important;
    height: 200px !important;
    border-radius: 50% !important;
    overflow: hidden !important;
    position: relative !important;
    cursor: pointer !important;
    border: 3px solid #c9a84c !important;
    box-shadow: 0 4px 16px rgba(0,0,0,0.18) !important;
    background: #000 !important;
    transition: transform 0.3s ease, box-shadow 0.3s ease !important;
}
.af-vid-item:hover {
    transform: scale(1.06) !important;
    box-shadow: 0 8px 28px rgba(0,0,0,0.28) !important;
}
/* Thumbnail image fills the circle */
.af-vid-item img,
.af-vid-item .af-vid-thumb {
    width: 100% !important;
    height: 100% !important;
    object-fit: cover !important;
    display: block !important;
    border-radius: 50% !important;
}
/* Play button overlay */
.af-vid-play {
    position: absolute !important;
    inset: 0 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    background: rgba(0,0,0,0.25) !important;
    border-radius: 50% !important;
    pointer-events: none !important;
}
.af-vid-play svg {
    width: 48px !important;
    height: 48px !important;
    fill: rgba(255,255,255,0.9) !important;
    filter: drop-shadow(0 2px 4px rgba(0,0,0,0.5)) !important;
}
/* Lightbox overlay for playing video */
.af-vid-lightbox {
    position: fixed !important;
    inset: 0 !important;
    background: rgba(0,0,0,0.85) !important;
    z-index: 99999 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
}
.af-vid-lightbox iframe {
    width: 80vw !important;
    height: 45vw !important;
    max-width: 960px !important;
    max-height: 540px !important;
    border: none !important;
    border-radius: 8px !important;
}
.af-vid-lightbox-close {
    position: absolute !important;
    top: 20px !important;
    right: 28px !important;
    color: #fff !important;
    font-size: 40px !important;
    cursor: pointer !important;
    line-height: 1 !important;
    background: none !important;
    border: none !important;
    z-index: 100000 !important;
}
/* Hide the original ugly video list */
.af-vid-source-hidden {
    display: none !important;
}
@media (max-width: 768px) {
    .af-vid-item { width: 140px !important; height: 140px !important; }
}
@media (max-width: 480px) {
    .af-vid-item { width: 110px !important; height: 110px !important; }
}
</style>
<script>
(function() {
    // Extract YouTube video ID from various URL formats
    function ytId(url) {
        if (!url) return null;
        var m = url.match(/(?:youtu\.be\/|youtube\.com\/(?:embed\/|watch\?v=|shorts\/))([A-Za-z0-9_-]{11})/);
        return m ? m[1] : null;
    }

    function buildMotionSlider() {
        // Find the "Products In Motion" section — look for heading text
        var headings = document.querySelectorAll('h2, h3, h4, .elementor-heading-title');
        var motionSection = null;
        headings.forEach(function(h) {
            if (/products?\s+in\s+motion/i.test(h.textContent)) {
                motionSection = h.closest('.elementor-section, .elementor-widget-wrap, section, .elementor-container') || h.parentElement;
            }
        });
        if (!motionSection) return;
        if (motionSection.dataset.afMotionDone) return;
        motionSection.dataset.afMotionDone = '1';

        // Collect all YouTube iframes and links in this section
        var videos = [];

        // From iframes
        motionSection.querySelectorAll('iframe').forEach(function(iframe) {
            var vid = ytId(iframe.src || iframe.getAttribute('data-src') || '');
            if (vid) videos.push({ id: vid, el: iframe });
        });

        // From elementor video widgets (data-src or src on inner iframes)
        motionSection.querySelectorAll('[data-src]').forEach(function(el) {
            var vid = ytId(el.getAttribute('data-src') || '');
            if (vid && !videos.find(function(v) { return v.id === vid; })) {
                videos.push({ id: vid, el: el });
            }
        });

        // From YouTube playlist or link elements
        motionSection.querySelectorAll('a[href*="youtube"], a[href*="youtu.be"]').forEach(function(a) {
            var vid = ytId(a.href);
            if (vid && !videos.find(function(v) { return v.id === vid; })) {
                videos.push({ id: vid, el: a });
            }
        });

        if (!videos.length) return;

        // Find the container to insert slider before/after the video list
        // Hide original video elements' parent widget containers
        var widgetsToHide = new Set();
        videos.forEach(function(v) {
            var widget = v.el.closest('.elementor-widget, .elementor-element');
            if (widget) widgetsToHide.add(widget);
        });

        // Build the circular slider shell
        var GAP = 20;
        var shell = document.createElement('div');
        shell.className = 'af-vid-shell';

        var btnP = document.createElement('button');
        btnP.className = 'af-vid-btn'; btnP.innerHTML = '&#8249;'; btnP.setAttribute('aria-label','Prev');

        var btnN = document.createElement('button');
        btnN.className = 'af-vid-btn'; btnN.innerHTML = '&#8250;'; btnN.setAttribute('aria-label','Next');

        var vp = document.createElement('div');
        vp.className = 'af-vid-vp';

        var track = document.createElement('div');
        track.className = 'af-vid-track';

        // Build circle items
        var items = videos.map(function(v) {
            var item = document.createElement('div');
            item.className = 'af-vid-item';

            // Use YouTube thumbnail as background
            var img = document.createElement('img');
            img.src = 'https://img.youtube.com/vi/' + v.id + '/hqdefault.jpg';
            img.alt = '';
            img.className = 'af-vid-thumb';

            // Play button overlay
            var play = document.createElement('div');
            play.className = 'af-vid-play';
            play.innerHTML = '<svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>';

            item.appendChild(img);
            item.appendChild(play);

            // Click → lightbox
            item.addEventListener('click', function() {
                var lb = document.createElement('div');
                lb.className = 'af-vid-lightbox';
                var close = document.createElement('button');
                close.className = 'af-vid-lightbox-close';
                close.innerHTML = '&times;';
                var iframe = document.createElement('iframe');
                iframe.src = 'https://www.youtube.com/embed/' + v.id + '?autoplay=1&rel=0';
                iframe.allow = 'autoplay; encrypted-media';
                iframe.allowFullscreen = true;
                lb.appendChild(close);
                lb.appendChild(iframe);
                document.body.appendChild(lb);
                close.addEventListener('click', function() { document.body.removeChild(lb); });
                lb.addEventListener('click', function(e) { if (e.target === lb) document.body.removeChild(lb); });
            });

            track.appendChild(item);
            return item;
        });

        vp.appendChild(track);
        shell.appendChild(btnP);
        shell.appendChild(vp);
        shell.appendChild(btnN);

        // Insert shell right before the first hidden widget
        var firstWidget = Array.from(widgetsToHide)[0];
        if (firstWidget && firstWidget.parentNode) {
            firstWidget.parentNode.insertBefore(shell, firstWidget);
        } else {
            motionSection.appendChild(shell);
        }

        // Hide original widgets
        widgetsToHide.forEach(function(w) { w.classList.add('af-vid-source-hidden'); });

        // Slider logic
        var idx = 0;
        function visCount() {
            var vpW = vp.getBoundingClientRect().width;
            if (!vpW) return 3;
            return Math.max(1, Math.floor((vpW + GAP) / (200 + GAP)));
        }
        function go(newIdx) {
            var vis = visCount();
            var max = Math.max(0, items.length - vis);
            idx = Math.max(0, Math.min(newIdx, max));
            var itemW = items[0] ? items[0].getBoundingClientRect().width || 200 : 200;
            track.style.setProperty('transform', 'translateX(' + (-(idx * (itemW + GAP))) + 'px)', 'important');
        }
        btnP.addEventListener('click', function() { go(idx - visCount()); });
        btnN.addEventListener('click', function() { go(idx + visCount()); });
        window.addEventListener('resize', function() { idx = 0; go(0); });
        setTimeout(function() { go(0); }, 100);
    }

    window.addEventListener('load', function() {
        buildMotionSlider();
        setTimeout(buildMotionSlider, 800);
    });
}());
</script>
<?php }, 10001);
