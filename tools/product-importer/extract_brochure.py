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

import base64
import csv
import json
import os
import re
import sys
from pathlib import Path

import requests
from dotenv import load_dotenv

HERE = Path(__file__).parent
MODEL = "gemini-2.5-flash"
URL = f"https://generativelanguage.googleapis.com/v1beta/models/{MODEL}:generateContent"

PROMPT = """You are reading ONE page from a printed product catalog / brochure for
canvas wall-art. Extract EVERY distinct product shown on this page.
Return ONLY a JSON array. Each item must be:
{"subject": "<artwork/product name>", "size": "<size if shown, else ''>",
 "category": "<category or theme if shown, else ''>",
 "price": "<number only if shown, else ''>"}
If the page shows no products, return []."""


def norm(s):
    return re.sub(r"[^a-z0-9]", "", (s or "").lower())


def extract_page(api_key, img_path):
    mime = "image/png" if img_path.suffix.lower() == ".png" else "image/jpeg"
    body = {
        "contents": [{"parts": [
            {"text": PROMPT},
            {"inline_data": {"mime_type": mime,
                             "data": base64.b64encode(img_path.read_bytes()).decode()}},
        ]}],
        "generationConfig": {"responseMimeType": "application/json"},
    }
    r = requests.post(URL, headers={"x-goog-api-key": api_key,
                                    "Content-Type": "application/json"},
                      json=body, timeout=180)
    r.raise_for_status()
    txt = r.json()["candidates"][0]["content"]["parts"][0]["text"]
    try:
        return json.loads(txt)
    except json.JSONDecodeError:
        m = re.search(r"\[.*\]", txt, re.S)
        return json.loads(m.group(0)) if m else []


def existing_names():
    raw = HERE / "output" / "products_raw.json"
    if not raw.exists():
        return set()
    return {norm(p.get("name", "")[:25]) for p in json.loads(raw.read_text(encoding="utf-8"))}


def main():
    load_dotenv(HERE / ".env")
    key = os.getenv("GEMINI_API_KEY")
    if not key:
        sys.exit("Set GEMINI_API_KEY in .env first.")
    if len(sys.argv) < 2:
        sys.exit("usage: python extract_brochure.py <brochure_folder>")
    folder = Path(sys.argv[1])
    if not folder.exists():
        sys.exit(f"Folder not found: {folder}")
    imgs = sorted(p for p in folder.iterdir()
                  if p.suffix.lower() in (".png", ".jpg", ".jpeg", ".webp"))
    if not imgs:
        sys.exit(f"No page images (.png/.jpg) found in {folder}")

    existing = existing_names()
    rows, seen = [], set()
    for img in imgs:
        print(f"reading {img.name} ...")
        try:
            products = extract_page(key, img)
        except Exception as e:
            print(f"  [warn] {img.name}: {e}")
            continue
        for p in products:
            subj = (p.get("subject") or "").strip()
            if not subj:
                continue
            n = norm(subj[:25])
            if n in existing:
                print(f"  skip (already on store): {subj}")
                continue
            if n in seen:
                continue
            seen.add(n)
            rows.append(p)
            print(f"  + NEW: {subj}")

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
            w.writerow([subj, size, size, "Premium Digital Canvas Print", "",
                        cats, "", subj.lower(), price, "", f"TAF-{slug}-{i:03d}"])

    print(f"\nWrote {len(rows)} NEW products to {out}")
    print("Next steps:")
    print("  1) Open products_extracted.csv, put the artwork image URL in each 'image' cell.")
    print("  2) python publish.py --gallery --csv products_extracted.csv")


if __name__ == "__main__":
    main()
