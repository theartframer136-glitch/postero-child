<?php
/**
 * Capture (a) the Quick View modal wrapper markup present on the homepage and
 * (b) the structure of a related-products loop card on a product page (the same
 * markup the modal injects), so we can scope a precise CSS fix for the grey-gap
 * / stuck-overlay problem inside the modal.
 * Run: wp eval-file tools/diag-quickview.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

function af_fetch($url){
    $r = wp_remote_get($url, array('timeout'=>25,'sslverify'=>false,'headers'=>array('User-Agent'=>'AF-Diag')));
    if (is_wp_error($r)) return null;
    return wp_remote_retrieve_body($r);
}

// 1. Homepage — look for quick-view modal wrapper containers
$home = af_fetch(home_url('/'));
echo "=== HOMEPAGE quick-view containers ===\n";
if ($home) {
    $hints = array('quick','qv','modal','popup','mfp','lightbox','pswp');
    if (preg_match_all('/<(div|section|aside)[^>]*(id|class)="([^"]*)"/i', $home, $m)) {
        $seen = array();
        foreach ($m[3] as $cls) {
            foreach ($hints as $h) {
                if (stripos($cls, $h) !== false) {
                    $key = strtolower($cls);
                    if (!isset($seen[$key])) { $seen[$key]=1; echo "  ".$cls."\n"; }
                }
            }
        }
    }
} else { echo "  (homepage fetch failed)\n"; }

// 2. Product page — dump one related-products li.product inner HTML
$pid = get_posts(array('post_type'=>'product','post_status'=>'publish','posts_per_page'=>1,'fields'=>'ids'));
$pid = $pid ? $pid[0] : 0;
$purl = $pid ? get_permalink($pid) : '';
echo "\n=== PRODUCT PAGE related-card markup ({$purl}) ===\n";
$body = $purl ? af_fetch($purl) : '';
if ($body) {
    // find the related/upsell products section, then first li.product
    if (preg_match('/<(section|div)[^>]*class="[^"]*\b(related|up-sells|upsells)\b[^"]*"[\s\S]*?<li[^>]*class="[^"]*\bproduct\b[^"]*"[\s\S]*?<\/li>/i', $body, $mm)) {
        $card = $mm[0];
    } elseif (preg_match('/<li[^>]*class="[^"]*\bproduct\b[^"]*"[\s\S]*?<\/li>/i', $body, $mm)) {
        $card = $mm[0];
    } else { $card = '(no li.product found)'; }
    // Trim to ~1600 chars and strip runs of whitespace
    $card = preg_replace('/\s+/', ' ', $card);
    echo substr($card, 0, 1800) . "\n";
} else { echo "  (product fetch failed)\n"; }
echo "\n=== DONE ===\n";
