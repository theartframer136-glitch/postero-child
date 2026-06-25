<?php
/**
 * Full Premium Update — Radha Krishna Sacred Bond Canvas Art (#11578)
 *
 * Applies master-template structure: title, short desc, long desc (all sections),
 * specifications, SEO (Yoast + RankMath), tags, attributes, image meta.
 *
 * Run:
 *   wp eval-file wp-content/themes/postero-child/tools/update-product-sacred-bond.php --allow-root
 */

if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }
if ( ! function_exists( 'wc_get_product' ) ) { WP_CLI::error( 'WooCommerce not active.' ); }

/* ─────────────────────────────────────────────────────────────
   PRODUCT IDENTITY
   ───────────────────────────────────────────────────────────── */
$PRODUCT_ID   = 11578;
$ATTACHMENT_ID = 11576; // featured image already in media library

/* ─────────────────────────────────────────────────────────────
   CONTENT
   ───────────────────────────────────────────────────────────── */

$TITLE = 'Radha Krishna Sacred Bond Canvas Wall Art | Divine Love Digital Canvas Print';

$SLUG  = 'radha-krishna-sacred-bond-canvas-art';

/* ── SHORT DESCRIPTION ──────────────────────────────────────── */
$SHORT_DESC = '<p><span>Add divine romance and vibrant elegance to your décor with this Modern <strong>Radha Krishna Abstract Canvas Wall Art</strong>, symbolizing love, devotion, and harmony.</span></p>
<table class="taf-specs-table">
<tbody>
<tr>
<td class="taf-label">Original</td><td class="taf-value">Yes</td>
<td class="taf-label">Brand</td><td class="taf-value">The Art Framer</td>
</tr>
<tr>
<td class="taf-label">Category</td><td class="taf-value">Radha Krishna</td>
<td class="taf-label">Type</td><td class="taf-value">Canvas Wall Art</td>
</tr>
<tr>
<td class="taf-label">Material</td><td class="taf-value">Premium Canvas</td>
<td class="taf-label">Support Base</td><td class="taf-value">Canvas</td>
</tr>
<tr>
<td class="taf-label">Print Method</td><td class="taf-value">XYZ Colour Digital Printing</td>
<td class="taf-label">Ink Type</td><td class="taf-value">Eco-Friendly Ink</td>
</tr>
<tr>
<td class="taf-label">Frame Type</td><td class="taf-value">Custom Frame Available</td>
<td class="taf-label">Orientation</td><td class="taf-value">Portrait / Landscape</td>
</tr>
<tr>
<td class="taf-label">Shape</td><td class="taf-value">Rectangular / Square</td>
<td class="taf-label">Colour</td><td class="taf-value">Multicolor</td>
</tr>
<tr>
<td class="taf-label">Use / Room Type</td><td class="taf-value">Living Room / Bedroom / Puja Room</td>
<td class="taf-label">Indoor / Outdoor</td><td class="taf-value">Indoor</td>
</tr>
<tr>
<td class="taf-label">Selling Unit</td><td class="taf-value">Single Piece</td>
<td class="taf-label">Sample</td><td class="taf-value">Provided</td>
</tr>
<tr>
<td class="taf-label">OEM / ODM</td><td class="taf-value">Available</td>
<td class="taf-label">Product Weight</td><td class="taf-value">—</td>
</tr>
<tr>
<td class="taf-label">Product Size</td><td class="taf-value">—</td>
<td class="taf-label">Product Dimension</td><td class="taf-value">—</td>
</tr>
<tr>
<td class="taf-label">Number of Items</td><td class="taf-value">1</td>
<td class="taf-label">Theme</td><td class="taf-value">Radha Krishna</td>
</tr>
<tr>
<td class="taf-label">Recommended Use</td><td class="taf-value">Home / Office Decoration</td>
<td class="taf-label">Wall Art Form</td><td class="taf-value">Canvas Wall Art</td>
</tr>
<tr>
<td class="taf-label">Style</td><td class="taf-value">Modern</td>
<td class="taf-label">Colour Family</td><td class="taf-value">Multicolor</td>
</tr>
<tr>
<td class="taf-label">Age Range</td><td class="taf-value">All Ages</td>
<td class="taf-label">Pattern</td><td class="taf-value">Artistic</td>
</tr>
<tr>
<td class="taf-label">Special Feature</td><td class="taf-value">Fade Resistant Print</td>
<td class="taf-label">Frame Material</td><td class="taf-value">Wood / Fibre / Aluminium</td>
</tr>
<tr>
<td class="taf-label">Mounting Type</td><td class="taf-value">Wall Mount</td>
<td class="taf-label">Finish Type</td><td class="taf-value">Matte / Glossy</td>
</tr>
<tr>
<td class="taf-label">Is Framed</td><td class="taf-value">Yes</td>
<td class="taf-label">Manufacturer</td><td class="taf-value">The Art Framer</td>
</tr>
<tr>
<td class="taf-label">Country Of Origin</td><td class="taf-value">India</td>
<td class="taf-label">Item Part Number</td><td class="taf-value">TAF-7H-001</td>
</tr>
<tr>
<td class="taf-label">ASIN</td><td class="taf-value">Not Applicable</td>
<td class="taf-value"></td><td class="taf-value"></td>
</tr>
</tbody>
</table>
<strong class="taf-section-title">Additional Information</strong>
<table class="taf-additional-table">
<tbody>
<tr><td class="taf-label">Manufacturer</td><td class="taf-value">The Art Framer</td></tr>
<tr><td class="taf-label">Packer</td><td class="taf-value">The Art Framer</td></tr>
<tr><td class="taf-label">Item Weight</td><td class="taf-value">Approx. 2 – 4 kg (Depending on Size &amp; Frame Type)</td></tr>
<tr><td class="taf-label">Net Quantity</td><td class="taf-value">1 Piece</td></tr>
<tr><td class="taf-label">Generic Name</td><td class="taf-value">Canvas Printed Wall Frame</td></tr>
<tr><td class="taf-label">Best Sellers Rank</td><td class="taf-value">#Trending in Home Decor &amp; Wall Art</td></tr>
</tbody>
</table>';

