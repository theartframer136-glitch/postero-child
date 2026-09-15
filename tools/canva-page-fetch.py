#!/usr/bin/env python3
"""Download one page of the Master Brochure as a PNG, so it can be compared
pixel for pixel against a product's contact-sheet tile.

WHY THIS EXISTS. The audit's whole rule is that a product belongs on a page only
if the PICTURE matches — titles and captions have both been caught describing a
different painting. Judging that from the thumbnail an MCP read-design call
renders inline is fine for "is this a horse or a deity" and not fine for "which
of these two black idols", which is how #17543 sat mirrored on #15913's page for
several passes.

Downloading the page directly used to be impossible: media.canva.com is refused
at the agent proxy. But every thumbnail URL read-design hands back carries a
`fallback` query parameter holding a pre-signed s3.amazonaws.com URL for the very
same PNG, and that host is not refused. So: pass the media.canva.com URL in
verbatim, and this pulls the fallback out and fetches that instead.

The signature inside `fallback` is per-object and expires, so a URL cannot be
reused for another page or kept for long — call read-design again for a fresh one.

Usage:
    tools/canva-page-fetch.py out.png '<the media.canva.com url from read-design>'
"""
import subprocess
import sys
from urllib.parse import urlparse, parse_qs


def fetch(out, url):
    fallback = parse_qs(urlparse(url).query).get('fallback')
    if not fallback:
        sys.exit('no fallback parameter in that URL — pass the media.canva.com '
                 'URL exactly as read-design returned it')
    r = subprocess.run(['curl', '-sS', '-o', out, '-w', '%{http_code}', fallback[0]],
                       capture_output=True, text=True)
    code = r.stdout.strip()
    if code != '200':
        sys.exit('HTTP %s fetching the page (the signature may have expired — '
                 'read the design again for a fresh URL)%s' % (code, r.stderr))
    print('%s written' % out)


if __name__ == '__main__':
    if len(sys.argv) != 3:
        sys.exit(__doc__)
    fetch(sys.argv[1], sys.argv[2])
