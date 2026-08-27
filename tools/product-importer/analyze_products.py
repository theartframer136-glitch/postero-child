#!/usr/bin/env python3
"""
Learn from the products already on the store.

Read-only: pulls every existing product through the WooCommerce REST API and
derives a "style profile" — the conventions the new products should copy
(description shape, image counts, categories, tags, attributes, pricing, voice).

Writes three files into ./output/:
  - products_raw.json   the full pulled catalog (cache; nothing is modified live)
  - profile.json        machine-readable profile, consumed by the generator later
  - profile.md          human-readable report for you to eyeball

Run:
    pip install -r requirements.txt
    cp .env.example .env   # then fill in your keys
    python analyze_products.py
"""

import json
import os
import re
import statistics
from collections import Counter
from html.parser import HTMLParser
from pathlib import Path

import requests
from dotenv import load_dotenv

OUTPUT_DIR = Path(__file__).parent / "output"


# --------------------------------------------------------------------------- #
# Fetching
# --------------------------------------------------------------------------- #
def fetch_all_products(base_url, auth):
    """Page through /wc/v3/products and return every product."""
    products = []
    page = 1
    endpoint = f"{base_url.rstrip('/')}/wp-json/wc/v3/products"
    while True:
        resp = requests.get(
            endpoint,
            auth=auth,
            params={"per_page": 100, "page": page, "status": "any"},
            timeout=60,
        )
        resp.raise_for_status()
        batch = resp.json()
        if not batch:
            break
        products.extend(batch)
        total_pages = int(resp.headers.get("X-WP-TotalPages", page))
        print(f"  fetched page {page}/{total_pages} ({len(batch)} products)")
        if page >= total_pages:
            break
        page += 1
    return products


# --------------------------------------------------------------------------- #
# Description analysis
# --------------------------------------------------------------------------- #
class _TagCounter(HTMLParser):
    def __init__(self):
        super().__init__()
        self.tags = Counter()

    def handle_starttag(self, tag, attrs):
        self.tags[tag] += 1


def strip_html(html):
    return re.sub(r"<[^>]+>", " ", html or "").strip()


def analyze_descriptions(products):
    lengths, word_counts = [], []
    tag_usage = Counter()
    uses_bullets = 0
    samples = []
    for p in products:
        html = p.get("description") or ""
        if not html.strip():
            continue
        text = strip_html(html)
        lengths.append(len(text))
        word_counts.append(len(text.split()))
        tc = _TagCounter()
        tc.feed(html)
        tag_usage.update(tc.tags)
        if "<li" in html or "<ul" in html:
            uses_bullets += 1
        if len(samples) < 3:
            samples.append({"name": p.get("name"), "description_html": html})
    n = len(lengths) or 1
    return {
        "products_with_description": len(lengths),
        "avg_char_length": round(statistics.mean(lengths)) if lengths else 0,
        "median_char_length": round(statistics.median(lengths)) if lengths else 0,
        "avg_word_count": round(statistics.mean(word_counts)) if word_counts else 0,
        "uses_bullet_lists_pct": round(100 * uses_bullets / n),
        "html_tags_used": dict(tag_usage.most_common()),
        "verbatim_samples": samples,
    }


# --------------------------------------------------------------------------- #
# Title / image / taxonomy / pricing analysis
# --------------------------------------------------------------------------- #
def analyze_titles(products):
    separators = Counter()
    lengths = []
    for p in products:
        name = p.get("name") or ""
        lengths.append(len(name))
        for sep in ["—", "-", "|", ":", "·"]:
            if sep in name:
                separators[sep] += 1
    return {
        "avg_char_length": round(statistics.mean(lengths)) if lengths else 0,
        "common_separators": dict(separators.most_common()),
        "examples": [p.get("name") for p in products[:10]],
    }


def analyze_images(products):
    counts = [len(p.get("images") or []) for p in products]
    n = len(counts) or 1
    return {
        "avg_images_per_product": round(sum(counts) / n, 1),
        "min_images": min(counts) if counts else 0,
        "max_images": max(counts) if counts else 0,
        "distribution": dict(Counter(counts).most_common()),
    }


def analyze_taxonomy(products):
    categories, tags = Counter(), Counter()
    for p in products:
        for c in p.get("categories") or []:
            categories[c.get("name")] += 1
        for t in p.get("tags") or []:
            tags[t.get("name")] += 1
    return {
        "categories": dict(categories.most_common()),
        "top_tags": dict(tags.most_common(30)),
    }


def analyze_attributes(products):
    attr_values = {}
    variable_count = 0
    for p in products:
        if p.get("type") == "variable":
            variable_count += 1
        for a in p.get("attributes") or []:
            name = a.get("name")
            attr_values.setdefault(name, Counter()).update(a.get("options") or [])
    return {
        "variable_products": variable_count,
        "attributes": {
            name: dict(vals.most_common(20)) for name, vals in attr_values.items()
        },
    }


