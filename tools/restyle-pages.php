<?php
/**
 * Phase 13 — rich, styled content for the 12 spec pages.
 * Replaces the plain starter copy with structured HTML that uses the
 * .taf-page design system in assets/css/custom.css (v2.9.0).
 * Idempotent (stamps _taf_styled_page). Run: wp eval-file tools/restyle-pages.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$STYLE_VERSION = '13.0';
$EMAIL = 'theartframer136@gmail.com';
$PHONE = '+1 (610) 470-7280';
$TEL   = 'tel:+16104707280';

$bodies = array();

/* ── Gift Cards ─────────────────────────────────────────────── */
$bodies['gift-cards'] = <<<HTML
<div class="taf-page">
  <div class="taf-hero">
    <span class="taf-eyebrow">The Perfect Present</span>
    <h1>Gift Cards</h1>
    <p class="taf-sub">Give the gift of art — let them choose the canvas, frame, and size that fits their space perfectly.</p>
  </div>

  <div class="taf-section">
    <h2 class="taf-h2">Choose Your Amount</h2>
    <p class="taf-lead">Digital gift cards, delivered by email within hours — to you or straight to the lucky recipient.</p>
    <div class="taf-price-grid">
      <div class="taf-price"><span class="amt">\$25</span><span class="lbl">Starter</span></div>
      <div class="taf-price"><span class="amt">\$50</span><span class="lbl">Popular</span></div>
      <div class="taf-price"><span class="amt">\$100</span><span class="lbl">Statement</span></div>
      <div class="taf-price"><span class="amt">\$200</span><span class="lbl">Gallery</span></div>
      <div class="taf-price"><span class="amt">Custom</span><span class="lbl">You Decide</span></div>
    </div>
  </div>

  <div class="taf-section">
    <h2 class="taf-h2">How It Works</h2>
    <ol class="taf-steps">
      <li><b>Pick a value</b> — \$25, \$50, \$100, \$200, or any custom amount you like.</li>
      <li><b>We email the card</b> — a beautifully designed digital gift card sent to you or directly to the recipient, with your personal message.</li>
      <li><b>They shop &amp; redeem</b> — the card applies at checkout on any product, including fully custom prints and framing.</li>
    </ol>
  </div>

  <div class="taf-section taf-two">
    <div>
      <h2 class="taf-h2">Good to Know</h2>
      <ul class="taf-check">
        <li>Gift cards <b>never expire</b></li>
        <li>Can be combined with sale prices and offers</li>
        <li>Unused balance stays on the card for next time</li>
        <li>Works on custom orders and personalised prints</li>
        <li>Delivered same day in most cases</li>
      </ul>
    </div>
    <div class="taf-card">
      <span class="taf-ico">💡</span>
      <h3>Not sure what they'd love?</h3>
      <p>A gift card pairs perfectly with our <a href="/try-on-wall/">Try It on Your Wall</a> tool — they can preview any artwork in their own room before deciding. No sizing mistakes, no returns, just the right art.</p>
    </div>
  </div>

  <div class="taf-cta">
    <h2>Order a Gift Card</h2>
    <p>Tell us the amount and the recipient's name — we'll handle the design and delivery, usually within a few hours.</p>
    <a class="taf-btn" href="mailto:{$EMAIL}?subject=Gift%20Card%20Order">Email Us to Order</a>
    <a class="taf-btn-alt" href="{$TEL}">Call {$PHONE}</a>
  </div>
</div>
HTML;

