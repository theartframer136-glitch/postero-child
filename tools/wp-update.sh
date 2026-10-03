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
#   wp-update.sh plan          read-only: the server, what is installed, what each stage would update
#   wp-update.sh backup        the backup alone, nothing updated
#   wp-update.sh plugins-low   the plugins nothing else is built on
#   wp-update.sh core          WordPress itself, then its database. This host runs WordPress
#                              from its own managed copy (/opt/h5g/flavors/wp-<version>):
#                              on 3 Oct it said 6.9.9 was the latest while 7.1.2 was out,
#                              so moving to 7 is done in Hostinger's hPanel.
#   wp-update.sh woocommerce   WooCommerce and the plugins built on it
#   wp-update.sh elementor     Elementor, Elementor Pro and the Elementor add-ons
#   wp-update.sh rollback STAMP     put back the plugin folder (and WordPress, for a core
#                                   stage) from that backup; the database is left alone
#   wp-update.sh rollback-db STAMP  put back the database too. Anything written since the
#                                   backup is lost, orders included: only on the owner's word.
#
# A plugin whose new version needs a newer WooCommerce or Elementor than the
# site has is held back at every stage, and the plan says which and why
# (check_needs): on 3 Oct Square 5.5.1 needed WooCommerce 10.9, the shop had
# 10.7, and WooCommerce 11 waits on WordPress 7. They go in once that is done.
#
# A plugin whose newest release needs a newer WordPress or PHP than the site
# has (WP-CLI does not offer it at all) goes to the newest release that does
# run here (find_pins), and what was held for it is checked again in the same
# stage: on 3 Oct, WooCommerce 11.1.2 needed WordPress 7.0 while 11.0.1 runs
# on 6.9, and WooCommerce 11.0.1 lets Square 5.5.1 and Google Listings 3.9.5 in.
#
# Every stage that changes anything backs up first, outside the web root in a
# private folder in /tmp: the database (mysqldump), the plugin folder, and the
# version of WordPress and of every plugin. A stage with nothing to update changes nothing and
# backs up nothing. The four newest backups are kept.
set -uo pipefail
STAGE="${1:-plan}"
STAMP_IN="${2:-}"
WP="wp --allow-root"
# Where backups go. On this host the shell can write nowhere outside the web
# root but /tmp (3 Oct: the home folder, ~/websites and the site folder all
# refuse a new file), and a database dump never goes inside the web root. So:
# a folder in /tmp only this account can open, files only it can read. /tmp is
# cleared from time to time, so these are for rolling a stage back straight
# away; Hostinger's own backups (hPanel) are the long-term copy.
BKDIR="/tmp/af-backup-$(id -u)"
BK="$BKDIR/af-backup"

# Being replaced by theme code (the owner, 3 Oct: "Skip those"): left alone
# while switched on, so an update cannot move them under that work. Once the
# theme has taken over and they are switched off, an update changes nothing a
# visitor sees, and they go with the everyday plugins (the owner, later the
# same day: "update all").
SKIP="code-snippets header-footer-code-manager classic-editor mas-woocommerce-brands wpc-estimated-delivery-date click-to-chat-for-whatsapp"
# WooCommerce and what is built on it: the shop, its prices and its payments.
WOO="woocommerce woocommerce-square google-listings-and-ads woocommerce-currency-switcher woo-variation-swatches"
# Elementor and its add-ons: they move together, an add-on can need the new Elementor.
ELE="elementor elementor-pro essential-addons-for-elementor-lite premium-addons-for-elementor header-footer-elementor dynamic-visibility-for-elementor templately"

# Lists come space- or line-separated (WP-CLI prints one name a line).
in_list() { case " $(printf '%s' "$2" | tr '\n\t' '  ') " in *" $1 "*) return 0;; esac; return 1; }
INACTIVE=$($WP plugin list --status=inactive --field=name 2>/dev/null)
stage_of() {
    if in_list "$1" "$SKIP" && ! in_list "$1" "$INACTIVE"; then echo skip
    elif in_list "$1" "$SKIP"; then echo plugins-low
    elif in_list "$1" "$WOO"; then echo woocommerce
    elif in_list "$1" "$ELE"; then echo elementor
    else echo plugins-low; fi
}
logs() { ls ~/websites/OPu0sKi4J/public_html/wp-content/debug.log ~/websites/OPu0sKi4J/logs/error_log ~/logs/error_log 2>/dev/null; }
LOGLEN=""
for f in $(logs); do LOGLEN="$LOGLEN $f:$(wc -l < "$f")"; done

