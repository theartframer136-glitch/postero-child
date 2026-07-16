<?php
/**
 * Phase 13 — rich, styled content for the 12 spec pages.
 * Replaces the plain starter copy with structured HTML that uses the
 * .taf-page design system in assets/css/custom.css (v2.9.0).
 * Idempotent (stamps _taf_styled_page). Run: wp eval-file tools/restyle-pages.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$STYLE_VERSION = '13.9';
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


  <div class="taf-section taf-media">
    <div>
      <h2 class="taf-h2">Why Art Makes the Perfect Gift</h2>
      <p>Flowers fade and gadgets get replaced — but wall art becomes part of someone's home. Every time they walk past it, they'll think of you. A gift card means they get to choose a piece that truly speaks to them, in the exact size and frame their wall needs.</p>
      <p>Whether it's a housewarming, wedding, Diwali, or just-because — an Art Framer gift card is a gift of taste, not guesswork.</p>
    </div>
    <img src="https://theartframer.us/wp-content/uploads/2026/06/Radha-Krishna-Canvas-Wall-Art-Placement_Living-Room.webp" alt="Canvas wall art styled in a living room" loading="lazy">
  </div>

  <div class="taf-section">
    <h2 class="taf-h2">Gift Card Questions</h2>
    <div class="taf-grid">
      <div class="taf-card"><span class="taf-ico">📩</span><h3>Can I send it directly?</h3><p>Yes — give us the recipient's email and your message, and we deliver it beautifully designed, on the date you choose.</p></div>
      <div class="taf-card"><span class="taf-ico">💱</span><h3>USD or CAD?</h3><p>Gift cards work in both currencies at checkout — the balance converts automatically at the current store rate.</p></div>
      <div class="taf-card"><span class="taf-ico">🧾</span><h3>Lost the email?</h3><p>No problem. Write to us with the purchaser's name and we'll resend the card with its full remaining balance.</p></div>
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


  <div class="taf-media">
    <img src="https://theartframer.us/wp-content/uploads/2026/03/canvas-texture-horizontal-1536x1024-1-69aa9f4f0a94b.webp" alt="Premium canvas texture from The Art Framer studio" loading="lazy">
    <div>
      <h2 style="margin-top:0;">About Our Studio</h2>
      <p>The Art Framer is an independent print-and-frame studio. Every canvas sold on this website is printed, stretched, and framed in-house on museum-grade materials — which is also why we take full and direct responsibility for the quality of what we ship.</p>
      <p>We serve customers across the United States with free delivery in Delaware, Pennsylvania, Maryland, New Jersey and nearby areas.</p>
    </div>
  </div>

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


  <img class="taf-banner-img" src="https://theartframer.us/wp-content/uploads/2026/06/Radha-Krishna_Wall-Art_-Living-Room.webp" alt="Customer living room with Radha Krishna canvas wall art" loading="lazy">

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


  <div class="taf-section taf-media">
    <div>
      <h2 class="taf-h2">Why Walls Trust Us</h2>
      <ul class="taf-check">
        <li><b>5.0-star average</b> across all published Google reviews</li>
        <li><b>In-house production</b> — no outsourced printing, no quality surprises</li>
        <li><b>Big-wall-safe packaging</b> — canvases arrive intact, guaranteed</li>
        <li><b>Real rooms, real photos</b> — the reviews above come with customer pictures on Google</li>
      </ul>
      <p style="margin-top:14px;">Every review is from a verified customer. We never edit or filter them — what you read is what people actually experienced.</p>
    </div>
    <img src="https://theartframer.us/wp-content/uploads/2026/06/Radha-Krishna-Abstract-Wall-Art_living-room.webp" alt="Abstract canvas print styled in a modern living room" loading="lazy">
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


  <div class="taf-section taf-media">
    <div>
      <h2 class="taf-h2">Your Photo, Museum Quality</h2>
      <p>A phone photo of your family, your wedding, your dog — our studio turns it into gallery-grade wall art. We colour-correct by hand, print on premium canvas with eco-friendly inks, and frame it in the finish you choose.</p>
      <p>Not sure about sizing? Send the photo and a picture of your wall — we'll recommend the size that fits the space best.</p>
    </div>
    <img src="https://theartframer.us/wp-content/uploads/2026/03/personalized-prints-portrait-69aea49e486c6.webp" alt="Personalised portrait canvas print" loading="lazy">
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


  <div class="taf-section">
    <h2 class="taf-h2">See the Difference</h2>
    <div class="taf-imgrow">
      <figure><img src="https://theartframer.us/wp-content/uploads/2026/06/TAF-RADHA-KRISHNA-18530-room-1.jpg" alt="Framed canvas styled in a room" loading="lazy"><figcaption>Styled in a real room</figcaption></figure>
      <figure><img src="https://theartframer.us/wp-content/uploads/2026/03/kids-room-decor.webp" alt="Kids room canvas decor" loading="lazy"><figcaption>Kids room custom art</figcaption></figure>
      <figure><img src="https://theartframer.us/wp-content/uploads/2026/03/6-color-frame.webp" alt="Six frame colour options" loading="lazy"><figcaption>Choose from 6 frame finishes</figcaption></figure>
    </div>
  </div>

  <div class="taf-section">
    <h2 class="taf-h2">Tips for the Best Result</h2>
    <ul class="taf-check">
      <li>Send the original photo, not a screenshot — resolution matters</li>
      <li>Daylight photos print truer than indoor evening shots</li>
      <li>For collages, 5–9 photos works beautifully on large canvases</li>
      <li>Tell us the room — we'll match the frame finish to your décor</li>
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


  <div class="taf-section taf-media">
    <img src="https://theartframer.us/wp-content/uploads/2026/03/6-color-frame.webp" alt="Frame finishes printed and assembled in-house" loading="lazy">
    <div>
      <h2 class="taf-h2">Where the Savings Come From</h2>
      <p>Galleries mark up 3–4×. Marketplaces take 15–30% in fees. We do neither — your canvas goes straight from our printer to your wall.</p>
      <ul class="taf-check">
        <li>In-house printing — no third-party production costs</li>
        <li>Direct-to-you shipping — no retail middlemen</li>
        <li>Made to order — no warehouse of unsold stock priced into your art</li>
      </ul>
    </div>
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


  <div class="taf-section taf-media">
    <div>
      <h2 class="taf-h2">Sharing Ideas</h2>
      <p>The best referrals happen naturally — when guests ask "where did you get that?" Here are the easiest ways to spread the word:</p>
      <ul class="taf-check">
        <li>Post your wall on Instagram and tag us — we'll reshare it</li>
        <li>Forward your order confirmation with a "you'd love these" note</li>
        <li>Send friends to <b>theartframer.us</b> and have them mention your name at checkout</li>
        <li>House-warming coming up? Gift a <a href="/gift-cards/">gift card</a> and earn on their next order too</li>
      </ul>
    </div>
    <img src="https://theartframer.us/wp-content/uploads/2026/03/Living-Room-Decor.webp" alt="Living room decorated with canvas wall art" loading="lazy">
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


  <div class="taf-section taf-media">
    <img src="https://theartframer.us/wp-content/uploads/2026/03/Cafe-Decor.webp" alt="Cafe interior decorated with canvas art" loading="lazy">
    <div>
      <h2 class="taf-h2">Content That Converts</h2>
      <p>Wall art is one of the most visual products on the internet — perfect for reels, room tours, before/after transformations, and styling guides. Our affiliates do best with:</p>
      <ul class="taf-check">
        <li>Room makeover videos featuring our canvases</li>
        <li>"How to choose art for your wall" guides using our <a href="/try-on-wall/">Try It on Your Wall</a> tool</li>
        <li>Festive gift guides linking our <a href="/gift-cards/">gift cards</a></li>
        <li>Café &amp; office interior case studies</li>
      </ul>
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


  <div class="taf-section">
    <h2 class="taf-h2">Formats We Print</h2>
    <div class="taf-imgrow">
      <figure><img src="https://theartframer.us/wp-content/uploads/2026/03/backdrops.webp" alt="Custom event backdrop" loading="lazy"><figcaption>Event Backdrops</figcaption></figure>
      <figure><img src="https://theartframer.us/wp-content/uploads/2026/03/banner-stand.webp" alt="Roll-up banner stand" loading="lazy"><figcaption>Roll-Up Stands</figcaption></figure>
      <figure><img src="https://theartframer.us/wp-content/uploads/2026/03/vinyl-banner-1.webp" alt="Vinyl banner printing" loading="lazy"><figcaption>Vinyl Banners</figcaption></figure>
      <figure><img src="https://theartframer.us/wp-content/uploads/2026/03/fabric-cloth-banners.webp" alt="Fabric cloth banner" loading="lazy"><figcaption>Fabric Banners</figcaption></figure>
      <figure><img src="https://theartframer.us/wp-content/uploads/2026/03/fence-banner.webp" alt="Fence banner for construction sites" loading="lazy"><figcaption>Fence Banners</figcaption></figure>
    </div>
    <p class="taf-note">All formats available in custom sizes, with weatherproof inks for outdoor use and free design templates.</p>
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


  <img class="taf-banner-img" src="https://theartframer.us/wp-content/uploads/2026/04/works-of-artist-69da11c3d9a55.webp" alt="Original works by The Art Framer artists" loading="lazy">

  <div class="taf-section">
    <h2 class="taf-h2">Meet Our Artists</h2>
    <p class="taf-lead">This list updates automatically — every artist added under Products → Categories → Direct from Artists appears here.</p>
    [af_artists]
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


  <div class="taf-section taf-media">
    <img src="https://theartframer.us/wp-content/uploads/2026/04/discover-original-works-of-artists-1-69d75a56972fb-69d75ac9d6589.webp" alt="Discover original works of artists" loading="lazy">
    <div>
      <h2 class="taf-h2">How the Program Works</h2>
      <ol class="taf-steps">
        <li><b>Artists submit originals</b> — paintings, digital art, or mixed media.</li>
        <li><b>We scan &amp; print</b> — archival resolution, artist-approved colour proofs.</li>
        <li><b>You buy direct</b> — every sale pays the artist a transparent share.</li>
      </ol>
    </div>
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


  <div class="taf-section">
    <h2 class="taf-h2">From the Collection</h2>
    <div class="taf-imgrow">
      <figure><img src="https://theartframer.us/wp-content/uploads/2026/06/Radha-Krishna-Face-To-Face-wall-Art.webp" alt="Radha Krishna face to face wall art by Ananya Sengupta" loading="lazy"><figcaption>Face to Face</figcaption></figure>
      <figure><img src="https://theartframer.us/wp-content/uploads/2026/06/Devotional-Radha-Krishna-Canvas-Wall-Art.webp" alt="Devotional Radha Krishna canvas art" loading="lazy"><figcaption>Devotional Series</figcaption></figure>
      <figure><img src="https://theartframer.us/wp-content/uploads/2026/02/Radha-Krishna-Abstract-flute.webp" alt="Radha Krishna abstract flute artwork" loading="lazy"><figcaption>The Flute (Abstract)</figcaption></figure>
    </div>
  </div>

  <div class="taf-section">
    <h2 class="taf-h2">Signature Style</h2>
    <p class="taf-lead">Ananya's work is known for its layered symbolism — lotus motifs, peacock feathers, and the interplay of gold on deep indigo. Collectors often pair her devotional pieces with warm lighting and natural wood frames, where the gold detailing catches the evening light beautifully.</p>
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


  <div class="taf-section">
    <h2 class="taf-h2">From the Collection</h2>
    <div class="taf-imgrow">
      <figure><img src="https://theartframer.us/wp-content/uploads/2026/06/Radha-Krishna-Abstract-Wall-Art.webp" alt="Abstract wall art by Tanmay Choudhary" loading="lazy"><figcaption>Abstract Devotion</figcaption></figure>
      <figure><img src="https://theartframer.us/wp-content/uploads/2026/03/Cafe-Decor.webp" alt="Contemporary cafe decor artwork" loading="lazy"><figcaption>Café Series</figcaption></figure>
      <figure><img src="https://theartframer.us/wp-content/uploads/2026/03/Living-Room-Decor.webp" alt="Modern living room composition" loading="lazy"><figcaption>Living Room Statements</figcaption></figure>
    </div>
  </div>

  <div class="taf-section">
    <h2 class="taf-h2">Signature Style</h2>
    <p class="taf-lead">Tanmay builds his compositions around colour fields and bold negative space — art that holds a wall without shouting. His large-format pieces are favourites for offices, studios, and minimalist interiors where a single artwork sets the tone for the whole room.</p>
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


  <div class="taf-media">
    <img src="https://theartframer.us/wp-content/uploads/2026/02/The-Art-Framer-1.webp" alt="The Art Framer studio branding" loading="lazy">
    <div>
      <h2 style="margin-top:0;">Our Commitment</h2>
      <p>We collect only what we need to print, frame, and deliver your order — nothing more. Your photos are used solely for your prints, your email is never sold, and you can ask us to delete your data at any time with a single email.</p>
    </div>
  </div>

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


/* ── Help & Support ─────────────────────────────────────────── */
$bodies['help-support'] = <<<HTML
<div class="taf-page">
  <div class="taf-hero">
    <span class="taf-eyebrow">We're Here For You</span>
    <h1>Help &amp; Support</h1>
    <p class="taf-sub">Real people, fast answers — before, during, and after your order.</p>
  </div>

  <div class="taf-section">
    <h2 class="taf-h2">Talk to Us</h2>
    <p class="taf-lead">Choose whichever channel suits you — every message reaches the same small studio team.</p>
    <div class="taf-grid">
      <div class="taf-card"><span class="taf-ico">📞</span><h3>Call or WhatsApp</h3><p><a href="{$TEL}">{$PHONE}</a><br>Quickest for sizing advice, order changes, and bulk quotes.</p></div>
      <div class="taf-card"><span class="taf-ico">✉️</span><h3>Email Us</h3><p><a href="mailto:{$EMAIL}">{$EMAIL}</a><br>Best for custom photo orders and anything with attachments. We reply within 24 hours.</p></div>
      <div class="taf-card"><span class="taf-ico">📝</span><h3>Contact Form</h3><p><a href="/contact/">theartframer.us/contact</a><br>Leave the details and we'll get back to you — no account needed.</p></div>
    </div>
  </div>

  <div class="taf-section">
    <h2 class="taf-h2">Common Topics</h2>
    <div class="taf-grid">
      <div class="taf-card"><span class="taf-ico">📦</span><h3>Track Your Order</h3><p>See where your canvas is right now.</p><p style="margin-top:12px;"><a class="taf-btn" href="/track-your-order/">Track Order</a></p></div>
      <div class="taf-card"><span class="taf-ico">🚚</span><h3>Shipping &amp; Delivery</h3><p>Timelines, free-delivery areas, and packaging.</p><p style="margin-top:12px;"><a class="taf-btn" href="/shipping-delivery/">Read More</a></p></div>
      <div class="taf-card"><span class="taf-ico">🔄</span><h3>Returns &amp; Exchanges</h3><p>Something not right? Here's how we fix it.</p><p style="margin-top:12px;"><a class="taf-btn" href="/returns-exchanges/">Read More</a></p></div>
      <div class="taf-card"><span class="taf-ico">💳</span><h3>Refund Policy</h3><p>When and how refunds are processed.</p><p style="margin-top:12px;"><a class="taf-btn" href="/refund-policy/">Read More</a></p></div>
      <div class="taf-card"><span class="taf-ico">❓</span><h3>FAQs</h3><p>Quick answers to the questions we hear most.</p><p style="margin-top:12px;"><a class="taf-btn" href="/faqs/">Browse FAQs</a></p></div>
      <div class="taf-card"><span class="taf-ico">🖼️</span><h3>Custom Orders</h3><p>Turn your own photo into premium wall art.</p><p style="margin-top:12px;"><a class="taf-btn" href="/customize-your-picture/">Start Here</a></p></div>
    </div>
  </div>

  <div class="taf-section taf-media">
    <img src="https://theartframer.us/wp-content/uploads/2026/03/Choosing-the-best-canvas-prints-in-Delaware.webp" alt="Choosing the best canvas prints with The Art Framer" loading="lazy">
    <div>
      <h2 class="taf-h2">Talk to the Makers</h2>
      <p>When you contact The Art Framer, you're not talking to a call centre — you're talking to the studio that prints, stretches, and frames your order. That means real answers about sizes, frames, colours, and walls, from people who work with canvas every day.</p>
      <ul class="taf-check">
        <li>Sizing advice for your exact wall — send a photo, we'll recommend</li>
        <li>Frame &amp; colour guidance matched to your décor</li>
        <li>Order changes possible any time before printing starts</li>
        <li>Bulk &amp; corporate quotes within one business day</li>
      </ul>
    </div>
  </div>

  <div class="taf-section">
    <h2 class="taf-h2">How Support Works</h2>
    <ol class="taf-steps">
      <li><b>Reach out</b> — call, WhatsApp, email, or the contact form, whatever is easiest.</li>
      <li><b>We investigate</b> — order issues are checked against production and shipping records the same day.</li>
      <li><b>We make it right</b> — replacement, reprint, or refund. A damaged canvas is always on us, never on you.</li>
    </ol>
  </div>

  <div class="taf-section">
    <h2 class="taf-h2">Quick Answers</h2>
    <div class="taf-grid">
      <div class="taf-card"><span class="taf-ico">⏱️</span><h3>How fast do you reply?</h3><p>Calls and WhatsApp — usually right away during business hours. Email and the contact form — within 24 hours, often much sooner.</p></div>
      <div class="taf-card"><span class="taf-ico">📐</span><h3>Can you help me pick a size?</h3><p>Yes — send a photo of your wall and its rough width. We'll recommend a size, or use <a href="/try-on-wall/">Try It on Your Wall</a> to preview it yourself.</p></div>
      <div class="taf-card"><span class="taf-ico">📦</span><h3>My canvas arrived damaged</h3><p>Send us a photo of the damage and the packaging within 48 hours — we reprint and reship at no cost, no return needed.</p></div>
      <div class="taf-card"><span class="taf-ico">✏️</span><h3>Can I change my order?</h3><p>Any time before printing starts — contact us with your order number and the change you need.</p></div>
    </div>
  </div>

  <div class="taf-cta">
    <h2>Still Need a Hand?</h2>
    <p>Tell us what's going on — we'll sort it out quickly.</p>
    <a class="taf-btn" href="mailto:{$EMAIL}?subject=Support%20Request">Email Support</a>
    <a class="taf-btn-alt" href="{$TEL}">Call {$PHONE}</a>
  </div>
