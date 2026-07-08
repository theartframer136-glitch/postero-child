#!/usr/bin/env python3
"""
Extract products from a Canva brochure automatically using Gemini vision.

You export the brochure from Canva ONCE (Download > PNG, all pages -> a folder of
page images). This script reads every page with Gemini, pulls out each product
(name, size, category, price), skips the ones already on your store, and writes
a ready-to-use products CSV.

Usage:
    python extract_brochure.py path\\to\\brochure_folder
    # then review products_extracted.csv, add image URLs, and:
    #   python publish.py --gallery --csv products_extracted.csv
"""

import argparse
import base64
import csv
import io
import json
import os
import re
import time
from pathlib import Path

import requests
from dotenv import load_dotenv
from PIL import Image

HERE = Path(__file__).parent
MODEL = "gemini-2.5-flash"
URL = f"https://generativelanguage.googleapis.com/v1beta/models/{MODEL}:generateContent"
# Groq — free tier, no credit card, ~1000+ requests/day (fits the whole brochure).
GROQ_URL = "https://api.groq.com/openai/v1/chat/completions"
GROQ_MODEL = "meta-llama/llama-4-scout-17b-16e-instruct"
STATE = HERE / "output" / "brochure_state.json"  # resume progress
RETRY_STATUS = {429, 500, 503}

PROMPT = """You are reading ONE page from a printed product catalog / brochure for
canvas wall-art. Extract EVERY distinct product shown on this page.
Return ONLY a JSON array. Each item must be:
{"subject": "<artwork/product name>", "size": "<size if shown, else ''>",
 "category": "<category or theme if shown, else ''>",
 "price": "<number only if shown, else ''>"}
If the page shows no products, return []."""


def norm(s):
    return re.sub(r"[^a-z0-9]", "", (s or "").lower())


def _encode_image(img_path, max_dim=1024):
    """Downscale to a small JPEG for the API call (far fewer tokens = faster,
    avoids per-minute token limits). Does NOT touch the original file."""
    im = Image.open(img_path).convert("RGB")
    im.thumbnail((max_dim, max_dim))
    buf = io.BytesIO()
    im.save(buf, "JPEG", quality=80)
    return base64.b64encode(buf.getvalue()).decode()


def _parse_products(txt):
    """Accept a JSON array, or an object wrapping a products array."""
    try:
        data = json.loads(txt)
    except json.JSONDecodeError:
        m = re.search(r"\[.*\]", txt, re.S)
        data = json.loads(m.group(0)) if m else []
    if isinstance(data, dict):
        for v in data.values():
            if isinstance(v, list):
                return v
        return []
    return data if isinstance(data, list) else []


def _post_with_retry(url, headers, body, retries=6):
    for attempt in range(retries):
        r = requests.post(url, headers=headers, json=body, timeout=180)
        if r.status_code in RETRY_STATUS:
            wait = 15 * (attempt + 1)
            print(f"  rate-limited (HTTP {r.status_code}); waiting {wait}s ...")
            time.sleep(wait)
            continue
        r.raise_for_status()
        return r.json()
    raise RuntimeError("still rate-limited after retries")


def extract_page_gemini(api_key, img_path):
    body = {
        "contents": [{"parts": [
            {"text": PROMPT},
            {"inline_data": {"mime_type": "image/jpeg",
                             "data": _encode_image(img_path)}},
        ]}],
        "generationConfig": {"responseMimeType": "application/json"},
    }
    data = _post_with_retry(URL, {"x-goog-api-key": api_key,
                                  "Content-Type": "application/json"}, body)
    return _parse_products(data["candidates"][0]["content"]["parts"][0]["text"])


def extract_page_groq(api_key, img_path):
    b64 = _encode_image(img_path)
    body = {
        "model": GROQ_MODEL,
        "temperature": 0,
        "response_format": {"type": "json_object"},
        "messages": [{"role": "user", "content": [
            {"type": "text", "text": PROMPT + ' Return a JSON object: {"products": [...]}.'},
            {"type": "image_url", "image_url": {"url": f"data:image/jpeg;base64,{b64}"}},
        ]}],
    }
    data = _post_with_retry(GROQ_URL, {"Authorization": f"Bearer {api_key}"}, body)
    return _parse_products(data["choices"][0]["message"]["content"])


