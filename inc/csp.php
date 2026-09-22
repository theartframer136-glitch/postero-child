<?php
/**
 * Content-Security-Policy on the pages that handle payment.
 *
 * DEF-06. Neither Content-Security-Policy nor its Report-Only form is sent on
 * checkout. CSP is the main control against an injected card-skimming script,
 * and PCI DSS v4.0 6.4.3 and 11.6.1 expect the scripts running on a payment
 * page to be inventoried and monitored. With 205 script tags on this site that
 * is not a small surface.
 *
 * REPORT-ONLY, DELIBERATELY, AND NOT AS A HALF MEASURE.
 *
 * An enforcing policy written today would be written from a guess at which
 * third-party hosts checkout needs, and the first thing a wrong guess blocks
 * is the hosted card field — which is to say it stops the shop taking money,
 * silently, for everyone. Report-Only blocks nothing. It cannot break
 * checkout, and it answers the question an enforcing policy has to be built
 * on: what is actually loading here. functions.php:15485 already reached the
 * same conclusion in an earlier pass ("CSP deferred — Elementor needs
 * report-only"); this is that, carried out.
 *
 * So the order is: report, read what came back, widen the policy to the hosts
 * that are genuinely needed, then enforce. Two of those three steps are the
 * owner's, and the admin screen exists so they are possible without a deploy.
 *
 * WHY template_redirect AND NOT send_headers. The existing headers at
 * functions.php:15487 are unconditional, so send_headers suits them. This one
 * has to know whether the current page is checkout, and WP::main() calls
 * send_headers() BEFORE query_posts() — conditional tags are not reliable
 * there yet. template_redirect runs after the query is resolved and before a
 * byte of output, which is where a conditional header belongs.
 *
 * WHAT THIS CANNOT DO. On this host LiteSpeed serves a cache hit without
 * running PHP, so no PHP-set header reaches a cached page. Checkout, cart and
 * my-account are uncached (no-store), so they are reachable; the home, shop
 * and product templates are not, and adding CSP there is a LiteSpeed/CDN
 * setting in hPanel rather than anything this file can do.
 *
 * Owner controls, all without a deploy:
 *   wp option update af_csp_policy '<directives>'
 *   wp option update af_csp_mode enforce      (refused while the policy is
 *                                              still the discovery default)
 *   wp option delete af_csp_reports           (clears what has been collected)
 */
if (!defined('ABSPATH')) exit;

/**
 * The starting policy, written from what checkout actually loads.
 *
 * Measured on the live checkout before this was written:
 *
 *     183 script tags — 89 inline, 94 external
 *      90  self
 *       1  web.squarecdn.com          1  cdnjs.cloudflare.com
 *       1  www.googletagmanager.com   1  cdn.jsdelivr.net
 *
 *     iframes: 3 web.squarecdn.com  (the hosted card field)
 *
 * Four third-party hosts. That is a small enough surface to name, so this
 * permits them and nothing else — which turns the report from "here is
 * everything you already load" into "something NEW appeared on your payment
 * page". That alert is the actual point of PCI 6.4.3, and it is only possible
 * because the inventory was measured rather than assumed.
 *
 * Only measured hosts are listed. The wildcards stay inside one vendor whose
 * presence the measurement already established — *.squareup.com alongside
 * *.squarecdn.com, because the card field talks to its own back end. Nothing
 * else is pre-permitted on a hunch: connect-src in particular is left at
 * 'self' plus Square, so wherever else this page talks to will report itself
 * instead of being quietly whitelisted by me.
 *
 * Presentation is deliberately wide open. Images, styles and fonts are not
 * how a card gets skimmed, and constraining them would bury the signal.
 * 'unsafe-inline' and 'unsafe-eval' stay for scripts because 89 of the 183
 * tags are inline and Elementor needs eval — removing either is a separate
 * job, and pretending otherwise would just produce noise.
 *
 * frame-ancestors 'self' is carried deliberately: checkout already sends it
 * today, and a policy of ours that dropped it would quietly remove
 * clickjacking protection to add script monitoring.
 */
function af_csp_default_policy() {
    return "default-src * data: blob: 'unsafe-inline' 'unsafe-eval'; "
         . "script-src 'self' 'unsafe-inline' 'unsafe-eval' "
             . "https://*.squarecdn.com https://*.squareup.com "
             . "https://www.googletagmanager.com "
             . "https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; "
         . "connect-src 'self' https://*.squarecdn.com https://*.squareup.com; "
         . "frame-src 'self' https://*.squarecdn.com https://*.squareup.com; "
         . "form-action 'self'; "
         . "object-src 'none'; "
         . "base-uri 'self'; "
         . "frame-ancestors 'self'";
}