/* ── Legal Imprint ──────────────────────────────────────────── */
$bodies['legal-imprint'] = <<<HTML
<div class="taf-page taf-legal">
  <div class="taf-hero">
    <span class="taf-eyebrow">Transparency</span>
    <h1>Legal Imprint</h1>
    <p class="taf-sub">Who we are, and how to reach the people behind The Art Framer.</p>
  </div>

  <h2>Website Operator</h2>
  <p><b>The Art Framer</b><br>Operating in the United States (Delaware · Pennsylvania · New Jersey and nationwide shipping)<br>Website: theartframer.us</p>

  <h2>Contact</h2>
  <ul>
    <li>Email: <a href="mailto:{$EMAIL}">{$EMAIL}</a></li>
    <li>Phone: <a href="{$TEL}">{$PHONE}</a></li>
    <li>Contact form: <a href="/contact/">theartframer.us/contact</a></li>
  </ul>

  <h2>Responsible for Content</h2>
  <p>The Art Framer is responsible for the content published on this website. All artwork, product photography, and text are the property of The Art Framer or its contributing artists unless otherwise noted, and may not be reproduced without written permission.</p>

  <h2>Artist Content</h2>
  <p>Artworks sold under our <a href="/artists/">Artists &amp; Creators</a> program remain the intellectual property of the respective artist and are reproduced and sold under licence. See our <a href="/content-ethics-policy/">Content &amp; Ethics Policy</a> for how we source and credit art.</p>

  <h2>Dispute Resolution</h2>
  <p>We aim to resolve any concern directly and quickly — please contact us first at <a href="mailto:{$EMAIL}">{$EMAIL}</a>. We respond to all enquiries within two business days.</p>

  <p class="taf-note">This imprint is provided for transparency. Please have it reviewed by a legal professional for your jurisdiction.</p>
</div>
HTML;

/* ── Reviews & Press ────────────────────────────────────────── */
$bodies['reviews-press'] = <<<HTML
<div class="taf-page">
  <div class="taf-hero">
    <span class="taf-eyebrow">Social Proof</span>
    <h1>Reviews &amp; Press</h1>
    <p class="taf-sub"><span class="taf-stars">★★★★★</span><br>Rated 5.0 out of 5 by our customers on Google — real feedback from real walls.</p>
  </div>

  <div class="taf-section">
    <h2 class="taf-h2">What Customers Say</h2>
    <div class="taf-grid">
      <blockquote class="taf-quote">
        <span class="taf-stars">★★★★★</span>
        <p>"Fantastic experience from start to finish. The canvas prints for my living room look stunning and add so much character to my home."</p>
        <footer>— Rama, verified Google review</footer>
      </blockquote>
      <blockquote class="taf-quote">
        <span class="taf-stars">★★★★★</span>
        <p>"The colors are super vibrant but still look classy and elegant, not overdone. The quality really stands out, and you can tell they pay attention to detail."</p>
        <footer>— Arun Daga, verified Google review</footer>
      </blockquote>
      <blockquote class="taf-quote">
        <span class="taf-stars">★★★★★</span>
        <p>"Ever since I hung my prints in my space, my home feels so much more vibrant and welcoming — it's like it finally reflects my personality."</p>
        <footer>— Karthik Govindaswamy, verified Google review</footer>
      </blockquote>
      <blockquote class="taf-quote">
        <span class="taf-stars">★★★★★</span>
        <p>"I ordered a large wall art piece and was impressed by its quality. I highly recommend The Art Framer for premium results."</p>
        <footer>— Aswin Garimalla, verified Google review</footer>
      </blockquote>
    </div>
    <p style="text-align:center;margin-top:26px;">
      <a class="taf-btn" href="https://www.google.com/search?q=The+Art+Framer+Delaware+reviews" target="_blank" rel="noopener">Read All Google Reviews</a>
    </p>
  </div>

  <div class="taf-section taf-two">
    <div class="taf-card">
      <span class="taf-ico">📸</span>
      <h3>Share Your Wall</h3>
      <p>Bought from us? We'd love to see it. Tag us on Instagram or email your photos — our favourites get featured on the homepage gallery, with credit to you.</p>
    </div>
    <div class="taf-card">
      <span class="taf-ico">📰</span>
      <h3>Press &amp; Media</h3>
      <p>Writing about wall art, interior trends, or small-business printing? We're happy to help with interviews, background, and high-resolution imagery.</p>
    </div>
  </div>

  <div class="taf-cta">
    <h2>Get in Touch</h2>
    <p>Customer photos, press enquiries, or partnership ideas — one inbox for all of it.</p>
    <a class="taf-btn" href="mailto:{$EMAIL}">Email {$EMAIL}</a>
    <a class="taf-btn-alt" href="/contact/">Contact Form</a>
  </div>
