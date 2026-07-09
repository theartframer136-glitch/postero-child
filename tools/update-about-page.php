<?php
/**
 * Replace the default theme "About Us" content with The Art Framer's own.
 * Renders custom HTML content (disables Elementor takeover for this page so
 * the content shows). Idempotent via a version marker.
 * Run: wp eval-file tools/update-about-page.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$ABOUT_VERSION = '1';

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
  <section class="taf-about-hero">
    <span class="taf-about-eyebrow">Premium Art &amp; Décor · Delaware, USA</span>
    <h1>About The Art Framer</h1>
    <p>The Art Framer is a premium art and décor brand crafting high-quality canvas wall art, framed prints, digital downloads, and custom décor. We bring spiritual, cultural, and modern artwork to homes and workspaces across the USA and beyond — made with archival, fade-resistant inks and ready to inspire.</p>
  </section>

  <section class="taf-about-block">
    <h2>Our Story</h2>
    <p>Born from a love of art and craftsmanship, The Art Framer began with a simple belief: beautiful, meaningful art should be accessible to everyone. From devotional Radha Krishna and Seven Horses canvases to modern abstracts and personalised prints, every piece is produced with premium materials and a gallery-grade finish. Operating from Delaware, USA, we serve customers nationwide with care, quality, and a passion for detail.</p>
  </section>

  <section class="taf-about-block">
    <h2>What We Offer</h2>
    <div class="taf-about-grid">
      <div class="taf-about-card"><h3>Digital Canvas Prints</h3><p>Vibrant, ready-to-hang canvas art across spiritual, cultural, and modern themes.</p></div>
      <div class="taf-about-card"><h3>Framed Canvases</h3><p>Premium framing in aluminium, fibre, floating, and wooden frames.</p></div>
      <div class="taf-about-card"><h3>Personalised Prints</h3><p>Turn your photos into portraits, family collages, and custom gifts.</p></div>
      <div class="taf-about-card"><h3>Digital Downloads</h3><p>Instant high-resolution files, delivered by email, ready to print.</p></div>
      <div class="taf-about-card"><h3>Banners &amp; Signage</h3><p>Vinyl, fabric, and fence banners plus stands for events and business.</p></div>
      <div class="taf-about-card"><h3>Art Accessories</h3><p>Stretcher bars, frames, rolled canvas, and DIY framing tools.</p></div>
    </div>
  </section>

  <section class="taf-about-block">
    <h2>Why Choose Us</h2>
    <ul class="taf-about-list">
      <li><strong>Archival Quality</strong> — fade-resistant inks and premium canvas built to last.</li>
      <li><strong>Try It On Your Wall</strong> — preview any artwork on your own wall before you buy.</li>
      <li><strong>Free USA Shipping</strong> — on premium canvas art to serviceable locations.</li>
      <li><strong>Custom Sizes &amp; Frames</strong> — tailored to your space and style.</li>
      <li><strong>Secure Checkout</strong> — encrypted, PCI-compliant payments.</li>
      <li><strong>Dedicated Support</strong> — real help by phone, email, and WhatsApp.</li>
    </ul>
  </section>

  <section class="taf-about-mission">
    <h2>Our Mission</h2>
    <p>To make premium, meaningful art effortless to discover, personalise, and enjoy — turning everyday walls into spaces that inspire.</p>
  </section>

  <section class="taf-about-cta">
    <a class="taf-btn-gold" href="/shop/">Explore the Collection</a>
    <a class="taf-btn-dark" href="/contact/">Contact Us</a>
    <p class="taf-about-contact">📞 {$phone} &nbsp;·&nbsp; ✉️ <a href="mailto:{$email}">{$email}</a> &nbsp;·&nbsp; 📍 Delaware, USA</p>
  </section>
</div>

<style>
.taf-about{max-width:1100px;margin:0 auto;padding:20px 18px 40px;color:#2a2a2a;}
.taf-about h1{font-size:40px;font-weight:800;color:#1a1a1a;margin:10px 0 16px;}
.taf-about h2{font-size:27px;font-weight:800;color:#1a1a1a;margin:0 0 14px;}
.taf-about h3{font-size:16px;font-weight:700;color:#1a1a1a;margin:0 0 8px;}
.taf-about p{font-size:15px;line-height:1.75;color:#444;}
.taf-about-hero{text-align:center;background:#faf7ef;border:1px solid #ece4cf;border-radius:16px;padding:40px 26px;margin-bottom:34px;}
.taf-about-eyebrow{display:inline-block;background:#1a1a1a;color:#e8c766;font-size:12px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;padding:6px 14px;border-radius:16px;margin-bottom:12px;}
.taf-about-hero p{max-width:760px;margin:0 auto;}
.taf-about-block{margin:0 0 34px;}
.taf-about-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;}
.taf-about-card{background:#fff;border:1px solid #eee;border-radius:12px;padding:18px;transition:box-shadow .2s,transform .2s;}
.taf-about-card:hover{box-shadow:0 8px 22px rgba(0,0,0,.08);transform:translateY(-2px);}
.taf-about-card p{font-size:13.5px;margin:0;}
.taf-about-list{list-style:none;padding:0;margin:0;display:grid;grid-template-columns:1fr 1fr;gap:12px 26px;}
.taf-about-list li{font-size:14.5px;line-height:1.6;color:#444;padding-left:24px;position:relative;}
.taf-about-list li::before{content:'✓';position:absolute;left:0;color:#c9a84c;font-weight:800;}
.taf-about-mission{background:linear-gradient(90deg,#141414,#2a2416);border-radius:16px;padding:32px 28px;text-align:center;margin:0 0 34px;}
.taf-about-mission h2{color:#fff;}
.taf-about-mission p{color:#e8e2cf;font-size:17px;max-width:720px;margin:0 auto;}
.taf-about-cta{text-align:center;}
.taf-btn-gold,.taf-btn-dark{display:inline-block;margin:6px;padding:13px 26px;border-radius:9px;font-weight:800;font-size:14px;text-decoration:none;}
.taf-btn-gold{background:#c9a84c;color:#fff;}
.taf-btn-gold:hover{background:#a8872e;}
.taf-btn-dark{background:#1a1a1a;color:#fff;}
.taf-btn-dark:hover{background:#333;}
.taf-about-contact{margin-top:18px;font-size:13.5px;color:#666;}
@media(max-width:820px){ .taf-about-grid{grid-template-columns:1fr 1fr;} .taf-about-list{grid-template-columns:1fr;} .taf-about h1{font-size:30px;} }
@media(max-width:520px){ .taf-about-grid{grid-template-columns:1fr;} }
</style>
HTML;

// Update content
wp_update_post(array('ID'=>$pid, 'post_content'=>$html));

// Stop Elementor from taking over this page so our content renders
delete_post_meta($pid, '_elementor_edit_mode');
delete_post_meta($pid, '_elementor_data');
delete_post_meta($pid, '_elementor_template_type');
update_post_meta($pid, '_wp_page_template', 'elementor_header_footer'); // header/footer + the_content

update_post_meta($pid, '_taf_about_v', $ABOUT_VERSION);
clean_post_cache($pid);

echo "About page (#{$pid}) updated to The Art Framer content (v{$ABOUT_VERSION}).\n";
