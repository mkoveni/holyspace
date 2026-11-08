#!/usr/bin/env bash
# Installs one built web release on the server, as root:
#   sudo bash remote-install.sh /path/to/onechurch-web-<id>.tar.gz      (release.sh, by hand)
#   ... | sudo /usr/local/sbin/onechurch-web-install --stdin <id>       (CI, through ci-receive.sh)
# The archive holds the contents of frontend/dist (vite build). Steps: unpack into releases/<id>, carry over the
# hashed assets of the previous releases (open tabs keep lazy-loading the chunks they were built with), switch
# `current` atomically, check the site over HTTPS (switches back on failure), keep 5 releases.
set -euo pipefail

APP=onechurch-web
BASE=/srv/$APP
KEEP=5
# How many earlier releases' assets each new release keeps serving.
CARRY=2

[[ $EUID -eq 0 ]] || { echo "Run as root (sudo)." >&2; exit 1; }
TMP_ARCHIVE=
staging=
trap 'rm -rf "$staging"; [[ -z "$TMP_ARCHIVE" ]] || rm -f "$TMP_ARCHIVE"' EXIT
if [[ "${1:-}" == "--stdin" ]]; then
    RELEASE_ID="${2:-}"
    [[ "$RELEASE_ID" =~ ^[0-9]{14}-[0-9a-f]{12}$ ]] || { echo "Invalid release id." >&2; exit 2; }
    TMP_ARCHIVE="$(mktemp --suffix=.tar.gz)"
    ARCHIVE="$TMP_ARCHIVE"
    # 100 MB is far above a built web release (a few MB); refuse anything larger.
    head -c 100000000 > "$ARCHIVE"
    [[ -s "$ARCHIVE" ]] || { echo "No release archive on stdin." >&2; exit 2; }
else
    ARCHIVE="${1:?usage: remote-install.sh <release.tar.gz> | --stdin <release-id>}"
    RELEASE_ID="$(basename "$ARCHIVE" .tar.gz)"
    RELEASE_ID="${RELEASE_ID#onechurch-web-}"
    [[ "$RELEASE_ID" =~ ^[0-9]{14}-[0-9a-f]{12}$ ]] || { echo "Invalid release id: $RELEASE_ID" >&2; exit 2; }
fi
[[ -f "$BASE/shared/deploy.conf" ]] || { echo "Run setup-server.sh first." >&2; exit 1; }
# shellcheck source=/dev/null
source "$BASE/shared/deploy.conf"
# One deploy at a time (CI and a manual release.sh must never interleave).
exec 9>"$BASE/shared/.deploy.lock"
flock -n 9 || { echo "Another deploy is running." >&2; exit 1; }

log() { printf '\n==> %s\n' "$*"; }

RELEASE="$BASE/releases/$RELEASE_ID"
[[ ! -e "$RELEASE" ]] || { echo "$RELEASE already exists." >&2; exit 1; }
# Only a real symlink counts: readlink -f of a missing `current` prints the path itself.
PREVIOUS=
if [[ -L "$BASE/current" ]]; then PREVIOUS="$(readlink -f "$BASE/current")"; fi

log "Unpacking $RELEASE_ID"
staging="$BASE/releases/.$RELEASE_ID.partial"
rm -rf "$staging"
install -d -m 0755 "$staging"
# Static files only: no links, no special files, no owners from the archive. (The listing is read whole first: a
# `tar | grep -q` pipeline under pipefail can fail on SIGPIPE and silently pass the check.)
listing="$(tar -tvzf "$ARCHIVE")"
if grep -qvE '^[-d]' <<< "$listing"; then
    echo "The archive contains links or special files; refusing it." >&2
    exit 1
