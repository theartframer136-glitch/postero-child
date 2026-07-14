#!/usr/bin/env python3
"""
Image-FIRST product builder — the reliable pipeline.

The old flow named a product from the brochure and then GUESSED which photo
matched that name (visual_main.py). When two artworks looked alike (a haloed
figure), the guess was wrong and the name landed on the wrong picture
("Surya Dev" on a Jesus image). This script removes that guess entirely:

  1. Start from the MAIN image file itself (the artwork you actually have).
  2. Read the picture with vision -> the NAME and CATEGORY come FROM the image,
     so the name can never disagree with the picture.
  3. Skip images already on the store (visual ORB match, not names).
  4. Attach gallery photos whose WALL shows the SAME artwork as this main
     image (visual ORB match, strict threshold).

Output: products_named.csv  (ready for publish.py)
        build_from_images_report.txt

Setup:  pip install opencv-python numpy requests pillow python-dotenv
        GROQ_API_KEY (free) in .env   (same key the brochure reader uses)

Run the ONE-product test first:
    python build_from_images.py --limit 1
    python publish.py --no-ai-images --limit 1 --csv products_named.csv --fresh-skus
Then the full batch (drafts):
    python build_from_images.py
"""

import argparse
import csv
import json
import os
import re
import time
from pathlib import Path

from dotenv import load_dotenv

from visual_match import features, good_matches, list_images
from extract_brochure import _encode_image, _post_with_retry, GROQ_URL, GROQ_MODEL

HERE = Path(__file__).parent
OUT = HERE / "products_named.csv"
REPORT = HERE / "build_from_images_report.txt"
RAW = HERE / "output" / "products_raw.json"       # existing store products
STORE_CACHE = HERE / "output" / "store_images"
STATE = HERE / "output" / "name_state.json"        # resume vision naming

MAIN_DIRS = [
    Path(r"C:\Users\user\SynologyDrive\Final Edited Photos so far"),
    Path(r"C:\Users\user\SynologyDrive\Final Edited Photos so far\New"),
]
GALLERY_DIR = Path(r"C:\Users\user\SynologyDrive\Final Edited Photos so far\Gallary Pic")
IMG_EXT = {".jpg", ".jpeg", ".png", ".webp"}

# Categories that already exist on the store — vision must pick from THIS list.
CATEGORIES = [
    "Radha Krishna", "Buddha", "Sikh Art", "Christian Art", "Hindu Deities",
    "Seven Horses", "Wildlife", "Kids Room Decor", "Indian Culture",
    "Landscapes", "Still Life", "Abstract Art",
]
DEFAULT_SIZES = ["3x4 Feet", "4x5 Feet", "4x6 Feet"]

NAME_PROMPT = (
    "You are looking at ONE canvas wall-art product photo (the artwork may be "
    "shown on its own or hung on a wall in a room). Identify the ARTWORK, not "
    "the room. Return a JSON object exactly like:\n"
    '{"name": "<short product title, 2-5 words, Title Case>", '
    '"category": "<ONE of: ' + ", ".join(CATEGORIES) + '>", '
    '"subject": "<one plain sentence describing the artwork>"}\n'
    "Pick the closest category from that list. If the artwork is a Hindu god or "
    "goddess use 'Hindu Deities'; a Christian figure -> 'Christian Art'; a Sikh "
    "shrine/guru -> 'Sikh Art'. Do not invent categories outside the list."
)


def name_image(api_key, img_path):
    b64 = _encode_image(img_path)
    body = {
        "model": GROQ_MODEL,
        "temperature": 0,
        "response_format": {"type": "json_object"},
        "messages": [{"role": "user", "content": [
            {"type": "text", "text": NAME_PROMPT},
            {"type": "image_url", "image_url": {"url": f"data:image/jpeg;base64,{b64}"}},
        ]}],
    }
    data = _post_with_retry(GROQ_URL, {"Authorization": f"Bearer {api_key}"}, body)
    txt = data["choices"][0]["message"]["content"]
    try:
        obj = json.loads(txt)
    except json.JSONDecodeError:
        m = re.search(r"\{.*\}", txt, re.S)
        obj = json.loads(m.group(0)) if m else {}
    return obj if isinstance(obj, dict) else {}


def list_main_images():
    files, seen = [], set()
    for d in MAIN_DIRS:
        if not d.exists():
            print(f"  [warn] main folder not found: {d}")
            continue
        for f in d.iterdir():
            if f.is_file() and f.suffix.lower() in IMG_EXT and f.resolve() not in seen:
                seen.add(f.resolve())
                files.append(f)
    return files


def load_store_features():
    """One center-cropped ORB descriptor per existing store product (featured img)."""
    if not RAW.exists():
        print(f"  [warn] {RAW} not found — store dedupe skipped (nothing filtered).")
        return []
    import requests
    STORE_CACHE.mkdir(parents=True, exist_ok=True)
    feats = []
    for p in json.loads(RAW.read_text(encoding="utf-8")):
        ims = p.get("images") or []
        if not ims:
            continue
        url = ims[0].get("src")
        if not url:
            continue
        dest = STORE_CACHE / f"{p.get('id')}_0{Path(url).suffix or '.jpg'}"
        if not dest.exists():
            try:
                r = requests.get(url, timeout=60)
                r.raise_for_status()
                dest.write_bytes(r.content)
            except Exception as e:
                print(f"  [warn] fetch {url}: {e}")
                continue
        d = features(dest)
        if d is not None:
            feats.append((p.get("name", ""), d))
    return feats


def load_state():
    if STATE.exists():
        return json.loads(STATE.read_text(encoding="utf-8"))
    return {}


