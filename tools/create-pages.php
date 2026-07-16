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
    'compare' => array(
        'title' => 'Compare Products',
        'body'  => "<h2>Compare Products</h2><p>Select artworks in the shop with the Compare button, then review them side by side here.</p>",
    ),
    'safe-easy-payments' => array(
        'title' => 'Safe & Easy Payments',
        'body'  => "<h2>Safe &amp; Easy Payments</h2>
<p>Every transaction on {$BRAND} is protected with SSL encryption and processed by PCI-compliant payment providers. We never store your card details on our servers.</p>
<p>Questions about payments? Email <a href=\"mailto:{$EMAIL}\">{$EMAIL}</a> or call {$PHONE}.</p>",
    ),
    'gift-cards' => array(
        'title' => 'Gift Cards',
        'body'  => "<h2>Gift Cards</h2>
<p>Give the gift of art. A {$BRAND} gift card lets your loved ones choose the perfect canvas, frame, or custom print for their space.</p>
<h3>How It Works</h3>
<ol><li>Choose a value — \$25, \$50, \$100, \$200, or a custom amount.</li>
<li>We email a beautifully designed digital gift card to you or directly to the recipient.</li>
<li>The recipient redeems it at checkout on any product, including custom orders.</li></ol>
<h3>Good to Know</h3>
<ul><li>Gift cards never expire.</li><li>They can be combined with sale prices.</li><li>Any unused balance stays on the card for the next order.</li></ul>
<h3>Order a Gift Card</h3>
<p>Email <a href=\"mailto:{$EMAIL}\">{$EMAIL}</a> or call {$PHONE} with the amount and the recipient's name — we'll take care of the rest, usually within a few hours.</p>",
    ),
    'legal-imprint' => array(
        'title' => 'Legal Imprint',
        'body'  => "<h2>Legal Imprint</h2>
<h3>Website Operator</h3>
<p>{$BRAND}<br>United States</p>
<h3>Contact</h3>
<p>Email: <a href=\"mailto:{$EMAIL}\">{$EMAIL}</a><br>Phone: {$PHONE}</p>
<h3>Responsible for Content</h3>
<p>{$BRAND} is responsible for the content of this website. All artwork, product photography, and text are the property of {$BRAND} or its contributing artists unless otherwise noted.</p>
<h3>Dispute Resolution</h3>
<p>We aim to resolve any concern directly and quickly — please contact us first at <a href=\"mailto:{$EMAIL}\">{$EMAIL}</a>.</p>
<p><em>This imprint is provided for transparency. Please have it reviewed by a legal professional for your jurisdiction.</em></p>",
    ),
    'reviews-press' => array(
        'title' => 'Reviews & Press',
        'body'  => "<h2>Reviews &amp; Press</h2>
<p>Real feedback from real walls. {$BRAND} is rated <strong>5.0 out of 5</strong> by our customers on Google.</p>
<h3>What Customers Say</h3>
<ul>
<li>&ldquo;Fantastic experience from start to finish. The canvas prints for my living room look stunning.&rdquo;</li>
<li>&ldquo;The colors are super vibrant but still look classy and elegant. The quality really stands out.&rdquo;</li>
<li>&ldquo;I ordered a large wall art piece and was impressed by its quality — premium results.&rdquo;</li>
</ul>
<p><a href=\"https://www.google.com/search?q=The+Art+Framer+reviews\" rel=\"noopener\" target=\"_blank\">Read all our Google reviews →</a></p>
<h3>Share Your Wall</h3>
<p>Bought from us? We'd love to see it. Tag us on Instagram or email your photos to <a href=\"mailto:{$EMAIL}\">{$EMAIL}</a> — favourites get featured on our homepage gallery.</p>
<h3>Press &amp; Media</h3>
<p>For press enquiries, interviews, or high-resolution imagery, contact <a href=\"mailto:{$EMAIL}\">{$EMAIL}</a>.</p>",
    ),
    'customize-your-picture' => array(
        'title' => 'Customize Your Picture',
        'body'  => "<h2>Customize Your Picture</h2>
<p>Turn your own photo into premium canvas or framed wall art — portraits, family collages, weddings, pets, and personalised gifts, made to order.</p>
<h3>How It Works</h3>
<ol>
<li><strong>Send your photo</strong> — email it to <a href=\"mailto:{$EMAIL}\">{$EMAIL}</a> or WhatsApp {$PHONE}. High-resolution originals give the best result.</li>
<li><strong>Choose your size</strong> — from 2×3 ft up to extra-large 4×6 ft statement pieces.</li>
<li><strong>Pick your frame</strong> — floating, aluminium, wooden, or gallery-wrapped without frame, in Black, Silver, Gold, or Rose Gold.</li>
<li><strong>Preview &amp; approve</strong> — we send a digital mockup before printing. Want to see it on your wall first? Use <a href=\"/try-on-wall/\">Try It on Your Wall</a>.</li>
<li><strong>We print &amp; ship</strong> — museum-grade canvas, eco-friendly inks, secure packaging.</li>
</ol>
<h3>Popular Custom Orders</h3>
<ul><li>Family photo collages</li><li>Wedding &amp; anniversary portraits</li><li>Baby's first year timelines</li><li>Pet portraits</li><li>Business &amp; café branding walls</li></ul>
<p><a class=\"button\" href=\"/contact/\">Start Your Custom Order</a></p>",
    ),
    'low-price-guarantee' => array(
        'title' => 'Low Price Guarantee',
        'body'  => "<h2>Low Price Guarantee</h2>
<p>Premium wall art shouldn't come with an inflated price tag. {$BRAND} prints and frames in-house, so you get gallery quality at direct-from-maker prices.</p>
<h3>Our Promise</h3>
<p>If you find the same size, material, and framing combination advertised for less by a comparable US print studio within 7 days of your purchase, send us the link — we'll match the price or refund the difference.</p>
<h3>Conditions</h3>
<ul><li>Applies to identical specifications (size, canvas type, frame type and colour).</li><li>The comparison price must be publicly listed and in stock.</li><li>Marketplace flash sales and clearance listings are excluded.</li></ul>
<p>To make a claim, email <a href=\"mailto:{$EMAIL}\">{$EMAIL}</a> with your order number and the competing link.</p>",
    ),
    'refer-a-friend' => array(
        'title' => 'Refer a Friend',
        'body'  => "<h2>Refer a Friend</h2>
<p>Love your wall? Share it. When a friend places their first order with {$BRAND}, you both save.</p>
<h3>How It Works</h3>
<ol><li>Tell your friend to mention your name or email in their order note (or in a message to us).</li>
<li>They get <strong>10% off</strong> their first order.</li>
<li>You get a <strong>10% discount code</strong> for your next order once theirs ships.</li></ol>
<h3>No Limits</h3>
<p>Refer as many friends as you like — a new code for every first-time order you send our way.</p>
<p>Questions? Email <a href=\"mailto:{$EMAIL}\">{$EMAIL}</a> or WhatsApp {$PHONE}.</p>",
    ),
    'affiliates' => array(
        'title' => 'Affiliate Program',
        'body'  => "<h2>Affiliate Program</h2>
<p>Interior bloggers, home-décor creators, and design professionals — earn commission recommending art you already love.</p>
<h3>What You Get</h3>
<ul><li>Commission on every completed sale you refer</li><li>Ready-made creative assets and product imagery</li><li>A dedicated contact for product questions</li><li>Early access to new collections</li></ul>
<h3>Who It's For</h3>
<p>Content creators, interior designers, real-estate stagers, and anyone with an audience that cares about beautiful spaces.</p>
<h3>Apply</h3>
<p>Email <a href=\"mailto:{$EMAIL}\">{$EMAIL}</a> with your website or social profiles and a line about your audience. We reply within 2 business days.</p>",
    ),
    'exhibitions-events' => array(
        'title' => 'Exhibitions & Events',
        'body'  => "<h2>Exhibitions &amp; Events</h2>
<p>From gallery pop-ups to trade shows, {$BRAND} brings large-format art and signage to life at events across the US.</p>
<h3>What We Do</h3>
<ul><li>Exhibition prints and gallery walls</li><li>Event backdrops, banners, and standees</li><li>Corporate branding for conferences and launches</li><li>Artist showcase collaborations</li></ul>
<h3>Upcoming Events</h3>
<p>No public events are scheduled right now — follow us on Instagram and Facebook for announcements.</p>
<h3>Host or Collaborate</h3>
<p>Planning an exhibition or need event signage? Email <a href=\"mailto:{$EMAIL}\">{$EMAIL}</a> or call {$PHONE} — we handle design, printing, and delivery timelines.</p>",
    ),
    'artists' => array(
        'title' => 'Artists & Creators',
        'body'  => "<h2>Artists &amp; Creators</h2>
<p>Original art, direct from the people who made it. {$BRAND} partners with independent artists and prints their work with the same museum-grade care as our own collections — and every sale supports the artist directly.</p>
<h3>Meet Our Artists</h3>
<ul>
<li><a href=\"/artists/ananya-sengupta/\"><strong>Ananya Sengupta</strong></a> — spiritual and devotional canvas art rooted in Indian tradition.</li>
<li><a href=\"/artists/tanmay-choudhary/\"><strong>Tanmay Choudhary</strong></a> — contemporary and abstract compositions for modern interiors.</li>
</ul>
<h3>Are You an Artist?</h3>
<p>We're always looking for new voices. If you'd like your work printed, framed, and sold through {$BRAND} — with fair, transparent revenue sharing — email a portfolio link to <a href=\"mailto:{$EMAIL}\">{$EMAIL}</a>.</p>",
    ),
    'artists/ananya-sengupta' => array(
        'title'  => 'Ananya Sengupta',
        'parent' => 'artists',
        'body'   => "<h2>Ananya Sengupta</h2>
<p>Ananya Sengupta creates devotional and spiritual artwork inspired by Indian tradition — Radha Krishna, Pichwai motifs, and sacred iconography reimagined for contemporary homes.</p>
<h3>About the Artist</h3>
<p>Working across gouache, digital painting, and mixed media, Ananya's pieces balance rich traditional detail with a calm, modern palette. Each artwork is reproduced as a limited premium canvas print, quality-checked by the artist.</p>
<h3>Shop the Collection</h3>
<p><a class=\"button\" href=\"/product-category/direct-from-artists/ananya-sengupta/\">View all artworks by Ananya Sengupta</a></p>",
    ),
    'artists/tanmay-choudhary' => array(
        'title'  => 'Tanmay Choudhary',
        'parent' => 'artists',
        'body'   => "<h2>Tanmay Choudhary</h2>
<p>Tanmay Choudhary's work spans bold abstracts, landscapes, and contemporary compositions — art designed to anchor a modern living room, office, or studio wall.</p>
<h3>About the Artist</h3>
<p>Tanmay works in large formats, exploring colour fields and texture. His originals are scanned at archival resolution so every canvas print preserves the depth of the source painting.</p>
<h3>Shop the Collection</h3>
<p><a class=\"button\" href=\"/product-category/direct-from-artists/tanmay-choudhary/\">View all artworks by Tanmay Choudhary</a></p>",
    ),
);

