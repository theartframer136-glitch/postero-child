<?php
/**
 * Replace the default theme "About Us" content with The Art Framer's own.
 * Styling is loaded from the child theme (functions.php), NOT inline, because
 * the theme strips <style> from page content. Idempotent via version marker.
 * Run: wp eval-file tools/update-about-page.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$ABOUT_VERSION = '2';

$page = get_page_by_path('about');
if (!$page) { $page = get_page_by_path('about-us'); }
if (!$page) { echo "About page not found\n"; exit; }
$pid = $page->ID;

if (get_post_meta($pid, '_taf_about_v', true) === $ABOUT_VERSION) {
    echo "About page already at v{$ABOUT_VERSION} (skip)\n";
    return;
}

$email = 'theartframer136@gmail.com';
$phone = '+1 (610) 470-7280';

$html = <<<HTML
<div class="taf-about">

  <section class="taf-hero">
    <span class="taf-eyebrow">Premium Art &amp; Décor · Delaware, USA</span>
    <h1>Art That Transforms<br><span>Your Space</span></h1>
    <p>The Art Framer crafts premium canvas wall art, framed prints, digital downloads, and custom décor — spiritual, cultural, and modern pieces made with archival, fade-resistant inks and ready to inspire.</p>
    <div class="taf-hero-badges">
      <span>🎨 Archival Quality</span>
      <span>🖼️ Try On Your Wall</span>
      <span>🚚 Free USA Shipping</span>
      <span>🔒 Secure Checkout</span>
    </div>
  </section>

  <section class="taf-stats">
    <div class="taf-stat"><strong>100+</strong><span>Curated Artworks</span></div>
    <div class="taf-stat"><strong>4</strong><span>Frame Options</span></div>
    <div class="taf-stat"><strong>USA</strong><span>Free Shipping</span></div>
    <div class="taf-stat"><strong>5★</strong><span>Crafted Quality</span></div>
  </section>

  <section class="taf-block taf-story">
    <div class="taf-block-head"><h2>Our Story</h2><div class="taf-rule"></div></div>
    <p>Born from a love of art and craftsmanship, The Art Framer began with a simple belief: beautiful, meaningful art should be accessible to everyone. From devotional Radha Krishna and Seven Horses canvases to modern abstracts and personalised prints, every piece is produced with premium materials and a gallery-grade finish. Operating from Delaware, USA, we serve customers nationwide with care, quality, and a passion for detail.</p>
  </section>

  <section class="taf-block">
    <div class="taf-block-head"><h2>What We Offer</h2><div class="taf-rule"></div></div>
    <div class="taf-grid">
      <div class="taf-card"><div class="taf-ico">🖼️</div><h3>Digital Canvas Prints</h3><p>Vibrant, ready-to-hang canvas art across spiritual, cultural, and modern themes.</p></div>
      <div class="taf-card"><div class="taf-ico">🪵</div><h3>Framed Canvases</h3><p>Premium framing in aluminium, fibre, floating, and wooden frames.</p></div>
      <div class="taf-card"><div class="taf-ico">👨‍👩‍👧</div><h3>Personalised Prints</h3><p>Turn your photos into portraits, family collages, and custom gifts.</p></div>
      <div class="taf-card"><div class="taf-ico">⬇️</div><h3>Digital Downloads</h3><p>Instant high-resolution files, delivered by email, ready to print.</p></div>
      <div class="taf-card"><div class="taf-ico">📢</div><h3>Banners &amp; Signage</h3><p>Vinyl, fabric, and fence banners plus stands for events and business.</p></div>
      <div class="taf-card"><div class="taf-ico">🧰</div><h3>Art Accessories</h3><p>Stretcher bars, frames, rolled canvas, and DIY framing tools.</p></div>
    </div>
  </section>

  <section class="taf-block">
    <div class="taf-block-head"><h2>Why Choose Us</h2><div class="taf-rule"></div></div>
    <ul class="taf-list">
      <li><strong>Archival Quality</strong> — fade-resistant inks and premium canvas built to last.</li>
      <li><strong>Try It On Your Wall</strong> — preview any artwork on your own wall before you buy.</li>
      <li><strong>Free USA Shipping</strong> — on premium canvas art to serviceable locations.</li>
      <li><strong>Custom Sizes &amp; Frames</strong> — tailored to your space and style.</li>
      <li><strong>Secure Checkout</strong> — encrypted, PCI-compliant payments.</li>
      <li><strong>Dedicated Support</strong> — real help by phone, email, and WhatsApp.</li>
    </ul>
  </section>

  <section class="taf-mission">
    <h2>Our Mission</h2>
    <p>To make premium, meaningful art effortless to discover, personalise, and enjoy — turning everyday walls into spaces that inspire.</p>
  </section>

  <section class="taf-cta">
    <h2>Ready to find your piece?</h2>
    <div class="taf-cta-btns">
      <a class="taf-btn-gold" href="/shop/">Explore the Collection</a>
      <a class="taf-btn-dark" href="/contact/">Contact Us</a>
    </div>
    <p class="taf-contact">📞 {$phone} &nbsp;·&nbsp; ✉️ <a href="mailto:{$email}">{$email}</a> &nbsp;·&nbsp; 📍 Delaware, USA</p>
  </section>

</div>
HTML;

wp_update_post(array('ID'=>$pid, 'post_content'=>$html));

// Stop Elementor from taking over so our content + theme CSS render
delete_post_meta($pid, '_elementor_edit_mode');
delete_post_meta($pid, '_elementor_data');
delete_post_meta($pid, '_elementor_template_type');
update_post_meta($pid, '_wp_page_template', 'elementor_header_footer');

update_post_meta($pid, '_taf_about_v', $ABOUT_VERSION);
clean_post_cache($pid);

echo "About page (#{$pid}) updated to stylish v{$ABOUT_VERSION}.\n";
