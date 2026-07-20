<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Find where the parent theme builds the "Shop by Collection" #productGrid and
 * what limits it to 12 (posts_per_page / AJAX handler). Read-only.
 * Run: wp eval-file tools/diag-theme-grid.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }
$dir = get_template_directory();
echo "Parent theme dir: {$dir}\n\n";
$needles = array('productGrid','product-container','product-slider','prev-prod','next-prod');
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
$hitFiles = array();
foreach ($rii as $f){
    if ($f->getExtension() !== 'php') continue;
    $txt = @file_get_contents($f->getPathname());
    if ($txt === false) continue;
    foreach ($needles as $n){ if (strpos($txt,$n)!==false){ $hitFiles[$f->getPathname()][]=$n; } }
}
echo "=== files mentioning the grid ===\n";
foreach ($hitFiles as $path=>$ns){ echo "  ".str_replace($dir.'/','',$path)."  (".implode(',',array_unique($ns)).")\n"; }

// In those files, print lines with posts_per_page / WP_Query / wp_ajax / number / limit / category
echo "\n=== query / limit lines in those files ===\n";
foreach (array_keys($hitFiles) as $path){
    $lines = file($path);
    foreach ($lines as $i=>$ln){
        if (preg_match('/posts_per_page|new WP_Query|wp_ajax_|->query|numberposts|\'number\'|"number"|per_page|showposts|product_cat|posts_number|12/', $ln)){
            $t = trim($ln);
            if (strlen($t) > 4) echo "  ".str_replace($dir.'/','',$path).":".($i+1)."  ".substr($t,0,160)."\n";
        }
    }
}
echo "\n=== DONE ===\n";
