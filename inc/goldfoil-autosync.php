<?php
if (!defined('ABSPATH')) exit;
/**
 * Watch a folder that lives somewhere else, and keep the Gold Foiled & UV
 * section in step with it — by itself, on a schedule, with nobody uploading
 * anything.
 *
 * THE PROBLEM THIS SOLVES
 * The artwork lives in C:\Users\user\SynologyDrive\Personalised, on the owner's
 * PC. The shop runs on a server in a data centre. Neither that server nor the
 * deploy can read a drive letter on someone's desk — there is no route between
 * them, and no code can invent one. Every route offered so far therefore ended
 * in the owner moving the files by hand, once, every time they added a picture.
 * That is the thing they asked not to have to do.
 *
 * So the direction is reversed. Instead of pushing files up, the SITE reaches
 * out and pulls them down, on its own, for as long as it is pointed at an
 * address it can reach:
 *
 *     wp option update af_goldfoil_watch_url 'https://<the folder's link>'
 *
 * Synology Drive can produce exactly such an address — a share link on that
 * folder — and so can Dropbox, Google Drive, or any WebDAV the NAS exposes.
 * That link is set ONCE. Afterwards the owner drops a picture into the folder
 * on their PC, Synology syncs it to the NAS as it always does, and within the
 * hour this has fetched it and turned it into a product. Nothing else is ever
 * asked of them.
 *
 * WHY IT DOES NOT RE-IMPORT THE SAME THING FOREVER
 * Two guards, one behind the other. The archive's own bytes are hashed, so an
 * unchanged folder costs one request and stops; and every product records the
 * file it came from, so even when the hash does move — a picture added, the
 * rest untouched — only what is genuinely new becomes a product.
 */

/* ── how often ────────────────────────────────────────────────────────── */
add_filter('cron_schedules', function ($s) {
    if (!isset($s['af_gf_15min'])) {
        $s['af_gf_15min'] = array('interval' => 900, 'display' => 'Every 15 minutes (gold-foil watch)');
    }
    return $s;
});

add_action('init', function () {
    // Nothing is scheduled until there is an address to watch, so a site that
    // never uses this never carries the job.
    $url = trim((string) get_option('af_goldfoil_watch_url', ''));
    $has = wp_next_scheduled('af_goldfoil_sync');
    if ($url === '') {
        if ($has) wp_unschedule_event($has, 'af_goldfoil_sync');
        return;
    }
    if (!$has) {
        $every = (string) get_option('af_goldfoil_watch_every', 'hourly');
        wp_schedule_event(time() + 120, $every, 'af_goldfoil_sync');
    }
});

/**
 * A share link is a page for a human; a downloader needs the bytes.
 *
 * Synology's folder links come in two shapes, /sharing/<token> and /d/f/<token>,
 * and both answer a browser with HTML. The zip lives behind their
 * FolderSharing download endpoint, keyed by the same token. Google Drive and
 * Dropbox have their own equivalents. Rewriting is attempted, never assumed:
 * whatever comes back is judged by its bytes further down, so a link that needs
 * no rewriting is simply used as it stands.
 */
function af_goldfoil_direct_url($url) {
    $url = trim($url);
    if ($url === '') return array();
    $tries = array($url);

    // Synology publishes a folder under several link shapes depending on which
    // app made the link — File Station's /sharing/<token>, Synology Drive's
    // /d/f/ and /d/s/, and the mobile /mo/sharing/. They are NOT one endpoint
    // with four spellings: File Station answers the FolderSharing download API,
    // Drive answers a /download path on the share itself. Both are tried, in
    // the order most likely to be right, and whichever returns a zip wins —
    // the bytes decide, so a wrong guess costs one request and nothing else.
    if (preg_match('#^(https?://[^/]+)(?:/[^/]+)*?/(?:mo/)?(sharing|d/f|d/s)/([A-Za-z0-9_-]+)#', $url, $m)) {
        $host = $m[1];
        $kind = $m[2];
        $tok  = $m[3];
        $enc  = rawurlencode($tok);

        // Synology Drive share: the folder's own download path
        if ($kind !== 'sharing') {
            $tries[] = $host . '/d/s/' . $enc . '/download';
            $tries[] = $host . '/d/f/' . $enc . '/download';
        }
        // File Station share: the documented FolderSharing download call.
        // Two spellings of the id parameter — DSM 6 wants sharing_token, DSM 7
        // wants a quoted _sharing_id — so both are sent; the unused one is
        // ignored rather than rejected.
        $tries[] = $host . '/fsdownload/webapi/entry.cgi/artwork.zip?api=SYNO.FolderSharing.Download'
                 . '&version=2&method=download&mode=download&stdhtml=false&dlname=artwork'
                 . '&path=%5B%22%2F%22%5D&sharing_token=' . $enc . '&_sharing_id=' . $enc;
        $tries[] = $host . '/sharing/webapi/entry.cgi?api=SYNO.FolderSharing.Download'
                 . '&version=2&method=download&mode=download&stdhtml=false&dlname=%22artwork%22'
                 . '&path=%5B%22%2F%22%5D&_sharing_id=%22' . $enc . '%22';
    }
    // Dropbox: ?dl=0 is the preview page, ?dl=1 is the file
    if (strpos($url, 'dropbox.com') !== false) {
        $tries[] = preg_replace('/([?&])dl=0/', '$1dl=1', $url)
                 . (strpos($url, 'dl=') === false ? ((strpos($url, '?') === false ? '?' : '&') . 'dl=1') : '');
    }
    // Google Drive folder/file id
    if (preg_match('#drive\.google\.com/.*?(?:/d/|id=)([A-Za-z0-9_-]{20,})#', $url, $m)) {
        $tries[] = 'https://drive.google.com/uc?export=download&id=' . $m[1];
    }
    return array_values(array_unique(array_filter($tries)));
}

