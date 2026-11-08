#!/usr/bin/env bash
# Points `current` back to an earlier release on the server. Run there as root:
#   sudo bash /srv/onechurch-api/current/deploy/rollback.sh            (the release before the current one)
#   sudo bash /srv/onechurch-api/current/deploy/rollback.sh <release-id>
# Database migrations are not reverted; they are additive, so older code keeps working against the newer schema.
set -euo pipefail

APP=onechurch-api
BASE=/srv/$APP
[[ $EUID -eq 0 ]] || { echo "Run as root (sudo)." >&2; exit 1; }
# shellcheck source=/dev/null
source "$BASE/shared/deploy.conf"

current="$(readlink -f "$BASE/current")"
if [[ -n "${1:-}" ]]; then
    target="$BASE/releases/$1"
else
    # Release ids start with a UTC timestamp, so name order is release order.
    target="$(ls -1d "$BASE"/releases/*/ | sed 's:/$::' | sort | awk -v c="$current" '$0 == c { print prev; exit } { prev = $0 }')"
fi
[[ -n "$target" && -d "$target" ]] || { echo "No release to roll back to." >&2; exit 1; }

echo "Switching $(basename "$current") -> $(basename "$target")"
ln -sfn "$target" "$BASE/current.next"
mv -Tf "$BASE/current.next" "$BASE/current"
systemctl reload "php$PHP_VERSION-fpm"
systemctl restart "$APP-worker"
echo "Rolled back. Check: curl -fsS https://$DOMAIN/health/ready"
