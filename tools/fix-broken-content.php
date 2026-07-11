<?php
/**
 * Remove broken <img> tags from product descriptions so dead images (e.g. the
 * "canvas wall art care instructions" graphic, and the broken image near the
 * Try It On Your Wall block) don't render as broken-image icons.
 *
 * SAFE & ADDITIVE to content integrity: only strips <img> whose src returns a
 * 404/00 (unreachable). Leaves all working images and all text untouched.
 * Idempotent — re-running is harmless.
 *
 * Run: wp eval-file tools/fix-broken-content.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$ids = get_posts(array(
    'post_type'      => 'product',
    'post_status'    => array('publish','draft','pending','private'),
    'posts_per_page' => -1,
    'fields'         => 'ids',
));

echo "=== FIX BROKEN CONTENT IMAGES (" . count($ids) . " products) ===\n";

$cache = array(); // src => bool(ok)
function af_img_ok($src, &$cache) {
    if (isset($cache[$src])) return $cache[$src];
    $ok = true;
    // Only validate http(s) URLs. Protocol-relative -> add https.
    $url = $src;
    if (strpos($url, '//') === 0) $url = 'https:' . $url;
    if (!preg_match('#^https?://#i', $url)) { $cache[$src] = true; return true; } // data:/relative -> leave
    // Local uploads: check the file on disk (fast, reliable).
    $up = wp_get_upload_dir();
    if (!empty($up['baseurl']) && strpos($url, $up['baseurl']) === 0) {
        $path = $up['basedir'] . substr($url, strlen($up['baseurl']));
        $path = strtok($path, '?');
        $ok = @file_exists($path);
        $cache[$src] = $ok; return $ok;
    }
    // Remote: HEAD request via loopback-capable HTTP API.
    $resp = wp_remote_head($url, array('timeout'=>12, 'sslverify'=>false, 'redirection'=>3));
    if (is_wp_error($resp)) {
        $resp = wp_remote_get($url, array('timeout'=>12, 'sslverify'=>false, 'redirection'=>3));
    }
    if (is_wp_error($resp)) { $ok = false; }
    else { $code = (int) wp_remote_retrieve_response_code($resp); $ok = ($code >= 200 && $code < 400); }
    $cache[$src] = $ok;
    return $ok;
}

$changed = 0; $removed = 0;
foreach ($ids as $pid) {
    $content = get_post_field('post_content', $pid);
    if (!$content || stripos($content, '<img') === false) continue;

    $orig = $content;
    // Match each <img ...> tag
    $content = preg_replace_callback('/<img\b[^>]*>/i', function($m) use (&$cache, &$removed) {
        $tag = $m[0];
        if (!preg_match('/\bsrc\s*=\s*["\']([^"\']+)["\']/i', $tag, $s)) return $tag;
        $src = trim($s[1]);
        if ($src === '') { $removed++; return ''; }
        if (af_img_ok($src, $cache)) return $tag;
        $removed++;
        return ''; // strip broken image
    }, $content);

    // Clean up now-empty wrappers left behind (figure / anchor / p around removed img)
    $content = preg_replace('#<figure[^>]*>\s*(<figcaption[^>]*>.*?</figcaption>)?\s*</figure>#is', '', $content);
    $content = preg_replace('#<a[^>]*>\s*</a>#is', '', $content);
    $content = preg_replace('#<p[^>]*>\s*</p>#is', '', $content);

    if ($content !== $orig) {
        wp_update_post(array('ID'=>$pid, 'post_content'=>$content));
        clean_post_cache($pid);
        $changed++;
        echo "  #{$pid} " . get_the_title($pid) . " — cleaned\n";
    }
}

echo "\nProducts changed: {$changed}, broken images removed: {$removed}\n=== DONE ===\n";