/** Is this actually an archive, or a sign-in page dressed as one? */
function af_goldfoil_looks_like_zip($file) {
    $fh = @fopen($file, 'rb');
    if (!$fh) return false;
    $magic = (string) fread($fh, 4);
    fclose($fh);
    return substr($magic, 0, 2) === 'PK';
}

/** What the last run did, so the deploy log and wp-admin can both report it. */
function af_goldfoil_watch_note($msg) {
    update_option('af_goldfoil_watch_last', gmdate('Y-m-d H:i') . ' UTC — ' . $msg, false);
}

add_action('af_goldfoil_sync', 'af_goldfoil_sync_run');

function af_goldfoil_sync_run($force = false) {
    $url = trim((string) get_option('af_goldfoil_watch_url', ''));
    if ($url === '') return 'no watch url set';
    if (!function_exists('af_goldfoil_slug')) return 'gold-foil module not loaded';

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $got = ''; $used = '';
    foreach (af_goldfoil_direct_url($url) as $try) {
        $tmp = download_url($try, 600);
        if (is_wp_error($tmp)) continue;
        if (af_goldfoil_looks_like_zip($tmp) || wp_check_filetype(basename(parse_url($try, PHP_URL_PATH)))['ext']) {
            $got = $tmp; $used = $try; break;
        }
        @unlink($tmp);   // HTML: a share PAGE, not the folder's bytes
    }
    if ($got === '') {
        $msg = 'could not fetch anything usable from the watch link — if it asks for a login, '
             . 'or only shows a preview page, the server cannot read it';
        af_goldfoil_watch_note($msg);
        return $msg;
    }

    // Unchanged folder: one request, and stop. This is what makes a 15-minute
    // schedule reasonable rather than wasteful.
    $hash = @md5_file($got);
    if (!$force && $hash && $hash === (string) get_option('af_goldfoil_watch_hash', '')) {
        @unlink($got);
        af_goldfoil_watch_note('checked, folder unchanged');
        return 'unchanged';
    }

    $up   = wp_get_upload_dir();
    $work = trailingslashit($up['basedir']) . 'gold-foil-watch';
    if (!is_dir($work)) wp_mkdir_p($work);
    $into = trailingslashit($work) . 'latest';

    if (af_goldfoil_looks_like_zip($got)) {
        WP_Filesystem();
        // A fresh directory each time: the importer skips what it has already
        // seen, so leftovers cost nothing but confusion.
        if (is_dir($into)) {
            foreach ((array) glob(trailingslashit($into) . '*') as $f) { if (is_file($f)) @unlink($f); }
        } else {
            wp_mkdir_p($into);
        }
        $ok = unzip_file($got, $into);
        @unlink($got);
        if (is_wp_error($ok)) {
            $msg = 'fetched, but the archive could not be unpacked: ' . $ok->get_error_message();
            af_goldfoil_watch_note($msg);
            return $msg;
        }
    } else {
        if (!is_dir($into)) wp_mkdir_p($into);
        $name = basename(parse_url($used, PHP_URL_PATH));
        if ($name === '' || strpos($name, '.') === false) $name = 'artwork.jpg';
        @rename($got, trailingslashit($into) . sanitize_file_name($name));
    }

    // Hand the folder to the importer that already knows how to make a product
    // out of a picture — same titles, same 1.40x pricing, same descriptions as
    // every other route into this section.
    $before = (int) af_goldfoil_count();
    if (!defined('AF_GOLDFOIL_INTERNAL')) define('AF_GOLDFOIL_INTERNAL', true);
    $args = array($into);
    ob_start();
    include get_stylesheet_directory() . '/tools/import-gold-foil.php';
    $out = ob_get_clean();
    $after = (int) af_goldfoil_count();

    if ($hash) update_option('af_goldfoil_watch_hash', $hash, false);
    update_option('af_goldfoil_watch_log', substr((string) $out, -4000), false);

    $made = max(0, $after - $before);
    $msg  = sprintf('fetched and imported — %d new product(s), %d in the section', $made, $after);
    af_goldfoil_watch_note($msg);
    return $msg;
}