</div>
HTML;

/* ── Customize Your Picture ─────────────────────────────────── */
$bodies['customize-your-picture'] = <<<HTML
<div class="taf-page">
  <div class="taf-hero">
    <span class="taf-eyebrow">Made Just for You</span>
    <h1>Customize Your Picture</h1>
    <p class="taf-sub">Turn your own photo into premium canvas or framed wall art — portraits, collages, weddings, pets, and personalised gifts, made to order.</p>
  </div>

  <div class="taf-section">
    <h2 class="taf-h2">How It Works</h2>
    <ol class="taf-steps">
      <li><b>Send your photo</b> — email it to <a href="mailto:{$EMAIL}">{$EMAIL}</a> or WhatsApp us at {$PHONE}. High-resolution originals give the best result.</li>
      <li><b>Choose your size</b> — from cozy 2×3 ft prints up to extra-large 4×6 ft statement pieces.</li>
      <li><b>Pick your frame</b> — floating, aluminium, wooden, or gallery-wrapped without frame, in Black, Silver, Gold, or Rose Gold.</li>
      <li><b>Preview &amp; approve</b> — we send a digital mockup before anything is printed. Want to see it in your room first? Use <a href="/try-on-wall/">Try It on Your Wall</a>.</li>
      <li><b>We print &amp; ship</b> — museum-grade canvas, eco-friendly inks, and secure big-wall-safe packaging.</li>
    </ol>
  </div>

  <div class="taf-section">
    <h2 class="taf-h2">Popular Custom Orders</h2>
    <div class="taf-grid">
      <div class="taf-card"><span class="taf-ico">👨‍👩‍👧‍👦</span><h3>Family Photo Collages</h3><p>Multiple memories arranged into one beautiful wall display.</p></div>
      <div class="taf-card"><span class="taf-ico">💍</span><h3>Wedding &amp; Anniversary</h3><p>Your favourite moment, printed large and framed to last a lifetime.</p></div>
      <div class="taf-card"><span class="taf-ico">👶</span><h3>Baby's First Year</h3><p>Month-by-month timelines and newborn portraits for the nursery.</p></div>
      <div class="taf-card"><span class="taf-ico">🐕</span><h3>Pet Portraits</h3><p>Because they're family too — playful or regal, you choose the style.</p></div>
      <div class="taf-card"><span class="taf-ico">🏢</span><h3>Business &amp; Café Walls</h3><p>Branding walls, menus, and interior art for commercial spaces.</p></div>
      <div class="taf-card"><span class="taf-ico">🎁</span><h3>Personalised Gifts</h3><p>Birthdays, housewarmings, retirements — a gift no one else can give.</p></div>
    </div>
  </div>

  <div class="taf-section">
    <h2 class="taf-h2">Our Quality Promise</h2>
    <ul class="taf-check">
      <li>Museum-grade premium canvas with eco-friendly inks</li>
      <li>Digital mockup approval before printing — no surprises</li>
      <li>Colour-corrected by hand for vibrant, accurate prints</li>
      <li>Secure packaging — no broken canvas, guaranteed</li>
      <li>Free shipping options across our delivery areas</li>
    </ul>
  </div>

  <div class="taf-cta">
    <h2>Start Your Custom Order</h2>
    <p>Send us your photo today and we'll reply with a free mockup and quote — usually within 24 hours.</p>
    <a class="taf-btn" href="mailto:{$EMAIL}?subject=Custom%20Picture%20Order">Email Your Photo</a>
    <a class="taf-btn-alt" href="/contact/">Contact Form</a>
  </div>
</div>
HTML;

