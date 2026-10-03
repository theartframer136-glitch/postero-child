<?php
/**
 * Classic Editor -> theme code.
 *
 * The plugin's settings on the live site (read 3 Oct 2026): "Default editor
 * for all users: Classic", "Allow users to switch editors: No". So every post
 * type opens in the classic editor and nobody is offered the block editor.
 * Admin only; the storefront never saw this plugin. Harmless while it is
 * still on: it answers the same.
 */
if (!defined('ABSPATH')) exit;

add_filter('use_block_editor_for_post_type', '__return_false', 100);
add_filter('use_block_editor_for_post', '__return_false', 100);
