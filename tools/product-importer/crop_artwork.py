#!/usr/bin/env python3
"""
Crop the clean artwork out of each brochure page.

Each brochure page has ONE framed artwork plus a logo, caption, size box, and
contact footer AROUND it. This asks the vision model for the artwork's bounding
box, then crops the original page down to just that artwork (like a clean product
image). Updates the CSV to point at the crops.

Free with Groq (GROQ_API_KEY) or cheap with Gemini (GEMINI_API_KEY).

Usage:
    python crop_artwork.py                 # crops images in products_clean.csv
    python crop_artwork.py --csv other.csv
    # then: python publish.py --gallery --no-ai-images --csv products_final.csv
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
CROPS = HERE / "crops"
STATE = HERE / "output" / "crop_state.json"
GROQ_URL = "https://api.groq.com/openai/v1/chat/completions"
GROQ_MODEL = "meta-llama/llama-4-scout-17b-16e-instruct"
GEMINI_URL = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent"
RETRY = {429, 500, 503}

PROMPT = (
    "This is a product brochure page containing ONE main framed artwork/painting, "
    "plus a logo, a caption, an 'Available Sizes' box, and a contact footer around "
    "it. Return ONLY JSON: {\"box\": [x1, y1, x2, y2]} giving the bounding box of "
    "JUST the main artwork/painting rectangle, as fractions from 0.0 to 1.0 of the "
    "page width (x) and height (y). Exclude the logo, caption text, size box, and "
    "footer — return only the artwork itself."
)


def _img_b64(path, max_dim=1024):
    im = Image.open(path).convert("RGB")
    im.thumbnail((max_dim, max_dim))
    buf = io.BytesIO()
    im.save(buf, "JPEG", quality=80)
    return base64.b64encode(buf.getvalue()).decode()


def _retry_post(url, headers, body):
    for attempt in range(6):
        r = requests.post(url, headers=headers, json=body, timeout=180)
        if r.status_code in RETRY:
            wait = 15 * (attempt + 1)
            print(f"  rate-limited; waiting {wait}s ...")
            time.sleep(wait)
            continue
        r.raise_for_status()
        return r.json()
    raise RuntimeError("rate-limited after retries")


def _box_from_text(txt):
    try:
        d = json.loads(txt)
    except json.JSONDecodeError:
        m = re.search(r"\{.*\}", txt, re.S)
        d = json.loads(m.group(0)) if m else {}
    box = d.get("box") or d.get("bbox")
    if isinstance(box, list) and len(box) == 4:
        return [float(x) for x in box]
    return None


def get_box_groq(key, path):
    body = {"model": GROQ_MODEL, "temperature": 0,
            "response_format": {"type": "json_object"},
            "messages": [{"role": "user", "content": [
                {"type": "text", "text": PROMPT},
                {"type": "image_url",
                 "image_url": {"url": f"data:image/jpeg;base64,{_img_b64(path)}"}},
            ]}]}
    d = _retry_post(GROQ_URL, {"Authorization": f"Bearer {key}"}, body)
    return _box_from_text(d["choices"][0]["message"]["content"])


def get_box_gemini(key, path):
    body = {"contents": [{"parts": [
                {"text": PROMPT},
                {"inline_data": {"mime_type": "image/jpeg", "data": _img_b64(path)}}]}],
            "generationConfig": {"responseMimeType": "application/json"}}
    d = _retry_post(GEMINI_URL, {"x-goog-api-key": key, "Content-Type": "application/json"}, body)
    return _box_from_text(d["candidates"][0]["content"]["parts"][0]["text"])


def crop_to(path, box, out_path, margin=0.01):
    im = Image.open(path).convert("RGB")
    W, H = im.size
    x1, x2 = sorted((box[0], box[2]))
    y1, y2 = sorted((box[1], box[3]))
    x1 = max(0.0, x1 - margin); y1 = max(0.0, y1 - margin)
    x2 = min(1.0, x2 + margin); y2 = min(1.0, y2 + margin)
    px = (int(x1 * W), int(y1 * H), int(x2 * W), int(y2 * H))
    if px[2] - px[0] < 60 or px[3] - px[1] < 60:
        return False
    im.crop(px).save(out_path, "JPEG", quality=92)
    return True


def load_state():
    if STATE.exists():
        return json.loads(STATE.read_text(encoding="utf-8"))
    return {}


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--csv", default=str(HERE / "products_clean.csv"))
    args = ap.parse_args()
    load_dotenv(HERE / ".env")
    groq, gemini = os.getenv("GROQ_API_KEY"), os.getenv("GEMINI_API_KEY")
    if groq:
        get_box, prov = (lambda p: get_box_groq(groq, p)), "Groq (free)"
    elif gemini:
        get_box, prov = (lambda p: get_box_gemini(gemini, p)), "Gemini"
    else:
        raise SystemExit("Set GROQ_API_KEY or GEMINI_API_KEY in .env")
    print(f"Cropping with: {prov}\n")

    CROPS.mkdir(exist_ok=True)
    STATE.parent.mkdir(exist_ok=True)
    src = Path(args.csv)
    with open(src, newline="", encoding="utf-8-sig") as f:
        rows = list(csv.DictReader(f))

    done = load_state()  # {page_path: crop_path or ""}
    pages = sorted({r["image"] for r in rows if r.get("image")})
    todo = [p for p in pages if p not in done]
    print(f"{len(pages)} images, {len(done)} already cropped, {len(todo)} to go.\n")

    try:
        for page in todo:
            name = Path(page).stem
            print(f"cropping {Path(page).name} ...")
            out = CROPS / f"{name}-art.jpg"
            try:
                box = get_box(page)
                ok = box and crop_to(page, box, out)
                done[page] = str(out.resolve()) if ok else ""
                print("  ok" if ok else "  no box found — keeping full page")
            except Exception as e:
                print(f"  [warn] {e}")
                print("  Stopping — run again later to resume.")
                break
            STATE.write_text(json.dumps(done), encoding="utf-8")
            time.sleep(2)
    except KeyboardInterrupt:
        print("\nInterrupted — writing CSV from crops so far ...")

    for r in rows:
        crop = done.get(r.get("image", ""))
        if crop:
            r["image"] = crop
    out_csv = HERE / "products_final.csv"
    with open(out_csv, "w", newline="", encoding="utf-8") as f:
        w = csv.DictWriter(f, fieldnames=rows[0].keys())
        w.writeheader()
        w.writerows(rows)
    cropped = sum(1 for v in done.values() if v)
    print(f"\nDone. {cropped} artworks cropped. Wrote {out_csv}")
    print("Next: python publish.py --gallery --no-ai-images --limit 3 --csv products_final.csv")


if __name__ == "__main__":
    main()
