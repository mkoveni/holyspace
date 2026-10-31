#!/usr/bin/env bash
# Boot an isolated production-shaped deployment, test it, then remove only its own resources.
set -euo pipefail
verify_dir=$(mktemp -d)
verify_project="holyspace-verify-$(date +%s)-$RANDOM"
export PRODUCTION_ENV_FILE="$verify_dir/environment"
export PRODUCTION_OVERRIDE_FILE="$verify_dir/override.yaml"
export PRODUCTION_PROJECT_NAME="$verify_project"
source "$(dirname "$0")/common.sh"
cleanup() {
  dc down --volumes --remove-orphans >/dev/null 2>&1 || true
  rm -rf "$verify_dir"
}
trap cleanup EXIT
openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:2048 -aes-256-cbc -pass pass:ci-only -out "$verify_dir/private.pem" 2>/dev/null
openssl pkey -in "$verify_dir/private.pem" -passin pass:ci-only -pubout -out "$verify_dir/public.pem" 2>/dev/null
# Disposable test keys must be readable by the unprivileged PHP container.
chmod 644 "$verify_dir/private.pem" "$verify_dir/public.pem"
cat > "$PRODUCTION_ENV_FILE" <<ENV
CHURCH_DOMAIN=church.example.test
APP_SECRET=isolated-production-verification-only
DATABASE_PASSWORD=isolated-test-password
DATABASE_PASSWORD_URLENCODED=isolated-test-password
DATABASE_ROOT_PASSWORD=isolated-root-password
JWT_PASSPHRASE=ci-only
JWT_PRIVATE_KEY_FILE=$verify_dir/private.pem
JWT_PUBLIC_KEY_FILE=$verify_dir/public.pem
RELEASE_TAG=${VERIFY_RELEASE_TAG:-readiness}
ENV
cat > "$PRODUCTION_OVERRIDE_FILE" <<YAML
services:
  database:
    environment:
      MYSQL_DATABASE: church_ci
  php:
    environment:
      DATABASE_URL: mysql://church:isolated-test-password@database:3306/church_ci?serverVersion=8.0
    volumes:
      - $production_root/backend/tests/Support/seed-browser.php:/tmp/browser-seed.php:ro
  outbox:
    environment:
      DATABASE_URL: mysql://church:isolated-test-password@database:3306/church_ci?serverVersion=8.0
  communications:
    environment:
      DATABASE_URL: mysql://church:isolated-test-password@database:3306/church_ci?serverVersion=8.0
  followups:
    environment:
      DATABASE_URL: mysql://church:isolated-test-password@database:3306/church_ci?serverVersion=8.0
  payments:
    environment:
      DATABASE_URL: mysql://church:isolated-test-password@database:3306/church_ci?serverVersion=8.0
  web:
    ports: ['127.0.0.1:${VERIFY_PORT:-5181}:80']
YAML
dc build php web tools
dc up -d --wait database
dc run --rm -T --no-deps php php bin/console doctrine:migrations:migrate --no-interaction
dc up -d --wait --wait-timeout 120 php web outbox communications followups payments
dc exec -T -e BROWSER_TEST_FIXTURE=1 -e BROWSER_TEST_PROJECT_DIR=/var/www/html php php /tmp/browser-seed.php
dc exec -T web nginx -t
ready=0
for attempt in {1..30}; do
  if curl --fail --silent "http://127.0.0.1:${VERIFY_PORT:-5181}/health/ready" >/dev/null; then ready=1; break; fi
  sleep 2
done
[[ "$ready" == 1 ]] || { dc logs --tail 40 php web; exit 1; }
export PLAYWRIGHT_BASE_URL="http://127.0.0.1:${VERIFY_PORT:-5181}"
if [[ "${BROWSER_RUNNER:-docker}" == host ]]; then
  (cd "$production_root/frontend" && npm run test:e2e)
else
  browser_version=$(node -p "require('$production_root/frontend/node_modules/@playwright/test/package.json').version")
  docker run --rm --network host --ipc host -e CI=1 -e PLAYWRIGHT_BASE_URL \
    --user "$(id -u):$(id -g)" -v "$production_root/frontend:/app" -w /app \
    "mcr.microsoft.com/playwright:v${browser_version}-noble" npx playwright test
fi
PRODUCTION_CHECK_SERVICES="database php web outbox communications followups payments" "$production_root/scripts/production/check.sh"
dc run --rm -T --no-deps tools age-keygen > "$verify_dir/backup-key"
recipient=$(dc run --rm -T --no-deps tools age-keygen -y < "$verify_dir/backup-key")
# Record a manifest without image bytes; the isolated fixture has no concurrent image writers.
dc exec -T database sh -c 'export MYSQL_PWD="$MYSQL_PASSWORD"; exec mysql --user="$MYSQL_USER" --batch --skip-column-names "$MYSQL_DATABASE" -e "SELECT target,owner_id,version,COALESCE(SHA2(content,256),0x72656d6f766564) FROM media_images ORDER BY target,owner_id"' > "$verify_dir/image-manifest"
"$production_root/scripts/production/backup.sh" "$recipient" "$verify_dir/backups"
backup_file=$(find "$verify_dir/backups" -name '*.age' -type f -print -quit)
"$production_root/scripts/production/restore-drill.sh" "$backup_file" "$verify_dir/backup-key" "$verify_dir/image-manifest"
echo 'Production-shaped deployment, browser journeys, monitoring, and encrypted restore verification passed.'