function af_csp_policy() {
    $p = get_option('af_csp_policy');
    if (!is_string($p) || trim($p) === '') $p = af_csp_default_policy();
    $p = (string) apply_filters('af_csp_policy', $p);
    return trim(rtrim(trim($p), ';'));
}

/**
 * report or enforce.
 *
 * Enforcing is one option value away from blocking the card field, so it
 * takes two deliberate acts rather than one: the mode has to say enforce AND
 * a policy has to have been stored explicitly. Writing the policy down is
 * what says somebody read the reports first. Until then this returns report,
 * and the admin screen says so rather than failing silently.
 */
function af_csp_mode() {
    $mode = get_option('af_csp_mode', 'report');
    $mode = is_string($mode) ? strtolower(trim($mode)) : 'report';
    if ($mode !== 'enforce') return 'report';
    $stored = get_option('af_csp_policy');
    if (!is_string($stored) || trim($stored) === '') return 'report';
    return 'enforce';
}

/** Where violation reports are posted. */
function af_csp_report_url() {
    return add_query_arg('action', 'af_csp_report', admin_url('admin-ajax.php'));
}

/** Which pages carry the policy. Payment pages, which are also the uncached ones. */
function af_csp_is_target() {
    if (is_admin()) return false;
    if (!function_exists('is_checkout')) return false;
    $on = false;
    try {
        // is_checkout() covers /checkout/, order-pay and order-received.
        $on = (bool) is_checkout();
    } catch (\Throwable $e) {
        return false;
    }
    return (bool) apply_filters('af_csp_is_target', $on);
}

add_action('template_redirect', function () {
    try {
        if (headers_sent()) return;          // never turn a header into a warning
        if (!af_csp_is_target()) return;

        $url    = af_csp_report_url();
        $policy = af_csp_policy()
                . '; report-uri ' . $url
                . '; report-to af-csp';

        // report-to needs its endpoint declared separately. Both forms are
        // sent because report-uri is deprecated but universally supported,
        // and report-to is supported but not universal.
        header('Reporting-Endpoints: af-csp="' . $url . '"');

        // replace = FALSE, which matters. Checkout already sends
        // "Content-Security-Policy: frame-ancestors 'self'" today — measured,
        // not assumed. PHP's header() replaces by default, so enforcing mode
        // would silently drop the clickjacking protection that is there now
        // in order to add script monitoring. Browsers apply every CSP header
        // they receive and enforce the intersection, so adding is both safe
        // and the correct semantics.
        header((af_csp_mode() === 'enforce'
            ? 'Content-Security-Policy: '
            : 'Content-Security-Policy-Report-Only: ') . $policy, false);
    } catch (\Throwable $e) {
        // A header that cannot be set is not a reason to fail the page.
    }
}, 1);

/* ----------------------------------------------------------------------
 * The collector.
 *
 * This is an unauthenticated write endpoint on a live shop, which is a thing
 * to be careful with rather than clever about. It is bounded in every
 * direction: POST only, 8 KB of body, at most ten reports per request, the
 * document must be same-origin, a hard ceiling of 200 distinct findings, and
 * one database write per finding per hour.
 *
 * That last one matters on a site already running at load average 27–36. The
 * question being answered is WHICH directives and hosts fire, not how many
 * times — after the first few minutes every report is a duplicate, and
 * writing an option for each would be a self-inflicted load problem. The
 * count is therefore of recorded samples, not of violations, and is labelled
 * that way on the screen rather than dressed up as a total.
 * ------------------------------------------------------------------------ */

if (!defined('AF_CSP_MAX_KEYS')) define('AF_CSP_MAX_KEYS', 200);

/** The host a blocked URI belongs to, or the keyword when it is not a URL. */
function af_csp_host_of($blocked) {
    $b = trim((string) $blocked);
    if ($b === '') return '(empty)';
    $low = strtolower($b);
    foreach (array('inline', 'eval', 'data', 'blob', 'about', 'self', 'wasm-eval') as $kw) {
        if ($low === $kw || strpos($low, $kw . ':') === 0) return $kw;
    }
    $h = wp_parse_url($b, PHP_URL_HOST);
    if (!$h) return substr(preg_replace('/[^\x20-\x7E]/', '', $b), 0, 60);
    return substr(strtolower($h), 0, 80);
}

