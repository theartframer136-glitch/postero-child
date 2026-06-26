#!/usr/bin/env python3
"""
Publish generated products to WooCommerce.

Reads products.csv, builds each product with generator.build_product(), uploads
its image, and creates the product through the WooCommerce REST API.

SAFE BY DEFAULT:
  - status is "draft" unless you pass --publish
  - --dry-run builds everything and prints it WITHOUT touching the live site
  - idempotent: skips a row whose SKU already exists on the store
  - --limit N processes only the first N rows (use 1 for your first test)

Usage:
    python publish.py --dry-run            # preview, nothing posted
    python publish.py --limit 1            # post ONE product as a draft
    python publish.py                      # post all rows as drafts
    python publish.py --publish            # post all rows live (after review)

Images (the `image` column):
  - An http(s) URL  -> WooCommerce sideloads it automatically.
  - A local file path -> uploaded to the Media Library first. This needs a
    WordPress Application Password in .env (WP_APP_USER / WP_APP_PASSWORD),
    because WooCommerce keys alone cannot upload media.
"""

import argparse
import csv
import mimetypes
import os
import sys
import time
from pathlib import Path

import requests
from dotenv import load_dotenv

import generator

HERE = Path(__file__).parent


def load_config():
    load_dotenv(HERE / ".env")
    cfg = {
        "url": os.getenv("WC_STORE_URL", "").rstrip("/"),
        "ck": os.getenv("WC_CONSUMER_KEY"),
        "cs": os.getenv("WC_CONSUMER_SECRET"),
        "wp_user": os.getenv("WP_APP_USER"),
        "wp_pass": os.getenv("WP_APP_PASSWORD"),
    }
    if not all([cfg["url"], cfg["ck"], cfg["cs"]]):
        sys.exit("Missing WC_STORE_URL / WC_CONSUMER_KEY / WC_CONSUMER_SECRET in .env")
    return cfg


# --------------------------------------------------------------------------- #
# CSV -> spec
# --------------------------------------------------------------------------- #
def row_to_spec(row, status):
    def split(v):
        return [x.strip() for x in v.split("|") if x.strip()] if v else []

    spec = {
        "subject": row["subject"].strip(),
        "size": row["size"].strip(),
        "sizes": split(row.get("sizes", "")) or [row["size"].strip()],
        "style": row.get("style", "").strip() or "Premium Digital Canvas Print",
        "use_case": row.get("use_case", "").strip(),
        "categories": split(row.get("categories", "")) or ["Digital Canvas Prints"],
        "focus_keyword": row.get("focus_keyword", "").strip(),
        "status": status,
    }
    if row.get("price", "").strip():
        spec["price"] = row["price"].strip()
    return spec


# --------------------------------------------------------------------------- #
# Images
# --------------------------------------------------------------------------- #
def upload_local_image(cfg, path):
    """Upload a local file to the WP Media Library; return its public URL."""
    if not (cfg["wp_user"] and cfg["wp_pass"]):
        sys.exit(
            f"Image '{path}' is a local file, which needs a WordPress Application "
            "Password. Add WP_APP_USER and WP_APP_PASSWORD to .env "
            "(WP Admin > Users > Profile > Application Passwords), or use image URLs."
        )
    p = Path(path)
    if not p.exists():
        sys.exit(f"Image file not found: {path}")
    endpoint = f"{cfg['url']}/wp-json/wp/v2/media"
    mime = mimetypes.guess_type(p.name)[0] or "image/jpeg"
    with open(p, "rb") as fh:
        resp = requests.post(
            endpoint,
            auth=(cfg["wp_user"], cfg["wp_pass"]),
            headers={
                "Content-Disposition": f'attachment; filename="{p.name}"',
                "Content-Type": mime,
            },
            data=fh.read(),
            timeout=120,
        )
    resp.raise_for_status()
    return resp.json()["source_url"]


def resolve_image(cfg, image_field, dry_run):
    if not image_field.strip():
        return []
    image = image_field.strip()
    if image.startswith(("http://", "https://")):
        return [{"src": image}]
    if dry_run:
        return [{"src": f"(local file, will upload on real run): {image}"}]
    return [{"src": upload_local_image(cfg, image)}]


# --------------------------------------------------------------------------- #
# WooCommerce
# --------------------------------------------------------------------------- #
def sku_exists(cfg, sku):
    if not sku:
        return False
    resp = requests.get(
        f"{cfg['url']}/wp-json/wc/v3/products",
        auth=(cfg["ck"], cfg["cs"]),
        params={"sku": sku},
        timeout=60,
    )
    resp.raise_for_status()
    return len(resp.json()) > 0


def create_product(cfg, payload):
    resp = requests.post(
        f"{cfg['url']}/wp-json/wc/v3/products",
        auth=(cfg["ck"], cfg["cs"]),
        json=payload,
        timeout=120,
    )
    resp.raise_for_status()
    return resp.json()


# --------------------------------------------------------------------------- #
def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--csv", default=str(HERE / "products.csv"))
    ap.add_argument("--dry-run", action="store_true", help="preview only, post nothing")
    ap.add_argument("--publish", action="store_true", help="status=publish (default draft)")
    ap.add_argument("--limit", type=int, default=0, help="process only first N rows")
    args = ap.parse_args()

    cfg = load_config()
    status = "publish" if args.publish else "draft"
    csv_path = Path(args.csv)
    if not csv_path.exists():
        sys.exit(f"CSV not found: {csv_path}\nCopy products_template.csv to products.csv and fill it in.")

    with open(csv_path, newline="", encoding="utf-8-sig") as fh:
        rows = list(csv.DictReader(fh))
    if args.limit:
        rows = rows[: args.limit]

    print(f"{len(rows)} product(s) | status={status} | dry_run={args.dry_run}\n")
    created, skipped = 0, 0

    for i, row in enumerate(rows, 1):
        subject = row.get("subject", "?").strip()
        sku = row.get("sku", "").strip()
        print(f"[{i}/{len(rows)}] {subject} ({sku or 'no sku'})")

        if sku and not args.dry_run and sku_exists(cfg, sku):
            print("  already exists -> skip")
            skipped += 1
            continue

        spec = row_to_spec(row, status)
        payload = generator.build_product(spec)
        if sku:
            payload["sku"] = sku
        payload["images"] = resolve_image(cfg, row.get("image", ""), args.dry_run)

        if args.dry_run:
            print(f"  TITLE: {payload['name']}")
            print(f"  price={payload['regular_price']} cats={[c['name'] for c in payload['categories']]} "
                  f"images={[im['src'] for im in payload['images']]}")
            print(f"  description: {len(payload['description'])} chars\n")
            continue

        try:
            product = create_product(cfg, payload)
            print(f"  created id={product['id']} status={product['status']} -> {product.get('permalink','')}\n")
            created += 1
            time.sleep(1)  # be gentle on the server
        except requests.HTTPError as e:
            print(f"  ERROR: {e} -> {e.response.text[:300]}\n")

    print(f"Done. created={created} skipped={skipped} "
          f"{'(dry run — nothing posted)' if args.dry_run else ''}")


if __name__ == "__main__":
    main()
