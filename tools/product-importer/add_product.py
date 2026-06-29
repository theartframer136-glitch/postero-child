#!/usr/bin/env python3
"""
Interactive product adder — no CSV editing, no PowerShell Set-Content.

Just run:
    python add_product.py

Then answer the prompts. Press Enter to accept the [default] shown in brackets.
It builds the full product (description, technical-specs table, attributes,
categories, SEO), optionally auto-generates the framed + room gallery from one
image, creates Size/Frame/Colour variations, and posts it. Then asks if you want
to add another — so you can add many without re-running anything.
"""

import re
import time

import generator
from publish import (load_config, sku_exists, assemble_images, resolve_terms,
                     name_terms, create_product, create_variations)


def ask(prompt, default=""):
    suffix = f" [{default}]" if default else ""
    return input(f"  {prompt}{suffix}: ").strip() or default


def yes(prompt, default="y"):
    return ask(prompt + " (y/n)", default).lower().startswith("y")


def slug(s):
    return re.sub(r"[^A-Z0-9]+", "-", s.upper()).strip("-")


def add_one(cfg):
    print("\n--- New product ---")
    subject = ask("Artwork subject (e.g. Lord Ganesha)")
    if not subject:
        print("  Subject is required.")
        return
    sizes = [s.strip() for s in
             ask("Sizes (comma separated)", "24x36 Inches, 36x48 Inches").split(",")
             if s.strip()]
    categories = [c.strip() for c in
                  ask("Categories (comma separated)", "Digital Canvas Prints, Hindu Deities").split(",")
                  if c.strip()]
    images = [u.strip() for u in ask("Image URL(s) (comma separated)").split(",") if u.strip()]
    if not images:
        print("  At least one image URL is required.")
        return
    gallery = yes("Auto-generate framed + room gallery from the first image?", "y")
    price = ask("Base price (blank = auto by size)")
    sku = ask("SKU", f"TAF-{slug(subject)}-{int(time.time()) % 100000}")
    status = ask("Publish now or save as draft? (draft/publish)", "draft")

    if sku_exists(cfg, sku):
        print(f"  SKU {sku} already exists — skipped.")
        return

    spec = {"subject": subject, "size": sizes[0], "sizes": sizes,
            "categories": categories, "sku": sku, "status": status}
    if price:
        spec["price"] = price

    payload = generator.build_product(spec)
    payload["sku"] = sku
    print("  building images" + (" + gallery (this can take a few seconds)" if gallery else "") + " ...")
    payload["images"] = assemble_images(cfg, images, sku, gallery=gallery, dry_run=False)
    payload["categories"] = resolve_terms(cfg, "categories",
                                          [c["name"] for c in payload["categories"]])
    payload["tags"] = name_terms([t["name"] for t in payload["tags"]])

    product = create_product(cfg, payload)
    print(f"  OK  created id={product['id']} status={product['status']}")
    if payload.get("type") == "variable":
        n = create_variations(cfg, product["id"], generator.build_variations(spec))
        print(f"  OK  {n} variations (size x frame x colour)")
    print(f"  Edit: {cfg['url']}/wp-admin/post.php?post={product['id']}&action=edit")


def main():
    cfg = load_config()
    print("=== The Art Framer — add a product ===")
    print("Answer the prompts; press Enter for the [default]. Ctrl+C to quit.")
    while True:
        try:
            add_one(cfg)
        except Exception as e:  # don't crash the whole session on one bad product
            print(f"  ERROR: {e}")
        if not yes("\nAdd another product?", "n"):
            print("Done.")
            break


if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        print("\nCancelled.")
