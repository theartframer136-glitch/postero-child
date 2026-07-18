<?php
/**
 * Dump the exact markup of a "New Arrivals" product card (image area) from the
 * homepage so we can target the main + gallery image containers precisely.
 * Read-only. Run: wp eval-file tools/diag-newarrivals.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$body = '';
$r = wp_remote_get(home_url('/'), array('timeout'=>25,'sslverify'=>false,'headers'=>array('User-Agent'=>'AF-Diag')));
if (is_wp_error($r)) { echo "fetch failed: ".$r->get_error_message()."\n"; exit; }
$body = wp_remote_retrieve_body($r);
echo "HOME bytes: ".strlen($body)."\n\n";

// locate "New Arrivals"
$pos = stripos($body, 'New Arrivals');
if ($pos === false) { echo "'New Arrivals' not found in HTML\n"; }
else {
  // take a window after the heading, find the first product card <li>/<div ... class contains product
  $win = substr($body, $pos, 9000);
  // find first element that starts a product card
  if (preg_match('/<(li|div|article)[^>]*class="[^"]*\bproduct[^"]*"[^>]*>/i', $win, $m, PREG_OFFSET_CAPTURE)) {
    $start = $m[0][1];
    $card = substr($win, $start, 2600);
  } else {
    // fallback: first <img after heading, dump surrounding
    $ip = stripos($win, '<img');
    $card = $ip!==false ? substr($win, max(0,$ip-600), 2200) : substr($win,0,2000);
  }
  // collapse whitespace, show
  $card = preg_replace('/\s+/', ' ', $card);
  echo "=== New Arrivals FIRST CARD markup (image area) ===\n";
  echo $card . "\n\n";

  // also list every <img ...> tag in the window with its classes + parent hints
  echo "=== all <img> tags in New Arrivals window ===\n";
  if (preg_match_all('/<img\b[^>]*>/i', $win, $imgs)) {
    foreach (array_slice($imgs[0],0,8) as $t){ echo "  ".preg_replace('/\s+/',' ', $t)."\n"; }
  }
}
echo "\n=== DONE ===\n";
