<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * FULL LIVE-SITE AUDIT — function + style, measured on the pages a visitor
 * actually receives. For every key page: HTTP status, fatal/error markers,
 * unrendered shortcodes, broken-markup tells, and the functional marker that
 * proves the page's job actually happened. Read-only.
 * Run: wp eval-file tools/audit-live-site.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
$fail = 0; $warn = 0;

function af_au_get($url) {
    $a = array('timeout'=>60,'sslverify'=>false,'headers'=>array('User-Agent'=>'Mozilla/5.0 (X11; Linux x86_64) AF-Audit'));
    for ($i=0;$i<3;$i++) {
        $r = wp_remote_get($url,$a);
        if (!is_wp_error($r) && !in_array((int) wp_remote_retrieve_response_code($r), array(508,503,429), true)) return $r;
        sleep(3);
    }
    return $r;
}

/** Universal problems every page must be free of. */
function af_au_common($body, &$issues) {
    if (stripos($body, 'Fatal error') !== false)                    $issues[] = 'PHP FATAL in output';
    if (stripos($body, 'critical error on this website') !== false) $issues[] = 'WP critical error page';
    if (preg_match('/\bWarning: .{0,80} in \/home\//', $body))      $issues[] = 'PHP warning leaked into page';
    if (preg_match('/\[af_[a-z_]+[^\]]*\]/', $body, $m))            $issues[] = 'unrendered shortcode ' . $m[0];
    if (strpos($body, 'af-consent') === false)                      $issues[] = 'cookie banner missing';
    if (strpos($body, 'af-chat-bub') === false)                     $issues[] = 'chat launcher missing';
    if (substr_count($body, '</html>') === 0)                       $issues[] = 'page truncated (no </html>)';
}

$product = get_posts(array('post_type'=>'product','post_status'=>'publish','fields'=>'ids','posts_per_page'=>1,
    'meta_query'=>array(array('key'=>'_thumbnail_id','compare'=>'EXISTS'))));
$prod_url = $product ? get_permalink($product[0]) : '';
$terms = get_terms(array('taxonomy'=>'product_cat','hide_empty'=>true,'number'=>1,'orderby'=>'count','order'=>'DESC'));
$cat_url = ($terms && !is_wp_error($terms)) ? get_term_link($terms[0]) : '';
$parent = get_term_by('slug','direct-from-artists','product_cat');
$artist = $parent ? get_terms(array('taxonomy'=>'product_cat','parent'=>$parent->term_id,'hide_empty'=>false,'number'=>1)) : null;
$artist_url = ($artist && !is_wp_error($artist) && $artist) ? home_url('/artists/' . $artist[0]->slug . '/') : '';

$pages = array(
    'Homepage'            => array(home_url('/'), array('Shop by Collection|shop-by|product-card' => 'collection section', 'af-lt-|products' => 'products render')),
    'Shop'                => array(home_url('/shop/'), array('ul.products|<ul class="products' => 'product grid', 'data-af-orient' => 'orientation dropdown', 'af-layout-toggle' => 'masonry toggle')),
    'Category page'       => array($cat_url, array('products' => 'product grid', 'data-af-orient' => 'orientation dropdown', 'af-layout-toggle' => 'masonry toggle', 'af-sold' => 'sales badges CSS')),
    'Product page'        => array($prod_url, array('af-opts|af_size' => 'size/frame/colour options', 'add_to_cart|single_add_to_cart' => 'add to cart', 'af-ship-note' => 'shipping note')),
    'Cart'                => array(wc_get_cart_url(), array('cart' => 'cart renders')),
    'Checkout'            => array(wc_get_checkout_url(), array('checkout' => 'checkout renders')),
    'My Account'          => array(wc_get_page_permalink('myaccount'), array('woocommerce' => 'account renders')),
    'Saved Previews'      => array(wc_get_endpoint_url('saved-previews','',wc_get_page_permalink('myaccount')), array('woocommerce|previews' => 'endpoint resolves')),
    'Try On Wall'         => array(home_url('/try-on-wall/'), array('tow-layouts' => 'layout chips', 'tow-cambtn' => 'camera button', 'tow-saveacct' => 'save to account')),
    'Frame The Moment'    => array(home_url('/frame-the-moment/'), array('ftm-layouts' => 'layout chips', 'ftm-cambtn' => 'camera button', 'ftm-saveacct' => 'save to account')),
    'Blog hub'            => array(home_url('/blog/'), array('af-hub-card|af-hub-hero' => 'article cards', 'af-hub-search' => 'search', 'min read' => 'reading times')),
    'Reels'               => array(home_url('/reels/'), array('af-reel|Reels are on their way' => 'reels or empty state')),
    'Artists'             => array(home_url('/artists/'), array('taf-artist|af-artist' => 'artist cards')),
    'Artist profile'      => array($artist_url, array('af-artist|taf-' => 'profile renders')),
    'Terms of Use'        => array(home_url('/terms-of-use/'), array('Terms of Use' => 'content')),
    'Terms of Service'    => array(home_url('/terms-of-service/'), array('Terms of Service' => 'content')),
    'Privacy Policy'      => array(home_url('/privacy-policy/'), array('privacy|Privacy' => 'content')),
    'Contact'             => array(home_url('/contact/'), array('af_name|taf-form|contact' => 'contact form')),
    'About'               => array(home_url('/about/'), array('af-about-hero' => 'designed about page', 'af-about-cats' => 'category cards', 'af-about-contact' => 'contact band')),
    'Gift Cards'          => array(home_url('/gift-cards/'), array('gift' => 'content')),
    'FAQs'                => array(home_url('/faqs/'), array('faq|FAQ' => 'content')),
);

echo "=== FULL LIVE-SITE AUDIT ===\n";
foreach ($pages as $label => $def) {
    list($url, $markers) = $def;
    if (!$url || is_wp_error($url)) { printf("%-20s SKIP (no url)\n", $label); continue; }
    $r = af_au_get(add_query_arg('nocache', time(), $url));
    if (is_wp_error($r)) { printf("%-20s FETCH FAILED %s\n", $label, $r->get_error_message()); $fail++; continue; }
    $code = (int) wp_remote_retrieve_response_code($r);
    $body = wp_remote_retrieve_body($r);
    $issues = array();
    if ($code !== 200) $issues[] = "HTTP {$code}";
    af_au_common($body, $issues);
    foreach ($markers as $needles => $what) {
        $hit = false;
        foreach (explode('|', $needles) as $n) { if (stripos($body, $n) !== false) { $hit = true; break; } }
        if (!$hit) $issues[] = "missing: {$what}";
    }
    if ($issues) { $fail += count($issues); printf("%-20s %s\n", $label, 'ISSUES <<< ' . implode(' | ', $issues)); }
    else printf("%-20s OK  (%s KB)\n", $label, number_format(strlen($body)/1024, 0));
}

// the 404 must be the designed one and actually be a 404
$r404 = af_au_get(home_url('/audit-nonexistent-' . time() . '/'));
if (!is_wp_error($r404)) {
    $c = (int) wp_remote_retrieve_response_code($r404);
    $b = wp_remote_retrieve_body($r404);
    $ok = ($c === 404 && strpos($b, 'af-404') !== false);
    printf("%-20s %s\n", '404 page', $ok ? 'OK' : ('ISSUES <<< HTTP ' . $c . (strpos($b, 'af-404') === false ? ' | designed page missing' : '')));
    if (!$ok) $fail++;
}

echo "\n=== RESULT: " . ($fail ? "{$fail} ISSUE(S)" : "ALL PAGES CLEAN") . " ===\n";