echo "=== server ==="
for t in mysqldump mysql tar gzip sed xargs; do printf '  %-10s %s\n' "$t" "$(command -v "$t" || echo MISSING)"; done
# -w only asks the permission bits; this host refuses some writes they allow
# (3 Oct: -w said yes for the home folder, creating a file there failed). Try.
for d in "$HOME" "$HOME/websites" "$HOME/websites/OPu0sKi4J" "$HOME/websites/OPu0sKi4J/logs" \
         "$HOME/tmp" "$HOME/.cache" "$HOME/.local" "$HOME/.wp-cli" /tmp "${TMPDIR:-/tmp}"; do
    [ -d "$d" ] || { echo "  (no folder)   $d"; continue; }
    t="$d/.af-write-test-$$"
    if ( : > "$t" ) 2>/dev/null; then rm -f "$t"; echo "  can write:    $d"; else echo "  cannot write: $d"; fi
done
echo "  user $(id -un), home $HOME"
ls -la "$HOME" 2>/dev/null | head -20 | sed 's/^/    /'
df -h /tmp 2>/dev/null | tail -1 | sed 's/^/  \/tmp: /'
echo "  free: $(df -Pk "$HOME/websites/OPu0sKi4J" 2>/dev/null | tail -1 | tr -s ' ' | cut -d' ' -f4) KB"
echo "  database: $($WP db size --human-readable --skip-plugins --skip-themes 2>/dev/null | tail -1)"
echo
echo "=== now ==="
echo "WordPress $($WP core version)"
echo "--- WordPress updates available ---"
$WP core check-update --fields=version,update_type --format=table 2>&1 | head -8
echo "--- plugins ---"
$WP plugin list --fields=name,status,version,update_version --format=table 2>&1
if [ "$STAGE" = plan ]; then
    # This host runs WordPress from its own managed copy; whether wp-admin and
    # wp-includes are links into it says whether an update from here could
    # touch WordPress at all, or it has to be moved on in hPanel.
    echo "--- how WordPress itself is installed here ---"
    for f in wp-admin wp-includes wp-settings.php wp-load.php index.php; do
        if [ -L "$f" ]; then echo "  $f -> $(readlink "$f")"
        elif [ -e "$f" ]; then echo "  $f: here, not a link"
        else echo "  $f: missing"; fi
    done
    echo "  version.php is $(readlink -f wp-includes/version.php 2>/dev/null)"
    echo "  WordPress runs from $($WP eval 'echo ABSPATH;' --skip-plugins --skip-themes 2>/dev/null | tail -1)"
    # Elementor Pro's update comes from Elementor's server, which answers to
    # the licence. Its state as Elementor Pro stored it; never the key.
    echo "--- Elementor Pro licence (the key is not printed) ---"
    $WP eval '
        $k = get_option("elementor_pro_license_key");
        echo "  a key is stored: ", $k ? "yes" : "no", "\n";
        foreach (array("_elementor_pro_license_v2_data", "_elementor_pro_license_data") as $o) {
            $d = get_option($o);
            if (!is_array($d)) continue;
            $v = isset($d["value"]) ? json_decode($d["value"], true) : $d;
            if (!is_array($v)) continue;
            $out = array();
            foreach (array("license", "success", "expires", "error") as $f) if (isset($v[$f])) $out[] = $f . " " . (is_bool($v[$f]) ? ($v[$f] ? "yes" : "no") : (is_scalar($v[$f]) ? $v[$f] : "?"));
            echo "  ", $o, ": ", $out ? implode(", ", $out) : "(nothing readable)", isset($d["timeout"]) ? " (Elementor keeps this until " . gmdate("Y-m-d H:i", (int) $d["timeout"]) . " UTC)" : "", "\n";
        }
    ' 2>/dev/null | grep -E '^  '