def save_state(st):
    STATE.parent.mkdir(parents=True, exist_ok=True)
    STATE.write_text(json.dumps(st), encoding="utf-8")


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--limit", type=int, default=0, help="build only N products (test)")
    ap.add_argument("--store-min", type=int, default=22,
                    help="ORB score to treat a main image as ALREADY on the store")
    ap.add_argument("--gallery-min", type=int, default=20,
                    help="ORB score for a gallery photo to count as the SAME artwork")
    ap.add_argument("--max-gallery", type=int, default=6)
    ap.add_argument("--min-gallery", type=int, default=0,
                    help="only keep products that have at least N matching wall photos")
    ap.add_argument("--delay", type=float, default=3.0, help="seconds between vision calls")
    args = ap.parse_args()

    load_dotenv(HERE / ".env")
    groq = os.getenv("GROQ_API_KEY")
    if not groq:
        raise SystemExit("Set GROQ_API_KEY (free) in .env first.")

    mains = list_main_images()
    if not mains:
        raise SystemExit("No main images found — check MAIN_DIRS paths.")
    print(f"{len(mains)} main images found.")

    print("Loading existing store images (for visual dedupe) ...")
    store_feat = load_store_features()
    print(f"  comparing against {len(store_feat)} live products.")

    print(f"Indexing gallery folder ...")
    gallery = list_images(GALLERY_DIR)
    gfeat = {g: features(g) for g in gallery}
    print(f"  {len(gallery)} gallery photos.\n")

    state = load_state()          # cache vision names across runs (crash-safe)
    rows, used_gallery, lines, made = [], set(), [], 0

    for i, mp in enumerate(mains, 1):
        if args.limit and made >= args.limit:
            break
        md = features(mp)

        # 1) already on the store? (visual, not by name)
        best_s, best_name = 0, ""
        for name, sf in store_feat:
            n = good_matches(md, sf)
            if n > best_s:
                best_s, best_name = n, name
        if best_s >= args.store_min:
            lines.append(f"[ON STORE {best_s}] {mp.name}  ~ {best_name}")
            print(f"[{i}/{len(mains)}] {mp.name} -> already on store ({best_s}) — skip")
            continue

        # 2) NAME from the image itself (cached)
        key = str(mp.resolve())
        info = state.get(key)
        if not info:
            try:
                info = name_image(groq, mp)
            except Exception as e:
                print(f"[{i}/{len(mains)}] {mp.name} -> vision error: {e} — skip")
                lines.append(f"[VISION-ERR] {mp.name}: {e}")
                continue
            state[key] = info
            save_state(state)
            time.sleep(args.delay)
        name = (info.get("name") or "").strip() or mp.stem
        cat = (info.get("category") or "").strip()
        if cat not in CATEGORIES:
            cat = ""
        subject_desc = (info.get("subject") or "").strip()

        # 3) gallery photos whose wall shows THIS artwork (strict)
        scored = []
        for g, gd in gfeat.items():
            if g in used_gallery:
                continue
            n = good_matches(md, gd)
            if n >= args.gallery_min:
                scored.append((n, g))
        scored.sort(reverse=True, key=lambda x: x[0])
        picks = [g for _, g in scored[: args.max_gallery]]

        # Require a matching wall photo when asked (keeps the test product clean).
        if len(picks) < args.min_gallery:
            lines.append(f"[NO-GALLERY] {name}  main={mp.name} — skipped "
                         f"(only {len(picks)} wall matches, need {args.min_gallery})")
            print(f"[{i}/{len(mains)}] {mp.name} -> \"{name}\" but "
                  f"{len(picks)} gallery matches (<{args.min_gallery}) — skip")
            continue
        for g in picks:
            used_gallery.add(g)

        cats = "Digital Canvas Prints" + (f"|{cat}" if cat else "")
        slug = re.sub(r"[^A-Z0-9]+", "-", name.upper()).strip("-")[:20]
        rows.append({
            "subject": name,
            "size": DEFAULT_SIZES[0],
            "sizes": "|".join(DEFAULT_SIZES),
            "style": "Premium Digital Canvas Print",
            "use_case": "Living Room & Home Spiritual Wall Décor",
            "categories": cats,
            "tags": "",
            "focus_keyword": f"{name} canvas wall art".lower(),
            "price": "",
            "image": "|".join([str(mp)] + [str(g) for g in picks]),
            "sku": f"TAF-{slug}-{i:03d}",
        })
        made += 1
        gnames = [g.name for g in picks] or ["-"]
        lines.append(f"[NEW] {name}  ({cat or 'no-cat'})  main={mp.name}  "
                     f"gallery={gnames}  gscores={[n for n, _ in scored[:args.max_gallery]]}")
        print(f"[{i}/{len(mains)}] {mp.name} -> \"{name}\" [{cat or 'no-cat'}] "
              f"+{len(picks)} gallery")

    cols = ["subject", "size", "sizes", "style", "use_case", "categories",
            "tags", "focus_keyword", "price", "image", "sku"]
    with open(OUT, "w", newline="", encoding="utf-8") as f:
        w = csv.DictWriter(f, fieldnames=cols)
        w.writeheader()
        w.writerows(rows)
    REPORT.write_text("\n".join(lines), encoding="utf-8")

    print(f"\nDone. {len(rows)} products written to {OUT}")
    print(f"Review {REPORT} — every name is derived from its own picture.")
    print("Test ONE:")
    print("  python publish.py --no-ai-images --limit 1 --csv products_named.csv --fresh-skus")


if __name__ == "__main__":
    main()
