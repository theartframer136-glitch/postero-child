<?php
/**
 * Postero Child Theme - functions.php
 * The Art Framer - theartframer.us
 */

// 1. Enqueue parent and child theme styles
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('postero-parent', get_template_directory_uri() . '/style.css');
    wp_enqueue_style('postero-child', get_stylesheet_uri(), array('postero-parent'), '1.0.0');
    wp_enqueue_style('postero-child-custom', get_stylesheet_directory_uri() . '/assets/css/custom.css', array('postero-child'), '3.3.4');
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
/**
 * Storefront currencies (spec: USD + CAD). The site once geo-switched visitors
 * to INR, so anything outside this whitelist is still forced back to USD.
 */
function af_allowed_currencies() { return array('USD', 'CAD'); }

function af_currency_symbol_for($code) { return $code === 'CAD' ? 'CA$' : '$'; }

function af_active_currency() {
    static $cur = null;
    if ($cur !== null) return $cur;
    $allowed = af_allowed_currencies();
    $c = '';
    if (isset($_GET['currency'])) {
        $c = strtoupper(sanitize_text_field(wp_unslash($_GET['currency'])));
    } elseif (isset($_COOKIE['woocs_current_currency'])) {
        $c = strtoupper(sanitize_text_field(wp_unslash($_COOKIE['woocs_current_currency'])));
    } elseif (isset($_COOKIE['woocs_session_currency'])) {
        $c = strtoupper(sanitize_text_field(wp_unslash($_COOKIE['woocs_session_currency'])));
    }
    $cur = in_array($c, $allowed, true) ? $c : 'USD';
    return $cur;
}

add_filter('woocommerce_currency', function() { return af_active_currency(); }, 9999);
add_filter('woocommerce_currency_symbol', function($symbol, $currency) { return af_currency_symbol_for($currency); }, 9999, 2);

