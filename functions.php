<?php
/**
 * Postero Child Theme - functions.php
 * The Art Framer - theartframer.us
 */

// 1. Enqueue parent and child theme styles
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('postero-parent', get_template_directory_uri() . '/style.css');
    wp_enqueue_style('postero-child', get_stylesheet_uri(), array('postero-parent'), '1.0.0');
    wp_enqueue_style('postero-child-custom', get_stylesheet_directory_uri() . '/assets/css/custom.css', array('postero-child'), '2.8.0');
    wp_enqueue_script('postero-child-custom-js', get_stylesheet_directory_uri() . '/assets/js/custom.js', array('jquery'), '1.3.1', true);
    wp_localize_script('postero-child-custom-js', 'af_ajax', array('url' => admin_url('admin-ajax.php')));
}, 20);

// 1b. Tag Sign Up / Login / user-icon nav items with CSS classes (server-side, reliable)
add_filter('nav_menu_css_class', function($classes, $item) {
    if (is_user_logged_in()) return $classes;
    $title = strtolower(trim(strip_tags($item->title)));
    $url   = strtolower($item->url);
    if (preg_match('/sign.?up|register/i', $title)) {
        $classes[] = 'af-nav-signup';
        $classes[] = 'af-nav-acc-hide';
    } elseif (preg_match('/^log\s*in$/i', $title) || preg_match('/^login$/i', $title)) {
        $classes[] = 'af-nav-login';
        $classes[] = 'af-nav-acc-hide';
    } elseif (preg_match('/my-account|account/i', $url) && !preg_match('/sign|register|login/i', $title . $url)) {
        $classes[] = 'af-nav-user-icon';
    } elseif (preg_match('/account|user|person/i', $title) && !preg_match('/sign|register|login/i', $title)) {
        $classes[] = 'af-nav-user-icon';
    }
    return $classes;
}, 10, 2);

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

// Clear cached YouTube feeds on deploys
delete_transient('af_yt_UC_GX4vXRQrN4GsvSfgmZxYw');
delete_transient('af_yt_ids_UC_GX4vXRQrN4GsvSfgmZxYw');

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

// Add body class for front page (works for both static and blog front page)
add_filter('body_class', function($classes) {
    if (is_front_page()) $classes[] = 'af-front-page';
    return $classes;
});

