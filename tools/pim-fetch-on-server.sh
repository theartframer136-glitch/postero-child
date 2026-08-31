#!/bin/bash
# Fetch the Products In Motion reels onto the site, from the site.
#
# WHY HERE AND NOT ANYWHERE ELSE
# YouTube refuses video downloads from datacenter addresses. Measured three
# times: twice from GitHub Actions runners, once from the agent container. The
# one address never tried is this one - the web host itself. Shared hosting
# does not look like CI to YouTube, so it may simply be served. If it is, the
# whole thing is automatic: the site fetches its own reels on deploy and the
# owner does nothing at all.
#
# If YouTube refuses here too, this exits cleanly saying so, and the row falls
# back to a chrome-free embed instead - no titles, no buttons, still no work
# for the owner. Nothing here can break a deploy: every failure is per-video
# and the script always exits 0.
#
# Run on the server:  bash wp-content/themes/postero-child/tools/pim-fetch-on-server.sh

set -u
WEB="$(cd "$(dirname "$0")/../../../.." && pwd)"     # .../public_html
THEME="$WEB/wp-content/themes/postero-child"
DEST="$WEB/wp-content/uploads/pim"
BIN="$HOME/.af-bin"
YTDLP="$BIN/yt-dlp"
MAX_PER_RUN="${MAX_PER_RUN:-16}"

echo "=== FETCH THE ROW'S REELS, ON THE SERVER ==="
echo "  site : $WEB"
mkdir -p "$DEST" "$BIN" 2>/dev/null

# -- which ids does the row use, and which are still missing ---------------
IDS="$(cd "$WEB" && wp eval-file "$THEME/tools/pim-print-ids.php" --allow-root 2>/dev/null \
        | grep -E '^[A-Za-z0-9_-]{11}$')"
TOTAL=$(printf '%s\n' "$IDS" | grep -c . || true)
echo "  videos in the row: $TOTAL"
[ "$TOTAL" -gt 0 ] || { echo "  no ids - nothing to do."; echo "=== DONE ==="; exit 0; }

MISSING=""
for id in $IDS; do
    [ -s "$DEST/$id.mp4" ] || MISSING="$MISSING $id"
done
NMISS=$(printf '%s\n' $MISSING | grep -c . || true)
echo "  already local    : $((TOTAL - NMISS))"
echo "  still to fetch   : $NMISS"
[ "$NMISS" -gt 0 ] || { echo "  every reel is already on the site."; echo "=== DONE ==="; exit 0; }

# -- the downloader -------------------------------------------------------
# yt-dlp_linux is a self-contained build: no python, no pip, no packages to
# install on shared hosting. Refreshed when older than a week, because
# YouTube changes its player often and a stale copy simply stops working.
NEED_DL=1
if [ -x "$YTDLP" ]; then
    if [ -z "$(find "$YTDLP" -mtime +7 2>/dev/null)" ]; then NEED_DL=0; fi
fi
if [ "$NEED_DL" = "1" ]; then
    echo "  fetching yt-dlp..."
    curl -sSL --retry 2 --max-time 180 \
        -o "$YTDLP.new" \
        https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp_linux 2>/dev/null \
        && mv -f "$YTDLP.new" "$YTDLP" && chmod +x "$YTDLP" 2>/dev/null
fi
if ! "$YTDLP" --version >/dev/null 2>&1; then
    echo "  yt-dlp will not run here (shared hosting may forbid it)."
    echo "  The row keeps its chrome-free embeds. Nothing is broken."
    echo "=== DONE ==="
    exit 0
fi
echo "  yt-dlp $("$YTDLP" --version 2>/dev/null)"

# -- fetch ----------------------------------------------------------------
# 720p is plenty for a 290px-wide card and keeps each file a few MB. The
# merge branch comes first; the progressive stream is only ~360p and would
# otherwise always win.
FMT='bv*[ext=mp4][height<=720]+ba[ext=m4a]/b[ext=mp4][height<=720]/b[height<=720]/b'
GOT=0; FAIL=0; TRIED=0
for id in $MISSING; do
    [ "$TRIED" -ge "$MAX_PER_RUN" ] && break
    TRIED=$((TRIED + 1))
    if "$YTDLP" --quiet --no-warnings --no-playlist \
         -f "$FMT" --merge-output-format mp4 --max-filesize 40M \
         --socket-timeout 30 --retries 2 \
         -o "$DEST/$id.mp4" "https://www.youtube.com/watch?v=$id" >/dev/null 2>&1 \
       && [ -s "$DEST/$id.mp4" ]; then
        GOT=$((GOT + 1))
        echo "    ok    $id.mp4  ($(du -h "$DEST/$id.mp4" 2>/dev/null | cut -f1))"
    else
        FAIL=$((FAIL + 1))
        rm -f "$DEST/$id.mp4" "$DEST/$id.mp4.part" 2>/dev/null
        echo "    no    $id"
    fi
done

echo "  fetched $GOT, failed $FAIL, of $TRIED attempted"
if [ "$GOT" = "0" ] && [ "$FAIL" -gt 0 ]; then
    echo "  YouTube refused this address too. The row keeps its chrome-free"
    echo "  embeds, which need nothing from anyone."
fi
chmod 644 "$DEST"/*.mp4 2>/dev/null
echo "=== DONE ==="
exit 0
