<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Crawl guard: stop bot traffic from burning CPU on uncacheable URLs.
 *
 * Measured 2026-08-03: a cached homepage costs ~4ms (LiteSpeed hit, no PHP)
 * while a WordPress-rendered 404 costs ~3s and /?s= search ~3.3s. One bot hit
 * on a dead URL therefore equals ~900 real pageviews, and the account sat at
 * 606% of its 600% CPU limit almost entirely from crawlers hammering:
 *   - /items/A######## spam URLs left indexed by the July SEO hack
 *   - on-site search /?s=
 *   - missing static files
 *
 * Writes a rewrite block into the DOCROOT .htaccess so those requests are
 * answered by the web server itself — zero PHP:
 *   1. /items/*                         -> 410 Gone (also the fastest way out of Google's index)
 *   2. /?s= without Sec-Fetch headers   -> 403 (real browsers send them; scrapers with fake Chrome UAs don't;
 *                                          never applies to logged-in users, wp-admin or wp-json)
 *   3. missing static files             -> plain 404, scoped to wp-content/wp-includes so virtual URLs
 *                                          (Rank Math sitemaps, robots.txt, feeds) are untouched
 *   4. bulk SEO/AI crawlers             -> 403 (Googlebot/Bingbot/link-preview fetchers deliberately NOT listed)
 *   5. xmlrpc.php                       -> 403 (already disabled in PHP — this makes the refusal free)
 *
 * NOTE: these rules answer before WordPress boots, so any future redirect for
 * /items/* or old asset URLs must be added HERE, not in a WP redirect plugin.
 *
 * Safety, in order:
 *   - atomic writes (tmp + rename) so live requests never see a partial .htaccess
 *   - homepage re-verified with a cache-busting URL; on a confirmed 403/404/410/500
 *     the previous .htaccess is restored (restore result is checked and, if it
 *     fails, the previous content is dumped to the log for manual recovery)
 *   - POSITIVE-path probe: a search request WITH Sec-Fetch headers must not be
 *     403. If it is (e.g. the CDN strips Sec-Fetch on the way to origin), the
 *     search rule alone is removed and the rest of the guard stays.
 *   - hard failures print "AF-GUARD-FAIL:" and exit 1; the deploy step greps
 *     for that marker and fails loudly instead of scrolling past.
 *
 * Idempotent: replaces its own BEGIN/END block; inserts after the security
 * headers block when present (so repeat runs converge byte-for-byte and the
 * two scripts stop rewriting the file on every deploy).
 * Run: wp eval-file wp-content/themes/postero-child/ops/harden-crawl-budget.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$htaccess = rtrim( ABSPATH, '/\\' ) . '/.htaccess';

$search_stanza = <<<'HT'

# On-site search costs ~3s of PHP per hit and cannot be cached. Real browsers
# send Sec-Fetch-* headers; scrapers (even with fake Chrome UAs) do not.
# Refuse headerless searches before WordPress loads. Never applies to
# logged-in users, wp-admin or the REST API. If the CDN ever stops forwarding
# Sec-Fetch headers, this script detects that and removes this rule.
RewriteCond %{REQUEST_URI} !^/(wp-admin|wp-json)/ [NC]
RewriteCond %{HTTP_COOKIE} !wordpress_logged_in_ [NC]
RewriteCond %{QUERY_STRING} (^|&)s= [NC]
RewriteCond %{HTTP:Sec-Fetch-Mode} ^$
RewriteRule ^ - [F,L]
HT;

$block_tpl = <<<'HT'
# BEGIN Art Framer Crawl Guard
<IfModule mod_rewrite.c>
RewriteEngine On

# Hack-era spam URLs: answer 410 Gone at the server, never boot WordPress.
RewriteRule ^items(/|$) - [G,NC,L]
%%SEARCH%%
# A request for a static file that does not exist must not boot WordPress just
# to render a themed 404. (The CDN already short-circuits missing images; this
# also covers css/js/fonts and direct-to-origin hits.) Virtual URLs like the
# Rank Math sitemaps live outside these paths and are untouched.
RewriteCond %{REQUEST_URI} ^/(wp-content|wp-includes)/ [NC]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule \.(jpe?g|png|gif|webp|avif|svg|ico|css|js|map|woff2?|ttf|otf|eot|mp4|webm|pdf)$ - [R=404,NC,L]

# Bulk SEO/AI crawlers that bring no customers. Googlebot, Bingbot and the
# social link-preview fetchers (facebookexternalhit, Pinterestbot, WhatsApp)
# are deliberately NOT listed. LiteSpeed ignores BrowserMatchNoCase/SetEnvIf —
# RewriteCond is the mechanism that actually works on this host.
RewriteCond %{HTTP_USER_AGENT} (ahrefsbot|semrushbot|mj12bot|dotbot|blexbot|dataforseobot|petalbot|bytespider|amazonbot|gptbot|ccbot|claudebot|meta-externalagent|imagesiftbot|serpstatbot|barkrowler|seekportbot|zoominfobot|megaindex|timpibot) [NC]
RewriteRule ^ - [F,L]

# XML-RPC is already disabled in PHP (PHASE 25); this makes the refusal free.
# Remove this rule if Jetpack or the WP mobile app ever needs xmlrpc back.
RewriteRule ^xmlrpc\.php$ - [F,L]
</IfModule>
# END Art Framer Crawl Guard
HT;

$block_full     = str_replace( '%%SEARCH%%', $search_stanza . "\n", $block_tpl );
$block_nosearch = str_replace( '%%SEARCH%%', '', $block_tpl );

/** Atomic write: tmp file in the same dir + rename, so a live request never
 *  reads a truncated .htaccess. Returns true on success. */
