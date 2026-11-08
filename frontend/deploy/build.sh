#!/usr/bin/env bash
# Builds the production web release from a frontend source tree and packs it as a release archive.
#   frontend/deploy/build.sh <frontend-source-dir> <output.tar.gz>
# Used by release.sh (on your machine) and by CI, so both ship exactly the same kind of build.
#
# Production talks to the Symfony port of the Java API contract (VITE_BACKEND=java) on the same origin
# (VITE_API_BASE_URL=/): nginx on the web host sends /api/* to the API's PHP-FPM pool. See .env.java.
set -euo pipefail

SRC="$(cd "${1:?usage: build.sh <frontend-dir> <output.tar.gz>}" && pwd)"
OUT="${2:?usage: build.sh <frontend-dir> <output.tar.gz>}"

cd "$SRC"
npm ci --no-audit --no-fund
# Values from the environment win over .env files in Vite; pin them so a stray shell variable cannot leak in.
VITE_BACKEND=java VITE_API_BASE_URL=/ npm run build:java

[[ -f dist/index.html ]] || { echo "The build produced no dist/index.html." >&2; exit 1; }
# No source maps in production: they would publish the source.
find dist -name '*.map' -delete
# Files and directories only, owned by nobody in particular; remote-install.sh refuses anything else.
tar --owner=0 --group=0 --numeric-owner -czf "$OUT" -C dist .
echo "Built $(du -h "$OUT" | cut -f1) web release: $OUT"