</div>
HTML;

/* ── FAQs — same questions as the product detail pages ─────── */
$bodies['faqs'] = <<<HTML
<div class="taf-page">
  <div class="taf-hero">
    <span class="taf-eyebrow">Quick Answers</span>
    <h1>Frequently Asked Questions</h1>
    <p class="taf-sub">Everything about our canvas, printing, delivery, and support — the same answers you'll find on every product page.</p>
  </div>

  <div class="taf-section">
    <h2 class="taf-h2">Canvas, Printing &amp; Delivery</h2>
    <p class="taf-lead">Click any question to expand the answer.</p>
    [af_faqs]
  </div>

  <div class="taf-section">
    <h2 class="taf-h2">Didn't Find Your Answer?</h2>
    <div class="taf-grid">
      <div class="taf-card"><span class="taf-ico">🆘</span><h3>Help &amp; Support</h3><p>Contact channels, common topics, and how support works.</p><p style="margin-top:12px;"><a class="taf-btn" href="/help-support/">Get Help</a></p></div>
      <div class="taf-card"><span class="taf-ico">🚚</span><h3>Shipping &amp; Delivery</h3><p>Timelines, free-delivery areas, and packaging details.</p><p style="margin-top:12px;"><a class="taf-btn" href="/shipping-delivery/">Read More</a></p></div>
      <div class="taf-card"><span class="taf-ico">🔄</span><h3>Returns &amp; Refunds</h3><p>Our return process and when refunds are issued.</p><p style="margin-top:12px;"><a class="taf-btn" href="/returns-exchanges/">Read More</a></p></div>
    </div>
  </div>

  <div class="taf-cta">
    <h2>Ask Us Directly</h2>
    <p>Real people, real answers — usually within the hour during business time.</p>
    <a class="taf-btn" href="mailto:{$EMAIL}?subject=Question">Email {$EMAIL}</a>
    <a class="taf-btn-alt" href="{$TEL}">Call {$PHONE}</a>
  </div>
