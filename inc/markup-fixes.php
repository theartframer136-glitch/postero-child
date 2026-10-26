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

/**
 * The home page's product rows come from a page-builder widget that prints a
 * bare <img class="main-img" src="...-512x1024.jpg"> with no srcset, so every
 * screen downloads that one file whatever the card's real size (measured
 * 24 Oct 2026, tools/diag-picture-sharpness.js: 350px wide on a 390px phone). Each one gets
 * the srcset of the same picture at the same shape and a sizes that is never
 * smaller than the card, so the browser picks a file at least as wide as the
 * card times the screen's density. The list stops at the file the card uses
 * today: a screen that needs less takes a lighter copy, and none is ever sent
 * a bigger file than now (the aim is speed; and with nothing smaller than the
 * space on offer the browser keeps today's file, so nothing gets blurrier).
 *
 * Only main pictures that are clearly tall (at least 1.3 times as tall as
 * wide). The card's picture box is itself taller than wide (about 313x380 on
 * a laptop) and pictures fill it cropped, so for anything less tall it is the
 * box's HEIGHT that decides how many pixels are needed, which sizes cannot
 * express. Measured 24 Oct 2026 after PR 430: the 4:3 hover scenes took the
 * 400px copy on a 1x laptop and drew at 0.79 picture pixels per screen pixel
 * instead of 1.52 — softer. Hover pictures, and main pictures that are square
 * or wide, keep the one file they always had.
 * $resolve maps a list of sized upload URLs => srcset or ''.
 */
function af_mf_card_srcset($html, $resolve) {
    $re = '~<img\b[^>]*\bclass\s*=\s*["\'][^"\']*\bmain-img\b[^>]*>~i';
    $src = function ($tag) {
        if (preg_match('~\bsrcset\s*=~i', $tag)) return '';
        if (!preg_match('~\bsrc\s*=\s*["\']([^"\']*/wp-content/uploads/[^"\']*?-(\d{2,4})x(\d{2,4})\.(?:jpe?g|png|webp))["\']~i', $tag, $s)) return '';
        return (int) $s[3] >= 1.3 * (int) $s[2] ? $s[1] : '';
    };
    if (!preg_match_all($re, $html, $all)) return $html;
    $urls = array();
    foreach ($all[0] as $tag) { $u = $src($tag); if ($u !== '') $urls[$u] = true; }
    if (!$urls) return $html;
    $map = (array) call_user_func($resolve, array_keys($urls));
    $sizes = (string) apply_filters('af_home_card_sizes', '(max-width: 767px) 92vw, (max-width: 1199px) 45vw, 360px');
    return preg_replace_callback($re, function ($m) use ($src, $map, $sizes) {
        $tag = $m[0];
        $u = $src($tag);
        if ($u === '' || empty($map[$u]) || !preg_match('~-(\d{2,4})x\d{2,4}\.\w+$~', $u, $w)) return $tag;
        $keep = array();
        foreach (explode(',', $map[$u]) as $c) {
            if (preg_match('~^\s*(\S+)\s+(\d+)w\s*$~', $c, $p) && (int) $p[2] <= (int) $w[1]) $keep[(int) $p[2]] = $p[1] . ' ' . $p[2] . 'w';
        }
        if (count($keep) < 2) return $tag;
        ksort($keep);
        $add = ' srcset="' . esc_attr(implode(', ', $keep)) . '"';
        if (!preg_match('~\bsizes\s*=~i', $tag)) $add .= ' sizes="' . esc_attr($sizes) . '"';
        return preg_replace('~^<img\b~i', '<img' . $add, $tag, 1);
    }, $html);
}

/**
 * Sized upload URLs (name-WxH.ext) => srcset of the same picture in every copy
 * of the same shape ('' when unknown or there is no other copy). The home page
 * has close to 300 of these, so the attachments are found in one query and the
 * answers kept together for a day, rather than a lookup and a stored row each.
 */