/* ── LONG DESCRIPTION ───────────────────────────────────────── */
$LONG_DESC = '
<p>The <strong>Radha Krishna Abstract Canvas Wall Art</strong> (36 × 48 inches) Digital Canvas Print is a stunning combination of devotional symbolism and modern artistic creativity. Designed to represent divine love and emotional harmony, this artwork captures the sacred connection between Radha and Krishna through expressive colors and elegant forms. Radha and Krishna are universally recognized as the ultimate symbols of love and devotion. Their relationship represents unconditional affection, emotional connection, and spiritual unity. In Indian culture, displaying Radha Krishna artwork is believed to bring peace, positivity, and harmonious relationships into the home.</p>

<a href="https://theartframer.us/wp-content/uploads/2026/02/artwork-on-canvas-202604041618-69d0ecb373b53.webp"><img src="https://theartframer.us/wp-content/uploads/2026/02/artwork-on-canvas-202604041618-69d0ecb373b53.webp" alt="Radha Krishna Sacred Bond Canvas Wall Art on Canvas" width="1376" height="768" /></a>

<p>This particular Radha Krishna Abstract Canvas Wall Art stands out due to its modern abstract interpretation. Instead of traditional detailing, the painting uses bold strokes, vibrant colors, and fluid shapes to convey emotion and movement. The result is a visually engaging artwork that feels contemporary while preserving spiritual depth. The vibrant blue tone of Krishna represents calmness, wisdom, and divine consciousness. The golden and warm hues surrounding Radha symbolize love, beauty, and grace. Together, these contrasting colors create a harmonious balance that enhances the overall visual experience.</p>

<p>The subtle depiction of Krishna\'s flute adds symbolic meaning to the artwork. The flute represents melody, unity, and divine communication. It reflects the idea that love and devotion create harmony in life. The 36 × 48 inch size ensures the artwork becomes the visual centerpiece of the room. It fills wide wall spaces effectively while maintaining proportional balance.</p>

<h4>This artwork is especially suitable for:</h4>
<ul>
<li>Modern spiritual décor</li>
<li>Contemporary art styles</li>
<li>Symbolic love-themed artwork</li>
<li>Vibrant color compositions</li>
</ul>
<p>Its abstract style allows it to blend seamlessly into modern interiors, making it an excellent choice for urban homes and luxury apartments.</p>

