#!/usr/bin/env python3
"""
Find the hidden meta key behind the product editor's "Technical specs" box.

Custom fields like "Technical specs" are stored in product meta_data under some
key (e.g. _something). We already pulled every product (with meta_data) into
output/products_raw.json when the analyzer ran, so we can find which meta key
holds the specs content — by looking for the words seen in the existing table.

Run:
    python find_specs_key.py
"""

import json
from collections import Counter
from pathlib import Path

RAW = Path(__file__).parent / "output" / "products_raw.json"

# Distinctive words from the existing "Technical specs" table (screenshot).
NEEDLES = ["Item Weight", "Net Quantity", "Generic Name", "Best Sellers Rank",
           "Packer", "Manufacturer"]


def main():
    if not RAW.exists():
        raise SystemExit(f"{RAW} not found. Run analyze_products.py first.")
    products = json.loads(RAW.read_text(encoding="utf-8"))

    key_hits = Counter()
    sample_value = {}
    for p in products:
        for m in p.get("meta_data", []):
            key = m.get("key", "")
            val = m.get("value")
            text = val if isinstance(val, str) else json.dumps(val, ensure_ascii=False)
            if any(n.lower() in text.lower() for n in NEEDLES):
                key_hits[key] += 1
                if key not in sample_value:
                    sample_value[key] = (p.get("name", ""), text)

    if not key_hits:
        print("No meta key matched the technical-specs words.")
        print("The specs may be stored elsewhere. Listing all meta keys seen:")
        all_keys = Counter()
        for p in products:
            for m in p.get("meta_data", []):
                all_keys[m.get("key", "")] += 1
        for k, c in all_keys.most_common(60):
            print(f"  {c:>4}  {k}")
        return

    print("Found technical-specs meta key(s):\n")
    for key, count in key_hits.most_common():
        name, text = sample_value[key]
        print(f"=== KEY: {key}   (used by {count} products) ===")
        print(f"example product: {name}")
        print("----- value (first 2000 chars) -----")
        print(text[:2000])
        print("\n")


if __name__ == "__main__":
    main()
