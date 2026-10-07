#!/usr/bin/env python3
"""
Turn each brochure page into a CLEAN artwork image using Gemini image editing
(Nano Banana). It removes the logo, caption, size box, footer, and room
background, leaving just the artwork — like a proper product image.

Needs GEMINI_API_KEY with billing enabled (image model). ~=$0.039 per image.
Resumable: re-run to continue; --limit N to test a few first.

Usage:
    python clean_images.py --limit 1        # test one (~$0.04)
    python clean_images.py                  # all rows in products_clean.csv
    # then: python publish.py --gallery --no-ai-images --csv products_final.csv
"""

import argparse
import base64
import csv
import io
import json
import os
import time
from pathlib import Path

import requests
from dotenv import load_dotenv
from PIL import Image

HERE = Path(__file__).parent
CLEAN = HERE / "clean_images"
STATE = HERE / "output" / "clean_state.json"
MODEL = "gemini-2.5-flash-image"
URL = f"https://generativelanguage.googleapis.com/v1beta/models/{MODEL}:generateContent"
RETRY = {429, 500, 503}

PROMPT = (
    "This is a product brochure page. Extract ONLY the framed artwork/painting "
    "shown on it. Completely remove the company logo, all caption text, the "
    "'Available Sizes' box, the contact footer, the page number, and the "
    "surrounding room and wall. Output just the clean artwork itself, filling the "
    "image, high resolution, with no text or logos. Keep the artwork unchanged."
)


def _img_b64(path, max_dim=1200):
    im = Image.open(path).convert("RGB")
    im.thumbnail((max_dim, max_dim))
    buf = io.BytesIO()
    im.save(buf, "JPEG", quality=88)
    return base64.b64encode(buf.getvalue()).decode()


def clean_page(api_key, path, out_path):
    body = {"contents": [{"parts": [
        {"text": PROMPT},
        {"inline_data": {"mime_type": "image/jpeg", "data": _img_b64(path)}},
    ]}]}
    for attempt in range(5):
        r = requests.post(URL, headers={"x-goog-api-key": api_key,
                                        "Content-Type": "application/json"},
                          json=body, timeout=180)
        if r.status_code in RETRY:
            wait = 20 * (attempt + 1)
            print(f"  rate-limited; waiting {wait}s ...")
            time.sleep(wait)
            continue
        r.raise_for_status()
        parts = (r.json().get("candidates") or [{}])[0].get("content", {}).get("parts", [])
        for p in parts:
            inline = p.get("inline_data") or p.get("inlineData")
            if inline and inline.get("data"):
                out_path.write_bytes(base64.b64decode(inline["data"]))
                return True
        return False
    raise RuntimeError("rate-limited after retries (enable billing)")


def load_state():
    return json.loads(STATE.read_text(encoding="utf-8")) if STATE.exists() else {}


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--csv", default=str(HERE / "products_clean.csv"))
    ap.add_argument("--limit", type=int, default=0)
    args = ap.parse_args()
    load_dotenv(HERE / ".env")
    key = os.getenv("GEMINI_API_KEY")
    if not key:
        raise SystemExit("Set GEMINI_API_KEY in .env (billing enabled for the image model).")

    CLEAN.mkdir(exist_ok=True)
    STATE.parent.mkdir(exist_ok=True)
    with open(args.csv, newline="", encoding="utf-8-sig") as f:
        rows = list(csv.DictReader(f))

    done = load_state()
    pages = list(dict.fromkeys(r["image"] for r in rows if r.get("image")))
    todo = [p for p in pages if p not in done]
    if args.limit:
        todo = todo[: args.limit]
    print(f"{len(pages)} images, {len(done)} already cleaned, doing {len(todo)}.\n")

    try:
        for page in todo:
            print(f"cleaning {Path(page).name} ...")
            out = CLEAN / f"{Path(page).stem}-clean.png"
            try:
                ok = clean_page(key, page, out)
                done[page] = str(out.resolve()) if ok else ""
                print("  ok" if ok else "  no image returned — keeping original")
            except Exception as e:
                print(f"  [warn] {e}")
                break
            STATE.write_text(json.dumps(done), encoding="utf-8")
            time.sleep(1)
    except KeyboardInterrupt:
        print("\nInterrupted — writing CSV from cleaned images so far ...")

    for r in rows:
        c = done.get(r.get("image", ""))
        if c:
            r["image"] = c
    out_csv = HERE / "products_final.csv"
    with open(out_csv, "w", newline="", encoding="utf-8") as f:
        w = csv.DictWriter(f, fieldnames=rows[0].keys())
        w.writeheader()
        w.writerows(rows)
    print(f"\nDone. {sum(1 for v in done.values() if v)} cleaned. Wrote {out_csv}")
    print("Check clean_images/ then: python publish.py --gallery --no-ai-images --limit 3 --csv products_final.csv")


if __name__ == "__main__":
    main()