// 5a. Force Elementor footer template to show on all pages (not just home)
add_action('init', function() {
    if (!class_exists('\Elementor\Plugin')) return;
    $done = get_option('af_footer_condition_fixed', false);
    if ($done) return;

    $footers = get_posts([
        'post_type'      => 'elementor_library',
        'posts_per_page' => -1,
        'meta_query'     => [[
            'key'   => '_elementor_template_type',
            'value' => 'footer',
        ]],
    ]);

    foreach ($footers as $footer) {
        $conditions = get_post_meta($footer->ID, '_elementor_conditions', true);
        if (!is_array($conditions)) $conditions = [];
        // Check if "entire site" condition already exists
        $has_global = in_array('include/general//', $conditions, true);
        if (!$has_global) {
            // Replace any narrow conditions with "entire site"
            $new_conditions = ['include/general//'];
            // Keep any explicit exclude conditions
            foreach ($conditions as $c) {
                if (strpos($c, 'exclude') === 0) $new_conditions[] = $c;
            }
            update_post_meta($footer->ID, '_elementor_conditions', $new_conditions);
        }
    }

    // Clear Elementor conditions cache so the change takes effect immediately
    delete_option('elementor_pro_theme_builder_conditions');
    if (class_exists('\ElementorPro\Modules\ThemeBuilder\Classes\Conditions_Manager')) {
        delete_transient('elementor_pro_conditions_cache');
    }
    update_option('af_footer_condition_fixed', true);
}, 20);

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

    var _initDone = false;
    function init() {
        if (_initDone) return;
        // Primary target: #subcategorySlider (confirmed display:grid, the actual circles container)
        var slider = document.getElementById('subcategorySlider');
        if (slider) { fixEl(slider); _initDone = true; }

        // Fallback: UL with cat-items
        var catItems = document.querySelectorAll('li.cat-item');
        if (catItems.length && catItems[0].parentElement) {
            fixEl(catItems[0].parentElement);
            _initDone = true;
        }
        if (!_initDone) return; // nothing found yet, allow retry

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
    align-items: stretch !important;  /* all cards same height */
    margin: 0 !important;
    padding: 4px 0 12px !important;
    list-style: none !important;
    transition: transform 0.4s ease !important;
    will-change: transform !important;
}
.af-shell-track .product-card {
    height: 100% !important;  /* fill the stretched row */
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

        function sp(el, p, v) { el.style.setProperty(p, v, 'important'); }

        function styleCard(c) {
            // ── Card shell: flex column, full height so all cards in a row stretch equally ──
            sp(c,'background',    '#fff');
            sp(c,'border',        '1px solid #e8e8e8');
            sp(c,'border-radius', '12px');
            sp(c,'overflow',      'hidden');
            sp(c,'display',       'flex');
            sp(c,'flex-direction','column');
            sp(c,'height',        '100%');   // stretch to fill track cell
            sp(c,'position',      'relative');
            sp(c,'box-shadow',    '0 2px 12px rgba(0,0,0,.10)');
            sp(c,'margin',        '0');

            // ── Image wrapper — padding-bottom 75% = reliable 4:3 for every card ──
            var imgWrap = c.querySelector('.product-image, .image-wrapper, .woocommerce-loop-product__link');
            if (imgWrap) {
                sp(imgWrap,'display',        'block');
                sp(imgWrap,'position',       'relative');
                sp(imgWrap,'width',          '100%');
                sp(imgWrap,'height',         '0');
                sp(imgWrap,'padding-bottom', '75%');
                sp(imgWrap,'overflow',       'hidden');
                sp(imgWrap,'flex-shrink',    '0');
                sp(imgWrap,'background',     '#f0f0f0');
                imgWrap.querySelectorAll('img').forEach(function(i){
                    sp(i,'position',   'absolute');
                    sp(i,'inset',      '0');
                    sp(i,'width',      '100%');
                    sp(i,'height',     '100%');
                    sp(i,'object-fit', 'cover');
                    sp(i,'display',    'block');
                });
            }

            // ── product-info: consistent container padding ──
            var info = c.querySelector('.product-info');
            if (info) {
                sp(info,'display',        'flex');
                sp(info,'flex-direction', 'column');
                sp(info,'flex',           '1 1 auto');
                sp(info,'padding',        '12px 14px 0');
                sp(info,'margin',         '0');
                sp(info,'min-height',     '0');
                // Zero individual element padding so container is the only source of spacing
                info.querySelectorAll('.product-title,.rating,.rating-count,.price-section,.desc,.woocommerce-loop-product__title,.woocommerce-product-rating,.price,h2,h3').forEach(function(el) {
                    sp(el,'padding-left',  '0');
                    sp(el,'padding-right', '0');
                });
            }

            // ── Title: exactly 3-line height so all cards align below ──
            var title = c.querySelector('.product-title, h2, h3, .woocommerce-loop-product__title');
            if (title) {
                sp(title,'font-size',          '13.5px');
                sp(title,'font-weight',        '700');
                sp(title,'line-height',        '1.45');
                sp(title,'color',              '#1a1a1a');
                sp(title,'margin',             '0 0 8px');
                sp(title,'padding',            '0');
                sp(title,'display',            '-webkit-box');
                sp(title,'-webkit-line-clamp', '3');
                sp(title,'-webkit-box-orient', 'vertical');
                sp(title,'overflow',           'hidden');
                sp(title,'height',             'calc(1.45em * 3)');
                sp(title,'flex-shrink',        '0');
            }

            // ── Rating row ──
            var rating = c.querySelector('.rating, .woocommerce-product-rating, .product-meta-row');
            if (rating) {
                sp(rating,'display',     'flex');
                sp(rating,'align-items', 'center');
                sp(rating,'flex-wrap',   'nowrap');
                sp(rating,'gap',         '4px');
                sp(rating,'margin',      '0 0 4px');
                sp(rating,'padding',     '0');
                sp(rating,'line-height', '1.4');
                sp(rating,'height',      '22px');
                sp(rating,'overflow',    'hidden');
                sp(rating,'flex-shrink', '0');
            }
            var ratingCount = c.querySelector('.rating-count');
            if (ratingCount) {
                sp(ratingCount,'font-size',   '12px');
                sp(ratingCount,'color',       '#555');
                sp(ratingCount,'margin',      '0');
                sp(ratingCount,'padding',     '0');
                sp(ratingCount,'white-space', 'nowrap');
                sp(ratingCount,'flex-shrink', '0');
            }
            var stars = c.querySelector('.star-rating');
            if (stars) { sp(stars,'color','#c9a84c'); sp(stars,'font-size','12px'); sp(stars,'margin','0'); sp(stars,'padding','0'); sp(stars,'flex-shrink','0'); }

            // ── Price ──
            var price = c.querySelector('.price-section, .price, .product-price');
            if (price) {
                sp(price,'display',     'flex');
                sp(price,'flex-direction','row');
                sp(price,'flex-wrap',   'nowrap');
                sp(price,'align-items', 'center');
                sp(price,'gap',         '6px');
                sp(price,'font-size',   '14px');
                sp(price,'font-weight', '700');
                sp(price,'color',       '#1a1a1a');
                sp(price,'margin',      '0 0 6px');
                sp(price,'padding',     '0');
                sp(price,'min-height',  '22px');
                sp(price,'overflow',    'visible');
                sp(price,'flex-shrink', '0');
                sp(price,'white-space', 'nowrap');
            }
            var ins = c.querySelector('.price-section ins, .price ins');
            if (ins) { sp(ins,'display','inline-block'); sp(ins,'text-decoration','none'); sp(ins,'font-weight','700'); sp(ins,'color','#1a1a1a'); sp(ins,'font-size','15px'); }
            var del = c.querySelector('.price-section del, .price del, .old-price');
            if (del) {
                sp(del,'display','inline-block'); sp(del,'position','relative');
                sp(del,'color','#999'); sp(del,'font-weight','400'); sp(del,'font-size','13px');
                sp(del,'text-decoration','line-through'); sp(del,'text-decoration-color','#999'); sp(del,'text-decoration-thickness','1.5px');
                del.querySelectorAll('*').forEach(function(el){
                    sp(el,'color','#999'); sp(el,'text-decoration','line-through'); sp(el,'font-size','13px');
                });
            }

            var discount = c.querySelector('.price-section .discount, .discount-percentage, span.discount');
            if (discount) {
                sp(discount,'color','#4caf2f');
                sp(discount,'font-weight','600');
                sp(discount,'font-size','13px');
                discount.querySelectorAll('*').forEach(function(el){ sp(el,'color','#4caf2f'); });
            }

            // ── Description: 2-line clamp ──
            var desc = c.querySelector('.desc, p.desc, .product-excerpt, .short-description');
            if (desc) {
                sp(desc,'font-size',          '13px');
                sp(desc,'color',              '#555');
                sp(desc,'line-height',        '1.55');
                sp(desc,'margin',             '0 0 12px');
                sp(desc,'padding',            '0');
                sp(desc,'display',            '-webkit-box');
                sp(desc,'-webkit-line-clamp', '2');
                sp(desc,'-webkit-box-orient', 'vertical');
                sp(desc,'overflow',           'hidden');
                sp(desc,'height',             'calc(1.55em * 2)');
                sp(desc,'flex',               '0 0 auto');
            }

            // ── Spacer: fills remaining space to push buttons to bottom ──
            var spacer = c.querySelector('.product-spacer');
            if (!spacer) {
                spacer = document.createElement('div');
                spacer.className = 'product-spacer';
                var actionsEl = c.querySelector('.product-actions,.card-actions');
                if (actionsEl && actionsEl.parentNode) actionsEl.parentNode.insertBefore(spacer, actionsEl);
                else if (info) info.appendChild(spacer);
            }
            sp(spacer,'flex','1 1 auto');

            // ── Buttons row: always at bottom ──
            var actions = c.querySelector('.product-actions,.card-actions,.add-to-cart-wrap,.buttons-wrap,.product-buttons');
            if (actions) {
                sp(actions,'display',     'flex');
                sp(actions,'gap',         '8px');
                sp(actions,'padding',     '0 0 14px');
                sp(actions,'margin-top',  '0');
                sp(actions,'flex-shrink', '0');
                Array.from(actions.children).forEach(function(btn) {
                    sp(btn,'flex',            '1 1 50%');
                    sp(btn,'min-width',       '0');
                    sp(btn,'border',          'none');
                    sp(btn,'border-radius',   '7px');
                    sp(btn,'font-size',       '13px');
                    sp(btn,'font-weight',     '600');
                    sp(btn,'padding',         '11px 6px');
                    sp(btn,'cursor',          'pointer');
                    sp(btn,'display',         'flex');
                    sp(btn,'align-items',     'center');
                    sp(btn,'justify-content', 'center');
                    sp(btn,'gap',             '5px');
                    sp(btn,'white-space',     'nowrap');
                    sp(btn,'text-decoration', 'none');
                    sp(btn,'line-height',     '1');
                    var cls = btn.className || '';
                    if (/add.cart|add_to_cart/i.test(cls)) {
                        sp(btn,'background','#c9a84c'); sp(btn,'color','#fff');
                    } else {
                        sp(btn,'background','#1a1a1a'); sp(btn,'color','#fff');
                    }
                });
            }
        }


        // Log first card structure once to console so we can verify selectors
        if (freshCards.length && !window._afCardLogged) {
            window._afCardLogged = true;
            console.log('[AF] product-card HTML:', freshCards[0].outerHTML.substring(0, 2000));
        }

        freshCards.forEach(function(c) {
            track.appendChild(c);
            styleCard(c);

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

        // Watch every rating row — if WooCommerce JS re-sets padding, instantly clear it
        // Disconnect before mutating to prevent observer→mutate→observer infinite loop
        var ratingMo = new MutationObserver(function() {
            ratingMo.disconnect();
            track.querySelectorAll('.rating,.woocommerce-product-rating,.product-meta-row').forEach(function(r) {
                r.style.setProperty('padding', '0', 'important');
            });
            track.querySelectorAll('.rating,.woocommerce-product-rating,.product-meta-row').forEach(function(r) {
                ratingMo.observe(r, { attributes: true, attributeFilter: ['style'] });
            });
        });
        function zeroRatingPadding() {
            track.querySelectorAll('.rating,.woocommerce-product-rating,.product-meta-row').forEach(function(r) {
                r.style.setProperty('padding', '0', 'important');
            });
        }
        zeroRatingPadding();
        [200, 600, 1200].forEach(function(d){ setTimeout(zeroRatingPadding, d); });
        track.querySelectorAll('.rating,.woocommerce-product-rating,.product-meta-row').forEach(function(r) {
            ratingMo.observe(r, { attributes: true, attributeFilter: ['style'] });
        });

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

    var _sliderInitDone = false;
    function init() {
        var container = document.querySelector('.product-container');
        if (!container) return;

        var grid = container.querySelector('#productGrid') || container.querySelector('.product-slider');
        if (!grid) return;

        if (_sliderInitDone) return; // observer already attached, skip
        _sliderInitDone = true;

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

// Product card override — injected late in <head> to beat all theme/plugin CSS
add_action('wp_head', function() { ?>
<style id="af-card-override">
html body .product-card,
html body .af-shell-track .product-card,
html body .product-slider .product-card {
  background:#fff !important;
  border:1px solid #e8e8e8 !important;
  border-radius:12px !important;
  overflow:hidden !important;
  display:flex !important;
  flex-direction:column !important;
  position:relative !important;
  box-shadow:0 3px 14px rgba(0,0,0,.09) !important;
  transition:box-shadow .25s,transform .25s !important;
}
html body .product-card:hover,
html body .af-shell-track .product-card:hover,
html body .product-slider .product-card:hover {
  box-shadow:0 8px 28px rgba(0,0,0,.15) !important;
  transform:translateY(-3px) !important;
}

/* image */
html body .product-card .product-image,
html body .product-card .image-wrapper,
html body .product-card .woocommerce-loop-product__link {
  display:block !important;
  width:100% !important;
  aspect-ratio:4/3 !important;
  overflow:hidden !important;
  position:relative !important;
  background:#f4f4f4 !important;
  border-radius:0 !important;
  flex-shrink:0 !important;
}
html body .product-card .product-image img,
html body .product-card .image-wrapper img,
html body .product-card .woocommerce-loop-product__link img {
  width:100% !important; height:100% !important;
  object-fit:cover !important; display:block !important;
  transition:transform .4s !important;
}
html body .product-card:hover .product-image img,
html body .product-card:hover .image-wrapper img { transform:scale(1.05) !important; }

/* SALE ribbon */
html body .product-card .onsale,
html body .product-card .sale-ribbon {
  position:absolute !important;
  top:20px !important; left:-26px !important;
  z-index:10 !important;
  background:#c9a84c !important; color:#fff !important;
  font-size:11px !important; font-weight:800 !important;
  padding:5px 38px !important;
  text-transform:uppercase !important; letter-spacing:.08em !important;
  transform:rotate(-45deg) !important;
  box-shadow:0 2px 6px rgba(0,0,0,.22) !important;
  min-width:110px !important; text-align:center !important;
  line-height:1.4 !important; border-radius:0 !important; margin:0 !important;
}

/* wishlist */
html body .product-card .af-wishlist-btn {
  position:absolute !important;
  top:10px !important; right:10px !important;
  z-index:20 !important;
  width:36px !important; height:36px !important;
  border-radius:50% !important;
  background:rgba(255,255,255,.95) !important;
  border:none !important; cursor:pointer !important;
  display:flex !important; align-items:center !important; justify-content:center !important;
  box-shadow:0 1px 5px rgba(0,0,0,.18) !important; padding:0 !important;
}

/* content area */
html body .product-card .product-info {
  display:flex !important; flex-direction:column !important;
  flex:1 1 auto !important; padding:0 !important;
}

/* title */
html body .product-card h2,
html body .product-card h3,
html body .product-card .woocommerce-loop-product__title,
html body .product-card .product-title {
  font-size:13.5px !important; font-weight:700 !important;
  line-height:1.45 !important; color:#1a1a1a !important;
  margin:0 0 6px !important; padding:0 !important;
  display:-webkit-box !important;
  -webkit-line-clamp:3 !important; -webkit-box-orient:vertical !important;
  overflow:hidden !important; min-height:calc(1.45em * 3) !important;
}

/* meta row */
html body .product-card .woocommerce-product-rating,
html body .product-card .product-meta-row,
html body .product-card .rating {
  display:flex !important; align-items:center !important;
  flex-wrap:wrap !important; gap:5px !important;
  margin:0 0 5px !important; padding:0 !important;
}
html body .product-card .star-rating { font-size:12px !important; color:#c9a84c !important; margin:0 !important; padding:0 !important; }
html body .product-card .woocommerce-review-link { font-size:11px !important; color:#666 !important; }

/* price */
html body .product-card .price,
html body .product-card .price-section {
  display:flex !important; flex-direction:row !important; flex-wrap:nowrap !important;
  align-items:center !important; gap:4px !important; white-space:nowrap !important;
  font-size:14px !important; font-weight:700 !important; color:#1a1a1a !important;
  margin:0 0 6px !important; padding:0 !important;
}
html body .product-card .price ins { text-decoration:none !important; font-weight:700 !important; color:#1a1a1a !important; }
html body .product-card .price del,
html body .product-card .price del * { color:#999 !important; font-weight:400 !important; font-size:12px !important; }
html body .product-card .price del { text-decoration:line-through !important; }
html body .product-card .discount-percentage,
html body .product-card .price-section .discount,
html body .price-section .discount { font-size:12px !important; color:#c9a84c !important; font-weight:600 !important; }

/* description */
html body .product-card p.desc,
html body .product-card .desc {
  font-size:13px !important; color:#666 !important; line-height:1.55 !important;
  margin:0 0 10px !important; padding:0 !important;
  display:-webkit-box !important;
  -webkit-line-clamp:2 !important; -webkit-box-orient:vertical !important;
  overflow:hidden !important; min-height:calc(1.55em * 2) !important;
  flex:1 1 auto !important;
}

/* buttons */
html body .product-card .product-actions,
html body .product-card .card-actions {
  display:flex !important; gap:8px !important;
  padding:0 14px 14px !important; margin-top:auto !important;
}
html body .product-card .product-actions > * { flex:1 1 50% !important; min-width:0 !important; }

/* Add to Cart */
html body .product-card .add-cart,
html body .product-card .add_to_cart_button,
html body .product-card a.button,
html body .product-card button.button {
  background:#c9a84c !important; color:#fff !important;
  border:none !important; border-radius:7px !important;
  font-size:13px !important; font-weight:600 !important; padding:10px 6px !important;
  cursor:pointer !important; text-decoration:none !important;
  display:flex !important; align-items:center !important; justify-content:center !important;
  gap:5px !important; transition:background .2s !important; white-space:nowrap !important;
  flex:1 1 50% !important;
}
html body .product-card .add-cart:hover,
html body .product-card .add_to_cart_button:hover { background:#a8872e !important; }

/* Quick View */
html body .product-card .quick-view-btn,
html body .product-card [class*="quick-view"],
html body .product-card [class*="quickview"] {
  background:#1a1a1a !important; color:#fff !important;
  border:none !important; border-radius:7px !important;
  font-size:13px !important; font-weight:600 !important; padding:10px 6px !important;
  cursor:pointer !important; text-decoration:none !important;
  display:flex !important; align-items:center !important; justify-content:center !important;
  gap:5px !important; transition:background .2s !important; white-space:nowrap !important;
  flex:1 1 50% !important; min-width:0 !important;
}
html body .product-card .quick-view-btn:hover,
html body .product-card [class*="quick-view"]:hover { background:#333 !important; }

/* ── WooCommerce loop: image container + hover secondary image ── */
html body .woocommerce ul.products li.product .woocommerce-loop-product__link,
html body .woocommerce-page ul.products li.product .woocommerce-loop-product__link {
  position:relative !important; display:block !important;
  width:100% !important; height:0 !important; padding-bottom:75% !important;
  overflow:hidden !important; background:#f5f5f5 !important;
}
html body .woocommerce ul.products li.product .woocommerce-loop-product__link img,
html body .woocommerce-page ul.products li.product .woocommerce-loop-product__link img {
  position:absolute !important; inset:0 !important;
  width:100% !important; height:100% !important;
  object-fit:cover !important; display:block !important;
}
/* Second image (gallery hover) — hidden by default, shown on hover */
html body .woocommerce ul.products li.product .woocommerce-loop-product__link img:nth-child(2),
html body .woocommerce ul.products li.product .woocommerce-loop-product__link img + img,
html body .woocommerce ul.products li.product .woocommerce-loop-product__link .secondary-image,
html body .woocommerce-page ul.products li.product .woocommerce-loop-product__link img:nth-child(2),
html body .woocommerce-page ul.products li.product .woocommerce-loop-product__link img + img {
  opacity:0 !important; transition:opacity .4s ease !important; z-index:2 !important;
}
html body .woocommerce ul.products li.product:hover .woocommerce-loop-product__link img:nth-child(2),
html body .woocommerce ul.products li.product:hover .woocommerce-loop-product__link img + img,
html body .woocommerce ul.products li.product:hover .woocommerce-loop-product__link .secondary-image,
html body .woocommerce-page ul.products li.product:hover .woocommerce-loop-product__link img:nth-child(2),
html body .woocommerce-page ul.products li.product:hover .woocommerce-loop-product__link img + img {
  opacity:1 !important;
}

/* ── Override Elementor lazy-load that clears background-image on e-con children ── */
/* JS adds .e-lazyloaded to ancestors; this ensures slide-bg is shown when it has a bg */
html body .e-lazyloaded .swiper-slide-bg,
html body .swiper-slide-bg {
  background-size: cover !important;
  background-position: center center !important;
}

/* ── Mobile hero slider — broadest possible selectors ── */
@media (max-width: 768px) {
  /* Every Swiper slide on mobile gets full viewport width */
  html body .elementor-slides .swiper-slide,
  html body .elementor-slides-wrapper .swiper-slide,
  html body [class*="elementor-widget"] .swiper-slide {
    width: 100vw !important;
    min-width: 100vw !important;
    max-width: 100vw !important;
    min-height: 420px !important;
    position: relative !important;
    overflow: hidden !important;
    flex-shrink: 0 !important;
    box-sizing: border-box !important;
  }
  /* Slide containers — strip any fixed width */
  html body .elementor-slides-wrapper,
  html body .elementor-slides,
  html body [class*="elementor-widget"] .elementor-slides-wrapper {
    width: 100vw !important;
    max-width: 100vw !important;
    overflow: hidden !important;
  }
  /* Background image — absolutely fill the slide */
  html body .elementor-slides .swiper-slide-bg,
  html body [class*="elementor-widget"] .swiper-slide-bg {
    position: absolute !important;
    top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important;
    width: 100% !important;
    height: 100% !important;
    min-height: 420px !important;
    background-size: cover !important;
    background-position: center center !important;
    z-index: 0 !important;
  }
  /* Inner content on top of background */
  html body .elementor-slides .swiper-slide-inner,
  html body [class*="elementor-widget"] .swiper-slide-inner {
    position: relative !important;
    z-index: 1 !important;
    width: 100% !important;
    min-height: 420px !important;
    box-sizing: border-box !important;
  }
}

/* ── Card content spacing ── */
html body .product-card .product-info {
  padding: 12px 14px 0 !important;
}
html body .product-card .product-title,
html body .product-card h2,
html body .product-card h3,
html body .product-card .woocommerce-loop-product__title,
html body .product-card .rating,
html body .product-card .rating-count,
html body .product-card .woocommerce-product-rating,
html body .product-card .price-section,
html body .product-card .price,
html body .product-card .desc {
  padding-left: 0 !important;
  padding-right: 0 !important;
}
html body .product-card .product-actions,
html body .product-card .card-actions {
  padding: 0 0 14px !important;
}
html body .product-card .price-section del,
html body .product-card .price del,
html body .product-card .old-price,
html body .product-card .price-section del *,
html body .product-card .price del *,
html body .product-card .old-price * {
  color: #999 !important;
  text-decoration: line-through !important;
  font-size: 13px !important;
  font-weight: 400 !important;
}
html body .product-card .price-section ins,
html body .product-card .price ins {
  text-decoration: none !important;
  font-weight: 700 !important;
  color: #1a1a1a !important;
  font-size: 15px !important;
}

/* ── "Shop by Collection" title font sizes ── */
html body .product-container .elementor-heading-title,
html body .product-container h2.elementor-heading-title,
html body .product-container h3.elementor-heading-title {
  font-size: 35px !important;
}
@media (max-width: 600px) {
  html body .product-container .elementor-heading-title,
  html body .product-container h2.elementor-heading-title,
  html body .product-container h3.elementor-heading-title {
    font-size: 20px !important;
  }
}

/* ── "Shop by Collection" title + VIEW MORE button — mobile inline layout ── */
@media (max-width: 600px) {
  /* Ensure the product-container and its parents don't clip the title */
  .product-container,
  .product-container > *,
  .product-container .e-con,
  .product-container .e-con-inner,
  .product-container .elementor-container,
  .product-container .elementor-row {
    overflow: visible !important;
    box-sizing: border-box !important;
  }
  /* Title: fully visible, left side */
  .product-container .elementor-heading-title,
  .product-container h2.elementor-heading-title,
  .product-container h3.elementor-heading-title {
    font-size: 20px !important;
    white-space: normal !important;
    word-break: break-word !important;
    flex: 1 1 auto !important;
    min-width: 0 !important;
    padding-left: 0 !important;
    margin-left: 0 !important;
  }
  /* Button: right side, no shrink */
  .product-container .elementor-button-wrapper,
  .product-container .elementor-widget-button {
    flex-shrink: 0 !important;
    margin-left: 12px !important;
  }
}

/* ── Language switcher dropdown (top bar) — match site dark/gold theme ── */
/* The clickable trigger text */
#stylable-list-first-item.menu-link,
#sh_lsft_custom_dropdown_names .menu-link,
#sh_lsft_custom_dropdown_names > li > a {
  color: #fff !important;
  background: transparent !important;
  font-size: 13px !important;
  font-weight: 500 !important;
  letter-spacing: 0.3px !important;
}
#stylable-list-first-item.menu-link:hover,
#sh_lsft_custom_dropdown_names .menu-link:hover {
  color: #c9a84c !important;
}
/* The dropdown popup list */
#lsft-sub-menu,
#sh_lsft_custom_dropdown_names ul {
  background: #1a1a1a !important;
  border: 1px solid #333 !important;
  border-radius: 6px !important;
  box-shadow: 0 6px 20px rgba(0,0,0,0.4) !important;
  padding: 4px 0 !important;
  min-width: 120px !important;
}
/* Dropdown items */
#lsft-sub-menu li a,
#sh_lsft_custom_dropdown_names ul li a {
  color: #fff !important;
  background: transparent !important;
  font-size: 13px !important;
  font-weight: 500 !important;
  padding: 9px 16px !important;
  display: block !important;
  text-decoration: none !important;
  transition: background 0.2s, color 0.2s !important;
}
#lsft-sub-menu li a *,
#sh_lsft_custom_dropdown_names ul li a * {
  color: #fff !important;
}
#lsft-sub-menu li,
#sh_lsft_custom_dropdown_names ul li {
  background: transparent !important;
}
#lsft-sub-menu li:hover,
#lsft-sub-menu li a:hover,
#lsft-sub-menu li:hover a,
#sh_lsft_custom_dropdown_names ul li:hover,
#sh_lsft_custom_dropdown_names ul li a:hover,
#sh_lsft_custom_dropdown_names ul li:hover a {
  background: #c9a84c !important;
  color: #1a1a1a !important;
}
#lsft-sub-menu li:hover a *,
#lsft-sub-menu li a:hover *,
#sh_lsft_custom_dropdown_names ul li:hover a *,
#sh_lsft_custom_dropdown_names ul li a:hover * {
  color: #1a1a1a !important;
}

/* Hide popup data elements that render as static page content — only show in overlay */
.popup-data,
[class*="popup-data"],
[id*="popup-data"],
#shipping-data, #resolution-data, #frames-data, #payment-data,
/* Hide the Elementor bottom-popup widget — replaced by our custom overlay */
#bottomPopup, .bottom-popup, .bottom-popup.active,
#popupOverlay, .popup-overlay {
  display: none !important;
}

/* ── Mobile header: theme dropdown widget — hide menu by default, toggle on click ── */
@media (max-width: 600px) {
  body:not(.logged-in) .mobile_navbar_menu_dropdown_menu {
    display: none !important;
    position: absolute !important;
    top: 100% !important;
    right: 0 !important;
    background: #fff !important;
    border: 1px solid #e8e8e8 !important;
    border-radius: 10px !important;
    box-shadow: 0 6px 24px rgba(0,0,0,0.15) !important;
    z-index: 9999999 !important;
    min-width: 140px !important;
    padding: 6px 0 !important;
    flex-direction: column !important;
  }
  body:not(.logged-in) .mobile_navbar_menu_dropdown_menu.af-dd-open {
    display: flex !important;
  }
  body:not(.logged-in) .mobile_navbar_menu_dropdown_menu a {
    display: block !important;
    padding: 12px 20px !important;
    font-size: 14px !important;
    font-weight: 600 !important;
    color: #222 !important;
    text-decoration: none !important;
    white-space: nowrap !important;
    border-bottom: 1px solid #f0f0f0 !important;
  }
  body:not(.logged-in) .mobile_navbar_menu_dropdown_menu a:last-child {
    border-bottom: none !important;
  }
  body:not(.logged-in) .mobile_navbar_menu_dropdown_parent {
    position: relative !important;
  }
}
</style>
<script>
(function(){
  if (window.innerWidth > 600) return;
  if (document.body && document.body.classList.contains('logged-in')) return;

  function run() {
    if (document.body.classList.contains('logged-in')) return;
    if (document.body.dataset.afDdDone) return;

    var menu   = document.querySelector('.mobile_navbar_menu_dropdown_menu');
    var title  = document.querySelector('.mobile_navbar_menu_dropdown_title');
    var parent = document.querySelector('.mobile_navbar_menu_dropdown_parent');

    if (!menu || !title) return;
    document.body.dataset.afDdDone = '1';

    if (parent) parent.style.setProperty('position', 'relative', 'important');

    var open = false;
    title.addEventListener('click', function(e) {
      e.preventDefault(); e.stopPropagation();
      open = !open;
      menu.classList.toggle('af-dd-open', open);
    });
    document.addEventListener('click', function(e) {
      if (open && menu && !menu.contains(e.target) && !title.contains(e.target)) {
        open = false;
        menu.classList.remove('af-dd-open');
      }
    });
  }

  if (document.readyState !== 'loading') run();
  document.addEventListener('DOMContentLoaded', run);
  window.addEventListener('load', run);
  setTimeout(run, 500);
  setTimeout(run, 1500);
}());
</script>
<?php }, 99);

// Features bar: force inline on mobile
add_action('wp_head', function() { ?>
<style>
@media (max-width: 600px) {
  .features-container {
    display: flex !important;
    flex-direction: row !important;
    flex-wrap: nowrap !important;
    justify-content: space-around !important;
    align-items: flex-start !important;
    width: 100% !important;
    padding: 10px 2px !important;
    box-sizing: border-box !important;
    gap: 0 !important;
  }
  .feature-box {
    display: flex !important;
    flex-direction: column !important;
    align-items: center !important;
    justify-content: flex-start !important;
    flex: 1 1 0 !important;
    min-width: 0 !important;
    max-width: 25% !important;
    text-align: center !important;
    cursor: pointer !important;
    padding: 0 2px !important;
    gap: 0 !important;
  }
  .feature-box .feature-icon {
    width: 50px !important;
    height: 50px !important;
    min-width: 50px !important;
    border-radius: 50% !important;
    background: #c9a84c !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    margin: 0 auto 5px !important;
  }
  .feature-box .feature-icon i,
  .feature-box .feature-icon svg,
  .feature-box .feature-icon img {
    font-size: 20px !important;
    color: #fff !important;
    fill: #fff !important;
    width: 22px !important;
    height: 22px !important;
  }
  .feature-box .feature-title {
    font-size: 9px !important;
    font-weight: 700 !important;
    color: #222 !important;
    line-height: 1.2 !important;
    margin: 0 !important;
    padding: 0 !important;
    word-break: break-word !important;
    hyphens: auto !important;
  }

  /* Google review cards — fit to content height on mobile */
  /* Widget forces height:100% on .g-review — override it */
  #g-review .swiper .swiper-wrapper .swiper-slide .g-review,
  .g-review,
  [class*="g-review"] {
    height: auto !important;
  }
  /* Slider track: don't force equal heights */
  .eapps-google-reviews-list,
  [class*="google-reviews-list"],
  [class*="reviews-list"],
  .swiper-wrapper {
    align-items: flex-start !important;
  }
  .swiper-slide {
    height: auto !important;
  }
  .eapps-google-reviews-list-item,
  .eapps-google-reviews-list .eapps-google-reviews-list-item,
  [class*="google-reviews"] [class*="list-item"],
  [class*="review-card"],
  [class*="ReviewItem"],
  [class*="review_item"] {
    height: auto !important;
    min-height: 0 !important;
    max-height: none !important;
    align-self: flex-start !important;
    overflow: visible !important;
    display: flex !important;
    flex-direction: column !important;
  }
  .eapps-google-reviews-list-item-body,
  [class*="google-reviews"] [class*="body"],
  [class*="ReviewBody"],
  [class*="review_body"] {
    overflow: visible !important;
    max-height: none !important;
    -webkit-line-clamp: unset !important;
    display: block !important;
    flex: 0 0 auto !important;
  }
  .eapps-google-reviews-list-item-text,
  [class*="google-reviews"] [class*="text"],
  [class*="ReviewText"],
  [class*="review_text"] {
    overflow: visible !important;
    white-space: normal !important;
    text-overflow: unset !important;
    -webkit-line-clamp: unset !important;
    display: block !important;
    height: auto !important;
  }
}
</style>
<script>
(function(){
  if (window.innerWidth > 600) return;
  // Fix "Shop by Collection" title + VIEW MORE button inline on mobile
  function fixShopCollectionRow() {
    // Find the heading with "Shop" + "Collection" text
    var heading = null;
    document.querySelectorAll('h1,h2,h3,h4,.elementor-heading-title').forEach(function(h) {
      if (!heading && /shop/i.test(h.textContent) && /collection/i.test(h.textContent)) heading = h;
    });
    if (!heading) return;
    // Find VIEW MORE button nearby
    var btn = null;
    document.querySelectorAll('a,button').forEach(function(b) {
      if (!btn && /view\s*more/i.test(b.textContent)) btn = b;
    });
    if (!btn) return;
    // Find their common ancestor row container
    var row = heading.closest('.e-con, .elementor-container, .elementor-row, section');
    if (!row) return;
    if (row.dataset.shopRowFixed) return;
    row.dataset.shopRowFixed = '1';
    var sp = function(el,p,v){ el.style.setProperty(p,v,'important'); };
    sp(row,'display','flex');
    sp(row,'flex-direction','row');
    sp(row,'align-items','center');
    sp(row,'justify-content','space-between');
    sp(row,'flex-wrap','nowrap');
    sp(row,'width','100%');
    sp(row,'overflow','visible');
    sp(row,'box-sizing','border-box');
    sp(row,'padding-left','12px');
    sp(row,'padding-right','12px');
    // Title: bigger font, take available space
    sp(heading,'font-size','20px');
    sp(heading,'line-height','1.3');
    sp(heading,'white-space','normal');
    sp(heading,'flex','1 1 auto');
    sp(heading,'min-width','0');
    sp(heading,'padding-left','0');
    sp(heading,'margin-left','0');
    sp(heading,'max-width','none');
    // Button wrapper: push to right
    var btnWrap = btn.closest('.elementor-widget,.e-con') || btn.parentElement;
    sp(btnWrap,'flex-shrink','0');
    sp(btnWrap,'margin-left','auto');
  }

  // Generic helper: fix any section with a heading + "MORE LIKE THIS" or "VIEW MORE" button inline
  function fixSectionRow(headingText) {
    var heading = null;
    document.querySelectorAll('h1,h2,h3,h4,.elementor-heading-title').forEach(function(h) {
      if (!heading && headingText.test(h.textContent)) heading = h;
    });
    if (!heading) return;
    var btn = null;
    // Find nearest MORE LIKE THIS / VIEW MORE button — search from heading's section outward
    var sec = heading.closest('.e-con, .elementor-section, section') || document.body;
    sec.querySelectorAll('a,button').forEach(function(b) {
      if (!btn && /more\s*like\s*this|view\s*more/i.test(b.textContent)) btn = b;
    });
    var row = heading.closest('.e-con, .elementor-container, .elementor-row, section');
    if (!row) return;
    if (row.dataset.sectionRowFixed) return;
    row.dataset.sectionRowFixed = '1';
    var sp = function(el,p,v){ el.style.setProperty(p,v,'important'); };
    sp(row,'display','flex');
    sp(row,'flex-direction','row');
    sp(row,'align-items','center');
    sp(row,'justify-content','space-between');
    sp(row,'flex-wrap','nowrap');
    sp(row,'width','100%');
    sp(row,'overflow','visible');
    sp(row,'box-sizing','border-box');
    sp(row,'padding-left','12px');
    sp(row,'padding-right','12px');
    sp(heading,'font-size','20px');
    sp(heading,'line-height','1.3');
    sp(heading,'white-space','normal');
    sp(heading,'flex','1 1 auto');
    sp(heading,'min-width','0');
    sp(heading,'padding-left','0');
    sp(heading,'margin-left','0');
    sp(heading,'max-width','none');
    if (btn) {
      var btnWrap = btn.closest('.elementor-widget,.e-con') || btn.parentElement;
      sp(btnWrap,'flex-shrink','0');
      sp(btnWrap,'margin-left','auto');
    }
  }

  function fixAllSectionRows() {
    fixSectionRow(/trending\s*today/i);
    fixSectionRow(/new\s*arrivals/i);
    fixSectionRow(/shop.*collection|collection.*shop/i);
  }

  document.addEventListener('DOMContentLoaded', fixAllSectionRows);
  window.addEventListener('load', fixAllSectionRows);
  setTimeout(fixAllSectionRows, 400);

  // Tag the "Trending Today" section with a marker class so its price/discount
  // style can be scoped via CSS to match the Shop by Collection cards.
  function tagTrendingTodaySection() {
    var heading = null;
    document.querySelectorAll('h1,h2,h3,h4,.elementor-heading-title').forEach(function(h) {
      if (!heading && /trending\s*today/i.test(h.textContent)) heading = h;
    });
    if (!heading) return;
    // Walk up from the heading until we find an ancestor that actually
    // contains the price elements — the heading's own .e-con may only
    // wrap the title row, not the product grid below it.
    var node = heading;
    var sec = null;
    while (node && node !== document.body) {
      if (node.querySelector && node.querySelector('.price-section, .price, li.product')) {
        sec = node;
        break;
      }
      node = node.parentElement;
    }
    if (!sec) return;
    if (!sec.classList.contains('af-trending-section')) sec.classList.add('af-trending-section');

    // Real markup confirmed via DevTools: .trending-card > .trending-content
    // > .price-section > .price / .old-price / .discount (no <del> tag at all).
    // .trending-card only exists in this section, so it's a safe, unambiguous scope.
    document.querySelectorAll('.trending-card .discount, .trending-card .discount-percentage').forEach(function(el) {
      el.style.setProperty('color', '#4caf2f', 'important');
      el.style.setProperty('font-weight', '600', 'important');
      el.querySelectorAll('*').forEach(function(c) { c.style.setProperty('color', '#4caf2f', 'important'); });
    });
    document.querySelectorAll('.trending-card .old-price').forEach(function(el) {
      el.style.setProperty('display', 'inline-block', 'important');
      el.style.setProperty('position', 'relative', 'important');
      el.style.setProperty('color', '#999', 'important');
      el.style.setProperty('text-decoration', 'line-through', 'important');
      el.style.setProperty('text-decoration-color', '#999', 'important');
      el.style.setProperty('text-decoration-thickness', '1.5px', 'important');
      el.querySelectorAll('*').forEach(function(c) {
        c.style.setProperty('color', '#999', 'important');
        c.style.setProperty('text-decoration', 'line-through', 'important');
      });
    });
    document.querySelectorAll('.trending-card .add-to-cart-btn, .trending-card .add_to_cart_button, .trending-card a.button').forEach(function(el) {
      el.style.setProperty('background-color', '#c9a84c', 'important');
      el.style.setProperty('border-color', '#c9a84c', 'important');
    });
  }
  document.addEventListener('DOMContentLoaded', tagTrendingTodaySection);
  window.addEventListener('load', tagTrendingTodaySection);
  setTimeout(tagTrendingTodaySection, 400);
  setTimeout(tagTrendingTodaySection, 1200);
  setTimeout(tagTrendingTodaySection, 2500);

  // Fix WooCommerce product grid hover image gap.
  // Derives the aspect ratio from the main image's own naturalWidth/naturalHeight
  // so both images occupy the EXACT same box with no gap — regardless of each
  // product's image dimensions being portrait, landscape, or square.

  function applyHoverFix(card) {
    if (card.dataset.hoverFixed6) return;

    var allImgs = Array.from(card.querySelectorAll('img')).filter(function(img) {
      var src = img.getAttribute('src') || '';
      return src && src.indexOf('woocommerce-placeholder') === -1;
    });
    if (allImgs.length < 2) return;

    var mainImg  = allImgs[0];
    var hoverImg = allImgs[1];

    function build() {
      if (card.dataset.hoverFixed6) return;
      var nw = mainImg.naturalWidth, nh = mainImg.naturalHeight;
      if (!nw || !nh) return; // not loaded yet

      card.dataset.hoverFixed6 = '1';
      mainImg.classList.add('af-main-img');
      hoverImg.classList.add('af-hover-img');

      var ratio = (nh / nw * 100).toFixed(4); // e.g. 133.33 for portrait

      var wrap = document.createElement('div');
      wrap.className = 'af-img-ratio';
      wrap.style.setProperty('position',       'relative',    'important');
      wrap.style.setProperty('width',          '100%',        'important');
      wrap.style.setProperty('height',         '0',           'important');
      wrap.style.setProperty('padding-bottom', ratio + '%',   'important');
      wrap.style.setProperty('overflow',       'hidden',      'important');
      wrap.style.setProperty('display',        'block',       'important');
      wrap.style.setProperty('margin',         '0',           'important');

      mainImg.parentElement.insertBefore(wrap, mainImg);
      wrap.appendChild(mainImg);
      wrap.appendChild(hoverImg);

      var link = wrap.parentElement;
      if (link) {
        link.style.setProperty('display',        'block',  'important');
        link.style.setProperty('width',          '100%',   'important');
        link.style.setProperty('height',         'auto',   'important');
        link.style.setProperty('padding-bottom', '0',      'important');
        link.style.setProperty('overflow',       'hidden', 'important');
      }

      [mainImg, hoverImg].forEach(function(img) {
        img.style.setProperty('position',        'absolute', 'important');
        img.style.setProperty('top',             '0',        'important');
        img.style.setProperty('left',            '0',        'important');
        img.style.setProperty('width',           '100%',     'important');
        img.style.setProperty('height',          '100%',     'important');
        img.style.setProperty('object-fit',      'cover',    'important');
        img.style.setProperty('object-position', 'center',   'important');
        img.style.setProperty('margin',          '0',        'important');
        img.style.setProperty('max-width',       'none',     'important');
      });

      mainImg.style.setProperty('z-index', '1', 'important');
      hoverImg.style.setProperty('z-index', '2', 'important');
      hoverImg.style.setProperty('opacity', '0', 'important');
      hoverImg.style.setProperty('transition', 'opacity 0.4s ease', 'important');

      card.addEventListener('mouseenter', function() {
        hoverImg.style.setProperty('opacity', '1', 'important');
      });
      card.addEventListener('mouseleave', function() {
        hoverImg.style.setProperty('opacity', '0', 'important');
      });
    }

    if (mainImg.complete && mainImg.naturalWidth) {
      build();
    } else {
      mainImg.addEventListener('load', build);
      // also retry after short delay in case load already fired
      setTimeout(build, 300);
      setTimeout(build, 800);
    }
  }

  function fixProductHoverImages() {
    document.querySelectorAll('ul.products li.product, .woocommerce li.product, li.product').forEach(function(card) {
      applyHoverFix(card);
    });
  }

  document.addEventListener('DOMContentLoaded', fixProductHoverImages);
  window.addEventListener('load', fixProductHoverImages);
  setTimeout(fixProductHoverImages, 500);
  setTimeout(fixProductHoverImages, 1500);
  setTimeout(fixProductHoverImages, 3000);

  /* ── New Arrivals: comprehensive card fix ──────────────────────────────────
     The New Arrivals section renders li.product WITHOUT a .woocommerce parent,
     so CSS selectors like ".woocommerce ul.products li.product" never match.
     This function: tags the section, forces card-shell styles inline on each
     li.product, styles price correctly, hides text Wishlist/View links, and
     ensures the Add-to-Cart button is gold + Quick View is dark.              */
  function fixNewArrivalsCards() {
    var heading = null;
    document.querySelectorAll('h1,h2,h3,h4,.elementor-heading-title').forEach(function(h) {
      if (!heading && /new\s*arrivals/i.test(h.textContent)) heading = h;
    });
    if (!heading) return;

    // Walk up until we find an ancestor that contains li.product
    var node = heading, sec = null;
    while (node && node !== document.body) {
      if (node.querySelector && node.querySelector('li.product')) { sec = node; break; }
      node = node.parentElement;
    }
    if (!sec) return;
    if (!sec.classList.contains('af-new-arrivals')) sec.classList.add('af-new-arrivals');

    sec.querySelectorAll('li.product').forEach(function(card) {
      if (card.dataset.naFixed) return;
      card.dataset.naFixed = '1';

      // ── Card shell ──
      var sp = function(el, p, v) { el.style.setProperty(p, v, 'important'); };
      sp(card, 'background', '#fff');
      sp(card, 'border', '1px solid #ececec');
      sp(card, 'border-radius', '12px');
      sp(card, 'overflow', 'hidden');
      sp(card, 'display', 'flex');
      sp(card, 'flex-direction', 'column');
      sp(card, 'box-shadow', '0 2px 12px rgba(0,0,0,0.08)');
      sp(card, 'transition', 'box-shadow 0.25s, transform 0.25s');
      card.addEventListener('mouseenter', function() {
        sp(card, 'box-shadow', '0 8px 28px rgba(0,0,0,0.14)');
        sp(card, 'transform', 'translateY(-2px)');
      });
      card.addEventListener('mouseleave', function() {
        sp(card, 'box-shadow', '0 2px 12px rgba(0,0,0,0.08)');
        sp(card, 'transform', 'translateY(0)');
      });

      // ── Title ──
      var title = card.querySelector('.woocommerce-loop-product__title, h2, h3');
      if (title) {
        sp(title, 'font-size', '13.5px');
        sp(title, 'font-weight', '700');
        sp(title, 'line-height', '1.45');
        sp(title, 'color', '#1a1a1a');
        sp(title, 'margin', '10px 14px 6px');
        sp(title, 'padding', '0');
        sp(title, 'display', '-webkit-box');
        sp(title, '-webkit-line-clamp', '3');
        sp(title, '-webkit-box-orient', 'vertical');
        sp(title, 'overflow', 'hidden');
      }

      // ── Price: style del + ins + inject discount badge ──
      var priceEl = card.querySelector('.price');
      if (priceEl) {
        sp(priceEl, 'display', 'flex');
        sp(priceEl, 'flex-direction', 'row');
        sp(priceEl, 'flex-wrap', 'nowrap');
        sp(priceEl, 'align-items', 'center');
        sp(priceEl, 'gap', '6px');
        sp(priceEl, 'margin', '0 14px 8px');
        sp(priceEl, 'padding', '0');
        sp(priceEl, 'white-space', 'nowrap');

        var ins = priceEl.querySelector('ins');
        if (ins) { sp(ins, 'text-decoration', 'none'); sp(ins, 'font-weight', '700'); sp(ins, 'color', '#1a1a1a'); sp(ins, 'font-size', '15px'); }

        var del = priceEl.querySelector('del');
        if (del) {
          sp(del, 'display', 'inline-block'); sp(del, 'position', 'relative');
          sp(del, 'color', '#999'); sp(del, 'font-weight', '400'); sp(del, 'font-size', '13px');
          sp(del, 'text-decoration', 'line-through'); sp(del, 'text-decoration-color', '#999');
          del.querySelectorAll('*').forEach(function(c) { sp(c, 'color', '#999'); sp(c, 'text-decoration', 'line-through'); });
        }

        // Inject "(X% off)" badge if sale price exists and not already present
        if (ins && del && !priceEl.querySelector('.af-disc-badge')) {
          var saleRaw = ins.textContent.replace(/[^0-9.]/g, '');
          var origRaw = del.textContent.replace(/[^0-9.]/g, '');
          var sale = parseFloat(saleRaw), orig = parseFloat(origRaw);
          if (orig > 0 && sale < orig) {
            var pct = Math.round((orig - sale) / orig * 100);
            var badge = document.createElement('span');
            badge.className = 'af-disc-badge';
            badge.textContent = '(' + pct + '% off)';
            sp(badge, 'color', '#4caf2f');
            sp(badge, 'font-weight', '600');
            sp(badge, 'font-size', '13px');
            priceEl.appendChild(badge);
          }
        }
      }

      // ── Hide raw Wishlist/View text links ──
      card.querySelectorAll('.yith-wcwl-add-to-wishlist, .yith-wcwl-add-button, [class*="wishlist-popup"]').forEach(function(el) {
        sp(el, 'display', 'none');
      });

      // ── Style Add to Cart button gold ──
      var addCartBtn = card.querySelector('.add_to_cart_button, a.button, button.button');
      if (addCartBtn) {
        sp(addCartBtn, 'display', 'flex');
        sp(addCartBtn, 'align-items', 'center');
        sp(addCartBtn, 'justify-content', 'center');
        sp(addCartBtn, 'background', '#c9a84c');
        sp(addCartBtn, 'color', '#fff');
        sp(addCartBtn, 'border', 'none');
        sp(addCartBtn, 'border-radius', '7px');
        sp(addCartBtn, 'font-size', '13px');
        sp(addCartBtn, 'font-weight', '600');
        sp(addCartBtn, 'padding', '10px 14px');
        sp(addCartBtn, 'text-decoration', 'none');
        sp(addCartBtn, 'text-align', 'center');
        sp(addCartBtn, 'width', 'calc(100% - 28px)');
        sp(addCartBtn, 'margin', '0 14px 14px');
        sp(addCartBtn, 'cursor', 'pointer');
        sp(addCartBtn, 'box-sizing', 'border-box');
      }

      // Style View/Quick View link as dark button
      var viewBtn = card.querySelector('a[class*="quick"], a[class*="view"]:not(.add_to_cart_button), .view-btn, [data-quick-view]');
      if (viewBtn && viewBtn !== addCartBtn) {
        sp(viewBtn, 'display', 'flex');
        sp(viewBtn, 'align-items', 'center');
        sp(viewBtn, 'justify-content', 'center');
        sp(viewBtn, 'background', '#1a1a1a');
        sp(viewBtn, 'color', '#fff');
        sp(viewBtn, 'border', 'none');
        sp(viewBtn, 'border-radius', '7px');
        sp(viewBtn, 'font-size', '13px');
        sp(viewBtn, 'font-weight', '600');
        sp(viewBtn, 'padding', '10px 14px');
        sp(viewBtn, 'text-decoration', 'none');
        sp(viewBtn, 'text-align', 'center');
        sp(viewBtn, 'width', 'calc(100% - 28px)');
        sp(viewBtn, 'margin', '0 14px 14px');
        sp(viewBtn, 'cursor', 'pointer');
        sp(viewBtn, 'box-sizing', 'border-box');
      }
    });
  }

  document.addEventListener('DOMContentLoaded', fixNewArrivalsCards);
  window.addEventListener('load', fixNewArrivalsCards);
  setTimeout(fixNewArrivalsCards, 600);
  setTimeout(fixNewArrivalsCards, 1500);
  setTimeout(fixNewArrivalsCards, 3000);

}());
</script>
<?php }, 99);

/* ============================================================
   SHOP / CATEGORY PAGE — PHP hooks for card buttons & badge
   ============================================================ */

// Inject hidden product data (fires inside the product link, inside the image area)
add_action('woocommerce_before_shop_loop_item_title', function() {
  global $product;
  if (!$product) return;
  $id       = $product->get_id();
  $url      = get_permalink($id);
  $cart_url = esc_url(add_query_arg('add-to-cart', $id, $url));
  $regular  = (float) $product->get_regular_price();
  $sale     = (float) $product->get_sale_price();
  $pct      = ($regular > 0 && $sale > 0 && $sale < $regular)
              ? round(($regular - $sale) / $regular * 100) : 0;
  echo '<span class="af-pd" data-id="' . $id . '" data-url="' . esc_url($url) . '" data-cart="' . $cart_url . '" data-pct="' . $pct . '" style="display:none"></span>';
}, 1);

// Discount badge (fires after price, inside the product link — span is OK here)
add_action('woocommerce_after_shop_loop_item_title', function() {
  global $product;
  if (!$product || !$product->is_on_sale() || !$product->get_regular_price()) return;
  $regular = (float) $product->get_regular_price();
  $sale    = (float) $product->get_sale_price();
  if ($regular > 0 && $sale < $regular) {
    $pct = round(($regular - $sale) / $regular * 100);
    echo '<span class="af-shop-discount-badge">' . $pct . '% off</span>';
  }
}, 12);


// Features bar: force inline on mobile
add_action('wp_footer', function() { ?>
<script>
(function() {
  if (window.innerWidth > 600) return;
  var sp = function(el, p, v) { el.style.setProperty(p, v, 'important'); };

  function fixFeaturesBar() {
    // First try: custom HTML widget structure .features-container > .feature-box
    var container = document.querySelector('.features-container');

    // Second try: Elementor structure inside .features-section
    if (!container) {
      var section = document.querySelector('.features-section');
      container = section ? (section.querySelector('.e-con-inner') || section) : null;
    }

    // Third try: .collection-header
    if (!container) {
      var ch = document.querySelector('.collection-header');
      container = ch ? (ch.querySelector('.e-con-inner') || ch) : null;
    }

    // Last resort: find by data-popup attribute
    if (!container) {
      var firstBox = document.querySelector('[data-popup]');
      if (firstBox) container = firstBox.parentElement;
    }
    if (!container || container === document.body) return;
    var alreadyFixed = container.dataset.afFixed === '1';
    container.dataset.afFixed = '1';
    if (alreadyFixed) {
      // Only re-wire clicks on any newly added items, skip style work
      var popupMap2 = { 'shipping': 0, 'resolution': 1, 'frames': 2, 'payment': 3 };
      var labelMap2 = ['Free Shipping','High Resolution','Premium Frames','Secure Payment'];
      Array.from(container.children).forEach(function(item) {
        if (item.dataset.afClick) return;
        item.dataset.afClick = '1';
        var idx = -1;
        var dp = item.getAttribute('data-popup');
        if (dp && popupMap2[dp] !== undefined) idx = popupMap2[dp];
        if (idx === -1) { var txt2 = item.textContent.trim(); labelMap2.forEach(function(l,i){ if(txt2.indexOf(l)!==-1) idx=i; }); }
        if (idx >= 0) { item.style.cursor='pointer'; item.addEventListener('click', function(){ if(window.afOpenSheet) window.afOpenSheet(idx); }); }
      });
      return;
    }

    // Override Elementor CSS custom properties (flex-direction: var(--flex-direction))
    sp(container,'--flex-direction','row');
    sp(container,'--flex-wrap','nowrap');
    sp(container,'--justify-content','space-around');
    sp(container,'--align-items','flex-start');
    // Also override parent section's custom properties
    if (container.parentElement) {
      sp(container.parentElement,'--flex-direction','row');
      sp(container.parentElement,'--flex-wrap','nowrap');
    }

    // Force container to horizontal flex row
    sp(container,'display','flex');
    sp(container,'flex-direction','row');
    sp(container,'flex-wrap','nowrap');
    sp(container,'justify-content','space-around');
    sp(container,'align-items','flex-start');
    sp(container,'width','100%');
    sp(container,'transform','none');
    sp(container,'height','auto');

    // Each child item = one feature column
    Array.from(container.children).forEach(function(item) {
      sp(item,'flex','1 1 0');
      sp(item,'min-width','0');
      sp(item,'max-width','25%');
      sp(item,'display','flex');
      sp(item,'flex-direction','column');
      sp(item,'align-items','center');
      sp(item,'text-align','center');
      sp(item,'padding','6px 2px');
      sp(item,'width','auto');
      sp(item,'height','auto');

      // Drill into any inner wrappers
      item.querySelectorAll('div,span').forEach(function(d) {
        if (d.children.length > 1 || d.querySelector('i,svg,img')) {
          sp(d,'display','flex'); sp(d,'flex-direction','column');
          sp(d,'align-items','center'); sp(d,'text-align','center');
          sp(d,'padding','0'); sp(d,'width','100%');
        }
      });

      // Gold circle around icon
      var icon = item.querySelector('i,svg,img');
      if (icon) {
        var iconWrap = icon.parentElement;
        sp(iconWrap,'width','52px'); sp(iconWrap,'height','52px');
        sp(iconWrap,'border-radius','50%'); sp(iconWrap,'background','#c9a84c');
        sp(iconWrap,'display','flex'); sp(iconWrap,'align-items','center');
        sp(iconWrap,'justify-content','center'); sp(iconWrap,'margin','0 auto 5px');
        sp(iconWrap,'flex-shrink','0');
        sp(icon,'color','#fff'); sp(icon,'fill','#fff');
        sp(icon,'font-size','20px'); sp(icon,'width','20px'); sp(icon,'height','20px');
      }

      // Label — smallest readable size
      var texts = item.querySelectorAll('h1,h2,h3,h4,h5,h6,p,span');
      texts.forEach(function(t) {
        if (t.children.length === 0 && t.textContent.trim().length > 1) {
          sp(t,'font-size','10px'); sp(t,'font-weight','700');
          sp(t,'color','#222'); sp(t,'text-align','center');
          sp(t,'line-height','1.3'); sp(t,'margin','0'); sp(t,'padding','0');
          sp(t,'display','block');
        }
      });
    });

    // Wire click on each item to open the popup — only once per item
    var popupMap = { 'shipping': 0, 'resolution': 1, 'frames': 2, 'payment': 3 };
    var labelMap = ['Free Shipping','High Resolution','Premium Frames','Secure Payment'];
    Array.from(container.children).forEach(function(item) {
      if (item.dataset.afClick) return; // already wired
      item.dataset.afClick = '1';
      var idx = -1;
      // Try data-popup attribute first (e.g. data-popup="shipping")
      var dp = item.getAttribute('data-popup');
      if (dp && popupMap[dp] !== undefined) idx = popupMap[dp];
      // Fallback: match label text
      if (idx === -1) {
        var txt = item.textContent.trim();
        labelMap.forEach(function(l, i) { if (txt.indexOf(l) !== -1) idx = i; });
      }
      if (idx >= 0) {
        sp(item,'cursor','pointer');
        item.addEventListener('click', function() {
          if (window.afOpenSheet) window.afOpenSheet(idx);
        });
      }
    });
  }

  document.addEventListener('DOMContentLoaded', fixFeaturesBar);
  window.addEventListener('load', fixFeaturesBar);
  setTimeout(fixFeaturesBar, 800);
})();
</script>
<?php }, 5);

// Mobile hero slider width fix — force Swiper to recalculate using correct 100vw container
add_action('wp_footer', function() { ?>
<script>
(function() {
  if (window.innerWidth > 768) return;
  function sp(el, p, v) { el.style.setProperty(p, v, 'important'); }

  function fixContainers() {
    // Fix Elementor lazy-load: mark all e-con ancestors of any slider as loaded
    // so the rule `.e-con:nth-of-type(n+3):not(.e-lazyloaded) * { background-image:none!important }` stops firing
    document.querySelectorAll('.elementor-slides, .elementor-slides-wrapper, .elementor-widget-slides').forEach(function(el) {
      var node = el.parentElement;
      while (node && node !== document.body) {
        if (node.classList) {
          node.classList.add('e-lazyloaded');
          node.classList.remove('e-lazyloading');
        }
        node = node.parentElement;
      }
    });

    // Also force background-image via inline important on every slide-bg
    // (reads the CSS variable or computed inline style set by Elementor)
    document.querySelectorAll('.swiper-slide-bg').forEach(function(bg) {
      var inlineBg = bg.getAttribute('style') || '';
      var match = inlineBg.match(/background-image\s*:\s*(url\([^)]+\))/i);
      if (match) {
        bg.style.setProperty('background-image', match[1], 'important');
      }
    });

    document.querySelectorAll('.elementor-widget-slides, .elementor-slides-wrapper').forEach(function(w) {
      sp(w, 'width', '100vw'); sp(w, 'max-width', '100vw');
      sp(w, 'padding', '0');   sp(w, 'margin', '0'); sp(w, 'overflow', 'hidden');
      var el = w.parentElement;
      for (var i = 0; i < 5 && el && el.tagName !== 'BODY'; i++) {
        sp(el, 'padding-left', '0'); sp(el, 'padding-right', '0');
        sp(el, 'max-width', '100vw'); sp(el, 'overflow', 'hidden');
        el = el.parentElement;
      }
    });
    document.querySelectorAll(
      '.elementor-slides-wrapper,' +
      '.elementor-widget-slides .swiper,' +
      '.elementor-widget-slides .swiper-container'
    ).forEach(function(c) {
      sp(c, 'width', '100vw'); sp(c, 'max-width', '100vw'); sp(c, 'overflow', 'hidden');
    });
  }

  function fixSwiperAPI() {
    // Tell Swiper to recalculate sizes using the (now correct) container width
    var swiperEls = document.querySelectorAll(
      '.elementor-slides-wrapper,' +
      '.elementor-widget-slides .swiper,' +
      '.elementor-widget-slides .swiper-container'
    );
    swiperEls.forEach(function(el) {
      var swiper = el.swiper;
      if (swiper && typeof swiper.update === 'function') {
        swiper.params.width  = null; // clear fixed width so it recalculates
        swiper.params.height = null;
        swiper.update();
      }
    });
  }

  function fixSlideInlineWidths() {
    document.querySelectorAll('.elementor-slides .swiper-slide, .elementor-widget-slides .swiper-slide').forEach(function(s) {
      sp(s, 'width',      '100vw'); sp(s, 'min-width',  '100vw');
      sp(s, 'max-width',  '100vw'); sp(s, 'flex-shrink','0');
      sp(s, 'min-height', '420px'); sp(s, 'position',   'relative');
      sp(s, 'overflow',   'hidden'); sp(s, 'box-sizing', 'border-box');
    });
    document.querySelectorAll('.elementor-slides .swiper-slide-bg, .elementor-widget-slides .swiper-slide-bg').forEach(function(bg) {
      sp(bg, 'position',            'absolute');
      sp(bg, 'top',    '0'); sp(bg, 'left',   '0');
      sp(bg, 'right',  '0'); sp(bg, 'bottom', '0');
      sp(bg, 'width',  '100%'); sp(bg, 'height', '100%');
      sp(bg, 'min-height',          '420px');
      sp(bg, 'background-size',     'cover');
      sp(bg, 'background-position', 'center center');
      sp(bg, 'z-index',             '0');
    });
    document.querySelectorAll('.elementor-slides .swiper-slide-inner, .elementor-widget-slides .swiper-slide-inner').forEach(function(inner) {
      sp(inner, 'position',   'relative'); sp(inner, 'z-index',     '1');
      sp(inner, 'width',      '100%');     sp(inner, 'max-width',   '100%');
      sp(inner, 'min-height', '420px');    sp(inner, 'box-sizing',  'border-box');
    });
  }

  function fullFix() {
    fixContainers();
    fixSwiperAPI();
    fixSlideInlineWidths();
  }

  [300, 900, 2000].forEach(function(d) { setTimeout(fullFix, d); });
  window.addEventListener('resize', fullFix);

  // MutationObserver: lock slide width to 100vw regardless of when Swiper rewrites it
  // Swiper uses setProperty('width', Xpx, 'important') which beats stylesheet !important,
  // but our observer fires immediately after and resets it.
  function attachSlideLock() {
    var slides = [];
    var mo = new MutationObserver(function(muts) {
      mo.disconnect();
      var changed = false;
      muts.forEach(function(m) {
        var s = m.target;
        if (s.classList && s.classList.contains('swiper-slide')) {
          // Skip if already set to 100vw — prevents re-firing on our own mutation
          if (s.style.width === '100vw') return;
          changed = true;
          s.style.setProperty('width',      '100vw', 'important');
          s.style.setProperty('min-width',  '100vw', 'important');
          s.style.setProperty('max-width',  '100vw', 'important');
          s.style.setProperty('min-height', '420px', 'important');
          s.style.setProperty('position',   'relative', 'important');
          s.style.setProperty('overflow',   'hidden', 'important');
        }
      });
      slides.forEach(function(s) { mo.observe(s, { attributes: true, attributeFilter: ['style'] }); });
    });
    slides = Array.from(document.querySelectorAll('.elementor-slides .swiper-slide, .elementor-widget-slides .swiper-slide'));
    if (!slides.length) return; // nothing to lock
    slides.forEach(function(s) { mo.observe(s, { attributes: true, attributeFilter: ['style'] }); });
  }
  // Attach once — two calls created two observers on same slides causing mutual infinite loop
  var _slideLockAttached = false;
  function tryAttachSlideLock() {
    if (_slideLockAttached) return;
    var sl = document.querySelectorAll('.elementor-slides .swiper-slide, .elementor-widget-slides .swiper-slide');
    if (!sl.length) return; // slides not ready yet
    _slideLockAttached = true;
    attachSlideLock();
  }
  setTimeout(tryAttachSlideLock, 500);
  setTimeout(tryAttachSlideLock, 1500);
  setTimeout(tryAttachSlideLock, 3000);
}());
</script>
<?php }, 5);

// 11. Products In Motion — circular video slider (PHP-rendered, no JS dependency)
add_action('wp_footer', function() {
    if (!is_front_page()) return;

    // Fetch video IDs from YouTube RSS, cached 1 hour
    $channel = 'UC_GX4vXRQrN4GsvSfgmZxYw';
    $ids = get_transient('af_yt_ids2_' . $channel);
    if (empty($ids)) {
        delete_transient('af_yt_ids2_' . $channel);
        $ids = [];
        $resp = wp_remote_get(
            'https://www.youtube.com/feeds/videos.xml?channel_id=' . $channel,
            ['timeout' => 8, 'sslverify' => false]
        );
        if (!is_wp_error($resp)) {
            $xml = @simplexml_load_string(wp_remote_retrieve_body($resp));
            if ($xml) {
                foreach ($xml->entry as $entry) {
                    preg_match('/video:([A-Za-z0-9_-]{11})/', (string)$entry->id, $m);
                    if (!empty($m[1])) $ids[] = $m[1];
                }
            }
        }
        // Fallback: read IDs from Elementor page meta
        if (empty($ids)) {
            $raw = get_post_meta(get_the_ID(), '_elementor_data', true);
            if ($raw) {
                $stack = json_decode($raw, true) ?: [];
                while ($stack) {
                    $el = array_shift($stack);
                    $type = $el['widgetType'] ?? '';
                    if ($type === 'video') {
                        $u = $el['settings']['youtube_url'] ?? '';
                        if (preg_match('/(?:v=|embed\/|youtu\.be\/)([A-Za-z0-9_-]{11})/', $u, $m)) $ids[] = $m[1];
                    } elseif (in_array($type, ['video-playlist', 'playlist'])) {
                        foreach (($el['settings']['tabs'] ?? []) as $tab) {
                            $u = $tab['youtube_url'] ?? $tab['url'] ?? '';
                            if (preg_match('/(?:v=|embed\/|youtu\.be\/)([A-Za-z0-9_-]{11})/', $u, $m)) $ids[] = $m[1];
                        }
                    }
                    if (!empty($el['elements'])) $stack = array_merge($stack, $el['elements']);
                }
            }
        }
        $ids = array_values(array_unique($ids));
        if (!empty($ids)) set_transient('af_yt_ids2_' . $channel, $ids, HOUR_IN_SECONDS);
    }

    // Nothing to show
    if (empty($ids)) return;
    ?>
<style>
/* ── Products In Motion circular slider ── */
.af-pim-section-heading {
    text-align: left !important;
    width: 100% !important;
}
.af-pim-section-heading .elementor-heading-title,
.af-pim-section-heading h1,
.af-pim-section-heading h2,
.af-pim-section-heading h3,
.af-pim-section-heading h4 {
    text-align: left !important;
}
.af-pim-wrap {
    width:100%;
    padding:0 0 32px;
    box-sizing:border-box;
    overflow:hidden;
}
.af-pim-row {
    display:flex;
    align-items:center;
    gap:12px;
    width:100%;
}
.af-pim-btn {
    flex:0 0 44px;
    width:44px; height:44px;
    border-radius:50%;
    background:#c9a84c;
    border:none;
    color:#fff;
    font-size:30px;
    line-height:1;
    cursor:pointer;
    display:flex;
    align-items:center;
    justify-content:center;
    box-shadow:0 2px 8px rgba(0,0,0,.3);
    padding:0;
    flex-shrink:0;
}
.af-pim-btn:hover { background:#a8872e; }
.af-pim-vp {
    flex:1 1 auto;
    min-width:0;
    overflow:hidden;
}
.af-pim-track {
    display:flex;
    flex-direction:row;
    flex-wrap:nowrap;
    gap:24px;
    align-items:center;
    transition:transform .42s cubic-bezier(.4,0,.2,1);
    will-change:transform;
    padding:12px 0;
}
.af-pim-circle {
    flex:0 0 200px;
    width:200px; height:200px;
    border-radius:50%;
    overflow:hidden;
    border:3px solid #c9a84c;
    box-shadow:0 4px 18px rgba(0,0,0,.22);
    background:#111;
    position:relative;
    cursor:pointer;
    transition:transform .28s ease, box-shadow .28s ease;
}
.af-pim-circle:hover {
    transform:scale(1.07);
    box-shadow:0 8px 30px rgba(0,0,0,.35);
}
.af-pim-circle iframe {
    position:absolute;
    top:50%; left:50%;
    width:400%; height:225%;
    transform:translate(-50%,-50%) scale(2.2);
    transform-origin:center center;
    border:none;
    pointer-events:none;
    z-index:1;
}
.af-pim-thumb {
    position:absolute;
    inset:0;
    width:100%; height:100%;
    object-fit:cover;
    object-position:center;
    z-index:2;
    transition:opacity .6s;
}
.af-pim-play {
    position:absolute;
    inset:0;
    display:flex;
    align-items:center;
    justify-content:center;
    z-index:3;
    pointer-events:none;
}
.af-pim-play svg {
    width:54px; height:54px;
    fill:rgba(255,255,255,.9);
    filter:drop-shadow(0 2px 6px rgba(0,0,0,.6));
}
.af-pim-overlay {
    position:absolute;
    inset:0;
    z-index:10;
    cursor:pointer;
}
/* Lightbox */
.af-pim-lb {
    display:none;
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.9);
    z-index:999999;
    align-items:center;
    justify-content:center;
}
.af-pim-lb.open { display:flex; }
.af-pim-lb iframe {
    width:min(88vw,960px);
    height:min(49.5vw,540px);
    border:none;
    border-radius:10px;
}
.af-pim-lb-x {
    position:absolute;
    top:18px; right:26px;
    color:#fff;
    font-size:46px;
    line-height:1;
    cursor:pointer;
    background:none;
    border:none;
    padding:0;
}
@media(max-width:768px){
    .af-pim-circle { flex:0 0 140px; width:140px; height:140px; }
}
@media(max-width:480px){
    .af-pim-circle { flex:0 0 110px; width:110px; height:110px; }
    .af-pim-btn { flex:0 0 34px; width:34px; height:34px; font-size:22px; }
}
/* Hide original Elementor video/playlist widgets */
.elementor-widget-video-playlist,
.elementor-widget-video,
[data-widget_type^="video-playlist"],
[data-widget_type^="video"],
[data-widget_type^="video-playlist"] .elementor-widget-container,
[data-widget_type^="video"] .elementor-widget-container { display:none !important; }
/* Hide YouTube iframes that are NOT inside our circular slider or lightbox */
/* Instagram/elfsight/social iframes are excluded — they don't use youtube src */
body iframe[src*="youtube.com"]:not(#afPimWrap iframe):not(#afPimLb iframe),
body iframe[src*="youtube-nocookie.com"]:not(#afPimWrap iframe):not(#afPimLb iframe),
body iframe[src*="youtu.be"]:not(#afPimWrap iframe):not(#afPimLb iframe) {
  display: none !important;
}
/* Also hide the widget containers of those YouTube iframes so no empty space remains */
.elementor-widget-html:has(iframe[src*="youtube.com"]:not(#afPimWrap iframe)),
.elementor-widget-html:has(iframe[src*="youtube-nocookie.com"]:not(#afPimWrap iframe)) {
  display: none !important;
}
</style>

<?php
    // Build circle HTML for each video ID
    $circles_html = '';
    foreach ($ids as $vid) {
        $vid = esc_attr($vid);
        $thumb_hq  = "https://img.youtube.com/vi/{$vid}/hqdefault.jpg";
        $thumb_max = "https://img.youtube.com/vi/{$vid}/maxresdefault.jpg";
        $embed     = "https://www.youtube-nocookie.com/embed/{$vid}?autoplay=1&mute=1&loop=1&playlist={$vid}&controls=0&rel=0&playsinline=1";
        $circles_html .= "
<div class=\"af-pim-circle\" data-vid=\"{$vid}\">
  <iframe src=\"{$embed}\" allow=\"autoplay; encrypted-media\" frameborder=\"0\" loading=\"lazy\"></iframe>
  <img class=\"af-pim-thumb\" src=\"{$thumb_max}\" onerror=\"this.src='{$thumb_hq}';this.onerror=null\" alt=\"\">
  <div class=\"af-pim-play\"><svg viewBox=\"0 0 24 24\"><path d=\"M8 5v14l11-7z\"/></svg></div>
  <div class=\"af-pim-overlay\"></div>
</div>";
    }
?>
<div class="af-pim-wrap" id="afPimWrap">
  <div class="af-pim-row">
    <button class="af-pim-btn" id="afPimPrev" aria-label="Previous">&#8249;</button>
    <div class="af-pim-vp" id="afPimVp">
      <div class="af-pim-track" id="afPimTrack">
        <?php echo $circles_html; ?>
      </div>
    </div>
    <button class="af-pim-btn" id="afPimNext" aria-label="Next">&#8250;</button>
  </div>
</div>

<!-- Lightbox -->
<div class="af-pim-lb" id="afPimLb">
  <button class="af-pim-lb-x" id="afPimLbX">&times;</button>
  <iframe id="afPimLbFrame" src="" allow="autoplay; fullscreen; encrypted-media" allowfullscreen></iframe>
</div>

<script>
(function(){
    var track  = document.getElementById('afPimTrack');
    var vp     = document.getElementById('afPimVp');
    var lb     = document.getElementById('afPimLb');
    var lbFr   = document.getElementById('afPimLbFrame');
    var lbX    = document.getElementById('afPimLbX');
    var circles = Array.from(document.querySelectorAll('#afPimTrack .af-pim-circle'));
    var idx = 0, GAP = 24;

    function cw() {
        return window.innerWidth <= 480 ? 110 : window.innerWidth <= 768 ? 140 : 200;
    }
    function vis() {
        var vpW = vp.getBoundingClientRect().width || 800;
        return Math.max(1, Math.floor((vpW + GAP) / (cw() + GAP)));
    }
    function go(n) {
        var max = Math.max(0, circles.length - vis());
        idx = Math.max(0, Math.min(n, max));
        track.style.transform = 'translateX(' + (-(idx * (cw() + GAP))) + 'px)';
    }

    document.getElementById('afPimPrev').onclick = function(){ go(idx - vis()); };
    document.getElementById('afPimNext').onclick = function(){ go(idx + vis()); };
    window.addEventListener('resize', function(){ idx = 0; go(0); });

    // After iframe loads, fade out the thumbnail overlay
    circles.forEach(function(c) {
        var fr = c.querySelector('iframe');
        var th = c.querySelector('.af-pim-thumb');
        var pl = c.querySelector('.af-pim-play');
        if (fr && th) {
            fr.addEventListener('load', function(){
                setTimeout(function(){ th.style.opacity = '0'; if(pl) pl.style.opacity='0'; }, 1800);
            });
        }
        // Click: open lightbox with sound
        var ov = c.querySelector('.af-pim-overlay');
        if (ov) {
            ov.addEventListener('click', function(){
                var vid = c.getAttribute('data-vid');
                lbFr.src = 'https://www.youtube-nocookie.com/embed/' + vid
                    + '?autoplay=1&rel=0&playsinline=1';
                lb.classList.add('open');
            });
        }
    });

    function closeLb() {
        lb.classList.remove('open');
        lbFr.src = '';
    }
    lbX.onclick = closeLb;
    lb.addEventListener('click', function(e){ if (e.target === lb) closeLb(); });
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeLb(); });

    go(0);

    // Find the "Products In Motion" section, hide its video content, inject our slider inside it
    function placeSlider() {
        var wrap = document.getElementById('afPimWrap');
        if (!wrap || wrap.dataset.placed) return;

        // Find the heading
        var heading = null;
        document.querySelectorAll('h2,h3,h4,.elementor-heading-title').forEach(function(h) {
            if (!heading && /product.*mot/i.test(h.textContent)) heading = h;
        });
        if (!heading) return;

        // Left-align the heading
        var headWidget = heading.closest('.elementor-widget') || heading.parentElement;
        headWidget.classList.add('af-pim-section-heading');
        heading.style.setProperty('text-align', 'left', 'important');
        headWidget.style.setProperty('text-align', 'left', 'important');

        // Walk up to the outermost Elementor section containing this heading
        var section = null;
        var el = heading;
        for (var i = 0; i < 20; i++) {
            el = el.parentElement;
            if (!el || el === document.body) break;
            if (/elementor-top-section|elementor-section-wrap/.test(el.className || '')) { section = el; break; }
            if (/elementor-section/.test(el.className || '')) section = el;
        }
        if (!section) section = heading.parentElement;

        wrap.dataset.placed = '1';

        // Hide iframes ONLY inside the Products In Motion section (not page-wide)
        section.querySelectorAll('iframe').forEach(function(fr) {
            if (fr.closest('#afPimWrap') || fr.closest('.af-pim-lb')) return;
            var col = fr.closest('.elementor-column, .e-con, .elementor-widget');
            if (col) col.style.setProperty('display', 'none', 'important');
            else fr.style.setProperty('display', 'none', 'important');
        });

        // Also hide video/playlist widgets inside the section
        section.querySelectorAll('.elementor-widget').forEach(function(w) {
            var type = w.getAttribute('data-widget_type') || '';
            if (/^(heading|text-editor|text)\b/.test(type)) return;
            w.style.setProperty('display', 'none', 'important');
        });

        // Find the widget-wrap container that holds the heading (direct parent of elementor-widgets)
        var container = heading.closest('.elementor-widget-wrap, .e-con-inner, .e-con') || section;

        // Find description text widget in that same container
        var descWidget = null;
        container.querySelectorAll(':scope > .elementor-widget, :scope > .elementor-element').forEach(function(w) {
            var type = w.getAttribute('data-widget_type') || '';
            if (/^(text-editor|text)\b/.test(type)) descWidget = w;
        });
        // Fallback: search anywhere in section
        if (!descWidget) {
            section.querySelectorAll('.elementor-widget').forEach(function(w) {
                var type = w.getAttribute('data-widget_type') || '';
                if (/^(text-editor|text)\b/.test(type)) descWidget = w;
            });
        }

        // Append slider to container, then move desc just before it → title → desc → slider
        container.appendChild(wrap);
        if (descWidget) {
            container.insertBefore(descWidget, wrap);
        }
    }

    document.addEventListener('DOMContentLoaded', placeSlider);
    window.addEventListener('load', placeSlider);
    setTimeout(placeSlider, 500);
    setTimeout(placeSlider, 1500);
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
  window.afOpenSheet = openSheet;
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
    var sec = document.querySelector('.features-section');
    if (sec) return sec;
    sec = document.querySelector('[data-id="810fb7a"]');
    if (sec) return sec;
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
    // Must use setProperty('--flex-direction') because Elementor sets it as inline var()
    sec.querySelectorAll('.e-con-inner, .e-con').forEach(function(el) {
      el.style.setProperty('--flex-direction', 'row', 'important');
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

  // Poll until Elementor lazy-loads the section (up to ~10 seconds)
  var _fsAttempts = 0;
  function _fsPoll() {
    _fsAttempts++;
    fixFeaturesSection();
    var found = document.querySelector('[data-id="810fb7a"]') || document.querySelector('.features-section');
    var fixed = found && found.dataset.fsFixed;
    if (!fixed && _fsAttempts < 50) {
      setTimeout(_fsPoll, 200);
    }
  }
  document.addEventListener('DOMContentLoaded', _fsPoll);
  window.addEventListener('load', _fsPoll);
}());
</script>
<script>
// Hide inline popup content inside .feature-box — should only show in overlay sheet
(function(){
  function hideStaticPopupContent() {
    // 1. Hide .popup-data elements (Elementor HTML widgets with popup content)
    document.querySelectorAll('.popup-data, [class*="popup-data"], [id*="popup-data"]').forEach(function(el) {
      el.style.setProperty('display', 'none', 'important');
    });

    // 2. Hide any Elementor section/column that comes AFTER the features-container
    //    AND contains a close button (×) or has popup-style content — but NOT our #afFeatSheet
    var featContainer = document.querySelector('.features-container, .features-section');
    if (featContainer) {
      // Walk sibling elements after the features section and hide those with popup content
      var parent = featContainer.closest('.elementor-section, .e-con, section') || featContainer.parentElement;
      if (parent && parent.parentElement) {
        var siblings = Array.from(parent.parentElement.children);
        var found = false;
        siblings.forEach(function(sib) {
          if (sib === parent) { found = true; return; }
          if (!found) return;
          // Skip our own overlay elements
          if (sib.id === 'afFeatOverlay' || sib.id === 'afFeatSheet') return;
          // If sibling has content matching popup style (close btn + heading + list), hide it
          var hasClose = sib.querySelector('[class*="close"], button') && sib.querySelector('h3,h4');
          var hasList  = sib.querySelector('ul li');
          if (hasClose || (hasList && sib.querySelector('h3,h4'))) {
            sib.style.setProperty('display', 'none', 'important');
          }
        });
      }
    }
  }
  document.addEventListener('DOMContentLoaded', hideStaticPopupContent);
  window.addEventListener('load', hideStaticPopupContent);
  setTimeout(hideStaticPopupContent, 500);
}());
</script>
<?php }, 10003);

/* ============================================================
   Hero Slider mobile fix — the parent column of the slider
   widget is too narrow; walk up the DOM and widen all ancestors,
   then patch the Swiper instance and force slide widths.
   ============================================================ */
add_action('wp_footer', function() { ?>
<script>
(function(){
  if (window.innerWidth > 768) return;

  function doFix() {
    var vw = window.innerWidth;
    var widget = document.querySelector('.elementor-element-0971963');
    if (!widget) return;

    // 1. Full-bleed escape: pull widget to 100vw regardless of parent column width
    widget.style.setProperty('width', '100vw', 'important');
    widget.style.setProperty('max-width', '100vw', 'important');
    widget.style.setProperty('margin-left', 'calc(50% - 50vw)', 'important');
    widget.style.setProperty('position', 'relative', 'important');
    // Also widen all ancestors so overflow:hidden doesn't clip
    var el = widget.parentElement;
    while (el && el !== document.body) {
      var ow = el.offsetWidth;
      if (ow < vw) {
        el.style.setProperty('width', '100%', 'important');
        el.style.setProperty('max-width', '100%', 'important');
        el.style.setProperty('overflow', 'visible', 'important');
      }
      el = el.parentElement;
    }

    // 2. Find the Swiper container (walk from slide upward, find .swiper property)
    var slide = widget.querySelector('.swiper-slide');
    if (slide) {
      var node = slide.parentElement;
      while (node && node !== document.body) {
        if (node.swiper) {
          var sw = node.swiper;
          node.style.setProperty('width', vw + 'px', 'important');
          sw.params.slidesPerView = 1;
          sw.params.spaceBetween = 0;
          sw.params.centeredSlides = false;
          if (sw.originalParams) {
            sw.originalParams.slidesPerView = 1;
            sw.originalParams.spaceBetween = 0;
            sw.originalParams.centeredSlides = false;
          }
          sw.update();
          break;
        }
        node = node.parentElement;
      }
    }

    // 3. Force every slide to exactly viewport width
    widget.querySelectorAll('.swiper-slide').forEach(function(s) {
      s.style.setProperty('width', vw + 'px', 'important');
      s.style.setProperty('min-width', vw + 'px', 'important');
      s.style.setProperty('flex-shrink', '0', 'important');
    });
    // Note: attachSlideLock (above) already watches these slides via MutationObserver.
    // Do NOT add a second observer here — parseInt('100vw')=100 causes an infinite loop
    // between two observers watching the same slides.
  }

  window.addEventListener('load', doFix);
  setTimeout(doFix, 500);
  setTimeout(doFix, 1500);
  setTimeout(doFix, 3000);
}());
</script>
<?php }, 10004);


/* ============================================================
   SHOP / CATEGORY PAGE — comprehensive card fix via JS
   JS inline styles beat all CSS (including theme overrides).
   ============================================================ */
add_action('wp_footer', function() {
  $is_shop = is_product_category() || is_shop() || is_tax('product_cat');
  if (!$is_shop) return;
?><script>
(function() {
  var body = document.body;
  if (!body.classList.contains('tax-product_cat') &&
      !body.classList.contains('woocommerce-page') &&
      !/\/product-category\/|\/shop\//.test(window.location.pathname)) return;

  function sp(el, p, v) { el.style.setProperty(p, v, 'important'); }

  function fixShopCard(card) {
    if (card.dataset.afShopFixed) return;
    card.dataset.afShopFixed = '1';

    /* v4 — DEFINITIVE FIX
       1. Hide ALL unknown card children (theme overlay, badges) → eliminates blank space
       2. padding-top:100% wrapper → bulletproof 1:1 ratio, content-independent
       3. mainLink position:absolute inset:0 → fills wrap, zero layout contribution
       4. Custom .af-ov overlay → position:absolute inset:0, sibling of link (not nested in <a>)
       5. pointer-events:none on overlay, pointer-events:auto on buttons
    */

    // ── 1. Card shell
    sp(card,'background',    '#fff');
    sp(card,'border-radius', '10px');
    sp(card,'overflow',      'hidden');
    sp(card,'box-shadow',    '0 2px 16px rgba(0,0,0,.08)');
    sp(card,'border',        '1px solid #ede9e0');
    sp(card,'display',       'flex');
    sp(card,'flex-direction','column');
    sp(card,'transition',    'box-shadow .25s ease, transform .25s ease');
    card.addEventListener('mouseenter',function(){
      card.style.setProperty('box-shadow','0 8px 36px rgba(0,0,0,.14)','important');
      card.style.setProperty('transform','translateY(-2px)','important');
    });
    card.addEventListener('mouseleave',function(){
      card.style.setProperty('box-shadow','0 2px 16px rgba(0,0,0,.08)','important');
      card.style.setProperty('transform','translateY(0)','important');
    });

    // ── 2. Gather data
    var pd        = card.querySelector('.af-pd');
    var mainLink  = card.querySelector('a.woocommerce-loop-product__link');
    if (!mainLink) return;
    var themeCart = card.querySelector('a.add_to_cart_button,a.ajax_add_to_cart,a.button.product_type_simple');
    var cartUrl   = (pd&&pd.dataset.cart)||(themeCart&&themeCart.href)||mainLink.href||'#';
    var productUrl= (pd&&pd.dataset.url)||mainLink.href||'#';

    // Collect wishlist link data for cloning (before hiding)
    var wlSrc = card.querySelector('a.add_to_wishlist,.yith-wcwl-add-to-wishlist a,a[class*="wishlist"]');

    // ── 3. HIDE all non-content direct children → eliminates blank space absolutely
    Array.from(card.children).forEach(function(c){
      if (c===mainLink) return;
      var cls=c.classList;
      if (cls.contains('woocommerce-loop-product__title')) return;
      if (cls.contains('woocommerce-product-rating'))     return;
      if (cls.contains('price'))                          return;
      sp(c,'display','none');
    });

    // ── 4. Build .af-img-wrap (only once)
    if (!card.querySelector('.af-img-wrap')) {

      var wrap=document.createElement('div');
      wrap.className='af-img-wrap';
      sp(wrap,'position',   'relative');
      sp(wrap,'width',      '100%');
      sp(wrap,'padding-top','100%');  // bulletproof 1:1 — height = width, content-independent
      sp(wrap,'overflow',   'hidden');
      sp(wrap,'background', '#f5f2ed');
      sp(wrap,'flex-shrink','0');

      card.insertBefore(wrap, mainLink);
      wrap.appendChild(mainLink);

      // mainLink fills entire wrap
      sp(mainLink,'position','absolute');
      sp(mainLink,'top',    '0');
      sp(mainLink,'left',   '0');
      sp(mainLink,'right',  '0');
      sp(mainLink,'bottom', '0');
      sp(mainLink,'width',  '100%');
      sp(mainLink,'height', '100%');
      sp(mainLink,'display','block');
      sp(mainLink,'z-index','1');

      // Image fills mainLink with zoom on hover
      var img=mainLink.querySelector('img');
      if (img) {
        sp(img,'position',  'absolute');
        sp(img,'top',       '0');
        sp(img,'left',      '0');
        sp(img,'width',     '100%');
        sp(img,'height',    '100%');
        sp(img,'object-fit','cover');
        sp(img,'display',   'block');
        sp(img,'transition','transform .5s cubic-bezier(.25,.46,.45,.94)');
        card.addEventListener('mouseenter',function(){ img.style.setProperty('transform','scale(1.06)','important'); });
        card.addEventListener('mouseleave',function(){ img.style.setProperty('transform','scale(1)',   'important'); });
      }

      // Diagonal sale ribbon
      var ribbon=mainLink.querySelector('.onsale');
      if (!ribbon) ribbon=wrap.querySelector('.onsale');
      if (ribbon) {
        sp(ribbon,'position',      'absolute');
        sp(ribbon,'top',           '18px');
        sp(ribbon,'left',          '-22px');
        sp(ribbon,'width',         '90px');
        sp(ribbon,'background',    '#c9a84c');
        sp(ribbon,'color',         '#fff');
        sp(ribbon,'font-size',     '10px');
        sp(ribbon,'font-weight',   '800');
        sp(ribbon,'text-align',    'center');
        sp(ribbon,'padding',       '5px 0');
        sp(ribbon,'transform',     'rotate(-45deg)');
        sp(ribbon,'z-index',       '10');
        sp(ribbon,'letter-spacing','0.10em');
        sp(ribbon,'text-transform','uppercase');
        sp(ribbon,'line-height',   '1.4');
        sp(ribbon,'min-width',     'unset');
        sp(ribbon,'border-radius', '0');
        sp(ribbon,'margin',        '0');
        sp(ribbon,'display',       'block');
        sp(ribbon,'box-shadow',    '0 2px 6px rgba(0,0,0,.20)');
      }

      // Custom overlay — sibling of mainLink inside wrap (NOT nested in <a>)
      var ov=document.createElement('div');
      ov.className='af-ov';
      sp(ov,'position',       'absolute');
      sp(ov,'top',            '0');
      sp(ov,'left',           '0');
      sp(ov,'right',          '0');
      sp(ov,'bottom',         '0');
      sp(ov,'z-index',        '2');
      sp(ov,'display',        'flex');
      sp(ov,'align-items',    'flex-end');
      sp(ov,'justify-content','center');
      sp(ov,'gap',            '10px');
      sp(ov,'padding',        '0 12px 16px');
      sp(ov,'background',     'rgba(12,9,5,.50)');
      sp(ov,'opacity',        '0');
      sp(ov,'transition',     'opacity .3s ease');
      sp(ov,'pointer-events', 'none');
      sp(ov,'box-sizing',     'border-box');
      card.addEventListener('mouseenter',function(){ ov.style.setProperty('opacity','1','important'); });
      card.addEventListener('mouseleave',function(){ ov.style.setProperty('opacity','0','important'); });

      function iconBtn(el) {
        sp(el,'width',          '36px');
        sp(el,'height',         '36px');
        sp(el,'border-radius',  '50%');
        sp(el,'background',     'rgba(255,255,255,.9)');
        sp(el,'color',          '#222');
        sp(el,'display',        'inline-flex');
        sp(el,'align-items',    'center');
        sp(el,'justify-content','center');
        sp(el,'border',         'none');
        sp(el,'cursor',         'pointer');
        sp(el,'flex-shrink',    '0');
        sp(el,'text-decoration','none');
        sp(el,'pointer-events', 'auto');
        sp(el,'transition',     'background .2s');
      }

      // Wishlist — clone from theme element to preserve YITH data attributes
      var wlBtn;
      if (wlSrc) {
        wlBtn=wlSrc.cloneNode(false);
        wlBtn.innerHTML='<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>';
      } else {
        wlBtn=document.createElement('a');
        wlBtn.href='#';
        wlBtn.innerHTML='<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>';
      }
      iconBtn(wlBtn);

      // Add to Cart — primary gold pill button
      var atcBtn=document.createElement('a');
      atcBtn.href=cartUrl;
      atcBtn.className='af-ov-atc';
      atcBtn.innerHTML='<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg><span>Add to Cart</span>';
      sp(atcBtn,'display',        'inline-flex');
      sp(atcBtn,'align-items',    'center');
      sp(atcBtn,'gap',            '6px');
      sp(atcBtn,'padding',        '9px 18px');
      sp(atcBtn,'background',     '#c9a84c');
      sp(atcBtn,'color',          '#fff');
      sp(atcBtn,'border-radius',  '4px');
      sp(atcBtn,'font-size',      '12px');
      sp(atcBtn,'font-weight',    '700');
      sp(atcBtn,'letter-spacing', '0.05em');
      sp(atcBtn,'white-space',    'nowrap');
      sp(atcBtn,'text-decoration','none');
      sp(atcBtn,'cursor',         'pointer');
      sp(atcBtn,'flex-shrink',    '0');
      sp(atcBtn,'line-height',    '1');
      sp(atcBtn,'pointer-events', 'auto');

      // Quick View — eye icon circle
      var qvBtn=document.createElement('a');
      qvBtn.href=productUrl;
      qvBtn.className='af-ov-qv';
      qvBtn.innerHTML='<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
      iconBtn(qvBtn);

      ov.appendChild(wlBtn);
      ov.appendChild(atcBtn);
      ov.appendChild(qvBtn);
      wrap.appendChild(ov);
    }

    // ── 5. Content: Title → Rating → Price
    var titleEl =card.querySelector('.woocommerce-loop-product__title');
    var ratingEl=card.querySelector('.woocommerce-product-rating');
    var priceEl =card.querySelector('.price');

    if (titleEl) {
      sp(titleEl,'font-size',         '13.5px');
      sp(titleEl,'font-weight',       '600');
      sp(titleEl,'color',             '#1a1a1a');
      sp(titleEl,'line-height',       '1.45');
      sp(titleEl,'padding',           '13px 16px 3px');
      sp(titleEl,'margin',            '0');
      sp(titleEl,'height',            '56px');
      sp(titleEl,'overflow',          'hidden');
      sp(titleEl,'display',           '-webkit-box');
      sp(titleEl,'-webkit-line-clamp','2');
      sp(titleEl,'-webkit-box-orient','vertical');
      sp(titleEl,'box-sizing',        'border-box');
      titleEl.querySelectorAll('a').forEach(function(a){
        sp(a,'color','#1a1a1a'); sp(a,'text-decoration','none');
      });
    }
    if (ratingEl) {
      sp(ratingEl,'display',    'flex');
      sp(ratingEl,'align-items','center');
      sp(ratingEl,'padding',    '3px 16px 4px');
      sp(ratingEl,'margin',     '0');
      sp(ratingEl,'box-sizing', 'border-box');
      var starEl=ratingEl.querySelector('.star-rating');
      var cntEl =ratingEl.querySelector('.woocommerce-review-link');
      if (starEl){sp(starEl,'font-size','12px');sp(starEl,'margin','0');}
      if (cntEl) {sp(cntEl, 'font-size','11px');sp(cntEl, 'color','#888');sp(cntEl,'margin-left','4px');}
    }
    if (priceEl) {
      sp(priceEl,'display',    'flex');
      sp(priceEl,'align-items','baseline');
      sp(priceEl,'gap',        '6px');
      sp(priceEl,'padding',    '2px 16px 14px');
      sp(priceEl,'margin',     '0');
      sp(priceEl,'box-sizing', 'border-box');
      var ins=priceEl.querySelector('ins');
      var del=priceEl.querySelector('del');
      if (ins){sp(ins,'font-size','15px');sp(ins,'font-weight','700');sp(ins,'color','#1a1a1a');sp(ins,'text-decoration','none');}
      if (del){sp(del,'font-size','12px');sp(del,'color','#aaa');sp(del,'text-decoration','line-through');sp(del,'font-weight','400');}
      if (!ins){sp(priceEl,'font-size','15px');sp(priceEl,'font-weight','700');sp(priceEl,'color','#1a1a1a');}
    }
  }


  function run() {
    document.querySelectorAll('ul.products li.product').forEach(fixShopCard);
  }

  document.addEventListener('DOMContentLoaded', run);
  window.addEventListener('load', run);
  [300, 800, 1500].forEach(function(d) { setTimeout(run, d); });
}());
</script>
<?php
}, 200);

// ─────────────────────────────────────────────────────────────
// "Try It On Your Wall" — upload-photo AR mockup (no plugin, no cost)
// ─────────────────────────────────────────────────────────────

// Try-On-Wall CSS on product pages (button styling only; modal disabled)
add_action('wp_enqueue_scripts', function() {
    if (!function_exists('is_product') || !is_product()) return;
    wp_enqueue_style('af-ar-wall', get_stylesheet_directory_uri() . '/assets/css/ar-wall.css', array(), '1.1.0');
}, 25);

// "Try It On Your Wall" button under Add to Cart — now LINKS to the full
// standalone /try-on-wall/ page with this product pre-selected.
add_action('woocommerce_after_add_to_cart_button', function() {
    global $product;
    if (!$product) return;
    $url = add_query_arg('product', $product->get_id(), home_url('/try-on-wall/'));
    ?>
    <a href="<?php echo esc_url($url); ?>" class="af-arw-btn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/>
        </svg>
        Try It On Your Wall
    </a>
    <?php
}, 15);

// Render the AR modal in the footer (once, on product pages)
add_action('wp_footer', function() {
    return; // DISABLED: the button now links to the full /try-on-wall/ page.
    if (!function_exists('is_product') || !is_product()) return;
    // wp_footer often runs after WooCommerce clears the global $product,
    // so fetch it from the queried page instead — otherwise the modal
    // never renders and the button has nothing to open.
    $product = wc_get_product(get_queried_object_id());
    if (!$product) { global $product; }
    if (!$product) return;

    // Build size options from the product's Size attribute (parse inch width)
    $size_opts = array();
    $size_terms = $product->get_attribute('pa_size');
    if (!$size_terms) $size_terms = $product->get_attribute('size');
    if ($size_terms) {
        foreach (array_map('trim', explode(',', $size_terms)) as $s) {
            if (preg_match('/(\d+(?:\.\d+)?)\s*[x×*]\s*(\d+(?:\.\d+)?)/i', $s, $m)) {
                $w = floatval($m[1]);
                // If dimensions are in feet, convert to inches
                if (stripos($s, 'ft') !== false || stripos($s, 'feet') !== false) $w *= 12;
                $size_opts[$w] = $s;
            }
        }
    }
    if (empty($size_opts)) {
        $size_opts = array(24 => '24 inches wide', 36 => '36 inches wide', 48 => '48 inches wide', 60 => '60 inches wide');
    }
    ?>
    <div id="af-arw-overlay" class="af-arw-overlay" data-arw-close>
      <div class="af-arw-modal">
        <div class="af-arw-head">
          <h3>Try It On Your Wall</h3>
          <button type="button" class="af-arw-close" data-arw-close aria-label="Close">&times;</button>
        </div>
        <div class="af-arw-body">
          <div class="af-arw-stage-wrap">
            <div id="af-arw-stage" class="af-arw-stage">
              <img id="af-arw-wall" class="af-arw-wall" alt="" style="display:none">
              <div id="af-arw-placeholder" class="af-arw-placeholder">
                📷 Upload a photo of your wall to preview this artwork in your space. Then drag to position and adjust the size.
              </div>
              <img id="af-arw-art" class="af-arw-art" alt="Artwork preview" style="display:none" crossorigin="anonymous">
            </div>
          </div>
          <div class="af-arw-panel">
            <div class="af-arw-field">
              <label>1. Your wall photo</label>
              <label class="af-arw-upload">
                <input type="file" id="af-arw-wall-file" accept="image/*" hidden>
                ⬆ Upload wall photo
              </label>
            </div>
            <div class="af-arw-field">
              <label>2. Artwork size</label>
              <select id="af-arw-size" class="af-arw-size-select">
                <?php foreach ($size_opts as $w => $label): ?>
                  <option value="<?php echo esc_attr($w); ?>"><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="af-arw-field">
              <label>3. Your wall width (inches)</label>
              <input type="number" id="af-arw-wallwidth" class="af-arw-size-select" placeholder="e.g. 120" min="12" step="1">
              <p class="af-arw-hint">Enter the real width of the wall in the photo, so the artwork appears true-to-scale.</p>
            </div>
            <div class="af-arw-field">
              <label>Fine-tune size</label>
              <input type="range" id="af-arw-scale" min="50" max="150" value="100">
            </div>
            <div class="af-arw-actions">
              <button type="button" class="af-arw-reset" id="af-arw-reset">Reset</button>
              <button type="button" class="af-arw-save" id="af-arw-save">Save preview</button>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php
}, 50);

// ─────────────────────────────────────────────────────────────
// PHASE 4 — 4-column site footer (brand · shop · service · company).
// Theme-code footer, appended sitewide. Reversible via this block.
// ─────────────────────────────────────────────────────────────
add_action('wp_footer', function() {
    if (is_admin()) return;
    // DISABLED: the theme's own Elementor footer is used instead, to avoid a
    // duplicate/stacked footer. Kept here (reversible) in case it's wanted later.
    return;

    // Column 2: top shop categories (by product count), fall back gracefully
    $shop_cats = get_terms(array(
        'taxonomy'=>'product_cat','hide_empty'=>true,'number'=>7,
        'orderby'=>'count','order'=>'DESC','exclude'=>array(get_option('default_product_cat')),
    ));
    ?>
    <footer class="af-footer" aria-label="Site footer">
      <div class="af-footer-inner">

        <div class="af-fcol af-fcol-brand">
          <h4 class="af-f-logo">The Art Framer</h4>
          <p class="af-f-blurb">Premium digital canvas wall art &amp; framed prints — spiritual, cultural, and modern pieces crafted with archival, fade-resistant inks. Ready to hang, made to inspire.</p>
          <ul class="af-f-contact">
            <li>📍 Delaware, USA</li>
            <li>📞 <a href="tel:+16104707280">+1 (610) 470-7280</a></li>
            <li>✉️ <a href="mailto:theartframer136@gmail.com">theartframer136@gmail.com</a></li>
          </ul>
          <div class="af-f-social">
            <a href="https://www.facebook.com/" target="_blank" rel="noopener" aria-label="Facebook">
              <svg viewBox="0 0 24 24" fill="currentColor"><path d="M22 12a10 10 0 10-11.5 9.9v-7H8v-2.9h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.5h-1.3c-1.2 0-1.6.8-1.6 1.6v1.8H16l-.4 2.9h-2.1v7A10 10 0 0022 12z"/></svg>
            </a>
            <a href="https://www.instagram.com/" target="_blank" rel="noopener" aria-label="Instagram">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg>
            </a>
            <a href="https://wa.me/16104707280" target="_blank" rel="noopener" aria-label="WhatsApp">
              <svg viewBox="0 0 24 24" fill="currentColor"><path d="M.057 24l1.687-6.163a11.867 11.867 0 01-1.587-5.945C.16 5.335 5.495 0 12.05 0a11.82 11.82 0 018.413 3.488 11.82 11.82 0 013.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 01-5.688-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884a9.86 9.86 0 001.51 5.26l-.999 3.648 3.978-1.005z"/></svg>
            </a>
          </div>
        </div>

        <div class="af-fcol">
          <h5 class="af-f-title">Shop</h5>
          <ul class="af-f-links">
            <?php if (!is_wp_error($shop_cats) && $shop_cats): foreach ($shop_cats as $c): ?>
              <li><a href="<?php echo esc_url(get_term_link($c)); ?>"><?php echo esc_html($c->name); ?></a></li>
            <?php endforeach; endif; ?>
            <li><a href="/shop/">View All Products</a></li>
          </ul>
        </div>

        <div class="af-fcol">
          <h5 class="af-f-title">Customer Service</h5>
          <ul class="af-f-links">
            <li><a href="/help-support/">Help &amp; Support</a></li>
            <li><a href="/shipping-delivery/">Shipping &amp; Delivery</a></li>
            <li><a href="/returns-exchanges/">Returns &amp; Exchanges</a></li>
            <li><a href="/refund-policy/">Refund Policy</a></li>
            <li><a href="/track-your-order/">Track Your Order</a></li>
            <li><a href="/faqs/">FAQs</a></li>
          </ul>
        </div>

        <div class="af-fcol">
          <h5 class="af-f-title">Company</h5>
          <ul class="af-f-links">
            <li><a href="/about/">About Us</a></li>
            <li><a href="/contact/">Contact</a></li>
            <li><a href="/wholesale-corporate/">Wholesale &amp; Corporate</a></li>
            <li><a href="/privacy-policy/">Privacy Policy</a></li>
            <li><a href="/terms-conditions/">Terms &amp; Conditions</a></li>
            <li><a href="/content-ethics-policy/">Content &amp; Ethics</a></li>
          </ul>
          <div class="af-f-news">
            <h5 class="af-f-title">Newsletter</h5>
            <?php if (shortcode_exists('mc4wp_form')): ?>
              <?php echo do_shortcode('[mc4wp_form]'); ?>
            <?php else: ?>
              <form class="af-f-newsform" action="/contact/" method="get" onsubmit="window.location='/contact/';return false;">
                <input type="email" placeholder="Your email" aria-label="Email for newsletter">
                <button type="submit">Join</button>
              </form>
            <?php endif; ?>
          </div>
        </div>

      </div>
      <div class="af-footer-bottom">
        <p>&copy; <?php echo date('Y'); ?> The Art Framer &middot; theartframer.us &middot; All rights reserved.</p>
        <p class="af-f-pay">🔒 Secure checkout &middot; Square &middot; Zelle &middot; COD</p>
      </div>
    </footer>
    <style>
    .af-footer{background:#141414;color:#cfcfcf;padding:48px 16px 0;border-top:3px solid #c9a84c;}
    .af-footer-inner{max-width:1240px;margin:0 auto;display:grid;grid-template-columns:1.6fr 1fr 1fr 1.4fr;gap:34px;}
    .af-fcol-brand{max-width:340px;}
    .af-f-logo{font-size:22px;font-weight:800;color:#fff;margin:0 0 12px;letter-spacing:.02em;}
    .af-f-blurb{font-size:13px;line-height:1.7;color:#a8a8a8;margin:0 0 16px;}
    .af-f-contact{list-style:none;margin:0 0 16px;padding:0;display:flex;flex-direction:column;gap:7px;}
    .af-f-contact li{font-size:13px;color:#b8b8b8;}
    .af-f-contact a{color:#b8b8b8;text-decoration:none;}
    .af-f-contact a:hover{color:#c9a84c;}
    .af-f-social{display:flex;gap:12px;}
    .af-f-social a{width:36px;height:36px;border-radius:50%;background:#242424;display:flex;align-items:center;justify-content:center;color:#cfcfcf;transition:background .2s,color .2s;}
    .af-f-social a:hover{background:#c9a84c;color:#141414;}
    .af-f-social svg{width:18px;height:18px;}
    .af-f-title{font-size:14px;font-weight:700;color:#fff;margin:0 0 16px;text-transform:uppercase;letter-spacing:.06em;}
    .af-f-links{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:10px;}
    .af-f-links a{font-size:13px;color:#a8a8a8;text-decoration:none;transition:color .2s;}
    .af-f-links a:hover{color:#c9a84c;padding-left:3px;}
    .af-f-news{margin-top:22px;}
    .af-f-newsform{display:flex;gap:8px;}
    .af-f-newsform input{flex:1;min-width:0;padding:9px 11px;border:1px solid #333;border-radius:6px;background:#1e1e1e;color:#eee;font-size:13px;}
    .af-f-newsform button{padding:9px 16px;border:none;border-radius:6px;background:#c9a84c;color:#141414;font-weight:700;font-size:13px;cursor:pointer;}
    .af-f-newsform button:hover{background:#dcb85a;}
    .af-footer-bottom{max-width:1240px;margin:38px auto 0;border-top:1px solid #2a2a2a;padding:18px 0;display:flex;flex-wrap:wrap;justify-content:space-between;gap:8px;}
    .af-footer-bottom p{margin:0;font-size:12px;color:#8a8a8a;}
    @media(max-width:900px){
      .af-footer-inner{grid-template-columns:1fr 1fr;gap:28px;}
      .af-fcol-brand{grid-column:1 / -1;max-width:none;}
    }
    @media(max-width:560px){
      .af-footer-inner{grid-template-columns:1fr;}
      .af-footer-bottom{flex-direction:column;text-align:center;}
    }
    </style>
    <?php
}, 300);

// ─────────────────────────────────────────────────────────────
// PHASE 2 — Header: top utility/help-support bar (Layer 1) + sticky
// Additive; injected above the theme header. Reversible via this block.
// ─────────────────────────────────────────────────────────────
// Render via wp_footer (universally called) + JS relocates the bar to the
// very top of <body>, so it works even if the theme omits wp_body_open().
add_action('wp_footer', function() {
    if (is_admin()) return;
    ?>
    <div class="af-utilitybar">
      <div class="af-ub-inner">
        <div class="af-ub-msg" aria-live="polite">
          <span class="af-ub-rot">✨ Free Shipping across the USA on Premium Canvas Wall Art</span>
        </div>
        <nav class="af-ub-links" aria-label="Support and account">
          <a href="/track-your-order/" class="af-ub-link">🚚 Track Order</a>
          <a href="/help-support/" class="af-ub-link">❓ Help</a>
          <a href="tel:+16104707280" class="af-ub-link af-ub-phone">📞 +1 (610) 470-7280</a>
          <span class="af-ub-cur">USD $</span>
        </nav>
      </div>
    </div>
    <script>
    (function(){
      // Ensure the utility bar sits at the very top of <body> (theme-independent)
      var bar = document.querySelector('.af-utilitybar');
      if (bar && document.body && document.body.firstElementChild !== bar) {
        document.body.insertBefore(bar, document.body.firstChild);
      }
      // Rotating announcement messages
      var msgs = [
        '✨ Free Shipping across the USA on Premium Canvas Wall Art',
        '🖼️ Try "On Your Wall" preview on any product before you buy',
        '🎨 Custom sizes & frames available — message us on WhatsApp',
        '⭐ Trusted by art lovers — archival, fade-resistant prints'
      ];
      var el = document.querySelector('.af-ub-rot');
      if (el) {
        var i = 0;
        setInterval(function(){
          i = (i + 1) % msgs.length;
          el.style.opacity = '0';
          setTimeout(function(){ el.textContent = msgs[i]; el.style.opacity = '1'; }, 300);
        }, 4000);
      }
      // Sticky header on scroll — add class to the theme header
      var header = document.querySelector(
        'header.site-header, #masthead, .site-header, header#header, ' +
        '.postero-header, .header-main, [class*="site-header"], header[class*="header"]'
      );
      if (header) {
        var lastY = 0;
        window.addEventListener('scroll', function(){
          var y = window.pageYOffset || document.documentElement.scrollTop;
          if (y > 120) header.classList.add('af-header-stuck');
          else header.classList.remove('af-header-stuck');
          lastY = y;
        }, { passive: true });
      }
    })();
    </script>
    <style>
    .af-utilitybar{
      background:linear-gradient(90deg,#141414,#2a2416);color:#e8e2cf;
      font-size:13px;padding:7px 16px;border-bottom:1px solid rgba(201,168,76,.25);
      position:relative;z-index:9;
    }
    .af-ub-inner{max-width:1300px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;gap:16px;}
    .af-ub-msg{flex:1 1 auto;min-width:0;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;}
    .af-ub-rot{transition:opacity .3s;display:inline-block;}
    .af-ub-links{display:flex;align-items:center;gap:18px;flex-shrink:0;}
    .af-ub-link{color:#e8e2cf;text-decoration:none;transition:color .2s;white-space:nowrap;}
    .af-ub-link:hover{color:#c9a84c;}
    .af-ub-cur{color:#c9a84c;font-weight:700;border:1px solid rgba(201,168,76,.4);border-radius:4px;padding:1px 8px;}
    /* Sticky header polish */
    .af-header-stuck{
      position:sticky !important;top:0 !important;z-index:9999 !important;
      box-shadow:0 4px 18px rgba(0,0,0,.12) !important;
      animation:af-slidedown .3s ease !important;
    }
    @keyframes af-slidedown{from{transform:translateY(-6px);opacity:.85;}to{transform:translateY(0);opacity:1;}}
    @media(max-width:768px){
      .af-ub-msg{font-size:12px;}
      .af-ub-phone,.af-ub-cur{display:none;}
      .af-ub-links{gap:12px;}
    }
    @media(max-width:480px){
      .af-ub-link span,.af-ub-link{font-size:11.5px;}
    }
    </style>
    <?php
}, 5);

// ─────────────────────────────────────────────────────────────
// PHASE 3 — Homepage: floating quick-access panel (sitewide) +
// trust badges bar (front page). Additive; reversible via this block.
// ─────────────────────────────────────────────────────────────

// 3a. Floating quick-access side panel — WhatsApp, Wishlist, Track, Top
add_action('wp_footer', function() {
    if (is_admin()) return;
    ?>
    <div class="af-quickpanel" aria-label="Quick access">
      <a class="af-qp-btn af-qp-wa" href="https://wa.me/16104707280" target="_blank" rel="noopener" data-tip="Chat on WhatsApp" aria-label="Chat on WhatsApp">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M.057 24l1.687-6.163a11.867 11.867 0 01-1.587-5.945C.16 5.335 5.495 0 12.05 0a11.82 11.82 0 018.413 3.488 11.82 11.82 0 013.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 01-5.688-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884a9.86 9.86 0 001.51 5.26l-.999 3.648 3.978-1.005zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.767.967-.94 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.71.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
      </a>
      <a class="af-qp-btn af-qp-wl" href="/wishlist/" data-tip="Wishlist" aria-label="Wishlist">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
      </a>
      <a class="af-qp-btn af-qp-tr" href="/track-your-order/" data-tip="Track Order" aria-label="Track Order">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
      </a>
      <button class="af-qp-btn af-qp-top" id="af-qp-top" data-tip="Back to top" aria-label="Back to top">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 15l-6-6-6 6"/></svg>
      </button>
    </div>
    <script>
    (function(){
      var top = document.getElementById('af-qp-top');
      if (top) top.addEventListener('click', function(){ window.scrollTo({top:0,behavior:'smooth'}); });
      window.addEventListener('scroll', function(){
        var y = window.pageYOffset||document.documentElement.scrollTop;
        if (top) top.style.opacity = y>400 ? '1':'0';
        if (top) top.style.pointerEvents = y>400 ? 'auto':'none';
      }, {passive:true});
    })();
    </script>
    <style>
    .af-quickpanel{position:fixed;right:16px;bottom:20px;z-index:9998;display:flex;flex-direction:column;gap:12px;}
    .af-qp-btn{
      width:48px;height:48px;border-radius:50%;border:none;cursor:pointer;
      display:flex;align-items:center;justify-content:center;
      box-shadow:0 4px 14px rgba(0,0,0,.22);transition:transform .2s,box-shadow .2s;
      position:relative;color:#fff;text-decoration:none;
    }
    .af-qp-btn:hover{transform:scale(1.08);box-shadow:0 6px 20px rgba(0,0,0,.3);}
    .af-qp-btn svg{width:24px;height:24px;}
    .af-qp-wa{background:#25d366;}
    .af-qp-wl{background:#c9a84c;}
    .af-qp-tr{background:#1a1a1a;}
    .af-qp-top{background:#555;opacity:0;pointer-events:none;transition:opacity .3s,transform .2s;}
    .af-qp-btn[data-tip]:hover::after{
      content:attr(data-tip);position:absolute;right:58px;top:50%;transform:translateY(-50%);
      background:#1a1a1a;color:#fff;font-size:12px;white-space:nowrap;padding:5px 10px;border-radius:6px;
    }
    @media(max-width:600px){
      .af-quickpanel{right:12px;bottom:14px;gap:10px;}
      .af-qp-btn{width:44px;height:44px;}
      .af-qp-btn svg{width:21px;height:21px;}
      .af-qp-btn[data-tip]:hover::after{display:none;}
    }
    </style>
    <?php
}, 20);

// 3b. Trust badges bar — front page only, injected above the footer
add_action('wp_footer', function() {
    if (!is_front_page()) return;
    ?>
    <div class="af-trustbar">
      <div class="af-trust-inner">
        <div class="af-trust-item"><span class="af-trust-ico">🚚</span><div><strong>Free USA Shipping</strong><small>On premium canvas art</small></div></div>
        <div class="af-trust-item"><span class="af-trust-ico">🎨</span><div><strong>Archival Quality</strong><small>Fade-resistant inks</small></div></div>
        <div class="af-trust-item"><span class="af-trust-ico">🔒</span><div><strong>Secure Checkout</strong><small>Encrypted payments</small></div></div>
        <div class="af-trust-item"><span class="af-trust-ico">↩️</span><div><strong>Easy Returns</strong><small>7-day policy</small></div></div>
        <div class="af-trust-item"><span class="af-trust-ico">🖼️</span><div><strong>Try On Your Wall</strong><small>Preview before buying</small></div></div>
      </div>
    </div>
    <script>
    (function(){
      // Place the trust bar just before the footer/policy bar for a natural flow
      var bar = document.querySelector('.af-trustbar');
      var anchor = document.querySelector('.af-footer') || document.querySelector('footer');
      if (bar && anchor && anchor.parentNode) anchor.parentNode.insertBefore(bar, anchor);
    })();
    </script>
    <style>
    .af-trustbar{background:#faf7ef;border-top:1px solid #ece4cf;border-bottom:1px solid #ece4cf;padding:22px 16px;}
    .af-trust-inner{max-width:1200px;margin:0 auto;display:flex;flex-wrap:wrap;gap:20px;justify-content:space-between;}
    .af-trust-item{display:flex;align-items:center;gap:12px;flex:1 1 180px;min-width:170px;}
    .af-trust-ico{font-size:26px;line-height:1;}
    .af-trust-item strong{display:block;font-size:14px;color:#1a1a1a;}
    .af-trust-item small{display:block;font-size:12px;color:#777;}
    @media(max-width:600px){
      .af-trust-inner{gap:14px;}
      .af-trust-item{flex:1 1 45%;min-width:140px;}
    }
    </style>
    <?php
}, 25);

// ─────────────────────────────────────────────────────────────
// PHASE 5 — Category/Listing pages: filter & sort toolbar,
// subcategory chips, price filter, AR "Try on Wall" hooks on cards.
// Uses native WooCommerce params so filtering reliably works.
// ─────────────────────────────────────────────────────────────

// 5a. Enqueue AR assets on shop/category pages too (for card AR links -> product)
add_action('wp_enqueue_scripts', function() {
    if (function_exists('is_shop') && (is_shop() || is_product_category() || is_product_tag())) {
        // AR modal lives on the product page; listing only needs the toolbar CSS.
    }
}, 26);

// 5b. Filter / sort toolbar above the product loop
add_action('woocommerce_before_shop_loop', function() {
    if (is_admin()) return;

    // Subcategory chips: children of current category, else top-level categories
    $current_id = 0;
    if (is_product_category()) { $obj = get_queried_object(); $current_id = $obj->term_id ?? 0; }
    $chips = get_terms(array(
        'taxonomy'=>'product_cat','hide_empty'=>true,'parent'=>$current_id,
        'orderby'=>'count','order'=>'DESC','number'=>12,
    ));

    // Filterable global attributes
    $sizes  = get_terms(array('taxonomy'=>'pa_size','hide_empty'=>true,'number'=>30));
    $colors = get_terms(array('taxonomy'=>'pa_colors','hide_empty'=>true,'number'=>12));
    $frames = get_terms(array('taxonomy'=>'pa_frame','hide_empty'=>true,'number'=>12));
    $tags   = get_terms(array('taxonomy'=>'product_tag','hide_empty'=>true,'orderby'=>'count','order'=>'DESC','number'=>25));

    $base_url = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
    // Current price bounds
    $cur_min = isset($_GET['min_price']) ? (float)$_GET['min_price'] : '';
    $cur_max = isset($_GET['max_price']) ? (float)$_GET['max_price'] : '';
    // Helper: current URL params minus paging, for merging filter links
    $keep = $_GET;
    unset($keep['paged']);
    // Build a link that ADDS a filter param while keeping the others
    $flink = function($key, $val) use ($keep) {
        $q = $keep; $q[$key] = $val; $q['query_type_'.str_replace('filter_','',$key)] = 'or';
        return '?' . http_build_query($q);
    };
    $has_filters = !empty($_GET['filter_colors']) || !empty($_GET['filter_size']) || !empty($_GET['filter_frame']) || $cur_min !== '' || $cur_max !== '';
    ?>
    <div class="af-listing-toolbar">
      <?php if (!is_wp_error($chips) && $chips): ?>
      <div class="af-lt-chips" role="navigation" aria-label="Categories">
        <a class="af-chip<?php echo $current_id? '' : ' af-chip-active'; ?>" href="/shop/">All</a>
        <?php foreach ($chips as $c): ?>
          <a class="af-chip" href="<?php echo esc_url(get_term_link($c)); ?>"><?php echo esc_html($c->name); ?> <span><?php echo (int)$c->count; ?></span></a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div class="af-lt-controls">
        <!-- Price -->
        <div class="af-lt-drop">
          <button type="button" class="af-lt-dbtn">Price ▾</button>
          <div class="af-lt-menu af-lt-price">
            <form method="get" action="">
              <?php foreach ($keep as $k=>$v){ if(in_array($k,array('min_price','max_price'),true)) continue; echo '<input type="hidden" name="'.esc_attr($k).'" value="'.esc_attr(is_array($v)?reset($v):$v).'">'; } ?>
              <div class="af-price-row">
                <input type="number" name="min_price" placeholder="Min $" value="<?php echo esc_attr($cur_min); ?>" min="0">
                <span>–</span>
                <input type="number" name="max_price" placeholder="Max $" value="<?php echo esc_attr($cur_max); ?>" min="0">
              </div>
              <button type="submit" class="af-price-apply">Apply</button>
            </form>
          </div>
        </div>

        <?php if (!is_wp_error($sizes) && $sizes): ?>
        <div class="af-lt-drop">
          <button type="button" class="af-lt-dbtn">Size ▾</button>
          <div class="af-lt-menu">
            <?php foreach ($sizes as $t): ?>
              <a href="<?php echo esc_url($flink('filter_size',$t->slug)); ?>"><?php echo esc_html($t->name); ?></a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php if (!is_wp_error($colors) && $colors): ?>
        <div class="af-lt-drop">
          <button type="button" class="af-lt-dbtn">Frame Color ▾</button>
          <div class="af-lt-menu">
            <?php foreach ($colors as $t): ?>
              <a href="<?php echo esc_url($flink('filter_colors',$t->slug)); ?>"><?php echo esc_html($t->name); ?></a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php if (!is_wp_error($frames) && $frames): ?>
        <div class="af-lt-drop">
          <button type="button" class="af-lt-dbtn">Frame Type ▾</button>
          <div class="af-lt-menu">
            <?php foreach ($frames as $t): ?>
              <a href="<?php echo esc_url($flink('filter_frame',$t->slug)); ?>"><?php echo esc_html($t->name); ?></a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($has_filters): ?>
          <a class="af-lt-clear" href="<?php echo esc_url($base_url); ?>">✕ Clear filters</a>
        <?php endif; ?>
      </div>

      <?php if (!is_wp_error($tags) && $tags):
        $active_tag = isset($_GET['product_tag']) ? sanitize_title($_GET['product_tag']) : '';
        // Merge product_tag into current params (keeps other filters)
        $tlink = function($slug) use ($keep){ $q=$keep; $q['product_tag']=$slug; return '?'.http_build_query($q); };
        $clear_tag = $keep; unset($clear_tag['product_tag']);
      ?>
      <div class="af-lt-tags" role="navigation" aria-label="Tags">
        <span class="af-lt-tagslabel">Tags:</span>
        <?php foreach ($tags as $t): $on = ($active_tag === $t->slug); ?>
          <a class="af-tag-btn<?php echo $on?' active':''; ?>"
             href="<?php echo esc_url($on ? '?'.http_build_query($clear_tag) : $tlink($t->slug)); ?>">
            #<?php echo esc_html($t->name); ?>
          </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <script>
    (function(){
      document.querySelectorAll('.af-lt-dbtn').forEach(function(b){
        b.addEventListener('click', function(e){
          e.stopPropagation();
          var m = this.nextElementSibling;
          document.querySelectorAll('.af-lt-menu.open').forEach(function(o){ if(o!==m) o.classList.remove('open'); });
          m.classList.toggle('open');
        });
      });
      document.addEventListener('click', function(){ document.querySelectorAll('.af-lt-menu.open').forEach(function(o){ o.classList.remove('open'); }); });
    })();
    </script>
    <?php
}, 5);

// 5c. Add "Try on Wall" AR hook button on each listing card
add_action('woocommerce_after_shop_loop_item', function() {
    global $product;
    if (!$product) return;
    $url = add_query_arg('product', $product->get_id(), home_url('/try-on-wall/'));
    echo '<a class="af-card-ar" href="'.esc_url($url).'" aria-label="Try on your wall">'
       . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>'
       . '<span>Try on Wall</span></a>';
}, 15);

// 5d. Listing toolbar + card AR styling + grid polish
add_action('wp_head', function() {
    if (!function_exists('is_shop')) return;
    if (!(is_shop() || is_product_category() || is_product_tag())) return;
    ?>
    <style>
    .af-listing-toolbar{max-width:1240px;margin:0 auto 20px;padding:14px 0;border-bottom:1px solid #eee;display:flex;flex-direction:column;gap:12px;}
    .af-lt-chips{display:flex;flex-wrap:wrap;gap:8px;}
    .af-chip{display:inline-flex;align-items:center;gap:5px;padding:6px 13px;border-radius:20px;background:#f4f1e9;color:#333;font-size:13px;font-weight:600;text-decoration:none;transition:background .2s,color .2s;}
    .af-chip:hover{background:#c9a84c;color:#fff;}
    .af-chip-active{background:#1a1a1a;color:#fff;}
    .af-chip span{font-size:11px;opacity:.7;}
    .af-lt-controls{display:flex;flex-wrap:wrap;gap:10px;align-items:center;}
    .af-lt-drop{position:relative;}
    .af-lt-dbtn{background:#fff;border:1px solid #ddd;border-radius:8px;padding:8px 14px;font-size:13px;font-weight:600;cursor:pointer;color:#333;}
    .af-lt-dbtn:hover{border-color:#c9a84c;color:#a8872e;}
    .af-lt-menu{position:absolute;top:calc(100% + 6px);left:0;z-index:50;background:#fff;border:1px solid #eee;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.12);padding:8px;min-width:170px;display:none;max-height:280px;overflow:auto;}
    .af-lt-menu.open{display:block;}
    .af-lt-menu a{display:block;padding:8px 12px;font-size:13px;color:#333;text-decoration:none;border-radius:6px;}
    .af-lt-menu a:hover{background:#faf7ef;color:#a8872e;}
    .af-lt-price{padding:14px;min-width:210px;}
    .af-price-row{display:flex;align-items:center;gap:8px;margin-bottom:10px;}
    .af-price-row input{width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:6px;font-size:13px;}
    .af-price-row span{color:#999;}
    .af-price-apply{width:100%;background:#1a1a1a;color:#fff;border:none;border-radius:7px;padding:9px;font-size:13px;font-weight:700;cursor:pointer;}
    .af-price-apply:hover{background:#c9a84c;}
    .af-lt-clear{font-size:12.5px;color:#c0392b;text-decoration:none;font-weight:600;margin-left:4px;}
    /* Tag filter — simple clickable buttons */
    .af-lt-tags{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin-top:6px;}
    .af-lt-tagslabel{font-size:12.5px;font-weight:700;color:#666;margin-right:2px;}
    .af-tag-btn{display:inline-block;padding:5px 12px;border-radius:16px;background:#fff;border:1.5px solid #e2ddcf;color:#6b6250;font-size:12.5px;font-weight:600;text-decoration:none;transition:all .15s;}
    .af-tag-btn:hover{border-color:#c9a84c;color:#a8872e;}
    .af-tag-btn.active{background:#c9a84c;border-color:#c9a84c;color:#fff;}
    /* Card AR button */
    .woocommerce ul.products li.product{position:relative;}
    .af-card-ar{position:absolute;left:10px;bottom:64px;z-index:6;display:inline-flex;align-items:center;gap:5px;background:rgba(20,20,20,.86);color:#fff;font-size:11.5px;font-weight:600;padding:6px 10px;border-radius:20px;text-decoration:none;opacity:0;transform:translateY(6px);transition:opacity .25s,transform .25s,background .2s;}
    .woocommerce ul.products li.product:hover .af-card-ar{opacity:1;transform:translateY(0);}
    .af-card-ar:hover{background:#c9a84c;}
    .af-card-ar svg{width:14px;height:14px;}
    @media(max-width:768px){ .af-card-ar{opacity:1;transform:none;left:8px;bottom:auto;top:8px;padding:5px 8px;font-size:11px;} .af-card-ar span{display:none;} }
    </style>
    <?php
}, 20);

// ─────────────────────────────────────────────────────────────
// PHASE 7 — Product page missing sections (per spec):
// Buy Now, trust badges under CTA, Related Searches, Popular
// Products, Recently Viewed. Additive; existing sections untouched.
// ─────────────────────────────────────────────────────────────

// 7a. Track recently-viewed products (cookie)
add_action('template_redirect', function() {
    if (!function_exists('is_product') || !is_product()) return;
    global $post;
    if (!$post) return;
    $ids = isset($_COOKIE['af_recently_viewed']) ? array_filter(array_map('absint', explode('|', $_COOKIE['af_recently_viewed']))) : array();
    $ids = array_diff($ids, array($post->ID));
    array_unshift($ids, $post->ID);
    $ids = array_slice(array_unique($ids), 0, 12);
    wc_setcookie('af_recently_viewed', implode('|', $ids), time() + 60*60*24*30);
}, 20);

// 7b. "Buy Now" button beside Add to Cart.
//     Simple products: direct link to checkout with add-to-cart.
//     Variable products: JS adds the chosen variation, then redirects.
add_action('woocommerce_after_add_to_cart_button', function() {
    global $product;
    if (!$product || !$product->is_purchasable() || !$product->is_in_stock()) return;

    if ($product->is_type('simple')) {
        $url = esc_url(wc_get_checkout_url() . '?add-to-cart=' . $product->get_id());
        echo '<a href="' . $url . '" class="af-buynow button">Buy Now</a>';
    } elseif ($product->is_type('variable')) {
        // Button submits the variation form, then redirects to checkout.
        echo '<button type="button" class="af-buynow af-buynow-var button" data-checkout="' . esc_url(wc_get_checkout_url()) . '">Buy Now</button>';
        ?>
        <script>
        (function(){
          document.addEventListener('click', function(e){
            var b = e.target.closest('.af-buynow-var'); if(!b) return;
            e.preventDefault();
            var form = b.closest('form.cart');
            if(!form){ return; }
            var vid = form.querySelector('input[name="variation_id"]');
            if(!vid || !vid.value || vid.value === '0'){
              alert('Please select the options (size / frame / color) first.');
              return;
            }
            // Mark that we want to go to checkout after add-to-cart, then submit.
            var flag = document.createElement('input');
            flag.type='hidden'; flag.name='af_buy_now'; flag.value='1';
            form.appendChild(flag);
            var addBtn = form.querySelector('.single_add_to_cart_button');
            if(addBtn){ addBtn.click(); } else { form.submit(); }
          });
        })();
        </script>
        <?php
    }
}, 4);

// Redirect to checkout when Buy Now was used on a variable product
add_filter('woocommerce_add_to_cart_redirect', function($url){
    if (!empty($_REQUEST['af_buy_now'])) { return wc_get_checkout_url(); }
    return $url;
}, 20);

// 7c. Trust badges directly under the CTA
add_action('woocommerce_after_add_to_cart_button', function() {
    global $product;
    if (!$product) return;
    ?>
    <div class="af-pp-trust">
      <div class="af-ppt"><span>🔒</span><div><strong>Secure Payments</strong><small>Encrypted checkout</small></div></div>
      <div class="af-ppt"><span>↩️</span><div><strong>Easy Returns</strong><small>7-day policy</small></div></div>
      <div class="af-ppt"><span>🚚</span><div><strong>Fast Shipping</strong><small>Free across USA</small></div></div>
    </div>
    <?php
}, 25);

// 7d. Related Searches (from product tags + category) after summary
add_action('woocommerce_after_single_product_summary', function() {
    global $product;
    if (!$product) return;
    $terms = array();
    foreach (array('product_tag','product_cat') as $tax) {
        $t = get_the_terms($product->get_id(), $tax);
        if ($t && !is_wp_error($t)) foreach ($t as $term) $terms[$term->name] = get_term_link($term);
    }
    if (count($terms) < 2) return;
    $terms = array_slice($terms, 0, 10, true);
    echo '<section class="af-pp-sec af-related-searches"><h2>Related Searches</h2><div class="af-rs-chips">';
    foreach ($terms as $name => $link) {
        if (is_wp_error($link)) continue;
        echo '<a class="af-rs-chip" href="'.esc_url($link).'">'.esc_html($name).'</a>';
    }
    echo '</div></section>';
}, 16);

// 7e. Popular Products (best sellers) after tabs
add_action('woocommerce_after_single_product_summary', function() {
    global $product;
    if (!$product) return;
    $ids = wc_get_products(array(
        'status'=>'publish','limit'=>12,'orderby'=>'meta_value_num','meta_key'=>'total_sales',
        'order'=>'DESC','exclude'=>array($product->get_id()),'return'=>'ids',
    ));
    if (count($ids) < 4) { // fallback: recent if not enough sales data
        $ids = wc_get_products(array('status'=>'publish','limit'=>12,'orderby'=>'date','order'=>'DESC','exclude'=>array($product->get_id()),'return'=>'ids'));
    }
    $ids = af_ids_with_image($ids, 4);
    if (count($ids) < 4) return;
    echo '<section class="af-pp-sec af-popular"><h2>Popular Products</h2><div class="af-pp-row">';
    foreach ($ids as $pid) { af_render_mini_card($pid); }
    echo '</div></section>';
}, 21);

// 7f. Recently Viewed after tabs
add_action('woocommerce_after_single_product_summary', function() {
    global $product;
    if (!$product) return;
    $ids = isset($_COOKIE['af_recently_viewed']) ? array_filter(array_map('absint', explode('|', $_COOKIE['af_recently_viewed']))) : array();
    $ids = array_values(array_diff($ids, array($product->get_id())));
    $ids = af_ids_with_image($ids, 4);
    if (count($ids) < 2) return;
    echo '<section class="af-pp-sec af-recent"><h2>Recently Viewed</h2><div class="af-pp-row af-pp-row-left">';
    foreach ($ids as $pid) { af_render_mini_card($pid); }
    echo '</div></section>';
}, 22);

// Filter product IDs down to those with a real featured image (avoids
// grey placeholder cards), capped at $max.
function af_ids_with_image($ids, $max) {
    $out = array();
    foreach ((array) $ids as $pid) {
        $p = wc_get_product($pid);
        if (!$p || $p->get_status() !== 'publish' || !$p->get_image_id()) continue;
        $out[] = $pid;
        if (count($out) >= $max) break;
    }
    return $out;
}

// Shared mini product card renderer (assumes a valid featured image)
function af_render_mini_card($pid) {
    $p = wc_get_product($pid);
    if (!$p || $p->get_status() !== 'publish') return;
    $img = wp_get_attachment_image_url($p->get_image_id(),'medium');
    if (!$img) return; // never render a placeholder card
    echo '<a class="af-mini-card" href="'.esc_url(get_permalink($pid)).'">';
    echo '<div class="af-mini-img"><img src="'.esc_url($img).'" alt="'.esc_attr($p->get_name()).'" loading="lazy"></div>';
    echo '<div class="af-mini-info"><span class="af-mini-title">'.esc_html($p->get_name()).'</span>';
    echo '<span class="af-mini-price">'.$p->get_price_html().'</span></div></a>';
}

// 7g. Styles for the new product-page sections
add_action('wp_head', function() {
    if (!function_exists('is_product') || !is_product()) return;
    ?>
    <style>
    /* Buy Now */
    .single-product .af-buynow.button{background:#1a1a1a !important;color:#fff !important;margin-left:8px !important;}
    .single-product .af-buynow.button:hover{background:#c9a84c !important;}
    /* Trust badges under CTA */
    .af-pp-trust{display:flex;flex-wrap:wrap;gap:14px;margin:18px 0 0;padding:14px 0 0;border-top:1px solid #eee;width:100%;}
    .af-ppt{display:flex;align-items:center;gap:9px;flex:1 1 150px;min-width:140px;}
    .af-ppt span{font-size:22px;line-height:1;}
    .af-ppt strong{display:block;font-size:12.5px;color:#1a1a1a;}
    .af-ppt small{display:block;font-size:11px;color:#888;}
    /* Post-summary sections — full width of the theme content column so
       they line up with the native "Related products" section. */
    .af-pp-sec{width:100%;max-width:100%;margin:40px 0 0;padding:0;box-sizing:border-box;clear:both;}
    .af-pp-sec h2{font-size:22px;font-weight:800;color:#1a1a1a;margin:0 0 16px;}
    .af-rs-chips{display:flex;flex-wrap:wrap;gap:9px;}
    .af-rs-chip{padding:7px 15px;border-radius:20px;background:#f4f1e9;color:#333;font-size:13px;font-weight:600;text-decoration:none;transition:background .2s,color .2s;}
    .af-rs-chip:hover{background:#c9a84c;color:#fff;}
    /* 4-up grid; fewer items naturally left-align into the first columns */
    .af-pp-row{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;align-items:stretch;}
    .af-mini-card{background:#fff;border:1px solid #eee;border-radius:12px;overflow:hidden;text-decoration:none;transition:box-shadow .25s,transform .25s;display:flex;flex-direction:column;height:100%;}
    .af-mini-card:hover{box-shadow:0 8px 22px rgba(0,0,0,.12);transform:translateY(-3px);}
    .af-mini-img{aspect-ratio:1/1;overflow:hidden;background:#f4f4f4;}
    .af-mini-img img{width:100%;height:100%;object-fit:cover;transition:transform .4s;}
    .af-mini-card:hover .af-mini-img img{transform:scale(1.05);}
    .af-mini-info{padding:11px 13px;display:flex;flex-direction:column;gap:5px;flex:1 1 auto;}
    .af-mini-title{font-size:13px;font-weight:700;color:#1a1a1a;line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;min-height:2.7em;}
    .af-mini-price{font-size:13.5px;font-weight:700;color:#c9a84c;margin-top:auto;}
    @media(max-width:900px){ .af-pp-row{grid-template-columns:repeat(2,1fr);} }
    @media(max-width:600px){ .af-pp-sec h2{font-size:19px;} .af-ppt{flex:1 1 45%;} }
    </style>
    <?php
}, 21);

// ─────────────────────────────────────────────────────────────
// PHASE 7b — Remaining post-tab sections per spec Section 13:
// Digital Downloads + Customize Your Picture. Additive; reversible.
// ─────────────────────────────────────────────────────────────

// 7h. Digital Downloads section (post-tab) — shows Digital Downloads category
add_action('woocommerce_after_single_product_summary', function() {
    global $product;
    if (!$product) return;
    $ids = wc_get_products(array(
        'status'=>'publish','limit'=>12,'return'=>'ids',
        'category'=>array('digital-downloads','instant-downloads','printable-art'),
        'exclude'=>array($product->get_id()),
    ));
    $ids = af_ids_with_image($ids, 4);
    if (count($ids) < 2) return;
    echo '<section class="af-pp-sec af-digital"><h2>Digital Downloads</h2>';
    echo '<p class="af-pp-sub">Instant high-resolution files — delivered to your inbox, ready to print.</p>';
    echo '<div class="af-pp-row">';
    foreach ($ids as $pid) { af_render_mini_card($pid); }
    echo '</div></section>';
}, 23);

// 7i. Customize Your Picture CTA (post-tab)
add_action('woocommerce_after_single_product_summary', function() {
    global $product;
    if (!$product) return;
    // Link to Personalised Prints category if it exists, else Contact
    $term = get_term_by('slug', 'personalised-prints', 'product_cat');
    $url  = $term ? get_term_link($term) : home_url('/contact/');
    if (is_wp_error($url)) $url = home_url('/contact/');
    ?>
    <section class="af-pp-sec af-customize">
      <div class="af-cz-inner">
        <div class="af-cz-text">
          <h2>Customize Your Picture</h2>
          <p>Turn your own photo into premium canvas or framed art — portraits, family collages, and personalised gifts, made to order.</p>
        </div>
        <a class="af-cz-btn" href="<?php echo esc_url($url); ?>">Start Customizing →</a>
      </div>
    </section>
    <?php
}, 24);

// 7j. Styles for the two added sections
add_action('wp_head', function() {
    if (!function_exists('is_product') || !is_product()) return;
    ?>
    <style>
    .af-pp-sub{font-size:13.5px;color:#777;margin:-8px 0 16px;}
    .af-customize{background:linear-gradient(90deg,#141414,#2a2416);border-radius:16px;padding:30px 28px;color:#fff;}
    .af-cz-inner{display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap;}
    .af-cz-text h2{color:#fff;margin:0 0 6px;font-size:22px;}
    .af-cz-text p{margin:0;font-size:14px;color:#cfc7b3;max-width:640px;line-height:1.6;}
    .af-cz-btn{flex-shrink:0;background:#c9a84c;color:#141414;font-weight:800;font-size:14px;padding:13px 24px;border-radius:9px;text-decoration:none;transition:background .2s,transform .2s;white-space:nowrap;}
    .af-cz-btn:hover{background:#dcb85a;transform:translateY(-1px);}
    @media(max-width:600px){ .af-cz-inner{flex-direction:column;align-items:flex-start;} .af-cz-btn{width:100%;text-align:center;} }
    </style>
    <?php
}, 22);

// ─────────────────────────────────────────────────────────────
// PHASE 8 — Universal Size / Frame / Color selector + dynamic
// pricing on ALL products (simple ones). Formula-based; the
// default selection equals the product's current price, so nothing
// is mispriced unless the customer upgrades. Fully adjustable below.
// ─────────────────────────────────────────────────────────────

// ---- Pricing configuration (edit these numbers anytime) ----
function af_pricing_config() {
    return array(
        // Size label => price multiplier (relative to the product's base price)
        'sizes' => array(
            '24×36 in' => 1.00,   // base / smallest — keeps current price
            '30×45 in' => 1.40,
            '36×54 in' => 1.90,
            '48×72 in' => 2.70,
        ),
        // Frame type => flat add-on fee (USD)
        'frames' => array(
            'Without Frame'   => 0,
            'Fibre Frame'     => 25,
            'Floating Frame'  => 40,
            'Aluminium Frame' => 55,
        ),
        // Frame color => flat add-on fee (USD)
        'colors' => array(
            'Black'     => 0,
            'Silver'    => 0,
            'Gold'      => 10,
            'Rose Gold' => 10,
        ),
    );
}

// Authoritative server-side price calculation
function af_calc_price($base, $size, $frame, $color) {
    $cfg = af_pricing_config();
    $mult = isset($cfg['sizes'][$size]) ? (float)$cfg['sizes'][$size] : 1.0;
    $fee  = (isset($cfg['frames'][$frame]) ? (float)$cfg['frames'][$frame] : 0)
          + (isset($cfg['colors'][$color]) ? (float)$cfg['colors'][$color] : 0);
    return round(((float)$base * $mult) + $fee, 2);
}

// 8a. Render selectors inside the add-to-cart form (simple products)
add_action('woocommerce_before_add_to_cart_button', function() {
    global $product;
    if (!$product || !$product->is_type('simple') || !$product->is_purchasable() || !$product->is_in_stock()) return;
    $cfg  = af_pricing_config();
    $base = (float) wc_get_price_to_display($product);
    $sizes  = array_keys($cfg['sizes']);
    $frames = array_keys($cfg['frames']);
    $colors = array_keys($cfg['colors']);
    ?>
    <div class="af-opts" data-base="<?php echo esc_attr($base); ?>" data-config='<?php echo esc_attr(wp_json_encode($cfg)); ?>' data-symbol="<?php echo esc_attr(get_woocommerce_currency_symbol()); ?>">
      <div class="af-opt-group">
        <label class="af-opt-label">Size</label>
        <div class="af-chips af-size-chips">
          <?php foreach ($sizes as $i => $s): ?>
            <button type="button" class="af-chip-opt<?php echo $i===0?' active':''; ?>" data-type="size" data-val="<?php echo esc_attr($s); ?>"><?php echo esc_html($s); ?></button>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="af-opt-group">
        <label class="af-opt-label">Frame Type</label>
        <div class="af-chips af-frame-chips">
          <?php foreach ($frames as $i => $f): $fee=$cfg['frames'][$f]; ?>
            <button type="button" class="af-chip-opt<?php echo $i===0?' active':''; ?>" data-type="frame" data-val="<?php echo esc_attr($f); ?>"><?php echo esc_html($f); ?><?php if($fee>0) echo ' <em>+'.get_woocommerce_currency_symbol().$fee.'</em>'; ?></button>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="af-opt-group">
        <label class="af-opt-label">Frame Color</label>
        <div class="af-chips af-color-chips">
          <?php $swatch=array('Black'=>'#1a1a1a','Silver'=>'#c0c0c0','Gold'=>'#d4af37','Rose Gold'=>'#b76e79'); foreach ($colors as $i => $c): ?>
            <button type="button" class="af-swatch<?php echo $i===0?' active':''; ?>" data-type="color" data-val="<?php echo esc_attr($c); ?>" title="<?php echo esc_attr($c); ?>"><span style="background:<?php echo esc_attr($swatch[$c]??'#ccc'); ?>"></span><?php echo esc_html($c); ?></button>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="af-price-live">Your Price: <strong id="af-live-price"><?php echo wc_price($base); ?></strong></div>
      <input type="hidden" name="af_size"  value="<?php echo esc_attr($sizes[0]); ?>">
      <input type="hidden" name="af_frame" value="<?php echo esc_attr($frames[0]); ?>">
      <input type="hidden" name="af_color" value="<?php echo esc_attr($colors[0]); ?>">
    </div>
    <?php
}, 8);

// 8b. Capture selections + compute authoritative price on add to cart
add_filter('woocommerce_add_cart_item_data', function($data, $pid) {
    $product = wc_get_product($pid);
    if (!$product || !$product->is_type('simple')) return $data;
    $cfg = af_pricing_config();
    $size  = isset($_POST['af_size'])  ? sanitize_text_field(wp_unslash($_POST['af_size']))  : array_key_first($cfg['sizes']);
    $frame = isset($_POST['af_frame']) ? sanitize_text_field(wp_unslash($_POST['af_frame'])) : array_key_first($cfg['frames']);
    $color = isset($_POST['af_color']) ? sanitize_text_field(wp_unslash($_POST['af_color'])) : array_key_first($cfg['colors']);
    // Validate against config
    if (!isset($cfg['sizes'][$size]))   $size  = array_key_first($cfg['sizes']);
    if (!isset($cfg['frames'][$frame])) $frame = array_key_first($cfg['frames']);
    if (!isset($cfg['colors'][$color])) $color = array_key_first($cfg['colors']);
    $data['af_size']  = $size;
    $data['af_frame'] = $frame;
    $data['af_color'] = $color;
    $data['af_price'] = af_calc_price(wc_get_price_to_display($product), $size, $frame, $color);
    $data['af_unique'] = md5($size.'|'.$frame.'|'.$color.'|'.$pid);
    return $data;
}, 10, 2);

// 8c. Apply the computed price in the cart
add_action('woocommerce_before_calculate_totals', function($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;
    if (empty($cart) || !is_a($cart, 'WC_Cart')) return;
    foreach ($cart->get_cart() as $item) {
        if (isset($item['af_price']) && $item['af_price'] > 0) {
            $item['data']->set_price($item['af_price']);
        }
    }
}, 20);

// 8d. Show selected options in cart/checkout
add_filter('woocommerce_get_item_data', function($data, $item) {
    foreach (array('af_size'=>'Size','af_frame'=>'Frame Type','af_color'=>'Frame Color') as $k=>$label) {
        if (!empty($item[$k])) $data[] = array('name'=>$label, 'value'=>$item[$k]);
    }
    return $data;
}, 10, 2);

// 8e. Persist selected options to the order line items
add_action('woocommerce_checkout_create_order_line_item', function($item, $key, $values) {
    foreach (array('af_size'=>'Size','af_frame'=>'Frame Type','af_color'=>'Frame Color') as $k=>$label) {
        if (!empty($values[$k])) $item->add_meta_data($label, $values[$k]);
    }
}, 10, 3);

// 8f. Selector styling + live price JS
add_action('wp_head', function() {
    if (!function_exists('is_product') || !is_product()) return;
    ?>
    <style>
    .af-opts{margin:14px 0 6px;padding:16px 0 4px;border-top:1px solid #eee;}
    .af-opt-group{margin:0 0 14px;}
    .af-opt-label{display:block;font-size:12px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;color:#444;margin:0 0 8px;}
    .af-chips{display:flex;flex-wrap:wrap;gap:8px;}
    .af-chip-opt{background:#fff;border:1.5px solid #ddd;border-radius:8px;padding:8px 14px;font-size:13px;font-weight:600;color:#333;cursor:pointer;transition:all .15s;}
    .af-chip-opt em{font-style:normal;color:#a8872e;font-weight:700;}
    .af-chip-opt:hover{border-color:#c9a84c;}
    .af-chip-opt.active{border-color:#1a1a1a;background:#1a1a1a;color:#fff;}
    .af-chip-opt.active em{color:#e8c766;}
    .af-swatch{display:inline-flex;align-items:center;gap:7px;background:#fff;border:1.5px solid #ddd;border-radius:8px;padding:6px 12px 6px 8px;font-size:12.5px;font-weight:600;color:#333;cursor:pointer;transition:all .15s;}
    .af-swatch span{width:18px;height:18px;border-radius:50%;border:1px solid rgba(0,0,0,.15);display:inline-block;}
    .af-swatch:hover{border-color:#c9a84c;}
    .af-swatch.active{border-color:#1a1a1a;}
    .af-price-live{margin:6px 0 0;font-size:15px;color:#333;}
    .af-price-live strong{font-size:20px;color:#1a1a1a;}
    .af-price-live .amount{font-weight:800;}
    </style>
    <script>
    (function(){
      function money(sym,val){ return sym + val.toFixed(2); }
      document.addEventListener('click', function(e){
        var b = e.target.closest('.af-chip-opt, .af-swatch'); if(!b) return;
        var wrap = b.closest('.af-opts'); if(!wrap) return;
        var type = b.getAttribute('data-type');
        wrap.querySelectorAll('[data-type="'+type+'"]').forEach(function(x){ x.classList.remove('active'); });
        b.classList.add('active');
        var input = wrap.parentNode.querySelector('input[name="af_'+type+'"]') || document.querySelector('input[name="af_'+type+'"]');
        if(input) input.value = b.getAttribute('data-val');
        recalc(wrap);
      });
      function recalc(wrap){
        var base = parseFloat(wrap.getAttribute('data-base'))||0;
        var cfg = {}; try{ cfg = JSON.parse(wrap.getAttribute('data-config')); }catch(e){ return; }
        var sym = wrap.getAttribute('data-symbol')||'$';
        var size = wrap.querySelector('[data-type="size"].active');
        var frame= wrap.querySelector('[data-type="frame"].active');
        var color= wrap.querySelector('[data-type="color"].active');
        var mult = size ? (cfg.sizes[size.getAttribute('data-val')]||1) : 1;
        var fee  = (frame ? (cfg.frames[frame.getAttribute('data-val')]||0) : 0)
                 + (color ? (cfg.colors[color.getAttribute('data-val')]||0) : 0);
        var price = Math.round((base*mult + fee)*100)/100;
        var el = wrap.querySelector('#af-live-price');
        if(el) el.innerHTML = '<span class="amount">'+money(sym,price)+'</span>';
      }
    })();
    </script>
    <?php
}, 23);

// ─────────────────────────────────────────────────────────────
// PHASE 9 — Styled FAQ accordion (rendered via hook, not in content).
// Fixes duplicate FAQ + missing styling. Shows once on every product.
// ─────────────────────────────────────────────────────────────
function af_product_faqs() {
    return array(
        array('What is canvas wall art made of?', 'Our canvas wall art is made using premium-quality, high-density artist-grade canvas combined with durable wooden or metal frames. The material ensures vibrant color reproduction, long-lasting durability, and a premium gallery-style finish suitable for homes and offices.'),
        array('Is digital canvas printing long-lasting?', 'Yes, digital canvas printing is highly durable when produced with fade-resistant pigment inks and premium canvas materials. Under normal indoor conditions, our canvas prints maintain their color vibrancy and quality for many years.'),
        array('Does canvas wall art fade over time?', 'Our canvas prints are produced using advanced fade-resistant inks that help prevent color fading. When kept away from direct sunlight and moisture, the artwork retains its color richness for years.'),
        array('Is canvas wall art better than paper posters?', 'Yes, canvas wall art offers better durability, texture, and a premium appearance compared to paper posters. Canvas prints provide a realistic artistic look and are more resistant to tearing and fading.'),
        array('Is canvas wall art a good gift option?', 'Yes, canvas wall art is an excellent gift choice for housewarming events, weddings, festivals, birthdays, and special occasions.'),
        array('How can I contact customer support?', 'You can contact our customer support team for any canvas wall art or digital canvas printing inquiries through phone, email, or WhatsApp. Our team assists with product details, customization requests, order updates, and installation guidance.'),
        array('What is your customer support contact number?', 'You can reach our customer support team at +1 (610) 470-7280 for assistance related to product inquiries, order tracking, and customization support.'),
        array('What is your customer support email address?', 'You can contact us via email at theartframer136@gmail.com for product inquiries, bulk orders, or support-related questions.'),
        array('Do you provide WhatsApp support?', 'Yes, we provide WhatsApp support for quick assistance with product selection, order inquiries, and customization requests. You can message us on WhatsApp at +1 (610) 470-7280.'),
        array('Where is your canvas printing business located?', 'Our canvas printing business operates from Delaware, USA, and we provide delivery services across multiple locations.'),
        array('Which areas are eligible for free delivery?', 'We offer free delivery across major cities and serviceable locations in the USA, including Delaware, Pennsylvania, Maryland, New Jersey and nearby areas.'),
    );
}

add_action('woocommerce_after_single_product_summary', function() {
    if (!function_exists('is_product') || !is_product()) return;
    $faqs = af_product_faqs();
    if (empty($faqs)) return;
    echo '<section class="af-pp-sec af-faq"><h2>Frequently Asked Questions</h2><div class="af-faq-list">';
    foreach ($faqs as $i => $f) {
        echo '<details class="af-faq-item"'.($i===0?' open':'').'>';
        echo '<summary class="af-faq-q">'.esc_html($f[0]).'</summary>';
        echo '<div class="af-faq-a">'.esc_html($f[1]).'</div>';
        echo '</details>';
    }
    echo '</div></section>';
}, 14);

// FAQ accordion styling
add_action('wp_head', function() {
    if (!function_exists('is_product') || !is_product()) return;
    ?>
    <style>
    .af-faq-list{border:1px solid #eee;border-radius:12px;overflow:hidden;}
    .af-faq-item{border-bottom:1px solid #eee;background:#fff;}
    .af-faq-item:last-child{border-bottom:none;}
    .af-faq-q{list-style:none;cursor:pointer;padding:16px 46px 16px 18px;font-size:14.5px;font-weight:700;color:#1a1a1a;position:relative;transition:background .15s;user-select:none;}
    .af-faq-q::-webkit-details-marker{display:none;}
    .af-faq-q:hover{background:#faf7ef;}
    .af-faq-q::after{content:'+';position:absolute;right:18px;top:50%;transform:translateY(-50%);font-size:22px;font-weight:400;color:#c9a84c;line-height:1;transition:transform .2s;}
    .af-faq-item[open] .af-faq-q::after{content:'−';}
    .af-faq-item[open] .af-faq-q{background:#faf7ef;color:#a8872e;}
    .af-faq-a{padding:0 18px 18px;font-size:13.5px;line-height:1.7;color:#555;}
    </style>
    <?php
}, 24);

// ─────────────────────────────────────────────────────────────
// PHASE 10 — Shop tag filter (works on Elementor archives) + force
// price-filter widget to USD. Additive; other sections untouched.
// ─────────────────────────────────────────────────────────────

// 10a. Force the WooCommerce price-slider/filter widget to show USD ($)
add_filter('woocommerce_currency', function(){ return 'USD'; }, PHP_INT_MAX);
add_filter('woocommerce_currency_symbol', function($s,$c){ return '$'; }, PHP_INT_MAX, 2);
add_filter('woocommerce_price_slider_params', function($p){
    $p['currency_format_symbol']   = '$';
    $p['currency_format_num_decimals'] = 0;
    return $p;
}, PHP_INT_MAX);

// 10a-2. Force the FOX / WOOCS currency switcher itself to USD (it geo-switches
// per visitor to INR, which is why the price filter showed rupees). This makes
// its current currency USD so converted prices + the price filter stay in $.
add_filter('woocs_is_geoip_activated', '__return_false', PHP_INT_MAX);
add_filter('woocs_geoip_country', function(){ return 'US'; }, PHP_INT_MAX);
add_filter('woocs_current_currency', function(){ return 'USD'; }, PHP_INT_MAX);
add_filter('woocs_convert_price', function($price){ return $price; }, 1); // no conversion
add_action('init', function(){
    // WOOCS reads this cookie first — pin it to USD before the plugin runs
    if (!isset($_COOKIE['woocs_current_currency']) || $_COOKIE['woocs_current_currency'] !== 'USD') {
        @setcookie('woocs_current_currency', 'USD', time()+YEAR_IN_SECONDS, defined('COOKIEPATH')?COOKIEPATH:'/', defined('COOKIE_DOMAIN')?COOKIE_DOMAIN:'');
        $_COOKIE['woocs_current_currency'] = 'USD';
    }
    if (session_status() === PHP_SESSION_ACTIVE) { $_SESSION['woocs_current_currency'] = 'USD'; }
    $def = get_option('woocs_default_currency', '');
    if ($def !== 'USD') update_option('woocs_default_currency', 'USD');
}, 0);
add_action('wp_loaded', function(){
    if (isset($GLOBALS['WOOCS']) && is_object($GLOBALS['WOOCS'])) {
        $w = $GLOBALS['WOOCS'];
        if (method_exists($w, 'set_currency')) { $w->set_currency('USD'); }
        if (property_exists($w, 'current_currency')) { $w->current_currency = 'USD'; }
        if (property_exists($w, 'default_currency')) { $w->default_currency = 'USD'; }
    }
}, 1);

// 10b. Guarantee ?product_tag= filters the main product query (so it works
//      even on Elementor-built archives that use the main query)
add_action('woocommerce_product_query', function($q){
    if (is_admin()) return;
    if (!empty($_GET['product_tag'])) {
        $slug = sanitize_title(wp_unslash($_GET['product_tag']));
        $tax = (array) $q->get('tax_query');
        $tax[] = array('taxonomy'=>'product_tag','field'=>'slug','terms'=>array($slug));
        $q->set('tax_query', $tax);
    }
});

// 10c. Inject a clickable Tag filter bar above the product grid on shop/category
add_action('wp_footer', function() {
    if (!function_exists('is_shop')) return;
    if (!(is_shop() || is_product_category() || is_product_tag())) return;

    $tags = get_terms(array('taxonomy'=>'product_tag','hide_empty'=>true,'orderby'=>'count','order'=>'DESC','number'=>30));
    if (is_wp_error($tags) || !$tags) return;

    $active = isset($_GET['product_tag']) ? sanitize_title($_GET['product_tag']) : '';
    $keep = $_GET; unset($keep['paged']);
    $items = array();
    foreach ($tags as $t) {
        $q = $keep;
        if ($active === $t->slug) { unset($q['product_tag']); $on = true; }
        else { $q['product_tag'] = $t->slug; $on = false; }
        $items[] = array('name'=>$t->name, 'url'=>'?'.http_build_query($q), 'on'=>$on);
    }
    ?>
    <script>
    (function(){
      var tags = <?php echo wp_json_encode($items); ?>;
      if(!tags.length) return;
      // Find the "Categories" widget in the sidebar so we can place Tags below it
      function findCategoriesWidget(){
        var heads = document.querySelectorAll('h1,h2,h3,h4,h5,h6,.elementor-heading-title,.widget-title,.wp-block-heading,.widgettitle');
        for(var i=0;i<heads.length;i++){
          if(heads[i].textContent.trim().toLowerCase()==='categories'){
            return heads[i].closest('.elementor-widget, aside .widget, .widget, section, .elementor-element') || heads[i].parentElement;
          }
        }
        return null;
      }
      function build(){
        if(document.querySelector('.af-tagbar')) return true;

        var bar = document.createElement('div'); bar.className='af-tagbar af-tagbar-side';
        var lbl = document.createElement('div'); lbl.className='af-tagbar-label'; lbl.textContent='Filter by Tag';
        bar.appendChild(lbl);
        var wrap = document.createElement('div'); wrap.className='af-tagbar-chips';
        tags.forEach(function(t){
          var a=document.createElement('a');
          a.className='af-tag-btn2'+(t.on?' active':'');
          a.href=t.url; a.textContent='#'+t.name;
          wrap.appendChild(a);
        });
        bar.appendChild(wrap);

        // Preferred: place in the sidebar right after the Categories widget
        var catWidget = findCategoriesWidget();
        if(catWidget && catWidget.parentNode){
          catWidget.parentNode.insertBefore(bar, catWidget.nextSibling);
          return true;
        }
        // Fallback: above the product grid
        var anchor = document.querySelector('ul.products') || document.querySelector('.products') || document.querySelector('.woocommerce-result-count');
        if(anchor && anchor.parentNode){ bar.classList.remove('af-tagbar-side'); anchor.parentNode.insertBefore(bar, anchor); return true; }
        return false;
      }
      if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',build); else build();
      window.addEventListener('load', function(){ build(); setTimeout(build,700); setTimeout(build,1600); });
    })();
    </script>
    <style>
    /* Above-grid fallback layout */
    .af-tagbar{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin:0 0 20px;padding:0 0 6px;}
    /* Sidebar layout (preferred) */
    .af-tagbar-side{display:block;margin:22px 0 10px;padding:0;}
    .af-tagbar .af-tagbar-label{font-size:13px;font-weight:800;color:#555;margin-right:2px;}
    .af-tagbar-side .af-tagbar-label{display:block;font-size:17px;font-weight:800;color:#1a1a1a;margin:0 0 14px;}
    .af-tagbar-side .af-tagbar-chips{display:flex;flex-wrap:wrap;gap:7px;}
    .af-tag-btn2{display:inline-block;padding:6px 12px;border-radius:16px;background:#fff;border:1.5px solid #e2ddcf;color:#6b6250;font-size:12px;font-weight:600;text-decoration:none;transition:all .15s;cursor:pointer;}
    .af-tag-btn2:hover{border-color:#c9a84c;color:#a8872e;}
    .af-tag-btn2.active{background:#c9a84c;border-color:#c9a84c;color:#fff;}
    </style>
    <?php
}, 30);

// ─────────────────────────────────────────────────────────────
// PHASE 11 — Sidebar Color filter (clickable swatches), injected into
// the sidebar like the tag filter. Uses pa_colors (?filter_colors).
// Additive; other sections untouched.
// ─────────────────────────────────────────────────────────────

// 11a. Guarantee ?filter_colors= narrows the main product query (Elementor-safe)
add_action('woocommerce_product_query', function($q){
    if (is_admin()) return;
    if (!empty($_GET['filter_colors'])) {
        $slugs = array_filter(array_map('sanitize_title', explode(',', wp_unslash($_GET['filter_colors']))));
        if ($slugs) {
            $tax = (array) $q->get('tax_query');
            $tax[] = array('taxonomy'=>'pa_colors','field'=>'slug','terms'=>$slugs,'operator'=>'IN');
            $q->set('tax_query', $tax);
        }
    }
});

// 11b. Inject the Color filter into the sidebar
add_action('wp_footer', function() {
    if (!function_exists('is_shop')) return;
    if (!(is_shop() || is_product_category() || is_product_tag())) return;

    $colors = get_terms(array('taxonomy'=>'pa_colors','hide_empty'=>true,'number'=>12));
    if (is_wp_error($colors) || !$colors) return;

    $swatch = array('black'=>'#1a1a1a','silver'=>'#c0c0c0','gold'=>'#d4af37','rose-gold'=>'#b76e79');
    $active = isset($_GET['filter_colors']) ? sanitize_title($_GET['filter_colors']) : '';
    $keep = $_GET; unset($keep['paged']);
    $items = array();
    foreach ($colors as $t) {
        $q = $keep;
        if ($active === $t->slug) { unset($q['filter_colors']); $on = true; }
        else { $q['filter_colors'] = $t->slug; $q['query_type_colors']='or'; $on = false; }
        $items[] = array(
            'name'=>$t->name, 'slug'=>$t->slug, 'on'=>$on,
            'hex'=>($swatch[$t->slug] ?? '#cccccc'),
            'url'=>'?'.http_build_query($q),
        );
    }
    ?>
    <script>
    (function(){
      var colors = <?php echo wp_json_encode($items); ?>;
      if(!colors.length) return;
      function findWidget(label){
        var heads = document.querySelectorAll('h1,h2,h3,h4,h5,h6,.elementor-heading-title,.widget-title,.wp-block-heading,.widgettitle');
        for(var i=0;i<heads.length;i++){
          if(heads[i].textContent.trim().toLowerCase()===label){
            return heads[i].closest('.elementor-widget, aside .widget, .widget, section, .elementor-element') || heads[i].parentElement;
          }
        }
        return null;
      }
      function build(){
        if(document.querySelector('.af-colorbar')) return true;
        var box = document.createElement('div'); box.className='af-colorbar';
        var lbl = document.createElement('div'); lbl.className='af-colorbar-label'; lbl.textContent='Frame Color';
        box.appendChild(lbl);
        var wrap = document.createElement('div'); wrap.className='af-colorbar-swatches';
        colors.forEach(function(c){
          var a=document.createElement('a');
          a.className='af-swatch2'+(c.on?' active':'');
          a.href=c.url; a.title=c.name;
          var dot=document.createElement('span'); dot.className='af-swatch2-dot'; dot.style.background=c.hex;
          var nm=document.createElement('span'); nm.className='af-swatch2-name'; nm.textContent=c.name;
          a.appendChild(dot); a.appendChild(nm); wrap.appendChild(a);
        });
        box.appendChild(wrap);
        // place after Frame widget, else after Size, else after Categories, else above grid
        var anchor = findWidget('frame') || findWidget('size') || findWidget('categories');
        if(anchor && anchor.parentNode){ anchor.parentNode.insertBefore(box, anchor.nextSibling); return true; }
        var grid = document.querySelector('ul.products') || document.querySelector('.woocommerce-result-count');
        if(grid && grid.parentNode){ box.classList.add('af-colorbar-top'); grid.parentNode.insertBefore(box, grid); return true; }
        return false;
      }
      if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',build); else build();
      window.addEventListener('load', function(){ build(); setTimeout(build,700); setTimeout(build,1600); });
    })();
    </script>
    <style>
    .af-colorbar{margin:22px 0 10px;}
    .af-colorbar-label{font-size:17px;font-weight:800;color:#1a1a1a;margin:0 0 14px;}
    .af-colorbar-swatches{display:flex;flex-direction:column;gap:9px;}
    .af-swatch2{display:inline-flex;align-items:center;gap:9px;text-decoration:none;color:#555;font-size:13px;font-weight:600;padding:4px 6px;border-radius:8px;border:1.5px solid transparent;transition:all .15s;}
    .af-swatch2:hover{color:#a8872e;background:#faf7ef;}
    .af-swatch2.active{border-color:#c9a84c;background:#faf7ef;color:#1a1a1a;}
    .af-swatch2-dot{width:20px;height:20px;border-radius:50%;border:1px solid rgba(0,0,0,.2);flex-shrink:0;}
    .af-colorbar-top .af-colorbar-swatches{flex-direction:row;flex-wrap:wrap;gap:10px;}
    </style>
    <?php
}, 31);

// ─────────────────────────────────────────────────────────────
// PHASE 12 — Fix "Try It On Your Wall" nav link to point to the page.
// ─────────────────────────────────────────────────────────────
add_filter('wp_nav_menu_objects', function($items){
    // Resolve the Try-On-Wall page URL (fall back to a sensible slug)
    $page = get_page_by_path('try-on-wall');
    if (!$page) { $page = get_page_by_path('try-it-on-your-wall'); }
    $url = $page ? get_permalink($page) : home_url('/try-on-wall/');
    foreach ($items as $it) {
        $t = strtolower(trim(wp_strip_all_tags($it->title)));
        if (strpos($t,'try') !== false && strpos($t,'wall') !== false) {
            $it->url = $url;
            // Clear any "#" or empty target that blocks navigation
            $it->target = '';
        }
    }
    return $items;
}, 20);

// ─────────────────────────────────────────────────────────────
// PHASE 13 — Fully dynamic standalone "Try It On Your Wall" page.
// Replaces the static Elementor mockup at /try-on-wall/ with a working
// tool: Category→Product pickers, frame type/size/color, wall-photo
// upload, live framed preview, drag + scale, live price, buy link.
// ─────────────────────────────────────────────────────────────
add_action('template_redirect', function(){
    if (!function_exists('is_page') || !is_page(array('try-on-wall','try-it-on-your-wall'))) return;
    if (!function_exists('wc_get_products')) return;

    // Build catalog data
    $cats = get_terms(array('taxonomy'=>'product_cat','hide_empty'=>true,'orderby'=>'name'));
    $cat_items = array();
    if (!is_wp_error($cats)) foreach ($cats as $c) $cat_items[] = array('slug'=>$c->slug,'name'=>$c->name);

    $ids = wc_get_products(array('status'=>'publish','limit'=>-1,'return'=>'ids'));
    $prod_items = array();
    foreach ($ids as $pid) {
        $p = wc_get_product($pid);
        if (!$p || !$p->get_image_id()) continue;
        $prod_items[] = array(
            'id'=>$pid,
            'name'=>$p->get_name(),
            'cats'=>wp_get_post_terms($pid,'product_cat',array('fields'=>'slugs')),
            'img'=>wp_get_attachment_image_url($p->get_image_id(),'large'),
            'price'=>(float) wc_get_price_to_display($p),
            'url'=>get_permalink($pid),
        );
    }
    $cfg = function_exists('af_pricing_config') ? af_pricing_config() : array(
        'sizes'=>array('24×36 in'=>1.0,'30×45 in'=>1.4,'36×54 in'=>1.9,'48×72 in'=>2.7),
        'frames'=>array('Without Frame'=>0,'Fibre Frame'=>25,'Floating Frame'=>40,'Aluminium Frame'=>55),
        'colors'=>array('Black'=>0,'Silver'=>0,'Gold'=>10,'Rose Gold'=>10),
    );
    $sym = get_woocommerce_currency_symbol();

    get_header();
    ?>
    <div class="af-tow-wrap">
      <div class="af-tow-card">
        <a class="af-tow-home" href="<?php echo esc_url(home_url('/')); ?>">← Back to Home</a>
        <h1 class="af-tow-title">Try It on Your Wall</h1>
        <p class="af-tow-sub">Pick an artwork, choose your frame, upload a photo of your wall, and preview it to scale.</p>

        <div class="af-tow-grid">
          <div class="af-tow-panel">
            <label>Category</label>
            <select id="tow-cat"><option value="">All Categories</option></select>

            <label>Choose Product</label>
            <select id="tow-prod"><option value="">Select a product</option></select>

            <label>Frame Type</label>
            <select id="tow-frame"></select>

            <label>Frame Size</label>
            <select id="tow-size"></select>

            <label>Frame Color</label>
            <select id="tow-color"></select>

            <label>Upload Your Wall Photo</label>
            <label class="af-tow-upload"><input type="file" id="tow-wall" accept="image/*" hidden>⬆ Upload wall photo</label>

            <label>Adjust Size <span id="tow-scaleval">100%</span></label>
            <input type="range" id="tow-scale" min="40" max="160" value="100">

            <div class="af-tow-price">Price: <strong id="tow-price">—</strong></div>
            <div class="af-tow-actions">
              <button type="button" id="tow-save" class="af-tow-btn ghost">Save Preview</button>
              <a href="#" id="tow-view" class="af-tow-btn solid">View Product</a>
            </div>
          </div>

          <div class="af-tow-stagewrap">
            <div id="tow-stage" class="af-tow-stage">
              <img id="tow-wallimg" class="af-tow-wallimg" alt="">
              <div id="tow-placeholder" class="af-tow-ph">📷 Upload a wall photo and choose a product to preview it here. Drag the artwork to position it.</div>
              <div id="tow-framebox" class="af-tow-framebox" style="display:none"><img id="tow-art" class="af-tow-art" alt="" crossorigin="anonymous"></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <script>
    (function(){
      var CATS = <?php echo wp_json_encode($cat_items); ?>;
      var PRODUCTS = <?php echo wp_json_encode($prod_items); ?>;
      var CFG = <?php echo wp_json_encode($cfg); ?>;
      var SYM = <?php echo wp_json_encode($sym); ?>;
      var $=function(id){return document.getElementById(id);};

      // Populate selects
      CATS.forEach(function(c){ var o=document.createElement('option'); o.value=c.slug; o.textContent=c.name; $('tow-cat').appendChild(o); });
      Object.keys(CFG.frames).forEach(function(f){ var o=document.createElement('option'); o.value=f; o.textContent=f+(CFG.frames[f]>0?(' (+'+SYM+CFG.frames[f]+')'):''); $('tow-frame').appendChild(o); });
      Object.keys(CFG.sizes).forEach(function(s){ var o=document.createElement('option'); o.value=s; o.textContent=s; $('tow-size').appendChild(o); });
      Object.keys(CFG.colors).forEach(function(c){ var o=document.createElement('option'); o.value=c; o.textContent=c+(CFG.colors[c]>0?(' (+'+SYM+CFG.colors[c]+')'):''); $('tow-color').appendChild(o); });

      function fillProducts(cat){
        var sel=$('tow-prod'); sel.innerHTML='<option value="">Select a product</option>';
        PRODUCTS.filter(function(p){ return !cat || (p.cats||[]).indexOf(cat)>-1; })
                .forEach(function(p){ var o=document.createElement('option'); o.value=p.id; o.textContent=p.name; sel.appendChild(o); });
      }
      fillProducts('');
      $('tow-cat').addEventListener('change', function(){ fillProducts(this.value); });

      function current(){ return PRODUCTS.filter(function(p){ return String(p.id)===String($('tow-prod').value); })[0]; }

      function applyFrame(){
        var box=$('tow-framebox'), art=$('tow-art');
        var frame=$('tow-frame').value, color=$('tow-color').value;
        var hex={'Black':'#1a1a1a','Silver':'#c0c0c0','Gold':'#d4af37','Rose Gold':'#b76e79'}[color]||'#1a1a1a';
        var w = frame==='Without Frame'?0 : frame==='Floating Frame'?7 : 12;
        box.style.border = w? (w+'px solid '+hex) : 'none';
        box.style.background = frame==='Floating Frame' ? '#111' : hex;
        box.style.padding = frame==='Floating Frame' ? '8px' : '0';
        box.style.boxShadow = '0 12px 34px rgba(0,0,0,.4)';
      }

      function calcPrice(){
        var p=current(); if(!p) return null;
        var mult=CFG.sizes[$('tow-size').value]||1;
        var fee=(CFG.frames[$('tow-frame').value]||0)+(CFG.colors[$('tow-color').value]||0);
        return Math.round((p.price*mult+fee)*100)/100;
      }
      function refresh(){
        var p=current();
        if(p){
          $('tow-art').src=p.img;
          $('tow-framebox').style.display='inline-block';
          $('tow-placeholder').style.display='none';
          $('tow-view').href=p.url;
          applyFrame(); applyScale();
          var pr=calcPrice(); $('tow-price').textContent = pr!=null ? (SYM+pr.toFixed(2)) : '—';
        }
      }
      ['tow-prod','tow-frame','tow-size','tow-color'].forEach(function(id){ $(id).addEventListener('change', refresh); });

      function applyScale(){
        var stage=$('tow-stage'), box=$('tow-framebox');
        var sizeMult=CFG.sizes[$('tow-size').value]||1;
        var base=(stage.getBoundingClientRect().width||500)*0.32;
        var slider=parseFloat($('tow-scale').value)/100;
        box.style.width=Math.max(60, base*sizeMult*slider)+'px';
        $('tow-art').style.width='100%'; $('tow-art').style.height='auto'; $('tow-art').style.display='block';
      }
      $('tow-scale').addEventListener('input', function(){ $('tow-scaleval').textContent=this.value+'%'; applyScale(); });

      // Wall upload
      $('tow-wall').addEventListener('change', function(){
        var f=this.files&&this.files[0]; if(!f) return;
        var r=new FileReader();
        r.onload=function(e){ var im=$('tow-wallimg'); im.src=e.target.result; im.style.display='block'; $('tow-placeholder').style.display='none'; };
        r.readAsDataURL(f);
      });

      // Drag artwork
      var box=$('tow-framebox'), drag=false,sx,sy,ox,oy;
      box.addEventListener('pointerdown', function(e){ drag=true; box.classList.add('dragging'); var st=getComputedStyle(box); sx=e.clientX; sy=e.clientY; ox=parseFloat(st.left)||box.offsetLeft; oy=parseFloat(st.top)||box.offsetTop; box.style.left=ox+'px'; box.style.top=oy+'px'; box.style.transform='none'; e.preventDefault(); });
      document.addEventListener('pointermove', function(e){ if(!drag) return; box.style.left=(ox+(e.clientX-sx))+'px'; box.style.top=(oy+(e.clientY-sy))+'px'; });
      document.addEventListener('pointerup', function(){ drag=false; box.classList.remove('dragging'); });

      // Save preview
      $('tow-save').addEventListener('click', function(){
        var wall=$('tow-wallimg');
        if(!wall.src || wall.style.display==='none'){ alert('Please upload a wall photo first.'); return; }
        try{
          var stage=$('tow-stage'), r=stage.getBoundingClientRect();
          var cv=document.createElement('canvas'); cv.width=r.width; cv.height=r.height; var ctx=cv.getContext('2d');
          var iw=wall.naturalWidth,ih=wall.naturalHeight,sc=Math.max(r.width/iw,r.height/ih);
          ctx.drawImage(wall,(r.width-iw*sc)/2,(r.height-ih*sc)/2,iw*sc,ih*sc);
          var ar=$('tow-framebox').getBoundingClientRect();
          ctx.drawImage($('tow-art'), ar.left-r.left, ar.top-r.top, ar.width, ar.height);
          var a=document.createElement('a'); a.download='my-wall-preview.png'; a.href=cv.toDataURL('image/png'); a.click();
        }catch(err){ alert('Could not save (image may be cross-origin). Please screenshot instead.'); }
      });

      window.addEventListener('resize', applyScale);

      // Pre-select a product passed via ?product=<id> (from product page / cards)
      (function preselect(){
        var m = location.search.match(/[?&]product=(\d+)/);
        if(!m) return;
        var pid = m[1];
        var prod = PRODUCTS.filter(function(p){ return String(p.id)===String(pid); })[0];
        if(!prod) return;
        // Set category (first cat) then repopulate products, then select the product
        var cat = (prod.cats||[])[0] || '';
        if(cat){ $('tow-cat').value = cat; fillProducts(cat); }
        $('tow-prod').value = pid;
        // defaults for a nice first view
        if($('tow-frame').options.length>1) $('tow-frame').selectedIndex = 2; // Floating
        refresh();
        var el=document.querySelector('.af-tow-wrap'); if(el) el.scrollIntoView({behavior:'smooth',block:'start'});
      })();
    })();
    </script>

    <style>
    .af-tow-wrap{background:#f3efe6;padding:40px 16px 60px;}
    .af-tow-card{max-width:1200px;margin:0 auto;background:#faf7ef;border-radius:20px;padding:34px 30px;position:relative;box-shadow:0 10px 40px rgba(0,0,0,.06);}
    .af-tow-home{position:absolute;top:26px;right:26px;background:#1a1a1a;color:#fff;text-decoration:none;font-weight:700;font-size:13px;padding:11px 18px;border-radius:9px;}
    .af-tow-home:hover{background:#c9a84c;}
    .af-tow-title{text-align:center;font-size:44px;font-weight:800;color:#1a1a1a;margin:6px 0 8px;}
    .af-tow-sub{text-align:center;color:#6b6250;font-size:15px;margin:0 0 30px;}
    .af-tow-grid{display:grid;grid-template-columns:340px 1fr;gap:26px;align-items:start;}
    .af-tow-panel{background:#fff;border:1px solid #ece4cf;border-radius:14px;padding:20px;display:flex;flex-direction:column;gap:6px;}
    .af-tow-panel label{font-size:12.5px;font-weight:800;color:#444;text-transform:uppercase;letter-spacing:.03em;margin-top:10px;}
    .af-tow-panel select{width:100%;padding:11px;border:1px solid #ddd;border-radius:8px;font-size:14px;background:#fff;}
    .af-tow-upload{display:flex !important;align-items:center;justify-content:center;gap:8px;padding:12px;border:2px dashed #c9a84c;border-radius:9px;color:#a8872e;font-weight:700 !important;font-size:13px !important;cursor:pointer;text-transform:none !important;letter-spacing:0 !important;background:#fdfaf2;margin-top:6px;}
    .af-tow-panel input[type=range]{width:100%;accent-color:#c9a84c;margin-top:4px;}
    .af-tow-price{margin-top:14px;font-size:15px;color:#333;}
    .af-tow-price strong{font-size:22px;color:#1a1a1a;}
    .af-tow-actions{display:flex;gap:10px;margin-top:14px;}
    .af-tow-btn{flex:1;text-align:center;padding:12px;border-radius:9px;font-weight:800;font-size:13px;cursor:pointer;text-decoration:none;border:none;}
    .af-tow-btn.ghost{background:#efe9db;color:#333;}
    .af-tow-btn.solid{background:#c9a84c;color:#fff;}
    .af-tow-btn.solid:hover{background:#a8872e;}
    .af-tow-stagewrap{position:relative;}
    .af-tow-stage{position:relative;width:100%;height:560px;border-radius:14px;overflow:hidden;background:repeating-conic-gradient(#e9e9e9 0% 25%,#f4f4f4 0% 50%) 50%/26px 26px;display:flex;align-items:center;justify-content:center;}
    .af-tow-wallimg{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:none;}
    .af-tow-ph{color:#999;font-size:14px;text-align:center;max-width:340px;line-height:1.6;padding:20px;}
    .af-tow-framebox{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);cursor:grab;touch-action:none;z-index:5;}
    .af-tow-framebox.dragging{cursor:grabbing;}
    .af-tow-art{display:block;width:100%;height:auto;}
    @media(max-width:860px){ .af-tow-grid{grid-template-columns:1fr;} .af-tow-stage{height:420px;} .af-tow-title{font-size:32px;} .af-tow-home{position:static;display:inline-block;margin-bottom:14px;} }
    </style>
    <?php
    get_footer();
    exit;
}, 1);
