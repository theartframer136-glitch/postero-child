#!/bin/bash
# Updates WordPress and its plugins, one stage at a time, each behind a fresh
# backup. Runs ON THE SERVER, from the WordPress root; .github/workflows/
# update-wordpress.yml sends it up and runs it.
#
# Owner, 3 Oct: "update WordPress to the latest versions and all the plugins
# to the latest version", in stages with the shop checked after each, and
# leaving alone the six plugins another piece of work is replacing with theme
# code. Blanket auto-updates were switched off on 20 Jul after one left the
# site stuck in maintenance mode (tools/recover-maintenance.php): updates are
# done by hand, after a backup. This is that.
#
#   wp-update.sh plan          read-only: what is installed, what each stage would update
#   wp-update.sh plugins-low   the plugins nothing else is built on
#   wp-update.sh core          WordPress itself, then its database
#   wp-update.sh woocommerce   WooCommerce and the plugins built on it
#   wp-update.sh elementor     Elementor, Elementor Pro and the Elementor add-ons
#   wp-update.sh rollback STAMP     put back the plugin folder (and WordPress, for a core
#                                   stage) from that backup; the database is left alone
#   wp-update.sh rollback-db STAMP  put back the database too. Anything written since the
#                                   backup is lost, orders included: only on the owner's word.
#
# Every stage that changes anything backs up first, into ~/af-backups (outside
# the web root): the database, the plugin folder, and the version of WordPress
# and of every plugin. A stage with nothing to update changes nothing and
# backs up nothing. The four newest backups are kept.
set -uo pipefail
STAGE="${1:-plan}"
STAMP_IN="${2:-}"
WP="wp --allow-root"
BK="$HOME/af-backups"

# Being replaced by theme code (the owner, 3 Oct: "Skip those"). Never updated here.
SKIP="code-snippets header-footer-code-manager classic-editor mas-woocommerce-brands wpc-estimated-delivery-date click-to-chat-for-whatsapp"
# WooCommerce and what is built on it: the shop, its prices and its payments.
WOO="woocommerce woocommerce-square google-listings-and-ads woocommerce-currency-switcher woo-variation-swatches"
# Elementor and its add-ons: they move together, an add-on can need the new Elementor.
ELE="elementor elementor-pro essential-addons-for-elementor-lite premium-addons-for-elementor header-footer-elementor dynamic-visibility-for-elementor templately"

in_list() { case " $2 " in *" $1 "*) return 0;; esac; return 1; }
stage_of() {
    if in_list "$1" "$SKIP"; then echo skip
    elif in_list "$1" "$WOO"; then echo woocommerce
    elif in_list "$1" "$ELE"; then echo elementor
    else echo plugins-low; fi
}
logs() { ls ~/websites/OPu0sKi4J/public_html/wp-content/debug.log ~/websites/OPu0sKi4J/logs/error_log ~/logs/error_log 2>/dev/null; }
LOGLEN=""
for f in $(logs); do LOGLEN="$LOGLEN $f:$(wc -l < "$f")"; done

echo "=== now ==="
echo "WordPress $($WP core version)"
echo "--- WordPress updates available ---"
$WP core check-update --fields=version,update_type --format=table 2>&1 | head -8
echo "--- plugins ---"
$WP plugin list --fields=name,status,version,update_version --format=table 2>&1
echo
echo "=== what each stage would update ==="
AVAIL=$($WP plugin list --update=available --field=name 2>/dev/null)
for p in $AVAIL; do printf '  %-12s %s\n' "$(stage_of "$p")" "$p"; done | sort
CORE_NEW=$($WP core check-update --field=version 2>/dev/null | grep -E '^[0-9]+\.[0-9]+' | head -1)
printf '  %-12s %s\n' core "${CORE_NEW:-(WordPress is up to date)}"

if [ "$STAGE" = plan ]; then echo; echo "PLAN ONLY: nothing was changed."; exit 0; fi