fi
echo
# WP-CLI holds back an update that needs a newer WordPress or PHP (WooCommerce
# 11.1.2 needs WordPress 7.0, so it is not offered here), but not one that
# needs a newer WooCommerce or Elementor than the site has. Woo's own
# extensions switch themselves off then: Square 5.5.1 says "WC requires at
# least: 10.9", and on this shop's 10.7 that would take card payment off the
# checkout (3 Oct). So read each new version's header from its download, and
# hold back any that would not run here. A WooCommerce or Elementor update in
# the same run counts. A download that cannot be read is held too.
check_needs() {
    AF_CHECK="$*" $WP eval '
        require_once ABSPATH . "wp-admin/includes/file.php";
        require_once ABSPATH . "wp-admin/includes/plugin.php";
        $want = preg_split("/\s+/", trim(getenv("AF_CHECK")));
        $t = get_site_transient("update_plugins");
        $by = array();
        foreach (($t && !empty($t->response)) ? $t->response : array() as $file => $u) $by[dirname($file)] = array($file, $u);
        $has = array("woocommerce" => defined("WC_VERSION") ? WC_VERSION : "", "elementor" => defined("ELEMENTOR_VERSION") ? ELEMENTOR_VERSION : "");
        // WooCommerce and Elementor first: a new one counts for the rest only once it passes.
        usort($want, function ($a, $b) use ($has) { return (isset($has[$b]) ? 1 : 0) - (isset($has[$a]) ? 1 : 0); });
        foreach ($want as $slug) {
            if (!isset($by[$slug]) || empty($by[$slug][1]->package)) { echo "UNCHECKED $slug: no download offered\n"; continue; }
            list($file, $u) = $by[$slug];
            $tmp = download_url($u->package, 180);
            if (is_wp_error($tmp)) { echo "UNCHECKED $slug: ", $tmp->get_error_message(), "\n"; continue; }
            $head = "";
            if (class_exists("ZipArchive")) {
                $z = new ZipArchive();
                if ($z->open($tmp) === true) { $head = (string) $z->getFromName($file, 8192); $z->close(); }
            } else {
                require_once ABSPATH . "wp-admin/includes/class-pclzip.php";
                $z = new PclZip($tmp);
                $x = $z->extract(PCLZIP_OPT_BY_NAME, $file, PCLZIP_OPT_EXTRACT_AS_STRING);
                if (is_array($x) && isset($x[0]["content"])) $head = substr($x[0]["content"], 0, 8192);
            }
            @unlink($tmp);
            if ($head === "") { echo "UNCHECKED $slug: $file is not in the download\n"; continue; }
            $why = array();
            foreach (array("woocommerce" => array("WC requires at least", "WooCommerce"), "elementor" => array("Elementor requires at least", "Elementor")) as $base => $h) {
                if (!preg_match("/^[ \t\/*#@]*" . preg_quote($h[0], "/") . ":(.*)/mi", $head, $m)) continue;
                $need = trim($m[1]);
                if ($need !== "" && $has[$base] !== "" && version_compare($has[$base], $need, "<")) $why[] = $h[1] . " " . $need . " (the site has " . $has[$base] . ")";
            }
            if ($why) { echo "HOLD $slug: " . $u->new_version . " needs " . implode(" and ", $why) . "\n"; continue; }
            echo "OK $slug " . $u->new_version . "\n";
            if (isset($has[$slug])) $has[$slug] = $u->new_version;
        }
    ' 2>/dev/null | grep -E '^(OK|HOLD|UNCHECKED) '
}