/* ── Low Price Guarantee ────────────────────────────────────── */
$bodies['low-price-guarantee'] = <<<HTML
<div class="taf-page">
  <div class="taf-hero">
    <span class="taf-eyebrow">Fair Pricing, Always</span>
    <h1>Low Price Guarantee</h1>
    <p class="taf-sub">Gallery quality at direct-from-maker prices. Find it cheaper? We'll match it or refund the difference.</p>
  </div>

  <div class="taf-section taf-two">
    <div>
      <h2 class="taf-h2">Our Promise</h2>
      <p class="taf-lead">We print and frame in-house — no galleries, no middlemen, no inflated markups. If you find the same size, material, and framing combination advertised for less by a comparable US print studio within <b>7 days</b> of your purchase, send us the link and we'll match the price or refund the difference.</p>
    </div>
    <div class="taf-card">
      <span class="taf-ico">🛡️</span>
      <h3>Why we can promise this</h3>
      <p>Every canvas is printed, stretched, and framed in our own studio. When you cut out the middle layers, premium quality doesn't have to carry a premium price tag.</p>
    </div>
  </div>

  <div class="taf-section">
    <h2 class="taf-h2">How to Claim</h2>
    <ol class="taf-steps">
      <li><b>Find a lower price</b> — same size, canvas type, frame type and colour, at a comparable US print studio.</li>
      <li><b>Email us within 7 days</b> of your purchase with your order number and the competing link.</li>
      <li><b>We verify and refund</b> — if it checks out, we match the price or refund the difference. Done.</li>
    </ol>
  </div>

  <div class="taf-section">
    <h2 class="taf-h2">Conditions</h2>
    <ul class="taf-check">
      <li>Applies to identical specifications — size, canvas type, frame type and colour</li>
      <li>The comparison price must be publicly listed and in stock</li>
      <li>Marketplace flash sales and clearance listings are excluded</li>
      <li>One claim per order</li>
    </ul>
  </div>

  <div class="taf-cta">
    <h2>Found It Cheaper?</h2>
    <p>Send your order number and the competing link — we'll take a look right away.</p>
    <a class="taf-btn" href="mailto:{$EMAIL}?subject=Price%20Match%20Claim">Make a Claim</a>
    <a class="taf-btn-alt" href="/shop/">Shop with Confidence</a>
  </div>
</div>
HTML;

/* ── Refer a Friend ─────────────────────────────────────────── */
$bodies['refer-a-friend'] = <<<HTML
<div class="taf-page">
  <div class="taf-hero">
    <span class="taf-eyebrow">Share the Art</span>
    <h1>Refer a Friend</h1>
    <p class="taf-sub">Love your wall? Share it. When a friend places their first order, you both save 10%.</p>
  </div>

  <div class="taf-section">
    <h2 class="taf-h2">How It Works</h2>
    <ol class="taf-steps">
      <li><b>Tell your friend about us</b> — they mention your name or email in their order note (or in a message to us) when placing their first order.</li>
      <li><b>They save 10%</b> — instantly, on their entire first order, including custom prints.</li>
      <li><b>You get 10% too</b> — as soon as their order ships, we email you a 10% discount code for your next purchase.</li>
    </ol>
  </div>

  <div class="taf-section">
    <div class="taf-grid">
      <div class="taf-card"><span class="taf-ico">♾️</span><h3>No Limits</h3><p>Refer as many friends as you like — you earn a new code for every first-time order you send our way.</p></div>
      <div class="taf-card"><span class="taf-ico">🧾</span><h3>Works on Everything</h3><p>Referral discounts apply to canvas prints, framing, banners, and even fully custom orders.</p></div>
      <div class="taf-card"><span class="taf-ico">⏱️</span><h3>No Waiting</h3><p>Your friend's discount is applied immediately at order time — no coupons to hunt for.</p></div>
    </div>
  </div>

  <div class="taf-cta">
    <h2>Know Someone With an Empty Wall?</h2>
    <p>Send them our way — beautiful art for them, a discount for you.</p>
    <a class="taf-btn" href="/shop/">Browse the Collection</a>
    <a class="taf-btn-alt" href="mailto:{$EMAIL}?subject=Referral%20Question">Ask a Question</a>
  </div>
</div>
HTML;

