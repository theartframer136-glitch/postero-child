# The Art Framer — Full Quickstart (MCP + Compositor)

Everything from start to finish. Two ways to add products; both produce
identical, complete products (full description, technical-specs table,
attributes, tags, categories, and an auto-composited image gallery).

- **CSV script** — best for bulk (all 200 at once).
- **MCP server** — best for chatting one-at-a-time / "look at this image and add it".

---

## PART A — One-time setup (do once)

### A1. Get the code
```powershell
cd ~\Documents
git clone https://github.com/theartframer136-glitch/postero-child.git   # skip if already cloned
cd postero-child
git checkout claude/zen-fermi-ez7xyy
git pull origin claude/zen-fermi-ez7xyy
cd tools\product-importer
```

### A2. Install dependencies
```powershell
pip install -r requirements.txt
```

### A3. Create your secrets file
```powershell
copy .env.example .env
notepad .env      # (or: start notepad.exe .env)
```
Fill in 5 values:
```
WC_STORE_URL=https://theartframer.us
WC_CONSUMER_KEY=ck_...          # WooCommerce key, Read/Write permission
WC_CONSUMER_SECRET=cs_...
WP_APP_USER=your_wp_username    # for uploading gallery images
WP_APP_PASSWORD=xxxx xxxx xxxx xxxx xxxx xxxx
```

**Where to get the WooCommerce key:** WooCommerce > Settings > Advanced > REST API
> Add key > Permissions = **Read/Write** > copy both values.

**Where to get the WordPress Application Password:** WP Admin > Users > Profile >
scroll to "Application Passwords" > type a name ("importer") > Add > copy the
generated password (with spaces) into WP_APP_PASSWORD.

Save and close the file. Setup is done.

---

## PART B — Bulk upload with the CSV script

### B1. Build your product list
```powershell
copy products_template.csv products.csv
notepad products.csv
```
One product per row. Columns:
`subject, size, sizes, style, use_case, categories, tags, focus_keyword, price, image, sku`
- `image` = the REAL artwork URL (one URL is enough — the gallery is generated).
- `categories` and `sizes`/`tags` use `|` to separate multiple values.

### B2. Preview (nothing is posted)
```powershell
python publish.py --dry-run
```

### B3. Create the products as drafts, with auto-gallery
```powershell
python publish.py --gallery
```
- Add `--limit 1` to do just the first row as a test.
- Add `--publish` to make them live instead of draft (do this only after review).

Each product gets: original image + black/oak/white frame mockups + room scene,
plus the full description, specs table, attributes, tags, categories, and SEO.

---

## PART C — Chat upload with the MCP server (Claude Desktop)

### C1. Register the server
Open `%APPDATA%\Claude\claude_desktop_config.json` (create if missing) and add:
```json
{
  "mcpServers": {
    "art-framer": {
      "command": "python",
      "args": ["C:\\Users\\user\\Documents\\postero-child\\tools\\product-importer\\mcp_server.py"]
    }
  }
}
```
(If `python` isn't found, run `where python` and use the full path.)

### C2. Restart Claude Desktop
Fully quit and reopen. The `art-framer` tools appear (🔨 icon).

### C3. Use it
- "List my store categories."
- "Create a Lord Shiva canvas, size 36x48 Inches, price 179, category Hindu
  Deities, image <url>, generate the gallery automatically."
- (vision) Drag an artwork image in: "Add this as a product — identify the
  subject, write the listing, generate the gallery, category Hindu Deities."

It posts as a **draft** by default (say "publish" to go live).

---

## What gets created (both methods)
- Title in house style + ~750-word SEO description
- `_technical_specs` table (19-row spec table + Additional Information)
- Product attributes (Type/Frame/Size/Colour)
- Tags + correct categories (resolved to IDs)
- Rank Math SEO (focus keyword, title, meta description)
- Image gallery: original + 3 frame mockups + room scene

## Files
- `analyze_products.py` — learn style from existing products (already run)
- `generator.py` — builds the product content
- `compositor.py` — turns one image into a gallery
- `publish.py` — bulk CSV publisher (`--gallery`, `--dry-run`, `--limit`, `--publish`)
- `mcp_server.py` — MCP server for Claude Desktop
- `.env` — your secrets (never committed)
