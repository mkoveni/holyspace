#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/common.sh"
recipient=${1:?Usage: backup.sh AGE_PUBLIC_RECIPIENT BACKUP_DIRECTORY}
backup_directory=${2:?Provide a private backup directory}
umask 077
mkdir -p "$backup_directory"
backup_path="$backup_directory/holyspace-java-$(date -u +%Y%m%dT%H%M%SZ)-$RANDOM.dump.age"
trap 'rm -f "$backup_path.partial"' EXIT
# Keep the dump binary and encrypted throughout the pipeline; never create plaintext SQL.
dc exec -T database pg_dump -U holyspace_admin -d holyspace --format=custom --no-owner --no-acl \
  | dc run --rm -T --no-deps --user "$(id -u):$(id -g)" tools age --encrypt --recipient "$recipient" > "$backup_path.partial"
[[ -s "$backup_path.partial" ]]
mv "$backup_path.partial" "$backup_path"
checksum=$(sha256sum "$backup_path" | cut -d ' ' -f 1)
printf '%s  %s\n' "$checksum" "$(basename "$backup_path")" > "$backup_path.sha256"
printf 'Encrypted Java backup created: %s\n' "$backup_path"