</div>
HTML;

/* ── Contact Us — working form + real studio details ────────── */
$bodies['contact'] = <<<HTML
<div class="taf-page">
  <div class="taf-hero">
    <span class="taf-eyebrow">Get In Touch</span>
    <h1>Contact Us</h1>
    <p class="taf-sub">Questions, custom orders, bulk quotes — send us a message and we'll reply within 24 hours.</p>
  </div>

  <div class="taf-section taf-two">
    <div>
      <h2 class="taf-h2">Send a Message</h2>
      <p class="taf-lead">Fill in the form and it lands straight in our studio inbox.</p>
      [af_contact_form]
    </div>
    <div>
      <h2 class="taf-h2">Reach Us Directly</h2>
      <div class="taf-grid" style="grid-template-columns:1fr;">
        <div class="taf-card"><span class="taf-ico">📞</span><h3>Call or WhatsApp</h3><p><a href="{$TEL}">{$PHONE}</a><br>Quickest for sizing advice and order changes.</p></div>
        <div class="taf-card"><span class="taf-ico">✉️</span><h3>Email</h3><p><a href="mailto:{$EMAIL}">{$EMAIL}</a><br>Best for custom photo orders and attachments.</p></div>
        <div class="taf-card"><span class="taf-ico">📍</span><h3>Studio &amp; Delivery</h3><p>Operating from Delaware, USA.<br>Free delivery: Delaware, Pennsylvania, Maryland, New Jersey &amp; nearby areas. Nationwide shipping available.</p></div>
        <div class="taf-card"><span class="taf-ico">🕘</span><h3>Response Times</h3><p>Calls &amp; WhatsApp — right away in business hours.<br>Form &amp; email — within 24 hours.</p></div>
      </div>
    </div>
  </div>

  <div class="taf-section">
    <h2 class="taf-h2">Find Us</h2>
    <p class="taf-lead">Our studio operates from Delaware, USA — with free delivery across Delaware, Pennsylvania, Maryland, New Jersey and nearby areas.</p>
    <div class="taf-map">
      <iframe src="https://www.google.com/maps?q=The%20Art%20Framer%2C%20Delaware%2C%20USA&z=9&output=embed"
              loading="lazy" allowfullscreen referrerpolicy="no-referrer-when-downgrade"
              title="The Art Framer — Delaware, USA"></iframe>
      <div class="taf-map-card">
        <h3>📍 The Art Framer</h3>
        <p>Delaware, USA<br>Mon–Sat: 9am – 7pm ET</p>
        <p><a href="{$TEL}">{$PHONE}</a><br><a href="mailto:{$EMAIL}">{$EMAIL}</a></p>
        <a class="taf-map-btn" href="https://www.google.com/maps/search/?api=1&query=The+Art+Framer+Delaware+USA" target="_blank" rel="noopener">Get Directions ➜</a>
      </div>
    </div>
  </div>

  <div class="taf-section">
    <h2 class="taf-h2">Before You Write…</h2>
    <div class="taf-grid">
      <div class="taf-card"><span class="taf-ico">📦</span><h3>Where's my order?</h3><p>Check delivery status any time on the tracking page.</p><p style="margin-top:12px;"><a class="taf-btn" href="/track-your-order/">Track Order</a></p></div>
      <div class="taf-card"><span class="taf-ico">❓</span><h3>Quick questions</h3><p>Materials, fading, delivery areas — the FAQs cover the most common ones.</p><p style="margin-top:12px;"><a class="taf-btn" href="/faqs/">Browse FAQs</a></p></div>
      <div class="taf-card"><span class="taf-ico">🖼️</span><h3>Custom prints</h3><p>Turning your photo into wall art? Start here for sizes, frames, and mockups.</p><p style="margin-top:12px;"><a class="taf-btn" href="/customize-your-picture/">Custom Orders</a></p></div>
    </div>
  </div>
