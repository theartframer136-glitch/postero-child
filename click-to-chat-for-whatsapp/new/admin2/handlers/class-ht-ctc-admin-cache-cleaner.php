<?php
/**
 * Admin2 cache cleaner.
 *
 * After CTC settings are saved (signaled by update_option_ht_ctc_admin_pages),
 * flush caches owned by any popular caching/performance plugin that may be
 * holding stale front-end HTML containing the chat widget.
 *
 * Split out of HT_CTC_Admin_Core_Hooks so adding/removing a cache plugin
 * integration only touches one file.
 *
 * @package Click_To_Chat
 * @subpackage Admin2
 * @since 5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'HT_CTC_Admin_Cache_Cleaner' ) ) {

	/**
	 * Cache plugin integration.
	 */
	class HT_CTC_Admin_Cache_Cleaner {

		/**
		 * Constructor: hook the cache flush.
		 *
		 * The following hooks were inherited from the legacy Admin 2019 architecture.
		 * In Admin 2019, these pages had isolated save flows that did not trigger the global 'ht_ctc_admin_pages' update.
		 * In Admin 2026, the REST API centralizes saving and always triggers 'after_save_settings'
		 * via 'ht_ctc_ah_admin_after_save_settings', which updates 'ht_ctc_admin_pages'.
		 * Hooking only 'update_option_ht_ctc_admin_pages' here avoids multiple cache flushes per save.
		 */
		public function __construct() {
			add_action( 'update_option_ht_ctc_admin_pages', array( $this, 'clear_cache' ) );

			// // Legacy redundant hooks (Admin 2019). Kept commented for history.
			// add_action( 'update_option_ht_ctc_cs_options', array( $this, 'clear_cache' ) );
			// add_action( 'update_option_ht_ctc_greetings_settings', array( $this, 'clear_cache' ) );
		}

		/**
		 * Attempt to clear caches from popular caching/performance plugins.
		 *
		 * @return void
		 */
		public function clear_cache() {

			// WP Super Cache.
			if ( function_exists( 'wp_cache_clear_cache' ) ) {
				wp_cache_clear_cache();
			}

			// W3 Total Cache.
			if ( function_exists( 'w3tc_pgcache_flush' ) ) {
				w3tc_pgcache_flush();
			}

			// WP Fastest Cache.
			if ( function_exists( 'wpfc_clear_all_cache' ) ) {
				wpfc_clear_all_cache();
			}

			// Autoptimize.
			if ( class_exists( 'autoptimizeCache' ) && method_exists( 'autoptimizeCache', 'clearall' ) ) {
				autoptimizeCache::clearall();
			}

			// WP Rocket.
			if ( function_exists( 'rocket_clean_domain' ) ) {
				rocket_clean_domain();
			}

			// WPEngine.
			if ( class_exists( 'WpeCommon' ) ) {
				if ( method_exists( 'WpeCommon', 'purge_memcached' ) ) {
					WpeCommon::purge_memcached();
				}
				if ( method_exists( 'WpeCommon', 'purge_varnish_cache' ) ) {
					WpeCommon::purge_varnish_cache();
				}
			}

			// SG Optimizer by SiteGround.
			if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
				sg_cachepress_purge_cache();
			}

			// LiteSpeed Cache.
			if ( class_exists( 'LiteSpeed_Cache_API' ) && method_exists( 'LiteSpeed_Cache_API', 'purge_all' ) ) {
				LiteSpeed_Cache_API::purge_all();
			}

			// Cache Enabler.
			if ( class_exists( 'Cache_Enabler' ) && method_exists( 'Cache_Enabler', 'clear_total_cache' ) ) {
				Cache_Enabler::clear_total_cache();
			}

			// Comet Cache.
			if ( class_exists( 'comet_cache' ) && method_exists( 'comet_cache', 'clear' ) ) {
				comet_cache::clear();
			}

			// Hummingbird.
			if ( class_exists( '\Hummingbird\WP_Hummingbird' ) && method_exists( '\Hummingbird\WP_Hummingbird', 'flush_cache' ) ) {
				\Hummingbird\WP_Hummingbird::flush_cache();
			}

			// WP-Optimize.
			if ( function_exists( 'wpo_cache_flush' ) ) {
				wpo_cache_flush();
			}

			// WordPress object cache flush.
			//
			// todo: verify whether this call is actually necessary and remove it if not.
			//
			// Reasoning to remove it:
			// - update_option() already invalidates the cache entry for the updated option
			// via wp_cache_delete()/wp_cache_set() in the 'options' / 'alloptions' groups,
			// so option reads after a save will see the new value without a global flush.
			// - The page/CDN cache plugins handled above (WP Super Cache, W3TC, WP Rocket,
			// LiteSpeed, etc.) take care of the user-facing HTML cache that actually
			// contains the rendered widget.
			// - wp_cache_flush() wipes the ENTIRE object cache (Redis / Memcached). On a
			// busy site this can cause a cache stampede / temporary 503s as every page
			// refills its cached queries simultaneously. This handler fires after EVERY
			// settings save (via update_option_ht_ctc_admin_pages → clear_cache).
			//
			// Reasons it might still be needed (verify before removing):
			// - Some object-cache implementations key the chat widget HTML or computed
			// option bundles outside the 'options' group; a targeted invalidation may
			// not catch them.
			// - Custom hosts / drop-ins with non-standard caching semantics.
			//
			// Action item: confirm on staging that removing this does not leave stale widget
			// output, then drop it (or replace with targeted wp_cache_delete() calls).
			// wp_cache_flush() commented out to prevent 503 errors/cache stampedes on busy sites.
			// if ( function_exists( 'wp_cache_flush' ) ) {
			// wp_cache_flush();
			// }
		}
	}
} // END class_exists check