/* ── Affiliates ─────────────────────────────────────────────── */
$bodies['affiliates'] = <<<HTML
<div class="taf-page">
  <div class="taf-hero">
    <span class="taf-eyebrow">Partner With Us</span>
    <h1>Affiliate Program</h1>
    <p class="taf-sub">Interior bloggers, décor creators, and design professionals — earn commission recommending art you already love.</p>
  </div>

  <div class="taf-section">
    <h2 class="taf-h2">What You Get</h2>
    <div class="taf-grid">
      <div class="taf-card"><span class="taf-ico">💰</span><h3>Commission on Every Sale</h3><p>Earn on every completed order you refer — tracked and paid reliably.</p></div>
      <div class="taf-card"><span class="taf-ico">🎨</span><h3>Ready-Made Creatives</h3><p>Product imagery, banners, and copy blocks you can drop straight into your content.</p></div>
      <div class="taf-card"><span class="taf-ico">🤝</span><h3>A Real Contact</h3><p>A dedicated person for product questions, custom requests, and your audience's orders.</p></div>
      <div class="taf-card"><span class="taf-ico">🚀</span><h3>Early Access</h3><p>Preview new collections and seasonal campaigns before they go live.</p></div>
    </div>
  </div>

  <div class="taf-section taf-two">
    <div>
      <h2 class="taf-h2">Who It's For</h2>
      <ul class="taf-check">
        <li>Home décor and interior design content creators</li>
        <li>Interior designers and decorators</li>
        <li>Real-estate stagers and photographers</li>
        <li>Lifestyle bloggers and newsletter writers</li>
        <li>Anyone with an audience that cares about beautiful spaces</li>
      </ul>
    </div>
    <div>
      <h2 class="taf-h2">How to Apply</h2>
      <ol class="taf-steps">
        <li><b>Email us</b> your website or social profiles and a line about your audience.</li>
        <li><b>We reply within 2 business days</b> with commission details and your creative kit.</li>
        <li><b>Start earning</b> — share your link and watch the commissions come in.</li>
      </ol>
    </div>
  </div>

  <div class="taf-cta">
    <h2>Apply Today</h2>
    <p>It takes one email to get started.</p>
    <a class="taf-btn" href="mailto:{$EMAIL}?subject=Affiliate%20Application">Apply via Email</a>
  </div>
</div>
HTML;

/* ── Exhibitions & Events ───────────────────────────────────── */
$bodies['exhibitions-events'] = <<<HTML
<div class="taf-page">
  <div class="taf-hero">
    <span class="taf-eyebrow">Beyond the Wall</span>
    <h1>Exhibitions &amp; Events</h1>
    <p class="taf-sub">From gallery pop-ups to trade shows — large-format art and signage that makes your event unforgettable.</p>
  </div>

  <div class="taf-section">
    <h2 class="taf-h2">What We Do</h2>
    <div class="taf-grid">
      <div class="taf-card"><span class="taf-ico">🖼️</span><h3>Exhibition Prints</h3><p>Gallery-grade canvas and framed walls for shows, fairs, and installations.</p></div>
      <div class="taf-card"><span class="taf-ico">🎪</span><h3>Event Backdrops</h3><p>Vinyl and fabric backdrops, banners, and standees printed to any size.</p></div>
      <div class="taf-card"><span class="taf-ico">🏢</span><h3>Corporate Branding</h3><p>Conference signage, product launches, and branded environments.</p></div>
      <div class="taf-card"><span class="taf-ico">🎨</span><h3>Artist Showcases</h3><p>Collaborations with independent artists — see our <a href="/artists/">Artists &amp; Creators</a> program.</p></div>
    </div>
  </div>

  <div class="taf-section taf-two">
    <div class="taf-card">
      <span class="taf-ico">📅</span>
      <h3>Upcoming Events</h3>
      <p>No public events are scheduled right now. Follow us on Instagram and Facebook for pop-up announcements, or join the newsletter at the bottom of this page.</p>
    </div>
    <div class="taf-card">
      <span class="taf-ico">⚡</span>
      <h3>Tight Deadline?</h3>
      <p>Event timelines are our specialty. Tell us your date and we'll build the print and delivery schedule around it — including installation guidance.</p>
    </div>
  </div>

  <div class="taf-cta">
    <h2>Host or Collaborate</h2>
    <p>Planning an exhibition or need event signage? We handle design, printing, and delivery timelines.</p>
    <a class="taf-btn" href="mailto:{$EMAIL}?subject=Event%20Enquiry">Email Your Event Brief</a>
    <a class="taf-btn-alt" href="{$TEL}">Call {$PHONE}</a>
  </div>