</div>
HTML;

/* ── Shipping & Delivery ────────────────────────────────────── */
$bodies['shipping-delivery'] = <<<HTML
<div class="taf-page">
  <div class="taf-hero">
    <span class="taf-eyebrow">Made to Order, Delivered with Care</span>
    <h1>Shipping &amp; Delivery</h1>
    <p class="taf-sub">Every piece is printed and framed just for you — here's exactly what happens after you order.</p>
  </div>

  <div class="taf-section">
    <h2 class="taf-h2">From Our Studio to Your Wall</h2>
    <ol class="taf-steps">
      <li><b>Order placed</b> — you get an instant confirmation email with your order number.</li>
      <li><b>Production (3–5 business days)</b> — your canvas is printed, stretched, and framed to order. Custom and large-format pieces may need a little extra time.</li>
      <li><b>Dispatch &amp; tracking</b> — the moment your order ships, tracking details arrive by email. Follow it any time on our <a href="/track-your-order/">Track Your Order</a> page.</li>
      <li><b>Delivery (5–10 business days)</b> — standard delivery anywhere in the United States after dispatch.</li>
    </ol>
  </div>

  <div class="taf-section">
    <h2 class="taf-h2">Shipping Charges</h2>
    <div class="taf-grid">
      <div class="taf-card"><span class="taf-ico">🎁</span><h3>Free Delivery</h3><p>Free across major serviceable US locations — including <b>Delaware, Pennsylvania, Maryland, New Jersey</b> and nearby areas.</p></div>
      <div class="taf-card"><span class="taf-ico">🇺🇸</span><h3>Rest of the USA</h3><p>Nationwide shipping available everywhere else — charges are calculated automatically at checkout based on size and destination.</p></div>
      <div class="taf-card"><span class="taf-ico">🌎</span><h3>Outside the US?</h3><p>International orders are handled case by case — <a href="/contact/">contact us</a> with your address and we'll quote shipping before you pay anything.</p></div>
    </div>
  </div>

  <div class="taf-section">
    <h2 class="taf-h2">Will It Be Free For You?</h2>
    [af_delivery_checker]
  </div>

  <div class="taf-section taf-media">
    <img src="https://theartframer.us/wp-content/uploads/2026/02/Try-It-on-Your-Wall.jpg" alt="Canvas artwork ready for delivery by The Art Framer" loading="lazy">
    <div>
      <h2 class="taf-h2">Packed for Big Walls</h2>
      <p>Large canvases are the most damage-prone parcels in shipping — so we over-engineer the packaging.</p>
      <ul class="taf-check">
        <li>Corner protectors on every frame</li>
        <li>Moisture-resistant wrap around the canvas face</li>
        <li>Double-walled boxes sized to the artwork</li>
        <li><b>No Broken Canvas guarantee</b> — damaged in transit? We reprint and reship free, no return needed</li>
      </ul>
    </div>
  </div>

  <div class="taf-section">
    <h2 class="taf-h2">Good to Know</h2>
    <div class="taf-grid">
      <div class="taf-card"><span class="taf-ico">📦</span><h3>Multi-piece orders</h3><p>Sets and multiple artworks may arrive in separate parcels a day or two apart — each has its own tracking.</p></div>
      <div class="taf-card"><span class="taf-ico">✏️</span><h3>Address changes</h3><p>Need to redirect an order? Contact us before dispatch and we'll update it — after dispatch we'll help you reroute with the carrier.</p></div>
      <div class="taf-card"><span class="taf-ico">⏱️</span><h3>In a hurry?</h3><p>Tell us your deadline — events and gifts can often be prioritised in production. <a href="/contact/">Ask us first</a>.</p></div>
    </div>
  </div>

  <div class="taf-cta">
    <h2>Questions About Your Delivery?</h2>
    <p>We're happy to check production status, tracking, or timelines for you.</p>
    <a class="taf-btn" href="/track-your-order/">Track Your Order</a>
    <a class="taf-btn-alt" href="mailto:{$EMAIL}?subject=Delivery%20Question">Email {$EMAIL}</a>
  </div>
