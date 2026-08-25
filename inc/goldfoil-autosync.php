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

    // Synology: .../sharing/AbCdEf  or  .../d/f/AbCdEf
    if (preg_match('#^(https?://[^/]+).*/(?:sharing|d/f)/([A-Za-z0-9_-]+)#', $url, $m)) {
        $host = $m[1];
        $tok  = $m[2];
        $tries[] = $host . '/fsdownload/webapi/entry.cgi/artwork.zip?api=SYNO.FolderSharing.Download'
                 . '&version=2&method=download&mode=download&stdhtml=false&dlname=artwork'
                 . '&path=%5B%22%2F%22%5D&sharing_token=' . rawurlencode($tok) . '&_sharing_id=' . rawurlencode($tok);
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
