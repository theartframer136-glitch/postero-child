<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Is the offers ticker actually gone from the pages it should be gone from?
 *
 * The gate was written, committed and deployed, and the bar is still on screen.
 * That leaves exactly three possibilities, and guessing between them has
 * already cost a round trip each time. So each is asked directly:
 *
 *   1. the file on the SERVER is not the file in the repository (rsync, or
 *      PHP's opcache still holding the previous copy)
 *   2. the file is current and the gate is not doing what it looks like it does
 *   3. both are fine and the page being looked at is a cached copy, in
 *      LiteSpeed or in the browser
 *
 * Read-only. Run: wp eval-file tools/diag-ticker-live.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }

echo "=== DIAG: IS THE OFFERS TICKER GONE? ===\n";

/* ── 1. the code that is actually loaded ──────────────────────────────── */
echo "\nA. THE CODE THIS SERVER IS RUNNING\n";
printf("   af_ticker_should_run() exists: %s\n",
    function_exists('af_ticker_should_run') ? 'YES' : 'NO  <-- the server is running an OLDER functions.php');
if (function_exists('af_ticker_should_run')) {
    $r = new ReflectionFunction('af_ticker_should_run');
    printf("   defined at: %s line %d\n", str_replace(ABSPATH, '', $r->getFileName()), $r->getStartLine());
}
$f = get_stylesheet_directory() . '/functions.php';
printf("   functions.php last modified: %s UTC (%d bytes)\n",
    @filemtime($f) ? gmdate('Y-m-d H:i', filemtime($f)) : '?', (int) @filesize($f));
printf("   opcache: %s\n", function_exists('opcache_get_status') ? 'available' : 'not available');

/* ── 2. what the pages actually serve ─────────────────────────────────── */
// Fetched with a cache-busting argument so this reports what the SITE
// generates, not what a cache happens to be holding — and then once WITHOUT
// it, which is what a visitor really gets. The difference between the two is
// the whole answer when caching is the culprit.
echo "\nB. WHAT THE PAGES SERVE\n";
$targets = array('home' => home_url('/'));
if (function_exists('af_goldfoil_slug')) {
    $t = get_term_by('slug', af_goldfoil_slug(), 'product_cat');
    if ($t && !is_wp_error($t)) $targets['gold-foiled-uv'] = get_term_link($t);
}
foreach (get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => true, 'number' => 1,
                         'orderby' => 'count', 'order' => 'DESC')) as $busy) {
    $targets['another category (' . $busy->slug . ')'] = get_term_link($busy);
    break;
}

foreach ($targets as $label => $url) {
    if (is_wp_error($url)) { printf("   %-28s (no link)\n", $label); continue; }
    foreach (array('fresh' => true, 'as a visitor sees it' => false) as $how => $bust) {
        $u = $bust ? add_query_arg('afticker', time(), $url) : $url;
        $res = wp_remote_get($u, array('timeout' => 45, 'sslverify' => false,
            'headers' => array('User-Agent' => 'Mozilla/5.0 AF-Diag', 'Cache-Control' => 'no-cache')));
        if (is_wp_error($res)) { printf("   %-28s %-22s could not load: %s\n", $label, $how, $res->get_error_message()); continue; }
        $html = (string) wp_remote_retrieve_body($res);
        $has  = (strpos($html, 'id="afTicker"') !== false);
        printf("   %-28s %-22s ticker: %-8s (%d bytes)%s\n",
            $label, $how, $has ? 'PRESENT' : 'gone', strlen($html),
            ($label === 'home') ? ($has ? '  <-- correct' : '  <-- WRONG, it belongs here')
                                : ($has ? '  <-- WRONG, it should be gone' : '  <-- correct'));
    }
}

echo "\n=== DONE ===\n";
