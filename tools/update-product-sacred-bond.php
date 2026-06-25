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
$SHORT_DESC = '
<p>A divine celebration of eternal devotion — the <strong>Radha Krishna Sacred Bond Canvas Wall Art</strong> captures the sacred union of Radha and Krishna in a breathtaking symphony of colour, emotion, and spiritual grace. Every brushstroke tells the story of a love that transcends time, rendered with luminous depth and artistry that commands attention the moment it enters a room.</p>
<p>Produced on <strong>premium archival-grade cotton-blend canvas</strong> using <strong>eco-friendly, UV-resistant inks</strong>, this gallery-wrapped masterpiece is built to retain its brilliance for generations. It arrives fully finished — stretched, mounted, and ready to hang — with no framing required.</p>
<p>Available in multiple sizes. A timeless centrepiece for your home, prayer room, or as a meaningful gift.</p>
';

/* ── LONG DESCRIPTION ───────────────────────────────────────── */
$LONG_DESC = '
<!-- ═══ INTRODUCTION ═══════════════════════════════════════ -->
<h2>Radha Krishna Sacred Bond — A Divine Love Story on Canvas</h2>

<p>There are few images in the world as universally recognised and deeply felt as that of Radha and Krishna — the embodiment of divine love, devotion, and the eternal bond between the soul and the Supreme. The <strong>Radha Krishna Sacred Bond Canvas Wall Art</strong> brings this timeless story into your living space with a presence that is both visually stunning and spiritually uplifting.</p>

<p>This is not simply a print. It is a carefully crafted artistic statement — a tribute to one of the most celebrated love stories in human history, rendered with the precision, quality, and reverence it deserves.</p>

<!-- ═══ ARTWORK STORY ════════════════════════════════════════ -->
<h2>The Artwork Story</h2>

<p>The composition draws the viewer into an intimate, sacred moment between Radha and Krishna — their figures rendered in rich, jewel-like tones that radiate warmth and devotion. The colour palette blends deep blues, golden ochres, and luminous whites to create a sense of divine light emanating from within the artwork itself.</p>

<p>The fluid lines and expressive forms speak the language of classical Indian art while maintaining a contemporary vibrancy that makes this piece equally at home in a traditional pooja room or a modern living space. Every element — the positioning of the figures, the play of light and shadow, the richness of colour — has been composed with artistic intention and emotional sensitivity.</p>

<!-- ═══ SPIRITUAL & CULTURAL SIGNIFICANCE ═══════════════════ -->
<h2>Spiritual &amp; Cultural Significance</h2>

<p>In Hindu tradition, Radha and Krishna are far more than a romantic ideal — they are a profound spiritual metaphor. Krishna represents the Supreme Consciousness, and Radha the eternal devotee — the soul in its purest yearning for the divine. Together, they symbolise the highest form of love: selfless, unconditional, and transcendent.</p>

<p>Displaying this artwork in your home is believed to invite positive energy, devotion, and a sense of peace. It is a constant, beautiful reminder of the spiritual values of love, surrender, and divine grace — values that resonate across faiths and cultures.</p>

<p>Whether placed in a prayer room, meditation space, living room, or bedroom, this canvas carries a meaning far beyond its visual beauty.</p>

<!-- ═══ INTERIOR DÉCOR SUGGESTIONS ══════════════════════════ -->
<h2>Interior Décor Suggestions</h2>

<p>The <strong>Radha Krishna Sacred Bond Canvas Wall Art</strong> is a versatile statement piece that elevates any interior setting:</p>

<ul>
<li><strong>Pooja Room / Prayer Space:</strong> Create a focal point of devotion with this canvas as the centrepiece of your sacred space, surrounded by soft lighting and floral arrangements.</li>
<li><strong>Living Room Feature Wall:</strong> Hang as a large-format centrepiece on your main wall to anchor the room with colour, meaning, and visual drama.</li>
<li><strong>Master Bedroom:</strong> Place above the headboard for a serene, spiritually grounding atmosphere that encourages restful sleep and positive mornings.</li>
<li><strong>Meditation Room:</strong> Let the divine imagery deepen your practice and create an atmosphere of calm and focus.</li>
<li><strong>Entrance / Hallway:</strong> Welcome guests and family members with a powerful first impression of beauty and grace.</li>
<li><strong>Office or Study:</strong> Add a meaningful, inspiring presence to your professional space that reflects your values and aesthetic.</li>
</ul>

