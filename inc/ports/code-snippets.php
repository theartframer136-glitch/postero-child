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
 *
 * Changed from the plugin's copies: the "send preview" handlers of #7 and #9
 * call af_snip_preview_send() below, and their forms carry the Turnstile box
 * (security audit, 12 Oct).
 */
if (!defined('ABSPATH')) exit;
if (defined('CODE_SNIPPETS_FILE') || function_exists('code_snippets') || class_exists('\Code_Snippets\Plugin')) return;

function af_port_run_snippet($file) {
    include $file;   // a scope of its own, as the plugin's eval gave each snippet
}

/**
 * The one change to snippets #7 and #9 (security audit, 12 Oct): their "send
 * preview" handlers took any visitor's post, wrote whatever it decoded to as a
 * .png in uploads and emailed it with the raw form fields. Both now call this:
 * Cloudflare Turnstile when it is switched on (the forms carry its box),
 * 3 sends an hour per address, the image must really be a PNG or JPEG of
 * sane size, the file gets a name nobody can guess, and the fields are plain
 * text of bounded length. Recipient and subject are the snippet's own.
 */
function af_snip_preview_send($prefix, $subject, $fields) {
    if (function_exists('af_turnstile_require_ajax')) af_turnstile_require_ajax('preview');
    $ip = function_exists('af_visitor_ip') ? af_visitor_ip() : '';
    $rl = 'af_snip_rl_' . md5($prefix . '|' . $ip);
    $n  = (int) get_transient($rl);
    if ($n >= 3) wp_send_json_error(array('message' => 'Too many previews sent — please try again in an hour.'), 429);

    $raw = isset($_POST['preview']) && is_string($_POST['preview']) ? $_POST['preview'] : '';
    if (!preg_match('#^data:image/(png|jpeg);base64,#', $raw, $m)) wp_send_json_error(array('message' => 'No preview image.'), 400);
    $b64 = substr($raw, strlen($m[0]));
    if (strlen($b64) > 11 * 1024 * 1024) wp_send_json_error(array('message' => 'The preview is too large.'), 413);
    $bin = base64_decode($b64, true);
    $info = $bin !== false ? @getimagesizefromstring($bin) : false;
    if (!$info || !in_array($info[2], array(IMAGETYPE_PNG, IMAGETYPE_JPEG), true) || $info[0] < 1 || $info[1] < 1 || $info[0] > 6000 || $info[1] > 6000) {
        wp_send_json_error(array('message' => 'The preview is not an image.'), 400);
    }
    set_transient($rl, $n + 1, HOUR_IN_SECONDS);

    $upload = wp_upload_dir();
    if (!empty($upload['error'])) wp_send_json_error(array('message' => 'Could not save the preview.'), 500);
    $file = trailingslashit($upload['path']) . $prefix . wp_generate_password(20, false, false) . ($info[2] === IMAGETYPE_PNG ? '.png' : '.jpg');
    if (file_put_contents($file, $bin) === false) wp_send_json_error(array('message' => 'Could not save the preview.'), 500);

    $lines = array();
    foreach ($fields as $key => $label) {
        $v = isset($_POST[$key]) && is_string($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
        $lines[] = $label . ': ' . mb_substr($v, 0, 160);
    }
    wp_mail('chandan99832@gmail.com', $subject, implode("\n", $lines), array(), array($file));
    wp_send_json_success();
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
