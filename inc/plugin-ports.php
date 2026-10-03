<?php
/**
 * Plugins moved into the theme (owner, 3 Oct 2026: "change wordpress plugin
 * to custom code", the plugins "taking too much time to load", with nothing a
 * visitor sees or does changing).
 *
 * Each file in inc/ports/ does, in the theme, what one plugin did on this
 * site, and stays dormant while that plugin is still active: the plugin and
 * its replacement never both run. Switching a plugin off (switch-plugins.yml,
 * behind a whole-site before/after comparison) hands its work to the file
 * here; switching it back on hands it back. Nothing to reconfigure either way.
 */
if (!defined('ABSPATH')) exit;

foreach (array('code-snippets', 'hfcm', 'classic-editor', 'delivery-date', 'click-to-chat', 'mas-brands') as $af_port) {
    $af_port_file = __DIR__ . '/ports/' . $af_port . '.php';
    if (is_readable($af_port_file)) require_once $af_port_file;
}
unset($af_port, $af_port_file);
