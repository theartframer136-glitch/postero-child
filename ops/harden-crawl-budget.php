<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Crawl guard installer: stop bot traffic from burning CPU on uncacheable URLs.
 *
 * Measured 2026-08-03: cached pages ~4ms, WP-rendered 404 ~3s, /?s= ~3.3s.
 * The account sat at 606% of its 600% CPU limit almost entirely from crawlers
 * hammering /items/A######## hack leftovers, search, and missing files.
 *
 * Install anatomy — three host facts forced this design:
 *   - custom .htaccess rewrite rules are IGNORED on the public path (run #528)
 *   - wp-content/mu-plugins is ROOT-OWNED, writable=no (run #532; managed-WP
 *     platform, core mounted from /opt/h5g/flavors)
 *   - wp-content itself IS user-writable (themes/plugins/uploads live there)
 * So: the guard CONTENT is copied to wp-content/af-crawl-guard.php (rsync
 * never touches wp-content root, so it survives deploys), and a LOADER hooks
 * it in — preferred: a marker-delimited require in wp-config.php (runs before
 * WordPress: blocked requests cost ~10ms); fallback if wp-config is not
 * writable: a tiny loader plugin forced to the front of active_plugins.
 * Deleting wp-content/af-crawl-guard.php safely disables everything (the
 * loader is is_file-guarded). An .htaccess belt block is still written.
 *
 * Verification (each gap was found the hard way — keep all of it):
 *   - cache-busted homepage check; ANY non-2xx/3xx outcome across 3 attempts
 *     right after a change = confirmed breakage -> restore everything, exit 1
 *   - rollback results are CHECKED and reported truthfully
 *   - positive-path search probe trusts only the guard's own X-AF-Guard
 *     marker; the deploy workflow re-checks from the GitHub runner (provably
 *     through the CDN) and can invoke `... harden-crawl-budget.php
 *     strip-search` — persisted via option af_guard_no_search
 *   - effectiveness probes assert the X-AF-Guard marker, not just the status
 *
 * Run: wp eval-file wp-content/themes/postero-child/ops/harden-crawl-budget.php --allow-root
 * Strip search rule: append positional arg `strip-search`.
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$htaccess    = rtrim( ABSPATH, '/\\' ) . '/.htaccess';
$guard_src   = __DIR__ . '/mu/af-crawl-guard.php';
$content_dir = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : rtrim( ABSPATH, '/\\' ) . '/wp-content';
$content_dst = $content_dir . '/af-crawl-guard.php';
$plugin_dir  = ( defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : $content_dir . '/plugins' ) . '/af-crawl-guard';
$plugin_dst  = $plugin_dir . '/af-crawl-guard.php';
$plugin_rel  = 'af-crawl-guard/af-crawl-guard.php';

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

/** Idempotently place the loader line right after wp-config's opening <?php.
 *  Returns the new file content, or null if wp-config looks too odd to touch. */
function af_guard_wpc_compose( $existing, $loader_line ) {
    $clean = preg_replace( '#[ \t]*/\* BEGIN AF-CRAWL-GUARD \*/.*?/\* END AF-CRAWL-GUARD \*/\n?#s', '', $existing );
    if ( $clean === null ) { return null; }
    $pos = strpos( $clean, '<?php' );
    if ( $pos !== 0 && $pos !== false && trim( substr( $clean, 0, $pos ) ) !== '' ) { return null; }
    if ( $pos === false ) { return null; }
    $nl = strpos( $clean, "\n", $pos );
    if ( $nl === false ) { return null; }
    return substr( $clean, 0, $nl + 1 ) . $loader_line . "\n" . substr( $clean, $nl + 1 );
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

/** File-permission diagnostic line (owner/perms/writability). */
function af_guard_diag( $path ) {
    if ( ! file_exists( $path ) ) { return "{$path}: does not exist"; }
    $owner = function_exists( 'posix_getpwuid' ) ? ( posix_getpwuid( fileowner( $path ) )['name'] ?? fileowner( $path ) ) : fileowner( $path );
    return "{$path}: perms " . substr( sprintf( '%o', fileperms( $path ) ), -4 )
        . ", owner {$owner}, writable=" . ( is_writable( $path ) ? 'yes' : 'no' );
}

// ── Compose desired contents (search rule included only if still trusted) ──
$guard_full = file_exists( $guard_src ) ? file_get_contents( $guard_src ) : false;
if ( $guard_full === false || strpos( $guard_full, 'AF Crawl Guard' ) === false ) {
    echo "AF-GUARD-FAIL: guard source missing/invalid at {$guard_src}\n"; exit(1);
}
$guard_stripped = preg_replace( '#// BEGIN AF-SEARCH-GUARD.*?// END AF-SEARCH-GUARD\s*#s', '', $guard_full );
$guard_want = $want_search ? $guard_full : $guard_stripped;
$ht_block   = str_replace( '%%SEARCH%%', $want_search ? $search_stanza . "\n" : '', $block_tpl );

$rollback = array(); // list of array('desc' => ..., 'fn' => callable): undo in reverse order

// ── Layer 1: .htaccess (belt; known inert on the public path today) ─────────
$ht_prev = file_exists( $htaccess ) ? file_get_contents( $htaccess ) : '';
if ( $ht_prev === false ) { echo "AF-GUARD-FAIL: cannot read {$htaccess}\n"; exit(1); }
$ht_new = af_guard_compose( $ht_prev, $ht_block );
if ( $ht_new === $ht_prev ) {
    echo "htaccess block already current.\n";
} elseif ( ( file_exists( $htaccess ) && ! is_writable( $htaccess ) ) || ! af_guard_write( $htaccess, $ht_new ) ) {
    echo "AF-GUARD-WARN: could not write {$htaccess} — htaccess belt skipped.\n";
} else {
    echo "htaccess block written.\n";
    $rollback[] = array( 'desc' => 'htaccess', 'fn' => function () use ( $htaccess, $ht_prev ) {
        return af_guard_write( $htaccess, $ht_prev );
    } );
}

// ── Layer 2a: the guard CONTENT at wp-content/af-crawl-guard.php ────────────
$ct_prev = file_exists( $content_dst ) ? file_get_contents( $content_dst ) : null;
if ( $ct_prev === false ) { $ct_prev = null; } // unreadable = treat as absent, never "restore" an empty file
if ( $ct_prev === $guard_want ) {
    echo "guard content already current" . ( $want_search ? '' : ' (search rule stripped)' ) . ".\n";
} elseif ( ! af_guard_write( $content_dst, $guard_want ) ) {
    $reason = $GLOBALS['af_guard_err'] ?? 'unknown error';
    echo af_guard_diag( $content_dir ) . "\n";
    echo "AF-GUARD-FAIL: could not write {$content_dst} — {$reason}\n"; exit(1);
} else {
    echo "guard content installed" . ( $want_search ? '' : ' (search rule stripped)' ) . ": {$content_dst}\n";
    $rollback[] = array( 'desc' => 'guard-content', 'fn' => function () use ( $content_dst, $ct_prev ) {
        return ( $ct_prev === null ) ? ( ! file_exists( $content_dst ) || @unlink( $content_dst ) )
                                     : af_guard_write( $content_dst, $ct_prev );
    } );
}

// ── Layer 2b: the LOADER — wp-config require preferred, plugin fallback ─────
$loader_line = "/* BEGIN AF-CRAWL-GUARD */ if ( PHP_SAPI !== 'cli' && is_file( '{$content_dst}' ) ) { require '{$content_dst}'; } /* END AF-CRAWL-GUARD */";
$wpc = null;
foreach ( array( rtrim( ABSPATH, '/\\' ) . '/wp-config.php', dirname( rtrim( ABSPATH, '/\\' ) ) . '/wp-config.php' ) as $cand ) {
    if ( file_exists( $cand ) ) { $wpc = $cand; break; }
}
$loader_mode = 'none';
if ( $wpc !== null && is_writable( $wpc ) ) {
    $wpc_prev = file_get_contents( $wpc );
    $wpc_new  = ( $wpc_prev === false ) ? null : af_guard_wpc_compose( $wpc_prev, $loader_line );
    if ( $wpc_new === null ) {
        echo "AF-GUARD-WARN: {$wpc} has an unexpected shape — not touching it; falling back to loader plugin.\n";
    } elseif ( $wpc_new === $wpc_prev ) {
        $loader_mode = 'wp-config';
        echo "wp-config loader already current.\n";
    } elseif ( af_guard_write( $wpc, $wpc_new ) ) {
        $loader_mode = 'wp-config';
        echo "wp-config loader installed: {$wpc}\n";
        $rollback[] = array( 'desc' => 'wp-config', 'fn' => function () use ( $wpc, $wpc_prev ) {
            return af_guard_write( $wpc, $wpc_prev );
        } );
    } else {
        echo "AF-GUARD-WARN: could not rewrite {$wpc} (" . ( $GLOBALS['af_guard_err'] ?? 'unknown' ) . ") — falling back to loader plugin.\n";
    }
} else {
    echo "wp-config not writable — " . ( $wpc !== null ? af_guard_diag( $wpc ) : 'not found near ABSPATH' ) . "; using loader plugin.\n";
}

if ( $loader_mode === 'none' ) {
    $plugin_code = "<?php\n/**\n * Plugin Name: AF Crawl Guard loader\n * Description: Loads the early bot guard from wp-content/af-crawl-guard.php. Managed by ops/harden-crawl-budget.php — do not edit.\n */\n\$af = WP_CONTENT_DIR . '/af-crawl-guard.php';\nif ( is_file( \$af ) ) { require \$af; }\n";
    if ( ! is_dir( $plugin_dir ) && ! mkdir( $plugin_dir, 0755, true ) ) {
        echo af_guard_diag( dirname( $plugin_dir ) ) . "\n";
        echo "AF-GUARD-FAIL: no usable loader — mu-plugins root-owned, wp-config unwritable, and cannot create {$plugin_dir}\n"; exit(1);
    }
    $pl_prev = file_exists( $plugin_dst ) ? file_get_contents( $plugin_dst ) : null;
    if ( $pl_prev === false ) { $pl_prev = null; }
    if ( $pl_prev !== $plugin_code && ! af_guard_write( $plugin_dst, $plugin_code ) ) {
        echo "AF-GUARD-FAIL: could not write {$plugin_dst} — " . ( $GLOBALS['af_guard_err'] ?? 'unknown' ) . "\n"; exit(1);
    }
    if ( $pl_prev !== $plugin_code ) {
        $rollback[] = array( 'desc' => 'loader-plugin-file', 'fn' => function () use ( $plugin_dst, $pl_prev ) {
            return ( $pl_prev === null ) ? ( ! file_exists( $plugin_dst ) || @unlink( $plugin_dst ) )
                                         : af_guard_write( $plugin_dst, $pl_prev );
        } );
    }
    $active_prev = get_option( 'active_plugins', array() );
    if ( ! is_array( $active_prev ) ) { $active_prev = array(); }
    $active_new = array_values( array_unique( array_merge( array( $plugin_rel ), $active_prev ) ) );
    if ( $active_new !== $active_prev ) {
        update_option( 'active_plugins', $active_new );
        echo "loader plugin activated (front of load order).\n";
        $rollback[] = array( 'desc' => 'active_plugins', 'fn' => function () use ( $active_prev ) {
            return update_option( 'active_plugins', $active_prev ) !== false || get_option( 'active_plugins' ) === $active_prev;
        } );
    } else {
        echo "loader plugin already active.\n";
    }
    $loader_mode = 'plugin';
}

/** Undo everything this run changed, in reverse order; returns failures. */
function af_guard_run_rollback( $rollback ) {
    $fails = array();
    foreach ( array_reverse( $rollback ) as $step ) {
        if ( ! call_user_func( $step['fn'] ) ) { $fails[] = $step['desc']; }
    }
    return $fails;
}

// ── Homepage must still serve. The pre-change site was serving, so if we
// changed anything and can't get a single 2xx/3xx in 3 tries, that's a
// confirmed break: restore and fail. ─────────────────────────────────────────
$last = '';
if ( ! af_guard_home_ok( $last ) ) {
    if ( ! empty( $rollback ) ) {
        $fails = af_guard_run_rollback( $rollback );
        if ( empty( $fails ) ) {
            echo "AF-GUARD-FAIL: ROLLED BACK — homepage returned {$last} after the change; previous state restored.\n";
        } else {
            echo "AF-GUARD-FAIL: ROLLBACK FAILED (" . implode( ', ', $fails ) . ") — manually delete {$content_dst} (the loader no-ops without it).\n";
        }
    } else {
        echo "AF-GUARD-FAIL: homepage returned {$last} but nothing changed this run — investigate the site, not this script.\n";
    }
    exit(1);
}
echo "Homepage check (cache-busted): {$last} [loader: {$loader_mode}]\n";

// ── Positive path: a browser-headed search must NOT trip OUR guard. Only the
// guard's own marker counts (an edge/WAF artifact must not strip the rule),
// and the verdict is only trustworthy if the probe crossed the CDN. ─────────
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
        $ok = af_guard_write( $content_dst, $guard_stripped )
              && af_guard_write( $htaccess, af_guard_compose( $ht_prev, str_replace( '%%SEARCH%%', '', $block_tpl ) ) );
        $search_active = false;
        if ( ! $ok ) {
            echo "AF-GUARD-FAIL: search rule misfires for browsers and the strip-rewrite failed — manually delete {$content_dst}\n";
            exit(1);
        }
        if ( ! af_guard_home_ok( $last ) ) {
            $fails = af_guard_run_rollback( $rollback );
            echo empty( $fails )
                ? "AF-GUARD-FAIL: ROLLED BACK — homepage returned {$last} after the search-strip rewrite.\n"
                : "AF-GUARD-FAIL: ROLLBACK FAILED after search-strip (" . implode( ', ', $fails ) . ") — manually delete {$content_dst}\n";
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