/** How many published pieces the section holds right now. */
function af_goldfoil_count() {
    if (!function_exists('af_goldfoil_slug')) return 0;
    $t = get_term_by('slug', af_goldfoil_slug(), 'product_cat');
    return ($t && !is_wp_error($t)) ? (int) $t->count : 0;
}

/* ══════════════════════════════════════════════════════════════════════════
 * THE SCREEN THAT MAKES THIS SELF-SERVE
 *
 * Everything above works, and until now none of it could be switched on by the
 * person who needs it. The watch address was settable in exactly one place —
 * an input on the deploy workflow — which means every change of mind, every
 * re-issued Synology link, every "did it actually run?" cost a fifteen-minute
 * deploy and somebody to start it. That is not automatic; it is manual with
 * extra steps and a longer wait.
 *
 * So the whole thing gets a page of its own under Products, where the owner
 * pastes the folder's share link once, presses a button, and watches it work.
 * After that the site does it alone, on the schedule chosen here.
 * ═════════════════════════════════════════════════════════════════════════ */

add_action('admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=product',
        'Gold Foiled & UV — artwork sync',
        'Gold Foiled & UV',
        'manage_woocommerce',
        'af-goldfoil-sync',
        'af_goldfoil_admin_page'
    );
});

/** Put the schedule in step with whatever was just saved. */
function af_goldfoil_reschedule() {
    $has = wp_next_scheduled('af_goldfoil_sync');
    if ($has) wp_unschedule_event($has, 'af_goldfoil_sync');
    $url = trim((string) get_option('af_goldfoil_watch_url', ''));
    if ($url === '') return;
    $every = (string) get_option('af_goldfoil_watch_every', 'hourly');
    if (!isset(wp_get_schedules()[$every])) $every = 'hourly';
    wp_schedule_event(time() + 60, $every, 'af_goldfoil_sync');
}

