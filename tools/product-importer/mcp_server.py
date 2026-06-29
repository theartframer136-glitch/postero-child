#!/usr/bin/env python3
"""
MCP server for The Art Framer — lets an AI agent (e.g. Claude Desktop) create
full WooCommerce products by calling tools, instead of running the CSV script.

It REUSES generator.py + publish.py, so every product it creates is identical to
the script's output: full description, the _technical_specs table, attributes,
tags, categories (resolved to IDs), and a multi-image gallery.

Setup (run once):
    pip install -r requirements.txt          # installs the `mcp` package too
    # ensure .env has WC_STORE_URL / WC_CONSUMER_KEY (Read/Write) / WC_CONSUMER_SECRET

Then register it in Claude Desktop's config (see README) and restart Claude.

Tools exposed:
    list_categories()  -> the store's existing categories (so the agent picks valid ones)
    create_product(...) -> builds + publishes one complete product, returns its URL
"""

from typing import Optional

import requests
from mcp.server.fastmcp import FastMCP

import generator
from publish import (load_config, resolve_terms, name_terms, assemble_images,
                     sku_exists, create_product as wc_create_product,
                     create_variations)

mcp = FastMCP("art-framer")


@mcp.tool()
def list_categories() -> str:
    """List the store's existing product categories. Call this first so you
    assign new products to categories that already exist."""
    cfg = load_config()
    r = requests.get(f"{cfg['url']}/wp-json/wc/v3/products/categories",
                     auth=(cfg["ck"], cfg["cs"]),
                     params={"per_page": 100}, timeout=60)
    r.raise_for_status()
    return ", ".join(sorted(c["name"] for c in r.json()))


@mcp.tool()
def create_product(
    subject: str,
    size: str,
    image_urls: list[str],
    categories: Optional[list[str]] = None,
    tags: Optional[list[str]] = None,
    price: Optional[float] = None,
    sizes: Optional[list[str]] = None,
    use_case: Optional[str] = None,
    focus_keyword: Optional[str] = None,
    sku: Optional[str] = None,
    generate_gallery: bool = False,
    status: str = "draft",
) -> str:
    """Create one complete WooCommerce product in The Art Framer house style.

    Builds the full description, the technical-specs table, attributes, tags,
    and SEO automatically — you only supply the basics.

    Args:
        subject: The artwork subject, e.g. "Lord Ganesha" or "Radha Krishna".
        size: Main size, e.g. "36x48 Inches" (written height x width).
        image_urls: Public image URLs. The first becomes the featured image;
            the rest form the gallery. Use the REAL artwork image so the name
            matches the picture.
        categories: Category names (use list_categories to pick existing ones).
        tags: Optional extra tags; sensible ones are added automatically.
        price: Price in USD. Defaults by size if omitted.
        sizes: All offered sizes; defaults to [size].
        use_case: e.g. "Pooja Room & Living Room Spiritual Wall Décor".
        focus_keyword: SEO focus keyword; derived from subject if omitted.
        sku: Unique stock code; skipped if it already exists.
        generate_gallery: If true, composite the first image into framed
            (black/oak/white) + room-scene mockups automatically — one image in,
            a full gallery out. Needs WP_APP_USER/WP_APP_PASSWORD in .env.
        status: "draft" (default) or "publish".

    Returns:
        A summary with the new product's id and URL.
    """
    cfg = load_config()

    if sku and sku_exists(cfg, sku):
        return f"Skipped: a product with SKU '{sku}' already exists."

    spec = {
        "subject": subject,
        "size": size,
        "sizes": sizes or [size],
        "categories": categories or ["Digital Canvas Prints"],
        "tags": tags or [],
        "focus_keyword": focus_keyword or "",
        "sku": sku or "",
        "status": status,
    }
    if use_case:
        spec["use_case"] = use_case
    if price is not None:
        spec["price"] = str(price)

    payload = generator.build_product(spec)
    if sku:
        payload["sku"] = sku
    payload["images"] = assemble_images(
        cfg, image_urls, sku or subject.replace(" ", "-"),
        gallery=generate_gallery, dry_run=False)
    payload["categories"] = resolve_terms(
        cfg, "categories", [c["name"] for c in payload["categories"]])
    payload["tags"] = name_terms([t["name"] for t in payload["tags"]])

    product = wc_create_product(cfg, payload)
    extra = ""
    if payload.get("type") == "variable":
        n = create_variations(cfg, product["id"], generator.build_variations(spec))
        extra = f"\nVariations created: {n} (size x frame x colour)"
    return (f"Created product id={product['id']} status={product['status']}{extra}\n"
            f"Title: {product['name']}\n"
            f"Edit: {cfg['url']}/wp-admin/post.php?post={product['id']}&action=edit\n"
            f"View: {product.get('permalink', '')}")


if __name__ == "__main__":
    mcp.run()
