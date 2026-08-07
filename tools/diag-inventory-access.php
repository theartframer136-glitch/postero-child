<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * READ-ONLY check that everyone allowlisted for the inventory tool actually
 * has a WordPress account they can log in with. Makes no changes.
 *
 * The allowlist in functions.php matches on the logged-in user's email, so an
 * address with no account behind it silently grants nothing — the person just
 * can't get in, with no error explaining why. This reports which allowlisted
 * addresses resolve to a real user and which don't, plus each one's role, so
 * a missing account is visible rather than a mystery.
 *
 * Run: wp eval-file tools/diag-inventory-access.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

echo "=== INVENTORY TOOL ACCESS CHECK ===\n";

if ( ! function_exists( 'af_inv_allowed_emails' ) ) {
    echo "af_inv_allowed_emails() not defined — theme not loaded or not deployed yet.\n";
    echo "=== DONE ===\n";
    return;
}

$emails  = af_inv_allowed_emails();
$missing = 0;

echo "allowlisted addresses: " . count( $emails ) . "\n\n";

foreach ( $emails as $email ) {
    $user = get_user_by( 'email', $email );
    if ( ! $user ) {
        $missing++;
        echo "NO ACCOUNT: {$email} — allowlisted but no WordPress user has this address, so they cannot log in yet.\n";
        continue;
    }
    $roles = $user->roles ? implode( ', ', $user->roles ) : 'no role';
    $admin = user_can( $user, 'manage_options' ) ? ' (already an administrator)' : '';
    echo "OK: {$email} — user #{$user->ID} \"{$user->user_login}\", role: {$roles}{$admin}\n";
}

// Administrators reach the tool through manage_options, not the allowlist, so
// report them separately — otherwise it looks like the allowlist is the only
// way in and the real access surface stays hidden.
$admins = get_users( array( 'role' => 'administrator', 'fields' => array( 'ID', 'user_email' ) ) );
echo "\nadministrators with access via manage_options: " . count( $admins ) . "\n";
foreach ( $admins as $a ) {
    echo "  #{$a->ID} {$a->user_email}\n";
}

echo "\n{$missing} allowlisted address(es) without a WordPress account.\n";
echo "=== DONE ===\n";
