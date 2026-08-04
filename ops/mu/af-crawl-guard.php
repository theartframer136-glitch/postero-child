<?php
/**
 * Plugin Name: AF Crawl Guard (early bot short-circuit)
 * Description: Answers bot junk (hack-era spam URLs, headerless search, missing statics, bulk crawlers) before WordPress loads. Copied to wp-content/af-crawl-guard.php and hooked from wp-config.php (or a fallback loader plugin) by ops/harden-crawl-budget.php — this host ignores custom .htaccess rewrite rules on the public path AND its mu-plugins dir is root-owned (platform-managed WP, run #532).
 *
 * Loaded from wp-config.php: BEFORE WordPress, so a blocked request costs
 * ~10ms instead of the ~3s full page build. Must therefore use NO WordPress
 * functions/constants (ABSPATH may not exist yet). LiteSpeed cache HITs never
 * reach PHP, so real cached traffic is untouched.
 *
 * Every response sets an explicit Cache-Control: hCDN caches error responses
 * that lack one (verified live 2026-08-03: a bare 404 came back with Age: 23),
 * which would let a bot-triggered 403 be served to real customers. The /items
 * 410 is the one response we WANT edge-cached — repeat bot hits then cost the
 * origin nothing at all.
 *
 * Source of truth lives in the theme repo at ops/mu/af-crawl-guard.php —
 * edit THERE, never in mu-plugins directly (the installer overwrites).
 */

if ( PHP_SAPI === 'cli' || ( defined( 'WP_CLI' ) && WP_CLI ) ) { return; }
if ( defined( 'AF_CRAWL_GUARD_RAN' ) ) { return; } // wp-config hook + fallback plugin may both exist
define( 'AF_CRAWL_GUARD_RAN', true );

$af_path = parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH );
if ( ! is_string( $af_path ) || $af_path === '' ) { return; }
$af_qs = $_SERVER['QUERY_STRING'] ?? '';
$af_ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

// 1. Hack-era spam URLs: 410 Gone — Google's signal to drop them for good.
//    Deliberately edge-cacheable: the CDN absorbs the repeat bot traffic.
if ( preg_match( '#^/items(/|$)#i', $af_path ) ) {
    http_response_code( 410 );
    header( 'Cache-Control: public, max-age=86400' );
    header( 'Content-Type: text/plain; charset=utf-8' );
    header( 'X-AF-Guard: items-410' );
    exit( 'Gone' );
}

// 2. XML-RPC: pure attack surface on this site (already disabled in PHP later;
//    refusing it here is cheaper). Remove if Jetpack/mobile app ever needed.
if ( strcasecmp( $af_path, '/xmlrpc.php' ) === 0 ) {
    http_response_code( 403 );
    header( 'Cache-Control: no-store, max-age=0' );
    header( 'X-AF-Guard: xmlrpc-403' );
    exit( 'Forbidden' );
}

// 3. Bulk SEO/AI crawlers that bring no customers. Googlebot, Bingbot and
//    social link-preview fetchers are deliberately NOT listed. no-store is
//    load-bearing: a cached 403 keyed by URL would hit real customers too.
if ( $af_ua !== '' && preg_match(
    '#ahrefsbot|semrushbot|mj12bot|dotbot|blexbot|dataforseobot|petalbot|bytespider|amazonbot|gptbot|ccbot|claudebot|meta-externalagent|imagesiftbot|serpstatbot|barkrowler|seekportbot|zoominfobot|megaindex|timpibot#i',
    $af_ua
) ) {
    http_response_code( 403 );
    header( 'Cache-Control: no-store, max-age=0' );
    header( 'X-AF-Guard: bot-ua-403' );
    exit( 'Forbidden' );
}

