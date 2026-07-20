<?php
/**
 * EMERGENCY RECOVERY (added 2026-07-20)
 * 1. Clears a stuck WordPress maintenance-mode flag (.maintenance in the WP
 *    root) that takes the whole site down with a 503 "scheduled maintenance".
 * 2. Disables the blanket plugin/theme auto-updates that triggered it, so
 *    wp-cron won't re-enter maintenance mode. Updates go back to manual.
 *
 * Deletes NO content — only the temporary lock file + two option flags.
 * Idempotent. Run: wp eval-file tools/recover-maintenance.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

// 1. Remove the stuck maintenance flag.
$mfile = rtrim( ABSPATH, '/\\' ) . '/.maintenance';
if ( file_exists( $mfile ) ) {
    @unlink( $mfile );
    echo file_exists( $mfile ) ? "WARNING: could not remove {$mfile}\n" : "Removed stuck .maintenance flag — site restored.\n";
} else {
    echo "No .maintenance flag present (site already up).\n";
}

// 2. Turn OFF blanket auto-updates (root cause). Empty lists = nothing
//    auto-updates; you patch plugins manually after a backup.
update_option( 'auto_update_plugins', array() );
update_option( 'auto_update_themes',  array() );
echo "Blanket plugin/theme auto-updates DISABLED.\n";

echo "=== RECOVERY DONE ===\n";
