<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. After the header/slider trim: each page's HTML and header size,
 * whether Slider Revolution's files are on it, and which header widgets the
 * server left out. Fetched as a guest with the page cache bypassed.
 */
if (!defined('ABSPATH')) exit;
$paths = array('/cart/', '/shop/', '/', get_permalink(7676) ? wp_make_link_relative(get_permalink(7676)) : '/?p=7676', '/product-category/digital-canvas-prints/radha-krishna/');
foreach ($paths as $p) {
    $t = microtime(true);
    $r = wp_remote_get(home_url($p) . (strpos($p, '?') === false ? '?' : '&') . 'aftrim=' . mt_rand(), array('timeout' => 40, 'sslverify' => false, 'user-agent' => 'Mozilla/5.0 AF-trim-check'));
    $h = is_wp_error($r) ? '' : wp_remote_retrieve_body($r);
    $hd = preg_match('~<header id="masthead".*?</header>~s', $h, $m) ? strlen($m[0]) : 0;
    $note = preg_match('~af-header-weight: not sent, hidden at every width: ([^-]*)-->~', $h, $n) ? trim($n[1]) : '(none)';
    printf("%-58s %3d | page %4d KB, header %3d KB | sr7css %s, sr7.js %s, tptools %s | %4.0f ms\n   left out: %s\n",
        mb_strimwidth($p, 0, 58), is_wp_error($r) ? 0 : wp_remote_retrieve_response_code($r), strlen($h) / 1024, $hd / 1024,
        strpos($h, "id='sr7css-css'") !== false ? 'YES' : 'no', strpos($h, 'id="sr7-js"') !== false ? 'YES' : 'no', strpos($h, 'id="tp-tools-js"') !== false ? 'YES' : 'no',
        (microtime(true) - $t) * 1000, $note);
}