<h4>This artwork represents:</h4>
<ul>
<li>Divine love</li>
<li>Emotional harmony</li>
<li>Spiritual connection</li>
<li>Artistic expression</li>
<li>Positive energy</li>
</ul>
<p>Because of its symbolic depth and aesthetic appeal, this Radha Krishna canvas is also widely chosen as a thoughtful gift.</p>

<h4>Why Choose This Artwork?</h4>
<p>Choosing the Radha Krishna Abstract Canvas Wall Art (36 × 48 inches) means selecting a masterpiece that blends spiritual symbolism with modern artistic expression. This artwork stands out not only for its visual beauty but also for the deep emotional and spiritual meaning it represents. Radha–Krishna symbolize eternal love and harmony, making this canvas both meaningful and stylish. Its modern abstract style reimagines a timeless theme with bold colors and expressive forms, perfect for contemporary spaces.</p>

<a href="https://theartframer.us/wp-content/uploads/2026/02/ecommerce-infographic-layout-202604041619-69d0ecb30841c.webp"><img src="https://theartframer.us/wp-content/uploads/2026/02/ecommerce-infographic-layout-202604041619-69d0ecb30841c.webp" alt="Radha Krishna Sacred Bond Canvas Art Infographic" width="1376" height="768" /></a>

<table style="width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; font-size: 14px;">
<tbody>
<tr>
<td style="border: 1px solid #ddd; padding: 10px; font-weight: bold; width: 35%; background-color: #f7f7f7;">Type</td>
<td style="border: 1px solid #ddd; padding: 10px;">Digital Canvas Printing</td>
</tr>
<tr>
<td style="border: 1px solid #ddd; padding: 10px; font-weight: bold; background-color: #f7f7f7;"><strong>Multiple Framing Option Available</strong></td>
<td style="border: 1px solid #ddd; padding: 10px;">Fibre, Wooden, Aluminium</td>
</tr>
<tr>
<td style="border: 1px solid #ddd; padding: 10px; font-weight: bold; background-color: #f7f7f7;"><strong>Multiple Sizes Available</strong></td>
<td style="border: 1px solid #ddd; padding: 10px;"><ul><li>36" (H) × 48" (B)</li><li>24" (H) × 36" (B)</li></ul></td>
</tr>
<tr>
<td style="border: 1px solid #ddd; padding: 10px; font-weight: bold; background-color: #f7f7f7;"><strong>Multiple Colours Available</strong></td>
<td style="border: 1px solid #ddd; padding: 10px;"><ul><li>Black</li><li>Silver</li><li>Gold</li><li>Rose Gold</li><li>White</li><li>Wooden</li></ul></td>
</tr>
<tr>
<td style="border: 1px solid #ddd; padding: 10px; font-weight: bold; background-color: #f7f7f7;"><strong>DIY Collection</strong> <strong>(Easy Assembly)</strong></td>
<td style="border: 1px solid #ddd; padding: 10px;">Includes Canvas rolled print, Stretcher Bars, Hanging Accessories, Screws, Plier, Frame &amp; Accessories <em>(if ordered)</em></td>
</tr>
</tbody>
</table>

<p>The vibrant color palette makes this artwork highly appealing, with soothing blue tones of Krishna symbolizing calmness and wisdom, while warm golden and orange hues of Radha represent love and compassion. This balanced fusion creates visual harmony and emotional warmth, making it ideal for spaces focused on comfort and positivity. Its versatile design blends effortlessly with modern, minimal, or traditional interiors.</p>

<p>Another key advantage is its deep symbolic meaning, as Radha Krishna imagery is associated with love, peace, and harmony in relationships. Crafted on premium canvas with high-definition printing, it ensures durability and long-lasting vibrancy. Its universal appeal also makes it a meaningful and elegant gift choice.</p>

<h4>It is suitable for:</h4>
<ul>
<li>Weddings</li>
<li>Anniversaries</li>
<li>Housewarming ceremonies</li>
<li>Engagement celebrations</li>
<li>Festivals such as Diwali or Janmashtami</li>
<li>Romantic gifting occasions</li>
</ul>
<p>Gifting this artwork symbolizes blessings, love, and spiritual harmony.</p>

<h4>Overall, this artwork offers:</h4>
<ul>
<li>Spiritual symbolism</li>
<li>Contemporary style</li>
<li>Premium quality printing</li>
<li>Emotional value</li>
<li>Long-lasting durability</li>
</ul>
<p>These qualities make it a meaningful and aesthetically pleasing addition to any interior.</p>