# A plugin whose newest release needs a newer WordPress or PHP than the site
# has is not offered by WP-CLI at all (WooCommerce 11.1.2 needs WordPress 7.0;
# the host keeps this site on 6.9.9). It can still go to the newest release
# that runs here, read from that release's own header (or readme) on
# wordpress.org. Prints "PIN <slug> <version>: ..." or "NOPIN <slug>: ...".
find_pins() {
    AF_PIN="$*" $WP eval '
        require_once ABSPATH . "wp-admin/includes/plugin-install.php";
        require_once ABSPATH . "wp-admin/includes/plugin.php";
        global $wp_version;
        $all = get_plugins();
        $need = function ($text, $h) { return preg_match("/^[ \t\/*#@=]*" . preg_quote($h, "/") . ":(.*)/mi", $text, $m) ? trim($m[1]) : ""; };
        foreach (preg_split("/\s+/", trim(getenv("AF_PIN"))) as $slug) {
            $file = "";
            foreach ($all as $f => $d) if (dirname($f) === $slug) $file = $f;
            if ($file === "") { echo "NOPIN $slug: not installed\n"; continue; }
            $cur = $all[$file]["Version"];
            $info = plugins_api("plugin_information", array("slug" => $slug, "fields" => array("versions" => true)));
            if (is_wp_error($info) || empty($info->versions)) { echo "NOPIN $slug: wordpress.org gave no list of releases\n"; continue; }
            $vs = array_values(array_filter(array_keys((array) $info->versions), function ($v) use ($cur) { return preg_match("/^\d+(\.\d+)+$/", $v) && version_compare($v, $cur, ">"); }));
            usort($vs, function ($a, $b) { return version_compare($b, $a); });
            $pin = "";
            foreach (array_slice($vs, 0, 30) as $v) {
                $base = "https://plugins.svn.wordpress.org/$slug/tags/$v/";
                $head = substr((string) wp_remote_retrieve_body(wp_remote_get($base . basename($file), array("timeout" => 20))), 0, 8192);
                if (!preg_match("/Plugin Name:/i", $head)) continue;
                $wp = $need($head, "Requires at least"); $php = $need($head, "Requires PHP");
                if ($wp === "" || $php === "") {
                    $readme = substr((string) wp_remote_retrieve_body(wp_remote_get($base . "readme.txt", array("timeout" => 20))), 0, 8192);
                    if ($wp === "") $wp = $need($readme, "Requires at least");
                    if ($php === "") $php = $need($readme, "Requires PHP");
                }
                if (($wp === "" || version_compare($wp_version, $wp, ">=")) && ($php === "" || version_compare(PHP_VERSION, $php, ">="))) { $pin = $v; break; }
            }
            echo $pin !== "" ? "PIN $slug $pin: the newest release WordPress $wp_version runs (" . $info->version . " needs a newer WordPress or PHP)\n"
                             : "NOPIN $slug: no newer release runs on WordPress $wp_version\n";
        }
    ' 2>/dev/null | grep -E '^(PIN|NOPIN) '
}

echo "=== what each stage would update ==="
AVAIL=$($WP plugin list --update=available --field=name 2>/dev/null)
# Plugins with a newer release that WP-CLI does not offer (it needs a newer
# WordPress or PHP): try for the newest release that does run here.
BLOCKED=""
for p in $($WP plugin list --fields=name,update_version --format=csv 2>/dev/null | tail -n +2 | grep -E ',[0-9]' | cut -d, -f1); do
    in_list "$p" "$AVAIL" && continue
    s=$(stage_of "$p"); [ "$s" != skip ] && { [ "$STAGE" = plan ] || [ "$s" = "$STAGE" ]; } && BLOCKED="$BLOCKED $p"
done
PINS=""
[ -n "$BLOCKED" ] && PINS=$(find_pins $BLOCKED)
TO_CHECK=""
for p in $AVAIL; do s=$(stage_of "$p"); [ "$s" != skip ] && { [ "$STAGE" = plan ] || [ "$s" = "$STAGE" ]; } && TO_CHECK="$TO_CHECK $p"; done
CHECKED=""
[ -n "$TO_CHECK" ] && CHECKED=$(check_needs $TO_CHECK)
# Anything not answered OK is held: a crashed check holds them all.
HELD=""
for p in $TO_CHECK; do printf '%s\n' "$CHECKED" | grep -q "^OK $p " || HELD="$HELD $p"; done
for p in $AVAIL; do
    s=$(stage_of "$p")
    if in_list "$p" "$HELD"; then
        why=$(printf '%s\n' "$CHECKED" | grep -E "^(HOLD|UNCHECKED) $p:" | head -1 | cut -d: -f2-)
        printf '  %-12s %s  <- held:%s\n' "$s" "$p" "${why:- the check gave no answer}"
    else
        printf '  %-12s %s\n' "$s" "$p"
    fi
done | sort
for p in $BLOCKED; do
    line=$(printf '%s\n' "$PINS" | grep -E "^(PIN|NOPIN) $p[ :]" | head -1)
    case "$line" in
        PIN*)   printf '  %-12s %s -> %s\n' "$(stage_of "$p")" "$p" "$(printf '%s' "$line" | cut -d' ' -f3-)" ;;
        *)      printf '  %-12s %s  <- held: %s\n' "$(stage_of "$p")" "$p" "$(printf '%s' "${line:-the newest release needs a newer WordPress}" | cut -d: -f2- | sed 's/^ //')" ;;
    esac
