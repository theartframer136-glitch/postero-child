<?php
/**
 * Locate the Instagram video/feed on the site and what renders it, plus report
 * REST-nonce / cache signals behind "rest_cookie_invalid_nonce". Read-only.
 * Run: wp eval-file tools/diag-instagram.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

echo "=== ACTIVE PLUGINS (flagging instagram/feed/social) ===\n";
$active = (array) get_option('active_plugins', array());
foreach ($active as $p) {
    $flag = preg_match('/insta|feed|social|smash|elfsight|snapwidget|lightwidget|powr|juicer|spotlight/i', $p) ? '  <-- possible IG source' : '';
    echo "  {$p}{$flag}\n";
}

echo "\n=== CONTENT / ELEMENTOR referencing 'instagram' ===\n";
global $wpdb;
$rows = $wpdb->get_results("SELECT ID, post_title, post_type FROM {$wpdb->posts}
    WHERE post_status IN ('publish','draft') AND post_content LIKE '%instagram%' LIMIT 30");
foreach ($rows as $r) echo "  content: [{$r->post_type}] #{$r->ID} {$r->post_title}\n";
$meta = $wpdb->get_results("SELECT post_id, LEFT(meta_value, 0) v FROM {$wpdb->postmeta}
    WHERE meta_key='_elementor_data' AND meta_value LIKE '%instagram%' LIMIT 30");
foreach ($meta as $m) echo "  elementor: #{$m->post_id} " . get_the_title($m->post_id) . "\n";
if (!$rows && !$meta) echo "  (no post content / elementor data mentions instagram)\n";

echo "\n=== HOMEPAGE + a few pages: Instagram render markers ===\n";
$urls = array('HOME'=>home_url('/'));
foreach (array('about','contact','social-media','gallery') as $slug){ $pg=get_page_by_path($slug); if($pg) $urls[strtoupper($slug)]=get_permalink($pg->ID); }
$needles = array('instagram.com/','instagram-media','sb_instagram','cff-','#sb_instagram','elfsight','snapwidget','lightwidget','powr-instagram','data-instgrm','instgrm.Embeds','class="instagram','ig-video','reel');
foreach ($urls as $label=>$url){
    $r = wp_remote_get($url, array('timeout'=>20,'sslverify'=>false,'headers'=>array('User-Agent'=>'AF-Diag')));
    if (is_wp_error($r)) { echo "  {$label}: ERROR ".$r->get_error_message()."\n"; continue; }
    $body = wp_remote_retrieve_body($r);
    echo "  {$label} (".strlen($body)." bytes): ";
    $hits=array();
    foreach ($needles as $n){ $c=substr_count($body,$n); if($c) $hits[]="{$n} x{$c}"; }
    echo ($hits? implode(' | ',$hits) : 'no instagram markers') . "\n";
}

echo "\n=== NONCE / CACHE signals ===\n";
echo "  REST nonce lifetime (default 12-24h; caching can serve stale): " . (defined('DAY_IN_SECONDS')?'':'') . "\n";
echo "  LiteSpeed active: " . (defined('LSCWP_V') ? LSCWP_V : (is_plugin_active('litespeed-cache/litespeed-cache.php')?'yes':'unknown')) . "\n";
if (function_exists('get_option')) {
    $ls_nonce = get_option('litespeed.conf.util-no_cache');
    echo "  (If a plugin feed uses the REST API with a page-cached nonce, purging + nonce ESI resolves rest_cookie_invalid_nonce.)\n";
}
echo "\n=== DONE ===\n";
