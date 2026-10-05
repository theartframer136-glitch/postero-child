<?php
/**
 * Plugin Name: AF Crawl Guard loader
 * Description: Loads the early bot guard from wp-content/af-crawl-guard.php. Managed by ops/harden-crawl-budget.php — do not edit.
 */
$af = WP_CONTENT_DIR . '/af-crawl-guard.php';
if ( is_file( $af ) ) { require $af; }
