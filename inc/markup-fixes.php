<?php
/**
 * Finishing fixes from the full site test of 2026-10-23.
 *
 * Each part answers one measured finding (tools/probe-site-details.mjs, run
 * against the live shop). Each is small, applies only where the finding was,
 * and leaves a page untouched when the problem is not there.
 *
 *   1. ONE H1 ON EVERY PAGE. Fifteen pages and posts, nine blog archives and
 *      the empty categories had none. Blog archives and empty categories get a
 *      visible title (they showed none at all); a page whose title is already
 *      shown as a smaller heading gets the visually-hidden H1 page-headings.php
 *      uses for login, so nothing visible changes. The front page is left
 *      alone: it has no keyword H1 by the owner's request (functions.php 24d).
 *
 *   2. PICTURES THE SIZE THEY ARE SHOWN. The homepage's trending cards drew
 *      1024px files into 313px boxes, and the header logo a 1600px file into
 *      204px. The cards switch to the 768px copy WordPress already made of
 *      the same picture; the logo gets a srcset so the browser picks its own.
 *
 *   3. A DESCRIPTION WHERE NONE WAS WRITTEN. Try On Wall, Frame The Moment,
 *      the blog topics and other pages had no meta description, so search
 *      engines wrote their own. Rank Math prints ours when its own is empty.
 *
 *   4. TAP TARGETS. Slider dots were 6px, card buttons 19px tall, the phone
 *      bottom bar 15px, glyph buttons 13-16px: under WCAG 2.2's 24px minimum.
 *      Their tappable area grows with padding cancelled by an equal negative
 *      margin (or, for the dots, a larger invisible pseudo-element), so the
 *      layout does not move.
 *
 *   6. PICTURES SAVED UNDER A NEW NAME, and 7. PAGES OF A REMOVED PLUGIN:
 *      see their own notes below.
 *
 *   5. NAMES FOR ICON BUTTONS. Heart, Instagram, search, wishlist and close
 *      icons had no text a screen reader could announce; product pictures in
 *      the homepage cards linked with an empty alt. They are named in the
 *      browser, from the card they sit in, so markup that a database snippet
 *      or the parent theme prints is covered without editing either.
 */
if (!defined('ABSPATH')) exit;

/* ---------------------------------------------------------------- 1. H1 */

/** True if the markup already carries an H1 outside scripts and comments. */
function af_mf_has_h1($html) {
    $clean = preg_replace('~<script\b[\s\S]*?</script>|<!--[\s\S]*?-->~i', '', (string) $html);
    return (bool) preg_match('~<h1\b~i', $clean);
}

/** Insert a visually-hidden H1 straight after <body>, if there is no H1 at all. */
function af_mf_hidden_h1($html, $title) {
    $title = trim((string) $title);
    if ($title === '' || af_mf_has_h1($html)) return $html;
    $h1 = '<h1 class="af-sr-only" style="position:absolute!important;width:1px!important;height:1px!important;'
        . 'padding:0!important;margin:-1px!important;overflow:hidden!important;clip:rect(0,0,0,0)!important;'
        . 'white-space:nowrap!important;border:0!important">' . esc_html($title) . '</h1>';
    $out = preg_replace('~(<body\b[^>]*>)~i', '$1' . $h1, $html, 1, $n);
    return $n ? $out : $html;
}

/** The title an H1 should carry on the page being viewed, or '' to leave it. */
function af_mf_page_title() {
    if (is_front_page() || is_search() || is_404()) return '';
    if (is_singular()) {
        $id = get_queried_object_id();
        return $id ? wp_strip_all_tags(get_the_title($id)) : '';
    }
    if (is_category() || is_tag() || is_tax()) {
        $t = get_queried_object();
        return ($t && isset($t->name)) ? wp_strip_all_tags($t->name) : '';
    }
    if (is_author()) {
        $u = get_queried_object();
        return ($u && isset($u->display_name)) ? 'Articles by ' . $u->display_name : '';
    }
    if (is_post_type_archive()) return wp_strip_all_tags(post_type_archive_title('', false));
    if (is_date()) return wp_strip_all_tags(get_the_archive_title());
    return '';
}