<p>Pair with warm ambient lighting, natural textures, or a simple ornamental frame to enhance the artwork\'s natural presence.</p>

<!-- ═══ PREMIUM PRINTING QUALITY ════════════════════════════ -->
<h2>Premium Printing Quality</h2>

<p>At The Art Framer, quality is not a feature — it is our foundation. Every canvas we produce goes through a rigorous multi-stage quality process to ensure the final product reflects the original artwork with complete fidelity.</p>

<p>We use <strong>professional-grade archival Giclée printing technology</strong> — the same process used by fine art galleries and museums worldwide. This technique deposits ink at microscopic precision, resulting in extraordinary colour accuracy, tonal depth, and edge sharpness that standard printing methods simply cannot match.</p>

<p>The result is a canvas that looks and feels like a hand-crafted original — not a reproduction.</p>

<!-- ═══ ECO-FRIENDLY INKS ═════════════════════════════════════ -->
<h2>Eco-Friendly, UV-Resistant Archival Inks</h2>

<p>We are committed to producing art that is as responsible as it is beautiful. All of our canvas prints are produced using <strong>eco-friendly, water-based archival inks</strong> that are:</p>

<ul>
<li>Free from harmful solvents and VOCs</li>
<li>Safe for home environments including children\'s rooms</li>
<li>UV-resistant — protecting colours from fading for <strong>75+ years</strong> under normal indoor conditions</li>
<li>Colour-stable — maintaining the original vibrancy and accuracy of the artwork over decades</li>
</ul>

<p>You invest once. The art stays with you — and your family — for a lifetime.</p>

<!-- ═══ GALLERY WRAPPED CANVAS ════════════════════════════════ -->
<h2>Gallery Wrapped Canvas — Ready to Hang</h2>

<p>Every canvas from The Art Framer is produced as a <strong>gallery-wrapped print</strong> — meaning the artwork extends around the edges of the stretcher frame, creating a clean, frameless presentation that looks polished and professional from every angle.</p>

<p>Key features of our gallery-wrap production:</p>

<ul>
<li><strong>Premium cotton-blend canvas</strong> — smooth texture with just enough grain to give depth and authenticity</li>
<li><strong>Kiln-dried solid wood stretcher bars</strong> — moisture-resistant, warp-proof, and built for longevity</li>
<li><strong>Hand-stretched and stapled</strong> on the back for a clean front finish</li>
<li><strong>Pre-installed D-ring hooks</strong> — hang straight out of the box, no tools needed beyond a single nail</li>
<li>Arrives <strong>fully assembled</strong> — no additional framing required</li>
</ul>

<!-- ═══ FRAME OPTIONS ═════════════════════════════════════════ -->
<h2>Frame Options</h2>

<p>While the gallery-wrapped canvas is designed to stand beautifully on its own, we also offer optional framing to complement different interior styles:</p>

<ul>
<li><strong>Unframed (Gallery Wrapped):</strong> Clean, contemporary, minimalist — the artwork speaks for itself. Ideal for modern and transitional interiors.</li>
<li><strong>Classic Wood Frame:</strong> A traditional deep-profile wooden frame that adds warmth and a gallery-quality finish. Available in walnut, oak, and black.</li>
<li><strong>Floater Frame:</strong> A contemporary frame style where the canvas appears to float within the frame — adding dimension and a luxury hotel aesthetic. Available in gold, silver, and matte black.</li>
</ul>

<p>Select your preferred frame option from the product options above, or contact our team for bespoke framing advice.</p>