def analyze_pricing(products):
    prices = []
    for p in products:
        raw = p.get("price") or p.get("regular_price")
        try:
            if raw not in (None, ""):
                prices.append(float(raw))
        except (TypeError, ValueError):
            pass
    if not prices:
        return {"note": "no prices found"}
    return {
        "count": len(prices),
        "min": min(prices),
        "max": max(prices),
        "median": round(statistics.median(prices), 2),
        "mean": round(statistics.mean(prices), 2),
    }


def analyze_seo(products):
    """Detect Yoast / RankMath meta if exposed via meta_data."""
    seo_keys = Counter()
    for p in products:
        for m in p.get("meta_data") or []:
            key = m.get("key", "")
            if "yoast" in key or "rank_math" in key:
                seo_keys[key] += 1
    return {"detected_seo_meta_keys": dict(seo_keys.most_common())}


# --------------------------------------------------------------------------- #
# Report
# --------------------------------------------------------------------------- #
def write_markdown(profile):
    d, t = profile["descriptions"], profile["titles"]
    lines = [
        "# Store style profile",
        "",
        f"Derived from **{profile['product_count']}** existing products.",
        "",
        "## Titles",
        f"- Average length: {t['avg_char_length']} chars",
        f"- Separators used: {t['common_separators'] or 'none'}",
        f"- Examples: {', '.join(filter(None, t['examples'][:5]))}",
        "",
        "## Descriptions",
        f"- {d['products_with_description']} products have descriptions",
        f"- Average length: {d['avg_char_length']} chars (~{d['avg_word_count']} words)",
        f"- Use bullet lists: {d['uses_bullet_lists_pct']}% of products",
        f"- HTML tags used: {d['html_tags_used'] or 'plain text'}",
        "",
        "## Images",
        f"- Average per product: {profile['images']['avg_images_per_product']}",
        f"- Range: {profile['images']['min_images']}–{profile['images']['max_images']}",
        f"- Distribution (images: count): {profile['images']['distribution']}",
        "",
        "## Categories",
        *[f"- {name}: {count}" for name, count in profile["taxonomy"]["categories"].items()],
        "",
        "## Attributes / variations",
        f"- Variable products: {profile['attributes']['variable_products']}",
        *[f"- {name}: {list(vals.keys())}" for name, vals in profile["attributes"]["attributes"].items()],
        "",
        "## Pricing",
        f"- {profile['pricing']}",
        "",
        "## SEO",
        f"- {profile['seo']['detected_seo_meta_keys'] or 'no SEO plugin meta exposed via API'}",
        "",
        "## Verbatim description samples (voice reference)",
    ]
    for s in d["verbatim_samples"]:
        lines += [f"### {s['name']}", "```html", s["description_html"], "```", ""]
    (OUTPUT_DIR / "profile.md").write_text("\n".join(lines), encoding="utf-8")


# --------------------------------------------------------------------------- #
def main():
    load_dotenv(Path(__file__).parent / ".env")
    base_url = os.getenv("WC_STORE_URL")
    ck = os.getenv("WC_CONSUMER_KEY")
    cs = os.getenv("WC_CONSUMER_SECRET")
    if not all([base_url, ck, cs]):
        raise SystemExit(
            "Missing config. Copy .env.example to .env and fill in "
            "WC_STORE_URL, WC_CONSUMER_KEY, WC_CONSUMER_SECRET."
        )

    OUTPUT_DIR.mkdir(exist_ok=True)
    print(f"Fetching products from {base_url} ...")
    products = fetch_all_products(base_url, auth=(ck, cs))
    print(f"Pulled {len(products)} products.\n")

    (OUTPUT_DIR / "products_raw.json").write_text(
        json.dumps(products, indent=2), encoding="utf-8"
    )

    profile = {
        "product_count": len(products),
        "titles": analyze_titles(products),
        "descriptions": analyze_descriptions(products),
        "images": analyze_images(products),
        "taxonomy": analyze_taxonomy(products),
        "attributes": analyze_attributes(products),
        "pricing": analyze_pricing(products),
        "seo": analyze_seo(products),
    }
    (OUTPUT_DIR / "profile.json").write_text(
        json.dumps(profile, indent=2), encoding="utf-8"
    )
    write_markdown(profile)

    print("Done. Wrote to ./output/:")
    print("  - products_raw.json  (full catalog cache)")
    print("  - profile.json       (machine-readable profile)")
    print("  - profile.md         (read this — human report)")


if __name__ == "__main__":
    main()
