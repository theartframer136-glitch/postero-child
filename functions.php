<?php
/**
 * Postero Child Theme - functions.php
 * The Art Framer - theartframer.us
 */

// 1. Enqueue parent and child theme styles
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('postero-parent', get_template_directory_uri() . '/style.css');
    wp_enqueue_style('postero-child', get_stylesheet_uri(), array('postero-parent'), '1.0.0');
    // Version by file mtime: every deploy rewrites the files, so the URL
    // changes and no browser or edge cache can keep serving a stale copy.
    // (The hand-bumped strings before this were forgotten on deploy — the
    // Aug-13 drag-guard fix shipped server-side while every visitor's
    // browser kept the old custom.js?ver=1.3.6 for days.)
    $af_css_ver = @filemtime(get_stylesheet_directory() . '/assets/css/custom.css') ?: '3.4.13';
    $af_js_ver  = @filemtime(get_stylesheet_directory() . '/assets/js/custom.js') ?: '1.4.0';
    wp_enqueue_style('postero-child-custom', get_stylesheet_directory_uri() . '/assets/css/custom.css', array('postero-child'), $af_css_ver);
    wp_enqueue_script('postero-child-custom-js', get_stylesheet_directory_uri() . '/assets/js/custom.js', array('jquery'), $af_js_ver, true);
    wp_localize_script('postero-child-custom-js', 'af_ajax', array('url' => admin_url('admin-ajax.php')));

    // Checkout-only form styling — kept out of custom.css so the other
    // pages don't carry it.
    if (function_exists('is_checkout') && is_checkout()) {
        $af_co_ver = @filemtime(get_stylesheet_directory() . '/assets/css/checkout.css') ?: '1.0.0';
        wp_enqueue_style('postero-child-checkout', get_stylesheet_directory_uri() . '/assets/css/checkout.css', array('postero-child-custom'), $af_co_ver);
    }
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

/**
 * The plain-English name of each currency. "CA$ CAD" is two codes and no
 * words — it tells a shopper what the symbol is and what the code is, but
 * never what the money is. The switcher spells it out instead.
 */
function af_currency_name_for($code) {
    $names = array('USD' => 'US Dollar', 'CAD' => 'Canadian Dollar');
    return isset($names[$code]) ? $names[$code] : $code;
}

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
    var _fixedEl = null;

    // Fixed-width columns — matches the breakpoints in assets/css/custom.css —
    // so every subcategory item is the same size regardless of its label
    // length. Must be applied here (not left to the stylesheet alone):
    // this script previously set width:auto inline with !important, and an
    // inline !important always wins over an external stylesheet's
    // !important, so the stylesheet's fixed-width rule was silently losing.
    function itemWidth() {
        var w = window.innerWidth;
        if (w <= 576) return 72;
        if (w <= 991) return 78;
        return 92;
    }

    function fixEl(el) {
        if (!el) return;
        _busy = true;
        _fixedEl = el;
        el.style.setProperty('display',               'flex',    'important');
        el.style.setProperty('flex-direction',        'row',     'important');
        el.style.setProperty('flex-wrap',             'nowrap',  'important');
        el.style.setProperty('overflow-x',            'auto',    'important');
        el.style.setProperty('height',                'auto',    'important');
        el.style.setProperty('max-height',            'none',    'important');
        el.style.setProperty('width',                 '100%',    'important');
        el.style.setProperty('grid-template-columns', 'unset',   'important');
        el.style.setProperty('grid-template-rows',    'unset',   'important');
        var w = itemWidth() + 'px';
        Array.from(el.children).forEach(function(child) {
            child.style.setProperty('flex',      '0 0 ' + w, 'important');
            child.style.setProperty('position',  'static',   'important');
            child.style.setProperty('width',     w,          'important');
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

    // Keep the item width current across breakpoint changes (e.g. rotating
    // a tablet, resizing a desktop browser window).
    var _resizeTimer = null;
    window.addEventListener('resize', function() {
        clearTimeout(_resizeTimer);
        _resizeTimer = setTimeout(function() { if (_fixedEl) fixEl(_fixedEl); }, 150);
    });
}());
</script>
<?php }, 9999);


// 9b. Collection circles: the child theme owns the click (2026-08-13).
// History: an Aug-11 capture-phase interceptor swallowed circle clicks; the
// Aug-12 restore (bee70f9) removed the child's wiring entirely, betting the
// parent theme's own handler would take the clicks back. The Aug-12 server
// diagnostics disprove that bet: the circles are a.pf-value anchors produced
// by the parent's category walker (inc/woocommerce/woocommerce-template-
// functions.php), its load_products AJAX endpoint is registered and answers
// with product cards — but NO code in the parent theme or any plugin binds a
// click to the circles ("no direct click binding found by pattern",
// diag-pf-handler.php / diag-subcat-click.php, deploy runs 31599177961 and
// 31602796393). The endpoint is an orphan; with the child wiring gone every
// circle click has been dead, which is exactly what the owner's Aug-13
// screen recording shows. So the child owns the interaction, with three
// guarantees:
//   1. instant response — the clicked circle is marked active and the
//      visible product area dims while products load;
//   2. products swap in place through the theme's own contract
//      (POST action=load_products&subcategory=<data-val slug>);
//   3. never a dead click — if the request fails or answers without cards,
//      the browser navigates to the circle's real category archive URL.
// Delegated from document (bubble phase), so circles re-rendered later by
// tab switches or AJAX are covered without re-wiring, and the drag guard in
// assets/js/custom.js still cancels post-drag clicks before they get here.
// Handles every strip variant the theme renders: li.cat-item > a.pf-value
// (data-val + real href), and the .sub-cat <img>+<span> circles (no anchor —
// resolved by caption against the category map below).
add_action('wp_footer', function() {
  if (is_admin()) return;
  $terms = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false));
  if (is_wp_error($terms) || !$terms) return;
  $map = array();
  foreach ($terms as $t) {
    $link = get_term_link($t);
    if (is_wp_error($link)) continue;
    $key = strtolower(preg_replace('/[^a-z0-9]+/i', '', html_entity_decode($t->name)));
    if ($key !== '') $map[$key] = array('u' => $link, 's' => $t->slug);
  }
  if (!$map) return;
  ?>
<style id="af-circle-active">
/* The chosen circle keeps a visible gold ring until another is chosen */
#subcategorySlider .active img, .subcategory-slider .active img,
li.cat-item.active img, .sub-cat.active img,
ul.postero-scroll-content li.cat-item.active img {
    outline: 3px solid #c9a84c !important;
    outline-offset: 2px !important;
    border-radius: 50% !important;
}
</style>
<script>
(function(){
  var AJAX = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
  var CATS = <?php echo wp_json_encode($map); ?>;
  function dbg(m){ if (window.afdbg) window.afdbg(m); }
  function norm(s){ return (s||'').toLowerCase().replace(/[^a-z0-9]+/g,''); }

  var STRIPS   = '#subcategorySlider, .subcategory-slider, ul.postero-scroll-content, .subcategory-container';
  var ITEM     = 'a.pf-value, li.cat-item, .sub-cat';
  var GRID_SEL = '#productGrid, .product-slider, .custom-product-track, ul.products';

  // Resolve which category a circle stands for: trust data-val (the exact
  // slug load_products expects) and the anchor's real href when present;
  // otherwise match the caption against the category map.
  function catFor(item){
    var a = (item.matches && item.matches('a.pf-value,[data-val]')) ? item
          : (item.querySelector ? item.querySelector('a.pf-value,[data-val]') : null);
    if (a && a.getAttribute('data-val')) {
      var href = (a.tagName === 'A' && a.getAttribute('href')) || '';
      if (!href || href === '#' || href.indexOf('javascript:') === 0) {
        var m = CATS[norm(a.getAttribute('data-title') || a.textContent)];
        href = m ? m.u : '';
      }
      return { s: a.getAttribute('data-val'), u: href };
    }
    var label = norm((item.getAttribute && item.getAttribute('data-title')) || item.textContent);
    if (!label) return null;
    if (CATS[label]) return CATS[label];
    var best = null, bestLen = 0;
    for (var k in CATS) {
      if (k.length > bestLen && label.indexOf(k) !== -1) { best = CATS[k]; bestLen = k.length; }
    }
    return best;
  }

  // The grid to swap is the one in the SAME section as the clicked strip —
  // walking up keeps a grid from an unrelated section out of the swap.
  function gridFor(item){
    for (var node = item; node && node !== document.body; node = node.parentElement) {
      var g = node.querySelector(GRID_SEL);
      if (g && !g.contains(item)) return g;
    }
    return document.querySelector(GRID_SEL);
  }

  // What the visitor actually SEES may be the af-shell carousel built from
  // the (then hidden) grid — dim that, not the hidden element.
  function visibleAreaFor(grid){
    if (grid.classList.contains('af-grid-hidden') && grid.parentNode) {
      var shell = grid.parentNode.querySelector('.af-shell');
      if (shell) return shell;
    }
    return grid;
  }

  function markActive(item){
    var circle = (item.closest && (item.closest('li.cat-item, .sub-cat') || item)) || item;
    var strip = item.closest ? item.closest(STRIPS) : null;
    if (strip) {
      strip.querySelectorAll('.active').forEach(function(x){ x.classList.remove('active'); });
    }
    circle.classList.add('active');
  }

  var busy = false;
  function filterTo(cat, item, e){
    var grid = gridFor(item);
    dbg('click slug=' + cat.s + ' grid=' + (grid ? (grid.id || grid.className || grid.tagName).toString().slice(0,50) : 'NONE'));
    if (!grid) {
      // nothing to filter on this page — the click must still go somewhere
      var a = item.tagName === 'A' ? item : (item.querySelector && item.querySelector('a[href]'));
      if (!a && cat.u) { e.preventDefault(); window.location.href = cat.u; }
      return; // real anchors navigate natively
    }
    e.preventDefault();
    if (busy) return; // one request at a time; the click is already answered
    busy = true;
    markActive(item);

    var area = visibleAreaFor(grid);
    area.style.opacity = '.45';
    // Safety: never leave the page dimmed (shell rebuilds replace the dimmed
    // element on success; this covers a rebuild that never comes)
    var unDim = setTimeout(function(){ area.style.opacity = ''; }, 4000);

    var body = new URLSearchParams();
    body.set('action', 'load_products');
    body.set('subcategory', cat.s);
    fetch(AJAX, { method: 'POST', credentials: 'same-origin',
                  headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                  body: body.toString() })
      .then(function(r){ dbg('ajax http=' + r.status); if (!r.ok) throw new Error('http ' + r.status); return r.text(); })
      .then(function(html){
        var ok = html && (html.indexOf('product-card') !== -1 || html.indexOf('woocommerce-loop-product') !== -1);
        dbg('ajax bytes=' + (html ? html.length : 0) + (ok ? '' : ' (no cards)'));
        busy = false;
        if (!ok) {
          // an empty category reads better on its archive page, which offers
          // similar pieces — and the click still visibly does something
          clearTimeout(unDim); area.style.opacity = '';
          if (cat.u) { window.location.href = cat.u; }
          return;
        }
        grid.innerHTML = html;
        // A visible grid is done now; a shell-backed grid is rebuilt by the
        // slider's own MutationObserver moments later (it replaces the shell).
        if (area === grid) { clearTimeout(unDim); area.style.opacity = ''; }
        document.dispatchEvent(new Event('af_products_appended'));
        if (window.jQuery) jQuery(document.body).trigger('wc_fragments_refreshed');
        dbg('swapped into ' + (grid.id || grid.className || grid.tagName).toString().slice(0,50));
      })
      .catch(function(err){
        dbg('ajax FAILED ' + err);
        busy = false;
        clearTimeout(unDim); area.style.opacity = '';
        if (cat.u) window.location.href = cat.u; // never a dead click
      });
  }

  document.addEventListener('click', function(e){
    if (e.defaultPrevented) return;                 // someone else owns this click
    if (!e.target || !e.target.closest) return;
    var item = e.target.closest(ITEM);
    if (!item || !item.closest(STRIPS)) return;     // only circles inside a strip
    if (e.target.closest('button, .next-circle, .prev-circle, [class*="arrow"]')) return;
    var cat = catFor(item);
    if (!cat || !cat.s) { dbg('click: no category match for "' + (item.textContent||'').trim().slice(0,40) + '"'); return; }
    filterTo(cat, item, e);
  });
})();
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
                    // 8px, matching every other card section. This inline
                    // !important is what silently beat the stylesheet's gap:
                    // it is the styler that builds the carousel cards, so
                    // Trending Today kept its 5px however the CSS was written.
                    sp(btn,'gap',             '8px');
                    sp(btn,'white-space',     'nowrap');
                    sp(btn,'text-decoration', 'none');
                    sp(btn,'line-height',     '1');
                    var cls = btn.className || '';
                    // Every spelling the sections ship: add-cart, add_to_cart_button,
                    // add-to-cart-btn. The old /add.cart|add_to_cart/ needed exactly
                    // ONE character between "add" and "cart", so add-to-cart-btn fell
                    // through and got painted black like Quick View.
                    var isCart = /add[-_ ]?(to[-_ ]?)?cart/i.test(cls);
                    sp(btn,'background', isCart ? '#c9a84c' : '#1a1a1a');
                    sp(btn,'color','#fff');
                    // inline !important cannot be hovered by a stylesheet
                    if (!btn.dataset.afHoverBound) {
                        btn.dataset.afHoverBound = '1';
                        btn.addEventListener('mouseenter', function(){
                            sp(btn,'background', isCart ? '#8b6a2b' : '#333');
                        });
                        btn.addEventListener('mouseleave', function(){
                            sp(btn,'background', isCart ? '#c9a84c' : '#1a1a1a');
                        });
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
  top:24px !important; left:-46px !important; right:auto !important;
  z-index:10 !important;
  background:linear-gradient(135deg,#ecc768,#cf9f2e) !important; color:#fff !important;
  font-size:12px !important; font-weight:800 !important;
  padding:7px 0 !important;
  text-transform:uppercase !important; letter-spacing:.12em !important;
  transform:rotate(-45deg) !important;
  text-shadow:0 1px 2px rgba(0,0,0,.25) !important;
  box-shadow:0 3px 8px rgba(0,0,0,.28) !important;
  min-width:0 !important; width:160px !important; text-align:center !important;
  line-height:1.3 !important; border-radius:0 !important; margin:0 !important;
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
  gap:8px !important; transition:background .2s !important; white-space:nowrap !important;
  flex:1 1 50% !important;
}
html body .product-card .add-cart:hover,
html body .product-card .add_to_cart_button:hover { background:#8b6a2b !important; }

/* Add to Cart — the icon's breathing room, brand gold and hover, applied
   wherever the button lives and whatever the section calls it. The homepage
   carousels ship .add-to-cart-btn (hyphens); the loop ships
   .add_to_cart_button (underscores); earlier rules only knew the second, so
   the gap never reached Trending Today, Digital Downloads or Corporate
   Signages. The space is a margin on the ICON, not a flex gap, so it works
   whether the button is flex or inline-block, and the ::before pair covers
   icon-font buttons that have no child element at all. */
/* ONE spacing mechanism, not two: make every variant a flex box and let a
   single gap do the work. An icon margin on top of an inherited flex gap
   doubled the space to 16px on the loop cards — measured, not guessed. Flex
   also spaces ::before icon fonts, which become flex items in their own
   right, so no separate pseudo-element rule is needed. */
html body .add_to_cart_button,
html body .add-to-cart-btn,
html body .add-cart,
html body [class*="add-to-cart-btn"] {
  display:inline-flex !important; align-items:center !important;
  justify-content:center !important; gap:8px !important;
  /* the label must never wrap under the icon in a narrow card — only the
     loop selector carried nowrap before, so the carousels could break it */
  white-space:nowrap !important;
}
html body .add_to_cart_button > i,  html body .add_to_cart_button > svg,
html body .add-to-cart-btn > i,     html body .add-to-cart-btn > svg,
html body .add-cart > i,            html body .add-cart > svg,
html body .af-ov-atc > svg {
  flex-shrink:0 !important; margin-right:0 !important;
}
html body .add-to-cart-btn,
html body [class*="add-to-cart-btn"] {
  background:#c9a84c !important; border-color:#c9a84c !important;
  transition:background .2s !important;
}
html body .add-to-cart-btn:hover,
html body [class*="add-to-cart-btn"]:hover {
  background:#8b6a2b !important; border-color:#8b6a2b !important;
}

/* Quick View */
html body .product-card .quick-view-btn,
html body .product-card [class*="quick-view"],
html body .product-card [class*="quickview"] {
  background:#1a1a1a !important; color:#fff !important;
  border:none !important; border-radius:7px !important;
  font-size:13px !important; font-weight:600 !important; padding:10px 6px !important;
  cursor:pointer !important; text-decoration:none !important;
  display:flex !important; align-items:center !important; justify-content:center !important;
  gap:8px !important; transition:background .2s !important; white-space:nowrap !important;
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
      // inline !important beats any stylesheet :hover, so bind the swap here
      if (!el.dataset.afHoverBound) {
        el.dataset.afHoverBound = '1';
        el.addEventListener('mouseenter', function(){
          el.style.setProperty('background-color', '#8b6a2b', 'important');
          el.style.setProperty('border-color', '#8b6a2b', 'important');
        });
        el.addEventListener('mouseleave', function(){
          el.style.setProperty('background-color', '#c9a84c', 'important');
          el.style.setProperty('border-color', '#c9a84c', 'important');
        });
      }
      // spacing comes from the flex gap above; force the layout in case the
      // theme left this button inline-block
      el.style.setProperty('display', 'inline-flex', 'important');
      el.style.setProperty('align-items', 'center', 'important');
      el.style.setProperty('justify-content', 'center', 'important');
      el.style.setProperty('gap', '8px', 'important');
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
        sp(addCartBtn, 'gap', '8px');
        sp(addCartBtn, 'background', '#c9a84c');
        // these styles are inline !important, so a stylesheet :hover can never
        // out-rank them — the hover swap has to be scripted
        if (!addCartBtn.dataset.afHoverBound) {
          addCartBtn.dataset.afHoverBound = '1';
          addCartBtn.addEventListener('mouseenter', function(){ sp(addCartBtn, 'background', '#8b6a2b'); });
          addCartBtn.addEventListener('mouseleave', function(){ sp(addCartBtn, 'background', '#c9a84c'); });
        }
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
        sp(viewBtn, 'gap', '8px');
        sp(viewBtn, 'background', '#1a1a1a');
        // inline !important styles: hover must be scripted, same as Add to Cart
        if (!viewBtn.dataset.afHoverBound) {
          viewBtn.dataset.afHoverBound = '1';
          viewBtn.addEventListener('mouseenter', function(){ sp(viewBtn, 'background', '#333'); });
          viewBtn.addEventListener('mouseleave', function(){ sp(viewBtn, 'background', '#1a1a1a'); });
        }
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
  $product = af_wc_product();
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

// ─────────────────────────────────────────────────────────────
// ONE PRICE ROW EVERYWHERE: what you pay, then what it was, then the saving.
//
//     $80.00  $121.21  (34% off)
//
// The site renders product cards in at least five different ways — the shop
// grid, the homepage sliders, Trending Today, the related/wishlist rows and
// Quick View — and each carries its own price markup. Some put the struck
// price first, some omit the percentage entirely, so the same product read
// differently depending on where you met it. The price a customer pays
// should lead every time.
//
// Rows are found by looking for the struck price itself — a <del> or an
// .old-price — and taking its parent, rather than by guessing at class names
// that differ per card. The current price is whatever is left in the row; if
// it is a bare text node with no element around it, one is added, because
// CSS cannot reorder what is not an element.
//
// The cart's own "price before discount" line uses <s>, not <del>, so it is
// untouched — its layout is a table and reordering would wreck it.
// ─────────────────────────────────────────────────────────────
add_action('wp_footer', function () {
    if (is_admin()) return;
    ?>
<script>
(function(){
  function num(t){
    t = (t || '').replace(/[^0-9.,]/g, '').replace(/,/g, '');
    var v = parseFloat(t);
    return isFinite(v) ? v : 0;
  }
  function fix(row){
    if (!row || row.dataset.afPrice) return;
    row.dataset.afPrice = '1';

    var was = row.querySelector(':scope > del, :scope > .old-price');
    if (!was) return;
    var oldV = num(was.textContent);
    if (oldV <= 0) return;

    // The current price: an <ins> when the markup has one, otherwise the
    // text left over once the struck price is set aside. Bare text gets
    // wrapped so it can be ordered and weighted.
    var now = row.querySelector(':scope > ins, :scope > .current-price, :scope > .af-now');
    if (!now) {
      var moved = [], n;
      for (var i = 0; i < row.childNodes.length; i++) {
        n = row.childNodes[i];
        if (n === was || was.contains(n)) continue;
        if (n.nodeType === 3 && !n.nodeValue.trim()) continue;
        // The saving belongs to the row, not inside the pay price. Sweeping
        // the theme's own .discount in here made the wrapper as narrow as
        // "$80.00" and stacked the two inside it — the pay price on one line,
        // the saving on the next, overflowing up into the rating row.
        if (n.nodeType === 1 && n.classList && (
              n.classList.contains('af-pct-off') ||
              n.classList.contains('discount') ||
              n.classList.contains('discount-percentage'))) continue;
        moved.push(n);
      }
      if (!moved.length) return;
      now = document.createElement('span');
      now.className = 'af-now';
      moved[0].parentNode.insertBefore(now, moved[0]);
      moved.forEach(function(x){ now.appendChild(x); });
    }
    var newV = num(now.textContent);
    if (!(newV > 0) || newV >= oldV) return;

    row.classList.add('af-pricerow');

    // one percentage per row — never a second next to the theme's own
    if (!row.querySelector('.af-pct-off, .af-disc-badge, .discount, .discount-percentage')) {
      var b = document.createElement('span');
      b.className = 'af-pct-off';
      b.textContent = '(' + Math.round((oldV - newV) / oldV * 100) + '% off)';
      row.appendChild(b);
    }
  }
  function scan(){
    // find the rows by the struck price, not by a class name that differs
    // between every card variant on the site
    document.querySelectorAll('del, .old-price').forEach(function(el){
      if (el.closest('.af-ct-was')) return;         // the cart totals table
      fix(el.parentElement);
    });
  }
  if (document.readyState !== 'loading') scan();
  else document.addEventListener('DOMContentLoaded', scan);
  window.addEventListener('load', scan);
  var t = null;
  try {
    new MutationObserver(function(){ clearTimeout(t); t = setTimeout(scan, 120); })
      .observe(document.body, { childList:true, subtree:true });
  } catch(e){}
})();
</script>
<style id="af-pricerow-style">
/* Order is stated here rather than in the markup, so it holds however each
   card happens to nest its two prices. */
/* af-pricerow, NOT af-price-row: the listing toolbar's min/max price FILTER
   already owns that name, and its rules (align-items, gap, and a span colour)
   were landing on every card price on the same page. */
.af-pricerow{display:flex !important;align-items:baseline !important;flex-wrap:wrap !important;
  gap:4px 7px !important;margin:0 !important;}
/* Whatever the theme sets on these — display:block, width:100%, a float —
   would put each part on its own line inside a flex row. Neutralise it. */
.af-pricerow > *{flex:0 0 auto !important;width:auto !important;max-width:none !important;
  min-width:0 !important;float:none !important;margin:0 !important;clear:none !important;}
.af-pricerow > ins,.af-pricerow > .af-now,.af-pricerow > .current-price{order:1;
  text-decoration:none !important;font-weight:700;}
.af-pricerow > del,.af-pricerow > .old-price{order:2;opacity:.65;font-weight:400;
  text-decoration:line-through;}
.af-pricerow > .af-pct-off,.af-pricerow > .discount,
.af-pricerow > .discount-percentage{order:3;}
.af-pct-off{color:#4caf2f;font-weight:700;font-size:.85em;white-space:nowrap;
  text-decoration:none !important;}
/* The pay price is one line, whatever ends up inside it. A card that already
   nests its saving in there (or any theme markup that turns a child into a
   block) would otherwise stack them and grow the wrapper past the row. */
.af-pricerow > .af-now{display:inline-flex !important;flex-direction:row !important;
  flex-wrap:nowrap !important;align-items:baseline !important;gap:6px !important;
  width:auto !important;white-space:nowrap !important;}
.af-pricerow .af-now > *{display:inline !important;width:auto !important;
  max-width:none !important;float:none !important;margin:0 !important;
  padding:0 !important;white-space:nowrap !important;}
</style>
    <?php
}, 41);

// Discount badge (fires after price, inside the product link — span is OK here)
add_action('woocommerce_after_shop_loop_item_title', function() {
  $product = af_wc_product();
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
  // shipping wording comes from af_shipping_copy() so the badge, its popup,
  // the announcement bar and the chatbot always say the same thing
  var AF_SHIP = <?php echo wp_json_encode(af_shipping_copy()); ?>;
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
      var labelMap2 = [AF_SHIP.label,'High Resolution','Premium Frames','Secure Payment'];
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
    var labelMap = [AF_SHIP.label,'High Resolution','Premium Frames','Secure Payment'];
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

// 11a. The row's video ids, as plain text at /?af_pim_ids=1.
// This exists for the reel-fetch tool on the OWNER'S machine — the one
// address YouTube actually serves, after downloads from GitHub's runners and
// the work container were both refused (datacenter addresses, measured, three
// strikes). The tool asks the site which videos the row uses, fetches them at
// home, and uploads them named by id. Public and read-only on purpose: these
// ids already sit in the homepage markup and on the public channel, so this
// discloses nothing — it only saves the tool parsing HTML.
add_action('init', function () {
    if (!isset($_GET['af_pim_ids'])) return;
    $channel = 'UC_GX4vXRQrN4GsvSfgmZxYw';
    $ids = get_transient('af_yt_ids3_' . $channel);
    if (!is_array($ids) || !$ids) $ids = get_option('af_yt_ids3_lastgood_' . $channel);
    $ids = is_array($ids) ? array_values(array_filter($ids)) : array();
    // NOT sanitize_key: it lowercases, and video ids are case-sensitive.
    // The output rule is the id's own alphabet, nothing else escapes.
    $ids = array_values(array_filter($ids, function ($v) {
        return is_string($v) && preg_match('/^[A-Za-z0-9_-]{11}$/', $v);
    }));

    // ?af_pim_ids=missing lists only the videos with no local copy yet, so the
    // owner's tool can re-run without downloading and re-uploading the whole
    // set every time — which would pile up a duplicate attachment per reel.
    if (isset($_GET['af_pim_ids']) && $_GET['af_pim_ids'] === 'missing') {
        $have = get_option('af_pim_local');
        if (!is_array($have)) $have = array();
        $up  = wp_get_upload_dir();
        $dir = trailingslashit($up['basedir']) . 'pim/';
        $ids = array_values(array_filter($ids, function ($v) use ($have, $dir) {
            return !isset($have[$v]) && !file_exists($dir . $v . '.mp4');
        }));
    }

    nocache_headers();
    header('Content-Type: text/plain; charset=utf-8');
    // The sentinel line lets a caller tell "the endpoint answered and the
    // list is empty" apart from "something else answered" — a cached page, a
    // maintenance screen, a challenge. An empty body cannot make that
    // distinction, and one misread of it cost a deploy once already.
    echo "#af-pim-ids\n";
    echo implode("\n", $ids);
    exit;
});

// 11. Products In Motion — circular video slider (PHP-rendered, no JS dependency)
add_action('wp_footer', function() {
    if (!is_front_page()) return;

    // Fetch video IDs, cached 1 hour. (Key bumped to _ids3_ to bypass stale cache.)
    $channel = 'UC_GX4vXRQrN4GsvSfgmZxYw';
    $ids = get_transient('af_yt_ids3_' . $channel);
    // Treat a too-small cached list (e.g. only the single Elementor video, left
    // over from a moment when the YouTube RSS fetch was blocked) as needing a
    // rebuild — otherwise the 1-hour transient pins the row to one video.
    $af_yt_titles = array();
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
                    if (preg_match('/video:([A-Za-z0-9_-]{11})/', (string)$entry->id, $m)) {
                        $ids[] = $m[1];
                        // Keep the title too — the cards carry a caption now.
                        // Stored separately from $ids on purpose: that list has
                        // a last-known-good fallback tuned to survive a blocked
                        // fetch, and changing its shape would put that at risk.
                        $t = trim((string) $entry->title);
                        if ($t !== '') $af_yt_titles[$m[1]] = $t;
                    }
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
        // Merge rather than replace: a fetch that returns fewer entries than
        // last time must not cost us captions we already had.
        if ($af_yt_titles) {
            $known = get_option('af_yt_titles_' . $channel);
            update_option('af_yt_titles_' . $channel,
                array_merge(is_array($known) ? $known : array(), $af_yt_titles), false);
        }
    }
    // ($titles used to feed a caption on each card; the row now shows only
    // the moving picture, so nothing here reads them — the pim tools still do.)

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
    overflow:hidden;          /* the track runs wider than the page on purpose */
}
/* The row scrolls by itself, continuously, in one direction. Two copies of
   the same cards sit end to end and the track slides exactly one copy's width
   before the animation repeats — so the seam lands where the first copy began
   and the loop is invisible. Animating a transform keeps it on the compositor:
   no layout per frame, which is what makes it smooth rather than steppy. */
.af-pim-vp {
    width:100%;
    overflow:hidden;
    -webkit-mask-image:linear-gradient(90deg,transparent 0,#000 40px,#000 calc(100% - 40px),transparent 100%);
            mask-image:linear-gradient(90deg,transparent 0,#000 40px,#000 calc(100% - 40px),transparent 100%);
}
.af-pim-track {
    display:flex;
    flex-direction:row;
    flex-wrap:nowrap;
    align-items:stretch;
    width:max-content;
    /* Spacing lives on the cards as a margin, NOT as flex `gap`. With gap the
       track is (n × card) + (n−1 × gap), so translating -50% lands half a gap
       short of where the second copy begins and the row twitches once per
       loop. A margin belongs to the card, so each copy is exactly n × pitch
       and half of it is exactly one copy. */
    will-change:transform;
    animation:af-pim-marquee var(--af-pim-dur,60s) linear infinite;
}
@keyframes af-pim-marquee {
    from { transform:translate3d(0,0,0); }
    /* half, because the track holds the cards twice */
    to   { transform:translate3d(-50%,0,0); }
}
/* The row eases to a stop rather than freezing mid-stride — a visitor
   reaching for a card should not be chasing a target that stops dead. The
   easing itself is done in script, by ramping the animation's playback rate,
   because animation-duration cannot be transitioned. This class is the
   fallback for browsers that will not hand the animation over: a hard pause
   is worse than an eased one and far better than none. */
.af-pim-track.af-pim-hold { animation-play-state:paused; }

.af-pim-card {
    flex:0 0 auto;
    margin-right:22px;
    /* MEASURED (headless check, deploy 889): the card was sized by
       aspect-ratio alone, and a height that comes from aspect-ratio is not a
       DEFINITE height — so every `height:100%` child resolved to auto and
       collapsed to its own natural shape. The poster came out 290x163 inside a
       290x516 card, which is exactly the black band above and below that the
       owner reported, and the player was doing the same thing.
       Width was never affected, because a percentage width had a definite
       width to resolve against: 290px measured, 290px expected.
       So the card states its height outright. One custom property feeds both
       dimensions, so they cannot drift apart. */
    --af-pim-w: clamp(150px, 21vw, 290px);
    --af-pim-h: calc(var(--af-pim-w) * 16 / 9);
    width:var(--af-pim-w);
    height:var(--af-pim-h);
    aspect-ratio:9 / 16;          /* same shape, kept as the stated intent */
    border-radius:14px;
    overflow:hidden;
    position:relative;
    background:#111;
    cursor:pointer;
    box-shadow:0 6px 22px rgba(0,0,0,.28);
    /* A long, soft curve. The card is already sliding sideways, so a quick
       snappy lift reads as a jolt on top of motion that is already happening. */
    transition:transform .45s cubic-bezier(.22,.61,.36,1),
               box-shadow .45s cubic-bezier(.22,.61,.36,1);
    transform:translate3d(0,0,0);            /* its own layer, so the lift does
                                                not repaint the whole row */
}
.af-pim-card:hover {
    transform:translate3d(0,-6px,0) scale(1.02);
    box-shadow:0 14px 38px rgba(0,0,0,.42);
}
/* The poster stays underneath the player for the whole life of the card. A
   YouTube embed shows black while it negotiates, and with several playing at
   once the browser throttles some of them — that is exactly how this row
   ended up a wall of black circles with spinners before. Keeping the still
   behind means the worst case is a card that is not moving yet, never a hole. */
.af-pim-thumb {
    position:absolute;
    inset:0;
    /* !important, and it is earned. Measured (deploy 890): with the card's
       height now definite at 516px, this image STILL came out 290x163 — which
       is its own 1280x720 shape, not 100% of anything. Something outside this
       stylesheet is forcing the height, and the near-universal culprit is a
       theme's `img { max-width:100%; height:auto }` reset. That is a sensible
       rule for content images and a wrong one for a deliberately-cropped
       cover, so it is overridden here and nowhere else. */
    width:100% !important;
    height:100% !important;
    max-width:none !important;
    object-fit:cover;
    object-position:center;
    z-index:1;
}
/* The site's own copy of the video — the ONLY player the row ever creates.
   The YouTube embed is gone from these cards on purpose: it paints a title
   bar and, for looped embeds, a centre pause with previous/next INSIDE its
   frame, and no page CSS reaches in there. (Its removal also buries an
   embarrassment: the title-crop rule that used to sit here had a doubled
   comment closer, so the prose became junk inside the rule and CSS error
   recovery ate the very declaration the geometry hung on — which is why the
   title text survived deploy after deploy.) A real <video> needs none of
   that arithmetic: object-fit covers the 9:16 card and crops its own sides,
   and there is no chrome because there is no player UI at all. The max-width
   lift is the same lesson the poster taught — the theme's media reset would
   otherwise clamp it. */
.af-pim-card video.af-pim-video {
    position:absolute;
    inset:0;
    width:100% !important;
    height:100% !important;
    max-width:none !important;
    max-height:none !important;
    object-fit:cover;
    object-position:center;
    /* The reels are letterboxed at source, so a local copy carries the same
       bands as the embed and needs the same zoom past them. */
    transform:scale(var(--af-pim-zoom));
    border:0;
    pointer-events:none;
    /* Above the moving preview (2), not level with it. Both are absolutely
       positioned, and the player is inserted as the card's FIRST child - so at
       equal z-index the preview, being later in the document, would paint over
       the real video. The mp4 is the better thing whenever it exists. */
    z-index:4;
    opacity:0;
    transition:opacity .5s ease;
}
.af-pim-card.af-pim-live video.af-pim-video { opacity:1; }
/* NO YOUTUBE PLAYER IN THE CARDS — measured twice now, and final.
   The embed experiment (deploys 906-913) ended where the original decision
   said it would: with parameters, sizing, a working API handshake and a
   page-driven loop all in place, the player STILL drew a persistent centre
   pause chip on playing cards (owner's recordings, 09:55 and 11:34 — the
   second one after every steering fix was live). That chip is the player's
   own UI, inside its frame, where no page CSS reaches and nothing can cover
   it without covering the video. Only a same-origin <video> can promise a
   card with nothing on it but the moving picture; until its file exists, the
   drifting stills below make that same promise. */
/* A card whose reel has not reached the site yet keeps its poster — and the
   poster DRIFTS, a slow alternating zoom, so the card reads as alive rather
   than stalled. Costless: it is one compositor transform, it sits underneath
   the video whenever one is playing, and it stops for anyone who asked the
   OS for less motion. */
.af-pim-card .af-pim-thumb {
    animation:af-pim-drift 16s ease-in-out infinite alternate;
    will-change:transform;
}
/* The zoom that cuts the baked-in bands.
   MEASURED, deploy 906: every image now covers its card exactly (312x556 in a
   290x516 card, all 20 elements), and the owner's recording STILL shows black
   above and below the picture. So the bands are not the page's fit — they are
   painted into the source. The reels were exported letterboxed, and YouTube's
   thumbnails inherit it, which is why no amount of object-fit removed them:
   cover-cropping a bordered picture keeps the border.
   The remedy is the only one available to a page that cannot re-export the
   video: scale past them. ~12% of the height at each end, so 1.3 clears both
   with a little to spare, and the drift rides on top of that baseline instead
   of starting from 1. */
:root, .af-pim-card { --af-pim-zoom: 1.30; }
@keyframes af-pim-drift {
    from { transform:scale(var(--af-pim-zoom)); }
    to   { transform:scale(calc(var(--af-pim-zoom) * 1.09)); }
}
/* FOUR frames per card, cross-fading.
   Every route to the actual mp4 is now measured and closed: YouTube refuses
   downloads from GitHub runners and from the agent container, the web host
   cannot reach its own URL, and it cannot fetch or run yt-dlp either. But
   YouTube publishes three storyboard stills per video at hq1/hq2/hq3 — real,
   different moments FROM the reel — alongside the cover frame. Cross-fading
   those four, each one still drifting, is motion taken from the video itself,
   served as plain images: instant, no player, and therefore none of the
   chrome a player insists on drawing.
   Staggered by one quarter of the cycle each, so exactly one frame is opaque
   at a time and the card is never blank. */
.af-pim-card .af-pim-f1,
.af-pim-card .af-pim-f2,
.af-pim-card .af-pim-f3 { opacity:0; }
.af-pim-card .af-pim-f0 { animation:af-pim-drift 26s ease-in-out infinite alternate,
                                    af-pim-cross 24s linear infinite -0s; }
.af-pim-card .af-pim-f1 { animation:af-pim-drift 26s ease-in-out infinite alternate-reverse,
                                    af-pim-cross 24s linear infinite -6s; }
.af-pim-card .af-pim-f2 { animation:af-pim-drift 26s ease-in-out infinite alternate,
                                    af-pim-cross 24s linear infinite -12s; }
.af-pim-card .af-pim-f3 { animation:af-pim-drift 26s ease-in-out infinite alternate-reverse,
                                    af-pim-cross 24s linear infinite -18s; }
/* Opaque for a quarter of the cycle, with a short fade at each end. The cover
   frame alone stays visible if hq1-3 ever 404: a broken <img> paints nothing,
   and the frame beneath it is still there. */
/* The fade-out of one frame must be exactly the fade-in of the next, or the
   total dips and the whole card visibly darkens four times a cycle. With four
   frames staggered by 25%: hold from 5% to 25%, fall to zero by 30% - so the
   next frame, whose own 0-5% rise is this one's 25-30% fall, sums to 1
   throughout. Checked numerically over the full cycle before shipping; the
   first attempt held 3%-22% and dipped to 0.4. */
@keyframes af-pim-cross {
    0%    { opacity:0; }
    5%    { opacity:1; }
    25%   { opacity:1; }
    30%   { opacity:0; }
    100%  { opacity:0; }
}
/* The moving preview sits above the stills and hides them. onerror removes
   the element outright rather than leaving a broken-image glyph, so a card
   whose animation disappears later falls back to the cross-fade instead of
   showing a torn icon - which is what one card in the owner's recording was
   doing. */
.af-pim-card .af-pim-anim {
    position:absolute;
    inset:0;
    width:100% !important;
    height:100% !important;
    max-width:none !important;
    object-fit:cover;
    object-position:center;
    z-index:2;
    pointer-events:none;
    transform:scale(var(--af-pim-zoom));
}
/* While a video covers the posters there is nothing to see underneath, so
   stop paying the compositor for any of them. */
.af-pim-card.af-pim-live .af-pim-thumb { animation-play-state:paused; }
@media (prefers-reduced-motion: reduce){
    /* No drift — but the zoom is not decoration, it is what hides the bands
       burnt into the source, so it stays. */
    .af-pim-card .af-pim-thumb { animation:none; transform:scale(var(--af-pim-zoom)); }
    .af-pim-card .af-pim-f1,
    .af-pim-card .af-pim-f2,
    .af-pim-card .af-pim-f3 { display:none; }
}
.af-pim-overlay {
    position:absolute;
    inset:0;
    /* Above every media layer, so the click target is this and never the
       player underneath it. */
    z-index:5;
}
@media (max-width:768px){
    .af-pim-card  { margin-right:14px; }
}
/* Someone who has asked for less motion gets a still row they can scroll
   themselves, rather than one that moves on its own. */
@media (prefers-reduced-motion: reduce){
    .af-pim-track { animation:none; }
    .af-pim-vp    { overflow-x:auto; }
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
    // Build one card per video. Two copies of the run are emitted below, so
    // the marquee can slide exactly one copy's width and start over without a
    // visible seam.
    $cards_html = '';
    // The row plays the site's OWN copy of each video when one exists —
    // uploads/pim/<videoid>.mp4, mirrored there by the deploy. A same-origin
    // <video> starts in the time of one range request, with no YouTube
    // negotiation, no spinner and no title text; the owner timed the embeds at
    // several seconds of black per card, which is exactly that negotiation.
    // YouTube still owns the lightbox, where the full player belongs. A card
    // whose mp4 has not been mirrored yet keeps the embed path, so a missing
    // file costs a slower card, never a dead one.
    $pim_up   = wp_get_upload_dir();
    $pim_dirf = trailingslashit($pim_up['basedir']) . 'pim/';
    $pim_urlf = trailingslashit($pim_up['baseurl']) . 'pim/';
    // tools/pim-local-video.php also matches videos ALREADY in the Media
    // Library to the row's videos by title, so a reel the studio has uploaded
    // itself serves its card without anything being downloaded at all.
    $pim_local = get_option('af_pim_local');
    if (!is_array($pim_local)) $pim_local = array();
    // A MOVING preview per card, discovered by tools/pim-anim-probe.php: an
    // animated WebP from YouTube's image CDN. It is an image to the browser,
    // so it autoplays and loops with no player and therefore none of a
    // player's chrome. Empty until the probe has run, or for a video that has
    // no animation - those cards keep the cross-fading stills.
    $pim_anim = get_option('af_pim_anim');
    if (!is_array($pim_anim)) $pim_anim = array();
    // The stills were rendering 290x163 inside a 516px-tall card — full width,
    // intrinsic height — so every card carried a black band above and below the
    // picture. The stylesheet already says height:100% !important on
    // .af-pim-thumb, and it was still losing, which means something later in
    // the cascade carries !important of its own on an img selector. An inline
    // declaration marked important is the top of the author cascade: nothing in
    // any stylesheet can outrank it. Carried per element rather than added to
    // the class, precisely because the class rule is the thing being overruled.
    $thumb_fit = 'width:100%!important;height:100%!important;max-width:none!important;'
               . 'max-height:none!important;object-fit:cover!important;object-position:center!important;';
    foreach ($ids as $vid) {
        $vid = esc_attr($vid);
        $thumb_hq  = "https://img.youtube.com/vi/{$vid}/hqdefault.jpg";
        $thumb_max = "https://img.youtube.com/vi/{$vid}/maxresdefault.jpg";
        // Three sources, best first. The true reel (fetched or matched in the
        // Media Library) always wins. <id>.anim.mp4 is the site's OWN motion
        // clip, built by the fetch workflow from the reel's published frames
        // for every reel YouTube refuses to hand over — a real file, played by
        // a real <video>, with nothing drawn on it. The ids endpoint's
        // "missing" list deliberately ignores .anim.mp4, so the hunt for the
        // original continues daily and replaces the built clip the moment it
        // lands, under the same card without another deploy.
        $mp4       = file_exists($pim_dirf . $vid . '.mp4') ? $pim_urlf . $vid . '.mp4'
                   : (isset($pim_local[$vid]) ? $pim_local[$vid]
                   : (file_exists($pim_dirf . $vid . '.anim.mp4') ? $pim_urlf . $vid . '.anim.mp4' : ''));
        // The owner's spec for this row (2026-08-31): ONLY the moving picture.
        // No title text — so the caption is gone, not styled away. No player
        // element in the markup either: the JS builds one, only for cards on
        // screen and never more than a handful at once. Both the card's player
        // and the lightbox derive their URLs from data-vid.
        $cards_html .= '
<div class="af-pim-card" data-vid="' . $vid . '"'
  . ($mp4 !== '' ? ' data-mp4="' . esc_attr($mp4) . '"' : '') . '>
  <img class="af-pim-thumb af-pim-f0" style="' . $thumb_fit . '" src="' . $thumb_max . '" onerror="this.src=\'' . $thumb_hq . '\';this.onerror=null" alt="" loading="lazy" decoding="async">
  <img class="af-pim-thumb af-pim-f1" style="' . $thumb_fit . '" src="https://img.youtube.com/vi/' . $vid . '/hq1.jpg" alt="" loading="lazy" decoding="async">
  <img class="af-pim-thumb af-pim-f2" style="' . $thumb_fit . '" src="https://img.youtube.com/vi/' . $vid . '/hq2.jpg" alt="" loading="lazy" decoding="async">
  <img class="af-pim-thumb af-pim-f3" style="' . $thumb_fit . '" src="https://img.youtube.com/vi/' . $vid . '/hq3.jpg" alt="" loading="lazy" decoding="async">'
  . (isset($pim_anim[$vid]) ? '
  <img class="af-pim-anim" style="' . $thumb_fit . '" src="' . esc_url($pim_anim[$vid]) . '" alt="" loading="lazy" decoding="async" onerror="this.remove()">' : '') . '
  <div class="af-pim-overlay"></div>
</div>';
    }
    // Seconds for one full pass. Tied to the number of cards so adding videos
    // makes the row longer, not faster — the apparent speed stays constant.
    $pim_dur = max(20, count($ids) * 5);
?>
<?php
/* Open the connections the players will need before any of them is created.
   The first embed otherwise pays for DNS, TLS and the redirect before a single
   frame arrives, which is most of the delay a visitor sees on arrival — and
   the preload above only helps if the connection is ready when it fires. */
?>
<link rel="preconnect" href="https://www.youtube-nocookie.com" crossorigin>
<link rel="preconnect" href="https://i.ytimg.com" crossorigin>
<link rel="preconnect" href="https://www.google.com" crossorigin>
<link rel="dns-prefetch" href="https://www.youtube-nocookie.com">

<div class="af-pim-wrap" id="afPimWrap">
  <div class="af-pim-vp" id="afPimVp">
    <div class="af-pim-track" id="afPimTrack" style="--af-pim-dur:<?php echo (int) $pim_dur; ?>s">
      <?php echo $cards_html; ?>
      <?php
      /* The second copy is decorative: it exists to make the loop seamless and
         must not be announced twice to a screen reader. */
      ?>
      <span class="af-pim-dupe" aria-hidden="true" style="display:contents"><?php echo $cards_html; ?></span>
    </div>
  </div>
</div>

<!-- Lightbox -->
<div class="af-pim-lb" id="afPimLb">
  <button class="af-pim-lb-x" id="afPimLbX">&times;</button>
  <!-- No src attribute: src="" resolves to the page's own URL, which made
       every page load a full hidden copy of itself inside this lightbox. -->
  <iframe id="afPimLbFrame" allow="autoplay; fullscreen; encrypted-media" allowfullscreen></iframe>
</div>

<script>
(function(){
    var track = document.getElementById('afPimTrack');
    var vp    = document.getElementById('afPimVp');
    var lb    = document.getElementById('afPimLb');
    var lbFr  = document.getElementById('afPimLbFrame');
    var lbX   = document.getElementById('afPimLbX');
    var cards = Array.prototype.slice.call(document.querySelectorAll('#afPimTrack .af-pim-card'));

    var reduceMotion = false;
    try { reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches; } catch(e){}

    // ── Easing the row to a stop and back ───────────────────────────────
    // The scroll itself stays a CSS animation, so at a steady speed it runs on
    // the compositor and costs the main thread nothing — which is the whole
    // reason it glides while six video players are working. What CSS cannot do
    // is change speed gradually: animation-duration is not transitionable, and
    // animation-play-state only has "running" and "stopped", so pausing on
    // hover means stopping dead mid-stride.
    //
    // So take hold of the very same animation object and ramp its playbackRate
    // instead. Script runs only during the ~450ms of a ramp; the rest of the
    // time the compositor is left alone. If the browser will not hand the
    // animation over, the class-based hard pause below still works.
    var anim = null, haveAnim = false;
    function marquee(){
        if (haveAnim) return anim;
        haveAnim = true;
        try {
            var list = track.getAnimations ? track.getAnimations() : [];
            for (var i = 0; i < list.length; i++) {
                if (list[i].animationName === 'af-pim-marquee') { anim = list[i]; break; }
            }
            if (!anim && list.length) anim = list[0];
        } catch (e) { anim = null; }
        return anim;
    }

    var rateNow = 1, rateWant = 1, ramping = false;
    function ease(k){ return k < 0.5 ? 2*k*k : 1 - Math.pow(-2*k + 2, 2) / 2; }
    function speed(target){
        rateWant = target;
        var a = marquee();
        if (!a) {                                   // no handle on it: hard pause
            track.classList.toggle('af-pim-hold', target === 0);
            return;
        }
        track.classList.remove('af-pim-hold');
        if (ramping) return;                        // the running ramp reads rateWant
        ramping = true;
        var from = rateNow, t0 = 0;
        (function step(now){
            if (!t0) t0 = now;
            var k = Math.min(1, (now - t0) / 450);
            rateNow = from + (rateWant - from) * ease(k);
            try { a.playbackRate = rateNow; } catch (e) {}
            if (k < 1) { requestAnimationFrame(step); return; }
            ramping = false;
            // The target may have changed while this ramp was running — chase
            // it rather than settling on a stale one.
            if (Math.abs(rateNow - rateWant) > 0.01) speed(rateWant);
        })(0);
    }

    // How many players may run at once. The row used to create one per card
    // and roughly twenty autoplaying YouTube embeds made the browser throttle
    // them — several tiles just sat black behind a spinner. Only the cards on
    // screen get a player, and never more than this many.
    var MAX_LIVE = 6;
    var live = [];      // in the order they started, so the oldest is dropped first

    function stop(card){
        var i = live.indexOf(card);
        if (i !== -1) live.splice(i, 1);
        card.classList.remove('af-pim-live');
        var fr = card.querySelector('video.af-pim-video');
        if (!fr) return;
        // Marked BEFORE the timer, because the pause/load below rejects the
        // element's pending play() promise with AbortError, and the rejection
        // handler must be able to tell that apart from a genuinely broken
        // file. Without it an ordinary eviction — the marquee moving a card
        // out, MAX_LIVE dropping the oldest, the lightbox opening — looked
        // identical to a media error and struck data-mp4 off the card for the
        // rest of the page, so a card that HAS its reel went permanently
        // quiet. That is the exact failure this whole row was meant to end.
        fr.dataset.afTearingDown = '1';
        // Let the fade finish before the node goes, so a card leaving the row
        // dissolves back to its poster instead of blinking. The handle is kept
        // so a card that comes straight back can cancel its own teardown
        // instead of being blocked by the node it is about to inherit.
        card._afPimTeardown = setTimeout(function(){
            card._afPimTeardown = null;
            try { fr.pause(); fr.removeAttribute('src'); fr.load(); } catch(e){}
            if (fr.parentNode) fr.parentNode.removeChild(fr);
        }, 500);
    }
    // The YouTube in-card player was removed here for the second and final
    // time. The full attempt — controls=0, muted autoplay, a working API
    // handshake, a page-driven loop that never let the ended state draw —
    // still ended with the player painting a persistent centre pause chip on
    // playing cards (owner's recordings, 09:55 and 11:34, the second with
    // every steering fix live). The chip is the player's own UI, inside its
    // frame, beyond page CSS. Cards therefore play ONLY same-origin files;
    // without one, the drifting stills stand in. Do not put the embed back.

    function start(card){
        if (reduceMotion) return;
        // Same-origin files ONLY — see the note above on the embed's removal.
        // A card without its file keeps the drifting stills, which cannot
        // show a button, and upgrades itself the moment the file appears.
        var mp4 = card.getAttribute('data-mp4');
        if (!mp4) return;
        if (lb.classList.contains('open')) return;      // the lightbox has the stage

        // A card can come back inside its own 500ms fade — a scroll bounce, or
        // the marquee nudging it over the boundary twice. Its old node is
        // still here; adopt it rather than declining, which used to leave the
        // card visible, poster-only and outside live[], with no further
        // observer callback to rescue it until it crossed the margin again.
        var old = card.querySelector('video.af-pim-video');
        if (old) {
            if (card._afPimTeardown) {
                clearTimeout(card._afPimTeardown);
                card._afPimTeardown = null;
                delete old.dataset.afTearingDown;
                if (live.indexOf(card) === -1) {
                    while (live.length >= MAX_LIVE) stop(live[0]);
                    live.push(card);
                }
                if (!old.paused) card.classList.add('af-pim-live');
                else { var rp = old.play(); if (rp && rp.catch) rp.catch(function(){}); }
            }
            return;                                     // never two players in one card
        }

        while (live.length >= MAX_LIVE) stop(live[0]);
        live.push(card);

        var v = document.createElement('video');
        v.className = 'af-pim-video';
        v.muted = true; v.loop = true; v.playsInline = true;
        v.setAttribute('muted', '');                 // the property alone does
        v.setAttribute('playsinline', '');           // not survive some parsers
        v.preload = 'auto';
        v.src = mp4;
        // Only reveal if this node is still the card's player. A 'playing'
        // event can land inside a teardown window, and re-adding the class to
        // a card whose node is about to vanish left it flagged live with no
        // video — so the NEXT player was born under opacity:1 and popped in
        // instead of cross-fading.
        v.addEventListener('playing', function(){
            if (v.dataset.afTearingDown) return;
            if (v.parentNode !== card) return;
            card.classList.add('af-pim-live');
        });

        // A bad FILE ends at the poster — never at an embed, and never on a
        // mere interruption. An eviction pauses the element, which rejects the
        // pending play() with AbortError; treating that as a broken file is
        // what used to demote a perfectly good card permanently.
        var fell = false;
        function giveUp(){
            if (fell) return;
            fell = true;
            var i = live.indexOf(card);
            if (i !== -1) live.splice(i, 1);
            card.classList.remove('af-pim-live');
            try { v.pause(); } catch(e){}
            if (v.parentNode) v.parentNode.removeChild(v);
            card.removeAttribute('data-mp4');       // this file is no good
        }
        v.addEventListener('error', giveUp);
        card.insertBefore(v, card.firstChild);
        var p = v.play();
        if (p && p.catch) p.catch(function(err){
            // Interrupted, not broken: leave data-mp4 alone so the card plays
            // again the next time it comes round.
            if (v.dataset.afTearingDown) return;
            if (err && (err.name === 'AbortError' || err.name === 'NotAllowedError')) return;
            giveUp();
        });
    }

    // Play what is on screen. The row moves continuously, so this fires as
    // cards drift in and out — the generous margin means a card is already
    // playing by the time it is properly visible, rather than starting from
    // black in front of the visitor.
    try {
        var io = new IntersectionObserver(function(entries){
            entries.forEach(function(e){
                if (e.isIntersecting) start(e.target);
                else                  stop(e.target);
            });
        // The vertical margin is the preload. A card begins loading while the
        // section is still most of a screen below the fold, so by the time the
        // visitor arrives the row is already moving — instead of a wall of
        // stills that blink into life once they have been looked at. The
        // horizontal margin does the same job sideways, for cards about to
        // slide in from the right.
        }, { root: null, rootMargin: '700px 260px 700px 260px', threshold: 0.2 });
        cards.forEach(function(c){ io.observe(c); });
    } catch(e){
        cards.slice(0, MAX_LIVE).forEach(start);        // no observer: play the first few
    }

    // The whole section leaving the viewport stops everything, so nothing
    // burns data or CPU further down the page — and it picks up again on the
    // way back.
    try {
        new IntersectionObserver(function(en){
            en.forEach(function(e){
                if (!e.isIntersecting) { live.slice().forEach(stop); speed(0); }
                else                    { speed(1); }
            });
        // Matched to the card margin above. With a plain threshold this
        // observer fired "not visible" for the whole approach and tore down
        // every player the preload had just built.
        }, { rootMargin: '750px 0px 750px 0px', threshold: 0 })
          .observe(document.getElementById('afPimWrap'));
    } catch(e){}

    // Ease to a stop under the pointer, and back up on the way out.
    if (!reduceMotion) {
        vp.addEventListener('mouseenter', function(){ speed(0); });
        vp.addEventListener('mouseleave', function(){ speed(1); });
        // A background tab should not be animating at all, and coming back to
        // one that kept running looks like the row jumped while you were away.
        document.addEventListener('visibilitychange', function(){
            speed(document.hidden ? 0 : 1);
        });
    }

    // Click still opens the video with sound.
    cards.forEach(function(c){
        var ov = c.querySelector('.af-pim-overlay');
        if (!ov) return;
        ov.addEventListener('click', function(){
            lbFr.src = 'https://www.youtube-nocookie.com/embed/' + c.getAttribute('data-vid')
                     + '?autoplay=1&rel=0&playsinline=1';
            lb.classList.add('open');
            live.slice().forEach(stop);                 // one thing playing at a time
            speed(0);
        });
    });

    function closeLb() {
        lb.classList.remove('open');
        // 'about:blank', not '': an empty src navigates the iframe to the
        // page's own URL, silently re-downloading the whole page.
        lbFr.src = 'about:blank';
        speed(1);                                // the row eases back into motion
        // Opening the lightbox stopped every card. The per-card observer only
        // fires on boundary crossings, so a card already on screen would get
        // no callback and sit as a still poster until the marquee carried it
        // out and back — the row looked frozen after every preview. Restart
        // what is on screen, next frame, once the overlay is really gone.
        requestAnimationFrame(function(){
            cards.forEach(function(c){
                var r = c.getBoundingClientRect();
                if (r.right > 0 && r.left < window.innerWidth && r.bottom > 0
                    && r.top < window.innerHeight) start(c);
            });
        });
    }
    lbX.onclick = closeLb;
    lb.addEventListener('click', function(e){ if (e.target === lb) closeLb(); });
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeLb(); });

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
  // shipping wording comes from af_shipping_copy() so the badge, its popup,
  // the announcement bar and the chatbot always say the same thing
  var AF_SHIP = <?php echo wp_json_encode(af_shipping_copy()); ?>;
  var features = [
    {
      label: AF_SHIP.label,
      icon: '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M20 8h-3V4H3c-1.1 0-2 .9-2 2v11h2c0 1.66 1.34 3 3 3s3-1.34 3-3h6c0 1.66 1.34 3 3 3s3-1.34 3-3h2v-5l-3-4zM6 18.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm13.5-9l1.96 2.5H17V9.5h2.5zm-1.5 9c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/></svg>',
      title: '🚚 ' + AF_SHIP.label,
      // the old copy promised free shipping here and then listed free delivery
      // in four states only — one claim, stated once, from af_shipping_copy()
      body: '<p>' + AF_SHIP.blurb + '</p><h4>📦 How it travels:</h4><ul><li>Smaller unframed prints ship rolled in a protective tube</li><li>Framed and larger pieces ship flat in a corner-protected crate</li><li>Oversize handling, where it applies, is shown at checkout</li></ul><p>Everything is made to order, so allow a few days for production before it ships. Tracking follows by email.</p>'
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
        // Diagonal corner ribbon across the top-left corner. The band's ends
        // run off the card and are clipped by .af-img-wrap (overflow:hidden),
        // leaving a clean gold "SALE!" banner across the corner.
        sp(ribbon,'position',      'absolute');
        sp(ribbon,'top',           '15px');
        sp(ribbon,'left',          '-46px');
        sp(ribbon,'width',         '160px');
        sp(ribbon,'background',    'linear-gradient(135deg,#ecc768,#cf9f2e)');
        sp(ribbon,'color',         '#fff');
        sp(ribbon,'font-size',     '12px');
        sp(ribbon,'font-weight',   '800');
        sp(ribbon,'text-align',    'center');
        sp(ribbon,'padding',       '7px 0');
        sp(ribbon,'transform',     'rotate(-45deg)');
        sp(ribbon,'z-index',       '10');
        sp(ribbon,'letter-spacing','0.12em');
        sp(ribbon,'text-transform','uppercase');
        sp(ribbon,'text-shadow',   '0 1px 2px rgba(0,0,0,.25)');
        sp(ribbon,'line-height',   '1.3');
        sp(ribbon,'min-width',     'unset');
        sp(ribbon,'border-radius', '0');
        sp(ribbon,'margin',        '0');
        sp(ribbon,'display',       'block');
        sp(ribbon,'box-shadow',    '0 3px 8px rgba(0,0,0,.28)');
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
      sp(atcBtn,'gap',            '8px');
      atcBtn.addEventListener('mouseenter', function(){ sp(atcBtn,'background','#8b6a2b'); });
      atcBtn.addEventListener('mouseleave', function(){ sp(atcBtn,'background','#c9a84c'); });
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
    $product = af_wc_product();
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
    if (!($product instanceof WC_Product)) { $product = af_wc_product($product); }
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
// The visible top utility bar (rotating message + Help + phone + currency) has
// been removed. Two things it hosted are kept: the country selector — rendered
// hidden here so the relocation script in its shortcode can still move it
// beside the social icons — and the sticky-header-on-scroll behaviour.
add_action('wp_footer', function() {
    if (is_admin()) return;
    ?>
    <div class="af-ub-hidden-host" aria-hidden="true" style="display:none !important;">
      <?php echo do_shortcode('[af_country_selector]'); ?>
    </div>
    <script>
    (function(){
      // Sticky header on scroll — add class to the theme header
      var header = document.querySelector(
        'header.site-header, #masthead, .site-header, header#header, ' +
        '.postero-header, .header-main, [class*="site-header"], header[class*="header"]'
      );
      if (header) {
        window.addEventListener('scroll', function(){
          var y = window.pageYOffset || document.documentElement.scrollTop;
          if (y > 120) header.classList.add('af-header-stuck');
          else header.classList.remove('af-header-stuck');
        }, { passive: true });
      }
    })();
    </script>
    <style>
    /* Sticky header polish (kept from the old utility-bar block) */
    .af-header-stuck{
      position:sticky !important;top:0 !important;z-index:9999 !important;
      box-shadow:0 4px 18px rgba(0,0,0,.12) !important;
      animation:af-slidedown .3s ease !important;
    }
    @keyframes af-slidedown{from{transform:translateY(-6px);opacity:.85;}to{transform:translateY(0);opacity:1;}}
    /* The relocated selector must stay visible even though its host is hidden. */
    .af-cty.af-cty--inline{display:inline-flex !important;}
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
        <!-- filled in by JS, never by PHP: this page is full-page cached, so a
             server-rendered count would show one visitor's wishlist to everyone -->
        <span class="af-qp-count" id="af-qp-wl-count" hidden>0</span>
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

      // ── one WhatsApp button, not two ──────────────────────────────────
      // A plugin drops its own floating WhatsApp bubble into the same corner
      // as ours. Rather than trust a list of plugin class names, find it by
      // what it is: a WhatsApp link inside a fixed-position element that is
      // not our quick panel. Anything in the page flow (footer, contact,
      // product share) is left alone.
      function dedupeWhatsApp(){
        var links = document.querySelectorAll(
          'a[href*="wa.me"],a[href*="api.whatsapp.com"],a[href*="whatsapp://"],a[href*="web.whatsapp.com"]');
        Array.prototype.forEach.call(links, function(a){
          if (a.closest('.af-quickpanel')) return;          // ours — keep
          var n = a, floating = null;
          for (var i = 0; i < 8 && n && n !== document.body; i++) {
            var pos = getComputedStyle(n).position;
            if (pos === 'fixed' || pos === 'sticky') { floating = n; break; }
            n = n.parentElement;
          }
          if (!floating) return;                            // in-page link — keep
          if (floating.closest('.af-quickpanel') || floating.id === 'af-chat') return;

          // A floating bar can hold far more than WhatsApp: the site has a
          // slide-out panel whose toggle opens a bar of several options, and
          // WhatsApp is only one row of it. Hiding the whole container to
          // remove one duplicate took that entire panel with it — the toggle
          // still showed, but the bar it opened was display:none, so clicking
          // it appeared to do nothing. Remove the duplicate itself and leave
          // everything else in the bar alone.
          var others = floating.querySelectorAll('a[href],button');
          var foreign = 0;
          Array.prototype.forEach.call(others, function (el) {
            if (el === a) return;
            if (/wa\.me|whatsapp/i.test(el.getAttribute('href') || '')) return;
            foreign++;
          });
          if (foreign > 0) {
            // hide just this entry: the link, or the list item wrapping it
            var item = a.closest('li,.item,[class*="item"]') || a;
            if (item === floating || floating.contains(item) === false) item = a;
            item.style.setProperty('display', 'none', 'important');
            return;
          }
          floating.style.setProperty('display', 'none', 'important');
        });
      }

      // ── wishlist count on the floating button ─────────────────────────
      // Computed in the browser, never in PHP: these pages are full-page
      // cached, so a server-rendered number would hand one visitor's wishlist
      // to every other visitor. Reads the plugin's own cookie first, then
      // falls back to whatever counter the header is already showing.
      function wishlistCount(){
        var m = document.cookie.match(/(?:^|;\s*)yith_wcwl_products=([^;]*)/);
        if (m && m[1]) {
          try {
            var arr = JSON.parse(decodeURIComponent(m[1].replace(/\+/g, ' ')));
            if (Array.isArray(arr)) return arr.length;
            if (arr && typeof arr === 'object') return Object.keys(arr).length;
          } catch(e){}
        }
        var el = document.querySelector(
          '.wishlist-count,.wishlist_count,.yith-wcwl-items-count,[class*="wishlist"] .count,[class*="wishlist"] .counter');
        if (el) {
          var n2 = parseInt((el.textContent||'').replace(/[^\d]/g, ''), 10);
          if (!isNaN(n2)) return n2;
        }
        return 0;
      }
      function paintWishlist(){
        var badge = document.getElementById('af-qp-wl-count');
        if (!badge) return;
        var n = wishlistCount();
        badge.textContent = n > 99 ? '99+' : String(n);
        if (n > 0) badge.removeAttribute('hidden'); else badge.setAttribute('hidden','');
      }

      function refresh(){ dedupeWhatsApp(); paintWishlist(); }
      refresh();
      // plugin widgets and counters arrive late and change as the visitor
      // adds items, so re-run on the events that matter rather than once
      window.addEventListener('load', refresh);
      [400, 1200, 2500].forEach(function(t){ setTimeout(refresh, t); });
      ['added_to_wishlist','removed_from_wishlist','yith_wcwl_reload_fragments','added_to_cart']
        .forEach(function(ev){ document.body.addEventListener(ev, paintWishlist); });
      if (window.jQuery) {
        jQuery(document.body).on('added_to_wishlist removed_from_wishlist yith_wcwl_reload_fragments', paintWishlist);
      }
      document.addEventListener('click', function(e){
        if (e.target.closest('[class*="wishlist"],[class*="wcwl"]')) setTimeout(paintWishlist, 900);
      }, true);
    })();
    </script>
    <style>
    .af-quickpanel{position:fixed;right:16px;bottom:20px;z-index:9998;display:flex;flex-direction:column;gap:12px;}
    .af-qp-btn{
      width:48px;height:48px;border-radius:50%;border:none;cursor:pointer;
      /* padding:0 !important — the theme's global button padding (14px 40px) otherwise
         collapses the border-box content area to 0, hiding the SVG icon. */
      padding:0 !important;
      display:flex;align-items:center;justify-content:center;
      box-shadow:0 4px 14px rgba(0,0,0,.22);transition:transform .2s,box-shadow .2s;
      position:relative;color:#fff;text-decoration:none;
    }
    /* Live-chat launcher (#af-chat is injected as a site snippet, not from this theme):
       same global-button-padding bug turns the 56px circle into an icon-less black oval. */
    .af-chat-bub{padding:0 !important;}
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
    /* wishlist count badge */
    .af-qp-count{
      position:absolute;top:-4px;right:-4px;min-width:20px;height:20px;padding:0 5px;
      border-radius:999px;background:#1a1a1a;color:#fff;font-size:11px;font-weight:800;
      line-height:20px;text-align:center;box-sizing:border-box;box-shadow:0 2px 6px rgba(0,0,0,.3);
      pointer-events:none;
    }
    .af-qp-count[hidden]{display:none !important;}
    /* A second, plugin-supplied WhatsApp bubble sits in the same corner as ours.
       Hidden by class where the common plugins are known; the script below also
       catches any other floating WhatsApp widget by behaviour. */
    .ht-ctc.ht-ctc-chat, .joinchat, .wa__floating_btn, .wa-chat-box-wrapper,
    .whatsapp-chat-widget, #whatsapp-chat-widget, .wt-whatsapp-float {
      display:none !important;
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
        <div class="af-trust-item"><span class="af-trust-ico">🚚</span><div><strong><?php echo esc_html(af_shipping_copy()['label']); ?></strong><small><?php echo esc_html(af_shipping_copy()['cost'] !== '' ? 'Shipping ' . af_shipping_copy()['cost'] : 'Cost shown at checkout'); ?></small></div></div>
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
    $has_filters = !empty($_GET['filter_colors']) || !empty($_GET['filter_size']) || !empty($_GET['filter_frame']) || !empty($_GET['orientation']) || $cur_min !== '' || $cur_max !== '';
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
            <?php foreach ($sizes as $t): $soos = function_exists('af_filter_label_is_oos') && af_filter_label_is_oos($t->name); ?>
              <?php if ($soos) continue; // not on sale: not on the menu ?>
              <a rel="nofollow" href="<?php echo esc_url($flink('filter_size',$t->slug)); ?>"><?php echo esc_html($t->name); ?></a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php if (!is_wp_error($colors) && $colors): ?>
        <div class="af-lt-drop">
          <button type="button" class="af-lt-dbtn">Frame Color ▾</button>
          <div class="af-lt-menu">
            <?php foreach ($colors as $t): ?>
              <a rel="nofollow" href="<?php echo esc_url($flink('filter_colors',$t->slug)); ?>"><?php echo esc_html($t->name); ?></a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php if (!is_wp_error($frames) && $frames): ?>
        <div class="af-lt-drop">
          <button type="button" class="af-lt-dbtn">Frame Type ▾</button>
          <div class="af-lt-menu">
            <?php foreach ($frames as $t): $foos = function_exists('af_filter_label_is_oos') && af_filter_label_is_oos($t->name); ?>
              <?php if ($foos) continue; // not on sale: not on the menu ?>
              <a rel="nofollow" href="<?php echo esc_url($flink('filter_frame',$t->slug)); ?>"><?php echo esc_html($t->name); ?></a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php if (function_exists('af_orientation_current')):
          $cur_o = af_orientation_current();
          $olink = function($val) use ($keep) { $q = $keep; $q['orientation'] = $val; return '?' . http_build_query($q); };
        ?>
        <div class="af-lt-drop" data-af-orient="1">
          <button type="button" class="af-lt-dbtn"><?php echo $cur_o ? esc_html(ucfirst($cur_o)) : 'Orientation'; ?> ▾</button>
          <div class="af-lt-menu">
            <a href="<?php echo esc_url($olink('portrait')); ?>">▯ Portrait</a>
            <a href="<?php echo esc_url($olink('landscape')); ?>">▭ Landscape</a>
            <a href="<?php echo esc_url($olink('square')); ?>">□ Square</a>
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
    $product = af_wc_product();
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
    $product = af_wc_product();
    if (!$product || !$product->is_purchasable() || !$product->is_in_stock()) return;

    if ($product->is_type('simple')) {
        $url = esc_url(wc_get_checkout_url() . '?add-to-cart=' . $product->get_id());
        // nofollow: crawlers following this link mint a cart session and an
        // uncacheable checkout render per product; the crawl guard bounces
        // headerless hits, this stops compliant bots queueing them at all.
        echo '<a href="' . $url . '" rel="nofollow" class="af-buynow button">Buy Now</a>';
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
    $product = af_wc_product();
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
af_section(function() {
    if (!af_show_product_sections()) return;   // page + quick-view modal, nothing else
    $product = af_wc_product();
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
af_section(function() {
    if (!af_show_product_sections()) return;   // page + quick-view modal, nothing else
    $product = af_wc_product();
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
af_section(function() {
    if (!af_show_product_sections()) return;   // page + quick-view modal, nothing else
    $product = af_wc_product();
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
    /* Inside the quick-view popup the same sections render in a narrower,
       scrolling container — two cards across, tighter rhythm. */
    .eael-product-popup .af-pp-row{grid-template-columns:repeat(2,1fr);}
    .eael-product-popup .af-pp-sec{margin-top:28px;}
    .eael-product-popup .af-recent-row a{flex:0 0 120px;}
    .eael-product-popup .af-recent-row img{width:120px;height:120px;}
    @media(max-width:600px){ .af-pp-sec h2{font-size:19px;} .af-ppt{flex:1 1 45%;} }
    </style>
    <?php
}, 21);

// ─────────────────────────────────────────────────────────────
// PHASE 7b — Remaining post-tab sections per spec Section 13:
// Digital Downloads + Customize Your Picture. Additive; reversible.
// ─────────────────────────────────────────────────────────────

// 7h. Digital Downloads section (post-tab) — shows Digital Downloads category
af_section(function() {
    if (!af_show_product_sections()) return;   // page + quick-view modal, nothing else
    $product = af_wc_product();
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
af_section(function() {
    if (!af_show_product_sections()) return;   // page + quick-view modal, nothing else
    $product = af_wc_product();
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
/**
 * The current product, or null — never a string.
 *
 * WooCommerce's $product global is only reliable inside its own loop. A
 * plugin, shortcode or template that reassigns it (we have one that leaves a
 * string behind by the time wp_footer runs) turns every `if (!$product)`
 * guard into a lie, because a non-empty string is truthy — and the next
 * `$product->get_id()` is a fatal that takes the whole page down with it.
 * Ask for the product through here instead of trusting the global.
 */
function af_wc_product($maybe = null) {
    if ($maybe instanceof WC_Product) return $maybe;
    if (!function_exists('wc_get_product')) return null;
    $global = isset($GLOBALS['product']) ? $GLOBALS['product'] : null;
    if ($global instanceof WC_Product) return $global;

    $id = 0;
    if (is_numeric($maybe))      $id = (int) $maybe;
    elseif (is_numeric($global)) $id = (int) $global;
    else {
        $id = (int) get_the_ID();
        if (!$id && function_exists('get_queried_object_id')) $id = (int) get_queried_object_id();
    }
    if (!$id) return null;
    $p = wc_get_product($id);
    return ($p instanceof WC_Product) ? $p : null;
}

/**
 * True only on the real single-product page — no ajax, no REST, no admin.
 */
function af_is_product_page() {
    if (is_admin()) return false;
    if (function_exists('wp_doing_ajax') && wp_doing_ajax()) return false;
    if (defined('REST_REQUEST') && REST_REQUEST) return false;
    if (defined('DOING_AJAX') && DOING_AJAX) return false;
    return function_exists('is_product') && is_product();
}

/**
 * Does this ajax action name belong to a quick-view plugin?
 *
 * Split out from the request check because it is the part worth testing, and
 * the request check cannot be exercised from WP-CLI, where wp_doing_ajax() is
 * always false. Matched on the action rather than a plugin's name: the site
 * currently quick-views through Essential Addons
 * (eael_product_quickview_popup), and swapping that for WooSQ or another
 * plugin must not silently empty the modal.
 */
function af_is_quick_view_action($action) {
    $action = (string) $action;
    return $action !== '' && (bool) preg_match('/(woosq|quick_?view|^qv_|_qv$)/i', $action);
}

/**
 * True while a quick-view plugin is rendering its modal.
 */
function af_is_quick_view_request() {
    $ajax = (function_exists('wp_doing_ajax') && wp_doing_ajax()) || (defined('DOING_AJAX') && DOING_AJAX);
    if (!$ajax) return false;
    if (defined('REST_REQUEST') && REST_REQUEST) return false;
    return af_is_quick_view_action(isset($_REQUEST['action']) ? $_REQUEST['action'] : '');
}

/**
 * Register a product-page section so it renders in BOTH places it belongs.
 *
 * On the real product page these hang off woocommerce_after_single_product_summary.
 * The quick-view modal (Essential Addons) renders the summary hook but never
 * fires the after-summary hook at all — measured directly: every in-summary
 * piece of ours reaches the modal, every after-summary section is absent, and
 * the response ends right after the product meta. So each section also joins a
 * private af_product_sections action, and a bridge at the end of the summary
 * hook fires that action only during a quick-view request. The bridge
 * deliberately does NOT fire the whole after-summary hook inside the modal:
 * WooCommerce hangs tabs, up-sells and related products there (44 callbacks),
 * and the modal wants the page's own sections, not the entire tail of the
 * template.
 */
function af_section($fn, $prio = 10) {
    add_action('woocommerce_after_single_product_summary', $fn, $prio);
    add_action('af_product_sections', $fn, $prio);
}
/**
 * Fire the sections inside the modal, on a hook the modal actually reaches.
 *
 * Essential Addons builds its popup layout by hand: it never fires
 * woocommerce_single_product_summary either — proven by which of our pieces
 * arrive in its response (all three are on the add-to-cart button hooks) and
 * by a first bridge on the summary hook that never ran. What its popup DOES
 * render is the product meta, whose template fires
 * woocommerce_product_meta_end — the very tail of the modal. The bridge sits
 * there, and also on the summary hook for quick-view plugins that do fire it,
 * with a latch so the sections render once whichever hook comes first. On the
 * real product page af_is_quick_view_request() is false and both are no-ops.
 */
function af_qv_bridge() {
    static $done = false;
    if ($done || !af_is_quick_view_request()) return;
    $done = true;
    do_action('af_product_sections');
}
add_action('woocommerce_product_meta_end', 'af_qv_bridge', 200);
add_action('woocommerce_single_product_summary', 'af_qv_bridge', 200);

/**
 * Should the product page's own sections — Related Searches, Popular Products,
 * Recently Viewed, Digital Downloads, the Customize CTA, the FAQ — render here?
 *
 * The quick view is meant to be the product page for that product, not a
 * trimmed version of it, so the answer is yes on the real page AND inside the
 * modal. What it is not is a licence to render during any old ajax call, a
 * REST response or an admin screen, which is what this keeps out.
 *
 * These sections hang off woocommerce_after_single_product_summary, which the
 * quick-view plugin fires too. They used to stay out of the modal only by
 * accident — af_wc_product() found no $product global there and each section
 * bailed on its own first line — and when that helper learned to fall back to
 * the current post they appeared unstyled, because the stylesheet they need was
 * gated to is_product(). That stylesheet is now site-wide, so they render in
 * the modal exactly as they do on the page.
 */
function af_show_product_sections() {
    return af_is_product_page() || af_is_quick_view_request();
}

/* ============================================================
   QUICK VIEW = THE PRODUCT PAGE ITSELF
   The plugin's popup rebuilds the product from its own template, so it can
   only ever be an approximation of the page. The owner wants an exact full
   copy — tabs, art code, highlights, spec table, placement ideas, FAQ,
   related pieces, everything. The only rendering that is exactly the product
   page is the product page, so Quick View now opens the real page inside a
   modal frame, with the site chrome (header, footer, floating widgets)
   hidden via the af_qv=1 embed mode below. It cannot drift out of sync with
   the page, because it IS the page.
   ============================================================ */

// ── embed mode: the product page stripped to its content ──
function af_is_qv_embed() {
    return !empty($_GET['af_qv']);
}
add_filter('body_class', function($classes) {
    if (af_is_qv_embed()) $classes[] = 'af-qv-embed';
    return $classes;
});
// Don't merely hide the admin bar in the modal — don't render it. It ships its
// own stylesheet and markup, and it is site furniture, not product detail.
add_filter('show_admin_bar', function($show) {
    return af_is_qv_embed() ? false : $show;
}, 99);
add_action('wp_head', function() {
    if (!af_is_qv_embed()) return;
    ?>
    <style>
    /* hide everything that belongs to the surrounding site, keep the page */
    .af-qv-embed header, .af-qv-embed .site-header, .af-qv-embed #masthead,
    .af-qv-embed footer, .af-qv-embed .site-footer,
    .af-qv-embed [data-elementor-type="header"], .af-qv-embed [data-elementor-type="footer"],
    .af-qv-embed .af-quickpanel, .af-qv-embed #af-chat, .af-qv-embed #af-consent,
    .af-qv-embed .af-ck-footwrap, .af-qv-embed .woocommerce-breadcrumb,
    /* the top utility strip (Track Order / Help / phone / currency) and the
       WordPress admin bar — site furniture, not product detail */
    .af-qv-embed .af-utilitybar, .af-qv-embed #wpadminbar {
      display: none !important;
    }
    /* WordPress reserves space for the admin bar on <html>; with the bar hidden
       that margin would leave a strip of empty page at the top of the modal */
    html { margin-top: 0 !important; }
    * html body { margin-top: 0 !important; }
    .af-qv-embed { background: #fff; }
    </style>
    <?php
}, 99);
add_action('wp_footer', function() {
    if (!af_is_qv_embed()) return;
    ?>
    <script>
    (function(){
      // Inside the frame, anything that leaves the product (Buy Now, checkout,
      // cart, breadcrumbs to categories, related-product cards) should take
      // over the whole window rather than navigating within the modal.
      // Add-to-cart form posts stay inside so the modal keeps its context.
      document.addEventListener('click', function(e){
        var a = e.target.closest('a[href]');
        if (!a) return;
        var h = a.getAttribute('href') || '';
        if (h.charAt(0) === '#' || /^javascript:/i.test(h)) return;
        // keep in-frame: gallery lightboxes and same-page anchors only
        a.target = '_top';
      }, true);
    })();
    </script>
    <?php
}, 99);

// ── the modal: intercept quick-view clicks on catalogue pages ──
add_action('wp_footer', function() {
    if (is_admin() || af_is_qv_embed()) return;
    ?>
    <div id="af-qv" class="af-qv" hidden>
      <div class="af-qv-back"></div>
      <div class="af-qv-box" role="dialog" aria-modal="true" aria-label="Quick view">
        <button type="button" class="af-qv-x" aria-label="Close quick view">✕</button>
        <div class="af-qv-spin"></div>
        <iframe class="af-qv-frame" title="Product quick view"></iframe>
      </div>
    </div>
    <style>
    .af-qv[hidden]{display:none !important;}
    .af-qv{position:fixed;inset:0;z-index:100000;display:flex;align-items:center;justify-content:center;}
    .af-qv-back{position:absolute;inset:0;background:rgba(12,10,6,.62);backdrop-filter:blur(2px);}
    .af-qv-box{position:relative;width:min(1200px,94vw);height:92vh;background:#fff;border-radius:16px;
      overflow:hidden;box-shadow:0 30px 90px rgba(0,0,0,.45);}
    .af-qv-x{position:absolute;top:10px;right:10px;z-index:3;width:38px;height:38px;border:none;border-radius:50%;
      background:#1a1a1a;color:#fff;font-size:16px;font-weight:700;cursor:pointer;line-height:1;}
    .af-qv-x:hover{background:#c9a84c;}
    .af-qv-frame{position:absolute;inset:0;width:100%;height:100%;border:0;opacity:0;transition:opacity .25s;}
    .af-qv.loaded .af-qv-frame{opacity:1;}
    .af-qv-spin{position:absolute;left:50%;top:50%;width:42px;height:42px;margin:-21px 0 0 -21px;border-radius:50%;
      border:4px solid #eee2c8;border-top-color:#c9a84c;animation:afqvspin .8s linear infinite;}
    .af-qv.loaded .af-qv-spin{display:none;}
    @keyframes afqvspin{to{transform:rotate(360deg);}}
    @media(max-width:600px){ .af-qv-box{width:100vw;height:100vh;border-radius:0;} }
    /* Belt and braces: if the plugin's own popup still manages to open behind
       ours, keep it off the screen rather than stacking two modals. */
    html.af-qv-open .eael-product-popup, html.af-qv-open .woosq-popup,
    html.af-qv-open .mfp-wrap, html.af-qv-open .pswp--open { display:none !important; }
    </style>
    <script>
    (function(){
      var wrap=document.getElementById('af-qv');
      if(!wrap) return;
      var frame=wrap.querySelector('.af-qv-frame'), readyTimer=null, giveUp=null, target='';

      // Find the product URL for whatever card this button belongs to. The
      // first version looked for li.product/.product, which does not match the
      // homepage's .product-card (a class selector matches whole tokens), so
      // on the homepage it found nothing, bailed, and let the plugin's popup
      // open — which is exactly what the visitor kept seeing. Walk up instead:
      // any ancestor holding a /product/ link is the card, whatever it's called.
      function productURL(btn){
        var own=btn.getAttribute('href')||'';
        if(own.indexOf('/product/')>-1) return own;
        var d=btn.getAttribute('data-product-url')||btn.getAttribute('data-url')||'';
        if(d.indexOf('/product/')>-1) return d;
        var n=btn;
        for(var i=0;i<10 && n && n!==document.body;i++){
          var a=n.querySelector ? n.querySelector('a[href*="/product/"]') : null;
          if(a && a.href) return a.href;
          n=n.parentElement;
        }
        return '';
      }
      function reveal(){
        wrap.classList.add('loaded');
        if(readyTimer){ clearInterval(readyTimer); readyTimer=null; }
        if(giveUp){ clearTimeout(giveUp); giveUp=null; }
      }
      function openQV(url){
        target=url;
        wrap.classList.remove('loaded');
        document.documentElement.classList.add('af-qv-open');
        frame.onload=reveal;
        frame.src=url+(url.indexOf('?')>-1?'&':'?')+'af_qv=1';
        wrap.hidden=false;
        document.documentElement.style.overflow='hidden';
        // The frame is same-origin, so show it the moment it has a document
        // rather than waiting for every image — a 500 KB product page kept the
        // spinner up long enough to read as broken.
        if(readyTimer) clearInterval(readyTimer);
        readyTimer=setInterval(function(){
          try{
            var d=frame.contentDocument;
            if(d && (d.readyState==='interactive'||d.readyState==='complete') && d.body && d.body.children.length) reveal();
          }catch(err){ /* cross-origin: wait for onload */ }
        }, 120);
        // and never spin for ever: if the page will not frame, just go to it
        if(giveUp) clearTimeout(giveUp);
        giveUp=setTimeout(function(){
          if(!wrap.classList.contains('loaded') && target){ window.location.href=target; }
        }, 12000);
      }
      function closeQV(){
        wrap.hidden=true;
        frame.src='about:blank';
        document.documentElement.style.overflow='';
        document.documentElement.classList.remove('af-qv-open');
        if(readyTimer){ clearInterval(readyTimer); readyTimer=null; }
        if(giveUp){ clearTimeout(giveUp); giveUp=null; }
      }
      wrap.querySelector('.af-qv-x').addEventListener('click', closeQV);
      wrap.querySelector('.af-qv-back').addEventListener('click', closeQV);
      document.addEventListener('keydown', function(e){ if(e.key==='Escape' && !wrap.hidden) closeQV(); });

      // Capture phase on document runs before any delegated handler the
      // quick-view plugin binds, so this wins without a race. Matched broadly:
      // the buttons ship as quick-view, quickview, quick_view or view-btn
      // depending on which section rendered the card.
      var SEL='[class*="quick-view"],[class*="quickview"],[class*="quick_view"],'
             +'[class*="eael-product-quick"],[data-quick-view],.view-btn,a[class*="quick"]';
      function grab(e){
        var b=e.target.closest(SEL);
        if(!b || wrap.contains(b)) return;
        var url=productURL(b);
        if(!url) return;                       // unknown card: leave the plugin to it
        e.preventDefault(); e.stopPropagation(); e.stopImmediatePropagation();
        openQV(url.split('#')[0]);
      }
      document.addEventListener('click', grab, true);
    })();
    </script>
    <?php
}, 98);

/**
 * Repair a corrupted $product global before the footer runs. Something on the
 * product page hands the global to wp_footer as a string; anything that then
 * calls a method on it dies, and WordPress answers 500 for the whole page.
 * Runs first (priority 0) so every later footer hook — ours, the parent
 * theme's, a plugin's — sees a real product or nothing at all.
 */
add_action('wp_footer', function() {
    if (!function_exists('is_product') || !is_product()) return;
    if (!array_key_exists('product', $GLOBALS)) return;
    if ($GLOBALS['product'] instanceof WC_Product) return;
    $p = function_exists('wc_get_product') ? wc_get_product(get_queried_object_id()) : null;
    $GLOBALS['product'] = ($p instanceof WC_Product) ? $p : null;
}, 0);

function af_pricing_applies($product) {
    if (!($product instanceof WC_Product)) return false;
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

/**
 * The studio's public contact details — the single place any customer-facing
 * surface should ask.
 *
 * Deliberately NOT get_option('admin_email'): that is whoever owns the
 * WordPress login, so publishing it hands customers a personal address that
 * has nothing to do with the studio. These match what the header, footer and
 * contact page already show. Set the af_contact_email / af_contact_phone
 * options, or filter af_studio_contact, to change them everywhere at once.
 */
function af_studio_contact() {
    $email = get_option('af_contact_email');
    $phone = get_option('af_contact_phone');
    $c = apply_filters('af_studio_contact', array(
        'email' => $email ? $email : 'theartframer136@gmail.com',
        'phone' => $phone ? $phone : '+1 (610) 470-7280',
    ));
    // a blank option must never fall through as an empty mailto:
    if (empty($c['email'])) $c['email'] = 'theartframer136@gmail.com';
    if (empty($c['phone'])) $c['phone'] = '+1 (610) 470-7280';
    $digits     = preg_replace('/\D+/', '', $c['phone']);
    $c['tel']   = 'tel:+' . $digits;
    $c['wa']    = $digits;
    return $c;
}

/**
 * How the site talks about shipping — one source, so the trust badge, its
 * popup, the announcement bar and the chatbot can never contradict each other.
 *
 * The site used to promise "Free Shipping across the USA" while the badge's
 * own popup listed free delivery in four states only. It now says the studio
 * ships throughout the USA and states the cost.
 *
 * The cost line is deliberately not invented here: set the af_shipping_cost
 * option (e.g. "$15" or "from $12") and every surface picks it up; leave it
 * empty and they all say the cost is shown at checkout, which is true whatever
 * the rate turns out to be. Filter af_shipping_copy to override any of it.
 */
function af_shipping_copy() {
    $cost = trim((string) get_option('af_shipping_cost', ''));
    $line = $cost !== ''
        ? 'Shipping ' . $cost . ' throughout the USA'
        : 'Shipping cost shown at checkout';
    return apply_filters('af_shipping_copy', array(
        'label' => 'Shipping Throughout the USA',
        'short' => $line,
        'blurb' => $cost !== ''
            ? 'Delivered anywhere in the USA — shipping ' . $cost . ', shown before you pay.'
            : 'Delivered anywhere in the USA — shipping cost is shown at checkout before you pay.',
        'cost'  => $cost,
    ));
}

/**
 * The price book.
 *
 * $product_id is optional and changes nothing for a normal print. It exists so
 * a section with its own price level — Gold Foiled & UV — can be expressed as a
 * ratio on this one card instead of a second book that would drift out of sync:
 * pass the product and the size prices come back scaled for it. Frame and
 * colour surcharges are never scaled; a moulding costs what a moulding costs.
 */
function af_pricing_config($product_id = 0) {
    $cfg = af_pricing_config_base();
    if ($product_id && function_exists('af_goldfoil_factor')) {
        $factor = af_goldfoil_factor($product_id);
        if ($factor != 1.0) {
            foreach ($cfg['sizes'] as $label => $usd) {
                $cfg['sizes'][$label] = af_goldfoil_scale($usd, $factor);
            }
        }
    }
    return $cfg;
}

function af_pricing_config_base() {
    return array(
        // Size label => PRICE IN USD, straight from the printed "Pine Wood
        // Framing Sizes & Pricing" rate card. These used to be multipliers on
        // each product's listing price, which was an invented curve nobody had
        // reconciled with the card — a 4×6 came out at $608 against the card's
        // $150. The card is the price book now. Sizes the card doesn't list
        // (2×3.5, 2×4, 2.5×…, and 2×5/2.5×4/3×5 which match card areas) are
        // interpolated along the card's own per-square-foot curve and rounded
        // to $5.
        'sizes' => array(
            '2×3 ft (24×36 in)'   => 60,    // card
            '3×2 ft (36×24 in)'   => 60,    // card 2×3, turned (same 6 sq ft)
            '2×3.5 ft (24×42 in)' => 65,    // interpolated (7 sq ft)
            '2×4 ft (24×48 in)'   => 70,    // interpolated (8 sq ft)
            '2×5 ft (24×60 in)'   => 75,    // card 5×2 (same 10 sq ft)
            '2.5×3 ft (30×36 in)' => 65,    // interpolated (7.5 sq ft)
            '2.5×4 ft (30×48 in)' => 75,    // interpolated (10 sq ft)
            '2.5×5 ft (30×60 in)' => 85,    // interpolated (12.5 sq ft)
            '3×4 ft (36×48 in)'   => 80,    // card
            '3×5 ft (36×60 in)'   => 100,   // card 5×3 (same 15 sq ft)
            '3×6 ft (36×72 in)'   => 120,   // card
            '4×3 ft (48×36 in)'   => 80,    // card
            '4×4 ft (48×48 in)'   => 110,   // card
            '4×5 ft (48×60 in)'   => 130,   // card
            '4×6 ft (48×72 in)'   => 150,   // card
        ),
        // Quick-filter groups (spec: Small / Medium / Large / Custom)
        'groups' => array(
            'Small'  => array('2×3 ft (24×36 in)','3×2 ft (36×24 in)','2×3.5 ft (24×42 in)','2.5×3 ft (30×36 in)'),
            'Medium' => array('2×4 ft (24×48 in)','2.5×4 ft (30×48 in)','3×4 ft (36×48 in)','4×3 ft (48×36 in)'),
            'Large'  => array('2×5 ft (24×60 in)','2.5×5 ft (30×60 in)','3×5 ft (36×60 in)','3×6 ft (36×72 in)','4×4 ft (48×48 in)','4×5 ft (48×60 in)','4×6 ft (48×72 in)'),
        ),
        // Wall-suitability hints (spec: "Best for 10×12 ft walls")
        'hints' => array(
            '2×3 ft (24×36 in)'   => 'Best for 6×8 ft walls & cozy corners',
            '3×2 ft (36×24 in)'   => 'Best for 6×8 ft walls & cozy corners (landscape)',
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
        // Frame type => flat add-on fee (USD). Floating matches the rate
        // card's "ADD FLOATING FRAME: +$50"; the card is silent on the others.
        'frames' => array(
            'Without Frame'   => 0,
            'Fibre Frame'     => 25,
            'Floating Frame'  => 50,
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

/**
 * The discount percentage shown against a product's struck-through reference
 * price. Every product used to sit at the same figure, which reads as a
 * pricing rule rather than a saving, so each one gets its own percentage
 * inside the band below.
 *
 * Deterministic, not actually random: the value is derived from the product
 * id, so a product shows the same percentage on every page load and on every
 * re-run of tools/apply-mrp-markup.php. A genuinely random number would move
 * the struck price each time the script ran.
 */
function af_mrp_discount_pct( $product_id ) {
    $min = 20;   // gentlest saving shown
    $max = 45;   // largest saving shown
    $hash = abs( crc32( 'af-mrp-' . (int) $product_id ) );
    return $min + ( $hash % ( $max - $min + 1 ) );
}

/**
 * What to multiply the selling price by to get that product's reference
 * price. Derived from the percentage so the badge the theme computes back
 * out of the two prices lands on exactly af_mrp_discount_pct().
 */
function af_mrp_multiplier( $product_id ) {
    $pct = af_mrp_discount_pct( $product_id );
    if ( $pct <= 0 || $pct >= 100 ) return 1.0;
    return 1 / ( 1 - $pct / 100 );
}

/**
 * Frame types we can currently make. Everything else in af_pricing_config()
 * stays priced and previewable — the selector still shows the full range — but
 * is offered as OUT OF STOCK and cannot be chosen or ordered. Editing this one
 * list is all it takes to put a frame back on sale.
 */
function af_frames_in_stock() {
    return array( 'Without Frame', 'Aluminium Frame' );
}

function af_frame_is_in_stock( $frame ) {
    return in_array( $frame, af_frames_in_stock(), true );
}

/** The frames currently NOT available, in the same order the card lists them. */
function af_frames_out_of_stock() {
    return array_values( array_filter(
        array_keys( af_pricing_config()['frames'] ),
        function ( $f ) { return ! af_frame_is_in_stock( $f ); }
    ) );
}

/** The sizes we no longer sell, in the order the price book lists them. */
function af_sizes_out_of_stock() {
    return array_values( array_filter(
        array_keys( af_pricing_config()['sizes'] ),
        function ( $s ) { return ! af_size_is_offered( $s ); }
    ) );
}

/**
 * Every option a filter may list that cannot actually be bought — frames we
 * cannot make and sizes we no longer sell, in one list.
 */
function af_filter_oos_labels() {
    return array_merge( af_frames_out_of_stock(), af_sizes_out_of_stock() );
}

/**
 * Does this label name an option we cannot sell — either frame or size?
 *
 * Sizes needed the same treatment as frames and for the same reason: the
 * filter offered all fourteen with a count beside each while the product page
 * sells five. It is worth being clear about one difference, because it looks
 * like an inconsistency and is not: the product page DROPS a size it does not
 * sell and STRIKES a frame it cannot make. In the filter both are struck, so
 * a shopper who knows the size exists is told plainly that it is unavailable
 * rather than left wondering where it went.
 */
function af_filter_label_is_oos( $label ) {
    $clean = trim( preg_replace( '/\s+/', ' ',
        preg_replace( '/\s*\(\s*\d+\s*\)\s*$/', '', wp_strip_all_tags( (string) $label ) ) ) );
    if ( $clean === '' ) return false;
    foreach ( af_filter_oos_labels() as $oos ) {
        if ( strcasecmp( $oos, $clean ) === 0 ) return true;
    }
    return false;
}

/**
 * Does this label name a frame we cannot currently make?
 *
 * Written to take a label rather than a clean name because the places that
 * need the answer are filter widgets, where the text arrives as the term name
 * with a count stuck on the end — "Fibre Frame (337)" — and sometimes wrapped
 * in markup. Returns false for anything that is not one of our frames at all,
 * so it is safe to run over every label on a page.
 */
function af_frame_label_is_oos( $label ) {
    $clean = trim( preg_replace( '/\s*\(\s*\d+\s*\)\s*$/', '', wp_strip_all_tags( (string) $label ) ) );
    if ( $clean === '' ) return false;
    foreach ( array_keys( af_pricing_config()['frames'] ) as $frame ) {
        if ( strcasecmp( $frame, $clean ) === 0 ) return ! af_frame_is_in_stock( $frame );
    }
    return false;
}

/** The frame a product opens on: the first in-stock one in config order. */
function af_frame_default() {
    $frames = array_keys( af_pricing_config()['frames'] );
    foreach ( $frames as $f ) { if ( af_frame_is_in_stock( $f ) ) return $f; }
    return $frames[0];
}

/**
 * Sizes we currently offer. Unlike frames — which stay on the page struck
 * through — a size we do not sell is simply not shown: nine greyed-out chips
 * would be noise, not information. The rest of af_pricing_config()['sizes']
 * stays put so the price book, the size-from-title lookup and every existing
 * order keep resolving exactly as before. Offering a size again is one edit
 * to this list.
 */
function af_sizes_offered() {
    return array(
        '2×3 ft (24×36 in)',
        '3×2 ft (36×24 in)',
        '2.5×3 ft (30×36 in)',
        '3×4 ft (36×48 in)',
        '3×5 ft (36×60 in)',
    );
}

function af_size_is_offered( $size ) {
    return in_array( $size, af_sizes_offered(), true );
}

/** Offered sizes in price-book order, so the selector reads small → large. */
function af_sizes_available() {
    return array_values( array_filter(
        array_keys( af_pricing_config()['sizes'] ), 'af_size_is_offered' ) );
}

/**
 * The size a product opens on: its own titled size when we still offer it,
 * otherwise the first size we do offer.
 */
function af_size_default( $product = null ) {
    $avail = af_sizes_available();
    if ( ! $avail ) return array_key_first( af_pricing_config()['sizes'] );
    if ( $product ) {
        $titled = af_size_label_for_product( $product );
        if ( $titled !== '' && in_array( $titled, $avail, true ) ) return $titled;
    }
    return $avail[0];
}

/**
 * The size a product is titled as — "…Canvas Wall Art 3x4 Feet", "60 x 36
 * Inch…" — resolved to its selector label. This is what makes the numbers
 * agree "variation-wise": the listing price, the pre-selected size chip and
 * the cart default all key off the product's own size instead of everything
 * defaulting to 2×3. Returns '' when the title carries no recognisable size.
 */
function af_size_label_for_product($product) {
    $name = is_object($product) ? $product->get_name() : (string) $product;
    if (!preg_match('/(\d+(?:\.\d+)?)\s*[x×]\s*(\d+(?:\.\d+)?)\s*(ft|feet|foot|inches|inch|in)?\b/iu', $name, $m)) return '';
    $a = (float) $m[1]; $b = (float) $m[2];
    $u = strtolower(isset($m[3]) ? $m[3] : '');
    if ($u === '') $u = ($a > 12 || $b > 12) ? 'in' : 'ft';   // a bare "36x48" is inches
    if (strpos($u, 'in') === 0) { $a /= 12; $b /= 12; }
    $labels = array();
    foreach (af_pricing_config()['sizes'] as $label => $usd) {
        if (preg_match('/^(\d+(?:\.\d+)?)×(\d+(?:\.\d+)?) ft/u', $label, $lm)) {
            $labels[$label] = array((float) $lm[1], (float) $lm[2]);
        }
    }
    foreach ($labels as $label => $d) {                        // exact orientation
        if (abs($d[0] - $a) < 0.26 && abs($d[1] - $b) < 0.26) return $label;
    }
    foreach ($labels as $label => $d) {                        // same print, turned
        if (abs($d[0] - $b) < 0.26 && abs($d[1] - $a) < 0.26) return $label;
    }
    return '';
}

// Authoritative server-side price calculation.
// The size sets the price outright from the rate card — the product's listing
// price no longer multiplies into it ($base stays in the signature so the
// call sites don't churn, and in case a per-product premium ever returns).
// The colour fee pays for the frame's finish, so an unframed (gallery-wrapped)
// print is never charged for one — there is no moulding to finish.
function af_calc_price($base, $size, $frame, $color, $product_id = 0) {
    $cfg = af_pricing_config($product_id);
    $sizes = $cfg['sizes'];
    $price = isset($sizes[$size]) ? (float)$sizes[$size] : (float)reset($sizes);
    $fee  = (isset($cfg['frames'][$frame]) ? (float)$cfg['frames'][$frame] : 0);
    if ($frame !== 'Without Frame') {
        $fee += (isset($cfg['colors'][$color]) ? (float)$cfg['colors'][$color] : 0);
    }
    return round($price + $fee, 2);
}

// 8a. Render selectors inside the add-to-cart form (simple products)
add_action('woocommerce_before_add_to_cart_button', function() {
    $product = af_wc_product();
    if (!$product || !af_pricing_applies($product) || !$product->is_purchasable() || !$product->is_in_stock()) return;
    // priced FOR THIS PRODUCT: the Gold Foiled & UV section rides a ratio on
    // the same card, and data-config below is what the selector recomputes from
    $cfg  = af_pricing_config($product->get_id());
    if ($product->is_type('variable')) {
        $min  = (float) $product->get_variation_price('min');
        $base = $min > 0 ? $min : (float) wc_get_price_to_display($product);
    } else {
        $base = (float) wc_get_price_to_display($product);
    }
    // only the sizes we actually offer reach the selector
    $sizes  = af_sizes_available();
    $frames = array_keys($cfg['frames']);
    $colors = array_keys($cfg['colors']);
    // pre-select the size the product is titled as, so the opening price IS
    // this product's price — not every product pretending to be a 2×3
    $def_size = af_size_default($product);
    // Frames that are out of stock are shown but cannot be picked, so the
    // selector opens on the first one we can actually make.
    $def_frame = af_frame_default();
    ?>
    <div class="af-opts" id="af-opts" data-base="<?php echo esc_attr($base); ?>" data-config='<?php echo esc_attr(wp_json_encode($cfg)); ?>' data-symbol="<?php echo esc_attr(get_woocommerce_currency_symbol()); ?>" data-mrp-mult="<?php echo esc_attr(round(af_mrp_multiplier($product->get_id()), 6)); ?>">
      <div class="af-opt-group">
        <label class="af-opt-label" for="af-size-select">Size <span class="af-opt-sub">(height × width)</span></label>
        <div class="af-size-row">
          <select id="af-size-select" class="af-size-select" data-type="size">
            <?php foreach ($sizes as $s): ?>
              <option value="<?php echo esc_attr($s); ?>"<?php selected($s, $def_size); ?>><?php echo esc_html($s); ?></option>
            <?php endforeach; ?>
          </select>
          <a class="af-chip-grp af-chip-custom" href="/customize-your-picture/">Custom ↗</a>
        </div>
      </div>
      <p class="af-wall-hint" id="afWallHint"></p>
      <div class="af-opt-group">
        <label class="af-opt-label">Frame Type</label>
        <div class="af-chips af-frame-chips">
          <?php foreach ($frames as $i => $f): $fee=$cfg['frames'][$f]; $oos = !af_frame_is_in_stock($f); ?>
            <button type="button" class="af-chip-opt<?php echo $f===$def_frame?' active':''; ?><?php echo $oos?' af-chip-oos':''; ?>" data-type="frame" data-val="<?php echo esc_attr($f); ?>"<?php echo $oos?' disabled aria-disabled="true"':''; ?>><?php if(!$oos && $f==='Floating Frame') echo '<span class="af-rec">Recommended</span>'; ?><?php echo esc_html($f); ?><?php if($fee>0) echo ' <em>+'.get_woocommerce_currency_symbol().$fee.'</em>'; ?><?php if($oos) echo ' <span class="af-oos">Out of stock</span>'; ?></button>
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
        <s id="af-live-mrp" class="af-live-mrp"></s>
        <span id="af-live-disc" class="af-live-disc"></span></div>
      <p class="af-price-notes">✓ Inclusive of all taxes &nbsp;·&nbsp; 📦 Free Secure Packaging</p>
      <input type="hidden" name="af_size"  value="<?php echo esc_attr($def_size); ?>">
      <input type="hidden" name="af_frame" value="<?php echo esc_attr($def_frame); ?>">
      <input type="hidden" name="af_color" value="<?php echo esc_attr($colors[0]); ?>">
    </div>
    <?php
}, 8);

// 8b. Capture selections + compute authoritative price on add to cart
add_filter('woocommerce_add_cart_item_data', function($data, $pid) {
    if (!empty($_REQUEST['af_digital'])) return $data; // digital handled separately
    $product = wc_get_product($pid);
    if (!$product || !af_pricing_applies($product)) return $data;
    $cfg = af_pricing_config($pid);
    // quick add-to-cart (no options chosen) defaults to the product's OWN
    // titled size, so a 4×6-titled piece is never silently sold as a 2×3
    $size  = isset($_POST['af_size'])  ? sanitize_text_field(wp_unslash($_POST['af_size']))
             : af_size_default($product);
    $frame = isset($_POST['af_frame']) ? sanitize_text_field(wp_unslash($_POST['af_frame'])) : af_frame_default();
    $color = isset($_POST['af_color']) ? sanitize_text_field(wp_unslash($_POST['af_color'])) : array_key_first($cfg['colors']);
    // Validate against config. A size or frame we no longer offer is refused
    // here too, not just hidden in the selector, so a stale page, a saved
    // preview link or a hand-made POST cannot order one.
    if (!isset($cfg['sizes'][$size]) || !af_size_is_offered($size)) $size = af_size_default($product);
    if (!isset($cfg['frames'][$frame]) || !af_frame_is_in_stock($frame)) $frame = af_frame_default();
    if (!isset($cfg['colors'][$color])) $color = array_key_first($cfg['colors']);
    $data['af_size']  = $size;
    $data['af_frame'] = $frame;
    $data['af_color'] = $color;
    $af_base = $product->is_type('variable') ? (float) $product->get_variation_price('min') : (float) wc_get_price_to_display($product);
    if ($af_base <= 0) $af_base = (float) wc_get_price_to_display($product);
    $data['af_price'] = af_calc_price($af_base, $size, $frame, $color, $pid);
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
    /* Out-of-stock frames stay visible (so the range still reads) but are
       plainly unavailable: greyed, struck through and not clickable. */
    .af-chip-opt.af-chip-oos{background:#f6f6f6;border-color:#e4e4e4;color:#a3a3a3;cursor:not-allowed;
      text-decoration:line-through;text-decoration-color:#c9c9c9;}
    .af-chip-opt.af-chip-oos:hover{border-color:#e4e4e4;}
    .af-chip-opt.af-chip-oos em{color:#b5b5b5;}
    .af-chip-opt .af-oos{display:inline-block;margin-left:6px;padding:1px 6px;border-radius:4px;
      background:#ececec;color:#8a8a8a;font-size:10px;font-weight:800;letter-spacing:.04em;
      text-transform:uppercase;text-decoration:none;vertical-align:1px;}
    .af-swatch{display:inline-flex;align-items:center;gap:7px;background:#fff;border:1.5px solid #ddd;border-radius:8px;padding:6px 12px 6px 8px;font-size:12.5px;font-weight:600;color:#333;cursor:pointer;transition:all .15s;}
    .af-swatch span{width:18px;height:18px;border-radius:50%;border:1px solid rgba(0,0,0,.15);display:inline-block;}
    .af-swatch:hover{border-color:#c9a84c;}
    .af-swatch.active{border-color:#1a1a1a;}
    /* Size is a dropdown rather than a chip grid — fourteen chips was a wall,
       and the list is short enough now that a select reads faster. */
    .af-size-row{display:flex;flex-wrap:wrap;align-items:center;gap:10px;}
    .af-size-select{flex:1 1 220px;max-width:340px;background:#fff;border:1.5px solid #ddd;border-radius:8px;
      padding:10px 34px 10px 14px;font-size:14px;font-weight:600;color:#1a1a1a;cursor:pointer;
      transition:border-color .15s;appearance:none;-webkit-appearance:none;
      background-image:url("data:image/svg+xml;charset=UTF-8,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%23666' d='M1 1h10L6 7z'/%3E%3C/svg%3E");
      background-repeat:no-repeat;background-position:right 13px center;}
    .af-size-select:hover{border-color:#c9a84c;}
    .af-size-select:focus{outline:none;border-color:#1a1a1a;box-shadow:0 0 0 3px rgba(201,168,76,.18);}
    .af-price-live{margin:6px 0 0;font-size:15px;color:#333;}
    .af-price-live strong{font-size:20px;color:#1a1a1a;}
    .af-price-live .amount{font-weight:800;}
    </style>
    <script>
    (function(){
      function money(sym,val){ return sym + val.toFixed(2); }
      document.addEventListener('click', function(e){
        var b = e.target.closest('.af-chip-opt, .af-swatch'); if(!b) return;
        if(b.disabled || b.classList.contains('af-chip-oos')) return;   // out of stock
        var wrap = b.closest('.af-opts'); if(!wrap) return;
        var type = b.getAttribute('data-type');
        wrap.querySelectorAll('[data-type="'+type+'"]').forEach(function(x){ x.classList.remove('active'); });
        b.classList.add('active');
        var input = wrap.parentNode.querySelector('input[name="af_'+type+'"]') || document.querySelector('input[name="af_'+type+'"]');
        if(input) input.value = b.getAttribute('data-val');
        recalc(wrap);
      });

      // Size is a <select>, frame and colour are still chips. One reader for
      // both shapes, so nothing downstream has to care which is which.
      function chosen(wrap, type){
        var sel = wrap.querySelector('select[data-type="'+type+'"]');
        if(sel) return sel.value;
        var chip = wrap.querySelector('[data-type="'+type+'"].active');
        return chip ? chip.getAttribute('data-val') : '';
      }

      document.addEventListener('change', function(e){
        var sel = e.target && e.target.closest ? e.target.closest('select[data-type]') : null;
        if(!sel) return;
        var wrap = sel.closest('.af-opts'); if(!wrap) return;
        var type = sel.getAttribute('data-type');
        var input = wrap.parentNode.querySelector('input[name="af_'+type+'"]') || document.querySelector('input[name="af_'+type+'"]');
        if(input) input.value = sel.value;
        recalc(wrap);
      });

      function recalc(wrap){
        var base = parseFloat(wrap.getAttribute('data-base'))||0;
        var cfg = {}; try{ cfg = JSON.parse(wrap.getAttribute('data-config')); }catch(e){ return; }
        var sym = wrap.getAttribute('data-symbol')||'$';
        var sizeVal  = chosen(wrap, 'size');
        var frameVal = chosen(wrap, 'frame');
        var colorVal = chosen(wrap, 'color');
        // the size IS the price, from the rate card (matches af_calc_price)
        var sizePrice = (sizeVal && cfg.sizes[sizeVal]) ? cfg.sizes[sizeVal] : base;
        // no frame means no frame-finish surcharge (matches af_calc_price)
        var fee  = (cfg.frames[frameVal]||0)
                 + ((colorVal && frameVal !== 'Without Frame') ? (cfg.colors[colorVal]||0) : 0);
        var price = Math.round((sizePrice + fee)*100)/100;
        var el = wrap.querySelector('#af-live-price');
        if(el) el.innerHTML = '<span class="amount">'+money(sym,price)+'</span>';
      }

      // Arriving from Try On Wall? Pre-select what the visitor configured there,
      // so the price they were shown is the price they land on.
      function preselect(){
        var q = new URLSearchParams(location.search);
        var want = { size:q.get('af_size'), frame:q.get('af_frame'), color:q.get('af_color') };
        if(!(want.size || want.frame || want.color)) return;
        document.querySelectorAll('.af-opts').forEach(function(wrap){
          Object.keys(want).forEach(function(type){
            if(!want[type]) return;
            // dropdown types: only honour a value the select still offers
            var sel = wrap.querySelector('select[data-type="'+type+'"]');
            if(sel){
              var has = Array.prototype.some.call(sel.options, function(o){ return o.value === want[type]; });
              if(!has) return;
              sel.value = want[type];
              var si = wrap.parentNode.querySelector('input[name="af_'+type+'"]') || document.querySelector('input[name="af_'+type+'"]');
              if(si) si.value = want[type];
              return;
            }
            var target = null;
            wrap.querySelectorAll('[data-type="'+type+'"]').forEach(function(x){
              if(x.getAttribute('data-val') === want[type]) target = x;
            });
            // never restore a Try-On-Wall choice we can no longer make
            if(!target || target.disabled || target.classList.contains('af-chip-oos')) return;
            wrap.querySelectorAll('[data-type="'+type+'"]').forEach(function(x){ x.classList.remove('active'); });
            target.classList.add('active');
            var input = wrap.parentNode.querySelector('input[name="af_'+type+'"]') || document.querySelector('input[name="af_'+type+'"]');
            if(input) input.value = want[type];
          });
          recalc(wrap);
        });
      }
      if(document.readyState !== 'loading') preselect();
      else document.addEventListener('DOMContentLoaded', preselect);
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
        // wording follows af_shipping_copy() so this FAQ cannot promise
        // something the badge, the bar and the chatbot no longer say
        array('Where do you deliver, and what does shipping cost?',
              (function_exists('af_shipping_copy') ? af_shipping_copy()['blurb']
               : 'We ship throughout the USA — the cost is shown at checkout.')
              . ' We ship from our Delaware studio to every state.'),
    );
}

af_section(function() {
    if (!af_show_product_sections()) return;   // page + quick-view modal, nothing else
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

// Drop the "Social Media", "Contact Us" and "About" items (and their dropdown
// children) from the header navigation only, leaving that bar as Blog. Footer
// menus and standalone social icon links are left alone.
add_filter('wp_nav_menu_objects', function($items, $args){
    $loc = isset($args->theme_location) ? strtolower((string) $args->theme_location) : '';
    if ($loc !== '' && strpos($loc, 'footer') !== false) return $items;

    // Belt-and-braces footer guard for menus rendered without a theme
    // location (widgets, page builders): anything self-describing as a footer
    // menu keeps every item.
    foreach (array('menu', 'menu_class', 'container_class', 'menu_id') as $k) {
        if (empty($args->$k)) continue;
        $v = $args->$k;
        if (is_object($v)) { $v = isset($v->slug) ? $v->slug : (isset($v->name) ? $v->name : ''); }
        if (is_string($v) && stripos($v, 'footer') !== false) return $items;
    }

    $is_header = ($loc !== '' && preg_match('/primary|header|main|top/', $loc));
    if (!$is_header) {
        // No usable location (builder menus): identify the header nav by the
        // items it carries. Checked BEFORE anything is removed, so dropping
        // those items below does not break this detection.
        $markers = 0;
        foreach ($items as $it) {
            $t = strtolower(wp_strip_all_tags($it->title));
            if (strpos($t, 'contact') !== false || strpos($t, 'about') !== false
                || strpos($t, 'blog') !== false) { $markers++; }
        }
        if ($markers < 2) return $items;
    }

    $remove = array();
    foreach ($items as $it) {
        $t = strtolower(trim(wp_strip_all_tags($it->title)));
        $t = preg_replace('/\s+/', ' ', $t);
        if ($t === 'social media' || $t === 'social' || $t === 'social medias'
            || $t === 'contact us' || $t === 'contact'
            || $t === 'about' || $t === 'about us') {
            $remove[] = (int) $it->ID;
        }
    }
    if (!$remove) return $items;

    // Pull the dropdown children down with the parent.
    $changed = true;
    while ($changed) {
        $changed = false;
        foreach ($items as $it) {
            if (in_array((int) $it->menu_item_parent, $remove, true)
                && !in_array((int) $it->ID, $remove, true)) {
                $remove[] = (int) $it->ID;
                $changed  = true;
            }
        }
    }
    return array_values(array_filter($items, function($it) use ($remove) {
        return !in_array((int) $it->ID, $remove, true);
    }));
}, 25, 2);

// DOM fallback for builder-rendered headers that never pass through
// wp_nav_menu_objects. Same scoping rule: only the nav holding Contact, and
// never inside a footer.
add_action('wp_footer', function() {
    if (is_admin()) return;
    ?>
<script>
(function(){
  function strip(){
    var lists = document.querySelectorAll('ul');
    for (var i = 0; i < lists.length; i++) {
      var ul = lists[i];
      if (ul.closest('footer, .footer, #footer, .site-footer, .elementor-location-footer')) continue;
      var links = ul.querySelectorAll(':scope > li > a');
      if (links.length < 2) continue;                 // not a real nav bar
      // Identify the header nav by its remaining items, so this keeps
      // matching after Contact Us is gone.
      var markers = 0;
      for (var j = 0; j < links.length; j++) {
        if (/contact|about|blog/i.test(links[j].textContent || '')) markers++;
      }
      if (markers < 2) continue;
      for (var k = 0; k < links.length; k++) {
        var txt = (links[k].textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
        if (txt === 'social media' || txt === 'social'
            || txt === 'contact us' || txt === 'contact'
            || txt === 'about' || txt === 'about us') {
          var li = links[k].closest('li');
          if (li) li.style.setProperty('display', 'none', 'important');
        }
      }
    }
  }
  document.addEventListener('DOMContentLoaded', strip);
  window.addEventListener('load', strip);
  [300, 1000, 2200].forEach(function(d){ setTimeout(strip, d); });
})();
</script>
<?php }, 60);

// ─────────────────────────────────────────────────────────────
// PHASE 13 — Fully dynamic standalone "Try It On Your Wall" page.
// Replaces the static Elementor mockup at /try-on-wall/ with a working
// tool: Category→Product pickers, frame type/size/color, wall-photo
// upload, live framed preview, drag + scale, live price, buy link.
// ─────────────────────────────────────────────────────────────
add_action('template_redirect', function(){
    if (!function_exists('is_page') || !is_page(array('try-on-wall','try-it-on-your-wall'))) return;
    if (!function_exists('wc_get_products')) return;

    // Build catalog data. Skip utility categories that aren't real shopping
    // groups: size cats ("3x4 ft"), frame-color/size/type cats and their
    // children (Black/Gold/Silver…), uncategorized.
    $cats = get_terms(array('taxonomy'=>'product_cat','hide_empty'=>true,'orderby'=>'name'));
    $cat_items = array();
    $skip_slugs = array('frame-colors','frame-sizes','frame-types','black','gold','silver','rose-gold','uncategorized');
    $skip_parents = array();
    if (!is_wp_error($cats)) {
        foreach ($cats as $c) {
            if (in_array($c->slug, array('frame-colors','frame-sizes','frame-types'), true)) $skip_parents[] = (int) $c->term_id;
        }
        foreach ($cats as $c) {
            if (in_array($c->slug, $skip_slugs, true)) continue;
            if (in_array((int) $c->parent, $skip_parents, true)) continue;
            if (preg_match('/^\d+(?:\.\d+)?\s*[x×]\s*\d+/iu', $c->name)) continue;   // "3x4 ft" size cats
            $cat_items[] = array('slug'=>$c->slug,'name'=>$c->name);
        }
    }

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
            'gf'=>function_exists('af_goldfoil_factor') ? af_goldfoil_factor($pid) : 1,
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
    af_preview_share_assets();
    ?>
    <div class="af-tow-wrap">
      <div class="af-tow-card">
        <a class="af-tow-home" href="<?php echo esc_url(home_url('/')); ?>">← Back to Home</a>
        <span class="af-tow-badge">Live Preview</span>
        <h1 class="af-tow-title">See It on Your Wall</h1>
        <p class="af-tow-sub">Choose an artwork and frame, then place it in a real room — or upload a photo of your own wall to preview it to scale.</p>

        <div class="af-tow-grid">
          <div class="af-tow-panel">
            <div class="af-tow-step">
              <span class="af-tow-stepnum">1</span>
              <span class="af-tow-steptitle">Pick your artwork</span>
            </div>
            <label>Category</label>
            <select id="tow-cat"><option value="">All Categories</option></select>

            <label>Choose Product</label>
            <select id="tow-prod"><option value="">Select a product</option></select>

            <div class="af-tow-step">
              <span class="af-tow-stepnum">2</span>
              <span class="af-tow-steptitle">Style the frame</span>
            </div>
            <label>Frame Type</label>
            <select id="tow-frame"></select>

            <label>Frame Size</label>
            <select id="tow-size"></select>

            <label>Frame Color</label>
            <div id="tow-colorsw" class="af-tow-swatches"></div>
            <select id="tow-color" class="af-tow-hiddensel"></select>

            <label>Wall Layout</label>
            <div class="af-tow-layouts" id="tow-layouts">
              <button type="button" class="af-tow-lay on" data-n="1"><span>Single</span></button>
              <button type="button" class="af-tow-lay" data-n="2"><span>2 Panels</span></button>
              <button type="button" class="af-tow-lay" data-n="4"><span>4 Panels</span></button>
            </div>

            <!-- Wall height belongs here, in the panel, because it changes what
                 the visitor is looking at: a 3 ft print covers more of an 8 ft
                 wall than of a 10 ft one. There is a second copy of this row
                 inside the camera calibration overlay, which is where it used
                 to live ALONE — and that overlay only exists while the camera
                 is running, and hides itself a second after the wall locks. So
                 for anyone using a room photo the control was not on screen at
                 all. Both rows drive af_tow_set_wall_ft() and stay in step. -->
            <label>Wall height</label>
            <div class="af-tow-wallh" id="tow-wallh">
              <button type="button" data-ft="8">8 ft</button>
              <button type="button" data-ft="9">9 ft</button>
              <button type="button" data-ft="10" class="on">10 ft</button>
            </div>

            <div class="af-tow-price"><span>Your price</span><strong id="tow-price">—</strong></div>
            <div class="af-tow-actions">
              <button type="button" id="tow-save" class="af-tow-btn ghost">⤓ Save Preview</button>
              <a href="#" id="tow-view" class="af-tow-btn solid">View Product</a>
            </div>

            <p class="af-sharelabel">Keep it &amp; share it</p>
            <div class="af-share">
              <button type="button" id="tow-saveacct">💾 Save to my account</button>
            </div>
            <div class="af-share">
              <button type="button" id="tow-share-wa" class="af-share-wa">WhatsApp</button>
              <button type="button" id="tow-share-mail">Email</button>
              <button type="button" id="tow-share-copy">Copy link</button>
            </div>
            <p class="af-tow-fine">
              Saved previews live in your account.
              <a href="#" id="tow-acctlink" style="display:none">View saved previews →</a>
            </p>
          </div>

          <div class="af-tow-stagewrap">
            <div id="tow-stage" class="af-tow-stage">
              <img id="tow-wallimg" class="af-tow-wallimg" alt="">
              <video id="tow-cam" class="af-tow-cam" autoplay playsinline muted></video>
              <div id="tow-placeholder" class="af-tow-ph"><span class="af-tow-ph-ic">🖼️</span>Choose a product on the left to preview it on the wall. Drag to reposition, and use the slider to resize.</div>
              <div id="tow-framebox" class="af-tow-framebox" style="display:none">
                <div id="tow-panels" class="af-tow-panels"></div>
                <span class="af-tow-hint">✥ drag</span>
              </div>
              <button type="button" id="tow-camstop" class="af-tow-camstop" style="display:none">✕ Stop camera</button>
              <!-- live-camera calibration: fit the wall into the rectangle and it
                   turns green — from that moment scale is MEASURED, not assumed -->
              <div id="tow-cal" class="af-tow-cal" style="display:none">
                <div id="tow-calbox" class="af-tow-calbox">
                  <span class="af-tow-calcorner tl"></span><span class="af-tow-calcorner tr"></span>
                  <span class="af-tow-calcorner bl"></span><span class="af-tow-calcorner br"></span>
                </div>
                <div id="tow-calmsg" class="af-tow-calmsg">Step back or forward until the <strong>ceiling line</strong> touches the top edge and the <strong>floor line</strong> touches the bottom edge</div>
                <div id="tow-calh" class="af-tow-calh">
                  <span>Wall height</span>
                  <button type="button" data-ft="8">8 ft</button>
                  <button type="button" data-ft="9">9 ft</button>
                  <button type="button" data-ft="10" class="on">10 ft</button>
                </div>
              </div>
              <button type="button" id="tow-recal" class="af-tow-recal" style="display:none">📐 True scale locked · tap to recalibrate</button>
              <div id="tow-toast" class="af-tow-toast"></div>
            </div>
            <p class="af-tow-tip">Tip: drag the artwork to line it up with your furniture, then hit <strong>Save Preview</strong> — the image downloads to your device.</p>

            <!-- Step 3 sits under the wall it controls: it fills the column
                 beside the (much taller) options rail instead of leaving the
                 page half empty, and the room switcher is next to the room. -->
            <div class="af-tow-panel af-tow-room">
              <div class="af-tow-step">
                <span class="af-tow-stepnum">3</span>
                <span class="af-tow-steptitle">Set the room</span>
              </div>
              <div class="af-tow-roomgrid">
                <div class="af-tow-roomcol">
                  <label>Room Scene</label>
                  <div id="tow-scenes" class="af-tow-scenes"></div>
                  <label class="af-tow-upload"><input type="file" id="tow-wall" accept="image/*" hidden><span>⬆ Upload your own wall photo</span></label>
                </div>
                <div class="af-tow-roomcol">
                  <button type="button" id="tow-cambtn" class="af-tow-cambtn">🎥 Use live camera <em>point it at your wall</em></button>
                  <p class="af-tow-scalenote">📏 Shown true to scale on a 10&nbsp;ft wall</p>
                  <label>Adjust Size <span id="tow-scaleval">100%</span></label>
                  <input type="range" id="tow-scale" min="40" max="160" value="100">
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <script>
    (function(){
      var CATS = <?php echo wp_json_encode($cat_items); ?>;
      var PRODUCTS = <?php echo wp_json_encode($prod_items); ?>;
      window.AFProducts = PRODUCTS;   // same array, for the test harness (already public in page source)
      var CFG = <?php echo wp_json_encode($cfg); ?>;
      var SYM = <?php echo wp_json_encode($sym); ?>;
      // Frames we can actually make — the rest are listed but not selectable.
      var INSTOCK = <?php echo wp_json_encode(function_exists('af_frames_in_stock') ? af_frames_in_stock() : array_keys($cfg['frames'])); ?>;
      // Sizes we currently offer — the rest are not listed at all.
      var SIZES = <?php echo wp_json_encode(function_exists('af_sizes_available') ? af_sizes_available() : array_keys($cfg['sizes'])); ?>;
      var $=function(id){return document.getElementById(id);};

      // Populate selects
      CATS.forEach(function(c){ var o=document.createElement('option'); o.value=c.slug; o.textContent=c.name; $('tow-cat').appendChild(o); });
      Object.keys(CFG.frames).forEach(function(f){ var o=document.createElement('option'); o.value=f;
        var oos = INSTOCK.indexOf(f) === -1;
        o.textContent=f+(CFG.frames[f]>0?(' (+'+SYM+CFG.frames[f]+')'):'')+(oos?' — Out of stock':'');
        o.disabled = oos; if(!oos && !$('tow-frame').value) o.selected = true;
        $('tow-frame').appendChild(o); });
      SIZES.forEach(function(s){ var o=document.createElement('option'); o.value=s; o.textContent=s; $('tow-size').appendChild(o); });
      Object.keys(CFG.colors).forEach(function(c){ var o=document.createElement('option'); o.value=c; o.textContent=c+(CFG.colors[c]>0?(' (+'+SYM+CFG.colors[c]+')'):''); $('tow-color').appendChild(o); });

      // Realistic frame material gradients (bevel comes from CSS box-shadows)
      var SWATCH={'Black':'#1e1e1e','Silver':'#c0c0c0','Gold':'#d4af37','Rose Gold':'#b76e79'};
      var MAT={
        'Black':'linear-gradient(135deg,#4a4a4a 0%,#1c1c1c 42%,#050505 58%,#333 100%)',
        'Silver':'linear-gradient(135deg,#fdfdfd 0%,#c4c4c4 42%,#8f8f8f 58%,#e6e6e6 100%)',
        'Gold':'linear-gradient(135deg,#fbe7ad 0%,#d8b445 42%,#a67c1e 58%,#f2d879 100%)',
        'Rose Gold':'linear-gradient(135deg,#f7d3c8 0%,#dca596 42%,#b76e79 58%,#efbcae 100%)'
      };
      // Layers a mitred corner seam (the diagonal joint where two moulding
      // sides meet on a real frame) plus a polished-metal specular glint on
      // top of the material gradient — matched to reference photos of the
      // actual aluminium mouldings (buffed sheen + hard highlight, not a
      // brushed/sandy texture). Corner lines use a fixed px width via
      // calc() so they read the same on a thumbnail as on a full-size stage.
      function frameBG(mat){
        var hi='rgba(255,255,255,.45)', lo='rgba(0,0,0,.5)';
        return [
          // mitred corner grooves: a lit inner edge then a dark outer edge per
          // quadrant, so each corner reads as a carved cut rather than a
          // printed line
          'linear-gradient(to bottom right, transparent calc(50% - 2.5px), '+hi+' calc(50% - 1.5px), '+lo+' calc(50% + .5px), transparent calc(50% + 2px)) left top/50% 50% no-repeat',
          'linear-gradient(to bottom left,  transparent calc(50% - 2.5px), '+hi+' calc(50% - 1.5px), '+lo+' calc(50% + .5px), transparent calc(50% + 2px)) right top/50% 50% no-repeat',
          'linear-gradient(to top right,    transparent calc(50% - 2.5px), '+hi+' calc(50% - 1.5px), '+lo+' calc(50% + .5px), transparent calc(50% + 2px)) left bottom/50% 50% no-repeat',
          'linear-gradient(to top left,     transparent calc(50% - 2.5px), '+hi+' calc(50% - 1.5px), '+lo+' calc(50% + .5px), transparent calc(50% + 2px)) right bottom/50% 50% no-repeat',
          // polished-metal glint: a narrow, hard-edged bright streak swept
          // around the ring so the top/left face catches a sharp specular
          // highlight the way buffed aluminium does, with a fainter second
          // glint opposite it
          'conic-gradient(from 300deg at 50% 50%, rgba(255,255,255,0) 0deg, rgba(255,255,255,.85) 16deg, rgba(255,255,255,0) 38deg, rgba(255,255,255,0) 175deg, rgba(255,255,255,.5) 196deg, rgba(255,255,255,0) 218deg, rgba(255,255,255,0) 360deg)',
          // broad wraparound light/shadow so each of the 4 faces reads as its
          // own lit or shaded plane (upper-left key light), pushed to higher
          // contrast so the moulding reads as metal, not painted flat colour
          'conic-gradient(from 315deg at 50% 50%, rgba(255,255,255,.5) 0deg, rgba(255,255,255,0) 95deg, rgba(0,0,0,.55) 180deg, rgba(0,0,0,0) 265deg, rgba(255,255,255,.5) 360deg)',
          mat
        ].join(',');
      }

      // ── PHOTOREAL FRAME TEXTURES ─────────────────────────────────────────
      // Paints an actual picture of a frame — mitred corners, a moulding
      // profile lit from above, and per-material surface texture (brushed
      // metal, satin lacquer, fibre grain) — on an offscreen canvas, matched
      // to the studio's real moulding photos. Applied with border-image, so
      // the corners stay true 45° joints at any panel size, and drawn into
      // the saved preview by 9-slice so the download matches the screen.
      var TEXQ = {};
      // Desaturated faces, patina-dark recesses and CREAM highlights — never
      // white. Full-saturation colour with a white-hot crown is exactly what
      // made the first pass read as plastic.
      function framePalette(color){
        return ({
          'Black':     {face:'#282520', hi:'#6e6a62', crown:'#3d3a34', lo:'#0a0806'},
          'Silver':    {face:'#b6b9bd', hi:'#eef0f2', crown:'#d0d3d7', lo:'#585c61'},
          'Gold':      {face:'#b08a40', hi:'#f0dfa4', crown:'#d2ab58', lo:'#553f10'},
          'Rose Gold': {face:'#a86f60', hi:'#e2bfae', crown:'#c28d7c', lo:'#4e2e25'}
        })[color] || null;
      }
      // Fine monochrome grain, tiled over the moulding at low alpha so flat
      // runs of colour stop looking computer-clean. Deterministic, so every
      // render of a texture is identical.
      var NOISETILE = null;
      function noiseTile(){
        if (NOISETILE) return NOISETILE;
        var n = document.createElement('canvas'); n.width = 128; n.height = 128;
        var nx = n.getContext('2d'), id = nx.createImageData(128, 128);
        var s = 13; function r(){ s = (s * 16807) % 2147483647; return s / 2147483647; }
        for (var i = 0; i < id.data.length; i += 4) {
          var v = Math.floor(r() * 255);
          id.data[i] = v; id.data[i+1] = v; id.data[i+2] = v; id.data[i+3] = 16;
        }
        nx.putImageData(id, 0, 0);
        NOISETILE = n; return n;
      }
      function frameTexture(frame, color){
        var key = frame + '|' + color;
        if (TEXQ[key]) return TEXQ[key];
        var pal = framePalette(color) || framePalette('Black');
        var S = 512, B = 96;
        var cv = document.createElement('canvas'); cv.width = S; cv.height = S;
        var g = cv.getContext('2d');

        // One horizontal strip of moulding, grain running along its length.
        var strip = document.createElement('canvas'); strip.width = S; strip.height = B;
        var sg = strip.getContext('2d');
        // The moulding profile, lit from the top. Every transition passes
        // through crown (a midtone) on its way to hi, so the highlight rolls
        // off the way light leaves a curved surface — a stop that jumps
        // straight from face to hi is the hard bright band that read as fake.
        var grad = sg.createLinearGradient(0, 0, 0, B);
        grad.addColorStop(0.00, pal.lo);
        grad.addColorStop(0.05, pal.crown);
        grad.addColorStop(0.13, pal.face);
        grad.addColorStop(0.30, pal.crown);
        grad.addColorStop(0.42, pal.hi);
        grad.addColorStop(0.50, pal.crown);
        grad.addColorStop(0.68, pal.face);
        grad.addColorStop(0.88, pal.lo);
        grad.addColorStop(0.935, pal.crown);
        grad.addColorStop(1.00, pal.lo);
        sg.fillStyle = grad; sg.fillRect(0, 0, S, B);
        // Deterministic pseudo-random, so every render of a texture is identical
        var seed = 7; function rnd(){ seed = (seed * 16807) % 2147483647; return seed / 2147483647; }
        if (frame === 'Aluminium Frame') {
          // brushed metal: dense hairlines along the extrusion
          for (var i = 0; i < 110; i++) {
            var y = rnd() * B;
            sg.strokeStyle = 'rgba(' + (rnd() < 0.5 ? '255,255,255' : '0,0,0') + ',' + (0.028 + rnd() * 0.062).toFixed(3) + ')';
            sg.lineWidth = 0.6 + rnd() * 0.9;
            sg.beginPath(); sg.moveTo(0, y); sg.lineTo(S, y); sg.stroke();
          }
        } else if (frame === 'Fibre Frame') {
          // fibre/wood: long shallow grain waves with occasional darker streaks
          for (var j = 0; j < 26; j++) {
            var gy = 4 + rnd() * (B - 8), amp = 0.6 + rnd() * 1.8, wav = 70 + rnd() * 160, ph = rnd() * 6.28;
            var dark = rnd() < 0.28;
            sg.strokeStyle = dark ? 'rgba(0,0,0,' + (0.10 + rnd() * 0.08).toFixed(3) + ')'
                                  : 'rgba(255,255,255,' + (0.035 + rnd() * 0.05).toFixed(3) + ')';
            sg.lineWidth = dark ? 1.2 : 0.8;
            sg.beginPath();
            for (var x = 0; x <= S; x += 7) {
              var yy = gy + Math.sin(x / wav + ph) * amp;
              x === 0 ? sg.moveTo(x, yy) : sg.lineTo(x, yy);
            }
            sg.stroke();
          }
        } else {
          // Floating frame: satin lacquer — a soft sheen, almost no pattern
          for (var k = 0; k < 18; k++) {
            var sy = rnd() * B;
            sg.strokeStyle = 'rgba(255,255,255,' + (0.015 + rnd() * 0.03).toFixed(3) + ')';
            sg.lineWidth = 1.5;
            sg.beginPath(); sg.moveTo(0, sy); sg.lineTo(S, sy); sg.stroke();
          }
        }
        // slow luminance drift along the stick — real moulding is never one
        // even value end to end
        var lw = sg.createLinearGradient(0, 0, S, 0);
        lw.addColorStop(0, 'rgba(255,255,255,.04)');
        lw.addColorStop(0.3, 'rgba(0,0,0,.03)');
        lw.addColorStop(0.55, 'rgba(255,255,255,.05)');
        lw.addColorStop(0.8, 'rgba(0,0,0,.04)');
        lw.addColorStop(1, 'rgba(255,255,255,.02)');
        sg.fillStyle = lw; sg.fillRect(0, 0, S, B);
        // crisp outer and rebate edges
        sg.fillStyle = 'rgba(0,0,0,.38)'; sg.fillRect(0, 0, S, 1); sg.fillRect(0, B - 1, S, 1);

        // Lay the strip on all four sides, each clipped to its mitred trapezoid
        // so the grain runs along every side and the corners join at 45°.
        // Each side then takes its own exposure from the room's key light,
        // above and slightly left: top rail lit, left rail faintly lit, right
        // rail shaded, bottom rail in shadow. One flat gradient over the whole
        // ring — the old approach — is precisely what a photograph never does.
        var sides = [
          {path: [[0,0],[S,0],[S-B,B],[B,B]],       tx: 0, ty: 0, rot: 0,           lum:  0.09},
          {path: [[S,0],[S,S],[S-B,S-B],[S-B,B]],   tx: S, ty: 0, rot: Math.PI/2,   lum: -0.06},
          {path: [[S,S],[0,S],[B,S-B],[S-B,S-B]],   tx: S, ty: S, rot: Math.PI,     lum: -0.14},
          {path: [[0,S],[0,0],[B,B],[B,S-B]],       tx: 0, ty: S, rot: -Math.PI/2,  lum:  0.03}
        ];
        sides.forEach(function(sd){
          g.save();
          g.beginPath();
          sd.path.forEach(function(pt, n){ n === 0 ? g.moveTo(pt[0], pt[1]) : g.lineTo(pt[0], pt[1]); });
          g.closePath(); g.clip();
          g.translate(sd.tx, sd.ty); g.rotate(sd.rot);
          g.drawImage(strip, 0, 0);
          g.setTransform(1, 0, 0, 1, 0, 0);
          g.fillStyle = sd.lum > 0 ? 'rgba(255,255,255,' + sd.lum + ')' : 'rgba(0,0,0,' + (-sd.lum) + ')';
          g.fillRect(0, 0, S, S);
          g.restore();
        });
        // mitre seams: the dark joint line with a lit edge beside it — present,
        // as in the moulding photos, but a joint, not a drawn-on stripe
        [[0,0,B,B],[S,0,S-B,B],[S,S,S-B,S-B],[0,S,B,S-B]].forEach(function(c){
          g.strokeStyle = 'rgba(0,0,0,.28)'; g.lineWidth = 1.6;
          g.beginPath(); g.moveTo(c[0], c[1]); g.lineTo(c[2], c[3]); g.stroke();
          g.strokeStyle = 'rgba(255,255,255,.12)'; g.lineWidth = 0.8;
          g.beginPath(); g.moveTo(c[0] + (c[2] > c[0] ? 1.6 : -1.6), c[1]); g.lineTo(c[2] + (c[2] > c[0] ? 1.6 : -1.6), c[3]); g.stroke();
        });
        // a soft light-catch where the key light grazes the top rail
        g.globalCompositeOperation = 'source-atop';
        var blob = g.createRadialGradient(S * 0.30, B * 0.45, 4, S * 0.30, B * 0.45, S * 0.28);
        blob.addColorStop(0, 'rgba(255,255,255,.22)');
        blob.addColorStop(1, 'rgba(255,255,255,0)');
        g.fillStyle = blob; g.fillRect(0, 0, S, S);
        // film grain over the whole ring
        g.globalAlpha = 0.06;
        var nt = noiseTile();
        for (var ny = 0; ny < S; ny += 128) for (var nx2 = 0; nx2 < S; nx2 += 128) g.drawImage(nt, nx2, ny);
        g.globalAlpha = 1;
        g.globalCompositeOperation = 'source-over';

        TEXQ[key] = {url: cv.toDataURL('image/png'), cv: cv, B: B, S: S};
        return TEXQ[key];
      }
      // Border width for the current profile, from the panel's real on-screen
      // size — border-width cannot take %, so this runs whenever the box is
      // (re)measured.
      function sizeFrameBorders(){
        var fb = $('tow-framebox');
        fb.querySelectorAll('.af-tow-pframe').forEach(function(frEl){
          var prof = parseFloat(frEl.getAttribute('data-prof') || '0');
          if (!(prof > 0)) { frEl.style.borderWidth = '0'; return; }
          var w = frEl.parentNode.getBoundingClientRect().width || 0;
          frEl.style.borderWidth = Math.max(11, w * prof / 100).toFixed(1) + 'px';
        });
      }

      // Colour swatches (drive the hidden select for pricing/logic)
      (function buildSwatches(){
        var wrap=$('tow-colorsw'); wrap.innerHTML='';
        Object.keys(CFG.colors).forEach(function(c,i){
          var b=document.createElement('button'); b.type='button'; b.className='af-tow-sw'+(i===0?' on':'');
          b.setAttribute('data-c',c); b.title=c+(CFG.colors[c]>0?(' (+'+SYM+CFG.colors[c]+')'):'');
          b.style.background=SWATCH[c]||'#1a1a1a';
          b.innerHTML='<span>'+c+'</span>';
          b.addEventListener('click',function(){
            wrap.querySelectorAll('.af-tow-sw').forEach(function(x){x.classList.remove('on');});
            b.classList.add('on'); $('tow-color').value=c; refresh();
          });
          wrap.appendChild(b);
        });
      })();

      // ── Photorealistic room scenes (SVG data-URIs, no external assets) ──
      // Bright modern living room styled like a real product mockup:
      // floor-to-ceiling window on the right casting a soft light patch on
      // the wall, curtains, pale wood floor in perspective, woven rug,
      // light sofa cropped at the left with a throw pillow + side table,
      // slim floor lamp, and a fiddle-leaf fig on the right.
      function room(o){
        var W=1600,H=1000,FLOOR=690;              // wall/floor split line
        var wall=o.wall, wall2=o.wall2, floor=o.floor, floor2=o.floor2;
        var sofa=o.sofa, sofa2=o.sofa2, rug=o.rug, curtain=o.curtain, pot=o.pot;
        // Receding floor plank seams (converge outward toward viewer)
        var seams='';
        for(var sx=-500; sx<=2100; sx+=150){
          var nx=800+(sx-800)*2.3;
          seams+='<line x1="'+sx+'" y1="'+FLOOR+'" x2="'+nx.toFixed(0)+'" y2="'+H+'"/>';
        }
        var rungs=''; var n=6;
        for(var i=1;i<=n;i++){ var t=Math.pow(i/n,2.1); var y=(FLOOR+(H-FLOOR)*t).toFixed(1);
          rungs+='<line x1="0" y1="'+y+'" x2="'+W+'" y2="'+y+'"/>'; }
        // fiddle-leaf helper
        function leaf(cx,cy,rx,ry,rot,fill){ return '<ellipse cx="'+cx+'" cy="'+cy+'" rx="'+rx+'" ry="'+ry+'" fill="'+fill+'" transform="rotate('+rot+' '+cx+' '+cy+')"/>'; }
        var svg='<svg xmlns="http://www.w3.org/2000/svg" width="'+W+'" height="'+H+'" viewBox="0 0 '+W+' '+H+'">'
        +'<defs>'
        +'<linearGradient id="wg" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="'+wall+'"/><stop offset="100%" stop-color="'+wall2+'"/></linearGradient>'
        +'<linearGradient id="fg" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="'+floor2+'"/><stop offset="46%" stop-color="'+floor+'"/><stop offset="100%" stop-color="'+floor+'"/></linearGradient>'
        +'<linearGradient id="sky" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#d6e7ef"/><stop offset="60%" stop-color="#eaf3f7"/><stop offset="100%" stop-color="#f3f8fa"/></linearGradient>'
        +'<linearGradient id="drape" x1="0" y1="0" x2="1" y2="0"><stop offset="0%" stop-color="'+curtain+'"/><stop offset="50%" stop-color="#ffffff" stop-opacity=".5"/><stop offset="100%" stop-color="'+curtain+'"/></linearGradient>'
        +'<linearGradient id="aocc" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#000" stop-opacity=".22"/><stop offset="100%" stop-color="#000" stop-opacity="0"/></linearGradient>'
        +'<radialGradient id="vig" cx="50%" cy="42%" r="78%"><stop offset="62%" stop-color="#000" stop-opacity="0"/><stop offset="100%" stop-color="#000" stop-opacity=".16"/></radialGradient>'
        +'<filter id="wt"><feTurbulence type="fractalNoise" baseFrequency="0.9" numOctaves="2" seed="7" result="n"/><feColorMatrix in="n" type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 .03 0"/></filter>'
        +'<filter id="wood"><feTurbulence type="fractalNoise" baseFrequency="0.012 0.15" numOctaves="3" seed="3" result="n"/><feColorMatrix in="n" type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 .10 0"/></filter>'
        +'<filter id="rugt"><feTurbulence type="turbulence" baseFrequency="0.5 0.5" numOctaves="2" seed="5" result="n"/><feColorMatrix in="n" type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 .10 0"/></filter>'
        +'<filter id="soft" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur stdDeviation="16"/></filter>'
        +'<filter id="lightblur" x="-40%" y="-40%" width="180%" height="180%"><feGaussianBlur stdDeviation="18"/></filter>'
        +'<clipPath id="fc"><rect x="0" y="'+FLOOR+'" width="'+W+'" height="'+(H-FLOOR)+'"/></clipPath>'
        +'<clipPath id="wc"><rect x="0" y="0" width="'+W+'" height="'+FLOOR+'"/></clipPath>'
        +'</defs>'
        // WALL
        +'<rect width="'+W+'" height="'+FLOOR+'" fill="url(#wg)"/>'
        +'<rect width="'+W+'" height="'+FLOOR+'" filter="url(#wt)"/>'
        // soft window-light patch projected on the wall (signature look)
        +'<g clip-path="url(#wc)">'
        +'<polygon points="1030,150 1230,120 1250,540 1040,560" fill="#ffffff" opacity=".34" filter="url(#lightblur)"/>'
        +'<g stroke="#c9cdd2" stroke-opacity=".5" stroke-width="6" filter="url(#lightblur)"><line x1="1140" y1="130" x2="1150" y2="552"/><line x1="1035" y1="345" x2="1245" y2="330"/></g>'
        +'</g>'
        // floor-to-ceiling WINDOW on the right
        +'<g>'
        +'<rect x="1430" y="30" width="200" height="'+(FLOOR-30)+'" fill="url(#sky)"/>'
        +'<rect x="1430" y="30" width="200" height="'+(FLOOR-30)+'" fill="none" stroke="#3a3d42" stroke-width="12"/>'
        +'<line x1="1524" y1="36" x2="1524" y2="'+(FLOOR-6)+'" stroke="#3a3d42" stroke-width="9"/>'
        +'<line x1="1436" y1="330" x2="1624" y2="330" stroke="#3a3d42" stroke-width="9"/>'
        // curtain drape on the left edge of the window
        +'<rect x="1360" y="24" width="86" height="'+(FLOOR-10)+'" fill="url(#drape)"/>'
        +'<g stroke="#000" stroke-opacity=".06" stroke-width="10"><line x1="1378" y1="30" x2="1378" y2="'+FLOOR+'"/><line x1="1404" y1="30" x2="1404" y2="'+FLOOR+'"/><line x1="1430" y1="30" x2="1430" y2="'+FLOOR+'"/></g>'
        +'</g>'
        // baseboard
        +'<rect x="0" y="'+(FLOOR-16)+'" width="'+W+'" height="18" fill="#f4f4f2"/>'
        +'<rect x="0" y="'+(FLOOR-16)+'" width="'+W+'" height="3" fill="#000" opacity=".05"/>'
        // FLOOR (pale wood, perspective)
        +'<g clip-path="url(#fc)">'
        +'<rect x="0" y="'+FLOOR+'" width="'+W+'" height="'+(H-FLOOR)+'" fill="url(#fg)"/>'
        +'<rect x="0" y="'+FLOOR+'" width="'+W+'" height="'+(H-FLOOR)+'" filter="url(#wood)"/>'
        +'<g stroke="#000" stroke-opacity=".08" stroke-width="2">'+seams+'</g>'
        +'<g stroke="#000" stroke-opacity=".06" stroke-width="2">'+rungs+'</g>'
        +'<rect x="0" y="'+FLOOR+'" width="'+W+'" height="70" fill="url(#aocc)"/>'
        // reflected window light on the floor
        +'<polygon points="1180,'+FLOOR+' 1360,'+FLOOR+' 1520,'+H+' 1150,'+H+'" fill="#ffffff" opacity=".10" filter="url(#soft)"/>'
        +'</g>'
        // woven area RUG (perspective)
        +'<polygon points="430,'+(H-140)+' 1150,'+(H-140)+' 1360,'+H+' 250,'+H+'" fill="'+rug+'"/>'
        +'<g clip-path="url(#fc)"><polygon points="430,'+(H-140)+' 1150,'+(H-140)+' 1360,'+H+' 250,'+H+'" filter="url(#rugt)"/></g>'
        +'<polygon points="430,'+(H-140)+' 1150,'+(H-140)+' 1360,'+H+' 250,'+H+'" fill="none" stroke="#000" stroke-opacity=".08" stroke-width="4"/>'
        // slim black FLOOR LAMP (left) — drawn behind the sofa
        +'<ellipse cx="384" cy="'+(FLOOR+120)+'" rx="40" ry="12" fill="#000" opacity=".16" filter="url(#soft)"/>'
        +'<rect x="380" y="'+(FLOOR-350)+'" width="9" height="480" fill="#26282b"/>'
        +'<rect x="360" y="'+(FLOOR+118)+'" width="50" height="12" rx="4" fill="#26282b"/>'   // foot
        +'<path d="M318 '+(FLOOR-408)+' h150 l-20 84 h-110 z" fill="#f0ebde"/>'                // drum shade
        +'<ellipse cx="393" cy="'+(FLOOR-408)+'" rx="75" ry="15" fill="#f7f3e8"/>'
        +'<ellipse cx="393" cy="'+(FLOOR-408)+'" rx="55" ry="10" fill="#fffdf5" opacity=".7"/>'
        // SOFA cropped at the left (light, modern) + contact shadow
        +'<ellipse cx="250" cy="'+(FLOOR+152)+'" rx="380" ry="48" fill="#000" opacity=".22" filter="url(#soft)"/>'
        +'<g>'
        +'<rect x="-70" y="'+(FLOOR-160)+'" width="580" height="164" rx="30" fill="'+sofa2+'"/>'          // backrest
        +'<rect x="-70" y="'+(FLOOR-150)+'" width="580" height="30" rx="16" fill="#ffffff" opacity=".22"/>' // back top highlight
        +'<rect x="-80" y="'+(FLOOR-130)+'" width="150" height="290" rx="30" fill="'+sofa2+'"/>'          // arm L (cropped)
        +'<rect x="440" y="'+(FLOOR-130)+'" width="96" height="290" rx="26" fill="'+sofa+'"/>'            // arm R
        +'<rect x="60" y="'+(FLOOR-40)+'" width="400" height="190" rx="26" fill="'+sofa+'"/>'             // seat
        +'<rect x="60" y="'+(FLOOR+96)+'" width="400" height="54" rx="24" fill="#000" opacity=".10"/>'    // seat under-shadow
        +'<line x1="262" y1="'+(FLOOR-30)+'" x2="262" y2="'+(FLOOR+120)+'" stroke="#000" stroke-opacity=".08" stroke-width="6"/>' // cushion split
        +'<rect x="80" y="'+(FLOOR-30)+'" width="360" height="20" rx="10" fill="#ffffff" opacity=".14"/>' // seat top highlight
        // throw pillow
        +'<rect x="86" y="'+(FLOOR-128)+'" width="140" height="128" rx="22" fill="'+o.pillow+'" transform="rotate(-8 156 '+(FLOOR-64)+')"/>'
        +'<rect x="104" y="'+(FLOOR-110)+'" width="104" height="20" rx="10" fill="#ffffff" opacity=".18" transform="rotate(-8 156 '+(FLOOR-64)+')"/>'
        +'<rect x="452" y="'+(FLOOR+120)+'" width="18" height="34" fill="#2a221c"/>'
        +'</g>'
        // low side TABLE with books (front-left)
        +'<ellipse cx="250" cy="'+(H-64)+'" rx="150" ry="26" fill="#000" opacity=".14" filter="url(#soft)"/>'
        +'<rect x="120" y="'+(H-150)+'" width="240" height="30" rx="6" fill="#8a6a48"/>'
        +'<rect x="140" y="'+(H-120)+'" width="16" height="120" fill="#6f5236"/><rect x="324" y="'+(H-120)+'" width="16" height="120" fill="#6f5236"/>'
        +'<rect x="150" y="'+(H-172)+'" width="120" height="22" rx="4" fill="#c9bfa8"/><rect x="164" y="'+(H-190)+'" width="96" height="20" rx="4" fill="#b7ac93"/>'
        // FIDDLE-LEAF fig on the right
        +'<ellipse cx="1300" cy="'+(FLOOR+140)+'" rx="90" ry="22" fill="#000" opacity=".18" filter="url(#soft)"/>'
        +'<rect x="1250" y="'+(FLOOR+40)+'" width="110" height="120" rx="12" fill="'+pot+'"/>'
        +'<rect x="1250" y="'+(FLOOR+40)+'" width="110" height="18" rx="8" fill="#000" opacity=".06"/>'
        +'<g stroke="#5a4632" stroke-width="7" fill="none"><path d="M1305 '+(FLOOR+40)+' C 1300 '+(FLOOR-120)+' 1300 '+(FLOOR-220)+' 1305 '+(FLOOR-300)+'"/></g>'
        +leaf(1305,(FLOOR-300),34,58,4,'#3f6b3a')
        +leaf(1240,(FLOOR-250),30,52,-38,'#4b7d43')
        +leaf(1372,(FLOOR-250),30,52,38,'#356035')
        +leaf(1235,(FLOOR-140),30,50,-58,'#4b7d43')
        +leaf(1378,(FLOOR-140),30,50,58,'#3f6b3a')
        +leaf(1305,(FLOOR-210),30,52,0,'#57904c')
        +leaf(1258,(FLOOR-60),26,44,-72,'#3f6b3a')
        +leaf(1352,(FLOOR-60),26,44,72,'#4b7d43')
        // GLOBAL soft vignette
        +'<rect width="'+W+'" height="'+H+'" fill="url(#vig)"/>'
        +'</svg>';
        return 'data:image/svg+xml;charset=utf8,'+encodeURIComponent(svg);
      }
      var MOCKBASE=<?php echo wp_json_encode( wp_get_upload_dir()['baseurl'] . '/mockups/' ); ?>;
      var SCENES=[
        {name:'Living Room', photo:1, bg:MOCKBASE+'Radha-Krishna-Canvas-Wall-Art-Placement_Living-Room-blankwall.jpg', focus:'52% 50%'},
        {name:'Lounge',      photo:1, bg:MOCKBASE+'Radha-Krishna_Wall-Art_-Living-Room-blankwall.jpg',                 focus:'51% 50%'},
        {name:'Bright Loft', photo:1, bg:MOCKBASE+'Radha-Krishna-Abstract-Wall-Art_living-room-blankwall.jpg',         focus:'56% 50%'},
        {name:'Studio',      photo:1, bg:MOCKBASE+'TAF-RADHA-KRISHNA-18530-room-1-blankwall.jpg',                      focus:'48% 50%'}
      ];
      var sceneWrap=$('tow-scenes');
      SCENES.forEach(function(s,i){
        var b=document.createElement('button'); b.type='button'; b.className='af-tow-scene'+(i===0?' on':'');
        b.style.backgroundImage='url("'+s.bg+'")'; b.title=s.name;
        b.innerHTML='<span>'+s.name+'</span>';
        b.addEventListener('click',function(){ setScene(i); });
        sceneWrap.appendChild(b);
      });
      var usingUpload=false, sceneIdx=0;
      function setScene(i){
        usingUpload=false; sceneIdx=i; savedURL=''; stopCam();
        sceneWrap.querySelectorAll('.af-tow-scene').forEach(function(x,j){ x.classList.toggle('on', j===i); });
        var im=$('tow-wallimg'); im.src=SCENES[i].bg; im.style.display='block';
        im.style.objectPosition = SCENES[i].focus || '50% 50%';
        $('tow-placeholder').style.display = current() ? 'none' : 'flex';
      }

      // ── LIVE CAMERA (spec §8): point the camera at your wall and the framed
      //    artwork is overlaid in real time. Uses the rear camera on phones.
      var camOn=false, camStream=null, camSeq=0, camStarting=false;
      function startCam(){
        if(camOn || camStarting) return;            // ignore impatient double clicks
        if(!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia)){
          toast('Camera not supported on this browser'); return;
        }
        var seq=++camSeq; camStarting=true;
        navigator.mediaDevices.getUserMedia({ video:{ facingMode:'environment', width:{ideal:1920}, height:{ideal:1080} }, audio:false })
          .then(function(stream){
            camStarting=false;
            // The visitor moved on while the permission prompt was open —
            // throw the stream away instead of hijacking the stage back.
            if(seq!==camSeq){ stream.getTracks().forEach(function(t){ t.stop(); }); return; }
            camStream=stream; camOn=true;
            // if the track dies on its own (call, app switch, revoked permission)
            // fall back to the room photo instead of freezing on a dead frame
            stream.getTracks().forEach(function(t){
              t.addEventListener('ended', function(){ if(camStream===stream) stopCam(); });
            });
            var v=$('tow-cam'); v.srcObject=stream; v.style.display='block';
            $('tow-wallimg').style.display='none';
            $('tow-camstop').style.display='block';
            sceneWrap.querySelectorAll('.af-tow-scene').forEach(function(x){x.classList.remove('on');});
            $('tow-placeholder').style.display = current() ? 'none' : 'flex';
            camLabel();
            calStart();
            toast('🎥 Live camera on — point it at your wall');
          })
          .catch(function(err){
            camStarting=false; camLabel();
            if(seq!==camSeq) return;
            toast(err && err.name==='NotAllowedError' ? 'Camera permission was denied' : 'Could not start the camera');
          });
      }
      function stopCam(){
        camSeq++; camStarting=false;              // cancels any start still in flight
        if(camStream){ camStream.getTracks().forEach(function(t){ t.stop(); }); camStream=null; }
        camOn=false;
        calStop();
        var v=$('tow-cam'); v.srcObject=null; v.style.display='none';
        $('tow-camstop').style.display='none';
        // bring the room photo (or the uploaded wall) back, and re-highlight
        // the thumbnail that matches what is now on the stage
        var im=$('tow-wallimg');
        if(im.getAttribute('src')){
          im.style.display='block';
          if(!usingUpload) sceneWrap.querySelectorAll('.af-tow-scene').forEach(function(x,j){ x.classList.toggle('on', j===sceneIdx); });
        }
        camLabel();
      }
      // the button is a toggle, so it has to say which way it will go
      function camLabel(){
        $('tow-cambtn').innerHTML = (camOn || camStarting)
          ? '⏹ Stop live camera <em>back to the room photo</em>'
          : '🎥 Use live camera <em>point it at your wall</em>';
      }
      $('tow-cambtn').addEventListener('click', function(){
        if(camOn || camStarting) stopCam(); else startCam();
        camLabel();
      });
      $('tow-camstop').addEventListener('click', function(){ stopCam(); });
      window.addEventListener('pagehide', stopCam);

      // ── LIVE-CAMERA CALIBRATION — measured true scale ─────────────────
      // The rectangle over the feed stands for the wall between ceiling and
      // floor. The visitor steps back or forward until both lines sit on its
      // edges; an edge detector watches the feed and flips the rectangle red
      // → amber → green, and at the lock pixels-per-foot becomes MEASURED
      // (rectangle height ÷ chosen wall height) instead of assumed. After
      // the lock the same detector keeps tracking the two lines, so walking
      // closer or farther rescales the artwork exactly as a real painting
      // grows and shrinks in a person's view. If the lines are lost the
      // scale simply holds — it never jumps.
      // wallFt is the height of the wall being looked at, in feet, and it is
      // the visitor's to choose — the Wall height buttons set it. It starts at
      // the height the room photos are drawn against (WALL_FT below), so the
      // button marked "on" and the picture on screen agree before anything is
      // touched. Both must move together if this default ever changes.
      var CAL = { locked:false, wallFt:10, base:0, pxPerFt:0, factor:1,
                  streak:0, spanLock:0, timer:null };
      window.AFCal = CAL;   // read-only view for the harness and live verifier
      var CAL_TOP=0.16, CAL_BOT=0.84;   // rectangle edges as fractions of stage height
      var calCv=document.createElement('canvas');
      calCv.width=120; calCv.height=90;
      var calCx=calCv.getContext('2d', { willReadFrequently:true });

      // where the rectangle's edges land in detector-row space, allowing for
      // the object-fit:cover crop of the feed inside the stage
      function calRows(){
        var v=$('tow-cam'); if(!(v.videoWidth>0)) return null;
        var st=$('tow-stage').getBoundingClientRect();
        var s=Math.max(st.width/v.videoWidth, st.height/v.videoHeight);
        var cropY=(v.videoHeight - st.height/s)/2;
        var toRow=function(frac){
          return (cropY + (frac*st.height)/s) / v.videoHeight * calCv.height;
        };
        return { top:toRow(CAL_TOP), bot:toRow(CAL_BOT) };
      }

      // the strongest horizontal edge in the upper and the lower half of the
      // feed — on a wall shot those are the ceiling line and the floor line
      function calSample(){
        var v=$('tow-cam'); if(!(v.videoWidth>0)) return null;
        try{
          calCx.drawImage(v,0,0,calCv.width,calCv.height);
          var d=calCx.getImageData(0,0,calCv.width,calCv.height).data;
        }catch(e){ return null; }
        var W=calCv.width,H=calCv.height,x0=Math.round(W*0.15),x1=Math.round(W*0.85);
        function lum(x,y){ var i=(y*W+x)*4; return d[i]*0.299+d[i+1]*0.587+d[i+2]*0.114; }
        var rows=[];
        for(var y=1;y<H-1;y++){ var acc=0; for(var x=x0;x<x1;x++){ acc+=Math.abs(lum(x,y+1)-lum(x,y-1)); } rows[y]=acc; }
        var sorted=rows.filter(function(v2){return v2!=null;}).sort(function(a,b){return a-b;});
        var med=sorted[Math.floor(sorted.length/2)]||1;
        function pick(a,b){
          var best=-1, by=0;
          for(var y=Math.max(1,Math.round(a)); y<Math.min(H-1,Math.round(b)); y++){
            if(rows[y]>best){ best=rows[y]; by=y; }
          }
          return { y:by, ok: best > med*2.2 };
        }
        return { top:pick(H*0.03,H*0.48), bot:pick(H*0.52,H*0.97) };
      }

      function calTick(){
        if(!camOn) return;
        var s=calSample(); if(!s) return;
        if(!CAL.locked){
          var r=calRows(); if(!r) return;
          var tol=calCv.height*0.055;
          var hit=s.top.ok && s.bot.ok &&
                  Math.abs(s.top.y-r.top)<tol && Math.abs(s.bot.y-r.bot)<tol;
          $('tow-calbox').classList.toggle('near', hit);
          CAL.streak = hit ? CAL.streak+1 : 0;
          if(CAL.streak>=5) calLock(s);      // ~0.8s of steady alignment
        }else{
          if(!(s.top.ok && s.bot.ok)) return;          // lines lost: hold scale
          var span=s.bot.y-s.top.y; if(span<=4) return;
          var f=span/CAL.spanLock; if(!(f>0.35 && f<2.8)) return;
          CAL.factor += (f-CAL.factor)*0.25;           // smooth, no jitter
          var want=CAL.base*CAL.factor;
          if(CAL.pxPerFt>0 && Math.abs(want-CAL.pxPerFt)/CAL.pxPerFt>0.015){
            CAL.pxPerFt=want; applyScale();
          }
        }
      }

      function calLock(s){
        var st=$('tow-stage').getBoundingClientRect();
        CAL.base=((CAL_BOT-CAL_TOP)*st.height)/CAL.wallFt;   // MEASURED px per ft
        CAL.factor=1; CAL.pxPerFt=CAL.base;
        CAL.spanLock=s.bot.y-s.top.y;
        CAL.locked=true; CAL.streak=0;
        $('tow-calbox').classList.add('locked');
        $('tow-calmsg').textContent='✓ Wall locked — the frame is now shown at its real size';
        if(navigator.vibrate) try{ navigator.vibrate(60); }catch(e){}
        setTimeout(function(){
          if(CAL.locked){ $('tow-cal').style.display='none'; $('tow-recal').style.display='block'; }
        }, 1100);
        applyScale();
      }

      function calStart(){
        CAL.locked=false; CAL.streak=0; CAL.factor=1;
        $('tow-cal').style.display='block';
        $('tow-recal').style.display='none';
        $('tow-calbox').classList.remove('locked','near');
        $('tow-calmsg').innerHTML='Step back or forward until the <strong>ceiling line</strong> touches the top edge and the <strong>floor line</strong> touches the bottom edge';
        if(!CAL.timer) CAL.timer=setInterval(calTick,160);
      }
      function calStop(){
        if(CAL.timer){ clearInterval(CAL.timer); CAL.timer=null; }
        CAL.locked=false;
        $('tow-cal').style.display='none';
        $('tow-recal').style.display='none';
        applyScale();                       // back to the room-photo scale
      }
      $('tow-recal').addEventListener('click', calStart);

      /**
       * The one place the wall height changes. Two rows offer it — the panel's
       * (always on screen) and the calibration overlay's (only while the camera
       * is aligning) — and both land here, so they can never disagree about
       * which button is lit or what the preview is drawn against.
       *
       * The redraw is unconditional. It used to happen only when CAL.locked,
       * which is the one moment the visitor is NOT being asked for a height:
       * they are asked while the rectangle is still red and nothing is locked,
       * and that press lit a button and changed nothing.
       */
      function af_tow_set_wall_ft( n ) {
        CAL.wallFt = parseInt( n, 10 ) || 10;
        [ 'tow-wallh', 'tow-calh' ].forEach(function(id){
          var row = $(id); if ( ! row ) { return; }
          row.querySelectorAll('button[data-ft]').forEach(function(x){
            x.classList.toggle('on', parseInt(x.getAttribute('data-ft'),10) === CAL.wallFt);
          });
        });
        if(CAL.locked){                     // re-derive the measurement, keep tracking
          var st=$('tow-stage').getBoundingClientRect();
          CAL.base=((CAL_BOT-CAL_TOP)*st.height)/CAL.wallFt;
          CAL.pxPerFt=CAL.base*CAL.factor;
        }
        applyScale();
      }
      [ 'tow-wallh', 'tow-calh' ].forEach(function(id){
        var row = $(id); if ( ! row ) { return; }
        row.addEventListener('click', function(e){
          var b = e.target.closest('button[data-ft]'); if ( ! b ) { return; }
          af_tow_set_wall_ft( b.getAttribute('data-ft') );
        });
      });

      // ── 1 / 2 / 4 PANEL WALL LAYOUTS (spec §8) ─────────────────────────
      // 2 and 4 render the artwork as a split multi-panel set (vertical
      // slices), each slice in its own frame — the classic gallery-wall look.
      var LAYOUT=1, artImg=null, artRatio=1.33;   // ratio = h/w of the artwork
      $('tow-layouts').addEventListener('click', function(e){
        var b=e.target.closest('.af-tow-lay'); if(!b) return;
        this.querySelectorAll('.af-tow-lay').forEach(function(x){x.classList.remove('on');});
        b.classList.add('on');
        LAYOUT=parseInt(b.getAttribute('data-n'),10)||1;
        rebuild();
      });

      // A print is produced at the SIZE the visitor chose, so the artwork is
      // cover-cropped to that ratio. Without this the frame keeps the photo's
      // own shape and every size with the same height (2×3, 2×4, 2×5 ft) draws
      // exactly the same box while the price moves — the width does nothing.
      var cropKey='', cropURL='', cropImg=null;
      function printRatio(){
        var ft=sizeFeet($('tow-size').value);
        return (ft && ft.h>0 && ft.w>0) ? (ft.h/ft.w) : artRatio;
      }
      function ensureCrop(cb){
        var p=current();
        if(!p || !(artImg && artImg.naturalWidth>0)){ cb(); return; }
        var ratio=printRatio(), key=p.img+'|'+ratio.toFixed(4);
        if(key===cropKey && cropURL){ cb(); return; }
        try{
          var iw=artImg.naturalWidth, ih=artImg.naturalHeight;
          var tw=Math.min(iw,1600), th=Math.max(1, Math.round(tw*ratio));
          var cv=document.createElement('canvas'); cv.width=tw; cv.height=th;
          var cx=cv.getContext('2d');
          var s=Math.max(tw/iw, th/ih);
          cx.drawImage(artImg,(tw-iw*s)/2,(th-ih*s)/2,iw*s,ih*s);
          cropURL=cv.toDataURL('image/jpeg',0.92);
          cropImg=new Image(); cropImg.src=cropURL;
          cropKey=key;
        }catch(err){
          // image host without CORS headers taints the canvas — keep the
          // uncropped artwork rather than losing the preview
          cropURL=''; cropImg=null; cropKey='';
        }
        cb();
      }
      function rebuild(){ ensureCrop(function(){ buildPanels(); applyFrame(); applyScale(); }); }

      function buildPanels(){
        var p=current(); if(!p) return;      // never wipe the wall when there is nothing to rebuild
        // The frame's shape is ALWAYS the chosen print size, even while the
        // artwork is still downloading. It used to fall back to the photo's own
        // aspect until the crop was ready, so picking a product flashed a
        // wrong-shape frame on a page whose whole promise is true-to-scale —
        // a briefly stretched artwork is the better half of that trade.
        var src=cropURL||p.img, ratio=printRatio();
        var wrap=$('tow-panels'); wrap.innerHTML='';
        for(var i=0;i<LAYOUT;i++){
          var panel=document.createElement('div'); panel.className='af-tow-wpanel';
          var frame=document.createElement('div'); frame.className='af-tow-pframe';
          var mat=document.createElement('div'); mat.className='af-tow-pmat';
          var art=document.createElement('div'); art.className='af-tow-part';
          art.style.backgroundImage='url("'+src+'")';
          if(LAYOUT===1){
            art.style.backgroundSize='100% 100%';
            art.style.backgroundPosition='0 0';
          }else{
            art.style.backgroundSize=(LAYOUT*100)+'% 100%';
            art.style.backgroundPosition=(i/(LAYOUT-1)*100)+'% 0';
          }
          art.style.paddingBottom=(ratio*LAYOUT*100)+'%';
          var glass=document.createElement('span'); glass.className='af-tow-glass';
          mat.appendChild(art); mat.appendChild(glass);
          frame.appendChild(mat); panel.appendChild(frame); wrap.appendChild(panel);
        }
      }

      function fillProducts(cat){
        var sel=$('tow-prod'); sel.innerHTML='<option value="">Select a product</option>';
        PRODUCTS.filter(function(p){ return !cat || (p.cats||[]).indexOf(cat)>-1; })
                .forEach(function(p){ var o=document.createElement('option'); o.value=p.id; o.textContent=p.name; sel.appendChild(o); });
      }
      fillProducts('');
      // changing category clears the product selection — put the stage back to
      // its prompt rather than leaving the previous artwork stranded on the wall
      $('tow-cat').addEventListener('change', function(){ fillProducts(this.value); refresh(); });

      function current(){ return PRODUCTS.filter(function(p){ return String(p.id)===String($('tow-prod').value); })[0]; }

      function applyFrame(){
        var frame=$('tow-frame').value, color=$('tow-color').value;
        var fb=$('tow-framebox');
        // Frame profile thickness (% of panel width so it scales) + reveal.
        // These are CANVAS prints: a real fibre or aluminium frame mounts the
        // canvas edge-to-edge — no paper-print mat. The wide cream border the
        // preview used to draw between moulding and art was a museum mat that
        // this product never ships with, and at 4×6 ft it scaled up to a
        // hand-width band of nothing. Only the floating frame keeps a border:
        // its slim dark reveal is how a real float frame is built.
        var prof, matw, matbg='#f6f1e6';
        if(frame==='Without Frame'){ prof=0; matw=0; }         // gallery-wrapped canvas
        else if(frame==='Floating Frame'){ prof=5; matw=2.5; matbg='#161616'; }
        else if(frame==='Aluminium Frame'){ prof=4; matw=0; }
        else { prof=7; matw=0; }                                // Fibre / default wood
        // Multi-panel float sets keep a hairline reveal so slices stay separate
        if(LAYOUT>1 && matw>0){ matw=Math.max(1.5, matw-1); }
        var tex = prof>0 ? frameTexture(frame, color) : null;
        fb.querySelectorAll('.af-tow-pframe').forEach(function(frEl){
          frEl.setAttribute('data-prof', prof);
          frEl.style.padding='0';
          if(tex){
            // the moulding is a rendered picture of a frame, not a gradient —
            // border-image keeps the 45° mitres true at any panel size
            frEl.style.borderStyle='solid';
            frEl.style.borderColor='transparent';
            frEl.style.borderImage='url("'+tex.url+'") '+tex.B+' stretch';
            frEl.style.background='transparent';
            frEl.style.borderRadius='0';
            frEl.style.boxShadow='none';
          }else{
            frEl.style.border='0';
            frEl.style.borderImage='none';
            frEl.style.background='transparent';
            frEl.style.boxShadow='none';
          }
        });
        sizeFrameBorders();
        fb.querySelectorAll('.af-tow-pmat').forEach(function(mat){
          mat.style.padding = matw>0 ? ('max(8px, '+matw+'%)') : '0';
          mat.style.background = matw>0 ? matbg : 'transparent';
          // the frame's rebate overhangs whatever sits in it, so its fine
          // shadow falls on the reveal when there is one and straight onto
          // the canvas when there is not — only an unframed wrap has none
          mat.style.boxShadow = prof>0 ? 'inset 0 2px 5px rgba(0,0,0,.34), inset 0 0 9px rgba(0,0,0,.16)' : 'none';
        });
        // Sitting on the wall: a tight contact shadow right behind the
        // moulding plus the broad soft cast from the room's key light — two
        // shadows is what makes it read as hanging ON the wall rather than
        // pasted over the photo.
        fb.style.filter = 'drop-shadow(0 2px 4px rgba(0,0,0,.35)) drop-shadow(12px 18px 26px rgba(0,0,0,.30))';
      }

      function calcPrice(){
        var p=current(); if(!p) return null;
        // the size IS the price, from the rate card (matches af_calc_price)
        var sizePrice=CFG.sizes[$('tow-size').value]||p.price;
        // a Gold Foiled & UV piece rides a ratio on the same card, so the
        // preview quotes what the product page will (1 for everything else)
        if (p.gf && p.gf !== 1) sizePrice = Math.max(5, Math.round(sizePrice * p.gf / 5) * 5);
        var frameVal=$('tow-frame').value;
        // no frame means no frame-finish surcharge (matches af_calc_price)
        var fee=(CFG.frames[frameVal]||0)+(frameVal!=='Without Frame' ? (CFG.colors[$('tow-color').value]||0) : 0);
        return Math.round((sizePrice+fee)*100)/100;
      }
      function refresh(){
        var p=current();
        if(!p){
          $('tow-panels').innerHTML='';
          $('tow-framebox').style.display='none';
          $('tow-placeholder').style.display='flex';
          $('tow-price').textContent='—';
          $('tow-view').removeAttribute('href');
          return;
        }
        if(p){
          // (re)load only when the product changed — the same artwork is reused
          // across size / frame / colour changes so the crop cache stays warm
          if(!artImg || artImg.getAttribute('data-src')!==p.img){
            artImg=new Image(); artImg.crossOrigin='anonymous';
            artImg.setAttribute('data-src', p.img);
            artImg.onload=function(){
              if(artImg.naturalWidth>0) artRatio=artImg.naturalHeight/artImg.naturalWidth;
              cropKey=''; rebuild();
            };
            // If the image host doesn't answer with CORS headers the anonymous
            // request fails outright — retry plainly so the preview still works.
            artImg.onerror=function(){
              var plain=new Image();
              plain.setAttribute('data-src', p.img);
              plain.onload=function(){
                artImg=plain;
                if(plain.naturalWidth>0) artRatio=plain.naturalHeight/plain.naturalWidth;
                cropKey=''; rebuild();
              };
              plain.src=p.img;
            };
            artImg.src=p.img;
          }
          $('tow-framebox').style.display='inline-block';
          $('tow-placeholder').style.display='none';
          // carry the visitor's choices through to the product page
          $('tow-view').href=p.url + (p.url.indexOf('?')>-1?'&':'?') +
            'af_size='  + encodeURIComponent($('tow-size').value) +
            '&af_frame='+ encodeURIComponent($('tow-frame').value) +
            '&af_color='+ encodeURIComponent($('tow-color').value);
          rebuild();
          var pr=calcPrice(); $('tow-price').textContent = pr!=null ? (SYM+pr.toFixed(2)) : '—';
        }
      }
      ['tow-prod','tow-frame','tow-size','tow-color'].forEach(function(id){ $(id).addEventListener('change', refresh); });

      // ── TRUE-TO-SCALE preview ──────────────────────────────────────────
      // The room photos are shot against a wall whose height the visitor tells
      // us (CAL.wallFt, from the Wall height buttons; WALL_FT is only the
      // fallback if that is somehow unset), and the wall occupies WALL_FRAC of
      // the stage's height (the rest is floor).
      // A size label like "3×5 ft (36×60 in)" is HEIGHT × WIDTH in feet, so the
      // artwork is drawn at (height_ft / wall height) of the wall — e.g. 3 ft on
      // a 10 ft wall = 30% of the wall height — and its width follows from the
      // real aspect ratio, not from the price multiplier.
      // Saying the wall is shorter makes the same print cover more of it, which
      // is the whole point of asking: an 8 ft ceiling is not a 10 ft one.
      var WALL_FT = 10;      // fallback wall height; CAL.wallFt starts here too
      var WALL_FRAC = 0.78;  // portion of the stage height that is wall
      var scaleSeq = 0;      // guards the deferred size correction below
      function sizeFeet(label){
        // "3×5 ft (36×60 in)" → {h:3, w:5}   (also accepts x / X / ×)
        var m = String(label||'').match(/(\d+(?:\.\d+)?)\s*[x×X]\s*(\d+(?:\.\d+)?)/);
        if(!m) return null;
        return { h: parseFloat(m[1]), w: parseFloat(m[2]) };
      }
      function applyScale(){
        var stage=$('tow-stage'), box=$('tow-framebox');
        var r=stage.getBoundingClientRect(), sw=r.width||500, sh=r.height||560;
        var label=$('tow-size').value;
        var ft=sizeFeet(label);
        var slider=parseFloat($('tow-scale').value)/100;

        if(ft && ft.h>0 && ft.w>0){
          // pixels-per-foot from the wall height in this scene
          // Calibrated live camera: the scale is measured against the visitor's
          // actual wall and tracks them as they move. Otherwise: the room photo,
          // read as a wall of the height the visitor picked.
          var pxPerFt = (camOn && CAL.locked && CAL.pxPerFt > 0)
            ? CAL.pxPerFt
            : (sh * WALL_FRAC) / (CAL.wallFt || WALL_FT);
          var targetH = ft.h * pxPerFt * slider;         // true height on the wall
          var targetW = ft.w * pxPerFt * slider;         // true width  on the wall
          // Set width first, then correct so the RENDERED box (frame + mat +
          // art, whose own ratio may differ) matches the real footprint.
          box.style.width = Math.max(40, targetW) + 'px';
          // Only the newest correction may run: two applyScale() calls in one
          // frame (refresh() then the artwork's onload) used to have the second
          // measure the already-corrected box and undo the correction.
          var seq = ++scaleSeq;
          requestAnimationFrame(function(){
            if(seq!==scaleSeq) return;
            var b = box.getBoundingClientRect();
            if(b.height>0 && b.width>0){
              // Scale from what is actually on screen, so this is idempotent and
              // works for any panel count: once the height matches, it is a no-op.
              var corrected = Math.max(40, b.width * (targetH / b.height));
              // never exceed the wall itself
              corrected = Math.min(corrected, sw*0.92);
              box.style.width = corrected + 'px';
            }
            // the moulding thickness is a % of the panel, expressed in border
            // px — re-derive it now that the box has its final width
            sizeFrameBorders();
          });
          return;
        }
        // Fallback (unparsable label): gentle scaling — CFG.sizes now holds
        // dollar prices, so normalise against the smallest to get a ratio
        var sMin=Math.min.apply(null, Object.keys(CFG.sizes).map(function(k){return CFG.sizes[k];}));
        var sizeMult=(CFG.sizes[label]||sMin)/(sMin||1);
        box.style.width=Math.max(60, sw*0.28*Math.sqrt(sizeMult)*slider)+'px';
        sizeFrameBorders();
      }
      $('tow-scale').addEventListener('input', function(){ $('tow-scaleval').textContent=this.value+'%'; applyScale(); });

      // Wall upload (overrides the chosen room scene)
      $('tow-wall').addEventListener('change', function(){
        var f=this.files&&this.files[0]; if(!f) return;
        var r=new FileReader();
        r.onload=function(e){
          usingUpload=true; savedURL=''; stopCam();
          sceneWrap.querySelectorAll('.af-tow-scene').forEach(function(x){x.classList.remove('on');});
          var im=$('tow-wallimg'); im.src=e.target.result; im.style.display='block';
          im.style.objectPosition='50% 50%';
          $('tow-placeholder').style.display = current() ? 'none' : 'flex';
        };
        r.readAsDataURL(f);
      });

      // Drag artwork
      var box=$('tow-framebox'), drag=false,sx,sy,ox,oy;
      box.addEventListener('pointerdown', function(e){
        // The box is centred with translate(-50%,-50%); read where it ACTUALLY
        // is before dropping that transform, or it jumps half its own size.
        var sr=$('tow-stage').getBoundingClientRect(), br=box.getBoundingClientRect();
        ox=br.left-sr.left; oy=br.top-sr.top;
        box.style.left=ox+'px'; box.style.top=oy+'px'; box.style.transform='none';
        sx=e.clientX; sy=e.clientY;
        drag=true; box.classList.add('dragging');
        if(box.setPointerCapture){ try{ box.setPointerCapture(e.pointerId); }catch(err){} }
        e.preventDefault();
      });
      document.addEventListener('pointermove', function(e){
        if(!drag) return;
        var sr=$('tow-stage').getBoundingClientRect(), br=box.getBoundingClientRect();
        var nx=ox+(e.clientX-sx), ny=oy+(e.clientY-sy);
        // always leave a grabbable piece of the artwork on the wall
        var keepX=Math.min(70, br.width*0.5), keepY=Math.min(70, br.height*0.5);
        nx=Math.max(keepX-br.width, Math.min(nx, sr.width-keepX));
        ny=Math.max(keepY-br.height, Math.min(ny, sr.height-keepY));
        box.style.left=nx+'px'; box.style.top=ny+'px';
      });
      function endDrag(){ if(!drag) return; drag=false; box.classList.remove('dragging'); }
      document.addEventListener('pointerup', endDrag);
      document.addEventListener('pointercancel', endDrag);
      window.addEventListener('blur', endDrag);

      // Save preview
      var toastTimer;
      function toast(msg){
        var t=$('tow-toast'); if(!t) return;
        t.textContent=msg; t.classList.add('show');
        clearTimeout(toastTimer); toastTimer=setTimeout(function(){ t.classList.remove('show'); }, 3200);
      }
      // Compose the wall + framed artwork onto a canvas. Returns {url,name} or
      // null (having explained why). Shared by download, save-to-account and share.
      function composePreview(){
        var wall=$('tow-wallimg');
        if(!current()){ toast('Please choose a product first'); return null; }
        var haveWall = camOn || (wall.src && wall.style.display!=='none');
        if(!haveWall){ toast('Please pick a room, use the camera, or upload a wall photo'); return null; }
        var panels=$('tow-framebox').querySelectorAll('.af-tow-wpanel');
        if(!panels.length){ toast('Still preparing the preview — try again in a moment'); return null; }
        // draw from the same cropped image the panels display, so the file matches
        var artSrc=(cropImg && cropImg.complete && cropImg.naturalWidth>0) ? cropImg : artImg;
        if(!(artSrc && artSrc.naturalWidth>0)){ toast('Artwork is still loading — try again in a moment'); return null; }
        try{
          var stage=$('tow-stage'), r=stage.getBoundingClientRect();
          var cv=document.createElement('canvas'); cv.width=Math.round(r.width); cv.height=Math.round(r.height); var ctx=cv.getContext('2d');
          // Cover-fit like CSS object-fit:cover, honouring object-position so the
          // export frames the room exactly the way the preview does.
          function cover(src, nw, nh, pos){
            var sc=Math.max(r.width/nw, r.height/nh), dw=nw*sc, dh=nh*sc;
            var m=String(pos||'50% 50%').match(/([\d.]+)%\s+([\d.]+)%/);
            var px=m?parseFloat(m[1])/100:0.5, py=m?parseFloat(m[2])/100:0.5;
            ctx.drawImage(src, (r.width-dw)*px, (r.height-dh)*py, dw, dh);
          }
          if(camOn){
            var v=$('tow-cam');
            cover(v, v.videoWidth||r.width, v.videoHeight||r.height, '50% 50%');
          }else{
            cover(wall, wall.naturalWidth||r.width, wall.naturalHeight||r.height, getComputedStyle(wall).objectPosition);
          }
          function rect(el){ var b=el.getBoundingClientRect(); return {x:b.left-r.left,y:b.top-r.top,w:b.width,h:b.height}; }
          var color=$('tow-color').value, hex=SWATCH[color]||'#1a1a1a';
          var frameName=$('tow-frame').value;
          var N=panels.length||1;
          // The same rendered moulding the screen shows, 9-sliced into the
          // export so corners keep their true mitres at any panel size.
          function drawFrameRing(fr, bw){
            if(!(bw>0)) return;
            var t=frameTexture(frameName, color), s=t.cv, B=t.B, S=t.S;
            var iw=Math.max(1, fr.w-2*bw), ih=Math.max(1, fr.h-2*bw);
            ctx.drawImage(s, 0,0,B,B,             fr.x,fr.y,bw,bw);                       // corners
            ctx.drawImage(s, S-B,0,B,B,           fr.x+fr.w-bw,fr.y,bw,bw);
            ctx.drawImage(s, 0,S-B,B,B,           fr.x,fr.y+fr.h-bw,bw,bw);
            ctx.drawImage(s, S-B,S-B,B,B,         fr.x+fr.w-bw,fr.y+fr.h-bw,bw,bw);
            ctx.drawImage(s, B,0,S-2*B,B,         fr.x+bw,fr.y,iw,bw);                    // edges
            ctx.drawImage(s, B,S-B,S-2*B,B,       fr.x+bw,fr.y+fr.h-bw,iw,bw);
            ctx.drawImage(s, 0,B,B,S-2*B,         fr.x,fr.y+bw,bw,ih);
            ctx.drawImage(s, S-B,B,B,S-2*B,       fr.x+fr.w-bw,fr.y+bw,bw,ih);
          }
          // Pass 1: cast every panel's shadow on the wall first, so a later
          // panel's shadow can never fall across an already-drawn frame.
          ctx.save(); ctx.shadowColor='rgba(0,0,0,.4)'; ctx.shadowBlur=26; ctx.shadowOffsetX=10; ctx.shadowOffsetY=16;
          ctx.fillStyle=hex;
          panels.forEach(function(panel){
            var fr=rect(panel.querySelector('.af-tow-pframe'));
            ctx.fillRect(fr.x,fr.y,fr.w,fr.h);
          });
          ctx.restore();
          // Pass 2: moulding, mat and each panel's slice of the artwork
          var idx=0;
          panels.forEach(function(panel){
            var frEl=panel.querySelector('.af-tow-pframe'), matEl=panel.querySelector('.af-tow-pmat'), artEl=panel.querySelector('.af-tow-part');
            var fr=rect(frEl);
            var bw=parseFloat(getComputedStyle(frEl).borderTopWidth)||0;
            if(bw>0){ drawFrameRing(fr, bw); }
            else { ctx.fillStyle=hex; }
            var mt=rect(matEl); var st=getComputedStyle(matEl);
            if(parseFloat(st.paddingTop)>0){ ctx.fillStyle=(st.backgroundColor&&st.backgroundColor!=='rgba(0, 0, 0, 0)')?st.backgroundColor:'#f6f1e6'; ctx.fillRect(mt.x,mt.y,mt.w,mt.h); }
            var ar=rect(artEl);
            var siw=artSrc.naturalWidth/N;
            ctx.drawImage(artSrc, idx*siw, 0, siw, artSrc.naturalHeight, ar.x, ar.y, ar.w, ar.h);
            idx++;
          });
          var p=current(); var fname=((p&&p.name)?p.name.replace(/[^a-z0-9]+/gi,'-').replace(/^-+|-+$/g,'').toLowerCase():'my')+'-on-wall.png';
          return { url: cv.toDataURL('image/png'), name: fname };
        }catch(err){ toast('Couldn’t build the preview — right-click the wall to save it'); return null; }
      }

      $('tow-save').addEventListener('click', function(){
        var out=composePreview(); if(!out) return;
        var a=document.createElement('a'); a.download=out.name; a.href=out.url;
        document.body.appendChild(a); a.click(); a.remove();
        toast('✓ Saved to your downloads: '+out.name);
      });

      // ── SAVE TO ACCOUNT + SHARE (spec §8) ────────────────────────────
      // Cleared whenever the configuration changes, so sharing never sends the
      // image of a preview the visitor has already moved on from.
      var savedURL='';
      ['tow-prod','tow-frame','tow-size','tow-color','tow-scale'].forEach(function(id){
        $(id).addEventListener('change', function(){ savedURL=''; });
      });
      $('tow-layouts').addEventListener('click', function(){ savedURL=''; });
      function shareTarget(){
        // share the saved image when there is one, otherwise the configured product
        if(savedURL) return savedURL;
        var p=current();
        return p ? ($('tow-view').getAttribute('href')||p.url) : location.href;
      }
      function shareText(){
        var p=current();
        return (p?('“'+p.name+'” '):'') + 'on my wall — from The Art Framer';
      }
      $('tow-saveacct').addEventListener('click', function(){
        var btn=this, out=composePreview(); if(!out) return;
        btn.disabled=true; toast('Saving to your account…');
        var p=current();
        AFPreview.save(out.url, {
          product: p?p.id:0, source:'try-on-wall',
          size: $('tow-size').value, frame: $('tow-frame').value,
          color: $('tow-color').value, layout: LAYOUT
        }, function(ok, msg, url, fallback){
          btn.disabled=false;
          if(ok){
            savedURL=url||'';
            toast('✓ '+msg);
            var link=$('tow-acctlink');
            if(link && fallback){ link.href=fallback; link.style.display='inline'; }
          }else{
            toast(msg);
            if(fallback && !AFPreview.cfg.logged){
              var l=$('tow-acctlink');
              if(l){ l.href=AFPreview.withRedirect(fallback); l.textContent='Sign in →'; l.style.display='inline'; }
            }
          }
        });
      });
      $('tow-share-wa').addEventListener('click', function(){
        if(AFPreview.native('The Art Framer', shareText(), shareTarget())) return;
        window.open(AFPreview.whatsapp(shareText(), shareTarget()), '_blank', 'noopener');
      });
      $('tow-share-mail').addEventListener('click', function(){
        location.href = AFPreview.email('My wall preview — The Art Framer', shareText(), shareTarget());
      });
      $('tow-share-copy').addEventListener('click', function(){
        AFPreview.copy(shareTarget(), function(ok){
          toast(ok ? '✓ Link copied' : 'Could not copy — long-press the link to copy it');
        });
      });

      window.addEventListener('resize', applyScale);

      // The stage now stretches to match the control rail, so its height can
      // change without the window changing size. pixels-per-foot is derived
      // from that height, so re-run the maths whenever the box itself moves.
      if (window.ResizeObserver) {
        var scaleRO = new ResizeObserver(function(){ applyScale(); });
        scaleRO.observe($('tow-stage'));
      }

      // Show a realistic room by default so the preview always looks real
      setScene(0);

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
        // defaults for a nice first view — the first frame we can still make
        refresh();
        var el=document.querySelector('.af-tow-wrap'); if(el) el.scrollIntoView({behavior:'smooth',block:'start'});
      })();
    })();
    </script>

    <style>
    .af-tow-wrap{background:linear-gradient(180deg,#f6f1e6 0%,#efe7d6 100%);padding:44px 16px 70px;}
    .af-tow-card{max-width:1300px;margin:0 auto;background:#fffdf8;border:1px solid #efe6d2;border-radius:24px;padding:34px 30px;position:relative;box-shadow:0 24px 60px rgba(70,54,26,.10);}
    .af-tow-home{position:absolute;top:26px;left:26px;background:#1a1a1a;color:#fff;text-decoration:none;font-weight:700;font-size:12.5px;padding:10px 16px;border-radius:9px;transition:background .2s;}
    .af-tow-home:hover{background:#c9a84c;}
    .af-tow-badge{position:absolute;top:26px;right:26px;background:#f3ead2;color:#a8801f;font-weight:800;font-size:11px;letter-spacing:.08em;text-transform:uppercase;padding:8px 14px;border-radius:999px;border:1px solid #e6d7ad;}
    .af-tow-title{text-align:center;font-size:46px;font-weight:800;color:#1a1a1a;margin:14px 0 10px;letter-spacing:-.5px;font-family:'Playfair Display',Georgia,serif;}
    .af-tow-sub{text-align:center;color:#6b6250;font-size:15.5px;max-width:640px;margin:0 auto 26px;line-height:1.6;}
    .af-tow-grid{display:grid;grid-template-columns:360px 1fr;gap:30px;align-items:start;}
    .af-tow-panel{background:#fff;border:1px solid #ece4cf;border-radius:18px;padding:24px;display:flex;flex-direction:column;gap:6px;box-shadow:0 8px 26px rgba(70,54,26,.05);position:sticky;top:20px;}
    .af-tow-step{display:flex;align-items:center;gap:10px;margin:16px 0 4px;}
    .af-tow-step:first-child{margin-top:0;}
    .af-tow-stepnum{width:24px;height:24px;border-radius:50%;background:#c9a84c;color:#fff;font-weight:800;font-size:13px;display:flex;align-items:center;justify-content:center;flex:0 0 auto;}
    .af-tow-steptitle{font-size:14px;font-weight:800;color:#1a1a1a;letter-spacing:.01em;}
    .af-tow-panel label{font-size:11.5px;font-weight:800;color:#6b6250;text-transform:uppercase;letter-spacing:.05em;margin-top:12px;}
    .af-tow-panel select{width:100%;padding:12px;border:1px solid #e2d9c4;border-radius:10px;font-size:14px;background:#fffdf8;color:#1a1a1a;cursor:pointer;transition:border-color .15s;}
    .af-tow-panel select:focus{outline:none;border-color:#c9a84c;}
    .af-tow-hiddensel{display:none;}
    .af-tow-swatches{display:flex;gap:9px;flex-wrap:wrap;margin-top:6px;}
    .af-tow-sw{position:relative;width:34px;height:34px;border-radius:50%;border:2px solid #fff;box-shadow:0 0 0 1px #d8cdb3, 0 2px 5px rgba(0,0,0,.15);cursor:pointer;padding:0;transition:transform .12s;}
    .af-tow-sw:hover{transform:scale(1.08);}
    .af-tow-sw.on{box-shadow:0 0 0 2px #c9a84c, 0 2px 6px rgba(0,0,0,.2);}
    .af-tow-sw span{position:absolute;bottom:calc(100% + 6px);left:50%;transform:translateX(-50%);background:#1a1a1a;color:#fff;font-size:10.5px;font-weight:700;padding:4px 8px;border-radius:6px;white-space:nowrap;opacity:0;pointer-events:none;transition:opacity .15s;}
    .af-tow-sw:hover span{opacity:1;}
    .af-tow-scenes{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:6px;}
    .af-tow-scene{position:relative;height:52px;border-radius:10px;border:2px solid #e2d9c4;background-size:cover;background-position:center;cursor:pointer;overflow:hidden;padding:0;transition:border-color .15s,transform .12s;}
    .af-tow-scene:hover{transform:translateY(-1px);}
    .af-tow-scene.on{border-color:#c9a84c;box-shadow:0 0 0 1px #c9a84c;}
    .af-tow-scene span{position:absolute;left:0;right:0;bottom:0;background:linear-gradient(transparent,rgba(0,0,0,.6));color:#fff;font-size:10.5px;font-weight:700;padding:8px 6px 4px;text-align:center;}
    .af-tow-upload{display:flex !important;align-items:center;justify-content:center;gap:8px;padding:13px;border:2px dashed #c9a84c;border-radius:11px;color:#a8872e;font-weight:700 !important;font-size:12.5px !important;cursor:pointer;text-transform:none !important;letter-spacing:0 !important;background:#fdfaf2;margin-top:12px;transition:background .15s;}
    .af-tow-upload:hover{background:#faf3e0;}
    .af-tow-panel input[type=range]{width:100%;accent-color:#c9a84c;margin-top:6px;cursor:pointer;}
    .af-tow-price{display:flex;align-items:center;justify-content:space-between;margin-top:20px;padding-top:16px;border-top:1px solid #f0e8d6;font-size:13px;color:#6b6250;font-weight:700;text-transform:uppercase;letter-spacing:.04em;}
    .af-tow-price strong{font-size:26px;color:#1a1a1a;letter-spacing:-.5px;}
    .af-tow-actions{display:flex;gap:10px;margin-top:16px;}
    .af-tow-btn{flex:1 1 0;min-width:0;box-sizing:border-box;height:46px;display:flex;align-items:center;justify-content:center;gap:6px;padding:0 12px;border-radius:11px;font-weight:700 !important;font-size:13px !important;line-height:1 !important;white-space:nowrap !important;text-transform:none !important;letter-spacing:0 !important;cursor:pointer;text-decoration:none;border:none;transition:transform .12s,background .2s;}
    .af-tow-btn:hover{transform:translateY(-1px);}
    .af-tow-btn.ghost{background:#f2ecdd;color:#5a5140;}
    .af-tow-btn.ghost:hover{background:#e9e0cc;}
    .af-tow-btn.solid{background:#c9a84c;color:#fff;box-shadow:0 6px 16px rgba(201,168,76,.35);}
    .af-tow-btn.solid:hover{background:#b8973c;}
    .af-tow-toast{position:absolute;left:50%;bottom:18px;transform:translateX(-50%) translateY(12px);background:#1a1a1a;color:#fff;font-size:13px;font-weight:600;padding:11px 18px;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.28);opacity:0;pointer-events:none;transition:opacity .25s,transform .25s;z-index:20;white-space:nowrap;}
    .af-tow-toast.show{opacity:1;transform:translateX(-50%) translateY(0);}
    /* The preview column stretches to whatever height the control rail needs
       and the wall itself swallows the difference, so neither column can end
       short and leave a band of empty card behind it. */
    .af-tow-stagewrap{position:relative;display:flex;flex-direction:column;align-self:stretch;min-height:0;}
    .af-tow-stage{position:relative;width:100%;flex:1 1 auto;min-height:470px;max-height:760px;border-radius:18px;overflow:hidden;background:#e9e4d8;display:flex;align-items:center;justify-content:center;box-shadow:inset 0 0 60px rgba(0,0,0,.10);}
    /* room card under the wall — never sticky, it is already in view */
    .af-tow-room{position:static;top:auto;margin-top:16px;gap:0;}
    .af-tow-roomgrid{display:grid;grid-template-columns:1.15fr .85fr;gap:4px 26px;align-items:start;}
    .af-tow-roomcol{display:flex;flex-direction:column;min-width:0;}
    .af-tow-room .af-tow-scenes{grid-template-columns:repeat(4,1fr);}
    .af-tow-room .af-tow-scene{height:84px;}
    .af-tow-room .af-tow-cambtn{margin-top:26px;padding:16px 12px;}
    .af-tow-room .af-tow-scalenote{margin-top:16px;}
    .af-tow-room .af-tow-upload{padding:15px;}
    .af-tow-wallimg{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:none;}
    .af-tow-ph{position:relative;z-index:2;color:#8a8170;font-size:14.5px;text-align:center;max-width:360px;line-height:1.65;padding:24px;display:flex;flex-direction:column;align-items:center;gap:12px;background:rgba(255,255,255,.78);border-radius:14px;backdrop-filter:blur(2px);}
    .af-tow-ph-ic{font-size:34px;}
    .af-tow-scalenote{margin:10px 0 0;font-size:11.5px;color:#8a6d1f;font-weight:600;}
    .af-tow-framebox{position:absolute;top:42%;left:50%;transform:translate(-50%,-50%);cursor:grab;touch-action:none;z-index:5;}
    .af-tow-framebox.dragging{cursor:grabbing;}
    .af-tow-framebox:hover .af-tow-hint{opacity:1;}
    /* multi-panel layouts */
    .af-tow-panels{display:flex;gap:2.5%;align-items:flex-start;}
    .af-tow-wpanel{flex:1 1 0;min-width:0;}
    .af-tow-pframe{position:relative;box-sizing:border-box;}
    .af-tow-pmat{position:relative;box-sizing:border-box;}
    .af-tow-part{width:100%;background-repeat:no-repeat;}
    /* layout chips */
    .af-tow-layouts{display:flex;gap:8px;margin-top:6px;}
    /* The chip is its label and nothing else. It used to draw the layout as
       little bars behind the word, which read as clutter sitting under the
       text rather than as a picture of the layout, so the bars are gone and
       the label is centred in the chip instead of pinned below them. The
       selected chip says so with its border and now its label colour too —
       that was the gold bars' job. */
    .af-tow-lay{flex:1;display:flex;align-items:center;justify-content:center;height:44px;padding:7px 8px;
      border:2px solid #e2d9c4;border-radius:10px;background:#fffdf8;cursor:pointer;transition:border-color .15s;}
    .af-tow-lay span{font-size:11px;font-weight:700;color:#8a8170;text-align:center;letter-spacing:.02em;}
    .af-tow-lay.on{border-color:#c9a84c;box-shadow:0 0 0 1px #c9a84c;}
    .af-tow-lay.on span{color:#8a6d3b;}
    /* Wall height, in the panel. Same chip as the layout row above it, so the
       two read as one pair of choices rather than two unrelated controls.
       "8 ft" is one line, and is held to one line here rather than trusted to
       stay that way: the parent theme styles buttons too, and its line-height
       and padding were enough to break the label at its space and stack the
       number above the unit. nowrap settles it whatever the inherited value;
       the explicit flex row, line-height and padding stop the same styles
       spreading the two halves to the top and bottom of the chip. */
    .af-tow-wallh{display:flex;gap:8px;margin-top:6px;}
    .af-tow-wallh button{flex:1 1 0;min-width:0;box-sizing:border-box;
      display:flex;flex-direction:row;align-items:center;justify-content:center;
      white-space:nowrap;line-height:1;height:38px;padding:0 4px;
      border:2px solid #e2d9c4;border-radius:10px;background:#fffdf8;
      font-size:11px;font-weight:700;color:#8a8170;letter-spacing:.02em;cursor:pointer;transition:border-color .15s;}
    .af-tow-wallh button.on{border-color:#c9a84c;box-shadow:0 0 0 1px #c9a84c;color:#8a6d3b;}
    /* live camera */
    .af-tow-cam{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:none;z-index:1;}
    .af-tow-cambtn{display:flex;flex-direction:column;align-items:center;gap:2px;width:100%;margin-top:8px;padding:11px 12px;
      border:2px solid #1a1a1a;border-radius:11px;background:#1a1a1a;color:#fff;font-size:12.5px;font-weight:700;cursor:pointer;
      text-transform:none;letter-spacing:0;transition:background .2s;}
    .af-tow-cambtn:hover{background:#000;}
    .af-tow-cambtn em{font-style:normal;font-weight:500;font-size:10.5px;color:#cbc2ac;}
    .af-tow-camstop{position:absolute;top:12px;right:12px;z-index:8;background:rgba(20,20,20,.85);color:#fff;border:none;
      border-radius:999px;padding:8px 14px;font-size:12px;font-weight:700;cursor:pointer;}
    /* wall calibration: red rectangle → amber when the lines are close → green
       at the lock; the huge shadow dims everything outside the rectangle */
    .af-tow-cal{position:absolute;inset:0;z-index:7;pointer-events:none;}
    .af-tow-calbox{position:absolute;left:9%;right:9%;top:16%;bottom:16%;border:3px solid #e04338;border-radius:6px;
      box-shadow:0 0 0 2000px rgba(0,0,0,.16);transition:border-color .25s,box-shadow .3s;}
    .af-tow-calbox.near{border-color:#e8b400;}
    .af-tow-calbox.locked{border-color:#2fae52;box-shadow:0 0 0 2000px rgba(0,0,0,0),0 0 26px rgba(47,174,82,.85);}
    .af-tow-calcorner{position:absolute;width:20px;height:20px;border-color:inherit;border-style:solid;border-width:0;}
    .af-tow-calcorner.tl{top:-3px;left:-3px;border-top-width:6px;border-left-width:6px;border-top-left-radius:6px;}
    .af-tow-calcorner.tr{top:-3px;right:-3px;border-top-width:6px;border-right-width:6px;border-top-right-radius:6px;}
    .af-tow-calcorner.bl{bottom:-3px;left:-3px;border-bottom-width:6px;border-left-width:6px;border-bottom-left-radius:6px;}
    .af-tow-calcorner.br{bottom:-3px;right:-3px;border-bottom-width:6px;border-right-width:6px;border-bottom-right-radius:6px;}
    .af-tow-calmsg{position:absolute;left:50%;top:4%;transform:translateX(-50%);background:rgba(16,16,16,.82);color:#fff;
      font-size:12.5px;line-height:1.45;padding:8px 14px;border-radius:9px;max-width:78%;text-align:center;}
    .af-tow-calmsg strong{color:#efd48d;}
    .af-tow-calh{position:absolute;left:50%;bottom:4%;transform:translateX(-50%);display:flex;gap:7px;align-items:center;
      background:rgba(16,16,16,.82);border-radius:999px;padding:6px 10px;pointer-events:auto;}
    .af-tow-calh span{color:#cbc2ac;font-size:11px;font-weight:700;text-transform:none;letter-spacing:0;margin:0;}
    .af-tow-calh button{background:transparent;border:1px solid #6f6a5e;color:#fff;font-size:11.5px;font-weight:700;
      border-radius:999px;padding:4px 10px;cursor:pointer;transition:background .15s;}
    .af-tow-calh button.on{background:#c9a84c;border-color:#c9a84c;color:#1a1a1a;}
    .af-tow-recal{position:absolute;top:12px;left:12px;z-index:8;background:rgba(24,110,52,.92);color:#fff;border:none;
      border-radius:999px;padding:8px 13px;font-size:12px;font-weight:700;cursor:pointer;}
    .af-tow-frame{position:relative;box-sizing:border-box;}
    .af-tow-mat{position:relative;box-sizing:border-box;}
    .af-tow-art{display:block;width:100%;height:auto;}
    .af-tow-glass{position:absolute;inset:0;pointer-events:none;background:linear-gradient(125deg,rgba(255,255,255,.22) 0%,rgba(255,255,255,.05) 22%,rgba(255,255,255,0) 42%,rgba(255,255,255,0) 100%);}
    .af-tow-hint{position:absolute;top:-12px;left:50%;transform:translateX(-50%);background:#1a1a1a;color:#fff;font-size:10.5px;font-weight:700;padding:4px 10px;border-radius:999px;white-space:nowrap;opacity:0;transition:opacity .15s;pointer-events:none;}
    .af-tow-tip{text-align:center;color:#8a8170;font-size:13px;margin:14px 0 0;}
    .af-tow-tip strong{color:#5a5140;}
    /* The room card stays two-up as far down as it comfortably fits: the moment
       it stacks, the preview column grows taller than the rail and the dead
       space reappears — on the other side. */
    @media(max-width:1180px){
      .af-tow-grid{grid-template-columns:330px 1fr;gap:24px;}
      .af-tow-roomgrid{gap:4px 18px;}
    }
    @media(max-width:1000px){
      .af-tow-roomgrid{grid-template-columns:1fr;gap:0;}
      .af-tow-room .af-tow-cambtn{margin-top:12px;}
    }
    @media(max-width:900px){
      .af-tow-grid{grid-template-columns:1fr;gap:16px;}
      .af-tow-panel{position:static;}
      .af-tow-stagewrap{align-self:auto;}
      .af-tow-stage{flex:0 0 auto;height:440px;min-height:0;max-height:none;}
      .af-tow-title{font-size:34px;}
      .af-tow-card{padding:64px 20px 26px;}
      .af-tow-room .af-tow-scenes{grid-template-columns:1fr 1fr;}
      .af-tow-room .af-tow-scene{height:56px;}
    }
    @media(max-width:480px){
      .af-tow-title{font-size:27px;}
      .af-tow-stage{height:360px;}
      .af-tow-badge{display:none;}
    }
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

// The modal used to display the card's raw image, which a right-click saved
// cleanly — the exact leak shown in the screen recording. It now asks the
// server for a WATERMARKED preview of the product's artwork instead.
function af_dd_preview_handler() {
    $pid = isset($_GET['pid']) ? absint($_GET['pid']) : 0;
    if (!$pid || get_post_type($pid) !== 'product') wp_send_json_error();
    $thumb = get_post_thumbnail_id($pid);
    if (!$thumb) wp_send_json_error();
    $wm = function_exists('af_wm_preview_url') ? af_wm_preview_url($thumb) : false;
    if ($wm && !empty($wm['url'])) wp_send_json_success(array('url' => $wm['url'], 'wm' => 1));
    // Watermarking unavailable (GD can choke on the very large masters).
    // Fall back to a genuinely small size — and never the master: WordPress
    // returns the ORIGINAL file for any size an image never generated, which
    // is exactly the leak this endpoint exists to close. No small size, no image.
    $master = wp_get_attachment_url($thumb);
    foreach (array('medium', 'woocommerce_thumbnail', 'thumbnail') as $size) {
        $small = wp_get_attachment_image_src($thumb, $size);
        if ($small && !empty($small[0]) && $small[0] !== $master) {
            wp_send_json_success(array('url' => $small[0], 'wm' => 0));
        }
    }
    wp_send_json_error();
}
add_action('wp_ajax_af_dd_preview',        'af_dd_preview_handler');
add_action('wp_ajax_nopriv_af_dd_preview', 'af_dd_preview_handler');

// Some theme-built cards carry no data-product_id at all — but they do link to
// the product page. Resolve the slug so those cards can still sell properly
// instead of falling into the not-available state.
function af_dd_resolve_handler() {
    $slug = isset($_GET['slug']) ? sanitize_title(wp_unslash($_GET['slug'])) : '';
    if ($slug === '') wp_send_json_error();
    $post = get_page_by_path($slug, OBJECT, 'product');
    if (!$post || $post->post_status !== 'publish') wp_send_json_error();
    wp_send_json_success(array('pid' => (int) $post->ID, 'name' => get_the_title($post)));
}
add_action('wp_ajax_af_dd_resolve',        'af_dd_resolve_handler');
add_action('wp_ajax_nopriv_af_dd_resolve', 'af_dd_resolve_handler');

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
          <div class="af-dd-imgwrap"><img id="af-dd-img" alt="" draggable="false"><span class="af-dd-shield" aria-hidden="true"></span></div>
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
        var lnk= card.querySelector('a[href*="/product/"]') || card.querySelector('a[href]');
        var name = t ? t.textContent.trim() : (lnk && lnk.title ? lnk.title.trim() : '');
        // No id on the card? Its product link still knows who it is.
        if(!curPid && lnk && /\/product\/([^\/?#]+)/.test(lnk.href)){
          var slug = lnk.href.match(/\/product\/([^\/?#]+)/)[1];
          fetch(<?php echo wp_json_encode(admin_url('admin-ajax.php')); ?> + '?action=af_dd_resolve&slug=' + encodeURIComponent(slug), {credentials:'same-origin'})
            .then(function(r){ return r.json(); })
            .then(function(j){
              if(j && j.success && j.data && j.data.pid){
                curPid = String(j.data.pid);
                if(!name && j.data.name){ name = j.data.name; titleEl.textContent = name + ' — Digital Download'; }
                if(addEl) addEl.style.display = '';
                msgEl.textContent = '';
                imgEl.removeAttribute('src'); imgEl.style.opacity = '.35';
                fetch(<?php echo wp_json_encode(admin_url('admin-ajax.php')); ?> + '?action=af_dd_preview&pid=' + encodeURIComponent(curPid), {credentials:'same-origin'})
                  .then(function(r2){ return r2.json(); })
                  .then(function(j2){ imgEl.src = (j2 && j2.success && j2.data && j2.data.url) ? j2.data.url : ''; imgEl.style.opacity = '1'; })
                  .catch(function(){ imgEl.style.opacity = '1'; });
              }
            }).catch(function(){});
        }
        // Ghost guard: a product with no image or no name must not sell blind —
        // the recording showed exactly that (blank title, broken image, Add to Cart).
        if(!curPid || !im || !name){
          imgEl.removeAttribute('src');
          titleEl.textContent = 'Not available for instant download';
          if(addEl) addEl.style.display = 'none';
          msgEl.textContent = 'This item cannot be purchased as a digital download.';
          open(); return;
        }
        if(addEl) addEl.style.display = '';
        // never show the raw artwork here: ask the server for the watermarked
        // preview (the raw file was one right-click away in the old modal)
        imgEl.removeAttribute('src');
        imgEl.style.opacity = '.35';
        fetch(<?php echo wp_json_encode(admin_url('admin-ajax.php')); ?> + '?action=af_dd_preview&pid=' + encodeURIComponent(curPid), {credentials:'same-origin'})
          .then(function(r){ return r.json(); })
          .then(function(j){
            imgEl.src = (j && j.success && j.data && j.data.url) ? j.data.url : '';
            imgEl.style.opacity = '1';
          })
          .catch(function(){ imgEl.style.opacity = '1'; });
        titleEl.textContent = name + ' — Digital Download';
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
    .af-dd-imgwrap{flex:1 1 260px;min-height:240px;background:#f4f4f4;position:relative;}
    .af-dd-shield{position:absolute;inset:0;z-index:2;}
    .af-dd-imgwrap img{-webkit-user-drag:none;user-select:none;}
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
    $product = af_wc_product();
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
        // The faceted-filter URL space (sizes x colors x frames x orientation
        // x price x sort) is combinatorially infinite for well-behaved
        // crawlers; every combination is a cache MISS and a ~3s render. The
        // crawl guard already bounces headerless clients — these lines stop
        // Googlebot/Bingbot (deliberately never blocked) from queueing them.
        'Disallow: /*?*filter_',
        'Disallow: /*?*orientation=',
        'Disallow: /*?*min_price=',
        'Disallow: /*?*max_price=',
        'Disallow: /*?*orderby=',
        'Disallow: /*?*query_type_',
        'Disallow: /*?*add-to-cart=',
        'Disallow: /*?*currency=',
        'Disallow: /*?*lang=',
        'Disallow: /*?*per_page=',
        'Disallow: /*?*layout=',
        // Deep tag pagination was the largest remaining crawl cost after the
        // filter URLs: the 2026-08-04 profile is full of /product-tag/*/page/N/
        // hits (page/22, page/13, page/11 ...) at ~2.2-4.2s each. Tag archives
        // duplicate the category listings, and page 2 onward carries no ranking
        // value the categories do not already have. Page 1 of each tag stays
        // crawlable, and every product remains discoverable through the
        // sitemap, so nothing drops out of the index by being unreachable.
        'Disallow: /product-tag/*/page/',
        // Feeds are rendered by PHP, are never cached, and bring no customers.
        'Disallow: /*/feed/',
        'Disallow: /*/embed/',
        // Ignored by Google (it paces itself from Search Console) but honoured
        // by Bing, Yandex and most well-behaved crawlers — the ones that were
        // arriving in bursts and stacking concurrent renders on a shared host.
        'Crawl-delay: 10',
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

// 24c-2. PERF: restore lazy-loading at the HTML layer + repair two bugs
// found in the 2026-07-31 speed audit (homepage: 250 requests / 6.4 MB
// visible payload + ~8 hidden YouTube embeds, full load 13.7s).
//
// Background: LiteSpeed's JS lazy-loader (data-src placeholder swap) broke
// carousel/AJAX images, so deploy.yml force-disables it — which also left
// every below-fold image loading eagerly. Browser-native loading="lazy"
// is a different mechanism (no placeholder, no JS) and is already present
// on ~60 images per page via WP core without issues, so extending it is
// safe where the JS variant was not.
//
// What this buffer does:
//  1) Repairs the LiteSpeed-mangled Google Fonts link. It ships as
//     family=Instrument+Sans:<local-css-url>, which HTTP-400s — a wasted
//     render-blocking request AND the reason Instrument Sans never loads.
//     (The local CSS file glued into the URL is a 0-byte artifact; nothing
//     is lost by dropping it.)
//  2) Adds loading="lazy" to YouTube embed iframes. The Products In Motion
//     section hides its 8 playlist embeds behind the circle slider, but
//     they still download in full (~1 MB each). Lazy + hidden = the
//     browser never fetches them; if the slider ever fails to place, they
//     still load on scroll as a fallback.
//  3) Adds loading="lazy" decoding="async" to below-fold <img> tags.
//     Skips: the first 7 images (header logos + revslider hero — the LCP
//     must stay eager), anything already declaring loading=, slider-
//     managed images (revslider/swiper run their own loaders), and
//     data-URI placeholders.
add_action('template_redirect', function () {
    if (is_admin() || is_feed() || is_customize_preview()) return;
    // Insurance: skip AJAX-fragment-heavy WooCommerce pages (the original
    // LiteSpeed breakage was lazy images inside AJAX-replaced markup).
    if (function_exists('is_cart') && (is_cart() || is_checkout() || is_account_page())) return;
    ob_start(function ($html) {
        if (stripos($html, '</html>') === false) return $html; // only full pages

        // (1) broken Google Fonts href -> correct one
        $html = preg_replace(
            '#<link\b[^>]*href=["\'](?:https?:)?//fonts\.googleapis\.com/css2\?family=Instrument\+Sans:https?[^"\']*["\'][^>]*/?>#i',
            '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;600&display=swap">',
            $html
        );

        // (2) YouTube embeds
        $html = preg_replace_callback('#<iframe\b[^>]*>#i', function ($m) {
            $tag = $m[0];
            if (stripos($tag, 'youtube.com/embed') === false) return $tag;
            if (stripos($tag, 'loading=') !== false) return $tag;
            return '<iframe loading="lazy"' . substr($tag, 7);
        }, $html);

        // (3) below-fold images
        $seen = 0;
        $html = preg_replace_callback('#<img\b[^>]*>#i', function ($m) use (&$seen) {
            $tag = $m[0];
            $seen++;
            if ($seen <= 7) return $tag;                                   // logos + hero (LCP)
            if (stripos($tag, 'loading=') !== false) return $tag;          // already lazy/eager by choice
            if (stripos($tag, 'data-src') !== false || stripos($tag, 'data-lazy') !== false) return $tag;
            if (stripos($tag, '/revslider/') !== false) return $tag;       // slider's own loader
            if (preg_match('#class=["\'][^"\']*(?:\brs-|\bsr7|\btp-|swiper-slide-image)#i', $tag)) return $tag;
            if (stripos($tag, 'src="data:') !== false || stripos($tag, "src='data:") !== false) return $tag;
            $ins = ' loading="lazy"';
            if (stripos($tag, 'decoding=') === false) $ins .= ' decoding="async"';
            return '<img' . $ins . substr($tag, 4);
        }, $html);

        return $html;
    });
}, 0);

// 24c-3. PERF: preconnect to the font/icon CDNs the page actually uses —
// fonts.gstatic.com serves the Instrument Sans woff2 files (see 24c-2),
// cdnjs.cloudflare.com serves the chat launcher's Font Awesome. Shaves a
// DNS+TLS round-trip off each first fetch.
add_action('wp_head', function () {
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
    echo '<link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>' . "\n";
    // Correct Instrument Sans stylesheet, printed by us because LiteSpeed's
    // CSS optimizer runs AFTER the 24c-2 buffer (PHP buffers flush
    // innermost-first) and mangles the original link into an HTTP-400 URL
    // that the buffer therefore cannot see, let alone repair.
    // data-no-optimize keeps LiteSpeed's optimizer off this one.
    echo '<link rel="stylesheet" data-no-optimize="1" href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;600&display=swap">' . "\n";
}, 2);

// 24c-4. AJAX-rendered product cards (Trending Today / More Like This
// sections arrive via admin-ajax) bypass the 24c-2 output buffer, so give
// their images native lazy-loading here. AJAX fragments are injected after
// first paint and are never the LCP, so blanket lazy is safe there.
add_filter('wp_get_attachment_image_attributes', function ($attr) {
    if (wp_doing_ajax() && empty($attr['loading'])) {
        $attr['loading'] = 'lazy';
        if (empty($attr['decoding'])) $attr['decoding'] = 'async';
    }
    return $attr;
}, 20);

// 24c-5. QUICK VIEW RELIABILITY: the stock quick-view binder (inline
// script outside this theme) has a success handler but NO error handler,
// so when the shared host's process cap returns a transient 508 the modal
// sits on "Loading..." forever (user-reported 2026-07-31 with a screen
// recording; reproduced live — admin-ajax load_quick_product answered
// 508). Intercept the click in the capture phase (fires before jQuery's
// delegated handler), run the same loader with a timeout, retry up to 3
// attempts with backoff — the 508s are burst-transient — and fall back to
// a link to the full product page if the host stays saturated.
add_action('wp_footer', function () {
    ?>
<script>
(function () {
  if (!window.jQuery) return;
  var $ = window.jQuery;
  var AJAX_URL = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;

  function productUrl(btn) {
    var card = btn.closest('.product-card, li.product, .product');
    var a = card ? card.querySelector('a[href*="/product/"]') : null;
    if (a) return a.href;
    var pid = btn.getAttribute('data-product-id');
    return pid ? '/?post_type=product&p=' + encodeURIComponent(pid) : null;
  }

  function load(btn, attempt) {
    $.ajax({
      url: AJAX_URL,
      type: 'POST',
      timeout: 20000,
      data: { action: 'load_quick_product', product_id: btn.getAttribute('data-product-id') },
      success: function (response) {
        $('#quickViewContent').html(response);
        // same follow-up the stock handler performs
        setTimeout(function () {
          if (typeof $.fn.wc_product_gallery !== 'undefined') {
            $('.woocommerce-product-gallery').each(function () { $(this).wc_product_gallery(); });
          }
        }, 400);
      },
      error: function () {
        if (attempt < 3) {
          setTimeout(function () { load(btn, attempt + 1); }, 800 * attempt);
          return;
        }
        var url = productUrl(btn);
        $('#quickViewContent').html(
          '<div style="padding:28px;text-align:center">' +
          '<p style="margin:0 0 14px">Sorry &mdash; the quick preview could not load right now.</p>' +
          (url ? '<a href="' + url + '" style="display:inline-block;padding:10px 22px;background:#1f1f1f;color:#fff;border-radius:6px;text-decoration:none">Open the product page</a>' : '') +
          '</div>'
        );
      }
    });
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('.quick-view-btn') : null;
    if (!btn) return;
    e.preventDefault();
    e.stopImmediatePropagation(); // keep the error-blind stock handler out of the way
    $('#quickViewContent').html('Loading...');
    $('#quickViewModal').fadeIn();
    $('body').addClass('modal-open');
    load(btn, 1);
  }, true);
})();
</script>
    <?php
}, 99);

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

// Compare toggle button on product cards — icon only, label shows as a
// native title tooltip, same as the wishlist/quick-view icons already use.
// The single-product "Add to Compare" CTA below keeps its full text; only
// the card-level icon is condensed.
add_action('woocommerce_after_shop_loop_item', function() {
    $product = af_wc_product();
    if (!$product) return;
    echo '<button type="button" class="af-cmp-btn" data-id="' . esc_attr($product->get_id())
        . '" title="Compare" aria-label="Add to compare">'
        . '<span class="af-cmp-icon" aria-hidden="true">⇄</span></button>';
}, 25);

// Compare toggle on single product pages
add_action('woocommerce_after_add_to_cart_button', function() {
    $product = af_wc_product();
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
    b.dataset.id = id;
    if (cls === 'af-cmp-single') {
      b.textContent = '⇄ Add to Compare';
    } else {
      b.setAttribute('title', 'Compare');
      b.setAttribute('aria-label', 'Add to compare');
      var icon = document.createElement('span');
      icon.className = 'af-cmp-icon';
      icon.setAttribute('aria-hidden', 'true');
      icon.textContent = '⇄';
      b.appendChild(icon);
    }
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
// WordPress re-encodes every registered size at quality 82 by default. That's
// tuned for photographs; this catalogue is flat-colour, hard-edged artwork,
// where any re-encode leaves visible ringing along the colour boundaries.
// Set to 100 (no additional compression loss) so every generated thumbnail/
// medium/large copy stays visually identical to the uploaded original.
// Only affects copies generated from here on — existing sizes keep their
// current quality until thumbnails are regenerated.
add_filter('jpeg_quality',          function() { return 100; }, 10, 0);
add_filter('wp_editor_set_quality', function() { return 100; }, 10, 0);

// ── The size the cards actually need ─────────────────────────
// WooCommerce generates the catalogue thumbnail at 600px wide. A card on the
// three-up grid is about 380 CSS px across, which is comfortable on an
// ordinary screen and half of what a 2x phone or laptop asks for — so on
// every retina display the browser is stretching a 600px file across 760
// device pixels, and flat-colour artwork with hard edges shows that as soft,
// slightly furry outlines. 800px covers the 2x case with a little headroom.
//
// Width only. Height and crop are left exactly as WooCommerce has them,
// because those decide the SHAPE of every card on the site and this is a
// change about resolution, not layout. And only ever upward: if the setting
// is already larger than this, it is deliberate and stays.
// Only ever upward, width only, height scaled to match when the size is
// uncropped — the same rule for all three below, so none of them changes the
// SHAPE of anything, only how many pixels it is drawn from.
function af_bump_image_width( $size, $want ) {
    $want = (int) $want;
    if ( ! empty( $size['width'] ) && (int) $size['width'] >= $want ) return $size;
    // Scale the height by the same factor whether the size is cropped or not.
    // Skipping it for cropped sizes was a bug and precisely the thing this
    // helper exists to avoid: the gallery thumbnail ships as 100x100 cropped
    // square, and raising the width alone turned it into 200x100 — a square
    // thumbnail silently became a 2:1 letterbox. A cropped size's height is
    // what defines its shape, so it has to move with the width.
    if ( ! empty( $size['height'] ) && ! empty( $size['width'] ) ) {
        $size['height'] = (int) round( $size['height'] * ( $want / (int) $size['width'] ) );
    }
    $size['width'] = $want;
    return $size;
}

// ── The product page's own picture ───────────────────────────
// This is the one that was missed, and it is the one that matters most: the
// main image on a product page is woocommerce_single, NOT the catalogue
// thumbnail raised below. WooCommerce ships it at 600px wide. That image is
// drawn at roughly 570 CSS px on a desktop product page, so on any 2x screen
// the browser is stretching 600 pixels across about 1140 — nearly double —
// and this is precisely the picture a customer leans in to look at before
// spending eighty dollars.
//
// 1200 covers 2x with a little to spare and stays inside the masters, which
// the orientation scan measured at about 1600px on the long side, so nothing
// here is invented from pixels that do not exist.
add_filter('woocommerce_get_image_size_single', function ($size) {
    return af_bump_image_width( $size, (int) apply_filters('af_single_image_width', 1200) );
});

// The little strip beside it, likewise: shipped at 100px and shown at about
// that size, which is a blurred stamp on any modern screen.
add_filter('woocommerce_get_image_size_gallery_thumbnail', function ($size) {
    return af_bump_image_width( $size, (int) apply_filters('af_gallery_thumb_width', 200) );
});

add_filter('woocommerce_get_image_size_thumbnail', function ($size) {
    return af_bump_image_width( $size, (int) apply_filters('af_card_image_width', 800) );
});

// WordPress also silently downscales any upload wider or taller than 2560px
// on the way in, and that scaled-down copy — not the original — becomes the
// "full" size used everywhere (product pages, zoom, etc.); the true original
// is kept but never referenced. Disable that so uploaded images are used at
// their full uploaded resolution.
add_filter('big_image_size_threshold', '__return_false');

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

/**
 * Watermarks belong on products SOLD as files, not on the whole catalogue
 * (every product is flagged downloadable for the add-on option, so
 * is_downloadable() cannot be the gate). Digital-download categories and
 * their children are the honest signal.
 */
function af_wm_applies($product) {
    if (!($product instanceof WC_Product)) return false;
    $ids = array();
    foreach (array('digital-downloads', 'instant-downloads', 'printable-art') as $slug) {
        $t = get_term_by('slug', $slug, 'product_cat');
        if (!$t) continue;
        $ids[] = (int) $t->term_id;
        $kids = get_term_children($t->term_id, 'product_cat');
        if (!is_wp_error($kids)) $ids = array_merge($ids, array_map('intval', $kids));
    }
    if (!$ids) return false;
    return has_term($ids, 'product_cat', $product->get_id());
}

// Swap gallery images for watermarked previews on downloadable products
add_filter('woocommerce_single_product_image_thumbnail_html', function($html, $attachment_id) {
    if (!is_product()) return $html;
    if (!af_wm_enabled()) return $html;
    $product = af_wc_product();
    if (!$product || !af_wm_applies($product)) return $html;
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
    $product = af_wc_product();
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
        // the note follows af_shipping_copy(); it used to promise free delivery
        // in four states, which is what the currency strip showed on the shop,
        // cart, about, FAQ and every product page
        'US' => array('flag' => '🇺🇸', 'name' => 'United States', 'cur' => 'USD',
                      'note' => function_exists('af_shipping_copy') ? af_shipping_copy()['short'] : 'Shipping cost shown at checkout'),
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
  <button type="button" class="af-cty-btn" aria-haspopup="true" aria-expanded="false"
          title="Choose the country you are shipping to">
    <span class="af-cty-flag"><?php echo $sel['flag']; ?></span>
    <svg class="af-cty-globe" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><path d="M2 12h20"></path><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
    <span class="af-cty-label">Ship to</span>
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

  // Sit the selector in the header contact row, between the phone number and
  // the Facebook icon. That row is theme markup, so the node is moved into it
  // (the listeners above survive the move). Anchoring on the phone number is
  // far more reliable than the social icon's href, and it lands the selector
  // exactly where it belongs. Falls back to the social icons, then leaves the
  // selector hidden rather than dropping it somewhere wrong.
  function inFooter(el){
    return !!(el.closest && el.closest('footer, .site-footer, #footer, .elementor-location-footer, .footer'));
  }
  function put(anchor, where){
    var host = (anchor.closest && anchor.closest('.elementor-widget, li')) || anchor;
    if (!host.parentNode || host === w || host.contains(w)) return false;
    host.parentNode.insertBefore(w, where === 'after' ? host.nextSibling : host);
    w.dataset.moved = '1';
    w.classList.add('af-cty--inline');
    // Keep the contact row on ONE clean line and pack its items together
    // instead of letting them spread edge-to-edge (which was forcing the email
    // and phone text to wrap). Apply to the flex ancestors that hold the row.
    // The row's spacing in three numbers: GAP between items, PAD inside each
    // one, SEP before a contact detail. PAD matters as much as GAP — the
    // theme's own padding sits inside each item, where no gap value reaches
    // it, which is why passes that only touched GAP appeared to do nothing.
    // Tune here.
    var GAP = '10px', PAD = '4px', SEP = '18px';
    var p = w.parentElement, hops = 0;
    while (p && hops < 4) {
      var cs = window.getComputedStyle(p);
      if (cs.display === 'flex' || cs.display === 'inline-flex') {
        // ONE line, and one that fits. Wrapping put the tail on a second row
        // and the row read as two half-rows; nowrap on its own overflowed or
        // got clipped. Neither is what this bar should do — so it stays on one
        // line and afFitRow() below shrinks it until it fits the page.
        p.style.setProperty('flex-wrap', 'nowrap', 'important');
        p.style.setProperty('align-items', 'center', 'important');
        p.style.setProperty('overflow', 'visible', 'important');
        // Pack from the start rather than spreading edge to edge. Centring
        // was how the social icons first left the page: a centred row that
        // runs wide spills past BOTH edges, and the left-hand spill cannot be
        // scrolled back to.
        if (/space-|center/.test(cs.justifyContent)) {
          p.style.setProperty('justify-content', 'flex-start', 'important');
        }
        // One gap for the whole row rather than a conditional range, so the
        // items sit at even distances instead of keeping whatever each
        // ancestor started with.
        p.style.setProperty('column-gap', GAP, 'important');
        p.style.setProperty('gap', GAP, 'important');
        // The gap alone barely moved these items, because it is not what was
        // holding them apart: each one carries the theme's own horizontal
        // PADDING, and padding sits inside the item, so no gap value can
        // shrink it. Margin and padding both have to come down for the row to
        // actually close up.
        for (var m = 0; m < p.children.length; m++) {
          var it = p.children[m];
          it.style.setProperty('margin-left', '0', 'important');
          it.style.setProperty('margin-right', '0', 'important');
          it.style.setProperty('padding-left', PAD, 'important');
          it.style.setProperty('padding-right', PAD, 'important');
          // …and on the link inside it, which usually carries the padding
          var ins = it.querySelectorAll('a,.elementor-widget-container,.menu-item');
          for (var r = 0; r < ins.length; r++) {
            ins[r].style.setProperty('padding-left', PAD, 'important');
            ins[r].style.setProperty('padding-right', PAD, 'important');
            ins[r].style.setProperty('margin-left', '0', 'important');
            ins[r].style.setProperty('margin-right', '0', 'important');
          }
          // The nav links want to sit close together; the contact details do
          // NOT. "My account" hard against an email address reads as one long
          // string, which is what made that pair look broken even once they
          // stopped overlapping. Give anything that is a mailto: or tel: its
          // own breathing room, so the row is tight where it should be tight
          // and separated where the meaning changes.
          if (it.querySelector('a[href^="mailto:"], a[href^="tel:"]') ||
              /^(mailto|tel):/.test(it.getAttribute('href') || '')) {
            it.style.setProperty('margin-left', SEP, 'important');
          }
        }
        for (var k = 0; k < p.children.length; k++) {
          var ch = p.children[k];
          // white-space is inherited, so this stops the email/phone text inside
          // each item from breaking onto two lines
          ch.style.setProperty('white-space', 'nowrap', 'important');
          // Tracked-out uppercase (MY ACCOUNT) is wider than the box the theme
          // measured for it, so the tail of one item printed on top of the
          // next. Normal spacing costs a little style and buys a row that
          // reads; the items keep their own size and stop being squeezed.
          ch.style.setProperty('letter-spacing', 'normal', 'important');
          ch.style.setProperty('word-spacing', 'normal', 'important');
          // shrinkable, so a tight window narrows the row instead of
          // pushing its last items out of the page
          ch.style.setProperty('flex', '0 1 auto', 'important');
          ch.style.setProperty('min-width', '0', 'important');
          var kids = ch.querySelectorAll('a,span,p,div,strong,em');
          for (var q = 0; q < kids.length; q++) {
            kids[q].style.setProperty('letter-spacing', 'normal', 'important');
            kids[q].style.setProperty('word-spacing', 'normal', 'important');
          }
        }
      }
      p = p.parentElement; hops++;
    }
    afFitRow(afRowOf(w));
    return true;
  }

  // The flex row that actually holds these items.
  function afRowOf(el){
    var n = el && el.parentElement, guard = 0;
    while (n && guard < 4) {
      var d = window.getComputedStyle(n).display;
      if (d === 'flex' || d === 'inline-flex') return n;
      n = n.parentElement; guard++;
    }
    return null;
  }

  // Make the row fit its container on ONE line, by measuring rather than
  // guessing. Every fixed set of numbers tried here was wrong at some window
  // width — too loose and the tail left the page, too tight and the row was
  // unreadable at widths where it did not need to be. So: start at a
  // comfortable spacing, and give ground only while the row is still too wide,
  // cheapest thing first — gap, then padding, then type size, with a floor so
  // it can never shrink to unreadable.
  function afFitRow(row){
    if (!row || !row.clientWidth) return;   // not rendered yet — measuring it
                                            // would only prove it "fits"

    // Start every pass from the top of the ladder, not from wherever the last
    // pass stopped. This routine only ever gives ground, so without a reset it
    // is a one-way trip: a phone-width pass wraps the row, and a wrapped row
    // can never overflow, so the next pass at desktop width measures "fits"
    // and returns — the header stays wrapped and small at a width that had
    // room to spare. Undo the previous pass first, then re-measure.
    row.style.removeProperty('flex-wrap');
    row.style.removeProperty('row-gap');
    row.style.setProperty('flex-wrap', 'nowrap', 'important');
    // Only take back the type size if WE set it — the theme puts inline sizes
    // on some of these elements and wiping those would be a different bug.
    // Our own marks are unmistakable: the flag on the row, and 'inherit' on
    // the descendants, which is the only value this routine ever writes there.
    if (row.getAttribute('data-af-fit-size') === '1') {
      row.style.removeProperty('font-size');
      row.removeAttribute('data-af-fit-size');
    }
    var prev = row.querySelectorAll('a,span,p,div,strong,em,li');
    for (var r = 0; r < prev.length; r++) {
      if (prev[r].style.fontSize === 'inherit') prev[r].style.removeProperty('font-size');
    }

    var gap = 10, pad = 4, size = 0;      // size 0 = leave the theme's own
    function apply(){
      row.style.setProperty('column-gap', gap + 'px', 'important');
      row.style.setProperty('gap', gap + 'px', 'important');
      for (var i = 0; i < row.children.length; i++) {
        var it = row.children[i];
        it.style.setProperty('padding-left', pad + 'px', 'important');
        it.style.setProperty('padding-right', pad + 'px', 'important');
        var ins = it.querySelectorAll('a,.elementor-widget-container,.menu-item');
        for (var j = 0; j < ins.length; j++) {
          ins[j].style.setProperty('padding-left', pad + 'px', 'important');
          ins[j].style.setProperty('padding-right', pad + 'px', 'important');
        }
      }
      if (size) {
        row.style.setProperty('font-size', size + 'px', 'important');
        row.setAttribute('data-af-fit-size', '1');
        var t = row.querySelectorAll('a,span,p,div,strong,em,li');
        for (var k = 0; k < t.length; k++) t[k].style.setProperty('font-size', 'inherit', 'important');
      }
    }
    function tooWide(){ return row.scrollWidth > row.clientWidth + 1; }

    apply();
    if (!tooWide()) return;               // it already fits — leave it alone

    var guard = 0;
    while (tooWide() && guard < 40) {
      if (gap > 2)            gap -= 1;
      else if (pad > 0)       pad -= 1;
      else if (!size)         size = 13;  // only now start on the type
      else if (size > 10)     size -= 0.5;
      else break;                          // floor: readable beats fitting
      apply();
      guard++;
    }

    // Narrow enough — a phone — and one line is simply not possible without
    // shrinking the text past reading. Wrap there instead: a second line is a
    // worse layout than one line, but it is a far better outcome than text at
    // 8px or a row running off the screen. One line where it fits, legible
    // everywhere.
    if (tooWide()) {
      row.style.setProperty('flex-wrap', 'wrap', 'important');
      row.style.setProperty('row-gap', '4px', 'important');
    }
  }

  // ── Re-fit whenever the answer could have changed ──────────────────
  // A single resize listener is not enough. The row is measured, so it has to
  // be re-measured every time the measurement could differ: a narrower window,
  // yes, but also web fonts arriving after first paint (they change every
  // width), a container that resizes without the window doing so (the sticky
  // header shrinking on scroll), a phone turning sideways, and the case that
  // silently did nothing before — being measured while hidden, where
  // clientWidth is 0 and the row looks like it fits when it has not rendered.
  var afFitT = null, afFitting = false;
  function afRefit(){
    var row = afRowOf(w);
    if (!row) return;
    if (!row.clientWidth) return;        // hidden: nothing meaningful to measure
    afFitting = true;                    // our own writes must not re-trigger us
    afFitRow(row);
    setTimeout(function(){ afFitting = false; }, 60);
  }
  function afRefitSoon(){
    if (afFitting) return;
    clearTimeout(afFitT);

  // Deepest element in the header area whose text contains the phone number.
  function phoneEl(){
    var re = /470[\s .\-]?7280/;
    var all = document.body ? document.body.querySelectorAll('a,span,div,p,li,strong,em') : [];
    for (var i = 0; i < all.length; i++) {
      var el = all[i];
      if (inFooter(el)) continue;
      if (!re.test(el.textContent || '')) continue;
      var childHit = false;
      for (var j = 0; j < el.children.length; j++) {
        if (re.test(el.children[j].textContent || '')) { childHit = true; break; }
      }
      if (!childHit) return el;   // deepest match, header comes first in the DOM
    }
    return null;
  }
  var tries = 0;
  function relocate(){
    if (w.dataset.moved) return true;
    var i, links;
    // 1) right AFTER the phone number (matched by its text) → before the FB icon
    var pe = phoneEl();
    if (pe && put(pe, 'after')) return true;
    // 2) after a tel: link
    links = document.querySelectorAll('a[href^="tel:"]');
    for (i = 0; i < links.length; i++) { if (!inFooter(links[i]) && put(links[i], 'after')) return true; }
    // 3) before the first social icon
    links = document.querySelectorAll('a[href*="facebook"],a[href*="instagram"],a[aria-label*="Facebook" i],a[aria-label*="Instagram" i]');
    for (i = 0; i < links.length; i++) { if (!inFooter(links[i]) && put(links[i], 'before')) return true; }
    // 4) after the email address
    links = document.querySelectorAll('a[href^="mailto:"]');
    for (i = 0; i < links.length; i++) { if (!inFooter(links[i]) && put(links[i], 'after')) return true; }
    return false;
  }
  // Last resort: never let the selector stay hidden. If it can't be placed in
  // the contact row after several tries, show it fixed in the top-right corner
  // so currency switching is always reachable.
  function ensureVisible(){
    if (w.dataset.moved) return;
    w.dataset.moved = '1';
    w.classList.add('af-cty--inline', 'af-cty--fallback');
    document.body.appendChild(w);
  }
  function tick(){
    if (relocate()) return;
    if (++tries >= 12) ensureVisible();
  }
  document.addEventListener('DOMContentLoaded', tick);
  window.addEventListener('load', tick);
  [300, 700, 1200, 1800, 2600, 3600, 5000].forEach(function(d){ setTimeout(tick, d); });
  try {
    var mo = new MutationObserver(function(){
      if (w.dataset.moved) { mo.disconnect(); return; }
      relocate();
    });
    mo.observe(document.documentElement, { childList: true, subtree: true });
  } catch (e) {}
})();
</script>
<style>
/* CRITICAL: the dropdown must stay CLOSED until its button is clicked, in any
   context it is relocated into. In the relocated header the base
   `.af-cty-menu{display:none}` was being overridden, leaving the panel open
   and covering the page — force it hidden unless the wrapper has .open. */
.af-cty .af-cty-menu{display:none !important;}
.af-cty.open .af-cty-menu{display:block !important;}

/* Match the My Account dropdown: a clean SOLID WHITE panel with dark text.
   Literal hex only — the CSS custom properties (--taf-dark/--taf-gold) do not
   resolve in the relocated Elementor header context, which is what made the
   text invisible before. */
.af-cty .af-cty-menu{background:#ffffff !important;border:1px solid #e6e2d8 !important;
  border-radius:6px !important;box-shadow:0 12px 32px rgba(0,0,0,.16) !important;
  opacity:1 !important;overflow:hidden !important;}
.af-cty .af-cty-head{color:#8a8a8a !important;background:#ffffff !important;}
.af-cty .af-cty-item{background:#ffffff !important;color:#1e1e1e !important;}
.af-cty .af-cty-item:hover{background:#f5f1e6 !important;}
.af-cty .af-cty-item.on{background:#f5f1e6 !important;}
.af-cty .af-cty-nm{color:#1e1e1e !important;}
.af-cty .af-cty-nm small{color:#777777 !important;}
.af-cty .af-cty-cur{color:#8a6d1f !important;}
.af-cty .af-cty-foot{color:#777777 !important;background:#ffffff !important;
  border-top:1px solid #e6e2d8 !important;}
.af-cty .af-cty-foot a{color:#8a6d1f !important;}

/* Relocated next to the social icons: align on the row and keep the dropdown
   anchored to the button rather than the old utility-bar position. */
.af-cty--inline{display:inline-flex !important;align-items:center;margin:0 6px;vertical-align:middle;
  flex:0 0 auto;white-space:nowrap;}
.af-cty--inline .af-cty-menu{right:0;left:auto;}
/* Match the plain-text look of the other header items (English, CA$ CAD,
   About Us …): drop the boxed border and inherit the row's font and colour
   instead of the utility-bar's boxed style. */
.af-cty--inline .af-cty-btn{border:none !important;background:transparent !important;
  color:inherit !important;font:inherit !important;font-weight:600 !important;
  line-height:1;white-space:nowrap;padding:0 !important;gap:4px;
  text-transform:none;letter-spacing:normal;cursor:pointer;}
.af-cty--inline .af-cty-btn:hover{color:var(--taf-gold) !important;border:none !important;}
.af-cty--inline .af-cty-code{font:inherit !important;font-weight:600 !important;}
.af-cty--inline .af-cty-caret{font-size:.8em;opacity:.75;}
/* On Windows/Chrome the flag emoji renders as its letters (e.g. "GB"), which
   next to the code reads as "GB GB". Drop the flag in the inline placement so
   it shows just the code + caret, and it's narrower — helping the one-line fit. */
.af-cty--inline .af-cty-flag{display:none !important;}
/* The chip read as faint grey "GB" on a dark bar: too low-contrast to notice
   and too terse to understand. Give it readable colour, a quiet pill outline so
   it looks clickable, a globe mark and the words "Ship to" — the label folds
   away on narrow screens where the row has no room for it. */
.af-cty--inline .af-cty-btn{
  color:#f4efe3 !important;
  padding:4px 10px !important;
  border:1px solid rgba(244,239,227,.35) !important;
  border-radius:999px !important;
  background:rgba(255,255,255,.07) !important;
  gap:6px !important;
  transition:border-color .2s,background .2s,color .2s;}
.af-cty--inline .af-cty-btn:hover,
.af-cty--inline .af-cty-btn:focus-visible{
  color:#fff !important;
  border-color:var(--taf-gold,#c9a84c) !important;
  background:rgba(201,168,76,.22) !important;}
.af-cty--inline .af-cty-globe{width:14px;height:14px;flex:0 0 auto;opacity:.9;}
.af-cty--inline .af-cty-label{font-weight:500;opacity:.85;}
.af-cty--inline .af-cty-code{letter-spacing:.02em;}
.af-cty--inline .af-cty-caret{opacity:.9;}
@media (max-width:1100px){
  .af-cty--inline .af-cty-label{display:none;}
  .af-cty--inline .af-cty-btn{padding:4px 8px !important;}
}
/* Last-resort corner placement if the contact row can't be found. */
.af-cty--fallback{position:fixed !important;top:8px;right:14px;z-index:100000;
  background:rgba(20,20,20,.9);border:1px solid rgba(201,168,76,.5);border-radius:6px;
  padding:2px 6px;}
.af-cty--fallback .af-cty-code,.af-cty--fallback .af-cty-caret{color:#e8e2cf;}
</style>
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
        do_action('af_rma_admin_saved', $id);
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
        do_action('af_rma_admin_extra', $r);
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
// Values mirror the store's PUBLISHED policies (shipping: throughout the
// USA at the rate in af_shipping_cost, ~5 business days production + up to
// 10 days transit;
// refund page: 7-day return window). returnFees intentionally
// omitted — free returns are only promised for damaged/wrong-item,
// so claiming FreeReturn store-wide would overstate. Makes products
// eligible for "free shipping / returns" treatment in US results.
// Keep these in sync with the policy pages if they change.
// ─────────────────────────────────────────────────────────────
function af_offer_shipping_details() {
    // The rate follows af_shipping_copy(): telling Google "0" while the site
    // charges for shipping is a false free-shipping claim in Merchant results,
    // which is a policy problem as well as a factual one. With no rate
    // configured the amount is omitted rather than guessed at.
    $cost = function_exists('af_shipping_copy') ? af_shipping_copy()['cost'] : '';
    $num  = ($cost !== '' && preg_match('/[\d.]+/', $cost, $m)) ? $m[0] : '';
    $rate = ($num !== '')
        ? array('@type' => 'MonetaryAmount', 'value' => $num, 'currency' => 'USD')
        : null;
    return array_filter(array(
        '@type' => 'OfferShippingDetails',
        'shippingRate' => $rate,
        'shippingDestination' => array('@type' => 'DefinedRegion', 'addressCountry' => 'US'),
        'deliveryTime' => array(
            '@type' => 'ShippingDeliveryTime',
            'handlingTime' => array('@type' => 'QuantitativeValue', 'minValue' => 1, 'maxValue' => 5, 'unitCode' => 'DAY'),
            'transitTime'  => array('@type' => 'QuantitativeValue', 'minValue' => 3, 'maxValue' => 10, 'unitCode' => 'DAY'),
        ),
    ), function($v){ return $v !== null; });
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
    // camera=(self) so the Try-On-Wall / Frame-The-Moment live camera preview
    // can request it (spec §8 "AR – live camera"); still blocked for iframes.
    header('Permissions-Policy: geolocation=(), camera=(self), microphone=()');
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
    // Printed on every front-end page, not just the product page. The quick-view
    // modal is injected into whatever page the visitor is already on — the shop,
    // the homepage — so gating this to is_product() left the modal's Recently
    // Viewed strip with no .af-recent-row rule at all, which is why it came out
    // as a column of full-width images. Every selector is .af-* scoped and the
    // whole block is ~3 KB, so serving it site-wide is the cheap fix.
    if (is_admin()) return;
    ?>
<style>
.af-opt-sub{font-weight:400;color:#999;font-size:11.5px;}
/* Only the "Custom ↗" pill still uses this — the S/M/L filters went with the
   size chip grid, since a five-item dropdown has nothing to filter. */
.af-chip-grp{background:#f5f0e4;border:1px solid #e0d5b8;color:#6b5a23;font-size:11.5px;font-weight:800;
  padding:5px 13px;cursor:pointer;border-radius:20px;text-decoration:none;display:inline-block;}
.af-chip-custom{border-style:dashed;}
.af-wall-hint{margin:2px 0 12px;font-size:12.5px;color:#8a6d1f;font-weight:600;}
.af-rec{display:block;font-size:9px;letter-spacing:.08em;text-transform:uppercase;color:#256d2c;font-weight:800;}
.af-color-tip{margin:2px 0 8px;font-size:11.5px;color:#999;}
.af-live-mrp{color:#9a9a9a;margin-left:10px;font-size:15px;text-decoration:line-through;text-decoration-color:#9a9a9a;text-decoration-thickness:2px;}
.af-live-disc{margin-left:8px;font-size:13.5px;font-weight:700;color:#4caf2f;}
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

  // ── Wall-suitability hint follows the selected size
  var hintEl = document.getElementById('afWallHint');
  function refreshExtras(){
    var sz = (opts.querySelector('input[name="af_size"]') || {}).value;
    if (hintEl) hintEl.textContent = (cfg.hints && cfg.hints[sz]) ? '📐 ' + cfg.hints[sz] : '';
    // MRP strikethrough plus the matching discount badge, so the product page
    // shows the saving too. The multiplier is this product's own — the same
    // one af_mrp_multiplier() gave tools/apply-mrp-markup.php when it wrote
    // the card price — because the live price recomputes as the size and frame
    // change, so the strike-through has to be derived here rather than read.
    var live = document.getElementById('af-live-price'),
        mrp  = document.getElementById('af-live-mrp'),
        disc = document.getElementById('af-live-disc');
    if (live && mrp) {
      var num = parseFloat((live.textContent || '').replace(/[^0-9.]/g, ''));
      var symM = (live.textContent || '').match(/^[^0-9]*/);
      var mult = parseFloat(opts.dataset.mrpMult) || 1.40;
      if (num) {
        var mrpVal = num * mult;
        mrp.textContent = (symM ? symM[0] : '$') + mrpVal.toFixed(2);
        if (disc) {
          var pct = Math.round((mrpVal - num) / mrpVal * 100);
          disc.textContent = pct > 0 ? '(' + pct + '% OFF)' : '';
        }
      }
    }
  }
  opts.addEventListener('click', function(){ setTimeout(refreshExtras, 30); });
  // the size dropdown also changes by keyboard, which never fires a click
  opts.addEventListener('change', function(){ setTimeout(refreshExtras, 30); });
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
    opts.addEventListener('change', function(){ setTimeout(dims, 30); });

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

af_section(function() {
    if (!af_show_product_sections()) return;   // page + quick-view modal, nothing else
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
/**
 * The frame-colour dots shown on every card. One definition, so the
 * server-rendered strip and the AJAX fallback can never drift apart.
 */
function af_card_vars_dots() {
    return array('Black' => '#1a1a1a', 'Silver' => '#c0c0c0', 'Gold' => '#d4af37', 'Rose Gold' => '#b76e79');
}

/**
 * "5 sizes · 1 frame" — the card's option summary. Counts only what the
 * product page will actually sell, so a card never advertises a size or a
 * frame the selector refuses. Shared by the rendered strip and the AJAX
 * fallback, which is why it is built in one place.
 */
function af_card_vars_label() {
    $sizes  = count(af_sizes_available());
    $frames = count(af_frames_in_stock());
    return $sizes . ' size' . ($sizes === 1 ? '' : 's')
         . ' &middot; ' . $frames . ' frame' . ($frames === 1 ? '' : 's');
}

/** The strip's markup for one product, or '' when the product has no options. */
function af_card_vars_html($product) {
    if (!$product || $product->get_status() !== 'publish') return '';
    if (!af_pricing_applies($product) || !$product->is_purchasable()) return '';

    $cfg  = af_pricing_config();
    $base = $product->is_type('variable')
        ? (float) $product->get_variation_price('min')
        : (float) wc_get_price_to_display($product);

    $dots = '';
    foreach (af_card_vars_dots() as $name => $hex) {
        $dots .= '<i title="' . esc_attr($name) . '" style="background:' . esc_attr($hex) . '"></i>';
    }
    return '<div class="af-card-vars"><a href="' . esc_url(get_permalink($product->get_id()) . '#af-opts') . '">'
         . '<span class="af-card-dots">' . $dots . '</span>'
         . '<span>' . af_card_vars_label() . '</span>'
         . '<span class="af-card-from">From ' . esc_html(wp_strip_all_tags(wc_price($base))) . '</span>'
         . '</a></div>';
}

// PERF: render the strip into the card at page-build time. The 2026-08-04
// profiler measured this endpoint at 43.1s of PHP across 33 calls (~1.3s each)
// in 15 minutes — and every one of those calls happened on a page LiteSpeed had
// already served from cache, because the markup was only ever built in the
// browser. Emitting it here folds the cost into the cached HTML: paid once per
// cache generation instead of once per visitor. Priority 13 puts it after the
// price (10) and the discount badge (12), matching where the JS inserted it.
// The JS skips any card that already carries .af-card-vars and makes no request
// at all when every card is covered, so it now only fires for cards that this
// hook cannot reach (Elementor-built homepage rows).
add_action('woocommerce_after_shop_loop_item_title', function() {
    $product = af_wc_product();
    if (!$product) return;
    echo af_card_vars_html($product); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the builder
}, 13);

function af_card_variations_handler() {
    $raw = isset($_POST['ids']) ? (array) $_POST['ids'] : array();
    $ids = array_slice(array_filter(array_map('absint', $raw)), 0, 48);
    $cfg = af_pricing_config();
    $meta = array('sizes' => count(af_sizes_available()), 'frames' => count(af_frames_in_stock()),
                  'label' => html_entity_decode(af_card_vars_label(), ENT_QUOTES, 'UTF-8'));
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
            '<span>' + (meta.label || (meta.sizes + ' sizes · ' + meta.frames + ' frames')) + '</span>' +
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
// on the homepage.
// ROOT CAUSE (from post-75 Elementor CSS): the container that
// wraps the reviews widget (elementor-element-2596335) carries
// --margin-bottom:-141px (desktop) / -110px (smaller breakpoint),
// which pulls the next section up underneath the cards. Neutralise
// that margin — scoped to this one container on page 75 only.
// Also keep a widget-scoped safety net (#g-review):
//   • CSS: let the swiper track/slides size to their content
//   • JS : if the widget (or its container) still reports less
//     height than its content occupies, pin min-height to the
//     real content height; re-check on resize and late loads
// ─────────────────────────────────────────────────────────────
add_action('wp_footer', function () {
    ?>
<style id="af-review-overlap-fix">
.elementor-75 .elementor-element.elementor-element-2596335,
.elementor-element.elementor-element-2596335 {
  --margin-bottom: 0px !important;
  margin-bottom: 0 !important;
}
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

// ─────────────────────────────────────────────────────────────
// The review carousel's arrows did nothing when clicked.
//
// ROOT CAUSE (measured, tools/diag-review-slider.php): the widget's slider
// markup is on the page — <div class="swiper reviews_embedder_slider"> with
// its slides — but the served homepage references NO slider library at all
// ("none — no slider library is loaded", 90 scripts on the page). Embedder for
// Google Reviews enqueues its assets when it sees its shortcode in the post
// content; on this homepage the shortcode lives inside Elementor's JSON, so
// that check never matches and the script is never queued. The markup renders,
// the arrows render, and nothing is listening to them.
//
// The fix is to notice when the shortcode actually renders and load the
// plugin's own assets — its files, unmodified — so the slider initialises the
// way it always did. Scoped to pages that really render the widget, so nothing
// else on the site is affected. Footer scripts still run before
// DOMContentLoaded, so the plugin's own init listener fires normally.
// ─────────────────────────────────────────────────────────────
add_filter('do_shortcode_tag', function ($output, $tag) {
    if (!is_admin() && is_string($output) && $output !== ''
        && (strpos($output, 'id="g-review"') !== false || strpos($output, 'grwp_') !== false)) {
        $GLOBALS['af_grwp_rendered'] = true;
    }
    return $output;
}, 10, 2);

/**
 * Will the reviews widget appear on the page being served?
 *
 * The shortcode-render flag is the reliable signal, but it is only set once the
 * content has rendered, and Elementor can serve a widget from its own cache
 * without running the shortcode again — measured: the first attempt reported
 * "widget shortcode not detected" on a page that plainly shows the widget. So
 * also look at where the design is stored: if this page's Elementor data or
 * content asks for the reviews widget, its assets belong on the page.
 */
function af_grwp_page_has_widget() {
    if (is_admin()) return false;
    if (!empty($GLOBALS['af_grwp_rendered'])) return true;

    // Two attempts at inferring this failed against the live page: the
    // shortcode-render flag missed because Elementor can serve the widget from
    // its own cache, and matching the stored design missed because the
    // shortcode's own tag carries none of the words the widget's markup does.
    // Guessing the tag would be a third inference. The plugin's front-end
    // bundle is small and self-initialising, so when the plugin is active the
    // assets simply ship — a little weight on pages without the widget, in
    // exchange for arrows that work wherever the widget appears.
    static $active = null;
    if ($active === null) {
        $active = false;
        foreach ((array) get_option('active_plugins', array()) as $p) {
            if (strpos($p, 'embedder-for-google-reviews') === 0) { $active = true; break; }
        }
    }
    return $active;
}

add_action('wp_footer', function () {
    if (!af_grwp_page_has_widget()) return;

    $slug   = 'embedder-for-google-reviews';
    $loaded = array();

    // Print the plugin's files directly, and do it FIRST.
    //
    // The previous order asked WordPress to print the plugin's registered
    // handle instead, reasoning that this preserves its dependencies. Measured
    // on the live page that path reported success — state "enqueued", handle
    // "google-reviews" — while the served html still contained no plugin
    // script at all, so the arrows stayed dead. Whatever swallows that print,
    // a plain tag cannot be swallowed.
    //
    // Order matters here: the vendor swiper bundle must execute before the
    // plugin's own bundle asks it to build the carousel.
    $base = WP_PLUGIN_DIR . '/' . $slug;
    if (!is_dir($base)) { af_grwp_state('plugin-dir-missing', array()); return; }

    // Take every front-end file the plugin ships. An earlier version filtered
    // names to front/public/main/bundle/… and found nothing, because the
    // plugin does not name its files any of those things — the filter was a
    // guess about someone else's build output, and a guess is what left the
    // page with no slider library at all.
    $seen = array();
    foreach (array('js', 'css') as $ext) {
        $files = array();
        foreach (array('/*.', '/dist/*.', '/dist/*/*.', '/assets/*.', '/assets/*/*.',
                       '/public/*.', '/public/*/*.') as $pat) {
            $files = array_merge($files, (array) glob($base . $pat . $ext));
        }
        $files = array_values(array_unique(array_filter($files)));
        // vendor libraries first: the plugin's own bundle calls Swiper, and a
        // library that loads afterwards is a library that was not there when
        // the carousel tried to start
        usort($files, function ($a, $b) {
            $av = (int) (strpos($a, '/vendor/') !== false);
            $bv = (int) (strpos($b, '/vendor/') !== false);
            return $av === $bv ? strcmp($a, $b) : ($bv - $av);
        });
        foreach ($files as $file) {
            if (preg_match('#/(admin|backend|block|editor|gutenberg)#i', $file)) continue;
            $name = basename($file);
            if (isset($seen[$name])) continue;
            $seen[$name] = true;
            $href = plugins_url(substr($file, strlen($base) + 1), $base . '/' . $slug . '.php');
            $ver  = (int) @filemtime($file);
            if ($ext === 'js') {
                echo '<script src="' . esc_url($href) . '?ver=' . $ver . '"></script>' . "\n";
            } else {
                echo '<link rel="stylesheet" href="' . esc_url($href) . '?ver=' . $ver . '">' . "\n";
            }
            $loaded[] = $name;
        }
    }
    af_grwp_state($loaded ? 'from-disk' : 'nothing-found', $loaded);
}, 5);

/**
 * Say what the loader did, in a way that survives the page minifier.
 *
 * The first version reported itself in an HTML comment, and the served page
 * showed no comment at all — which read as "the loader never ran" when the
 * minifier had simply stripped it. An attribute on a real tag cannot be
 * dropped that way, so the deploy check can trust it.
 */
function af_grwp_state($state, $files) {
    printf('<script id="af-grwp-loader" data-state="%s" data-files="%s"></script>' . "\n",
        esc_attr($state), esc_attr(implode(' ', (array) $files)));
}

// ---------------------------------------------------------------------------
// PHASE 25 — Brochure "Art Code" (e.g. RK 01) shown on shop card + product page
// so an incoming order can be matched back to the printed collection book.
// Value lives in product meta `_taf_art_code` (set by tools/product-importer/
// apply_art_codes.py). Products without a code simply show nothing.
// ---------------------------------------------------------------------------
function af_get_art_code($product = null) {
  if (!($product instanceof WC_Product)) { $product = af_wc_product($product); }
  if (!$product) return '';
  $code = get_post_meta($product->get_id(), '_taf_art_code', true);
  return is_string($code) ? trim($code) : '';
}

// Shop/archive card: small code line under the title. Always output the span
// (even when a product has no code) so every card reserves the same row
// height — otherwise cards with a code grow taller than their neighbours and
// the rows below (rating, price, description, buttons) fall out of alignment.
add_action('woocommerce_after_shop_loop_item_title', function() {
  global $product;
  $code = af_get_art_code($product);
  if ($code === '') {
    echo '<span class="af-art-code af-art-code--card af-art-code--empty" aria-hidden="true">&nbsp;</span>';
    return;
  }
  echo '<span class="af-art-code af-art-code--card">Art Code: '
     . esc_html($code) . '</span>';
}, 9);

// Single product page: code line in the summary, just under the title
add_action('woocommerce_single_product_summary', function() {
  global $product;
  $code = af_get_art_code($product);
  if ($code === '') return;
  // Say so, because the short-description filter below prints the same code
  // and cannot otherwise tell that this already has. Both land inside the
  // product summary — this at priority 6, the excerpt template at 20 — so the
  // page showed "Art Code: HD 15-GF" twice, once above the price and once
  // below it. The two existing dedupes could never catch each other: this one
  // echoes straight into the output buffer, and that one only inspects the
  // excerpt string it was handed.
  $GLOBALS['af_art_code_printed'] = true;
  echo '<p class="af-art-code af-art-code--single">Art Code: <strong>'
     . esc_html($code) . '</strong></p>';
}, 6);

// PHASE 25c — Art Code inside the product Description content as well, so the
// code is readable in the description tab/section (not only under the title).
add_filter('the_content', function($content) {
  if (is_admin()) return $content;
  if (get_post_type() !== 'product') return $content;
  $code = af_get_art_code(get_the_ID());
  if ($code === '') return $content;
  if (strpos($content, 'af-art-code--desc') !== false) return $content; // already added
  $line = '<p class="af-art-code af-art-code--desc">Art Code: <strong>'
        . esc_html($code) . '</strong></p>';
  return $line . $content;
}, 8);

// And at the top of the short description (summary excerpt) for themes that
// render only the excerpt on the product page.
add_filter('woocommerce_short_description', function($excerpt) {
  if (is_admin() || !function_exists('is_product') || !is_product()) return $excerpt;
  // Already shown under the title. Not deleted outright, because the quick-view
  // popup never fires woocommerce_single_product_summary — there this filter is
  // the only thing that puts the art code in front of the shopper, and the
  // latch is simply never set.
  if (!empty($GLOBALS['af_art_code_printed'])) return $excerpt;
  $code = af_get_art_code(get_the_ID());
  if ($code === '' || strpos((string)$excerpt, 'af-art-code') !== false) return $excerpt;
  return '<p class="af-art-code af-art-code--desc">Art Code: <strong>'
       . esc_html($code) . '</strong></p>' . $excerpt;
}, 8);

// Show the code on admin order line items so fulfilment can read it off an order.
add_filter('woocommerce_display_item_meta', function($html, $item, $args) {
  $pid = method_exists($item, 'get_product_id') ? $item->get_product_id() : 0;
  $code = $pid ? get_post_meta($pid, '_taf_art_code', true) : '';
  if ($code) {
    $html .= '<br><small>Art Code: ' . esc_html($code) . '</small>';
  }
  return $html;
}, 10, 3);

add_action('wp_head', function() { ?>
<style>
.af-art-code--card{display:block;margin:2px 0 4px;font-size:12px;letter-spacing:.04em;
  color:#8a6d3b;font-weight:600;text-transform:uppercase;}
.af-art-code--empty{visibility:hidden;}
.af-art-code--single{margin:.4em 0 .8em;font-size:14px;color:#6b6b6b;
  letter-spacing:.03em;}
.af-art-code--single strong{color:#8a6d3b;letter-spacing:.06em;}
.af-art-code--desc{margin:0 0 .9em;font-size:14px;color:#6b6b6b;
  letter-spacing:.03em;}
.af-art-code--desc strong{color:#8a6d3b;letter-spacing:.06em;}
</style>
<?php });

// Remove the "Themes" filter widget from the shop / category sidebar.
add_action('wp_footer', function() {
  if (!function_exists('is_shop')) return;
  if (!(is_shop() || is_product_taxonomy() || is_product_category() || is_product_tag())) return;
  ?>
<script>
(function(){
  function hideThemes(){
    var heads = document.querySelectorAll(
      '.widget-title, .widgettitle, .widget h2, .widget h3, .widget h4, aside h2, aside h3, aside h4, #secondary h2, #secondary h3, .elementor-heading-title'
    );
    heads.forEach(function(t){
      if (!/^\s*themes\s*$/i.test((t.textContent||'').trim())) return;
      var w = t.closest('.widget, li.widget, section.widget, .elementor-widget, .wp-block-group, .sidebar-widget');
      if (w) { w.style.setProperty('display','none','important'); return; }
      // fallback: hide the heading and the list that follows it
      t.style.setProperty('display','none','important');
      var nx = t.nextElementSibling; if (nx) nx.style.setProperty('display','none','important');
    });
  }
  document.addEventListener('DOMContentLoaded', hideThemes);
  window.addEventListener('load', hideThemes);
  [300,900,2000].forEach(function(d){ setTimeout(hideThemes, d); });
})();
</script>
  <?php
}, 50);

// PHASE 25b — product CARD Art Code via JS, SITE-WIDE (homepage carousels,
// related products, up-sells, search, category grids). The Postero loop and
// Elementor product widgets don't fire the standard WooCommerce card hook, so
// inject client-side. Runs on every front-end page; the query only executes on
// a LiteSpeed cache-miss, and the JS no-ops where there are no product cards.
add_action('wp_footer', function() {
  if (is_admin()) return;
  global $wpdb;
  $rows = $wpdb->get_results(
    "SELECT p.ID, p.post_name, m.meta_value
       FROM {$wpdb->postmeta} m
       JOIN {$wpdb->posts} p ON p.ID = m.post_id
      WHERE m.meta_key='_taf_art_code' AND m.meta_value<>''");
  if (!$rows) return;
  $map = array(); $slugmap = array();
  foreach ($rows as $r) {
    $map[(string)$r->ID] = $r->meta_value;
    if ($r->post_name) $slugmap[$r->post_name] = $r->meta_value;
  }
  ?>
<script>
(function(){
  var CODES = <?php echo wp_json_encode($map); ?>;
  var SLUGS = <?php echo wp_json_encode($slugmap); ?>;
  function place(card, code){
    if (card.getAttribute('data-af-code')) return;
    // Dedupe at the visual-card level: if the nearest product container
    // already shows an Art Code (from the PHP hook or a previous run), skip —
    // never add a second one.
    var box = card.closest('li.product, .product-card, .trending-card, .product, article') || card;
    if (box.querySelector('.af-art-code--card')) { card.setAttribute('data-af-code','1'); box.setAttribute('data-af-code','1'); return; }
    card.setAttribute('data-af-code','1');
    var span = document.createElement('span');
    if (code) {
      span.className = 'af-art-code af-art-code--card';
      span.textContent = 'Art Code: ' + code;
    } else {
      // No code on this product — insert an empty line of the same class so
      // it still reserves the row's height. Without this, cards that DO have
      // a code grow taller than their neighbours and every row below (rating,
      // price, description, buttons) drifts out of alignment across the row.
      span.className = 'af-art-code af-art-code--card af-art-code--empty';
      span.setAttribute('aria-hidden', 'true');
      span.innerHTML = '&nbsp;';
    }
    span.style.setProperty('display','block','important');
    // prefer to sit right under the product title, else after the card's link
    var title = card.querySelector('.woocommerce-loop-product__title, .product-title, .trending-title, h2, h3, h4, .elementor-heading-title');
    if (title && title.parentNode) { title.parentNode.insertBefore(span, title.nextSibling); return; }
    card.appendChild(span);
  }
  // Resolve the Art Code from any link in the card whose URL ends in a known
  // product slug — works for /product/<slug>/ and flat /<slug>/ permalinks.
  // SLUGS only holds real product post_names, so a false match is impossible.
  function slugFrom(card){
    var links = card.querySelectorAll('a[href]');
    for (var i=0;i<links.length;i++){
      var href = links[i].getAttribute('href') || '';
      var path = href.split('#')[0].split('?')[0].replace(/\/+$/,'');
      var seg = path.substring(path.lastIndexOf('/')+1);
      if (seg && SLUGS[seg]) return SLUGS[seg];
    }
    return null;
  }
  function inject(root){
    root = root || document;
    // Cast a wide net: standard Woo cards, the homepage "Shop by Collection"
    // slider tiles (.product-card), any element carrying a post-<id> class
    // (Elementor product widgets, carousel slides), and any add-to-cart button
    // that exposes data-product_id.
    var cards = root.querySelectorAll(
      'li.product, .product-card, .trending-card, [class*="type-product"], [class*="post-"], [data-product_id]'
    );
    cards.forEach(function(card){
      if (card.getAttribute('data-af-code')) return;
      var id = null;
      var m = (card.className||'').match(/(?:^|\s)post-(\d+)/);
      if (m) id = m[1];
      if (!id && card.hasAttribute('data-product_id')) id = card.getAttribute('data-product_id');
      if (!id) {
        var b = card.querySelector('[data-product_id]');
        if (b) id = b.getAttribute('data-product_id');
      }
      if (!id) {                       // fallback: add-to-cart URL
        var lk = card.querySelector('a[href*="add-to-cart="]');
        if (lk) { var mm = lk.href.match(/add-to-cart=(\d+)/); if (mm) id = mm[1]; }
      }
      var code = (id && CODES[id]) ? CODES[id] : slugFrom(card);   // slug fallback
      if (!code && !id) return;   // couldn't confirm this is a real product card — skip
      // climb to the nearest sensible card container so the label sits with
      // the title, not buried inside a button
      var host = card;
      if ((card.hasAttribute('data-product_id') || card.tagName === 'A')) {
        host = card.closest('li.product, .product-card, .trending-card, .product, [class*="post-"], article, .elementor-widget') || card;
      }
      place(host, code || null);
    });
  }
  function run(){ inject(document); }
  document.addEventListener('DOMContentLoaded', run);
  window.addEventListener('load', run);
  [400,1000,2200,4000].forEach(function(d){ setTimeout(run, d); });
  // catch carousels / AJAX / lazy-loaded product cards
  try {
    var obs = new MutationObserver(function(muts){
      for (var i=0;i<muts.length;i++){ if (muts[i].addedNodes && muts[i].addedNodes.length){ run(); break; } }
    });
    obs.observe(document.body, {childList:true, subtree:true});
  } catch(e){}
})();
</script>
  <?php
}, 210);

// ─────────────────────────────────────────────────────────────
// PHASE 27 — "Frame The Moment" (/frame-the-moment/)
// The Elementor version rendered a dead preview panel (an empty grey
// box) and an unstyled browser file input. Replace it with a working,
// styled builder — same proven approach as /try-on-wall/:
//   • upload your own photo (drag & drop or click)
//   • pick product type, size, frame type and frame colour
//   • LIVE realistic preview: bevelled moulding + mat + glass glare,
//     true-to-size aspect ratio, shown on a wall
//   • live price from af_pricing_config()
//   • download the preview, or send the whole spec via WhatsApp
// ─────────────────────────────────────────────────────────────
add_action('template_redirect', function () {
    if (!function_exists('is_page') || !is_page(array('frame-the-moment','frame-the-moments'))) return;

    $cfg = function_exists('af_pricing_config') ? af_pricing_config() : array(
        'sizes'  => array('2×3 ft (24×36 in)'=>1.0),
        'frames' => array('Without Frame'=>0,'Fibre Frame'=>25,'Floating Frame'=>40,'Aluminium Frame'=>55),
        'colors' => array('Black'=>0,'Silver'=>0,'Gold'=>10,'Rose Gold'=>10),
    );
    $sym  = function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '$';
    $base = 179.0;   // starting price for a customer-supplied photo print

    // Product types this service prints on
    $types = array(
        'Canvas Print'        => 0,
        'Framed Canvas'       => 20,
        'Gallery Print'       => 15,
        'Personalised Gift'   => 10,
    );
    $wa = '16104707280';

    get_header();
    af_preview_share_assets();
    ?>
    <div class="af-ftm-wrap">
      <div class="af-ftm-card">
        <a class="af-ftm-home" href="<?php echo esc_url(home_url('/')); ?>">← Back to Home</a>
        <span class="af-ftm-badge">Your Photo · Our Craft</span>
        <h1 class="af-ftm-title">Frame The Moment</h1>
        <p class="af-ftm-sub">Upload a photo you love, choose the size and frame, and see it become gallery-quality wall art — previewed instantly, before you order.</p>

        <div class="af-ftm-grid">
          <!-- ── controls ── -->
          <div class="af-ftm-panel">
            <div class="af-ftm-step"><span class="af-ftm-num">1</span><span class="af-ftm-steptitle">Upload your photo</span></div>
            <label for="ftm-file" class="af-ftm-drop" id="ftm-drop">
              <input type="file" id="ftm-file" accept="image/*" hidden>
              <span class="af-ftm-dropic">🖼️</span>
              <strong id="ftm-dropmain">Click to choose a photo</strong>
              <small id="ftm-dropsub">or drag &amp; drop it here · JPG or PNG</small>
            </label>
            <p class="af-ftm-hint" id="ftm-quality"></p>

            <div class="af-ftm-step"><span class="af-ftm-num">2</span><span class="af-ftm-steptitle">Choose your print</span></div>
            <label>Product Type</label>
            <select id="ftm-type">
              <?php foreach ($types as $t => $fee): ?>
                <option value="<?php echo esc_attr($t); ?>" data-fee="<?php echo esc_attr($fee); ?>"><?php echo esc_html($t); ?><?php if($fee>0) echo ' (+'.$sym.$fee.')'; ?></option>
              <?php endforeach; ?>
            </select>

            <label>Frame Size</label>
            <select id="ftm-size">
              <?php $ftm_sizes = function_exists('af_sizes_available') ? af_sizes_available() : array_keys($cfg['sizes']); ?>
              <?php foreach ($ftm_sizes as $i => $s): ?>
                <option value="<?php echo esc_attr($s); ?>" data-mult="<?php echo esc_attr($cfg['sizes'][$s]); ?>"<?php echo $i===0?' selected':''; ?>><?php echo esc_html($s); ?></option>
              <?php endforeach; ?>
            </select>

            <div class="af-ftm-step"><span class="af-ftm-num">3</span><span class="af-ftm-steptitle">Style the frame</span></div>
            <label>Frame Type</label>
            <select id="ftm-frame">
              <?php $ftm_def = function_exists('af_frame_default') ? af_frame_default() : array_key_first($cfg['frames']); ?>
              <?php foreach ($cfg['frames'] as $f => $fee): $oos = function_exists('af_frame_is_in_stock') && !af_frame_is_in_stock($f); ?>
                <option value="<?php echo esc_attr($f); ?>" data-fee="<?php echo esc_attr($fee); ?>"<?php echo $oos?' disabled':''; ?><?php echo $f===$ftm_def?' selected':''; ?>><?php echo esc_html($f); ?><?php if($fee>0) echo ' (+'.$sym.$fee.')'; ?><?php if($oos) echo ' — Out of stock'; ?></option>
              <?php endforeach; ?>
            </select>

            <label>Frame Colour</label>
            <div class="af-ftm-swatches" id="ftm-swatches"></div>
            <select id="ftm-color" class="af-ftm-hidden">
              <?php foreach ($cfg['colors'] as $c => $fee): ?>
                <option value="<?php echo esc_attr($c); ?>" data-fee="<?php echo esc_attr($fee); ?>"><?php echo esc_html($c); ?></option>
              <?php endforeach; ?>
            </select>

            <label>Wall Layout</label>
            <div class="af-ftm-layouts" id="ftm-layouts">
              <button type="button" class="af-ftm-lay on" data-n="1"><span>Single</span></button>
              <button type="button" class="af-ftm-lay" data-n="2"><span>2 Panels</span></button>
              <button type="button" class="af-ftm-lay" data-n="4"><span>4 Panels</span></button>
            </div>

            <!-- In the panel for the same reason as Try On Wall's: the copy in
                 the calibration overlay is only on screen while the camera is
                 aligning. See the note there. -->
            <label>Wall height</label>
            <div class="af-ftm-wallh" id="ftm-wallh">
              <button type="button" data-ft="8">8 ft</button>
              <button type="button" data-ft="9">9 ft</button>
              <button type="button" data-ft="10" class="on">10 ft</button>
            </div>

            <p class="af-sharelabel">Keep it &amp; share it</p>
            <div class="af-share">
              <button type="button" id="ftm-saveacct">💾 Save to my account</button>
            </div>
            <div class="af-share">
              <button type="button" id="ftm-share-wa" class="af-share-wa">WhatsApp</button>
              <button type="button" id="ftm-share-mail">Email</button>
              <button type="button" id="ftm-share-copy">Copy link</button>
            </div>
            <p class="af-ftm-fine">
              Saved previews live in your account.
              <a href="#" id="ftm-acctlink" style="display:none">View saved previews →</a>
            </p>
          </div>

          <!-- ── live preview ── -->
          <div class="af-ftm-stagewrap">
            <div id="ftm-stage" class="af-ftm-stage">
              <img id="ftm-wall" class="af-ftm-wall" alt="" src="<?php echo esc_url( wp_get_upload_dir()['baseurl'] . '/mockups/Radha-Krishna-Canvas-Wall-Art-Placement_Living-Room-blankwall.jpg' ); ?>">
              <div id="ftm-empty" class="af-ftm-empty">
                <span class="af-ftm-emptyic">📷</span>
                <strong>Your photo appears here</strong>
                <small>Upload a photo to see it framed on a wall, true to size.</small>
              </div>
              <video id="ftm-camv" class="af-ftm-camv" autoplay playsinline muted></video>
              <button type="button" id="ftm-camstop" class="af-ftm-camstop" style="display:none">✕ Stop camera</button>
              <!-- live-camera calibration, the same measured-scale tool the
                   Try On Wall page uses: fit the wall between ceiling and floor
                   into the rectangle and it turns green, at which point
                   pixels-per-foot is measured rather than assumed -->
              <div id="ftm-cal" class="af-ftm-cal" style="display:none">
                <div id="ftm-calbox" class="af-ftm-calbox">
                  <span class="af-ftm-calcorner tl"></span><span class="af-ftm-calcorner tr"></span>
                  <span class="af-ftm-calcorner bl"></span><span class="af-ftm-calcorner br"></span>
                </div>
                <div id="ftm-calmsg" class="af-ftm-calmsg">Step back or forward until the <strong>ceiling line</strong> touches the top edge and the <strong>floor line</strong> touches the bottom edge</div>
                <div id="ftm-calh" class="af-ftm-calh">
                  <span>Wall height</span>
                  <button type="button" data-ft="8">8 ft</button>
                  <button type="button" data-ft="9">9 ft</button>
                  <button type="button" data-ft="10" class="on">10 ft</button>
                </div>
              </div>
              <button type="button" id="ftm-recal" class="af-ftm-recal" style="display:none">📐 True scale locked · tap to recalibrate</button>
              <div id="ftm-framebox" class="af-ftm-framebox" style="display:none">
                <div id="ftm-panels" class="af-ftm-panels"></div>
              </div>
            </div>
            <p class="af-ftm-tip" id="ftm-tip">Shown true to scale on a 10&nbsp;ft wall — change the size to compare.</p>

            <!-- The room switcher and the price belong beside the preview they
                 describe. Left in the rail they pushed it 650px past the
                 bottom of the wall image, which read as a broken page. -->
            <div class="af-ftm-under">
              <div class="af-ftm-panel af-ftm-flat">
                <div class="af-ftm-step"><span class="af-ftm-num">4</span><span class="af-ftm-steptitle">Set the room</span></div>
                <label>Room Scene</label>
                <div class="af-ftm-scenes" id="ftm-scenes"></div>
                <button type="button" id="ftm-cambtn" class="af-ftm-cambtn">🎥 Use live camera <em>point it at your wall</em></button>
              </div>
              <div class="af-ftm-panel af-ftm-flat">
                <div class="af-ftm-price"><span>Your price</span><strong id="ftm-price">—</strong></div>
                <p class="af-ftm-notes">✓ Inclusive of all taxes &nbsp;·&nbsp; 📦 Free secure packaging</p>
                <div class="af-ftm-actions">
                  <button type="button" id="ftm-save" class="af-ftm-btn ghost">⤓ Save Preview</button>
                  <a href="#" id="ftm-send" class="af-ftm-btn solid" target="_blank" rel="noopener">Confirm &amp; Send</a>
                </div>
                <p class="af-ftm-fine">“Confirm &amp; Send” opens WhatsApp with your choices ready — just attach the saved preview and hit send.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <script>
    (function(){
      var $ = function(id){ return document.getElementById(id); };
      var SYM = <?php echo wp_json_encode($sym); ?>;
      var BASE = <?php echo (float) $base; ?>;
      var WA = <?php echo wp_json_encode($wa); ?>;
      var SWATCH = {'Black':'#1e1e1e','Silver':'#c0c0c0','Gold':'#d4af37','Rose Gold':'#b76e79'};
      var MAT = {
        'Black':'linear-gradient(135deg,#4a4a4a 0%,#1c1c1c 42%,#050505 58%,#333 100%)',
        'Silver':'linear-gradient(135deg,#fdfdfd 0%,#c4c4c4 42%,#8f8f8f 58%,#e6e6e6 100%)',
        'Gold':'linear-gradient(135deg,#fbe7ad 0%,#d8b445 42%,#a67c1e 58%,#f2d879 100%)',
        'Rose Gold':'linear-gradient(135deg,#f7d3c8 0%,#dca596 42%,#b76e79 58%,#efbcae 100%)'
      };
      // Mitred corner seam + polished-metal specular glint over the material
      // gradient — same treatment as /try-on-wall/'s frameBG(), matched to
      // reference photos of the actual aluminium mouldings (buffed sheen +
      // hard highlight, not a brushed/sandy texture).
      function frameBG(mat){
        var hi='rgba(255,255,255,.45)', lo='rgba(0,0,0,.5)';
        return [
          // mitred corner grooves: a lit inner edge then a dark outer edge per
          // quadrant, so each corner reads as a carved cut rather than a
          // printed line
          'linear-gradient(to bottom right, transparent calc(50% - 2.5px), '+hi+' calc(50% - 1.5px), '+lo+' calc(50% + .5px), transparent calc(50% + 2px)) left top/50% 50% no-repeat',
          'linear-gradient(to bottom left,  transparent calc(50% - 2.5px), '+hi+' calc(50% - 1.5px), '+lo+' calc(50% + .5px), transparent calc(50% + 2px)) right top/50% 50% no-repeat',
          'linear-gradient(to top right,    transparent calc(50% - 2.5px), '+hi+' calc(50% - 1.5px), '+lo+' calc(50% + .5px), transparent calc(50% + 2px)) left bottom/50% 50% no-repeat',
          'linear-gradient(to top left,     transparent calc(50% - 2.5px), '+hi+' calc(50% - 1.5px), '+lo+' calc(50% + .5px), transparent calc(50% + 2px)) right bottom/50% 50% no-repeat',
          // polished-metal glint: a narrow, hard-edged bright streak swept
          // around the ring so the top/left face catches a sharp specular
          // highlight the way buffed aluminium does, with a fainter second
          // glint opposite it
          'conic-gradient(from 300deg at 50% 50%, rgba(255,255,255,0) 0deg, rgba(255,255,255,.85) 16deg, rgba(255,255,255,0) 38deg, rgba(255,255,255,0) 175deg, rgba(255,255,255,.5) 196deg, rgba(255,255,255,0) 218deg, rgba(255,255,255,0) 360deg)',
          // broad wraparound light/shadow so each of the 4 faces reads as its
          // own lit or shaded plane (upper-left key light), pushed to higher
          // contrast so the moulding reads as metal, not painted flat colour
          'conic-gradient(from 315deg at 50% 50%, rgba(255,255,255,.5) 0deg, rgba(255,255,255,0) 95deg, rgba(0,0,0,.55) 180deg, rgba(0,0,0,0) 265deg, rgba(255,255,255,.5) 360deg)',
          mat
        ].join(',');
      }
      var photoURL = null, photoW = 0, photoH = 0, photoName = '';

      // ── real photographic rooms (same cleaned blank-wall mockups the
      //    Try-On-Wall tool uses). focusY = where the wall centre sits, so the
      //    frame always hangs on clean wall above the furniture.
      var MOCK = <?php echo wp_json_encode( wp_get_upload_dir()['baseurl'] . '/mockups/' ); ?>;
      var SCENES = [
        { name:'Living Room', file:'Radha-Krishna-Canvas-Wall-Art-Placement_Living-Room-blankwall.jpg', focus:'52% 50%', top:'40%', left:'52%' },
        { name:'Lounge',      file:'Radha-Krishna_Wall-Art_-Living-Room-blankwall.jpg',                 focus:'51% 50%', top:'38%', left:'51%' },
        { name:'Bright Loft', file:'Radha-Krishna-Abstract-Wall-Art_living-room-blankwall.jpg',         focus:'56% 50%', top:'40%', left:'56%' },
        { name:'Studio',      file:'TAF-RADHA-KRISHNA-18530-room-1-blankwall.jpg',                      focus:'48% 50%', top:'44%', left:'48%' }
      ];
      var scene = SCENES[0];
      (function(){
        var wrap = $('ftm-scenes');
        SCENES.forEach(function(s, i){
          var b = document.createElement('button');
          b.type = 'button';
          b.className = 'af-ftm-scene' + (i === 0 ? ' on' : '');
          b.style.backgroundImage = 'url("' + MOCK + s.file + '")';
          b.title = s.name;
          b.innerHTML = '<span>' + s.name + '</span>';
          b.addEventListener('click', function(){
            wrap.querySelectorAll('.af-ftm-scene').forEach(function(x){ x.classList.remove('on'); });
            b.classList.add('on');
            setScene(i);
          });
          wrap.appendChild(b);
        });
      })();
      function setScene(i){
        scene = SCENES[i];
        if (typeof stopCam === 'function') stopCam();
        var im = $('ftm-wall');
        im.src = MOCK + scene.file;
        im.style.objectPosition = scene.focus;
        var box = $('ftm-framebox');
        box.style.top  = scene.top;
        box.style.left = scene.left;
        render();
      }

      // ── colour swatches drive the hidden select ──
      (function(){
        var sel = $('ftm-color'), wrap = $('ftm-swatches');
        Array.prototype.forEach.call(sel.options, function(o, i){
          var b = document.createElement('button');
          b.type = 'button';
          b.className = 'af-ftm-sw' + (i===0 ? ' on' : '');
          b.style.background = SWATCH[o.value] || '#1a1a1a';
          b.title = o.textContent;
          b.innerHTML = '<span>' + o.value + '</span>';
          b.addEventListener('click', function(){
            wrap.querySelectorAll('.af-ftm-sw').forEach(function(x){ x.classList.remove('on'); });
            b.classList.add('on'); sel.selectedIndex = i; render();
          });
          wrap.appendChild(b);
        });
      })();

      // ── size → real feet, for true-to-scale rendering ──
      function sizeFeet(label){
        var m = String(label).match(/([\d.]+)\s*[x×]\s*([\d.]+)\s*ft/i);
        if (m) return { h: parseFloat(m[1]), w: parseFloat(m[2]) };
        var inch = String(label).match(/(\d+)\s*[x×]\s*(\d+)\s*in/i);
        if (inch) return { h: parseFloat(inch[1])/12, w: parseFloat(inch[2])/12 };
        return { h: 3, w: 2 };
      }

      function price(){
        // data-mult now carries the rate card's absolute USD price per size —
        // the size IS the price (matches af_calc_price); BASE no longer
        // multiplies into it
        var sizePrice = parseFloat($('ftm-size').selectedOptions[0].dataset.mult) || BASE;
        // no frame means no frame-finish surcharge (matches af_calc_price)
        var noFrame = $('ftm-frame').value === 'Without Frame';
        var fee  = (parseFloat($('ftm-frame').selectedOptions[0].dataset.fee) || 0)
                 + (noFrame ? 0 : (parseFloat($('ftm-color').selectedOptions[0].dataset.fee) || 0))
                 + (parseFloat($('ftm-type').selectedOptions[0].dataset.fee)  || 0);
        return Math.round((sizePrice + fee) * 100) / 100;
      }

      // ── 1 / 2 / 4 panel layouts (spec §8) ──
      var LAYOUT = 1;
      $('ftm-layouts').addEventListener('click', function(e){
        var b = e.target.closest('.af-ftm-lay'); if (!b) return;
        this.querySelectorAll('.af-ftm-lay').forEach(function(x){ x.classList.remove('on'); });
        b.classList.add('on');
        LAYOUT = parseInt(b.getAttribute('data-n'), 10) || 1;
        // the crop only depends on the print ratio, so switching layout reuses it
        render();
      });

      // Cover-crop the photo once to the frame's aspect ratio so multi-panel
      // slices are distortion-free (CSS percentage slicing would stretch).
      var cropKey = '', cropURL = null, cropImg = null;
      function ensureCrop(ratio, cb){
        var key = photoURL ? (photoURL.length + ':' + photoW + 'x' + photoH + ':' + ratio.toFixed(4)) : '';
        if (key && key === cropKey && cropURL) { cb(); return; }
        var im = new Image();
        im.onload = function(){
          var sw = im.naturalWidth, sh = im.naturalHeight;
          var tw = Math.min(sw, 1600), th = Math.round(tw * ratio);
          var cv = document.createElement('canvas'); cv.width = tw; cv.height = th;
          var cx = cv.getContext('2d');
          var s = Math.max(tw / sw, th / sh);
          cx.drawImage(im, (tw - sw * s) / 2, (th - sh * s) / 2, sw * s, sh * s);
          cropURL = cv.toDataURL('image/jpeg', 0.92);
          cropImg = new Image(); cropImg.src = cropURL;
          cropKey = key;
          cb();
        };
        im.src = photoURL;
      }

      function stylePanels(){
        var frame = $('ftm-frame').value, color = $('ftm-color').value;
        var prof, matw, matbg = '#f6f1e6';
        if (frame === 'Without Frame')          { prof = 0; matw = 0; }
        else if (frame === 'Floating Frame')    { prof = 5; matw = 6; matbg = '#161616'; }
        else if (frame === 'Aluminium Frame')   { prof = 4; matw = 7; }
        else                                    { prof = 7; matw = 9; }
        if (LAYOUT > 1) { matw = Math.max(0, matw - 4); }
        var wrap = $('ftm-panels');
        wrap.querySelectorAll('.af-ftm-pframe').forEach(function(m){
          // Same fix as /try-on-wall/: a bare percentage shrinks to a sliver on
          // small previews, too thin for the material gradient or bevel to read.
          m.style.padding      = prof ? 'max(11px, ' + prof + '%)' : '0';
          m.style.background   = prof ? frameBG(MAT[color] || MAT['Black']) : 'transparent';
          m.style.borderRadius = (frame === 'Aluminium Frame') ? '3px' : '2px';
          m.style.boxShadow    = prof
            ? 'inset 2px 2px 3px rgba(255,255,255,.5), inset -3px -3px 6px rgba(0,0,0,.6), inset 0 0 0 1px rgba(0,0,0,.3), 0 1px 3px rgba(0,0,0,.35)'
            : 'none';
        });
        wrap.querySelectorAll('.af-ftm-pmat').forEach(function(m){
          m.style.padding    = matw ? 'max(8px, ' + matw + '%)' : '0';
          m.style.background = matw ? matbg : 'transparent';
          m.style.boxShadow  = matw ? 'inset 0 0 8px rgba(0,0,0,.28)' : 'none';
        });
      }

      // The rendered box is taller than the artwork alone (moulding + mat), and a
      // 2/4-panel set has a different overall ratio again, so nudge the width
      // until the box really occupies `wantH` on the wall. Idempotent — once the
      // height matches it is a no-op — and sequenced so only the newest wins.
      var wantH = 0, scaleSeq = 0;
      function fitToWall(){
        if (!(wantH > 0)) return;
        var boxEl = $('ftm-framebox'), stage = $('ftm-stage').getBoundingClientRect();
        var targetH = wantH, seq = ++scaleSeq;
        requestAnimationFrame(function(){
          if (seq !== scaleSeq) return;
          var b = boxEl.getBoundingClientRect();
          if (b.height > 0 && b.width > 0) {
            boxEl.style.width = Math.max(70, Math.min(b.width * (targetH / b.height), stage.width * 0.92)) + 'px';
          }
        });
      }

      function render(){
        var p = price();
        $('ftm-price').textContent = SYM + p.toFixed(2);
        buildSendLink(p);
        if (!photoURL) return;

        $('ftm-empty').style.display = 'none';
        $('ftm-framebox').style.display = 'block';

        var ft = sizeFeet($('ftm-size').value);
        var ratio = ft.h / ft.w;

        // Work out the true-to-scale target BEFORE building the panels: when the
        // crop is already cached ensureCrop() calls back synchronously, and
        // fitToWall() would otherwise still be aiming at the previous size.
        var stage = $('ftm-stage').getBoundingClientRect();
        // The visitor's chosen wall height (CAL.wallFt); WALL_FT is only the
        // fallback, including on the first render before CAL is assigned.
        var WALL_FT = 10;      // fallback wall height for the room photos
        var WALL_FRAC = 0.78;  // …of which this much of the stage is wall, not floor
        var wallFt = (typeof CAL !== 'undefined' && CAL && CAL.wallFt) || WALL_FT;
        var h, w;
        if (typeof CAL !== 'undefined' && camOn && CAL.locked && CAL.pxPerFt > 0) {
          // the wall has been measured through the camera: use the real
          // pixels-per-foot rather than the assumption baked into the room
          // photos, so the print is shown at its true size and rescales as
          // the visitor walks towards or away from the wall
          h = ft.h * CAL.pxPerFt;
          w = ft.w * CAL.pxPerFt;
        } else {
          h = stage.height * WALL_FRAC * (ft.h / wallFt);
          w = h * (ft.w / ft.h);
        }
        // Cap generously, so a larger print never renders smaller than a smaller
        // one just because it hit the limit first on a narrow phone.
        var maxW = stage.width * 0.92, maxH = stage.height * 0.86;
        if (w > maxW) { h *= maxW / w; w = maxW; }
        if (h > maxH) { w *= maxH / h; h = maxH; }
        $('ftm-framebox').style.width = Math.max(70, w) + 'px';
        wantH = h;

        ensureCrop(ratio, function(){
          // (re)build the panel set
          var wrap = $('ftm-panels'); wrap.innerHTML = '';
          for (var i = 0; i < LAYOUT; i++){
            var panel = document.createElement('div'); panel.className = 'af-ftm-wpanel';
            var fr = document.createElement('div'); fr.className = 'af-ftm-pframe';
            var mt = document.createElement('div'); mt.className = 'af-ftm-pmat';
            var art = document.createElement('div'); art.className = 'af-ftm-part';
            art.style.backgroundImage = 'url("' + cropURL + '")';
            if (LAYOUT === 1){
              art.style.backgroundSize = '100% 100%';
              art.style.backgroundPosition = '0 0';
            } else {
              art.style.backgroundSize = (LAYOUT * 100) + '% 100%';
              art.style.backgroundPosition = (i / (LAYOUT - 1) * 100) + '% 0';
            }
            art.style.paddingBottom = (ratio * LAYOUT * 100) + '%';
            var glass = document.createElement('span'); glass.className = 'af-ftm-glass';
            mt.appendChild(art); mt.appendChild(glass);
            fr.appendChild(mt); panel.appendChild(fr); wrap.appendChild(panel);
          }
          stylePanels();
          fitToWall();     // panels exist now, so the box can be measured
        });

        $('ftm-tip').textContent = 'Shown true to scale on a 10 ft wall — ' +
          ft.h.toFixed(1).replace(/\.0$/,'') + '×' + ft.w.toFixed(1).replace(/\.0$/,'') + ' ft print' +
          (LAYOUT > 1
            ? ' as a ' + LAYOUT + '-panel set. Split sets are quoted on WhatsApp — the price above is for the single print.'
            : '.');
      }

      // ── live camera backdrop (spec §8) ──
      var camOn = false, camStream = null, camSeq = 0, camStarting = false;
      function stopCam(){
        camSeq++; camStarting = false;          // cancels any start still in flight
        if (camStream){ camStream.getTracks().forEach(function(t){ t.stop(); }); camStream = null; }
        camOn = false;
        calStop();
        var v = $('ftm-camv'); v.srcObject = null; v.style.display = 'none';
        $('ftm-camstop').style.display = 'none';
        $('ftm-wall').style.display = 'block';
        // re-highlight the room thumbnail that matches the photo now showing
        var idx = SCENES.indexOf(scene);
        document.querySelectorAll('#ftm-scenes .af-ftm-scene').forEach(function(x, j){ x.classList.toggle('on', j === idx); });
        camLabel();
      }
      // the button is a toggle, so it has to say which way it will go
      function camLabel(){
        $('ftm-cambtn').innerHTML = (camOn || camStarting)
          ? '⏹ Stop live camera <em>back to the room photo</em>'
          : '🎥 Use live camera <em>point it at your wall</em>';
      }
      function startCam(){
        if (camOn || camStarting) return;        // ignore impatient double clicks
        if (!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia)){
          alert('Camera is not supported on this browser.'); return;
        }
        var seq = ++camSeq; camStarting = true;
        navigator.mediaDevices.getUserMedia({ video:{ facingMode:'environment', width:{ideal:1920}, height:{ideal:1080} }, audio:false })
          .then(function(stream){
            camStarting = false;
            // the visitor picked a room while the permission prompt was open
            if (seq !== camSeq){ stream.getTracks().forEach(function(t){ t.stop(); }); return; }
            camStream = stream; camOn = true;
            stream.getTracks().forEach(function(t){
              t.addEventListener('ended', function(){ if (camStream === stream) stopCam(); });
            });
            var v = $('ftm-camv'); v.srcObject = stream; v.style.display = 'block';
            $('ftm-wall').style.display = 'none';
            $('ftm-camstop').style.display = 'block';
            document.querySelectorAll('#ftm-scenes .af-ftm-scene').forEach(function(x){ x.classList.remove('on'); });
            camLabel();
            calStart();
          })
          .catch(function(err){
            camStarting = false; camLabel();
            if (seq !== camSeq) return;
            alert(err && err.name === 'NotAllowedError' ? 'Camera permission was denied.' : 'Could not start the camera.');
          });
      }
      $('ftm-cambtn').addEventListener('click', function(){
        if (camOn || camStarting) stopCam(); else startCam();
        camLabel();
      });
      $('ftm-camstop').addEventListener('click', stopCam);
      window.addEventListener('pagehide', stopCam);

      // ── LIVE-CAMERA CALIBRATION — measured true scale ─────────────────
      // Identical in behaviour to the Try On Wall page (owner request,
      // 2026-08-18). The rectangle over the feed stands for the wall between
      // ceiling and floor. The visitor steps back or forward until both lines
      // sit on its edges; an edge detector watches the feed and flips the
      // rectangle red → amber → green, and at the lock pixels-per-foot becomes
      // MEASURED (rectangle height ÷ chosen wall height) rather than assumed.
      // After the lock the detector keeps tracking the two lines, so walking
      // closer or farther rescales the print exactly as a real one would grow
      // or shrink in view. If the lines are lost the scale simply holds.
      // The visitor's wall height, set by the Wall height buttons. Starts at the
      // height render() draws the room photos against, so the lit button and the
      // picture agree before anything is touched. See the note on AFCal.
      var CAL = { locked:false, wallFt:10, base:0, pxPerFt:0, factor:1,
                  streak:0, spanLock:0, timer:null };
      window.AFCalFTM = CAL;   // read-only view for the live verifier
      var CAL_TOP = 0.16, CAL_BOT = 0.84;   // rectangle edges, fractions of stage height
      var calCv = document.createElement('canvas');
      calCv.width = 120; calCv.height = 90;
      var calCx = calCv.getContext('2d', { willReadFrequently:true });

      // where the rectangle's edges land in detector-row space, allowing for
      // the object-fit:cover crop of the feed inside the stage
      function calRows(){
        var v = $('ftm-camv'); if (!(v.videoWidth > 0)) return null;
        var st = $('ftm-stage').getBoundingClientRect();
        var sc = Math.max(st.width / v.videoWidth, st.height / v.videoHeight);
        var cropY = (v.videoHeight - st.height / sc) / 2;
        var toRow = function(frac){
          return (cropY + (frac * st.height) / sc) / v.videoHeight * calCv.height;
        };
        return { top: toRow(CAL_TOP), bot: toRow(CAL_BOT) };
      }

      // the strongest horizontal edge in the upper and the lower half of the
      // feed — on a wall shot those are the ceiling line and the floor line
      function calSample(){
        var v = $('ftm-camv'); if (!(v.videoWidth > 0)) return null;
        var d;
        try {
          calCx.drawImage(v, 0, 0, calCv.width, calCv.height);
          d = calCx.getImageData(0, 0, calCv.width, calCv.height).data;
        } catch(e){ return null; }
        var W = calCv.width, H = calCv.height,
            x0 = Math.round(W * 0.15), x1 = Math.round(W * 0.85);
        function lum(x, y){ var i = (y * W + x) * 4; return d[i]*0.299 + d[i+1]*0.587 + d[i+2]*0.114; }
        var rows = [];
        for (var y = 1; y < H - 1; y++){
          var acc = 0;
          for (var x = x0; x < x1; x++){ acc += Math.abs(lum(x, y+1) - lum(x, y-1)); }
          rows[y] = acc;
        }
        var sorted = rows.filter(function(v2){ return v2 != null; }).sort(function(a,b){ return a-b; });
        var med = sorted[Math.floor(sorted.length/2)] || 1;
        function pick(a, b){
          var best = -1, by = 0;
          for (var y = Math.max(1, Math.round(a)); y < Math.min(H-1, Math.round(b)); y++){
            if (rows[y] > best){ best = rows[y]; by = y; }
          }
          return { y: by, ok: best > med * 2.2 };
        }
        return { top: pick(H*0.03, H*0.48), bot: pick(H*0.52, H*0.97) };
      }

      function calTick(){
        if (!camOn) return;
        var sm = calSample(); if (!sm) return;
        if (!CAL.locked){
          var r = calRows(); if (!r) return;
          var tol = calCv.height * 0.055;
          var hit = sm.top.ok && sm.bot.ok &&
                    Math.abs(sm.top.y - r.top) < tol && Math.abs(sm.bot.y - r.bot) < tol;
          $('ftm-calbox').classList.toggle('near', hit);
          CAL.streak = hit ? CAL.streak + 1 : 0;
          if (CAL.streak >= 5) calLock(sm);        // ~0.8s of steady alignment
        } else {
          if (!(sm.top.ok && sm.bot.ok)) return;   // lines lost: hold the scale
          var span = sm.bot.y - sm.top.y; if (span <= 4) return;
          var f = span / CAL.spanLock; if (!(f > 0.35 && f < 2.8)) return;
          CAL.factor += (f - CAL.factor) * 0.25;   // smooth, no jitter
          var want = CAL.base * CAL.factor;
          if (CAL.pxPerFt > 0 && Math.abs(want - CAL.pxPerFt) / CAL.pxPerFt > 0.015){
            CAL.pxPerFt = want; render();
          }
        }
      }

      function calLock(sm){
        var st = $('ftm-stage').getBoundingClientRect();
        CAL.base = ((CAL_BOT - CAL_TOP) * st.height) / CAL.wallFt;   // MEASURED px per ft
        CAL.factor = 1; CAL.pxPerFt = CAL.base;
        CAL.spanLock = sm.bot.y - sm.top.y;
        CAL.locked = true; CAL.streak = 0;
        $('ftm-calbox').classList.add('locked');
        $('ftm-calmsg').textContent = '✓ Wall locked — your print is now shown at its real size';
        if (navigator.vibrate) try { navigator.vibrate(60); } catch(e){}
        setTimeout(function(){
          if (CAL.locked){ $('ftm-cal').style.display = 'none'; $('ftm-recal').style.display = 'block'; }
        }, 1100);
        render();
      }

      function calStart(){
        CAL.locked = false; CAL.streak = 0; CAL.factor = 1;
        $('ftm-cal').style.display = 'block';
        $('ftm-recal').style.display = 'none';
        $('ftm-calbox').classList.remove('locked','near');
        $('ftm-calmsg').innerHTML = 'Step back or forward until the <strong>ceiling line</strong> touches the top edge and the <strong>floor line</strong> touches the bottom edge';
        if (!CAL.timer) CAL.timer = setInterval(calTick, 160);
      }
      function calStop(){
        if (CAL.timer){ clearInterval(CAL.timer); CAL.timer = null; }
        CAL.locked = false;
        $('ftm-cal').style.display = 'none';
        $('ftm-recal').style.display = 'none';
        render();                                  // back to the room-photo scale
      }
      $('ftm-recal').addEventListener('click', calStart);

      /** The one place the wall height changes — see the matching note in Try
       *  On Wall. Both rows land here, so the panel's and the overlay's can
       *  never disagree, and the redraw is unconditional. */
      function af_ftm_set_wall_ft( n ) {
        CAL.wallFt = parseInt( n, 10 ) || 10;
        [ 'ftm-wallh', 'ftm-calh' ].forEach(function(id){
          var row = $(id); if ( ! row ) { return; }
          row.querySelectorAll('button[data-ft]').forEach(function(x){
            x.classList.toggle('on', parseInt(x.getAttribute('data-ft'),10) === CAL.wallFt);
          });
        });
        if (CAL.locked){                           // re-derive, keep tracking
          var st = $('ftm-stage').getBoundingClientRect();
          CAL.base = ((CAL_BOT - CAL_TOP) * st.height) / CAL.wallFt;
          CAL.pxPerFt = CAL.base * CAL.factor;
        }
        render();
      }
      [ 'ftm-wallh', 'ftm-calh' ].forEach(function(id){
        var row = $(id); if ( ! row ) { return; }
        row.addEventListener('click', function(e){
          var b = e.target.closest('button[data-ft]'); if ( ! b ) { return; }
          af_ftm_set_wall_ft( b.getAttribute('data-ft') );
        });
      });

      function buildSendLink(p){
        var msg = 'Hi The Art Framer! I would like to order a *Frame The Moment* custom print:%0A%0A' +
          '• Product: ' + encodeURIComponent($('ftm-type').value) + '%0A' +
          '• Size: '    + encodeURIComponent($('ftm-size').value) + '%0A' +
          '• Frame: '   + encodeURIComponent($('ftm-frame').value) + '%0A' +
          '• Colour: '  + encodeURIComponent($('ftm-color').value) + '%0A' +
          '• Layout: '  + encodeURIComponent(LAYOUT > 1 ? LAYOUT + '-panel split set (please confirm the price for a split set)' : 'Single print') + '%0A' +
          '• Price: '   + encodeURIComponent(SYM + p.toFixed(2)) + '%0A' +
          (photoName ? ('• Photo: ' + encodeURIComponent(photoName) + '%0A') : '') +
          '%0A(I will attach my photo here.)';
        $('ftm-send').href = 'https://wa.me/' + WA + '?text=' + msg;
      }

      // ── upload: click, change and drag & drop ──
      function loadFile(file){
        if (!file || !/^image\//.test(file.type)) { alert('Please choose an image file (JPG or PNG).'); return; }
        // a different photo means the saved image is no longer what is on screen;
        // set here rather than on the picker so drag-and-drop is covered too
        savedURL = '';
        photoName = file.name;
        var r = new FileReader();
        r.onload = function(e){
          photoURL = e.target.result;
          var im = new Image();
          im.onload = function(){
            photoW = im.naturalWidth; photoH = im.naturalHeight;
            var mp = (photoW * photoH) / 1000000;
            $('ftm-quality').textContent = photoW + '×' + photoH + 'px · ' +
              (mp >= 4 ? '✓ great quality for large prints'
                       : mp >= 1.5 ? '✓ good for prints up to 3 ft'
                                   : '⚠ low resolution — best kept small');
            $('ftm-quality').className = 'af-ftm-hint' + (mp < 1.5 ? ' warn' : ' ok');
            render();
          };
          im.src = photoURL;
          $('ftm-dropmain').textContent = file.name.length > 34 ? file.name.slice(0,31) + '…' : file.name;
          $('ftm-dropsub').textContent  = 'Click to choose a different photo';
          $('ftm-drop').classList.add('has');
        };
        r.readAsDataURL(file);
      }
      $('ftm-file').addEventListener('change', function(){ loadFile(this.files && this.files[0]); });
      var drop = $('ftm-drop');
      ['dragenter','dragover'].forEach(function(ev){
        drop.addEventListener(ev, function(e){ e.preventDefault(); drop.classList.add('over'); });
      });
      ['dragleave','drop'].forEach(function(ev){
        drop.addEventListener(ev, function(e){ e.preventDefault(); drop.classList.remove('over'); });
      });
      drop.addEventListener('drop', function(e){
        if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]) loadFile(e.dataTransfer.files[0]);
      });

      ['ftm-type','ftm-size','ftm-frame','ftm-color'].forEach(function(id){
        $(id).addEventListener('change', render);
      });
      window.addEventListener('resize', render);
      // The stage stretches to match the control rail, so it can change height
      // without the window resizing — the wall maths reads that height.
      if (window.ResizeObserver) {
        new ResizeObserver(function(){ render(); }).observe($('ftm-stage'));
      }

      // ── compose the preview: the whole wall, not just the frames ──
      // done(dataURL) receives the finished PNG; used by download, save-to-account
      // and share alike. Composition is async because the crop may still be loading.
      function composePreview(done){
        if (!photoURL) { alert('Please upload a photo first.'); return; }
        var boxEl = $('ftm-framebox');
        var panels = boxEl.querySelectorAll('.af-ftm-wpanel');
        var box = boxEl.getBoundingClientRect();
        if (!panels.length || !(box.width > 0 && box.height > 0)) {
          alert('The preview is still rendering — please try again in a moment.'); return;
        }
        var stageEl = $('ftm-stage'), st = stageEl.getBoundingClientRect();
        var scale = Math.max(1, 1200 / st.width);
        var cv = document.createElement('canvas');
        cv.width = Math.round(st.width * scale); cv.height = Math.round(st.height * scale);
        var ctx = cv.getContext('2d');
        function rel(r){ return { x:(r.left-st.left)*scale, y:(r.top-st.top)*scale, w:r.width*scale, h:r.height*scale }; }
        // cover-fit the backdrop exactly as CSS object-fit/object-position do
        function cover(src, nw, nh, pos){
          if (!(nw > 0 && nh > 0)) return;
          var sc = Math.max(cv.width / nw, cv.height / nh), dw = nw * sc, dh = nh * sc;
          var m = String(pos || '50% 50%').match(/([\d.]+)%\s+([\d.]+)%/);
          var px = m ? parseFloat(m[1]) / 100 : 0.5, py = m ? parseFloat(m[2]) / 100 : 0.5;
          ctx.drawImage(src, (cv.width - dw) * px, (cv.height - dh) * py, dw, dh);
        }

        var hex = SWATCH[$('ftm-color').value] || '#1a1a1a';
        var N = panels.length || 1;
        function draw(img){
          // backdrop first: the live camera frame, or the room photo
          if (camOn) {
            var v = $('ftm-camv');
            cover(v, v.videoWidth, v.videoHeight, '50% 50%');
          } else {
            var wall = $('ftm-wall');
            cover(wall, wall.naturalWidth, wall.naturalHeight, getComputedStyle(wall).objectPosition);
          }
          // every panel's shadow first, so none falls across an adjacent frame
          ctx.save();
          ctx.shadowColor = 'rgba(0,0,0,.34)'; ctx.shadowBlur = 22 * scale;
          ctx.shadowOffsetX = 10 * scale; ctx.shadowOffsetY = 16 * scale;
          ctx.fillStyle = hex;
          panels.forEach(function(panel){
            var m = rel(panel.querySelector('.af-ftm-pframe').getBoundingClientRect());
            ctx.fillRect(m.x, m.y, m.w, m.h);
          });
          ctx.restore();
          var idx = 0;
          panels.forEach(function(panel){
            var frEl = panel.querySelector('.af-ftm-pframe');
            var mtEl = panel.querySelector('.af-ftm-pmat');
            var arEl = panel.querySelector('.af-ftm-part');
            var m = rel(frEl.getBoundingClientRect());
            ctx.fillStyle = hex; ctx.fillRect(m.x, m.y, m.w, m.h);
            var matStyle = getComputedStyle(mtEl);
            if (parseFloat(matStyle.paddingTop) > 0) {
              var mt = rel(mtEl.getBoundingClientRect());
              ctx.fillStyle = (matStyle.backgroundColor && matStyle.backgroundColor !== 'rgba(0, 0, 0, 0)') ? matStyle.backgroundColor : '#f6f1e6';
              ctx.fillRect(mt.x, mt.y, mt.w, mt.h);
            }
            var a = rel(arEl.getBoundingClientRect());
            var siw = img.width / N;
            ctx.drawImage(img, idx * siw, 0, siw, img.height, a.x, a.y, a.w, a.h);
            idx++;
          });
          var url;
          try { url = cv.toDataURL('image/png'); }
          catch (err) {
            // the room photo came from another host without CORS headers, so the
            // canvas is tainted and cannot be exported
            alert('Could not save automatically — right-click the preview and choose “Save image as…”.');
            return;
          }
          done(url);
        }
        // use the cover-cropped image so panel slices match the preview exactly
        if (cropImg && cropImg.complete && cropImg.naturalWidth > 0) { draw(cropImg); }
        else {
          var img = new Image();
          img.onload = function(){ draw(img); };
          img.onerror = function(){ alert('The preview is still rendering — please try again in a moment.'); };
          img.src = cropURL || photoURL;
        }
      }

      $('ftm-save').addEventListener('click', function(){
        composePreview(function(url){
          var link = document.createElement('a');
          link.download = 'frame-the-moment-preview.png';
          link.href = url;
          document.body.appendChild(link); link.click(); link.remove();
        });
      });

      // ── SAVE TO ACCOUNT + SHARE (spec §8) ────────────────────────────
      // Cleared whenever the configuration changes, so sharing never sends the
      // image of a preview the visitor has already moved on from.
      var savedURL = '';
      ['ftm-type','ftm-size','ftm-frame','ftm-color'].forEach(function(id){
        $(id).addEventListener('change', function(){ savedURL = ''; });
      });
      $('ftm-layouts').addEventListener('click', function(){ savedURL = ''; });
      $('ftm-file').addEventListener('change', function(){ savedURL = ''; });
      function shareTarget(){ return savedURL || location.href.split('#')[0]; }
      function shareText(){
        return 'My photo framed by The Art Framer — ' + $('ftm-type').value + ', ' + $('ftm-size').value +
               (LAYOUT > 1 ? ', ' + LAYOUT + '-panel set' : '');
      }
      $('ftm-saveacct').addEventListener('click', function(){
        var btn = this;
        composePreview(function(url){
          btn.disabled = true;
          AFPreview.save(url, {
            product: 0, source: 'frame-the-moment',
            size: $('ftm-size').value, frame: $('ftm-frame').value,
            color: $('ftm-color').value, layout: LAYOUT
          }, function(ok, msg, saved, fallback){
            btn.disabled = false;
            var link = $('ftm-acctlink');
            if (ok) {
              savedURL = saved || '';
              if (link && fallback){ link.href = fallback; link.style.display = 'inline'; }
              alert('✓ ' + msg);
            } else {
              if (fallback && !AFPreview.cfg.logged && link) {
                link.href = AFPreview.withRedirect(fallback);
                link.textContent = 'Sign in →';
                link.style.display = 'inline';
              }
              alert(msg);
            }
          });
        });
      });
      $('ftm-share-wa').addEventListener('click', function(){
        if (AFPreview.native('The Art Framer', shareText(), shareTarget())) return;
        window.open(AFPreview.whatsapp(shareText(), shareTarget()), '_blank', 'noopener');
      });
      $('ftm-share-mail').addEventListener('click', function(){
        location.href = AFPreview.email('My framed photo — The Art Framer', shareText(), shareTarget());
      });
      $('ftm-share-copy').addEventListener('click', function(){
        AFPreview.copy(shareTarget(), function(ok){
          alert(ok ? '✓ Link copied' : 'Could not copy — please copy the address bar link.');
        });
      });

      render();
    })();
    </script>

    <style>
    .af-ftm-wrap{background:linear-gradient(180deg,#f6f1e6 0%,#efe7d6 100%);padding:44px 16px 70px;}
    .af-ftm-card{max-width:1300px;margin:0 auto;background:#fffdf8;border:1px solid #efe6d2;border-radius:24px;
      padding:36px 30px 34px;position:relative;box-shadow:0 24px 60px rgba(70,54,26,.10);}
    .af-ftm-home{position:absolute;top:26px;left:26px;background:#1a1a1a;color:#fff;text-decoration:none;font-weight:700;
      font-size:12.5px;padding:10px 16px;border-radius:9px;transition:background .2s;}
    .af-ftm-home:hover{background:#c9a84c;color:#fff;}
    .af-ftm-badge{position:absolute;top:26px;right:26px;background:#f3ead2;color:#a8801f;font-weight:800;font-size:11px;
      letter-spacing:.08em;text-transform:uppercase;padding:8px 14px;border-radius:999px;border:1px solid #e6d7ad;}
    .af-ftm-title{text-align:center;font-size:46px;font-weight:800;color:#1a1a1a;margin:16px 0 10px;letter-spacing:-.5px;
      font-family:'Playfair Display',Georgia,serif;}
    .af-ftm-sub{text-align:center;color:#6b6250;font-size:15.5px;max-width:660px;margin:0 auto 26px;line-height:1.65;}
    .af-ftm-grid{display:grid;grid-template-columns:380px 1fr;gap:30px;align-items:start;}
    .af-ftm-panel{background:#fff;border:1px solid #ece4cf;border-radius:18px;padding:24px;display:flex;flex-direction:column;
      gap:6px;box-shadow:0 8px 26px rgba(70,54,26,.05);position:sticky;top:20px;}
    .af-ftm-step{display:flex;align-items:center;gap:10px;margin:18px 0 6px;}
    .af-ftm-step:first-child{margin-top:0;}
    .af-ftm-num{width:24px;height:24px;border-radius:50%;background:#c9a84c;color:#fff;font-weight:800;font-size:13px;
      display:flex;align-items:center;justify-content:center;flex:0 0 auto;}
    .af-ftm-steptitle{font-size:14px;font-weight:800;color:#1a1a1a;}
    .af-ftm-panel label{font-size:11.5px;font-weight:800;color:#6b6250;text-transform:uppercase;letter-spacing:.05em;margin-top:12px;}
    .af-ftm-panel select{width:100%;padding:12px;border:1px solid #e2d9c4;border-radius:10px;font-size:14px;background:#fffdf8;
      color:#1a1a1a;cursor:pointer;transition:border-color .15s;}
    .af-ftm-panel select:focus{outline:none;border-color:#c9a84c;}
    .af-ftm-hidden{display:none !important;}
    /* upload drop zone */
    .af-ftm-drop{display:flex !important;flex-direction:column;align-items:center;justify-content:center;gap:5px;
      padding:26px 16px;border:2px dashed #c9a84c;border-radius:14px;background:#fdfaf2;cursor:pointer;text-align:center;
      transition:background .15s,border-color .15s;margin-top:2px;text-transform:none !important;letter-spacing:0 !important;}
    .af-ftm-drop:hover,.af-ftm-drop.over{background:#faf3e0;border-color:#a8872e;}
    .af-ftm-drop.has{border-style:solid;background:#f7fdf5;border-color:#5aa85a;}
    .af-ftm-dropic{font-size:30px;line-height:1;}
    .af-ftm-drop strong{font-size:14px;font-weight:800;color:#1a1a1a;text-transform:none;letter-spacing:0;}
    .af-ftm-drop small{font-size:12px;color:#8a8170;}
    .af-ftm-hint{margin:8px 0 0;font-size:12px;font-weight:600;min-height:16px;}
    .af-ftm-hint.ok{color:#2e7d32;} .af-ftm-hint.warn{color:#c07a12;}
    /* swatches */
    .af-ftm-swatches{display:flex;gap:9px;flex-wrap:wrap;margin-top:6px;}
    .af-ftm-sw{position:relative;width:34px;height:34px;border-radius:50%;border:2px solid #fff;
      box-shadow:0 0 0 1px #d8cdb3,0 2px 5px rgba(0,0,0,.15);cursor:pointer;padding:0;transition:transform .12s;}
    .af-ftm-sw:hover{transform:scale(1.08);}
    .af-ftm-sw.on{box-shadow:0 0 0 2px #c9a84c,0 2px 6px rgba(0,0,0,.2);}
    .af-ftm-sw span{position:absolute;bottom:calc(100% + 6px);left:50%;transform:translateX(-50%);background:#1a1a1a;color:#fff;
      font-size:10.5px;font-weight:700;padding:4px 8px;border-radius:6px;white-space:nowrap;opacity:0;pointer-events:none;transition:opacity .15s;}
    .af-ftm-sw:hover span{opacity:1;}
    /* price + actions */
    .af-ftm-price{display:flex;align-items:center;justify-content:space-between;margin-top:20px;padding-top:16px;
      border-top:1px solid #f0e8d6;font-size:13px;color:#6b6250;font-weight:700;text-transform:uppercase;letter-spacing:.04em;}
    .af-ftm-price strong{font-size:26px;color:#1a1a1a;letter-spacing:-.5px;}
    .af-ftm-notes{margin:6px 0 0;font-size:12.5px;color:#256d2c;font-weight:600;}
    .af-ftm-actions{display:flex;gap:10px;margin-top:16px;}
    .af-ftm-btn{flex:1 1 0;min-width:0;box-sizing:border-box;height:46px;display:flex;align-items:center;justify-content:center;
      gap:6px;padding:0 12px;border-radius:11px;font-weight:700 !important;font-size:13px !important;line-height:1 !important;
      white-space:nowrap !important;text-transform:none !important;letter-spacing:0 !important;cursor:pointer;text-decoration:none;
      border:none;transition:transform .12s,background .2s;}
    .af-ftm-btn:hover{transform:translateY(-1px);}
    .af-ftm-btn.ghost{background:#f2ecdd;color:#5a5140;}
    .af-ftm-btn.ghost:hover{background:#e9e0cc;}
    .af-ftm-btn.solid{background:#25a366;color:#fff;box-shadow:0 6px 16px rgba(37,163,102,.30);}
    .af-ftm-btn.solid:hover{background:#1e8b56;color:#fff;}
    .af-ftm-fine{margin:10px 0 0;font-size:11.5px;color:#8a8170;line-height:1.5;}
    /* stage */
    /* Same rule as Try-On-Wall: the preview column matches the rail's height and
       the wall absorbs the difference, so no band of empty card is left over. */
    .af-ftm-stagewrap{position:relative;display:flex;flex-direction:column;align-self:stretch;min-height:0;}
    .af-ftm-stage{position:relative;width:100%;flex:1 1 auto;min-height:470px;max-height:760px;border-radius:18px;overflow:hidden;display:flex;
      align-items:center;justify-content:center;box-shadow:inset 0 0 60px rgba(0,0,0,.10);background:#e9e4d8;}
    /* the two cards under the wall — equal height so neither leaves a void */
    .af-ftm-under{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:16px;align-items:stretch;}
    .af-ftm-flat{position:static;top:auto;}
    .af-ftm-under .af-ftm-scenes{grid-template-columns:repeat(4,1fr);}
    .af-ftm-under .af-ftm-scene{height:74px;}
    .af-ftm-under .af-ftm-cambtn{margin-top:14px;padding:15px 12px;}
    .af-ftm-under .af-ftm-price{margin-top:0;padding-top:0;border-top:none;}
    /* real photographic room behind the frame */
    .af-ftm-wall{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:52% 50%;display:block;}
    .af-ftm-empty{position:relative;z-index:3;display:flex;flex-direction:column;align-items:center;gap:8px;text-align:center;
      background:rgba(255,255,255,.86);border-radius:14px;padding:26px 30px;max-width:340px;backdrop-filter:blur(2px);}
    .af-ftm-emptyic{font-size:34px;}
    .af-ftm-empty strong{font-size:15px;color:#1a1a1a;}
    .af-ftm-empty small{font-size:13px;color:#8a8170;line-height:1.55;}
    .af-ftm-framebox{position:absolute;top:40%;left:52%;transform:translate(-50%,-50%);z-index:5;
      filter:drop-shadow(10px 16px 22px rgba(0,0,0,.34));}
    /* room scene thumbnails */
    .af-ftm-scenes{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:6px;}
    .af-ftm-scene{position:relative;height:52px;border-radius:10px;border:2px solid #e2d9c4;background-size:cover;
      background-position:center;cursor:pointer;overflow:hidden;padding:0;transition:border-color .15s,transform .12s;}
    .af-ftm-scene:hover{transform:translateY(-1px);}
    .af-ftm-scene.on{border-color:#c9a84c;box-shadow:0 0 0 1px #c9a84c;}
    .af-ftm-scene span{position:absolute;left:0;right:0;bottom:0;background:linear-gradient(transparent,rgba(0,0,0,.6));
      color:#fff;font-size:10.5px;font-weight:700;padding:8px 6px 4px;text-align:center;}
    .af-ftm-moulding{position:relative;box-sizing:border-box;}
    .af-ftm-mat{position:relative;box-sizing:border-box;}
    .af-ftm-art{width:100%;background-size:cover;background-position:center;background-repeat:no-repeat;}
    /* multi-panel layouts (1 / 2 / 4 prints on the wall) */
    .af-ftm-panels{display:flex;gap:2.5%;align-items:flex-start;}
    .af-ftm-wpanel{flex:1 1 0;min-width:0;}
    .af-ftm-pframe{position:relative;box-sizing:border-box;}
    .af-ftm-pmat{position:relative;box-sizing:border-box;}
    .af-ftm-part{width:100%;background-repeat:no-repeat;}
    /* layout chips */
    .af-ftm-layouts{display:flex;gap:8px;margin-top:6px;}
    /* Same as the Try On Wall chips: the label alone, centred, with the
       selected state carried by the border and the label colour. See the note
       on .af-tow-lay. */
    .af-ftm-lay{flex:1;display:flex;align-items:center;justify-content:center;height:44px;padding:7px 8px;
      border:2px solid #e2d9c4;border-radius:10px;background:#fffdf8;cursor:pointer;transition:border-color .15s;}
    .af-ftm-lay span{font-size:11px;font-weight:700;color:#8a8170;text-align:center;letter-spacing:.02em;}
    .af-ftm-lay.on{border-color:#c9a84c;box-shadow:0 0 0 1px #c9a84c;}
    .af-ftm-lay.on span{color:#8a6d3b;}
    /* Wall height, in the panel — same chip as the layout row above it, and
       held to one line for the reason set out on .af-tow-wallh. */
    .af-ftm-wallh{display:flex;gap:8px;margin-top:6px;}
    .af-ftm-wallh button{flex:1 1 0;min-width:0;box-sizing:border-box;
      display:flex;flex-direction:row;align-items:center;justify-content:center;
      white-space:nowrap;line-height:1;height:38px;padding:0 4px;
      border:2px solid #e2d9c4;border-radius:10px;background:#fffdf8;
      font-size:11px;font-weight:700;color:#8a8170;letter-spacing:.02em;cursor:pointer;transition:border-color .15s;}
    .af-ftm-wallh button.on{border-color:#c9a84c;box-shadow:0 0 0 1px #c9a84c;color:#8a6d3b;}
    /* live camera backdrop */
    .af-ftm-camv{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:none;z-index:1;}
    /* calibration overlay — same measured-scale tool as Try On Wall */
    .af-ftm-cal{position:absolute;inset:0;z-index:7;pointer-events:none;}
    .af-ftm-calbox{position:absolute;left:9%;right:9%;top:16%;bottom:16%;border:3px solid #e04338;border-radius:6px;
      box-shadow:0 0 0 2000px rgba(0,0,0,.16);transition:border-color .25s,box-shadow .3s;}
    .af-ftm-calbox.near{border-color:#e8b400;}
    .af-ftm-calbox.locked{border-color:#2fae52;box-shadow:0 0 0 2000px rgba(0,0,0,0),0 0 26px rgba(47,174,82,.85);}
    .af-ftm-calcorner{position:absolute;width:20px;height:20px;border-color:inherit;border-style:solid;border-width:0;}
    .af-ftm-calcorner.tl{top:-3px;left:-3px;border-top-width:6px;border-left-width:6px;border-top-left-radius:6px;}
    .af-ftm-calcorner.tr{top:-3px;right:-3px;border-top-width:6px;border-right-width:6px;border-top-right-radius:6px;}
    .af-ftm-calcorner.bl{bottom:-3px;left:-3px;border-bottom-width:6px;border-left-width:6px;border-bottom-left-radius:6px;}
    .af-ftm-calcorner.br{bottom:-3px;right:-3px;border-bottom-width:6px;border-right-width:6px;border-bottom-right-radius:6px;}
    .af-ftm-calmsg{position:absolute;left:50%;top:4%;transform:translateX(-50%);background:rgba(16,16,16,.82);color:#fff;
      font-size:12.5px;line-height:1.45;padding:8px 14px;border-radius:9px;max-width:78%;text-align:center;}
    .af-ftm-calmsg strong{color:#efd48d;}
    .af-ftm-calh{position:absolute;left:50%;bottom:4%;transform:translateX(-50%);display:flex;gap:7px;align-items:center;
      background:rgba(16,16,16,.82);border-radius:999px;padding:6px 10px;pointer-events:auto;}
    .af-ftm-calh span{color:#cbc2ac;font-size:11px;font-weight:700;text-transform:none;letter-spacing:0;margin:0;}
    .af-ftm-calh button{background:transparent;border:1px solid #6f6a5e;color:#fff;font-size:11.5px;font-weight:700;
      border-radius:999px;padding:4px 10px;cursor:pointer;transition:background .15s;}
    .af-ftm-calh button.on{background:#c9a84c;border-color:#c9a84c;color:#1a1a1a;}
    .af-ftm-recal{position:absolute;top:12px;left:12px;z-index:8;background:rgba(24,110,52,.92);color:#fff;border:none;
      border-radius:999px;padding:8px 13px;font-size:12px;font-weight:700;cursor:pointer;}
    .af-ftm-cambtn{display:flex;flex-direction:column;align-items:center;gap:2px;width:100%;margin-top:8px;padding:11px 12px;
      border:2px solid #1a1a1a;border-radius:11px;background:#1a1a1a;color:#fff;font-size:12.5px;font-weight:700;cursor:pointer;
      text-transform:none;letter-spacing:0;transition:background .2s;}
    .af-ftm-cambtn:hover{background:#000;}
    .af-ftm-cambtn em{font-style:normal;font-weight:500;font-size:10.5px;color:#cbc2ac;}
    .af-ftm-camstop{position:absolute;top:12px;right:12px;z-index:8;background:rgba(20,20,20,.85);color:#fff;border:none;
      border-radius:999px;padding:8px 14px;font-size:12px;font-weight:700;cursor:pointer;}
    .af-ftm-glass{position:absolute;inset:0;pointer-events:none;
      background:linear-gradient(125deg,rgba(255,255,255,.22) 0%,rgba(255,255,255,.05) 22%,rgba(255,255,255,0) 42%);}
    .af-ftm-tip{text-align:center;color:#8a8170;font-size:13px;margin:14px 0 0;}
    @media(max-width:1180px){
      .af-ftm-grid{grid-template-columns:340px 1fr;gap:24px;}
      .af-ftm-under{gap:14px;}
    }
    @media(max-width:1000px){
      .af-ftm-under{grid-template-columns:1fr;}
      .af-ftm-under .af-ftm-scenes{grid-template-columns:1fr 1fr;}
      .af-ftm-under .af-ftm-scene{height:56px;}
    }
    @media(max-width:900px){
      .af-ftm-grid{grid-template-columns:1fr;gap:16px;}
      .af-ftm-panel{position:static;}
      .af-ftm-stagewrap{align-self:auto;}
      .af-ftm-stage{flex:0 0 auto;height:440px;min-height:0;max-height:none;}
      .af-ftm-title{font-size:34px;}
      .af-ftm-card{padding:64px 20px 26px;}
    }
    @media(max-width:480px){
      .af-ftm-title{font-size:27px;}
      .af-ftm-stage{height:360px;}
      .af-ftm-badge{display:none;}
    }
    </style>
    <?php
    get_footer();
    exit;
}, 1);


/* ================================================================
 * PHASE 29 — CART, CHECKOUT & PAYMENTS  (requirements §11)
 * Abandoned cart recovery, address validation, fraud screening and
 * invoice / packing-slip generation. Kept in inc/ so this file does
 * not grow another few thousand lines.
 * ================================================================ */
foreach (array('artcode-book', 'abandoned-cart', 'address-validation', 'fraud-detection', 'documents', 'marketplace', 'shipping', 'shipping-distance', 'kit-options', 'deals-page', 'gold-foil', 'goldfoil-collection', 'goldfoil-autosync', 'reels', 'cookie-consent', 'masonry', 'orientation-filter', 'blog-hub', 'analytics', 'chatbot', 'sales-count', 'review-enhancements', 'artist-profiles', 'banner-links', 'about-page', 'image-guard', 'fatal-recorder', 'sku', 'goldfoil-promo', 'promo-hide') as $af_mod) {
    $af_path = get_stylesheet_directory() . '/inc/' . $af_mod . '.php';
    if (file_exists($af_path)) require_once $af_path;
}
unset($af_mod, $af_path);

/* ================================================================
 * PHASE 28 — SAVE PREVIEW TO ACCOUNT + SHARE  (spec §8, and the
 * "Save and share preview" line of the §7 AR block)
 *
 * The AR pages can compose a preview of the artwork on a wall. This
 * stores that composition against the customer's account so they can
 * come back to it, and gives them WhatsApp / Email / copy-link share
 * targets. Guests can still share their configuration — the link
 * carries size / frame / colour, which the product page pre-selects.
 * ================================================================ */

// Saved previews live as a private CPT owned by the customer, with the
// composed PNG as its featured image.
add_action('init', function() {
    register_post_type('af_preview', array(
        'labels' => array(
            'name'          => 'Saved Previews',
            'singular_name' => 'Saved Preview',
        ),
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => 'edit.php?post_type=product',
        'capability_type'     => 'post',
        'map_meta_cap'        => true,
        'supports'            => array('title', 'author', 'thumbnail'),
        'exclude_from_search' => true,
        'has_archive'         => false,
        'rewrite'             => false,
    ));
    add_rewrite_endpoint('saved-previews', EP_ROOT | EP_PAGES);
}, 8);

// A new endpoint only resolves once rewrites are rebuilt. Deploy does this, but
// flush once per version here too so the account link can never quietly 404.
add_action('wp_loaded', function() {
    $ver = '1';
    if (get_option('af_preview_rewrite_ver') === $ver) return;
    flush_rewrite_rules(false);
    update_option('af_preview_rewrite_ver', $ver);
});

function af_preview_max_per_user() { return 60; }

// Store a composed preview sent from the AR pages.
function af_save_preview_handler() {
    check_ajax_referer('af_preview', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(array('code' => 'login', 'message' => 'Please sign in to save previews to your account.'), 401);
    }
    $uid = get_current_user_id();

    $raw = isset($_POST['image']) ? wp_unslash($_POST['image']) : '';
    if (!preg_match('#^data:image/(png|jpeg);base64,#i', $raw, $m)) {
        wp_send_json_error(array('message' => 'That preview could not be read.'));
    }
    $base64 = substr($raw, strpos($raw, ',') + 1);
    if (strlen($base64) > 12 * 1024 * 1024) {
        wp_send_json_error(array('message' => 'That preview is too large to save.'));
    }
    $bin = base64_decode($base64, true);
    if ($bin === false || strlen($bin) < 512) {
        wp_send_json_error(array('message' => 'That preview could not be read.'));
    }
    // Confirm the bytes really are an image, not just a well-formed data URL
    $probe = @getimagesizefromstring($bin);
    if (!$probe || !in_array($probe[2], array(IMAGETYPE_PNG, IMAGETYPE_JPEG), true)) {
        wp_send_json_error(array('message' => 'That preview could not be read.'));
    }
    // A few compressed megabytes can still decode to hundreds of megapixels and
    // exhaust the worker when thumbnails are generated, so bound the raster too.
    $w = (int) $probe[0]; $h = (int) $probe[1];
    if ($w < 1 || $h < 1 || $w > 6000 || $h > 6000 || ($w * $h) > 30000000) {
        wp_send_json_error(array('message' => 'That preview is too large to save.'));
    }
    // Trust the decoded bytes for the type, never the client's data-URL header
    $ext  = ($probe[2] === IMAGETYPE_PNG) ? 'png' : 'jpg';
    $mime = ($probe[2] === IMAGETYPE_PNG) ? 'image/png' : 'image/jpeg';

    $count = (int) count_user_posts($uid, 'af_preview', true);
    if ($count >= af_preview_max_per_user()) {
        wp_send_json_error(array('message' => 'You have reached ' . af_preview_max_per_user() . ' saved previews — please delete one first.'));
    }

    // Saving is cheap for the customer and expensive for us, so cap the rate as
    // well as the total — a stuck script should not be able to fill the disk.
    $bucket = (array) get_transient('af_preview_rate_' . $uid);
    $bucket = array_values(array_filter($bucket, function($t) { return $t > (time() - 600); }));
    if (count($bucket) >= 12) {
        wp_send_json_error(array('message' => 'That is a lot of previews at once — please wait a few minutes and try again.'), 429);
    }
    $bucket[] = time();
    set_transient('af_preview_rate_' . $uid, $bucket, 900);

    $pid = isset($_POST['product']) ? absint($_POST['product']) : 0;
    // only a real, published product — otherwise a crafted id would pull the
    // title of someone's draft into this customer's account page
    if ($pid && (get_post_type($pid) !== 'product' || get_post_status($pid) !== 'publish')) {
        $pid = 0;
    }
    $source = isset($_POST['source']) && $_POST['source'] === 'frame-the-moment' ? 'frame-the-moment' : 'try-on-wall';
    $size   = isset($_POST['size'])   ? sanitize_text_field(wp_unslash($_POST['size']))   : '';
    $frame  = isset($_POST['frame'])  ? sanitize_text_field(wp_unslash($_POST['frame']))  : '';
    $color  = isset($_POST['color'])  ? sanitize_text_field(wp_unslash($_POST['color']))  : '';
    $layout = isset($_POST['layout']) ? max(1, absint($_POST['layout'])) : 1;
    $title  = $pid ? get_the_title($pid) : 'My framed photo';
    if ($title === '') $title = 'Wall preview';

    // The uploads folder is public — it has to be, since sharing hands someone a
    // link to this image. So the filename carries the secrecy: a random suffix
    // makes previews unguessable rather than walkable by user id and timestamp.
    $file = wp_upload_bits('af-preview-' . $uid . '-' . wp_generate_password(24, false, false) . '.' . $ext, null, $bin);
    if (!empty($file['error'])) {
        wp_send_json_error(array('message' => 'Could not save that preview right now.'));
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    $att_id = wp_insert_attachment(array(
        'post_mime_type' => $mime,
        'post_title'     => $title . ' — wall preview',
        // NOT 'inherit'. An orphaned inherit attachment is public: it renders at
        // ?attachment_id=N and, worse, the anonymous /wp-json/wp/v2/media listing
        // hands out every attachment's source_url. Attachment ids are sequential,
        // so that walks straight past the unguessable filename — and a Frame The
        // Moment preview contains the customer's own photograph. 'private' keeps
        // it out of both, while the direct file URL still works, which is all the
        // share links and the account thumbnails actually use.
        'post_status'    => 'private',
        'post_author'    => $uid,
    ), $file['file']);
    if (is_wp_error($att_id) || !$att_id) {
        @unlink($file['file']);
        wp_send_json_error(array('message' => 'Could not save that preview right now.'));
    }
    wp_update_attachment_metadata($att_id, wp_generate_attachment_metadata($att_id, $file['file']));

    $post_id = wp_insert_post(array(
        'post_type'   => 'af_preview',
        'post_status' => 'publish',
        'post_title'  => $title,
        'post_author' => $uid,
    ), true);
    if (is_wp_error($post_id)) {
        wp_delete_attachment($att_id, true);
        wp_send_json_error(array('message' => 'Could not save that preview right now.'));
    }
    set_post_thumbnail($post_id, $att_id);
    update_post_meta($post_id, '_af_preview_product', $pid);
    update_post_meta($post_id, '_af_preview_source',  $source);
    update_post_meta($post_id, '_af_preview_size',    $size);
    update_post_meta($post_id, '_af_preview_frame',   $frame);
    update_post_meta($post_id, '_af_preview_color',   $color);
    update_post_meta($post_id, '_af_preview_layout',  $layout);

    wp_send_json_success(array(
        'message' => 'Saved to your account.',
        'url'     => wp_get_attachment_url($att_id),
        'account' => af_preview_account_url(),
    ));
}
add_action('wp_ajax_af_save_preview',        'af_save_preview_handler');
add_action('wp_ajax_nopriv_af_save_preview', 'af_save_preview_handler');

/** Where the Saved Previews list lives (falls back if WooCommerce is off). */
function af_preview_account_url() {
    if (function_exists('wc_get_endpoint_url') && function_exists('wc_get_page_permalink')) {
        return wc_get_endpoint_url('saved-previews', '', wc_get_page_permalink('myaccount'));
    }
    return home_url('/my-account/');
}

// Delete one of my own saved previews (posted from the account page).
add_action('template_redirect', function() {
    if (empty($_POST['af_preview_delete'])) return;
    $id = absint($_POST['af_preview_delete']);
    if (!$id || !is_user_logged_in()) return;

    // Say so rather than appearing to work — an expired nonce is common on a
    // page left open, and a silent no-op looks like the delete button is broken.
    if (!isset($_POST['af_preview_nonce']) || !wp_verify_nonce(wp_unslash($_POST['af_preview_nonce']), 'af_preview_delete_' . $id)) {
        wp_safe_redirect(add_query_arg('af_deleted', 'expired', af_preview_account_url()));
        exit;
    }
    $post = get_post($id);
    if (!$post || $post->post_type !== 'af_preview' || (int) $post->post_author !== get_current_user_id()) {
        wp_safe_redirect(add_query_arg('af_deleted', 'denied', af_preview_account_url()));
        exit;
    }
    $thumb = get_post_thumbnail_id($id);
    if ($thumb) wp_delete_attachment($thumb, true);
    wp_delete_post($id, true);
    wp_safe_redirect(add_query_arg('af_deleted', '1', af_preview_account_url()));
    exit;
});

// ── My Account → Saved Previews ──────────────────────────────────
add_filter('woocommerce_account_menu_items', function($items) {
    $new = array();
    foreach ($items as $k => $v) {
        $new[$k] = $v;
        if ($k === 'orders') $new['saved-previews'] = 'Saved Previews';
    }
    if (!isset($new['saved-previews'])) $new['saved-previews'] = 'Saved Previews';
    return $new;
}, 20);

// Track Order and Help live in My Account rather than the top utility bar:
// Track Order directly under Orders, Help directly under Account details.
// Runs last so each lands immediately after its anchor row, and both point at
// standalone pages rather than account endpoints.
add_filter('woocommerce_account_menu_items', function($items) {
    $after = array(
        'orders'       => array('af-track-order', 'Track Order'),
        'edit-account' => array('af-help',        'Help'),
    );
    $new = array();
    foreach ($items as $k => $v) {
        $new[$k] = $v;
        if (isset($after[$k]) && !isset($items[$after[$k][0]])) {
            $new[$after[$k][0]] = $after[$k][1];
        }
    }
    // Anchor row missing (menus vary) — still surface the entry.
    foreach ($after as $add) {
        if (!isset($new[$add[0]])) $new[$add[0]] = $add[1];
    }
    return $new;
}, 30);

add_filter('woocommerce_get_endpoint_url', function($url, $endpoint) {
    if ($endpoint === 'af-track-order') return home_url('/track-your-order/');
    if ($endpoint === 'af-help')        return home_url('/help-support/');
    return $url;
}, 10, 2);

// The header's "My Account" dropdown is rendered by the theme, not by
// woocommerce_account_menu_items, so the filter above never reaches it. Insert
// the same entries into that dropdown client-side, each cloned from the row it
// follows so they inherit the dropdown's own styling.
add_action('wp_footer', function() {
    if (is_admin()) return;
    ?>
<script>
(function(){
  var ROWS = [
    { cls: 'af-track-order-item', label: 'Track Order',
      url: <?php echo wp_json_encode(home_url('/track-your-order/')); ?>,
      match: function(t, h){ return t === 'orders' || t === 'my orders'
                                    || /\/my-account\/orders\/?$/.test(h); } },
    { cls: 'af-help-item', label: 'Help',
      url: <?php echo wp_json_encode(home_url('/help-support/')); ?>,
      match: function(t, h){ return t === 'account details' || t === 'account detail'
                                    || /\/my-account\/edit-account\/?$/.test(h); } }
  ];
  function tidy(u){ return (u || '').split('?')[0].split('#')[0].replace(/\/+$/, ''); }

  function hasRow(list, row){
    var target = tidy(row.url);
    var want   = row.label.toLowerCase();
    var links  = list.querySelectorAll('a[href]');
    for (var k = 0; k < links.length; k++) {
      if (tidy(links[k].getAttribute('href')) === target) return true;
      if ((links[k].textContent || '').replace(/\s+/g, ' ').trim().toLowerCase() === want) return true;
    }
    return false;
  }

  function add(){
    var anchors = document.querySelectorAll('a[href]');
    for (var i = 0; i < anchors.length; i++) {
      var a = anchors[i];
      var t = (a.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
      var h = (a.getAttribute('href') || '').split('?')[0];
      for (var r = 0; r < ROWS.length; r++) {
        var row = ROWS[r];
        if (!row.match(t, h)) continue;
        var li = a.closest('li');
        if (!li || !li.parentNode) continue;
        var list = li.parentNode;
        // Already there? This looked only for OUR clone's class, so it never
        // saw the copy the PHP menu filter adds on the My Account page under
        // its own class — and that page listed Track Order and Help twice
        // (owner screenshot, 2026-08-21). Test what a person would instead:
        // does this menu already link to that page, or already carry that
        // label, however it got there?
        if (hasRow(list, row)) continue;
        // Only a dropdown/account menu — must sit alongside other account rows.
        var all = (list.textContent || '').toLowerCase();
        if (all.indexOf('log out') === -1 && all.indexOf('logout') === -1
            && all.indexOf('dashboard') === -1 && all.indexOf('account details') === -1) continue;
        var clone = li.cloneNode(true);
        clone.className = (li.className || '') + ' ' + row.cls;
        clone.removeAttribute('id');
        var link = clone.querySelector('a');
        if (!link) continue;
        link.setAttribute('href', row.url);
        // replace the label text but keep any icon markup the theme adds
        var replaced = false;
        (function walk(node){
          for (var n = 0; n < node.childNodes.length; n++) {
            var c = node.childNodes[n];
            if (c.nodeType === 3 && c.nodeValue.trim()) {
              if (!replaced) { c.nodeValue = row.label; replaced = true; }
              else { c.nodeValue = ''; }
            } else if (c.nodeType === 1) { walk(c); }
          }
        })(link);
        if (!replaced) link.textContent = row.label;
        li.parentNode.insertBefore(clone, li.nextSibling);
      }
    }
  }
  document.addEventListener('DOMContentLoaded', add);
  window.addEventListener('load', add);
  [400, 1200, 2500].forEach(function(d){ setTimeout(add, d); });
  try {
    new MutationObserver(function(m){
      for (var i = 0; i < m.length; i++) {
        if (m[i].addedNodes && m[i].addedNodes.length) { add(); break; }
      }
    }).observe(document.body, { childList: true, subtree: true });
  } catch (e) {}
})();
</script>
<?php }, 65);

add_action('woocommerce_account_saved-previews_endpoint', function() {
    // WP_Query drops an author clause of 0, which would list everyone's previews
    if (!is_user_logged_in()) { echo '<p>Please sign in to see your saved previews.</p>'; return; }
    $previews = get_posts(array(
        'post_type'      => 'af_preview',
        'author'         => get_current_user_id(),
        'posts_per_page' => af_preview_max_per_user(),
        'orderby'        => 'date',
        'order'          => 'DESC',
    ));

    if (!empty($_GET['af_deleted'])) {
        $what = sanitize_key(wp_unslash($_GET['af_deleted']));
        if ($what === 'expired') {
            echo '<div class="woocommerce-error" role="alert">That page had been open a while, so the delete was not accepted. Please try again.</div>';
        } elseif ($what === 'denied') {
            echo '<div class="woocommerce-error" role="alert">That preview could not be deleted.</div>';
        } else {
            echo '<div class="woocommerce-message" role="alert">Preview deleted.</div>';
        }
    }

    echo '<p>Previews you saved from <a href="' . esc_url(home_url('/try-on-wall/')) . '">Try It On Your Wall</a> and '
       . '<a href="' . esc_url(home_url('/frame-the-moment/')) . '">Frame The Moment</a>.</p>';

    if (!$previews) {
        echo '<div class="af-sp-empty"><p>You have not saved any previews yet.</p>'
           . '<p><a class="button" href="' . esc_url(home_url('/try-on-wall/')) . '">Try art on your wall</a></p></div>';
        return;
    }

    echo '<div class="af-sp-grid">';
    foreach ($previews as $p) {
        $img   = get_the_post_thumbnail_url($p->ID, 'large');
        $full  = get_the_post_thumbnail_url($p->ID, 'full');
        $pid   = (int) get_post_meta($p->ID, '_af_preview_product', true);
        $size  = get_post_meta($p->ID, '_af_preview_size',  true);
        $frame = get_post_meta($p->ID, '_af_preview_frame', true);
        $color = get_post_meta($p->ID, '_af_preview_color', true);
        $lay   = (int) get_post_meta($p->ID, '_af_preview_layout', true);
        $link  = $pid ? get_permalink($pid) : home_url('/frame-the-moment/');
        if ($pid && ($size || $frame || $color)) {
            $link = add_query_arg(array(
                'af_size'  => rawurlencode($size),
                'af_frame' => rawurlencode($frame),
                'af_color' => rawurlencode($color),
            ), $link);
        }
        $src   = get_post_meta($p->ID, '_af_preview_source', true);
        $cta   = ($pid && $src !== 'frame-the-moment') ? 'View product' : 'Open in Frame The Moment';
        $bits  = array_filter(array($size, $frame, $color, $lay > 1 ? $lay . '-panel set' : ''));
        $wa   = 'https://wa.me/?text=' . rawurlencode(get_the_title($p) . ' — my wall preview: ' . $full);
        $mail = 'mailto:?subject=' . rawurlencode('My wall preview — ' . get_the_title($p))
              . '&body=' . rawurlencode("Here is the preview I saved:\n" . $full . "\n\n" . $link);

        echo '<div class="af-sp-card">';
        if ($img) echo '<a href="' . esc_url($full) . '" target="_blank" rel="noopener"><img src="' . esc_url($img) . '" alt="' . esc_attr(get_the_title($p)) . '"></a>';
        echo '<div class="af-sp-body">';
        echo '<strong>' . esc_html(get_the_title($p)) . '</strong>';
        if ($bits) echo '<small>' . esc_html(implode(' · ', $bits)) . '</small>';
        echo '<small>' . esc_html(get_the_date('', $p)) . '</small>';
        echo '<div class="af-sp-actions">';
        echo '<a class="af-sp-btn" href="' . esc_url($link) . '">' . esc_html($cta) . '</a>';
        echo '<a class="af-sp-btn" href="' . esc_url($full) . '" download>Download</a>';
        echo '<a class="af-sp-btn" href="' . esc_url($wa) . '" target="_blank" rel="noopener">WhatsApp</a>';
        echo '<a class="af-sp-btn" href="' . esc_url($mail) . '">Email</a>';
        echo '</div>';
        echo '<form method="post" onsubmit="return confirm(\'Delete this saved preview?\');">';
        wp_nonce_field('af_preview_delete_' . $p->ID, 'af_preview_nonce');
        echo '<button type="submit" name="af_preview_delete" value="' . esc_attr($p->ID) . '" class="af-sp-del">Delete</button>';
        echo '</form>';
        echo '</div></div>';
    }
    echo '</div>';

    echo '<style>
    .af-sp-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:18px;margin-top:18px;}
    .af-sp-card{border:1px solid #ece4cf;border-radius:14px;overflow:hidden;background:#fffdf8;display:flex;flex-direction:column;}
    .af-sp-card img{width:100%;height:170px;object-fit:cover;display:block;}
    .af-sp-body{padding:12px 14px 14px;display:flex;flex-direction:column;gap:4px;}
    .af-sp-body strong{font-size:14px;line-height:1.35;}
    .af-sp-body small{font-size:11.5px;color:#8a8170;}
    .af-sp-actions{display:flex;flex-wrap:wrap;gap:6px;margin:8px 0 4px;}
    .af-sp-btn{font-size:11.5px;font-weight:700;padding:6px 10px;border-radius:8px;background:#f2ecdd;color:#5a5140;text-decoration:none;}
    .af-sp-btn:hover{background:#e9e0cc;color:#3d342a;}
    .af-sp-del{background:none;border:none;padding:0;font-size:11.5px;color:#a33;cursor:pointer;text-align:left;}
    .af-sp-empty{padding:22px 0;}
    </style>';
});

/**
 * Shared front-end helper for the AR pages: saving a composed canvas to
 * the account, and the WhatsApp / Email / copy-link share targets.
 * Echoed inside the page templates, before their own scripts run.
 */
function af_preview_share_assets() {
    $account = function_exists('wc_get_endpoint_url')
        ? wc_get_endpoint_url('saved-previews', '', wc_get_page_permalink('myaccount'))
        : home_url('/my-account/');
    $login = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/my-account/');
    ?>
    <script>
    window.AFPreview = (function(){
      var CFG = {
        ajax:    <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,
        nonce:   <?php echo wp_json_encode(wp_create_nonce('af_preview')); ?>,
        logged:  <?php echo is_user_logged_in() ? 'true' : 'false'; ?>,
        account: <?php echo wp_json_encode($account); ?>,
        login:   <?php echo wp_json_encode($login); ?>
      };
      // POST a composed canvas to the account. cb(ok, message, url)
      function save(dataURL, meta, cb){
        if(!CFG.logged){
          cb(false, 'Please sign in to save previews to your account.', null, CFG.login);
          return;
        }
        var body = new URLSearchParams();
        body.set('action','af_save_preview');
        body.set('nonce', CFG.nonce);
        body.set('image', dataURL);
        Object.keys(meta||{}).forEach(function(k){ body.set(k, meta[k]); });
        fetch(CFG.ajax, { method:'POST', credentials:'same-origin', body:body })
          .then(function(r){ return r.json().catch(function(){ return {success:false,data:{message:'Could not save that preview.'}}; }); })
          .then(function(j){
            if(j && j.success) cb(true, (j.data&&j.data.message)||'Saved to your account.', j.data&&j.data.url, (j.data&&j.data.account)||CFG.account);
            else cb(false, (j&&j.data&&j.data.message)||'Could not save that preview.', null, CFG.login);
          })
          .catch(function(){ cb(false, 'Could not reach the server — please try again.', null, CFG.login); });
      }
      // Send them back to where they were after signing in. Plain permalinks put
      // a query string on the account URL already, so pick the separator.
      function withRedirect(url){
        return url + (url.indexOf('?') > -1 ? '&' : '?') + 'redirect_to=' + encodeURIComponent(location.href);
      }
      function whatsapp(text, url){ return 'https://wa.me/?text=' + encodeURIComponent(text + ' ' + url); }
      function email(subject, text, url){
        return 'mailto:?subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(text + '\n\n' + url);
      }
      function copy(url, cb){
        if(navigator.clipboard && navigator.clipboard.writeText){
          navigator.clipboard.writeText(url).then(function(){ cb(true); }, function(){ cb(false); });
          return;
        }
        try{
          var t=document.createElement('textarea'); t.value=url; t.setAttribute('readonly','');
          t.style.position='fixed'; t.style.opacity='0'; document.body.appendChild(t);
          t.select(); document.execCommand('copy'); t.remove(); cb(true);
        }catch(err){ cb(false); }
      }
      // Native share sheet on phones, when the browser offers one
      function native(title, text, url){
        if(!navigator.share) return false;
        navigator.share({ title:title, text:text, url:url }).catch(function(){});
        return true;
      }
      return { save:save, whatsapp:whatsapp, email:email, copy:copy, native:native, withRedirect:withRedirect, cfg:CFG };
    })();
    </script>
    <style>
    .af-share{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px;}
    .af-share button,.af-share a{flex:1 1 0;min-width:0;display:flex;align-items:center;justify-content:center;gap:6px;
      height:40px;padding:0 10px;border-radius:10px;border:1.5px solid #e2d9c4;background:#fffdf8;color:#5a5140;
      font-size:12px;font-weight:700;cursor:pointer;text-decoration:none;white-space:nowrap;transition:border-color .15s,background .2s;}
    .af-share button:hover,.af-share a:hover{border-color:#c9a84c;background:#fdf9ef;color:#5a5140;}
    .af-share .af-share-wa{border-color:#25a366;color:#1e8b56;}
    .af-share .af-share-wa:hover{background:#eefaf3;border-color:#25a366;color:#1e8b56;}
    .af-sharelabel{margin:16px 0 0;font-size:11.5px;font-weight:800;color:#6b6250;text-transform:uppercase;letter-spacing:.05em;}
    </style>
    <?php
}

// ── PHASE 28 — Admin-only Inventory Management page (/inventory-management/) ──
// Standalone stock dashboard rendered over a real WP page, same pattern as the
// try-on-wall / frame-the-moment builders. Lists every product with its stock
// state and lets an administrator edit quantities inline. Restricted to
// manage_options (site administrators) — shop managers do NOT get in.

// Named staff who get the inventory tool without being site administrators.
// Kept as an explicit email allowlist rather than granting these accounts the
// administrator role: the role would also hand over plugins, users, theme
// editing and everything else, when all that was asked for is this one page.
function af_inv_allowed_emails() {
    return array(
        'sushovanberawinquest452@gmail.com',
        'yashwinquest@gmail.com',
        'dinesh@winquestonline.com',
    );
}

// The TOP-LEVEL storefront categories a product belongs to, matching the
// category menu on the site (e.g. "Direct from Artists"), not the sub-category
// under it (e.g. an individual artist). Each assigned term is rolled up to its
// root ancestor so the dashboard groups the way the shop menu does. A product
// under two different top-level categories appears under each — accurate, not
// a duplicate.
function af_inv_product_cats($pid) {
    $terms = get_the_terms($pid, 'product_cat');
    if (!$terms || is_wp_error($terms)) return array();
    $names = array();
    foreach ($terms as $t) {
        $root = $t;
        if ($t->parent) {
            $anc = get_ancestors($t->term_id, 'product_cat'); // nearest-first ... root-last
            if ($anc) {
                $root_id = end($anc);
                $rt = get_term($root_id, 'product_cat');
                if ($rt && !is_wp_error($rt)) $root = $rt;
            }
        }
        $names[] = html_entity_decode(wp_strip_all_tags($root->name));
    }
    return array_values(array_unique($names));
}

// Top-level product categories in the storefront's own display order (the same
// order shown in the shop's category menu), so the dashboard's sections line up
// with what the owner sees on the site rather than a plain alphabetical list.
function af_inv_category_order() {
    $terms = get_terms(array(
        'taxonomy'   => 'product_cat',
        'parent'     => 0,
        'hide_empty' => false,
        'orderby'    => 'menu_order', // honours the manual product-category order
        'order'      => 'ASC',
    ));
    if (!$terms || is_wp_error($terms)) return array();
    $names = array();
    foreach ($terms as $t) {
        $names[] = html_entity_decode(wp_strip_all_tags($t->name));
    }
    return $names;
}

function af_inv_can_access() {
    if (!is_user_logged_in()) return false;
    if (current_user_can('manage_options')) return true;

    $user = wp_get_current_user();
    if (!$user || empty($user->user_email)) return false;

    $email   = strtolower(trim($user->user_email));
    $allowed = array_map('strtolower', af_inv_allowed_emails());
    return in_array($email, $allowed, true);
}

// Endpoint: write a new stock quantity for one product.
function af_inventory_save_handler() {
    if (!af_inv_can_access()) {
        wp_send_json_error(array('message' => 'Permission denied.'), 403);
    }
    check_ajax_referer('af_inventory', 'nonce');

    $pid = isset($_POST['id']) ? absint($_POST['id']) : 0;
    $product = $pid ? wc_get_product($pid) : null;
    if (!$product) {
        wp_send_json_error(array('message' => 'Product not found.'), 404);
    }

    // An empty qty means "stop tracking a number for this product" and fall
    // back to the plain in/out-of-stock flag, which is how WooCommerce itself
    // treats a blank stock field.
    $raw = isset($_POST['qty']) ? trim(wp_unslash($_POST['qty'])) : '';
    if ($raw === '') {
        $product->set_manage_stock(false);
        $status = isset($_POST['status']) && $_POST['status'] === 'outofstock' ? 'outofstock' : 'instock';
        $product->set_stock_status($status);
        $qty = null;
    } else {
        $qty = (int) $raw;
        if ($qty < 0) $qty = 0;
        $product->set_manage_stock(true);
        $product->set_stock_quantity($qty);
        $product->set_stock_status($qty > 0 ? 'instock' : 'outofstock');
    }
    $product->save();

    if (function_exists('wc_delete_product_transients')) wc_delete_product_transients($pid);

    wp_send_json_success(array(
        'id'     => $pid,
        'qty'    => $qty,
        'status' => $product->get_stock_status(),
    ));
}
add_action('wp_ajax_af_inventory_save', 'af_inventory_save_handler');

add_action('template_redirect', function(){
    if (!function_exists('is_page') || !is_page(array('inventory-management','inventory'))) return;
    if (!function_exists('wc_get_products')) return;

    if (!af_inv_can_access()) {
        wp_die(
            'You do not have permission to view this page.',
            'Access Denied',
            array('response' => 403, 'back_link' => true)
        );
    }

    $ids = wc_get_products(array('status' => array('publish','draft'), 'limit' => -1, 'return' => 'ids'));
    $rows = array();
    foreach ($ids as $pid) {
        $p = wc_get_product($pid);
        if (!$p) continue;
        $rows[] = array(
            'id'      => $pid,
            'title'   => html_entity_decode(wp_strip_all_tags($p->get_name())),
            'sku'     => $p->get_sku(),
            'price'   => (float) $p->get_price(),
            'managed' => (bool) $p->get_manage_stock(),
            'qty'     => $p->get_manage_stock() ? (int) $p->get_stock_quantity() : null,
            'status'  => $p->get_stock_status(),
            'draft'   => $p->get_status() !== 'publish',
            'img'     => get_the_post_thumbnail_url($pid, 'thumbnail') ?: wc_placeholder_img_src('thumbnail'),
            // The console's own edit screen, not wp-admin's — see af_pe_url().
            'edit'    => af_pe_url($pid),
            'wpedit'  => get_edit_post_link($pid, 'raw'),
            'view'    => get_permalink($pid),
            'cats'    => af_inv_product_cats($pid),
        );
    }
    usort($rows, function($a, $b){ return strcasecmp($a['title'], $b['title']); });

    $cfg = array(
        'ajax'     => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('af_inventory'),
        'sym'      => function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '$',
        'low'      => 5, // at or below this counts as "low stock"
        'catOrder' => af_inv_category_order(),
    );

    get_header();
    ?>
    <div class="af-inv-wrap">
      <header class="af-inv-head">
        <div>
          <p class="af-inv-eyebrow">Admin only</p>
          <h1>Inventory Management</h1>
          <p class="af-inv-sub">Edit a stock number and press Enter (or click away) to save. Leave it blank to stop tracking a count for that product.</p>
        </div>
        <a class="af-inv-home" href="<?php echo esc_url(home_url('/')); ?>">Back to site</a>
      </header>

      <div class="af-inv-stats" id="inv-stats"></div>

      <div class="af-inv-toolbar">
        <input type="search" id="inv-search" class="af-inv-search" placeholder="Search by name or SKU&hellip;" autocomplete="off">
        <div class="af-inv-filters" id="inv-filters">
          <button type="button" class="af-inv-chip is-on" data-filter="all">All</button>
          <button type="button" class="af-inv-chip" data-filter="instock">In stock</button>
          <button type="button" class="af-inv-chip" data-filter="low">Low stock</button>
          <button type="button" class="af-inv-chip" data-filter="outofstock">Out of stock</button>
          <button type="button" class="af-inv-chip" data-filter="untracked">Not tracked</button>
        </div>
        <label class="af-inv-groupToggle"><input type="checkbox" id="inv-groupby" checked> Group by category</label>
      </div>

      <div class="af-inv-layout" id="inv-layout">
        <nav class="af-inv-catrail" id="inv-catrail" aria-label="Categories"></nav>
        <div class="af-inv-main">
          <div id="inv-groups"></div>
          <p class="af-inv-empty" id="inv-empty" hidden>No products match that search.</p>
        </div>
      </div>
    </div>

    <script>
    (function(){
      var ROWS = <?php echo wp_json_encode($rows); ?>;
      var CFG  = <?php echo wp_json_encode($cfg); ?>;
      var $ = function(id){ return document.getElementById(id); };
      var filter = 'all', term = '', groupBy = true, activeCat = 'all';

      function esc(s){ var d=document.createElement('div'); d.textContent = s==null?'':String(s); return d.innerHTML; }
      function money(n){ return CFG.sym + (Math.round(n*100)/100).toFixed(2); }

      // A product's bucket drives both the filter chips and the status pill.
      function bucket(r){
        if (!r.managed) return 'untracked';
        if (r.status === 'outofstock' || r.qty <= 0) return 'outofstock';
        if (r.qty <= CFG.low) return 'low';
        return 'instock';
      }
      var LABEL = { instock:'In stock', low:'Low stock', outofstock:'Out of stock', untracked:'Not tracked' };

      function visible(){
        return ROWS.filter(function(r){
          if (filter !== 'all') {
            var b = bucket(r);
            // "In stock" should include low-stock items; "Low stock" narrows it.
            if (filter === 'instock' ? (b !== 'instock' && b !== 'low') : b !== filter) return false;
          }
          if (!term) return true;
          var hay = (r.title + ' ' + (r.sku||'')).toLowerCase();
          return hay.indexOf(term) !== -1;
        });
      }

      function renderStats(){
        var c = { instock:0, low:0, outofstock:0, untracked:0 };
        ROWS.forEach(function(r){ c[bucket(r)]++; });
        $('inv-stats').innerHTML =
          stat('Products', ROWS.length, '') +
          stat('In stock', c.instock, 'ok') +
          stat('Low stock', c.low, 'warn') +
          stat('Out of stock', c.outofstock, 'bad') +
          stat('Not tracked', c.untracked, 'mute');
      }
      function stat(label, n, kind){
        return '<div class="af-inv-stat af-inv-stat--'+kind+'"><span class="af-inv-statn">'+n+'</span><span class="af-inv-statl">'+label+'</span></div>';
      }

      var UNCAT = 'Uncategorized';

      function rowHtml(r){
        var b = bucket(r);
        return '<tr data-id="'+r.id+'">'
          + '<td class="af-inv-tdimg"><img src="'+esc(r.img)+'" alt="" loading="lazy"></td>'
          + '<td class="af-inv-tdname"><a href="'+esc(r.view)+'" target="_blank" rel="noopener">'+esc(r.title)+'</a>'
            + (r.draft ? ' <span class="af-inv-draft">Draft</span>' : '') + '</td>'
          + '<td class="af-inv-tdsku">'+(r.sku ? esc(r.sku) : '<span class="af-inv-dash">&mdash;</span>')+'</td>'
          + '<td class="af-inv-tdprice">'+money(r.price)+'</td>'
          + '<td class="af-inv-tdstock"><input type="number" min="0" step="1" class="af-inv-qty" '
            + 'value="'+(r.managed && r.qty !== null ? r.qty : '')+'" placeholder="&mdash;" aria-label="Stock quantity"></td>'
          + '<td class="af-inv-tdstatus"><span class="af-inv-pill af-inv-pill--'+b+'">'+LABEL[b]+'</span></td>'
          + '<td class="af-inv-tdact"><a class="af-inv-edit" href="'+esc(r.edit)+'">Edit</a></td>'
          + '</tr>';
      }

      // A category's products fill the available screen height and scroll
      // inside for the rest — so you can read down to the last product without
      // the page itself growing. The header row stays pinned (sticky thead) so
      // the columns stay labelled while scrolling.
      function tableHtml(rows){
        return '<div class="af-inv-tablewrap is-scroll"><table class="af-inv-table"><thead><tr>'
          + '<th class="af-inv-thimg"></th><th>Product</th><th class="af-inv-thsku">SKU</th>'
          + '<th class="af-inv-thprice">Price</th><th class="af-inv-thstock">Stock</th>'
          + '<th class="af-inv-thstatus">Status</th><th class="af-inv-thact"></th>'
          + '</tr></thead><tbody>' + rows.map(rowHtml).join('') + '</tbody></table></div>';
      }

      // Bucket the (filtered) products by top-level category.
      function groupByCat(list){
        var groups = {};
        list.forEach(function(r){
          var cats = (r.cats && r.cats.length) ? r.cats : [UNCAT];
          cats.forEach(function(c){ (groups[c] = groups[c] || []).push(r); });
        });
        return groups;
      }

      // Category names ordered to match the storefront's own category menu
      // (CFG.catOrder). Anything not in that list (a stray category, or
      // Uncategorized) trails after, with Uncategorized always last.
      function orderedCatNames(groups){
        var order = CFG.catOrder || [];
        var rank = {};
        order.forEach(function(n, i){ rank[n] = i; });
        return Object.keys(groups).sort(function(a, b){
          if (a === UNCAT) return 1;
          if (b === UNCAT) return -1;
          var ra = (a in rank) ? rank[a] : 9998;
          var rb = (b in rank) ? rank[b] : 9998;
          if (ra !== rb) return ra - rb;
          return a.localeCompare(b);
        });
      }

      // Vertical category rail down the left — the same categories as the shop
      // menu, in the same order, each a click-to-filter entry.
      function renderRail(){
        var rail = $('inv-catrail');
        if (!groupBy) { rail.hidden = true; return; }
        rail.hidden = false;

        var groups = groupByCat(ROWS); // counts reflect the whole catalogue
        var names = orderedCatNames(groups);
        var html = '<button type="button" class="af-inv-catlink' + (activeCat === 'all' ? ' is-on' : '')
          + '" data-cat="all">All categories <span class="af-inv-catn">' + ROWS.length + '</span></button>';
        html += names.map(function(name){
          return '<button type="button" class="af-inv-catlink' + (activeCat === name ? ' is-on' : '')
            + '" data-cat="' + esc(name) + '">' + esc(name)
            + ' <span class="af-inv-catn">' + groups[name].length + '</span></button>';
        }).join('');
        rail.innerHTML = html;
      }

      // Size each scrollable product table so it stops at the bottom of the
      // viewport — the table fits the page and its extra rows scroll inside,
      // instead of the whole page growing taller than the screen.
      function fitTables(){
        var wraps = document.querySelectorAll('.af-inv-tablewrap.is-scroll');
        for (var i = 0; i < wraps.length; i++) {
          var w = wraps[i];
          var top = w.getBoundingClientRect().top; // distance from viewport top
          var avail = Math.floor(window.innerHeight - top - 20);
          if (avail < 220) avail = 220; // always show a few rows
          w.style.maxHeight = avail + 'px';
        }
      }

      function render(){
        renderRail();
        var list = visible();

        if (!groupBy) {
          $('inv-empty').hidden = list.length > 0;
          $('inv-groups').innerHTML = list.length ? tableHtml(list) : '';
          fitTables();
          return;
        }

        var groups = groupByCat(list);
        var names = orderedCatNames(groups);
        if (activeCat !== 'all') names = names.filter(function(n){ return n === activeCat; });

        $('inv-empty').hidden = names.length > 0;
        $('inv-groups').innerHTML = names.map(function(name){
          var rows = groups[name];
          return '<section class="af-inv-cat">'
            + '<h2 class="af-inv-cattitle">' + esc(name)
            + ' <span class="af-inv-catcount">' + rows.length + '</span></h2>'
            + tableHtml(rows) + '</section>';
        }).join('');
        fitTables();
      }

      function save(tr, input){
        var id = parseInt(tr.getAttribute('data-id'), 10);
        var row = ROWS.filter(function(r){ return r.id === id; })[0];
        if (!row) return;
        var raw = input.value.trim();
        var prev = (row.managed && row.qty !== null) ? String(row.qty) : '';
        if (raw === prev) return; // nothing actually changed

        tr.classList.add('is-saving');
        var body = new URLSearchParams();
        body.set('action', 'af_inventory_save');
        body.set('nonce', CFG.nonce);
        body.set('id', id);
        body.set('qty', raw);

        fetch(CFG.ajax, { method:'POST', credentials:'same-origin', body:body })
          .then(function(res){ return res.json(); })
          .then(function(json){
            tr.classList.remove('is-saving');
            if (!json || !json.success) throw new Error((json && json.data && json.data.message) || 'Save failed');
            row.managed = json.data.qty !== null;
            row.qty     = json.data.qty;
            row.status  = json.data.status;
            flash(tr, 'ok');
            renderStats();
            // In grouped view the same product can appear under several
            // category headings, so sync every row for this id — not just the
            // one edited — so the copies never disagree.
            var b = bucket(row);
            var all = document.querySelectorAll('.af-inv-table tr[data-id="'+row.id+'"]');
            Array.prototype.forEach.call(all, function(t){
              var pill = t.querySelector('.af-inv-pill');
              if (pill) { pill.className = 'af-inv-pill af-inv-pill--'+b; pill.textContent = LABEL[b]; }
              if (t !== tr) {
                var qi = t.querySelector('.af-inv-qty');
                if (qi) qi.value = (row.managed && row.qty !== null) ? row.qty : '';
              }
            });
          })
          .catch(function(err){
            tr.classList.remove('is-saving');
            flash(tr, 'bad');
            input.value = prev; // put the old number back so the table never lies
            alert('Could not save: ' + err.message);
          });
      }
      function flash(tr, kind){
        tr.classList.add('af-inv-flash-'+kind);
        setTimeout(function(){ tr.classList.remove('af-inv-flash-'+kind); }, 1200);
      }

      $('inv-groups').addEventListener('change', function(e){
        if (!e.target.classList.contains('af-inv-qty')) return;
        save(e.target.closest('tr'), e.target);
      });
      $('inv-groups').addEventListener('keydown', function(e){
        if (e.key === 'Enter' && e.target.classList.contains('af-inv-qty')) { e.preventDefault(); e.target.blur(); }
      });
      $('inv-search').addEventListener('input', function(){ term = this.value.trim().toLowerCase(); render(); });
      $('inv-filters').addEventListener('click', function(e){
        var btn = e.target.closest('.af-inv-chip');
        if (!btn) return;
        filter = btn.getAttribute('data-filter');
        Array.prototype.forEach.call(this.querySelectorAll('.af-inv-chip'), function(b){ b.classList.toggle('is-on', b === btn); });
        render();
      });
      $('inv-groupby').addEventListener('change', function(){ groupBy = this.checked; render(); });
      $('inv-catrail').addEventListener('click', function(e){
        var btn = e.target.closest('.af-inv-catlink');
        if (!btn) return;
        activeCat = btn.getAttribute('data-cat');
        render();
      });
      // Keep the table fitting the viewport as the window is resized.
      var rzT;
      window.addEventListener('resize', function(){ clearTimeout(rzT); rzT = setTimeout(fitTables, 120); });

      // Open on the first real category rather than every category at once,
      // so only the selected category's products show. "All categories" in the
      // rail is still there if the whole list is wanted.
      (function pickInitialCat(){
        var names = orderedCatNames(groupByCat(ROWS));
        var firstReal = names.filter(function(n){ return n !== UNCAT; })[0];
        activeCat = firstReal || names[0] || 'all';
      })();

      renderStats();
      render();
    })();
    </script>

    <style>
    .af-inv-wrap{max-width:1180px;margin:0 auto;padding:34px 18px 70px;
      background:linear-gradient(180deg,#f6f1e6 0%,#efe7d6 100%);}
    .af-inv-head{display:flex;flex-wrap:wrap;gap:16px;align-items:flex-start;justify-content:space-between;margin:0 0 22px;}
    .af-inv-eyebrow{margin:0 0 6px;font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;
      color:#a8801f;background:#f3ead2;border:1px solid #e6d7ad;border-radius:999px;display:inline-block;padding:4px 11px;}
    .af-inv-head h1{margin:0 0 8px;font-family:'Playfair Display',Georgia,serif;font-size:34px;color:#1a1a1a;line-height:1.15;}
    .af-inv-sub{margin:0;font-size:13.5px;color:#6b6250;max-width:56ch;line-height:1.6;}
    .af-inv-home{align-self:center;background:#1a1a1a;color:#fff;text-decoration:none;font-size:12.5px;font-weight:700;
      padding:11px 18px;border-radius:9px;transition:background .2s;white-space:nowrap;}
    .af-inv-home:hover{background:#c9a84c;color:#fff;}

    .af-inv-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin:0 0 22px;}
    .af-inv-stat{background:#fffdf8;border:1px solid #efe6d2;border-radius:12px;padding:14px 16px;
      box-shadow:0 2px 10px rgba(70,54,26,.05);}
    .af-inv-statn{display:block;font-family:'Playfair Display',Georgia,serif;font-size:27px;color:#1a1a1a;line-height:1.1;}
    .af-inv-statl{display:block;margin-top:3px;font-size:11px;font-weight:700;letter-spacing:.05em;
      text-transform:uppercase;color:#8a8170;}
    .af-inv-stat--ok .af-inv-statn{color:#1e8b56;}
    .af-inv-stat--warn .af-inv-statn{color:#a8801f;}
    .af-inv-stat--bad .af-inv-statn{color:#b4453a;}
    .af-inv-stat--mute .af-inv-statn{color:#8a8170;}

    .af-inv-toolbar{display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;margin:0 0 14px;}
    .af-inv-search{flex:1 1 260px;min-width:0;height:42px;padding:0 14px;border:1.5px solid #e2d9c4;border-radius:10px;
      background:#fffdf8;color:#1a1a1a;font-size:14px;}
    .af-inv-search:focus{outline:none;border-color:#c9a84c;}
    .af-inv-filters{display:flex;flex-wrap:wrap;gap:7px;}
    .af-inv-chip{height:36px;padding:0 14px;border:1.5px solid #e2d9c4;border-radius:999px;background:#fffdf8;
      color:#6b6250;font-size:12.5px;font-weight:700;cursor:pointer;transition:border-color .15s,background .2s,color .2s;}
    .af-inv-chip:hover{border-color:#c9a84c;background:#fdf9ef;}
    .af-inv-chip.is-on{background:#1a1a1a;border-color:#1a1a1a;color:#fff;}
    .af-inv-groupToggle{display:flex;align-items:center;gap:7px;font-size:12.5px;font-weight:700;
      color:#6b6250;cursor:pointer;user-select:none;white-space:nowrap;}
    .af-inv-groupToggle input{width:16px;height:16px;accent-color:#c9a84c;cursor:pointer;}

    /* Two-column layout: a vertical category rail on the left, product
       sections on the right — mirroring the shop's category menu. */
    .af-inv-layout{display:grid;grid-template-columns:230px 1fr;gap:22px;align-items:start;}
    .af-inv-main{min-width:0;}
    .af-inv-catrail{position:sticky;top:16px;display:flex;flex-direction:column;gap:4px;
      background:#fffdf8;border:1px solid #efe6d2;border-radius:14px;padding:10px;
      box-shadow:0 4px 18px rgba(70,54,26,.07);}
    .af-inv-catlink{display:flex;align-items:center;justify-content:space-between;gap:8px;width:100%;
      text-align:left;padding:10px 12px;border:none;border-radius:9px;background:transparent;
      color:#3a352a;font-size:13px;font-weight:700;cursor:pointer;transition:background .15s,color .15s;}
    .af-inv-catlink:hover{background:#f7f0df;color:#1a1a1a;}
    .af-inv-catlink.is-on{background:#1a1a1a;color:#fff;}
    .af-inv-catn{flex:0 0 auto;font-size:11px;font-weight:800;color:#a8801f;background:#f3ead2;
      border-radius:999px;padding:2px 8px;line-height:1.6;}
    .af-inv-catlink.is-on .af-inv-catn{background:#c9a84c;color:#1a1a1a;}
    @media(max-width:820px){
      .af-inv-layout{grid-template-columns:1fr;}
      .af-inv-catrail{position:static;flex-direction:row;flex-wrap:wrap;}
      .af-inv-catlink{width:auto;}
    }

    .af-inv-cat{margin:0 0 26px;}
    .af-inv-cattitle{margin:0 0 10px;font-family:'Playfair Display',Georgia,serif;font-size:20px;color:#1a1a1a;
      display:flex;align-items:center;gap:10px;padding-bottom:8px;border-bottom:2px solid #e6d7ad;}
    .af-inv-catcount{font-family:inherit;font-size:11px;font-weight:800;color:#a8801f;background:#f3ead2;
      border:1px solid #e6d7ad;border-radius:999px;padding:2px 9px;line-height:1.6;}

    .af-inv-tablewrap{background:#fffdf8;border:1px solid #efe6d2;border-radius:14px;overflow-x:auto;
      box-shadow:0 4px 18px rgba(70,54,26,.07);}
    /* Let a category's table use most of the screen height, showing products
       to the end of that space and scrolling within for the rest. A short
       category simply doesn't reach the cap, so it never scrolls. The sticky
       thead keeps the column labels visible while scrolling. */
    .af-inv-tablewrap.is-scroll{max-height:72vh;overflow-y:auto;}
    .af-inv-table{width:100%;border-collapse:collapse;font-size:13.5px;min-width:760px;}
    .af-inv-table thead th{position:sticky;top:0;background:#f3ead2;color:#6b6250;text-align:left;
      font-size:11px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;padding:12px 14px;
      border-bottom:1px solid #e6d7ad;z-index:1;}
    .af-inv-table tbody td{padding:11px 14px;border-bottom:1px solid #f0e9da;vertical-align:middle;color:#1a1a1a;}
    .af-inv-table tbody tr:last-child td{border-bottom:none;}
    .af-inv-table tbody tr:hover{background:#fdf9ef;}
    .af-inv-thimg,.af-inv-tdimg{width:54px;}
    .af-inv-tdimg img{width:40px;height:40px;object-fit:cover;border-radius:7px;border:1px solid #ece4cf;display:block;}
    .af-inv-tdname a{color:#1a1a1a;text-decoration:none;font-weight:600;}
    .af-inv-tdname a:hover{color:#c9a84c;}
    .af-inv-draft{display:inline-block;margin-left:6px;font-size:10px;font-weight:800;text-transform:uppercase;
      letter-spacing:.04em;color:#8a8170;background:#f0e9da;border-radius:4px;padding:2px 6px;vertical-align:middle;}
    .af-inv-tdsku,.af-inv-thsku{color:#6b6250;font-size:12.5px;white-space:nowrap;}
    .af-inv-dash{color:#b8b0a0;}
    .af-inv-tdprice,.af-inv-thprice{white-space:nowrap;font-weight:600;}
    .af-inv-thstock,.af-inv-tdstock{width:104px;}
    .af-inv-qty{width:84px;height:36px;padding:0 9px;border:1.5px solid #e2d9c4;border-radius:8px;background:#fff;
      color:#1a1a1a;font-size:13.5px;font-weight:600;text-align:center;}
    .af-inv-qty:focus{outline:none;border-color:#c9a84c;background:#fffdf8;}
    .af-inv-pill{display:inline-block;padding:4px 10px;border-radius:999px;font-size:11px;font-weight:800;
      letter-spacing:.03em;white-space:nowrap;}
    .af-inv-pill--instock{background:#e6f5ed;color:#1e8b56;border:1px solid #c4e6d5;}
    .af-inv-pill--low{background:#f3ead2;color:#a8801f;border:1px solid #e6d7ad;}
    .af-inv-pill--outofstock{background:#fbe9e7;color:#b4453a;border:1px solid #f0cdc8;}
    .af-inv-pill--untracked{background:#f0e9da;color:#8a8170;border:1px solid #e2d9c4;}
    .af-inv-edit{color:#6b6250;text-decoration:none;font-size:12px;font-weight:700;border:1.5px solid #e2d9c4;
      border-radius:8px;padding:7px 12px;transition:border-color .15s,color .2s,background .2s;white-space:nowrap;}
    .af-inv-edit:hover{border-color:#c9a84c;color:#a8801f;background:#fdf9ef;}
    .af-inv-empty{margin:0;padding:34px 16px;text-align:center;color:#8a8170;font-size:14px;}

    .af-inv-table tbody tr.is-saving{opacity:.55;}
    .af-inv-table tbody tr.af-inv-flash-ok{background:#e6f5ed;}
    .af-inv-table tbody tr.af-inv-flash-bad{background:#fbe9e7;}

    @media(max-width:640px){
      .af-inv-wrap{padding:24px 12px 54px;}
      .af-inv-head h1{font-size:27px;}
    }
    </style>
    <?php
    get_footer();
    exit;
}, 1);

function af_inv_url() {
    $page = get_page_by_path('inventory-management');
    return $page ? get_permalink($page) : home_url('/inventory-management/');
}

function af_log_url() {
    $page = get_page_by_path('activity-log');
    return $page ? get_permalink($page) : home_url('/activity-log/');
}

// The activity log is administrators only (unlike Inventory, which also admits
// the allowlisted staff), matching the manage_options gate on the page itself.
function af_log_can_access() {
    return is_user_logged_in() && current_user_can('manage_options');
}

function af_console_url() {
    $page = get_page_by_path('admin-console');
    return $page ? get_permalink($page) : home_url('/admin-console/');
}

// The Admin Console is reachable by anyone who can open at least one of the
// tools inside it — administrators see every tool, allowlisted inventory staff
// see just Inventory. Each tool still enforces its own gate.
function af_console_can_access() {
    return (function_exists('af_inv_can_access') && af_inv_can_access()) || af_log_can_access();
}

// ── Admin Console hub page /admin-console/ ──────────────────────────────────
// A panel that gathers the admin tools (Inventory Management, Audit) in one
// place. Each tool is shown only if the viewer can actually open it.
add_action('template_redirect', function(){
    if (!function_exists('is_page') || !is_page(array('admin-console','admin'))) return;
    if (!af_console_can_access()) {
        wp_die('You do not have permission to view this page.', 'Access Denied',
            array('response' => 403, 'back_link' => true));
    }
    $can_inv = (function_exists('af_inv_can_access') && af_inv_can_access());
    $can_log = af_log_can_access();

    $cards = array();
    if ($can_inv) {
        $cards[] = array(
            'url'   => af_inv_url(),
            'icon'  => '&#128230;', // package
            'title' => 'Inventory Management',
            'desc'  => 'View and edit product stock, grouped by category.',
        );
    }
    if ($can_log) {
        $cards[] = array(
            'url'   => af_log_url(),
            'icon'  => '&#128220;', // scroll
            'title' => 'Audit',
            'desc'  => 'Timestamped log of what signed-in visitors do, with IP and email.',
        );
    }

    get_header();
    ?>
    <div class="af-console-wrap">
      <header class="af-console-head">
        <div>
          <p class="af-console-eyebrow">Admin only</p>
          <h1>Admin Console</h1>
          <p class="af-console-sub">Your staff tools in one place. Open a tool to manage the store.</p>
        </div>
        <a class="af-console-home" href="<?php echo esc_url(home_url('/')); ?>">Back to site</a>
      </header>

      <div class="af-console-grid">
        <?php foreach ($cards as $c) : ?>
          <a class="af-console-card" href="<?php echo esc_url($c['url']); ?>">
            <span class="af-console-cardicon"><?php echo $c['icon']; ?></span>
            <span class="af-console-cardtitle"><?php echo esc_html($c['title']); ?></span>
            <span class="af-console-carddesc"><?php echo esc_html($c['desc']); ?></span>
            <span class="af-console-cardgo">Open &rarr;</span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <style>
    .af-console-wrap{max-width:960px;margin:0 auto;padding:34px 18px 70px;
      background:linear-gradient(180deg,#f6f1e6 0%,#efe7d6 100%);}
    .af-console-head{display:flex;flex-wrap:wrap;gap:16px;align-items:flex-start;justify-content:space-between;margin:0 0 26px;}
    .af-console-eyebrow{margin:0 0 6px;font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;
      color:#a8801f;background:#f3ead2;border:1px solid #e6d7ad;border-radius:999px;display:inline-block;padding:4px 11px;}
    .af-console-head h1{margin:0 0 8px;font-family:'Playfair Display',Georgia,serif;font-size:34px;color:#1a1a1a;line-height:1.15;}
    .af-console-sub{margin:0;font-size:13.5px;color:#6b6250;max-width:56ch;line-height:1.6;}
    .af-console-home{align-self:center;background:#1a1a1a;color:#fff;text-decoration:none;font-size:12.5px;font-weight:700;
      padding:11px 18px;border-radius:9px;transition:background .2s;white-space:nowrap;}
    .af-console-home:hover{background:#c9a84c;color:#fff;}
    .af-console-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:18px;}
    .af-console-card{display:flex;flex-direction:column;gap:8px;padding:24px 22px;text-decoration:none;
      background:#fffdf8;border:1px solid #efe6d2;border-radius:16px;box-shadow:0 4px 18px rgba(70,54,26,.07);
      transition:border-color .15s,transform .15s,box-shadow .2s;}
    .af-console-card:hover{border-color:#c9a84c;transform:translateY(-2px);box-shadow:0 10px 26px rgba(70,54,26,.13);}
    .af-console-cardicon{font-size:30px;line-height:1;}
    .af-console-cardtitle{font-family:'Playfair Display',Georgia,serif;font-size:20px;color:#1a1a1a;}
    .af-console-carddesc{font-size:13px;color:#6b6250;line-height:1.55;}
    .af-console-cardgo{margin-top:6px;font-size:12.5px;font-weight:800;letter-spacing:.02em;color:#a8801f;}
    @media(max-width:640px){ .af-console-wrap{padding:24px 12px 54px;} .af-console-head h1{font-size:27px;} }
    </style>
    <?php
    get_footer();
    exit;
}, 1);

// Admin-only "Inventory" link in the main nav, sitting after Contact Us.
// The menu itself is managed in WP, so rather than editing it there (where the
// item would be visible to everyone) the link is injected at render time and
// only for users who can actually open the page.
//
// Admin-bar entry: works no matter how the header is built, so there is always
// one reliable way in even if the theme renders its nav without wp_nav_menu().
// The console is the parent; the individual tools hang off it as children.
add_action('admin_bar_menu', function($bar) {
    if (!af_console_can_access()) return;
    $bar->add_node(array(
        'id'    => 'af-admin-console',
        'title' => 'Admin Console',
        'href'  => af_console_url(),
        'meta'  => array('title' => 'Open the admin console'),
    ));
    if (function_exists('af_inv_can_access') && af_inv_can_access()) {
        $bar->add_node(array(
            'id'     => 'af-inventory',
            'parent' => 'af-admin-console',
            'title'  => 'Inventory',
            'href'   => af_inv_url(),
        ));
    }
}, 80);

// This adds the item to EVERY menu carrying a Contact Us link, not just the
// first. The first version stopped after one menu to avoid repeating it, but
// themes commonly emit a hidden mobile/off-canvas copy of the nav ahead of the
// desktop one — so the single insert landed in markup that is never visible on
// desktop. Adding to each matching menu also means the link works on mobile.
add_filter('wp_nav_menu_items', function($items, $args) {
    // Contact Us is removed from the header nav, so match on what remains
    // there too — otherwise this link would disappear along with it.
    if (stripos($items, 'contact') === false
        && stripos($items, 'about') === false
        && stripos($items, 'blog') === false) return $items;
    if (!af_console_can_access()) return $items;
    if (strpos($items, 'af-console-navitem') !== false) return $items;

    return $items . '<li class="menu-item af-console-navitem"><a href="' . esc_url(af_console_url()) . '">Admin Console</a></li>';
}, 20, 2);

add_action('wp_head', function() {
    if (!af_console_can_access()) return;
    ?>
    <style>
    /* Marks the injected link as an admin-only tool so it reads as distinct
       from the customer-facing nav items it sits beside. */
    .af-console-navitem > a{position:relative;color:#c9a84c !important;}
    .af-console-navitem > a::after{content:'ADMIN';margin-left:6px;font-size:8.5px;font-weight:800;
      letter-spacing:.06em;vertical-align:super;opacity:.75;}
    /* The item must sit INLINE with its siblings. The theme gives its own
       menu lis their inline layout through theme-specific classes this
       injected li does not carry, so without these rules it falls back to a
       full-width block li and lands alone on a second nav row. */
    .af-console-navitem{display:inline-flex !important;align-items:center !important;
      width:auto !important;max-width:none !important;flex:0 0 auto !important;
      float:none !important;clear:none !important;white-space:nowrap !important;
      vertical-align:middle !important;}
    .af-console-navitem > a{display:inline-block !important;width:auto !important;
      white-space:nowrap !important;}
    /* Displaying inline is not enough on its own: the nav row is a wrapping
       flex line already filled by the shop's own items, so a seventh one at
       their size still breaks to a second row. Make this item the compact
       one — it is a tool, not a shop link, so it need not match them — which
       is what lets it share the row. (The script also holds the row to one
       line where there is room; it measures first, so a row that cannot take
       the item wraps as before rather than overflowing the header.) */
    .af-console-navitem > a{font-size:11px !important;letter-spacing:.03em !important;
      padding-left:10px !important;padding-right:0 !important;}
    .af-console-navitem > a::after{font-size:7.5px;margin-left:4px;}
    </style>
    <?php
}, 99);

// Last-resort DOM fallback. Headers built by Elementor or a page builder can
// render their nav without ever passing through wp_nav_menu_items, in which
// case the PHP filter above adds nothing. This checks the rendered page and,
// only if no Inventory item is present, appends one to the nav list that
// actually holds the Contact link — matching what the visitor really sees
// rather than what the theme was assumed to output.
add_action('wp_footer', function() {
    if (!af_console_can_access()) return;
    ?>
    <script>
    (function(){
      var ITEMS = [{cls:'af-console-navitem', label:'Admin Console', href:<?php echo wp_json_encode(af_console_url()); ?>}];

      function findNav(){
        var lists = document.querySelectorAll('ul');
        var best = null, bestScore = 0;
        for (var i = 0; i < lists.length; i++) {
          var ul = lists[i];
          var links = ul.querySelectorAll(':scope > li > a');
          if (links.length < 2) continue; // not a real nav bar
          // Contact Us no longer sits in the header nav, so score on the
          // items that remain there.
          var markers = 0;
          for (var j = 0; j < links.length; j++) {
            if (/contact|about|blog/i.test(links[j].textContent || '')) markers++;
          }
          if (markers < 2) continue;
          // Prefer the visible nav: a hidden mobile copy has no layout box.
          var visible = ul.getBoundingClientRect().width > 0;
          var score = links.length + (visible ? 1000 : 0);
          if (score > bestScore) { bestScore = score; best = ul; }
        }
        return best;
      }

      function inject(){
        var best = null;
        ITEMS.forEach(function(item){
          if (document.querySelector('.' + item.cls)) return; // PHP filter already added it
          if (!best) best = findNav();
          if (!best) return;
          var first = best.querySelector(':scope > li');
          var li = document.createElement('li');
          li.className = (first ? first.className + ' ' : 'menu-item ') + item.cls;
          var a = document.createElement('a');
          a.href = item.href;
          a.textContent = item.label;
          li.appendChild(a);
          best.appendChild(li);
        });
      }
      // Hold the row the item ended up in to a single line. The stylesheet
      // says the same thing with :has(), which every current browser
      // supports; this repeats it on the element so the item never drops to
      // a second row on an older one, and so it applies to whichever list
      // actually received it — the theme's menu or the one found above.
      function pinRow(){
        document.querySelectorAll('.af-console-navitem').forEach(function(li){
          var ul = li.parentElement;
          if (!ul) return;
          ul.style.removeProperty('flex-wrap');
          if (window.innerWidth < 1100) return;
          if (getComputedStyle(ul).display.indexOf('flex') === -1) return;
          ul.style.setProperty('flex-wrap', 'nowrap', 'important');
          // Only hold the line while the line can actually hold it. Forcing
          // nowrap on a row with no room left does not save the item — it
          // pushes the whole nav past its container, which is worse than the
          // second row it was there to prevent. Measure, and stand down.
          if (ul.scrollWidth > ul.clientWidth + 1) ul.style.removeProperty('flex-wrap');
        });
      }
      function run(){ inject(); pinRow(); }
      if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run);
      else run();
      window.addEventListener('load', run);
      var _navTimer = null;
      window.addEventListener('resize', function(){
        clearTimeout(_navTimer); _navTimer = setTimeout(pinRow, 150);
      });
    })();
    </script>
    <?php
}, 99);

// ── PHASE 29 — Visitor activity log (logged-in users only) ──────────────────
// Records, for signed-in visitors ONLY, a timestamp + what they did + their IP
// into a dedicated table. Admin-only viewer at /activity-log/. Nothing here runs
// on guest requests, so cached anonymous traffic keeps paying zero extra cost —
// the log never touches the per-visitor budget we spent effort cutting.

function af_activity_log_table() {
    global $wpdb;
    return $wpdb->prefix . 'af_activity_log';
}

// Create/upgrade the table once, guarded by an option so dbDelta never runs on
// an ordinary request. Safe to call repeatedly.
function af_activity_log_ensure_table() {
    if (get_option('af_activity_log_db_ver') === '1') return;
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table   = af_activity_log_table();
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        created_at DATETIME NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        user_login VARCHAR(120) NOT NULL DEFAULT '',
        ip VARCHAR(45) NOT NULL DEFAULT '',
        action VARCHAR(255) NOT NULL DEFAULT '',
        url VARCHAR(255) NOT NULL DEFAULT '',
        PRIMARY KEY (id),
        KEY created_at (created_at),
        KEY user_id (user_id)
    ) {$charset};";
    dbDelta($sql);
    update_option('af_activity_log_db_ver', '1');
}

// Best-effort client IP. Honours the proxy headers this host / Cloudflare set,
// falls back to REMOTE_ADDR, and validates the result so a spoofed header can't
// store junk.
function af_activity_client_ip() {
    $candidates = array();
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) $candidates[] = $_SERVER['HTTP_CF_CONNECTING_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $candidates[] = $parts[0];
    }
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) $candidates[] = $_SERVER['HTTP_X_REAL_IP'];
    if (!empty($_SERVER['REMOTE_ADDR']))    $candidates[] = $_SERVER['REMOTE_ADDR'];
    foreach ($candidates as $ip) {
        $ip = trim($ip);
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    return '';
}

// Write one entry. Only ever called for logged-in users.
function af_activity_log_record($action, $user = null) {
    $action = trim((string) $action);
    if ($action === '') return;
    af_activity_log_ensure_table();
    global $wpdb;

    if ($user === null) $user = wp_get_current_user();
    $uid   = ($user && $user->ID) ? (int) $user->ID : 0;
    if (!$uid) return; // logged-in users only
    $login = ($user && $user->user_login) ? $user->user_login : '';
    $url   = isset($_SERVER['REQUEST_URI']) ? esc_url_raw($_SERVER['REQUEST_URI']) : '';

    $wpdb->insert(
        af_activity_log_table(),
        array(
            'created_at' => current_time('mysql'),
            'user_id'    => $uid,
            'user_login' => substr($login, 0, 120),
            'ip'         => substr(af_activity_client_ip(), 0, 45),
            'action'     => substr($action, 0, 255),
            'url'        => substr($url, 0, 255),
        ),
        array('%s', '%d', '%s', '%s', '%s', '%s')
    );

    // Occasionally prune entries older than 90 days so the table stays bounded.
    if (mt_rand(1, 40) === 1) {
        $cutoff = date('Y-m-d H:i:s', current_time('timestamp') - 90 * DAY_IN_SECONDS);
        $wpdb->query($wpdb->prepare(
            'DELETE FROM ' . af_activity_log_table() . ' WHERE created_at < %s', $cutoff
        ));
    }
}

// Whether the current front-end request is a real page view worth logging.
function af_activity_should_log_request() {
    if (!is_user_logged_in()) return false;
    if (is_admin()) return false;
    if (defined('DOING_AJAX') && DOING_AJAX) return false;
    if (defined('DOING_CRON') && DOING_CRON) return false;
    if (defined('REST_REQUEST') && REST_REQUEST) return false;
    if (function_exists('wp_is_json_request') && wp_is_json_request()) return false;
    if (function_exists('is_feed') && is_feed()) return false;
    // Don't log admins opening our own tool pages — keeps the log about real
    // storefront activity rather than filling with self-views.
    if (function_exists('is_page') && is_page(array('activity-log','user-activity-log','inventory-management','inventory','admin-console','admin'))) return false;
    return true;
}

// A human-readable label for the current front-end page.
function af_activity_page_label() {
    if (is_front_page() || is_home()) return 'Visited homepage';
    if (function_exists('is_product') && is_product()) {
        return 'Viewed product: ' . html_entity_decode(wp_strip_all_tags(get_the_title()));
    }
    if (function_exists('is_product_category') && is_product_category()) {
        $o = get_queried_object();
        return 'Viewed category: ' . ($o ? html_entity_decode(wp_strip_all_tags($o->name)) : '');
    }
    if (function_exists('is_product_tag') && is_product_tag()) {
        $o = get_queried_object();
        return 'Viewed tag: ' . ($o ? html_entity_decode(wp_strip_all_tags($o->name)) : '');
    }
    if (function_exists('is_shop') && is_shop()) return 'Viewed shop';
    if (is_search()) return 'Searched: "' . html_entity_decode(wp_strip_all_tags(get_search_query())) . '"';
    if (function_exists('is_cart') && is_cart()) return 'Viewed cart';
    if (function_exists('is_checkout') && is_checkout()) {
        return (function_exists('is_order_received_page') && is_order_received_page())
            ? 'Reached order confirmation' : 'Viewed checkout';
    }
    if (function_exists('is_account_page') && is_account_page()) return 'Viewed my account';
    if (is_page()) return 'Viewed page: ' . html_entity_decode(wp_strip_all_tags(get_the_title()));
    if (is_singular()) return 'Viewed: ' . html_entity_decode(wp_strip_all_tags(get_the_title()));
    if (is_category() || is_tag() || is_tax()) {
        $o = get_queried_object();
        return 'Browsed: ' . ($o && isset($o->name) ? html_entity_decode(wp_strip_all_tags($o->name)) : '');
    }
    return 'Viewed a page';
}

// Log the page view once the main query is resolved (so conditional tags work).
add_action('template_redirect', function(){
    if (!af_activity_should_log_request()) return;
    af_activity_log_record(af_activity_page_label());
}, 9999);

// Key WooCommerce / auth events — the "what a user does" beyond plain views.
add_action('woocommerce_add_to_cart', function($key, $product_id, $quantity){
    if (!is_user_logged_in()) return;
    $name = html_entity_decode(wp_strip_all_tags(get_the_title($product_id)));
    af_activity_log_record('Added to cart: ' . $name . ' (x' . (int) $quantity . ')');
}, 10, 3);

add_action('woocommerce_checkout_order_processed', function($order_id){
    if (!is_user_logged_in()) return;
    $order = function_exists('wc_get_order') ? wc_get_order($order_id) : null;
    $total = $order ? html_entity_decode(wp_strip_all_tags(wc_price($order->get_total()))) : '';
    af_activity_log_record('Placed order #' . (int) $order_id . ($total ? ' (' . $total . ')' : ''));
}, 10, 1);

add_action('wp_login', function($user_login, $user){
    af_activity_log_record('Logged in', $user);
}, 10, 2);

add_action('wp_logout', function($user_id){
    $u = $user_id ? get_user_by('id', $user_id) : null;
    if ($u) af_activity_log_record('Logged out', $u);
}, 10, 1);

// Admin-bar shortcut to the log, nested under the Admin Console parent node.
add_action('admin_bar_menu', function($bar){
    if (!af_log_can_access()) return;
    $bar->add_node(array(
        'id'     => 'af-activity-log',
        'parent' => 'af-admin-console',
        'title'  => 'Audit',
        'href'   => af_log_url(),
        'meta'   => array('title' => 'View the audit log'),
    ));
}, 81);

// ── Admin-only viewer page /activity-log/ ───────────────────────────────────
add_action('template_redirect', function(){
    if (!function_exists('is_page') || !is_page(array('activity-log','user-activity-log'))) return;
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        wp_die('You do not have permission to view this page.', 'Access Denied',
            array('response' => 403, 'back_link' => true));
    }
    af_activity_log_ensure_table();
    global $wpdb;
    $table = af_activity_log_table();
    $limit = 1000;
    $raw = $wpdb->get_results($wpdb->prepare(
        "SELECT created_at, user_login, user_id, ip, action, url FROM {$table} ORDER BY id DESC LIMIT %d",
        $limit
    ), ARRAY_A);
    $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

    // Resolve each user's email at display time (not stored), cached per id so
    // a user who appears in many rows is only looked up once. Falls back to
    // empty if the account was since deleted.
    $email_cache = array();
    $rows = array();
    foreach ((array) $raw as $r) {
        $ts  = strtotime($r['created_at']);
        $uid = (int) $r['user_id'];
        if ($uid && !array_key_exists($uid, $email_cache)) {
            $u = get_userdata($uid);
            $email_cache[$uid] = ($u && $u->user_email) ? $u->user_email : '';
        }
        $rows[] = array(
            'time'  => $ts ? date_i18n('M j, Y g:i a', $ts) : $r['created_at'],
            'user'  => $r['user_login'] !== '' ? $r['user_login'] : ('#' . $uid),
            'email' => $uid ? $email_cache[$uid] : '',
            'ip'    => $r['ip'],
            'act'   => $r['action'],
            'url'   => $r['url'],
        );
    }

    get_header();
    ?>
    <div class="af-log-wrap">
      <header class="af-log-head">
        <div>
          <p class="af-log-eyebrow">Admin only</p>
          <h1>Audit</h1>
          <p class="af-log-sub">Timestamped record of what signed-in visitors do on the site. Showing the most recent <?php echo (int) count($rows); ?> of <?php echo (int) $total; ?> entries. Entries older than 90 days are pruned automatically.</p>
        </div>
        <a class="af-log-home" href="<?php echo esc_url(home_url('/')); ?>">Back to site</a>
      </header>

      <div class="af-log-toolbar">
        <input type="search" id="log-search" class="af-log-search" placeholder="Search user, email, IP or action&hellip;" autocomplete="off">
      </div>

      <div class="af-log-tablewrap">
        <table class="af-log-table">
          <thead><tr>
            <th class="af-log-thtime">Time</th>
            <th class="af-log-thuser">User</th>
            <th class="af-log-themail">Email</th>
            <th class="af-log-thip">IP address</th>
            <th>Action</th>
          </tr></thead>
          <tbody id="log-body"></tbody>
        </table>
        <p class="af-log-empty" id="log-empty" hidden>No entries match that search.</p>
      </div>
    </div>

    <script>
    (function(){
      var ROWS = <?php echo wp_json_encode($rows); ?>;
      var $ = function(id){ return document.getElementById(id); };
      var term = '';
      function esc(s){ var d=document.createElement('div'); d.textContent = s==null?'':String(s); return d.innerHTML; }

      function render(){
        var list = ROWS.filter(function(r){
          if (!term) return true;
          return (r.user + ' ' + (r.email||'') + ' ' + r.ip + ' ' + r.act).toLowerCase().indexOf(term) !== -1;
        });
        $('log-empty').hidden = list.length > 0;
        $('log-body').innerHTML = list.map(function(r){
          var act = r.url
            ? '<a href="' + esc(r.url) + '" target="_blank" rel="noopener">' + esc(r.act) + '</a>'
            : esc(r.act);
          return '<tr>'
            + '<td class="af-log-tdtime">' + esc(r.time) + '</td>'
            + '<td class="af-log-tduser">' + esc(r.user) + '</td>'
            + '<td class="af-log-tdemail">' + (r.email ? esc(r.email) : '<span class="af-log-dash">&mdash;</span>') + '</td>'
            + '<td class="af-log-tdip">' + (r.ip ? esc(r.ip) : '<span class="af-log-dash">&mdash;</span>') + '</td>'
            + '<td class="af-log-tdact">' + act + '</td>'
            + '</tr>';
        }).join('');
      }
      $('log-search').addEventListener('input', function(){ term = this.value.trim().toLowerCase(); render(); });
      render();
    })();
    </script>

    <style>
    .af-log-wrap{max-width:1080px;margin:0 auto;padding:34px 18px 70px;
      background:linear-gradient(180deg,#f6f1e6 0%,#efe7d6 100%);}
    .af-log-head{display:flex;flex-wrap:wrap;gap:16px;align-items:flex-start;justify-content:space-between;margin:0 0 22px;}
    .af-log-eyebrow{margin:0 0 6px;font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;
      color:#a8801f;background:#f3ead2;border:1px solid #e6d7ad;border-radius:999px;display:inline-block;padding:4px 11px;}
    .af-log-head h1{margin:0 0 8px;font-family:'Playfair Display',Georgia,serif;font-size:32px;color:#1a1a1a;line-height:1.15;}
    .af-log-sub{margin:0;font-size:13px;color:#6b6250;max-width:64ch;line-height:1.6;}
    .af-log-home{align-self:center;background:#1a1a1a;color:#fff;text-decoration:none;font-size:12.5px;font-weight:700;
      padding:11px 18px;border-radius:9px;transition:background .2s;white-space:nowrap;}
    .af-log-home:hover{background:#c9a84c;color:#fff;}
    .af-log-toolbar{margin:0 0 14px;}
    .af-log-search{width:100%;max-width:420px;height:42px;padding:0 14px;border:1.5px solid #e2d9c4;border-radius:10px;
      background:#fffdf8;color:#1a1a1a;font-size:14px;}
    .af-log-search:focus{outline:none;border-color:#c9a84c;}
    .af-log-tablewrap{background:#fffdf8;border:1px solid #efe6d2;border-radius:14px;overflow:auto;max-height:72vh;
      box-shadow:0 4px 18px rgba(70,54,26,.07);}
    .af-log-table{width:100%;border-collapse:collapse;font-size:13px;min-width:820px;}
    .af-log-table thead th{position:sticky;top:0;background:#f3ead2;color:#6b6250;text-align:left;
      font-size:11px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;padding:11px 14px;
      border-bottom:1px solid #e6d7ad;z-index:1;}
    .af-log-table tbody td{padding:9px 14px;border-bottom:1px solid #f0e9da;vertical-align:top;color:#1a1a1a;}
    .af-log-table tbody tr:last-child td{border-bottom:none;}
    .af-log-table tbody tr:hover{background:#fdf9ef;}
    .af-log-thtime,.af-log-tdtime{white-space:nowrap;color:#6b6250;}
    .af-log-thuser,.af-log-tduser{white-space:nowrap;font-weight:700;}
    .af-log-themail,.af-log-tdemail{white-space:nowrap;color:#5a5140;font-size:12.5px;}
    .af-log-thip,.af-log-tdip{white-space:nowrap;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12px;color:#5a5140;}
    .af-log-tdact a{color:#1a1a1a;text-decoration:none;}
    .af-log-tdact a:hover{color:#c9a84c;}
    .af-log-dash{color:#b8b0a0;}
    .af-log-empty{margin:0;padding:34px 16px;text-align:center;color:#8a8170;font-size:14px;}
    @media(max-width:640px){ .af-log-wrap{padding:24px 12px 54px;} .af-log-head h1{font-size:26px;} }
    </style>
    <?php
    get_footer();
    exit;
}, 1);

// ---------------------------------------------------------------------------
// PHASE 30 — "Download Brochure" (printed collection book, PDF)
// Added 2026-08-08. One canonical PDF in the media library, linked from every
// product card, the single-product page, the shop/category header and the main
// menu, so a visitor can always grab the printed catalogue.
//
// The source file supplied was 1.1 GB (358 full-bleed pages) — far too large to
// serve to visitors. It is re-encoded to ~41 MB with no visible quality loss
// before upload (PyMuPDF re-render at 1.05x, JPEG q62). To replace the brochure
// later: upload the new PDF and set the `taf_brochure_url` option (or hook the
// filter of the same name) — nothing else here needs to change.
// ---------------------------------------------------------------------------
define('TAF_BROCHURE_FALLBACK',
  'https://theartframer.us/wp-content/uploads/2026/08/TheArtFramer-Collection-Brochure.pdf');
define('TAF_BROCHURE_SIZE', '41 MB');

function taf_brochure_url() {
  $url = get_option('taf_brochure_url', '');
  if (!$url) { $url = TAF_BROCHURE_FALLBACK; }
  return esc_url(apply_filters('taf_brochure_url', $url));
}

/**
 * Brochure anchor. Always an <a>, never a <button>: a global rule elsewhere in
 * the site collapses padding on bare <button> elements, which would squash it.
 * `download` asks the browser to save the 41 MB file instead of opening it
 * inline in the PDF viewer.
 */
function taf_brochure_link($variant = 'card') {
  $url = taf_brochure_url();
  if (!$url) return '';
  // an open book, not a download arrow — the button views, it does not save
  $icon = '<svg class="taf-broch-ico" viewBox="0 0 24 24" width="15" height="15" aria-hidden="true" focusable="false">'
        . '<path fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"'
        . ' d="M2 4.5A2.5 2.5 0 0 1 4.5 2H11v17.5H4.5A2.5 2.5 0 0 0 2 22V4.5zM22 4.5A2.5 2.5 0 0 0 19.5 2H13v17.5h6.5A2.5 2.5 0 0 1 22 22V4.5zM11 19.5v.01M13 19.5v.01"/></svg>';
  // "View", not "Download": the link opens the PDF in a browser tab (no
  // download attribute), so the visitor reads the collection book without a
  // 41 MB file landing in their downloads folder. Saving stays their choice
  // from the viewer.
  $label = 'View Brochure';
  return '<a class="taf-broch taf-broch--' . esc_attr($variant) . '" href="' . $url . '"'
       . ' target="_blank" rel="noopener"'
       . ' title="View the printed collection book (PDF, ' . esc_attr(TAF_BROCHURE_SIZE) . ')">'
       . $icon . '<span class="taf-broch-txt">' . $label . '</span></a>';
}

// 1. Single product page — button under the add-to-cart area.
add_action('woocommerce_single_product_summary', function() {
  echo '<div class="taf-broch-wrap">' . taf_brochure_link('single')
     . '<span class="taf-broch-note">PDF &middot; ' . esc_html(TAF_BROCHURE_SIZE)
     . ' &middot; 358 pages</span></div>';
}, 35);

// 2. Standard WooCommerce loop cards (shop, category, tag, related, up-sells).
add_action('woocommerce_after_shop_loop_item', function() {
  echo taf_brochure_link('card');
}, 15);

// 3. Shop / product-archive header strip.
add_action('woocommerce_before_shop_loop', function() {
  echo '<div class="taf-broch-banner"><span class="taf-broch-banner-txt">'
     . 'Browse all 358 pages of our printed collection book &mdash; every artwork with its Art Code.'
     . '</span>' . taf_brochure_link('banner') . '</div>';
}, 8);

// 4. Main-menu item, so the brochure is one click away from any page.
add_filter('wp_nav_menu_items', function($items, $args) {
  $loc = isset($args->theme_location) ? $args->theme_location : '';
  if (!in_array($loc, array('primary', 'menu-1', 'main', 'header'), true)) return $items;
  if (strpos($items, 'taf-broch-nav') !== false) return $items;
  return $items . '<li class="menu-item taf-broch-nav"><a href="' . taf_brochure_url()
       . '" target="_blank" rel="noopener">Brochure</a></li>';
}, 10, 2);

add_action('wp_head', function() { ?>
<style>
/* Matches the store's existing button language exactly — pill radius 50px,
   uppercase, 2px tracking, gold #c9a84c border with #8a6d1f text — so the
   brochure reads as a native secondary action next to "Show Dimensions"
   rather than a bolted-on box. Never a solid fill: the solid gold #ca9236 is
   reserved for Add to Cart and a second solid button would fight it. */
.taf-broch{display:inline-flex!important;align-items:center;justify-content:center;
  gap:8px;text-decoration:none;font-weight:700;text-transform:uppercase;
  letter-spacing:2px;line-height:1.15;border-radius:50px;
  border:.8px solid #c9a84c;background:#fff;color:#8a6d1f;
  box-shadow:none;text-align:center;
  transition:background .18s,border-color .18s,color .18s,box-shadow .18s;}
.taf-broch:hover,.taf-broch:focus{background:#c9a84c;border-color:#c9a84c;
  color:#fff;box-shadow:0 2px 12px rgba(201,168,76,.32);}
.taf-broch .taf-broch-ico{flex:0 0 auto;opacity:.85;}
.taf-broch:hover .taf-broch-ico{opacity:1;}
/* card — a full-width bar under Add to Cart and Quick View, squared off and
   in its own colour so the three actions read as three distinct things:
   gold buys, black previews, teal downloads the book. Width and side margins
   match the card's Add to Cart exactly (calc(100% - 28px) / 0 14px 14px), so
   the three line up as one stack whatever section rendered the card. */
.taf-broch-cardwrap{display:block;}
.taf-broch--card{display:flex!important;width:calc(100% - 28px);box-sizing:border-box;
  font-size:11px;padding:11px 12px;margin:0 14px 14px;border-radius:0;
  background:transparent;border:1.5px solid #c9a84c;color:#8a6d1f;}
.taf-broch--card:hover,.taf-broch--card:focus{background:#c9a84c;border-color:#c9a84c;
  color:#fff;box-shadow:0 2px 12px rgba(201,168,76,.32);}
.taf-broch--card .taf-broch-ico{width:13px;height:13px;opacity:1;}
/* single product page — sits with Show Dimensions, size as a caption below */
.taf-broch-wrap{margin:16px 0 12px;display:flex;flex-direction:column;
  align-items:flex-start;gap:7px;}
.taf-broch--single{font-size:12.5px;padding:14px 26px;}
.taf-broch-note{font-size:11px;letter-spacing:1.4px;text-transform:uppercase;
  color:#a49c8b;font-weight:600;}
/* archive banner */
.taf-broch-banner{display:flex;flex-wrap:wrap;align-items:center;gap:12px 20px;
  justify-content:space-between;margin:0 0 24px;padding:16px 22px;
  border:.8px solid rgba(201,168,76,.55);border-radius:14px;
  background:linear-gradient(180deg,rgba(201,168,76,.08),rgba(201,168,76,.02));}
.taf-broch-banner-txt{font-size:14px;line-height:1.45;color:#5a5140;flex:1 1 280px;}
.taf-broch--banner{font-size:12px;padding:13px 24px;white-space:nowrap;}
@media(max-width:600px){
  .taf-broch-banner{padding:14px 16px;}
  .taf-broch-wrap{align-items:stretch;}
  .taf-broch--banner,.taf-broch--single{width:100%;}
  .taf-broch-note{text-align:center;}
}
</style>
<?php });

// 5. Cards rendered outside the standard Woo loop (Postero homepage carousels,
//    Elementor product widgets, search results). Same reason as PHASE 25b: those
//    templates never fire `woocommerce_after_shop_loop_item`, so inject in JS.
//    Unlike the Art Code this needs no per-product lookup — the link is the same
//    for every product, so it only has to recognise a product card.
add_action('wp_footer', function() {
  if (is_admin()) return; ?>
<script>
(function(){
  var HTML   = <?php echo wp_json_encode(taf_brochure_link('card')); ?>;
  var BANNER = <?php echo wp_json_encode(taf_brochure_link('banner')); ?>;
  var BLURB  = 'Browse all 358 pages of our printed collection book — every artwork with its Art Code.';
  var URL    = <?php echo wp_json_encode(taf_brochure_url()); ?>;
  if (!HTML) return;
  function add(card){
    if (card.getAttribute('data-taf-broch')) return;
    card.setAttribute('data-taf-broch','1');
    if (card.querySelector('.taf-broch--card')) return;   // PHP hook already handled it
    var w = document.createElement('div');
    w.className = 'taf-broch-cardwrap';
    w.innerHTML = HTML;
    card.appendChild(w);
  }
  function run(){
    var cards = document.querySelectorAll('li.product, .product-card, .trending-card, [class*="type-product"]');
    Array.prototype.forEach.call(cards, function(c){
      if (document.body.classList.contains('single-product') && c.closest('.summary')) return;
      if (c.querySelector('.taf-broch--single')) return;  // this is the single-product body
      if (!c.querySelector('a[href]')) return;            // not a real card
      add(c);
    });
    banner();
    navItem();
  }
  // Archive banner. `woocommerce_before_shop_loop` never fires on the Postero
  // shop template, so place it in front of the products grid client-side.
  function banner(){
    if (!BANNER || document.querySelector('.taf-broch-banner')) return;
    var b = document.body.className || '';
    if (!/(post-type-archive-product|tax-product_cat|tax-product_tag|woocommerce-shop|search-results)/.test(b)) return;
    var grid = document.querySelector('ul.products, ul.postero-products, .products');
    if (!grid || !grid.parentNode) return;
    var d = document.createElement('div');
    d.className = 'taf-broch-banner';
    d.innerHTML = '<span class="taf-broch-banner-txt">' + BLURB + '</span>' + BANNER;
    grid.parentNode.insertBefore(d, grid);
  }
  // Primary menu item. The header menu is built by Elementor, so it carries no
  // theme_location and the PHP `wp_nav_menu_items` filter never matches it.
  // Append to the FIRST top-level menu inside the header nav only — never the
  // sub-menus or the footer menu, or the link would appear several times.
  function navItem(){
    if (document.querySelector('.taf-broch-nav')) return;
    var ul = document.querySelector('nav.main-navigation ul.menu, .primary-navigation > ul.menu');
    if (!ul || ul.closest('.sub-menu')) return;
    var li = document.createElement('li');
    li.className = 'menu-item taf-broch-nav';
    li.innerHTML = '<a href="' + URL + '"'
                 + ' target="_blank" rel="noopener"><span class="menu-title">Brochure</span></a>';
    ul.appendChild(li);
  }
  document.addEventListener('DOMContentLoaded', run);
  window.addEventListener('load', run);
  [400,1200,2500].forEach(function(d){ setTimeout(run,d); });
  try {
    var obs = new MutationObserver(function(m){
      for (var i=0;i<m.length;i++){ if (m[i].addedNodes && m[i].addedNodes.length){ run(); break; } }
    });
    obs.observe(document.body,{childList:true,subtree:true});
  } catch(e){}
})();
</script>
<?php }, 60);

// ─────────────────────────────────────────────────────────────
// THE WISHLIST PAGE
//
// It shipped as the plugin's bare table: one hairline-bordered row per saved
// piece, a date column nobody asked for, and an "add to cart" control that
// rendered as a shopping-bag EMOJI because the button carries no label of its
// own. There was nothing to browse afterwards either — a saved piece was a
// dead end rather than the start of a visit.
//
// Three things happen here, all scoped to this page so no other template
// changes: the row becomes a card that matches the rest of the store, the
// emoji becomes a proper Add to Cart button in the house gold, and a "You may
// also like" row is appended, built from the categories of whatever is
// actually saved (falling back to the best sellers when nothing is).
// ─────────────────────────────────────────────────────────────

/** Is the page being rendered the wishlist? */
function af_is_wishlist_page() {
    if (is_admin()) return false;
    if (function_exists('YITH_WCWL') && method_exists(YITH_WCWL(), 'is_wishlist_page')) {
        if (YITH_WCWL()->is_wishlist_page()) return true;
    }
    if (function_exists('is_page') && (is_page('wishlist') || is_page('Wishlist'))) return true;
    $path = trim((string) wp_parse_url(add_query_arg(array()), PHP_URL_PATH), '/');
    return $path !== '' && strpos($path, 'wishlist') === 0;
}

function af_wl_related_html($markup) {
    if (!function_exists('wc_get_products')) return '';

    $ids = array();
    if (preg_match_all('/add-to-cart=(\d+)/', $markup, $m)) $ids = array_map('intval', $m[1]);
    if (preg_match_all('/data-product-id=["\'](\d+)["\']/', $markup, $m2)) {
        $ids = array_merge($ids, array_map('intval', $m2[1]));
    }
    $ids = array_values(array_unique(array_filter($ids)));

    // categories of what is saved — "related" should mean related to THIS
    // wishlist, not a generic carousel
    $cats = array();
    foreach ($ids as $pid) {
        $terms = get_the_terms($pid, 'product_cat');
        if (!$terms || is_wp_error($terms)) continue;
        foreach ($terms as $t) {
            if (in_array($t->slug, array('uncategorized'), true)) continue;
            $cats[$t->slug] = true;
        }
    }

    $args = array('status' => 'publish', 'limit' => 8, 'orderby' => 'popularity',
                  'visibility' => 'catalog', 'exclude' => $ids);
    if ($cats) $args['category'] = array_keys($cats);
    $picks = wc_get_products($args);
    if (count($picks) < 4) {                       // thin category: top up with best sellers
        $more = wc_get_products(array('status' => 'publish', 'limit' => 8, 'orderby' => 'popularity',
                                      'visibility' => 'catalog',
                                      'exclude' => array_merge($ids, wp_list_pluck($picks, 'id'))));
        $picks = array_merge($picks, $more);
    }
    if (!$picks) return '';
    $picks = array_slice($picks, 0, 8);

    ob_start();
    // The wrapper carries the woocommerce class deliberately: the shop's card
    // styling is written as ".woocommerce ul.products li.product …", so without
    // it these cards fall back to unstyled defaults and need hand-written css —
    // which is exactly what went wrong here before.
    echo '<section class="af-wl-related woocommerce"><h2>You may also like</h2>';
    echo '<ul class="products columns-4">';
    $keep = isset($GLOBALS['post']) ? $GLOBALS['post'] : null;
    foreach ($picks as $p) {
        $po = get_post($p->get_id());
        if (!$po) continue;
        $GLOBALS['post'] = $po;
        setup_postdata($GLOBALS['post']);
        wc_get_template_part('content', 'product');
    }
    $GLOBALS['post'] = $keep;
    wp_reset_postdata();
    echo '</ul></section>';
    return ob_get_clean();
}

// ─────────────────────────────────────────────────────────────
// CROSS-SELL ON THIN CATEGORY PAGES
// A category holding one product renders one card against an acre of empty
// grid — "Showing the single result" and nothing to look at. The visitor
// arrived wanting art of that kind, so rather than leaving them to hit Back,
// show what else the studio has that is close to it.
//
// "Close to it" means siblings first: the categories sharing this one's
// parent, plus whatever else the products already on the page are filed
// under. Only when that comes up short does it fall back to the shop's
// popular pieces, so the row is a recommendation before it is filler.
// ─────────────────────────────────────────────────────────────

/** A full row of cards. Fewer than this on an archive and the page looks broken. */
function af_xsell_min_cards() {
    return (int) apply_filters('af_xsell_min_cards', 4);
}

/**
 * The tags the visitor actually chose, however they chose them: a
 * /product-tag/ archive, or the "Filter by Tag" bar, which filters in place
 * with ?product_tag= instead of navigating away.
 */
function af_xsell_chosen_tags() {
    $slugs = array();
    $term  = get_queried_object();
    if ($term && !empty($term->taxonomy) && $term->taxonomy === 'product_tag' && !empty($term->slug)) {
        $slugs[$term->slug] = true;
    }
    if (!empty($_GET['product_tag'])) {
        foreach (explode(',', (string) wp_unslash($_GET['product_tag'])) as $one) {
            $one = sanitize_title($one);
            if ($one !== '') $slugs[$one] = true;
        }
    }
    return array_keys($slugs);
}

/**
 * Tags that read as "more like this": the chosen ones first, then the tags
 * the products already on the page also carry.
 *
 * The second half is what makes a one-result tag useful. Filter to
 * "krishna wall art", find two pieces, and the tag itself has nothing left to
 * give — but those two are also tagged Spiritual, Hindu Deities, Canvas Art,
 * and that is a far better answer than the shop's bestsellers.
 */
function af_xsell_related_tags($shown_ids) {
    $slugs = array();
    foreach (af_xsell_chosen_tags() as $chosen) $slugs[$chosen] = true;
    foreach ((array) $shown_ids as $pid) {
        $terms = get_the_terms($pid, 'product_tag');
        if (!$terms || is_wp_error($terms)) continue;
        foreach ($terms as $t) $slugs[$t->slug] = true;
    }
    return array_slice(array_keys($slugs), 0, 12);
}

/** Category slugs that read as "similar" to the archive currently being viewed. */
function af_xsell_related_slugs($shown_ids) {
    $slugs = array();
    $term  = get_queried_object();

    if ($term && !empty($term->term_id) && !empty($term->taxonomy) && $term->taxonomy === 'product_cat') {
        // Siblings under the same parent. A top-level category has no siblings
        // worth pairing with, so use its own children instead.
        $sibs = get_terms(array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => true,
            'parent'     => (int) $term->parent,
            'exclude'    => array((int) $term->term_id),
        ));
        if (is_wp_error($sibs) || !$sibs) {
            $sibs = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => true,
                                    'parent' => (int) $term->term_id));
        }
        if (!is_wp_error($sibs)) foreach ($sibs as $s) $slugs[$s->slug] = true;
    }

    // Whatever the products already on the page are filed under — a lone
    // penguin poster in a thin category is still the best clue we have.
    foreach ((array) $shown_ids as $pid) {
        $terms = get_the_terms($pid, 'product_cat');
        if (!$terms || is_wp_error($terms)) continue;
        foreach ($terms as $t) {
            if ($t->slug === 'uncategorized') continue;
            if ($term && !empty($term->slug) && $t->slug === $term->slug) continue;
            $slugs[$t->slug] = true;
        }
    }
    return array_slice(array_keys($slugs), 0, 12);
}

/**
 * Walk the stages narrowest-first and take products until the row is full,
 * never repeating one that is already on the page or already picked.
 *
 * Each stage is a fragment merged into a wc_get_products() call — array('tag'
 * => …), array('category' => …), or array() for the whole shop.
 */
function af_xsell_fill($stages, $exclude, $want) {
    $picks = array();
    $have  = (array) $exclude;
    foreach ((array) $stages as $narrow) {
        if (count($picks) >= $want) break;
        $found = wc_get_products(array_merge(array(
            'status' => 'publish', 'limit' => $want - count($picks),
            'orderby' => 'popularity', 'visibility' => 'catalog', 'exclude' => $have,
        ), (array) $narrow));
        foreach ((array) $found as $f) {
            if (count($picks) >= $want) break;
            $id = $f->get_id();
            if (in_array($id, $have, true)) continue;   // a stage may overlap the last
            $picks[] = $f;
            $have[]  = $id;
        }
    }
    return $picks;
}

/**
 * Print the row. Runs at most once per request — an archive with no products
 * fires woocommerce_no_products_found, one with a few fires
 * woocommerce_after_shop_loop, and a theme may well fire both.
 */
function af_xsell_render() {
    static $done = false;
    if ($done) return;
    if (!function_exists('wc_get_products') || !function_exists('is_product_category')) return;
    // A tag chosen from the "Filter by Tag" bar filters the listing in place
    // with ?product_tag=, so the page can still be the plain shop archive —
    // which is exactly where a thin result most needs the row.
    $tag_filtered = !empty($_GET['product_tag']);
    $on_listing   = is_product_category() || is_product_tag()
                    || ($tag_filtered && function_exists('is_shop') && is_shop());
    if (!$on_listing) return;
    if (is_paged()) return;                       // page 2+ is not a thin page

    global $wp_query;
    $shown_ids = array();
    if (isset($wp_query->posts) && is_array($wp_query->posts)) {
        foreach ($wp_query->posts as $p) {
            if (isset($p->ID)) $shown_ids[] = (int) $p->ID;
        }
    }
    if (count($shown_ids) >= af_xsell_min_cards()) return;   // the grid stands on its own

    // Fill the row in order of how closely each source answers what the
    // visitor asked for, and stop as soon as it is full. Ordering the sources
    // rather than merging them is the point: a single query over chosen tags
    // AND co-occurring tags AND sibling categories is an OR, so the shop's
    // bestsellers can outrank the one other piece in the tag that was
    // actually chosen. Graded stages cannot do that — nothing from a broader
    // source is looked at while a narrower one still has something to give.
    $want   = 8;
    $chosen = af_xsell_chosen_tags();
    $stages = array();
    if ($chosen)                              $stages[] = array('tag' => $chosen);
    if ($cotags = af_xsell_related_tags($shown_ids)) $stages[] = array('tag' => $cotags);
    if ($cats   = af_xsell_related_slugs($shown_ids)) $stages[] = array('category' => $cats);
    $stages[] = array();                      // whatever the studio sells best

    $picks = af_xsell_fill($stages, $shown_ids, $want);

    if (!$picks) return;
    $picks = array_slice($picks, 0, $want);
    $done  = true;

    // Say what the row IS. "You may also like" under a tag the visitor picked
    // themselves reads as filler; naming the tag says these were chosen.
    $title = 'You may also like';
    $sub   = 'More from the studio, close to what you were looking at.';
    if ($chosen) {
        $ct = get_term_by('slug', $chosen[0], 'product_tag');
        if ($ct && !is_wp_error($ct)) {
            $title = 'More in ' . $ct->name;
            $sub   = 'Other pieces tagged ' . $ct->name . ', and a few close to them.';
        }
    }

    echo '<section class="af-xsell"><h2>' . esc_html($title) . '</h2>'
       . '<p class="af-xsell-sub">' . esc_html($sub) . '</p>'
       . '<ul class="products columns-4">';
    $keep = isset($GLOBALS['post']) ? $GLOBALS['post'] : null;
    foreach ($picks as $p) {
        $po = get_post($p->get_id());
        if (!$po) continue;
        $GLOBALS['post'] = $po;
        setup_postdata($GLOBALS['post']);
        wc_get_template_part('content', 'product');
    }
    $GLOBALS['post'] = $keep;
    wp_reset_postdata();
    echo '</ul></section>';
    ?>
<style id="af-xsell-style">
.af-xsell{max-width:1200px;margin:40px auto 56px;padding:0 16px;clear:both}
.af-xsell h2{font-size:22px;margin:0 0 4px}
.af-xsell .af-xsell-sub{margin:0 0 18px;font-size:13.5px;opacity:.7}
.af-xsell ul.products{display:grid!important;grid-template-columns:repeat(4,1fr)!important;gap:20px!important;margin:0!important;padding:0!important;list-style:none!important}
.af-xsell ul.products::before,.af-xsell ul.products::after{display:none!important}
.af-xsell ul.products li.product{width:100%!important;margin:0!important;float:none!important}
.af-xsell ul.products li.product img{width:100%!important;height:auto!important;aspect-ratio:1/1;object-fit:cover!important}
@media (max-width:1024px){.af-xsell ul.products{grid-template-columns:repeat(3,1fr)!important}}
@media (max-width:760px){.af-xsell ul.products{grid-template-columns:repeat(2,1fr)!important}}
</style>
    <?php
}
// Three entry points because a thin archive and an empty one fire different
// hooks, and a theme that renders its own loop may fire neither — the static
// guard inside means the extra hooks cost nothing when an earlier one lands.
add_action('woocommerce_after_shop_loop', 'af_xsell_render', 30);
add_action('woocommerce_no_products_found', 'af_xsell_render', 30);
add_action('woocommerce_after_main_content', 'af_xsell_render', 30);

// Path 1: the wishlist arrives via a shortcode. Match ANY shortcode whose name
// mentions the wishlist — the first version accepted only names starting
// yith_wcwl, and the owner's recording proved the page renders through
// something else entirely.
add_filter('do_shortcode_tag', function ($output, $tag) {
    if (is_admin() || stripos((string) $tag, 'wishlist') === false && strpos((string) $tag, 'wcwl') === false && strpos((string) $tag, 'tinvwl') === false) return $output;
    if (!is_string($output) || $output === '' || strpos($output, 'af-wl-related') !== false) return $output;
    if (!af_is_wishlist_page()) return $output;
    $extra = af_wl_related_html($output);
    return $extra === '' ? $output : $output . $extra;
}, 20, 2);

// Path 2: whatever rendered the items, they end up in the page content. After
// the shortcodes have run (priority 99), append the row if path 1 did not.
add_filter('the_content', function ($content) {
    if (is_admin() || !is_string($content)) return $content;
    if (!af_is_wishlist_page() || !in_the_loop() || !is_main_query()) return $content;
    if (strpos($content, 'af-wl-related') !== false) return $content;
    $extra = af_wl_related_html($content);
    return $extra === '' ? $content : $content . $extra;
}, 99);

add_action('wp_footer', function () {
    if (!af_is_wishlist_page()) return;
    ?>
<style id="af-wishlist-style">
.af-wl-head{max-width:1200px;margin:24px auto 8px;padding:0 16px}
.af-wl-head h1{font-size:30px;margin:0 0 4px;color:#1a1a1a}
.af-wl-head p{margin:0;color:#6b6b6b;font-size:14px}

/* the plugin's table, read as a list of cards.
   The page is rendered by WPC Smart Wishlist ([woosw_list], woosw- classes) —
   verified from the served page; the .wishlist_table selectors are kept for
   any template that still uses them. */
.woosw-list table.woosw-items,
.wishlist_table{border:0!important;border-collapse:separate!important;border-spacing:0 14px!important;width:100%!important;background:transparent!important}
.woosw-items thead{display:none!important}
.woosw-items tr,.woosw-item{background:#fff!important;box-shadow:0 2px 14px rgba(0,0,0,.07)!important;border-radius:14px!important}
.woosw-items td,.woosw-item td{border:0!important;vertical-align:middle!important;padding:16px 12px!important;background:transparent!important}
.woosw-item td:first-child{border-radius:14px 0 0 14px!important}
.woosw-item td:last-child{border-radius:0 14px 14px 0!important;text-align:right!important}
.woosw-item--image img,.woosw-item img{width:88px!important;height:88px!important;object-fit:cover!important;border-radius:10px!important;display:block!important}
.woosw-item--name a,.woosw-item--info a{color:#1a1a1a!important;font-weight:600!important;font-size:15px!important;line-height:1.45!important;text-decoration:none!important}
.woosw-item--name a:hover{color:#8b6a2b!important}
.woosw-item--price{color:#1a1a1a!important;font-weight:700!important;white-space:nowrap!important}
/* the date a piece was saved is noise next to the piece itself */
.woosw-item--time,.woosw-item--date{display:none!important}

/* remove ("×") */
.woosw-item--remove span,.woosw-item-remove,.woosw-item--remove a{
  width:30px!important;height:30px!important;line-height:28px!important;border-radius:50%!important;
  background:#f4f4f4!important;color:#8a8a8a!important;font-size:17px!important;text-align:center!important;
  display:inline-block!important;transition:background .2s,color .2s!important}
.woosw-item--remove span:hover,.woosw-item-remove:hover{background:#e5c9c9!important;color:#a11!important}

/* the add-to-cart control: a real button, not a bare emoji.
   Written against every name this control ships under, plus a catch-all for
   any add-to-cart link inside the items table — the first pass bound only to
   .wishlist_table descendants and the live page proved that was not enough. */
.woosw-item--add a,
.woosw-item--actions a.button,
.woosw-item td a[href*="add-to-cart="],
body.woocommerce-wishlist a[href*="add-to-cart="],
.wishlist-items a[href*="add-to-cart="],
table a[href*="add-to-cart="].af-wl-labelled{
  display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:8px!important;
  background:#c9a84c!important;border:0!important;color:#fff!important;font-size:14px!important;font-weight:600!important;
  padding:11px 20px!important;border-radius:6px!important;white-space:nowrap!important;text-decoration:none!important;
  transition:background .2s!important}
.woosw-item--add a:hover,
.woosw-item td a[href*="add-to-cart="]:hover,
table a[href*="add-to-cart="].af-wl-labelled:hover{background:#8b6a2b!important}
.af-wl-cart-ico{width:17px;height:17px;flex:0 0 auto}
/* The theme ships this control as a slide-in-on-hover button: parked off the
   card's right edge, translated in when a product card is hovered. Inside the
   wishlist table nothing hovers it, so all that showed was the sliver poking
   past the card. Park it in the row like a normal button. */
.woosw-item .opal-add-to-cart-button,
.woosw-item p.add_to_cart_inline,
.woosw-item--atc,
.woosw-item--atc *{position:static!important;transform:none!important;opacity:1!important;
  visibility:visible!important;margin:0!important;right:auto!important;left:auto!important}
.woosw-item p.add_to_cart_inline{display:inline-block!important;padding:0!important}
.woosw-item--actions{min-width:200px!important;padding-right:18px!important;text-align:right!important}
/* an art-code placeholder escapes its card on this page — a bare
   "ART CODE: …" line floating between sections is noise, hide it */
.woosw-list .af-art-code,.woosw-list ~ .af-art-code,
.entry-content > .af-art-code,.cart_totals .af-art-code{display:none!important}

/* share link row */
.yith-wcwl-share,.wishlist-title,.wishlist_table+form{max-width:1200px;margin-inline:auto}

/* related products */
.af-wl-related{max-width:1200px;margin:44px auto 56px;padding:0 16px}
.af-wl-related h2{font-size:24px;margin:0 0 18px;color:#1a1a1a}
.af-wl-related ul.products{display:grid!important;grid-template-columns:repeat(4,1fr)!important;gap:20px!important;margin:0!important;padding:0!important;list-style:none!important}
.af-wl-related ul.products::before,.af-wl-related ul.products::after{display:none!important}
/* Uniform image box, same measurement the shop uses. The card script builds a
   fixed 300px box (260 under 520px) only for cards shipping TWO images, main
   plus hover. A single-image card never gets that box, so it keeps the natural
   ratio and the row goes ragged. Give those cards the same box; images already
   inside a built box stay under the script control. */
.af-wl-related li.product > a:first-of-type > img:not(.af-main-img):not(.af-hover-img){
  height:300px!important;width:100%!important;object-fit:cover!important;display:block!important}
@media (max-width:520px){
  .af-wl-related li.product > a:first-of-type > img:not(.af-main-img):not(.af-hover-img){height:260px!important}}

@media (max-width:900px){
  .af-wl-related ul.products{grid-template-columns:repeat(2,1fr)!important}
  .wishlist_table td{padding:12px 8px!important}
  .wishlist_table td.product-thumbnail img{width:64px!important;height:64px!important}
  .wishlist_table td.product-name a{font-size:14px!important}
}
</style>
<script>
(function(){
  // The plugin renders its add-to-cart control with no label of its own, which
  // is why the page showed a shopping-bag emoji. Give it the words and the
  // icon the rest of the store uses. Runs again when the table re-renders.
  var CART = '<svg class="af-wl-cart-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
           + 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
           + '<circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle>'
           + '<path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>';

  function label(a){
    if (a.dataset.afWlDone) return;
    a.dataset.afWlDone = '1';
    a.classList.add('button');
    a.classList.add('af-wl-labelled');
    a.innerHTML = CART + '<span>Add to Cart</span>';
    a.setAttribute('aria-label', 'Add to cart');
  }
  // Only rewrite a link that has no words of its own (the emoji control).
  // A link that already says something keeps its text.
  function bare(a){
    var t = (a.textContent || '').replace(/[\s\u200b]/g, '');
    return t.length <= 3;   // '', an emoji, or an icon ligature
  }
  function heading(){
    var table = document.querySelector('.woosw-items, .woosw-list, .wishlist_table, table.shop_table');
    if (!table || document.querySelector('.af-wl-head')) return;
    var h = document.createElement('div');
    h.className = 'af-wl-head';
    var n = table.querySelectorAll('tbody tr').length;
    h.innerHTML = '<h1>Your Wishlist</h1><p>' +
      (n ? n + (n === 1 ? ' piece saved' : ' pieces saved') + ' — ready when you are.'
         : 'Nothing saved yet. Tap the heart on any piece to keep it here.') + '</p>';
    var host = table.closest('.woocommerce, .entry-content, main') || table.parentElement;
    host.insertBefore(h, host.firstChild);
  }
  // Inline styles, deliberately. The stylesheet version of these rules ships
  // on the page and still loses — the control is positioned by rules this
  // theme applies with higher specificity, so a stylesheet fight is not
  // winnable here. An inline property with priority beats every stylesheet,
  // which is the same lesson the product-card buttons taught earlier.
  // The button was styled and still clipped: the plugin's table's column
  // minimums push the actions cell past the card edge — geometry, not cascade.
  // Stop using table layout entirely: each row becomes a flex line (image,
  // shrinking title block, button pinned right), which cannot overflow.
  function unclip(){
    var table = document.querySelector('.woosw-items');
    if (!table) return;
    table.style.setProperty('width', '100%', 'important');
    table.style.setProperty('max-width', '100%', 'important');
    table.style.setProperty('display', 'block', 'important');
    table.querySelectorAll('tbody').forEach(function(tb){
      tb.style.setProperty('display', 'block', 'important');
    });
    table.querySelectorAll('tr').forEach(function(tr){
      tr.style.setProperty('display', 'flex', 'important');
      tr.style.setProperty('align-items', 'center', 'important');
      tr.style.setProperty('gap', '14px', 'important');
      tr.style.setProperty('width', '100%', 'important');
      tr.style.setProperty('box-sizing', 'border-box', 'important');
      tr.style.setProperty('padding', '14px 18px', 'important');
    });
    table.querySelectorAll('td').forEach(function(td){
      td.style.setProperty('display', 'block', 'important');
      td.style.setProperty('overflow', 'visible', 'important');
      td.style.setProperty('padding', '0', 'important');
      td.style.setProperty('border', '0', 'important');
      var isInfo = td.className.indexOf('info') !== -1 || td.querySelector('.woosw-item--name');
      td.style.setProperty('flex', isInfo ? '1 1 auto' : 'none', 'important');
      if (isInfo) td.style.setProperty('min-width', '0', 'important');
      if (td.className.indexOf('actions') !== -1 || td.querySelector('a[href*="add-to-cart="]')) {
        td.style.setProperty('margin-left', 'auto', 'important');
        td.style.setProperty('min-width', '0', 'important');
        td.style.setProperty('text-align', 'right', 'important');
        td.style.setProperty('flex', 'none', 'important');
      }
    });
    table.querySelectorAll('.woosw-item--image img, td img').forEach(function(im){
      im.style.setProperty('width', '88px', 'important');
      im.style.setProperty('height', '88px', 'important');
      im.style.setProperty('object-fit', 'cover', 'important');
      im.style.setProperty('border-radius', '10px', 'important');
    });
    var name = table.querySelector('.woosw-item--info, .woosw-item--name');
    if (name) name.style.setProperty('word-break', 'break-word', 'important');
  }

  // The saved-item card and the related row sat on different left/right edges,
  // because each is constrained by a different container. Rather than guess a
  // width that matches, measure where the related row's cards actually start
  // and end and put the saved-item card on exactly those edges.
  function alignSections(){
    var rel  = document.querySelector('.af-wl-related');
    var list = document.querySelector('.woosw-list');
    if (!rel || !list || !list.parentElement) return;
    var cards = rel.querySelectorAll('li.product');
    if (!cards.length) return;
    var first = cards[0].getBoundingClientRect();
    var last  = cards[cards.length - 1].getBoundingClientRect();
    var host  = list.parentElement.getBoundingClientRect();
    if (!first.width || !host.width) return;
    var left  = Math.round(first.left - host.left);
    var right = Math.round(host.right - last.right);
    if (left < 0 || right < 0) return;
    list.style.setProperty('box-sizing', 'border-box', 'important');
    list.style.setProperty('width', 'auto', 'important');
    list.style.setProperty('max-width', 'none', 'important');
    list.style.setProperty('margin-left', left + 'px', 'important');
    list.style.setProperty('margin-right', right + 'px', 'important');
    // the heading and the share row belong on the same edges
    ['.af-wl-head', '.woosw-actions'].forEach(function(sel){
      var el = document.querySelector(sel);
      if (!el) return;
      el.style.setProperty('box-sizing', 'border-box', 'important');
      el.style.setProperty('max-width', 'none', 'important');
      el.style.setProperty('margin-left', left + 'px', 'important');
      el.style.setProperty('margin-right', right + 'px', 'important');
      el.style.setProperty('padding-left', '0', 'important');
      el.style.setProperty('padding-right', '0', 'important');
    });
  }
  function forceButton(a){
    // Width matters most: the theme renders this control as a small round icon
    // button (fixed width, overflow hidden), which is why the label showed as
    // the middle fragment "D TO" of "ADD TO CART" rather than the whole words.
    // Releasing the width is what makes the button whole.
    var st = {display:'inline-flex', alignItems:'center', justifyContent:'center', gap:'8px',
              background:'#c9a84c', border:'0', color:'#fff', fontSize:'14px', fontWeight:'600',
              padding:'11px 20px', borderRadius:'6px', whiteSpace:'nowrap', textDecoration:'none',
              position:'static', transform:'none', opacity:'1', visibility:'visible',
              margin:'0', right:'auto', left:'auto', top:'auto', bottom:'auto',
              width:'auto', minWidth:'0', maxWidth:'none', height:'auto', minHeight:'0',
              overflow:'visible', textIndent:'0', lineHeight:'1.2', flex:'none',
              boxSizing:'border-box', textTransform:'none'};
    for (var k in st) a.style.setProperty(k.replace(/[A-Z]/g, function(m){return '-'+m.toLowerCase();}), st[k], 'important');
    a.addEventListener('mouseenter', function(){ a.style.setProperty('background', '#8b6a2b', 'important'); });
    a.addEventListener('mouseleave', function(){ a.style.setProperty('background', '#c9a84c', 'important'); });
    // the wrappers the theme parks off-edge
    var n = a.parentElement, hops = 0;
    while (n && hops++ < 4 && !n.matches('td,.woosw-item')) {
      n.style.setProperty('width', 'auto', 'important');
      n.style.setProperty('max-width', 'none', 'important');
      ['position','transform','opacity','visibility','right','left','margin','overflow'].forEach(function(prop){
        n.style.setProperty(prop, prop === 'position' ? 'static'
                                : prop === 'transform' ? 'none'
                                : prop === 'opacity' ? '1'
                                : prop === 'visibility' ? 'visible'
                                : prop === 'overflow' ? 'visible'
                                : prop === 'margin' ? '0' : 'auto', 'important');
      });
      n.style.setProperty('display', 'block', 'important');
      n = n.parentElement;
    }
    if (n && n.matches('td')) {
      n.style.setProperty('overflow', 'visible', 'important');
      n.style.setProperty('min-width', '190px', 'important');
      n.style.setProperty('text-align', 'right', 'important');
    }
  }
  // an art code printed outside a product card is noise between sections
  function dropStrayCodes(){
    document.querySelectorAll('.af-art-code').forEach(function(el){
      if (el.closest('li.product') || el.closest('.woosw-item')) return;   // inside a card: keep
      el.style.setProperty('display', 'none', 'important');
    });
  }
  function run(){
    document.querySelectorAll('.woosw-items a[href*="add-to-cart="], .wishlist_table a[href*="add-to-cart="]').forEach(label);
    document.querySelectorAll('.woosw-item a[href*="add-to-cart="], .woosw-item--atc a.button').forEach(forceButton);
    unclip();
    alignSections();
    dropStrayCodes();
    // the same control wherever the plugin put it, as long as it is wordless
    document.querySelectorAll('a[href*="add-to-cart="]').forEach(function(a){
      if (!a.closest('.af-wl-related') && bare(a)) label(a);
    });
    heading();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run);
  else run();
  window.addEventListener('load', run);
  [400, 1200, 2500].forEach(function(d){ setTimeout(run, d); });
  var _art; window.addEventListener('resize', function(){ clearTimeout(_art); _art = setTimeout(run, 200); });
  try {
    new MutationObserver(function(m){
      for (var i = 0; i < m.length; i++) { if (m[i].addedNodes && m[i].addedNodes.length) { run(); break; } }
    }).observe(document.body, {childList:true, subtree:true});
  } catch(e){}
})();
</script>
    <?php
}, 20);

// ─────────────────────────────────────────────────────────────
// "You may also like" on the CART page too (owner request, video
// 2026-08-12). Same row the wishlist got, built from the categories of what
// is in the cart. WooCommerce's native cross-sells slot would cover this only
// if every product had cross-sells picked by hand; none do.
// ─────────────────────────────────────────────────────────────
add_action('woocommerce_after_cart', function () {
    if (!function_exists('WC') || !WC()->cart || WC()->cart->is_empty()) return;
    $ids = array();
    foreach (WC()->cart->get_cart() as $item) {
        if (!empty($item['product_id'])) $ids[] = (int) $item['product_id'];
    }
    if (!$ids) return;
    // af_wl_related_html() reads ids out of markup; feed it the cart's ids in
    // the same shape rather than duplicating its category logic
    $html = af_wl_related_html(implode(' ', array_map(function ($id) {
        return 'add-to-cart=' . $id;
    }, array_unique($ids))));
    if ($html === '') return;
    // the wishlist's row styling, printed once here since the wishlist-page
    // footer block does not run on the cart
    echo '<style id="af-cart-related-style">
.af-wl-related{max-width:1200px;margin:44px auto 56px;padding:0 16px}
.af-wl-related h2{font-size:24px;margin:0 0 18px;color:#1a1a1a}
.af-wl-related ul.products{display:grid!important;grid-template-columns:repeat(4,1fr)!important;gap:20px!important;margin:0!important;padding:0!important;list-style:none!important}
.af-wl-related ul.products::before,.af-wl-related ul.products::after{display:none!important}
/* Uniform image box, same measurement the shop uses. The card script builds a
   fixed 300px box (260 under 520px) only for cards shipping TWO images, main
   plus hover. A single-image card never gets that box, so it keeps the natural
   ratio and the row goes ragged. Give those cards the same box; images already
   inside a built box stay under the script control. */
.af-wl-related li.product > a:first-of-type > img:not(.af-main-img):not(.af-hover-img){
  height:300px!important;width:100%!important;object-fit:cover!important;display:block!important}
@media (max-width:520px){
  .af-wl-related li.product > a:first-of-type > img:not(.af-main-img):not(.af-hover-img){height:260px!important}}
@media (max-width:900px){.af-wl-related ul.products{grid-template-columns:repeat(2,1fr)!important}}
</style>';
    echo $html;
}, 30);

// ─────────────────────────────────────────────────────────────
// Cart totals breakdown. "Subtotal $80 / Total $80" tells a buyer nothing and
// reads like a placeholder — the same two numbers twice, with no working shown.
// These rows show the arithmetic instead: how many pieces, what they list at
// before the discount, what that discount is worth, and what is still to be
// added at checkout.
//
// The reference price uses af_mrp_multiplier(), the same per-product figure
// behind the struck-through price on the card and the product page, so the
// saving quoted here is the one the shopper was shown on the way in. It is
// derived from the live line totals rather than stored, because the size and
// frame engine sets the price at add-to-cart time.
// ─────────────────────────────────────────────────────────────
function af_cart_reference_total() {
    if (!function_exists('WC') || !WC()->cart) return array(0, 0.0, 0.0);
    $items = 0; $ref = 0.0;
    foreach (WC()->cart->get_cart() as $item) {
        if (empty($item['product_id'])) continue;
        $qty    = isset($item['quantity']) ? (int) $item['quantity'] : 1;
        $line   = isset($item['line_subtotal']) ? (float) $item['line_subtotal'] : 0.0;
        $items += $qty;
        $ref   += $line * af_mrp_multiplier((int) $item['product_id']);
    }
    $sub = (float) WC()->cart->get_subtotal();
    return array($items, round($ref, 2), $sub);
}

add_action('woocommerce_cart_totals_before_shipping', function () {
    if (!function_exists('af_mrp_multiplier') || !function_exists('WC') || !WC()->cart) return;
    list($items, $ref, $sub) = af_cart_reference_total();
    if ($items < 1) return;
    $save = $ref - $sub;
    $pct  = $ref > 0 ? (int) round($save / $ref * 100) : 0;
    ?>
  <tr class="af-ct-items">
    <th><?php echo esc_html($items === 1 ? 'Item' : 'Items'); ?></th>
    <td data-title="Items"><?php echo esc_html($items); ?> <?php echo esc_html($items === 1 ? 'piece' : 'pieces'); ?></td>
  </tr>
    <?php if ($save > 0.01): ?>
  <tr class="af-ct-was">
    <th>Price before discount</th>
    <td data-title="Price before discount"><s><?php echo wp_kses_post(wc_price($ref)); ?></s></td>
  </tr>
  <tr class="af-ct-save">
    <th>You save</th>
    <td data-title="You save">&minus;<?php echo wp_kses_post(wc_price($save)); ?> <span class="af-ct-pct">(<?php echo esc_html($pct); ?>% off)</span></td>
  </tr>
    <?php endif; ?>
    <?php
}, 5);

// Below the total: what is NOT yet in that number, said plainly. A buyer who
// cannot tell whether $80 is the final figure abandons the cart.
add_action('woocommerce_cart_totals_after_order_total', function () {
    if (!function_exists('af_shipping_copy')) return;
    $ship = af_shipping_copy();
    ?>
  <tr class="af-ct-note">
    <td colspan="2">
      <span>Inclusive of all taxes</span>
      <span><?php echo esc_html($ship['short']); ?></span>
    </td>
  </tr>
    <?php
}, 5);

// ─────────────────────────────────────────────────────────────
// Cart totals box (owner: "give some more"). The box held only Subtotal, the
// gift-card field and Total. Add the reassurance a buyer looks for at exactly
// this moment — shipping terms, secure checkout, a human to call — using the
// site's single sources (af_shipping_copy / af_studio_contact) so nothing
// here can drift from the rest of the site. Scoped to the cart page.
// ─────────────────────────────────────────────────────────────
add_action('woocommerce_after_cart_totals', function () {
    if (!function_exists('af_shipping_copy') || !function_exists('af_studio_contact')) return;
    $ship = af_shipping_copy();
    $c    = af_studio_contact();
    ?>
<div class="af-ct-extra">
  <div class="af-ct-row">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
    <span><?php echo esc_html($ship['short']); ?></span>
  </div>
  <div class="af-ct-row">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
    <span>Secure SSL checkout &mdash; cards, PayPal &amp; more</span>
  </div>
  <div class="af-ct-row">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
    <span>Questions? <a href="<?php echo esc_attr($c['tel']); ?>"><?php echo esc_html($c['phone']); ?></a></span>
  </div>
  <div class="af-ct-row">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 12v10H4V12"></path><path d="M2 7h20v5H2z"></path><path d="M12 22V7"></path><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"></path><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"></path></svg>
    <span>Gallery-wrapped &amp; carefully packed</span>
  </div>
</div>
<style id="af-ct-style">
.cart_totals, .cart-collaterals .cart_totals{border-radius:14px!important}
.af-ct-extra{margin-top:18px;padding-top:16px;border-top:1px solid #eee;display:flex;flex-direction:column;gap:11px}
.af-ct-row{display:flex;align-items:flex-start;gap:10px;font-size:13.5px;color:#444;line-height:1.45}
.af-ct-row svg{width:18px;height:18px;flex:0 0 auto;margin-top:1px;color:#c9a84c}
.af-ct-row a{color:#8b6a2b;font-weight:600;text-decoration:none}
.af-ct-row a:hover{text-decoration:underline}
/* Breakdown rows. Colours are inherited from the totals box so this reads the
   same on the dark cart as on a light one; only the saving and the struck
   reference price are tinted, because those two carry the meaning. */
.cart_totals .af-ct-items th,.cart_totals .af-ct-was th,.cart_totals .af-ct-save th{font-weight:600;opacity:.85}
.cart_totals .af-ct-was td s{opacity:.6;text-decoration-thickness:1.5px}
.cart_totals .af-ct-save th,.cart_totals .af-ct-save td{color:#4caf2f!important}
.cart_totals .af-ct-save td .amount{color:#4caf2f!important}
.cart_totals .af-ct-pct{font-weight:700}
.cart_totals .af-ct-note td{padding-top:4px!important;border-top:0!important}
.cart_totals .af-ct-note span{display:block;font-size:12px;opacity:.7;line-height:1.5}
</style>
    <?php
}, 20);

// ─────────────────────────────────────────────────────────────
// Related-row cards are SHOP-PAGE cards. They come from the same template
// (wc_get_template_part content-product), so the site's own card machinery —
// the uniform image box, the hover second image, the button styling — already
// treats them correctly. Earlier passes here added their own image sizing and
// moved the hover block around by hand, which fought that machinery and
// produced the tall white gaps under the artwork. Nothing does that now: the
// row only lays the cards out in a grid, and the cards style themselves.
// ─────────────────────────────────────────────────────────────
add_action('wp_footer', function () {
    ?>
<style id="af-wl-related-stray">
/* An art code printed outside a card is noise between sections. The earlier
   rule was scoped inside .af-wl-related, but the stray line sits BEFORE that
   section, so it kept showing above the heading. Hide art codes that are not
   inside a product card, on the wishlist and cart pages only. Codes inside
   cards are untouched. */
.woosw-list .af-art-code,
.entry-content > .af-art-code,
.entry-content > * > .af-art-code:not(li .af-art-code),
.af-wl-related > .af-art-code{display:none!important}
</style>
    <?php
}, 21);

// ─────────────────────────────────────────────────────────────
// SIDEBAR CATEGORIES = THE HEADER'S CATEGORY MENU
// The sidebar listed all 91 product categories — including frame sizes and
// frame colours, which are attribute values that happen to be terms, and
// several that hold nothing. The header menu already answers "what does this
// shop sell" in ten lines. The sidebar now shows that same list, in the same
// order, worded the same way — so a section added to the header menu has to be
// added here too, or it silently goes missing from the shop sidebar.
//
// Kept as slugs with fallbacks rather than IDs: slugs survive a re-import,
// and the site has near-duplicate terms (digital-downloads vs
// digital-downloads-2, home-decor-space vs home-decor-by-space) where either
// may be the live one.
// ─────────────────────────────────────────────────────────────
function af_sidebar_cat_menu() {
    return array(
        array('label' => 'Digital Canvas Prints', 'slugs' => array('digital-canvas-prints', 'digital-canvas-print')),
        array('label' => 'Framed Canvases',       'slugs' => array('framed-canvases')),
        array('label' => 'Direct from Artists',   'slugs' => array('direct-from-artists', 'from-artists')),
        array('label' => 'Art Accessories',       'slugs' => array('art-accessories')),
        array('label' => 'Banners & Signage',     'slugs' => array('banners-signage', 'banners-and-signage')),
        array('label' => 'Digital Downloads',     'slugs' => array('digital-downloads', 'digital-downloads-2')),
        array('label' => 'Home Decor Space',      'slugs' => array('home-decor-space', 'home-decor-by-space', 'home-decor')),
        array('label' => 'Personalised Prints',   'slugs' => array('personalised-prints', 'personalized-prints')),
        array('label' => 'Gifts',                 'slugs' => array('gifts')),
        // The header's category menu gained this section, and this list exists
        // to mirror that menu — so it belongs here too. It carries no products
        // yet, which does not matter: this widget is an allow-list with
        // hide_empty off, and several of the rows above are empty for the same
        // reason.
        array('label' => 'Gold Foiled & UV',      'slugs' => array('gold-foiled-uv')),
    );
}

/** term_id => the header's wording, in the header's order. Empty if none resolve. */
function af_sidebar_cat_terms() {
    static $map = null;
    if ($map !== null) return $map;
    $map = array();
    foreach (af_sidebar_cat_menu() as $row) {
        foreach ($row['slugs'] as $slug) {
            $t = get_term_by('slug', $slug, 'product_cat');
            if ($t && !is_wp_error($t)) { $map[(int) $t->term_id] = $row['label']; break; }
        }
    }
    return $map;
}

/**
 * The nine parents plus their DIRECT children, in display order.
 *
 * Direct children only, on purpose. Going deeper would drag the whole tree
 * back in — art-accessories → frame-sizes → "2x3 ft", "4x6 ft" and the rest
 * are grandchildren, and those are attribute values wearing category
 * costumes. One level down is the shop's structure; two is its plumbing.
 */
function af_sidebar_cat_tree_ids() {
    $ids = array();
    foreach (array_keys(af_sidebar_cat_terms()) as $parent_id) {
        $ids[] = (int) $parent_id;
        $kids = get_terms(array(
            'taxonomy'   => 'product_cat',
            'parent'     => (int) $parent_id,
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
            'fields'     => 'ids',
        ));
        if (!is_wp_error($kids)) foreach ($kids as $k) $ids[] = (int) $k;
    }
    return array_values(array_unique($ids));
}

// The widget builds its list through wp_list_categories; include only these
// terms, in this order. orderby=include is what keeps the header's order
// rather than falling back to alphabetical, and hierarchical nests the
// children under the parent they belong to.
add_filter('woocommerce_product_categories_widget_args', function ($args) {
    $ids = af_sidebar_cat_tree_ids();
    if (!$ids) return $args;                       // resolved nothing — leave the widget alone
    $args['include']      = implode(',', $ids);
    $args['orderby']      = 'include';
    $args['hierarchical'] = 1;
    $args['depth']        = 2;                     // parents and their children, no deeper
    $args['hide_empty']   = 0;                     // several are empty; the cross-sell row covers that
    return $args;
}, 20);

// Use the header's wording where the term's own name differs. WooCommerce's
// widget renders through its own walker, which fires list_product_cats and
// never list_cats — filtering only the latter is why "Home Decor Space" was
// still coming out as the term's own "Home Decor By Space". Both are hooked
// so it does not matter which walker is in play.
function af_sidebar_cat_label($name, $category = null) {
    if (!$category || !is_object($category) || empty($category->term_id)) return $name;
    $terms = af_sidebar_cat_terms();
    return isset($terms[(int) $category->term_id]) ? $terms[(int) $category->term_id] : $name;
}
add_filter('list_cats', 'af_sidebar_cat_label', 20, 2);
add_filter('list_product_cats', 'af_sidebar_cat_label', 20, 2);

// ─────────────────────────────────────────────────────────────
// CURRENCY SWITCHER — say what the money is
// The switcher lists "$ USD" and "CA$ CAD". That is a symbol and a code and
// no words: it tells you how the price will be punctuated, not which
// country's dollars you are about to be charged in — which is the one thing
// a shopper actually needs to know before switching.
//
// The list now reads "CA$ CAD — Canadian Dollar". The button keeps the short
// form, because it sits in a header row that has to stay on one line.
//
// Relabelled in the browser: the switcher is a plugin's, and its markup
// differs between the header dropdown and the <select> it falls back to.
// ─────────────────────────────────────────────────────────────
add_action('wp_footer', function () {
    if (is_admin()) return;
    $names = array();
    foreach (af_allowed_currencies() as $code) {
        $names[$code] = array(af_currency_symbol_for($code), af_currency_name_for($code));
    }
    ?>
<script>
(function(){
  var CUR = <?php echo wp_json_encode($names); ?>;
  // "$ USD", "CA$ CAD", "USD", "CA$" — the shapes the plugin prints
  var RE = /^\s*(?:(CA\$|C\$|US\$|\$)\s*)?(USD|CAD)\s*$/i;

  function label(code, sym){ return sym + ' ' + code + ' — ' + CUR[code][1]; }

  function relabel(el, text){
    var m = RE.exec(text);
    if (!m) return false;
    var code = m[2].toUpperCase();
    if (!CUR[code]) return false;
    var sym = m[1] || CUR[code][0];
    var full = label(code, sym);
    if (el.tagName === 'OPTION') { el.textContent = full; return true; }
    // only the dropdown list is spelled out — the trigger stays short so the
    // header row it lives in keeps to one line
    var inList = el.closest('ul, ol, [class*="dropdown"], [class*="menu"], [class*="list"]');
    var isTrigger = el.closest('[aria-haspopup], [class*="current"], [class*="selected"], [class*="toggle"], [class*="active"]');
    if (!inList || isTrigger) return false;
    el.textContent = full;
    return true;
  }

  function scan(){
    document.querySelectorAll('option').forEach(function(o){
      if (o.dataset.afCur) return;
      if (relabel(o, o.textContent)) o.dataset.afCur = '1';
    });
    // leaf elements only, so a wrapper is never rewritten over its own child
    document.querySelectorAll('a, span, li, button, div').forEach(function(el){
      if (el.dataset.afCur || el.children.length) return;
      var t = el.textContent || '';
      if (t.length > 12) return;                 // cheap reject before the regex
      if (relabel(el, t)) el.dataset.afCur = '1';
    });
  }
  if (document.readyState !== 'loading') scan();
  else document.addEventListener('DOMContentLoaded', scan);
  window.addEventListener('load', scan);
  var t = null;
  try {
    new MutationObserver(function(){ clearTimeout(t); t = setTimeout(scan, 150); })
      .observe(document.body, { childList:true, subtree:true });
  } catch(e){}
})();
</script>
    <?php
}, 44);

// ─────────────────────────────────────────────────────────────
// TEMPORARY, owner-only: visit the homepage with ?af_debug_circles=1 and a
// small panel reports what a circle click actually does — how many circles
// were wired, which slug the click resolved, which container was chosen for
// the swap, and what the ajax answered. Ten days of this regression have been
// diagnosed from server logs, which cannot see a browser click; this can.
// Prints nothing for normal visitors.
// ─────────────────────────────────────────────────────────────
add_action('wp_footer', function () {
    if (!isset($_GET['af_debug_circles'])) return;
    ?>
<style>#af-circdbg{position:fixed;left:10px;bottom:10px;z-index:99999;background:#111;color:#9f9;
font:12px/1.5 monospace;padding:10px 14px;border-radius:8px;max-width:460px;max-height:45vh;overflow:auto}</style>
<div id="af-circdbg">circle debug on…</div>
<script>
(function(){
  var box = document.getElementById('af-circdbg');
  window.afdbg = function(m){ box.innerHTML += '<br>' + m; };
  window.addEventListener('load', function(){
    setTimeout(function(){
      var strips = document.querySelectorAll('#subcategorySlider, .subcategory-slider, ul.postero-scroll-content').length;
      var items = document.querySelectorAll('a.pf-value, li.cat-item, .sub-cat').length;
      var pf = document.querySelectorAll('a.pf-value').length;
      var grids = ['#productGrid','.product-slider','.custom-product-track','ul.products']
        .map(function(sel){ return sel + ':' + document.querySelectorAll(sel).length; }).join('  ');
      afdbg('delegated handler on document  strips:' + strips + '  circle items:' + items + '  pf-value anchors:' + pf);
      afdbg('grids ' + grids);
    }, 1500);
  });
})();
</script>
    <?php
}, 9998);

// ─────────────────────────────────────────────────────────────
// Related row: uniform artwork height, measured from the DOM.
// The shop-card script builds its fixed image box only for cards shipping a
// main AND a hover image; a single-image card keeps its natural ratio and sits
// visibly shorter than the row. Two stylesheet attempts at this missed because
// they assumed where the img lives in the card; this finds it wherever it is
// and sizes it inline, which no stylesheet can override. Cards the script
// already boxed (.af-main-img) are left to the script. Runs only where the
// related row exists.
// ─────────────────────────────────────────────────────────────
add_action('wp_footer', function () {
    ?>
<script>
(function(){
  function even(){
    var cards = document.querySelectorAll('.af-wl-related li.product');
    if (!cards.length) return;
    var H = (window.innerWidth <= 520) ? 260 : 300;
    cards.forEach(function(card){
      var img = card.querySelector('img');
      if (!img || img.classList.contains('af-main-img') || img.classList.contains('af-hover-img')) return;
      img.style.setProperty('height', H + 'px', 'important');
      img.style.setProperty('width', '100%', 'important');
      img.style.setProperty('object-fit', 'cover', 'important');
      img.style.setProperty('display', 'block', 'important');
      var box = img.parentElement;
      if (box && box !== card) {
        box.style.setProperty('display', 'block', 'important');
        box.style.setProperty('height', H + 'px', 'important');
        box.style.setProperty('overflow', 'hidden', 'important');
      }
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', even);
  else even();
  window.addEventListener('load', even);
  [600, 1500, 3000].forEach(function(d){ setTimeout(even, d); });
  var rt; window.addEventListener('resize', function(){ clearTimeout(rt); rt = setTimeout(even, 200); });
})();
</script>
    <?php
}, 22);

// ─────────────────────────────────────────────────────────────
// Cart page LAYOUT only (owner request, video 2026-08-13). Three things, all
// measured in the browser and applied to this page alone. No cart behaviour,
// totals, coupon handling or checkout logic is touched.
//
//   1. Three different left edges: the items table, the coupon row and the
//      related row each sat on their own margin. They are put on one edge.
//   2. Product titles broke mid-word ("Wa / ll Art", "Pre / mium") because a
//      break-anywhere rule applies in the narrow name column. Words wrap at
//      word boundaries again.
//   3. An art code printed inside the Cart totals box, where it belongs to no
//      product. Hidden, exactly as on the wishlist page.
// ─────────────────────────────────────────────────────────────
add_action('wp_footer', function () {
    if (!function_exists('is_cart') || !is_cart()) return;
    ?>
<script>
(function(){
  function titles(){
    document.querySelectorAll('.woocommerce-cart-form .product-name a, .woocommerce-cart-form .product-name')
      .forEach(function(el){
        el.style.setProperty('word-break', 'normal', 'important');
        el.style.setProperty('overflow-wrap', 'break-word', 'important');
        el.style.setProperty('hyphens', 'manual', 'important');
      });
  }
  function strayCodes(){
    document.querySelectorAll('.af-art-code').forEach(function(el){
      if (el.closest('li.product') || el.closest('tr.cart_item')) return;  // inside a product: keep
      el.style.setProperty('display', 'none', 'important');
    });
  }
  // one shared left/right edge for every block on the page
  function align(){
    var form = document.querySelector('.woocommerce-cart-form');
    var rel  = document.querySelector('.af-wl-related');
    if (!form) return;
    var host = form.parentElement;
    if (!host) return;
    var hostBox = host.getBoundingClientRect();
    var formBox = form.getBoundingClientRect();
    if (!formBox.width || !hostBox.width) return;
    // The cart is two columns: the items form on the left, the totals box on
    // the right. Aligning the related row to the FORM alone made it stop at the
    // form edge and leave the whole totals column empty beside it — the gap in
    // the recording. The content width is the union of both columns, so the
    // related row spans from the form's left edge to the totals' right edge.
    var totals = document.querySelector('.cart-collaterals, .cart_totals');
    var totalsBox = totals ? totals.getBoundingClientRect() : null;
    var contentLeft  = formBox.left;
    var contentRight = formBox.right;
    if (totalsBox && totalsBox.width) {
      contentLeft  = Math.min(contentLeft,  totalsBox.left);
      contentRight = Math.max(contentRight, totalsBox.right);
    }
    var left  = Math.round(contentLeft  - hostBox.left);
    var right = Math.round(hostBox.right - contentRight);
    if (left < 0 || right < 0) return;
    var targets = [];
    if (rel) targets.push(rel);
    targets.forEach(function(el){
      el.style.setProperty('box-sizing', 'border-box', 'important');
      el.style.setProperty('max-width', 'none', 'important');
      el.style.setProperty('margin-left', left + 'px', 'important');
      el.style.setProperty('margin-right', right + 'px', 'important');
      el.style.setProperty('padding-left', '0', 'important');
      el.style.setProperty('padding-right', '0', 'important');
    });
  }
  function run(){ titles(); strayCodes(); align(); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run);
  else run();
  window.addEventListener('load', run);
  [400, 1200, 2500].forEach(function(d){ setTimeout(run, d); });
  var rt; window.addEventListener('resize', function(){ clearTimeout(rt); rt = setTimeout(run, 200); });
  // the cart re-renders its contents over ajax on quantity changes
  if (window.jQuery) jQuery(document.body).on('updated_cart_totals updated_wc_div', run);
})();
</script>
    <?php
}, 23);

// ─────────────────────────────────────────────────────────────
// Related products as a slider (owner request 2026-08-13): one row that
// scrolls, with next/previous buttons, instead of two stacked rows of four.
// Runs wherever the related row exists — wishlist and cart. Layout only: the
// cards themselves, their links, buttons and prices are untouched.
// ─────────────────────────────────────────────────────────────
add_action('wp_footer', function () {
    ?>
<style id="af-rel-slider-css">
/* Owner-only probe: /cart/?af_debug_cards=1 reports what each card actually
   ships, since a browser click cannot be inspected from the server. */
.af-rel-vp{position:relative;width:100%;overflow:hidden}
.af-rel-vp ul.products{scrollbar-width:none;-ms-overflow-style:none}
.af-rel-vp ul.products::-webkit-scrollbar{display:none}
.af-rel-nav{position:absolute;top:38%;transform:translateY(-50%);z-index:5;
  width:44px;height:44px;border-radius:50%;border:0;cursor:pointer;
  background:#c9a84c;color:#fff;display:flex;align-items:center;justify-content:center;
  box-shadow:0 2px 10px rgba(0,0,0,.22);transition:background .2s,opacity .2s}
.af-rel-nav:hover{background:#8b6a2b}
.af-rel-nav[hidden]{display:none!important}
.af-rel-prev{left:-6px}
.af-rel-next{right:-6px}
/* the theme's global button rules flatten inline svg; state the icon size and
   colour here too, so the chevrons survive even before the script runs */
.af-rel-nav svg{width:20px!important;height:20px!important;display:block!important;
  fill:none!important;stroke:currentColor!important;stroke-width:2.5!important;opacity:1!important}
.af-rel-nav *{color:#fff!important}
@media (max-width:600px){.af-rel-nav{width:38px;height:38px}}
</style>
<script>
(function(){
  var GAP = 20;
  function perView(){
    var w = window.innerWidth;
    if (w <= 600) return 1;
    if (w <= 900) return 2;
    if (w <= 1200) return 3;
    return 4;
  }
  function sizeCards(ul){
    var n = perView();
    ul.querySelectorAll('li.product').forEach(function(li){
      li.style.setProperty('flex', '0 0 calc((100% - ' + (GAP * (n - 1)) + 'px) / ' + n + ')', 'important');
      li.style.setProperty('max-width', 'calc((100% - ' + (GAP * (n - 1)) + 'px) / ' + n + ')', 'important');
      li.style.setProperty('margin', '0', 'important');
      li.style.setProperty('scroll-snap-align', 'start', 'important');
    });
  }
  // Make every card's image area one fixed box that both the artwork and the
  // room preview fill. Without this the preview keeps its own proportions on
  // hover and leaves a white band under it, and the card visibly changes shape
  // as the pointer moves across the row.
  function fillImages(sec){
    var H = (window.innerWidth <= 520) ? 260 : 300;
    sec.querySelectorAll('li.product').forEach(function(li){
      var link = li.querySelector('a');
      if (!link) return;
      var imgs = link.querySelectorAll('img');
      if (!imgs.length) return;
      var host = imgs[0].parentElement;   // whatever wraps the artwork
      if (!host) return;
      host.style.setProperty('position', 'relative', 'important');
      host.style.setProperty('display', 'block', 'important');
      host.style.setProperty('height', H + 'px', 'important');
      host.style.setProperty('min-height', H + 'px', 'important');
      host.style.setProperty('overflow', 'hidden', 'important');
      host.style.setProperty('padding-bottom', '0', 'important');
      Array.prototype.forEach.call(imgs, function(im, i){
        im.style.setProperty('position', 'absolute', 'important');
        im.style.setProperty('top', '0', 'important');
        im.style.setProperty('left', '0', 'important');
        im.style.setProperty('width', '100%', 'important');
        im.style.setProperty('height', '100%', 'important');
        im.style.setProperty('max-width', 'none', 'important');
        im.style.setProperty('object-fit', 'cover', 'important');
        im.style.setProperty('margin', '0', 'important');
        im.style.setProperty('z-index', i === 0 ? '1' : '2', 'important');
      });
      // If the site's own hover swap never initialised on these cards, the
      // second image would simply sit on top forever. Give it the same
      // behaviour: hidden until the card is hovered.
      if (imgs.length > 1 && !li.dataset.afRelHover) {
        li.dataset.afRelHover = '1';
        var hover = imgs[1];
        hover.style.setProperty('opacity', '0', 'important');
        hover.style.setProperty('transition', 'opacity .35s ease', 'important');
        li.addEventListener('mouseenter', function(){ hover.style.setProperty('opacity', '1', 'important'); });
        li.addEventListener('mouseleave', function(){ hover.style.setProperty('opacity', '0', 'important'); });
      }
    });
  }

  // The four card actions — cart, compare, quick view, wishlist — as one row.
  //
  // Moving the theme's own controls into a row did not hold: several other
  // scripts on this site restyle those elements with inline !important rules
  // of their own, so whatever we set was overwritten moments later, and some
  // of the controls are injected only on first hover. So build a row of our
  // own buttons — elements nothing else on the site knows about — and have
  // each one CLICK the original control, which keeps every plugin behaviour
  // (ajax add to cart, compare list, quick view modal, wishlist save) exactly
  // as it is. The originals are parked in a zero-size holder rather than
  // removed, so any plugin that looks for them still finds them.
  var ICONS = {
    cart:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>',
    compare: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"></polyline><path d="M3 11V9a4 4 0 0 1 4-4h14"></path><polyline points="7 23 3 19 7 15"></polyline><path d="M21 13v2a4 4 0 0 1-4 4H3"></path></svg>',
    quick:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>',
    wish:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1L12 21l7.7-7.6 1.1-1a5.5 5.5 0 0 0 0-7.8z"></path></svg>'
  };

  function findControl(li, sels){
    var found = null;
    sels.split(',').some(function(sel){
      var list = li.querySelectorAll(sel.trim());
      for (var i = 0; i < list.length; i++) {
        var el = list[i];
        if (el.closest('.af-card-actions') || el.closest('.af-card-orig')) continue;
        if (el.tagName === 'A' && el.querySelector('img')) continue;   // the product link
        found = el;
        return true;
      }
      return false;
    });
    return found;
  }

  function makeBtn(kind, title, orig, gold){
    var b = document.createElement('button');
    b.type = 'button';
    b.className = 'af-ca-btn af-ca-' + kind;
    b.title = title;
    b.setAttribute('aria-label', title);
    b.innerHTML = ICONS[kind];
    [['width','38px'],['height','38px'],['min-width','38px'],['border-radius','50%'],
     ['display','inline-flex'],['align-items','center'],['justify-content','center'],
     ['padding','0'],['margin','0'],['border','0'],['cursor','pointer'],
     ['box-shadow','0 2px 8px rgba(0,0,0,.18)'],['flex','0 0 auto'],
     ['background', gold ? '#c9a84c' : '#fff'],['color', gold ? '#fff' : '#1a1a1a'],
     ['transition','background .2s,color .2s']].forEach(function(p){
      b.style.setProperty(p[0], p[1], 'important');
    });
    var svg = b.querySelector('svg');
    if (svg) {
      svg.style.setProperty('width', '17px', 'important');
      svg.style.setProperty('height', '17px', 'important');
      svg.style.setProperty('display', 'block', 'important');
      svg.style.setProperty('fill', 'none', 'important');
      svg.style.setProperty('stroke', 'currentColor', 'important');
    }
    b.addEventListener('mouseenter', function(){
      b.style.setProperty('background', gold ? '#8b6a2b' : '#c9a84c', 'important');
      b.style.setProperty('color', '#fff', 'important');
    });
    b.addEventListener('mouseleave', function(){
      b.style.setProperty('background', gold ? '#c9a84c' : '#fff', 'important');
      b.style.setProperty('color', gold ? '#fff' : '#1a1a1a', 'important');
    });
    b.addEventListener('click', function(e){
      e.preventDefault();
      e.stopPropagation();
      if (orig && typeof orig.click === 'function') orig.click();
    });
    return b;
  }

  var KINDS = [
    ['cart',    'Add to cart',      '.add-to-cart-btn, a.add_to_cart_button, [class*="add-to-cart"], [class*="add_to_cart"]', true],
    ['compare', 'Compare',          '[class*="compare"]', false],
    ['quick',   'Quick view',       '[class*="quick-view"], [class*="quickview"], [class*="quick_view"], .view-btn, [data-quick-view]', false],
    ['wish',    'Add to wishlist',  '[class*="wishlist"], [class*="wcwl"], [class*="wl-btn"]', false]
  ];

  // Incremental on purpose: the theme injects wishlist and quick view only when
  // a card is first hovered, so a card that has just cart and compare at load
  // must be able to gain the other two later. Each pass adds whatever kinds are
  // now present and have not been added yet, keeping the row in a fixed order.
  function arrangeActions(sec){
    sec.querySelectorAll('li.product').forEach(function(li){
      var firstImg = li.querySelector('img');
      var imgHost  = firstImg ? firstImg.parentElement : null;
      if (!imgHost) return;

      var bar = li.querySelector(':scope > .af-card-actions')
             || (imgHost.querySelector(':scope > .af-card-actions'));
      var holder = li.querySelector('.af-card-orig');
      var have = (li.dataset.afKinds || '').split(',').filter(Boolean);

      var pending = KINDS.filter(function(k){ return have.indexOf(k[0]) === -1; });
      if (!pending.length) return;

      var additions = [];
      pending.forEach(function(k){
        var el = findControl(li, k[2]);
        if (el) additions.push([k, el]);
      });
      if (!additions.length) return;

      if (!bar) {
        bar = document.createElement('div');
        bar.className = 'af-card-actions';
        [['position','absolute'],['left','0'],['right','0'],['bottom','10px'],
         ['display','flex'],['justify-content','center'],['align-items','center'],
         ['gap','8px'],['z-index','7'],
         // hidden until the card is hovered — every action appears together
         ['opacity','0'],['pointer-events','none'],['transition','opacity .25s ease']
        ].forEach(function(p){ bar.style.setProperty(p[0], p[1], 'important'); });

        var target = imgHost.closest('a') ? li : imgHost;
        target.style.setProperty('position', 'relative', 'important');
        if (target === li) {
          var h = imgHost.getBoundingClientRect().height ||
                  ((window.innerWidth <= 520) ? 260 : 300);
          bar.style.setProperty('bottom', 'auto', 'important');
          bar.style.setProperty('top', Math.max(0, Math.round(h - 52)) + 'px', 'important');
        }
        target.appendChild(bar);

        li.addEventListener('mouseenter', function(){
          bar.style.setProperty('opacity', '1', 'important');
          bar.style.setProperty('pointer-events', 'auto', 'important');
        });
        li.addEventListener('mouseleave', function(){
          bar.style.setProperty('opacity', '0', 'important');
          bar.style.setProperty('pointer-events', 'none', 'important');
        });
      }
      if (!holder) {
        holder = document.createElement('div');
        holder.className = 'af-card-orig';
        [['position','absolute'],['width','1px'],['height','1px'],['overflow','hidden'],
         ['opacity','0'],['pointer-events','none'],['left','-9999px'],['top','0']
        ].forEach(function(p){ holder.style.setProperty(p[0], p[1], 'important'); });
        li.appendChild(holder);
      }

      additions.forEach(function(pair){
        var k = pair[0], el = pair[1];
        bar.appendChild(makeBtn(k[0], k[1], el, k[3]));
        var home = el.parentElement;
        holder.appendChild(el);
        if (home && home !== holder && !home.querySelector('img, svg, a, button')
            && (home.textContent || '').trim() === '') {
          home.style.setProperty('display', 'none', 'important');
        }
        have.push(k[0]);
      });
      li.dataset.afKinds = have.join(',');

      // Any control the theme leaves floating over the artwork after our row
      // exists would duplicate it — park those too, so the card shows exactly
      // one set of actions and only on hover.
      KINDS.forEach(function(k){
        var stray = findControl(li, k[2]);
        if (stray && !stray.closest('.af-card-orig') && !stray.closest('.af-card-actions')) {
          holder.appendChild(stray);
        }
      });

      // keep the fixed order regardless of when each kind arrived
      var order = {cart: 1, compare: 2, quick: 3, wish: 4};
      Array.prototype.slice.call(bar.children)
        .sort(function(a, b){
          function rank(el){
            var m = (el.className || '').match(/af-ca-(\w+)/);
            return m ? (order[m[1]] || 9) : 9;
          }
          return rank(a) - rank(b);
        })
        .forEach(function(el){ bar.appendChild(el); });
    });
  }

  // Some controls are injected by the theme only when a card is first hovered,
  // so one pass at load can miss them. Re-collect on hover and on DOM changes.
  function watchCards(sec){
    if (sec.dataset.afActionWatch) return;
    sec.dataset.afActionWatch = '1';
    sec.querySelectorAll('li.product').forEach(function(li){
      // run on the FIRST hover too: that is when the theme injects wishlist
      // and quick view, and the row must pick them up straight away
      li.addEventListener('mouseenter', function(){
        arrangeActions(sec);
        setTimeout(function(){ arrangeActions(sec); }, 60);
      });
    });
    try {
      new MutationObserver(function(){ arrangeActions(sec); })
        .observe(sec, {childList: true, subtree: true});
    } catch(e){}
  }

  function build(){
    document.querySelectorAll('.af-wl-related').forEach(function(sec){
      var ul = sec.querySelector('ul.products');
      if (!ul || sec.dataset.afRelSlider) return;
      sec.dataset.afRelSlider = '1';

      var vp = document.createElement('div');
      vp.className = 'af-rel-vp';
      ul.parentNode.insertBefore(vp, ul);
      vp.appendChild(ul);

      // one scrolling line instead of a wrapping grid
      ul.style.setProperty('display', 'flex', 'important');
      ul.style.setProperty('flex-wrap', 'nowrap', 'important');
      ul.style.setProperty('gap', GAP + 'px', 'important');
      ul.style.setProperty('overflow-x', 'auto', 'important');
      ul.style.setProperty('scroll-behavior', 'smooth', 'important');
      ul.style.setProperty('scroll-snap-type', 'x mandatory', 'important');
      ul.style.setProperty('margin', '0', 'important');
      ul.style.setProperty('padding', '0', 'important');
      ul.style.setProperty('list-style', 'none', 'important');
      sizeCards(ul);
      fillImages(sec);
      arrangeActions(sec);
      watchCards(sec);

      var prev = document.createElement('button');
      var next = document.createElement('button');
      prev.className = 'af-rel-nav af-rel-prev';
      next.className = 'af-rel-nav af-rel-next';
      prev.type = next.type = 'button';
      prev.setAttribute('aria-label', 'Previous products');
      next.setAttribute('aria-label', 'Next products');
      prev.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>';
      next.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>';
      vp.appendChild(prev);
      vp.appendChild(next);

      // The chevrons rendered blank: this theme has global button rules that
      // flatten inline svg inside buttons (the same fault that once produced
      // icon-less black circles on the chat launcher). Inline properties beat
      // those rules; and if the svg still measures zero after paint, swap in a
      // text chevron, which no button rule can shrink away.
      function forceIcon(btn, chr){
        btn.style.setProperty('color', '#fff', 'important');
        btn.style.setProperty('padding', '0', 'important');
        btn.style.setProperty('overflow', 'visible', 'important');
        btn.style.setProperty('line-height', '1', 'important');
        btn.style.setProperty('font-size', '26px', 'important');
        var svg = btn.querySelector('svg');
        if (svg) {
          svg.setAttribute('width', '20');
          svg.setAttribute('height', '20');
          svg.style.setProperty('width', '20px', 'important');
          svg.style.setProperty('height', '20px', 'important');
          svg.style.setProperty('display', 'block', 'important');
          svg.style.setProperty('fill', 'none', 'important');
          svg.style.setProperty('stroke', 'currentColor', 'important');
          svg.style.setProperty('stroke-width', '2.5', 'important');
          svg.style.setProperty('opacity', '1', 'important');
          svg.style.setProperty('visibility', 'visible', 'important');
        }
        setTimeout(function(){
          var g = btn.querySelector('svg');
          var w = g ? g.getBoundingClientRect().width : 0;
          if (w < 4) {
            btn.textContent = chr;
            btn.style.setProperty('font-weight', '700', 'important');
            btn.style.setProperty('font-family', 'system-ui, -apple-system, Segoe UI, sans-serif', 'important');
          }
        }, 300);
      }
      forceIcon(prev, '\u2039');
      forceIcon(next, '\u203A');

      function step(){
        var card = ul.querySelector('li.product');
        return card ? (card.getBoundingClientRect().width + GAP) : ul.clientWidth;
      }
      prev.addEventListener('click', function(){ ul.scrollBy({left: -step(), behavior: 'smooth'}); });
      next.addEventListener('click', function(){ ul.scrollBy({left:  step(), behavior: 'smooth'}); });

      function paint(){
        var max = ul.scrollWidth - ul.clientWidth - 2;
        prev.hidden = ul.scrollLeft <= 2;
        next.hidden = ul.scrollLeft >= max;
      }
      ul.addEventListener('scroll', paint, {passive: true});
      paint();
      var rt;
      window.addEventListener('resize', function(){
        clearTimeout(rt);
        rt = setTimeout(function(){ sizeCards(ul); fillImages(sec); paint(); }, 180);
      });
    });
  }
  function report(){
    if (location.search.indexOf('af_debug_cards=1') === -1) return;
    var box = document.getElementById('af-carddbg');
    if (!box) {
      box = document.createElement('div');
      box.id = 'af-carddbg';
      box.style.cssText = 'position:fixed;left:10px;bottom:10px;z-index:99999;background:#111;color:#9f9;'
        + 'font:12px/1.5 monospace;padding:10px 14px;border-radius:8px;max-width:520px;max-height:45vh;overflow:auto';
      document.body.appendChild(box);
    }
    var out = [];
    document.querySelectorAll('.af-wl-related li.product').forEach(function(li, i){
      if (i > 2) return;
      var found = [];
      li.querySelectorAll('a,button').forEach(function(el){
        var c = (el.className || '').toString().slice(0, 46);
        if (/cart|compare|quick|wish|wcwl|view/i.test(c)) found.push(el.tagName.toLowerCase() + '.' + c);
      });
      out.push('card ' + (i + 1) + ' row:' + (li.dataset.afActionRow || 'no') + '<br>&nbsp;&nbsp;' + (found.join('<br>&nbsp;&nbsp;') || 'none'));
    });
    box.innerHTML = out.join('<br>');
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', build);
  else build();
  window.addEventListener('load', build);
  [500, 1500, 3000].forEach(function(d){ setTimeout(build, d); });
  [1200, 3200].forEach(function(d){ setTimeout(report, d); });
  // images arrive lazily; re-fit after they land
  [800, 2000, 3500].forEach(function(d){
    setTimeout(function(){
      document.querySelectorAll('.af-wl-related').forEach(function(sec){
        fillImages(sec); arrangeActions(sec);
      });
    }, d);
  });
})();
</script>
    <?php
}, 24);

// ─────────────────────────────────────────────────────────────
// "Filter by Tag" — show only tags that actually have products here.
//
// The filter itself works (clicking #Canvas Art applies ?product_tag=canvas-art
// and the grid changes). The complaint is the tags that lead nowhere: the
// widget lists every tag in the store, including ones with no product in the
// category being browsed, so clicking them empties the page. Work out which
// tags have at least one product in this context and hide the rest. The filter
// logic, the widget and the links are untouched — only dead chips disappear.
//
// Two queries, cached for six hours per category.
// ─────────────────────────────────────────────────────────────
function af_live_tag_slugs() {
    if (!function_exists('is_shop')) return array();
    $term = (function_exists('is_product_category') && is_product_category())
          ? get_queried_object() : null;
    $key  = 'af_live_tags_' . (($term && !is_wp_error($term)) ? (int) $term->term_id : 'shop');

    $cached = get_transient($key);
    if (is_array($cached)) return $cached;

    $args = array('post_type' => 'product', 'post_status' => 'publish',
                  'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true);
    if ($term && !is_wp_error($term)) {
        $args['tax_query'] = array(array(
            'taxonomy' => 'product_cat', 'field' => 'term_id',
            'terms' => (int) $term->term_id, 'include_children' => true,
        ));
    }
    $ids = get_posts($args);
    $slugs = array();
    if ($ids) {
        $tags = wp_get_object_terms($ids, 'product_tag', array('fields' => 'all'));
        if (!is_wp_error($tags)) {
            foreach ($tags as $t) $slugs[$t->slug] = true;
        }
    }
    $slugs = array_keys($slugs);
    set_transient($key, $slugs, 6 * HOUR_IN_SECONDS);
    return $slugs;
}

add_action('wp_footer', function () {
    if (is_admin()) return;
    if (!function_exists('is_shop')) return;
    if (!is_shop() && !(function_exists('is_product_category') && is_product_category())) return;
    $live = af_live_tag_slugs();
    if (!$live) return;
    ?>
<script>
(function(){
  var LIVE = <?php echo wp_json_encode(array_values($live)); ?>;
  var ok = {};
  LIVE.forEach(function(s){ ok[s] = 1; });

  function slugOf(a){
    var d = a.getAttribute('data-val');
    if (d) return d;
    var href = a.getAttribute('href') || '';
    var m = href.match(/[?&]product_tag=([^&#]+)/);
    if (m) return decodeURIComponent(m[1]);
    m = href.match(/\/product-tag\/([^\/?#]+)/);
    return m ? decodeURIComponent(m[1]) : '';
  }
  function prune(){
    document.querySelectorAll('a[href*="product_tag="], a[href*="/product-tag/"], a.pf-value[data-val]')
      .forEach(function(a){
        // only inside a tag filter list — never touch tags printed on a product
        if (a.closest('li.product, .product-card, .woosw-item, .af-wl-related')) return;
        var slug = slugOf(a);
        if (!slug || ok[slug]) return;
        var chip = a.closest('li') || a;
        chip.style.setProperty('display', 'none', 'important');
      });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', prune);
  else prune();
  window.addEventListener('load', prune);
  [400, 1200, 2500].forEach(function(d){ setTimeout(prune, d); });
  try {
    new MutationObserver(function(m){
      for (var i = 0; i < m.length; i++) { if (m[i].addedNodes && m[i].addedNodes.length) { prune(); break; } }
    }).observe(document.body, {childList:true, subtree:true});
  } catch(e){}
})();
</script>
    <?php
}, 25);

// ─────────────────────────────────────────────────────────────
// Shop and category archives: three cards to a row (owner request,
// 2026-08-18). Four across made each card narrow enough that the titles
// wrapped to three lines and the art itself was the smallest thing on the
// page. Three gives the artwork room without leaving gaps.
//
// Scoped to the ARCHIVES only. The homepage sliders, the "You may also like"
// row on cart and wishlist, and the cross-sell row keep their own layouts —
// those were tuned separately and are not touched here.
// ─────────────────────────────────────────────────────────────
add_filter('loop_shop_columns', function () { return 3; }, 20);

add_action('wp_head', function () {
    if (is_admin()) return;
    if (!function_exists('is_shop')) return;
    if (!is_shop() && !is_product_category() && !is_product_tag() && !is_post_type_archive('product')) return;
    ?>
<style id="af-archive-3col">
/* the grid itself — written against every column class WooCommerce may emit */
body.woocommerce ul.products:not(.af-wl-related ul.products):not(.af-xsell ul.products),
body.woocommerce-page ul.products:not(.af-wl-related ul.products):not(.af-xsell ul.products),
.woocommerce ul.products.columns-1,
.woocommerce ul.products.columns-2,
.woocommerce ul.products.columns-4,
.woocommerce ul.products.columns-5,
.woocommerce ul.products.columns-6 {
  display: grid !important;
  grid-template-columns: repeat(3, 1fr) !important;
  gap: 22px !important;
  margin: 0 !important;
  padding: 0 !important;
  list-style: none !important;
}
.woocommerce ul.products::before,
.woocommerce ul.products::after { display: none !important; }

/* the cards: the theme floats them at a percentage width, which fights a grid */
.woocommerce ul.products li.product,
.woocommerce-page ul.products li.product {
  width: 100% !important;
  max-width: 100% !important;
  margin: 0 !important;
  float: none !important;
  clear: none !important;
}

/* the related/cross-sell rows keep their own four-across layout */
.af-wl-related ul.products,
.af-xsell ul.products {
  grid-template-columns: repeat(4, 1fr) !important;
  gap: 20px !important;
}

@media (max-width: 1024px) {
  body.woocommerce ul.products,
  body.woocommerce-page ul.products,
  .woocommerce ul.products.columns-4 { grid-template-columns: repeat(2, 1fr) !important; }
}
@media (max-width: 560px) {
  body.woocommerce ul.products,
  body.woocommerce-page ul.products,
  .woocommerce ul.products.columns-4 { grid-template-columns: 1fr !important; }
  .af-wl-related ul.products, .af-xsell ul.products { grid-template-columns: 1fr !important; }
}
</style>
    <?php
}, 99);


// ─────────────────────────────────────────────────────────────
// Filters must say the same thing as the product page.
//
// Frames: the product page has offered only Without Frame and Aluminium
// Frame since af_frames_in_stock() was introduced — Fibre and Floating are
// shown struck through and cannot be picked. Sizes: the page sells the five
// in af_sizes_offered() and no others. Neither restriction reached the
// filters, which listed all four frames and all fourteen sizes as equal,
// live choices with a count beside each — so a shopper could filter down to
// 337 products in a size or frame that is unbuyable on every one of them.
//
// The same two lists drive the filters and the product page, so putting an
// option back on sale is still one edit to af_frames_in_stock() or
// af_sizes_offered() and nothing else.
//
// Three routes in, because these lists appear in markup we own and markup we
// do not:
//   • WooCommerce's own layered-nav widget, via its term-html filter;
//   • our archive toolbar's Frame Type and Size dropdowns, where they are built;
//   • anything else — a theme widget, a filter block — by matching the label
//     text on the page, which is the only handle on markup this theme builds
//     in Elementor and does not expose a hook for.
//
// These options are HIDDEN, not struck through. Striking them was the first
// answer and it was wrong twice over: ten struck rows out of fifteen made the
// Size filter unreadable, a wall of greyed text with three live options lost
// in it, and a filter is a way to narrow a list — an option that narrows to
// nothing has no place in it. The product page is where a size gets explained
// (and it already drops the ones it does not sell); the sidebar just stops
// offering them.
// ─────────────────────────────────────────────────────────────

/**
 * WooCommerce's layered-nav widget: take the term out of the list.
 *
 * The widget wraps whatever this returns in its own <li>, and that <li> is not
 * ours to remove — so returning nothing would leave an empty row. Return a
 * marker instead, which the stylesheet below hides along with the <li> holding
 * it. Emitting the marker rather than relying on the row measuring "empty"
 * matters: an empty row is a guess about whitespace, and a marker is a fact.
 */
add_filter('woocommerce_layered_nav_term_html', function ($html, $term, $link, $count) {
    if (!function_exists('af_filter_label_is_oos')) return $html;
    $name = is_object($term) && isset($term->name) ? $term->name : '';
    if (!af_filter_label_is_oos($name)) return $html;
    return '<span class="af-fx-gone" data-af-oos="1" aria-hidden="true"></span>';
}, 10, 4);

add_action('wp_footer', function () {
    if (is_admin()) return;
    $oos = function_exists('af_filter_oos_labels') ? af_filter_oos_labels() : array();
    if (!$oos) return;
    ?>
<style id="af-filter-oos-css">
/* Hidden in the stylesheet, so the row is never painted — a row removed by
   script after first paint flashes on screen first, and on a fifteen-row Size
   filter that flash is the whole list jumping. The :has() rule takes the <li>
   the widget wrapped around our marker; the script below is the fallback for
   anything that route does not reach. */
.af-fx-gone{display:none !important;}
li:has(> .af-fx-gone),
.af-fx-oos{display:none !important;}
</style>
<script id="af-filter-oos">
(function(){
  var OOS = <?php echo wp_json_encode(array_map('strtolower', $oos)); ?>;
  if (!OOS.length) return;

  // Where a filter can plausibly live. Deliberately broad, because the match
  // itself is exact — an element only gets touched when its whole text is the
  // name of an option we cannot sell, so a wide net costs nothing.
  var SCOPE = '.widget, aside, .sidebar, .widget-area, .elementor-widget-woocommerce-products,' +
              '[class*="layered"], [class*="filter"], [class*="facet"], [class*="attribute"]';
  // …except the product page's own picker, which already says all this and
  // says it better.
  var SKIP  = '.af-chips, .af-opt-group, form.cart, .af-ftm-panel';

  function clean(t){
    return (t || '').replace(/\s+/g, ' ')
                    .replace(/\(\s*\d+\s*\)\s*$/, '')   // the "(337)" count
                    .trim().toLowerCase();
  }
  function isOos(el){ return OOS.indexOf(clean(el.textContent)) !== -1; }

  // Take the row off the page. Hidden rather than removed: these widgets
  // re-render themselves and count their own children, and deleting nodes out
  // from under a script that owns them is how you get a widget that breaks on
  // the second refine.
  function hide(row){
    row.setAttribute('data-af-fx-oos', '1');
    row.classList.add('af-fx-oos');
    row.setAttribute('aria-hidden', 'true');
    row.style.setProperty('display', 'none', 'important');
    // Hidden is not the same as unreachable: a display:none input is still
    // submitted with its form, and still focusable in some browsers.
    var kids = row.querySelectorAll('a, input, button');
    for (var i = 0; i < kids.length; i++) {
      var k = kids[i];
      k.setAttribute('tabindex', '-1');
      k.setAttribute('aria-hidden', 'true');
      if (k.tagName === 'INPUT' || k.tagName === 'BUTTON') k.disabled = true;
    }
  }

  function sweep(){
    var scopes = document.querySelectorAll(SCOPE);
    for (var s = 0; s < scopes.length; s++) {
      var sc = scopes[s];
      if (sc.closest && sc.closest(SKIP)) continue;

      // Rows the PHP hook already emptied. The <li> around our marker belongs
      // to the widget, so this is the only way to reach it if :has() does not
      // apply — and it must run BEFORE the label pass, which cannot see these
      // rows at all now that their text is gone.
      var gone = sc.querySelectorAll('.af-fx-gone');
      for (var g = 0; g < gone.length; g++) {
        var grow = gone[g].closest('li') || gone[g].parentElement;
        if (grow && !grow.hasAttribute('data-af-fx-oos')) hide(grow);
      }

      var cand = sc.querySelectorAll('li, label, a, span');
      for (var i = 0; i < cand.length; i++) {
        var el = cand[i];
        if (el.closest('[data-af-fx-oos]')) continue;   // this row is done
        if (el.closest(SKIP)) continue;
        if (!isOos(el)) continue;
        // Prefer the whole row, so the checkbox and the count go with it —
        // but only if the row is JUST this option and not a list that happens
        // to contain it.
        var row = el.closest('li');
        if (!row || !isOos(row)) row = el;
        hide(row);
      }
    }
  }

  var t = null, busy = false;
  function soon(){
    if (busy) return;
    clearTimeout(t);
    t = setTimeout(function(){
      busy = true;                 // our own DOM writes must not re-trigger us
      sweep();
      setTimeout(function(){ busy = false; }, 80);
    }, 100);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', soon);
  else soon();
  // Filter widgets re-render themselves after an AJAX refine, which drops the
  // marks — so watch for it rather than assuming one pass is enough.
  try { new MutationObserver(soon).observe(document.body, {childList:true, subtree:true}); } catch (e) {}
  [400, 1500].forEach(function(d){ setTimeout(soon, d); });
})();
</script>
    <?php
}, 98);

// ─────────────────────────────────────────────────────────────
// Product page: the picture stays put while the details scroll.
//
// The gallery is short and the buy column is long — size, frame, colour,
// price, delivery, the lot. Scrolling to read any of it used to carry the
// artwork off the top of the screen, so by the time someone was choosing a
// frame they could no longer see the thing they were framing. Pinning the
// gallery keeps the product in view for the whole decision.
//
// CSS alone cannot do this here. `position: sticky` is silently cancelled by
// any ancestor with a clipping overflow, and by a flex parent that stretches
// its children to full height — both of which this theme does, in markup the
// child theme does not own. So the rule is applied, and then the handful of
// conditions that would cancel it are cleared on the ancestors between the
// gallery and the row. Nothing else on the page is touched.
// ─────────────────────────────────────────────────────────────
add_action('wp_footer', function () {
    if (!function_exists('is_product') || !is_product()) return;
    ?>
<style id="af-sticky-gallery-css">
/* Only where there are genuinely two columns. Stacked on a phone, a sticky
   gallery would pin the image over the details underneath it. */
@media (min-width: 993px) {
  body.single-product .af-sticky-gallery {
    position: sticky !important;
    align-self: flex-start !important;   /* a stretched item is as tall as the
                                            row and can never stick */
    z-index: 2;
  }
}
/* Full size at rest; smaller only once the page has actually scrolled, so
   the picture is never permanently reduced just to make pinning possible.
   The size is eased rather than switched, because a picture that snaps to a
   new size mid-scroll reads as the page glitching. */
@media (min-width: 993px) {
  body.single-product .af-sticky-gallery .af-sg-shrink {
    transition: max-height .35s cubic-bezier(.22,.61,.36,1) !important;
    max-height: none;
  }
  body.single-product .af-sticky-gallery.af-sg-compact .af-sg-shrink {
    max-height: var(--af-sg-max, none) !important;
    height: auto !important;
    width: auto !important;
    max-width: 100% !important;
    object-fit: contain !important;
    margin-left: auto !important;
    margin-right: auto !important;
  }
}
@media (prefers-reduced-motion: reduce) {
  /* Sticky is not an animation, but it does mean the page moves in two
     speeds. Anyone who has asked for less of that gets the plain layout. */
  body.single-product .af-sticky-gallery { position: static !important; }
  /* …and with nothing pinned, shrinking the picture buys nothing at all —
     it would just be a smaller picture for no reason. */
  body.single-product .af-sticky-gallery .af-sg-shrink,
  body.single-product .af-sticky-gallery.af-sg-compact .af-sg-shrink {
    max-height: none !important;
    transition: none !important;
  }
}
</style>
<script id="af-sticky-gallery">
(function(){
  // The first version moved the gallery with translateY on every scroll frame.
  // Scroll paints first and the correction lands a frame later, which the eye
  // reads as the column JUMPING (owner recording, 2026-08-19). So no per-scroll
  // JS at all: the gallery column is stretched to end where the details column
  // ends, and a sticky wrapper INSIDE it pins the images while scrolling. The
  // browser's own compositor does the pinning — glassy smooth — and the sticky
  // range ends at the stretched column's bottom, which is exactly where the
  // Description / Reviews tabs begin.
  var TOP_GAP = 96;
  var g, s, inner;

  function pick(){
    g = document.querySelector('div.product .woocommerce-product-gallery');
    s = document.querySelector('div.product .summary.entry-summary, div.product .summary');
    return g && s;
  }
  function absTop(el){ var r = el.getBoundingClientRect(); return r.top + window.pageYOffset; }

  function wrapOnce(){
    if (inner) return;
    inner = document.createElement('div');
    inner.className = 'af-sg-inner';
    inner.style.setProperty('position', 'sticky');
    inner.style.setProperty('top', TOP_GAP + 'px');
    while (g.firstChild) inner.appendChild(g.firstChild);
    g.appendChild(inner);
  }

  // The gallery box is a flex container in this theme. That makes the wrapper a
  // flex item, and a flex item's default cross-axis behaviour is STRETCH: the
  // wrapper is pulled to the full height of the column we just stretched, so it
  // has no room left to slide in and sticky pins nothing. Measured in Chromium:
  //   flex gallery -> innerH 2000 of 2000, scroll 800 gives top -780 (not pinned)
  //   align-self:flex-start -> innerH 400 of 2000, scroll 800 gives top 96 (pinned)
  // Only the vertical stretch is cancelled. In a row flex container that is
  // align-self; the width is kept by letting the item grow along the row. In a
  // COLUMN container the axes are swapped -- align-self would shrink the width
  // instead -- so there we only make sure the item is not grown vertically.
  function unstretch(on){
    if (!inner) return;
    if (!on) {
      inner.style.removeProperty('align-self');
      inner.style.removeProperty('flex');
      return;
    }
    var cs = window.getComputedStyle(g);
    var d  = cs.display;
    if (d === 'flex' || d === 'inline-flex') {
      if ((cs.flexDirection || 'row').indexOf('column') === -1) {
        inner.style.setProperty('align-self', 'flex-start');
        inner.style.setProperty('flex', '1 1 auto');   // keep the full column width
      } else {
        inner.style.setProperty('flex', '0 0 auto');   // never grown down the column
      }
    } else if (d === 'grid' || d === 'inline-grid') {
      inner.style.setProperty('align-self', 'start');  // block axis only; width still stretches
    } else {
      inner.style.removeProperty('align-self');
      inner.style.removeProperty('flex');
    }
  }

  var sizing = false;
  function size(){
    if (!g || !s || sizing) return;
    sizing = true;
    if (window.innerWidth < 992) {              // stacked layout: no pinning
      g.style.height = '';
      if (inner) inner.style.position = 'static';
      unstretch(false);
      sizing = false;
      return;
    }
    if (inner) inner.style.position = 'sticky';
    unstretch(true);                            // before measuring: a stretched
                                                // wrapper reports the column's
                                                // height, not the images'
    g.style.height = '';                        // measure natural sizes first
    var innerH = inner ? inner.offsetHeight : g.offsetHeight;
    var target = absTop(s) + s.offsetHeight - absTop(g);
    if (target > innerH + 40) {
      g.style.height = Math.round(target) + 'px';
    }
    sizing = false;
  }

  // position:sticky silently does nothing if ANY ancestor clips overflow —
  // and theme wrappers often do. Walk up from the gallery and lift the clip on
  // those containers (recorded on the element so it is visible in devtools).
  // The gallery's own internals are left alone.
  // The walk below stops at <body> — but the two elements ABOVE it decide
  // whether anything can stick at all. When html AND body both set an overflow
  // (themes add overflow-x:hidden to both to kill a horizontal scrollbar),
  // body no longer propagates its value to the viewport and becomes a scroll
  // container in its own right. That container never scrolls, so a sticky
  // element inside it can never move. Reproduced in Chromium against the live
  // geometry: with both set the wrapper tracked the page 1:1 (top 300 - scrollY)
  // even with a 1020px wrapper inside a 2275px column.
  //
  // Simply clearing it would undo what the theme wanted: measured in Chromium,
  // an overflowing element then scrolls the page sideways again. So swap the
  // hidden for CLIP, which crops exactly the same but does NOT make the element
  // a scroll container -- the horizontal scrollbar stays gone and sticky works.
  // Only the horizontal axis is touched, and only where it was already hidden.
  function unclipRoot(){
    [document.documentElement, document.body].forEach(function(el){
      if (!el) return;
      var cs = window.getComputedStyle(el);
      if (cs.overflowX === 'hidden' && cs.overflowY !== 'hidden' && cs.overflowY !== 'scroll') {
        el.style.setProperty('overflow-x', 'clip', 'important');
        el.style.setProperty('overflow-y', 'visible', 'important');
        el.dataset.afSgRootClipped = '1';
      }
    });
  }

  function unclipAncestors(){
    unclipRoot();
    for (var a = g.parentElement; a && a !== document.body; a = a.parentElement) {
      var cs = window.getComputedStyle(a);
      if (cs.overflow !== 'visible' || cs.overflowY !== 'visible') {
        a.style.setProperty('overflow', 'visible', 'important');
        a.dataset.afSgUnclipped = '1';
      }
      // A transform / filter / perspective / paint-containment on an ancestor
      // makes it the sticky containing block, which quietly kills the pinning
      // just as surely as an overflow clip. Elementor's entrance animations
      // leave exactly these behind on wrapper containers. Clear them on plain
      // wrappers (marked for devtools); the gallery internals are untouched.
      var breaks = (cs.transform !== 'none') || (cs.filter && cs.filter !== 'none')
                || (cs.perspective && cs.perspective !== 'none')
                || (cs.contain && /paint|layout|content|strict/.test(cs.contain))
                || (cs.willChange && /transform|filter|perspective/.test(cs.willChange));
      if (breaks) {
        a.style.setProperty('transform', 'none', 'important');
        a.style.setProperty('filter', 'none', 'important');
        a.style.setProperty('perspective', 'none', 'important');
        a.style.setProperty('contain', 'none', 'important');
        a.style.setProperty('will-change', 'auto', 'important');
        a.dataset.afSgUnblocked = '1';
      }
    }
  }

  // Owner-only probe: /product-url/?af_debug_sticky=1 prints what the pinning
  // sees — a screenshot of this panel replaces another guessing round.
  function debugPanel(){
    if (location.search.indexOf('af_debug_sticky=1') === -1) return;
    var box = document.createElement('div');
    box.style.cssText = 'position:fixed;left:10px;bottom:10px;z-index:99999;background:#111;color:#9f9;'
      + 'font:12px/1.5 monospace;padding:10px 14px;border-radius:8px;max-width:480px;max-height:50vh;overflow:auto';
    var out = [];
    out.push('gallery: ' + (g ? 'found' : 'MISSING') + '  summary: ' + (s ? 'found' : 'MISSING'));
    if (inner) {
      var ics = getComputedStyle(inner);
      out.push('inner position: ' + ics.position + '  top: ' + ics.top);
    }
    if (g) out.push('gallery display: ' + getComputedStyle(g).display + '  inner align-self: ' + (inner ? getComputedStyle(inner).alignSelf : '-'));
    if (g) out.push('gallery height set: ' + (g.style.height || '(natural)') + '  innerH: ' + (inner ? inner.offsetHeight : '-'));
    if (s) out.push('summary height: ' + s.offsetHeight);
    if (g) {
      var n = 0;
      for (var a = g.parentElement; a && a !== document.body && n < 12; a = a.parentElement, n++) {
        var cs = getComputedStyle(a);
        out.push((a.className || a.tagName).toString().slice(0, 44)
          + ' | ov:' + cs.overflow + ' tf:' + (cs.transform === 'none' ? '-' : 'YES')
          + ' ft:' + (cs.filter === 'none' || !cs.filter ? '-' : 'YES')
          + (a.dataset.afSgUnclipped ? ' [unclipped]' : '')
          + (a.dataset.afSgUnblocked ? ' [unblocked]' : ''));
      }
    }
    box.innerHTML = out.join('<br>');
    document.body.appendChild(box);
  }

  function boot(){
    if (!pick()) return;
    wrapOnce();
    unclipAncestors();
    size();
    window.addEventListener('resize', size);
    g.querySelectorAll('img').forEach(function(im){ im.addEventListener('load', size); });
    try {
      new ResizeObserver(function(){ size(); }).observe(s);
    } catch(e){}
    debugPanel();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
  window.addEventListener('load', boot);
  setTimeout(boot, 1200);
})();
</script>
    <?php
}, 97);

// ─────────────────────────────────────────────────────────────
// The offers ticker: a single line of promises that scrolls under the header.
//
// Modelled on the reference recording, with one deliberate difference. The
// recording is a competitor's bar and its claims are theirs — "Free Shipping
// Across India", "Trusted by 1000+ Happy Customers", "Extra 5% OFF on Your
// First Order". Repeating those here would put statements on this shop that
// are either about another country or simply not established, and this file
// already carries the scar from that: af_shipping_copy() exists because the
// site once promised "Free Shipping across the USA" while its own popup
// listed four states. So the shipping line is taken from that one source
// rather than written again, and every other line is something the site
// already tells customers on its own product pages.
//
// The list is filterable. Anything the owner can stand behind can go in it,
// and nothing here has to be argued with to change it.
// ─────────────────────────────────────────────────────────────
function af_ticker_items() {
    $ship = function_exists('af_shipping_copy') ? af_shipping_copy() : array('label' => 'Shipping throughout the USA');
    return array_values( array_filter( (array) apply_filters('af_ticker_items', array(
        '🚚 ' . $ship['label'],
        '🖼️ Gallery-wrapped and ready to hang',
        '💎 Fade-resistant archival inks on museum-quality canvas',
        '✓ Inclusive of all taxes',
        '📦 Free secure packaging',
        '📐 Custom sizes available',
    )) ) );
}

// The offers ticker is a shopfront thing: it talks to customers about framing
// and shipping. On the staff tool pages it is noise running through work, so
// it is skipped there — only there. Everywhere a customer can go, it still
// runs exactly as before.
function af_ticker_is_staff_page() {
    if ( ! function_exists( 'is_page' ) ) { return false; }
    return is_page( apply_filters( 'af_ticker_hidden_pages', array(
        'admin-console', 'admin',
        'inventory-management', 'inventory',
        'activity-log', 'user-activity-log',
    ) ) );
}

/**
 * Where the ticker runs: the HOME PAGE, and nowhere else.
 *
 * It used to run on every page a customer could reach. On the home page it is
 * a welcome — six things the studio promises, moving past once. On a category
 * page it lands between the section's own description and the products, a
 * black band across the middle of the page saying things that have nothing to
 * do with what the shopper came to look at. The owner asked for it back on the
 * home page only, and that is the right place for it.
 *
 * Filterable, so widening it again later is one line and not an edit here.
 */
function af_ticker_should_run() {
    $ok = function_exists('is_front_page') ? (bool) is_front_page() : false;
    if ($ok && af_ticker_is_staff_page()) $ok = false;
    return (bool) apply_filters('af_ticker_should_run', $ok);
}

add_action('wp_footer', function () {
    if (is_admin()) return;
    if (!af_ticker_should_run()) return;
    $items = af_ticker_items();
    if (!$items) return;
    $run = '';
    foreach ($items as $it) {
        $run .= '<span class="af-tk-item">' . esc_html($it) . '</span><span class="af-tk-dot" aria-hidden="true">•</span>';
    }
    ?>
<style id="af-ticker-css">
.af-ticker{
  width:100%;
  overflow:hidden;
  background:#161616;
  border-top:1px solid rgba(255,255,255,.06);
  border-bottom:1px solid rgba(255,255,255,.06);
  padding:9px 0;
  position:relative;
  z-index:5;
}
/* The same seam rule as the video row: spacing belongs to the ITEM as a
   margin, never to the track as a flex gap. With gap, a run of n items is
   (n × item) + (n−1 × gap), so sliding by half the track lands half a gap
   short of where the second copy starts and the line twitches once a loop. */
.af-ticker-track{
  display:flex;
  flex-wrap:nowrap;
  width:max-content;
  will-change:transform;
  animation:af-tk-run var(--af-tk-dur,42s) linear infinite;
}
@keyframes af-tk-run{
  from{ transform:translate3d(0,0,0); }
  to  { transform:translate3d(-50%,0,0); }
}
.af-ticker:hover .af-ticker-track{ animation-play-state:paused; }
.af-tk-item,.af-tk-dot{
  flex:0 0 auto;
  white-space:nowrap;
  font-size:13px;
  line-height:1.2;
  color:#e8e2d4;
  letter-spacing:.01em;
}
.af-tk-item{ margin-right:26px; font-weight:600; }
.af-tk-dot { margin-right:26px; color:#c9a84c; }
@media (max-width:768px){
  .af-tk-item,.af-tk-dot{ font-size:11.5px; }
  .af-tk-item,.af-tk-dot{ margin-right:16px; }
  .af-ticker{ padding:7px 0; }
}
/* A line of text sliding sideways is exactly what "reduce motion" is about:
   hold it still and let it be scrolled by hand instead. */
@media (prefers-reduced-motion: reduce){
  .af-ticker-track{ animation:none; }
  .af-ticker{ overflow-x:auto; }
}
</style>

<div class="af-ticker" id="afTicker" role="complementary" aria-label="Store offers">
  <div class="af-ticker-track" id="afTickerTrack">
    <?php echo $run; ?>
    <?php /* The second run exists only to make the loop seamless — it must not
             be read out twice. */ ?>
    <span aria-hidden="true" style="display:contents"><?php echo $run; ?></span>
  </div>
</div>

<script id="af-ticker-js">
(function(){
  var bar = document.getElementById('afTicker');
  var track = document.getElementById('afTickerTrack');
  if (!bar || !track) return;

  // Sit it directly under the header, above whatever the page opens with.
  // The header is Elementor's, not ours, so it is found on the page rather
  // than assumed: the last header-ish element that actually sits at the top.
  function place(){
    if (bar.dataset.placed) return;
    var best = null;
    var cands = document.querySelectorAll(
      '.elementor-location-header, header.site-header, header#masthead, header[role="banner"], header'
    );
    for (var i = 0; i < cands.length; i++) {
      var h = cands[i];
      if (h.contains(bar)) continue;
      var r = h.getBoundingClientRect();
      if (r.height < 40) continue;                 // a stray inner <header>
      if (r.top > 400) continue;                   // not the page's own header
      if (!best || r.bottom > best.getBoundingClientRect().bottom) best = h;
    }
    if (!best) return;
    best.insertAdjacentElement('afterend', bar);
    bar.dataset.placed = '1';
  }

  // Speed, not duration: the run gets longer as items are added, so tying the
  // duration to the width keeps the pace the same however much is in it.
  function pace(){
    var w = track.scrollWidth / 2;               // one copy
    if (!w) return;
    var pxPerSec = 60;
    track.style.setProperty('--af-tk-dur', Math.max(18, Math.round(w / pxPerSec)) + 's');
  }

  // ── Make sure the bar occupies its own space ───────────────────────
  // Being placed after the header is not the same as having room. Two ways
  // this page takes the room back: the header can be taken out of normal flow
  // (a transparent header laid over the hero is a common build), which leaves
  // the bar out of flow with it and floating over the artwork; and the section
  // below can be pulled up under it with a negative margin, which a slider
  // does routinely.
  //
  // Rather than guess which, measure the result and push whatever follows down
  // by however much is actually covered.
  function outOfFlow(){
    for (var n = bar.parentElement; n && n !== document.body; n = n.parentElement) {
      var pos = window.getComputedStyle(n).position;
      if (pos === 'fixed' || pos === 'absolute') return true;
    }
    return false;
  }

  function room(){
    var next = bar.nextElementSibling;
    // Clear any room made earlier before measuring, or the measurement is of
    // the gap we ourselves opened and the bar keeps pushing the page down.
    if (next && next.dataset.afTkRoom) {
      next.style.removeProperty('margin-top');
      delete next.dataset.afTkRoom;
    }
    if (document.body.dataset.afTkRoom) {
      document.body.style.removeProperty('padding-top');
      delete document.body.dataset.afTkRoom;
    }

    var b = bar.getBoundingClientRect();
    if (!b.height) return;

    // Out of flow: the bar takes no space at all, so the page needs the whole
    // of its height back at the top.
    if (outOfFlow()) {
      document.body.style.setProperty('padding-top', Math.ceil(b.height) + 'px', 'important');
      document.body.dataset.afTkRoom = '1';
      return;
    }
    // In flow, but something below is reaching up under it.
    if (!next) return;
    var overlap = b.bottom - next.getBoundingClientRect().top;
    if (overlap > 1) {
      // ADD to whatever margin the theme already set, never replace it. The
      // section is usually reaching up because of a negative margin of its
      // own, so writing the overlap straight in throws that value away and
      // opens a gap exactly as big as the overlap used to be — the same
      // mistake in the other direction.
      var cur = parseFloat(window.getComputedStyle(next).marginTop) || 0;
      next.style.setProperty('margin-top', Math.ceil(cur + overlap) + 'px', 'important');
      next.dataset.afTkRoom = '1';
    }
  }

  function go(){ place(); pace(); room(); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', go);
  else go();
  window.addEventListener('load', go);
  window.addEventListener('resize', function(){ pace(); room(); });
  try { if (document.fonts && document.fonts.ready) document.fonts.ready.then(pace); } catch (e) {}
  [300, 900, 2000].forEach(function(d){ setTimeout(go, d); });
})();
</script>
    <?php
}, 96);

// ─────────────────────────────────────────────────────────────
// (Removed) Product page: the translateY version of the sticky gallery.
//
// It followed the scroll by writing g.style.transform on every scroll frame.
// Scroll paints first and the correction lands a frame later, which the eye
// reads as the column JUMPING — the fault the v2 block above was written to
// cure. v2 landed but this one was never taken out, and because it is later
// in the document its transform ran last and won, so the jump survived the
// fix. Deleted rather than disabled: two implementations of one behaviour on
// one element is how this page got here.
// ─────────────────────────────────────────────────────────────


// ─────────────────────────────────────────────────────────────
// Icons for the My Account rows.
//
// The list reads as a wall of words — Dashboard, Orders, Track Order,
// Downloads, Account details, Help, Log out — and a customer scanning it has
// nothing to aim at. One small mark per row makes each one findable at a
// glance without changing a single label.
//
// The rows live in two different places: the My Account page nav (WooCommerce
// markup, one class per endpoint) and the header account dropdown (theme
// markup, no useful classes at all). The endpoint classes carry the page nav;
// the dropdown rows are tagged client-side from their own label text.
//
// The icons are inline SVG masks, not images: the mark is painted in
// currentColor, so it takes the row's own colour on the dark dropdown and on
// the light page alike, and follows hover states for free. Nothing is fetched.
// ─────────────────────────────────────────────────────────────
function af_account_icons() {
    // 24x24, stroked, no fill — the shapes read at 16px, which is the size
    // they are actually drawn at next to the text.
    return array(
        'dashboard' => '<rect x="3" y="3" width="7.5" height="7.5" rx="1.6"/><rect x="13.5" y="3" width="7.5" height="7.5" rx="1.6"/><rect x="3" y="13.5" width="7.5" height="7.5" rx="1.6"/><rect x="13.5" y="13.5" width="7.5" height="7.5" rx="1.6"/>',
        'orders' => '<path d="M20.5 7.8v8.4a1.8 1.8 0 0 1-1 1.6l-6.6 3a2 2 0 0 1-1.8 0l-6.6-3a1.8 1.8 0 0 1-1-1.6V7.8"/><path d="M3.8 7.2 12 3.3l8.2 3.9L12 11.1z"/><path d="M12 11.1V21"/>',
        'track-order' => '<path d="M3 6.5h10.5v9H3z"/><path d="M13.5 9.5H17l3.5 3.2v2.8h-7z"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>',
        'downloads' => '<path d="M12 3v11.5"/><path d="m7.5 10.5 4.5 4.5 4.5-4.5"/><path d="M4 20.2h16"/>',
        'edit-account' => '<circle cx="12" cy="8" r="4"/><path d="M4.2 20.8c0-3.9 3.5-5.9 7.8-5.9s7.8 2 7.8 5.9"/>',
        'help' => '<circle cx="12" cy="12" r="9"/><path d="M9.4 9.3a2.7 2.7 0 1 1 3.5 2.6c-.7.3-1 .9-1 1.6v.4"/><path d="M11.95 17.2h.1"/>',
        'logout' => '<path d="M14.5 3.8H18a2 2 0 0 1 2 2v12.4a2 2 0 0 1-2 2h-3.5"/><path d="M9.5 8 5.5 12l4 4"/><path d="M5.5 12h9"/>',
        'messages' => '<path d="M20 4.8H4a1.2 1.2 0 0 0-1.2 1.2v9.6A1.2 1.2 0 0 0 4 16.8h3v4l5-4h8a1.2 1.2 0 0 0 1.2-1.2V6A1.2 1.2 0 0 0 20 4.8z"/>',
        'returns' => '<path d="M9 4.2 4.2 9 9 13.8"/><path d="M4.2 9h9.3a6.3 6.3 0 1 1 0 12.6H8.5"/>',
        'saved-previews' => '<rect x="3" y="4.5" width="18" height="15" rx="2"/><circle cx="8.6" cy="10" r="1.8"/><path d="m3.6 17.4 5-4.8 3.9 3.8 2.9-2 5 4.4"/>',
        'edit-address' => '<path d="M12 21.2s6.8-5.4 6.8-10.6a6.8 6.8 0 1 0-13.6 0c0 5.2 6.8 10.6 6.8 10.6z"/><circle cx="12" cy="10.2" r="2.6"/>',
        'payment-methods' => '<rect x="2.8" y="5.2" width="18.4" height="13.6" rx="2"/><path d="M2.8 10h18.4"/>',
        'wishlist' => '<path d="M12 20.4 4.9 13.5a4.6 4.6 0 0 1 6.4-6.6l.7.7.7-.7a4.6 4.6 0 1 1 6.4 6.6z"/>',
    );
}

// Which selectors each icon answers to. The page nav gives us an endpoint
// class; the dropdown gives us only the tag the script below writes on.
function af_account_icon_selectors( $key ) {
    $endpoints = array(
        'dashboard'       => array( 'dashboard' ),
        'orders'          => array( 'orders' ),
        'track-order'     => array( 'af-track-order' ),
        'downloads'       => array( 'downloads' ),
        'edit-account'    => array( 'edit-account' ),
        'help'            => array( 'af-help' ),
        'logout'          => array( 'customer-logout' ),
        'messages'        => array( 'messages' ),
        'returns'         => array( 'returns' ),
        'saved-previews'  => array( 'saved-previews' ),
        'edit-address'    => array( 'edit-address', 'edit-addresses' ),
        'payment-methods' => array( 'payment-methods' ),
        'wishlist'        => array( 'wishlist' ),
    );
    $sel = array( 'a[data-af-acc-icon="' . $key . '"]' );
    foreach ( ( isset( $endpoints[ $key ] ) ? $endpoints[ $key ] : array() ) as $ep ) {
        $sel[] = '.woocommerce-MyAccount-navigation li.woocommerce-MyAccount-navigation-link--' . $ep . ' > a';
        $sel[] = '.woocommerce-MyAccount-navigation li.woocommerce-MyAccount-navigation-link--' . $ep . ' > a > span';
    }
    return $sel;
}

add_action( 'wp_footer', function () {
	if ( is_admin() ) {
		return;
	}
	$icons = af_account_icons();
	$all   = array();
	$rules = '';
	foreach ( $icons as $key => $paths ) {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" '
		     . 'stroke="#000" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">'
		     . $paths . '</svg>';
		$sel = af_account_icon_selectors( $key );
		$all = array_merge( $all, $sel );
		$rules .= implode( ",\n", $sel ) . " {\n  --af-acc-icon: url(\"data:image/svg+xml;charset=utf-8,"
		        . rawurlencode( $svg ) . "\");\n}\n";
	}
	// Every selector names the element that carries the mark, so a variant is
	// built by appending to it rather than by rewriting the middle of it.
	$with = function ( $suffix ) use ( $all ) {
		$out = array();
		foreach ( $all as $s ) { $out[] = $s . $suffix; }
		return implode( ",\n", $out );
	};
	// One number moves the words and the mark together, because the mark is
	// sized in em: at 1.1 the row is 10% larger and the icon grows with it,
	// keeping their proportions exactly as they were.
	$scale = (float) apply_filters( 'af_account_row_scale', 1.12 );
	$icon  = (float) apply_filters( 'af_account_icon_scale', 1.15 );
	?>
<style id="af-account-icons-css">
<?php echo $rules; // built above from the icon table ?>
<?php echo $with( '' ); ?> {
  font-size: <?php echo esc_html( number_format( $scale, 3, '.', '' ) ); ?>em;
}
<?php echo $with( '::before' ); ?> {
  content: "" !important;
  display: inline-block !important;
  width: <?php echo esc_html( number_format( $icon, 3, '.', '' ) ); ?>em !important;
  height: <?php echo esc_html( number_format( $icon, 3, '.', '' ) ); ?>em !important;
  margin-right: .55em !important;
  vertical-align: -.18em !important;
  background-color: currentColor !important;
  opacity: .8;
  -webkit-mask-image: var(--af-acc-icon);
          mask-image: var(--af-acc-icon);
  -webkit-mask-repeat: no-repeat;
          mask-repeat: no-repeat;
  -webkit-mask-position: center;
          mask-position: center;
  -webkit-mask-size: contain;
          mask-size: contain;
}
/* The mark belongs to the row, so it brightens with it rather than staying
   a flat grey while the words light up. */
<?php echo $with( ':hover::before' ); ?> {
  opacity: 1;
}
/* Without mask support there is no way to tint the shape to the row's colour,
   and a black mark on a dark dropdown would be worse than none. */
@supports not ((-webkit-mask-image: none) or (mask-image: none)) {
  <?php echo $with( '::before' ); ?> { display: none !important; }
}
</style>
<script id="af-account-icons">
(function(){
  // The rows are identified by the words the customer reads, because the
  // dropdown is the theme's own markup and carries nothing else to match on.
  // Matching is exact on the trimmed label — a substring test would tag
  // "Order history" as the logout row on any site that renames things.
  var MAP = {
    'dashboard': 'dashboard',
    'orders': 'orders', 'my orders': 'orders',
    'track order': 'track-order', 'track your order': 'track-order',
    'downloads': 'downloads',
    'account details': 'edit-account', 'account detail': 'edit-account',
    'help': 'help', 'help & support': 'help', 'help and support': 'help',
    'log out': 'logout', 'logout': 'logout', 'sign out': 'logout',
    'messages': 'messages',
    'returns': 'returns',
    'saved previews': 'saved-previews',
    'addresses': 'edit-address', 'address': 'edit-address',
    'payment methods': 'payment-methods',
    'wishlist': 'wishlist'
  };

  // The first version looked for ul > li > a. That is the WooCommerce page
  // nav's shape, not the header dropdown's — the dropdown showed no icons at
  // all (owner screenshot, 2026-08-20). The Track Order and Help rows this
  // theme does show are injected by a script that walks ANCHORS and ignores
  // the surrounding structure, which is the proof that anchors are the thing
  // to match. So: walk anchors, and let the markup around them be whatever it
  // is. The mark is drawn on the anchor itself.
  function inAccountMenu(a){
    // "Log out" is the giveaway no other menu on the site carries — but only
    // when it is read from the MENU, not from the page. A first attempt walked
    // plain ancestors and reached <body>, where the words exist somewhere, so
    // an unrelated menu with an "Orders" link got tagged too. So: climb to the
    // nearest thing that is actually a menu container and test only that.
    for (var n = a.parentElement, i = 0; n && n !== document.body && i < 8; n = n.parentElement, i++) {
      var tag = n.tagName;
      var cls = (typeof n.className === 'string' ? n.className : '').toLowerCase();
      var isMenu = tag === 'UL' || tag === 'OL' || tag === 'NAV'
                || /(^|[\s_-])(menu|dropdown|submenu|nav|account)([\s_-]|$)/.test(cls);
      if (!isMenu) continue;
      var t = (n.textContent || '').toLowerCase();
      if (t.indexOf('log out') !== -1 || t.indexOf('logout') !== -1
          || t.indexOf('sign out') !== -1 || t.indexOf('account details') !== -1) {
        return true;
      }
    }
    return false;
  }

  function tag(){
    var anchors = document.querySelectorAll('a[href]:not([data-af-acc-icon])');
    for (var i = 0; i < anchors.length; i++) {
      var a = anchors[i];
      var label = (a.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
      var key = MAP[label];
      if (!key) continue;
      if (!inAccountMenu(a)) continue;
      a.setAttribute('data-af-acc-icon', key);
    }
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', tag);
  else tag();
  // The dropdown is often built (or cloned into) after load, and the Track
  // Order / Help rows are added by our own later script.
  [400, 1200, 2500].forEach(function(d){ setTimeout(tag, d); });
  document.addEventListener('click', function(){ setTimeout(tag, 60); }, true);
  document.addEventListener('mouseover', function(e){
    // Menus that build themselves on hover would otherwise never be tagged.
    if (e.target && e.target.closest && e.target.closest('a,li,nav')) setTimeout(tag, 60);
  }, true);
})();
</script>
	<?php
}, 95 );

// ─────────────────────────────────────────────────────────────
// The Edit screen behind Inventory Management's "Edit" button.
//
// That button used to open wp-admin's product editor in a new tab: a large,
// unfamiliar screen with dozens of fields, ninety per cent of which nobody
// managing stock will ever touch. This is the small version — the handful of
// things this shop actually edits, in the console's own styling, on the page
// they were already on.
//
// It lives at /inventory-management/?af_edit=<id> rather than a page of its
// own on purpose: the slug already exists, already has the right permission
// gate, and "Back to inventory" is then genuinely back rather than sideways.
// The escape hatch to the full WordPress editor is still there for the rare
// field this screen deliberately leaves out.
//
// Who may change what follows the same split as the rest of the console:
// anyone who can open Inventory may edit stock, because that is the job the
// page exists for; the rest — title, prices, art code, published state — is
// administrators only, and the fields are simply not rendered for others.
// ─────────────────────────────────────────────────────────────

function af_pe_can_edit_all() {
	return is_user_logged_in() && current_user_can( 'manage_options' );
}

function af_pe_url( $pid ) {
	return add_query_arg( 'af_edit', (int) $pid, af_inv_url() );
}

add_action( 'wp_ajax_af_product_edit_save', function () {
	if ( ! function_exists( 'af_inv_can_access' ) || ! af_inv_can_access() ) {
		wp_send_json_error( array( 'message' => 'You do not have permission to edit products.' ), 403 );
	}
	check_ajax_referer( 'af_product_edit', 'nonce' );

	$pid     = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
	$product = $pid ? wc_get_product( $pid ) : null;
	if ( ! $product ) {
		wp_send_json_error( array( 'message' => 'That product no longer exists.' ), 404 );
	}

	$full    = af_pe_can_edit_all();
	$changed = array();
	$notes   = array();

	// ── Stock: the part anyone with console access may change ───────────────
	if ( isset( $_POST['manage_stock'] ) ) {
		$manage = $_POST['manage_stock'] === '1';
		$product->set_manage_stock( $manage );
		if ( $manage ) {
			$qty = isset( $_POST['stock_qty'] ) ? (int) $_POST['stock_qty'] : 0;
			$product->set_stock_quantity( max( 0, $qty ) );
			// Let the count decide the status while a count is being kept —
			// a tracked product showing "in stock" at zero is how oversells
			// happen.
			$product->set_stock_status( $qty > 0 ? 'instock' : 'outofstock' );
			$changed[] = 'stock count';
		} else {
			$product->set_stock_quantity( null );
			$status = isset( $_POST['stock_status'] ) ? sanitize_text_field( wp_unslash( $_POST['stock_status'] ) ) : 'instock';
			if ( ! in_array( $status, array( 'instock', 'outofstock', 'onbackorder' ), true ) ) { $status = 'instock'; }
			$product->set_stock_status( $status );
			$changed[] = 'stock status';
		}
	}

	// ── Everything else: administrators only ────────────────────────────────
	if ( $full ) {
		if ( isset( $_POST['title'] ) ) {
			$title = sanitize_text_field( wp_unslash( $_POST['title'] ) );
			if ( $title === '' ) {
				wp_send_json_error( array( 'message' => 'A product needs a name.' ), 400 );
			}
			if ( $title !== $product->get_name() ) {
				$product->set_name( $title );
				$changed[] = 'name';
			}
		}

		if ( isset( $_POST['sku'] ) ) {
			$sku = trim( sanitize_text_field( wp_unslash( $_POST['sku'] ) ) );
			if ( $sku !== (string) $product->get_sku() ) {
				$holder = $sku !== '' ? (int) wc_get_product_id_by_sku( $sku ) : 0;
				if ( $holder && $holder !== $pid ) {
					wp_send_json_error( array(
						'message' => 'SKU "' . $sku . '" is already used by ' . get_the_title( $holder )
						           . ' (#' . $holder . '). Two products cannot share one.',
					), 409 );
				}
				$product->set_sku( $sku );
				$changed[] = 'SKU';
			}
		}

		foreach ( array( 'regular_price', 'sale_price' ) as $field ) {
			if ( ! isset( $_POST[ $field ] ) ) { continue; }
			$raw = trim( (string) wp_unslash( $_POST[ $field ] ) );
			if ( $raw !== '' && ! is_numeric( $raw ) ) {
				wp_send_json_error( array( 'message' => str_replace( '_', ' ', $field ) . ' must be a number.' ), 400 );
			}
			$product->{ 'set_' . $field }( $raw === '' ? '' : wc_format_decimal( $raw ) );
		}
		$reg  = (float) $product->get_regular_price();
		$sale = $product->get_sale_price();
		if ( $sale !== '' && (float) $sale > 0 && $reg > 0 && (float) $sale >= $reg ) {
			wp_send_json_error( array(
				'message' => 'The sale price must be below the regular price, or a customer sees a discount that is not one.',
			), 400 );
		}
		$changed[] = 'price';

		if ( isset( $_POST['status'] ) ) {
			$status = wp_unslash( $_POST['status'] ) === 'publish' ? 'publish' : 'draft';
			if ( $status !== $product->get_status() ) {
				$product->set_status( $status );
				$changed[] = $status === 'publish' ? 'published' : 'moved to draft';
			}
		}
	}

	$product->save();

	if ( $full && isset( $_POST['art_code'] ) ) {
		$code = preg_replace( '/\s+/', ' ', trim( sanitize_text_field( wp_unslash( $_POST['art_code'] ) ) ) );
		$was  = (string) get_post_meta( $pid, '_taf_art_code', true );
		if ( $code !== $was ) {
			if ( $code === '' ) {
				delete_post_meta( $pid, '_taf_art_code' );
			} else {
				update_post_meta( $pid, '_taf_art_code', $code );
			}
			$changed[] = 'art code';
			// The SKU pass takes its cue from this marker; clearing it lets the
			// next deploy reconsider this product with its new code.
			delete_post_meta( $pid, '_af_sku_artcode' );
			$notes[] = 'The SKU will follow the new art code on the next deploy, if that code is not already in use elsewhere.';
		}
	}

	if ( function_exists( 'wc_delete_product_transients' ) ) { wc_delete_product_transients( $pid ); }

	$fresh = wc_get_product( $pid );
	wp_send_json_success( array(
		'message' => $changed ? 'Saved — ' . implode( ', ', array_unique( $changed ) ) . '.' : 'Nothing had changed.',
		'notes'   => $notes,
		'product' => array(
			'title'  => html_entity_decode( wp_strip_all_tags( $fresh->get_name() ) ),
			'sku'    => (string) $fresh->get_sku(),
			'price'  => (string) $fresh->get_price(),
			'qty'    => $fresh->get_manage_stock() ? (int) $fresh->get_stock_quantity() : null,
			'status' => $fresh->get_stock_status(),
			'state'  => $fresh->get_status(),
		),
	) );
} );

// The screen. Rendered in place of the inventory table when ?af_edit=<id> is
// present, so the permission gate, the header and the styling are already the
// ones the console uses.
add_action( 'template_redirect', function () {
	if ( ! function_exists( 'is_page' ) || ! is_page( array( 'inventory-management', 'inventory' ) ) ) { return; }
	if ( ! isset( $_GET['af_edit'] ) ) { return; }
	if ( ! function_exists( 'af_inv_can_access' ) || ! af_inv_can_access() ) {
		wp_die( 'You do not have permission to view this page.', 'Access Denied',
			array( 'response' => 403, 'back_link' => true ) );
	}

	$pid     = (int) $_GET['af_edit'];
	$product = $pid ? wc_get_product( $pid ) : null;
	if ( ! $product ) {
		wp_safe_redirect( af_inv_url() );
		exit;
	}

	$full   = af_pe_can_edit_all();
	$code   = (string) get_post_meta( $pid, '_taf_art_code', true );
	$img    = get_the_post_thumbnail_url( $pid, 'medium' ) ?: wc_placeholder_img_src( 'medium' );
	$sym    = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$';
	$manage = (bool) $product->get_manage_stock();

	get_header();
	?>
<div class="af-inv-wrap af-pe-wrap">
  <a class="af-pe-back" href="<?php echo esc_url( af_inv_url() ); ?>">&larr; Back to inventory</a>

  <div class="af-pe-head">
    <div>
      <span class="af-inv-badge">ADMIN ONLY</span>
      <h1>Edit product</h1>
      <p class="af-inv-sub">
        <?php echo $full
          ? 'Change what a customer sees, then save. Anything not here is in the full WordPress editor.'
          : 'You can set the stock for this product. Names, prices and codes are administrators only.'; ?>
      </p>
    </div>
    <div class="af-pe-headact">
      <a class="af-inv-back" href="<?php echo esc_url( get_permalink( $pid ) ); ?>" target="_blank" rel="noopener">View on site</a>
      <a class="af-inv-back" href="<?php echo esc_url( get_edit_post_link( $pid, 'raw' ) ); ?>" target="_blank" rel="noopener">Full editor</a>
    </div>
  </div>

  <form class="af-pe-form" id="af-pe-form" data-id="<?php echo (int) $pid; ?>">
    <div class="af-pe-grid">
      <div class="af-pe-media">
        <img src="<?php echo esc_url( $img ); ?>" alt="">
        <span class="af-pe-pid">#<?php echo (int) $pid; ?></span>
      </div>

      <div class="af-pe-fields">
        <?php if ( $full ) : ?>
          <label class="af-pe-field">
            <span class="af-pe-label">Product name</span>
            <input type="text" name="title" value="<?php echo esc_attr( html_entity_decode( wp_strip_all_tags( $product->get_name() ) ) ); ?>" maxlength="200">
          </label>

          <div class="af-pe-row">
            <label class="af-pe-field">
              <span class="af-pe-label">Art code</span>
              <input type="text" name="art_code" value="<?php echo esc_attr( $code ); ?>" placeholder="RK 01">
              <span class="af-pe-hint">The code from the collection book.</span>
            </label>
            <label class="af-pe-field">
              <span class="af-pe-label">SKU</span>
              <input type="text" name="sku" value="<?php echo esc_attr( (string) $product->get_sku() ); ?>">
              <span class="af-pe-hint">Must be unique across the shop.</span>
            </label>
          </div>

          <div class="af-pe-row">
            <label class="af-pe-field">
              <span class="af-pe-label">Regular price (<?php echo esc_html( $sym ); ?>)</span>
              <input type="text" inputmode="decimal" name="regular_price" value="<?php echo esc_attr( (string) $product->get_regular_price() ); ?>">
            </label>
            <label class="af-pe-field">
              <span class="af-pe-label">Sale price (<?php echo esc_html( $sym ); ?>)</span>
              <input type="text" inputmode="decimal" name="sale_price" value="<?php echo esc_attr( (string) $product->get_sale_price() ); ?>">
              <span class="af-pe-hint">Leave empty for no sale.</span>
            </label>
          </div>
        <?php else : ?>
          <div class="af-pe-readonly">
            <span class="af-pe-label">Product</span>
            <strong><?php echo esc_html( html_entity_decode( wp_strip_all_tags( $product->get_name() ) ) ); ?></strong>
            <span class="af-pe-hint">SKU <?php echo esc_html( $product->get_sku() ?: '—' ); ?></span>
          </div>
        <?php endif; ?>

        <fieldset class="af-pe-stock">
          <legend class="af-pe-label">Stock</legend>
          <label class="af-pe-check">
            <input type="checkbox" name="manage_stock" id="af-pe-manage" <?php checked( $manage ); ?>>
            <span>Keep a count for this product</span>
          </label>

          <div class="af-pe-row af-pe-stockrow">
            <label class="af-pe-field" id="af-pe-qtyfield" <?php echo $manage ? '' : 'hidden'; ?>>
              <span class="af-pe-label">Units in stock</span>
              <input type="number" min="0" step="1" name="stock_qty"
                     value="<?php echo esc_attr( $manage ? (int) $product->get_stock_quantity() : 0 ); ?>">
              <span class="af-pe-hint">Zero marks it out of stock.</span>
            </label>

            <label class="af-pe-field" id="af-pe-statusfield" <?php echo $manage ? 'hidden' : ''; ?>>
              <span class="af-pe-label">Availability</span>
              <select name="stock_status">
                <?php foreach ( array( 'instock' => 'In stock', 'outofstock' => 'Out of stock', 'onbackorder' => 'On backorder' ) as $v => $l ) : ?>
                  <option value="<?php echo esc_attr( $v ); ?>" <?php selected( $product->get_stock_status(), $v ); ?>><?php echo esc_html( $l ); ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>
        </fieldset>

        <?php if ( $full ) : ?>
          <label class="af-pe-check af-pe-publish">
            <input type="checkbox" name="status" <?php checked( $product->get_status(), 'publish' ); ?>>
            <span>Visible in the shop</span>
          </label>
        <?php endif; ?>

        <div class="af-pe-actions">
          <button type="submit" class="af-pe-save">Save changes</button>
          <a class="af-pe-cancel" href="<?php echo esc_url( af_inv_url() ); ?>">Cancel</a>
          <span class="af-pe-msg" id="af-pe-msg" role="status" aria-live="polite"></span>
        </div>
      </div>
    </div>
  </form>
</div>

<style>
.af-pe-wrap{max-width:1000px;}
.af-pe-back{display:inline-block;margin-bottom:14px;color:#6b6250;text-decoration:none;font-size:13px;font-weight:700;}
.af-pe-back:hover{color:#a8801f;}
.af-pe-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;flex-wrap:wrap;margin-bottom:20px;}
.af-pe-headact{display:flex;gap:10px;flex-wrap:wrap;}
.af-pe-form{background:#fffdf8;border:1px solid #efe6d2;border-radius:14px;padding:22px;
  box-shadow:0 4px 18px rgba(70,54,26,.07);}
.af-pe-grid{display:grid;grid-template-columns:220px 1fr;gap:26px;align-items:start;}
.af-pe-media{position:relative;}
.af-pe-media img{width:100%;border-radius:11px;border:1px solid #ece4cf;display:block;background:#f7f0df;}
.af-pe-pid{position:absolute;left:10px;bottom:10px;background:rgba(26,26,26,.82);color:#fff;font-size:11px;
  font-weight:800;letter-spacing:.03em;border-radius:6px;padding:3px 8px;}
.af-pe-fields{min-width:0;display:flex;flex-direction:column;gap:16px;}
.af-pe-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.af-pe-field{display:flex;flex-direction:column;gap:6px;min-width:0;}
/* The browser's own [hidden] rule is display:none, which a class-based
   display:flex outranks — so hiding a field by attribute alone left it on
   screen. Restate it at higher specificity. */
.af-pe-field[hidden]{display:none;}
.af-pe-label{font-size:11px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;color:#6b6250;}
.af-pe-field input,.af-pe-field select{height:40px;padding:0 11px;border:1.5px solid #e2d9c4;border-radius:9px;
  background:#fff;color:#1a1a1a;font-size:14px;font-weight:600;width:100%;box-sizing:border-box;}
.af-pe-field input:focus,.af-pe-field select:focus{outline:none;border-color:#c9a84c;background:#fffdf8;}
.af-pe-hint{font-size:11.5px;color:#8a8170;}
.af-pe-readonly{display:flex;flex-direction:column;gap:4px;}
.af-pe-stock{border:1px solid #efe6d2;border-radius:11px;padding:14px 16px;margin:0;background:#fffefb;
  display:flex;flex-direction:column;gap:12px;}
.af-pe-stock legend{padding:0 6px;}
.af-pe-check{display:flex;align-items:center;gap:9px;font-size:13.5px;font-weight:600;color:#1a1a1a;cursor:pointer;}
.af-pe-check input{width:17px;height:17px;accent-color:#c9a84c;}
.af-pe-publish{padding-top:2px;}
.af-pe-actions{display:flex;align-items:center;gap:14px;flex-wrap:wrap;padding-top:4px;}
.af-pe-save{height:42px;padding:0 22px;border:none;border-radius:9px;background:#1a1a1a;color:#fff;
  font-size:13.5px;font-weight:800;letter-spacing:.02em;cursor:pointer;}
.af-pe-save:hover{background:#c9a84c;color:#1a1a1a;}
.af-pe-save[disabled]{opacity:.55;cursor:default;}
.af-pe-cancel{color:#6b6250;text-decoration:none;font-size:13px;font-weight:700;}
.af-pe-cancel:hover{color:#a8801f;}
.af-pe-msg{font-size:13px;font-weight:700;}
.af-pe-msg.is-ok{color:#1e8b56;}
.af-pe-msg.is-bad{color:#b4453a;}
.af-pe-note{margin:10px 0 0;font-size:12.5px;color:#8a8170;}
@media(max-width:760px){
  .af-pe-grid{grid-template-columns:1fr;}
  .af-pe-row{grid-template-columns:1fr;}
}
</style>

<script>
(function(){
  var form = document.getElementById('af-pe-form');
  if (!form) return;
  var msg    = document.getElementById('af-pe-msg');
  var manage = document.getElementById('af-pe-manage');
  var qty    = document.getElementById('af-pe-qtyfield');
  var stat   = document.getElementById('af-pe-statusfield');
  var AJAX   = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
  var NONCE  = <?php echo wp_json_encode( wp_create_nonce( 'af_product_edit' ) ); ?>;

  // A count and an availability dropdown are two ways of saying the same
  // thing, so only the one in use is shown.
  function sync(){
    if (!manage) return;
    if (qty)  qty.hidden  = !manage.checked;
    if (stat) stat.hidden =  manage.checked;
  }
  if (manage) manage.addEventListener('change', sync);
  sync();

  function say(text, ok){
    msg.textContent = text;
    msg.className = 'af-pe-msg ' + (ok ? 'is-ok' : 'is-bad');
  }

  form.addEventListener('submit', function(e){
    e.preventDefault();
    var btn = form.querySelector('.af-pe-save');
    btn.disabled = true;
    say('Saving…', true);

    var body = new URLSearchParams();
    body.set('action', 'af_product_edit_save');
    body.set('nonce', NONCE);
    body.set('id', form.dataset.id);
    form.querySelectorAll('input[name], select[name]').forEach(function(el){
      if (el.type === 'checkbox') { body.set(el.name, el.checked ? '1' : '0'); }
      else { body.set(el.name, el.value); }
    });

    fetch(AJAX, { method:'POST', credentials:'same-origin', body: body })
      .then(function(r){ return r.json().catch(function(){ throw new Error('The server did not answer in a way we could read.'); }); })
      .then(function(res){
        if (!res || !res.success) {
          throw new Error((res && res.data && res.data.message) || 'That did not save.');
        }
        say(res.data.message, true);
        if (res.data.notes && res.data.notes.length) {
          var n = document.createElement('p');
          n.className = 'af-pe-note';
          n.textContent = res.data.notes.join(' ');
          form.querySelector('.af-pe-actions').insertAdjacentElement('afterend', n);
        }
      })
      .catch(function(err){ say(err.message, false); })
      .then(function(){ btn.disabled = false; });
  });
})();
</script>
	<?php
	get_footer();
	exit;
}, 0 );