function af_mf_sized_upload_srcsets($urls) {
    $key = 'af_mf_card_srcsets';
    $cache = get_transient($key);
    if (!is_array($cache)) $cache = array();
    $out = array(); $todo = array();
    foreach ($urls as $u) {
        $h = md5($u);
        if (isset($cache[$h])) $out[$u] = $cache[$h]; else $todo[$u] = true;
    }
    if (!$todo) return $out;

    $up = wp_get_upload_dir();
    $base = trailingslashit(set_url_scheme($up['baseurl'], 'https'));
    $want = array(); // _wp_attached_file => list of sized URLs
    foreach (array_keys($todo) as $u) {
        $out[$u] = '';
        if (!preg_match('~^(.*)-(\d{2,4})x(\d{2,4})\.(jpe?g|png|webp)$~i', set_url_scheme(strtok($u, '?'), 'https'), $m)) continue;
        if (strpos($m[1], $base) !== 0) continue;
        $rel = substr($m[1], strlen($base));
        foreach (array("{$rel}.{$m[4]}", "{$rel}-scaled.{$m[4]}") as $f) $want[$f][] = array($u, (int) $m[2], (int) $m[3]);
    }
    if ($want) {
        global $wpdb;
        $in = implode(',', array_fill(0, count($want), '%s'));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value IN ($in)",
            array_keys($want)
        ));
        $ids = array();
        foreach ((array) $rows as $r) $ids[(int) $r->post_id] = $r->meta_value;
        if ($ids) update_meta_cache('post', array_keys($ids));
        foreach ($ids as $id => $file) {
            $meta = wp_get_attachment_metadata($id);
            if (!is_array($meta)) continue;
            foreach ($want[$file] as $w) {
                $calc = wp_calculate_image_srcset(array($w[1], $w[2]), $w[0], $meta, $id);
                if (is_string($calc) && $calc !== '') $out[$w[0]] = $calc;
            }
        }
    }
    foreach (array_keys($todo) as $u) $cache[md5($u)] = $out[$u];
    set_transient($key, $cache, DAY_IN_SECONDS);
    return $out;
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
            $html = af_mf_card_srcset($html, 'af_mf_sized_upload_srcsets');
        }
        if (stripos($html, 'hfe-site-logo-img') !== false || stripos($html, 'custom-logo') !== false) {
            $html = af_mf_logo_srcset($html, 'af_mf_upload_srcset');
        }
        $html = af_mf_hidden_h1($html, af_mf_page_title());
        if (is_page(array('login', 'sign-up'))) {
            $html = af_mf_mend_login_buttons($html);
        }
        $html = af_mf_pretty_page_links($html);
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

/* -------------------------------------- 13. the sort box by keyboard */

/**
 * The sort box on the shop and collections is the parent theme's own
 * dropdown, drawn in plain divs in front of the real (hidden) select. Mouse
 * and touch work; a keyboard cannot reach it at all — no tab stop, no role
 * (sort probe, 2026-10-23). This gives it one: Tab to it, Enter, Space or
 * Down to open, the arrows to move, Enter to choose, Escape to close. The
 * theme's own click handlers still do the choosing, so nothing about what
 * a choice does changes.
 */