function af_guard_write( $path, $content ) {
    $tmp = $path . '.af-tmp';
    if ( file_put_contents( $tmp, $content ) === false ) { @unlink( $tmp ); return false; }
    @chmod( $tmp, file_exists( $path ) ? ( fileperms( $path ) & 0777 ) : 0644 );
    if ( ! rename( $tmp, $path ) ) { @unlink( $tmp ); return false; }
    return true;
}

/** Strip our block, then re-insert: after the security-headers block when it
 *  exists (repeat runs then converge byte-for-byte with that script's own
 *  prepend), else prepended. Must in all cases sit before "# BEGIN WordPress". */
function af_guard_compose( $existing, $block ) {
    $clean = preg_replace(
        '/# BEGIN Art Framer Crawl Guard.*?# END Art Framer Crawl Guard\s*/s',
        '',
        $existing
    );
    $sec_end = '# END Art Framer Security Headers';
    $parts   = explode( $sec_end, $clean, 2 );
    if ( count( $parts ) === 2 ) {
        return $parts[0] . $sec_end . "\n\n" . $block . "\n" . ltrim( $parts[1], "\n" );
    }
    return $block . "\n\n" . ltrim( $clean );
}

/** GET through the public hostname (i.e. the full CDN->origin path). */
function af_guard_probe( $path, $headers = array() ) {
    $r = wp_remote_get( home_url( $path ), array(
        'timeout'     => 45,
        'sslverify'   => false,
        'redirection' => 0,
        'user-agent'  => 'AF-CrawlGuard-Verify',
        'headers'     => $headers,
    ) );
    if ( is_wp_error( $r ) ) { return 'ERR: ' . $r->get_error_message(); }
    return (int) wp_remote_retrieve_response_code( $r );
}

$existing = file_exists( $htaccess ) ? file_get_contents( $htaccess ) : '';
if ( $existing === false ) { echo "AF-GUARD-FAIL: cannot read {$htaccess}\n"; exit(1); }

$new     = af_guard_compose( $existing, $block_full );
$changed = false;
if ( $new === $existing ) {
    echo "Crawl guard already current.\n";
} else {
    if ( file_exists( $htaccess ) && ! is_writable( $htaccess ) ) {
        echo "AF-GUARD-FAIL: {$htaccess} is not writable — crawl guard NOT applied.\n"; exit(1);
    }
    if ( ! af_guard_write( $htaccess, $new ) ) {
        echo "AF-GUARD-FAIL: could not write {$htaccess}\n"; exit(1);
    }
    $changed = true;
    echo "Crawl guard written to docroot .htaccess.\n";
}

