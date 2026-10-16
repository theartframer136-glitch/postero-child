<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * READ-ONLY. Where exactly do the rendered Shipping and Cart pages still say
 * "free shipping/delivery"? Prints every match with context and whether it sits
 * inside a <script> block, so the source (theme JS literal, plugin string,
 * page content) can be told apart.
 *
 *   wp eval-file wp-content/themes/postero-child/tools/diag-free-shipping-rendered.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
$pages = array('shipping' => home_url('/shipping-delivery/'), 'cart' => home_url('/cart/'), 'home' => home_url('/'), 'contact' => home_url('/contact/'), 'legal' => home_url('/legal-imprint/'), 'customize' => home_url('/customize-your-picture/'));
foreach ($pages as $label => $url) {
    $r = wp_remote_get($url, array('timeout' => 40, 'sslverify' => false, 'headers' => array('User-Agent' => 'af-diag/1.0')));
    if (is_wp_error($r)) { echo "=== $label: ERROR " . $r->get_error_message() . "\n"; continue; }
    $b = (string) wp_remote_retrieve_body($r);
    echo "=== $label  HTTP " . wp_remote_retrieve_response_code($r) . "  " . strlen($b) . " bytes\n";
    // script ranges
    $scripts = array();
    if (preg_match_all('#<script\b[^>]*>.*?</script>#is', $b, $sm, PREG_OFFSET_CAPTURE)) foreach ($sm[0] as $s) $scripts[] = array($s[1], $s[1] + strlen($s[0]));
    $n = preg_match_all('/free[\s\-_]*(usa?[\s\-_]+)?(shipping|delivery|ship)/i', $b, $mm, PREG_OFFSET_CAPTURE);
    if (!$n) { echo "  clean\n\n"; continue; }
    foreach ($mm[0] as $m) {
        $pos = $m[1]; $in = 'html';
        foreach ($scripts as $s) if ($pos >= $s[0] && $pos < $s[1]) { $in = 'SCRIPT'; break; }
        $ctx = substr($b, max(0, $pos - 110), 260);
        echo "  [$in] …" . preg_replace('/\s+/', ' ', $ctx) . "…\n";
    }
    echo "\n";
}
echo "=== DONE ===\n";