fi
tar -xzf "$ARCHIVE" -C "$staging" --no-same-owner --no-same-permissions
[[ -f "$staging/index.html" ]] || { echo "The archive has no index.html at its root." >&2; exit 1; }
[[ -d "$staging/assets" ]] && grep -q '/assets/' "$staging/index.html" \
    || { echo "No assets/ directory or no /assets/ bundle in index.html; is this a vite build?" >&2; exit 1; }
chown -R root:root "$staging"
find "$staging" -type d -exec chmod 0755 {} +
find "$staging" -type f -exec chmod 0644 {} +

# What this release built itself, so later releases carry exactly these files and nothing accumulates forever.
(cd "$staging" && find assets -type f 2>/dev/null | sort) > "$staging/.release-assets"
chmod 0644 "$staging/.release-assets"

carried=0
# Release ids start with a UTC timestamp, so name order is release order (the staging directory starts with a dot).
while IFS= read -r old; do
    [[ -f "$old/.release-assets" ]] || continue
    while IFS= read -r file; do
        [[ "$file" == assets/* && "$file" != *..* && -f "$old/$file" && ! -L "$old/$file" && ! -e "$staging/$file" ]] || continue
        install -D -m 0644 "$old/$file" "$staging/$file"
        carried=$((carried + 1))
    done < "$old/.release-assets"
done < <(find "$BASE/releases" -mindepth 1 -maxdepth 1 -type d -name '[0-9]*' | sort -r | head -n "$CARRY")
echo "Carried $carried asset file(s) from earlier releases."
mv -T "$staging" "$RELEASE"
staging=

switch_to() {
    ln -sfn "$1" "$BASE/current.next"
    mv -Tf "$BASE/current.next" "$BASE/current"
}

log "Switching current to $RELEASE_ID"
switch_to "$RELEASE"

log "Health check"
healthy=1
CERT="/etc/letsencrypt/live/$DOMAIN/fullchain.pem"
if [[ -f "$CERT" ]]; then
    # The entry bundle this release's index.html loads; the live site must serve both.
    entry="$(grep -oE '/assets/[^"]+\.js' "$RELEASE/index.html" | head -n1)"
    check() { curl -fsS --max-time 5 --resolve "$DOMAIN:443:127.0.0.1" "https://$DOMAIN$1"; }
    healthy=0
    for _ in 1 2 3 4 5; do
        if check / | grep -qF "$entry" && check "$entry" >/dev/null && check /login | grep -qF "$entry"; then
            healthy=1; break
        fi
        sleep 2
    done
    # The API is deployed on its own; only report it here.
    if check /health/ready 2>/dev/null | grep -q '"UP"'; then
        echo "API reachable through https://$DOMAIN/api/ (health UP)."
    else
        echo "Warning: https://$DOMAIN/health/ready is not UP. The web app is live but the API behind it is not answering."
    fi
else
    echo "No certificate for $DOMAIN yet; skipping the HTTPS check."
    [[ -f "$BASE/current/index.html" ]] || healthy=0
fi

if [[ $healthy -ne 1 ]]; then
    echo "Release $RELEASE_ID is unhealthy." >&2
    if [[ -n "$PREVIOUS" && -d "$PREVIOUS" ]]; then
        echo "Switching back to $(basename "$PREVIOUS")." >&2
        switch_to "$PREVIOUS"
    else
        echo "No earlier release to switch back to; taking the site offline (404) instead." >&2
        rm -f "$BASE/current"
    fi
    # Gone, so a later deploy never carries its assets.
    rm -rf "$RELEASE"
    echo "Inspect: /var/log/nginx/$APP.error.log; sudo nginx -t" >&2
    exit 1
fi

log "Pruning old releases (keeping $KEEP)"
current="$(readlink -f "$BASE/current")"
find "$BASE/releases" -mindepth 1 -maxdepth 1 -type d -name '[0-9]*' | sort -r | tail -n +$((KEEP + 1)) | while read -r old; do
    [[ "$old" == "$current" ]] || rm -rf "$old"
done

log "Release $RELEASE_ID is live at https://$DOMAIN"
