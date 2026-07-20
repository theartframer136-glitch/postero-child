<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Find the "Shop by Collection" product widget on the front page and report its
 * widgetType + any query/limit settings (posts_per_page, posts_number, etc.) so
 * we can raise the 12-product cap. Read-only.
 * Run: wp eval-file tools/diag-collection.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$fp = (int) get_option('page_on_front');
echo "Front page ID: {$fp}\n";
$raw = get_post_meta($fp, '_elementor_data', true);
if (empty($raw)) { echo "No _elementor_data on front page\n"; }
else {
  $data = json_decode($raw, true);
  $limitKeys = array('posts_per_page','posts_number','number','per_page','products_count','ppp','limit','product_limit','_skin','number_of_posts');
  $found = 0;
  $walk = function($node) use (&$walk, $limitKeys, &$found){
    if (!is_array($node)) return;
    if (isset($node['widgetType']) || isset($node['elType'])) {
      $wt = isset($node['widgetType']) ? $node['widgetType'] : $node['elType'];
      $s  = isset($node['settings']) && is_array($node['settings']) ? $node['settings'] : array();
      // is it product-ish?
      $blob = strtolower(json_encode($s));
      if (strpos($wt,'product')!==false || strpos($blob,'product_cat')!==false || strpos($blob,'collection')!==false
          || strpos($blob,'posts_per_page')!==false || strpos($blob,'radha')!==false) {
        $rep = array();
        foreach ($limitKeys as $k){ if (isset($s[$k])) $rep[$k] = is_scalar($s[$k]) ? $s[$k] : json_encode($s[$k]); }
        // also capture any query/tax settings
        foreach ($s as $k=>$v){ if (preg_match('/query|tax|cat|term|source|orderby|slider|carousel|column/i',$k) && is_scalar($v)) $rep[$k]=$v; }
        if ($rep || strpos($wt,'product')!==false){
          $found++;
          echo "\n[".$found."] id=".($node['id']??'?')." widgetType={$wt}\n";
          foreach ($rep as $k=>$v){ echo "    {$k} = ".substr((string)$v,0,120)."\n"; }
        }
      }
    }
    if (isset($node['elements']) && is_array($node['elements'])) foreach ($node['elements'] as $c) $walk($c);
  };
  foreach ((array)$data as $sec) $walk($sec);
  if (!$found) echo "No product-ish widget found in Elementor data.\n";
}

// Also: how many published products are actually in Radha Krishna?
$q = new WP_Query(array('post_type'=>'product','post_status'=>'publish','posts_per_page'=>-1,'fields'=>'ids',
  'tax_query'=>array(array('taxonomy'=>'product_cat','field'=>'slug','terms'=>'radha-krishna','include_children'=>true))));
echo "\nRadha Krishna published products: ".$q->found_posts."\n";
echo "=== DONE ===\n";