echo "=== Create Missing Pages ===\n\n";
$created = 0; $skipped = 0;
foreach ( $pages as $slug => $data ) {
    $existing = get_page_by_path( $slug );
    if ( $existing ) {
        // Page exists but is not visible (e.g. WP-core's draft Privacy Policy
        // squatting the slug -> live 404). Publish it and fill empty content.
        if ( $existing->post_status !== 'publish' ) {
            $update = array( 'ID' => $existing->ID, 'post_status' => 'publish' );
            if ( trim( wp_strip_all_tags( $existing->post_content ) ) === '' ) {
                $update['post_content'] = $data['body'];
            }
            $res = wp_update_post( $update, true );
            if ( is_wp_error( $res ) ) {
                echo "ERROR /{$slug} publish: " . $res->get_error_message() . "\n";
            } else {
                echo "PUBLISH /{$slug} (was {$existing->post_status}, id {$existing->ID})\n";
                $created++;
            }
        } else {
            echo "SKIP  /{$slug} (already exists, id {$existing->ID})\n";
            $skipped++;
        }
        continue;
    }
    // Resolve parent for nested slugs like artists/ananya-sengupta
    $parent_id = 0;
    $post_name = $slug;
    if ( ! empty( $data['parent'] ) ) {
        $parent = get_page_by_path( $data['parent'] );
        if ( ! $parent ) {
            echo "ERROR /{$slug}: parent '{$data['parent']}' not found — run again after it exists\n";
            continue;
        }
        $parent_id = $parent->ID;
        $post_name = basename( $slug );
    }
    $id = wp_insert_post( array(
        'post_title'   => $data['title'],
        'post_name'    => $post_name,
        'post_parent'  => $parent_id,
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

// Point WordPress's own "Privacy Policy page" setting at the published page
$pp = get_page_by_path( 'privacy-policy' );
if ( $pp && $pp->post_status === 'publish' && (int) get_option( 'wp_page_for_privacy_policy' ) !== $pp->ID ) {
    update_option( 'wp_page_for_privacy_policy', $pp->ID );
    echo "SET   wp_page_for_privacy_policy = {$pp->ID}\n";
}

echo "\n=== DONE. Created/published {$created}, skipped {$skipped}. ===\n";
echo "Note: content is starter copy — review legal pages before relying on them.\n";