<h4>Placement Ideas</h4>
<p>The Radha Krishna Abstract Canvas Wall Art (36 × 48 inches) is designed to enhance multiple interior settings. Its horizontal orientation allows it to fit beautifully across wide walls.</p>

<a href="https://theartframer.us/wp-content/uploads/2026/02/Abstract-R-K-Placement.webp"><img src="https://theartframer.us/wp-content/uploads/2026/02/Abstract-R-K-Placement.webp" alt="Radha Krishna Abstract wall art Placements" width="1536" height="839" /></a>

<h4>Frame &amp; Finish Details</h4>
<p>The Radha Krishna Abstract Canvas Wall Art is available with multiple premium frame options designed to suit various interior preferences.</p>
<ul>
<li>Floating Frame</li>
<li>Fibre Frame</li>
<li>Aluminium Frame</li>
<li>Golden Frame</li>
<li>Rose Gold Frame</li>
<li>Silver Frame</li>
<li>White Frame</li>
<li>Wooden Frame</li>
</ul>

<h4>Finish Quality</h4>
<p>The canvas features a matte textured finish, which offers:</p>
<ul>
<li>Reduced glare</li>
<li>Enhanced color depth</li>
<li>Realistic texture</li>
<li>Long-term durability</li>
</ul>

<h4>Care Instructions</h4>
<a href="https://theartframer.us/wp-content/uploads/2026/02/wall-art-care-202604031610-69cf9a2f4de25.webp"><img class="alignnone wp-image-9280 size-full" src="https://theartframer.us/wp-content/uploads/2026/02/wall-art-care-202604031610-69cf9a2f4de25.webp" alt="Radha Krishna canvas art care instructions" width="1376" height="768" /></a>
<ul>
<li>Dust Regularly</li>
<li>Use a soft dry cloth to gently remove dust from the surface.</li>
<li>Continuous exposure to direct sunlight may affect color brightness over time.</li>
</ul>

<h3>Other Collections</h3>
<a href="https://theartframer.us/wp-content/uploads/2026/02/10-1-69d648860cb9e.webp"><img class="alignnone size-full wp-image-9514" src="https://theartframer.us/wp-content/uploads/2026/02/10-1-69d648860cb9e.webp" alt="The Art Framer Other Canvas Collections" width="1536" height="839" /></a>

<h2>Frequently Asked Questions</h2>

<details class="afaq-item"><summary class="afaq-question">What is canvas wall art made of?</summary>
<p class="afaq-answer">Our canvas wall art is made using premium-quality, high-density artist-grade canvas combined with durable wooden or metal frames. The material ensures vibrant color reproduction, long-lasting durability, and a premium gallery-style finish suitable for homes and offices.</p>
</details>

<details class="afaq-item"><summary class="afaq-question">Is digital canvas printing long-lasting?</summary>
<p class="afaq-answer">Yes, digital canvas printing is highly durable when produced with fade-resistant pigment inks and premium canvas materials. Under normal indoor conditions, our canvas prints can maintain their color vibrancy and quality for many years.</p>
</details>

<details class="afaq-item"><summary class="afaq-question">Does canvas wall art fade over time?</summary>
<p class="afaq-answer">Our canvas prints are produced using advanced fade-resistant inks that help prevent color fading. When kept away from direct sunlight and moisture, the artwork retains its color richness for years.</p>
</details>

<details class="afaq-item"><summary class="afaq-question">Is canvas wall art better than paper posters?</summary>
<p class="afaq-answer">Yes, canvas wall art offers better durability, texture, and a premium appearance compared to paper posters. Canvas prints provide a realistic artistic look and are more resistant to tearing and fading.</p>
</details>

<details class="afaq-item"><summary class="afaq-question">Why should I choose canvas wall art for home decoration?</summary>
<p class="afaq-answer">Canvas wall art is a popular choice because it provides a premium artistic appearance, long-lasting durability, and versatile placement options suitable for modern and traditional interiors.</p>
</details>

<details class="afaq-item"><summary class="afaq-question">What makes premium canvas wall art different from regular prints?</summary>
<p class="afaq-answer">Premium canvas wall art uses high-quality materials, fade-resistant inks, and professional framing techniques, resulting in better color accuracy, durability, and visual appeal.</p>
</details>

