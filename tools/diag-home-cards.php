<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Inspect the homepage "Trending Today" and "Corporate Signages" sections:
 * dump the card markup around each heading and report which product ids/slugs
 * appear there and whether they carry a _taf_art_code. Read-only.
 * Run: wp eval-file tools/diag-home-cards.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$r = wp_remote_get(home_url('/?afhc='.time()), array('timeout'=>35,'sslverify'=>false,'headers'=>array('User-Agent'=>'AF-Diag')));
if (is_wp_error($r)) { echo "fetch failed: ".$r->get_error_message()."\n"; exit; }
$b = wp_remote_retrieve_body($r);
echo "home bytes: ".strlen($b)."\n";

foreach (array('Trending','Corporate Signages','Large Format') as $label){
    $p = stripos($b, $label);
    echo "\n=== '{$label}' @ ".var_export($p,true)." ===\n";
    if ($p===false) continue;
    $seg = substr($b, $p, 2600);
    // list card-ish classes and product links in this window
    if (preg_match_all('/class="([^"]*(?:product|trending|card|slide)[^"]*)"/i', $seg, $mc)) {
        $classes = array_slice(array_unique($mc[1]), 0, 12);
        echo "  card classes: \n";
        foreach ($classes as $c) echo "     - ".$c."\n";
    }
    if (preg_match_all('#/product/([^/"?\#]+)#', $seg, $ms)) {
        $slugs = array_slice(array_unique($ms[1]), 0, 8);
        echo "  product slugs: ".implode(', ', $slugs)."\n";
        foreach ($slugs as $slug){
            $post = get_page_by_path($slug, OBJECT, 'product');
            $code = $post ? get_post_meta($post->ID, '_taf_art_code', true) : '';
            echo "     {$slug} => id ".($post?$post->ID:'?')." code '".($code?:'(none)')."'\n";
        }
    }
    if (preg_match_all('/data-product_id="(\d+)"/', $seg, $mp)) {
        echo "  data-product_id: ".implode(', ', array_slice(array_unique($mp[1]),0,8))."\n";
    }
}
echo "\n=== DONE ===\n";
