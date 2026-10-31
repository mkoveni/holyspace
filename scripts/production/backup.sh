#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/common.sh"
recipient=${1:?Usage: backup.sh AGE_PUBLIC_RECIPIENT BACKUP_DIRECTORY}
backup_directory=${2:?Provide a secure backup directory}
umask 077
mkdir -p "$backup_directory"
backup_path="$backup_directory/church-$(date -u +%Y%m%dT%H%M%SZ)-$RANDOM.sql.age"
trap 'rm -f "$backup_path.partial"' EXIT
# pipefail prevents a failed dump from being reported as a successful backup.
dc exec -T database sh -c 'export MYSQL_PWD="$MYSQL_PASSWORD"; exec mysqldump --user="$MYSQL_USER" --single-transaction --hex-blob --set-gtid-purged=OFF --no-tablespaces "$MYSQL_DATABASE"' \
  | dc run --rm -T --no-deps tools age --encrypt --recipient "$recipient" > "$backup_path.partial"
[[ -s "$backup_path.partial" ]]
mv "$backup_path.partial" "$backup_path"
printf 'Encrypted backup created: %s\n' "$backup_path"