/**
 * Blog topics, tags, authors and dates: a visible title at the top of the list.
 * They showed a grid of posts with no word saying which topic it was.
 */
add_action('loop_start', function ($q) {
    try {
        if (is_admin() || !($q instanceof WP_Query) || !$q->is_main_query()) return;
        if (!($q->is_category() || $q->is_tag() || $q->is_author() || $q->is_date())) return;
        if (function_exists('af_heading_printed') && af_heading_printed()) return;
        $title = af_mf_page_title();
        if ($title === '') return;
        if (function_exists('af_heading_printed')) af_heading_printed(true);
        echo (function_exists('af_heading_css') ? af_heading_css() : '')
           . '<h1 class="af-archive-title">' . esc_html($title) . '</h1>';
    } catch (\Throwable $e) {
    }
});

/* ---------------------------------------------------------- 2. pictures */

/**
 * Card pictures drawn from a 1000px+ copy switch to WordPress's 768px copy of
 * the same picture, when that file exists. $exists is given the file's URL.
 */
function af_mf_card_images($html, $exists) {
    return preg_replace_callback('~<img\b[^>]*\bclass\s*=\s*["\'][^"\']*\b(?:hover-img|main-img)\b[^>]*>~i', function ($m) use ($exists) {
        $tag = $m[0];
        if (preg_match('~\bsrcset\s*=~i', $tag)) return $tag;
        if (!preg_match('~\bsrc\s*=\s*(["\'])([^"\']*/wp-content/uploads/[^"\']*?)-(\d{3,4})x(\d{3,4})\.(jpe?g|png|webp)\1~i', $tag, $s)) return $tag;
        list(, , $base, $w, $h, $ext) = $s;
        if ((int) $w < 1000) return $tag;
        $nh = (int) round(((int) $h) * 768 / (int) $w);
        $smaller = "{$base}-768x{$nh}.{$ext}";
        if (!call_user_func($exists, $smaller)) return $tag;
        return str_replace("{$base}-{$w}x{$h}.{$ext}", $smaller, $tag);
    }, $html);
}

/** The header logo, a full-size upload with no srcset, gets one. $srcset maps URL => srcset or ''. */
function af_mf_logo_srcset($html, $srcset) {
    return preg_replace_callback('~<img\b[^>]*\bclass\s*=\s*["\'][^"\']*\b(?:hfe-site-logo-img|custom-logo)\b[^>]*>~i', function ($m) use ($srcset) {
        $tag = $m[0];
        if (preg_match('~\bsrcset\s*=~i', $tag)) return $tag;
        if (!preg_match('~\bsrc\s*=\s*["\']([^"\']*/wp-content/uploads/[^"\']+)["\']~i', $tag, $s)) return $tag;
        $set = (string) call_user_func($srcset, $s[1]);
        if ($set === '') return $tag;
        return preg_replace('~<img\b~i', '<img srcset="' . esc_attr($set) . '" sizes="(max-width: 767px) 160px, 210px"', $tag, 1);
    }, $html);
}

/** Uploads URL => does that file exist on disk. Remembered for the request. */
function af_mf_upload_exists($url) {
    static $seen = array();
    if (isset($seen[$url])) return $seen[$url];
    $up = wp_get_upload_dir();
    $path = str_replace(array($up['baseurl'], set_url_scheme($up['baseurl'], 'http')), $up['basedir'], strtok($url, '?'));
    return $seen[$url] = ($path !== $url && is_file(rawurldecode($path)));
}

/** Uploads URL => srcset of its attachment, cached for a day. */
function af_mf_upload_srcset($url) {
    $key = 'af_mf_srcset_' . md5($url);
    $set = get_transient($key);
    if ($set !== false) return (string) $set;
    $set = '';
    $id = attachment_url_to_postid($url);
    if ($id) $set = (string) wp_get_attachment_image_srcset($id, 'full');
    set_transient($key, $set, DAY_IN_SECONDS);
    return $set;
}