function af_csp_record($r) {
    if (!is_array($r)) return;

    $pick = function ($keys) use ($r) {
        foreach ($keys as $k) {
            if (isset($r[$k]) && is_scalar($r[$k]) && (string) $r[$k] !== '') return (string) $r[$k];
        }
        return '';
    };
    $dir     = $pick(array('effective-directive', 'effectiveDirective', 'violated-directive', 'violatedDirective'));
    $blocked = $pick(array('blocked-uri', 'blockedURL', 'blockedURI'));
    $doc     = $pick(array('document-uri', 'documentURL'));
    if ($dir === '') return;

    // Reports about somebody else's page are not ours to store.
    $home = wp_parse_url(home_url(), PHP_URL_HOST);
    $dh   = $doc ? wp_parse_url($doc, PHP_URL_HOST) : '';
    if ($home && $dh && strcasecmp($dh, $home) !== 0) return;

    $dir  = substr(preg_replace('/[^a-z0-9\-]/i', '', strtok($dir, ' ')), 0, 40);
    if ($dir === '') return;
    $host = af_csp_host_of($blocked);
    $key  = $dir . '|' . $host;

    // Path only, never the query string: an order-pay URL carries a key.
    $page = $doc ? (string) wp_parse_url($doc, PHP_URL_PATH) : '';
    $page = substr((string) $page, 0, 80);

    $store = get_option('af_csp_reports', array());
    if (!is_array($store)) $store = array();
    $now = time();

    if (isset($store[$key]) && is_array($store[$key])) {
        if ($now - (int) (isset($store[$key]['last']) ? $store[$key]['last'] : 0) < HOUR_IN_SECONDS) return;
        $store[$key]['last'] = $now;
        $store[$key]['n']    = (int) (isset($store[$key]['n']) ? $store[$key]['n'] : 0) + 1;
    } else {
        if (count($store) >= AF_CSP_MAX_KEYS) return;
        $store[$key] = array(
            'dir' => $dir, 'host' => $host, 'page' => $page,
            'first' => $now, 'last' => $now, 'n' => 1,
        );
    }
    update_option('af_csp_reports', $store, false);
}

function af_csp_receive() {
    // One answer to everything. An endpoint that responds differently to
    // different input tells an attacker what it accepts.
    try {
        $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : '';
        if ($method !== 'POST') { status_header(204); exit; }

        $raw = file_get_contents('php://input');
        if (!is_string($raw) || $raw === '' || strlen($raw) > 8192) { status_header(204); exit; }

        $data = json_decode($raw, true);
        if (!is_array($data)) { status_header(204); exit; }

        $reports = array();
        if (isset($data['csp-report']) && is_array($data['csp-report'])) {
            $reports[] = $data['csp-report'];                       // report-uri shape
        } elseif (isset($data[0])) {
            foreach (array_slice($data, 0, 10) as $item) {          // Reporting API shape
                if (is_array($item) && isset($item['body']) && is_array($item['body'])) {
                    $reports[] = $item['body'];
                }
            }
        }
        foreach ($reports as $rep) af_csp_record($rep);
    } catch (\Throwable $e) {
        // Swallowed on purpose: a reporting endpoint must never be the thing
        // that produces an error.
    }
    status_header(204);
    exit;
}
add_action('wp_ajax_nopriv_af_csp_report', 'af_csp_receive');
add_action('wp_ajax_af_csp_report',        'af_csp_receive');

/* ----------------------------------------------------------------------
 * Reading what came back. Without this the data is invisible and the
 * "collect for a week, then enforce" step cannot happen.
 * ------------------------------------------------------------------------ */
add_action('admin_menu', function () {
    add_submenu_page('woocommerce', 'Checkout Script Policy', 'Checkout Script Policy',
        'manage_woocommerce', 'af-csp', 'af_csp_admin_page');
});

