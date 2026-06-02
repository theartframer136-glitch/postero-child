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
    wp_enqueue_script('postero-child-custom-js', get_stylesheet_directory_uri() . '/assets/js/custom.js', array('jquery'), '1.2.5', true);
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
/* Shell */
.af-vid-shell {
    display: flex !important;
    align-items: center !important;
    gap: 16px !important;
    width: 100% !important;
    box-sizing: border-box !important;
    padding: 16px 0 !important;
}
.af-vid-btn {
    flex: 0 0 44px !important;
    width: 44px !important; height: 44px !important;
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
    padding: 0 !important;
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
    gap: 24px !important;
    align-items: center !important;
    transition: transform 0.4s ease !important;
    will-change: transform !important;
    padding: 8px 0 !important;
}
/* Each circle: wraps the Elementor widget */
.af-vid-circle {
    flex: 0 0 200px !important;
    width: 200px !important;
    height: 200px !important;
    border-radius: 50% !important;
    overflow: hidden !important;
    border: 3px solid #c9a84c !important;
    box-shadow: 0 4px 16px rgba(0,0,0,0.18) !important;
    background: #111 !important;
    transition: transform 0.3s ease, box-shadow 0.3s ease !important;
    cursor: pointer !important;
    position: relative !important;
}
.af-vid-circle:hover {
    transform: scale(1.06) !important;
    box-shadow: 0 8px 28px rgba(0,0,0,0.3) !important;
}
/* Make the thumbnail image fill the circle */
.af-vid-circle img {
    width: 100% !important;
    height: 100% !important;
    object-fit: cover !important;
    display: block !important;
}
/* Play icon overlay */
.af-vid-circle .af-play-icon {
    position: absolute !important;
    inset: 0 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    background: rgba(0,0,0,0.22) !important;
    pointer-events: none !important;
}
.af-vid-circle .af-play-icon svg {
    width: 52px !important; height: 52px !important;
    fill: rgba(255,255,255,0.92) !important;
    filter: drop-shadow(0 2px 6px rgba(0,0,0,.5)) !important;
}
/* Lightbox */
.af-vid-lb {
    position: fixed !important;
    inset: 0 !important;
    background: rgba(0,0,0,0.88) !important;
    z-index: 99999 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
}
.af-vid-lb iframe {
    width: min(80vw, 960px) !important;
    height: min(45vw, 540px) !important;
    border: none !important;
    border-radius: 8px !important;
}
.af-vid-lb-x {
    position: absolute !important;
    top: 16px !important; right: 24px !important;
    color: #fff !important;
    font-size: 44px !important;
    cursor: pointer !important;
    background: none !important;
    border: none !important;
    line-height: 1 !important;
}
/* Hide original video grid once we've built the circular slider */
.af-vid-original-hidden { display: none !important; }
@media (max-width: 768px) {
    .af-vid-circle { flex: 0 0 140px !important; width: 140px !important; height: 140px !important; }
}
@media (max-width: 480px) {
    .af-vid-circle { flex: 0 0 110px !important; width: 110px !important; height: 110px !important; }
}
</style>
<script>
(function() {
    var SIZES = { lg: 200, md: 140, sm: 110 };
    var GAP = 24;

    function ytId(str) {
        if (!str) return null;
        var m = str.match(/(?:v=|youtu\.be\/|\/embed\/|shorts\/)([A-Za-z0-9_-]{11})/);
        return m ? m[1] : null;
    }

    // Deep search for YouTube ID in any element's subtree
    function extractId(el) {
        var id = null;
        // 1. All data-settings attributes
        el.querySelectorAll('[data-settings]').forEach(function(e) {
            if (id) return;
            try { var s = JSON.parse(e.getAttribute('data-settings')||'{}'); id = ytId(s.youtube_url||s.url||''); } catch(x){}
        });
        if (id) return id;
        // 2. data-settings on element itself
        try { var s2 = JSON.parse(el.getAttribute('data-settings')||'{}'); id = ytId(s2.youtube_url||s2.url||''); } catch(x){}
        if (id) return id;
        // 3. iframes (src or data-lazy-src or data-src)
        el.querySelectorAll('iframe').forEach(function(f) {
            if (id) return;
            id = ytId(f.src||f.getAttribute('data-lazy-src')||f.getAttribute('data-src')||'');
        });
        if (id) return id;
        // 4. Any anchor href with youtu
        el.querySelectorAll('a[href]').forEach(function(a) {
            if (id) return; if (/youtu/i.test(a.href)) id = ytId(a.href);
        });
        if (id) return id;
        // 5. Any data-url / data-video-url / data-video attributes
        ['data-url','data-video-url','data-video','data-src'].forEach(function(attr) {
            if (!id) id = ytId(el.getAttribute(attr)||'');
        });
        if (id) return id;
        // 6. Scan every element's every attribute for a YouTube URL
        el.querySelectorAll('*').forEach(function(e) {
            if (id) return;
            Array.from(e.attributes).forEach(function(a) {
                if (!id && /youtu/i.test(a.value)) id = ytId(a.value);
            });
        });
        return id;
    }

    function findSection() {
        var found = null;

        // Strategy 1: match heading text (handles typos like "Motation")
        document.querySelectorAll('h2,h3,h4,.elementor-heading-title').forEach(function(h) {
            if (!found && /product.*mot/i.test(h.textContent.trim())) {
                var el = h;
                for (var i = 0; i < 12; i++) {
                    el = el.parentElement;
                    if (!el) break;
                    if (/elementor-section|e-container|elementor-top-section/.test(el.className)) { found = el; break; }
                }
                if (!found) found = h.closest('[class*="elementor-section"],[class*="e-container"]') || h.parentElement;
            }
        });
        if (found) return found;

        // Strategy 2: find the elementor-widget-wrap that has the most video widgets
        var best = null, bestCount = 0;
        document.querySelectorAll('.elementor-widget-wrap').forEach(function(wrap) {
            var count = wrap.querySelectorAll('.elementor-widget-video').length;
            if (count > bestCount) { bestCount = count; best = wrap; }
        });
        if (bestCount >= 2) return best;

        // Strategy 3: find any section containing 2+ iframes
        document.querySelectorAll('.elementor-section, .e-container').forEach(function(sec) {
            if (!found && sec.querySelectorAll('iframe').length >= 2) found = sec;
        });
        return found;
    }

    function buildSlider() {
        var sec = findSection();
        if (!sec || sec.dataset.afVidDone) return;

        // Collect every Elementor video widget in this section
        var widgets = Array.from(sec.querySelectorAll('.elementor-widget-video'));

        // Also grab youtube_playlist widgets
        sec.querySelectorAll('.elementor-widget-youtube').forEach(function(w) {
            if (!widgets.includes(w)) widgets.push(w);
        });

        // Fallback 1: any widget with an iframe
        if (!widgets.length) {
            sec.querySelectorAll('.elementor-widget').forEach(function(w) {
                if (w.querySelector('iframe')) widgets.push(w);
            });
        }
        // Fallback 2: just grab every iframe directly
        if (!widgets.length) {
            sec.querySelectorAll('iframe').forEach(function(iframe) {
                widgets.push(iframe.closest('.elementor-widget') || iframe.parentElement || iframe);
            });
        }

        if (!widgets.length) return;
        sec.dataset.afVidDone = '1';

        // For each widget, extract the YouTube ID for the thumbnail
        var videoData = widgets.map(function(w) {
            var id = null;
            var src = '';

            // from iframe
            var iframe = w.querySelector ? w.querySelector('iframe') : (w.tagName === 'IFRAME' ? w : null);
            if (iframe) src = iframe.src || iframe.getAttribute('data-src') || '';
            if (src) id = ytId(src);

            // from data-settings JSON
            if (!id && w.querySelectorAll) {
                w.querySelectorAll('[data-settings]').forEach(function(el) {
                    if (id) return;
                    try { var s = JSON.parse(el.getAttribute('data-settings') || '{}'); id = ytId(s.youtube_url || s.url || ''); } catch(e){}
                });
            }

            // from data-settings on self
            if (!id && w.getAttribute) {
                try { var s2 = JSON.parse(w.getAttribute('data-settings') || '{}'); id = ytId(s2.youtube_url || s2.url || ''); } catch(e){}
            }

            return { widget: w, id: id, src: src };
        });

        // Remove duplicates (same widget matched twice)
        var seen2 = new Set();
        videoData = videoData.filter(function(v) {
            if (seen2.has(v.widget)) return false;
            seen2.add(v.widget); return true;
        });

        // Build shell
        var shell  = document.createElement('div'); shell.className = 'af-vid-shell';
        var btnP   = document.createElement('button'); btnP.className = 'af-vid-btn'; btnP.innerHTML = '&#8249;'; btnP.setAttribute('aria-label','Prev');
        var btnN   = document.createElement('button'); btnN.className = 'af-vid-btn'; btnN.innerHTML = '&#8250;'; btnN.setAttribute('aria-label','Next');
        var vp     = document.createElement('div'); vp.className = 'af-vid-vp';
        var track  = document.createElement('div'); track.className = 'af-vid-track';

        var circles = videoData.map(function(v) {
            var circle = document.createElement('div');
            circle.className = 'af-vid-circle';

            if (v.id) {
                var img = document.createElement('img');
                img.src = 'https://img.youtube.com/vi/' + v.id + '/hqdefault.jpg';
                img.alt = '';
                circle.appendChild(img);
            }

            var icon = document.createElement('div');
            icon.className = 'af-play-icon';
            icon.innerHTML = '<svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>';
            circle.appendChild(icon);

            // Click → lightbox with autoplay
            circle.addEventListener('click', function() {
                if (!v.id) return;
                var lb = document.createElement('div'); lb.className = 'af-vid-lb';
                var x  = document.createElement('button'); x.className = 'af-vid-lb-x'; x.innerHTML = '&times;';
                var fr = document.createElement('iframe');
                fr.src = 'https://www.youtube.com/embed/' + v.id + '?autoplay=1&rel=0';
                fr.allow = 'autoplay; fullscreen; encrypted-media';
                fr.allowFullscreen = true;
                lb.appendChild(x); lb.appendChild(fr);
                document.body.appendChild(lb);
                function close() { try { document.body.removeChild(lb); } catch(e){} }
                x.onclick = close;
                lb.onclick = function(e) { if (e.target === lb) close(); };
            });

            track.appendChild(circle);
            return circle;
        });

        vp.appendChild(track);
        shell.appendChild(btnP); shell.appendChild(vp); shell.appendChild(btnN);

        // Insert shell before first widget, then hide all widgets
        widgets[0].parentNode.insertBefore(shell, widgets[0]);
        widgets.forEach(function(w) { w.classList.add('af-vid-original-hidden'); });

        // Slider logic
        var idx = 0;
        function iw() { return window.innerWidth <= 480 ? SIZES.sm : window.innerWidth <= 768 ? SIZES.md : SIZES.lg; }
        function vis() { var vpW = vp.getBoundingClientRect().width || 800; return Math.max(1, Math.floor((vpW + GAP) / (iw() + GAP))); }
        function go(n) {
            var v = vis(), max = Math.max(0, circles.length - v);
            idx = Math.max(0, Math.min(n, max));
            track.style.setProperty('transform','translateX('+( -(idx*(iw()+GAP)) )+'px)','important');
        }
        btnP.onclick = function() { go(idx - vis()); };
        btnN.onclick = function() { go(idx + vis()); };
        window.addEventListener('resize', function() { idx=0; go(0); });
        setTimeout(function(){ go(0); }, 200);
    }

    function tryBuild() {
        // Primary: page uses .circle-gallery-slider with .circle-item / .video-circle
        var gallerySlider = document.querySelector('.circle-gallery-slider');
        if (gallerySlider) {
            var items = Array.from(gallerySlider.querySelectorAll('.circle-item, .video-circle'));
            if (items.length >= 2) { buildFromItems(gallerySlider, items); return; }
        }

        // Fallback: any .video-circle elements on the page
        var loose = Array.from(document.querySelectorAll('.video-circle'));
        if (loose.length >= 2) {
            var wrap = loose[0].parentElement;
            buildFromItems(wrap, loose); return;
        }

        // Last resort: original detection
        buildSlider();
    }

    function buildFromItems(container, items) {
        if (container.dataset.afVidDone) return;
        container.dataset.afVidDone = '1';

        var videoData = items.map(function(item) {
            return { item: item, id: extractId(item) };
        });

        // Build shell
        var shell = document.createElement('div'); shell.className = 'af-vid-shell';
        var btnP  = document.createElement('button'); btnP.className = 'af-vid-btn'; btnP.innerHTML = '&#8249;'; btnP.setAttribute('aria-label','Prev');
        var btnN  = document.createElement('button'); btnN.className = 'af-vid-btn'; btnN.innerHTML = '&#8250;'; btnN.setAttribute('aria-label','Next');
        var vp    = document.createElement('div'); vp.className = 'af-vid-vp';
        var track = document.createElement('div'); track.className = 'af-vid-track';

        var circles = videoData.map(function(v) {
            var circle = document.createElement('div'); circle.className = 'af-vid-circle';

            if (v.id) {
                // YouTube thumbnail as background image
                var img = document.createElement('img');
                img.src = 'https://img.youtube.com/vi/' + v.id + '/maxresdefault.jpg';
                img.alt = '';
                // Fall back to hqdefault if maxres not available
                img.onerror = function(){ this.src = 'https://img.youtube.com/vi/' + v.id + '/hqdefault.jpg'; this.onerror=null; };
                circle.appendChild(img);

                // Play icon overlay
                var icon = document.createElement('div'); icon.className = 'af-play-icon';
                icon.innerHTML = '<svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>';
                circle.appendChild(icon);

                // Click → fullscreen lightbox with autoplay + sound
                circle.addEventListener('click', function() {
                    var lb = document.createElement('div'); lb.className = 'af-vid-lb';
                    var x  = document.createElement('button'); x.className = 'af-vid-lb-x'; x.innerHTML = '&times;';
                    var lbfr = document.createElement('iframe');
                    lbfr.src = 'https://www.youtube.com/embed/' + v.id + '?autoplay=1&rel=0';
                    lbfr.allow = 'autoplay; fullscreen; encrypted-media'; lbfr.allowFullscreen = true;
                    lb.appendChild(x); lb.appendChild(lbfr); document.body.appendChild(lb);
                    function close() { try { document.body.removeChild(lb); } catch(e){} }
                    x.onclick = close; lb.onclick = function(e){ if(e.target===lb) close(); };
                });
            } else {
                // No ID found — dark circle with play icon
                circle.style.background = '#222';
                var icon2 = document.createElement('div'); icon2.className = 'af-play-icon';
                icon2.innerHTML = '<svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>';
                circle.appendChild(icon2);
            }

            track.appendChild(circle); return circle;
        });

        vp.appendChild(track);
        shell.appendChild(btnP); shell.appendChild(vp); shell.appendChild(btnN);
        container.insertAdjacentElement('afterend', shell);
        container.classList.add('af-vid-original-hidden');

        var idx = 0;
        function iw() { return window.innerWidth<=480?SIZES.sm:window.innerWidth<=768?SIZES.md:SIZES.lg; }
        function vis() { var vpW=vp.getBoundingClientRect().width||800; return Math.max(1,Math.floor((vpW+GAP)/(iw()+GAP))); }
        function go(n) {
            var v=vis(), max=Math.max(0,circles.length-v);
            idx=Math.max(0,Math.min(n,max));
            track.style.setProperty('transform','translateX('+(-(idx*(iw()+GAP)))+'px)','important');
        }
        btnP.onclick=function(){go(idx-vis());}; btnN.onclick=function(){go(idx+vis());};
        window.addEventListener('resize',function(){idx=0;go(0);});
        setTimeout(function(){go(0);},200);
    }

    window.addEventListener('load', function() { tryBuild(); setTimeout(tryBuild, 1000); });
}());
</script>
<?php }, 10001);
