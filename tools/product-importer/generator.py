#!/usr/bin/env python3
"""
Product content generator for The Art Framer.

Turns a small product spec (subject, size, price, etc.) into a full WooCommerce
product payload — title + long HTML description in the learned house style +
categories + SEO meta — matching STYLE_SPEC.md.

Two narrative modes:
  - If ANTHROPIC_API_KEY is set, Claude writes the unique narrative paragraphs.
  - Otherwise a solid templated narrative is used (zero cost), so this always runs.

The reusable boilerplate (spec table, care block, FAQ accordion, contact info)
is constant across products, exactly as observed in the existing catalog.
"""

import os
import textwrap

# --- Business constants (from the existing catalog) ------------------------- #
PHONE = "+1 (610) 470-7280"
EMAIL = "theartframer136@gmail.com"
LOCATION = "Delaware, USA"
FREE_DELIVERY = "Delaware, Pennsylvania, Maryland, New Jersey & nearby areas"

FRAME_OPTIONS = ["Floating Frame", "Fibre Frame", "Aluminium Frame",
                 "Golden Frame", "Rose Gold Frame", "Silver Frame",
                 "White Frame", "Wooden Frame"]
COLOR_OPTIONS = ["Black", "Silver", "Gold", "Rose Gold", "White", "Wooden"]


# --------------------------------------------------------------------------- #
# Title
# --------------------------------------------------------------------------- #
def build_title(spec):
    """e.g. 'Ganesha Canvas Wall Art 36x48 Inches – Floating Frame – ...'"""
    parts = [
        f"{spec['subject']} Canvas Wall Art {spec['size']}",
        spec.get("frame_label", "Floating Frame"),
        spec.get("style", "Premium Digital Canvas Print"),
        spec.get("use_case", "Pooja Room & Living Room Spiritual Wall Décor"),
    ]
    return " – ".join(p for p in parts if p)


# --------------------------------------------------------------------------- #
# Narrative — AI if available, else template
# --------------------------------------------------------------------------- #
def _ai_narrative(spec):
    """Use Claude to write the unique intro/highlights/symbolism paragraphs."""
    import anthropic  # imported lazily so the template path needs no install

    client = anthropic.Anthropic()  # reads ANTHROPIC_API_KEY from env
    prompt = textwrap.dedent(f"""
        You are writing the unique narrative section of a product description for
        The Art Framer, a premium Hindu/Indian devotional canvas wall-art store.

        Product: {spec['subject']} canvas wall art, size {spec['size']}.
        Voice: warm, devotional, premium, emotionally evocative; blend spiritual
        symbolism with interior-decor and gifting angles. Match this structure and
        return ONLY valid HTML (no markdown, no code fences):

        - 2-3 <p> intro paragraphs (emotional + spiritual hook; mention colors,
          symbolism, the subject).
        - <h4>Product Highlights</h4> then a <ul> of 5 <li> bullets (material,
          fade-resistant inks, gallery-wrapped/ready to hang, sizes, placement).
        - <h4>This artwork is suitable for:</h4> then a <ul> of 4 decor styles.
        - <h4>This artwork represents:</h4> then a <ul> of 5 symbolic meanings.
        - <h4>Why Choose This Artwork?</h4> then 1 <p>.

        Keep it ~450-550 words. Do not include FAQs, care instructions, spec
        tables, or contact info — those are added separately.
    """).strip()

    msg = client.messages.create(
        model="claude-opus-4-8",
        max_tokens=2000,
        messages=[{"role": "user", "content": prompt}],
    )
    return msg.content[0].text.strip()