/* ------------------------------------------------------ the page buffer */

function af_mf_filter_page($html) {
    if (!is_string($html) || $html === '' || stripos($html, '<body') === false) return $html;
    try {
        if (stripos($html, 'hover-img') !== false || stripos($html, 'main-img') !== false) {
            $html = af_mf_card_images($html, 'af_mf_upload_exists');
        }
        if (stripos($html, 'hfe-site-logo-img') !== false || stripos($html, 'custom-logo') !== false) {
            $html = af_mf_logo_srcset($html, 'af_mf_upload_srcset');
        }
        $html = af_mf_hidden_h1($html, af_mf_page_title());
    } catch (\Throwable $e) {
    }
    return $html;
}

/* Opened as early as the link guard (inc/demo-guard.php explains why: pages
 * that draw themselves at template_redirect 1 would otherwise finish first). */
add_action('template_redirect', function () {
    if (is_admin() || is_feed() || is_robots() || is_trackback()) return;
    if (defined('DOING_AJAX') && DOING_AJAX) return;
    if (defined('REST_REQUEST') && REST_REQUEST) return;
    if (function_exists('wp_is_json_request') && wp_is_json_request()) return;
    ob_start('af_mf_filter_page');
}, -99);

/* ------------------------------------------------------ 3. descriptions */

/** A description for the page being viewed when none was written. */
function af_mf_fallback_description() {
    $site = 'The Art Framer';
    if (is_page()) {
        $p = get_queried_object();
        if (!$p) return '';
        $by_slug = array(
            'try-on-wall'      => 'See any artwork from The Art Framer on your own wall before you buy: choose a piece, its size and frame, and preview it true to scale.',
            'frame-the-moment' => 'Turn your own photo into framed canvas wall art: upload it, pick the size, frame and colour, see a live preview and price, then order.',
            'all-artists'      => 'Meet the artists behind The Art Framer collection and browse canvas wall art direct from each of them, printed and framed to order.',
            'terms-of-use'     => 'The terms for using The Art Framer website: browsing, accounts, our artwork and content, reviews you post, and how to contact us.',
            'terms-of-service' => 'The terms of sale at The Art Framer: orders, made-to-order printing, payment, delivery across the USA, returns and refunds.',
        );
        if (isset($by_slug[$p->post_name])) return $by_slug[$p->post_name];
        $text = trim(preg_replace('~\s+~', ' ', wp_strip_all_tags(strip_shortcodes((string) $p->post_content))));
        if (str_word_count($text) >= 20) return wp_trim_words($text, 26, '…');
        return '';
    }
    if (is_category() || is_tag()) {
        $t = get_queried_object();
        if (!$t) return '';
        $d = trim(wp_strip_all_tags(term_description($t)));
        if ($d !== '') return wp_trim_words($d, 26, '…');
        return $t->name . ': guides and ideas from the ' . $site . ' blog on Vastu placement, choosing and framing canvas wall art, and caring for it.';
    }
    if (is_tax()) {
        // Product tags, product themes and any other product taxonomy (the
        // 2026-10-23 recheck found /product-themes/kids/ and nine siblings
        // with no description). Written term description first.
        $t = get_queried_object();
        if (!$t || empty($t->name)) return '';
        $d = trim(wp_strip_all_tags(term_description($t)));
        if ($d !== '') return wp_trim_words($d, 26, '…');
        return 'Shop ' . $t->name . ' canvas wall art at ' . $site . ': premium prints, framed to order in our Delaware studio and delivered across the USA.';
    }
    if (is_author()) {
        $u = get_queried_object();
        return $u ? 'Articles by ' . $u->display_name . ' on the ' . $site . ' blog: Vastu, framing and wall art guides for American homes.' : '';
    }
    return '';
}