function af_csp_admin_page() {
    if (!current_user_can('manage_woocommerce')) return;

    if (isset($_GET['af_csp_clear'], $_GET['_wpnonce'])
        && wp_verify_nonce($_GET['_wpnonce'], 'af_csp_clear')) {
        delete_option('af_csp_reports');
        echo '<script>location.replace("' . esc_url_raw(admin_url('admin.php?page=af-csp')) . '");</script>';
        return;
    }

    $store = get_option('af_csp_reports', array());
    if (!is_array($store)) $store = array();
    $mode  = af_csp_mode();
    $asked = get_option('af_csp_mode', 'report');

    echo '<div class="wrap"><h1>Checkout Script Policy</h1>';
    echo '<p>Which scripts the checkout page loads, and where it talks to. '
       . 'This is the inventory PCI DSS v4.0 6.4.3 asks you to keep.</p>';

    echo '<p><strong>Mode:</strong> <code>' . esc_html($mode) . '</code>';
    if ($mode === 'report') {
        echo ' — reporting only. <strong>Nothing is being blocked.</strong>';
        if (strtolower((string) $asked) === 'enforce') {
            echo ' <em>Enforcement was requested but is being refused: no policy has been '
               . 'stored yet, so enforcing would mean enforcing a default nobody has reviewed. '
               . 'Save the policy you want with <code>wp option update af_csp_policy</code> '
               . 'first — that is the step that says the reports below have been read.</em>';
        }
    } else {
        echo ' — <strong>enforcing.</strong> Anything not permitted below is being blocked on checkout.';
    }
    echo '</p>';

    echo '<p><strong>Policy:</strong></p><pre style="white-space:pre-wrap;background:#fff;'
       . 'border:1px solid #ccd0d4;padding:10px;max-width:900px">'
       . esc_html(af_csp_policy()) . '</pre>';

    if (!$store) {
        echo '<p><em>Nothing reported yet.</em> Reports appear after visitors load the checkout '
           . 'page in a browser that supports CSP reporting. Give it a day of real traffic '
           . 'before reading anything into an empty table.</p></div>';
        return;
    }

    uasort($store, function ($a, $b) {
        $an = isset($a['n']) ? (int) $a['n'] : 0;
        $bn = isset($b['n']) ? (int) $b['n'] : 0;
        return $bn <=> $an;
    });

    echo '<table class="widefat striped" style="max-width:1100px"><thead><tr>'
       . '<th>Directive</th><th>Host / source</th><th>Page</th>'
       . '<th>First seen</th><th>Last seen</th><th>Samples</th>'
       . '</tr></thead><tbody>';
    foreach ($store as $row) {
        if (!is_array($row)) continue;
        echo '<tr>';
        echo '<td><code>' . esc_html(isset($row['dir']) ? $row['dir'] : '') . '</code></td>';
        echo '<td><strong>' . esc_html(isset($row['host']) ? $row['host'] : '') . '</strong></td>';
        echo '<td>' . esc_html(isset($row['page']) ? $row['page'] : '') . '</td>';
        echo '<td>' . esc_html(isset($row['first']) ? date_i18n('M j, Y H:i', (int) $row['first']) : '') . '</td>';
        echo '<td>' . esc_html(isset($row['last'])  ? date_i18n('M j, Y H:i', (int) $row['last'])  : '') . '</td>';
        echo '<td>' . esc_html(isset($row['n']) ? (int) $row['n'] : 0) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    echo '<p style="color:#666"><em>“Samples” counts recorded reports, not total violations — '
       . 'one per finding per hour at most, so that collecting this cannot itself load the site.</em></p>';

    // Turn the collection into the next step rather than leaving it as a list.
    $by = array();
    foreach ($store as $row) {
        if (!is_array($row) || empty($row['dir'])) continue;
        $h = isset($row['host']) ? $row['host'] : '';
        if ($h === '' || in_array($h, array('inline', 'eval', 'data', 'blob', 'self', 'about', '(empty)'), true)) continue;
        $by[$row['dir']][$h] = true;
    }
    if ($by) {
        echo '<h2>Suggested policy, from what was actually seen</h2>';
        echo '<p>Every host below was reported as blocked-if-enforced. Check each one is '
           . 'something you recognise and intend — an unfamiliar host on a payment page is '
           . 'the finding this whole exercise is for — then widen the policy before switching '
           . 'to enforce.</p>';
        $lines = array();
        foreach ($by as $dir => $hosts) {
            $lines[] = $dir . " 'self' https://" . implode(' https://', array_keys($hosts));
        }
        echo '<pre style="white-space:pre-wrap;background:#fff;border:1px solid #ccd0d4;'
           . 'padding:10px;max-width:900px">' . esc_html(implode(";\n", $lines)) . '</pre>';
    }

    $clear = wp_nonce_url(admin_url('admin.php?page=af-csp&af_csp_clear=1'), 'af_csp_clear');
    echo '<p><a href="' . esc_url($clear) . '" class="button">Clear collected reports</a></p>';
    echo '</div>';
}
