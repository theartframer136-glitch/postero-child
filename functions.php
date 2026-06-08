<?php
/**
 * Postero Child Theme - functions.php
 * The Art Framer - theartframer.us
 */

// 1. Enqueue parent and child theme styles
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('postero-parent', get_template_directory_uri() . '/style.css');
    wp_enqueue_style('postero-child', get_stylesheet_uri(), array('postero-parent'), '1.0.0');
    wp_enqueue_style('postero-child-custom', get_stylesheet_directory_uri() . '/assets/css/custom.css', array('postero-child'), '1.7.3');
    wp_enqueue_script('postero-child-custom-js', get_stylesheet_directory_uri() . '/assets/js/custom.js', array('jquery'), '1.2.9', true);
    wp_localize_script('postero-child-custom-js', 'af_ajax', array('url' => admin_url('admin-ajax.php')));
}, 20);

// 2. Force USD as default currency — override currency switcher plugins
add_filter('woocommerce_currency', function() { return 'USD'; }, 9999);
add_filter('woocommerce_currency_symbol', function($symbol, $currency) { return '$'; }, 9999, 2);

// Redirect any ?currency=INR (or any non-USD) URL to ?currency=USD
add_action('template_redirect', function() {
    if (isset($_GET['currency']) && $_GET['currency'] !== 'USD') {
        $url = add_query_arg('currency', 'USD', remove_query_arg('currency'));
        wp_redirect($url, 302);
        exit;
    }
});

// Force WMC plugin's own stored default to USD (fixes navbar showing INR)
add_action('init', function() {
    // Fix WMC plugin database default
    $wmc_options = get_option('wmc_options', []);
    if (!empty($wmc_options) && ($wmc_options['default_currency'] ?? '') !== 'USD') {
        $wmc_options['default_currency'] = 'USD';
        update_option('wmc_options', $wmc_options);
    }
    // Fix WOOCS plugin database default
    $woocs_def = get_option('woocs_default_currency', '');
    if ($woocs_def && $woocs_def !== 'USD') {
        update_option('woocs_default_currency', 'USD');
    }

    // Set all currency cookies to USD
    $exp  = time() + 86400 * 365;
    $path = COOKIEPATH ?: '/';
    $host = COOKIE_DOMAIN ?: '';
    foreach (['woocs_session_currency','wmc_current_currency','wmc-currency','currency','chosen_currency'] as $name) {
        if (!isset($_COOKIE[$name]) || $_COOKIE[$name] !== 'USD') {
            setcookie($name, 'USD', $exp, $path, $host);
            $_COOKIE[$name] = 'USD';
        }
    }
    // WPML / WCML
    if (defined('WCML_VERSION')) {
        add_filter('wcml_client_currency', function() { return 'USD'; }, 9999);
    }
}, 1);

// Override any currency stored in WC session
add_action('woocommerce_init', function() {
    if (!WC()->session) return;
    $sess_cur = WC()->session->get('currency');
    if ($sess_cur && $sess_cur !== 'USD') {
        WC()->session->set('currency', 'USD');
    }
}, 9999);

// Force USD, symbol before number, no space
add_filter('woocommerce_price_format', function() { return '%1$s%2$s'; }, 9999);
add_filter('woocommerce_currency_pos', function() { return 'left'; }, 9999);
add_filter('woocommerce_price_args', function($args) {
    $args['currency'] = 'USD';
    $args['currency_pos'] = 'left';
    return $args;
}, 9999);

// WMC (Woo Multi Currency) — force USD as default
add_filter('wmc_get_price', function($price, $currency) { return $price; }, 9999, 2);
add_filter('wmc_current_currency', function() { return 'USD'; }, 9999);
add_filter('wmc_frontend_display_currency', function() { return 'USD'; }, 9999);