// Redirect any ?currency= outside the whitelist (e.g. INR) back to USD
add_action('template_redirect', function() {
    if (isset($_GET['currency']) && !in_array(strtoupper($_GET['currency']), af_allowed_currencies(), true)) {
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

    // Pin all currency cookies to the visitor's chosen whitelist currency
    // (USD default, CAD allowed). Anything else — e.g. geo-set INR — is reset.
    $active = af_active_currency();
    $exp  = time() + 86400 * 365;
    $path = COOKIEPATH ?: '/';
    $host = COOKIE_DOMAIN ?: '';
    foreach (['woocs_session_currency','wmc_current_currency','wmc-currency','currency','chosen_currency'] as $name) {
        if (!isset($_COOKIE[$name]) || $_COOKIE[$name] !== $active) {
            setcookie($name, $active, $exp, $path, $host);
            $_COOKIE[$name] = $active;
        }
    }
    // WPML / WCML
    if (defined('WCML_VERSION')) {
        add_filter('wcml_client_currency', function() { return af_active_currency(); }, 9999);
    }
}, 1);

// Override any non-whitelisted currency stored in WC session
add_action('woocommerce_init', function() {
    if (!WC()->session) return;
    $sess_cur = WC()->session->get('currency');
    $active   = af_active_currency();
    if ($sess_cur && $sess_cur !== $active) {
        WC()->session->set('currency', $active);
    }
}, 9999);

// Symbol before number, no space; currency comes from the whitelist helper
add_filter('woocommerce_price_format', function() { return '%1$s%2$s'; }, 9999);
add_filter('woocommerce_currency_pos', function() { return 'left'; }, 9999);
add_filter('woocommerce_price_args', function($args) {
    $args['currency'] = af_active_currency();
    $args['currency_pos'] = 'left';
    return $args;
}, 9999);

// WMC (Woo Multi Currency) — keep pinned to the active whitelist currency
add_filter('wmc_get_price', function($price, $currency) { return $price; }, 9999, 2);
add_filter('wmc_current_currency', function() { return af_active_currency(); }, 9999);
add_filter('wmc_frontend_display_currency', function() { return af_active_currency(); }, 9999);

// Immediately fix navbar currency display before page renders
add_action('wp_head', function() { ?>
<script>
(function(){
  // Set cookies immediately — before any plugin JS reads them.
  // "cur" is the server-validated choice: USD default, CAD allowed.
  var cur = '<?php echo esc_js(af_active_currency()); ?>';
  var opts = '; path=/; max-age=' + (86400 * 365);
  document.cookie = 'woocs_session_currency=' + cur + opts;
  document.cookie = 'woocs_current_currency=' + cur + opts;
  document.cookie = 'wmc_current_currency=' + cur + opts;
  document.cookie = 'wmc-currency=' + cur + opts;
  document.cookie = 'currency=' + cur + opts;
  document.cookie = 'chosen_currency=' + cur + opts;

  // Select the active currency in dropdowns without removing other options
  function fixNavCurrency() {
    // Fix <select> dropdowns — just change selected value, keep all options intact
    document.querySelectorAll('select[name*="currency"], select[id*="currency"], select[class*="currency"]').forEach(function(sel) {
      var opt = Array.from(sel.options).find(function(o) { return o.value === cur || o.text.indexOf(cur) !== -1; });
      if (opt) { sel.value = opt.value; opt.selected = true; }
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

// NOTE: previous top-level delete_transient('af_yt_*') calls ran on EVERY
// request (not just deploys), nuking the feed cache and getting the server
// rate-limited by YouTube. Removed — the transient's own TTL handles freshness.

// 4. AJAX proxy: fetch YouTube playlist/channel RSS — no API key needed
add_action('wp_ajax_af_yt_feed',        'af_yt_feed_handler');
add_action('wp_ajax_nopriv_af_yt_feed', 'af_yt_feed_handler');
function af_yt_feed_handler() {
    // Rate limit: 30 requests/hour per IP — this endpoint is nopriv and each
    // distinct id writes a transient, so cap it to prevent DB/cache bloat.
    $af_yt_ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '';
    $af_yt_rl = 'af_yt_rl_' . md5($af_yt_ip);
    $af_yt_n  = (int) get_transient($af_yt_rl);
    if ($af_yt_n >= 30) { wp_send_json_error('rate limited'); return; }
    set_transient($af_yt_rl, $af_yt_n + 1, HOUR_IN_SECONDS);

    $list_id    = sanitize_text_field($_GET['list']    ?? '');
    $channel_id = sanitize_text_field($_GET['channel'] ?? '');

    // Whitelist the YouTube id charset. Blocks query-string injection into the
    // upstream feed URL and stops attacker-chosen transient cache keys.
    if ($list_id !== ''    && !preg_match('/^[A-Za-z0-9_-]{10,64}$/', $list_id))    { wp_send_json_error('bad id'); return; }
    if ($channel_id !== '' && !preg_match('/^[A-Za-z0-9_-]{10,64}$/', $channel_id)) { wp_send_json_error('bad id'); return; }

    if ($list_id) {
        $url = 'https://www.youtube.com/feeds/videos.xml?playlist_id=' . $list_id;
    } elseif ($channel_id) {
        $url = 'https://www.youtube.com/feeds/videos.xml?channel_id=' . $channel_id;
    } else {
        wp_send_json_error('no id'); return;
    }

    $cached = get_transient('af_yt_' . ($list_id ?: $channel_id));
    if ($cached) { wp_send_json_success($cached); return; }

    $af_yt_key = $list_id ?: $channel_id;
    $resp = wp_remote_get($url, ['timeout' => 10]);
    $af_yt_body = is_wp_error($resp) ? '' : wp_remote_retrieve_body($resp);
    $xml = $af_yt_body ? simplexml_load_string($af_yt_body) : false;
    if (!$xml) {
        // YouTube failed/blocked us — serve the last-known-good list so the
        // section never collapses to a single video.
        $fallback = get_option('af_yt_lastgood_' . $af_yt_key);
        if (is_array($fallback) && $fallback) { wp_send_json_success($fallback); return; }
        wp_send_json_error(is_wp_error($resp) ? $resp->get_error_message() : 'bad xml'); return;
    }

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

    if ($videos) {
        set_transient('af_yt_' . $af_yt_key, $videos, 2 * HOUR_IN_SECONDS);
        update_option('af_yt_lastgood_' . $af_yt_key, $videos, false); // durable fallback
    } else {
        $fallback = get_option('af_yt_lastgood_' . $af_yt_key);
        if (is_array($fallback) && $fallback) { wp_send_json_success($fallback); return; }
    }
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

// 8b. Trim the sidebar "Categories" widget — hide empty categories so the list
//     isn't the entire 90-item site tree (functional cleanup on archives).
add_filter('woocommerce_product_categories_widget_args', function($args){
    $args['hide_empty'] = 1;
    return $args;
});

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
        // Guard: only enhance REAL product sliders (cards with a price / add-to-cart).
        // Skips non-product sliders like the "Products In Motion" video section.
        var isProductSlider = freshCards.some(function(c){
            return c.querySelector('.price, .price-section, .add-cart, .add_to_cart_button, [class*="price"]');
        });
        if (!isProductSlider) { grid.classList.remove('af-grid-hidden'); return; }

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
        // RE-ENABLED (homepage only): the vanishing-products bug was traced to
        // the category-page card styler, not this slider. Guards below keep the
        // slider strictly off archive/category/shop pages, and the
        // ensureProductsVisible() safety net un-hides the grid if the shell
        // ever fails to render cards.
        var b = document.body;
        var isHome = b && (b.classList.contains('af-front-page') || b.classList.contains('home') || b.classList.contains('front-page'));
        var isArchive = b && (b.classList.contains('archive') || b.classList.contains('tax-product_cat') || b.classList.contains('post-type-archive-product') || b.classList.contains('woocommerce-shop') || b.classList.contains('search'));
        if (!isHome || isArchive) return;

        // Find the REAL product slider (a container whose cards have prices),
        // so we never grab the "Products In Motion" video section.
        var containers = document.querySelectorAll('.product-container');
        var container = null, grid = null;
        for (var i = 0; i < containers.length; i++) {
            var g = containers[i].querySelector('#productGrid') || containers[i].querySelector('.product-slider');
            if (!g) continue;
            var cards = Array.from(g.querySelectorAll('.product-card'));
            if (!cards.length) continue;
            var hasPrice = cards.some(function(c){
                return c.querySelector('.price, .price-section, .add-cart, .add_to_cart_button, [class*="price"]');
            });
            if (hasPrice) { container = containers[i]; grid = g; break; }
        }
        if (!container || !grid) return;

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
    // The theme fills #productGrid via AJAX; since the collection now loads ALL
    // products the response can land well after the retries above — keep
    // retrying until the cards exist (up to ~30s), then stop.
    (function(){
        var tries = 0;
        var poll = setInterval(function(){
            if (_sliderInitDone || ++tries > 60) { clearInterval(poll); return; }
            init();
        }, 500);
    })();

    // Safety net: never leave a product grid hidden unless a slider shell is
    // actually showing its cards. Guarantees products stay visible everywhere.
    function ensureProductsVisible(){
        document.querySelectorAll('.af-grid-hidden').forEach(function(grid){
            if (!grid.querySelector('.product-card')) return;
            var shell = grid.parentNode ? grid.parentNode.querySelector('.af-shell') : null;
            var shellCards = shell ? shell.querySelectorAll('.product-card').length : 0;
            if (shellCards === 0) grid.classList.remove('af-grid-hidden');
        });
    }
    setInterval(ensureProductsVisible, 700);
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
/* All images (main AND hover/gallery) fill the exact same 4:3 box so a card
   never changes size on hover — main image and gallery image share one box. */
html body .product-card .product-image img,
html body .product-card .image-wrapper img,
html body .product-card .woocommerce-loop-product__link img {
  position:absolute !important; top:0 !important; left:0 !important;
  width:100% !important; height:100% !important; max-width:none !important;
  object-fit:cover !important; display:block !important;
  transition:transform .4s,opacity .3s !important;
}
html body .product-card:hover .product-image img,
html body .product-card:hover .image-wrapper img,
html body .product-card:hover .woocommerce-loop-product__link img { transform:scale(1.05) !important; }
/* gallery (2nd) image hidden at rest, fades in on hover — same box as main */
html body .product-card .product-image img + img,
html body .product-card .image-wrapper img + img,
html body .product-card .woocommerce-loop-product__link img + img,
html body .product-card .woocommerce-loop-product__link img:nth-child(2),
html body .product-card .secondary-image {
  opacity:0 !important; z-index:2 !important;
}
html body .product-card:hover .product-image img + img,
html body .product-card:hover .image-wrapper img + img,
html body .product-card:hover .woocommerce-loop-product__link img + img,
html body .product-card:hover .woocommerce-loop-product__link img:nth-child(2),
html body .product-card:hover .secondary-image {
  opacity:1 !important;
}

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

      card.dataset.hoverFixed6 = '1';
      mainImg.classList.add('af-main-img');
      hoverImg.classList.add('af-hover-img');

      // UNIFORM box: fixed height for every card. The old per-image natural
      // ratio (padding-bottom) made each card a different height, so grids
      // (New Arrivals, etc.) staggered and hover images misaligned.
      var H = (window.innerWidth <= 520) ? 260 : 300;

      var wrap = document.createElement('div');
      wrap.className = 'af-img-ratio';
      wrap.style.setProperty('position',       'relative',    'important');
      wrap.style.setProperty('width',          '100%',        'important');
      wrap.style.setProperty('height',         H + 'px',      'important');
      wrap.style.setProperty('max-height',     H + 'px',      'important');
      wrap.style.setProperty('padding-bottom', '0',           'important');
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

    // Fixed-height box needs no natural dimensions — build immediately.
    build();
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

    // Fetch video IDs, cached 1 hour. (Key bumped to _ids3_ to bypass stale cache.)
    $channel = 'UC_GX4vXRQrN4GsvSfgmZxYw';
    $ids = get_transient('af_yt_ids3_' . $channel);
    // Treat a too-small cached list (e.g. only the single Elementor video, left
    // over from a moment when the YouTube RSS fetch was blocked) as needing a
    // rebuild — otherwise the 1-hour transient pins the row to one video.
    if (!is_array($ids) || count($ids) < 3) {
        $ids = [];

        // 1) PRIMARY: pull the YouTube video IDs already placed in the homepage's
        //    Elementor content (reliable — no external request needed).
        $raw = get_post_meta(get_the_ID(), '_elementor_data', true);
        if ($raw) {
            $flat = str_replace('\\/', '/', $raw); // unescape JSON slashes
            if (preg_match_all('#(?:youtube(?:-nocookie)?\.com/(?:watch\?(?:[^"&]*&)*v=|embed/|shorts/|v/)|youtu\.be/)([A-Za-z0-9_-]{11})#i', $flat, $mm)) {
                $ids = array_merge($ids, $mm[1]);
            }
            if (preg_match_all('#[?&]v=([A-Za-z0-9_-]{11})#', $flat, $mm2)) {
                $ids = array_merge($ids, $mm2[1]);
            }
        }

        // 2) SUPPLEMENT: latest uploads from the channel RSS (best-effort)
        $resp = wp_remote_get(
            'https://www.youtube.com/feeds/videos.xml?channel_id=' . $channel,
            ['timeout' => 8, 'sslverify' => false]
        );
        if (!is_wp_error($resp)) {
            $xml = @simplexml_load_string(wp_remote_retrieve_body($resp));
            if ($xml) {
                foreach ($xml->entry as $entry) {
                    if (preg_match('/video:([A-Za-z0-9_-]{11})/', (string)$entry->id, $m)) $ids[] = $m[1];
                }
            }
        }

        $ids = array_values(array_unique(array_filter($ids)));

        // 3) DURABLE FALLBACK: merge the last-known-good list so a blocked RSS
        //    fetch can never shrink the row back to a single video. Cap the
        //    total so it can't grow unbounded as videos are added over time.
        $lastgood = get_option('af_yt_ids3_lastgood_' . $channel);
        if (is_array($lastgood) && $lastgood) {
            $ids = array_values(array_unique(array_merge($ids, $lastgood)));
        }
        $ids = array_slice($ids, 0, 30);

        // Only persist / update last-good once we actually have the full row,
        // so a bad fetch never overwrites a good saved list.
        if (count($ids) >= 3) {
            set_transient('af_yt_ids3_' . $channel, $ids, HOUR_IN_SECONDS);
            update_option('af_yt_ids3_lastgood_' . $channel, $ids, false);
        }
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

    /* v5 — structure guard: this transform was designed for the CLASSIC card
       markup where the image link is a DIRECT child of li.product. On nested
       "product-block" layouts a partial transform exposes the theme's hover
       overlay and adds a stray 1:1 spacer — so on those cards we do NOTHING
       and let the theme's own (correct) styling stand untouched. */
    var linkProbe = card.querySelector('a.woocommerce-loop-product__link');
    if (!linkProbe || linkProbe.parentNode !== card) return;

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
    //      SAFETY: never hide a wrapper that CONTAINS the product content.
    //      (Postero "product-block" layouts nest link/title/price inside a
    //      wrapper div — hiding it blanked the whole card on category pages.)
    Array.from(card.children).forEach(function(c){
      if (c===mainLink) return;
      var cls=c.classList;
      if (cls.contains('woocommerce-loop-product__title')) return;
      if (cls.contains('woocommerce-product-rating'))     return;
      if (cls.contains('price'))                          return;
      if (c.contains(mainLink)) return;
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

      // Insert relative to mainLink's ACTUAL parent (mainLink may be nested
      // inside a theme wrapper, not a direct child of the card — the old
      // card.insertBefore threw NotFoundError and aborted styling mid-loop,
      // leaving remaining cards blank).
      mainLink.parentNode.insertBefore(wrap, mainLink);
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
    document.querySelectorAll('ul.products li.product').forEach(function(card){
      try { fixShopCard(card); }
      catch(e) {
        // Never leave a card half-styled/hidden if anything throws —
        // un-hide everything inside and let the theme's own styling stand.
        card.removeAttribute('data-af-shop-fixed');
        card.dataset.afShopFixed = 'error';
        card.querySelectorAll('*').forEach(function(el){
          if (el.style && el.style.display === 'none') el.style.removeProperty('display');
        });
      }
    });
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

// Try-On-Wall CSS — loaded site-wide (not just product pages) so the button
// is styled inside the Quick View modal too (Quick View is injected on the
// homepage/shop where is_product() is false). The CSS is fully class-scoped
// (.af-arw-*), so it has no effect on any other markup.
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('af-ar-wall', get_stylesheet_directory_uri() . '/assets/css/ar-wall.css', array(), '1.2.0');
}, 25);

// "Try It On Your Wall" button under Add to Cart — now LINKS to the full
// standalone /try-on-wall/ page with this product pre-selected.
add_action('woocommerce_after_add_to_cart_button', function() {
    global $product;
    if (!$product) return;
    $url = add_query_arg('tow', $product->get_id(), home_url('/try-on-wall/'));
    ?>
    <a href="<?php echo esc_url($url); ?>" class="af-arw-btn">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px;flex-shrink:0">
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
            <li><a href="/low-price-guarantee/">Low Price Guarantee</a></li>
            <li><a href="/gift-cards/">Gift Cards</a></li>
            <li><a href="/faqs/">FAQs</a></li>
          </ul>
        </div>

        <div class="af-fcol">
          <h5 class="af-f-title">Company</h5>
          <ul class="af-f-links">
            <li><a href="/about/">About Us</a></li>
            <li><a href="/contact/">Contact</a></li>
            <li><a href="/wholesale-corporate/">Wholesale &amp; Corporate</a></li>
            <li><a href="/artists/">Artists &amp; Creators</a></li>
            <li><a href="/reviews-press/">Reviews &amp; Press</a></li>
            <li><a href="/refer-a-friend/">Refer a Friend</a></li>
            <li><a href="/affiliates/">Affiliates</a></li>
            <li><a href="/exhibitions-events/">Exhibitions &amp; Events</a></li>
            <li><a href="/privacy-policy/">Privacy Policy</a></li>
            <li><a href="/terms-conditions/">Terms &amp; Conditions</a></li>
            <li><a href="/content-ethics-policy/">Content &amp; Ethics</a></li>
            <li><a href="/legal-imprint/">Legal Imprint</a></li>
          </ul>
          <div class="af-f-news">
            <h5 class="af-f-title">Newsletter</h5>
            <?php if (shortcode_exists('mc4wp_form')): ?>
              <?php echo do_shortcode('[mc4wp_form]'); ?>
            <?php else: ?>
              <form class="af-f-newsform" method="post"
                    data-ajax="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
                    data-nonce="<?php echo esc_attr(wp_create_nonce('af_nl_subscribe')); ?>">
                <input type="email" name="af_nl_email" placeholder="Your email" required aria-label="Email for newsletter">
                <input type="text" name="af_nl_hp" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;">
                <button type="submit">Join</button>
              </form>
              <p class="af-f-newsmsg" role="status" aria-live="polite" style="display:none;margin:8px 0 0;font-size:12.5px;"></p>
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
          <?php echo do_shortcode('[af_country_selector]'); ?>
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
    $url = add_query_arg('tow', $product->get_id(), home_url('/try-on-wall/'));
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

// 7g. Styles for the new product-page sections.
//     Loaded site-wide (all selectors are .af-* class-scoped) so the Quick View
//     modal — which renders the same product summary on the homepage/shop —
//     gets identical button/chip/card styling as the product page.
add_action('wp_head', function() {
    ?>
    <style>
    /* Buy Now (works on product page AND inside the Quick View modal) */
    .af-buynow.button{background:#1a1a1a !important;color:#fff !important;margin-left:8px !important;}
    .af-buynow.button:hover{background:#c9a84c !important;}
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

// 7j. Styles for the two added sections (loaded site-wide; .af-* class-scoped
//     so the Quick View modal's Digital Downloads / Customize sections match).
add_action('wp_head', function() {
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
// Which products get the size/frame/color engine: every purchasable
// simple or variable product EXCEPT the gift card and non-canvas
// categories (accessories, banners) that have their own option sets.
function af_pricing_applies($product) {
    if (!$product) return false;
    if (!$product->is_type('simple') && !$product->is_type('variable')) return false;
    if (function_exists('af_gc_product_id') && (int) $product->get_id() === af_gc_product_id()) return false;
    if (get_post_meta($product->get_id(), '_af_is_gift_card', true) === 'yes') return false;
    if ($product->get_slug() === 'the-art-framer-gift-card') return false;
    $excluded = array('art-accessories', 'banners-signage');
    $terms = get_the_terms($product->get_id(), 'product_cat');
    if ($terms && !is_wp_error($terms)) {
        foreach ($terms as $t) {
            if (in_array($t->slug, $excluded, true)) return false;
            // also honour excluded parents (e.g. child cats of accessories)
            $anc = get_ancestors($t->term_id, 'product_cat');
            foreach ($anc as $aid) {
                $at = get_term($aid, 'product_cat');
                if ($at && !is_wp_error($at) && in_array($at->slug, $excluded, true)) return false;
            }
        }
    }
    return true;
}

function af_pricing_config() {
    return array(
        // Size label => price multiplier (relative to the product's base price)
        'sizes' => array(
            '2×3 ft (24×36 in)'   => 1.00,  // base — spec size list (mega menu)
            '2×3.5 ft (24×42 in)' => 1.15,
            '2×4 ft (24×48 in)'   => 1.30,
            '2×5 ft (24×60 in)'   => 1.60,
            '2.5×3 ft (30×36 in)' => 1.22,
            '2.5×4 ft (30×48 in)' => 1.55,
            '2.5×5 ft (30×60 in)' => 1.90,
            '3×4 ft (36×48 in)'   => 1.80,
            '3×5 ft (36×60 in)'   => 2.20,
            '3×6 ft (36×72 in)'   => 2.60,
            '4×3 ft (48×36 in)'   => 1.80,
            '4×4 ft (48×48 in)'   => 2.40,
            '4×5 ft (48×60 in)'   => 2.90,
            '4×6 ft (48×72 in)'   => 3.40,
        ),
        // Quick-filter groups (spec: Small / Medium / Large / Custom)
        'groups' => array(
            'Small'  => array('2×3 ft (24×36 in)','2×3.5 ft (24×42 in)','2.5×3 ft (30×36 in)'),
            'Medium' => array('2×4 ft (24×48 in)','2.5×4 ft (30×48 in)','3×4 ft (36×48 in)','4×3 ft (48×36 in)'),
            'Large'  => array('2×5 ft (24×60 in)','2.5×5 ft (30×60 in)','3×5 ft (36×60 in)','3×6 ft (36×72 in)','4×4 ft (48×48 in)','4×5 ft (48×60 in)','4×6 ft (48×72 in)'),
        ),
        // Wall-suitability hints (spec: "Best for 10×12 ft walls")
        'hints' => array(
            '2×3 ft (24×36 in)'   => 'Best for 6×8 ft walls & cozy corners',
            '2×3.5 ft (24×42 in)' => 'Best for 6×8 ft walls',
            '2×4 ft (24×48 in)'   => 'Best for 8×10 ft walls',
            '2×5 ft (24×60 in)'   => 'Best above sofas & consoles',
            '2.5×3 ft (30×36 in)' => 'Best for 7×9 ft walls',
            '2.5×4 ft (30×48 in)' => 'Best for 8×10 ft walls',
            '2.5×5 ft (30×60 in)' => 'Best for 9×11 ft walls',
            '3×4 ft (36×48 in)'   => 'Best for 9×11 ft walls',
            '3×5 ft (36×60 in)'   => 'Best for 10×12 ft walls',
            '3×6 ft (36×72 in)'   => 'Statement piece — 10×12 ft+ walls',
            '4×3 ft (48×36 in)'   => 'Best for 9×11 ft walls (landscape)',
            '4×4 ft (48×48 in)'   => 'Best for 10×12 ft walls',
            '4×5 ft (48×60 in)'   => 'Grand walls & double-height spaces',
            '4×6 ft (48×72 in)'   => 'Extra large — lobbies & feature walls',
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
    if (!$product || !af_pricing_applies($product) || !$product->is_purchasable() || !$product->is_in_stock()) return;
    $cfg  = af_pricing_config();
    if ($product->is_type('variable')) {
        $min  = (float) $product->get_variation_price('min');
        $base = $min > 0 ? $min : (float) wc_get_price_to_display($product);
    } else {
        $base = (float) wc_get_price_to_display($product);
    }
    $sizes  = array_keys($cfg['sizes']);
    $frames = array_keys($cfg['frames']);
    $colors = array_keys($cfg['colors']);
    ?>
    <div class="af-opts" id="af-opts" data-base="<?php echo esc_attr($base); ?>" data-config='<?php echo esc_attr(wp_json_encode($cfg)); ?>' data-symbol="<?php echo esc_attr(get_woocommerce_currency_symbol()); ?>">
      <div class="af-opt-group">
        <label class="af-opt-label">Size <span class="af-opt-sub">(height × width)</span></label>
        <div class="af-chips af-group-chips">
          <button type="button" class="af-chip-grp active" data-grp="All">All</button>
          <button type="button" class="af-chip-grp" data-grp="Small">Small</button>
          <button type="button" class="af-chip-grp" data-grp="Medium">Medium</button>
          <button type="button" class="af-chip-grp" data-grp="Large">Large</button>
          <a class="af-chip-grp af-chip-custom" href="/customize-your-picture/">Custom ↗</a>
        </div>
        <div class="af-chips af-size-chips">
          <?php foreach ($sizes as $i => $s): ?>
            <button type="button" class="af-chip-opt<?php echo $i===0?' active':''; ?>" data-type="size" data-val="<?php echo esc_attr($s); ?>"><?php echo esc_html($s); ?></button>
          <?php endforeach; ?>
        </div>
      </div>
      <p class="af-wall-hint" id="afWallHint"></p>
      <div class="af-opt-group">
        <label class="af-opt-label">Frame Type</label>
        <div class="af-chips af-frame-chips">
          <?php foreach ($frames as $i => $f): $fee=$cfg['frames'][$f]; ?>
            <button type="button" class="af-chip-opt<?php echo $i===0?' active':''; ?>" data-type="frame" data-val="<?php echo esc_attr($f); ?>"><?php if($f==='Floating Frame') echo '<span class="af-rec">Recommended</span>'; ?><?php echo esc_html($f); ?><?php if($fee>0) echo ' <em>+'.get_woocommerce_currency_symbol().$fee.'</em>'; ?></button>
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
      <p class="af-color-tip">🎨 Color may slightly vary due to lighting</p>
      <div class="af-price-live">Your Price: <strong id="af-live-price"><?php echo wc_price($base); ?></strong>
        <s id="af-live-mrp" class="af-live-mrp"></s></div>
      <p class="af-price-notes">✓ Inclusive of all taxes &nbsp;·&nbsp; 📦 Free Secure Packaging</p>
      <input type="hidden" name="af_size"  value="<?php echo esc_attr($sizes[0]); ?>">
      <input type="hidden" name="af_frame" value="<?php echo esc_attr($frames[0]); ?>">
      <input type="hidden" name="af_color" value="<?php echo esc_attr($colors[0]); ?>">
    </div>
    <?php
}, 8);

// 8b. Capture selections + compute authoritative price on add to cart
add_filter('woocommerce_add_cart_item_data', function($data, $pid) {
    if (!empty($_REQUEST['af_digital'])) return $data; // digital handled separately
    $product = wc_get_product($pid);
    if (!$product || !af_pricing_applies($product)) return $data;
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
    $af_base = $product->is_type('variable') ? (float) $product->get_variation_price('min') : (float) wc_get_price_to_display($product);
    if ($af_base <= 0) $af_base = (float) wc_get_price_to_display($product);
    $data['af_price'] = af_calc_price($af_base, $size, $frame, $color);
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

// 8f. Selector styling + live price JS.
//     Loaded site-wide (all .af-* class-scoped) so the Size/Frame/Color chips,
//     swatches and live price look identical inside the Quick View modal.
add_action('wp_head', function() {
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

// FAQ accordion styling (site-wide; .af-faq-* class-scoped, matches modal)
add_action('wp_head', function() {
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

// 10a. Pin the WooCommerce price-slider/filter widget to the active currency
add_filter('woocommerce_currency', function(){ return af_active_currency(); }, PHP_INT_MAX);
add_filter('woocommerce_currency_symbol', function($s,$c){ return af_currency_symbol_for($c); }, PHP_INT_MAX, 2);
add_filter('woocommerce_price_slider_params', function($p){
    $p['currency_format_symbol']   = af_currency_symbol_for(af_active_currency());
    $p['currency_format_num_decimals'] = 0;
    return $p;
}, PHP_INT_MAX);

// 10a-2. Keep FOX/WOOCS off geoip (it used to geo-switch visitors to INR) and
// pin its current currency to the visitor's whitelisted choice (USD or CAD).
// Conversion is bypassed only for USD (base); CAD converts at the WOOCS rate.
add_filter('woocs_is_geoip_activated', '__return_false', PHP_INT_MAX);
add_filter('woocs_geoip_country', function(){ return 'US'; }, PHP_INT_MAX);
add_filter('woocs_current_currency', function(){ return af_active_currency(); }, PHP_INT_MAX);
add_filter('woocs_convert_price', function($price){
    return $price; // passthrough; WOOCS handles CAD conversion via its rate
}, 1);
add_action('init', function(){
    // WOOCS reads this cookie first — pin it to the whitelisted choice before the plugin runs
    $active = af_active_currency();
    if (!isset($_COOKIE['woocs_current_currency']) || $_COOKIE['woocs_current_currency'] !== $active) {
        @setcookie('woocs_current_currency', $active, time()+YEAR_IN_SECONDS, defined('COOKIEPATH')?COOKIEPATH:'/', defined('COOKIE_DOMAIN')?COOKIE_DOMAIN:'');
        $_COOKIE['woocs_current_currency'] = $active;
    }
    if (session_status() === PHP_SESSION_ACTIVE) { $_SESSION['woocs_current_currency'] = $active; }
    $def = get_option('woocs_default_currency', '');
    if ($def !== 'USD') update_option('woocs_default_currency', 'USD');
}, 0);
add_action('wp_loaded', function(){
    if (isset($GLOBALS['WOOCS']) && is_object($GLOBALS['WOOCS'])) {
        $w = $GLOBALS['WOOCS'];
        $active = af_active_currency();
        if (method_exists($w, 'set_currency')) { $w->set_currency($active); }
        if (property_exists($w, 'current_currency')) { $w->current_currency = $active; }
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
        var m = location.search.match(/[?&]tow=(\d+)/);
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

// ─────────────────────────────────────────────────────────────
// PHASE 14 — Mobile responsiveness hardening. Only applies at mobile
// breakpoints; additive, does not change desktop layout/logic.
// ─────────────────────────────────────────────────────────────
add_action('wp_head', function() {
    ?>
    <style id="af-mobile-responsive">
    /* ── Tablets & phones (≤ 781px) ── */
    @media (max-width: 781px){
      html{ -webkit-text-size-adjust: 100%; }
      /* Kill horizontal scroll without breaking the sticky header (clip keeps sticky) */
      body{ overflow-x: clip; }
      /* Media never overflows its container */
      img, svg{ max-width: 100% !important; height: auto !important; }
      video, iframe{ max-width: 100% !important; }
      /* Long words / URLs wrap instead of forcing width */
      p, h1, h2, h3, h4, h5, h6, a, span, li, td, th{ overflow-wrap: break-word; word-wrap: break-word; }
      /* Wide tables scroll inside their own box */
      .entry-content table, .woocommerce table.shop_table, .af-faq-a table, table{ display: block; width: 100%; max-width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
      /* Elementor containers go full-width on mobile (prevents fixed-width overflow) */
      .elementor-section .elementor-container, .e-con-inner, .e-con{ max-width: 100% !important; }
      /* Our custom sections collapse gracefully */
      .af-pp-row{ grid-template-columns: 1fr 1fr !important; }
      .af-hs-row{ grid-template-columns: 1fr 1fr !important; }
      .af-rv-row, .af-blog-row{ grid-template-columns: 1fr !important; }
      .af-trust-inner{ flex-wrap: wrap !important; }
      .af-tow-grid{ grid-template-columns: 1fr !important; }
      .af-tow-stage{ height: 420px !important; }
      .af-footer-inner{ grid-template-columns: 1fr 1fr !important; }
      .af-listing-toolbar .af-lt-controls{ overflow-x: auto; -webkit-overflow-scrolling: touch; }
      /* Hide our floating quick-access panel on mobile — it overlaps content
         and is redundant with the theme's bottom mobile nav + Click-to-Chat. */
      .af-quickpanel{ display: none !important; }
    }
    /* ── Phones (≤ 480px) ── */
    @media (max-width: 480px){
      .af-pp-row, .af-hs-row{ grid-template-columns: 1fr !important; }
      .af-footer-inner{ grid-template-columns: 1fr !important; }
      .af-tow-title{ font-size: 28px !important; }
      .af-pp-sec h2, .af-hs-head h2{ font-size: 20px !important; }
    }
    </style>
    <?php
}, 99);

// ─────────────────────────────────────────────────────────────
// PHASE 15 — Fix blank product cards on shop/category: force any
// lazy-loaded images (LiteSpeed/Elementor/native) to load. Their
// data is valid; the lazy placeholder just never swapped in.
// DISABLED: LiteSpeed lazy-load is now turned off at the source
// (media-lazy=false), so this JS patch is unnecessary and could
// conflict with other image scripts.
// ─────────────────────────────────────────────────────────────
add_action('wp_footer', function() {
    return; // DISABLED — lazy-load off at source
    if (!function_exists('is_shop')) return;
    if (!(is_shop() || is_product_category() || is_product_tag())) return;
    ?>
    <script>
    (function(){
      function unlazy(){
        // <img> lazy variants
        document.querySelectorAll('img:not([data-afun])').forEach(function(im){
          var real = im.getAttribute('data-src') || im.getAttribute('data-lazy-src') ||
                     im.getAttribute('data-lazysrc') || im.getAttribute('data-original') ||
                     im.getAttribute('data-ls-src');
          var cur = im.getAttribute('src') || '';
          if (real && (cur === '' || cur.indexOf('data:image') === 0 || cur.indexOf('base64') > -1 || cur.indexOf('lazy') > -1)) {
            im.src = real;
          }
          var ss = im.getAttribute('data-srcset') || im.getAttribute('data-lazy-srcset');
          if (ss && !im.getAttribute('srcset')) im.setAttribute('srcset', ss);
          im.removeAttribute('loading');
          im.classList.remove('lazyload','litespeed-lazyload');
          im.setAttribute('data-afun','1');
        });
        // lazy background images
        document.querySelectorAll('[data-bg]:not([data-afun]),[data-lazy-bg]:not([data-afun])').forEach(function(el){
          var v = el.getAttribute('data-bg') || el.getAttribute('data-lazy-bg');
          if (v) el.style.backgroundImage = 'url("' + v + '")';
          el.setAttribute('data-afun','1');
        });
      }
      function run(){ try{ unlazy(); }catch(e){} }
      if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run); else run();
      window.addEventListener('load', function(){ run(); setTimeout(run,400); setTimeout(run,1200); });
      // Re-run when the grid loads more cards (AJAX) — childList only, no loop
      var mo = new MutationObserver(function(muts){
        if (muts.some(function(m){ return m.addedNodes && m.addedNodes.length; })) run();
      });
      if (document.body) mo.observe(document.body, { childList:true, subtree:true });
    })();
    </script>
    <?php
}, 40);

// ─────────────────────────────────────────────────────────────
// PHASE 16 — Digital Download modal from product-card buttons.
// Clicking a card's "Digital Download" opens a modal; Add to Cart
// adds a digital line (own price + label). Additive.
// ─────────────────────────────────────────────────────────────

// Digital download flat price (adjustable via filter)
function af_digital_price() { return (float) apply_filters('af_digital_price', 9.99); }

// Mark the cart item as a digital download with its own price/label
add_filter('woocommerce_add_cart_item_data', function($data, $pid) {
    if (!empty($_REQUEST['af_digital'])) {
        $data['af_digital'] = '1';
        $data['af_price']   = af_digital_price();
        $data['af_unique']  = md5('digital|' . $pid . '|' . microtime());
    }
    return $data;
}, 20, 2);

// Show "Digital Download" in cart; hide size/frame for digital lines
add_filter('woocommerce_get_item_data', function($data, $item) {
    if (!empty($item['af_digital'])) {
        // strip any size/frame rows that may have been added
        $data = array_values(array_filter($data, function($row){
            return !in_array(($row['name'] ?? ''), array('Size','Frame Type','Frame Color'), true);
        }));
        $data[] = array('name'=>'Format', 'value'=>'Digital Download (instant email delivery)');
    }
    return $data;
}, 20, 2);

// Make digital lines virtual (no shipping) at cart calc time
add_action('woocommerce_before_calculate_totals', function($cart){
    if (is_admin() && !defined('DOING_AJAX')) return;
    if (empty($cart) || !is_a($cart,'WC_Cart')) return;
    foreach ($cart->get_cart() as $item) {
        if (!empty($item['af_digital']) && is_object($item['data'])) {
            $item['data']->set_virtual(true);
        }
    }
}, 21);

// Persist to order line item
add_action('woocommerce_checkout_create_order_line_item', function($item, $key, $values){
    if (!empty($values['af_digital'])) $item->add_meta_data('Format', 'Digital Download');
}, 20, 3);

// Render the Digital Download modal on homepage / shop / category
add_action('wp_footer', function() {
    if (is_admin()) return;
    $is_shop = function_exists('is_shop') && (is_shop() || is_product_category() || is_product_tag());
    if (!is_front_page() && !$is_shop) return;
    $price_html = wc_price(af_digital_price());
    ?>
    <div id="af-dd-overlay" class="af-dd-overlay" data-dd-close>
      <div class="af-dd-modal">
        <button class="af-dd-x" data-dd-close aria-label="Close">&times;</button>
        <div class="af-dd-flex">
          <div class="af-dd-imgwrap"><img id="af-dd-img" alt=""></div>
          <div class="af-dd-info">
            <span class="af-dd-tag">⬇ Instant Digital Download</span>
            <h3 id="af-dd-title">Digital Download</h3>
            <ul class="af-dd-feat">
              <li>High-resolution, print-ready file (JPG)</li>
              <li>Delivered instantly by email after purchase</li>
              <li>Print at home or any print shop — no frame needed</li>
            </ul>
            <div class="af-dd-price"><?php echo $price_html; ?></div>
            <div class="af-dd-actions">
              <button id="af-dd-add" class="af-dd-btn solid">Add to Cart</button>
              <a id="af-dd-view" class="af-dd-btn ghost" href="#">View Product</a>
            </div>
            <p class="af-dd-msg" id="af-dd-msg"></p>
          </div>
        </div>
      </div>
    </div>
    <script>
    (function(){
      var overlay=document.getElementById('af-dd-overlay');
      var imgEl=document.getElementById('af-dd-img'), titleEl=document.getElementById('af-dd-title');
      var viewEl=document.getElementById('af-dd-view'), addEl=document.getElementById('af-dd-add'), msgEl=document.getElementById('af-dd-msg');
      var curPid='';
      function open(){ overlay.classList.add('open'); document.body.style.overflow='hidden'; }
      function close(){ overlay.classList.remove('open'); document.body.style.overflow=''; msgEl.textContent=''; }
      overlay.querySelectorAll('[data-dd-close]').forEach(function(el){ el.addEventListener('click', function(e){ if(e.target===el) close(); }); });
      document.addEventListener('keydown', function(e){ if(e.key==='Escape') close(); });

      // Detect a "Digital Download" trigger inside a product card.
      // Robust across sections: strip icons/whitespace and match the label
      // exactly ("⊕ Digital Download", "Digital Download", etc.), walking up a
      // few levels so clicking the icon or its link still opens the modal.
      var CARD_SEL = '.product-card, li.product, .product, .product-block, [class*="product-block"]';
      document.addEventListener('click', function(e){
        var trg = e.target.closest('.digital-download, .digital-download-btn, [class*="digital-download"], [data-digital-download]');
        if(!trg){
          var node = e.target;
          for(var i=0;i<4 && node && node!==document.body;i++){
            var txt = (node.textContent||'').replace(/[^a-z]/gi,'').toLowerCase();
            if(txt === 'digitaldownload'){ trg = node; break; }
            node = node.parentElement;
          }
        }
        if(!trg) return;
        var card = trg.closest(CARD_SEL); if(!card) return;
        e.preventDefault(); e.stopPropagation();
        var atc = card.querySelector('[data-product_id]');
        curPid = atc ? atc.getAttribute('data-product_id') : '';
        if(!curPid){ // fallback: WooCommerce li.product carries a post-<id> class
          var m = (card.className||'').match(/post-(\d+)/);
          if(m) curPid = m[1];
          if(!curPid){ var pel=card.querySelector('[class*="post-"]'); if(pel){ var m2=(pel.className||'').match(/post-(\d+)/); if(m2) curPid=m2[1]; } }
        }
        var im = card.querySelector('img');
        var t  = card.querySelector('.product-title, h2, h3, .woocommerce-loop-product__title');
        var lnk= card.querySelector('a[href]');
        imgEl.src = im ? (im.currentSrc||im.src||im.getAttribute('data-src')||'') : '';
        titleEl.textContent = (t?t.textContent.trim():'This Artwork') + ' — Digital Download';
        viewEl.href = lnk ? lnk.href : '#';
        msgEl.textContent='';
        open();
      }, true);

      addEl.addEventListener('click', function(){
        if(!curPid){ msgEl.textContent='Please open this from a product to add it.'; return; }
        addEl.disabled=true; addEl.textContent='Adding…';
        fetch('/?wc-ajax=add_to_cart', {
          method:'POST', credentials:'same-origin',
          headers:{'Content-Type':'application/x-www-form-urlencoded'},
          body:'product_id='+encodeURIComponent(curPid)+'&quantity=1&af_digital=1'
        }).then(function(r){ return r.json(); }).then(function(res){
          addEl.disabled=false; addEl.textContent='Add to Cart';
          msgEl.style.color='#2e7d32';
          msgEl.textContent='✓ Digital download added to cart.';
          // update mini-cart counters if present
          document.body.dispatchEvent(new Event('wc_fragment_refresh'));
          if(res && res.fragments){ for(var k in res.fragments){ document.querySelectorAll(k).forEach(function(n){ n.outerHTML=res.fragments[k]; }); } }
        }).catch(function(){ addEl.disabled=false; addEl.textContent='Add to Cart'; msgEl.style.color='#c0392b'; msgEl.textContent='Could not add. Please try again.'; });
      });
    })();
    </script>
    <style>
    .af-dd-overlay{position:fixed;inset:0;z-index:100000;display:none;background:rgba(15,15,15,.82);backdrop-filter:blur(3px);align-items:center;justify-content:center;padding:20px;}
    .af-dd-overlay.open{display:flex;}
    .af-dd-modal{background:#fff;border-radius:16px;max-width:720px;width:100%;position:relative;box-shadow:0 24px 70px rgba(0,0,0,.5);overflow:hidden;}
    .af-dd-x{position:absolute;top:12px;right:14px;background:none;border:none;font-size:28px;line-height:1;color:#888;cursor:pointer;z-index:2;}
    .af-dd-x:hover{color:#1a1a1a;}
    .af-dd-flex{display:flex;flex-wrap:wrap;}
    .af-dd-imgwrap{flex:1 1 260px;min-height:240px;background:#f4f4f4;}
    .af-dd-imgwrap img{width:100%;height:100%;object-fit:cover;display:block;}
    .af-dd-info{flex:1 1 300px;padding:26px 24px;}
    .af-dd-tag{display:inline-block;background:#faf2df;color:#a8872e;font-weight:800;font-size:12px;padding:5px 11px;border-radius:14px;letter-spacing:.03em;}
    .af-dd-info h3{font-size:19px;font-weight:800;color:#1a1a1a;margin:12px 0 12px;line-height:1.35;}
    .af-dd-feat{list-style:none;margin:0 0 16px;padding:0;display:flex;flex-direction:column;gap:8px;}
    .af-dd-feat li{font-size:13px;color:#555;padding-left:22px;position:relative;line-height:1.5;}
    .af-dd-feat li::before{content:'✓';position:absolute;left:0;color:#c9a84c;font-weight:800;}
    .af-dd-price{font-size:24px;font-weight:800;color:#1a1a1a;margin:0 0 16px;}
    .af-dd-actions{display:flex;gap:10px;flex-wrap:wrap;}
    .af-dd-btn{flex:1;min-width:130px;text-align:center;padding:12px 14px;border-radius:9px;font-weight:800;font-size:13.5px;cursor:pointer;text-decoration:none;border:none;}
    .af-dd-btn.solid{background:#c9a84c;color:#fff;}
    .af-dd-btn.solid:hover{background:#a8872e;}
    .af-dd-btn.ghost{background:#efe9db;color:#333;}
    .af-dd-msg{font-size:13px;margin:12px 0 0;min-height:16px;}
    @media(max-width:560px){ .af-dd-imgwrap{flex-basis:100%;min-height:200px;} }
    </style>
    <?php
}, 45);

// ─────────────────────────────────────────────────────────────
// PHASE 16b — Digital download is delivered ONLY after payment, and
// ONLY for lines bought as digital. WooCommerce natively grants the
// download on payment; here we revoke it for non-digital (physical)
// lines so only paying digital customers can download.
// ─────────────────────────────────────────────────────────────
add_action('woocommerce_grant_product_download_permissions', function($order_id){
    $order = wc_get_order($order_id);
    if (!$order) return;

    // Which products in this order were purchased AS digital?
    $digital_pids = array();
    foreach ($order->get_items() as $item) {
        $is_digital = ($item->get_meta('Format') === 'Digital Download') || $item->get_meta('af_digital');
        if ($is_digital) $digital_pids[$item->get_product_id()] = true;
    }

    // Revoke download permissions for products NOT bought as digital
    if (!class_exists('WC_Data_Store')) return;
    try {
        $store = WC_Data_Store::load('customer-download');
        $downloads = $store->get_downloads(array('order_id' => $order_id));
        foreach ($downloads as $d) {
            if (empty($digital_pids[$d->get_product_id()])) {
                $store->delete_by_id($d->get_id());
            }
        }
    } catch (Exception $e) { /* no-op */ }
}, 20);

// ─────────────────────────────────────────────────────────────
// PHASE 17 — Keep the primary nav menu on ONE line (desktop only).
// ─────────────────────────────────────────────────────────────
add_action('wp_head', function() {
    ?>
    <style id="af-nav-oneline">
    @media (min-width: 1025px){
      /* Force the header menu row to not wrap */
      .elementor-nav-menu,
      ul.elementor-nav-menu,
      .elementor-nav-menu--main > .elementor-nav-menu__container > ul,
      .elementor-nav-menu--main ul.elementor-nav-menu,
      nav .elementor-nav-menu,
      .main-navigation > ul,
      #site-navigation > ul,
      .site-header .menu,
      .postero-nav ul.menu {
        display: flex !important;
        flex-wrap: nowrap !important;
        align-items: center !important;
        white-space: nowrap !important;
        row-gap: 0 !important;
      }
      /* Each item stays on one line */
      .elementor-nav-menu > li,
      .elementor-nav-menu li.menu-item,
      .main-navigation li,
      .site-header .menu > li {
        white-space: nowrap !important;
        flex: 0 0 auto !important;
      }
      /* Tighten spacing + font so all items fit one row */
      .elementor-nav-menu .elementor-item,
      .elementor-nav-menu > li > a,
      .main-navigation a,
      .site-header .menu > li > a {
        padding-left: 11px !important;
        padding-right: 11px !important;
        font-size: 14px !important;
        white-space: nowrap !important;
      }
    }
    @media (min-width: 1025px) and (max-width: 1200px){
      /* Slightly smaller on narrower desktops to still fit one line */
      .elementor-nav-menu .elementor-item,
      .elementor-nav-menu > li > a,
      .main-navigation a { font-size: 13px !important; padding-left: 8px !important; padding-right: 8px !important; }
    }
    </style>
    <?php
}, 100);

// ─────────────────────────────────────────────────────────────
// PHASE 17b — Force the primary nav onto one line via JS (finds the
// menu by its content, so it works regardless of theme class names).
// ─────────────────────────────────────────────────────────────
add_action('wp_footer', function() {
    ?>
    <script>
    (function(){
      function fixNav(){
        if (window.innerWidth < 1025) return; // desktop only
        var target = null;
        document.querySelectorAll('ul').forEach(function(ul){
          if (target) return;
          var t = (ul.textContent || '').toLowerCase();
          // top-level header menu contains these items
          if (t.indexOf('try it on your wall') > -1 && t.indexOf('categories') > -1 &&
              t.indexOf('contact us') > -1 && ul.children.length >= 5) {
            // prefer the shallowest matching UL (the main menu, not a wrapper)
            target = ul;
          }
        });
        if (!target) return;
        target.style.setProperty('display','flex','important');
        target.style.setProperty('flex-wrap','nowrap','important');
        target.style.setProperty('align-items','center','important');
        target.style.setProperty('white-space','nowrap','important');
        target.style.setProperty('width','100%','important');
        Array.from(target.children).forEach(function(li){
          li.style.setProperty('flex','0 0 auto','important');
          li.style.setProperty('white-space','nowrap','important');
          var a = li.querySelector('a');
          if (a){
            a.style.setProperty('white-space','nowrap','important');
            a.style.setProperty('padding-left','9px','important');
            a.style.setProperty('padding-right','9px','important');
            a.style.setProperty('font-size','13.5px','important');
          }
        });
      }
      if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fixNav); else fixNav();
      window.addEventListener('load', function(){ fixNav(); setTimeout(fixNav,400); setTimeout(fixNav,1200); });
      window.addEventListener('resize', fixNav);
    })();
    </script>
    <?php
}, 101);

// ─────────────────────────────────────────────────────────────
// PHASE 18 — Styling for the custom About Us page (loaded from theme
// so it isn't stripped like inline <style> in page content).
// ─────────────────────────────────────────────────────────────
add_action('wp_head', function() {
    return; // DISABLED: original Elementor About page restored; theme design used.
    if (!function_exists('is_page') || !is_page(array('about','about-us'))) return;
    ?>
    <style id="af-about-css">
    .taf-about{max-width:1140px;margin:0 auto;padding:10px 18px 50px;color:#2a2a2a;font-family:inherit;}
    .taf-about h1,.taf-about h2,.taf-about h3{color:#1a1a1a;}
    /* Hero */
    .taf-hero{position:relative;text-align:center;border-radius:22px;padding:64px 28px 58px;margin:8px 0 26px;overflow:hidden;
      background:radial-gradient(1200px 400px at 50% -10%, #3a3320 0%, #191712 60%, #141210 100%);color:#f3eede;}
    .taf-hero::after{content:"";position:absolute;inset:0;background:
      repeating-linear-gradient(135deg, rgba(201,168,76,.06) 0 2px, transparent 2px 22px);pointer-events:none;}
    .taf-eyebrow{position:relative;display:inline-block;background:rgba(201,168,76,.16);color:#e8c766;border:1px solid rgba(201,168,76,.4);
      font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;padding:7px 15px;border-radius:20px;margin-bottom:18px;}
    .taf-hero h1{position:relative;font-size:52px;line-height:1.08;font-weight:800;color:#fff;margin:0 0 16px;letter-spacing:-.01em;}
    .taf-hero h1 span{color:#c9a84c;}
    .taf-hero p{position:relative;max-width:720px;margin:0 auto;font-size:16px;line-height:1.8;color:#d8d1bf;}
    .taf-hero-badges{position:relative;display:flex;flex-wrap:wrap;gap:10px;justify-content:center;margin-top:24px;}
    .taf-hero-badges span{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);color:#f0ead9;
      font-size:13px;font-weight:600;padding:9px 15px;border-radius:30px;backdrop-filter:blur(4px);}
    /* Stats */
    .taf-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin:0 0 40px;}
    .taf-stat{background:#faf7ef;border:1px solid #ece4cf;border-radius:14px;padding:22px 14px;text-align:center;}
    .taf-stat strong{display:block;font-size:30px;font-weight:800;color:#c9a84c;line-height:1;}
    .taf-stat span{display:block;margin-top:7px;font-size:12.5px;font-weight:700;letter-spacing:.03em;text-transform:uppercase;color:#6b6250;}
    /* Blocks */
    .taf-block{margin:0 0 44px;}
    .taf-block-head{margin:0 0 18px;}
    .taf-block-head h2{font-size:30px;font-weight:800;margin:0 0 10px;}
    .taf-rule{width:64px;height:4px;border-radius:3px;background:linear-gradient(90deg,#c9a84c,#e8c766);}
    .taf-story p, .taf-block > p{font-size:15.5px;line-height:1.85;color:#444;max-width:900px;}
    /* Offer cards */
    .taf-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;}
    .taf-card{background:#fff;border:1px solid #ececec;border-radius:16px;padding:24px 20px;transition:box-shadow .25s,transform .25s,border-color .25s;}
    .taf-card:hover{box-shadow:0 14px 34px rgba(0,0,0,.10);transform:translateY(-4px);border-color:#e6d9b5;}
    .taf-ico{width:52px;height:52px;border-radius:13px;background:#faf2df;display:flex;align-items:center;justify-content:center;font-size:26px;margin-bottom:14px;}
    .taf-card h3{font-size:17px;font-weight:800;margin:0 0 8px;}
    .taf-card p{font-size:13.5px;line-height:1.65;color:#5a5a5a;margin:0;}
    /* Why-choose list */
    .taf-list{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:1fr 1fr;gap:14px 30px;}
    .taf-list li{position:relative;padding:14px 16px 14px 46px;background:#faf7ef;border:1px solid #efe8d6;border-radius:12px;font-size:14.5px;line-height:1.55;color:#444;}
    .taf-list li strong{color:#1a1a1a;}
    .taf-list li::before{content:"✓";position:absolute;left:16px;top:14px;width:20px;height:20px;background:#c9a84c;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;}
    /* Mission */
    .taf-mission{position:relative;text-align:center;border-radius:20px;padding:46px 28px;margin:0 0 40px;overflow:hidden;
      background:radial-gradient(900px 300px at 50% 0%, #2a2416 0%, #17150f 70%);}
    .taf-mission h2{color:#fff;font-size:28px;margin:0 0 12px;}
    .taf-mission p{color:#e8e2cf;font-size:18px;line-height:1.7;max-width:740px;margin:0 auto;font-style:italic;}
    /* CTA */
    .taf-cta{text-align:center;background:#faf7ef;border:1px solid #ece4cf;border-radius:20px;padding:40px 26px;}
    .taf-cta h2{font-size:26px;margin:0 0 20px;}
    .taf-cta-btns{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;}
    .taf-btn-gold,.taf-btn-dark{display:inline-block;padding:14px 30px;border-radius:10px;font-weight:800;font-size:14.5px;text-decoration:none;transition:background .2s,transform .2s;}
    .taf-btn-gold{background:#c9a84c;color:#fff;}
    .taf-btn-gold:hover{background:#a8872e;transform:translateY(-1px);}
    .taf-btn-dark{background:#1a1a1a;color:#fff;}
    .taf-btn-dark:hover{background:#333;transform:translateY(-1px);}
    .taf-contact{margin-top:22px;font-size:13.5px;color:#666;}
    .taf-contact a{color:#a8872e;text-decoration:none;}
    @media(max-width:900px){
      .taf-hero h1{font-size:38px;}
      .taf-grid{grid-template-columns:1fr 1fr;}
      .taf-stats{grid-template-columns:repeat(2,1fr);}
      .taf-list{grid-template-columns:1fr;}
    }
    @media(max-width:540px){
      .taf-hero{padding:44px 20px;}
      .taf-hero h1{font-size:30px;}
      .taf-grid{grid-template-columns:1fr;}
    }
    </style>
    <?php
}, 100);

// ─────────────────────────────────────────────────────────────
// PHASE 19 — Fix header email/phone links (they pointed to About).
// Email -> mailto:, Phone -> tel:. Class-agnostic (finds by text).
// ─────────────────────────────────────────────────────────────
add_action('wp_footer', function() {
    ?>
    <script>
    (function(){
      var EMAIL = 'theartframer136@gmail.com';
      var TEL   = '+16104707280';
      function fix(){
        document.querySelectorAll('a').forEach(function(a){
          var t = (a.textContent || '').replace(/\s+/g,' ').trim();
          if (!t) return;
          if (t.toLowerCase().indexOf(EMAIL) > -1) {
            if (a.getAttribute('href') !== 'mailto:' + EMAIL) a.setAttribute('href', 'mailto:' + EMAIL);
            a.removeAttribute('target');
          } else if (/470[\s\-]?7280/.test(t)) {
            if (a.getAttribute('href') !== 'tel:' + TEL) a.setAttribute('href', 'tel:' + TEL);
            a.removeAttribute('target');
          }
        });
      }
      if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fix); else fix();
      window.addEventListener('load', function(){ fix(); setTimeout(fix,500); setTimeout(fix,1500); });
    })();
    </script>
    <?php
}, 102);

// ─────────────────────────────────────────────────────────────
// PHASE 20 — Fixes for issues reported in the site review PDF.
// All additive & scoped; existing section styles/logic untouched.
// ─────────────────────────────────────────────────────────────

// 20a. Related products: drop any product with no real featured image so the
//      blank "Posters" card (and similar) never appears in the row.
add_filter('woocommerce_related_products', function($related) {
    if (empty($related) || !is_array($related)) return $related;
    $out = array();
    foreach ($related as $pid) {
        if (!has_post_thumbnail($pid)) continue;            // no image set at all
        $tid = get_post_thumbnail_id($pid);
        $file = $tid ? get_attached_file($tid) : '';
        if ($file && !@file_exists($file)) continue;        // image record exists but file missing
        $out[] = $pid;
    }
    return $out ?: $related; // never return empty (WooCommerce would query fresh)
}, 20);

// 20b. Cart page: auto-recalculate totals when the quantity changes (theme
//      +/- buttons and manual edits), so the "Cart totals" box stays in sync
//      without the shopper having to click "Update cart".
add_action('wp_footer', function() {
    if (!function_exists('is_cart') || !is_cart()) return;
    ?>
    <script>
    (function(){
      if (typeof jQuery === 'undefined') return;
      var $ = jQuery, timer = null;
      function triggerUpdate(){
        var $btn = $('.woocommerce-cart-form [name="update_cart"], .woocommerce [name="update_cart"]');
        if (!$btn.length) return;
        $btn.prop('disabled', false).trigger('click');
      }
      function schedule(){
        clearTimeout(timer);
        timer = setTimeout(triggerUpdate, 700); // debounce rapid +/- clicks
      }
      // Manual edits / native change
      $(document.body).on('change', '.woocommerce-cart-form input.qty', schedule);
      // Theme +/- stepper buttons change the value programmatically — poll after click
      $(document.body).on('click', '.woocommerce-cart-form .quantity button, .woocommerce-cart-form .quantity .plus, .woocommerce-cart-form .quantity .minus, .woocommerce-cart-form .quantity a', function(){
        schedule();
      });
    })();
    </script>
    <?php
}, 103);

// 20c. (Removed) Cart page CSS overrides — reverted to the theme's native cart
//      styling per request. Only the quantity auto-update behaviour (20b) and
//      the theme's own layout remain.

// 20d. Front page: make banner/landing carousel arrows work and give the
//      "Explore Now" popup button a destination if its link is empty.
add_action('wp_footer', function() {
    if (!function_exists('is_front_page') || !is_front_page()) return;
    ?>
    <script>
    (function(){
      function findSwiper(arrow){
        var node = arrow;
        for (var i=0; i<8 && node; i++){ if (node.swiper) return node.swiper; node = node.parentElement; }
        var cont = arrow.closest('.elementor-widget-slides, .elementor-widget-media-carousel, .swiper, .swiper-container, .elementor-main-swiper');
        if (cont){
          var sw = cont.querySelector('.swiper, .swiper-container');
          if (sw && sw.swiper) return sw.swiper;
          if (cont.swiper) return cont.swiper;
        }
        return null;
      }
      function bindArrows(){
        document.querySelectorAll('.elementor-swiper-button-next, .swiper-button-next, .elementor-swiper-button-prev, .swiper-button-prev').forEach(function(a){
          if (a.getAttribute('data-af-bound')) return;
          a.setAttribute('data-af-bound','1');
          a.addEventListener('click', function(){
            var sw = findSwiper(a);
            if (!sw) return;
            if (a.className.indexOf('prev') > -1) sw.slidePrev(); else sw.slideNext();
          });
        });
      }
      function fixExploreBtn(){
        var links = document.querySelectorAll('a.elementor-button, .elementor-popup-modal a, a');
        links.forEach(function(a){
          var t = (a.textContent||'').replace(/\s+/g,' ').trim().toLowerCase();
          if (t !== 'explore now') return;
          var href = a.getAttribute('href');
          if (!href || href === '#' || href === '') a.setAttribute('href', '/shop/');
        });
      }
      function run(){ bindArrows(); fixExploreBtn(); }
      if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run); else run();
      window.addEventListener('load', function(){ run(); setTimeout(run, 800); setTimeout(run, 2000); });
    })();
    </script>
    <?php
}, 104);

// ─────────────────────────────────────────────────────────────
// PHASE 21 — Homepage product-card hover swap fix.
// The theme swaps to the product's gallery image on hover; on the
// homepage grids the second image rendered in normal flow (sliver
// below the card). Overlay it on the first image with a fade instead.
// Scoped to the front page only.
// ─────────────────────────────────────────────────────────────
add_action('wp_head', function() {
    if (!is_front_page()) return;
    ?>
    <style>
    body.home ul.products li.product .woocommerce-loop-product__link,
    body.af-front-page ul.products li.product .woocommerce-loop-product__link{
      position:relative;display:block;overflow:hidden;
    }
    body.home ul.products li.product .woocommerce-loop-product__link > img,
    body.af-front-page ul.products li.product .woocommerce-loop-product__link > img{
      width:100%;display:block;
    }
    body.home ul.products li.product .woocommerce-loop-product__link > img + img,
    body.home ul.products li.product .woocommerce-loop-product__link .secondary-image,
    body.af-front-page ul.products li.product .woocommerce-loop-product__link > img + img,
    body.af-front-page ul.products li.product .woocommerce-loop-product__link .secondary-image{
      position:absolute;top:0;left:0;width:100%;height:100%;
      object-fit:cover;opacity:0;transition:opacity .35s ease;z-index:2;
    }
    body.home ul.products li.product:hover .woocommerce-loop-product__link > img + img,
    body.home ul.products li.product:hover .woocommerce-loop-product__link .secondary-image,
    body.af-front-page ul.products li.product:hover .woocommerce-loop-product__link > img + img,
    body.af-front-page ul.products li.product:hover .woocommerce-loop-product__link .secondary-image{
      opacity:1;
    }
    </style>
    <?php
}, 30);

// ─────────────────────────────────────────────────────────────
// PHASE 22 — Quick View modal: keep it COMPACT (buy box only).
// The modal renders the entire product page, but the theme's card
// CSS doesn't load inside it, so every post-summary card section
// (Additional info, Reviews, Related Searches, Related products,
// Popular, Recently Viewed, Digital Downloads, Customize, FAQ)
// renders broken (grey gaps / missing images). A Quick View should
// be short anyway — hide those sections in the modal; they remain on
// the full product page (one click via the title / "View details").
// STRICTLY scoped to the modal containers — no effect elsewhere.
// ─────────────────────────────────────────────────────────────
add_action('wp_head', function() {
    ?>
    <style>
    .quick-view-modal .woocommerce-tabs,
    .quick-view-modal .woocommerce-Tabs-panel,
    .quick-view-modal .related.products,
    .quick-view-modal .up-sells,
    .quick-view-modal .woocommerce-product-attributes,
    .quick-view-modal .af-pp-sec,
    .quick-view-modal .cross-sells,
    .quick-view-wrapper .woocommerce-tabs,
    .quick-view-wrapper .related.products,
    .quick-view-wrapper .up-sells,
    .quick-view-wrapper .af-pp-sec,
    .quick-view-wrapper .cross-sells,
    #quickViewContent .woocommerce-tabs,
    #quickViewContent .related.products,
    #quickViewContent .up-sells,
    #quickViewContent .woocommerce-product-attributes,
    #quickViewContent .af-pp-sec,
    #quickViewContent .cross-sells{
      display:none !important;
    }
    </style>
    <?php
}, 31);

// ─────────────────────────────────────────────────────────────
// PHASE 23 — "Price on request" for products with no price set.
// The frame / accessory products (Art Accessories category) have no
// price, so they displayed as $0.00. Until real prices are entered
// in wp-admin, show "Price on request" and make them non-purchasable
// (no Add to Cart / Buy Now at $0). Fully automatic & reversible:
// the moment a real price is saved, the product behaves normally.
// ─────────────────────────────────────────────────────────────

// A zero / empty price → not purchasable (removes Add to Cart + Buy Now)
add_filter('woocommerce_is_purchasable', function($purchasable, $product){
    if (!$product) return $purchasable;
    $p = $product->get_price();
    if ($p === '' || $p === null || (float)$p <= 0) return false;
    return $purchasable;
}, 20, 2);

// Show "Price on request" instead of $0.00 anywhere the price renders
add_filter('woocommerce_get_price_html', function($html, $product){
    if (!$product) return $html;
    $p = $product->get_price();
    if ($p === '' || $p === null || (float)$p <= 0) {
        return '<span class="af-por">Price on request</span>';
    }
    return $html;
}, 20, 2);

// Loop cards: swap the (now absent) Add to Cart for an "Enquire" link
add_filter('woocommerce_loop_add_to_cart_link', function($html, $product){
    if (!$product) return $html;
    $p = $product->get_price();
    if ($p === '' || $p === null || (float)$p <= 0) {
        return '<a href="'.esc_url(home_url('/contact/')).'" class="button af-por-btn">Enquire</a>';
    }
    return $html;
}, 20, 2);

// Single product page: add an "Enquire for price" button where Add to Cart would be
add_action('woocommerce_single_product_summary', function(){
    global $product;
    if (!$product) return;
    $p = $product->get_price();
    if ($p === '' || $p === null || (float)$p <= 0) {
        echo '<a href="'.esc_url(home_url('/contact/')).'" class="button af-por-btn af-por-single">Enquire for Price</a>';
    }
}, 31);

// Minimal styling for the label + enquire buttons
add_action('wp_head', function(){
    ?>
    <style>
    .af-por{font-size:15px;font-weight:700;color:#a8872e;}
    .af-por-btn{display:inline-block;}
    .af-por-single{background:#1a1a1a !important;color:#fff !important;padding:12px 22px !important;border-radius:8px !important;margin:6px 0 0 !important;}
    .af-por-single:hover{background:#c9a84c !important;}
    </style>
    <?php
}, 32);

/* ============================================================
   PHASE 12 — Spec completions (added 2026-07-15)
   12b. Clearance countdown timer (spec §11)
   12c. Newsletter subscribe endpoint (kept for future form use)
   (12a Shop Through Videos and 12d footer strip removed 2026-07-15 —
    Products In Motion already covers video commerce, and footer links
    now live in the Elementor footer itself via tools/fix-footer-links.php)
   All blocks are additive: existing sections are never modified.
   ============================================================ */

// ── 12b. Clearance countdown timer ───────────────────────────
// Attaches a live countdown under the "Stock Clearance Sale" heading.
// End time: option 'af_clearance_end' ("YYYY-MM-DD HH:MM", site timezone) if
// set and in the future; otherwise rolls to next Sunday 23:59:59 each week.
add_action('wp_footer', function() {
    if (!is_front_page()) return;

    $tz  = wp_timezone();
    $now = new DateTime('now', $tz);
    $end = null;
    $opt = trim((string) get_option('af_clearance_end', ''));
    if ($opt !== '') {
        $try = date_create($opt, $tz);
        if ($try && $try > $now) $end = $try;
    }
    if (!$end) {
        $end = new DateTime('now', $tz);
        $end->modify('sunday this week')->setTime(23, 59, 59);
        if ($end <= $now) $end->modify('+7 days');
    }
    $end_ms = $end->getTimestamp() * 1000;
    ?>
<style>
.af-cd-bar{display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin:10px 0 6px;}
.af-cd-label{font-size:14px;font-weight:700;color:#b4342a;letter-spacing:.02em;}
.af-cd-units{display:flex;gap:8px;}
.af-cd-unit{min-width:56px;background:#1a1a1a;color:#fff;border-radius:10px;padding:7px 8px 6px;text-align:center;border:2px solid #c9a84c;}
.af-cd-num{display:block;font-size:20px;font-weight:800;font-variant-numeric:tabular-nums;line-height:1.1;}
.af-cd-word{display:block;font-size:9.5px;text-transform:uppercase;letter-spacing:.09em;color:#c9a84c;margin-top:2px;}
.af-cd-note{font-size:12.5px;color:#8a6d1f;font-weight:600;}
@media(max-width:480px){.af-cd-unit{min-width:48px;padding:6px 6px 5px;}.af-cd-num{font-size:17px;}}
</style>
<div class="af-cd-bar" id="afCdBar" style="display:none;" data-end="<?php echo esc_attr($end_ms); ?>">
  <span class="af-cd-label">&#9889; Offers end in</span>
  <span class="af-cd-units">
    <span class="af-cd-unit"><span class="af-cd-num" data-u="d">0</span><span class="af-cd-word">Days</span></span>
    <span class="af-cd-unit"><span class="af-cd-num" data-u="h">0</span><span class="af-cd-word">Hours</span></span>
    <span class="af-cd-unit"><span class="af-cd-num" data-u="m">0</span><span class="af-cd-word">Mins</span></span>
    <span class="af-cd-unit"><span class="af-cd-num" data-u="s">0</span><span class="af-cd-word">Secs</span></span>
  </span>
  <span class="af-cd-note">Limited stock &mdash; while supplies last</span>
</div>
<script>
(function(){
  var bar = document.getElementById('afCdBar');
  if (!bar) return;
  var end = parseInt(bar.dataset.end, 10);

  function place(){
    if (bar.dataset.placed) return;
    var heading = null;
    document.querySelectorAll('h1,h2,h3,h4,.elementor-heading-title').forEach(function(h){
      if (!heading && /stock\s*clearance/i.test(h.textContent)) heading = h;
    });
    if (!heading) return;
    bar.dataset.placed = '1';
    var w = heading.closest('.elementor-widget') || heading;
    w.parentElement.insertBefore(bar, w.nextSibling);
    bar.style.display = 'flex';
  }

  function pad(n){ return n < 10 ? '0' + n : '' + n; }
  function tick(){
    var left = end - Date.now();
    if (left <= 0) { end += 7 * 86400000; left = end - Date.now(); } // roll weekly
    var d = Math.floor(left / 86400000),
        h = Math.floor(left % 86400000 / 3600000),
        m = Math.floor(left % 3600000 / 60000),
        s = Math.floor(left % 60000 / 1000);
    bar.querySelector('[data-u="d"]').textContent = d;
    bar.querySelector('[data-u="h"]').textContent = pad(h);
    bar.querySelector('[data-u="m"]').textContent = pad(m);
    bar.querySelector('[data-u="s"]').textContent = pad(s);
  }
  setInterval(tick, 1000); tick();
  document.addEventListener('DOMContentLoaded', place);
  window.addEventListener('load', place);
  setTimeout(place, 600); setTimeout(place, 1600);
})();
</script>
<?php }, 10004);

// ── 12c. Newsletter subscribe endpoint ───────────────────────
// Stores subscribers in the 'af_newsletter_subscribers' option and notifies
// the admin. Swap for Klaviyo/Mailchimp later without touching the form.
function af_nl_subscribe_handler() {
    check_ajax_referer('af_nl_subscribe', 'nonce');
    // Rate limit: 10 attempts/hour per IP — each new subscriber triggers an
    // admin wp_mail and grows the af_newsletter_subscribers option.
    $af_nl_ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '';
    $af_nl_rl = 'af_nl_rl_' . md5($af_nl_ip);
    $af_nl_n  = (int) get_transient($af_nl_rl);
    if ($af_nl_n >= 10) {
        wp_send_json_error(array('message' => 'Too many attempts — please try again later.'));
    }
    set_transient($af_nl_rl, $af_nl_n + 1, HOUR_IN_SECONDS);
    if (!empty($_POST['af_nl_hp'])) { // honeypot
        wp_send_json_success(array('message' => 'Thanks — you are subscribed!'));
    }
    $email = isset($_POST['af_nl_email']) ? sanitize_email(wp_unslash($_POST['af_nl_email'])) : '';
    if (!$email || !is_email($email)) {
        wp_send_json_error(array('message' => 'Please enter a valid email address.'));
    }
    $subs = get_option('af_newsletter_subscribers', array());
    if (!is_array($subs)) $subs = array();
    foreach ($subs as $s) {
        if (isset($s['email']) && strtolower($s['email']) === strtolower($email)) {
            wp_send_json_success(array('message' => 'You are already on the list — thank you!'));
        }
    }
    $subs[] = array('email' => $email, 'time' => current_time('mysql'), 'ip' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '');
    if (count($subs) > 5000) $subs = array_slice($subs, -5000);
    update_option('af_newsletter_subscribers', $subs, false);
    wp_mail(get_option('admin_email'), '[The Art Framer] New newsletter subscriber', "New subscriber: {$email}\nTotal subscribers: " . count($subs));
    wp_send_json_success(array('message' => 'Thanks — you are subscribed!'));
}
add_action('wp_ajax_af_nl_subscribe',        'af_nl_subscribe_handler');
add_action('wp_ajax_nopriv_af_nl_subscribe', 'af_nl_subscribe_handler');

// ── 12e. [af_faqs] shortcode — renders the SAME FAQ list used on
// product detail pages (af_product_faqs), so /faqs/ never drifts.
add_shortcode('af_faqs', function() {
    if (!function_exists('af_product_faqs')) return '';
    $faqs = af_product_faqs();
    $out  = '<div class="taf-faq">';
    $ld   = array();
    foreach ($faqs as $i => $qa) {
        $q = esc_html($qa[0]);
        $a = esc_html($qa[1]);
        $out .= '<details' . ($i === 0 ? ' open' : '') . '><summary>' . $q . '</summary><div class="taf-faq-a"><p>' . $a . '</p></div></details>';
        $ld[] = array(
            '@type' => 'Question', 'name' => $qa[0],
            'acceptedAnswer' => array('@type' => 'Answer', 'text' => $qa[1]),
        );
    }
    $out .= '</div>';
    $out .= '<script type="application/ld+json">' . wp_json_encode(array(
        '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $ld,
    )) . '</script>';
    return $out;
});

// Front-end submit handler for the footer form
add_action('wp_footer', function() { ?>
<script>
(function(){
  document.querySelectorAll('form.af-f-newsform[data-ajax]').forEach(function(form){
    form.addEventListener('submit', function(e){
      e.preventDefault();
      var btn = form.querySelector('button'),
          msg = form.parentElement.querySelector('.af-f-newsmsg'),
          fd  = new FormData(form);
      fd.append('action', 'af_nl_subscribe');
      fd.append('nonce', form.dataset.nonce);
      if (btn) { btn.disabled = true; btn.textContent = '…'; }
      fetch(form.dataset.ajax, { method: 'POST', credentials: 'same-origin', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(res){
          if (msg) {
            msg.style.display = 'block';
            msg.style.color = res.success ? '#7ec98f' : '#e08e88';
            msg.textContent = (res.data && res.data.message) ? res.data.message : 'Something went wrong — please try again.';
          }
          if (res.success) form.reset();
        })
        .catch(function(){
          if (msg) { msg.style.display='block'; msg.style.color='#e08e88'; msg.textContent='Network error — please try again.'; }
        })
        .finally(function(){ if (btn) { btn.disabled = false; btn.textContent = 'Join'; } });
    });
  });
})();
</script>
<?php }, 301);



// ── 12f. Contact form — custom table, AJAX submit, admin viewer ─
// [af_contact_form] renders the form; submissions are stored in the
// {prefix}af_contact_messages table and listed under wp-admin →
// "Contact Messages". Admin also gets an email notification.

function af_contact_table() {
    global $wpdb;
    return $wpdb->prefix . 'af_contact_messages';
}

// Create/upgrade the table once (guarded by a schema-version option)
add_action('init', function() {
    if (get_option('af_contact_db_ver') === '1') return;
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table   = af_contact_table();
    $charset = $wpdb->get_charset_collate();
    dbDelta("CREATE TABLE {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        created_at DATETIME NOT NULL,
        name VARCHAR(120) NOT NULL,
        email VARCHAR(190) NOT NULL,
        phone VARCHAR(40) DEFAULT '',
        subject VARCHAR(120) DEFAULT '',
        message TEXT NOT NULL,
        ip VARCHAR(45) DEFAULT '',
        status VARCHAR(10) DEFAULT 'new',
        PRIMARY KEY  (id),
        KEY created_at (created_at),
        KEY status (status)
    ) {$charset};");
    update_option('af_contact_db_ver', '1');
}, 5);

// Shortcode: the form
add_shortcode('af_contact_form', function() {
    $subjects = array('General Question', 'Custom Order / Personalised Print', 'Bulk & Corporate Order', 'Order Support / Tracking', 'Returns & Refunds', 'Artist / Partnership');
    ob_start(); ?>
<form class="taf-form" id="afContactForm"
      data-ajax="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
      data-nonce="<?php echo esc_attr(wp_create_nonce('af_contact_submit')); ?>">
  <div class="taf-form-row">
    <label>Full Name *<input type="text" name="af_name" required maxlength="120" autocomplete="name"></label>
    <label>Email *<input type="email" name="af_email" required maxlength="190" autocomplete="email"></label>
  </div>
  <div class="taf-form-row">
    <label>Phone (optional)<input type="tel" name="af_phone" maxlength="40" autocomplete="tel"></label>
    <label>Subject *
      <select name="af_subject" required>
        <?php foreach ($subjects as $s) echo '<option>' . esc_html($s) . '</option>'; ?>
      </select>
    </label>
  </div>
  <label>Your Message *<textarea name="af_message" rows="6" required maxlength="5000" placeholder="Tell us about your wall, your order, or your question…"></textarea></label>
  <input type="text" name="af_hp" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;">
  <button type="submit" class="taf-form-submit">Send Message</button>
  <p class="taf-form-msg" role="status" aria-live="polite" style="display:none;"></p>
</form>
<script>
(function(){
  var form = document.getElementById('afContactForm');
  if (!form) return;
  form.addEventListener('submit', function(e){
    e.preventDefault();
    var btn = form.querySelector('.taf-form-submit'),
        msg = form.querySelector('.taf-form-msg'),
        fd  = new FormData(form);
    fd.append('action', 'af_contact_submit');
    fd.append('nonce', form.dataset.nonce);
    btn.disabled = true; btn.textContent = 'Sending…';
    fetch(form.dataset.ajax, { method:'POST', credentials:'same-origin', body: fd })
      .then(function(r){ return r.json(); })
      .then(function(res){
        msg.style.display = 'block';
        msg.className = 'taf-form-msg ' + (res.success ? 'ok' : 'err');
        msg.textContent = (res.data && res.data.message) ? res.data.message : 'Something went wrong — please try again.';
        if (res.success) form.reset();
      })
      .catch(function(){
        msg.style.display = 'block';
        msg.className = 'taf-form-msg err';
        msg.textContent = 'Network error — please try again, or email us directly.';
      })
      .finally(function(){ btn.disabled = false; btn.textContent = 'Send Message'; });
  });
})();
</script>
<?php return ob_get_clean();
});

// AJAX endpoint
function af_contact_submit_handler() {
    check_ajax_referer('af_contact_submit', 'nonce');
    if (!empty($_POST['af_hp'])) { // honeypot: pretend success
        wp_send_json_success(array('message' => 'Thank you! Your message has been sent.'));
    }
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '';
    // Rate limit: 5 messages per hour per IP
    $rl_key = 'af_ct_rl_' . md5($ip);
    $count  = (int) get_transient($rl_key);
    if ($count >= 5) {
        wp_send_json_error(array('message' => 'Too many messages from this connection — please try again in an hour, or email us directly.'));
    }

    $name    = isset($_POST['af_name'])    ? sanitize_text_field(wp_unslash($_POST['af_name']))    : '';
    $email   = isset($_POST['af_email'])   ? sanitize_email(wp_unslash($_POST['af_email']))        : '';
    $phone   = isset($_POST['af_phone'])   ? sanitize_text_field(wp_unslash($_POST['af_phone']))   : '';
    $subject = isset($_POST['af_subject']) ? sanitize_text_field(wp_unslash($_POST['af_subject'])) : '';
    $message = isset($_POST['af_message']) ? sanitize_textarea_field(wp_unslash($_POST['af_message'])) : '';

    if (mb_strlen($name) < 2)          wp_send_json_error(array('message' => 'Please enter your name.'));
    if (!$email || !is_email($email))  wp_send_json_error(array('message' => 'Please enter a valid email address.'));
    if (mb_strlen($message) < 10)      wp_send_json_error(array('message' => 'Please write a few words about your request.'));

    global $wpdb;
    $ok = $wpdb->insert(af_contact_table(), array(
        'created_at' => current_time('mysql'),
        'name'       => mb_substr($name, 0, 120),
        'email'      => mb_substr($email, 0, 190),
        'phone'      => mb_substr($phone, 0, 40),
        'subject'    => mb_substr($subject, 0, 120),
        'message'    => mb_substr($message, 0, 5000),
        'ip'         => $ip,
        'status'     => 'new',
    ), array('%s','%s','%s','%s','%s','%s','%s','%s'));

    if (!$ok) {
        wp_send_json_error(array('message' => 'Could not save your message — please email us directly at theartframer136@gmail.com.'));
    }
    set_transient($rl_key, $count + 1, HOUR_IN_SECONDS);

    wp_mail(
        get_option('admin_email'),
        '[The Art Framer] New contact message: ' . $subject,
        "From: {$name} <{$email}>" . ($phone ? " / {$phone}" : '') . "\nSubject: {$subject}\n\n{$message}\n\n— Saved in wp-admin → Contact Messages",
        array('Reply-To: ' . $name . ' <' . $email . '>')
    );
    wp_send_json_success(array('message' => 'Thank you! Your message has been sent — we usually reply within 24 hours.'));
}
add_action('wp_ajax_af_contact_submit',        'af_contact_submit_handler');
add_action('wp_ajax_nopriv_af_contact_submit', 'af_contact_submit_handler');

// Admin viewer: wp-admin → Contact Messages
add_action('admin_menu', function() {
    global $wpdb;
    $table  = af_contact_table();
    $unread = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status='new'");
    $badge  = $unread ? " <span class='awaiting-mod'>{$unread}</span>" : '';
    $cap = current_user_can('af_view_contact_messages') ? 'af_view_contact_messages' : 'manage_options';
    add_menu_page('Contact Messages', 'Contact Messages' . $badge, $cap,
        'af-contact-messages', 'af_contact_admin_page', 'dashicons-email-alt2', 26);
});

function af_contact_admin_page() {
    if (!current_user_can('manage_options') && !current_user_can('af_view_contact_messages')) return;
    global $wpdb;
    $table = af_contact_table();

    // Row actions (mark read / delete), nonce-protected
    if (isset($_GET['af_action'], $_GET['id'], $_GET['_wpnonce'])) {
        $id = (int) $_GET['id'];
        if (wp_verify_nonce($_GET['_wpnonce'], 'af_ct_' . $id)) {
            if ($_GET['af_action'] === 'read')   $wpdb->update($table, array('status' => 'read'), array('id' => $id));
            if ($_GET['af_action'] === 'unread') $wpdb->update($table, array('status' => 'new'),  array('id' => $id));
            if ($_GET['af_action'] === 'delete') $wpdb->delete($table, array('id' => $id));
        }
        echo '<script>location.replace("' . esc_url_raw(admin_url('admin.php?page=af-contact-messages')) . '");</script>';
        return;
    }

    $per  = 20;
    $pg   = max(1, (int) ($_GET['paged'] ?? 1));
    $tot  = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $per, ($pg - 1) * $per
    ));
    echo '<div class="wrap"><h1>Contact Messages</h1>';
    echo '<p>' . esc_html($tot) . ' total. Submissions from the <a href="' . esc_url(home_url('/contact/')) . '" target="_blank">contact form</a> are stored here and emailed to ' . esc_html(get_option('admin_email')) . '.</p>';
    if (!$rows) { echo '<p><em>No messages yet.</em></p></div>'; return; }
    echo '<table class="widefat striped"><thead><tr><th>Date</th><th>Name</th><th>Email</th><th>Phone</th><th>Subject</th><th style="width:34%">Message</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        $n1 = wp_create_nonce('af_ct_' . $r->id);
        $base = admin_url('admin.php?page=af-contact-messages&id=' . $r->id . '&_wpnonce=' . $n1);
        $bold = $r->status === 'new' ? ' style="font-weight:700;"' : '';
        echo '<tr' . $bold . '>';
        echo '<td>' . esc_html(mysql2date('M j, Y g:i a', $r->created_at)) . '</td>';
        echo '<td>' . esc_html($r->name) . '</td>';
        echo '<td><a href="mailto:' . esc_attr($r->email) . '">' . esc_html($r->email) . '</a></td>';
        echo '<td>' . esc_html($r->phone) . '</td>';
        echo '<td>' . esc_html($r->subject) . '</td>';
        echo '<td>' . esc_html(mb_strimwidth($r->message, 0, 220, '…')) . '</td>';
        echo '<td>' . esc_html($r->status) . '</td>';
        echo '<td>';
        echo $r->status === 'new'
            ? '<a href="' . esc_url($base . '&af_action=read') . '">Mark read</a>'
            : '<a href="' . esc_url($base . '&af_action=unread') . '">Mark unread</a>';
        echo ' | <a href="' . esc_url($base . '&af_action=delete') . '" onclick="return confirm(\'Delete this message?\');" style="color:#b32d2e;">Delete</a>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:8px;display:flex;flex-direction:column;gap:5px;min-width:200px;">';
        echo '<input type="hidden" name="action" value="af_msg_reply">';
        echo '<input type="hidden" name="parent_id" value="' . (int) $r->id . '">';
        wp_nonce_field('af_msg_reply');
        echo '<textarea name="reply" rows="2" placeholder="Reply (emails the customer)"></textarea>';
        echo '<button class="button button-primary">Send reply</button>';
        echo '</form>';
        echo '</td></tr>';
    }
    echo '</tbody></table>';
    $pages = (int) ceil($tot / $per);
    if ($pages > 1) {
        echo '<p>';
        for ($i = 1; $i <= $pages; $i++) {
            echo $i === $pg ? "<strong style='margin-right:8px;'>{$i}</strong>"
                : '<a style="margin-right:8px;" href="' . esc_url(admin_url('admin.php?page=af-contact-messages&paged=' . $i)) . '">' . $i . '</a>';
        }
        echo '</p>';
    }
    echo '</div>';
}

// ── 12g. [af_delivery_checker] — ZIP-based delivery estimate ──
// Interactive widget for the Shipping & Delivery page: tells the visitor
// whether their ZIP gets free delivery and computes an estimated arrival
// window (production 3–5 business days + delivery 5–10 business days).
add_shortcode('af_delivery_checker', function() {
    ob_start(); ?>
<div class="taf-shipcheck" id="afShipCheck">
  <div class="taf-shipcheck-head">
    <span class="taf-ico">📮</span>
    <div>
      <h3>Check Your Delivery</h3>
      <p>Enter your ZIP code — see your shipping cost and estimated arrival dates instantly.</p>
    </div>
  </div>
  <div class="taf-shipcheck-row">
    <input type="text" id="afZip" inputmode="numeric" maxlength="10" placeholder="e.g. 19801" aria-label="ZIP code">
    <button type="button" id="afZipBtn">Check Delivery</button>
  </div>
  <div class="taf-shipcheck-result" id="afZipResult" role="status" aria-live="polite" style="display:none;"></div>
</div>
<script>
(function(){
  var input = document.getElementById('afZip'),
      btn   = document.getElementById('afZipBtn'),
      out   = document.getElementById('afZipResult');
  if (!input) return;

  // Free-delivery states by ZIP3 prefix: DE 197-199, PA 150-196, MD 206-219, NJ 070-089
  function zone(z3){
    if (z3 >= 197 && z3 <= 199) return 'Delaware';
    if (z3 >= 150 && z3 <= 196) return 'Pennsylvania';
    if (z3 >= 206 && z3 <= 219) return 'Maryland';
    if (z3 >=  70 && z3 <=  89) return 'New Jersey';
    return null;
  }
  function addBiz(date, days){
    var d = new Date(date);
    while (days > 0) { d.setDate(d.getDate() + 1); var w = d.getDay(); if (w !== 0 && w !== 6) days--; }
    return d;
  }
  function fmt(d){ return d.toLocaleDateString('en-US', { month:'short', day:'numeric' }); }

  function check(){
    var raw = (input.value || '').trim(),
        m   = raw.match(/^(\d{5})(?:-\d{4})?$/);
    out.style.display = 'block';
    if (!m) {
      out.className = 'taf-shipcheck-result err';
      out.innerHTML = 'Please enter a valid 5-digit US ZIP code. Outside the US? <a href="/contact/">Contact us</a> for an international quote.';
      return;
    }
    var z3    = parseInt(m[1].substring(0,3), 10),
        st    = zone(z3),
        today = new Date(),
        early = addBiz(addBiz(today, 3), 5),   // fastest: 3d production + 5d transit
        late  = addBiz(addBiz(today, 5), 10);  // slowest: 5d production + 10d transit
    if (st) {
      out.className = 'taf-shipcheck-result ok';
      out.innerHTML = '<b>🎁 Free delivery to ' + m[1] + ' (' + st + ')!</b><br>' +
        'Order today and your artwork should arrive between <b>' + fmt(early) + '</b> and <b>' + fmt(late) + '</b>.';
    } else {
      out.className = 'taf-shipcheck-result mid';
      out.innerHTML = '<b>🚚 We deliver to ' + m[1] + '.</b> Shipping is calculated at checkout based on artwork size.<br>' +
        'Estimated arrival between <b>' + fmt(early) + '</b> and <b>' + fmt(late) + '</b>.';
    }
  }
  btn.addEventListener('click', check);
  input.addEventListener('keydown', function(e){ if (e.key === 'Enter') { e.preventDefault(); check(); } });
})();
</script>
<?php return ob_get_clean();
});

// ── 12h. [af_artists] — dynamic artist cards from the admin panel ─
// Renders one card per child category of "Direct from Artists"
// (Products → Categories in wp-admin). Add a new artist category there
// and it appears on /artists/ automatically: name, description, category
// thumbnail, product count, links to the profile page (if one exists at
// /artists/<slug>/) and the shop archive.
add_shortcode('af_artists', function() {
    $parent = get_term_by('slug', 'direct-from-artists', 'product_cat');
    if (!$parent) return '<p>No artists found yet — check back soon.</p>';
    $terms = get_terms(array(
        'taxonomy'   => 'product_cat',
        'parent'     => $parent->term_id,
        'hide_empty' => false,
        'orderby'    => 'name',
    ));
    if (is_wp_error($terms) || empty($terms)) return '<p>No artists found yet — check back soon.</p>';

    $fallback_img = 'https://theartframer.us/wp-content/uploads/2026/04/works-of-artist-69da11c3d9a55.webp';
    $out = '<div class="taf-grid taf-artists">';
    foreach ($terms as $t) {
        $thumb_id = get_term_meta($t->term_id, 'thumbnail_id', true);
        $img      = $thumb_id ? wp_get_attachment_image_url($thumb_id, 'large') : '';
        if (!$img) $img = $fallback_img;
        $desc = trim($t->description);
        if ($desc === '') $desc = 'Original artworks by ' . $t->name . ', printed and framed by The Art Framer.';
        $archive = get_term_link($t);
        if (is_wp_error($archive)) $archive = '/product-category/direct-from-artists/';
        $profile = get_page_by_path('artists/' . $t->slug);
        $count   = (int) $t->count;

        $out .= '<div class="taf-card taf-artist-card">';
        $out .= '<img class="taf-artist-img" loading="lazy" src="' . esc_url($img) . '" alt="' . esc_attr('Artwork by ' . $t->name) . '">';
        $out .= '<h3>' . esc_html($t->name) . '</h3>';
        if ($count) $out .= '<span class="taf-badge">' . $count . ' artwork' . ($count === 1 ? '' : 's') . '</span>';
        $out .= '<p>' . esc_html(wp_trim_words($desc, 28, '…')) . '</p>';
        $out .= '<p class="taf-artist-actions">';
        if ($profile && $profile->post_status === 'publish') {
            $out .= '<a class="taf-btn" href="' . esc_url(get_permalink($profile)) . '">View Profile</a> ';
        }
        $out .= '<a class="taf-btn-alt" href="' . esc_url($archive) . '">Shop Artworks</a>';
        $out .= '</p></div>';
    }
    $out .= '</div>';
    return $out;
});

// ── 12i. [af_dashboard] — dynamic customer dashboard ─────────
// Replaces the dead [dokan-dashboard] shortcode on /dashboard/.
// Logged in: greeting, live order/download counts, recent orders,
// quick links to My Account endpoints. Logged out: login/register CTAs.
add_shortcode('af_dashboard', function() {
    if (!function_exists('wc_get_account_endpoint_url')) return '';
    ob_start();

    if (!is_user_logged_in()) { ?>
<div class="afd afd-guest">
  <div class="afd-welcome">
    <span class="afd-welcome-ico">🖼️</span>
    <span class="afd-eyebrow">Your Account</span>
    <h2>Welcome to Your Dashboard</h2>
    <p>Sign in to see your orders, downloads, and saved details — or create a free account in under a minute.</p>
    <div class="afd-welcome-btns">
      <a class="afd-btn" href="<?php echo esc_url(home_url('/my-account/')); ?>">Sign In</a>
      <a class="afd-btn afd-btn-ghost" href="<?php echo esc_url(home_url('/sign-up/')); ?>">Create Account</a>
    </div>
    <ul class="afd-perks">
      <li>Track orders and deliveries in one place</li>
      <li>Instant access to your digital downloads</li>
      <li>Faster checkout with saved addresses</li>
      <li>Wishlist saved across devices</li>
    </ul>
  </div>
</div>
<?php
        return ob_get_clean();
    }

    $user   = wp_get_current_user();
    $uid    = $user->ID;
    $name   = $user->first_name ? $user->first_name : $user->display_name;
    $initial = strtoupper(mb_substr($name, 0, 1));
    $orders = function_exists('wc_get_customer_order_count') ? wc_get_customer_order_count($uid) : 0;
    $downloads = function_exists('wc_get_customer_available_downloads') ? count(wc_get_customer_available_downloads($uid)) : 0;
    $recent = wc_get_orders(array('customer_id' => $uid, 'limit' => 3, 'orderby' => 'date', 'order' => 'DESC'));
    ?>
<div class="afd">

  <div class="afd-hero">
    <div class="afd-hero-user">
      <div class="afd-avatar"><?php echo esc_html($initial); ?></div>
      <div class="afd-hero-txt">
        <span class="afd-eyebrow">My Dashboard</span>
        <h2>Hi <?php echo esc_html($name); ?> <span class="afd-wave">👋</span></h2>
        <p>Welcome back — here's what's happening with your art.</p>
      </div>
    </div>
    <a class="afd-btn afd-btn-outline" href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>">Log Out</a>
  </div>

  <div class="afd-stats">
    <div class="afd-stat"><span class="afd-stat-ico">📦</span><span class="afd-num"><?php echo (int) $orders; ?></span><span class="afd-lbl">Orders placed</span></div>
    <div class="afd-stat"><span class="afd-stat-ico">💾</span><span class="afd-num"><?php echo (int) $downloads; ?></span><span class="afd-lbl">Digital downloads</span></div>
    <div class="afd-stat"><span class="afd-stat-ico">⭐</span><span class="afd-num"><?php echo esc_html(date_i18n('M Y', strtotime($user->user_registered))); ?></span><span class="afd-lbl">Member since</span></div>
  </div>

  <section class="afd-block">
    <div class="afd-block-head">
      <h3>Recent Orders</h3>
      <?php if ($recent): ?><a class="afd-viewall" href="<?php echo esc_url(wc_get_account_endpoint_url('orders')); ?>">View all →</a><?php endif; ?>
    </div>
    <?php if ($recent) : ?>
    <div class="afd-orders">
      <?php foreach ($recent as $o) :
        $st = $o->get_status(); ?>
      <a class="afd-order" href="<?php echo esc_url($o->get_view_order_url()); ?>">
        <span class="afd-order-id">#<?php echo esc_html($o->get_order_number()); ?></span>
        <span class="afd-order-date"><?php echo esc_html(wc_format_datetime($o->get_date_created(), 'M j, Y')); ?></span>
        <span class="afd-badge afd-badge-<?php echo esc_attr($st); ?>"><?php echo esc_html(wc_get_order_status_name($st)); ?></span>
        <span class="afd-order-total"><?php echo wp_kses_post($o->get_formatted_order_total()); ?></span>
        <span class="afd-order-arrow">→</span>
      </a>
      <?php endforeach; ?>
    </div>
    <?php else : ?>
    <div class="afd-empty">
      <span class="afd-empty-ico">🖼️</span>
      <h4>No orders yet</h4>
      <p>Your walls are waiting — browse the collection or preview art in your own room.</p>
      <div class="afd-empty-btns">
        <a class="afd-btn" href="/shop/">Start Shopping</a>
        <a class="afd-btn afd-btn-ghost" href="/try-on-wall/">Try It on Your Wall</a>
      </div>
    </div>
    <?php endif; ?>
  </section>

  <section class="afd-block">
    <div class="afd-block-head"><h3>Quick Access</h3></div>
    <div class="afd-grid">
      <a class="afd-tile" href="<?php echo esc_url(wc_get_account_endpoint_url('orders')); ?>"><span class="afd-tile-ico">📦</span><span class="afd-tile-txt"><strong>My Orders</strong><span>Order history, invoices &amp; details.</span></span><span class="afd-tile-go">→</span></a>
      <a class="afd-tile" href="<?php echo esc_url(wc_get_account_endpoint_url('downloads')); ?>"><span class="afd-tile-ico">💾</span><span class="afd-tile-txt"><strong>Downloads</strong><span>Your purchased digital files.</span></span><span class="afd-tile-go">→</span></a>
      <a class="afd-tile" href="/track-your-order/"><span class="afd-tile-ico">🚚</span><span class="afd-tile-txt"><strong>Track an Order</strong><span>From studio to your doorstep.</span></span><span class="afd-tile-go">→</span></a>
      <a class="afd-tile" href="<?php echo esc_url(wc_get_account_endpoint_url('edit-address')); ?>"><span class="afd-tile-ico">📍</span><span class="afd-tile-txt"><strong>Addresses</strong><span>Shipping &amp; billing details.</span></span><span class="afd-tile-go">→</span></a>
      <a class="afd-tile" href="/wishlist/"><span class="afd-tile-ico">❤️</span><span class="afd-tile-txt"><strong>Wishlist</strong><span>Pieces saved for later.</span></span><span class="afd-tile-go">→</span></a>
      <a class="afd-tile" href="<?php echo esc_url(wc_get_account_endpoint_url('edit-account')); ?>"><span class="afd-tile-ico">⚙️</span><span class="afd-tile-txt"><strong>Account Details</strong><span>Name, email &amp; password.</span></span><span class="afd-tile-go">→</span></a>
    </div>
  </section>

</div>
<?php
    return ob_get_clean();
});

// ─────────────────────────────────────────────────────────────
// PHASE 24 — SEO hardening for US search (added 2026-07-16).
// 24a force one https:// origin   24b robots.txt (virtual)
// 24c strip invalid hreflang tags 24d (removed per request)
// 24e alt-text fallback on rendered images  24f /about-us 301.
// Persisted meta (titles, descriptions, alts, physical robots.txt)
// is written by tools/seo-improvements.php on deploy.
// Additive; reversible via this block.
// ─────────────────────────────────────────────────────────────

// 24a. http:// serves the full site with a 200 — force a single 301
// to https so search engines see one origin.
add_action('init', function () {
    if (is_ssl()) return;
    if (php_sapi_name() === 'cli' || (defined('WP_CLI') && WP_CLI) || wp_doing_cron()) return;
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') return;
    $host = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : (string) parse_url(home_url('/'), PHP_URL_HOST);
    $uri  = !empty($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
    wp_redirect('https://' . $host . $uri, 301);
    exit;
}, 0);

// 24b. robots.txt content. Served two ways: WP's virtual robots.txt
// (filter below) and a physical file written on deploy, because the
// web server 404s /robots.txt before WordPress sees it.
// The "Disallow: /items/" tourniquet was REMOVED 2026-07-16 once the
// cloaking hack was confirmed cleaned (sitemaps serve real URLs, all
// /items/ 404 to Googlebot). Google must be able to crawl those URLs
// to see the 404s and drop them from the index — do NOT re-add it.
function af_seo_robots_txt() {
    $lines = array(
        '# theartframer-seo',
        'User-agent: *',
        'Disallow: /wp-admin/',
        'Allow: /wp-admin/admin-ajax.php',
        'Disallow: /cart/',
        'Disallow: /checkout/',
        'Disallow: /my-account/',
        'Disallow: /?s=',
        '',
        'Sitemap: https://theartframer.us/sitemap_index.xml',
    );
    return implode("\n", $lines) . "\n";
}
add_filter('robots_txt', function ($output, $public) {
    return af_seo_robots_txt();
}, 99, 2);

// 24c. Something outside the theme emits invalid hreflang alternates
// (relative hrefs, plus a Hindi variant with no real content) that
// dilute US geo-targeting. A single-language US store needs no
// hreflang at all, so strip every hreflang link tag from output.
add_action('template_redirect', function () {
    if (is_admin() || is_feed()) return;
    ob_start(function ($html) {
        if (stripos($html, 'hreflang') === false) return $html;
        return preg_replace('#<link\b[^>]*\bhreflang=[^>]*>\s*#i', '', $html);
    });
}, 0);

// 24d. REMOVED 2026-07-16 per request — the front-page H1 intro strip
// ("Custom Canvas Prints, Wall Art & Picture Framing") was removed at
// the user's request (see the wp_footer hider at the end of this
// file, added by the same request). Leaving the H1 in the HTML while
// JS hides it would be hidden text, so the injection is gone
// entirely. The homepage intentionally has no keyword H1.

// 24e. Most rendered images ship without alt text. Fall back to the
// attachment's parent (product) title, then the attachment title.
add_filter('wp_get_attachment_image_attributes', function ($attr, $attachment) {
    if (!empty($attr['alt']) || !($attachment instanceof WP_Post)) return $attr;
    $alt = (string) get_post_meta($attachment->ID, '_wp_attachment_image_alt', true);
    if ($alt === '' && $attachment->post_parent) $alt = (string) get_the_title($attachment->post_parent);
    if ($alt === '') $alt = trim(preg_replace('/[-_]+/', ' ', (string) $attachment->post_title));
    if ($alt !== '') $attr['alt'] = wp_strip_all_tags($alt);
    return $attr;
}, 20, 2);

// 24f. /about-us is a dead URL that still collects links — 301 it to
// the real About page.
add_action('template_redirect', function () {
    if (!is_404()) return;
    $uri  = !empty($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
    $path = strtolower(trim((string) parse_url($uri, PHP_URL_PATH), '/'));
    if ($path === 'about-us') {
        wp_redirect(home_url('/about/'), 301);
        exit;
    }
}, 1);

// ── 12j. Compare Products (spec §4) ──────────────────────────
// Client-side selection (localStorage, max 4) + floating bar + /compare/
// page that renders a side-by-side table from a JSON endpoint.

// Endpoint: product data for the compare table
function af_compare_data_handler() {
    $raw = isset($_POST['ids']) ? (array) $_POST['ids'] : array();
    $ids = array_slice(array_filter(array_map('absint', $raw)), 0, 4);
    $out = array();
    foreach ($ids as $id) {
        $p = wc_get_product($id);
        if (!$p || $p->get_status() !== 'publish') continue;
        $attr = function($tax) use ($p) {
            $terms = wc_get_product_terms($p->get_id(), $tax, array('fields' => 'names'));
            return $terms && !is_wp_error($terms) ? implode(', ', $terms) : '—';
        };
        $img = wp_get_attachment_image_url($p->get_image_id(), 'woocommerce_thumbnail');
        $out[] = array(
            'id'      => $p->get_id(),
            'title'   => html_entity_decode(wp_strip_all_tags($p->get_name()), ENT_QUOTES),
            'url'     => get_permalink($p->get_id()),
            'img'     => $img ?: wc_placeholder_img_src(),
            'price'   => wp_strip_all_tags($p->get_price_html()),
            'rating'  => $p->get_average_rating() > 0 ? number_format((float)$p->get_average_rating(), 1) . ' ★ (' . $p->get_review_count() . ')' : 'No reviews yet',
            'sizes'   => $attr('pa_size'),
            'frames'  => $attr('pa_frame'),
            'colours' => $attr('pa_colour'),
            'type'    => $p->is_type('variable') ? 'Multiple options' : 'Single option',
            'stock'   => $p->is_in_stock() ? 'In stock' : 'Out of stock',
            'digital' => $p->is_downloadable() ? 'Available' : '—',
        );
    }
    wp_send_json_success($out);
}
add_action('wp_ajax_af_compare_data',        'af_compare_data_handler');
add_action('wp_ajax_nopriv_af_compare_data', 'af_compare_data_handler');

// Compare toggle button on product cards
add_action('woocommerce_after_shop_loop_item', function() {
    global $product;
    if (!$product) return;
    echo '<button type="button" class="af-cmp-btn" data-id="' . esc_attr($product->get_id())
        . '" aria-label="Add to compare">⇄ Compare</button>';
}, 25);

// Compare toggle on single product pages
add_action('woocommerce_after_add_to_cart_button', function() {
    global $product;
    if (!$product) return;
    echo '<button type="button" class="af-cmp-btn af-cmp-single" data-id="' . esc_attr($product->get_id())
        . '">⇄ Add to Compare</button>';
}, 25);

// Shortcode: the compare page table
add_shortcode('af_compare', function() {
    return '<div id="afCompareWrap" data-ajax="' . esc_url(admin_url('admin-ajax.php')) . '">'
         . '<div class="af-cmp-empty" id="afCmpEmpty" style="display:none;text-align:center;padding:30px 10px;">'
         . '<span class="taf-ico" style="font-size:46px;">⇄</span>'
         . '<h3 style="margin:12px 0 8px;">Nothing to compare yet</h3>'
         . '<p>Browse the shop and tap <b>⇄ Compare</b> on up to four artworks.</p>'
         . '<p style="margin-top:14px;"><a class="taf-btn" href="/shop/">Browse Artworks</a></p></div>'
         . '<div id="afCmpTable"></div></div>';
});

// Site-wide compare JS: state, floating bar, page renderer
add_action('wp_footer', function() { ?>
<script>
(function(){
  var KEY = 'af_compare_ids', MAX = 4;
  function ids(){ try { return JSON.parse(localStorage.getItem(KEY)) || []; } catch(e){ return []; } }
  function save(a){ localStorage.setItem(KEY, JSON.stringify(a.slice(0, MAX))); refresh(); }
  function toggle(id){
    var a = ids(), i = a.indexOf(id);
    if (i >= 0) a.splice(i, 1);
    else { if (a.length >= MAX) { alert('You can compare up to ' + MAX + ' artworks — remove one first.'); return; } a.push(id); }
    save(a);
  }
  function refresh(){
    var a = ids();
    document.querySelectorAll('.af-cmp-btn').forEach(function(b){
      b.classList.toggle('on', a.indexOf(parseInt(b.dataset.id, 10)) >= 0);
    });
    var bar = document.getElementById('afCmpBar');
    if (a.length > 0) {
      if (!bar) {
        bar = document.createElement('div');
        bar.id = 'afCmpBar';
        bar.innerHTML = '<span id="afCmpCount"></span>' +
          '<a class="af-cmp-go" href="/compare/">Compare Now →</a>' +
          '<button type="button" class="af-cmp-clear" id="afCmpClear">Clear</button>';
        document.body.appendChild(bar);
        document.getElementById('afCmpClear').addEventListener('click', function(){ save([]); });
      }
      bar.style.display = 'flex';
      document.getElementById('afCmpCount').textContent = a.length + ' of ' + MAX + ' selected';
    } else if (bar) { bar.style.display = 'none'; }
  }
  document.addEventListener('click', function(e){
    var b = e.target.closest('.af-cmp-btn');
    if (!b) return;
    e.preventDefault(); e.stopPropagation();
    toggle(parseInt(b.dataset.id, 10));
  });

  // The Elementor/Postero product grid doesn't fire the WooCommerce loop
  // hooks, so inject compare buttons from the cards' data-product_id.
  function makeBtn(id, cls){
    var b = document.createElement('button');
    b.type = 'button'; b.className = 'af-cmp-btn' + (cls ? ' ' + cls : '');
    b.dataset.id = id; b.textContent = '⇄ Compare';
    return b;
  }
  function inject(){
    document.querySelectorAll('a[data-product_id]').forEach(function(a){
      var card = a.closest('li.product, .type-product, .product');
      if (!card || card.querySelector('.af-cmp-btn')) return;
      var id = parseInt(a.getAttribute('data-product_id'), 10);
      if (!id) return;
      (a.parentElement || card).appendChild(makeBtn(id));
    });
    if (document.body.classList.contains('single-product')) {
      var m = document.body.className.match(/postid-(\d+)/);
      var host = document.querySelector('.summary form.cart, .summary .cart, .summary');
      if (m && host && !host.querySelector('.af-cmp-btn') && !document.querySelector('.af-cmp-single')) {
        host.appendChild(makeBtn(parseInt(m[1], 10), 'af-cmp-single'));
      }
    }
    refresh();
  }
  document.addEventListener('DOMContentLoaded', inject);
  window.addEventListener('load', inject);
  setTimeout(inject, 800); setTimeout(inject, 2000);
  refresh();

  // Compare page renderer
  var wrap = document.getElementById('afCompareWrap');
  if (!wrap) return;
  function esc(t){ var d = document.createElement('div'); d.textContent = t == null ? '' : String(t); return d.innerHTML; }
  function render(){
    var a = ids(), empty = document.getElementById('afCmpEmpty'), tbl = document.getElementById('afCmpTable');
    if (!a.length) { empty.style.display = 'block'; tbl.innerHTML = ''; return; }
    empty.style.display = 'none';
    tbl.innerHTML = '<p style="text-align:center;color:#888;">Loading comparison…</p>';
    var fd = new FormData();
    fd.append('action', 'af_compare_data');
    a.forEach(function(id){ fd.append('ids[]', id); });
    fetch(wrap.dataset.ajax, { method:'POST', credentials:'same-origin', body: fd })
      .then(function(r){ return r.json(); })
      .then(function(res){
        var items = (res && res.data) || [];
        if (!items.length) { empty.style.display = 'block'; tbl.innerHTML = ''; return; }
        var rows = [
          ['Artwork', function(p){ return '<a href="' + esc(p.url) + '"><img src="' + esc(p.img) + '" alt="' + esc(p.title) + '" style="width:100%;max-width:170px;display:block;margin:0 auto 10px;"><b>' + esc(p.title) + '</b></a>'; }],
          ['Price', function(p){ return esc(p.price); }],
          ['Rating', function(p){ return esc(p.rating); }],
          ['Sizes', function(p){ return esc(p.sizes); }],
          ['Frame Types', function(p){ return esc(p.frames); }],
          ['Frame Colours', function(p){ return esc(p.colours); }],
          ['Options', function(p){ return esc(p.type); }],
          ['Digital Download', function(p){ return esc(p.digital); }],
          ['Availability', function(p){ return esc(p.stock); }],
          ['', function(p){ return '<a class="taf-btn" href="' + esc(p.url) + '">View Product</a> <button type="button" class="af-cmp-rm taf-btn-alt" data-id="' + p.id + '">Remove</button>'; }]
        ];
        var h = '<div style="overflow-x:auto;"><table class="taf-table af-cmp-table"><tbody>';
        rows.forEach(function(row){
          h += '<tr><td style="white-space:nowrap;font-weight:700;">' + row[0] + '</td>';
          items.forEach(function(p){ h += '<td style="text-align:center;">' + row[1](p) + '</td>'; });
          h += '</tr>';
        });
        h += '</tbody></table></div>';
        tbl.innerHTML = h;
        tbl.querySelectorAll('.af-cmp-rm').forEach(function(b){
          b.addEventListener('click', function(){ toggle(parseInt(b.dataset.id, 10)); render(); });
        });
      })
      .catch(function(){ tbl.innerHTML = '<p style="text-align:center;color:#a13232;">Could not load the comparison — please refresh.</p>'; });
  }
  render();
})();
</script>
<?php }, 302);

// 24g. BreadcrumbList JSON-LD on single product pages ONLY — every
// other page type (categories, pages, posts) already gets a
// BreadcrumbList from elsewhere; products were the one gap.
add_action('wp_head', function () {
    if (is_admin() || is_front_page()) return;
    if (!function_exists('is_product') || !is_product()) return;
    $crumbs = array(array('Home', home_url('/')));
    $terms = get_the_terms(get_the_ID(), 'product_cat');
    if ($terms && !is_wp_error($terms)) {
        $t = $terms[0];
        if ($t->parent) {
            $p = get_term($t->parent, 'product_cat');
            if ($p && !is_wp_error($p)) $crumbs[] = array($p->name, get_term_link($p));
        }
        $link = get_term_link($t);
        if (!is_wp_error($link)) $crumbs[] = array($t->name, $link);
    }
    $crumbs[] = array(get_the_title(), get_permalink());
    if (count($crumbs) < 2) return;
    $items = array();
    foreach ($crumbs as $i => $c) {
        $items[] = array('@type' => 'ListItem', 'position' => $i + 1, 'name' => wp_strip_all_tags((string) $c[0]), 'item' => (string) $c[1]);
    }
    echo '<script type="application/ld+json">' . wp_json_encode(array(
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $items,
    )) . '</script>' . "\n";
}, 5);

// ─────────────────────────────────────────────────────────────
// PHASE 24 — Remove the "Custom Canvas Prints, Wall Art & Picture
// Framing" hero heading + subtitle (per request). Targeted by exact
// text so nothing else is affected; removes the tight wrapper that
// contains only this hero (heading + subtitle).
// ─────────────────────────────────────────────────────────────
add_action('wp_footer', function(){
    ?>
    <script>
    (function(){
      var HEAD = 'custom canvas prints, wall art & picture framing';
      var SUB  = 'museum-quality prints and handcrafted frames';
      function norm(s){ return (s||'').replace(/\s+/g,' ').trim().toLowerCase(); }
      function removeHero(matchFn, budget){
        var all = document.querySelectorAll('h1,h2,h3,p,span,div');
        for (var i=0;i<all.length;i++){
          var el = all[i];
          if (!matchFn(norm(el.textContent))) continue;
          // walk up while the wrapper still contains ONLY this hero text
          var target = el, node = el.parentElement;
          while (node && node !== document.body){
            if (norm(node.textContent).length <= budget){ target = node; node = node.parentElement; }
            else break;
          }
          target.style.display = 'none';   // hide (safe) rather than destroy
          target.setAttribute('data-af-removed','1');
          return true;
        }
        return false;
      }
      function run(){
        var budget = (HEAD.length + SUB.length) + 60;
        removeHero(function(t){ return t === HEAD || t.indexOf(HEAD) === 0; }, budget);
        removeHero(function(t){ return t.indexOf(SUB) === 0; }, budget);
      }
      if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run); else run();
      window.addEventListener('load', function(){ run(); setTimeout(run, 600); });
    })();
    </script>
    <?php
}, 105);

// ── 12k. Digital downloads: license acceptance + watermarked previews ─
// Spec §9: download limits (set by tools/enable-digital-downloads.php),
// license acceptance at checkout, watermarked product-page previews.

// Does the cart contain any digital download?
function af_cart_has_digital() {
    if (!function_exists('WC') || !WC()->cart) return false;
    foreach (WC()->cart->get_cart() as $item) {
        if (!empty($item['af_digital'])) return true;
        if (!empty($item['data']) && is_object($item['data']) && $item['data']->is_downloadable()) return true;
    }
    return false;
}

// Checkout: required license checkbox when a digital item is in the cart
add_action('woocommerce_review_order_before_submit', function() {
    if (!af_cart_has_digital()) return;
    ?>
    <p class="form-row af-dl-license-row" style="background:#faf7f0;border:1px solid #e0d5b8;padding:14px 16px;">
        <label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox" style="display:flex;gap:10px;align-items:flex-start;">
            <input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox" name="af_dl_license" id="af_dl_license" style="margin-top:4px;">
            <span>Your order includes a digital download. I accept the
            <a href="/digital-download-license/" target="_blank" rel="noopener">Digital Download License</a>
            (personal use only — no resale or redistribution). <abbr class="required" title="required">*</abbr></span>
        </label>
    </p>
    <?php
});

add_action('woocommerce_checkout_process', function() {
    if (af_cart_has_digital() && empty($_POST['af_dl_license'])) {
        wc_add_notice(__('Please accept the Digital Download License to complete your purchase.'), 'error');
    }
});

add_action('woocommerce_checkout_create_order', function($order) {
    if (!empty($_POST['af_dl_license'])) {
        $order->update_meta_data('_af_dl_license_accepted', current_time('mysql'));
    }
}, 10, 1);

// ── Resized-copy quality ─────────────────────────────────────
// WordPress re-encodes every registered size at quality 82. That is tuned for
// photographs; this catalogue is flat-colour, hard-edged artwork, where 82
// leaves visible ringing along the colour boundaries. 90 costs ~30% more bytes
// for images that are already 70-220KB. Only affects copies generated from here
// on — existing sizes keep their current quality until thumbnails are regenerated.
add_filter('jpeg_quality',          function() { return 90; }, 10, 0);
add_filter('wp_editor_set_quality', function() { return 90; }, 10, 0);

// ── Watermarked previews ─────────────────────────────────────
// For downloadable products, the product-page gallery serves a downscaled
// copy stamped with a diagonal watermark. Generated once with GD and
// cached in uploads/af-wm/. The paid file remains the clean original.
// Returns array( url, width, height ) for the cached preview, or false.
// Callers need the real dimensions: the gallery advertises them to the zoom,
// which upscales a smaller file if they describe the original master instead.
function af_wm_preview_url($attachment_id) {
    if (!function_exists('imagecreatetruecolor')) return false; // GD unavailable
    $src_path = get_attached_file($attachment_id);
    if (!$src_path || !file_exists($src_path)) return false;

    // Keep the preview in its source format. Re-encoding a WebP or PNG master
    // to JPEG adds ringing around the flat colour and hard edges this artwork
    // is built from, and silently flattens transparency onto black.
    $ext = strtolower(pathinfo($src_path, PATHINFO_EXTENSION));
    if     ($ext === 'webp' && function_exists('imagewebp')) $out = 'webp';
    elseif ($ext === 'png'  && function_exists('imagepng'))  $out = 'png';
    else                                                     $out = 'jpg';

    // Previews are shown at ~600 CSS px but zoomed to the full frame, and are
    // served to 2x/3x screens, so the cap has to leave the master untouched at
    // typical sizes rather than halving it.
    $max     = (int) apply_filters('af_wm_max_dimension', 1600);
    $quality = (int) apply_filters('af_wm_quality', 90);

    $up   = wp_get_upload_dir();
    $dir  = trailingslashit($up['basedir']) . 'af-wm';
    // The rendering settings are part of the cache key: previews are generated
    // once and never revisited, so without this every change below would only
    // reach products whose master image happens to change afterwards.
    $key  = 'v2|' . $max . '|' . $quality . '|' . $out . '|' . $src_path . '|' . filemtime($src_path);
    $name = 'wm-' . $attachment_id . '-' . substr(md5($key), 0, 8) . '.' . $out;
    $dest = $dir . '/' . $name;
    $url  = trailingslashit($up['baseurl']) . 'af-wm/' . $name;
    if (file_exists($dest)) {
        $sz = @getimagesize($dest);
        if ($sz) return array('url' => $url, 'width' => $sz[0], 'height' => $sz[1]);
        @unlink($dest); // unreadable/truncated cache entry — rebuild it
    }

    if (!wp_mkdir_p($dir)) return false;
    $raw = @file_get_contents($src_path);
    if (!$raw) return false;
    $img = @imagecreatefromstring($raw);
    if (!$img) return false;

    $w = imagesx($img); $h = imagesy($img);
    if ($w > $max || $h > $max) {
        $ratio = min($max / $w, $max / $h);
        $nw = (int) round($w * $ratio); $nh = (int) round($h * $ratio);
        $tmp = imagecreatetruecolor($nw, $nh);
        if ($out !== 'jpg') {
            imagealphablending($tmp, false);
            imagesavealpha($tmp, true);
            imagefill($tmp, 0, 0, imagecolorallocatealpha($tmp, 0, 0, 0, 127));
        }
        imagecopyresampled($tmp, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($img);
        $img = $tmp; $w = $nw; $h = $nh;
    }
    imagealphablending($img, true); // composite the stamp, don't overwrite pixels

    // Diagonal repeating watermark (built-in font — no TTF dependency).
    // imagestring() only speaks ASCII: a UTF-8 "©" or "·" arrives as its raw
    // bytes and prints as "Â©" / "Â·", so spell them out in ASCII instead.
    $text  = '(C) THE ART FRAMER - PREVIEW';
    $font  = 5;
    $tw    = imagefontwidth($font) * strlen($text);
    $th    = imagefontheight($font);
    $stamp = imagecreatetruecolor($tw + 40, $th + 24);
    imagesavealpha($stamp, true);
    imagefill($stamp, 0, 0, imagecolorallocatealpha($stamp, 0, 0, 0, 127));
    imagestring($stamp, $font, 20, 12, $text, imagecolorallocatealpha($stamp, 255, 255, 255, 78));
    imagestring($stamp, $font, 19, 11, $text, imagecolorallocatealpha($stamp, 20, 20, 20, 96));
    $rot = imagerotate($stamp, 30, imagecolorallocatealpha($stamp, 0, 0, 0, 127));
    imagesavealpha($rot, true);
    imagedestroy($stamp);
    $rw = imagesx($rot); $rh = imagesy($rot);
    for ($y = -$rh; $y < $h + $rh; $y += (int) ($rh * 1.6)) {
        for ($x = -$rw; $x < $w + $rw; $x += (int) ($rw * 1.15)) {
            imagecopy($img, $rot, $x, $y, 0, 0, $rw, $rh);
        }
    }
    imagedestroy($rot);

    if ($out !== 'jpg') imagesavealpha($img, true);
    switch ($out) {
        case 'webp': $ok = imagewebp($img, $dest, $quality); break;
        case 'png':  $ok = imagepng($img, $dest);            break; // lossless
        default:     $ok = imagejpeg($img, $dest, $quality); break;
    }
    imagedestroy($img);
    return $ok ? array('url' => $url, 'width' => $w, 'height' => $h) : false;
}

// Watermarked previews are OFF by default. They stamp the artwork and cost real
// resolution on every product page, and they protect nothing: WordPress serves
// the untouched master from its own public uploads URL, which this site already
// publishes in og:image, twitter:image and the product's JSON-LD. Every product
// is also currently flagged downloadable by tools/enable-digital-downloads.php,
// so leaving this on watermarks the whole catalogue of physical prints.
// Re-enable once genuine download-only products exist, and only for those:
//   wp option update af_wm_enabled yes
function af_wm_enabled() {
    return apply_filters('af_wm_enabled', get_option('af_wm_enabled', 'no') === 'yes');
}

// Swap gallery images for watermarked previews on downloadable products
add_filter('woocommerce_single_product_image_thumbnail_html', function($html, $attachment_id) {
    if (!is_product()) return $html;
    if (!af_wm_enabled()) return $html;
    global $product;
    if (!$product || !$product->is_downloadable()) return $html;
    $wm = af_wm_preview_url($attachment_id);
    if (!$wm) return $html;
    // Point every size variant, zoom target, and lightbox link at the preview.
    // This must cover the thumbnail sizes too: they appear in data-thumb-srcset,
    // so leaving them out published the un-watermarked original right next to
    // the preview that exists to protect it.
    $urls = array();
    foreach (array(
        'full', 'large', 'woocommerce_single', 'medium_large', 'medium',
        'woocommerce_thumbnail', 'woocommerce_gallery_thumbnail', 'thumbnail',
    ) as $size) {
        $s = wp_get_attachment_image_src($attachment_id, $size);
        if ($s && !empty($s[0])) $urls[$s[0]] = true;
    }
    $html = str_replace(array_keys($urls), $wm['url'], $html);
    $html = preg_replace('/\ssrcset="[^"]*"/', '', $html);
    $html = preg_replace('/\ssizes="[^"]*"/', '', $html);
    // Restate the dimensions to match the file actually being served. WooCommerce's
    // zoom magnifies to data-large_image_*, so the master's numbers here make it
    // upscale the preview and render a blurred crop instead of refusing to zoom.
    $html = preg_replace('/\sdata-large_image_width="\d+"/',  ' data-large_image_width="'  . $wm['width']  . '"', $html);
    $html = preg_replace('/\sdata-large_image_height="\d+"/', ' data-large_image_height="' . $wm['height'] . '"', $html);
    $html = preg_replace('/\swidth="\d+"/',  ' width="'  . $wm['width']  . '"', $html, 1);
    $html = preg_replace('/\sheight="\d+"/', ' height="' . $wm['height'] . '"', $html, 1);
    return $html;
}, 20, 2);

// ─────────────────────────────────────────────────────────────
// PHASE 24h — Review request in the "order completed" email.
// Every future buyer gets asked to review what they bought;
// star (aggregateRating) schema appears automatically once real
// reviews exist. No fake reviews — that's an FTC/Google violation.
// ─────────────────────────────────────────────────────────────
add_action('woocommerce_email_after_order_table', function ($order, $sent_to_admin, $plain_text, $email) {
    if ($sent_to_admin || !$email || $email->id !== 'customer_completed_order') return;
    if (!($order instanceof WC_Order)) return;
    $items = array_slice($order->get_items(), 0, 3);
    if (!$items) return;
    if ($plain_text) {
        echo "\n== How did we do? ==\n";
        echo "Reviews from customers like you help other art lovers choose with confidence.\n";
        foreach ($items as $item) {
            $pid = $item->get_product_id();
            $url = get_permalink($pid);
            if ($url) echo '- Review "' . $item->get_name() . '": ' . $url . "#reviews\n";
        }
        echo "\n";
        return;
    }
    echo '<div style="margin:24px 0;padding:18px 20px;border:1px solid #e5e0d8;border-radius:10px;background:#faf8f4;">';
    echo '<h3 style="margin:0 0 8px;font-size:16px;color:#1a1a1a;">How did we do?</h3>';
    echo '<p style="margin:0 0 12px;color:#555;font-size:14px;">Reviews from customers like you help other art lovers choose with confidence — it takes under a minute.</p>';
    foreach ($items as $item) {
        $pid = $item->get_product_id();
        $url = get_permalink($pid);
        if (!$url) continue;
        echo '<p style="margin:6px 0;"><a href="' . esc_url($url . '#reviews') . '" style="color:#c9a84c;font-weight:600;text-decoration:none;">&#9733; Review &ldquo;' . esc_html($item->get_name()) . '&rdquo;</a></p>';
    }
    echo '</div>';
}, 10, 4);

// ─────────────────────────────────────────────────────────────
// PHASE 24i — Organization schema enrichment for US entity SEO.
// Adds sameAs (real social profiles published in the footer),
// areaServed and currenciesAccepted. Filters Rank Math's existing
// Organization node rather than emitting a competing one.
//
// The postal address is intentionally NOT hard-coded: it activates
// only once the real address is stored in the `af_business_address`
// option (see tools/set-business-address.php). Never invent one —
// a wrong NAP is worse than none for local SEO.
// ─────────────────────────────────────────────────────────────
add_filter('rank_math/json_ld', function ($data, $jsonld) {
    foreach ($data as $key => $node) {
        if (!isset($node['@type'])) continue;
        $type = is_array($node['@type']) ? $node['@type'] : array($node['@type']);
        if (!in_array('Organization', $type, true)) continue;

        if (empty($node['sameAs'])) {
            $data[$key]['sameAs'] = array(
                'https://www.facebook.com/theartframer',
                'https://www.instagram.com/theartframer136/',
            );
        }
        if (empty($node['areaServed'])) {
            $data[$key]['areaServed'] = array(
                array('@type' => 'Country', 'name' => 'United States'),
                array('@type' => 'Country', 'name' => 'Canada'),
            );
        }
        if (empty($node['currenciesAccepted'])) {
            $data[$key]['currenciesAccepted'] = 'USD, CAD';
        }

        $addr = get_option('af_business_address', array());
        if (is_array($addr) && !empty($addr['street']) && !empty($addr['city'])
            && !empty($addr['region']) && !empty($addr['postal'])) {
            $data[$key]['address'] = array(
                '@type'           => 'PostalAddress',
                'streetAddress'   => $addr['street'],
                'addressLocality' => $addr['city'],
                'addressRegion'   => $addr['region'],
                'postalCode'      => $addr['postal'],
                'addressCountry'  => 'US',
            );
        }
    }
    return $data;
}, 20, 2);

/* ============================================================
   PHASE 15 — Gift Cards: purchasable product + code redemption
   Table {prefix}af_gift_cards holds code, balance, recipient.
   Buy: amount + recipient fields on the gift-card product page.
   Redeem: code field in cart/checkout applies balance as a discount;
   balance decrements on order completion (partial use supported).
   ============================================================ */

function af_gc_table() { global $wpdb; return $wpdb->prefix . 'af_gift_cards'; }
function af_gc_product_id() { return (int) get_option('af_gc_product_id', 0); }
function af_gc_amounts() { return array(25, 50, 100, 200); }

add_action('init', function() {
    if (get_option('af_gc_db_ver') === '1') return;
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $t = af_gc_table();
    dbDelta("CREATE TABLE {$t} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        code VARCHAR(24) NOT NULL,
        initial_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
        balance DECIMAL(10,2) NOT NULL DEFAULT 0,
        currency VARCHAR(6) NOT NULL DEFAULT 'USD',
        buyer_email VARCHAR(190) DEFAULT '',
        recipient_email VARCHAR(190) DEFAULT '',
        recipient_name VARCHAR(120) DEFAULT '',
        message TEXT,
        order_id BIGINT UNSIGNED DEFAULT 0,
        status VARCHAR(12) NOT NULL DEFAULT 'active',
        created_at DATETIME NOT NULL,
        used_at DATETIME DEFAULT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY code (code),
        KEY status (status)
    ) " . $wpdb->get_charset_collate() . ";");
    update_option('af_gc_db_ver', '1');
}, 6);

function af_gc_generate_code() {
    global $wpdb;
    $t = af_gc_table();
    do {
        $raw  = strtoupper(substr(str_replace(array('0','O','1','I'), '', strtoupper(wp_generate_password(24, false, false))), 0, 12));
        $code = 'TAF-' . substr($raw, 0, 4) . '-' . substr($raw, 4, 4) . '-' . substr($raw, 8, 4);
        $hit  = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$t} WHERE code = %s", $code));
    } while ($hit);
    return $code;
}

function af_gc_get($code) {
    global $wpdb;
    $code = strtoupper(trim($code));
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM " . af_gc_table() . " WHERE code = %s", $code));
}

// ── Product page: amount + recipient fields ──────────────────
add_action('woocommerce_before_add_to_cart_button', function() {
    global $product;
    if (!$product || $product->get_id() !== af_gc_product_id()) return;
    ?>
    <div class="af-gc-fields">
      <p class="af-gc-label">Choose an amount</p>
      <div class="af-gc-amounts">
        <?php foreach (af_gc_amounts() as $i => $a) : ?>
          <label class="af-gc-amt">
            <input type="radio" name="af_gc_amount" value="<?php echo esc_attr($a); ?>" <?php checked($i, 1); ?>>
            <span>$<?php echo esc_html($a); ?></span>
          </label>
        <?php endforeach; ?>
        <label class="af-gc-amt af-gc-amt-custom">
          <input type="radio" name="af_gc_amount" value="custom">
          <span>Custom</span>
        </label>
      </div>
      <p class="af-gc-custom-wrap" style="display:none;">
        <label>Custom amount (USD 10–1000)
          <input type="number" name="af_gc_custom" min="10" max="1000" step="1" value="75">
        </label>
      </p>
      <div class="af-gc-row">
        <label>Recipient name <input type="text" name="af_gc_rname" maxlength="120" placeholder="Who is it for?"></label>
        <label>Recipient email <input type="email" name="af_gc_remail" maxlength="190" placeholder="Leave blank to send it to yourself"></label>
      </div>
      <label>Personal message (optional)
        <textarea name="af_gc_message" rows="3" maxlength="300" placeholder="Happy birthday! Pick something beautiful for your wall."></textarea>
      </label>
      <p class="af-gc-note">🎁 Delivered by email within a few hours · never expires · works on every product</p>
    </div>
    <script>
    (function(){
      var wrap = document.querySelector('.af-gc-custom-wrap');
      document.querySelectorAll('input[name="af_gc_amount"]').forEach(function(r){
        r.addEventListener('change', function(){
          wrap.style.display = (document.querySelector('input[name="af_gc_amount"]:checked').value === 'custom') ? 'block' : 'none';
        });
      });
    })();
    </script>
    <?php
}, 20);

// ── Cart: carry gift-card data + price ───────────────────────
add_filter('woocommerce_add_cart_item_data', function($data, $pid) {
    if ((int) $pid !== af_gc_product_id()) return $data;
    $sel = isset($_POST['af_gc_amount']) ? sanitize_text_field(wp_unslash($_POST['af_gc_amount'])) : '50';
    if ($sel === 'custom') {
        $amt = isset($_POST['af_gc_custom']) ? (float) $_POST['af_gc_custom'] : 0;
        $amt = min(1000, max(10, $amt));
    } else {
        $amt = in_array((int) $sel, af_gc_amounts(), true) ? (float) $sel : 50.0;
    }
    $data['af_gc'] = array(
        'amount'  => $amt,
        'rname'   => isset($_POST['af_gc_rname'])   ? sanitize_text_field(wp_unslash($_POST['af_gc_rname'])) : '',
        'remail'  => isset($_POST['af_gc_remail'])  ? sanitize_email(wp_unslash($_POST['af_gc_remail'])) : '',
        'message' => isset($_POST['af_gc_message']) ? sanitize_textarea_field(wp_unslash($_POST['af_gc_message'])) : '',
    );
    $data['unique_key'] = md5(microtime() . wp_rand());
    return $data;
}, 10, 2);

add_action('woocommerce_before_calculate_totals', function($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;
    foreach ($cart->get_cart() as $item) {
        if (!empty($item['af_gc']['amount'])) $item['data']->set_price((float) $item['af_gc']['amount']);
    }
}, 25);

add_filter('woocommerce_get_item_data', function($data, $item) {
    if (!empty($item['af_gc'])) {
        $data[] = array('name' => 'Gift card value', 'value' => wc_price($item['af_gc']['amount']));
        if (!empty($item['af_gc']['rname']))  $data[] = array('name' => 'For', 'value' => esc_html($item['af_gc']['rname']));
        if (!empty($item['af_gc']['remail'])) $data[] = array('name' => 'Send to', 'value' => esc_html($item['af_gc']['remail']));
    }
    return $data;
}, 10, 2);

add_action('woocommerce_checkout_create_order_line_item', function($item, $key, $values) {
    if (!empty($values['af_gc'])) $item->add_meta_data('_af_gc', $values['af_gc']);
}, 10, 3);

// ── Issue codes when the order is paid ───────────────────────
function af_gc_issue_for_order($order_id) {
    $order = wc_get_order($order_id);
    if (!$order || $order->get_meta('_af_gc_issued')) return;
    global $wpdb;
    $issued = 0;
    foreach ($order->get_items() as $item) {
        $gc = $item->get_meta('_af_gc');
        if (empty($gc['amount'])) continue;
        for ($i = 0; $i < max(1, $item->get_quantity()); $i++) {
            $code = af_gc_generate_code();
            $wpdb->insert(af_gc_table(), array(
                'code'            => $code,
                'initial_amount'  => (float) $gc['amount'],
                'balance'         => (float) $gc['amount'],
                'currency'        => $order->get_currency(),
                'buyer_email'     => $order->get_billing_email(),
                'recipient_email' => !empty($gc['remail']) ? $gc['remail'] : $order->get_billing_email(),
                'recipient_name'  => isset($gc['rname']) ? $gc['rname'] : '',
                'message'         => isset($gc['message']) ? $gc['message'] : '',
                'order_id'        => $order_id,
                'status'          => 'active',
                'created_at'      => current_time('mysql'),
            ));
            $to   = !empty($gc['remail']) ? $gc['remail'] : $order->get_billing_email();
            $name = !empty($gc['rname']) ? $gc['rname'] : 'there';
            $body  = "Hi {$name},\n\n";
            $body .= trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()) . " has sent you a The Art Framer gift card!\n\n";
            $body .= "Gift card code: {$code}\n";
            $body .= "Value: " . strip_tags(wc_price($gc['amount'], array('currency' => $order->get_currency()))) . "\n\n";
            if (!empty($gc['message'])) $body .= "Their message:\n\"{$gc['message']}\"\n\n";
            $body .= "How to use it: shop at " . home_url('/shop/') . ", then enter the code in the \"Have a gift card?\" box at checkout.\n";
            $body .= "It never expires, and any unused balance stays on the card.\n\nEnjoy!\nThe Art Framer\n";
            wp_mail($to, '🎁 You have received a The Art Framer gift card', $body);
            $issued++;
        }
    }
    if ($issued) {
        $order->update_meta_data('_af_gc_issued', current_time('mysql'));
        $order->add_order_note(sprintf('%d gift card code(s) generated and emailed.', $issued));
        $order->save();
    }
}
add_action('woocommerce_payment_complete', 'af_gc_issue_for_order');
add_action('woocommerce_order_status_completed', 'af_gc_issue_for_order');
add_action('woocommerce_order_status_processing', 'af_gc_issue_for_order');

// ── Redemption ───────────────────────────────────────────────
function af_gc_applied() {
    if (!function_exists('WC') || !WC()->session) return null;
    $code = WC()->session->get('af_gc_code');
    if (!$code) return null;
    $gc = af_gc_get($code);
    if (!$gc || $gc->status !== 'active' || (float) $gc->balance <= 0) {
        WC()->session->set('af_gc_code', null);
        return null;
    }
    return $gc;
}

function af_gc_apply_handler() {
    check_ajax_referer('af_gc_apply', 'nonce');
    $code = isset($_POST['code']) ? strtoupper(sanitize_text_field(wp_unslash($_POST['code']))) : '';
    if ($code === 'REMOVE') {
        WC()->session->set('af_gc_code', null);
        wp_send_json_success(array('message' => 'Gift card removed.'));
    }
    $gc = af_gc_get($code);
    if (!$gc)                          wp_send_json_error(array('message' => 'That gift card code was not found — please check and try again.'));
    if ($gc->status !== 'active')      wp_send_json_error(array('message' => 'This gift card is no longer active.'));
    if ((float) $gc->balance <= 0)     wp_send_json_error(array('message' => 'This gift card has no remaining balance.'));
    WC()->session->set('af_gc_code', $gc->code);
    wp_send_json_success(array('message' => 'Gift card applied — balance ' . strip_tags(wc_price($gc->balance)) . '.'));
}
add_action('wp_ajax_af_gc_apply',        'af_gc_apply_handler');
add_action('wp_ajax_nopriv_af_gc_apply', 'af_gc_apply_handler');

// Apply the balance as a negative fee (never below zero, never on gift cards themselves)
add_action('woocommerce_cart_calculate_fees', function($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;
    $gc = af_gc_applied();
    if (!$gc) return;
    $eligible = 0.0;
    foreach ($cart->get_cart() as $item) {
        if (!empty($item['af_gc'])) continue; // can't buy gift cards with gift cards
        $eligible += (float) $item['line_total'] + (float) $item['line_tax'];
    }
    if ($eligible <= 0) return;
    $use = min((float) $gc->balance, $eligible);
    if ($use > 0) $cart->add_fee('Gift card (' . $gc->code . ')', -$use, false);
}, 20);

// Deduct the used balance when the order is placed
add_action('woocommerce_checkout_create_order', function($order) {
    $gc = af_gc_applied();
    if (!$gc) return;
    $used = 0.0;
    foreach ($order->get_fees() as $fee) {
        if (strpos($fee->get_name(), 'Gift card (') === 0) $used += abs((float) $fee->get_total());
    }
    if ($used <= 0) return;
    global $wpdb;
    $new = max(0, (float) $gc->balance - $used);
    $wpdb->update(af_gc_table(), array(
        'balance' => $new,
        'status'  => $new <= 0 ? 'used' : 'active',
        'used_at' => current_time('mysql'),
    ), array('id' => $gc->id));
    $order->update_meta_data('_af_gc_code', $gc->code);
    $order->update_meta_data('_af_gc_used', $used);
    $order->add_order_note(sprintf('Gift card %s redeemed: %s (remaining %s).', $gc->code, strip_tags(wc_price($used)), strip_tags(wc_price($new))));
    WC()->session->set('af_gc_code', null);
}, 20);

// Redemption UI on cart + checkout
add_action('woocommerce_cart_totals_before_order_total', 'af_gc_redeem_box');
add_action('woocommerce_review_order_before_order_total', 'af_gc_redeem_box');
function af_gc_redeem_box() {
    static $done = false;
    if ($done) return;
    $done = true;
    $gc = af_gc_applied();
    ?>
    <tr class="af-gc-redeem-row"><td colspan="2">
      <div class="af-gc-redeem" data-ajax="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
           data-nonce="<?php echo esc_attr(wp_create_nonce('af_gc_apply')); ?>">
        <?php if ($gc) : ?>
          <p class="af-gc-on">🎁 Gift card <b><?php echo esc_html($gc->code); ?></b> applied —
            balance <?php echo wp_kses_post(wc_price($gc->balance)); ?>
            <button type="button" class="af-gc-remove">Remove</button></p>
        <?php else : ?>
          <label class="af-gc-redeem-label">🎁 Have a gift card?</label>
          <div class="af-gc-redeem-row-inner">
            <input type="text" class="af-gc-input" placeholder="TAF-XXXX-XXXX-XXXX" autocomplete="off">
            <button type="button" class="af-gc-apply">Apply</button>
          </div>
        <?php endif; ?>
        <p class="af-gc-msg" role="status" aria-live="polite" style="display:none;"></p>
      </div>
    </td></tr>
    <script>
    (function(){
      var box = document.querySelector('.af-gc-redeem');
      if (!box || box.dataset.bound) return;
      box.dataset.bound = '1';
      function send(code){
        var msg = box.querySelector('.af-gc-msg'), fd = new FormData();
        fd.append('action', 'af_gc_apply'); fd.append('nonce', box.dataset.nonce); fd.append('code', code);
        fetch(box.dataset.ajax, { method:'POST', credentials:'same-origin', body: fd })
          .then(function(r){ return r.json(); })
          .then(function(res){
            msg.style.display = 'block';
            msg.className = 'af-gc-msg ' + (res.success ? 'ok' : 'err');
            msg.textContent = res.data.message;
            if (res.success) location.reload();
          });
      }
      var applyBtn = box.querySelector('.af-gc-apply'), rmBtn = box.querySelector('.af-gc-remove');
      if (applyBtn) applyBtn.addEventListener('click', function(){ send(box.querySelector('.af-gc-input').value.trim()); });
      if (rmBtn) rmBtn.addEventListener('click', function(){ send('REMOVE'); });
    })();
    </script>
    <?php
}

// ── Admin: Gift Cards list ───────────────────────────────────
add_action('admin_menu', function() {
    add_menu_page('Gift Cards', 'Gift Cards', 'manage_woocommerce', 'af-gift-cards', 'af_gc_admin_page', 'dashicons-tickets-alt', 27);
});
function af_gc_admin_page() {
    if (!current_user_can('manage_woocommerce')) return;
    global $wpdb;
    $t = af_gc_table();
    if (isset($_GET['gc_action'], $_GET['id'], $_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'af_gc_' . (int) $_GET['id'])) {
        $id = (int) $_GET['id'];
        if ($_GET['gc_action'] === 'void')   $wpdb->update($t, array('status' => 'void'),   array('id' => $id));
        if ($_GET['gc_action'] === 'active') $wpdb->update($t, array('status' => 'active'), array('id' => $id));
        echo '<script>location.replace("' . esc_url_raw(admin_url('admin.php?page=af-gift-cards')) . '");</script>';
        return;
    }
    $rows  = $wpdb->get_results("SELECT * FROM {$t} ORDER BY id DESC LIMIT 100");
    $total = (float) $wpdb->get_var("SELECT SUM(balance) FROM {$t} WHERE status='active'");
    echo '<div class="wrap"><h1>Gift Cards</h1>';
    echo '<p>Outstanding active balance: <b>' . wp_kses_post(wc_price($total ?: 0)) . '</b></p>';
    if (!$rows) { echo '<p><em>No gift cards issued yet.</em></p></div>'; return; }
    echo '<table class="widefat striped"><thead><tr><th>Code</th><th>Value</th><th>Balance</th><th>Recipient</th><th>Order</th><th>Issued</th><th>Status</th><th>Action</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        $n = wp_create_nonce('af_gc_' . $r->id);
        $base = admin_url('admin.php?page=af-gift-cards&id=' . $r->id . '&_wpnonce=' . $n);
        echo '<tr><td><code>' . esc_html($r->code) . '</code></td>';
        echo '<td>' . wp_kses_post(wc_price($r->initial_amount)) . '</td>';
        echo '<td><b>' . wp_kses_post(wc_price($r->balance)) . '</b></td>';
        echo '<td>' . esc_html($r->recipient_name ? $r->recipient_name . ' <' . $r->recipient_email . '>' : $r->recipient_email) . '</td>';
        echo '<td>' . ($r->order_id ? '<a href="' . esc_url(admin_url('post.php?post=' . $r->order_id . '&action=edit')) . '">#' . (int) $r->order_id . '</a>' : '—') . '</td>';
        echo '<td>' . esc_html(mysql2date('M j, Y', $r->created_at)) . '</td>';
        echo '<td>' . esc_html($r->status) . '</td>';
        echo '<td>' . ($r->status === 'void'
            ? '<a href="' . esc_url($base . '&gc_action=active') . '">Reactivate</a>'
            : '<a href="' . esc_url($base . '&gc_action=void') . '" style="color:#b32d2e;">Void</a>') . '</td></tr>';
    }
    echo '</tbody></table></div>';
}

/* ============================================================
   PHASE 16 — country selector, infinite scroll, messages inbox, RMA
   ============================================================ */

// ── 16a. Flag-based country selector (spec §3 Layer 1) ───────
function af_countries() {
    return array(
        'US' => array('flag' => '🇺🇸', 'name' => 'United States', 'cur' => 'USD', 'note' => 'Free delivery in DE, PA, MD, NJ &amp; nearby'),
        'CA' => array('flag' => '🇨🇦', 'name' => 'Canada',        'cur' => 'CAD', 'note' => 'Shipping calculated at checkout'),
        'GB' => array('flag' => '🇬🇧', 'name' => 'United Kingdom','cur' => 'USD', 'note' => 'International — contact us for a quote'),
        'AU' => array('flag' => '🇦🇺', 'name' => 'Australia',     'cur' => 'USD', 'note' => 'International — contact us for a quote'),
        'IN' => array('flag' => '🇮🇳', 'name' => 'India',         'cur' => 'USD', 'note' => 'International — contact us for a quote'),
    );
}
function af_active_country() {
    $c = isset($_COOKIE['af_country']) ? strtoupper(sanitize_text_field(wp_unslash($_COOKIE['af_country']))) : '';
    return array_key_exists($c, af_countries()) ? $c : 'US';
}

add_shortcode('af_country_selector', function() {
    $list = af_countries();
    $cur  = af_active_country();
    $sel  = $list[$cur];
    ob_start(); ?>
<div class="af-cty" id="afCty">
  <button type="button" class="af-cty-btn" aria-haspopup="true" aria-expanded="false">
    <span class="af-cty-flag"><?php echo $sel['flag']; ?></span>
    <span class="af-cty-code"><?php echo esc_html($cur); ?></span>
    <span class="af-cty-caret">▾</span>
  </button>
  <div class="af-cty-menu" role="menu">
    <p class="af-cty-head">Ship to</p>
    <?php foreach ($list as $code => $c) : ?>
      <button type="button" class="af-cty-item<?php echo $code === $cur ? ' on' : ''; ?>" role="menuitem"
              data-code="<?php echo esc_attr($code); ?>" data-cur="<?php echo esc_attr($c['cur']); ?>">
        <span class="af-cty-flag"><?php echo $c['flag']; ?></span>
        <span class="af-cty-nm"><?php echo esc_html($c['name']); ?><small><?php echo wp_kses_post($c['note']); ?></small></span>
        <span class="af-cty-cur"><?php echo esc_html($c['cur']); ?></span>
      </button>
    <?php endforeach; ?>
    <p class="af-cty-foot">Shipping elsewhere? <a href="/contact/">Ask for a quote →</a></p>
  </div>
</div>
<script>
(function(){
  var w = document.getElementById('afCty');
  if (!w || w.dataset.bound) return;
  w.dataset.bound = '1';
  var btn = w.querySelector('.af-cty-btn');
  btn.addEventListener('click', function(e){
    e.stopPropagation();
    var open = w.classList.toggle('open');
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
  document.addEventListener('click', function(){ w.classList.remove('open'); });
  w.querySelectorAll('.af-cty-item').forEach(function(b){
    b.addEventListener('click', function(){
      document.cookie = 'af_country=' + b.dataset.code + '; path=/; max-age=' + (86400*365);
      var u = new URL(location.href);
      u.searchParams.set('currency', b.dataset.cur);
      location.href = u.toString();
    });
  });
})();
</script>
<?php return ob_get_clean();
});

// ── 16b. Infinite scroll on shop/category (spec §6) ──────────
add_action('wp_footer', function() {
    if (!function_exists('is_shop')) return;
    if (!is_shop() && !is_product_category() && !is_product_tag()) return;
    ?>
<script>
(function(){
  var loading = false, done = false;
  function nextUrl(){
    var n = document.querySelector('.woocommerce-pagination a.next, .next.page-numbers, a.next');
    return n ? n.href : null;
  }
  function grid(){
    return document.querySelector('ul.products') ||
           document.querySelector('.elementor-widget-wc-archive-products ul') ||
           document.querySelector('.products');
  }
  var g = grid();
  if (!g || !nextUrl()) return;

  var bar = document.createElement('div');
  bar.className = 'af-inf-bar';
  bar.innerHTML = '<button type="button" class="af-inf-btn">Load More Artworks</button>' +
                  '<span class="af-inf-status" style="display:none;">Loading more artworks…</span>';
  (g.parentElement || document.body).insertBefore(bar, g.nextSibling);
  var btn = bar.querySelector('.af-inf-btn'), status = bar.querySelector('.af-inf-status');

  function load(){
    if (loading || done) return;
    var url = nextUrl();
    if (!url) { done = true; bar.innerHTML = '<span class="af-inf-end">✦ You have seen every artwork in this collection ✦</span>'; return; }
    loading = true;
    btn.style.display = 'none'; status.style.display = 'inline';
    fetch(url, { credentials: 'same-origin' })
      .then(function(r){ return r.text(); })
      .then(function(html){
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var ng = doc.querySelector('ul.products') || doc.querySelector('.products');
        var items = ng ? ng.children : [];
        var gnow = grid();
        Array.prototype.slice.call(items).forEach(function(li){ gnow.appendChild(document.importNode(li, true)); });
        var oldPag = document.querySelector('.woocommerce-pagination'),
            newPag = doc.querySelector('.woocommerce-pagination');
        if (oldPag && newPag) oldPag.replaceWith(document.importNode(newPag, true));
        else if (oldPag) oldPag.remove();
        // hide native pagination; keep it in DOM as the next-URL source
        var p = document.querySelector('.woocommerce-pagination');
        if (p) p.style.display = 'none';
        loading = false;
        status.style.display = 'none';
        if (nextUrl()) { btn.style.display = 'inline-block'; }
        else { done = true; bar.innerHTML = '<span class="af-inf-end">✦ You have seen every artwork in this collection ✦</span>'; }
        document.dispatchEvent(new Event('af_products_appended'));
        if (window.jQuery) jQuery(document.body).trigger('wc_fragments_refreshed');
      })
      .catch(function(){ loading = false; status.style.display = 'none'; btn.style.display = 'inline-block'; });
  }
  btn.addEventListener('click', load);
  var pag = document.querySelector('.woocommerce-pagination');
  if (pag) pag.style.display = 'none';

  // Auto-load when the bar scrolls into view (after the first manual click)
  var auto = false;
  btn.addEventListener('click', function(){ auto = true; });
  if ('IntersectionObserver' in window) {
    new IntersectionObserver(function(entries){
      if (entries[0].isIntersecting && auto) load();
    }, { rootMargin: '400px' }).observe(bar);
  }
})();
</script>
<?php }, 40);

// ── 16c. My Account: Messages inbox (spec §3) ────────────────
// Threads live in the af_contact_messages table (direction in/out).
add_action('init', function() {
    if (get_option('af_contact_db_ver') === '2') return;
    global $wpdb;
    $t = af_contact_table();
    foreach (array(
        'user_id'   => "ALTER TABLE {$t} ADD COLUMN user_id BIGINT UNSIGNED DEFAULT 0",
        'parent_id' => "ALTER TABLE {$t} ADD COLUMN parent_id BIGINT UNSIGNED DEFAULT 0",
        'direction' => "ALTER TABLE {$t} ADD COLUMN direction VARCHAR(4) DEFAULT 'in'",
    ) as $col => $sql) {
        $has = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$t} LIKE %s", $col));
        if (!$has) $wpdb->query($sql);
    }
    update_option('af_contact_db_ver', '2');
}, 7);

add_action('init', function() { add_rewrite_endpoint('messages', EP_ROOT | EP_PAGES); }, 8);
add_action('init', function() { add_rewrite_endpoint('returns',  EP_ROOT | EP_PAGES); }, 8);

add_filter('woocommerce_account_menu_items', function($items) {
    $new = array();
    foreach ($items as $k => $v) {
        $new[$k] = $v;
        if ($k === 'orders') {
            $new['messages'] = 'Messages';
            $new['returns']  = 'Returns';
        }
    }
    if (!isset($new['messages'])) { $new['messages'] = 'Messages'; $new['returns'] = 'Returns'; }
    return $new;
});

add_action('woocommerce_account_messages_endpoint', function() {
    $uid   = get_current_user_id();
    $user  = wp_get_current_user();
    global $wpdb;
    $t = af_contact_table();
    $threads = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$t} WHERE (user_id = %d OR email = %s) AND (parent_id = 0 OR parent_id IS NULL)
         ORDER BY id DESC LIMIT 25", $uid, $user->user_email
    ));
    echo '<div class="taf-page" style="padding:0;">';
    echo '<p>Messages between you and our studio team. Replies also arrive by email at <b>' . esc_html($user->user_email) . '</b>.</p>';
    echo '<div class="af-msg-new taf-card" style="margin:18px 0 26px;">';
    echo '<h3 style="margin-top:0;">Send a new message</h3>';
    echo '<form class="taf-form" method="post" style="box-shadow:none;padding:0;border:0;background:transparent;">';
    wp_nonce_field('af_msg_send', 'af_msg_nonce');
    echo '<label>Subject<input type="text" name="af_msg_subject" required maxlength="120" placeholder="What is this about?"></label>';
    echo '<label>Message<textarea name="af_msg_body" rows="4" required maxlength="3000" placeholder="How can we help?"></textarea></label>';
    echo '<button type="submit" class="taf-form-submit" name="af_msg_send" value="1">Send Message</button>';
    echo '</form></div>';
    if (!$threads) {
        echo '<div class="taf-card" style="text-align:center;"><span class="taf-ico">💬</span><h3>No messages yet</h3><p>Send us a message above — we usually reply within 24 hours.</p></div>';
    } else {
        foreach ($threads as $th) {
            $replies = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$t} WHERE parent_id = %d ORDER BY id ASC", $th->id));
            echo '<div class="af-msg-thread taf-card" style="margin-bottom:16px;">';
            echo '<h3 style="margin-top:0;">' . esc_html($th->subject ?: 'Message') . '</h3>';
            echo '<div class="af-msg-bubble you"><b>You</b> · ' . esc_html(mysql2date('M j, g:ia', $th->created_at)) . '<p>' . nl2br(esc_html($th->message)) . '</p></div>';
            foreach ($replies as $r) {
                $cls = $r->direction === 'out' ? 'studio' : 'you';
                $who = $r->direction === 'out' ? 'The Art Framer' : 'You';
                echo '<div class="af-msg-bubble ' . $cls . '"><b>' . $who . '</b> · ' . esc_html(mysql2date('M j, g:ia', $r->created_at)) . '<p>' . nl2br(esc_html($r->message)) . '</p></div>';
            }
            echo '<form method="post" class="af-msg-reply">';
            wp_nonce_field('af_msg_send', 'af_msg_nonce');
            echo '<input type="hidden" name="af_msg_parent" value="' . (int) $th->id . '">';
            echo '<textarea name="af_msg_body" rows="2" required placeholder="Write a reply…"></textarea>';
            echo '<button type="submit" class="taf-form-submit" name="af_msg_send" value="1">Reply</button>';
            echo '</form></div>';
        }
    }
    echo '</div>';
});

// Handle customer message/reply submissions
add_action('template_redirect', function() {
    if (empty($_POST['af_msg_send']) || !is_user_logged_in()) return;
    if (!isset($_POST['af_msg_nonce']) || !wp_verify_nonce($_POST['af_msg_nonce'], 'af_msg_send')) return;
    $user = wp_get_current_user();
    $body = sanitize_textarea_field(wp_unslash($_POST['af_msg_body'] ?? ''));
    if (mb_strlen($body) < 2) { wc_add_notice('Please write a message.', 'error'); return; }
    $parent  = (int) ($_POST['af_msg_parent'] ?? 0);
    $subject = $parent ? 'Re: thread #' . $parent : sanitize_text_field(wp_unslash($_POST['af_msg_subject'] ?? 'Message'));
    global $wpdb;
    $wpdb->insert(af_contact_table(), array(
        'created_at' => current_time('mysql'),
        'name'       => $user->display_name,
        'email'      => $user->user_email,
        'phone'      => '',
        'subject'    => $subject,
        'message'    => $body,
        'ip'         => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '',
        'status'     => 'new',
        'user_id'    => $user->ID,
        'parent_id'  => $parent,
        'direction'  => 'in',
    ));
    wp_mail(get_option('admin_email'), '[The Art Framer] Customer message: ' . $subject,
        "From: {$user->display_name} <{$user->user_email}>\n\n{$body}\n\nReply in wp-admin → Contact Messages.");
    wc_add_notice('Message sent — we usually reply within 24 hours.', 'success');
    wp_safe_redirect(wc_get_account_endpoint_url('messages'));
    exit;
});

// Admin: reply to a customer message (adds an 'out' message + emails them)
add_action('admin_post_af_msg_reply', function() {
    if (!current_user_can('manage_options') && !current_user_can('af_view_contact_messages')) wp_die('Denied');
    check_admin_referer('af_msg_reply');
    $parent = (int) ($_POST['parent_id'] ?? 0);
    $body   = sanitize_textarea_field(wp_unslash($_POST['reply'] ?? ''));
    global $wpdb;
    $t   = af_contact_table();
    $src = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id = %d", $parent));
    if ($src && $body !== '') {
        $wpdb->insert($t, array(
            'created_at' => current_time('mysql'),
            'name'       => 'The Art Framer',
            'email'      => $src->email,
            'subject'    => 'Re: ' . $src->subject,
            'message'    => $body,
            'status'     => 'read',
            'user_id'    => (int) $src->user_id,
            'parent_id'  => $parent,
            'direction'  => 'out',
        ));
        $wpdb->update($t, array('status' => 'read'), array('id' => $parent));
        wp_mail($src->email, 'Re: ' . $src->subject . ' — The Art Framer',
            "Hi " . $src->name . ",\n\n{$body}\n\n— The Art Framer\nView the conversation: " . wc_get_account_endpoint_url('messages'));
    }
    wp_safe_redirect(admin_url('admin.php?page=af-contact-messages'));
    exit;
});

// ── 16d. Self-service RMA / returns portal (spec §12) ────────
function af_rma_table() { global $wpdb; return $wpdb->prefix . 'af_rma_requests'; }

add_action('init', function() {
    if (get_option('af_rma_db_ver') === '1') return;
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta("CREATE TABLE " . af_rma_table() . " (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        order_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        email VARCHAR(190) DEFAULT '',
        reason VARCHAR(60) NOT NULL,
        resolution VARCHAR(20) NOT NULL DEFAULT 'replacement',
        details TEXT,
        status VARCHAR(16) NOT NULL DEFAULT 'pending',
        admin_note TEXT,
        created_at DATETIME NOT NULL,
        updated_at DATETIME DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY order_id (order_id),
        KEY status (status)
    ) " . $wpdb->get_charset_collate() . ";");
    update_option('af_rma_db_ver', '1');
}, 9);

function af_rma_reasons() {
    return array(
        'damaged'   => 'Arrived damaged',
        'defect'    => 'Printing or framing defect',
        'wrong'     => 'Wrong item delivered',
        'not_as'    => 'Not as described',
        'other'     => 'Something else',
    );
}

add_action('woocommerce_account_returns_endpoint', function() {
    $uid = get_current_user_id();
    global $wpdb;
    $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . af_rma_table() . " WHERE user_id = %d ORDER BY id DESC", $uid));
    $orders = wc_get_orders(array('customer_id' => $uid, 'limit' => 20, 'status' => array('processing', 'completed')));
    echo '<div class="taf-page" style="padding:0;">';
    echo '<p>Request a replacement or refund for an order. See our <a href="/returns-exchanges/">Returns &amp; Exchanges</a> policy — damaged items never need to be shipped back.</p>';

    if ($rows) {
        echo '<h3>Your Requests</h3><table class="taf-table"><thead><tr><th>#</th><th>Order</th><th>Reason</th><th>Resolution</th><th>Status</th><th>Submitted</th></tr></thead><tbody>';
        $reasons = af_rma_reasons();
        foreach ($rows as $r) {
            $badge = array('pending' => '#8a6d1f', 'approved' => '#256d2c', 'rejected' => '#a13232', 'resolved' => '#256d2c');
            echo '<tr><td>RMA-' . (int) $r->id . '</td><td>#' . (int) $r->order_id . '</td>';
            echo '<td>' . esc_html($reasons[$r->reason] ?? $r->reason) . '</td>';
            echo '<td>' . esc_html(ucfirst($r->resolution)) . '</td>';
            echo '<td><b style="color:' . esc_attr($badge[$r->status] ?? '#555') . ';">' . esc_html(ucfirst($r->status)) . '</b>';
            if ($r->admin_note) echo '<br><small>' . esc_html($r->admin_note) . '</small>';
            echo '</td><td>' . esc_html(mysql2date('M j, Y', $r->created_at)) . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    echo '<h3 style="margin-top:26px;">Start a New Request</h3>';
    if (!$orders) {
        echo '<div class="taf-card"><span class="taf-ico">📦</span><h3>No eligible orders</h3><p>Returns can be requested on paid orders. <a href="/shop/">Browse the collection →</a></p></div></div>';
        return;
    }
    echo '<form method="post" class="taf-form">';
    wp_nonce_field('af_rma_submit', 'af_rma_nonce');
    echo '<div class="taf-form-row">';
    echo '<label>Order *<select name="af_rma_order" required>';
    foreach ($orders as $o) {
        echo '<option value="' . (int) $o->get_id() . '">#' . esc_html($o->get_order_number()) . ' — ' . esc_html(wc_format_datetime($o->get_date_created(), 'M j, Y')) . ' — ' . wp_strip_all_tags($o->get_formatted_order_total()) . '</option>';
    }
    echo '</select></label>';
    echo '<label>Reason *<select name="af_rma_reason" required>';
    foreach (af_rma_reasons() as $k => $v) echo '<option value="' . esc_attr($k) . '">' . esc_html($v) . '</option>';
    echo '</select></label></div>';
    echo '<label>Preferred resolution *<select name="af_rma_resolution" required><option value="replacement">Free replacement</option><option value="refund">Refund</option><option value="advice">Just need advice</option></select></label>';
    echo '<label>Tell us what happened *<textarea name="af_rma_details" rows="4" required maxlength="2000" placeholder="Describe the issue. For damage, mention which part is affected — we may ask for photos by email."></textarea></label>';
    echo '<button type="submit" class="taf-form-submit" name="af_rma_submit" value="1">Submit Request</button>';
    echo '</form></div>';
});

add_action('template_redirect', function() {
    if (empty($_POST['af_rma_submit']) || !is_user_logged_in()) return;
    if (!isset($_POST['af_rma_nonce']) || !wp_verify_nonce($_POST['af_rma_nonce'], 'af_rma_submit')) return;
    $user  = wp_get_current_user();
    $oid   = (int) ($_POST['af_rma_order'] ?? 0);
    $order = wc_get_order($oid);
    if (!$order || (int) $order->get_customer_id() !== (int) $user->ID) {
        wc_add_notice('That order could not be verified.', 'error');
        return;
    }
    global $wpdb;
    $wpdb->insert(af_rma_table(), array(
        'order_id'   => $oid,
        'user_id'    => $user->ID,
        'email'      => $user->user_email,
        'reason'     => sanitize_text_field(wp_unslash($_POST['af_rma_reason'] ?? 'other')),
        'resolution' => sanitize_text_field(wp_unslash($_POST['af_rma_resolution'] ?? 'replacement')),
        'details'    => sanitize_textarea_field(wp_unslash($_POST['af_rma_details'] ?? '')),
        'status'     => 'pending',
        'created_at' => current_time('mysql'),
    ));
    $rid = (int) $wpdb->insert_id;
    $order->add_order_note(sprintf('Customer opened return request RMA-%d.', $rid));
    wp_mail(get_option('admin_email'), "[The Art Framer] New return request RMA-{$rid} (order #{$oid})",
        "Customer: {$user->display_name} <{$user->user_email}>\nOrder: #{$oid}\nReason: " . sanitize_text_field($_POST['af_rma_reason'] ?? '') .
        "\nWants: " . sanitize_text_field($_POST['af_rma_resolution'] ?? '') . "\n\n" . sanitize_textarea_field($_POST['af_rma_details'] ?? '') .
        "\n\nManage: " . admin_url('admin.php?page=af-returns'));
    wp_mail($user->user_email, "Return request received — RMA-{$rid}",
        "Hi {$user->display_name},\n\nWe've received your return request (RMA-{$rid}) for order #{$oid} and will review it within one business day.\n\nTrack it here: " . wc_get_account_endpoint_url('returns') . "\n\n— The Art Framer");
    wc_add_notice('Return request submitted — we will review it within one business day.', 'success');
    wp_safe_redirect(wc_get_account_endpoint_url('returns'));
    exit;
});

// Admin: RMA dashboard
add_action('admin_menu', function() {
    global $wpdb;
    $pending = (int) $wpdb->get_var("SELECT COUNT(*) FROM " . af_rma_table() . " WHERE status='pending'");
    $badge   = $pending ? " <span class='awaiting-mod'>{$pending}</span>" : '';
    add_menu_page('Returns (RMA)', 'Returns (RMA)' . $badge, 'manage_woocommerce', 'af-returns', 'af_rma_admin_page', 'dashicons-undo', 28);
});
function af_rma_admin_page() {
    if (!current_user_can('manage_woocommerce')) return;
    global $wpdb;
    $t = af_rma_table();
    if (isset($_POST['af_rma_admin_nonce']) && wp_verify_nonce($_POST['af_rma_admin_nonce'], 'af_rma_admin')) {
        $id     = (int) $_POST['rma_id'];
        $status = sanitize_text_field($_POST['status']);
        $note   = sanitize_textarea_field(wp_unslash($_POST['admin_note'] ?? ''));
        $row    = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id = %d", $id));
        $wpdb->update($t, array('status' => $status, 'admin_note' => $note, 'updated_at' => current_time('mysql')), array('id' => $id));
        if ($row && $row->email) {
            wp_mail($row->email, "Update on your return request RMA-{$id}",
                "Your return request RMA-{$id} is now: " . strtoupper($status) . "\n\n" . ($note ?: '') .
                "\n\nDetails: " . wc_get_account_endpoint_url('returns') . "\n\n— The Art Framer");
        }
        echo '<div class="notice notice-success"><p>RMA-' . $id . ' updated and the customer was emailed.</p></div>';
    }
    $rows    = $wpdb->get_results("SELECT * FROM {$t} ORDER BY FIELD(status,'pending','approved','rejected','resolved'), id DESC LIMIT 100");
    $reasons = af_rma_reasons();
    echo '<div class="wrap"><h1>Returns (RMA)</h1>';
    if (!$rows) { echo '<p><em>No return requests yet.</em></p></div>'; return; }
    echo '<table class="widefat striped"><thead><tr><th>RMA</th><th>Order</th><th>Customer</th><th>Reason</th><th>Wants</th><th>Details</th><th>Status</th><th>Action</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        echo '<tr' . ($r->status === 'pending' ? ' style="font-weight:600;"' : '') . '>';
        echo '<td>RMA-' . (int) $r->id . '<br><small>' . esc_html(mysql2date('M j', $r->created_at)) . '</small></td>';
        echo '<td><a href="' . esc_url(admin_url('post.php?post=' . $r->order_id . '&action=edit')) . '">#' . (int) $r->order_id . '</a></td>';
        echo '<td>' . esc_html($r->email) . '</td>';
        echo '<td>' . esc_html($reasons[$r->reason] ?? $r->reason) . '</td>';
        echo '<td>' . esc_html(ucfirst($r->resolution)) . '</td>';
        echo '<td style="max-width:280px;">' . esc_html(mb_strimwidth((string) $r->details, 0, 180, '…')) . '</td>';
        echo '<td>' . esc_html(ucfirst($r->status)) . '</td>';
        echo '<td><form method="post" style="display:flex;flex-direction:column;gap:5px;min-width:190px;">';
        wp_nonce_field('af_rma_admin', 'af_rma_admin_nonce');
        echo '<input type="hidden" name="rma_id" value="' . (int) $r->id . '">';
        echo '<select name="status">';
        foreach (array('pending', 'approved', 'rejected', 'resolved') as $s) {
            echo '<option value="' . $s . '"' . selected($r->status, $s, false) . '>' . ucfirst($s) . '</option>';
        }
        echo '</select>';
        echo '<textarea name="admin_note" rows="2" placeholder="Note emailed to customer">' . esc_textarea((string) $r->admin_note) . '</textarea>';
        echo '<button class="button button-primary">Update &amp; email</button>';
        echo '</form></td></tr>';
    }
    echo '</tbody></table></div>';
}

/* ============================================================
   PHASE 17 — video testimonials (§13) + maintenance mode (§4)
   ============================================================ */

// ── 17a. [af_video_testimonials] — customer video wall ───────
// Video pool: option 'af_testimonial_videos' (one YouTube URL/ID per
// line). Falls back to the cached channel pool. Rendered on the
// Reviews & Press page — deliberately NOT the homepage, where
// "Products In Motion" already covers video.
add_shortcode('af_video_testimonials', function() {
    $ids = array();
    $opt = trim((string) get_option('af_testimonial_videos', ''));
    if ($opt !== '') {
        foreach (preg_split('/[\r\n,]+/', $opt) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            if (preg_match('#(?:youtube(?:-nocookie)?\.com/(?:watch\?(?:[^&\s]*&)*v=|embed/|shorts/|v/)|youtu\.be/)([A-Za-z0-9_-]{11})#i', $line, $m)) {
                $ids[] = $m[1];
            } elseif (preg_match('/^[A-Za-z0-9_-]{11}$/', $line)) {
                $ids[] = $line;
            }
        }
    }
    if (empty($ids)) {
        $pool = get_transient('af_yt_ids3_UC_GX4vXRQrN4GsvSfgmZxYw');
        if (is_array($pool)) $ids = $pool;
    }
    $ids = array_slice(array_values(array_unique(array_filter($ids))), 0, 8);
    if (empty($ids)) return '';
    ob_start(); ?>
<div class="taf-vids" id="afVids">
  <div class="taf-vids-row">
    <?php foreach ($ids as $vid) : ?>
      <button type="button" class="taf-vid" data-vid="<?php echo esc_attr($vid); ?>" aria-label="Play customer video">
        <img loading="lazy" src="https://i.ytimg.com/vi/<?php echo esc_attr($vid); ?>/hqdefault.jpg" alt="Customer video testimonial">
        <span class="taf-vid-play"></span>
      </button>
    <?php endforeach; ?>
  </div>
  <p class="taf-vids-note">🎥 Real customers, real walls — filmed in their own homes.
    <a href="https://www.youtube.com/channel/UC_GX4vXRQrN4GsvSfgmZxYw" target="_blank" rel="noopener">See more on YouTube →</a></p>
</div>
<div class="taf-vid-lb" id="afVidLb">
  <button class="taf-vid-x" id="afVidX" aria-label="Close video">&times;</button>
  <iframe id="afVidFrame" allow="autoplay; encrypted-media" allowfullscreen title="Customer video"></iframe>
</div>
<script>
(function(){
  var lb = document.getElementById('afVidLb'), fr = document.getElementById('afVidFrame'), x = document.getElementById('afVidX');
  if (!lb) return;
  document.querySelectorAll('.taf-vid').forEach(function(b){
    b.addEventListener('click', function(){
      fr.src = 'https://www.youtube-nocookie.com/embed/' + b.dataset.vid + '?autoplay=1&rel=0&playsinline=1';
      lb.classList.add('open');
    });
  });
  function close(){ lb.classList.remove('open'); fr.src = ''; }
  x.onclick = close;
  lb.addEventListener('click', function(e){ if (e.target === lb) close(); });
  document.addEventListener('keydown', function(e){ if (e.key === 'Escape') close(); });
})();
</script>
<?php return ob_get_clean();
});

// ── 17b. Maintenance mode (spec §4) ──────────────────────────
// Toggle: option 'af_maintenance' = 'on'. Logged-in admins/shop
// managers always bypass it, so you can work on the live site.
// Optional end time: option 'af_maintenance_until' ("YYYY-MM-DD HH:MM").
add_action('template_redirect', function() {
    if (get_option('af_maintenance') !== 'on') return;
    if (is_admin() || wp_doing_ajax() || (defined('WP_CLI') && WP_CLI)) return;
    if (current_user_can('manage_options') || current_user_can('manage_woocommerce')) return;
    // Let people still log in
    if (function_exists('wc_get_page_id') && is_page(wc_get_page_id('myaccount'))) return;
    if (isset($GLOBALS['pagenow']) && $GLOBALS['pagenow'] === 'wp-login.php') return;

    $until = trim((string) get_option('af_maintenance_until', ''));
    $back  = '';
    if ($until !== '') {
        $d = date_create($until, wp_timezone());
        if ($d) $back = $d->format('l j F, g:ia T');
    }
    nocache_headers();
    header('HTTP/1.1 503 Service Temporarily Unavailable');
    header('Status: 503 Service Temporarily Unavailable');
    header('Retry-After: 3600');
    ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>We'll be right back — The Art Framer</title>
<style>
*{box-sizing:border-box;}
body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:30px;
  background:linear-gradient(135deg,#141414 0%,#2a2318 60%,#3a2f16 100%);color:#e9e4d6;
  font-family:'Fredoka',system-ui,-apple-system,'Segoe UI',sans-serif;text-align:center;}
.wrap{max-width:560px;}
.ico{font-size:64px;line-height:1;margin-bottom:18px;}
h1{font-size:38px;font-weight:800;color:#fff;margin:0 0 14px;line-height:1.15;}
.gold{color:#c9a84c;}
p{font-size:16px;line-height:1.75;color:#b8b2a2;margin:0 0 14px;}
.back{display:inline-block;background:rgba(201,168,76,.14);border:1px solid #8a6d1f;color:#e6d9ae;
  padding:11px 20px;font-size:14px;font-weight:700;margin:8px 0 22px;}
.links{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-top:8px;}
.btn{background:#c9a84c;color:#141414;text-decoration:none;font-weight:800;font-size:14px;padding:13px 26px;}
.btn:hover{background:#dcb85a;}
.btn-alt{background:transparent;color:#c9a84c;border:1px solid #c9a84c;text-decoration:none;font-weight:700;font-size:14px;padding:12px 26px;}
.btn-alt:hover{background:#c9a84c;color:#141414;}
.foot{margin-top:34px;font-size:12.5px;color:#8a8375;}
</style>
</head>
<body>
  <div class="wrap">
    <div class="ico">🖼️</div>
    <h1>We're hanging some<br><span class="gold">new art</span></h1>
    <p>The Art Framer is briefly offline for scheduled maintenance. Your orders and files are safe — we'll be back very soon.</p>
    <?php if ($back) : ?><p class="back">⏱ Expected back: <?php echo esc_html($back); ?></p><?php endif; ?>
    <p>Need something urgently? We're still reachable:</p>
    <div class="links">
      <a class="btn" href="mailto:theartframer136@gmail.com">Email Us</a>
      <a class="btn-alt" href="tel:+16104707280">Call +1 (610) 470-7280</a>
    </div>
    <p class="foot">© <?php echo date('Y'); ?> The Art Framer · theartframer.us</p>
  </div>
</body>
</html>
    <?php
    exit;
}, 0);

// Admin bar notice so maintenance mode is never left on by accident
add_action('admin_bar_menu', function($bar) {
    if (get_option('af_maintenance') !== 'on') return;
    if (!current_user_can('manage_options')) return;
    $bar->add_node(array(
        'id'    => 'af-maint',
        'title' => '⚠️ Maintenance mode ON',
        'href'  => admin_url('options-general.php?page=af-maintenance'),
        'meta'  => array('title' => 'The site is showing the maintenance page to visitors'),
    ));
}, 100);

// Settings screen: toggle + expected-return time
add_action('admin_menu', function() {
    add_options_page('Maintenance Mode', 'Maintenance Mode', 'manage_options', 'af-maintenance', function() {
        if (!current_user_can('manage_options')) return;
        if (isset($_POST['af_maint_nonce']) && wp_verify_nonce($_POST['af_maint_nonce'], 'af_maint')) {
            update_option('af_maintenance', isset($_POST['af_maintenance']) ? 'on' : 'off');
            update_option('af_maintenance_until', sanitize_text_field(wp_unslash($_POST['af_maintenance_until'] ?? '')));
            echo '<div class="notice notice-success"><p>Saved.</p></div>';
        }
        $on    = get_option('af_maintenance') === 'on';
        $until = (string) get_option('af_maintenance_until', '');
        ?>
        <div class="wrap">
          <h1>Maintenance Mode</h1>
          <p>When enabled, visitors see a branded "we'll be right back" page (HTTP 503, safe for SEO).
             Administrators and shop managers keep full access, so you can keep working on the live site.</p>
          <form method="post">
            <?php wp_nonce_field('af_maint', 'af_maint_nonce'); ?>
            <table class="form-table">
              <tr>
                <th scope="row">Maintenance mode</th>
                <td><label><input type="checkbox" name="af_maintenance" <?php checked($on); ?>>
                    Show the maintenance page to visitors</label></td>
              </tr>
              <tr>
                <th scope="row">Expected back (optional)</th>
                <td><input type="text" name="af_maintenance_until" class="regular-text"
                      value="<?php echo esc_attr($until); ?>" placeholder="2026-07-20 14:30">
                    <p class="description">Shown on the page as "Expected back: …". Leave blank to omit.</p></td>
              </tr>
            </table>
            <?php submit_button('Save'); ?>
          </form>
          <p><a href="<?php echo esc_url(home_url('/?af_maint_preview=1')); ?>" target="_blank">Preview the maintenance page →</a>
             <em>(admins bypass it, so use a private window to see it as a visitor)</em></p>
        </div>
        <?php
    });
});

// ─────────────────────────────────────────────────────────────
// PHASE 24j — "Shop related art" internal-link block on blog posts.
// The 17 long-form posts (4k-5k words) carried ZERO body links to
// products/categories, so their crawl authority never reached the
// commercial pages. This appends an editorial related-products strip
// (does not touch the post prose). Relevance = title-keyword overlap
// with product titles/categories, fallback to featured then recent.
// Result cached per-post in a transient (products change rarely).
// ─────────────────────────────────────────────────────────────
function af_related_products_for_post($post_id, $limit = 4) {
    $cache_key = 'af_relprod_' . $post_id;
    $cached = get_transient($cache_key);
    if (is_array($cached)) return $cached;

    if (!function_exists('wc_get_products')) return array();

    // Keywords from the post title (drop stopwords / short tokens).
    $title = get_the_title($post_id);
    $stop = array('the','and','for','your','with','how','you','are','from','that','this','when','what','their','our','of','to','in','a','an','on','it','by','or','is');
    $words = array_filter(preg_split('/[^a-z]+/', strtolower($title)), function ($w) use ($stop) {
        return strlen($w) >= 4 && !in_array($w, $stop, true);
    });

    $scored = array();
    if ($words) {
        $q = wc_get_products(array('status' => 'publish', 'limit' => 60, 'orderby' => 'date', 'order' => 'DESC'));
        foreach ($q as $p) {
            $hay = strtolower($p->get_name());
            foreach (wp_get_post_terms($p->get_id(), 'product_cat', array('fields' => 'names')) as $cn) {
                $hay .= ' ' . strtolower($cn);
            }
            $score = 0;
            foreach ($words as $w) {
                if (strpos($hay, $w) !== false) $score++;
            }
            if ($score > 0 && $p->get_image_id()) $scored[$p->get_id()] = $score;
        }
        arsort($scored);
    }

    $ids = array_slice(array_keys($scored), 0, $limit);

    // Fallback: featured, then most recent with an image.
    if (count($ids) < $limit) {
        $need = $limit - count($ids);
        $fb = wc_get_products(array(
            'status' => 'publish', 'limit' => $need + count($ids) + 4,
            'featured' => true, 'orderby' => 'date', 'order' => 'DESC',
        ));
        if (count($fb) < $need) {
            $fb = array_merge($fb, wc_get_products(array(
                'status' => 'publish', 'limit' => $need + count($ids) + 6,
                'orderby' => 'date', 'order' => 'DESC',
            )));
        }
        foreach ($fb as $p) {
            if (count($ids) >= $limit) break;
            if (!in_array($p->get_id(), $ids, true) && $p->get_image_id()) $ids[] = $p->get_id();
        }
    }

    set_transient($cache_key, $ids, DAY_IN_SECONDS);
    return $ids;
}

add_filter('the_content', function ($content) {
    if (is_admin() || !is_singular('post') || !in_the_loop() || !is_main_query()) return $content;
    if (strpos($content, 'af-relprod') !== false) return $content;
    if (!function_exists('wc_get_product')) return $content;

    $ids = af_related_products_for_post(get_the_ID(), 4);
    if (!$ids) return $content;

    $cards = '';
    foreach ($ids as $pid) {
        $p = wc_get_product($pid);
        if (!$p) continue;
        $img = wp_get_attachment_image($p->get_image_id(), 'woocommerce_thumbnail', false, array('loading' => 'lazy', 'alt' => esc_attr($p->get_name())));
        $cards .= '<a class="af-relprod-card" href="' . esc_url(get_permalink($pid)) . '">'
                . '<span class="af-relprod-img">' . $img . '</span>'
                . '<span class="af-relprod-name">' . esc_html($p->get_name()) . '</span>'
                . '<span class="af-relprod-price">' . wp_kses_post($p->get_price_html()) . '</span>'
                . '</a>';
    }
    if ($cards === '') return $content;

    $block  = '<section class="af-relprod" aria-label="Related art from The Art Framer">';
    $block .= '<h2 class="af-relprod-h">Shop Related Art</h2>';
    $block .= '<div class="af-relprod-grid">' . $cards . '</div>';
    $block .= '<p class="af-relprod-more"><a href="' . esc_url(get_permalink(wc_get_page_id('shop'))) . '">Browse the full collection &rarr;</a></p>';
    $block .= '</section>';

    return $content . $block;
}, 50);

add_action('wp_head', function () {
    if (!is_singular('post')) return;
    echo '<style>'
       . '.af-relprod{margin:42px 0 10px;padding-top:28px;border-top:1px solid #ece7dd;}'
       . '.af-relprod-h{font-size:22px;font-weight:700;color:#1a1a1a;margin:0 0 18px;}'
       . '.af-relprod-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;}'
       . '.af-relprod-card{display:flex;flex-direction:column;text-decoration:none;color:inherit;background:#fff;border:1px solid #ece7dd;border-radius:10px;overflow:hidden;transition:box-shadow .2s,transform .2s;}'
       . '.af-relprod-card:hover{box-shadow:0 8px 24px rgba(0,0,0,.1);transform:translateY(-3px);}'
       . '.af-relprod-img img{display:block;width:100%;height:auto;aspect-ratio:1/1;object-fit:cover;}'
       . '.af-relprod-name{padding:10px 12px 4px;font-size:14px;font-weight:600;line-height:1.35;}'
       . '.af-relprod-price{padding:0 12px 12px;font-size:14px;color:#c9a84c;font-weight:700;}'
       . '.af-relprod-more{margin:18px 0 0;}'
       . '.af-relprod-more a{color:#c9a84c;font-weight:600;text-decoration:none;}'
       . '@media(max-width:900px){.af-relprod-grid{grid-template-columns:repeat(2,1fr);}}'
       . '@media(max-width:480px){.af-relprod-grid{grid-template-columns:repeat(2,1fr);gap:12px;}.af-relprod-name{font-size:13px;}}'
       . '</style>';
});

// Bust the related-products cache whenever a product is saved/removed.
add_action('save_post_product', function () {
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_af_relprod\_%' OR option_name LIKE '\_transient\_timeout\_af_relprod\_%'");
});

// ─────────────────────────────────────────────────────────────
// PHASE 24k — Product schema: shipping, returns, brand.
// Values mirror the store's PUBLISHED policies (shipping page: free
// US shipping, ~5 business days production + up to 10 days transit;
// refund page: 7-day return window). returnFees intentionally
// omitted — free returns are only promised for damaged/wrong-item,
// so claiming FreeReturn store-wide would overstate. Makes products
// eligible for "free shipping / returns" treatment in US results.
// Keep these in sync with the policy pages if they change.
// ─────────────────────────────────────────────────────────────
function af_offer_shipping_details() {
    return array(
        '@type' => 'OfferShippingDetails',
        'shippingRate' => array('@type' => 'MonetaryAmount', 'value' => '0', 'currency' => 'USD'),
        'shippingDestination' => array('@type' => 'DefinedRegion', 'addressCountry' => 'US'),
        'deliveryTime' => array(
            '@type' => 'ShippingDeliveryTime',
            'handlingTime' => array('@type' => 'QuantitativeValue', 'minValue' => 1, 'maxValue' => 5, 'unitCode' => 'DAY'),
            'transitTime'  => array('@type' => 'QuantitativeValue', 'minValue' => 3, 'maxValue' => 10, 'unitCode' => 'DAY'),
        ),
    );
}
function af_merchant_return_policy() {
    return array(
        '@type' => 'MerchantReturnPolicy',
        'applicableCountry' => 'US',
        'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
        'merchantReturnDays' => 7,
        'returnMethod' => 'https://schema.org/ReturnByMail',
    );
}
function af_enrich_product_offer($offer) {
    if (empty($offer['shippingDetails'])) $offer['shippingDetails'] = af_offer_shipping_details();
    if (empty($offer['hasMerchantReturnPolicy'])) $offer['hasMerchantReturnPolicy'] = af_merchant_return_policy();
    return $offer;
}

// Rank Math pipeline (its graph carries the Product node on this site).
add_filter('rank_math/json_ld', function ($data, $jsonld) {
    if (!function_exists('is_product') || !is_product()) return $data;
    foreach ($data as $k => $node) {
        $types = isset($node['@type']) ? (array) $node['@type'] : array();
        if (!in_array('Product', $types, true)) continue;
        if (empty($node['brand'])) {
            $data[$k]['brand'] = array('@type' => 'Brand', 'name' => 'The Art Framer');
        }
        if (!empty($node['offers'])) {
            $offers = $node['offers'];
            if (isset($offers['@type'])) {
                $data[$k]['offers'] = af_enrich_product_offer($offers);
            } elseif (is_array($offers)) {
                foreach ($offers as $i => $o) {
                    if (is_array($o)) $offers[$i] = af_enrich_product_offer($o);
                }
                $data[$k]['offers'] = $offers;
            }
        }
    }
    return $data;
}, 20, 2);

// WooCommerce pipeline (covered too in case its JSON-LD is active).
add_filter('woocommerce_structured_data_product', function ($markup, $product) {
    if (empty($markup['brand'])) {
        $markup['brand'] = array('@type' => 'Brand', 'name' => 'The Art Framer');
    }
    if (!empty($markup['offers']) && is_array($markup['offers'])) {
        foreach ($markup['offers'] as $i => $o) {
            if (is_array($o)) $markup['offers'][$i] = af_enrich_product_offer($o);
        }
    }
    return $markup;
}, 20, 2);

// ═══════════════════════════════════════════════════════════════
// PHASE 25 — Security hardening (added 2026-07-18, security audit)
// Covers: REST user enumeration, software-version disclosure,
// XML-RPC amplification, and missing HTTP security headers.
// ═══════════════════════════════════════════════════════════════

// 25a. Block unauthenticated REST user enumeration.
// /wp/v2/users leaks real login names to anonymous callers. Logged-in
// users (Gutenberg author pickers, etc.) keep access; anon is removed.
add_filter('rest_endpoints', function ($endpoints) {
    if (!is_user_logged_in()) {
        unset($endpoints['/wp/v2/users']);
        unset($endpoints['/wp/v2/users/(?P<id>[\d]+)']);
    }
    return $endpoints;
});

// 25b. Stop software-version disclosure (harder to fingerprint CVEs).
remove_action('wp_head', 'wp_generator');
add_filter('the_generator', '__return_empty_string');

// 25c. XML-RPC: disable unless explicitly re-enabled via the
// 'af_xmlrpc_enabled' option (set to 'yes' if Jetpack / the WP mobile
// app ever needs it — no redeploy required). Pingback methods and the
// X-Pingback header are ALWAYS removed (DDoS-amplification vector,
// no legitimate use on this store).
if (get_option('af_xmlrpc_enabled') !== 'yes') {
    add_filter('xmlrpc_enabled', '__return_false');
}
add_filter('xmlrpc_methods', function ($methods) {
    unset($methods['pingback.ping'], $methods['pingback.extensions.getPingbacks']);
    return $methods;
});
add_filter('wp_headers', function ($headers) {
    unset($headers['X-Pingback']);
    return $headers;
});

// 25d. HTTP security headers.
// On this LiteSpeed+hcdn host, headers do NOT reach *cached* pages (LiteSpeed
// serves cache hits without running PHP, and does not bake PHP-set headers
// into the cache; the docroot-.htaccess mod_headers path also isn't honored
// here). So we set them via PHP for every UNCACHED / dynamic response —
// which is exactly where they matter: checkout, cart, my-account, login,
// search, REST, 404. Cached marketing pages (home/shop/product) need the
// header added at the LiteSpeed/CDN layer (hPanel) — tracked as a manual item.
// tools/set-security-headers.php still writes the .htaccess block in case a
// future server config honors it. (CSP deferred — Elementor needs report-only.)
add_action('send_headers', function () {
    if (is_admin()) return;
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
    if (is_ssl()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
});

// 25e. Disable the in-dashboard theme/plugin file editor. If an admin
// account is ever compromised, this stops the attacker editing PHP to
// plant a backdoor straight from wp-admin.
if (!defined('DISALLOW_FILE_EDIT')) {
    define('DISALLOW_FILE_EDIT', true);
}

// 25f. Generic login error — never reveal whether a username exists
// (the 4 admin usernames are already known; don't confirm passwords too).
add_filter('login_errors', function () {
    return 'Invalid credentials. Please try again.';
});

/* ============================================================
   PHASE 18 — Product page 100% spec match (§7)
   Group chips + wall hints, variable-product bridge, dimension
   overlay toggle, frame-color preview swap, video labels + DIY,
   verified-buyers badge, recently viewed.
   ============================================================ */
add_action('wp_footer', function() {
    if (!function_exists('is_product') || !is_product()) return;
    ?>
<style>
.af-opt-sub{font-weight:400;color:#999;font-size:11.5px;}
.af-group-chips{margin-bottom:8px;}
.af-chip-grp{background:#f5f0e4;border:1px solid #e0d5b8;color:#6b5a23;font-size:11.5px;font-weight:800;
  padding:5px 13px;cursor:pointer;border-radius:20px;text-decoration:none;display:inline-block;}
.af-chip-grp.active{background:#141414;border-color:#141414;color:#c9a84c;}
.af-chip-custom{border-style:dashed;}
.af-wall-hint{margin:2px 0 12px;font-size:12.5px;color:#8a6d1f;font-weight:600;}
.af-rec{display:block;font-size:9px;letter-spacing:.08em;text-transform:uppercase;color:#256d2c;font-weight:800;}
.af-color-tip{margin:2px 0 8px;font-size:11.5px;color:#999;}
.af-live-mrp{color:#b0b0b0;margin-left:10px;font-size:14px;}
.af-price-notes{margin:4px 0 10px;font-size:12.5px;color:#256d2c;font-weight:600;}
.af-dim-toggle{display:inline-flex;align-items:center;gap:7px;background:#fff;border:1px solid #c9a84c;color:#8a6d1f;
  font-size:12.5px;font-weight:700;padding:8px 14px;cursor:pointer;margin:10px 0 0;}
.af-dim-toggle.on{background:#141414;color:#c9a84c;border-color:#141414;}
.af-dim-overlay{position:absolute;inset:0;pointer-events:none;display:none;z-index:5;}
.af-dim-overlay.show{display:block;}
.af-dim-w{position:absolute;left:8%;right:8%;bottom:10px;border-bottom:2px solid #c9a84c;text-align:center;color:#fff;
  font-size:13px;font-weight:800;text-shadow:0 1px 4px #000;padding-bottom:3px;}
.af-dim-w::before,.af-dim-w::after{content:"";position:absolute;bottom:-6px;width:2px;height:14px;background:#c9a84c;}
.af-dim-w::before{left:0;} .af-dim-w::after{right:0;}
.af-dim-h{position:absolute;top:8%;bottom:8%;right:10px;border-right:2px solid #c9a84c;display:flex;align-items:center;}
.af-dim-h span{writing-mode:vertical-rl;color:#fff;font-size:13px;font-weight:800;text-shadow:0 1px 4px #000;padding-right:3px;}
.af-dim-h::before,.af-dim-h::after{content:"";position:absolute;right:-6px;height:2px;width:14px;background:#c9a84c;}
.af-dim-h::before{top:0;} .af-dim-h::after{bottom:0;}
.af-vid-caption{margin:10px 0 4px;font-size:13px;font-weight:700;color:#141414;}
.af-vid-caption span{color:#8a6d1f;}
.af-diy-strip{display:flex;gap:10px;margin:8px 0 0;flex-wrap:wrap;}
.af-diy-strip a{display:flex;align-items:center;gap:8px;border:1px solid #e0d5b8;background:#fdfcf9;padding:8px 13px;
  font-size:12.5px;font-weight:700;color:#6b5a23;text-decoration:none;}
.af-diy-strip a:hover{border-color:#c9a84c;}
.af-verified{display:inline-flex;align-items:center;gap:5px;background:#eef7ee;border:1px solid #bfe3c2;color:#256d2c;
  font-size:11.5px;font-weight:800;padding:3px 10px;border-radius:14px;margin-left:10px;vertical-align:middle;}
.af-recent{margin:34px 0 8px;}
.af-recent h3{font-size:20px;font-weight:800;margin:0 0 14px;}
.af-recent-row{display:flex;gap:14px;overflow-x:auto;padding-bottom:8px;}
.af-recent-row a{flex:0 0 150px;text-decoration:none;color:#141414;font-size:12.5px;font-weight:700;line-height:1.35;}
.af-recent-row img{width:150px;height:150px;object-fit:cover;display:block;border:1px solid #ece5d4;margin-bottom:7px;}
</style>
<script>
(function(){
  var opts = document.querySelector('.af-opts');

  // ── Variable-product bridge: our engine is the UI; silently pick the
  // first Woo variation so add-to-cart validates, then hide Woo's selects.
  var vform = document.querySelector('form.variations_form');
  if (opts && vform) {
    vform.querySelectorAll('.variations select').forEach(function(sel){
      for (var i = 0; i < sel.options.length; i++) {
        if (sel.options[i].value) { sel.value = sel.options[i].value; break; }
      }
      sel.dispatchEvent(new Event('change', { bubbles: true }));
    });
    if (window.jQuery) jQuery(vform).trigger('check_variations');
    var css = document.createElement('style');
    css.textContent = '.af-opts ~ * .variations, form.variations_form table.variations,' +
      'form.variations_form .reset_variations, form.variations_form .woocommerce-variation.single_variation,' +
      '.variable-items-wrapper{display:none !important;}';
    document.head.appendChild(css);
  }
  if (!opts) return;

  var cfg = JSON.parse(opts.dataset.config || '{}');

  // ── S/M/L group chips filter the size chips
  var groups = cfg.groups || {};
  opts.querySelectorAll('.af-chip-grp[data-grp]').forEach(function(g){
    g.addEventListener('click', function(){
      opts.querySelectorAll('.af-chip-grp').forEach(function(x){ x.classList.remove('active'); });
      g.classList.add('active');
      var allow = g.dataset.grp === 'All' ? null : (groups[g.dataset.grp] || []);
      var first = null;
      opts.querySelectorAll('.af-size-chips .af-chip-opt').forEach(function(ch){
        var ok = !allow || allow.indexOf(ch.dataset.val) >= 0;
        ch.style.display = ok ? '' : 'none';
        if (ok && !first) first = ch;
      });
      var active = opts.querySelector('.af-size-chips .af-chip-opt.active');
      if (first && (!active || active.style.display === 'none')) first.click();
    });
  });

  // ── Wall-suitability hint follows the selected size
  var hintEl = document.getElementById('afWallHint');
  function refreshExtras(){
    var sz = (opts.querySelector('input[name="af_size"]') || {}).value;
    if (hintEl) hintEl.textContent = (cfg.hints && cfg.hints[sz]) ? '📐 ' + cfg.hints[sz] : '';
    // MRP strikethrough: list-style reference at +25% of the live price
    var live = document.getElementById('af-live-price'), mrp = document.getElementById('af-live-mrp');
    if (live && mrp) {
      var num = parseFloat((live.textContent || '').replace(/[^0-9.]/g, ''));
      var symM = (live.textContent || '').match(/^[^0-9]*/);
      if (num) mrp.textContent = (symM ? symM[0] : '$') + (num * 1.25).toFixed(2);
    }
  }
  opts.addEventListener('click', function(){ setTimeout(refreshExtras, 30); });
  refreshExtras();

  // ── Dimension overlay toggle on the main product image (spec: ON/OFF)
  var gallery = document.querySelector('.woocommerce-product-gallery__wrapper') ||
                document.querySelector('.woocommerce-product-gallery');
  if (gallery) {
    var holder = gallery.querySelector('.woocommerce-product-gallery__image') || gallery;
    holder.style.position = 'relative';
    var ov = document.createElement('div');
    ov.className = 'af-dim-overlay';
    ov.innerHTML = '<div class="af-dim-w"><span id="afDimW"></span></div><div class="af-dim-h"><span id="afDimH"></span></div>';
    holder.appendChild(ov);
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'af-dim-toggle';
    btn.innerHTML = '📐 Show dimensions';
    // Place after the categories/tags meta block (user request), with fallbacks
    var metaEl = document.querySelector('.summary .product_meta') ||
                 document.querySelector('.product_meta');
    (metaEl || document.querySelector('.woocommerce-product-gallery') || holder).after(btn);
    function dims(){
      var sz = (opts.querySelector('input[name="af_size"]') || {}).value || '';
      var m = sz.match(/(\d+(?:\.\d+)?)×(\d+(?:\.\d+)?) ft \((\d+)×(\d+) in\)/);
      if (m) {
        document.getElementById('afDimH').textContent = m[1] + ' ft (' + m[3] + ' in)';
        document.getElementById('afDimW').textContent = m[2] + ' ft (' + m[4] + ' in)';
      }
    }
    btn.addEventListener('click', function(){
      var on = ov.classList.toggle('show');
      btn.classList.toggle('on', on);
      btn.innerHTML = on ? '📐 Hide dimensions' : '📐 Show dimensions';
      dims();
    });
    opts.addEventListener('click', function(){ setTimeout(dims, 30); });

    // ── Frame-color preview: swap main image to the matching frame photo
    var colorImgs = {
      'Black':     'https://theartframer.us/wp-content/uploads/2026/03/black-frame.webp',
      'Silver':    'https://theartframer.us/wp-content/uploads/2026/03/silver-frame.webp',
      'Gold':      'https://theartframer.us/wp-content/uploads/2026/03/gold-frame.webp',
      'Rose Gold': 'https://theartframer.us/wp-content/uploads/2026/03/rosegold-frame.webp'
    };
    var mainImg = holder.querySelector('img');
    if (mainImg) {
      var orig = { src: mainImg.src, srcset: mainImg.getAttribute('srcset') };
      opts.querySelectorAll('.af-swatch').forEach(function(sw){
        sw.addEventListener('dblclick', function(){ restore(); });
        sw.addEventListener('click', function(){
          var u = colorImgs[sw.dataset.val];
          if (!u) return;
          mainImg.src = u; mainImg.removeAttribute('srcset');
          clearTimeout(mainImg._afT);
          mainImg._afT = setTimeout(restore, 3500); // brief preview, then back to art
        });
      });
      function restore(){ mainImg.src = orig.src; if (orig.srcset) mainImg.setAttribute('srcset', orig.srcset); }
    }

    // ── Video labels (spec: "Watch how it looks in real homes") + DIY guides
    var cap = document.createElement('div');
    cap.className = 'af-vid-caption';
    cap.innerHTML = '▶ <span>Watch how it looks in real homes</span>' +
      '<div class="af-diy-strip">' +
      '<a href="https://www.youtube.com/channel/UC_GX4vXRQrN4GsvSfgmZxYw" target="_blank" rel="noopener">🎬 Product &amp; framing videos</a>' +
      '<a href="https://www.youtube.com/channel/UC_GX4vXRQrN4GsvSfgmZxYw/search?query=DIY" target="_blank" rel="noopener">🛠️ DIY hanging guide</a>' +
      '</div>';
    btn.after(cap);
  }

  // ── Verified Buyers badge beside the star rating
  var rating = document.querySelector('.woocommerce-product-rating');
  if (rating && !rating.querySelector('.af-verified')) {
    var b = document.createElement('span');
    b.className = 'af-verified';
    b.textContent = '✓ Verified Buyers';
    rating.appendChild(b);
  }
})();
</script>
<?php }, 45);

// ── Recently viewed (spec §7 post-tab): cookie-based strip ───
add_action('template_redirect', function() {
    if (!function_exists('is_product') || !is_product()) return;
    global $post;
    if (!$post) return;
    $seen = isset($_COOKIE['af_recent']) ? array_filter(array_map('absint', explode(',', $_COOKIE['af_recent']))) : array();
    $seen = array_diff($seen, array($post->ID));
    array_unshift($seen, $post->ID);
    $seen = array_slice($seen, 0, 9);
    setcookie('af_recent', implode(',', $seen), time() + MONTH_IN_SECONDS, '/');
});

add_action('woocommerce_after_single_product_summary', function() {
    global $post;
    $seen = isset($_COOKIE['af_recent']) ? array_filter(array_map('absint', explode(',', $_COOKIE['af_recent']))) : array();
    $seen = array_values(array_diff($seen, array($post ? $post->ID : 0)));
    if (empty($seen)) return;
    $out = '';
    $n = 0;
    foreach ($seen as $pid) {
        if ($n >= 6) break;
        $p = wc_get_product($pid);
        if (!$p || $p->get_status() !== 'publish') continue;
        $img = wp_get_attachment_image_url($p->get_image_id(), 'woocommerce_thumbnail');
        if (!$img) continue;
        $out .= '<a href="' . esc_url(get_permalink($pid)) . '"><img loading="lazy" src="' . esc_url($img) . '" alt="' . esc_attr($p->get_name()) . '">'
              . esc_html(wp_trim_words($p->get_name(), 7, '…')) . '</a>';
        $n++;
    }
    if (!$out) return;
    echo '<div class="af-recent"><h3>Recently Viewed</h3><div class="af-recent-row">' . $out . '</div></div>';
}, 22);

// ── 18c. Variation strips on shop/category product cards ─────
// One batched endpoint tells the page which visible products get the
// size/frame/color engine and their from-price; JS injects a compact
// swatch strip on each eligible card, deep-linking to #af-opts.
function af_card_variations_handler() {
    $raw = isset($_POST['ids']) ? (array) $_POST['ids'] : array();
    $ids = array_slice(array_filter(array_map('absint', $raw)), 0, 48);
    $cfg = af_pricing_config();
    $meta = array('sizes' => count($cfg['sizes']), 'frames' => count($cfg['frames']));
    $out = array();
    foreach ($ids as $id) {
        $p = wc_get_product($id);
        if (!$p || $p->get_status() !== 'publish') continue;
        if (!af_pricing_applies($p) || !$p->is_purchasable()) { $out[$id] = array('ok' => 0); continue; }
        $base = $p->is_type('variable') ? (float) $p->get_variation_price('min') : (float) wc_get_price_to_display($p);
        $out[$id] = array(
            'ok'   => 1,
            'from' => wp_strip_all_tags(wc_price($base)),
            'url'  => get_permalink($id) . '#af-opts',
        );
    }
    wp_send_json_success(array('meta' => $meta, 'items' => $out));
}
add_action('wp_ajax_af_card_variations',        'af_card_variations_handler');
add_action('wp_ajax_nopriv_af_card_variations', 'af_card_variations_handler');

add_action('wp_footer', function() {
    if (!function_exists('is_shop')) return;
    if (!is_shop() && !is_product_category() && !is_product_tag() && !is_front_page()) return;
    ?>
<style>
.af-card-vars{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin:7px 0 4px;font-size:11.5px;color:#8a6d1f;font-weight:700;}
.af-card-dots{display:inline-flex;gap:4px;}
.af-card-dots i{width:14px;height:14px;border-radius:50%;border:1.5px solid #fff;box-shadow:0 0 0 1px #d9d0bd;display:inline-block;}
.af-card-vars a{color:#8a6d1f;text-decoration:none;display:inline-flex;align-items:center;gap:8px;flex-wrap:wrap;}
.af-card-vars a:hover{color:#141414;}
.af-card-from{color:#141414;font-weight:800;}
</style>
<script>
(function(){
  var dots = {'Black':'#1a1a1a','Silver':'#c0c0c0','Gold':'#d4af37','Rose Gold':'#b76e79'};
  function inject(){
    var cards = {};
    document.querySelectorAll('a[data-product_id]').forEach(function(a){
      var card = a.closest('li.product, .type-product, .product');
      var id = parseInt(a.getAttribute('data-product_id'), 10);
      if (card && id && !card.querySelector('.af-card-vars') && !card.dataset.afVarsDone) { card.dataset.afVarsDone = '1'; cards[id] = card; }
    });
    var ids = Object.keys(cards);
    if (!ids.length) return;
    var fd = new FormData();
    fd.append('action', 'af_card_variations');
    ids.forEach(function(id){ fd.append('ids[]', id); });
    fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', { method:'POST', credentials:'same-origin', body: fd })
      .then(function(r){ return r.json(); })
      .then(function(res){
        if (!res || !res.success) return;
        var meta = res.data.meta, items = res.data.items || {};
        ids.forEach(function(id){
          var card = cards[id], info = items[id];
          card.dataset.afVarsDone = '1';
          if (!info || !info.ok) return;
          var strip = document.createElement('div');
          strip.className = 'af-card-vars';
          var d = Object.keys(dots).map(function(c){ return '<i title="' + c + '" style="background:' + dots[c] + '"></i>'; }).join('');
          strip.innerHTML = '<a href="' + info.url + '"><span class="af-card-dots">' + d + '</span>' +
            '<span>' + meta.sizes + ' sizes · ' + meta.frames + ' frames</span>' +
            '<span class="af-card-from">From ' + info.from + '</span></a>';
          var anchor = card.querySelector('.price') || card.querySelector('.woocommerce-loop-product__title') || card;
          anchor.parentElement ? anchor.parentElement.insertBefore(strip, anchor.nextSibling) : card.appendChild(strip);
        });
      });
  }
  document.addEventListener('DOMContentLoaded', inject);
  window.addEventListener('load', inject);
  setTimeout(inject, 900); setTimeout(inject, 2200);
  document.addEventListener('af_products_appended', function(){ setTimeout(inject, 300); }); // infinite scroll
})();
</script>
<?php }, 46);

// ─────────────────────────────────────────────────────────────
// PHASE 25 — Shop by Collection: show ALL products per collection.
// The parent theme caps its homepage collection grid at 12 in TWO
// hardcoded WP_Query calls (initial render ~line 813 and the
// load_products AJAX tab handler ~line 186). Both are secondary
// queries, so pre_get_posts can lift the cap from the child theme
// without touching parent files. Strictly scoped:
//   • never main queries (shop archive keeps its own per-page)
//   • AJAX: only action=load_products
//   • render: only front-page product queries with the exact
//     signature posts_per_page==12 + a product_cat tax filter
// ─────────────────────────────────────────────────────────────
add_action('pre_get_posts', function($q){
    if ($q->is_main_query()) return;
    // post_type may be a string or an array — accept both
    $pt = $q->get('post_type');
    $is_product = ($pt === 'product') || (is_array($pt) && in_array('product', $pt, true));
    if (!$is_product) return;

    // Case 1: collection tab switch (theme AJAX)
    if (wp_doing_ajax() && isset($_REQUEST['action']) && $_REQUEST['action'] === 'load_products') {
        $q->set('posts_per_page', -1);
        return;
    }

    // Case 2: initial homepage grid render — 12-per-page product query filtered
    // by category (tax_query OR the product_cat/category query-var shortcuts)
    if (!is_admin() && is_front_page() && (int) $q->get('posts_per_page') === 12) {
        $has_cat = false;
        $tax = $q->get('tax_query');
        if (is_array($tax)) {
            foreach ($tax as $t) {
                if (is_array($t) && isset($t['taxonomy']) && $t['taxonomy'] === 'product_cat') { $has_cat = true; break; }
            }
        }
        if (!$has_cat && ($q->get('product_cat') || $q->get('category_name'))) $has_cat = true;
        if ($has_cat) $q->set('posts_per_page', -1);
    }
});

// ─────────────────────────────────────────────────────────────
// PHASE 26 — "What Clients Say" review cards overlapped the next
// section's heading (Corporate Signages & Large Format Printing)
// on the homepage: the Embedder-for-Google-Reviews widget
// (#g-review) keeps its container shorter than its cards, so the
// following Elementor section flows up underneath them.
// Scoped strictly to #g-review and its Elementor container:
//   • CSS: let the swiper track/slides size to their content
//   • JS : if the widget (or its container) still reports less
//     height than its content occupies, pin min-height to the
//     real content height; re-check on resize and late loads
// ─────────────────────────────────────────────────────────────
add_action('wp_footer', function () {
    ?>
<style id="af-review-overlap-fix">
@media (min-width: 601px) {
  #g-review .swiper,
  #g-review .swiper-container { height: auto !important; max-height: none !important; }
  #g-review .swiper-wrapper   { height: auto !important; align-items: stretch !important; }
  #g-review .swiper-slide     { height: auto !important; }
  #g-review .swiper-slide .g-review { height: 100% !important; }
}
</style>
<script id="af-review-overlap-fix-js">
(function () {
  function fit(el) {
    if (!el) return;
    el.style.removeProperty('min-height');
    var need = el.scrollHeight, have = el.clientHeight;
    if (need > have + 8) {
      el.style.setProperty('height', 'auto', 'important');
      el.style.setProperty('min-height', need + 'px', 'important');
    }
  }
  function run() {
    var w = document.getElementById('g-review');
    if (!w) return;
    fit(w.querySelector('.grwp_body'));
    fit(w);
    fit(w.closest('.e-con, .e-container, section.elementor-section'));
  }
  if (document.readyState === 'complete') run();
  else window.addEventListener('load', run);
  setTimeout(run, 1200);
  setTimeout(run, 3000);
  var rt;
  window.addEventListener('resize', function () { clearTimeout(rt); rt = setTimeout(run, 250); });
})();
</script>
    <?php
}, 99);
