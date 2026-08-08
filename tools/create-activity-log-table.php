<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Ensure the visitor activity-log table exists. Idempotent — the theme's
 * af_activity_log_ensure_table() is guarded by an option version, so this
 * creates the table on first run and is a no-op afterwards. Makes no other
 * changes.
 *
 * Run: wp eval-file tools/create-activity-log-table.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

if ( ! function_exists( 'af_activity_log_ensure_table' ) ) {
    echo "af_activity_log_ensure_table() not available — theme not active or not deployed yet.\n";
    echo "=== DONE ===\n";
    return;
}

// Force a create/verify even if the option flag was already set, so a manual
// run can repair a dropped table.
delete_option( 'af_activity_log_db_ver' );
af_activity_log_ensure_table();

global $wpdb;
$table  = af_activity_log_table();
$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
echo $exists ? "OK: activity-log table present ({$table})\n" : "ERROR: table {$table} was not created\n";
echo "=== DONE ===\n";
