#!/usr/bin/env bash
set -euo pipefail
script_root=$(cd "$(dirname "$0")/../.." && pwd)
export PRODUCTION_COMPOSE_FILE=${PRODUCTION_COMPOSE_FILE:-"$script_root/compose.production.postgresql.yaml"}
export PRODUCTION_ENV_FILE=${PRODUCTION_ENV_FILE:-"$script_root/.env.production.postgresql.local"}
source "$(dirname "$0")/common.sh"
backup_path=$(realpath "${1:?Usage: restore-drill-postgresql.sh BACKUP_FILE AGE_IDENTITY_FILE}")
identity_path=$(realpath "${2:?Provide the age private identity file}")
restore_container_id=''
cleanup() { if [[ -n "$restore_container_id" ]]; then docker rm -f "$restore_container_id" >/dev/null; fi; }
trap cleanup EXIT
restore_password=$(openssl rand -hex 32)
# No published ports, network access, or persistent named volume. Never targets the live DB.
restore_container_id=$(docker run -d --rm --network none -e POSTGRES_PASSWORD="$restore_password" -e POSTGRES_DB=church_portal postgres:18)
ready=0
for attempt in {1..60}; do
  if docker exec "$restore_container_id" pg_isready -U postgres -d church_portal >/dev/null 2>&1; then ready=1; break; fi
  sleep 1
done
[[ "$ready" == 1 ]] || { echo 'Isolated restore database did not become ready.' >&2; exit 1; }
dc run --rm -T --no-deps -v "$identity_path:/run/secrets/backup_identity:ro" tools age --decrypt --identity /run/secrets/backup_identity < "$backup_path" \
  | docker exec -i "$restore_container_id" pg_restore -U postgres -d church_portal --no-owner --no-acl --exit-on-error
docker exec "$restore_container_id" psql -U postgres -d church_portal -v ON_ERROR_STOP=1 -c 'SELECT COUNT(*) AS people FROM people_persons; SELECT COUNT(*) AS audit_entries FROM audit_entries; SELECT COUNT(*) AS pending_outbox FROM integration_outbox WHERE processed_at IS NULL; SELECT COUNT(*) AS image_slots FROM media_images;'
# The restored trigger must continue rejecting tampering.
if docker exec "$restore_container_id" psql -U postgres -d church_portal -v ON_ERROR_STOP=1 -c 'DELETE FROM audit_entries' >/dev/null 2>&1; then
  echo 'Restored audit immutability check failed.' >&2
  exit 1
fi
echo 'Isolated PostgreSQL restore and audit protection checks passed. Compare counts/checksums with the backup log, smoke-test the application, and reapply runtime-role grants before a real recovery.'