// Immediately fix navbar currency display before page renders
add_action('wp_head', function() { ?>
<script>
(function(){
  // Set cookies immediately — before any plugin JS reads them
  var opts = '; path=/; max-age=' + (86400 * 365);
  document.cookie = 'woocs_session_currency=USD' + opts;
  document.cookie = 'wmc_current_currency=USD' + opts;
  document.cookie = 'wmc-currency=USD' + opts;
  document.cookie = 'currency=USD' + opts;
  document.cookie = 'chosen_currency=USD' + opts;

  // Select USD in dropdowns without removing other options
  function fixNavCurrency() {
    // Fix <select> dropdowns — just change selected value, keep all options intact
    document.querySelectorAll('select[name*="currency"], select[id*="currency"], select[class*="currency"]').forEach(function(sel) {
      var usdOpt = Array.from(sel.options).find(function(o) { return o.value === 'USD' || o.text.indexOf('USD') !== -1; });
      if (usdOpt) { sel.value = usdOpt.value; usdOpt.selected = true; }
    });
    // Fix the visible label/button text (not inside <select>) — replace only the display label
    document.querySelectorAll(
      '.currency-switcher > span, .wmc-currency-wrapper > span, ' +
      '[class*="woocs"] .selected-currency, [class*="woocs"] > span, ' +
      '.wmc-switcher .current, [class*="wmc-cur"] .current'
    ).forEach(function(el) {
      if (el.children.length === 0 && (el.textContent.indexOf('INR') !== -1 || el.textContent.indexOf('₹') !== -1)) {
        el.textContent = el.textContent.replace(/₹\s*/g, '$ ').replace(/INR/g, 'USD');
      }
    });
  }
  document.addEventListener('DOMContentLoaded', fixNavCurrency);
  window.addEventListener('load', fixNavCurrency);
  // Run once immediately in case DOM is already ready
  if (document.readyState !== 'loading') fixNavCurrency();
})();
</script>
<?php }, 1);

// 3. Enable WooCommerce registration
add_action('init', function() {
    update_option('woocommerce_enable_myaccount_registration', 'yes');
    update_option('woocommerce_enable_checkout_login_reminder', 'yes');
    update_option('woocommerce_registration_generate_password', 'yes');
    update_option('woocommerce_registration_generate_username', 'no');
});

// Clear cached YouTube feed on activation (force fresh fetch after deploys)
delete_transient('af_yt_UC_GX4vXRQrN4GsvSfgmZxYw');

