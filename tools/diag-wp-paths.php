<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/** Read-only. Where this WordPress loads plugins, must-use plugins and drop-ins from. */
if (!defined('ABSPATH')) exit(1);
foreach (array('ABSPATH', 'WP_CONTENT_DIR', 'WP_PLUGIN_DIR', 'WPMU_PLUGIN_DIR', 'WP_LANG_DIR') as $c) echo "$c = " . (defined($c) ? constant($c) : '-') . "\n";
echo 'mu-plugins loaded: ' . implode(', ', array_map('basename', wp_get_mu_plugins())) . "\n";
echo 'mu dir writable: ' . (is_writable(WPMU_PLUGIN_DIR) ? 'yes' : 'no') . "\n";
echo 'public_html/wp-content/mu-plugins exists: ' . (is_dir(ABSPATH . 'wp-content/mu-plugins') ? 'yes: ' . implode(',', array_diff(scandir(ABSPATH . 'wp-content/mu-plugins'), array('.', '..'))) : 'no') . "\n";
