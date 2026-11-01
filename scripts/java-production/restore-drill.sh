#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/common.sh"
backup_path=$(realpath "${1:?Usage: restore-drill.sh BACKUP_FILE AGE_IDENTITY_FILE}")
identity_path=$(realpath "${2:?Provide a private age identity file}")
restore_id=''
cleanup() { if [[ -n "$restore_id" ]]; then docker rm -f "$restore_id" >/dev/null; fi; }
trap cleanup EXIT
if [[ -f "$backup_path.sha256" ]]; then (cd "$(dirname "$backup_path")" && sha256sum --check --status "$(basename "$backup_path").sha256"); fi
database_image=$(dc config --format json | python3 -c 'import json,sys;print(json.load(sys.stdin)["services"]["database"]["image"])')
restore_id=$(docker run -d --rm --network none -e POSTGRES_PASSWORD="$(openssl rand -hex 32)" -e POSTGRES_DB=holyspace "$database_image")
ready=0
for ((attempt = 0; attempt < 60; attempt++)); do
  if docker exec "$restore_id" pg_isready -U postgres -d holyspace >/dev/null 2>&1; then ready=1; break; fi
  sleep 1
done
[[ "$ready" == 1 ]] || { echo 'Isolated restore database did not become ready.' >&2; exit 1; }
docker exec -i "$restore_id" psql -U postgres -d holyspace -v ON_ERROR_STOP=1 <<'SQL'
CREATE ROLE holyspace_runtime NOLOGIN;
CREATE ROLE holyspace_migrator NOLOGIN;
GRANT CREATE ON DATABASE holyspace TO holyspace_migrator;
ALTER SCHEMA public OWNER TO holyspace_migrator;
SQL
dc run --rm -T --no-deps --user "$(id -u):$(id -g)" -v "$identity_path:/run/secrets/backup_identity:ro" tools \
  age --decrypt --identity /run/secrets/backup_identity < "$backup_path" \
  | docker exec -i "$restore_id" pg_restore -U postgres -d holyspace --role=holyspace_migrator --no-owner --no-acl --exit-on-error
# Restore the runtime grants, then prove schema/history cannot be changed by the API role.
docker exec -i "$restore_id" psql -U postgres -d holyspace -v ON_ERROR_STOP=1 < "$production_root/docker/java-production/grants.sql"
docker exec -i "$restore_id" psql -U postgres -d holyspace -v ON_ERROR_STOP=1 <<'SQL'
SELECT version, success FROM flyway_schema_history ORDER BY installed_rank DESC LIMIT 1;
SELECT count(*) AS people FROM people_persons;
SELECT count(*) AS gifts FROM giving_gifts;
SELECT count(*) AS push_jobs FROM communication_push_deliveries;
SELECT count(*) AS identity_audit FROM identity_access_audit;
SET ROLE holyspace_runtime;
SELECT count(*) AS readable_accounts FROM user_accounts;
SQL
if docker exec "$restore_id" psql -U postgres -d holyspace -v ON_ERROR_STOP=1 -c 'SET ROLE holyspace_runtime; CREATE TABLE public.forbidden_probe(id integer)' >/dev/null 2>&1; then
  echo 'Runtime schema isolation check failed.' >&2; exit 1
fi
if docker exec "$restore_id" psql -U postgres -d holyspace -v ON_ERROR_STOP=1 -c 'SET ROLE holyspace_runtime; DELETE FROM flyway_schema_history' >/dev/null 2>&1; then
  echo 'Runtime migration-history protection check failed.' >&2; exit 1
fi
echo 'Isolated Java database restore and role checks passed. Full application/provider recovery remains an operator acceptance step.'