</div>
HTML;

/* ── Artists hub ────────────────────────────────────────────── */
$bodies['artists'] = <<<HTML
<div class="taf-page">
  <div class="taf-hero">
    <span class="taf-eyebrow">Direct From the Studio</span>
    <h1>Artists &amp; Creators</h1>
    <p class="taf-sub">Original art, direct from the people who made it — printed with museum-grade care, with every sale supporting the artist directly.</p>
  </div>

  <div class="taf-section">
    <h2 class="taf-h2">Meet Our Artists</h2>
    <div class="taf-grid">
      <div class="taf-card">
        <span class="taf-ico">🕉️</span>
        <h3>Ananya Sengupta</h3>
        <p>Spiritual and devotional canvas art rooted in Indian tradition — Radha Krishna, Pichwai motifs, and sacred iconography reimagined for contemporary homes.</p>
        <p style="margin-top:14px;"><a class="taf-btn" href="/artists/ananya-sengupta/">View Profile</a></p>
      </div>
      <div class="taf-card">
        <span class="taf-ico">🎨</span>
        <h3>Tanmay Choudhary</h3>
        <p>Bold abstracts, landscapes, and contemporary compositions — art designed to anchor a modern living room, office, or studio wall.</p>
        <p style="margin-top:14px;"><a class="taf-btn" href="/artists/tanmay-choudhary/">View Profile</a></p>
      </div>
    </div>
    <p style="text-align:center;margin-top:24px;">
      <a class="taf-btn-alt" href="/product-category/direct-from-artists/">Shop All Artist Collections</a>
    </p>
  </div>

  <div class="taf-section">
    <h2 class="taf-h2">Why Buy Direct From Artists?</h2>
    <ul class="taf-check">
      <li>Every sale pays the artist directly — fair, transparent revenue sharing</li>
      <li>Original compositions you won't find in big-box stores</li>
      <li>Archival-resolution scans preserve every brushstroke</li>
      <li>The same museum-grade printing and framing as all our collections</li>
    </ul>
  </div>

  <div class="taf-cta">
    <h2>Are You an Artist?</h2>
    <p>We're always looking for new voices. Get your work printed, framed, and sold through The Art Framer — with fair, transparent revenue sharing.</p>
    <a class="taf-btn" href="mailto:{$EMAIL}?subject=Artist%20Portfolio%20Submission">Submit Your Portfolio</a>
  </div>
</div>
HTML;

/* ── Artist: Ananya Sengupta ────────────────────────────────── */
$bodies['artists/ananya-sengupta'] = <<<HTML
<div class="taf-page">
  <div class="taf-hero">
    <span class="taf-eyebrow">Artist Profile</span>
    <h1>Ananya Sengupta</h1>
    <p class="taf-sub">Devotional &amp; spiritual art rooted in Indian tradition — reimagined for contemporary homes.</p>
  </div>

  <div class="taf-section taf-two">
    <div>
      <h2 class="taf-h2">About the Artist</h2>
      <p class="taf-lead">Ananya Sengupta creates devotional and spiritual artwork inspired by Indian tradition — Radha Krishna, Pichwai motifs, and sacred iconography. Working across gouache, digital painting, and mixed media, her pieces balance rich traditional detail with a calm, modern palette.</p>
      <p>Each artwork is reproduced as a premium canvas print at archival resolution, and quality-checked by the artist before it ships.</p>
      <p style="margin-top:10px;">
        <span class="taf-badge">Radha Krishna</span>
        <span class="taf-badge">Pichwai Art</span>
        <span class="taf-badge">Sacred Iconography</span>
        <span class="taf-badge">Devotional</span>
      </p>
    </div>
    <div class="taf-card">
      <span class="taf-ico">✨</span>
      <h3>Collection Highlights</h3>
      <ul class="taf-check">
        <li>Hand-finished colour grading on every print run</li>
        <li>Ideal for pooja rooms, living rooms &amp; meditation spaces</li>
        <li>Available in all sizes up to extra-large 4×6 ft</li>
        <li>Every frame option: floating, aluminium, wooden</li>
      </ul>
    </div>
  </div>

  <div class="taf-cta">
    <h2>Shop the Collection</h2>
    <p>Every purchase directly supports Ananya's studio practice.</p>
    <a class="taf-btn" href="/product-category/direct-from-artists/ananya-sengupta/">View All Artworks</a>
    <a class="taf-btn-alt" href="/artists/">All Artists</a>
  </div>
