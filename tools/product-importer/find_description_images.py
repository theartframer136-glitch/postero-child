#!/usr/bin/env python3
"""
Audit the images embedded INSIDE product descriptions.

Existing good products embed images in the long description (infographic,
placement scene, care-instructions card, "other collections" banner). This finds:
  1. The embedded <img> URLs for a chosen product (default: a 'durga' product).
  2. Images that appear across MANY products — these are the reusable/generic
     ones (care card, collections banner) we should embed in every new product.

Run:
    python find_description_images.py
    python find_description_images.py durga      # match a different product
"""

import json
import re
import sys
from collections import Counter
from pathlib import Path

RAW = Path(__file__).parent / "output" / "products_raw.json"
IMG = re.compile(r'<img[^>]+src="([^"]+)"[^>]*?(?:alt="([^"]*)")?', re.I)


def imgs_in(html):
    return re.findall(r'<img[^>]+src="([^"]+)"', html or "", re.I)


def main():
    if not RAW.exists():
        raise SystemExit(f"{RAW} not found. Run analyze_products.py first.")
    products = json.loads(RAW.read_text(encoding="utf-8"))
    needle = (sys.argv[1] if len(sys.argv) > 1 else "durga").lower()

    # 1. The chosen product's embedded description images
    match = next((p for p in products
                  if needle in (p.get("name", "").lower() + p.get("slug", "").lower())), None)
    print(f"=== Description images for product matching '{needle}' ===")
    if match:
        print(f"product: {match.get('name')}")
        for src in imgs_in(match.get("description", "")):
            print("  ", src)
    else:
        print("  (no matching product found)")

    # 2. Images reused across many products (the generic/reusable ones)
    counter = Counter()
    for p in products:
        for src in set(imgs_in(p.get("description", ""))):
            counter[src] += 1
    print("\n=== Images reused across many products (likely care/banner/etc.) ===")
    for src, count in counter.most_common(25):
        if count >= 3:
            print(f"  {count:>3}x  {src}")


if __name__ == "__main__":
    main()
