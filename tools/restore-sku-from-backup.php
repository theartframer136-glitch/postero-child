<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Undo tools/sku-to-artcode.php.
 *
 * Every product it changed kept its previous SKU in _af_sku_before_artcode.
 * This puts them all back and clears the done-markers, so the shop is exactly
 * where it was before the art codes were applied. Nothing here guesses: a
 * product with no backup value is left untouched and reported.
 *
 * Run: wp eval-file tools/restore-sku-from-backup.php --allow-root
 * Env: AF_SKU_DRYRUN=1 — report what would be restored, write nothing
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$DRY = (bool) getenv( 'AF_SKU_DRYRUN' );

global $wpdb;
$pids = $wpdb->get_col(
	"SELECT post_id FROM {$wpdb->postmeta}
	 WHERE meta_key = '_af_sku_before_artcode' AND meta_value <> ''
	 ORDER BY post_id ASC"
);

echo "=== RESTORE SKUs ===\n";
echo "products with a saved SKU: " . count( $pids ) . ( $DRY ? '  |  DRY RUN' : '' ) . "\n";

$done = 0; $failed = 0; $samples = array();

foreach ( $pids as $pid ) {
	$old = (string) get_post_meta( $pid, '_af_sku_before_artcode', true );
	if ( $old === '' ) { continue; }
	$now = (string) get_post_meta( $pid, '_sku', true );
	if ( $now === $old ) { continue; }

	if ( count( $samples ) < 8 ) { $samples[] = "  #{$pid}  {$now}  →  {$old}"; }
	if ( $DRY ) { $done++; continue; }

	$product = wc_get_product( $pid );
	if ( ! $product ) { $failed++; continue; }
	try {
		$product->set_sku( $old );
		$product->save();
	} catch ( Exception $e ) {
		$failed++;
		echo "  FAILED #{$pid} → {$old}: " . $e->getMessage() . "\n";
		continue;
	}
	delete_post_meta( $pid, '_af_sku_artcode' );
	if ( function_exists( 'wc_delete_product_transients' ) ) { wc_delete_product_transients( $pid ); }
	$done++;
}

echo "restored {$done}  |  failed {$failed}\n";
if ( $samples ) { echo implode( "\n", $samples ) . "\n"; }
