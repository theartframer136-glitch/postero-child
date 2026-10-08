<?php
/**
 * Settings → Cloudflare Turnstile: where the owner pastes the two keys from
 * the Cloudflare dashboard and switches the check on or off (inc/turnstile.php
 * does the checking). The GitHub route, .github/workflows/turnstile.yml, writes
 * the same three options; whichever was used last wins.
 *
 * Switching on is refused until Cloudflare has accepted the secret key (asked
 * with a dummy token: a good secret refuses the token, a wrong one is refused
 * itself), so a mistyped secret can never leave the forms unprotected without
 * anyone knowing. A change of state clears the page cache: every cached page
 * carries the header's login popup, which must not meet the new check without
 * its box; the entrance pages are requested again straight away so they are
 * not left cold.
 */
if (!defined('ABSPATH')) exit;

function af_turnstile_key_ok($v) {
    return (bool) preg_match('/^[A-Za-z0-9_-]{20,120}$/', (string) $v);
}

// Does Cloudflare accept this secret key? Nothing of the site is changed.
function af_turnstile_check_secret($secret) {
    $r = wp_remote_post('https://challenges.cloudflare.com/turnstile/v0/siteverify', array(
        'timeout' => 10,
        'body'    => array('secret' => (string) $secret, 'response' => 'XXXX.DUMMY.TOKEN.XXXX'),
    ));
    if (is_wp_error($r)) {
        return array('ok' => false, 'why' => 'Cloudflare could not be reached from the server (' . $r->get_error_message() . '). Nothing was changed; please try again in a minute.');
    }
    $j = json_decode((string) wp_remote_retrieve_body($r), true);
    $codes = isset($j['error-codes']) && is_array($j['error-codes']) ? array_map('strval', $j['error-codes']) : array();
    if (array_intersect($codes, array('invalid-input-secret', 'missing-input-secret'))) {
        return array('ok' => false, 'why' => 'Cloudflare does not accept this Secret Key. In the Cloudflare dashboard open the Turnstile widget and copy the <strong>Secret Key</strong> again (not the Site Key).');
    }
    if (in_array('invalid-input-response', $codes, true) || (is_array($j) && !empty($j['success']) && af_turnstile_is_test_secret($secret))) {
        return array('ok' => true, 'why' => '');
    }
    return array('ok' => false, 'why' => 'Unexpected answer from Cloudflare: ' . esc_html(substr((string) wp_json_encode($j), 0, 200)) . '. Nothing was changed.');
}

// The page cache cleared, and the entrances requested again right away.
function af_turnstile_purge_and_warm() {
    do_action('litespeed_purge_all');
    wp_cache_flush();
    update_option('af_warm_cursor', 0, false);
    $urls = array(home_url('/'), home_url('/login/'), home_url('/sign-up/'));
    if (function_exists('wc_get_page_permalink')) {
        $shop = wc_get_page_permalink('shop');
        if (is_string($shop) && $shop !== '') $urls[] = $shop;
    }
    foreach (array_unique($urls) as $u) {
        if (function_exists('af_warm_request')) af_warm_request($u);
        else wp_remote_get($u, array('timeout' => 5, 'blocking' => false, 'sslverify' => false, 'redirection' => 0));
    }
}

/**
 * The form's save. Returns array('ok' => bool, 'messages' => string[]).
 * With any problem nothing is written and the check is left as it was.
 */
function af_turnstile_admin_save($post) {
    $k = af_turnstile_keys();
    $site    = isset($post['af_ts_site']) ? trim(sanitize_text_field(wp_unslash($post['af_ts_site']))) : '';
    $secret  = isset($post['af_ts_secret']) ? trim(sanitize_text_field(wp_unslash($post['af_ts_secret']))) : '';
    $want_on = !empty($post['af_ts_on']);
    if ($secret === '') $secret = $k['secret']; // a blank field keeps the saved secret

    $errors = array();
    if ($site !== '' && !af_turnstile_key_ok($site)) $errors[] = 'The Site Key does not look like a Turnstile key (letters, digits, - and _ only; a space may have been copied with it).';
    if ($secret !== '' && !af_turnstile_key_ok($secret)) $errors[] = 'The Secret Key does not look like a Turnstile key (letters, digits, - and _ only; a space may have been copied with it).';
    if ($site !== '' && $site === $secret) $errors[] = 'The Site Key and the Secret Key are the same value: one of them was pasted twice.';
    if ($want_on) {
        if ($site === '' || $secret === '') {
            $errors[] = 'Both keys are needed before the check can be switched on.';
        } elseif (!$errors) {
            $c = af_turnstile_check_secret($secret);
            if (!$c['ok']) $errors[] = $c['why'];
        }
    }
    if ($errors) return array('ok' => false, 'messages' => $errors);

    $was = af_turnstile_active();
    update_option('af_turnstile_site_key', $site, true);
    update_option('af_turnstile_secret_key', $secret, false);
    update_option('af_turnstile_mode', $want_on ? 'on' : 'off', true);
    delete_transient('af_turnstile_last_error');
    $now = af_turnstile_active();
    $msgs = array($now ? 'Saved. The security check is <strong>on</strong> for every login, sign-up and password-reset form.' : 'Saved. The security check is <strong>off</strong>.');
    if ($was !== $now) {
        af_turnstile_purge_and_warm();
        $msgs[] = 'The page cache was cleared so every page shows the change at once.';
    }
    return array('ok' => true, 'messages' => $msgs);
}

