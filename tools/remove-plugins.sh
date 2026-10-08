#!/bin/bash
# Removes switched-off plugin folders, each behind a backup. Runs ON THE
# SERVER, from the WordPress root; .github/workflows/remove-plugins.yml sends
# it up and runs it.
#
# Owner, 5 Oct: move what every plugin that can be does into the theme, switch
# them off and delete them; WooCommerce, WooCommerce Square, Elementor and
# LiteSpeed Cache stay. Owner, 6 Oct: all six were switched off; wait three
# days, then remove the folders. tools/diag-plugin-folders.php (6 Oct) found
# nothing a visitor gets loading a file from any switched-off folder.
#
#   remove-plugins.sh check   [slugs|all]        read-only: what would go, sizes, what is refused
#   remove-plugins.sh backup  [slugs|all]        back each folder up; nothing removed. Prints BACKUP <stamp>
#   remove-plugins.sh remove  STAMP [slugs|all]  remove the folders backed up under STAMP
#   remove-plugins.sh restore STAMP [slugs|all]  put folders back from STAMP (they stay switched off)
#
# A folder is removed only if: its name is a plain plugin slug, it is a real
# folder directly in wp-content/plugins (not a link), WordPress lists it as
# inactive, it is not one of the four kept plugins, and the backup STAMP holds
# a readable archive of it. The folder alone goes: WordPress's own Delete
# (and wp plugin delete) would first run the plugin's uninstall code, which
# can wipe settings and data the theme's copies still read. Must-use plugins
# and drop-ins are not in that folder and are never touched.
#
# Backups go where tools/wp-update.sh puts its own: on this host the shell can
# write nowhere outside the web root but /tmp, in a folder only this account
# can open. /tmp is cleared from time to time, so these are for undoing a
# removal soon after; a plugin from wordpress.org can be downloaded again at
# the same version, and for the others Hostinger's own backups (hPanel) are
# the long-term copy.
set -uo pipefail
MODE="${1:-check}"
if [ "$MODE" = remove ] || [ "$MODE" = restore ]; then STAMP_IN="${2:-}"; WHICH="${3:-all}"; else STAMP_IN=""; WHICH="${2:-all}"; fi
WP="wp --allow-root"
KEEP="woocommerce woocommerce-square elementor litespeed-cache"
PLUG="wp-content/plugins"
BKDIR="${AF_BKDIR:-/tmp/af-backup-$(id -u)}"

in_list() { case " $(printf '%s' "$2" | tr '\n\t' '  ') " in *" $1 "*) return 0;; esac; return 1; }
[ -d "$PLUG" ] || { echo "Not in the WordPress root (no $PLUG)."; exit 2; }
case "$MODE" in check|backup|remove|restore) ;; *) echo "Unknown mode: $MODE"; exit 2;; esac
case "$STAMP_IN" in *[!0-9A-Za-z-]*) echo "Bad backup stamp"; exit 2;; esac
if [ "$MODE" = remove ] || [ "$MODE" = restore ]; then [ -n "$STAMP_IN" ] || { echo "$MODE needs the backup stamp"; exit 2; }; fi

ACTIVE=$($WP plugin list --status=active --field=name 2>/dev/null)
INACTIVE=$($WP plugin list --status=inactive --field=name 2>/dev/null)
[ -n "$ACTIVE" ] || { echo "Could not read the plugin list; nothing done."; exit 2; }

# What to work on: every switched-off plugin, or the names given.
if [ "$WHICH" = all ]; then
    if [ "$MODE" = restore ]; then
        WANT=$(ls -1 "$BKDIR"/af-plugin-"$STAMP_IN"-*.tar.gz 2>/dev/null | sed "s|.*/af-plugin-$STAMP_IN-||; s|\.tar\.gz$||")
    else
        WANT="$INACTIVE"
    fi
else
    WANT="$WHICH"
