<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Remove duplicate product_cat terms that share the same name (e.g. two
 * "Certificates & Documents"). Keeps the term with the most products; the
 * empty/smaller duplicate is deleted (its products, if any, are first moved to
 * the kept term so nothing is lost). Idempotent.
 * Run: wp eval-file tools/dedupe-categories.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$terms = get_terms(array('taxonomy'=>'product_cat','hide_empty'=>false));
if (is_wp_error($terms)) { echo "error\n"; exit; }

$by_name = array();
foreach ($terms as $t) { $by_name[strtolower(trim($t->name))][] = $t; }

$deleted = 0;
foreach ($by_name as $name => $group) {
    if (count($group) < 2) continue;
    // keep the one with the highest count
    usort($group, function($a,$b){ return $b->count - $a->count; });
    $keep = array_shift($group);
    echo "DUPLICATE '{$keep->name}': keeping #{$keep->term_id} (count={$keep->count})\n";
    foreach ($group as $dup) {
        // move any products from dup -> keep
        $ids = get_posts(array('post_type'=>'product','post_status'=>'any','posts_per_page'=>-1,'fields'=>'ids',
            'tax_query'=>array(array('taxonomy'=>'product_cat','field'=>'term_id','terms'=>$dup->term_id))));
        foreach ($ids as $pid) { wp_set_object_terms($pid, array((int)$keep->term_id), 'product_cat', true); }
        wp_delete_term($dup->term_id, 'product_cat');
        $deleted++;
        echo "   deleted duplicate #{$dup->term_id} (moved ".count($ids)." product(s) to keep)\n";
    }
}
echo "\nDuplicates removed: {$deleted}\n=== DONE ===\n";
