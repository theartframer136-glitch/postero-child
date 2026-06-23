# The Art Framer — Product Importer

Tooling to bulk-add products to the WooCommerce store while matching the style
of products already on the site. **This is standalone tooling — it is not loaded
by WordPress.** It talks to the live site only through the WooCommerce REST API.

## Pipeline (planned)

1. **`analyze_products.py`** — *(built)* read-only. Learns from existing products
   and writes a style profile.
2. **`generate.py`** — *(next)* for each new product: write the description in the
   learned voice (Claude API) + composite frame/room mockup images from your art.
3. **`publish.py`** — *(next)* push finished products to WooCommerce (dry-run first,
   then publish), idempotent and resumable.

## Step 1 — Learn from existing products

```bash
cd tools/product-importer
pip install -r requirements.txt
cp .env.example .env          # then edit .env with your real keys
python analyze_products.py
```

Generate the keys at **WooCommerce → Settings → Advanced → REST API → Add key**.
**Read** permission is enough for analysis. Read/Write is needed later to publish.

Outputs land in `./output/` (git-ignored):

- `profile.md` — read this; the human-readable style report
- `profile.json` — machine-readable, fed to the generator
- `products_raw.json` — full catalog cache

## Security

- `.env` holds live API credentials and is **git-ignored**. Never commit it or
  paste keys into chat.
- Step 1 is strictly read-only; nothing on the live site is modified until the
  publish step, which runs in dry-run mode first.