done
CORE_NEW=$($WP core check-update --field=version 2>/dev/null | grep -E '^[0-9]+\.[0-9]+' | head -1)
printf '  %-12s %s\n' core "${CORE_NEW:-(WordPress is up to date)}"

if [ "$STAGE" = plan ]; then echo; echo "PLAN ONLY: nothing was changed."; exit 0; fi

# The database's connection, from wp-config.php, into a client options file
# only this account can read: mysqldump and mysql take the password from it,
# never from the command line. (wp db export / import cannot be used: they
# run mysqldump through PHP's exec(), which this host switches off.)
db_cnf() {
    local name user pass host port sock h
    name=$($WP config get DB_NAME --skip-plugins --skip-themes 2>/dev/null | tail -1)
    user=$($WP config get DB_USER --skip-plugins --skip-themes 2>/dev/null | tail -1)
    pass=$($WP config get DB_PASSWORD --skip-plugins --skip-themes 2>/dev/null | tail -1)
    h=$($WP config get DB_HOST --skip-plugins --skip-themes 2>/dev/null | tail -1)
    host="${h%%:*}"; port=""; sock=""
    case "$h" in *:/*) sock="${h#*:}";; *:*) port="${h#*:}";; esac
    DB_NAME="$name"
    DB_CNF="$BKDIR/.db.cnf"
    ( umask 077
      { echo "[client]"
        echo "user=\"$(printf '%s' "$user" | sed 's/\\/\\\\/g; s/"/\\"/g')\""
        echo "password=\"$(printf '%s' "$pass" | sed 's/\\/\\\\/g; s/"/\\"/g')\""
        echo "host=${host:-localhost}"
        [ -n "$port" ] && echo "port=$port"
        [ -n "$sock" ] && echo "socket=$sock"
      } > "$DB_CNF" )
}
trap 'rm -f "$BKDIR/.db.cnf"' EXIT

# Backups: af-backup-db-<stamp>.sql.gz, -plugins-<stamp>.tar.gz and
# -versions-<stamp>.txt in $BKDIR.
backup() {
    STAMP="$(date -u +%Y%m%d-%H%M%S)-$STAGE"
    echo
    echo "=== backup $STAMP ==="
    # The folder must be a real folder this account owns, not a link someone
    # else left in the shared /tmp.
    if [ -L "$BKDIR" ] || { [ -e "$BKDIR" ] && [ ! -O "$BKDIR" ]; }; then
        echo "BACKUP FAILED: $BKDIR is not this account's own folder. Nothing was updated."; exit 2
    fi
    mkdir -p -m 700 "$BKDIR" && chmod 700 "$BKDIR"
    if ! : > "$BK-versions-$STAMP.txt" 2>/dev/null; then echo "BACKUP FAILED: cannot write to $BKDIR. Nothing was updated."; exit 2; fi
    echo "  plugin folder $(du -sh wp-content/plugins | cut -f1); database $($WP db size --human-readable --skip-plugins --skip-themes 2>/dev/null | tail -1 | tr -s '\t ' ' ' | cut -d' ' -f2-)"
    db_cnf
    mysqldump --defaults-extra-file="$DB_CNF" --single-transaction --quick --routines --no-tablespaces "$DB_NAME" 2> "$BK-db-$STAMP.err" | gzip > "$BK-db-$STAMP.sql.gz"
    if [ "${PIPESTATUS[0]}" != 0 ] || ! gunzip -c "$BK-db-$STAMP.sql.gz" | tail -1 | grep -q "Dump completed"; then
        echo "BACKUP FAILED (database): $(head -3 "$BK-db-$STAMP.err"). Nothing was updated."
        rm -f "$BK-db-$STAMP.sql.gz" "$BK-db-$STAMP.err" "$BK-versions-$STAMP.txt"; exit 2
    fi
    rm -f "$BK-db-$STAMP.err"
    if ! tar czf "$BK-plugins-$STAMP.tar.gz" -C wp-content plugins 2>&1; then
        echo "BACKUP FAILED (plugins). Nothing was updated."
        rm -f "$BK-plugins-$STAMP.tar.gz" "$BK-db-$STAMP.sql.gz" "$BK-versions-$STAMP.txt"; exit 2
    fi
    { echo "wordpress $($WP core version)"; $WP plugin list --fields=name,status,version --format=csv 2>/dev/null; } > "$BK-versions-$STAMP.txt"
    chmod 600 "$BK"-*"$STAMP"*
    du -h "$BK"-*"$STAMP"* | sed 's/^/  /'
    # keep the four newest backups
    for kind in db plugins versions; do
        ls -1t "$BK-$kind"-* 2>/dev/null | tail -n +5 | xargs -r rm -f
    done
    echo "BACKUP $STAMP"
}

restore_files() {
    [ -f "$BK-plugins-$STAMP_IN.tar.gz" ] || { echo "No backup $STAMP_IN. Backups there are:"; ls -1 "$BK"-versions-* 2>/dev/null | sed "s|.*versions-||; s|\.txt||"; exit 2; }
    echo "=== putting back the plugin folder from $STAMP_IN ==="
    OLD="wp-content/plugins.replaced-$(date -u +%Y%m%d-%H%M%S)"
    mv wp-content/plugins "$OLD" || { echo "  RESTORE FAILED: could not move the plugin folder aside; nothing changed"; exit 2; }
    if tar xzf "$BK-plugins-$STAMP_IN.tar.gz" -C wp-content; then
        echo "  plugin folder restored; the one it replaced is kept as $OLD"
    else
        rm -rf wp-content/plugins; mv "$OLD" wp-content/plugins
        echo "  RESTORE FAILED: the plugin folder that was there is back in place"; exit 2
    fi
    WAS=$(grep '^wordpress ' "$BK-versions-$STAMP_IN.txt" | cut -d' ' -f2)
    if [ "${STAMP_IN##*-}" = core ] && [ -n "$WAS" ] && [ "$WAS" != "$($WP core version)" ]; then
        echo "=== putting back WordPress $WAS ==="
        $WP core download --version="$WAS" --force --skip-content 2>&1 | tail -3
    fi
}

case "$STAGE" in
    plugins-low|woocommerce|elementor)
        LIST=""
        for p in $AVAIL; do [ "$(stage_of "$p")" = "$STAGE" ] && ! in_list "$p" "$HELD" && LIST="$LIST $p"; done
        PINNED=$(printf '%s\n' "$PINS" | grep '^PIN ' | cut -d' ' -f2,3 | tr -d ':')
        if [ -z "$LIST" ] && [ -z "$PINNED" ]; then echo; echo "Nothing to update in $STAGE: nothing was changed."; exit 0; fi
        backup
        # The pinned ones first: a WooCommerce brought forward here is what
        # the extensions held for it below are checked against again.
        printf '%s\n' "$PINNED" | while read -r p v; do
            [ -n "$p" ] || continue
            echo
            echo "=== updating $p to $v (the newest release this WordPress runs) ==="
            $WP plugin update "$p" --version="$v" 2>&1
        done
        if [ -n "$LIST" ]; then
            echo
            echo "=== updating:$LIST ==="
            $WP plugin update $LIST 2>&1
        fi
        if [ "$STAGE" = woocommerce ]; then echo "--- WooCommerce database ---"; $WP wc update 2>&1 | tail -5; fi
        # Held for a newer WooCommerce or Elementor than the site had: if the
        # update above brought one far enough, they pass the check now.
        if [ -n "$PINNED" ] && [ -n "$HELD" ]; then
            AGAIN=$(check_needs $HELD)
            NOW=""
            for p in $HELD; do printf '%s\n' "$AGAIN" | grep -q "^OK $p " && NOW="$NOW $p"; done
            printf '%s\n' "$AGAIN" | grep -E '^(HOLD|UNCHECKED) ' | sed 's/^/  still held: /'
            if [ -n "$NOW" ]; then
                echo
                echo "=== updating, now that they run here:$NOW ==="
                $WP plugin update $NOW 2>&1
            fi
        fi
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
    backup)
        backup
        echo "BACKUP ONLY: nothing was updated."
        exit 0
        ;;
    rollback)
        restore_files
        ;;
    rollback-db)
        restore_files
        [ -f "$BK-db-$STAMP_IN.sql.gz" ] || { echo "No database backup $STAMP_IN"; exit 2; }
        db_cnf
        echo "=== putting back the database from $STAMP_IN ==="
        gunzip -c "$BK-db-$STAMP_IN.sql.gz" | mysql --defaults-extra-file="$DB_CNF" "$DB_NAME" 2>&1 | tail -2 \
            && echo "  database restored"
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