add_filter('rank_math/frontend/description', function ($desc) {
    try {
        if (is_string($desc) && trim($desc) !== '') return $desc;
        $fb = af_mf_fallback_description();
        return $fb !== '' ? $fb : $desc;
    } catch (\Throwable $e) {
        return $desc;
    }
}, 30);

/* ----------------------------------------------- 4. tap targets, 5. names */

add_action('wp_head', function () {
    if (is_admin()) return;
    ?>
<style id="af-tap-targets">
/* Only controls under the 24px WCAG 2.2 minimum, measured on the live site.
   The header icons (24px) and footer links (24px) already meet it and are left as they are. */
/* Slider dots: the dot stays 6px, the area that takes the tap is 34px. */
.swiper-pagination-bullet{position:relative}
.swiper-pagination-bullet::after{content:"";position:absolute;inset:-14px}
/* Card buttons (19px tall) and the phone bottom bar (15px): a taller hit area,
   with the padding cancelled by an equal negative margin so nothing moves. */
a.digital-download-btn,.new-product-card .product-actions a,
.elementor-icon-box-title a,.elementor-icon-box-icon a.elementor-icon{
  padding-top:7px!important;padding-bottom:7px!important;margin-top:-7px!important;margin-bottom:-7px!important}
/* Small glyph buttons (13-16px): a 30px+ square around the same glyph. */
a.mobile-nav-close,a.site-search-popup-close,button.af-feat-sheet-close,a.instagram-gallery-item__icon{
  padding:8px!important;margin:-8px!important;box-sizing:content-box}
</style>
    <?php
}, 99);

