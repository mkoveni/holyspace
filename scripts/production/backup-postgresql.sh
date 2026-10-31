#!/usr/bin/env bash
set -euo pipefail
script_root=$(cd "$(dirname "$0")/../.." && pwd)
export PRODUCTION_COMPOSE_FILE=${PRODUCTION_COMPOSE_FILE:-"$script_root/compose.production.postgresql.yaml"}
export PRODUCTION_ENV_FILE=${PRODUCTION_ENV_FILE:-"$script_root/.env.production.postgresql.local"}
source "$(dirname "$0")/common.sh"
recipient=${1:?Usage: backup-postgresql.sh AGE_PUBLIC_RECIPIENT BACKUP_DIRECTORY}
backup_directory=${2:?Provide a secure backup directory}
umask 077
mkdir -p "$backup_directory"
backup_path="$backup_directory/church-postgresql-$(date -u +%Y%m%dT%H%M%SZ)-$RANDOM.dump.age"
trap 'rm -f "$backup_path.partial"' EXIT
dc exec -T database sh -c 'export PGPASSWORD="$POSTGRES_PASSWORD"; exec pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" --format=custom --no-owner --no-acl' \
  | dc run --rm -T --no-deps tools age --encrypt --recipient "$recipient" > "$backup_path.partial"
[[ -s "$backup_path.partial" ]]
mv "$backup_path.partial" "$backup_path"
printf 'Encrypted PostgreSQL backup created: %s\n' "$backup_path"
