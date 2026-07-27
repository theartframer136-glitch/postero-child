<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Art Code audit + CSV export.
 *
 * Reports two problems:
 *   1) products with NO art code
 *   2) DUPLICATE art codes (the same code on several products)
 * and writes a CSV of every product (id, sku, slug, title, current code,
 * status, main image file) so the list can be reconciled against the renamed
 * files on Drive and re-imported with tools/import-artcodes.php.
 * Read-only apart from writing the CSV.
 * Run: wp eval-file tools/report-artcodes.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$up = wp_get_upload_dir();
$dir = $up['basedir'] . '/taf-reports';
if (!is_dir($dir)) wp_mkdir_p($dir);
$csv_path = $dir . '/artcode-audit.csv';
$csv_url  = $up['baseurl'] . '/taf-reports/artcode-audit.csv';

$ids = get_posts(array('post_type'=>'product','post_status'=>'publish','posts_per_page'=>-1,'fields'=>'ids','orderby'=>'title','order'=>'ASC'));

$by_code = array(); $rows = array(); $missing = array();
foreach ($ids as $pid) {
    $code = get_post_meta($pid, '_taf_art_code', true);
    $code = is_string($code) ? trim($code) : '';
    $thumb = get_post_thumbnail_id($pid);
    $file  = $thumb ? basename((string) get_post_meta($thumb, '_wp_attached_file', true)) : '';
    $rows[$pid] = array(
        'id'    => $pid,
        'sku'   => (string) get_post_meta($pid, '_sku', true),
        'slug'  => get_post_field('post_name', $pid),
        'title' => html_entity_decode(wp_strip_all_tags(get_the_title($pid))),
        'code'  => $code,
        'file'  => $file,
    );
    if ($code === '') $missing[] = $pid;
    else { $k = strtoupper(preg_replace('/\s+/', ' ', $code)); $by_code[$k][] = $pid; }
}

$dups = array_filter($by_code, function($v){ return count($v) > 1; });
uasort($dups, function($a,$b){ return count($b) - count($a); });

echo "=== ART CODE AUDIT ===\n";
echo "published products: ".count($ids)."\n";
echo "  with a code:    ".(count($ids) - count($missing))."\n";
echo "  MISSING a code: ".count($missing)."\n";
echo "  distinct codes: ".count($by_code)."\n";
echo "  DUPLICATE codes (same code on >1 product): ".count($dups)."\n";

$dupe_products = 0; foreach ($dups as $v) $dupe_products += count($v);
echo "  products sharing a duplicated code: {$dupe_products}\n";

echo "\n--- worst duplicates (top 25) ---\n";
$i=0;
foreach ($dups as $code => $pids) {
    echo "  '{$code}' used by ".count($pids)." products: ";
    $names = array();
    foreach (array_slice($pids,0,4) as $p) $names[] = '#'.$p;
    echo implode(', ', $names) . (count($pids)>4 ? ', …' : '') . "\n";
    if (++$i >= 25) break;
}

echo "\n--- first 15 products MISSING a code ---\n";
foreach (array_slice($missing,0,15) as $pid) {
    echo "  #{$pid}  {$rows[$pid]['sku']}  {$rows[$pid]['title']}\n";
}

// ── CSV ──
$fh = fopen($csv_path, 'w');
fputcsv($fh, array('product_id','sku','slug','title','current_art_code','status','main_image_file','new_art_code'));
foreach ($rows as $pid => $r) {
    $status = 'ok';
    if ($r['code'] === '') $status = 'MISSING';
    else {
        $k = strtoupper(preg_replace('/\s+/', ' ', $r['code']));
        if (isset($dups[$k])) $status = 'DUPLICATE(x'.count($dups[$k]).')';
    }
    fputcsv($fh, array($r['id'],$r['sku'],$r['slug'],$r['title'],$r['code'],$status,$r['file'],''));
}
fclose($fh);

echo "\n=== CSV WRITTEN ===\n";
echo "  {$csv_url}\n";
echo "  (fill the LAST column 'new_art_code' from the Drive filenames, then run tools/import-artcodes.php)\n";
echo "=== DONE ===\n";