// ── Homepage must still serve. Cache-busting query forces a real origin
// render (a plain homepage GET can be answered from cache and prove nothing).
// 403/404/410/500 are signatures of our rules misfiring or a syntax error;
// 508/502/504 mean the shared host is momentarily saturated — not our doing,
// so they retry and at worst downgrade to a warning, never a false rollback.
$home_ok = false; $saw_http_fail = false; $last = 'no response';
$fail_codes = array( 403, 404, 410, 500 );
for ( $i = 1; $i <= 3; $i++ ) {
    $code = af_guard_probe( '/?afv=' . uniqid() );
    $last = is_int( $code ) ? "HTTP {$code}" : $code;
    if ( is_int( $code ) && $code < 400 ) { $home_ok = true; break; }
    if ( is_int( $code ) && in_array( $code, $fail_codes, true ) ) { $saw_http_fail = true; }
    sleep( 5 );
}

if ( $changed && ! $home_ok && $saw_http_fail ) {
    if ( af_guard_write( $htaccess, $existing ) ) {
        echo "AF-GUARD-FAIL: ROLLED BACK — homepage returned {$last} after the edit; previous .htaccess restored.\n";
    } else {
        echo "AF-GUARD-FAIL: ROLLBACK FAILED — restore .htaccess manually. Previous content follows:\n";
        echo "----8<----\n" . $existing . "\n---->8----\n";
    }
    exit(1);
}
if ( ! $home_ok ) {
    echo "AF-GUARD-WARN: could not confirm homepage from here ({$last}) — verify https://theartframer.us/ manually. Not rolling back without a confirmed failure code.\n";
} else {
    echo "Homepage check (cache-busted): {$last}\n";
}

// ── POSITIVE path: a search carrying real-browser Sec-Fetch headers must NOT
// be 403. If it is, the CDN is stripping those headers and the rule would
// block every customer's search — remove just that rule, keep the rest.
$browser_headers = array(
    'Sec-Fetch-Mode' => 'navigate', 'Sec-Fetch-Site' => 'none',
    'Sec-Fetch-Dest' => 'document', 'Sec-Fetch-User' => '?1',
);
$search_rule_active = true;
$pos = af_guard_probe( '/?s=af-crawlguard-check', $browser_headers );
if ( $pos === 403 ) {
    $fallback = af_guard_compose( $existing, $block_nosearch );
    if ( af_guard_write( $htaccess, $fallback ) ) {
        $search_rule_active = false;
        $recheck = af_guard_probe( '/?s=af-crawlguard-check', $browser_headers );
        echo "AF-GUARD-WARN: CDN strips Sec-Fetch headers — search-guard rule REMOVED so real customers can search (recheck: {$recheck}). All other guards stay active.\n";
    } else {
        echo "AF-GUARD-FAIL: search guard blocks real browsers and the fallback write failed — restore .htaccess manually.\n";
        exit(1);
    }
} else {
    echo "Search positive-path check (browser headers): {$pos} (anything but 403 is correct)\n";
}

// ── Functional checks (report-only). af_guard_probe sends no Sec-Fetch
// headers, so the search probe exercises exactly the bot path we block. The
// static-file probe uses .css because the CDN answers missing *images* itself.
$checks = array(
    array( '/items/AF-crawlguard-check',                                 410, 'spam URL -> 410 Gone' ),
    array( '/wp-content/themes/postero-child/af-crawlguard-check.css',   404, 'missing static file -> server 404' ),
);
if ( $search_rule_active ) {
    $checks[] = array( '/?s=af-crawlguard-check', 403, 'headerless search -> 403' );
}
foreach ( $checks as $c ) {
    list( $path, $want, $label ) = $c;
    $got = af_guard_probe( $path );
    $got = is_int( $got ) ? (string) $got : $got;
    echo ( (string) $want === $got ? 'PASS' : 'CHECK' ) . ": {$label} (want {$want}, got {$got})\n";
}
echo "=== DONE ===\n";
