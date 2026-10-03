<?php
/**
 * Code Snippets -> theme code.
 *
 * The five snippets active on the live site (read 3 Oct 2026), copied
 * verbatim into inc/ports/snippets/: #6 the WhatsApp community banner
 * shortcode, #7 Try On Wall, #8 the homepage floating button, #9 Frame the
 * Moment, #10 the homepage script. Run as the plugin ran them: in the plugin's
 * order (all priority 10, then by id), each in its own scope, "global" ones on
 * every request and "front-end" ones only off wp-admin (is_admin() is also
 * true for admin-ajax, as in the plugin).
 *
 * Only while the plugin is off: while it is on it runs them itself, and a
 * second run would declare their functions twice.
 */
if (!defined('ABSPATH')) exit;
if (defined('CODE_SNIPPETS_FILE') || function_exists('code_snippets') || class_exists('\Code_Snippets\Plugin')) return;

function af_port_run_snippet($file) {
    include $file;   // a scope of its own, as the plugin's eval gave each snippet
}

foreach (array(
    array('snippet-6-community-banner.php', 'global'),
    array('snippet-7-try-on-wall.php',      'global'),
    array('snippet-8-floating-button.php',  'front-end'),
    array('snippet-9-frame-the-moment.php', 'global'),
    array('snippet-10-home-page-js.php',    'front-end'),
) as $af_snip) {
    if ($af_snip[1] === 'front-end' && is_admin()) continue;
    af_port_run_snippet(__DIR__ . '/snippets/' . $af_snip[0]);
}
unset($af_snip);
