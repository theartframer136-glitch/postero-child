<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Store the real US business address + Google Search Console
 * verification token. Both feed live output:
 *   - address  -> PostalAddress in Organization schema (PHASE 24i)
 *   - GSC code -> Rank Math's google-site-verification meta tag
 *
 * NOTHING here is invented. Each value stays empty until the real
 * one is supplied by the business owner; empty values are skipped,
 * so this script is a no-op until then.
 *
 * To populate: fill the constants below, commit, push. Deploy applies
 * them. Re-running with unchanged values changes nothing.
 */

if (!defined('ABSPATH')) exit;

// ── Fill these in with REAL values, then push ───────────────────────
$ADDRESS = array(
    'street' => '',   // e.g. '123 Main Street, Suite 4'
    'city'   => '',   // e.g. 'Allentown'
    'region' => '',   // 2-letter state, e.g. 'PA'
    'postal' => '',   // e.g. '18101'
);
$GSC_VERIFICATION_CODE = '';  // the content="..." value from Search Console's HTML tag method
// ────────────────────────────────────────────────────────────────────

$log = function ($m) { echo "[nap] $m\n"; };

$filled = array_filter($ADDRESS, function ($v) { return trim((string) $v) !== ''; });
if (count($filled) === 4) {
    $clean = array_map(function ($v) { return sanitize_text_field(trim($v)); }, $ADDRESS);
    if (get_option('af_business_address') !== $clean) {
        update_option('af_business_address', $clean);
        $log('business address stored — PostalAddress now in Organization schema');
    } else {
        $log('business address already current');
    }
} elseif ($filled) {
    $log('address PARTIALLY filled (' . count($filled) . '/4) — skipped; all four fields are required');
} else {
    $log('no address supplied yet — schema address omitted (correct: never invent a NAP)');
}

$code = trim($GSC_VERIFICATION_CODE);
if ($code !== '') {
    // Accept either the raw token or a pasted full meta tag.
    if (preg_match('/content=["\']([^"\']+)["\']/i', $code, $m)) $code = $m[1];
    $code = sanitize_text_field($code);
    $titles = get_option('rank-math-options-general', array());
    if (!is_array($titles)) $titles = array();
    if (($titles['google_verify'] ?? '') !== $code) {
        $titles['google_verify'] = $code;
        update_option('rank-math-options-general', $titles);
        $log('Google Search Console verification token installed');
    } else {
        $log('GSC token already current');
    }
} else {
    $log('no GSC token supplied yet — verification meta tag omitted');
}

$log('done');