fi
OK=""; REFUSED=0
for s in $WANT; do
    case "$s" in *[!a-z0-9-]*|''|-*) echo "  refused: bad name '$s'"; REFUSED=1; continue;; esac
    if in_list "$s" "$KEEP"; then echo "  refused: $s is one of the plugins the site keeps"; REFUSED=1; continue; fi
    if in_list "$s" "$ACTIVE"; then echo "  refused: $s is switched on"; REFUSED=1; continue; fi
    if [ "$MODE" != restore ]; then
        in_list "$s" "$INACTIVE" || { echo "  refused: WordPress does not list $s as an installed, switched-off plugin"; REFUSED=1; continue; }
        [ -d "$PLUG/$s" ] && [ ! -L "$PLUG/$s" ] || { echo "  refused: $PLUG/$s is not a real folder"; REFUSED=1; continue; }
    fi
    OK="$OK $s"
done
[ -n "$OK" ] || { echo "Nothing to do."; exit "$REFUSED"; }

case "$MODE" in
check)
    echo "=== would be backed up and removed ==="
    for s in $OK; do printf '  %-46s %s\n' "$s" "$(du -sh "$PLUG/$s" | cut -f1)"; done
    echo "  total $(cd "$PLUG" && du -shc $OK | tail -1 | cut -f1); free in /tmp $(df -Ph /tmp | tail -1 | tr -s ' ' | cut -d' ' -f4)"
    echo "CHECK ONLY: nothing was changed."
    ;;
backup)
    if [ -L "$BKDIR" ] || { [ -e "$BKDIR" ] && [ ! -O "$BKDIR" ]; }; then echo "BACKUP FAILED: $BKDIR is not this account's own folder."; exit 2; fi
    mkdir -p -m 700 "$BKDIR" && chmod 700 "$BKDIR"
    STAMP="$(date -u +%Y%m%d-%H%M%S)-plugins"
    echo "=== backup $STAMP ==="
    for s in $OK; do
        f="$BKDIR/af-plugin-$STAMP-$s.tar.gz"
        if tar czf "$f" -C "$PLUG" "$s" 2>/dev/null && tar tzf "$f" 2>/dev/null | head -1 | grep -q "^$s/"; then
            chmod 600 "$f"; printf '  %-46s %s\n' "$s" "$(du -h "$f" | cut -f1)"
        else
            rm -f "$f"; echo "BACKUP FAILED for $s: nothing was removed. Remove nothing under $STAMP."; exit 2
        fi
    done
    $WP plugin list --fields=name,status,version --format=csv 2>/dev/null > "$BKDIR/af-plugin-$STAMP-versions.csv"; chmod 600 "$BKDIR/af-plugin-$STAMP-versions.csv"
    echo "BACKUP $STAMP"
    ;;
remove)
    echo "=== removing the folders backed up under $STAMP_IN ==="
    GONE=""
    for s in $OK; do
        f="$BKDIR/af-plugin-$STAMP_IN-$s.tar.gz"
        if [ ! -f "$f" ] || ! tar tzf "$f" 2>/dev/null | head -1 | grep -q "^$s/"; then echo "  kept $s: no readable backup of it under $STAMP_IN"; REFUSED=1; continue; fi
        rm -rf -- "./$PLUG/$s" && { echo "  removed $s"; GONE="$GONE $s"; } || { echo "  COULD NOT REMOVE $s"; REFUSED=1; }
    done
    echo "plugins now: $($WP plugin list --fields=name,status --format=csv 2>/dev/null | tail -n +2 | tr '\n' ' ')"
    echo "DONE removed:${GONE:- nothing}"
    ;;
restore)
    echo "=== putting folders back from $STAMP_IN (they stay switched off) ==="
    for s in $OK; do
        f="$BKDIR/af-plugin-$STAMP_IN-$s.tar.gz"
        [ -f "$f" ] || { echo "  no backup of $s under $STAMP_IN"; REFUSED=1; continue; }
        [ -e "$PLUG/$s" ] && { echo "  $s is already there; left as it is"; continue; }
        tar xzf "$f" -C "$PLUG" && echo "  restored $s" || { echo "  COULD NOT RESTORE $s"; REFUSED=1; }
    done
    echo "DONE"
    ;;
esac
exit "$REFUSED"