<!-- ═══ PERFECT GIFT OCCASIONS ════════════════════════════════ -->
<h2>Perfect Gift for Every Occasion</h2>

<p>The <strong>Radha Krishna Sacred Bond Canvas Wall Art</strong> makes a deeply meaningful and memorable gift. It speaks of love, devotion, and beauty — values that resonate universally. We recommend it for:</p>

<ul>
<li>Weddings and marriage anniversaries</li>
<li>Housewarming ceremonies (Griha Pravesh)</li>
<li>Diwali, Janmashtami, and other festive occasions</li>
<li>Birthdays and milestone celebrations</li>
<li>Spiritual gifting for devotees and art lovers</li>
<li>Corporate gifting with a cultural and artistic dimension</li>
</ul>

<p>All orders can be gifted with complimentary gift wrapping and a personalised message card upon request. Contact us before placing your order.</p>

<!-- ═══ CARE INSTRUCTIONS ═════════════════════════════════════ -->
<h2>Care Instructions</h2>

<p>Your canvas is built to last with minimal maintenance. To keep it looking its best:</p>

<ul>
<li>Wipe gently with a <strong>soft, dry microfibre cloth</strong> to remove dust — never wet-clean the canvas surface</li>
<li>Avoid placing in <strong>direct sunlight</strong> for extended periods, even with UV-resistant inks</li>
<li>Keep away from areas of <strong>high humidity</strong> such as bathrooms and uncovered kitchen walls</li>
<li>Do not use <strong>chemical cleaners, sprays, or abrasive materials</strong> on the canvas</li>
<li>If the canvas ever needs re-stretching, contact our support team — we will advise you at no charge</li>
</ul>

<!-- ═══ PRODUCT SPECIFICATIONS ════════════════════════════════ -->
<h2>Product Specifications</h2>

<table>
<tbody>
<tr><th>Material</th><td>Premium Cotton-Blend Canvas</td></tr>
<tr><th>Print Type</th><td>Archival Giclée Print</td></tr>
<tr><th>Ink</th><td>Eco-Friendly UV-Resistant Archival Inks</td></tr>
<tr><th>Frame Options</th><td>Unframed (Gallery Wrapped) / Classic Wood Frame / Floater Frame</td></tr>
<tr><th>Hanging Ready</th><td>Yes — D-Ring Hooks Pre-Installed</td></tr>
<tr><th>Indoor Use</th><td>Yes</td></tr>
<tr><th>Available Sizes</th><td>12×16 in · 16×20 in · 20×24 in · 24×30 in · 24×36 in · 30×40 in · 36×48 in</td></tr>
<tr><th>Care Instructions</th><td>Wipe with soft dry cloth. Avoid direct sunlight and moisture.</td></tr>
<tr><th>Dispatch</th><td>3–5 Business Days</td></tr>
<tr><th>Shipping</th><td>Fully Insured. Tracked Delivery.</td></tr>
</tbody>
</table>

<!-- ═══ WHY CHOOSE THE ART FRAMER ═════════════════════════════ -->
<h2>Why Choose The Art Framer?</h2>

<ul>
<li><strong>Museum-Quality Giclée Printing</strong> — The same technology trusted by fine art galleries worldwide</li>
<li><strong>Archival Longevity</strong> — Colours guaranteed vivid for 75+ years</li>
<li><strong>Eco-Conscious Production</strong> — Sustainable inks and materials, safe for your home and the planet</li>
<li><strong>Gallery-Wrapped &amp; Ready to Hang</strong> — No assembly, no waiting, no extra cost</li>
<li><strong>Multiple Sizes &amp; Frame Options</strong> — A perfect fit for every wall and every style</li>
<li><strong>Insured Delivery</strong> — Every order fully tracked and insured from our studio to your door</li>
<li><strong>Dedicated Customer Support</strong> — Real people who care about your experience before, during, and after your purchase</li>
<li><strong>Satisfaction Guaranteed</strong> — If you are not delighted, we will make it right</li>
</ul>

<p><em>The Art Framer — Where Art Meets Soul.</em></p>
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
