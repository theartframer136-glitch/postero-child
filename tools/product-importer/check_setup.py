#!/usr/bin/env python3
"""
Status checker — shows what's configured and which image source will be used.

    python check_setup.py
"""

import os
from pathlib import Path

from dotenv import load_dotenv

HERE = Path(__file__).parent


def mask(v):
    if not v:
        return "(not set)"
    v = v.strip()
    return f"set  ...{v[-4:]}" if len(v) > 4 else "set"


def main():
    load_dotenv(HERE / ".env")
    print("=== The Art Framer — setup status ===\n")

    print("WooCommerce (create products):")
    print(f"  WC_STORE_URL       : {os.getenv('WC_STORE_URL') or '(not set)'}")
    print(f"  WC_CONSUMER_KEY    : {mask(os.getenv('WC_CONSUMER_KEY'))}")
    print(f"  WC_CONSUMER_SECRET : {mask(os.getenv('WC_CONSUMER_SECRET'))}")

    print("\nWordPress app password (upload generated images):")
    print(f"  WP_APP_USER        : {os.getenv('WP_APP_USER') or '(not set)'}")
    print(f"  WP_APP_PASSWORD    : {mask(os.getenv('WP_APP_PASSWORD'))}")

    print("\nImage sources:")
    gemini = bool(os.getenv("GEMINI_API_KEY"))
    openai = bool(os.getenv("OPENAI_API_KEY"))
    mockup = bool(os.getenv("DYNAMIC_MOCKUPS_API_KEY")) and (HERE / "mockups.json").exists()
    templates = list((HERE / "templates" / "frames").glob("*.png")) if (HERE / "templates" / "frames").exists() else []
    print(f"  GEMINI_API_KEY     : {mask(os.getenv('GEMINI_API_KEY'))}")
    print(f"  OPENAI_API_KEY     : {mask(os.getenv('OPENAI_API_KEY'))}")
    print(f"  Dynamic Mockups    : {'configured' if mockup else '(not set)'}")
    print(f"  Frame templates    : {len(templates)} PNG(s)")

    # Which one actually wins
    if mockup:
        chosen = "Dynamic Mockups API (photoreal)"
    elif gemini:
        chosen = "Gemini 2.5 Flash Image / Nano Banana (AI)"
    elif openai:
        chosen = "OpenAI GPT-Image (AI)"
    elif templates:
        chosen = "real frame templates"
    else:
        chosen = "built-in drawn compositor (free default)"

    print("\n-------------------------------------------")
    print(f"GALLERY IMAGES WILL USE:  {chosen}")
    print("-------------------------------------------")
    if gemini and chosen.startswith("Gemini"):
        print("Gemini IS connected and will generate your images. ✓")
    elif gemini:
        print("Gemini key is set, but a higher-priority source is active above.")
    else:
        print("Gemini is NOT connected — add GEMINI_API_KEY to .env to enable it.")


if __name__ == "__main__":
    main()