<details class="afaq-item"><summary class="afaq-question">Is canvas wall art a good gift option?</summary>
<p class="afaq-answer">Yes, canvas wall art is an excellent gift choice for housewarming events, weddings, festivals, birthdays, and special occasions.</p>
</details>

<details class="afaq-item"><summary class="afaq-question">How can I contact customer support for canvas wall art inquiries?</summary>
<p class="afaq-answer">You can contact our customer support team for any canvas wall art or digital canvas printing inquiries through phone, email, or WhatsApp. Our support team is available to assist with product details, customization requests, order updates, and installation guidance.</p>
</details>

<details class="afaq-item"><summary class="afaq-question">What is your customer support contact number?</summary>
<p class="afaq-answer">You can reach our customer support team at +1 (610) 470-7280 for assistance related to product inquiries, order tracking, and customization support.</p>
</details>

<details class="afaq-item"><summary class="afaq-question">What is your customer support email address?</summary>
<p class="afaq-answer">You can contact us via email at theartframer136@gmail.com for product inquiries, bulk orders, or support-related questions.</p>
</details>

<details class="afaq-item"><summary class="afaq-question">Do you provide WhatsApp support for canvas print orders?</summary>
<p class="afaq-answer">Yes, we provide WhatsApp support for quick assistance with product selection, order inquiries, and customization requests. You can message us on WhatsApp at +1 (610) 470-7280.</p>
</details>

<details class="afaq-item"><summary class="afaq-question">Where is your canvas printing business located?</summary>
<p class="afaq-answer">Our canvas printing business operates from Delaware, USA, and we provide delivery services across multiple locations.</p>
</details>

<details class="afaq-item"><summary class="afaq-question">Which areas are eligible for free delivery?</summary>
<p class="afaq-answer">We offer free delivery across major cities and serviceable locations in the USA. Free delivery is available in cities such as Delaware, Pennsylvania, Maryland, New Jersey &amp; nearby areas.</p>
</details>
';

/* ─────────────────────────────────────────────────────────────
   TAGS (20–30 SEO tags)
   ───────────────────────────────────────────────────────────── */
$TAGS = array(
    'Radha Krishna',
    'Radha Krishna canvas art',
    'Radha Krishna wall art',
    'sacred bond canvas print',
    'Krishna art',
    'devotional canvas print',
    'spiritual wall art',
    'Hindu wall art',
    'religious canvas art',
    'Krishna home decor',
    'Radha Krishna gift',
    'divine love wall art',
    'Indian art print',
    'pooja room decor',
    'temple room art',
    'spiritual home decor',
    'premium canvas print',
    'gallery wrapped canvas',
    'archival canvas print',
    'digital canvas print',
    'luxury wall art',
    'wedding gift art',
    'anniversary gift canvas',
    'housewarming gift art',
    'living room wall art',
    'bedroom wall art',
    'meditation room decor',
    'Indian religious art',
    'canvas wall decor',
    'Janmashtami gift',
);

/* ─────────────────────────────────────────────────────────────
   PRODUCT ATTRIBUTES (non-variation — informational)
   ───────────────────────────────────────────────────────────── */
$ATTRIBUTES = array(
    'Material'        => 'Premium Cotton-Blend Canvas',
    'Print Type'      => 'Archival Giclée Print',
    'Ink'             => 'Eco-Friendly UV-Resistant Archival Inks',
    'Frame Options'   => 'Unframed (Gallery Wrapped) | Classic Wood Frame | Floater Frame',
    'Hanging Ready'   => 'Yes – D-Ring Hooks Pre-Installed',
    'Indoor Use'      => 'Yes',
    'Available Sizes' => '12×16 in | 16×20 in | 20×24 in | 24×30 in | 24×36 in | 30×40 in | 36×48 in',
    'Dispatch'        => '3–5 Business Days',
    'Shipping'        => 'Fully Insured. Tracked Delivery.',
    'Style'           => 'Devotional / Spiritual',
    'Subject'         => 'Radha Krishna',
    'Theme'           => 'Divine Love | Sacred Bond | Hindu Art',
);

/* ─────────────────────────────────────────────────────────────
   SEO FIELDS
   ───────────────────────────────────────────────────────────── */
