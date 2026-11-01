#!/usr/bin/env bash
set -euo pipefail
if [[ -n "${POSTGRES_PASSWORD_FILE:-}" ]]; then
  PGPASSWORD="$(cat "$POSTGRES_PASSWORD_FILE")"
  export PGPASSWORD
elif [[ -n "${POSTGRES_PASSWORD:-}" ]]; then
  export PGPASSWORD="$POSTGRES_PASSWORD"
fi
export PGCONNECT_TIMEOUT=3
# Initialization's temporary server accepts only Unix sockets. TCP plus a real
# query proves the final server has finished initialization and this DB exists.
psql --host=127.0.0.1 --port="${PGPORT:-5432}" \
  --username="${POSTGRES_USER:-postgres}" --dbname="${POSTGRES_DB:-${POSTGRES_USER:-postgres}}" \
  --no-password --no-psqlrc --set=ON_ERROR_STOP=1 --tuples-only --no-align \
  --command='SELECT 1' >/dev/null
