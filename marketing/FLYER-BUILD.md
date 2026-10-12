# Corporate Printing flyers: Canva build plan

Goal: one Canva file, "The Art Framer - Corporate Printing Flyers 2026", with
33 A4 portrait pages: a cover flyer, then one flyer per rate-card product
(01 to 32, in rate-card order). Every page highlights the same six things in
the same place: brand, size, offer price, struck-through list price, phone,
email. The description on the flyer is one short line.

Source of every fact: the Corporate Rate Card 2026 PDF (9 pages). Prices are
list and 40%-off offer prices exactly as printed. Copy for each product is in
`marketing/flyer-copy.json` (written, then fact-checked against the rate card).

## State when this plan was written

- Canva folder for working files: `FAHXxvUX1Kg` ("Corporate Printing Flyers - working files").
- Style reference the owner approved: design `DAHXxm4sAfU` (navy, brand kit).
  The owner asked for the price, list price, size, email, phone and brand to be
  highlighted more strongly, and for a shorter description. The layout below
  does that.
- Brand kit: `kAFEcaeuHek`.
- Imported Canva image assets (media ids):

| Flyer | Asset | Flyer | Asset |
| --- | --- | --- | --- |
| logo (gold AF mark) | MAHXx8K2NMU | 14 | MAHXxyqKMos |
| 00 cover | MAHXx7deYbo | 16 | MAHXx2wCCg4 |
| 01 | MAHXx7skb00 | 18 | MAHXx7-CHLU |
| 02 | MAHXx5uMluc | 19 | MAHXxwtPYCU |
| 03 | MAHXx-eqGAM | 20 | MAHXx2cWM5M |
| 04 | MAHXxyjeMmk | 21 | MAHXx31aTtE |
| 05 | MAHXxyEwVNo | 22 | MAHXx2-sIyE |
| 06 | MAHXx88-yTM | 23 | MAHXx2-YFWg |
| 07 | MAHXx5FwqJg | 24 | MAHXxxseWIU |
| 08 | MAHXx0qlzOk | | |
| 09 | MAHXxyo3yMk | | |
| 10 | MAHXx8ahHTs | | |
| 12 | MAHXx2Bjjbo | | |

- Still to import with `upload-asset-from-url` from
  `https://raw.githubusercontent.com/theartframer136-glitch/postero-child/claude/confident-ride-vqsg0l/marketing/flyer-assets/<file>`:
  `25-gold-foil-certificate.jpg`, `27-foil-product-label-roll.jpg`,
  `28-logo-jute-tote-bag.jpg`, `29-canvas-shopper-tote.jpg`,
  `30-bamboo-handle-gift-bag.jpg`, `32-folded-corporate-brochure.jpg`.
- Still to generate with `generate-image` (aspect `LANDSCAPE_16_9`, no readable
  text or logos), because the brochure photo shows another product or another
  business's brand:
  - 11 LED Light Box Sign: an 8 ft wide illuminated LED light box sign glowing on a modern shopfront at dusk, blank glowing panel.
  - 13 Die-Cut Brand Sticker: a stack of glossy die-cut vinyl logo stickers in abstract shapes on a white desk, laptop corner in view.
  - 15 Circle Product Label: round 2 inch product labels applied to small amber glass jars and kraft boxes on a light table.
  - 17 Black & Gold Foil Card: a fan of matte black business cards with gold foil stamping on dark marble.
  - 26 Raised Foil Loyalty Card: premium loyalty cards with raised gold foil and a punch grid, held in a hand over a boutique counter.
  - 31 A5 Promotional Flier: a neat stack of glossy A5 promotional fliers with abstract colourful layouts on an office table.

## Page layout (794 x 1123 px)

Colours: navy `#0a192e` background, gold `#e0b457`, plum `#673154`, white
`#ffffff`, muted `#c7cbd3`. Every page is identical except the product text and
photo.

| Element | Position (top, left, width x height) | Style |
| --- | --- | --- |
| Logo image (MAHXx8K2NMU) | 40, 56, 64 x 64 | as is |
| "THE ART FRAMER" | 44, 132, 420 wide | white, bold, 28 |
| "CORPORATE SIGNAGE & LARGE FORMAT PRINTING" | 80, 132, 440 wide | gold, 12 |
| Offer tag: rounded rect | 48, 590, 148 x 44, corner 22 | gold fill |
| Offer tag text "40% OFF" | 56, 590, 148 wide | navy, bold, 22, centred |
| Headline | 140, 56, 682 wide | white, serif, 50, line height 1.05, at most 2 lines |
| Product name pill: rounded rect | 282, 56, 682 x 46, corner 23 | plum fill |
| Product name text | 290, 76, 642 wide | white, bold, 24 |
| Hero photo frame | 345, 56, 682 x 400, corner 16 | product photo, cropped to fill |
| Size badge: rounded rect | 765, 56, 262 x 124, corner 14 | no fill, gold stroke 3 |
| "SIZE" | 778, 76, 222 wide | gold, bold, 14 |
| Size value | 800, 76, 222 wide | white, bold, 24, up to 3 lines |
| Price block: rounded rect | 765, 336, 402 x 124, corner 14 | gold fill |
| Offer price | 772, 358, 360 wide | navy, bold, 56 |
| "List price $X" | 846, 358, 200 wide | navy, 18, strikethrough |
| "Save 40%" | 846, 560, 160 wide | navy, bold, 18, right aligned |
| Flyer line | 908, 56, 682 wide | white, 17, one line |
| Perks line | 942, 56, 682 wide | gold, 14: "Free artwork + 2 designs · Free delivery DE · PA · MD · NJ · Proof in 48 hours" |
| Contact bar: rect | 1003, 0, 794 x 120 | gold fill |
| Phone and email | 1022, 56, 682 wide | navy, bold, 24: "+1 (610) 470-7280  ·  theartframer136@gmail.com" |
| Website and call to action | 1062, 56, 682 wide | navy, 16: "theartframer.us  ·  Send us your logo. We send back 2 designs." |

The cover (page 1) uses the same frame. Its headline is "Your brand, printed
big.", its pill reads "Corporate Printing · Rate Card 2026", its photo is
asset MAHXx7deYbo, and the size badge and price block are replaced by one
gold-outlined panel listing:
Signs from $17.18 · Canvas prints from $45.36 · Stickers from $47.60 ·
Roll-up standees from $70.27 · Banners from $72.00 · Table covers from $73.46 ·
Business cards from $108.19 per 500 · Fliers from $155.40 per 250 ·
Tote bags from $159.60 per 25.

## Steps

1. Copy `DAHXxm4sAfU` and rebuild it to the layout above as the template.
   Delete its old elements except the background, add the elements in the
   table, and commit. Title it "Flyer template - The Art Framer".
2. Import the six remaining photos and generate the six images above.
3. For each product, copy the template, replace the headline, product name,
   size value, offer price, list price and flyer line from
   `marketing/flyer-copy.json`, replace the photo with `update_fill`, title it
   "Flyer NN - <product> - The Art Framer", commit, and move it to the working
   folder. Build the cover the same way.
4. Read every flyer back and check: product name, size, offer price, list price,
   phone, email and website all present and exact; the list price struck
   through; nothing overflowing or overlapping in the thumbnail; no placeholder
   text. Fix and re-check any that fail.
5. Make the single file with `merge-designs` (`create_new_design`), inserting
   page 1 of the cover and then of flyers 01 to 32 in order. Title:
   "The Art Framer - Corporate Printing Flyers 2026". Leave it at the top level.
6. Delete `marketing/flyer-assets/` from this branch once every photo is in Canva.
