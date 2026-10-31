#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/common.sh"
backup_path=$(realpath "${1:?Usage: restore-drill.sh BACKUP_FILE AGE_IDENTITY_FILE}")
identity_path=$(realpath "${2:?Provide the age private identity file}")
image_manifest=${3:-}
restore_container_id=''
cleanup() { if [[ -n "$restore_container_id" ]]; then docker rm -f "$restore_container_id" >/dev/null; fi; }
trap cleanup EXIT
restore_password=$(openssl rand -hex 32)
# No published ports or persistent volume; this command never imports into the live database.
restore_container_id=$(docker run -d --rm --network none -e MYSQL_ROOT_PASSWORD="$restore_password" -e MYSQL_DATABASE=church_portal mysql:8.0)
ready=0
for attempt in {1..60}; do
  if docker exec "$restore_container_id" sh -c 'export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"; mysql --protocol=TCP -h127.0.0.1 -uroot -e "SELECT 1" >/dev/null 2>&1'; then ready=1; break; fi
  sleep 2
done
[[ "$ready" == 1 ]] || { echo 'Isolated restore database did not become ready.' >&2; exit 1; }
dc run --rm -T --no-deps -v "$identity_path:/run/secrets/backup_identity:ro" tools age --decrypt --identity /run/secrets/backup_identity < "$backup_path" \
  | docker exec -i "$restore_container_id" sh -c 'export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"; exec mysql --protocol=TCP -h127.0.0.1 -uroot church_portal'
docker exec "$restore_container_id" sh -c 'export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"; exec mysql --protocol=TCP -h127.0.0.1 -uroot church_portal -e "SELECT COUNT(*) AS people FROM people_persons; SELECT COUNT(*) AS migrations FROM doctrine_migration_versions; SELECT COUNT(*) AS pending_outbox FROM integration_outbox WHERE processed_at IS NULL; SELECT COUNT(*) AS audit_entries FROM workflow_audit; SELECT COUNT(*) AS image_slots FROM media_images;"'
if [[ -n "$image_manifest" ]]; then
  restored_manifest=$(mktemp)
  docker exec "$restore_container_id" sh -c 'export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"; exec mysql --protocol=TCP -h127.0.0.1 -uroot --batch --skip-column-names church_portal -e "SELECT target,owner_id,version,COALESCE(SHA2(content,256),0x72656d6f766564) FROM media_images ORDER BY target,owner_id"' > "$restored_manifest"
  if ! cmp --silent "$image_manifest" "$restored_manifest"; then rm -f "$restored_manifest"; echo 'Restored image checksums differ from the backup manifest.' >&2; exit 1; fi
  rm -f "$restored_manifest"
  echo 'Restored image bytes and slot versions match the backup manifest.'
fi
echo 'Isolated restore and structural checks passed. Container will be removed. Compare record counts with your backup log; an application restore smoke test remains part of the pilot drill.'
