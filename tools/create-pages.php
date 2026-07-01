<?php
/**
 * Create missing legal/utility pages with starter content.
 * SAFE: skips any page whose slug already exists. Never overwrites.
 * Idempotent. Run: wp eval-file tools/create-pages.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$BRAND = 'The Art Framer';
$EMAIL = 'theartframer136@gmail.com';
$PHONE = '+1 (610) 470-7280';

$pages = array(
    'shipping-delivery' => array(
        'title' => 'Shipping & Delivery',
        'body'  => "<h2>Shipping &amp; Delivery</h2>
<p>At {$BRAND}, every piece is made to order and handled with care. Below is what you can expect after placing an order.</p>
<h3>Processing Time</h3><p>Orders are typically processed and produced within <strong>3–5 business days</strong>. Custom and large-format pieces may require additional time.</p>
<h3>Delivery Time</h3><p>Standard delivery within the United States takes <strong>5–10 business days</strong> after dispatch. You will receive tracking details by email once your order ships.</p>
<h3>Shipping Charges</h3><p>We offer <strong>free delivery</strong> across major serviceable locations in the USA including Delaware, Pennsylvania, Maryland, New Jersey and nearby areas. Charges for other regions are calculated at checkout.</p>
<h3>Need Help?</h3><p>For delivery questions, contact us at <a href=\"mailto:{$EMAIL}\">{$EMAIL}</a> or {$PHONE}.</p>",
    ),
    'returns-exchanges' => array(
        'title' => 'Returns & Exchanges',
        'body'  => "<h2>Returns &amp; Exchanges</h2>
<p>Your satisfaction matters to us. Because most items are made to order, please review the policy below.</p>
<h3>Eligibility</h3><p>Returns are accepted within <strong>7 days of delivery</strong> for items that arrive damaged, defective, or incorrect. The item must be unused and in its original packaging.</p>
<h3>Non-Returnable Items</h3><p>Personalised, custom-sized, and digital download products are not eligible for return unless they arrive damaged.</p>
<h3>How to Request a Return</h3><p>Email <a href=\"mailto:{$EMAIL}\">{$EMAIL}</a> with your order number and photos of the issue. Our team will guide you through the process.</p>",
    ),
    'refund-policy' => array(
        'title' => 'Refund Policy',
        'body'  => "<h2>Refund Policy</h2>
<p>Once an approved return is received and inspected, we will notify you of the status of your refund.</p>
<h3>Approved Refunds</h3><p>Approved refunds are processed to your original payment method within <strong>5–7 business days</strong>. Depending on your bank, it may take additional time to reflect.</p>
<h3>Damaged or Incorrect Items</h3><p>If your order arrived damaged or incorrect, we will offer a replacement or a full refund at no additional cost.</p>
<h3>Contact</h3><p>Questions about a refund? Reach us at <a href=\"mailto:{$EMAIL}\">{$EMAIL}</a> or {$PHONE}.</p>",
    ),
    'privacy-policy' => array(
        'title' => 'Privacy Policy',
        'body'  => "<h2>Privacy Policy</h2>
<p>{$BRAND} respects your privacy. This policy explains what information we collect and how we use it.</p>
<h3>Information We Collect</h3><p>We collect information you provide at checkout (name, address, email, phone) and standard analytics data to improve your experience.</p>
<h3>How We Use It</h3><p>Your information is used solely to process orders, provide support, and — with your consent — send updates. We never sell your data.</p>
<h3>Payment Security</h3><p>Payments are processed through secure, PCI-compliant gateways. We do not store your card details.</p>
<h3>Your Rights</h3><p>You may request access to, correction of, or deletion of your personal data by contacting <a href=\"mailto:{$EMAIL}\">{$EMAIL}</a>.</p>
<p><em>This is a starter policy. Please have it reviewed to ensure full GDPR/CCPA compliance for your jurisdiction.</em></p>",
    ),
    'terms-conditions' => array(
        'title' => 'Terms & Conditions',
        'body'  => "<h2>Terms &amp; Conditions</h2>
<p>By accessing and purchasing from {$BRAND}, you agree to the following terms.</p>
<h3>Products</h3><p>We make every effort to display product colors and details accurately. Slight variations may occur due to screen settings and the handmade nature of our products.</p>
<h3>Pricing</h3><p>All prices are listed in USD and are subject to change without notice. We reserve the right to correct any pricing errors.</p>
<h3>Intellectual Property</h3><p>All artwork, images, and content on this site are the property of {$BRAND} or its artists and may not be reproduced without permission.</p>
<h3>Contact</h3><p>Questions about these terms? Email <a href=\"mailto:{$EMAIL}\">{$EMAIL}</a>.</p>
<p><em>This is a starter template. Please have it reviewed by a legal professional.</em></p>",
    ),
    'help-support' => array(
        'title' => 'Help & Support',
        'body'  => "<h2>Help &amp; Support</h2>
<p>We're here to help. Choose the option that best fits your need.</p>
<h3>Contact Us</h3><ul>
<li><strong>Email:</strong> <a href=\"mailto:{$EMAIL}\">{$EMAIL}</a></li>
<li><strong>Phone / WhatsApp:</strong> {$PHONE}</li>
</ul>
<h3>Common Topics</h3><ul>
<li><a href=\"/shipping-delivery/\">Shipping &amp; Delivery</a></li>
<li><a href=\"/returns-exchanges/\">Returns &amp; Exchanges</a></li>
<li><a href=\"/refund-policy/\">Refund Policy</a></li>
<li><a href=\"/track-your-order/\">Track Your Order</a></li>
<li><a href=\"/faqs/\">Frequently Asked Questions</a></li>
</ul>",
    ),
    'track-your-order' => array(
        'title' => 'Track Your Order',
        'body'  => "<h2>Track Your Order</h2>
<p>Once your order ships, we email you a tracking number and link. You can also track your order from your account.</p>
<p><a class=\"button\" href=\"/my-account/orders/\">View My Orders</a></p>
<h3>Need Help Locating Your Order?</h3><p>Email <a href=\"mailto:{$EMAIL}\">{$EMAIL}</a> or message {$PHONE} with your order number and we'll help right away.</p>",
    ),
    'content-ethics-policy' => array(
        'title' => 'Content & Ethics Policy',
        'body'  => "<h2>Content &amp; Ethics Policy</h2>
<p>{$BRAND} is committed to respectful, authentic, and culturally sensitive artwork.</p>
<h3>Cultural Respect</h3><p>Our spiritual and cultural artworks are created with reverence. We aim to honor the traditions they represent.</p>
<h3>Artist Rights</h3><p>We work directly with artists and respect their intellectual property. Original creators are credited and fairly compensated.</p>
<h3>Authenticity</h3><p>We do not use misleading imagery. Product previews represent the actual artwork you will receive.</p>",
    ),
    'wholesale-corporate' => array(
        'title' => 'Wholesale & Corporate Orders',
        'body'  => "<h2>Wholesale &amp; Corporate Orders</h2>
<p>{$BRAND} offers special pricing for bulk, wholesale, and corporate gifting orders.</p>
<h3>Who It's For</h3><p>Interior designers, hotels, cafés, offices, and retailers looking to order at volume.</p>
<h3>What You Get</h3><ul><li>Volume-based pricing</li><li>Custom sizes and framing</li><li>Dedicated support</li><li>Corporate gifting &amp; branding options</li></ul>
<h3>Get a Quote</h3><p>Email <a href=\"mailto:{$EMAIL}\">{$EMAIL}</a> or call {$PHONE} with your requirements and we'll prepare a custom quote.</p>",
    ),
);

echo "=== Create Missing Pages ===\n\n";
$created = 0; $skipped = 0;
foreach ( $pages as $slug => $data ) {
    $existing = get_page_by_path( $slug );
    if ( $existing ) {
        echo "SKIP  /{$slug} (already exists, id {$existing->ID})\n";
        $skipped++;
        continue;
    }
    $id = wp_insert_post( array(
        'post_title'   => $data['title'],
        'post_name'    => $slug,
        'post_content' => $data['body'],
        'post_status'  => 'publish',
        'post_type'    => 'page',
        'post_author'  => 1,
        'comment_status'=> 'closed',
    ) );
    if ( is_wp_error( $id ) ) {
        echo "ERROR /{$slug}: " . $id->get_error_message() . "\n";
    } else {
        echo "CREATE /{$slug} -> id {$id}\n";
        update_post_meta( $id, '_taf_generated_page', '1' );
        $created++;
    }
}
echo "\n=== DONE. Created {$created}, skipped {$skipped}. ===\n";
echo "Note: content is starter copy — review legal pages before relying on them.\n";