add_action('wp_footer', function () {
    if (is_admin() || !function_exists('is_woocommerce') || !(is_shop() || is_product_taxonomy() || is_search())) return;
    ?>
<script>
(function(){
  function wire(form){
    var head = form.querySelector('.select-selected'), list = form.querySelector('.select-items');
    if (!head || !list || head.dataset.afKb) return;
    head.dataset.afKb = '1';
    head.setAttribute('tabindex', '0');
    head.setAttribute('role', 'button');
    head.setAttribute('aria-haspopup', 'listbox');
    list.setAttribute('role', 'listbox');
    list.setAttribute('aria-label', 'Sort products');
    var opts = function(){ return [].slice.call(list.children); };
    opts().forEach(function(o){ o.setAttribute('role', 'option'); o.setAttribute('tabindex', '-1'); });
    var isOpen = function(){ return !list.classList.contains('select-hide'); };
    var label = function(){
      head.setAttribute('aria-label', 'Sort products: ' + head.textContent.trim());
      head.setAttribute('aria-expanded', isOpen() ? 'true' : 'false');
    };
    label();
    new MutationObserver(label).observe(list, { attributes: true, attributeFilter: ['class'] });
    new MutationObserver(label).observe(head, { childList: true, characterData: true, subtree: true });
    function open(){
      if (!isOpen()) head.click();
      var o = opts(), cur = o.filter(function(x){ return x.classList.contains('same-as-selected'); })[0] || o[0];
      if (cur) setTimeout(function(){ cur.focus(); }, 0);
    }
    function close(){ if (isOpen()) head.click(); head.focus(); }
    head.addEventListener('keydown', function(e){
      if (e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowDown') { e.preventDefault(); open(); }
    });
    list.addEventListener('keydown', function(e){
      var o = opts(), i = o.indexOf(document.activeElement);
      if (e.key === 'ArrowDown') { e.preventDefault(); (o[i + 1] || o[0]).focus(); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); (o[i - 1] || o[o.length - 1]).focus(); }
      else if (e.key === 'Home') { e.preventDefault(); o[0].focus(); }
      else if (e.key === 'End') { e.preventDefault(); o[o.length - 1].focus(); }
      else if ((e.key === 'Enter' || e.key === ' ') && i > -1) { e.preventDefault(); o[i].click(); head.focus(); }
      else if (e.key === 'Escape') { e.preventDefault(); close(); }
      else if (e.key === 'Tab') { close(); }
    });
  }
  function run(){ document.querySelectorAll('.woocommerce-ordering').forEach(wire); }
  if (document.readyState === 'complete') run(); else window.addEventListener('load', run);
  setTimeout(run, 2500);   // the theme may draw its dropdown after load
})();
</script>
    <?php
}, 60);

/* ------------------------------ 14. capital letters in an address */

/**
 * /Shop/ answered 200 with the shop page's title in the full site test of
 * 2026-10-26, next to /shop/: WordPress matches a page name without regard to
 * case, so every spelling is a second copy of the same page with the same
 * title. Slugs here are all lower case, so an address with a capital letter
 * in its path is sent, once and for good, to the lower-case spelling. Only
 * the path changes: the query string (order keys, currency, layout) keeps
 * its case, and a path that is a 404 either way is left to the 404 page.
 */
function af_mf_lowercase_path($uri) {
    $uri  = (string) $uri;
    $path = (string) parse_url($uri, PHP_URL_PATH);
    if ($path === '' || !preg_match('/[A-Z]/', $path)) return '';
    $lower = strtolower($path);
    if ($lower === $path) return '';
    $q = (string) parse_url($uri, PHP_URL_QUERY);
    return $lower . ($q !== '' ? '?' . $q : '');
}

add_action('template_redirect', function () {
    try {
        if (is_admin() || is_404() || is_preview() || is_customize_preview()) return;
        if ((defined('DOING_AJAX') && DOING_AJAX) || (defined('REST_REQUEST') && REST_REQUEST)) return;
        if (!empty($_POST)) return;
        $to = af_mf_lowercase_path($_SERVER['REQUEST_URI'] ?? '');
        if ($to === '') return;
        wp_safe_redirect(home_url($to), 301, 'The Art Framer');
        exit;
    } catch (\Throwable $e) {
    }
}, 1);

/* -------------------------- 15. buttons with nowhere to go on login pages */

/**
 * The Login and Sign-up pages are drawn by Elementor, and four of their
 * buttons still carry href="" (full site test, 2026-10-26: "Sign In" on the
 * sign-up page). The database fix of 2026-10 wrote post_content, which
 * Elementor does not render from, so the empty buttons stayed. A button with
 * no address does nothing when tapped. Here the finished page is mended as it
 * leaves: an empty-href anchor labelled Sign In points at the login page,
 * Register / Sign Up / Create Account at the sign-up page. Only those labels,
 * only on those two pages, and only when the href is empty.
 */
