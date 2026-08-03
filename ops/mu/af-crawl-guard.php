<?php
/**
 * Plugin Name: AF Crawl Guard (early bot short-circuit)
 * Description: Answers bot junk (hack-era spam URLs, headerless search, missing statics, bulk crawlers) before plugins/Elementor/WooCommerce load. Installed into wp-content/mu-plugins/ by ops/harden-crawl-budget.php because this host ignores custom .htaccess rewrite rules on the public path.
 *
 * Runs as a must-use plugin: after WP core config, BEFORE all plugins and the
 * theme. A blocked request costs ~0.2s instead of the ~3s full page build.
 * LiteSpeed cache HITs never reach PHP, so real cached traffic is untouched.
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
// 4. On-site search costs ~3s of PHP and cannot be cached. Real browsers send
//    Sec-Fetch-* headers; scrapers with fake Chrome UAs do not. Headerless
//    searches get a harmless redirect to the (cached, ~free) homepage — so
//    the worst case for a rare old browser is landing on the homepage, never
//    a dead "Forbidden". Skips wp-admin, the REST API and logged-in users.
if ( $af_qs !== '' && preg_match( '#(^|&)s=#', $af_qs )
    && empty( $_SERVER['HTTP_SEC_FETCH_MODE'] )
    && strpos( $af_path, '/wp-admin' ) !== 0
    && strpos( $af_path, '/wp-json' ) !== 0
) {
    $af_logged_in = false;
    foreach ( array_keys( $_COOKIE ) as $af_ck ) {
        if ( strpos( $af_ck, 'wordpress_logged_in_' ) === 0 ) { $af_logged_in = true; break; }
    }
    if ( ! $af_logged_in ) {
        header( 'Cache-Control: no-store, max-age=0' );
        header( 'X-AF-Guard: search-302' );
        header( 'Location: /', true, 302 );
        exit;
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
    $af_file = rtrim( ABSPATH, '/\\' ) . $af_path;
    if ( ! file_exists( $af_file ) ) {
        http_response_code( 404 );
        header( 'Cache-Control: no-store, max-age=0' );
        header( 'Content-Type: text/plain; charset=utf-8' );
        header( 'X-AF-Guard: static-404' );
        exit( 'Not Found' );
    }
}