def existing_names():
    raw = HERE / "output" / "products_raw.json"
    if not raw.exists():
        return set()
    return {norm(p.get("name", "")[:25]) for p in json.loads(raw.read_text(encoding="utf-8"))}


def load_state():
    if STATE.exists():
        return json.loads(STATE.read_text(encoding="utf-8"))
    return {"done": [], "products": []}


def save_state(state):
    STATE.parent.mkdir(exist_ok=True)
    STATE.write_text(json.dumps(state), encoding="utf-8")


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("folder", help="folder of brochure page images")
    ap.add_argument("--delay", type=float, default=4.5,
                    help="seconds between pages (free tier ~15/min; default 4.5)")
    ap.add_argument("--build-csv", action="store_true",
                    help="skip reading; just write the CSV from pages already read")
    args = ap.parse_args()

    load_dotenv(HERE / ".env")
    groq_key = os.getenv("GROQ_API_KEY")
    gemini_key = os.getenv("GEMINI_API_KEY")
    if groq_key:
        provider, read = "Groq (free)", lambda img: extract_page_groq(groq_key, img)
    elif gemini_key:
        provider, read = "Gemini", lambda img: extract_page_gemini(gemini_key, img)
    else:
        raise SystemExit("Set GROQ_API_KEY (free) or GEMINI_API_KEY in .env first.")
    print(f"Reading with: {provider}\n")
    folder = Path(args.folder)
    if not folder.exists():
        raise SystemExit(f"Folder not found: {folder}")
    imgs = sorted(p for p in folder.iterdir()
                  if p.suffix.lower() in (".png", ".jpg", ".jpeg", ".webp"))
    if not imgs:
        raise SystemExit(f"No page images (.png/.jpg) found in {folder}")

    state = load_state()             # resume across runs
    done = set(state["done"])
    products = state["products"]
    todo = [p for p in imgs if p.name not in done]
    print(f"{len(imgs)} pages total, {len(done)} already read, {len(todo)} to go.\n")

    if not args.build_csv:
        try:
            for img in todo:
                print(f"reading {img.name} ...")
                try:
                    page_products = read(img)
                except Exception as e:
                    print(f"  [warn] {img.name}: {e}")
                    print("  Stopping — run the SAME command again later to resume.")
                    break
                for p in page_products:
                    subj = (p.get("subject") or "").strip()
                    if subj:
                        p["_page"] = str(img.resolve())  # page becomes the product image
                        products.append(p)
                        print(f"  + {subj}")
                done.add(img.name)
                state["done"], state["products"] = sorted(done), products
                save_state(state)        # crash-safe: progress saved after each page
                time.sleep(args.delay)
        except KeyboardInterrupt:
            print("\nInterrupted — writing the CSV from pages read so far ...")

    # Build the CSV from everything gathered so far, deduped against the store.
    existing = existing_names()
    rows, seen = [], set()
    for p in products:
        subj = (p.get("subject") or "").strip()
        if not subj:
            continue
        n = norm(subj[:25])
        if n in existing or n in seen:
            continue
        seen.add(n)
        rows.append(p)

    out = HERE / "products_extracted.csv"
    with open(out, "w", newline="", encoding="utf-8") as f:
        w = csv.writer(f)
        w.writerow(["subject", "size", "sizes", "style", "use_case", "categories",
                    "tags", "focus_keyword", "price", "image", "sku"])
        for i, p in enumerate(rows, 1):
            subj = p.get("subject", "").strip()
            size = (p.get("size") or "").strip() or "36x48 Inches"
            cat = (p.get("category") or "").strip()
            cats = "Digital Canvas Prints" + (f"|{cat}" if cat else "")
            price = str(p.get("price") or "").strip()
            slug = re.sub(r"[^A-Z0-9]+", "-", subj.upper()).strip("-")[:20]
            image = p.get("_page", "")  # local page image -> product's main image
            w.writerow([subj, size, size, "Premium Digital Canvas Print", "",
                        cats, "", subj.lower(), price, image, f"TAF-{slug}-{i:03d}"])

    print(f"\nWrote {len(rows)} NEW products to {out}")
    print("Next steps:")
    print("  1) Open products_extracted.csv, put the artwork image URL in each 'image' cell.")
    print("  2) python publish.py --gallery --csv products_extracted.csv")


if __name__ == "__main__":
    main()