function af_goldfoil_admin_page() {
    if (!current_user_can('manage_woocommerce')) wp_die('Not allowed');

    $notice = '';

    /* ── saving ───────────────────────────────────────────────────────── */
    if (isset($_POST['af_gf_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['af_gf_nonce'])), 'af_gf_sync')) {

        if (isset($_POST['af_gf_save'])) {
            $url_in = isset($_POST['af_gf_url']) ? trim((string) wp_unslash($_POST['af_gf_url'])) : '';
            // esc_url_raw, not sanitize_text_field: a share link is mostly
            // query string, and the query string is where the token lives.
            $url = $url_in === '' ? '' : esc_url_raw($url_in);
            $was = (string) get_option('af_goldfoil_watch_url', '');
            update_option('af_goldfoil_watch_url', $url, false);

            $every = isset($_POST['af_gf_every']) ? sanitize_key(wp_unslash($_POST['af_gf_every'])) : 'hourly';
            if (!isset(wp_get_schedules()[$every])) $every = 'hourly';
            update_option('af_goldfoil_watch_every', $every, false);

            $ratio = isset($_POST['af_gf_ratio']) ? (float) wp_unslash($_POST['af_gf_ratio']) : 1.40;
            if ($ratio > 0.05 && $ratio < 10) update_option('af_goldfoil_ratio', $ratio, false);

            // A NEW link is a new folder as far as this is concerned, so the
            // "have I seen these bytes before" guard is dropped — otherwise a
            // freshly pasted link whose zip happened to hash the same as the
            // last one would be reported "unchanged" and import nothing.
            if ($url !== $was) delete_option('af_goldfoil_watch_hash');

            af_goldfoil_reschedule();
            $notice = $url === ''
                ? 'Saved. No link is being watched, so nothing will import on its own.'
                : 'Saved. The site will check that folder ' . esc_html($every) . '.';
        }

        if (isset($_POST['af_gf_now'])) {
            // A folder of print masters is tens of megabytes and the importer
            // makes several image sizes of each; the default 30-second budget
            // is not the shape of this work.
            @set_time_limit(0);
            @ini_set('memory_limit', '512M');
            $notice = 'Sync run: ' . af_goldfoil_sync_run(true);
        }
    }

    $url     = (string) get_option('af_goldfoil_watch_url', '');
    $every   = (string) get_option('af_goldfoil_watch_every', 'hourly');
    $last    = (string) get_option('af_goldfoil_watch_last', 'never run');
    $log     = (string) get_option('af_goldfoil_watch_log', '');
    $count   = af_goldfoil_count();
    $next    = wp_next_scheduled('af_goldfoil_sync');
    $ratio   = function_exists('af_goldfoil_ratio') ? af_goldfoil_ratio() : 1.40;
    $term    = function_exists('af_goldfoil_slug') ? get_term_by('slug', af_goldfoil_slug(), 'product_cat') : null;

    $schedules = wp_get_schedules();
    ?>
    <div class="wrap">
      <h1>Gold Foiled &amp; UV — artwork sync</h1>

      <?php if ($notice !== '') : ?>
        <div class="notice notice-info"><p><?php echo esc_html($notice); ?></p></div>
      <?php endif; ?>

      <p style="max-width:46em">
        Paste the <strong>share link of your <code>Personalised</code> folder</strong> below. The site
        then downloads that folder by itself, on the schedule you choose, and turns every new
        picture in it into a Gold Foiled &amp; UV product — priced from the rate card at
        <strong>&times;<?php echo esc_html(number_format((float) $ratio, 2)); ?></strong>, with the same
        description, sizes and frames as the rest of the shop. Add a picture to the folder on your
        PC and it appears in the shop on its own; nothing has to be uploaded here.
      </p>

      <table class="widefat" style="max-width:46em;margin-bottom:1.5em">
        <tbody>
          <tr><td style="width:14em"><strong>Products in the section</strong></td>
              <td><?php echo (int) $count; ?><?php if ($term && !is_wp_error($term)) : ?>
                  &nbsp;<a href="<?php echo esc_url(get_term_link($term)); ?>" target="_blank">view the page</a>
                  <?php endif; ?></td></tr>
          <tr><td><strong>Last check</strong></td><td><?php echo esc_html($last); ?></td></tr>
          <tr><td><strong>Next check</strong></td>
              <td><?php echo $next ? esc_html(gmdate('Y-m-d H:i', $next) . ' UTC') : 'not scheduled (no link set)'; ?></td></tr>
        </tbody>
      </table>

      <form method="post">
        <?php wp_nonce_field('af_gf_sync', 'af_gf_nonce'); ?>
        <table class="form-table" role="presentation">
          <tr>
            <th scope="row"><label for="af_gf_url">Folder share link</label></th>
            <td>
              <input name="af_gf_url" id="af_gf_url" type="url" class="large-text code"
                     value="<?php echo esc_attr($url); ?>"
                     placeholder="https://your-nas.quickconnect.to/d/s/XXXXXXXX">
              <p class="description">
                In <strong>Synology Drive</strong>: right-click the <code>Personalised</code> folder &rarr;
                <em>Share</em> &rarr; turn the link on &rarr; set it to <em>Anyone with the link</em>
                (no password) &rarr; copy. Dropbox and Google&nbsp;Drive folder links work too.
                A link that asks for a sign-in cannot be read by the server.
              </p>
            </td>
          </tr>
          <tr>
            <th scope="row"><label for="af_gf_every">Check for new artwork</label></th>
            <td>
              <select name="af_gf_every" id="af_gf_every">
                <?php foreach (array('af_gf_15min', 'hourly', 'twicedaily', 'daily') as $k) :
                    if (!isset($schedules[$k])) continue; ?>
                  <option value="<?php echo esc_attr($k); ?>" <?php selected($every, $k); ?>>
                    <?php echo esc_html($schedules[$k]['display']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <p class="description">An unchanged folder costs one small request, so checking often is cheap.</p>
            </td>
          </tr>
          <tr>
            <th scope="row"><label for="af_gf_ratio">Price</label></th>
            <td>
              <input name="af_gf_ratio" id="af_gf_ratio" type="number" step="0.01" min="0.1" max="9"
                     value="<?php echo esc_attr(number_format((float) $ratio, 2, '.', '')); ?>" style="width:7em">
              <p class="description">
                Multiplier on the normal price for the same size. <code>1.40</code> is 40% more.
                Applies to pieces imported from now on.
              </p>
            </td>
          </tr>
        </table>
        <p>
          <button class="button button-primary" name="af_gf_save" value="1">Save</button>
          <button class="button" name="af_gf_now" value="1"
                  <?php disabled($url === ''); ?>>Sync now</button>
        </p>
        <p class="description" style="max-width:46em">
          <strong>Sync now</strong> fetches the folder straight away and imports anything it has not
          seen before. It is safe to press twice — a picture that is already a product is skipped,
          never duplicated.
        </p>
      </form>

      <?php if ($log !== '') : ?>
        <h2>What the last import did</h2>
        <pre style="background:#fff;border:1px solid #ccd0d4;padding:12px;max-height:24em;overflow:auto;white-space:pre-wrap"><?php
            echo esc_html($log); ?></pre>
      <?php endif; ?>
    </div>
    <?php
}