// BEGIN AF-SEARCH-GUARD (the installer removes this section if Sec-Fetch
// headers are found not to survive the CDN, so customer search cannot break)
// Rules 4/4b/4c share one signal: real browsers send Sec-Fetch-* headers;
// scrapers with fake Chrome UAs do not. Each redirect lands somewhere cached
// (~free), so the worst case for a rare old browser is a sensible page, never
// a dead "Forbidden". All skip wp-admin, the REST API and logged-in users.
if ( $af_qs !== ''
    && empty( $_SERVER['HTTP_SEC_FETCH_MODE'] )
    && strpos( $af_path, '/wp-admin' ) !== 0
    && strpos( $af_path, '/wp-json' ) !== 0
) {
    $af_logged_in = false;
    foreach ( array_keys( $_COOKIE ) as $af_ck ) {
        if ( strpos( $af_ck, 'wordpress_logged_in_' ) === 0 ) { $af_logged_in = true; break; }
    }
    if ( ! $af_logged_in ) {
        // 4. On-site search costs ~3s of PHP and cannot be cached.
        if ( preg_match( '#(^|&)s=#', $af_qs ) ) {
            header( 'Cache-Control: no-store, max-age=0' );
            header( 'X-AF-Guard: search-302' );
            header( 'Location: /', true, 302 );
            exit;
        }
        // 4b. Faceted-filter URLs are a combinatorial crawl trap: sizes x
        //     colors x frames x orientation x price x sort explode a
        //     394-product store into millions of unique URLs, every one a
        //     cache MISS and a full render. Headerless clients get the bare
        //     (cached) archive path instead; real shoppers filter untouched.
        // lang= is Transposh's per-language URL variant (this store's own SEO
        // code strips hreflang as a single-language US shop); per_page= and
        // layout= are grid-display toggles. All were live in the 2026-08-04
        // profiler capture, multiplying the crawlable URL space.
        if ( preg_match( '#(^|&)(filter_[a-z0-9_]+|orientation|min_price|max_price|orderby|query_type_[a-z0-9_]+|currency|lang|per_page|layout)=#i', $af_qs ) ) {
            header( 'Cache-Control: no-store, max-age=0' );
            header( 'X-AF-Guard: filter-302' );
            header( 'Location: ' . $af_path, true, 302 );
            exit;
        }
        // 4c. add-to-cart links (the Buy Now anchors) followed by bots build
        //     a cart, mint a WooCommerce session row and render checkout
        //     uncacheably — thousands of junk sessions. Bots land on the
        //     cached homepage; a real click carries Sec-Fetch and sails past.
        if ( preg_match( '#(^|&)add-to-cart=#i', $af_qs ) ) {
            header( 'Cache-Control: no-store, max-age=0' );
            header( 'X-AF-Guard: addtocart-302' );
            header( 'Location: /', true, 302 );
            exit;
        }
    }
}
// END AF-SEARCH-GUARD

// 5. Static-looking URLs under core asset paths only ever reach PHP when the
//    file does not exist (the web server serves real files itself) — answer
//    404 without building the 3-second themed page. no-store so a mid-deploy
//    miss can never be cached past the moment the file lands.
if ( preg_match(
    '#^/(wp-content|wp-includes)/.+\.(jpe?g|png|gif|webp|avif|svg|ico|css|js|map|woff2?|ttf|otf|eot|mp4|webm|pdf)$#i',
    $af_path
) ) {
    // Pre-WP context: ABSPATH may not exist. The deployed copy of this file
    // lives at <docroot>/wp-content/af-crawl-guard.php, so dirname(__DIR__)
    // is the docroot. (Existing files are served by the web server and never
    // reach PHP, so a wrong-negative here cannot 404 a real asset.)
    $af_docroot = defined( 'ABSPATH' ) ? rtrim( ABSPATH, '/\\' ) : dirname( __DIR__ );
    $af_file    = $af_docroot . $af_path;
    if ( ! file_exists( $af_file ) ) {
        http_response_code( 404 );
        header( 'Cache-Control: no-store, max-age=0' );
        header( 'Content-Type: text/plain; charset=utf-8' );
        header( 'X-AF-Guard: static-404' );
        exit( 'Not Found' );
    }
}
