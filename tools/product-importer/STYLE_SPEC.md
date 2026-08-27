# The Art Framer — Product Style Spec

Derived from analysis of 104 existing products (see `analyze_products.py`).
This is the template the generator must reproduce so new products are
indistinguishable from the existing catalog.

## Niche / voice
Hindu & Indian devotional canvas wall art (Radha Krishna, Lord Venkateswara/Balaji,
Buddha, Shiva, Lakshmi Ganesha, Seven Horses, etc.), sold from Delaware, USA to the
US Indian diaspora. Voice: warm, devotional, premium, emotionally evocative; blends
spiritual symbolism with interior-decor and gifting angles.

## Title pattern
- Length: ~94 chars, keyword-rich (SEO).
- Separators: en-dash `–` (preferred) and pipe `|`.
- Shape: `{Subject} Canvas Wall Art {Size} – {Frame Type} – {Style descriptors} – {Use case / room}`
- Examples:
  - "Lord Venkateswara Balaji Canvas Wall Art 5×3 Feet – Floating Frame – Tirupati Balaji Temple Style Hindu God Painting – Pooja Room Spiritual Wall Décor"
  - "Radha Krishna Sacred Bond Canvas Wall Art | Divine Love Digital Canvas Print"

## Description skeleton (HTML, ~700–800 words)
Order observed across the catalog:
1. 2–4 `<p>` intro paragraphs — emotional + spiritual hook, mentions subject, colors, symbolism.
2. `<h4>Product Highlights</h4>` + `<ul><li>` — material, fade-resistance, gallery-wrap, sizes, placement.
3. Spec `<table>` — rows: Type, Multiple Framing Options, Multiple Sizes, Multiple Colours, DIY Collection.
4. `<h4>This artwork is suitable for:</h4>` + `<ul>` (decor styles).
5. `<h4>This artwork represents:</h4>` + `<ul>` (symbolic meanings).
6. Embedded product images (infographic, placement mockup, care card, "other collections" banner).
7. `<h4>Frame & Finish Details</h4>`, `<h4>Finish Quality</h4>`, `<h4>Care Instructions</h4>`.
8. `<h2>Frequently Asked Questions</h2>` + `<details class="afaq-item"><summary class="afaq-question">…</summary><p class="afaq-answer">…</p></details>` block.

### Reusable boilerplate (constant across products — do NOT regenerate per product)
- FAQ accordion (~13 Q&As). Product-agnostic except subject noun.
- Contact details: phone +1 (610) 470-7280, email theartframer136@gmail.com,
  WhatsApp +1 (610) 470-7280, location Delaware, USA; free delivery DE/PA/MD/NJ.
- Care instructions block.
- Spec table structure (values vary by product).

### AI-generated per product (unique narrative)
- Intro paragraphs, Product Highlights phrasing, "suitable for" / "represents" lists,
  "Why Choose This Artwork" paragraph. Keep within the voice above.

## Images
- Bimodal: ~55% of products have 1 image; ~40% have the "full treatment" of 7–9.
- Full treatment gallery typically: artwork-on-canvas hero, frame mockups,
  room/placement scene, e-commerce infographic, care-instructions card,
  "other collections" banner.
- Format: `.webp`, hosted in /wp-content/uploads. Widths ~1376–1536.

## Categories (existing taxonomy — assign from these, do not invent)
Primary: Digital Canvas Prints (44), Art Accessories (34). Deity/subject:
Radha Krishna, Hindu Deities, Buddha, Sikh Art, Lord Shiva, Lakshmi Ganesha,
Seven Horses, Tirupati Balaji, Swaminarayan, Lord Rama. Space: Living Room Decor,
Kids Room Decor, Café Decor, Home Decor by Space. Type: Rolled Canvas,
Printable Art, Digital Downloads, Premium Aluminium Frames, Wooden Frames.

## Attributes (when product is variable)
- frame: Classic Oak, Aluminium Premium Black, Aluminium Premium Silver,
  Aluminium Premium White, No Frame
- size: 20x30, 30x40, 40x50, 50x70
- Colors: Black, Golden, Rose Gold, Silver

## Pricing
- Existing: min 1, median 140, mean 146, max ~554 (USD). Inconsistent — only 62/104 priced.
- New products need a deterministic price rule (likely by size). TBD with owner.

## SEO — Rank Math (not Yoast)
Set via product meta_data on create:
- `rank_math_focus_keyword`
- `rank_math_title`
- `rank_math_description`
- `rank_math_primary_product_cat`