backup() {
    mkdir -p "$BK"
    STAMP="$(date -u +%Y%m%d-%H%M%S)-$STAGE"
    echo
    echo "=== backup $STAMP ==="
    FREE_KB=$(df -Pk "$HOME" | awk 'NR==2 {print $4}')
    NEED_KB=$(( $(du -sk wp-content/plugins | cut -f1) + 2000000 ))
    echo "  plugin folder $(du -sh wp-content/plugins | cut -f1); free on disk $((FREE_KB / 1024)) MB"
    if [ -n "$FREE_KB" ] && [ "$FREE_KB" -lt "$NEED_KB" ]; then
        echo "BACKUP REFUSED: not enough free disk for a backup. Nothing was updated."; exit 2
    fi
    if ! $WP db export "$BK/db-$STAMP.sql" --quiet 2>&1; then echo "BACKUP FAILED (database). Nothing was updated."; exit 2; fi
    gzip -f "$BK/db-$STAMP.sql"
    if ! tar czf "$BK/plugins-$STAMP.tar.gz" -C wp-content plugins 2>&1; then echo "BACKUP FAILED (plugins). Nothing was updated."; exit 2; fi
    { echo "wordpress $($WP core version)"; $WP plugin list --fields=name,status,version --format=csv; } > "$BK/versions-$STAMP.txt"
    ls -la "$BK"/*"$STAMP"* | awk '{print "  " $5 "  " $9}'
    # keep the four newest backups
    for kind in db plugins versions; do
        ls -1t "$BK"/$kind-* 2>/dev/null | tail -n +5 | xargs -r rm -f
    done
    echo "BACKUP $STAMP"
}

restore_files() {
    [ -f "$BK/plugins-$STAMP_IN.tar.gz" ] || { echo "No backup $STAMP_IN in $BK:"; ls -1 "$BK"; exit 2; }
    echo "=== putting back the plugin folder from $STAMP_IN ==="
    OLD="wp-content/plugins.replaced-$(date -u +%Y%m%d-%H%M%S)"
    mv wp-content/plugins "$OLD" || { echo "  RESTORE FAILED: could not move the plugin folder aside; nothing changed"; exit 2; }
    if tar xzf "$BK/plugins-$STAMP_IN.tar.gz" -C wp-content; then
        echo "  plugin folder restored; the one it replaced is kept as $OLD"
    else
        rm -rf wp-content/plugins; mv "$OLD" wp-content/plugins
        echo "  RESTORE FAILED: the plugin folder that was there is back in place"; exit 2
    fi
    WAS=$(awk '/^wordpress / {print $2}' "$BK/versions-$STAMP_IN.txt")
    if [ "${STAMP_IN##*-}" = core ] && [ -n "$WAS" ] && [ "$WAS" != "$($WP core version)" ]; then
        echo "=== putting back WordPress $WAS ==="
        $WP core download --version="$WAS" --force --skip-content 2>&1 | tail -3
    fi
}

case "$STAGE" in
    plugins-low|woocommerce|elementor)
        LIST=""
        for p in $AVAIL; do [ "$(stage_of "$p")" = "$STAGE" ] && LIST="$LIST $p"; done
        if [ -z "$LIST" ]; then echo; echo "Nothing to update in $STAGE: nothing was changed."; exit 0; fi
        backup
        echo
        echo "=== updating:$LIST ==="
        $WP plugin update $LIST 2>&1
        if [ "$STAGE" = woocommerce ]; then echo "--- WooCommerce database ---"; $WP wc update 2>&1 | tail -5; fi
        if [ "$STAGE" = elementor ]; then
            echo "--- Elementor database and CSS ---"
            $WP elementor update db 2>&1 | tail -3
            $WP elementor flush-css 2>&1 | tail -2 || $WP elementor flush_css 2>&1 | tail -2
        fi
        ;;
    core)
        if [ -z "$CORE_NEW" ]; then echo; echo "WordPress is up to date: nothing was changed."; exit 0; fi
        backup
        echo
        echo "=== updating WordPress to $CORE_NEW ==="
        $WP core update 2>&1 | tail -5
        $WP core update-db 2>&1 | tail -3
        ;;
    rollback)
        restore_files
        ;;
    rollback-db)
        restore_files
        [ -f "$BK/db-$STAMP_IN.sql.gz" ] || { echo "No database backup $STAMP_IN"; exit 2; }
        echo "=== putting back the database from $STAMP_IN ==="
        gunzip -c "$BK/db-$STAMP_IN.sql.gz" | $WP db import - 2>&1 | tail -2
        ;;
    *)
        echo "Unknown stage: $STAGE"; exit 2
        ;;
esac

echo
echo "=== after ==="
# A stuck .maintenance file is what took the site down on 20 Jul.
rm -f .maintenance && echo "  no maintenance flag left"
$WP cache flush 2>&1 | tail -1
$WP litespeed-purge all 2>&1 | tail -1 || true
echo "WordPress $($WP core version)"
$WP plugin list --fields=name,status,version,update_version --format=table 2>&1
echo
echo "--- new fatal errors in the PHP logs since this run started ---"
NEW=0
for entry in $LOGLEN; do
    f="${entry%:*}"; n="${entry##*:}"
    out=$(tail -n +"$((n + 1))" "$f" 2>/dev/null | grep -E "PHP (Fatal|Parse|Recoverable)|Uncaught|Allowed memory size" | tail -20)
    if [ -n "$out" ]; then echo "[$f]"; echo "$out"; NEW=1; fi
done
[ "$NEW" = 0 ] && echo "  none"
echo "DONE $STAGE"
