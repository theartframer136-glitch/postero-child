#!/usr/bin/env python3
"""
Dynamic Mockups API integration (photorealistic mockups).

Sends your real artwork URL to Dynamic Mockups, which composites it into your
chosen photoreal frame/room mockups and returns hosted image URLs — your art is
preserved exactly (never redrawn).

Setup:
  1. Create an account at dynamicmockups.com, build/pick your mockups, and get an
     API key.
  2. Put the key in .env:  DYNAMIC_MOCKUPS_API_KEY=...
  3. List your mockups to get their UUIDs:
         python mockups_api.py list
  4. Copy mockups.json.example to mockups.json and fill in the
     mockup_uuid + smart_object_uuid for each mockup you want rendered.

The pipeline then uses these automatically when a gallery is generated.
"""

import json
import os
import sys
from pathlib import Path

import requests

API_BASE = "https://app.dynamicmockups.com/api/v1"
HERE = Path(__file__).parent
IMG_EXT = (".png", ".jpg", ".jpeg", ".webp")


def _find_image_urls(obj):
    """Recursively pull image URLs out of an arbitrary JSON response."""
    found = []
    if isinstance(obj, str):
        clean = obj.split("?")[0].lower()
        if obj.startswith("http") and clean.endswith(IMG_EXT):
            found.append(obj)
    elif isinstance(obj, dict):
        for v in obj.values():
            found += _find_image_urls(v)
    elif isinstance(obj, list):
        for v in obj:
            found += _find_image_urls(v)
    return found


def list_mockups(api_key):
    r = requests.get(f"{API_BASE}/mockups", headers={"x-api-key": api_key}, timeout=60)
    r.raise_for_status()
    return r.json()


def render(api_key, mockup_uuid, smart_object_uuid, asset_url):
    """Render one mockup; return the list of rendered image URL(s)."""
    body = {
        "mockup_uuid": mockup_uuid,
        "smart_objects": [{"uuid": smart_object_uuid, "asset": {"url": asset_url}}],
    }
    r = requests.post(f"{API_BASE}/renders",
                      headers={"x-api-key": api_key, "Content-Type": "application/json"},
                      json=body, timeout=180)
    r.raise_for_status()
    return _find_image_urls(r.json())


def load_config():
    p = HERE / "mockups.json"
    if not p.exists():
        return []
    return json.loads(p.read_text(encoding="utf-8"))


def is_configured():
    return bool(os.getenv("DYNAMIC_MOCKUPS_API_KEY")) and bool(load_config())


def render_all(art_url, api_key=None):
    """Render every mockup in mockups.json for the given artwork URL."""
    api_key = api_key or os.getenv("DYNAMIC_MOCKUPS_API_KEY")
    urls = []
    for m in load_config():
        urls += render(api_key, m["mockup_uuid"], m["smart_object_uuid"], art_url)
    return urls


if __name__ == "__main__":
    from dotenv import load_dotenv
    load_dotenv(HERE / ".env")
    key = os.getenv("DYNAMIC_MOCKUPS_API_KEY")
    if not key:
        raise SystemExit("Set DYNAMIC_MOCKUPS_API_KEY in .env first.")
    if len(sys.argv) > 1 and sys.argv[1] == "list":
        data = list_mockups(key)
        # print uuids to help fill mockups.json
        print(json.dumps(data, indent=2)[:6000])
    else:
        print("usage: python mockups_api.py list")
