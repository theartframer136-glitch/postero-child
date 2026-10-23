<?php
/**
 * Clicks that feel instant: fetch the next page while the pointer rests on a link.
 *
 * WordPress already prints Speculation Rules on every page, but in its
 * default "conservative" eagerness, which starts the fetch only on
 * mousedown/touchstart, a head start of a few tens of milliseconds. Measured
 * 23 Oct 2026 (tools/audit-perf.mjs): a cached page here answers in about
 * 0.07s, so the whole wait a shopper feels is the round trip after the click.
 *
 * "moderate" starts the fetch after the pointer has rested on a link for
 * about 200ms, and on phones as the tap begins. People typically hover for
 * 300-600ms before clicking, so the page is usually already downloaded when
 * the click lands.
 *
 * Kept at prefetch (download the HTML), not prerender (run the page in the
 * background): a prerendered page would run the Meta pixel and analytics for
 * pages nobody opened, inflating PageViews and spoiling ad optimisation.
 *
 * Pages that are never cached are left out, so a hover cannot make the
 * CPU-bound host build them for nothing: cart, checkout, account, wishlist,
 * order tracking. Links with a query string (add-to-cart, filters, search)
 * are already excluded by WordPress's own rule.
 */
if (!defined('ABSPATH')) exit;

add_filter('wp_speculation_rules_configuration', function ($config) {
    if (!is_array($config)) return $config;   // null = speculative loading switched off; leave it off
    $config['mode']      = 'prefetch';
    $config['eagerness'] = (string) apply_filters('af_speculation_eagerness', 'moderate');
    return $config;
});

add_filter('wp_speculation_rules_href_exclude_paths', function ($paths) {
    foreach (array('/cart/*', '/checkout/*', '/my-account/*', '/wishlist/*', '/track-your-order/*', '/compare/*') as $p) {
        $paths[] = $p;
    }
    return $paths;
});