// 4. AJAX proxy: fetch YouTube playlist/channel RSS — no API key needed
add_action('wp_ajax_af_yt_feed',        'af_yt_feed_handler');
add_action('wp_ajax_nopriv_af_yt_feed', 'af_yt_feed_handler');
function af_yt_feed_handler() {
    $list_id    = sanitize_text_field($_GET['list']    ?? '');
    $channel_id = sanitize_text_field($_GET['channel'] ?? '');

    if ($list_id) {
        $url = 'https://www.youtube.com/feeds/videos.xml?playlist_id=' . $list_id;
    } elseif ($channel_id) {
        $url = 'https://www.youtube.com/feeds/videos.xml?channel_id=' . $channel_id;
    } else {
        wp_send_json_error('no id'); return;
    }

    $cached = get_transient('af_yt_' . ($list_id ?: $channel_id));
    if ($cached) { wp_send_json_success($cached); return; }

    $resp = wp_remote_get($url, ['timeout' => 10]);
    if (is_wp_error($resp)) { wp_send_json_error($resp->get_error_message()); return; }

    $xml = simplexml_load_string(wp_remote_retrieve_body($resp));
    if (!$xml) { wp_send_json_error('bad xml'); return; }

    $xml->registerXPathNamespace('yt', 'http://www.youtube.com/xml/schemas/2015');
    $xml->registerXPathNamespace('media', 'http://search.yahoo.com/mrss/');

    $videos = [];
    foreach ($xml->entry as $entry) {
        $yt    = $entry->children('http://www.youtube.com/xml/schemas/2015');
        $media = $entry->children('http://search.yahoo.com/mrss/');
        $vid   = (string)($yt->videoId ?? '');
        if (!$vid) {
            // fallback: parse from <id> tag like yt:video:VIDEOID
            preg_match('/video:([A-Za-z0-9_-]{11})/', (string)$entry->id, $m);
            $vid = $m[1] ?? '';
        }
        if ($vid) {
            $videos[] = [
                'id'    => $vid,
                'title' => (string)($entry->title ?? $media->group->title ?? ''),
                'thumb' => 'https://img.youtube.com/vi/' . $vid . '/hqdefault.jpg',
            ];
        }
    }

    set_transient('af_yt_' . ($list_id ?: $channel_id), $videos, 2 * HOUR_IN_SECONDS);
    wp_send_json_success($videos);
}


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

        freshCards.forEach(function(c) {
            track.appendChild(c);
            c.style.setProperty('position', 'relative', 'important');

            // Completely hide the original YITH wishlist widget — our custom button triggers it
            var yith = c.querySelector('.yith-wcwl-add-to-wishlist');
            if (yith) yith.style.setProperty('display', 'none', 'important');

            // Create our own heart button, absolutely over the image top-right
            var heart = document.createElement('button');
            heart.className = 'af-wishlist-btn';
            heart.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>';
            heart.setAttribute('aria-label', 'Add to wishlist');
            heart.style.cssText = [
                'position:absolute','top:12px','right:12px','z-index:20',
                'width:36px','height:36px','border-radius:50%',
                'background:rgba(255,255,255,0.92)','border:none','cursor:pointer',
                'display:flex','align-items:center','justify-content:center',
                'box-shadow:0 2px 8px rgba(0,0,0,0.22)','padding:0','margin:0',
                'color:#555','transition:color 0.2s,background 0.2s'
            ].join('!important;') + '!important';
            heart.querySelector('svg').style.cssText = 'width:18px!important;height:18px!important;display:block!important';
            heart.addEventListener('mouseenter', function(){ this.style.setProperty('color','#c9a84c','important'); });
            heart.addEventListener('mouseleave', function(){ this.style.setProperty('color','#555','important'); });
            heart.addEventListener('click', function(e) {
                e.preventDefault(); e.stopPropagation();
                // Click the real YITH button
                var realBtn = c.querySelector('.add_to_wishlist, .yith-wcwl-add-button a, .yith-wcwl-add-to-wishlist a');
                if (realBtn) realBtn.click();
                // Toggle filled heart
                var filled = this.dataset.wishlisted === '1';
                this.dataset.wishlisted = filled ? '0' : '1';
                this.querySelector('svg').setAttribute('fill', filled ? 'none' : '#c9a84c');
                this.style.setProperty('color', filled ? '#555' : '#c9a84c', 'important');
            });
            c.appendChild(heart);
        });
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
                var img2 = document.createElement('img');
                img2.src = 'https://img.youtube.com/vi/' + v.id + '/maxresdefault.jpg';
                img2.alt = '';
                img2.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;object-fit:cover;';
                img2.onerror = function(){ this.src='https://img.youtube.com/vi/'+v.id+'/hqdefault.jpg'; this.onerror=null; };
                circle.appendChild(img2);
                var ic2 = document.createElement('div'); ic2.className = 'af-play-icon';
                ic2.innerHTML = '<svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>';
                circle.appendChild(ic2);
                circle.addEventListener('click', function() {
                    img2.style.display='none'; ic2.style.display='none';
                    var fr2 = document.createElement('iframe');
                    fr2.src = 'https://www.youtube-nocookie.com/embed/'+v.id+'?autoplay=1&mute=0&rel=0&playsinline=1';
                    fr2.allow = 'autoplay; fullscreen; encrypted-media';
                    fr2.allowFullscreen = true;
                    fr2.style.cssText = 'position:absolute;top:50%;left:50%;width:300%;height:300%;transform:translate(-50%,-46%);border:none;';
                    circle.appendChild(fr2);
                });
            } else {
                var icon = document.createElement('div'); icon.className = 'af-play-icon';
                icon.innerHTML = '<svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>';
                circle.appendChild(icon);
            }

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

    // Extract playlist or channel ID from any element's attributes/data-settings
    function extractPlaylistOrChannel() {
        var listId = null, chanId = null;
        document.querySelectorAll('[data-settings],[data-widget_type]').forEach(function(el) {
            if (listId || chanId) return;
            var raw = el.getAttribute('data-settings') || '';
            try {
                var s = JSON.parse(raw);
                var url = s.youtube_url || s.url || s.link || '';
                var lm = url.match(/[?&]list=([A-Za-z0-9_-]+)/);
                if (lm) { listId = lm[1]; return; }
                var cm = url.match(/channel\/([A-Za-z0-9_-]+)/);
                if (cm) { chanId = cm[1]; }
            } catch(x){}
            // Also scan raw attribute value
            var lm2 = raw.match(/list=([A-Za-z0-9_-]{10,})/);
            if (!listId && lm2) listId = lm2[1];
        });
        // Also scan all attribute values on the page
        if (!listId && !chanId) {
            document.querySelectorAll('*').forEach(function(el) {
                if (listId || chanId) return;
                Array.from(el.attributes).forEach(function(a) {
                    if (listId || chanId) return;
                    var lm = a.value.match(/[?&]list=([A-Za-z0-9_-]{10,})/);
                    if (lm) { listId = lm[1]; return; }
                    var cm = a.value.match(/youtube\.com\/channel\/([A-Za-z0-9_-]+)/);
                    if (cm) chanId = cm[1];
                });
            });
        }
        return { listId: listId, chanId: chanId };
    }

    function fetchAndBuild(anchor) {
        if (anchor.dataset.afVidDone) return;
        var ids = extractPlaylistOrChannel();
        if (!ids.listId && !ids.chanId) { buildSlider(); return; }

        anchor.dataset.afVidDone = '1';
        var params = ids.listId ? 'list=' + ids.listId : 'channel=' + ids.chanId;
        var ajaxUrl = (typeof af_ajax !== 'undefined' ? af_ajax.url : '/wp-admin/admin-ajax.php')
                    + '?action=af_yt_feed&' + params;

        fetch(ajaxUrl)
            .then(function(r){ return r.json(); })
            .then(function(data) {
                if (!data.success || !data.data.length) return;
                buildFromVideoList(anchor, data.data);
            })
            .catch(function(){ buildSlider(); });
    }

    function buildFromVideoList(anchor, videos) {
        var shell = document.createElement('div'); shell.className = 'af-vid-shell';
        var btnP  = document.createElement('button'); btnP.className = 'af-vid-btn'; btnP.innerHTML = '&#8249;'; btnP.setAttribute('aria-label','Prev');
        var btnN  = document.createElement('button'); btnN.className = 'af-vid-btn'; btnN.innerHTML = '&#8250;'; btnN.setAttribute('aria-label','Next');
        var vp    = document.createElement('div'); vp.className = 'af-vid-vp';
        var track = document.createElement('div'); track.className = 'af-vid-track';

        // Listen for YouTube postMessage errors (150/101 = embedding disabled)
        window.addEventListener('message', function(e) {
            try {
                var d = typeof e.data === 'string' ? JSON.parse(e.data) : e.data;
                if (d && d.event === 'onError' && (d.info === 150 || d.info === 101 || d.info === 2)) {
                    // Find which iframe sent it and show its thumbnail instead
                    document.querySelectorAll('.af-vid-circle iframe').forEach(function(fr) {
                        try { if (fr.contentWindow === e.source) {
                            fr.style.display = 'none';
                            var th = fr.parentElement.querySelector('.af-vid-thumb');
                            var ic = fr.parentElement.querySelector('.af-play-icon');
                            if (th) th.style.display = 'block';
                            if (ic) ic.style.display = 'flex';
                        }} catch(x){}
                    });
                }
            } catch(x){}
        });

        var circles = videos.map(function(v) {
            var circle = document.createElement('div'); circle.className = 'af-vid-circle';

            // Autoplay muted iframe — loads immediately
            var fr = document.createElement('iframe');
            fr.src = 'https://www.youtube-nocookie.com/embed/' + v.id
                   + '?autoplay=1&mute=1&loop=1&playlist=' + v.id
                   + '&rel=0&playsinline=1&enablejsapi=1&origin=' + encodeURIComponent(location.origin);
            fr.allow = 'autoplay; encrypted-media';
            fr.setAttribute('frameborder','0');
            fr.style.cssText = 'position:absolute;top:50%;left:50%;width:300%;height:300%;transform:translate(-50%,-46%);border:none;pointer-events:none;z-index:1;';
            circle.appendChild(fr);

            // Fallback thumbnail (shown if embedding is blocked)
            var img = document.createElement('img');
            img.className = 'af-vid-thumb';
            img.src = v.thumb || ('https://img.youtube.com/vi/' + v.id + '/hqdefault.jpg');
            img.alt = v.title || '';
            img.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:none;z-index:2;';
            img.onerror = function(){ this.src='https://img.youtube.com/vi/'+v.id+'/hqdefault.jpg'; this.onerror=null; };
            circle.appendChild(img);

            // Play icon (shown over thumbnail)
            var icon = document.createElement('div'); icon.className = 'af-play-icon';
            icon.innerHTML = '<svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>';
            icon.style.cssText = 'display:none;z-index:3;position:relative;';
            circle.appendChild(icon);

            // Click overlay — opens full video with sound
            var ov = document.createElement('div');
            ov.style.cssText = 'position:absolute;inset:0;z-index:10;cursor:pointer;';
            ov.addEventListener('click', function() {
                window.open('https://www.youtube.com/watch?v=' + v.id, '_blank');
            });
            circle.appendChild(ov);

            track.appendChild(circle); return circle;
        });

        vp.appendChild(track);
        shell.appendChild(btnP); shell.appendChild(vp); shell.appendChild(btnN);
        anchor.insertAdjacentElement('afterend', shell);
        anchor.classList.add('af-vid-original-hidden');

        var idx = 0;
        function iw2() { return window.innerWidth<=480?SIZES.sm:window.innerWidth<=768?SIZES.md:SIZES.lg; }
        function vis2() { var vpW=vp.getBoundingClientRect().width||800; return Math.max(1,Math.floor((vpW+GAP)/(iw2()+GAP))); }
        function go2(n) {
            var v2=vis2(), max=Math.max(0,circles.length-v2);
            idx=Math.max(0,Math.min(n,max));
            track.style.setProperty('transform','translateX('+(-(idx*(iw2()+GAP)))+'px)','important');
        }
        btnP.onclick=function(){go2(idx-vis2());}; btnN.onclick=function(){go2(idx+vis2());};
        window.addEventListener('resize',function(){idx=0;go2(0);});
        setTimeout(function(){go2(0);},200);
    }

    // Collect all individual YouTube video IDs already in the DOM
    function collectDomVideoIds() {
        var ids = [], seen = {};
        document.querySelectorAll('[data-settings]').forEach(function(el) {
            try {
                var s = JSON.parse(el.getAttribute('data-settings') || '{}');
                var url = s.youtube_url || s.url || s.link || '';
                var id = ytId(url);
                if (id && !seen[id]) { seen[id] = 1; ids.push(id); }
            } catch(x){}
        });
        // Also scan iframes
        document.querySelectorAll('iframe').forEach(function(f) {
            var id = ytId(f.src || f.getAttribute('data-lazy-src') || '');
            if (id && !seen[id]) { seen[id] = 1; ids.push(id); }
        });
        // Scan all attributes for youtube URLs
        document.querySelectorAll('.circle-gallery-slider *,[class*="video-circle"] *,.elementor-widget-video *').forEach(function(el) {
            Array.from(el.attributes).forEach(function(a) {
                if (/youtu/i.test(a.value)) {
                    var id = ytId(a.value);
                    if (id && !seen[id]) { seen[id] = 1; ids.push(id); }
                }
            });
        });
        return ids;
    }

    function tryBuild() {
        var anchor = document.querySelector('.circle-gallery-slider')
                  || (document.querySelector('.video-circle') && document.querySelector('.video-circle').parentElement)
                  || (function(){
                        var s=null;
                        document.querySelectorAll('.elementor-section,.e-container').forEach(function(el){
                            if(!s && el.querySelectorAll('.elementor-widget-video').length>=1) s=el;
                        });
                        return s;
                     })()
                  || document.querySelector('.elementor-widget-video');

        if (!anchor || anchor.dataset.afVidDone) return;
        anchor.dataset.afVidDone = '1';

        // Always fetch from hardcoded channel — guaranteed to get all videos
        var ajaxUrl = (typeof af_ajax !== 'undefined' ? af_ajax.url : '/wp-admin/admin-ajax.php')
                    + '?action=af_yt_feed&channel=UC_GX4vXRQrN4GsvSfgmZxYw';

        fetch(ajaxUrl)
            .then(function(r){ return r.json(); })
            .then(function(data) {
                if (data.success && data.data.length) {
                    buildFromVideoList(anchor, data.data);
                }
            })
            .catch(function(){ });
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
                // Use background-image — background-size:cover fills circle perfectly, no black bars
                var thumbHq  = 'https://img.youtube.com/vi/' + v.id + '/hqdefault.jpg';
                var thumbMax = 'https://img.youtube.com/vi/' + v.id + '/maxresdefault.jpg';
                circle.style.cssText += 'background-image:url(' + thumbMax + ');background-size:cover;background-position:center center;';
                // Fallback: if maxres 404s, swap to hqdefault via a probe image
                var probe = new Image();
                probe.onload = function() {
                    if (probe.naturalWidth < 100) {
                        circle.style.backgroundImage = 'url(' + thumbHq + ')';
                    }
                };
                probe.onerror = function() { circle.style.backgroundImage = 'url(' + thumbHq + ')'; };
                probe.src = thumbMax;

                // Gold play icon overlay
                var icon = document.createElement('div'); icon.className = 'af-play-icon';
                icon.innerHTML = '<svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>';
                circle.appendChild(icon);

                // Click: hide bg + play icon, show autoplay iframe
                circle.addEventListener('click', function() {
                    circle.style.backgroundImage = 'none';
                    icon.style.display = 'none';
                    var fr = document.createElement('iframe');
                    fr.src = 'https://www.youtube-nocookie.com/embed/' + v.id + '?autoplay=1&mute=0&rel=0&playsinline=1';
                    fr.allow = 'autoplay; fullscreen; encrypted-media';
                    fr.allowFullscreen = true;
                    fr.style.cssText = 'position:absolute;top:50%;left:50%;width:300%;height:300%;transform:translate(-50%,-46%);border:none;';
                    circle.appendChild(fr);
                });
            } else {
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
<?php }, 10002);

