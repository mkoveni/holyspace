#!/usr/bin/env bash
set -euo pipefail
if [[ "${1:-}" == -* ]]; then set -- postgres "$@"; fi
if [[ "${1:-}" == postgres && -s "$PGDATA/PG_VERSION" ]]; then
  if [[ ! -f "$PGDATA/.holyspace-runtime" ]] || [[ "$(cat "$PGDATA/.holyspace-runtime")" != alpine-3.24-pg18 ]]; then
    echo 'Existing database uses an unverified runtime. Restore a reviewed backup into a fresh compatible volume; do not switch Debian/Alpine data directories in place.' >&2
    exit 1
  fi
fi
exec /usr/local/bin/docker-entrypoint.sh "$@"
