#!/usr/bin/env python3
"""
Retrofit existing products: rebuild their description (with the embedded
care card, collections banner, and placement image) and the technical-specs
table — WITHOUT touching their images, variations, price, or categories.

Use this for products created before the description-images update.

Usage:
    python update_product.py 11617              # rebuild text/description only
    python update_product.py --images 11617     # ALSO regenerate images (Gemini/etc.)
    python update_product.py --images 11617 11595 11588
"""

import argparse
import re

import requests

import generator
from publish import load_config, assemble_images


def parse_spec(product):
    """Recover the minimal spec from an existing product."""
    name = product.get("name", "")
    subject = name.split(" Canvas Wall Art")[0].strip() or "Canvas Wall Art"
    m = re.search(r"Canvas Wall Art\s+([\d.]+\s*[x×]\s*[\d.]+\s*(?:Inches|Feet|ft)?)",
                  name, re.I)
    size = (m.group(1).strip() if m else "36x48 Inches")
    categories = [c["name"] for c in product.get("categories", [])] or ["Digital Canvas Prints"]
    gallery_urls = [img["src"] for img in product.get("images", []) if img.get("src")]
    return {
        "subject": subject,
        "size": size,
        "sizes": [size],
        "categories": categories,
        "sku": product.get("sku", ""),
        "gallery_urls": gallery_urls,
    }


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("ids", nargs="+", help="product IDs to update")
    ap.add_argument("--images", action="store_true",
                    help="also regenerate the gallery + description images (Gemini/mockups/compositor)")
    args = ap.parse_args()

    cfg = load_config()
    base = f"{cfg['url']}/wp-json/wc/v3/products"

    for pid in args.ids:
        r = requests.get(f"{base}/{pid}", auth=(cfg["ck"], cfg["cs"]), timeout=60)
        if r.status_code != 200:
            print(f"[{pid}] not found (HTTP {r.status_code})")
            continue
        product = r.json()
        spec = parse_spec(product)

        new_images = None
        if args.images:
            source = (spec.get("gallery_urls") or [None])[0]  # first image = original art
            if not source:
                print(f"[{pid}] no source image to regenerate from — skipping images")
            else:
                print(f"[{pid}] regenerating images from {source} ...")
                new_images = assemble_images(cfg, [source], product.get("sku") or str(pid),
                                             gallery=True, dry_run=False)
                spec["gallery_urls"] = [im["src"] for im in new_images]

        rebuilt = generator.build_product(spec)
        update = {
            "description": rebuilt["description"],
            "meta_data": [m for m in rebuilt["meta_data"] if m["key"] == "_technical_specs"],
        }
        if new_images:
            update["images"] = new_images

        u = requests.put(f"{base}/{pid}", auth=(cfg["ck"], cfg["cs"]),
                         json=update, timeout=180)
        if u.status_code in (200, 201):
            imgs = update["description"].count("<img")
            extra = f", {len(new_images)} gallery images regenerated" if new_images else ""
            print(f"[{pid}] updated '{spec['subject']}' — {imgs} description images{extra}")
        else:
            print(f"[{pid}] update failed (HTTP {u.status_code}): {u.text[:200]}")


if __name__ == "__main__":
    main()
