#!/usr/bin/env bash
set -euo pipefail
deployment_stage='loading deployment configuration'
report_deployment_exit() {
  local status=$?
  if (( status != 0 )); then
    printf 'Deployment failed during %s (exit code %s).\n' "$deployment_stage" "$status" >&2
  fi
}
trap report_deployment_exit EXIT
stage() {
  deployment_stage=$1
  printf '\nDeployment: %s\n' "$deployment_stage"
}
source "$(dirname "$0")/common.sh"
stage 'production preflight'
python3 "$production_root/scripts/java-production/preflight.py"
# CI delivers the tested images; local operator deployments can still build them.
if [[ "${JAVA_PRODUCTION_PREBUILT:-false}" == true ]]; then
  for service in "${production_images[@]}"; do
    stage "verifying loaded $service image"
    # Include operations services such as tools when resolving image metadata.
    image=$(dc --profile operations config --format json | python3 -c 'import json,sys;print(json.load(sys.stdin)["services"][sys.argv[1]]["image"])' "$service")
    docker image inspect "$image" >/dev/null
  done
else
  stage 'building production images'
  dc build --pull "${production_images[@]}"
fi
stage 'resolving release configuration'
release_tag=$(dc config --format json | python3 -c 'import json,sys;print(json.load(sys.stdin)["services"]["api"]["image"].rsplit(":",1)[-1])')
stage 'image security scan (first run may download the vulnerability database)'
"$production_root/scripts/java-production/security-scan.sh" "$release_tag" "${JAVA_PRODUCTION_SECURITY_REPORTS:-/tmp/holyspace-production-security}"
stage 'checking database volume compatibility'
database_volume=$(dc config --format json | python3 -c 'import json,sys;print(json.load(sys.stdin)["volumes"]["database"]["name"])')
existing_database=false
if docker volume inspect "$database_volume" >/dev/null 2>&1; then
  existing_database=true
  [[ $# == 2 ]] || { echo 'Existing database volume: supply AGE_PUBLIC_RECIPIENT and BACKUP_DIRECTORY before deployment.' >&2; exit 1; }
  # Refuse incompatible data directories before recreating a running database.
  database_image=$(dc config --format json | python3 -c 'import json,sys;print(json.load(sys.stdin)["services"]["database"]["image"])')
  docker run --rm --network none --memory 128m --memory-swap 128m \
    --mount "type=volume,source=$database_volume,target=/var/lib/postgresql,readonly" \
    "$database_image" postgres --version >/dev/null
fi
stage 'starting database and waiting for readiness'
dc up -d --no-build --pull never --wait --wait-timeout 180 database
if [[ "$existing_database" == true ]]; then
  stage 'encrypted database backup'
  "$production_root/scripts/java-production/backup.sh" "$1" "$2"
fi
# A failed migration leaves maintenance in place. Never auto-downgrade financial/custody schema.
stage 'stopping application for schema migration'
dc stop "${production_services[@]}"
stage 'database migrations'
dc run --rm -T --no-deps migrate
stage 'applying database permissions'
dc exec -T database psql -U holyspace_admin -d holyspace -v ON_ERROR_STOP=1 < "$production_root/docker/java-production/grants.sql"
stage 'starting application and waiting for readiness'
dc up -d --no-build --pull never --wait --wait-timeout 240 "${production_services[@]}"
stage 'application acceptance checks'
"$production_root/scripts/java-production/check.sh"
stage 'completed successfully'
