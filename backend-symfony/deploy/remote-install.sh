#!/usr/bin/env bash
# Installs one release on the server, as root:
#   sudo bash remote-install.sh /path/to/onechurch-api-<id>.tar.gz     (release.sh, by hand)
#   ... | sudo /usr/local/sbin/onechurch-api-install --stdin <id>      (CI, through ci-receive.sh)
# Steps: unpack into releases/<id>, composer install unless the archive already carries vendor/ (CI builds it),
# compile env, warm cache, migrate, switch `current` atomically, reload PHP-FPM, restart the worker, health check
# (rolls back the switch on failure), keep 5 releases.
set -euo pipefail

APP=onechurch-api
APP_USER=onechurch
BASE=/srv/$APP
KEEP=5

[[ $EUID -eq 0 ]] || { echo "Run as root (sudo)." >&2; exit 1; }
if [[ "${1:-}" == "--stdin" ]]; then
    RELEASE_ID="${2:-}"
    [[ "$RELEASE_ID" =~ ^[0-9]{14}-[0-9a-f]{12}$ ]] || { echo "Invalid release id." >&2; exit 2; }
    ARCHIVE="$(mktemp --suffix=.tar.gz)"
    trap 'rm -f "$ARCHIVE"' EXIT
    # 200 MB is far above a release with vendor/ (about 20 MB); refuse anything larger.
    head -c 200000000 > "$ARCHIVE"
    [[ -s "$ARCHIVE" ]] || { echo "No release archive on stdin." >&2; exit 2; }
else
    ARCHIVE="${1:?usage: remote-install.sh <release.tar.gz> | --stdin <release-id>}"
    RELEASE_ID="$(basename "$ARCHIVE" .tar.gz)"
    RELEASE_ID="${RELEASE_ID#onechurch-api-}"
fi
[[ -f "$BASE/shared/deploy.conf" ]] || { echo "Run setup-server.sh first." >&2; exit 1; }
# shellcheck source=/dev/null
source "$BASE/shared/deploy.conf"
PHP="/usr/bin/php$PHP_VERSION"
# One deploy at a time (CI and a manual release.sh must never interleave).
exec 9>"$BASE/shared/.deploy.lock"
flock -n 9 || { echo "Another deploy is running." >&2; exit 1; }
ENV_FILE="$BASE/shared/.env.local"

log() { printf '\n==> %s\n' "$*"; }
as_app() { sudo -u "$APP_USER" -H env COMPOSER_HOME="$BASE/shared/composer" APP_ENV=prod "$@"; }

log "Checking $ENV_FILE"
for key in APP_SECRET DATABASE_URL DEFAULT_URI CORS_ALLOW_ORIGIN; do
    value="$(grep -E "^$key=" "$ENV_FILE" | tail -n1 | cut -d= -f2- || true)"
    [[ -n "${value//[\"\']/}" ]] || { echo "$key is empty in $ENV_FILE." >&2; exit 1; }
done
if grep -qE 'CHANGE_ME|church\\?\.example\.org' "$ENV_FILE"; then
    echo "$ENV_FILE still contains template values (CHANGE_ME or church.example.org)." >&2
    exit 1
fi
for key in PAYMENTS_TOKEN_KEY PUSH_TOKEN_KEY; do
    grep -qE "^$key=.+" "$ENV_FILE" || echo "Warning: $key is empty; the features that need it stay unavailable."
done

RELEASE="$BASE/releases/$RELEASE_ID"
[[ ! -e "$RELEASE" ]] || { echo "$RELEASE already exists." >&2; exit 1; }
PREVIOUS="$(readlink -f "$BASE/current" 2>/dev/null || true)"

log "Unpacking $RELEASE_ID"
install -d -o "$APP_USER" -g "$APP_USER" -m 0755 "$RELEASE"
# Read as root (the upload directory is private to the SSH user), unpack as the app user.
sudo -u "$APP_USER" tar -xzf - -C "$RELEASE" < "$ARCHIVE"
cd "$RELEASE"
# Development-only files never reach production.
rm -rf tests docs docker compose.yaml Makefile .env.dev .env.test phpunit.dist.xml phpstan.dist.neon deptrac.yaml \
    .php-cs-fixer.dist.php .editorconfig AGENTS.md
sudo -u "$APP_USER" ln -s "$ENV_FILE" .env.local

COMPOSER="$(command -v composer || true)"
if [[ -f vendor/autoload_runtime.php ]]; then
    echo "Using the vendor/ directory built and tested by CI."
else
    log "Installing dependencies"
    [[ -n "$COMPOSER" ]] || { echo "composer is not installed and the archive has no vendor/." >&2; exit 1; }
    as_app "$PHP" "$COMPOSER" install --no-dev --classmap-authoritative --no-interaction --no-progress \
        --working-dir="$RELEASE"
fi
if [[ -n "$COMPOSER" ]]; then
    # Compiles .env files into .env.local.php so nothing is parsed per request.
    as_app "$PHP" "$COMPOSER" dump-env prod --working-dir="$RELEASE"
    # It holds every secret: readable by the app user only.
    chmod 0600 "$RELEASE/.env.local.php"
fi
as_app "$PHP" bin/console cache:clear --no-warmup
as_app "$PHP" bin/console cache:warmup

log "Migrating the database"
# Migrations are additive and backwards compatible (AGENTS.md), so the running release keeps working meanwhile.
as_app "$PHP" bin/console app:migrate --no-interaction

log "Moving database-stored covers into Media (idempotent; one bad image must not stop a deploy)"
as_app "$PHP" bin/console app:media:import-covers --no-interaction || log "WARNING: some covers failed to import; see the output above and re-run app:media:import-covers"

switch_to() {
    ln -sfn "$1" "$BASE/current.next"
    mv -Tf "$BASE/current.next" "$BASE/current"
    systemctl reload "php$PHP_VERSION-fpm"
    systemctl restart "$APP-worker"
}

log "Switching current to $RELEASE_ID"
switch_to "$RELEASE"

log "Health check"
healthy=0
if [[ -f "/etc/letsencrypt/live/$DOMAIN/fullchain.pem" ]]; then
    for _ in 1 2 3 4 5; do
        if curl -fsS --max-time 5 --resolve "$DOMAIN:443:127.0.0.1" "https://$DOMAIN/health/ready" | grep -q '"UP"'; then
            healthy=1; break
        fi
        sleep 2
    done
else
    echo "No certificate for $DOMAIN yet; skipping the HTTPS check (the migration above proved database access)."
    healthy=1
fi
sleep 3
systemctl is-active --quiet "$APP-worker" || { echo "The worker is not running." >&2; healthy=0; }

if [[ $healthy -ne 1 ]]; then
    echo "Release $RELEASE_ID is unhealthy." >&2
    if [[ -n "$PREVIOUS" && -d "$PREVIOUS" ]]; then
        echo "Switching back to $(basename "$PREVIOUS"). Migrations already applied are kept." >&2
        switch_to "$PREVIOUS"
    fi
    echo "Inspect: journalctl -u $APP-worker -n 100; /var/log/$APP/; /var/log/nginx/$APP.error.log" >&2
    exit 1
fi

log "Pruning old releases (keeping $KEEP)"
current="$(readlink -f "$BASE/current")"
ls -1d "$BASE"/releases/*/ | sed 's:/$::' | sort -r | tail -n +$((KEEP + 1)) | while read -r old; do
    [[ "$old" == "$current" ]] || rm -rf "$old"
done

log "Release $RELEASE_ID is live"