$SEO = array(
    // Yoast SEO
    '_yoast_wpseo_title'         => 'Radha Krishna Sacred Bond Canvas Wall Art | Divine Love Print – The Art Framer',
    '_yoast_wpseo_metadesc'      => 'Celebrate the eternal sacred bond of Radha and Krishna with this premium gallery-wrapped canvas wall art. Archival Giclée inks, eco-friendly, ready to hang. Shop now at The Art Framer.',
    '_yoast_wpseo_focuskw'       => 'Radha Krishna canvas wall art',
    '_yoast_wpseo_canonical'     => 'https://theartframer.us/product/radha-krishna-sacred-bond-canvas-art/',
    // RankMath SEO (covers both plugins)
    'rank_math_title'            => 'Radha Krishna Sacred Bond Canvas Wall Art | Divine Love Print – The Art Framer',
    'rank_math_description'      => 'Celebrate the eternal sacred bond of Radha and Krishna with this premium gallery-wrapped canvas wall art. Archival Giclée inks, eco-friendly, ready to hang. Shop now at The Art Framer.',
    'rank_math_focus_keyword'    => 'Radha Krishna canvas wall art',
    'rank_math_canonical_url'    => 'https://theartframer.us/product/radha-krishna-sacred-bond-canvas-art/',
    // Open Graph
    '_yoast_wpseo_opengraph-title'       => 'Radha Krishna Sacred Bond Canvas Wall Art | The Art Framer',
    '_yoast_wpseo_opengraph-description' => 'Premium gallery-wrapped Radha Krishna canvas wall art — archival quality, eco-friendly inks, ready to hang. A divine centrepiece for your home.',
    // Twitter Card
    '_yoast_wpseo_twitter-title'         => 'Radha Krishna Sacred Bond Canvas Wall Art | The Art Framer',
    '_yoast_wpseo_twitter-description'   => 'Premium gallery-wrapped Radha Krishna canvas wall art — archival quality, eco-friendly inks, ready to hang.',
);

/* ─────────────────────────────────────────────────────────────
   IMAGE META
   ───────────────────────────────────────────────────────────── */
$IMAGE_META = array(
    'post_title'   => 'Radha Krishna Sacred Bond Canvas Wall Art',
    'post_excerpt' => 'Sacred Bond – Radha Krishna Canvas Wall Art | The Art Framer',
    'post_content' => 'A premium digital canvas print depicting the sacred and eternal bond of Radha and Krishna, rendered in vibrant archival Giclée inks on gallery-wrapped premium cotton-blend canvas.',
    '_wp_attachment_image_alt' => 'Radha Krishna Sacred Bond Canvas Wall Art – Premium Archival Digital Canvas Print | The Art Framer',
);

/* ══════════════════════════════════════════════════════════════
   EXECUTION — update everything
   ══════════════════════════════════════════════════════════════ */

WP_CLI::log( '' );
WP_CLI::log( '╔══════════════════════════════════════════════════════╗' );
WP_CLI::log( '║  The Art Framer — Premium Product Update             ║' );
WP_CLI::log( '║  Radha Krishna Sacred Bond Canvas Art  #' . $PRODUCT_ID . '       ║' );
WP_CLI::log( '╚══════════════════════════════════════════════════════╝' );
WP_CLI::log( '' );

$product = wc_get_product( $PRODUCT_ID );
if ( ! $product ) {
    WP_CLI::error( "Product #{$PRODUCT_ID} not found. Check the ID." );
}

/* ── 1. Core post fields ── */
WP_CLI::log( '1. Updating title, slug, descriptions...' );
$result = wp_update_post( array(
    'ID'             => $PRODUCT_ID,
    'post_title'     => $TITLE,
    'post_name'      => $SLUG,
    'post_content'   => $LONG_DESC,
    'post_excerpt'   => $SHORT_DESC,
    'post_status'    => 'publish',
), true );
if ( is_wp_error( $result ) ) {
    WP_CLI::warning( '  Post update error: ' . $result->get_error_message() );
} else {
    WP_CLI::log( '  ✓ Title, slug, descriptions updated.' );
}

/* ── 2. WooCommerce product data ── */
WP_CLI::log( '2. Refreshing WooCommerce product fields...' );
$product = wc_get_product( $PRODUCT_ID );
$product->set_name( $TITLE );
$product->set_slug( $SLUG );
$product->set_short_description( $SHORT_DESC );
$product->set_description( $LONG_DESC );
$product->set_status( 'publish' );
$product->set_catalog_visibility( 'visible' );
$product->set_regular_price( '150' );
$product->set_sale_price( '100' );
$product->set_manage_stock( false );
$product->set_stock_status( 'instock' );
$product->save();
WP_CLI::log( '  ✓ WooCommerce fields saved.' );