</div>
HTML;

/* ── Artist: Tanmay Choudhary ───────────────────────────────── */
$bodies['artists/tanmay-choudhary'] = <<<HTML
<div class="taf-page">
  <div class="taf-hero">
    <span class="taf-eyebrow">Artist Profile</span>
    <h1>Tanmay Choudhary</h1>
    <p class="taf-sub">Bold abstracts and contemporary compositions for modern interiors.</p>
  </div>

  <div class="taf-section taf-two">
    <div>
      <h2 class="taf-h2">About the Artist</h2>
      <p class="taf-lead">Tanmay Choudhary works in large formats, exploring colour fields, texture, and contemporary composition. His pieces are designed to anchor a space — the single artwork a room is built around.</p>
      <p>His originals are scanned at archival resolution, so every canvas print preserves the depth and texture of the source painting.</p>
      <p style="margin-top:10px;">
        <span class="taf-badge">Abstract</span>
        <span class="taf-badge">Landscapes</span>
        <span class="taf-badge">Contemporary</span>
        <span class="taf-badge">Large Format</span>
      </p>
    </div>
    <div class="taf-card">
      <span class="taf-ico">✨</span>
      <h3>Collection Highlights</h3>
      <ul class="taf-check">
        <li>Statement pieces for living rooms, offices &amp; studios</li>
        <li>Texture-true archival scanning of every original</li>
        <li>Best in extra-large formats — 3×5 ft and up</li>
        <li>Every frame option: floating, aluminium, wooden</li>
      </ul>
    </div>
  </div>

  <div class="taf-cta">
    <h2>Shop the Collection</h2>
    <p>Every purchase directly supports Tanmay's studio practice.</p>
    <a class="taf-btn" href="/product-category/direct-from-artists/tanmay-choudhary/">View All Artworks</a>
    <a class="taf-btn-alt" href="/artists/">All Artists</a>
  </div>
</div>
HTML;