function af_mf_login_button_targets() {
    return array(
        'sign in'        => '/login/',
        'log in'         => '/login/',
        'login'          => '/login/',
        'register now'   => '/sign-up/',
        'register'       => '/sign-up/',
        'sign up'        => '/sign-up/',
        'create account' => '/sign-up/',
    );
}

function af_mf_mend_login_buttons($html) {
    if (!is_string($html) || stripos($html, 'href=""') === false && stripos($html, "href=''") === false) return $html;
    $map = af_mf_login_button_targets();
    return preg_replace_callback('#<a\b([^>]*?)\bhref=(["\'])\2([^>]*)>([\s\S]{0,400}?)</a>#i', function ($m) use ($map) {
        $label = strtolower(trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($m[4]))));
        if ($label === '' || empty($map[$label])) return $m[0];
        return '<a' . $m[1] . 'href="' . esc_url(home_url($map[$label])) . '"' . $m[3] . '>' . $m[4] . '</a>';
    }, $html);
}

/* ------------------------------ 16. ?page_id= links in the header */

/**
 * Every page links Sign In and Register as /?page_id=6030 and /?page_id=6032
 * (the header menu was built with the pages' IDs). Each click is answered
 * with a 301 to /login/ or /sign-up/ first: a round trip a shopper waits
 * through, and for Google two addresses for one page. The finished page
 * gets the permalink in place of each ?page_id= address that belongs to a
 * published page; anything else (a draft, an unknown ID) is left as it is.
 */
function af_mf_pretty_page_links($html) {
    if (!is_string($html) || strpos($html, '?page_id=') === false) return $html;
    $home = preg_quote(home_url('/'), '#');
    return preg_replace_callback('#href=(["\'])' . $home . '\?page_id=(\d+)\1#', function ($m) {
        static $seen = array();
        $id = (int) $m[2];
        if (!isset($seen[$id])) {
            $seen[$id] = '';
            if (get_post_status($id) === 'publish' && get_post_type($id) === 'page') {
                $link = get_permalink($id);
                if (is_string($link) && $link !== '' && strpos($link, '?page_id=') === false) $seen[$id] = $link;
            }
        }
        return $seen[$id] === '' ? $m[0] : 'href=' . $m[1] . esc_url($seen[$id]) . $m[1];
    }, $html);
}

/* --------------------------- 17. the Sign Up page with no sign-up form */

/**
 * The header's "Sign Up" opens /sign-up/, an Elementor login-register
 * widget. Since open registration was switched off in the 24 Oct security
 * cleanup (users_can_register = 0) the widget draws only its login half:
 * the browser pass of 2026-10-26 found no email, name or password field to
 * register with, so a shopper who wants an account before buying gets a
 * login form instead. WooCommerce's own registration on /my-account/ is
 * still on (functions.php keeps woocommerce_enable_myaccount_registration
 * at yes) and does not depend on users_can_register. So while WordPress
 * registration is off, a signed-out visitor who lands on /sign-up/ is sent
 * on to /my-account/, where the register form is. A temporary redirect
 * (302): the moment registration is switched back on, the page is served
 * again and nothing has to be undone.
 */
add_action('template_redirect', function () {
    try {
        if (!is_page('sign-up') || is_user_logged_in()) return;
        if (get_option('users_can_register')) return;
        if (!function_exists('wc_get_page_permalink') || get_option('woocommerce_enable_myaccount_registration') !== 'yes') return;
        $to = wc_get_page_permalink('myaccount');
        if (!is_string($to) || $to === '') return;
        wp_safe_redirect($to, 302, 'The Art Framer');
        exit;
    } catch (\Throwable $e) {
    }
}, 2);