/* ── 3. Product Attributes ── */
WP_CLI::log( '3. Setting product attributes...' );
$wc_attributes = array();
$position      = 0;
foreach ( $ATTRIBUTES as $attr_name => $attr_value ) {
    $attr = new WC_Product_Attribute();
    $attr->set_id( 0 );
    $attr->set_name( $attr_name );
    $attr->set_options( array( $attr_value ) );
    $attr->set_position( $position++ );
    $attr->set_visible( true );
    $attr->set_variation( false );
    $wc_attributes[ sanitize_title( $attr_name ) ] = $attr;
}
$product = wc_get_product( $PRODUCT_ID );
$product->set_attributes( $wc_attributes );
$product->save();
WP_CLI::log( '  ✓ ' . count( $ATTRIBUTES ) . ' attributes set.' );

/* ── 4. Tags ── */
WP_CLI::log( '4. Setting product tags...' );
wp_set_object_terms( $PRODUCT_ID, $TAGS, 'product_tag' );
WP_CLI::log( '  ✓ ' . count( $TAGS ) . ' tags applied.' );

/* ── 5. SEO meta ── */
WP_CLI::log( '5. Writing SEO meta fields...' );
foreach ( $SEO as $key => $value ) {
    update_post_meta( $PRODUCT_ID, $key, $value );
}
WP_CLI::log( '  ✓ Yoast + RankMath + OpenGraph + Twitter Card meta written.' );

/* ── 6. Image meta (alt, title, caption, description) ── */
WP_CLI::log( '6. Updating featured image meta...' );
wp_update_post( array(
    'ID'           => $ATTACHMENT_ID,
    'post_title'   => $IMAGE_META['post_title'],
    'post_excerpt' => $IMAGE_META['post_excerpt'],
    'post_content' => $IMAGE_META['post_content'],
) );
update_post_meta( $ATTACHMENT_ID, '_wp_attachment_image_alt', $IMAGE_META['_wp_attachment_image_alt'] );
WP_CLI::log( '  ✓ Image alt, title, caption, description set.' );

/* ── 7. Set featured image (confirm) ── */
WP_CLI::log( '7. Confirming featured image...' );
set_post_thumbnail( $PRODUCT_ID, $ATTACHMENT_ID );
WP_CLI::log( '  ✓ Featured image confirmed (attachment #' . $ATTACHMENT_ID . ').' );

/* ── 8. Flush ── */
WP_CLI::log( '8. Flushing caches...' );
wc_delete_product_transients( $PRODUCT_ID );
clean_post_cache( $PRODUCT_ID );
if ( function_exists( 'rocket_clean_post' ) ) { rocket_clean_post( $PRODUCT_ID ); }
WP_CLI::log( '  ✓ Caches cleared.' );

/* ── Summary ── */
WP_CLI::log( '' );
WP_CLI::log( '╔══════════════════════════════════════════════════════╗' );
WP_CLI::success( '  ALL DONE — Product fully updated.                    ' );
WP_CLI::log( '  Title    : ' . $TITLE );
WP_CLI::log( '  Slug     : ' . $SLUG );
WP_CLI::log( '  Price    : $150 regular  /  $100 sale' );
WP_CLI::log( '  Status   : Published' );
WP_CLI::log( '  Tags     : ' . count( $TAGS ) );
WP_CLI::log( '  Attributes: ' . count( $ATTRIBUTES ) );
WP_CLI::log( '  SEO fields: ' . count( $SEO ) );
WP_CLI::log( '  URL      : https://theartframer.us/product/' . $SLUG . '/' );
WP_CLI::log( '╚══════════════════════════════════════════════════════╝' );
WP_CLI::log( '' );
WP_CLI::log( '  Gallery images (mockups) needed — please upload to Media Library:' );
WP_CLI::log( '  1. Frame Mockup    2. Living Room Mockup  3. Bedroom Mockup' );
WP_CLI::log( '  4. Close-up Texture  5. Side Angle  6. Hanging Wall Scene  7. Lifestyle Scene' );
WP_CLI::log( '  Then run: add-gallery-images.php to attach them to this product.' );