</div>
HTML;

/* ── Returns & Exchanges ────────────────────────────────────── */
$bodies['returns-exchanges'] = <<<HTML
<div class="taf-page">
  <div class="taf-hero">
    <span class="taf-eyebrow">Your Satisfaction Matters</span>
    <h1>Returns &amp; Exchanges</h1>
    <p class="taf-sub">Most pieces are made to order just for you — here's exactly how returns, exchanges, and damage claims work.</p>
  </div>

  <div class="taf-section">
    <h2 class="taf-h2">How to Request a Return</h2>
    <ol class="taf-steps">
      <li><b>Email us within 7 days of delivery</b> — send your order number and clear photos of the issue to <a href="mailto:{$EMAIL}?subject=Return%20Request">{$EMAIL}</a>, or use the <a href="/contact/">contact form</a> (choose "Returns &amp; Refunds").</li>
      <li><b>We review the same day</b> — your photos are checked against production and packing records; no forms, no waiting on hold.</li>
      <li><b>We make it right</b> — a free reprint, an exchange, or a refund per our <a href="/refund-policy/">Refund Policy</a>. Damaged items never need to be shipped back.</li>
    </ol>
  </div>

  <div class="taf-section taf-two">
    <div>
      <h2 class="taf-h2">Eligible for Return</h2>
      <ul class="taf-check">
        <li>Items that arrive <b>damaged</b> in transit</li>
        <li>Items with a <b>printing or framing defect</b></li>
        <li><b>Incorrect items</b> — wrong artwork, size, or frame delivered</li>
        <li>Reported within <b>7 days of delivery</b></li>
        <li>Unused and in the original packaging</li>
      </ul>
    </div>
    <div>
      <h2 class="taf-h2">Not Returnable</h2>
      <div class="taf-grid" style="grid-template-columns:1fr;">
        <div class="taf-card"><span class="taf-ico">🖼️</span><h3>Personalised &amp; custom-sized art</h3><p>Made uniquely for you, so they can't be resold — unless they arrive damaged or defective, in which case we reprint free.</p></div>
        <div class="taf-card"><span class="taf-ico">💾</span><h3>Digital downloads</h3><p>Delivered instantly and can't be "returned" — but if a file has an issue, we'll fix or replace it right away.</p></div>
      </div>
    </div>
  </div>

  <div class="taf-section taf-media">
    <img src="https://theartframer.us/wp-content/uploads/2026/02/ChatGPT-Image-Feb-13-2026-12_11_29-PM.webp" alt="Large framed Radha Krishna canvas delivered in perfect condition" loading="lazy">
    <div>
      <h2 class="taf-h2">The No Broken Canvas Guarantee</h2>
      <p>A damaged canvas is always on us — never on you.</p>
      <ul class="taf-check">
        <li>Send photos of the damage and packaging within 48 hours of delivery</li>
        <li>We reprint and reship <b>completely free</b></li>
        <li><b>No return shipping</b> — keep or dispose of the damaged piece</li>
        <li>Priority production so the replacement ships fast</li>
      </ul>
    </div>
  </div>

  <div class="taf-section">
    <h2 class="taf-h2">Good to Know</h2>
    <div class="taf-grid">
      <div class="taf-card"><span class="taf-ico">🔄</span><h3>Want a different size or frame?</h3><p>Contact us before your order enters production and we'll change it free. After delivery, exchanges are assessed case by case — just ask.</p></div>
      <div class="taf-card"><span class="taf-ico">💳</span><h3>When is the refund paid?</h3><p>Approved refunds go back to your original payment method — see the <a href="/refund-policy/">Refund Policy</a> for timelines.</p></div>
      <div class="taf-card"><span class="taf-ico">📸</span><h3>Why photos?</h3><p>Clear photos of the item and packaging let us approve most claims the same day — and help us hold our carriers accountable.</p></div>
    </div>
  </div>

  <div class="taf-cta">
    <h2>Start a Return or Exchange</h2>
    <p>Have your order number and photos ready — we'll take it from there.</p>
    <a class="taf-btn" href="mailto:{$EMAIL}?subject=Return%20Request">Email a Return Request</a>
    <a class="taf-btn-alt" href="/contact/">Use the Contact Form</a>
  </div>
</div>
HTML;