/* ── Privacy Policy ─────────────────────────────────────────── */
$bodies['privacy-policy'] = <<<HTML
<div class="taf-page taf-legal">
  <div class="taf-hero">
    <span class="taf-eyebrow">Your Data, Protected</span>
    <h1>Privacy Policy</h1>
    <p class="taf-sub">How The Art Framer collects, uses, and protects your information.</p>
  </div>

  <p><span class="taf-updated">Last updated: 15 July 2026</span></p>
  <p>This Privacy Policy explains how <b>The Art Framer</b> ("we", "us") handles your personal information when you visit theartframer.us, create an account, or place an order.</p>

  <h2>1. Information We Collect</h2>
  <ul>
    <li><b>Account &amp; order details</b> — name, email, phone number, shipping and billing address, and order history.</li>
    <li><b>Payment information</b> — processed securely by our payment providers; we never store full card numbers on our servers.</li>
    <li><b>Uploaded content</b> — photos you send us for custom prints or the Try It on Your Wall tool.</li>
    <li><b>Usage data</b> — pages visited, device and browser type, collected via cookies and analytics tools.</li>
    <li><b>Communications</b> — messages you send us by email, contact form, or WhatsApp.</li>
  </ul>

  <h2>2. How We Use Your Information</h2>
  <ul>
    <li>To process and deliver your orders, including custom print production</li>
    <li>To manage your account, wishlist, and order tracking</li>
    <li>To respond to support requests and quote enquiries</li>
    <li>To send order updates, and — only if you opt in — our newsletter</li>
    <li>To improve our website, products, and shopping experience</li>
    <li>To prevent fraud and comply with legal obligations</li>
  </ul>

  <h2>3. Cookies &amp; Analytics</h2>
  <p>We use cookies to keep your cart working, remember preferences (like currency), and understand how the site is used (Google Analytics). You can control cookies through your browser settings; the store's essential cookies are required for checkout to function.</p>

  <h2>4. Sharing Your Information</h2>
  <p>We never sell your personal data. We share it only with service providers who help us run the store:</p>
  <ul>
    <li>Payment processors (to complete your purchase)</li>
    <li>Shipping carriers (to deliver your order)</li>
    <li>Email and analytics providers (to operate the site and send updates)</li>
  </ul>
  <p>Each provider receives only the data needed to perform their service.</p>

  <h2>5. Your Photos &amp; Custom Artwork</h2>
  <p>Photos you upload for custom prints are used solely to produce your order. We never publish, share, or reuse your personal images without your explicit permission. Customer gallery features are opt-in only.</p>

  <h2>6. Data Retention</h2>
  <p>We keep order records as long as required for tax and warranty purposes. You can request deletion of your account and associated personal data at any time (see Your Rights below).</p>

  <h2>7. Your Rights (GDPR &amp; CCPA)</h2>
  <ul>
    <li><b>Access</b> — request a copy of the personal data we hold about you</li>
    <li><b>Correction</b> — ask us to fix inaccurate information</li>
    <li><b>Deletion</b> — ask us to delete your personal data</li>
    <li><b>Opt-out</b> — unsubscribe from marketing at any time via the link in any email</li>
    <li><b>Portability</b> — receive your data in a portable format</li>
  </ul>
  <p>To exercise any of these rights, email <a href="mailto:{$EMAIL}">{$EMAIL}</a>. We respond within 30 days.</p>

  <h2>8. Security</h2>
  <p>The site is served over SSL/TLS encryption, checkout is PCI-compliant via our payment providers, and access to customer data is limited to staff who need it to fulfil orders.</p>

  <h2>9. Children's Privacy</h2>
  <p>Our services are not directed at children under 13, and we do not knowingly collect their personal information.</p>

  <h2>10. Changes to This Policy</h2>
  <p>We may update this policy as the site evolves. Material changes will be announced on this page with an updated date at the top.</p>

  <h2>11. Contact Us</h2>
  <p>Questions about privacy? Email <a href="mailto:{$EMAIL}">{$EMAIL}</a> or call <a href="{$TEL}">{$PHONE}</a>.</p>

  <p class="taf-note">This policy is provided as a starting point and should be reviewed by a legal professional for your jurisdiction.</p>
</div>
HTML;

/* ── Apply ──────────────────────────────────────────────────── */
echo "=== Restyle spec pages (v{$STYLE_VERSION}) ===\n\n";
$done = 0; $skipped = 0;
foreach ( $bodies as $slug => $body ) {
    $page = get_page_by_path( $slug );
    if ( ! $page ) {
        echo "MISS  /{$slug} — page not found (run create-pages first)\n";
        continue;
    }
    if ( get_post_meta( $page->ID, '_taf_styled_page', true ) === $STYLE_VERSION ) {
        echo "SKIP  /{$slug} — already styled v{$STYLE_VERSION}\n";
        $skipped++;
        continue;
    }
    $res = wp_update_post( array(
        'ID'           => $page->ID,
        'post_content' => $body,
        'post_status'  => 'publish',
    ), true );
    if ( is_wp_error( $res ) ) {
        echo "ERROR /{$slug}: " . $res->get_error_message() . "\n";
    } else {
        update_post_meta( $page->ID, '_taf_styled_page', $STYLE_VERSION );
        echo "STYLE /{$slug} -> id {$page->ID}\n";
        $done++;
    }
}
echo "\n=== DONE. Styled {$done}, skipped {$skipped}. ===\n";
