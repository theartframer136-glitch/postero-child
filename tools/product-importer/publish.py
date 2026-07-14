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

import io
import requests
from dotenv import load_dotenv
from PIL import Image

# Print files are huge (4ft @ 300 DPI ~ 187M px), which trips Pillow's bomb guard.
# These are the user's own trusted files.
Image.MAX_IMAGE_PIXELS = None

import generator
import compositor
import mockups_api
import ai_images

HERE = Path(__file__).parent
UPLOAD_MAX_DIM = 2400   # web product images: downscale before upload (fast, reliable)


def load_config():
    load_dotenv(HERE / ".env")
    cfg = {
        "url": os.getenv("WC_STORE_URL", "").rstrip("/"),
        "ck": os.getenv("WC_CONSUMER_KEY"),
        "cs": os.getenv("WC_CONSUMER_SECRET"),
        "wp_user": os.getenv("WP_APP_USER"),
        "wp_pass": os.getenv("WP_APP_PASSWORD"),
        "mockup_key": os.getenv("DYNAMIC_MOCKUPS_API_KEY"),
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
        "tags": split(row.get("tags", "")),
        "focus_keyword": row.get("focus_keyword", "").strip(),
        "sku": row.get("sku", "").strip(),
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

    # Downscale huge print files to a web-sized JPEG before upload. A 30-50 MB,
    # 187-megapixel file stalls or is rejected by WordPress; ~2400px is plenty
    # for a product page and uploads in a second.
    try:
        im = Image.open(p)
        im = im.convert("RGB")
        if max(im.size) > UPLOAD_MAX_DIM:
            im.thumbnail((UPLOAD_MAX_DIM, UPLOAD_MAX_DIM))
        buf = io.BytesIO()
        im.save(buf, "JPEG", quality=85, optimize=True)
        body = buf.getvalue()
        filename = p.stem + ".jpg"
        mime = "image/jpeg"
    except Exception as e:
        print(f"  [warn] could not downscale {p.name} ({e}); uploading original.")
        body = p.read_bytes()
        filename = p.name
        mime = mimetypes.guess_type(p.name)[0] or "image/jpeg"

    resp = requests.post(
        endpoint,
        auth=(cfg["wp_user"], cfg["wp_pass"]),
        headers={
            "Content-Disposition": f'attachment; filename="{filename}"',
            "Content-Type": mime,
        },
        data=body,
        timeout=120,
    )
    resp.raise_for_status()
    return resp.json()["source_url"]


# --------------------------------------------------------------------------- #
# WooCommerce
# --------------------------------------------------------------------------- #
_term_cache = {}


def resolve_terms(cfg, taxonomy, names):
    """Map category/tag NAMES to WooCommerce term IDs, creating any that are
    missing. WooCommerce assigns categories by ID, not name — passing names
    alone leaves products 'Uncategorized'."""
    ids = []
    for name in names:
        name = name.strip()
        if not name:
            continue
        key = (taxonomy, name.lower())
        if key in _term_cache:
            ids.append(_term_cache[key])
            continue
        base = f"{cfg['url']}/wp-json/wc/v3/products/{taxonomy}"
        r = requests.get(base, auth=(cfg["ck"], cfg["cs"]),
                         params={"search": name, "per_page": 100}, timeout=60)
        r.raise_for_status()
        match = next((t for t in r.json() if t["name"].lower() == name.lower()), None)
        if not match:
            cr = requests.post(base, auth=(cfg["ck"], cfg["cs"]),
                               json={"name": name}, timeout=60)
            try:
                data = cr.json()
            except ValueError:  # non-JSON (e.g. a security plugin's HTML block page)
                print(f"  [warn] could not create {taxonomy} '{name}' "
                      f"(HTTP {cr.status_code}, non-JSON response) — skipping it")
                continue
            if cr.ok:
                match = data
            elif data.get("data", {}).get("resource_id"):  # already exists
                match = {"id": data["data"]["resource_id"]}
            else:
                print(f"  [warn] could not create {taxonomy} '{name}': {data} — skipping it")
                continue
        _term_cache[key] = match["id"]
        ids.append(match["id"])
    return [{"id": i} for i in ids]


def name_terms(names):
    """Tags can be sent by name on product create — WooCommerce creates any
    that don't exist, avoiding a separate (sometimes-blocked) create request."""
    return [{"name": n.strip()} for n in names if n.strip()]


def assemble_images(cfg, image_list, base_name, gallery, dry_run, use_ai=True):
    """Build the product image list. With gallery=True, composite the FIRST
    image into framed (black/oak/white) + room mockups, upload them, and use
    them as the gallery (original art stays the featured image).

    Compositing + upload requires a WordPress Application Password in .env
    (WP_APP_USER / WP_APP_PASSWORD)."""
    images = [x.strip() for x in image_list if x and x.strip()]
    if not images:
        return []

    if not gallery:
        out = []
        for img in images:
            if img.startswith(("http://", "https://")):
                out.append({"src": img})
            elif dry_run:
                out.append({"src": f"(local file, upload on real run): {img}"})
            else:
                out.append({"src": upload_local_image(cfg, img)})
        return out

    # gallery mode
    if dry_run:
        return [{"src": images[0]},
                {"src": "(+ composited black/oak/white frame + room scene, uploaded)"}]

    import tempfile
    tmp = Path(tempfile.mkdtemp())
    src_field = images[0]
    if src_field.startswith(("http://", "https://")):
        src = tmp / "source.jpg"
        r = requests.get(src_field, timeout=120)
        r.raise_for_status()
        src.write_bytes(r.content)
        featured = src_field
    else:
        src = Path(src_field)
        if not src.exists():
            sys.exit(f"Artwork not found: {src_field}")
        featured = upload_local_image(cfg, str(src))

    out = [{"src": featured}]

    # Best image source wins: Dynamic Mockups API > AI (Gemini/OpenAI) > compositor.
    # 1) Dynamic Mockups — hosted renders, used directly (no WP upload).
    if cfg.get("mockup_key") and mockups_api.is_configured():
        try:
            for u in mockups_api.render_all(featured, cfg["mockup_key"]):
                out.append({"src": u})
        except Exception as e:
            print(f"  [warn] mockup API failed ({e}); trying next option.")
    # 2) AI image generation (Nano Banana / OpenAI) — save + upload.
    if len(out) == 1 and use_ai and ai_images.is_configured():
        try:
            art_bytes = Path(src).read_bytes()
            for f in ai_images.generate_gallery(art_bytes, tmp, base_name):
                out.append({"src": upload_local_image(cfg, f)})
        except Exception as e:
            print(f"  [warn] AI image gen failed ({e}); trying next option.")
    # 3) Built-in compositor (templates or drawn) — save + upload.
    if len(out) == 1:
        for f in compositor.make_gallery(src, tmp, base_name):
            out.append({"src": upload_local_image(cfg, f)})

    for extra in images[1:]:  # any additional images the user supplied
        out.append({"src": extra} if extra.startswith(("http", "https"))
                   else {"src": upload_local_image(cfg, extra)})
    return out
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


def create_variations(cfg, product_id, variations):
    """Create all variations for a variable product via the batch endpoint."""
    url = f"{cfg['url']}/wp-json/wc/v3/products/{product_id}/variations/batch"
    created = 0
    for i in range(0, len(variations), 100):  # batch endpoint caps ~100
        chunk = variations[i:i + 100]
        r = requests.post(url, auth=(cfg["ck"], cfg["cs"]),
                          json={"create": chunk}, timeout=180)
        r.raise_for_status()
        created += len(r.json().get("create", []))
    return created


# --------------------------------------------------------------------------- #
def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--csv", default=str(HERE / "products.csv"))
    ap.add_argument("--dry-run", action="store_true", help="preview only, post nothing")
    ap.add_argument("--publish", action="store_true", help="status=publish (default draft)")
    ap.add_argument("--limit", type=int, default=0, help="process only first N rows")
    ap.add_argument("--skip", type=int, default=0,
                    help="skip the first N rows (continue a previous batch)")
    ap.add_argument("--gallery-fill", action="store_true",
                    help="products with NO matched wall photos get composited "
                         "framed/room mockups made from their main image")
    ap.add_argument("--gallery", action="store_true",
                    help="auto-composite the main image into framed + room mockups")
    ap.add_argument("--no-ai-images", action="store_true",
                    help="use the FREE compositor for images even if a Gemini key is set")
    ap.add_argument("--main-only", action="store_true",
                    help="use only the first (main) image per product — no gallery")
    ap.add_argument("--fresh-skus", action="store_true",
                    help="append a unique run tag to every SKU so it can't collide")
    args = ap.parse_args()
    import time as _t
    run_tag = format(int(_t.time()) % 100000, "05d")

    cfg = load_config()
    status = "publish" if args.publish else "draft"
    csv_path = Path(args.csv)
    if not csv_path.exists():
        sys.exit(f"CSV not found: {csv_path}\nCopy products_template.csv to products.csv and fill it in.")

    with open(csv_path, newline="", encoding="utf-8-sig") as fh:
        rows = list(csv.DictReader(fh))
    if args.skip:
        rows = rows[args.skip:]
    if args.limit:
        rows = rows[: args.limit]

    print(f"{len(rows)} product(s) | status={status} | dry_run={args.dry_run}\n")
    created, skipped = 0, 0

    for i, row in enumerate(rows, 1):
        subject = row.get("subject", "?").strip()
        sku = row.get("sku", "").strip()
        if args.fresh_skus and sku:
            sku = f"{sku}-{run_tag}{i}"          # guaranteed unique this run
        print(f"[{i}/{len(rows)}] {subject} ({sku or 'no sku'})")

        if sku and not args.fresh_skus and not args.dry_run and sku_exists(cfg, sku):
            print("  already exists -> skip")
            skipped += 1
            continue

        spec = row_to_spec(row, status)
        img_list = (row.get("image", "") or "").split("|")
        if args.main_only:
            img_list = img_list[:1]
        # No real wall photos matched this artwork? Composite framed + room
        # mockups FROM the main image so the gallery always matches the main.
        want_gallery = args.gallery or (
            args.gallery_fill and len([x for x in img_list if x.strip()]) <= 1)
        images = assemble_images(
            cfg, img_list, sku or subject.replace(" ", "-"), want_gallery, args.dry_run,
            use_ai=not args.no_ai_images)
        if not args.dry_run:
            spec["gallery_urls"] = [im["src"] for im in images]
        payload = generator.build_product(spec)
        if sku:
            payload["sku"] = sku
        payload["images"] = images
        cat_names = [c["name"] for c in payload.get("categories", [])]
        tag_names = [t["name"] for t in payload.get("tags", [])]

        if args.dry_run:
            print(f"  TITLE: {payload['name']}")
            print(f"  type={payload['type']} cats={cat_names} tags={tag_names}")
            if payload.get("type") == "variable":
                vs = generator.build_variations(spec)
                prices = [float(v["regular_price"]) for v in vs]
                print(f"  variations={len(vs)} price range ${min(prices):.0f}-${max(prices):.0f}")
            print(f"  attributes={[a['name'] for a in payload.get('attributes', [])]} "
                  f"images={len(payload['images'])}")
            print(f"  description: {len(payload['description'])} chars\n")
            continue

        # Categories must be sent as term IDs (names alone -> Uncategorized).
        payload["categories"] = resolve_terms(cfg, "categories", cat_names)
        # Tags can go by name; WooCommerce creates them during product create.
        payload["tags"] = name_terms(tag_names)

        try:
            product = create_product(cfg, payload)
            print(f"  created id={product['id']} status={product['status']} -> {product.get('permalink','')}")
            if payload.get("type") == "variable":
                n = create_variations(cfg, product["id"], generator.build_variations(spec))
                print(f"  + {n} variations (size x frame x colour)")
            print()
            created += 1
            time.sleep(1)  # be gentle on the server
        except requests.HTTPError as e:
            print(f"  ERROR: {e} -> {e.response.text[:300]}\n")

    print(f"Done. created={created} skipped={skipped} "
          f"{'(dry run — nothing posted)' if args.dry_run else ''}")


if __name__ == "__main__":
    main()