add_action('wp_footer', function () {
    if (is_admin()) return;
    ?>
<script id="af-a11y-names">
(function () {
  function named(el) {
    if (el.getAttribute('aria-label') || el.getAttribute('aria-labelledby') || el.getAttribute('title')) return true;
    if ((el.textContent || '').trim()) return true;
    var imgs = el.querySelectorAll('img[alt]');
    for (var i = 0; i < imgs.length; i++) if (imgs[i].alt.trim()) return true;
    return false;
  }
  function cardTitle(el) {
    // The outer card first: the heart sits in an inner .custom-product-card that holds no title.
    var card = el.closest('.trending-card, .new-product-card, li.product') || el.closest('.product-card, .custom-product-card');
    if (!card) return '';
    var t = card.querySelector('.trending-content h3, .trending-content h2, .product-title, .product-name, .woocommerce-loop-product__title, h3, h2, h4');
    return t ? t.textContent.replace(/\s+/g, ' ').trim() : '';
  }
  var RULES = [
    ['a.wishlist-icon, a[href*="add-to-wishlist="], a[href*="add_to_wishlist="]', function (el) { var t = cardTitle(el); return t ? 'Add ' + t + ' to wishlist' : 'Add to wishlist'; }],
    ['a.instagram-gallery-item__icon', function () { return 'Open on Instagram'; }],
    ['a.button-search-popup', function () { return 'Search'; }],
    ['a.header-wishlist', function () { return 'Wishlist'; }],
    ['a.mobile-nav-close, a.site-search-popup-close, button.af-feat-sheet-close, .close-cart-side', function () { return 'Close'; }],
  ];
  function run(root) {
    root = root || document;
    RULES.forEach(function (r) {
      root.querySelectorAll(r[0]).forEach(function (el) { if (!named(el)) el.setAttribute('aria-label', r[1](el)); });
    });
    // Product pictures linked from homepage cards: the card's title is the alt.
    root.querySelectorAll('.trending-card a img, .new-product-card a img, .product-card a img').forEach(function (img) {
      if ((img.getAttribute('alt') || '').trim()) return;
      var t = cardTitle(img);
      if (t) img.setAttribute('alt', img.classList.contains('hover-img') ? t + ' on a wall' : t);
    });
    // The header search's category dropdown.
    root.querySelectorAll('select[name="product_cat"]').forEach(function (s) {
      if (!s.getAttribute('aria-label') && !(s.id && document.querySelector('label[for="' + s.id + '"]'))) s.setAttribute('aria-label', 'Search in category');
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { run(); }); else run();
  window.addEventListener('load', function () { run(); setTimeout(run, 1500); });
})();
</script>
    <?php
}, 99);

/* -------------------------------------- 6. pictures saved under a new name */

/**
 * A picture linked under a name that is no longer on disk, when its re-upload
 * sits next to it with WordPress's "-1" suffix.
 *
 * Full site test, 2026-10-23: the Elementor descriptions of two products
 * (#7781, #7815) link uploads/2026/02/wall-art-care-202604031610-69cf9a2f4de25.webp.
 * That file is gone; the same picture is on disk as ...-69cf9a2f4de25-1.webp,
 * with all its sizes. The link is mended to the file that exists, size suffix
 * and all. Only the content of the page being viewed is read, and only a URL
 * whose own file is missing is touched. $exists is given the URL.
 */
function af_mf_mend_renamed_uploads($html, $exists) {
    return preg_replace_callback(
        '~(https?://[^\s"\'<>]*?/wp-content/uploads/[^\s"\'<>?#]*?)((?:-\d{2,4}x\d{2,4})?)\.(jpe?g|png|webp|gif)(?=[\s"\'<>?#,]|$)~i',
        function ($m) use ($exists) {
            list($url, $base, $size, $ext) = $m;
            if (substr($base, -2) === '-1' || call_user_func($exists, $url)) return $url;
            $alt = $base . '-1' . $size . '.' . $ext;
            return call_user_func($exists, $alt) ? $alt : $url;
        },
        $html
    );
}

add_filter('the_content', function ($content) {
    try {
        if (!is_string($content) || strpos($content, '/wp-content/uploads/') === false) return $content;
        if (!is_singular() || is_admin()) return $content;
        return af_mf_mend_renamed_uploads($content, 'af_mf_upload_exists');
    } catch (\Throwable $e) {
        return $content;
    }
}, 20);

/* ------------------------------ 7. pages of a store directory that is gone */

/**
 * /all-artists/, /all-artists-2/, /store-listing/ and /my-orders/ were built
 * for a multi-vendor plugin (Dokan) that is no longer installed: their
 * [dokan-stores] and [dokan-my-orders] shortcodes are not registered, so they
 * show nothing or the shortcode as text (diag-missing-picture.php,
 * 2026-10-23). While the plugin is absent they answer 301 to the page that
 * does the job now. If it is installed again, the shortcode is registered and
 * the pages work as before, untouched.
 */
function af_mf_directory_redirects() {
    return array(
        'all-artists'   => '/artists/',
        'all-artists-2' => '/artists/',
        'store-listing' => '/artists/',
        'my-orders'     => '/my-account/orders/',
    );
}

add_action('template_redirect', function () {
    try {
        if (!is_page() || shortcode_exists('dokan-stores') || shortcode_exists('dokan-my-orders')) return;
        $p = get_queried_object();
        $map = af_mf_directory_redirects();
        if (!$p || empty($map[$p->post_name])) return;
        wp_safe_redirect(home_url($map[$p->post_name]), 301, 'The Art Framer');
        exit;
    } catch (\Throwable $e) {
    }
}, 0);

/* ------------------------------------------- 8. page numbers in titles */

/**
 * Page 2 onward of a listing carries the same title as page 1: the recheck
 * after the 2026-10-23 deploy counted the shop and every category that way
 * (/shop/page/2/, /product-category/.../page/3/ ...). The page number goes in
 * front of the site name, where a searcher sees it.
 */
function af_mf_paged_title($title, $n) {
    $title = (string) $title; $n = (int) $n;
    if ($n < 2 || $title === '' || preg_match('~\bPage\s+\d+\b~i', $title)) return $title;
    $tag = ' – Page ' . $n;
    foreach (array(' | ', ' - ', ' – ') as $sep) {
        $i = strrpos($title, $sep);
        if ($i !== false && $i > 0) return substr($title, 0, $i) . $tag . substr($title, $i);
    }
    return $title . $tag;
}

add_filter('rank_math/frontend/title', function ($title) {
    try {
        $n = max((int) get_query_var('paged'), (int) get_query_var('page'));
        return (is_archive() || is_home() || is_search() || (function_exists('is_shop') && is_shop())) ? af_mf_paged_title($title, $n) : $title;
    } catch (\Throwable $e) {
        return $title;
    }
}, 30);

/* -------------------------------------- 9. addresses people guess, and 404 */

/**
 * The addresses shoppers type or other sites link to, that this shop keeps
 * under another name. The ten-persona test of 2026-10-23 had a corporate
 * buyer try /corporate/ and /corporate-printing/, and a 65-year-old look for
 * the returns policy at /shipping-returns/ and WooCommerce's own default
 * /refund_returns/; every one was a "page not found". Only an address that
 * would otherwise be a 404 is redirected, so a real page of the same name,
 * if one is ever made, always wins.
 */
function af_mf_guess_redirects() {
    return array(
        'corporate'          => '/wholesale-corporate/',
        'corporate-orders'   => '/wholesale-corporate/',
        'wholesale'          => '/wholesale-corporate/',
        'bulk-orders'        => '/wholesale-corporate/',
        'corporate-printing' => '/product-category/corporate-printing/',
        'shipping-returns'   => '/shipping-delivery/',
        'shipping'           => '/shipping-delivery/',
        'delivery'           => '/shipping-delivery/',
        'shipping-policy'    => '/shipping-delivery/',
        'refund_returns'     => '/refund-policy/',
        'refund-returns'     => '/refund-policy/',
        'refunds'            => '/refund-policy/',
        'return-policy'      => '/returns-exchanges/',
        'returns-policy'     => '/returns-exchanges/',
        'contact-us'         => '/contact/',
        'faq'                => '/faqs/',
        'terms'              => '/terms-conditions/',
        'privacy'            => '/privacy-policy/',
    );
}

add_action('template_redirect', function () {
    try {
        if (!is_404()) return;
        $path = strtolower(trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/'));
        $map  = af_mf_guess_redirects();
        if ($path === '' || empty($map[$path])) return;
        wp_safe_redirect(home_url($map[$path]), 301, 'The Art Framer');
        exit;
    } catch (\Throwable $e) {
    }
}, 5);

/* ------------------------------------------------ 10. skip to the content */

/**
 * A "Skip to content" link, first thing on every page: keyboard users were
 * tabbing through the whole header on every page to reach anything (persona
 * 4, 2026-10-23). Hidden until it has focus, then shown at the top left. It
 * targets the page's main region, whichever the template uses.
 */
add_action('wp_body_open', function () {
    if (is_admin()) return;
    echo '<a class="af-skip" href="#af-main">Skip to content</a>'
       . '<style id="af-skip-style">.af-skip{position:absolute;left:8px;top:-60px;z-index:100000;padding:10px 16px;'
       . 'background:#1a1a1a;color:#fff;border-radius:8px;font-weight:700;text-decoration:none}'
       . '.af-skip:focus{top:8px;outline:3px solid #c9a84c;outline-offset:2px}</style>'
       . '<script>(function(){function t(){var m=document.querySelector("main, #main, #primary, #content, .site-main, .elementor-location-single, .woocommerce");'
       . 'if(m&&!document.getElementById("af-main")){if(!m.id)m.id="af-main";else{var a=document.querySelector(".af-skip");if(a)a.setAttribute("href","#"+m.id);}'
       . 'if(!m.hasAttribute("tabindex"))m.setAttribute("tabindex","-1");}}'
       . 'if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",t);else t();})();</script>';
}, 1);

/* The parent theme may not call wp_body_open(); then the link is added here. */
add_action('wp_footer', function () {
    if (is_admin()) return;
    echo '<script>(function(){if(document.querySelector(".af-skip"))return;var a=document.createElement("a");a.className="af-skip";a.href="#af-main";a.textContent="Skip to content";'
       . 'var st=document.createElement("style");st.textContent=".af-skip{position:absolute;left:8px;top:-60px;z-index:100000;padding:10px 16px;background:#1a1a1a;color:#fff;border-radius:8px;font-weight:700;text-decoration:none}.af-skip:focus{top:8px;outline:3px solid #c9a84c;outline-offset:2px}";'
       . 'document.head.appendChild(st);document.body.insertBefore(a,document.body.firstChild);'
       . 'var m=document.querySelector("main, #main, #primary, #content, .site-main, .elementor-location-single, .woocommerce");if(m){if(!m.id)m.id="af-main";else a.href="#"+m.id;if(!m.hasAttribute("tabindex"))m.setAttribute("tabindex","-1");}})();</script>';
}, 100);

/* ------------------------------------------------------ 11. a tab icon */

/**
 * The browser tab showed a blank icon: no Site Icon is set in WordPress, so
 * every first visit asked for /favicon.ico and got "not found" (purchase
 * path QA, 2026-10-23). Until one is set (Appearance > Customize > Site
 * Identity), the shop's monogram is used: gold AF on the dark of the header.
 * Setting a Site Icon replaces it, because WordPress then prints its own.
 */
add_action('wp_head', function () {
    if (is_admin() || (function_exists('has_site_icon') && has_site_icon())) return;
    if (!function_exists('af_static_url')) return;
    echo '<link rel="icon" href="' . esc_url(af_static_url('favicon.svg')) . '" type="image/svg+xml">' . "\n"
       . '<link rel="icon" href="' . esc_url(af_static_url('favicon-32.png')) . '" sizes="32x32" type="image/png">' . "\n"
       . '<link rel="icon" href="' . esc_url(af_static_url('icon-192.png')) . '" sizes="192x192" type="image/png">' . "\n"
       . '<link rel="apple-touch-icon" href="' . esc_url(af_static_url('apple-touch-icon.png')) . '">' . "\n";
}, 2);

/* ------------------------------------------- 12. a buy bar on phones */

/**
 * On a phone the price sat below the first screen of a product page and the
 * Add to Cart button a screen and a half further down, under the pictures and
 * the options (customer journey QA, 2026-10-23). A slim bar at the foot of the
 * screen keeps both in reach: the live price with the size picked, a link back
 * to the options, and Add to Cart, which presses the page's own button so the
 * options chosen and the usual added-to-cart message all still apply.
 *
 * Phones only, product pages only, and only while the real button is out of
 * sight. It sits on top of the shop's own bottom bar (Shop / Account /
 * Wishlist) rather than over it, and stays away while the cookie notice is up.
 */
add_action('wp_footer', function () {
    if (is_admin() || !function_exists('is_product') || !is_product()) return;
    ?>
<style id="af-buybar-style">
#af-buybar{display:none}
@media (max-width:767px){
  #af-buybar{position:fixed;left:0;right:0;bottom:0;z-index:9990;display:flex;align-items:center;gap:10px;
    padding:8px 12px calc(8px + env(safe-area-inset-bottom,0px));background:#fff;border-top:1px solid #e6e1d6;
    box-shadow:0 -4px 16px rgba(0,0,0,.08);transform:translateY(120%);transition:transform .2s ease;visibility:hidden}
  #af-buybar.af-on{transform:none;visibility:visible}
  #af-buybar .af-bb-info{flex:1;min-width:0;line-height:1.25}
  #af-buybar .af-bb-price{display:block;font-size:17px;font-weight:700;color:#1a1a1a}
  #af-buybar .af-bb-opt{display:block;font-size:13px;color:#555;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
    background:none;border:0;padding:4px 0;text-align:left;text-decoration:underline;cursor:pointer;min-height:24px;width:100%}
  #af-buybar .af-bb-add{flex:0 0 auto;min-height:44px;padding:0 18px;border:0;border-radius:8px;background:#1a1a1a;color:#fff;
    font-size:15px;font-weight:700;cursor:pointer}
  #af-buybar .af-bb-add:focus-visible,#af-buybar .af-bb-opt:focus-visible{outline:3px solid #c9a84c;outline-offset:2px}
}
</style>
<div id="af-buybar" role="region" aria-label="Buy this piece">
  <div class="af-bb-info"><span class="af-bb-price" aria-live="polite"></span><button type="button" class="af-bb-opt"></button></div>
  <button type="button" class="af-bb-add">Add to Cart</button>
</div>
<script>
(function(){
  var bar = document.getElementById('af-buybar');
  var real = document.querySelector('form.cart .single_add_to_cart_button');
  if (!bar || !real || !window.matchMedia || !window.IntersectionObserver) { if (bar) bar.remove(); return; }
  var phone = window.matchMedia('(max-width: 767px)');
  var priceEl = bar.querySelector('.af-bb-price'), optEl = bar.querySelector('.af-bb-opt');
  var opts = document.getElementById('af-opts') || real.closest('form.cart');
  var realSeen = true, optsSeen = false;

  function priceText(){
    var live = document.getElementById('af-live-price');
    var p = live || document.querySelector('.summary .price ins .amount, .summary .price .amount');
    return p ? (p.textContent || '').trim() : '';
  }
  function optText(){
    var s = document.getElementById('af-size-select');
    return s && s.value ? 'Size ' + s.value + ' · change' : 'Choose options';
  }
  function refresh(){ priceEl.textContent = priceText(); optEl.textContent = optText(); }

  // The shop's own bars fixed to the foot of the screen: sit on top of them.
  function lift(){
    var h = 0, vh = window.innerHeight;
    document.querySelectorAll('body *').forEach(function(el){
      if (el === bar || bar.contains(el) || el.offsetParent === null && getComputedStyle(el).position !== 'fixed') return;
      var cs = getComputedStyle(el);
      if (cs.position !== 'fixed' || cs.display === 'none' || cs.visibility === 'hidden') return;
      var r = el.getBoundingClientRect();
      if (r.height > 0 && r.height < 120 && r.width > window.innerWidth * 0.6 && Math.abs(r.bottom - vh) < 3) h = Math.max(h, r.height);
    });
    bar.style.bottom = h ? h + 'px' : '0';
  }
  function show(){
    var on = phone.matches && !realSeen && !optsSeen && !document.documentElement.classList.contains('af-ck-open');
    if (on && !bar.classList.contains('af-on')) { refresh(); lift(); }
    bar.classList.toggle('af-on', on);
    bar.setAttribute('aria-hidden', on ? 'false' : 'true');
  }

  new IntersectionObserver(function(es){ realSeen = es[0].isIntersecting; show(); }).observe(real);
  if (opts && opts !== real.closest('form.cart')) new IntersectionObserver(function(es){ optsSeen = es[0].isIntersecting; show(); }, { threshold: 0.35 }).observe(opts);
  new MutationObserver(show).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
  var live = document.getElementById('af-live-price');
  if (live) new MutationObserver(refresh).observe(live, { childList: true, characterData: true, subtree: true });
  document.addEventListener('change', function(e){ if (e.target && e.target.closest && e.target.closest('form.cart, #af-opts')) setTimeout(refresh, 50); });
  if (phone.addEventListener) phone.addEventListener('change', show);
  window.addEventListener('resize', function(){ if (bar.classList.contains('af-on')) lift(); });

  optEl.addEventListener('click', function(){
    var t = document.getElementById('af-size-select') || opts || real;
    t.scrollIntoView({ behavior: 'smooth', block: 'center' });
    setTimeout(function(){ try { t.focus({ preventScroll: true }); } catch (e) {} }, 400);
  });
  bar.querySelector('.af-bb-add').addEventListener('click', function(){
    // Bring the page's own button into view, then press it: whatever it
    // checks (a size, a quantity) and says (added, or what is missing) the
    // customer sees next to the options.
    real.scrollIntoView({ behavior: 'smooth', block: 'center' });
    setTimeout(function(){ real.click(); }, 350);
  });
  refresh();
})();
</script>
    <?php
}, 60);