/* ── Refund Policy ──────────────────────────────────────────── */
$bodies['refund-policy'] = <<<HTML
<div class="taf-page">
  <div class="taf-hero">
    <span class="taf-eyebrow">Fair &amp; Fast</span>
    <h1>Refund Policy</h1>
    <p class="taf-sub">Approved refunds go back to your original payment method within 5–7 business days — tracked and confirmed at every step.</p>
  </div>

  <div class="taf-section">
    <div class="taf-stats">
      <div class="taf-stat"><span class="num">5–7</span><span class="lbl">Business days to refund</span></div>
      <div class="taf-stat"><span class="num">100%</span><span class="lbl">Original payment method</span></div>
      <div class="taf-stat"><span class="num">\$0</span><span class="lbl">Restocking fees</span></div>
      <div class="taf-stat"><span class="num">48h</span><span class="lbl">Damage claim window</span></div>
    </div>
  </div>

  <div class="taf-section">
    <h2 class="taf-h2">Track Your Refund's Journey</h2>
    <p class="taf-lead">Every refund follows the same four steps — and you're emailed at each one.</p>
    <ul class="taf-track">
      <li><b>Request Approved</b><span>Your return request is reviewed — usually the same day.</span></li>
      <li><b>Item Inspected</b><span>The approved return is received and checked by our studio.</span></li>
      <li><b>Refund Issued</b><span>Sent to your original payment method within 5–7 business days.</span></li>
      <li><b>In Your Account</b><span>Bank posting can add 2–5 days depending on your card issuer.</span></li>
    </ul>
  </div>

  <div class="taf-section">
    <h2 class="taf-h2">What Happens in Each Case</h2>
    <table class="taf-table">
      <thead><tr><th>Situation</th><th>Replacement</th><th>Full Refund</th><th>What We Need</th></tr></thead>
      <tbody>
        <tr><td>🔨 Arrived damaged</td><td><span class="taf-yes">✓ Free</span></td><td><span class="taf-yes">✓ Yes</span></td><td>Photos of item &amp; packaging within 48 hours — no return shipping</td></tr>
        <tr><td>🖨️ Print or frame defect</td><td><span class="taf-yes">✓ Free</span></td><td><span class="taf-yes">✓ Yes</span></td><td>Photos of the defect within 7 days of delivery</td></tr>
        <tr><td>📦 Wrong item delivered</td><td><span class="taf-yes">✓ Free</span></td><td><span class="taf-yes">✓ Yes</span></td><td>Order number and a photo of what arrived</td></tr>
        <tr><td>💭 Changed my mind</td><td><span class="taf-no">✗ Custom items</span></td><td><span class="taf-no">✗ Custom items</span></td><td>Made-to-order pieces can't be restocked — contact us before production starts and we'll change or cancel free</td></tr>
        <tr><td>💾 Digital download issue</td><td><span class="taf-yes">✓ File replaced</span></td><td>Case by case</td><td>Tell us what's wrong — we fix or replace the file right away</td></tr>
      </tbody>
    </table>
  </div>

  <div class="taf-section taf-two">
    <div>
      <h2 class="taf-h2">Good to Know</h2>
      <ul class="taf-check">
        <li>Refunds always return to the <b>card or account you paid with</b> — no store-credit-only tricks</li>
        <li>Gift card purchases are refunded back to the <b>gift card balance</b></li>
        <li>You're notified by email at <b>every step</b>: approval, inspection, and issue</li>
        <li>Full return rules are in our <a href="/returns-exchanges/">Returns &amp; Exchanges</a> policy</li>
      </ul>
    </div>
    <div class="taf-card">
      <span class="taf-ico">⏳</span>
      <h3>Refund taking longer?</h3>
      <p>First check with your bank or card issuer — pending refunds often sit on their side for a few days. Still nothing after 10 business days? Send us your order number and we'll trace it with the payment processor right away.</p>
    </div>
  </div>

  <div class="taf-cta">
    <h2>Questions About a Refund?</h2>
    <p>Send your order number and we'll check the status immediately.</p>
    <a class="taf-btn" href="mailto:{$EMAIL}?subject=Refund%20Question">Email {$EMAIL}</a>
    <a class="taf-btn-alt" href="{$TEL}">Call {$PHONE}</a>
  </div>
</div>
HTML;

/* ── Safe & Easy Payments ───────────────────────────────────── */
$bodies['safe-easy-payments'] = <<<HTML
<div class="taf-page">
  <div class="taf-hero">
    <span class="taf-eyebrow">Shop With Confidence</span>
    <h1>Safe &amp; Easy Payments</h1>
    <p class="taf-sub">Encrypted checkout, trusted payment providers, and zero card details stored on our servers.</p>
  </div>

  <div class="taf-section">
    <h2 class="taf-h2">Ways to Pay</h2>
    <p class="taf-lead">Choose whichever suits you at checkout — every option is processed securely.</p>
    <div class="taf-grid">
      <div class="taf-card"><span class="taf-ico">💳</span><h3>Card Payments</h3><p>Credit and debit cards processed through our PCI-compliant secure checkout — your card number never touches our servers.</p></div>
      <div class="taf-card"><span class="taf-ico">⚡</span><h3>Zelle</h3><p>Fast, fee-free bank transfer for US customers — ideal for custom and bulk orders. Payment details are shared at checkout.</p></div>
      <div class="taf-card"><span class="taf-ico">💵</span><h3>Cash on Delivery</h3><p>Available in select local delivery areas — pay when your artwork arrives at your door.</p></div>
      <div class="taf-card"><span class="taf-ico">🎁</span><h3>Gift Cards</h3><p>Redeem an Art Framer <a href="/gift-cards/">gift card</a> at checkout — combinable with sale prices, any remaining balance stays on the card.</p></div>
    </div>
  </div>

  <div class="taf-section taf-two">
    <div>
      <h2 class="taf-h2">How We Keep Payments Safe</h2>
      <ul class="taf-check">
        <li><b>SSL/TLS encryption</b> on every page — look for the padlock in your browser</li>
        <li><b>PCI-compliant processing</b> — card data is handled by certified payment providers, never by our website directly</li>
        <li><b>No card storage</b> — we never save your card number, CVV, or banking credentials</li>
        <li><b>Fraud screening</b> — unusual transactions are automatically flagged and verified</li>
        <li><b>Two currencies</b> — pay in USD or CAD; switch any time using the currency selector in the header</li>
      </ul>
    </div>
    <div class="taf-card">
      <span class="taf-ico">🛡️</span>
      <h3>Our Payment Promise</h3>
      <p>If anything ever looks wrong with a charge — a duplicate, a wrong amount, an unrecognised line — contact us first. We resolve payment issues directly and quickly, and approved refunds go back to your original payment method within 5–7 business days per our <a href="/refund-policy/">Refund Policy</a>.</p>
    </div>
  </div>

  <div class="taf-section">
    <h2 class="taf-h2">Checkout in Three Steps</h2>
    <ol class="taf-steps">
      <li><b>Review your cart</b> — sizes, frames, and quantities, with prices shown in your chosen currency.</li>
      <li><b>Enter delivery details</b> — your address is used for shipping and never sold or shared beyond the carrier.</li>
      <li><b>Pay securely</b> — pick your payment method and confirm. An order confirmation lands in your inbox immediately.</li>
    </ol>
  </div>

  <div class="taf-section">
    <h2 class="taf-h2">Common Payment Questions</h2>
    <div class="taf-grid">
      <div class="taf-card"><span class="taf-ico">🧾</span><h3>Will I get an invoice?</h3><p>Yes — an order confirmation is emailed instantly, and a full invoice accompanies your delivery.</p></div>
      <div class="taf-card"><span class="taf-ico">💱</span><h3>USD or CAD?</h3><p>Both. Prices convert automatically when you switch currency — the charge is made in the currency you check out with.</p></div>
      <div class="taf-card"><span class="taf-ico">🔁</span><h3>How do refunds work?</h3><p>Back to your original payment method within 5–7 business days — details in our <a href="/refund-policy/">Refund Policy</a>.</p></div>
    </div>
    <p style="margin-top:18px;">More questions? See the full <a href="/faqs/">FAQs</a> or <a href="/contact/">contact us</a> directly.</p>
  </div>

  <div class="taf-cta">
    <h2>Ready When You Are</h2>
    <p>Browse the collection and check out securely in minutes.</p>
    <a class="taf-btn" href="/shop/">Shop the Collection</a>
    <a class="taf-btn-alt" href="/contact/">Ask a Payment Question</a>
  </div>
</div>
HTML;