def _template_narrative(spec):
    subject = spec["subject"]
    size = spec["size"]
    return textwrap.dedent(f"""\
        <p>Bring divine grace and serene beauty into your home with this {subject}
        Canvas Wall Art. Thoughtfully designed to capture spiritual depth and
        emotional warmth, the artwork radiates positivity and calm in any space it
        graces.</p>
        <p>Rich, harmonious colors and elegant detailing create an authentic
        devotional presence, making the piece feel like a true blessing within your
        home. It enhances meditation, prayer, and peaceful vibrations in your
        surroundings.</p>
        <p>Printed on premium high-definition cotton-blend canvas with fade-resistant
        archival inks, the {subject} artwork maintains its clarity and brilliance for
        years, arriving gallery-wrapped and ready to hang.</p>
        <h4>Product Highlights</h4>
        <ul>
        <li>Premium cotton-blend canvas — soft texture, museum quality</li>
        <li>Fade-resistant archival inks — stays vibrant for decades</li>
        <li>Gallery-wrapped with mirrored edges — ready to hang out of the box</li>
        <li>Available in multiple sizes including {size}</li>
        <li>Perfect for living rooms, bedrooms, pooja rooms, and offices</li>
        </ul>
        <h4>This artwork is suitable for:</h4>
        <ul>
        <li>Modern spiritual décor</li>
        <li>Traditional Indian interiors</li>
        <li>Symbolic devotional artwork</li>
        <li>Vibrant color compositions</li>
        </ul>
        <h4>This artwork represents:</h4>
        <ul>
        <li>Divine blessings</li>
        <li>Peace and positivity</li>
        <li>Spiritual connection</li>
        <li>Artistic expression</li>
        <li>Prosperity and harmony</li>
        </ul>
        <h4>Why Choose This Artwork?</h4>
        <p>Choosing this {subject} canvas means selecting a piece that blends timeless
        spiritual symbolism with premium craftsmanship — meaningful, beautiful, and
        built to last, and a thoughtful gift for any auspicious occasion.</p>""")


def build_narrative(spec):
    if os.getenv("ANTHROPIC_API_KEY"):
        try:
            return _ai_narrative(spec)
        except Exception as e:  # fall back rather than fail the whole run
            print(f"  [warn] AI narrative failed ({e}); using template.")
    return _template_narrative(spec)


# --------------------------------------------------------------------------- #
# Reusable boilerplate (constant)
# --------------------------------------------------------------------------- #
def _spec_table(spec):
    sizes = "".join(f"<li>{s}</li>" for s in spec.get("sizes", [spec["size"]]))
    colors = "".join(f"<li>{c}</li>" for c in COLOR_OPTIONS)
    return textwrap.dedent(f"""\
        <table style="width:100%;border-collapse:collapse;font-family:Arial,sans-serif;font-size:14px;">
        <tbody>
        <tr><td style="border:1px solid #ddd;padding:10px;font-weight:bold;width:35%;background-color:#f7f7f7;">Type</td>
        <td style="border:1px solid #ddd;padding:10px;">Digital Canvas Printing</td></tr>
        <tr><td style="border:1px solid #ddd;padding:10px;font-weight:bold;background-color:#f7f7f7;">Multiple Framing Options</td>
        <td style="border:1px solid #ddd;padding:10px;">Fibre, Wooden, Aluminium</td></tr>
        <tr><td style="border:1px solid #ddd;padding:10px;font-weight:bold;background-color:#f7f7f7;">Multiple Sizes Available</td>
        <td style="border:1px solid #ddd;padding:10px;"><ul>{sizes}</ul></td></tr>
        <tr><td style="border:1px solid #ddd;padding:10px;font-weight:bold;background-color:#f7f7f7;">Multiple Colours Available</td>
        <td style="border:1px solid #ddd;padding:10px;"><ul>{colors}</ul></td></tr>
        <tr><td style="border:1px solid #ddd;padding:10px;font-weight:bold;background-color:#f7f7f7;">DIY Collection (Easy Assembly)</td>
        <td style="border:1px solid #ddd;padding:10px;">Includes canvas rolled print, stretcher bars, hanging accessories, screws, plier, frame &amp; accessories <em>(if ordered)</em></td></tr>
        </tbody>
        </table>""")


def _frame_and_care():
    frames = "".join(f"<li>{f}</li>" for f in FRAME_OPTIONS)
    return textwrap.dedent(f"""\
        <h4>Frame &amp; Finish Details</h4>
        <p>This artwork is available with multiple premium frame options to suit
        various interior preferences.</p>
        <ul>{frames}</ul>
        <h4>Finish Quality</h4>
        <p>The canvas features a matte textured finish, which offers reduced glare,
        enhanced color depth, realistic texture, and long-term durability.</p>
        <h4>Care Instructions</h4>
        <ul>
        <li>Dust regularly with a soft, dry cloth.</li>
        <li>Avoid continuous exposure to direct sunlight to preserve color brightness.</li>
        <li>Keep away from moisture for long-lasting vibrancy.</li>
        </ul>""")


