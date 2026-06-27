# MCP server setup — create products by chatting with Claude

This exposes The Art Framer store to an AI agent as tools. Once set up, you can
tell Claude Desktop things like *"Add a Radha Krishna canvas, size 30x40, image
<url>, category Radha Krishna"* and it creates the full product — description,
technical-specs table, attributes, tags, categories, gallery — automatically.

It reuses the same generator as the CSV script, so the output is identical.

## When to use which
- **Bulk (all 200 at once):** use the CSV script — `python publish.py`. Faster.
- **Interactive / one-at-a-time / "look at this image and add it":** use MCP.

## 1. Install
```powershell
cd ~\Documents\postero-child\tools\product-importer
pip install -r requirements.txt
```
Make sure `.env` has your **Read/Write** WooCommerce key (same file the script uses).

## 2. Register the server in Claude Desktop
Open Claude Desktop's config file:
- Windows: `%APPDATA%\Claude\claude_desktop_config.json`
- (create it if it doesn't exist)

Add this (keep any existing servers; merge into the same `mcpServers` object).
Use the FULL path to python and to mcp_server.py:

```json
{
  "mcpServers": {
    "art-framer": {
      "command": "python",
      "args": [
        "C:\\Users\\user\\Documents\\postero-child\\tools\\product-importer\\mcp_server.py"
      ]
    }
  }
}
```

Tip: if `python` isn't found by Claude Desktop, use the full python path
(run `where python` in PowerShell to find it) in the `"command"` field.

## 3. Restart Claude Desktop
Fully quit and reopen it. You should see the `art-framer` tools available
(hammer/tools icon). 

## 4. Use it
Example prompts:
- "List my store categories."
- "Create a Lord Ganesha canvas, size 36x48 Inches, price 179, categories
  Digital Canvas Prints and Hindu Deities, image https://...ganesha.webp"
- (with vision) drag in an artwork image: "Add this as a product — identify the
  deity, write the listing, and use this image."

The agent calls `create_product`, which builds the complete product and posts it
as a **draft** (say "publish" if you want it live).

## Tools the server exposes
- `list_categories()` — existing categories, so the agent picks valid ones.
- `create_product(subject, size, image_urls, categories, tags, price, sizes,
  use_case, focus_keyword, sku, status)` — builds + publishes one full product.
