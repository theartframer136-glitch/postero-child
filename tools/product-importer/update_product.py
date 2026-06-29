#!/usr/bin/env python3
"""
Retrofit existing products: rebuild their description (with the embedded
care card, collections banner, and placement image) and the technical-specs
table — WITHOUT touching their images, variations, price, or categories.

Use this for products created before the description-images update.

Usage:
    python update_product.py 11617              # one product by ID
    python update_product.py 11617 11595 11588  # several
"""

import re
import sys

import requests

import generator
from publish import load_config


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
    if len(sys.argv) < 2:
        raise SystemExit("usage: python update_product.py <product_id> [more_ids...]")
    cfg = load_config()
    base = f"{cfg['url']}/wp-json/wc/v3/products"

    for pid in sys.argv[1:]:
        r = requests.get(f"{base}/{pid}", auth=(cfg["ck"], cfg["cs"]), timeout=60)
        if r.status_code != 200:
            print(f"[{pid}] not found (HTTP {r.status_code})")
            continue
        product = r.json()
        spec = parse_spec(product)

        rebuilt = generator.build_product(spec)
        update = {
            "description": rebuilt["description"],
            "meta_data": [m for m in rebuilt["meta_data"] if m["key"] == "_technical_specs"],
        }
        u = requests.put(f"{base}/{pid}", auth=(cfg["ck"], cfg["cs"]),
                         json=update, timeout=120)
        if u.status_code in (200, 201):
            imgs = update["description"].count("<img")
            print(f"[{pid}] updated '{spec['subject']}' — description now has {imgs} embedded images")
        else:
            print(f"[{pid}] update failed (HTTP {u.status_code}): {u.text[:200]}")


if __name__ == "__main__":
    main()
