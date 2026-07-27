<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Find where Art Codes can be recovered from. The source files were renamed on
 * Drive with their art code, so the uploaded image filenames (and/or slug,
 * title, SKU) may still carry it. Compare products that HAVE a code against
 * their filenames to learn the pattern, then show the filenames of products
 * that are MISSING a code so we can extract codes for them. Read-only.
 * Run: wp eval-file tools/diag-artcode-source.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

function af_files_for($pid){
    $out = array();
    $thumb = get_post_thumbnail_id($pid);
    if ($thumb) {
        $f = get_post_meta($thumb, '_wp_attached_file', true);
        if ($f) $out[] = basename($f);
    }
    $gal = get_post_meta($pid, '_product_image_gallery', true);
    if ($gal) {
        foreach (array_slice(array_filter(explode(',', $gal)), 0, 3) as $gid) {
            $f = get_post_meta((int)$gid, '_wp_attached_file', true);
            if ($f) $out[] = basename($f);
        }
    }
    return $out;
}

$ids = get_posts(array('post_type'=>'product','post_status'=>'publish','posts_per_page'=>-1,'fields'=>'ids'));
$with = array(); $without = array();
foreach ($ids as $pid) {
    $c = get_post_meta($pid, '_taf_art_code', true);
    if (is_string($c) && trim($c) !== '') $with[$pid] = trim($c); else $without[] = $pid;
}
echo "=== COVERAGE === total ".count($ids)." | with ".count($with)." | without ".count($without)."\n";

echo "\n=== A) products WITH a code — does the FILENAME contain it? ===\n";
$hit=0; $miss=0; $shown=0;
foreach ($with as $pid => $code) {
    $files = af_files_for($pid);
    $norm  = strtolower(preg_replace('/[^a-z0-9]/i', '', $code));      // "RK 59" -> "rk59"
    $found = false;
    foreach ($files as $f) {
        if ($norm !== '' && strpos(strtolower(preg_replace('/[^a-z0-9]/i','',$f)), $norm) !== false) { $found = true; break; }
    }
    if ($found) $hit++; else $miss++;
    if ($shown < 15) {
        echo "  [".($found?'MATCH':'  no ')."] #{$pid} code='{$code}' sku='".get_post_meta($pid,'_sku',true)."' files=".implode(' | ', $files)."\n";
        $shown++;
    }
}
echo "  --> filename contains the code: {$hit}   does not: {$miss}\n";

echo "\n=== B) products WITHOUT a code — their filenames / slug / sku ===\n";
$shown = 0;
foreach ($without as $pid) {
    if ($shown >= 30) break;
    $p = get_post($pid);
    echo "  #{$pid} sku='".get_post_meta($pid,'_sku',true)."' slug='".$p->post_name."'\n";
    echo "        files=".implode(' | ', af_files_for($pid))."\n";
    $shown++;
}

echo "\n=== C) do any code-like tokens (XX 00) appear in the MISSING products' data? ===\n";
$pat = '/\b([A-Z]{2,3})[\s\-_]?(\d{1,3})\b/';
$found_tokens = array();
foreach ($without as $pid) {
    $hay = get_the_title($pid) . ' ' . get_post($pid)->post_name . ' ' . get_post_meta($pid,'_sku',true) . ' ' . implode(' ', af_files_for($pid));
    if (preg_match_all($pat, strtoupper($hay), $m, PREG_SET_ORDER)) {
        foreach ($m as $mm) {
            $tok = $mm[1].' '.ltrim($mm[2],'0');
            if (!isset($found_tokens[$tok])) $found_tokens[$tok] = 0;
            $found_tokens[$tok]++;
        }
    }
}
arsort($found_tokens);
$i=0; foreach ($found_tokens as $tok=>$n){ echo "  '{$tok}' x{$n}\n"; if(++$i>=25) break; }
if (!$found_tokens) echo "  (no code-like tokens found — codes are NOT recoverable from site data)\n";

echo "\n=== D) prefixes already in use (for continuing the scheme) ===\n";
$pref = array();
foreach ($with as $code) {
    if (preg_match('/^([A-Za-z]+)\s*(\d+)/', $code, $m)) {
        $k = strtoupper($m[1]);
        if (!isset($pref[$k])) $pref[$k] = array('n'=>0,'max'=>0);
        $pref[$k]['n']++;
        $pref[$k]['max'] = max($pref[$k]['max'], (int)$m[2]);
    }
}
foreach ($pref as $k=>$v) echo "  {$k}: {$v['n']} products, highest number {$v['max']}\n";
echo "\n=== DONE ===\n";