/* ── Content & Ethics Policy ────────────────────────────────── */
$bodies['content-ethics-policy'] = <<<HTML
<div class="taf-page">
  <div class="taf-hero">
    <span class="taf-eyebrow">Art With Integrity</span>
    <h1>Content &amp; Ethics Policy</h1>
    <p class="taf-sub">Respectful, authentic, and culturally sensitive artwork — the standards every piece we sell must meet.</p>
  </div>

  <div class="taf-section">
    <h2 class="taf-h2">Our Commitments</h2>
    <div class="taf-grid">
      <div class="taf-card"><span class="taf-ico">🙏</span><h3>Cultural Respect</h3><p>Our spiritual and cultural artworks are created with reverence. Devotional imagery — Radha Krishna, Shiva, Buddha, Sikh art, and more — is rendered to honour the traditions it represents, never to trivialise them.</p></div>
      <div class="taf-card"><span class="taf-ico">🎨</span><h3>Artist Rights</h3><p>We work directly with artists and respect their intellectual property. Original creators are credited on their work and fairly compensated on every sale — see our <a href="/artists/">Artists &amp; Creators</a> program.</p></div>
      <div class="taf-card"><span class="taf-ico">✅</span><h3>Authenticity</h3><p>No misleading imagery. Product previews represent the actual artwork you will receive — same composition, same colours, same finish.</p></div>
    </div>
  </div>

  <div class="taf-section taf-two">
    <div>
      <h2 class="taf-h2">What We Won't Sell</h2>
      <ul class="taf-check">
        <li>Artwork that mocks or misappropriates sacred imagery</li>
        <li>Unlicensed reproductions of another artist's work</li>
        <li>Content that promotes hatred or discrimination</li>
        <li>Copyrighted characters or brands without permission</li>
      </ul>
      <p style="margin-top:14px;">Custom orders are reviewed against the same standards — we'll decline respectfully if a request crosses these lines.</p>
    </div>
    <div class="taf-card">
      <span class="taf-ico">🚩</span>
      <h3>See something wrong?</h3>
      <p>If you believe any artwork on our site infringes rights or misrepresents a tradition, tell us. Write to <a href="mailto:{$EMAIL}?subject=Content%20Concern">{$EMAIL}</a> with a link to the piece — every report is reviewed within two business days, and verified issues are removed promptly.</p>
    </div>
  </div>

  <div class="taf-cta">
    <h2>Questions About Our Standards?</h2>
    <p>We're happy to explain how any piece was sourced and licensed.</p>
    <a class="taf-btn" href="mailto:{$EMAIL}?subject=Content%20Ethics%20Question">Email {$EMAIL}</a>
    <a class="taf-btn-alt" href="/artists/">Meet Our Artists</a>
  </div>
</div>
HTML;

/* ── Terms & Conditions ─────────────────────────────────────── */
$bodies['terms-conditions'] = <<<HTML
<div class="taf-page taf-legal">
  <div class="taf-hero">
    <span class="taf-eyebrow">The Fine Print, Made Readable</span>
    <h1>Terms &amp; Conditions</h1>
    <p class="taf-sub">By accessing and purchasing from The Art Framer, you agree to the terms below.</p>
  </div>

  <p><span class="taf-updated">Last updated: 15 July 2026</span></p>

  <h2>1. Products</h2>
  <p>We make every effort to display product colours and details accurately. Slight variations may occur due to screen settings and the made-to-order nature of our products; these are not defects. One dimension-reference image is provided per product so you can judge real-world size.</p>

  <h2>2. Pricing &amp; Currency</h2>
  <p>Prices are listed in USD, with CAD available via the currency selector; your order is charged in the currency you check out with. Prices are subject to change without notice, and we reserve the right to correct pricing errors — if an error affects an order you've already placed, we'll contact you before proceeding.</p>

  <h2>3. Orders &amp; Production</h2>
  <p>Most items are printed, stretched, and framed to order. Orders can be modified or cancelled free of charge any time before production begins — after that, made-to-order items follow our <a href="/returns-exchanges/">Returns &amp; Exchanges</a> policy.</p>

  <h2>4. Shipping &amp; Delivery</h2>
  <p>Production and delivery timelines, free-delivery areas, and packaging standards are described in our <a href="/shipping-delivery/">Shipping &amp; Delivery</a> policy, which forms part of these terms.</p>

  <h2>5. Returns &amp; Refunds</h2>
  <p>Returns are governed by our <a href="/returns-exchanges/">Returns &amp; Exchanges</a> policy and refunds by our <a href="/refund-policy/">Refund Policy</a>. In short: damaged, defective, or incorrect items are replaced or refunded in full; personalised items are non-returnable unless damaged.</p>

  <h2>6. Custom &amp; Personalised Orders</h2>
  <p>By submitting a photo or design for a custom order you confirm you have the right to use it. We review custom requests against our <a href="/content-ethics-policy/">Content &amp; Ethics Policy</a> and may respectfully decline work that infringes rights or misrepresents cultural imagery. Your uploads are used solely to produce your order (see our <a href="/privacy-policy/">Privacy Policy</a>).</p>

  <h2>7. Intellectual Property</h2>
  <p>All artwork, images, and content on this site are the property of The Art Framer or its contributing artists and may not be reproduced without written permission. Purchasing a print grants you ownership of the physical piece, not reproduction rights to the artwork.</p>

  <h2>8. Accounts</h2>
  <p>You're responsible for keeping your account credentials secure. We may suspend accounts used fraudulently or in breach of these terms.</p>

  <h2>9. Limitation of Liability</h2>
  <p>To the fullest extent permitted by law, our liability for any claim related to an order is limited to the amount you paid for that order. Nothing in these terms limits liability that cannot be limited by law.</p>

  <h2>10. Changes to These Terms</h2>
  <p>We may update these terms as the store evolves. Material changes are announced on this page with an updated date; continued use of the site after changes means acceptance.</p>

  <h2>11. Contact</h2>
  <p>Questions about these terms? Email <a href="mailto:{$EMAIL}">{$EMAIL}</a> or call <a href="{$TEL}">{$PHONE}</a>.</p>

  <p class="taf-note">This is a starter template. Please have it reviewed by a legal professional for your jurisdiction.</p>
</div>
HTML;

