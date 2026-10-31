#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/common.sh"
python3 "$production_root/scripts/java-production/preflight.py"
# Build before downtime. The tag must refer to the reviewed, verified release.
dc build --pull "${production_images[@]}"
release_tag=$(dc config --format json | python3 -c 'import json,sys;print(json.load(sys.stdin)["services"]["api"]["image"].rsplit(":",1)[-1])')
"$production_root/scripts/java-production/security-scan.sh" "$release_tag" "${JAVA_PRODUCTION_SECURITY_REPORTS:-/tmp/holyspace-production-security}"
database_volume=$(dc config --format json | python3 -c 'import json,sys;print(json.load(sys.stdin)["volumes"]["database"]["name"])')
existing_database=false
if docker volume inspect "$database_volume" >/dev/null 2>&1; then
  existing_database=true
  [[ $# == 2 ]] || { echo 'Existing database volume: supply AGE_PUBLIC_RECIPIENT and BACKUP_DIRECTORY before deployment.' >&2; exit 1; }
fi
dc up -d --wait --wait-timeout 180 database
if [[ "$existing_database" == true ]]; then
  "$production_root/scripts/java-production/backup.sh" "$1" "$2"
fi
# A failed migration leaves maintenance in place. Never auto-downgrade financial/custody schema.
dc stop "${production_services[@]}"
dc run --rm -T --no-deps migrate
dc exec -T database psql -U holyspace_admin -d holyspace -v ON_ERROR_STOP=1 < "$production_root/docker/java-production/grants.sql"
dc up -d --wait --wait-timeout 240 "${production_services[@]}"
"$production_root/scripts/java-production/check.sh"
