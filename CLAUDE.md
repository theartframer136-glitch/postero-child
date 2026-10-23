# Owner rules for this theme (The Art Framer, theartframer.us)

## Nothing on the site is free — the word "free" must not appear anywhere

Owner, 2026-09-10 and again 2026-10-23: the shop does not ship free, does
not deliver free, and offers nothing free. Shipping cost is shown at
checkout. Do not write "free", "free shipping", "free delivery", "free of
charge", "fee-free", "complimentary" or any equivalent on any customer-facing
surface: PHP templates, JavaScript strings, page copy written by tools/,
product or category text, SEO titles and descriptions, blog posts, YouTube
descriptions, structured data, emails, chatbot replies, tickers, badges.

- Shipping wording comes only from `af_shipping_copy()` in functions.php,
  which has no "free" state. Do not add one.
- `tools/test-shipping-copy.php` and `tools/verify-polish.php` assert the rule.
  Keep them passing. `tools/diag-free-shipping*.php` find any claims held in
  the database; `tools/rewrite-free-shipping.php` removes them.
- Damaged or wrong items are replaced per the returns policy. Describe that as
  a replacement or reprint, never as "free".
