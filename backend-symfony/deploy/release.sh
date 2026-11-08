#!/usr/bin/env bash
# Ships a committed revision of backend-symfony to the server and installs it.
#   backend-symfony/deploy/release.sh deploy@your-vps [git-ref]     (default ref: HEAD)
# Only committed files are shipped (git archive), so uncommitted work never reaches production.
# The SSH user needs sudo on the server; you may be asked for its password.
set -euo pipefail

HOST="${1:?usage: release.sh user@host [git-ref]}"
REF="${2:-HEAD}"
APP=onechurch-api
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(git -C "$HERE" rev-parse --show-toplevel)"

sha="$(git -C "$REPO" rev-parse --short=12 "$REF^{commit}")"
if [[ "$REF" == HEAD ]] && [[ -n "$(git -C "$REPO" status --porcelain -- backend-symfony)" ]]; then
    echo "Note: backend-symfony has uncommitted changes; they are NOT included in $sha."
fi

id="$(date -u +%Y%m%d%H%M%S)-$sha"
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT
archive="$work/$APP-$id.tar.gz"
git -C "$REPO" archive --format=tar.gz -o "$archive" "$sha:backend-symfony"
git -C "$REPO" show "$sha:backend-symfony/deploy/remote-install.sh" > "$work/remote-install.sh"

remote_dir="/tmp/$APP-release-$id"
echo "Uploading $id to $HOST"
ssh "$HOST" "mkdir -m 0700 '$remote_dir'"
scp -q "$archive" "$work/remote-install.sh" "$HOST:$remote_dir/"
ssh -t "$HOST" "sudo bash '$remote_dir/remote-install.sh' '$remote_dir/$APP-$id.tar.gz'; status=\$?; rm -rf '$remote_dir'; exit \$status"