/* ── Wholesale & Corporate ──────────────────────────────────── */
$bodies['wholesale-corporate'] = <<<HTML
<div class="taf-page">
  <div class="taf-hero">
    <span class="taf-eyebrow">Trade &amp; Volume Orders</span>
    <h1>Wholesale &amp; Corporate</h1>
    <p class="taf-sub">Volume pricing, custom production, and dedicated support — for designers, businesses, and bulk gifting.</p>
  </div>

  <div class="taf-section">
    <h2 class="taf-h2">Who It's For</h2>
    <div class="taf-grid">
      <div class="taf-card"><span class="taf-ico">🛋️</span><h3>Interior Designers</h3><p>Trade pricing on statement pieces, art sets, and custom sizes matched to your project boards.</p></div>
      <div class="taf-card"><span class="taf-ico">🏨</span><h3>Hotels &amp; Hospitality</h3><p>Room-by-room art programs, lobby statements, and durable framing built for high-traffic spaces.</p></div>
      <div class="taf-card"><span class="taf-ico">☕</span><h3>Cafés &amp; Restaurants</h3><p>Branded interiors and rotating wall collections that give your space its personality.</p></div>
      <div class="taf-card"><span class="taf-ico">🏢</span><h3>Offices &amp; Retail</h3><p>Reception branding, conference-room art, and corporate signage — see <a href="/exhibitions-events/">Exhibitions &amp; Events</a> for event formats.</p></div>
      <div class="taf-card"><span class="taf-ico">🎁</span><h3>Corporate Gifting</h3><p>Personalised canvas gifts for clients and teams — logos, portraits, and milestone prints at volume prices.</p></div>
      <div class="taf-card"><span class="taf-ico">🏪</span><h3>Retailers &amp; Resellers</h3><p>Wholesale canvas and frame supply with OEM/ODM options and flexible SKUs.</p></div>
    </div>
  </div>

  <div class="taf-section taf-media">
    <img src="https://theartframer.us/wp-content/uploads/2026/03/Cafe-Decor.webp" alt="Corporate cafe interior decorated with The Art Framer canvases" loading="lazy">
    <div>
      <h2 class="taf-h2">What You Get</h2>
      <ul class="taf-check">
        <li><b>Volume-based pricing</b> — the more walls, the better the rate</li>
        <li><b>Custom sizes and framing</b> — from desk prints to 4×6 ft statements</li>
        <li><b>Dedicated support</b> — one contact from quote to installation</li>
        <li><b>Branding options</b> — logos, colours, and co-branded packaging</li>
        <li><b>Priority production</b> — deadlines built into the schedule</li>
      </ul>
    </div>
  </div>

  <div class="taf-section">
    <h2 class="taf-h2">How It Works</h2>
    <ol class="taf-steps">
      <li><b>Tell us the project</b> — spaces, quantities, sizes, and your deadline. Rough ideas are fine; we'll help you spec it.</li>
      <li><b>Get your quote within 1 business day</b> — itemised pricing with volume discounts applied, plus mockups on request.</li>
      <li><b>We produce &amp; deliver</b> — priority production, secure bulk packaging, and delivery scheduled around your opening or event.</li>
    </ol>
  </div>

  <div class="taf-cta">
    <h2>Get a Custom Quote</h2>
    <p>Send your requirements today — quotes are prepared within one business day.</p>
    <a class="taf-btn" href="mailto:{$EMAIL}?subject=Wholesale%20/%20Corporate%20Quote">Email Your Requirements</a>
    <a class="taf-btn-alt" href="{$TEL}">Call {$PHONE}</a>
  </div>
</div>
HTML;

/* ── Dashboard — replaces dead [dokan-dashboard] shortcode ──── */
$bodies['dashboard'] = <<<HTML
<div class="taf-page">
  <div class="taf-hero">
    <span class="taf-eyebrow">Your Art HQ</span>
    <h1>My Dashboard</h1>
    <p class="taf-sub">Orders, downloads, tracking, and account settings — everything in one place.</p>
  </div>
  [af_dashboard]
</div>
HTML;

/* ── Track Your Order ───────────────────────────────────────── */
$bodies['track-your-order'] = <<<HTML
<div class="taf-page">
  <div class="taf-hero">
    <span class="taf-eyebrow">From Studio to Doorstep</span>
    <h1>Track Your Order</h1>
    <p class="taf-sub">Enter your order details below, or track everything from your account dashboard.</p>
  </div>

  <div class="taf-section taf-two">
    <div>
      <h2 class="taf-h2">Track by Order Number</h2>
      <p class="taf-lead">Use the order number and the email from your confirmation.</p>
      [woocommerce_order_tracking]
    </div>
    <div>
      <h2 class="taf-h2">Or From Your Account</h2>
      <div class="taf-grid" style="grid-template-columns:1fr;">
        <div class="taf-card"><span class="taf-ico">📋</span><h3>My Orders</h3><p>Signed-in customers see every order, status, and invoice in one place.</p><p style="margin-top:12px;"><a class="taf-btn" href="/my-account/orders/">View My Orders</a></p></div>
        <div class="taf-card"><span class="taf-ico">📧</span><h3>Tracking Email</h3><p>The moment your order ships, a tracking number and courier link land in your inbox — check spam if it's missing.</p></div>
      </div>
    </div>
  </div>

  <div class="taf-section">
    <h2 class="taf-h2">What the Statuses Mean</h2>
    <ul class="taf-track">
      <li><b>Processing</b><span>Payment received — your canvas enters the production queue.</span></li>
      <li><b>In Production</b><span>Printing, stretching, and framing — typically 3–5 business days.</span></li>
      <li><b>Shipped</b><span>On its way with tracking — standard delivery is 5–10 business days.</span></li>
      <li><b>Delivered</b><span>On your wall! Something wrong? Our <a href="/returns-exchanges/">guarantee</a> has you covered.</span></li>
    </ul>
  </div>

  <div class="taf-cta">
    <h2>Need Help Locating Your Order?</h2>
    <p>Send your order number and we'll check production and shipping records right away.</p>
    <a class="taf-btn" href="mailto:{$EMAIL}?subject=Order%20Tracking%20Help">Email {$EMAIL}</a>
    <a class="taf-btn-alt" href="{$TEL}">Call {$PHONE}</a>
  </div>
</div>
HTML;

/* ── Compare Products ───────────────────────────────────────── */
$bodies['compare'] = <<<HTML
<div class="taf-page">
  <div class="taf-hero">
    <span class="taf-eyebrow">Side by Side</span>
    <h1>Compare Products</h1>
    <p class="taf-sub">Sizes, frames, prices, and ratings — up to four artworks compared at a glance.</p>
  </div>

  <div class="taf-section">
    [af_compare]
  </div>

  <div class="taf-section">
    <h2 class="taf-h2">How It Works</h2>
    <ol class="taf-steps">
      <li><b>Browse the shop</b> — tap the <b>⇄ Compare</b> button on any product card (up to four).</li>
      <li><b>Open the compare bar</b> — it floats at the bottom of the screen; hit "Compare Now".</li>
      <li><b>Decide with confidence</b> — every size, frame, and price side by side. Still unsure? Preview any piece with <a href="/try-on-wall/">Try It on Your Wall</a>.</li>
    </ol>
  </div>
</div>
HTML;

/* ── Apply ──────────────────────────────────────────────────── */
echo "=== Restyle spec pages (v{$STYLE_VERSION}) ===\n\n";
$done = 0; $skipped = 0;
$classic_render = array( 'faqs', 'contact', 'dashboard' );

foreach ( $bodies as $slug => $body ) {
    $page = get_page_by_path( $slug );
    if ( ! $page ) {
        echo "MISS  /{$slug} — page not found (run create-pages first)\n";
        continue;
    }
    if ( in_array( $slug, $classic_render, true ) ) {
        $mode = get_post_meta( $page->ID, '_elementor_edit_mode', true );
        if ( $mode === 'builder' ) {
            update_post_meta( $page->ID, '_taf_elementor_mode_backup', $mode );
            update_post_meta( $page->ID, '_elementor_edit_mode', '' );
            echo "MODE  /{$slug} — Elementor rendering disabled (backup kept)\n";
        }
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
