#!/usr/bin/env bash
# Isolated release rehearsal. Never uses the normal deployment environment or its volumes.
set -euo pipefail
# This rehearsal exercises the complete bundled topology, independently of the operator's target.
export JAVA_PRODUCTION_TARGET=bundled
production_root=$(cd "$(dirname "$0")/../.." && pwd)
verify_directory=$(mktemp -d /tmp/holyspace-java-verify.XXXXXX)
export JAVA_PRODUCTION_ENV_FILE="$verify_directory/environment"
export JAVA_PRODUCTION_PROJECT="holyspace-java-verify-$(date +%s)-$RANDOM"
export JAVA_PRODUCTION_OVERRIDE_FILE="$verify_directory/override.yaml"
source "$production_root/scripts/java-production/common.sh"
paused_database_id=''
cleanup() {
  local result=$?
  if [[ -n "$paused_database_id" ]]; then docker unpause "$paused_database_id" >/dev/null 2>&1 || true; fi
  if (( result != 0 )); then dc logs --no-color --tail 80 api web database edge > /tmp/holyspace-java-verify.log 2>&1 || true; fi
  dc down --volumes --remove-orphans >/dev/null 2>&1 || true
  rm -rf "$verify_directory"
  return "$result"
}
trap cleanup EXIT
umask 077
mkdir "$verify_directory/secrets"
for name in admin_password runtime_password migration_password fixture_password; do
  openssl rand -hex 32 > "$verify_directory/secrets/$name"
