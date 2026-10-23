# Owner rules for this theme (The Art Framer, theartframer.us)

## Nothing on the site is free — the word "free" must not appear anywhere

This applies to EVERY piece of text written for The Art Framer, whether it
goes on the site, into a tool, a feed, a post, a video description, an ad or
a reply: no sentence may contain the word "free" in any form.

Owner, 2026-09-10 and again 2026-10-23: the shop does not ship free, does
not deliver free, and offers nothing free. Shipping cost is shown at
checkout. Do not write "free", "free shipping", "free delivery", "free of
charge", "fee-free", "complimentary" or any equivalent on any customer-facing
surface: PHP templates, JavaScript strings, page copy written by tools/,
product or category text, SEO titles and descriptions, blog posts, YouTube
descriptions, structured data, emails, chatbot replies, tickers, badges.

- Shipping wording comes only from `af_shipping_copy()` in functions.php,
  which has no "free" state. Do not add one.
- The content tools reject any text containing the word: create-seo-posts,
  publish-seo-posts, fix-product-seo-meta and youtube-seo.mjs all skip it.
- `tools/test-shipping-copy.php` and `tools/verify-polish.php` assert the rule.
  Keep them passing. `tools/diag-free-shipping*.php` find any claims held in
  the database; `tools/rewrite-free-shipping.php` removes them.
- Damaged or wrong items are replaced per the returns policy. Describe that as
  a replacement or reprint, never as "free".