_FAQ = f"""\
<h2>Frequently Asked Questions</h2>
<details class="afaq-item"><summary class="afaq-question">What is canvas wall art made of?</summary>
<p class="afaq-answer">Our canvas wall art is made using premium, high-density artist-grade canvas combined with durable wooden or metal frames, ensuring vibrant color reproduction and a premium gallery-style finish.</p></details>
<details class="afaq-item"><summary class="afaq-question">Is digital canvas printing long-lasting?</summary>
<p class="afaq-answer">Yes. Produced with fade-resistant pigment inks and premium canvas, our prints maintain their color vibrancy for many years under normal indoor conditions.</p></details>
<details class="afaq-item"><summary class="afaq-question">Does canvas wall art fade over time?</summary>
<p class="afaq-answer">Our prints use advanced fade-resistant inks. Kept away from direct sunlight and moisture, the artwork retains its richness for years.</p></details>
<details class="afaq-item"><summary class="afaq-question">Is canvas wall art a good gift option?</summary>
<p class="afaq-answer">Absolutely — it is an excellent choice for housewarmings, weddings, festivals, birthdays, and special occasions.</p></details>
<details class="afaq-item"><summary class="afaq-question">What is your customer support contact number?</summary>
<p class="afaq-answer">Reach our team at {PHONE} for product inquiries, order tracking, and customization support.</p></details>
<details class="afaq-item"><summary class="afaq-question">What is your customer support email address?</summary>
<p class="afaq-answer">Email us at {EMAIL} for product inquiries, bulk orders, or support questions.</p></details>
<details class="afaq-item"><summary class="afaq-question">Do you provide WhatsApp support?</summary>
<p class="afaq-answer">Yes — message us on WhatsApp at {PHONE} for quick assistance with product selection and orders.</p></details>
<details class="afaq-item"><summary class="afaq-question">Where is your canvas printing business located?</summary>
<p class="afaq-answer">We operate from {LOCATION} and deliver across multiple locations.</p></details>
<details class="afaq-item"><summary class="afaq-question">Which areas are eligible for free delivery?</summary>
<p class="afaq-answer">Free delivery is available in {FREE_DELIVERY}.</p></details>"""


# --------------------------------------------------------------------------- #
# Pricing
# --------------------------------------------------------------------------- #
SIZE_PRICE = {  # USD, deterministic by size; tune freely
    "20x30": 89.0, "30x40": 129.0, "40x50": 179.0, "50x70": 249.0,
}


def price_for(spec):
    if spec.get("price"):
        return str(spec["price"])
    return str(SIZE_PRICE.get(spec.get("size", ""), 140.0))


# --------------------------------------------------------------------------- #
# Assemble full product
# --------------------------------------------------------------------------- #
def build_product(spec):
    """Return a WooCommerce-ready product dict (minus image URLs)."""
    description = "\n".join([
        build_narrative(spec),
        _spec_table(spec),
        _frame_and_care(),
        _FAQ,
    ])
    short = (f"<p>Premium {spec['subject']} canvas wall art ({spec['size']}), "
             f"gallery-wrapped and ready to hang. Fade-resistant archival inks, "
             f"museum-quality canvas. Multiple frames & sizes available.</p>")

    focus_kw = spec.get("focus_keyword",
                        f"{spec['subject']} canvas wall art".lower())
    categories = [{"name": c} for c in spec.get("categories",
                  ["Digital Canvas Prints"])]

    return {
        "name": build_title(spec),
        "type": "simple",
        "status": spec.get("status", "draft"),
        "regular_price": price_for(spec),
        "description": description,
        "short_description": short,
        "categories": categories,
        "meta_data": [
            {"key": "rank_math_focus_keyword", "value": focus_kw},
            {"key": "rank_math_title", "value": f"{build_title(spec)} | The Art Framer"},
            {"key": "rank_math_description", "value": short.replace("<p>", "").replace("</p>", "")[:155]},
        ],
    }


if __name__ == "__main__":
    # Demo: build one sample product and print it.
    import json
    sample = {
        "subject": "Lord Ganesha",
        "size": "36x48 Inches",
        "sizes": ["24x36 Inches", "36x48 Inches"],
        "style": "Premium Digital Canvas Print",
        "use_case": "Pooja Room & Living Room Spiritual Wall Décor",
        "categories": ["Digital Canvas Prints", "Hindu Deities"],
        "status": "draft",
    }
    product = build_product(sample)
    print(json.dumps({k: v for k, v in product.items() if k != "description"}, indent=2))
    print("\n--- DESCRIPTION HTML ---\n")
    print(product["description"])
