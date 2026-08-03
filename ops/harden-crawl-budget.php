<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Crawl guard installer: stop bot traffic from burning CPU on uncacheable URLs.
 *
 * Measured 2026-08-03: cached pages ~4ms, WP-rendered 404 ~3s, /?s= ~3.3s.
 * The account sat at 606% of its 600% CPU limit almost entirely from crawlers
 * hammering /items/A######## hack leftovers, search, and missing files.
 *
 * TWO layers, because run #528 proved this Hostinger stack IGNORES custom
 * .htaccess rewrite rules on the public (CDN->origin) path:
 *   1. .htaccess block (kept as a belt — free if the platform ever honors it)
 *   2. mu-plugin  wp-content/mu-plugins/af-crawl-guard.php  — the layer that
 *      works: PHP answers junk in ~0.2s before plugins/Elementor/Woo load.
 *
 * Verification (each gap below was found by adversarial review — keep them):
 *   - cache-busted homepage check; ANY non-2xx/3xx outcome across 3 attempts
 *     right after a change = confirmed breakage -> restore both layers, exit 1
 *     (the pre-change site was serving; "can't confirm" is not good enough)
 *   - rollback results are CHECKED; a failed restore prints AF-GUARD-FAIL with
 *     manual instructions instead of claiming success
 *   - positive-path search probe trusts only the mu-plugin's own X-AF-Guard
 *     marker (an edge/WAF 403 without it must not strip the rule) and reports
 *     whether it actually traversed the CDN; the deploy workflow re-checks
 *     from the GitHub runner (provably through the CDN) and can invoke
 *     `wp eval-file ... harden-crawl-budget.php strip-search` — persisted via
 *     option af_guard_no_search so later deploys stay stripped
 *   - effectiveness probes assert the X-AF-Guard marker header, not just the
 *     status code (a themed WP 404 is also "404" — that hid run #528's miss)
 *
 * Run: wp eval-file wp-content/themes/postero-child/ops/harden-crawl-budget.php --allow-root
 * Strip search rule: append positional arg `strip-search`.
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$htaccess = rtrim( ABSPATH, '/\\' ) . '/.htaccess';
$mu_src   = __DIR__ . '/mu/af-crawl-guard.php';
$mu_dir   = ( defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : rtrim( ABSPATH, '/\\' ) . '/wp-content' ) . '/mu-plugins';
$mu_dst   = $mu_dir . '/af-crawl-guard.php';

$strip_requested = isset( $args ) && is_array( $args ) && in_array( 'strip-search', $args, true );
if ( $strip_requested && get_option( 'af_guard_no_search' ) !== 'yes' ) {
    update_option( 'af_guard_no_search', 'yes', true );
    echo "strip-search requested: persisted via option af_guard_no_search.\n";
}
$want_search = ( get_option( 'af_guard_no_search' ) !== 'yes' );

$search_stanza = <<<'HT'

# On-site search costs ~3s of PHP per hit and cannot be cached. Real browsers
# send Sec-Fetch-* headers; scrapers (even with fake Chrome UAs) do not.
# Headerless searches are redirected to the cached homepage before WP loads.
# Never applies to logged-in users, wp-admin or the REST API.
RewriteCond %{REQUEST_URI} !^/(wp-admin|wp-json)/ [NC]
RewriteCond %{HTTP_COOKIE} !wordpress_logged_in_ [NC]
RewriteCond %{QUERY_STRING} (^|&)s= [NC]
RewriteCond %{HTTP:Sec-Fetch-Mode} ^$
RewriteRule ^ / [R=302,L]
HT;

$block_tpl = <<<'HT'
# BEGIN Art Framer Crawl Guard
<IfModule mod_rewrite.c>
RewriteEngine On

# Hack-era spam URLs: answer 410 Gone at the server, never boot WordPress.
RewriteRule ^items(/|$) - [G,NC,L]
%%SEARCH%%
# A request for a static file that does not exist must not boot WordPress just
# to render a themed 404. Virtual URLs like the Rank Math sitemaps live
# outside these paths and are untouched.
RewriteCond %{REQUEST_URI} ^/(wp-content|wp-includes)/ [NC]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule \.(jpe?g|png|gif|webp|avif|svg|ico|css|js|map|woff2?|ttf|otf|eot|mp4|webm|pdf)$ - [R=404,NC,L]

# Bulk SEO/AI crawlers that bring no customers. Googlebot, Bingbot and the
# social link-preview fetchers are deliberately NOT listed.
RewriteCond %{HTTP_USER_AGENT} (ahrefsbot|semrushbot|mj12bot|dotbot|blexbot|dataforseobot|petalbot|bytespider|amazonbot|gptbot|ccbot|claudebot|meta-externalagent|imagesiftbot|serpstatbot|barkrowler|seekportbot|zoominfobot|megaindex|timpibot) [NC]
RewriteRule ^ - [F,L]

# XML-RPC is already disabled in PHP (PHASE 25); this makes the refusal free.
RewriteRule ^xmlrpc\.php$ - [F,L]
</IfModule>
# END Art Framer Crawl Guard
HT;

/** Atomic write: tmp + rename so a live request never reads a partial file.
 *  On failure, stashes the real PHP error in $GLOBALS['af_guard_err'] so the
 *  caller can report *why*, not just *that*, the write failed. */
function af_guard_write( $path, $content ) {
    if ( ! is_string( $content ) ) { return false; }
    $tmp = $path . '.af-tmp';
    if ( file_put_contents( $tmp, $content ) === false ) {
        $e = error_get_last();
        $GLOBALS['af_guard_err'] = 'write ' . $tmp . ' failed: ' . ( $e['message'] ?? 'unknown error' );
        @unlink( $tmp ); return false;
    }
    @chmod( $tmp, file_exists( $path ) ? ( fileperms( $path ) & 0777 ) : 0644 );
    if ( ! rename( $tmp, $path ) ) {
        $e = error_get_last();
        $GLOBALS['af_guard_err'] = 'rename ' . $tmp . ' -> ' . $path . ' failed: ' . ( $e['message'] ?? 'unknown error' );
        @unlink( $tmp ); return false;
    }
    return true;
}

/** Strip our .htaccess block, re-insert after the security-headers block when
 *  present (repeat runs converge byte-for-byte), else prepend. */
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

/** GET through the public hostname. Returns code/err/marker/via_cdn. */
function af_guard_probe( $path, $headers = array() ) {
    $r = wp_remote_get( home_url( $path ), array(
        'timeout'     => 45,
        'sslverify'   => false,
        'redirection' => 0,
        'user-agent'  => 'AF-CrawlGuard-Verify',
        'headers'     => $headers,
    ) );
    if ( is_wp_error( $r ) ) {
        return array( 'code' => null, 'err' => $r->get_error_message(), 'marker' => null, 'via_cdn' => false );
    }
    $server = (string) wp_remote_retrieve_header( $r, 'server' );
    return array(
        'code'    => (int) wp_remote_retrieve_response_code( $r ),
        'err'     => null,
        'marker'  => (string) wp_remote_retrieve_header( $r, 'x-af-guard' ),
        'via_cdn' => ( stripos( $server, 'hcdn' ) !== false )
                     || wp_remote_retrieve_header( $r, 'x-hcdn-request-id' ) !== ''
                     || wp_remote_retrieve_header( $r, 'x-hcdn-cache-status' ) !== '',
    );
}

/** Cache-busted homepage check: true only on a 2xx/3xx within 3 attempts. */
function af_guard_home_ok( &$last ) {
    $last = 'no response';
    for ( $i = 1; $i <= 3; $i++ ) {
        $p = af_guard_probe( '/?afv=' . uniqid() );
        $last = ( $p['code'] !== null ) ? "HTTP {$p['code']}" : 'ERR: ' . $p['err'];
        if ( $p['code'] !== null && $p['code'] < 400 ) { return true; }
        sleep( 5 );
    }
    return false;
}

/** Restore pre-run state; returns list of failures (empty = clean restore). */
function af_guard_restore( $mu_dst, $mu_prev, $mu_changed, $htaccess, $ht_prev, $ht_changed ) {
    $fails = array();
    if ( $mu_changed ) {
        if ( $mu_prev === null ) {
            if ( file_exists( $mu_dst ) && ! @unlink( $mu_dst ) ) { $fails[] = "delete {$mu_dst}"; }
        } elseif ( ! af_guard_write( $mu_dst, $mu_prev ) ) {
            $fails[] = "rewrite {$mu_dst}";
        }
    }
    if ( $ht_changed && ! af_guard_write( $htaccess, $ht_prev ) ) {
        $fails[] = "rewrite {$htaccess}";
    }
    return $fails;
}

// ── Compose desired contents (search rule included only if still trusted) ──
$mu_full = file_exists( $mu_src ) ? file_get_contents( $mu_src ) : false;
if ( $mu_full === false || strpos( $mu_full, 'AF Crawl Guard' ) === false ) {
    echo "AF-GUARD-FAIL: mu-plugin source missing/invalid at {$mu_src}\n"; exit(1);
}
$mu_stripped = preg_replace( '#// BEGIN AF-SEARCH-GUARD.*?// END AF-SEARCH-GUARD\s*#s', '', $mu_full );
$mu_want = $want_search ? $mu_full : $mu_stripped;
$ht_block = str_replace( '%%SEARCH%%', $want_search ? $search_stanza . "\n" : '', $block_tpl );

// ── Layer 1: .htaccess (belt; known inert on the public path today) ─────────
$ht_prev = file_exists( $htaccess ) ? file_get_contents( $htaccess ) : '';
if ( $ht_prev === false ) { echo "AF-GUARD-FAIL: cannot read {$htaccess}\n"; exit(1); }
$ht_new     = af_guard_compose( $ht_prev, $ht_block );
$ht_changed = false;
if ( $ht_new === $ht_prev ) {
    echo "htaccess block already current.\n";
} elseif ( ( file_exists( $htaccess ) && ! is_writable( $htaccess ) ) || ! af_guard_write( $htaccess, $ht_new ) ) {
    echo "AF-GUARD-WARN: could not write {$htaccess} — htaccess belt skipped (mu-plugin is the working layer).\n";
} else {
    $ht_changed = true;
    echo "htaccess block written.\n";
}

// ── Layer 2: the mu-plugin (the layer that actually fires) ──────────────────
$mu_dir_existed = is_dir( $mu_dir );
if ( ! $mu_dir_existed && ! mkdir( $mu_dir, 0755, true ) ) {
    $e = error_get_last();
    echo "AF-GUARD-FAIL: cannot create {$mu_dir} — " . ( $e['message'] ?? 'unknown error' ) . "\n"; exit(1);
}
if ( $mu_dir_existed ) {
    $owner = function_exists( 'posix_getpwuid' ) ? ( posix_getpwuid( fileowner( $mu_dir ) )['name'] ?? fileowner( $mu_dir ) ) : fileowner( $mu_dir );
    $me    = function_exists( 'posix_getpwuid' ) ? ( posix_getpwuid( posix_geteuid() )['name'] ?? posix_geteuid() ) : 'unknown';
    echo "mu-plugins dir exists: perms " . substr( sprintf( '%o', fileperms( $mu_dir ) ), -4 )
        . ", owner {$owner}, running as {$me}, writable=" . ( is_writable( $mu_dir ) ? 'yes' : 'no' ) . "\n";
}
$mu_prev = file_exists( $mu_dst ) ? file_get_contents( $mu_dst ) : null;
if ( $mu_prev === false ) { $mu_prev = null; } // unreadable = treat as absent, never "restore" an empty file
$mu_changed = false;
if ( $mu_prev === $mu_want ) {
    echo "mu-plugin already current" . ( $want_search ? '' : ' (search rule stripped)' ) . ".\n";
} elseif ( ! af_guard_write( $mu_dst, $mu_want ) ) {
    $reason = $GLOBALS['af_guard_err'] ?? 'unknown error';
    echo "AF-GUARD-FAIL: could not write {$mu_dst} — {$reason}\n"; exit(1);
} else {
    $mu_changed = true;
    echo "mu-plugin installed" . ( $want_search ? '' : ' (search rule stripped)' ) . ": {$mu_dst}\n";
}

// ── Homepage must still serve. The pre-change site was serving, so if we
// changed anything and can't get a single 2xx/3xx in 3 tries, that's a
// confirmed break: restore and fail. ─────────────────────────────────────────
$last = '';
if ( ! af_guard_home_ok( $last ) ) {
    if ( $mu_changed || $ht_changed ) {
        $fails = af_guard_restore( $mu_dst, $mu_prev, $mu_changed, $htaccess, $ht_prev, $ht_changed );
        if ( empty( $fails ) ) {
            echo "AF-GUARD-FAIL: ROLLED BACK — homepage returned {$last} after the change; previous state restored.\n";
        } else {
            echo "AF-GUARD-FAIL: ROLLBACK FAILED (" . implode( ', ', $fails ) . ") — site may be broken; manually delete wp-content/mu-plugins/af-crawl-guard.php\n";
        }
    } else {
        echo "AF-GUARD-FAIL: homepage returned {$last} but nothing changed this run — investigate the site, not this script.\n";
    }
    exit(1);
}
echo "Homepage check (cache-busted): {$last}\n";

// ── Positive path: a browser-headed search must NOT trip OUR guard. Only the
// mu-plugin's own marker counts (an edge/WAF artifact must not strip the
// rule), and the verdict is only trustworthy if the probe crossed the CDN. ──
$search_active = $want_search;
if ( $want_search ) {
    $browser_headers = array(
        'Sec-Fetch-Mode' => 'navigate', 'Sec-Fetch-Site' => 'none',
        'Sec-Fetch-Dest' => 'document', 'Sec-Fetch-User' => '?1',
    );
    $pos = af_guard_probe( '/?s=af-guard-pos-' . uniqid(), $browser_headers );
    if ( strpos( (string) $pos['marker'], 'search-' ) === 0 ) {
        // Our rule fired despite browser headers => Sec-Fetch did not survive
        // the path. Strip it everywhere and persist the decision.
        update_option( 'af_guard_no_search', 'yes', true );
        $ok = af_guard_write( $mu_dst, $mu_stripped )
              && af_guard_write( $htaccess, af_guard_compose( $ht_prev, str_replace( '%%SEARCH%%', '', $block_tpl ) ) );
        $search_active = false;
        if ( ! $ok ) {
            echo "AF-GUARD-FAIL: search rule misfires for browsers and the strip-rewrite failed — manually delete wp-content/mu-plugins/af-crawl-guard.php\n";
            exit(1);
        }
        if ( ! af_guard_home_ok( $last ) ) {
            $fails = af_guard_restore( $mu_dst, $mu_prev, true, $htaccess, $ht_prev, true );
            echo empty( $fails )
                ? "AF-GUARD-FAIL: ROLLED BACK — homepage returned {$last} after the search-strip rewrite.\n"
                : "AF-GUARD-FAIL: ROLLBACK FAILED after search-strip (" . implode( ', ', $fails ) . ") — manually delete wp-content/mu-plugins/af-crawl-guard.php\n";
            exit(1);
        }
        echo "AF-GUARD-WARN: Sec-Fetch headers do not survive to PHP — search rule REMOVED everywhere (persisted) so customer search keeps working.\n";
    } elseif ( ! $pos['via_cdn'] ) {
        echo "AF-GUARD-WARN: positive-path probe did not traverse the CDN (vantage unproven) — the deploy's external search gate is the authority here.\n";
    } else {
        echo "Search positive-path via CDN: HTTP {$pos['code']}, no guard marker — correct.\n";
    }
}

// ── EFFECTIVENESS: unique URLs + the guard's own marker header, so neither a
// cache nor a themed WP 404 can fake a PASS. ────────────────────────────────
$eff_fail = 0;
$checks = array(
    array( '/items/AF-' . uniqid(),                                    410, 'items-410',  'spam URL -> 410' ),
    array( '/wp-content/themes/postero-child/af-' . uniqid() . '.css', 404, 'static-404', 'missing static -> instant 404' ),
);
if ( $search_active ) {
    $checks[] = array( '/?s=af-guard-bot-' . uniqid(), 302, 'search-302', 'headerless search -> 302 home' );
}
foreach ( $checks as $c ) {
    list( $path, $want_code, $want_marker, $label ) = $c;
    $p = af_guard_probe( $path );
    $got  = ( $p['code'] !== null ) ? (string) $p['code'] : 'ERR: ' . $p['err'];
    $pass = ( $p['code'] === $want_code && $p['marker'] === $want_marker );
    if ( ! $pass ) { $eff_fail++; }
    echo ( $pass ? 'PASS' : 'MISS' ) . ": {$label} (want {$want_code}+{$want_marker}, got {$got}+" . ( $p['marker'] !== '' ? $p['marker'] : 'no-marker' ) . ")\n";
}
if ( $eff_fail > 0 ) {
    echo "AF-GUARD-WARN: {$eff_fail} effectiveness probe(s) missed — the guard may not be filtering on the public path. Investigate before trusting the CPU fix.\n";
}
echo "=== DONE ===\n";
