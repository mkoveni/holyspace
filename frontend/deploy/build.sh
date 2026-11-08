#!/usr/bin/env bash
# Builds the production web release from a frontend source tree and packs it as a release archive.
#   frontend/deploy/build.sh <frontend-source-dir> <output.tar.gz>
# Used by release.sh (on your machine) and by CI, so both ship exactly the same kind of build.
#
# Production talks to the Symfony port of the Java API contract (VITE_BACKEND=java, see .env.java) at its own
# host, holyspace-api.rivalani.dev (cross-origin: the API's CORS_ALLOW_ORIGIN must name the web origin).
# Override with API_URL=https://... for another host.
set -euo pipefail

API_URL="${API_URL:-https://holyspace-api.rivalani.dev}"
[[ "$API_URL" =~ ^https://[A-Za-z0-9.-]+(:[0-9]+)?$ ]] || { echo "API_URL must be an https origin without a path: $API_URL" >&2; exit 2; }

SRC="$(cd "${1:?usage: build.sh <frontend-dir> <output.tar.gz>}" && pwd)"
OUT="${2:?usage: build.sh <frontend-dir> <output.tar.gz>}"

cd "$SRC"
npm ci --no-audit --no-fund
# Values from the environment win over .env files in Vite; pin them so a stray shell variable cannot leak in.
VITE_BACKEND=java VITE_API_BASE_URL="$API_URL" npm run build:java

[[ -f dist/index.html ]] || { echo "The build produced no dist/index.html." >&2; exit 1; }
grep -rqF "$API_URL" dist/assets || { echo "The build does not reference $API_URL." >&2; exit 1; }
# No source maps in production: they would publish the source.
find dist -name '*.map' -delete
# Files and directories only, owned by nobody in particular; remote-install.sh refuses anything else.
tar --owner=0 --group=0 --numeric-owner -czf "$OUT" -C dist .
echo "Built $(du -h "$OUT" | cut -f1) web release for API $API_URL: $OUT"