// Shortcode: [af_features] — place in any Elementor HTML widget
add_shortcode('af_features', function() {
    return '<div class="af-features-bar"></div>';
});

/* ============================================================
   Section 12: Feature Icons Bar with slide-up overlays
   ============================================================ */
add_action('wp_footer', function() { ?>
<div class="af-feat-overlay" id="afFeatOverlay"></div>
<div class="af-feat-sheet" id="afFeatSheet">
  <div class="af-feat-sheet-handle"></div>
  <button class="af-feat-sheet-close" id="afFeatClose">&times;</button>
  <h3 id="afFeatTitle"></h3>
  <div id="afFeatBody"></div>
</div>
<script>
(function(){
  var features = [
    {
      label: 'Free Shipping',
      icon: '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M20 8h-3V4H3c-1.1 0-2 .9-2 2v11h2c0 1.66 1.34 3 3 3s3-1.34 3-3h6c0 1.66 1.34 3 3 3s3-1.34 3-3h2v-5l-3-4zM6 18.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm13.5-9l1.96 2.5H17V9.5h2.5zm-1.5 9c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/></svg>',
      title: '🚚 Free Shipping Available!',
      body: '<p>Enjoy fast, safe, and reliable delivery with guaranteed on-time service.</p><h4>📍 Shipping Available In:</h4><ul><li>New Jersey</li><li>Pennsylvania</li><li>Philadelphia</li></ul><h4>🎁 Free Delivery In:</h4><ul><li>Delaware</li><li>Pennsylvania</li><li>Maryland</li><li>New Jersey &amp; nearby areas</li></ul>'
    },
    {
      label: 'High Resolution',
      icon: '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14zm-5-7h-2V8h-2v4H8v2h2v4h2v-4h2v-2z"/></svg>',
      title: '🖨️ High Resolution Printing',
      body: '<p>We use professional-grade printing technology to deliver crisp, vibrant, museum-quality prints.</p><h4>✅ Features:</h4><ul><li>Up to 1200 DPI resolution</li><li>Fade-resistant inks</li><li>True-to-life color accuracy</li><li>UV protective coating available</li></ul>'
    },
    {
      label: 'Premium Frames',
      icon: '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M20 2H4c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 18H4V4h16v16zM6 6h12v12H6z"/></svg>',
      title: '🛡️ Premium Frames',
      body: '<p>Handcrafted frames built to last — because great art deserves a great frame.</p><h4>✅ Frame Options:</h4><ul><li>Solid wood &amp; metal frames</li><li>Custom sizing available</li><li>Floater frames for canvas</li><li>Gallery-wrap ready</li></ul><h4>🎨 Styles:</h4><ul><li>Modern, Classic, Rustic, Minimalist</li></ul>'
    },
    {
      label: 'Secure Payment',
      icon: '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M20 4H4c-1.11 0-2 .89-2 2v12c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/></svg>',
      title: '🔒 Secure Payment',
      body: '<p>Your payment information is always safe with our encrypted checkout.</p><h4>✅ We Accept:</h4><ul><li>Visa, MasterCard, AMEX</li><li>PayPal</li><li>Google Pay &amp; Apple Pay</li><li>Bank Transfer</li></ul><h4>🔐 Security:</h4><ul><li>256-bit SSL encryption</li><li>PCI DSS compliant</li><li>No card data stored</li></ul>'
    }
  ];

  function buildBar() {
    var bars = document.querySelectorAll('.af-features-bar');
    if (!bars.length) return;
    bars.forEach(function(bar) {
      if (bar.dataset.afBuilt) return;
      bar.dataset.afBuilt = '1';
      features.forEach(function(f, i) {
        var item = document.createElement('div');
        item.className = 'af-feature-item';
        var iconDiv = document.createElement('div');
        iconDiv.className = 'af-feature-icon';
        iconDiv.innerHTML = f.icon;
        var label = document.createElement('div');
        label.className = 'af-feature-label';
        label.textContent = f.label;
        item.appendChild(iconDiv);
        item.appendChild(label);
        item.addEventListener('click', function() { openSheet(i); });
        bar.appendChild(item);
      });
    });
  }

  var overlay = document.getElementById('afFeatOverlay');
  var sheet   = document.getElementById('afFeatSheet');
  var closeBtn= document.getElementById('afFeatClose');
  var titleEl = document.getElementById('afFeatTitle');
  var bodyEl  = document.getElementById('afFeatBody');

  function openSheet(i) {
    titleEl.innerHTML = features[i].title;
    bodyEl.innerHTML  = features[i].body;
    overlay.classList.add('active');
    requestAnimationFrame(function(){ sheet.classList.add('open'); });
  }
  function closeSheet() {
    sheet.classList.remove('open');
    setTimeout(function(){ overlay.classList.remove('active'); }, 350);
  }
  if (closeBtn) closeBtn.addEventListener('click', closeSheet);
  if (overlay)  overlay.addEventListener('click', closeSheet);

  document.addEventListener('DOMContentLoaded', buildBar);
  window.addEventListener('load', buildBar);
}());
</script>
<script>
(function(){
  function styleIcon(iconEl) {
    // Walk up to find the best wrapper to turn into a gold circle
    var wrap = iconEl.closest('.elementor-icon') || iconEl.parentElement;
    wrap.style.setProperty('width', '56px', 'important');
    wrap.style.setProperty('height', '56px', 'important');
    wrap.style.setProperty('min-width', '56px', 'important');
    wrap.style.setProperty('border-radius', '50%', 'important');
    wrap.style.setProperty('background', '#c9a84c', 'important');
    wrap.style.setProperty('display', 'flex', 'important');
    wrap.style.setProperty('align-items', 'center', 'important');
    wrap.style.setProperty('justify-content', 'center', 'important');
    wrap.style.setProperty('margin', '0 auto 6px', 'important');
    wrap.style.setProperty('flex-shrink', '0', 'important');
    iconEl.style.setProperty('color', '#fff', 'important');
    iconEl.style.setProperty('fill', '#fff', 'important');
    iconEl.style.setProperty('font-size', '24px', 'important');
    iconEl.style.setProperty('width', '24px', 'important');
    iconEl.style.setProperty('height', '24px', 'important');
    iconEl.style.setProperty('line-height', '1', 'important');
  }

  function findFeaturesSec() {
    // Try custom class first, then fall back to: mobile-only e-con that contains icon widgets
    var sec = document.querySelector('.features-section');
    if (sec) return sec;
    // Find e-con sections visible on mobile (not hidden on mobile) containing elementor-icon widgets
    var candidates = document.querySelectorAll('.e-con-full.e-con:not(.elementor-hidden-mobile):not(.elementor-hidden-mobile_extra)');
    for (var i = 0; i < candidates.length; i++) {
      if (candidates[i].querySelectorAll('.elementor-widget-icon, .elementor-icon').length >= 3) return candidates[i];
    }
    return null;
  }

  function fixFeaturesSection() {
    var sec = findFeaturesSec();
    if (!sec || sec.dataset.fsFixed) return;
    sec.dataset.fsFixed = '1';

    // Find ALL icon widgets inside the section
    var iconEls = sec.querySelectorAll('.elementor-icon i, .elementor-icon svg');
    iconEls.forEach(function(iconEl) { styleIcon(iconEl); });

    // Find ALL label texts
    sec.querySelectorAll('.elementor-icon-box-title, .elementor-heading-title, .elementor-widget-container > p').forEach(function(label) {
      label.style.setProperty('font-size', '11px', 'important');
      label.style.setProperty('font-weight', '600', 'important');
      label.style.setProperty('color', '#222', 'important');
      label.style.setProperty('text-align', 'center', 'important');
      label.style.setProperty('line-height', '1.3', 'important');
      label.style.setProperty('margin', '0', 'important');
    });

    // Force EVERY .e-con-inner and .e-con inside features-section to row
    sec.querySelectorAll('.e-con-inner, .e-con').forEach(function(el) {
      el.style.setProperty('flex-direction', 'row', 'important');
      el.style.setProperty('flex-wrap', 'nowrap', 'important');
      el.style.setProperty('justify-content', 'space-evenly', 'important');
      el.style.setProperty('align-items', 'flex-start', 'important');
      el.style.setProperty('gap', '4px', 'important');
    });

    // Style each direct elementor widget as a flex column item
    sec.querySelectorAll('.elementor-element').forEach(function(item) {
      item.style.setProperty('flex', '1 1 0', 'important');
      item.style.setProperty('min-width', '0', 'important');
      item.style.setProperty('max-width', '90px', 'important');
      item.style.setProperty('text-align', 'center', 'important');
    });
  }

  document.addEventListener('DOMContentLoaded', fixFeaturesSection);
  window.addEventListener('load', fixFeaturesSection);
}());
</script>
<?php }, 10003);
