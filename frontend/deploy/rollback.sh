#!/usr/bin/env bash
# Points `current` back to an earlier web release. Run on the server as root:
#   sudo onechurch-web-rollback                 (the release before the current one)
#   sudo onechurch-web-rollback <release-id>
# No reload is needed: nginx resolves the `current` symlink on every request.
set -euo pipefail

APP=onechurch-web
BASE=/srv/$APP
[[ $EUID -eq 0 ]] || { echo "Run as root (sudo)." >&2; exit 1; }
# shellcheck source=/dev/null
source "$BASE/shared/deploy.conf"
exec 9>"$BASE/shared/.deploy.lock"
flock -n 9 || { echo "A deploy is running." >&2; exit 1; }

current="$(readlink -f "$BASE/current")"
if [[ -n "${1:-}" ]]; then
    [[ "$1" =~ ^[0-9]{14}-[0-9a-f]{12}$ ]] || { echo "Invalid release id." >&2; exit 2; }
    target="$BASE/releases/$1"
else
    # Release ids start with a UTC timestamp, so name order is release order.
    target="$(find "$BASE/releases" -mindepth 1 -maxdepth 1 -type d -name '[0-9]*' | sort \
        | awk -v c="$current" '$0 == c { print prev; exit } { prev = $0 }')"
fi
[[ -n "$target" && -f "$target/index.html" ]] || { echo "No release to roll back to." >&2; exit 1; }

echo "Switching $(basename "$current") -> $(basename "$target")"
ln -sfn "$target" "$BASE/current.next"
mv -Tf "$BASE/current.next" "$BASE/current"
echo "Rolled back. Check: https://$DOMAIN/"
