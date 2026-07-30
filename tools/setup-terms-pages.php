<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * §4 asks for Terms of Use and Terms of Service as separate pages.
 * Creates each once with distinct, purpose-correct content; never touches a
 * page that already exists, and leaves the old Terms & Conditions in place.
 * Run: wp eval-file tools/setup-terms-pages.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
$BRAND = get_bloginfo('name');
$EMAIL = get_option('woocommerce_email_from_address', get_option('admin_email'));

$pages = array(
    'terms-of-use' => array(
        'title' => 'Terms of Use',
        'body'  => "<h2>Terms of Use</h2>
<p>These terms govern your use of the {$BRAND} website itself — browsing, accounts, and the content we publish.</p>
<h3>Using this website</h3>
<p>You may browse, search, preview art on your wall, and share links for personal, non-commercial use. You agree not to scrape the catalogue, probe or disrupt the service, or misuse other customers' content.</p>
<h3>Your account</h3>
<p>Keep your sign-in details private; you are responsible for activity under your account. We may suspend accounts used abusively.</p>
<h3>Our content</h3>
<p>All artwork images, previews, product photography and text are the property of {$BRAND} or the credited artists, protected by copyright. Watermarked or preview images may not be reproduced, printed or resold.</p>
<h3>Reviews and uploads</h3>
<p>Content you submit (reviews, photos, wall previews) must be yours to share. You grant us a licence to display it on this site; we may remove content that is unlawful or abusive.</p>
<h3>Liability</h3>
<p>The website is provided as-is. To the extent the law allows, we are not liable for interruptions or for decisions made on the basis of on-screen colour reproduction, which varies by display.</p>
<h3>Contact</h3><p><a href=\"mailto:{$EMAIL}\">{$EMAIL}</a></p>",
    ),
    'terms-of-service' => array(
        'title' => 'Terms of Service',
        'body'  => "<h2>Terms of Service</h2>
<p>These terms govern purchases from {$BRAND} — orders, payment, delivery, returns and digital goods.</p>
<h3>Orders and payment</h3>
<p>An order is accepted when payment completes and you receive confirmation. Prices are shown inclusive of applicable taxes at checkout; we may cancel and refund orders affected by obvious pricing errors.</p>
<h3>Made-to-order items</h3>
<p>Canvas prints and framed pieces are produced for your order. Production begins shortly after confirmation; changes are possible only before production starts.</p>
<h3>Delivery</h3>
<p>Delivery estimates are shown at checkout. Oversized pieces may ship freight and cannot be delivered to PO boxes. International orders may attract customs duties payable by the recipient.</p>
<h3>Returns and issues</h3>
<p>Damaged or incorrect items are replaced or refunded — start a return from your order page within 14 days of delivery. Made-to-order pieces are otherwise returnable only as required by law. See the Return &amp; Refund Policy for the full process.</p>
<h3>Digital downloads</h3>
<p>Download links are personal, limited in number and time, and covered by the Digital Download License. No refunds once a file has been downloaded, except where the file is faulty.</p>
<h3>Contact</h3><p><a href=\"mailto:{$EMAIL}\">{$EMAIL}</a></p>",
    ),
);

echo "=== TERMS PAGES ===\n";
foreach ($pages as $slug => $def) {
    $existing = get_page_by_path($slug);
    if ($existing) { echo "  {$slug}: already exists (#{$existing->ID}) — untouched\n"; continue; }
    $id = wp_insert_post(array(
        'post_type'    => 'page',
        'post_status'  => 'publish',
        'post_title'   => $def['title'],
        'post_name'    => $slug,
        'post_content' => $def['body'],
    ));
    echo is_wp_error($id) ? "  {$slug}: FAILED\n" : "  {$slug}: created (#{$id})\n";
}
echo "=== DONE ===\n";
