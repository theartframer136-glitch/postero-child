<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * URGENT diagnostic: is the shop actually visible to the public, and are
 * product pages erroring? A deploy log showed feed URLs answering with a
 * "Coming Soon" page and 23 of 55 warmed product pages returning HTTP 500 —
 * both of which a status-code-only check would miss. Read-only.
 * Run: wp eval-file tools/diag-site-visibility.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }

echo "=== SITE VISIBILITY ===\n";
foreach (array(
    'woocommerce_coming_soon'          => 'WooCommerce coming-soon mode',
    'woocommerce_store_pages_only'     => '…store pages only',
    'woocommerce_private_link'         => '…shareable private link',
    'blog_public'                      => 'search-engine visibility (0 = discouraged)',
) as $opt => $label) {
    printf("  %-42s %s\n", $label, var_export(get_option($opt), true));
}
$maint = file_exists(ABSPATH . '.maintenance') ? 'PRESENT <<<' : 'absent';
printf("  %-42s %s\n", '.maintenance flag', $maint);

echo "\n=== WHAT AN ANONYMOUS VISITOR RECEIVES ===\n";
$urls = array('home' => home_url('/'), 'shop' => home_url('/shop/'));
$prod = get_posts(array('post_type'=>'product','post_status'=>'publish','posts_per_page'=>6,'fields'=>'ids','orderby'=>'modified','order'=>'DESC'));
foreach ($prod as $i => $pid) $urls['product ' . ($i+1)] = get_permalink($pid);

foreach ($urls as $label => $url) {
    $r = wp_remote_get(add_query_arg('nocache', time(), $url),
        array('timeout'=>45,'sslverify'=>false,'headers'=>array('User-Agent'=>'Mozilla/5.0 AF-Diag')));
    if (is_wp_error($r)) { printf("  %-12s FETCH FAILED %s\n", $label, $r->get_error_message()); continue; }
    $code = (int) wp_remote_retrieve_response_code($r);
    $body = wp_remote_retrieve_body($r);
    $flags = array();
    if (stripos($body, 'coming soon') !== false)        $flags[] = 'COMING SOON PAGE <<<';
    if (stripos($body, 'Fatal error') !== false)        $flags[] = 'PHP FATAL <<<';
    if (stripos($body, 'critical error') !== false)     $flags[] = 'WP CRITICAL <<<';
    if ($code >= 500)                                   $flags[] = 'SERVER ERROR <<<';
    if (!$flags && stripos($body, 'add-to-cart') === false && strpos($label, 'product') === 0) $flags[] = 'no add-to-cart on a product page';
    printf("  %-12s HTTP %-4d %6s KB  %s\n", $label, $code, number_format(strlen($body)/1024), $flags ? implode(' | ', $flags) : 'looks normal');
}

echo "\n=== RECENT PHP ERRORS ===\n";
foreach (array(WP_CONTENT_DIR . '/debug.log', ABSPATH . 'error_log', ABSPATH . 'wp-content/error_log') as $log) {
    if (!file_exists($log) || !is_readable($log)) continue;
    $lines = @file($log);
    if (!$lines) continue;
    echo "  {$log} (last 12 lines):\n";
    foreach (array_slice($lines, -12) as $l) echo '    ' . rtrim($l) . "\n";
}
echo "\n=== DONE ===\n";