done
for name in payments_key push_key; do openssl rand -base64 32 > "$verify_directory/secrets/$name"; done
touch "$verify_directory/secrets/smtp_password" "$verify_directory/secrets/push_access_token"
chmod 640 "$verify_directory"/secrets/*
# Match the API's stable supplementary file group without making secrets world readable.
docker run --rm --network none -v "$verify_directory/secrets:/secrets" alpine:3.22 chgrp -R 10001 /secrets
cat > "$JAVA_PRODUCTION_ENV_FILE" <<ENV
RELEASE_TAG=${JAVA_VERIFY_RELEASE_TAG:-release-check}
CHURCH_DOMAIN=verify.church.invalid
API_DOMAIN=api.verify.church.invalid
SECRETS_DIR=$verify_directory/secrets
PAYMENTS_PUBLIC_URL=https://verify.church.invalid
PAYMENTS_BACKEND_URL=https://api.verify.church.invalid
CORS_ALLOWED_ORIGINS=https://verify.church.invalid
EMAIL_ENABLED=false
PUSH_ENABLED=false
ENV
cat > "$verify_directory/Caddyfile" <<'CADDY'
:8080 {
    reverse_proxy web:8080
}
CADDY
chmod 644 "$verify_directory/Caddyfile"
cat > "$JAVA_PRODUCTION_OVERRIDE_FILE" <<OVERRIDE
services:
  web:
    ports: ['127.0.0.1::8080']
  edge:
    ports: !reset []
    volumes:
      - $verify_directory/Caddyfile:/etc/caddy/Caddyfile:ro
OVERRIDE
# Validate the real topology before adding the isolated local-only rehearsal relay.
JAVA_PRODUCTION_OVERRIDE_FILE= python3 "$production_root/scripts/java-production/preflight.py"
# Prove a private/local public URL is rejected before services can start.
cp "$JAVA_PRODUCTION_ENV_FILE" "$verify_directory/invalid-environment"
printf '\nPAYMENTS_BACKEND_URL=http://localhost:8082\n' >> "$verify_directory/invalid-environment"
if JAVA_PRODUCTION_OVERRIDE_FILE= JAVA_PRODUCTION_ENV_FILE="$verify_directory/invalid-environment" \
  python3 "$production_root/scripts/java-production/preflight.py" >/dev/null 2>&1; then
  echo 'Preflight accepted an insecure public origin.' >&2; exit 1
fi
if [[ "${JAVA_VERIFY_SKIP_BUILD:-0}" != 1 ]]; then dc build --pull api web tools database edge; fi
python3 "$production_root/scripts/java-production/verify-database-readiness.py" \
  --image "holyspace-java-database:${JAVA_VERIFY_RELEASE_TAG:-release-check}"
# The new runtime must refuse an older unmarked cluster without modifying it.
mkdir "$verify_directory/unmarked-database"
printf '18\n' > "$verify_directory/unmarked-database/PG_VERSION"
if docker run --rm --network none -e PGDATA=/test \
  -v "$verify_directory/unmarked-database:/test:ro" \
  "holyspace-java-database:${JAVA_VERIFY_RELEASE_TAG:-release-check}" postgres --version \
  > "$verify_directory/runtime-guard.log" 2>&1; then
  echo 'Database runtime guard accepted an unverified existing cluster.' >&2; exit 1
fi
grep -q 'Existing database uses an unverified runtime' "$verify_directory/runtime-guard.log"
dc up -d --wait --wait-timeout 180 database
dc run --rm -T --no-deps migrate
dc exec -T database psql -U holyspace_admin -d holyspace -v ON_ERROR_STOP=1 < "$production_root/docker/java-production/grants.sql"
# A freshly initialized cluster must remain usable after a normal restart.
dc restart database
dc up -d --wait --wait-timeout 180 database
# Migration mode must be safe to repeat and must not bootstrap the administrator.
dc run --rm -T --no-deps migrate
test "$(dc exec -T database psql -U holyspace_admin -d holyspace -Atc 'SELECT count(*) FROM user_accounts')" = 0
"$production_root/scripts/java-production/bootstrap.sh" release-verification release-verification@church.invalid 'Release Verification' "$verify_directory/secrets/fixture_password"
dc up -d --wait --wait-timeout 240 api web edge
export JAVA_VERIFY_BASE_URL="http://$(dc port web 8080)"
export JAVA_VERIFY_PASSWORD_FILE="$verify_directory/secrets/fixture_password"
python3 "$production_root/scripts/java-production/smoke.py"
browser_image=mcr.microsoft.com/playwright:v1.64.0-noble
if [[ ! -x "$production_root/frontend/node_modules/.bin/playwright" ]]; then
  docker run --rm -v "$production_root/frontend:/app" -w /app "$browser_image" npm ci
fi
docker run --rm --network host --ipc=host -e HOME=/tmp \
  -e "RELEASE_BROWSER_BASE_URL=$JAVA_VERIFY_BASE_URL" \
  -e RELEASE_BROWSER_PASSWORD_FILE=/run/release-password \
  -v "$production_root/frontend:/app" \
  -v "$verify_directory/secrets/fixture_password:/run/release-password:ro" \
  -w /app "$browser_image" npx playwright test --config playwright.production.config.ts --reporter=line
"$production_root/scripts/java-production/check.sh"
# Bootstrap on a running deployment must stop its regular JVM, reject the
# existing account, remove its temporary JVM and restore the healthy API.
if "$production_root/scripts/java-production/bootstrap.sh" release-verification release-verification@church.invalid \
  'Release Verification' "$verify_directory/secrets/fixture_password" > "$verify_directory/repeated-bootstrap.log" 2>&1; then
  echo 'Administrator bootstrap accepted an existing account store.' >&2; exit 1
fi
grep -F 'Restarting the regular API after administrator setup' "$verify_directory/repeated-bootstrap.log" >/dev/null
dc exec -T api curl --fail --silent --max-time 5 http://127.0.0.1:8082/actuator/health/readiness >/dev/null
# Render the actual HTTPS edge configuration as well; this never requests a certificate.
docker run --rm --network none \
  -e CHURCH_DOMAIN=verify.church.invalid -e API_DOMAIN=api.verify.church.invalid \
  -v "$production_root/docker/java-production/Caddyfile:/etc/caddy/Caddyfile:ro" \
  "holyspace-java-edge:${JAVA_VERIFY_RELEASE_TAG:-release-check}" caddy adapt --config /etc/caddy/Caddyfile --adapter caddyfile > "$verify_directory/edge.json"
python3 - "$verify_directory/edge.json" <<'PY'
import json
import sys
limits = []
def inspect(value):
    if isinstance(value, dict):
        if value.get('handler') == 'request_body':
            limits.append(value.get('max_size'))
        for child in value.values():
            inspect(child)
    elif isinstance(value, list):
        for child in value:
            inspect(child)
inspect(json.load(open(sys.argv[1])))
assert limits == [6291456, 6291456], 'Both public origins must enforce the body limit.'
PY
# Block schema changes under the runtime role in the live rehearsal, not only a restored DB.
if dc exec -T database psql -U holyspace_admin -d holyspace -v ON_ERROR_STOP=1 \
  -c 'SET ROLE holyspace_runtime; CREATE TABLE public.forbidden_probe(id integer)' >/dev/null 2>&1; then
  echo 'Runtime role can change schema.' >&2; exit 1
fi
dc run --rm -T --no-deps --user "$(id -u):$(id -g)" tools age-keygen > "$verify_directory/age-identity" 2> "$verify_directory/age-public"
recipient=$(dc run --rm -T --no-deps --user "$(id -u):$(id -g)" -v "$verify_directory/age-identity:/identity:ro" tools age-keygen -y /identity)
"$production_root/scripts/java-production/backup.sh" "$recipient" "$verify_directory/backups"
backup_file=$(find "$verify_directory/backups" -name '*.dump.age' -print -quit)
"$production_root/scripts/java-production/restore-drill.sh" "$backup_file" "$verify_directory/age-identity"
# Liveness survives loss of the DB while readiness must fail. Always unpause on completion.
database_id=$(dc ps -q database)
paused_database_id="$database_id"
docker pause "$database_id" >/dev/null
readiness_failed=false
if ! dc exec -T api curl --fail --silent --max-time 15 http://127.0.0.1:8082/actuator/health/readiness >/dev/null; then readiness_failed=true; fi
dc exec -T api curl --fail --silent --max-time 5 http://127.0.0.1:8082/actuator/health/liveness >/dev/null
docker unpause "$database_id" >/dev/null
paused_database_id=''
[[ "$readiness_failed" == true ]] || { echo 'Readiness did not detect a database outage.' >&2; exit 1; }
echo 'Isolated production rehearsal passed: migration, private roles, login, CORS, recovery and outage probes.'
