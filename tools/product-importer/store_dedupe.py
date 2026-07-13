#!/usr/bin/env python3
"""
Skip products already on the store — by VISUAL comparison, not names.

Downloads the images of your existing WooCommerce products (from the analyzer's
products_raw.json), then compares each new product's main image against them with
OpenCV ORB. If a new artwork visually matches a live product, it's dropped.

Input:  products_visual.csv (new products with main + gallery images)
Output: products_new.csv     (only products NOT already on the store)
        store_dedupe_report.txt

Setup:  pip install opencv-python numpy requests
Usage:  python store_dedupe.py
        python store_dedupe.py --min-matches 22
"""

import argparse
import csv
import json
from pathlib import Path

import requests

from visual_match import features, good_matches  # reuse the ORB matcher

HERE = Path(__file__).parent
RAW = HERE / "output" / "products_raw.json"
SRC = HERE / "products_visual.csv"
OUT = HERE / "products_new.csv"
REPORT = HERE / "store_dedupe_report.txt"
CACHE = HERE / "output" / "store_images"


def download_store_images():
    if not RAW.exists():
        raise SystemExit(f"{RAW} not found — run analyze_products.py first.")
    CACHE.mkdir(parents=True, exist_ok=True)
    products = json.loads(RAW.read_text(encoding="utf-8"))
    entries = []  # (product_name, [local_paths])
    for p in products:
        paths = []
        for j, im in enumerate(p.get("images") or []):
            url = im.get("src")
            if not url:
                continue
            dest = CACHE / f"{p.get('id')}_{j}{Path(url).suffix or '.jpg'}"
            if not dest.exists():
                try:
                    r = requests.get(url, timeout=60)
                    r.raise_for_status()
                    dest.write_bytes(r.content)
                except Exception as e:
                    print(f"  [warn] could not fetch {url}: {e}")
                    continue
            paths.append(dest)
        if paths:
            entries.append((p.get("name", ""), paths))
    return entries


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--min-matches", type=int, default=22,
                    help="min good feature matches to treat as the SAME artwork")
    args = ap.parse_args()

    if not SRC.exists():
        raise SystemExit(f"{SRC} not found — run visual_match.py first.")

    print("Downloading existing store product images (one-time cache) ...")
    store = download_store_images()
    print(f"Comparing against {len(store)} live products.\n")
    # Center-cropped artwork matching is accurate on the featured image alone,
    # so we compare against the first image per product (fast).
    store_feat = [(name, [features(paths[0])]) for name, paths in store]

    with open(SRC, newline="", encoding="utf-8-sig") as f:
        rows = list(csv.DictReader(f))

    kept, dropped, lines = [], 0, []
    for i, r in enumerate(rows, 1):
        main = (r.get("image", "").split("|") or [""])[0]
        md = features(Path(main)) if main else None
        best_n, best_name = 0, ""
        for name, feats in store_feat:
            for sf in feats:
                n = good_matches(md, sf)
                if n > best_n:
                    best_n, best_name = n, name
        if best_n >= args.min_matches:
            dropped += 1
            lines.append(f"[ALREADY ON STORE {best_n}] {r['subject']}  ~ {best_name}")
            print(f"[{i}/{len(rows)}] {r['subject']} -> already on store ({best_n}) — skip")
        else:
            kept.append(r)
            lines.append(f"[NEW {best_n}] {r['subject']}")
            print(f"[{i}/{len(rows)}] {r['subject']} -> new")

    with open(OUT, "w", newline="", encoding="utf-8") as f:
        w = csv.DictWriter(f, fieldnames=rows[0].keys())
        w.writeheader()
        w.writerows(kept)
    REPORT.write_text(f"{len(kept)} new, {dropped} already on store\n\n" + "\n".join(lines),
                      encoding="utf-8")
    print(f"\nDone. {len(kept)} new products, {dropped} already on store.")
    print(f"Wrote {OUT}. Review {REPORT}.")
    print("Then: python publish.py --no-ai-images --limit 1 --csv products_new.csv")


if __name__ == "__main__":
    main()
