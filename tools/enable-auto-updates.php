<?php
/**
 * Enable automatic updates for ALL plugins and themes (security hardening).
 * Unpatched plugin CVEs are the most likely re-compromise path for this store,
 * so background auto-updates are turned on for everything.
 *
 * UI-compatible: writes the SAME options the wp-admin "Enable auto-updates"
 * toggles use, so any single plugin can still be opted out by hand later
 * (Plugins screen → "Disable auto-updates") without editing code.
 *
 * Idempotent. Run: wp eval-file tools/enable-auto-updates.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

require_once ABSPATH . 'wp-admin/includes/plugin.php';

// Plugins
$plugins = array_keys( get_plugins() );
update_option( 'auto_update_plugins', array_values( $plugins ) );

// Themes
$themes = array_keys( wp_get_themes() );
update_option( 'auto_update_themes', array_values( $themes ) );

echo 'Auto-updates ENABLED for ' . count( $plugins ) . ' plugins and ' . count( $themes ) . " themes.\n";
echo "Opt a plugin out anytime in wp-admin -> Plugins -> Disable auto-updates.\n";
echo "=== DONE ===\n";
