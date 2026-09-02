<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Import Art Codes from a CSV so the site matches the renamed files on Drive.
 *
 * Reads:  wp-content/uploads/taf-reports/artcode-import.csv
 * Accepts any of these column headers (case-insensitive), in any order:
 *   product_id | id            → match by product ID
 *   sku                        → match by SKU
 *   slug                       → match by product slug
 *   filename | file | image    → match by the product's image filename
 *   title | name               → match by exact product title (last resort)
 * plus ONE of: new_art_code | art_code | code   → the code to write
 *
 * Rows with an empty code are skipped, so the audit CSV can be re-uploaded
 * with only the blanks filled in. Idempotent: writing the same code twice is
 * a no-op. Reports duplicates that remain after the import.
 * Run: wp eval-file tools/import-artcodes.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$up   = wp_get_upload_dir();
$path = $up['basedir'] . '/taf-reports/artcode-import.csv';
if (!file_exists($path)) {
    echo "No import file yet — upload your filled CSV to:\n  {$path}\n";
    echo "(or wp-content/uploads/taf-reports/artcode-import.csv via FTP / File Manager)\n=== DONE ===\n";
    exit(0);
}

// Build lookup indexes once.
$ids = get_posts(array('post_type'=>'product','post_status'=>'publish','posts_per_page'=>-1,'fields'=>'ids'));
$by_sku=array(); $by_slug=array(); $by_file=array(); $by_title=array();
foreach ($ids as $pid) {
    $sku = strtoupper(trim((string) get_post_meta($pid,'_sku',true)));
    if ($sku !== '') $by_sku[$sku] = $pid;
    $by_slug[strtolower(get_post_field('post_name',$pid))] = $pid;
    $by_title[strtolower(trim(html_entity_decode(wp_strip_all_tags(get_the_title($pid)))))] = $pid;
    $thumb = get_post_thumbnail_id($pid);
    if ($thumb) {
        $f = strtolower(basename((string) get_post_meta($thumb,'_wp_attached_file',true)));
        if ($f !== '') $by_file[$f] = $pid;
        $by_file[preg_replace('/\.[a-z0-9]+$/','',$f)] = $pid;   // also without extension
    }
}

$fh = fopen($path,'r');
$head = fgetcsv($fh);
if (!$head) { echo "empty CSV\n"; exit(1); }
$map = array();
foreach ($head as $i => $h) { $map[strtolower(trim($h))] = $i; }
function col($row,$map,$names){
    foreach ($names as $n) if (isset($map[$n]) && isset($row[$map[$n]])) {
        $v = trim($row[$map[$n]]); if ($v !== '') return $v;
    }
    return '';
}

$set=0; $same=0; $nomatch=0; $nocode=0; $line=1; $applied=array();
while (($row = fgetcsv($fh)) !== false) {
    $line++;
    $code = col($row,$map,array('new_art_code','art_code','code'));
    if ($code === '') { $nocode++; continue; }
    $code = preg_replace('/\s+/', ' ', trim($code));
    // A CSV may still be written in the numbering the book used to print
    // ("HD 15"). The book prints "HD - 0814" now, so put the row into the
    // book's own numbering before it is written; a code naming no page of the
    // book is left exactly as the row spells it.
    if (function_exists('af_artcode_book_code')) {
        $book = af_artcode_book_code($code);
        if ($book !== '') { $code = $book; }
    }

    $pid = 0;
    $v = col($row,$map,array('product_id','id'));            if ($v !== '' && ctype_digit($v)) $pid = (int) $v;
    if (!$pid) { $v = col($row,$map,array('sku'));            if ($v !== '') $pid = $by_sku[strtoupper($v)]   ?? 0; }
    if (!$pid) { $v = col($row,$map,array('slug'));           if ($v !== '') $pid = $by_slug[strtolower($v)]  ?? 0; }
    if (!$pid) { $v = col($row,$map,array('filename','file','image','main_image_file'));
                 if ($v !== '') { $k = strtolower(basename($v));
                                  $pid = $by_file[$k] ?? ($by_file[preg_replace('/\.[a-z0-9]+$/','',$k)] ?? 0); } }
    if (!$pid) { $v = col($row,$map,array('title','name'));   if ($v !== '') $pid = $by_title[strtolower($v)] ?? 0; }

    if (!$pid || get_post_type($pid) !== 'product') { $nomatch++; if ($nomatch<=10) echo "  no match (line {$line}): ".implode(' | ', array_slice($row,0,3))."\n"; continue; }

    $cur = trim((string) get_post_meta($pid,'_taf_art_code',true));
    $cur_as_book = function_exists('af_artcode_book_code') && af_artcode_book_code($cur) !== ''
        ? af_artcode_book_code($cur) : $cur;
    if ($cur_as_book === $code) { $same++; }
    else { update_post_meta($pid,'_taf_art_code',$code); $set++; if ($set<=15) echo "  SET #{$pid} '{$cur}' -> '{$code}'\n"; }
    $applied[$pid] = $code;
}
fclose($fh);

echo "\n=== IMPORT ART CODES ===\n";
echo "updated: {$set}   already correct: {$same}   no code in row: {$nocode}   unmatched rows: {$nomatch}\n";

// Post-import health check
$with=0; $miss=0; $by_code=array();
foreach ($ids as $pid) {
    $c = trim((string) get_post_meta($pid,'_taf_art_code',true));
    if ($c === '') { $miss++; continue; }
    $with++; $by_code[strtoupper($c)][] = $pid;
}
$dups = array_filter($by_code, function($v){ return count($v) > 1; });
echo "now: with code {$with} | missing {$miss} | duplicate codes ".count($dups)."\n";
if ($dups) { $i=0; foreach ($dups as $c=>$p){ echo "  dup '{$c}' x".count($p)."\n"; if(++$i>=10) break; } }
if (function_exists('wc_delete_product_transients')) wc_delete_product_transients();
echo "=== DONE ===\n";