add_action('admin_menu', function () {
    add_options_page('Cloudflare Turnstile', 'Cloudflare Turnstile', 'manage_options', 'af-turnstile', 'af_turnstile_admin_page');
});

function af_turnstile_admin_page() {
    if (!current_user_can('manage_options')) return;
    $result = null;
    if (isset($_POST['af_ts_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['af_ts_nonce'])), 'af_ts_save')) {
        $result = af_turnstile_admin_save($_POST);
    }
    $k      = af_turnstile_keys();
    $active = af_turnstile_active();
    $mode   = get_option('af_turnstile_mode', 'off');
    $last   = get_transient('af_turnstile_last_error');
    $site_field = ($result && !$result['ok'] && isset($_POST['af_ts_site'])) ? sanitize_text_field(wp_unslash($_POST['af_ts_site'])) : $k['site'];
    $cf_link = 'https://dash.cloudflare.com/?to=/:account/turnstile';
    ?>
    <div class="wrap">
      <h1>Cloudflare Turnstile</h1>
      <p>The "Verify you are human" check from Cloudflare, on every login, sign-up and password-reset form:
         the Login and Sign-up pages, My Account, the checkout's returning-customer login, the header's login popup and wp-login.php.</p>
      <?php if ($result) : ?>
        <div class="notice <?php echo $result['ok'] ? 'notice-success' : 'notice-error'; ?>"><?php foreach ($result['messages'] as $m) echo '<p>' . wp_kses($m, array('strong' => array())) . '</p>'; ?></div>
      <?php endif; ?>

      <h2 class="title">Status</h2>
      <table class="form-table" role="presentation">
        <tr><th scope="row">The check</th><td>
          <?php if ($active) : ?><strong style="color:#00a32a">On</strong> — visitors see the Cloudflare box on every form.
          <?php elseif (defined('AF_TURNSTILE_OFF') && AF_TURNSTILE_OFF) : ?><strong>Off</strong> — AF_TURNSTILE_OFF is set in wp-config.php; remove it to switch on.
          <?php elseif ($mode === 'on') : ?><strong>Off</strong> — switched on, but a key is missing.
          <?php else : ?><strong>Off</strong> — the forms work as they always did.<?php endif; ?>
        </td></tr>
        <tr><th scope="row">Site Key</th><td><?php echo $k['site'] !== '' ? 'saved (' . strlen($k['site']) . ' characters)' : 'not saved'; ?></td></tr>
        <tr><th scope="row">Secret Key</th><td><?php echo $k['secret'] !== '' ? 'saved (' . strlen($k['secret']) . ' characters; never shown)' : 'not saved'; ?></td></tr>
        <?php if (is_array($last)) : ?>
        <tr><th scope="row">Last problem</th><td><?php echo esc_html($last['when'] . ': ' . $last['kind'] . ' — ' . $last['detail']); ?><br><em>The form was let through that time; the problem is on Cloudflare's side or in the keys, never a visitor's.</em></td></tr>
        <?php endif; ?>
      </table>

      <h2 class="title">Keys</h2>
      <p>From the Cloudflare dashboard → <a href="<?php echo esc_url($cf_link); ?>" target="_blank" rel="noopener">Turnstile</a> → <strong>Add widget</strong>
         (hostnames <code><?php echo esc_html(wp_parse_url(home_url('/'), PHP_URL_HOST)); ?></code> and its www form, widget mode <strong>Managed</strong>) → copy the two keys here.</p>
      <form method="post">
        <?php wp_nonce_field('af_ts_save', 'af_ts_nonce'); ?>
        <table class="form-table" role="presentation">
          <tr><th scope="row"><label for="af_ts_site">Site Key</label></th>
              <td><input type="text" id="af_ts_site" name="af_ts_site" class="regular-text code" value="<?php echo esc_attr($site_field); ?>" autocomplete="off" spellcheck="false" placeholder="0x4AAAAAAA…"></td></tr>
          <tr><th scope="row"><label for="af_ts_secret">Secret Key</label></th>
              <td><input type="password" id="af_ts_secret" name="af_ts_secret" class="regular-text code" value="" autocomplete="new-password" spellcheck="false" placeholder="<?php echo $k['secret'] !== '' ? 'leave blank to keep the saved key' : '0x4AAAAAAA…'; ?>">
                  <p class="description">Checked with Cloudflare before the check is switched on. Never shown again after saving.</p></td></tr>
          <tr><th scope="row">Switch</th>
              <td><label><input type="checkbox" name="af_ts_on" value="1" <?php checked($mode === 'on'); ?>> Show the security check on every login, sign-up and password-reset form</label></td></tr>
        </table>
        <p class="submit"><button type="submit" class="button button-primary">Save</button></p>
      </form>

      <?php if ($active) : ?>
      <h2 class="title">Preview</h2>
      <p>The box exactly as visitors get it, drawn by Cloudflare with the saved Site Key. A green tick means the keys and the hostname are right;
         an error code in the box means the Site Key is wrong or this hostname is not in the widget's list.</p>
      <div style="max-width:420px"><div class="cf-turnstile" data-sitekey="<?php echo esc_attr($k['site']); ?>" data-size="flexible" data-theme="light" data-action="preview"></div></div>
      <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
      <p><a href="<?php echo esc_url(home_url('/login/')); ?>" target="_blank" rel="noopener">Open the Login page</a> (use a private window: logged-in visitors never see the box).</p>
      <?php endif; ?>
    </div>
    <?php
}
