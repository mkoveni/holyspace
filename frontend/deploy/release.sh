#!/usr/bin/env bash
# Builds a committed revision of the frontend on this machine and installs it on the server.
#   frontend/deploy/release.sh you@your-vps [git-ref]     (default ref: HEAD)
# Only committed files are built (git archive), so uncommitted work never reaches production.
# Needs Node (see frontend/package.json "engines") locally. The SSH user needs sudo on the server.
set -euo pipefail

HOST="${1:?usage: release.sh user@host [git-ref]}"
REF="${2:-HEAD}"
APP=onechurch-web
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(git -C "$HERE" rev-parse --show-toplevel)"

sha="$(git -C "$REPO" rev-parse --short=12 "$REF^{commit}")"
if [[ "$REF" == HEAD ]] && [[ -n "$(git -C "$REPO" status --porcelain -- frontend)" ]]; then
    echo "Note: frontend has uncommitted changes; they are NOT included in $sha."
fi

id="$(date -u +%Y%m%d%H%M%S)-$sha"
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT
mkdir "$work/src"
# Run from the repository root: "<sha>:frontend" is the frontend tree of that commit.
git -C "$REPO" archive "$sha:frontend" | tar -x -C "$work/src"
git -C "$REPO" show "$sha:frontend/deploy/remote-install.sh" > "$work/remote-install.sh"

echo "==> Testing and building $id"
(cd "$work/src" && npm ci --no-audit --no-fund && npm test)
bash "$work/src/deploy/build.sh" "$work/src" "$work/$APP-$id.tar.gz"

remote_dir="/tmp/$APP-release-$id"
echo "==> Uploading $id to $HOST"
ssh "$HOST" "mkdir -m 0700 '$remote_dir'"
scp -q "$work/$APP-$id.tar.gz" "$work/remote-install.sh" "$HOST:$remote_dir/"
ssh -t "$HOST" "sudo bash '$remote_dir/remote-install.sh' '$remote_dir/$APP-$id.tar.gz'; status=\$?; rm -rf '$remote_dir'; exit \$status"
