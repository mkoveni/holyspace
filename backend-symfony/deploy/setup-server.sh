#!/usr/bin/env bash
# One-time (re-runnable) preparation of an Ubuntu VPS that already runs nginx, for the Symfony API.
# Run on the server as root:  sudo bash setup-server.sh --domain api.church.example.org [options]
#
#   --domain NAME          Public API host name (required).
#   --php VERSION          Installed PHP version to use (default 8.4; must be >= 8.4.1). Not installed by this script.
#   --create-database      On the PostgreSQL already running on this host (not installed by this script), create
#                          the onechurch role and database if missing and write DATABASE_URL. Alias: --with-postgres.
#   --certbot-email EMAIL  Obtain a Let's Encrypt certificate for --domain with the webroot method.
#   --ufw                  Allow OpenSSH and Nginx Full in ufw and enable it.
#   --ci-key "ssh-ed25519 ..."  Public key GitHub Actions deploys with. Creates the onechurch-deploy user, whose key
#                          can only run deploy/ci-receive.sh (no shell, no forwarding). bootstrap-ci.sh passes it.
#
# PHP, its extensions and Composer must already be installed; the script checks them and stops if one is missing.
# It never removes data, other nginx sites, databases or existing secrets. See docs/deployment.md.
set -euo pipefail

APP=onechurch-api
APP_USER=onechurch
BASE=/srv/$APP
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

DOMAIN=
PHP_VERSION=8.4
WITH_POSTGRES=0
CERTBOT_EMAIL=
UFW=0
CI_KEY=

# Accept --option=value as well as --option value.
args=()
for arg in "$@"; do
    if [[ "$arg" == --*=* ]]; then args+=("${arg%%=*}" "${arg#*=}"); else args+=("$arg"); fi
done
set -- "${args[@]}"

while [[ $# -gt 0 ]]; do
    case "$1" in
        --domain) DOMAIN="$2"; shift 2 ;;
        --php) PHP_VERSION="$2"; shift 2 ;;
        --create-database|--with-postgres) WITH_POSTGRES=1; shift ;;
        --certbot-email) CERTBOT_EMAIL="$2"; shift 2 ;;
        --ufw) UFW=1; shift ;;
        --ci-key) CI_KEY="$2"; shift 2 ;;
        *) echo "Unknown option: $1" >&2; exit 2 ;;
    esac
done

[[ $EUID -eq 0 ]] || { echo "Run as root (sudo)." >&2; exit 1; }
[[ -n "$DOMAIN" ]] || { echo "--domain is required." >&2; exit 2; }
[[ "$DOMAIN" =~ ^[A-Za-z0-9.-]+$ ]] || { echo "Invalid domain." >&2; exit 2; }
if [[ -n "$CI_KEY" ]]; then
    [[ "$CI_KEY" =~ ^ssh-ed25519\ [A-Za-z0-9+/=]+(\ [A-Za-z0-9@._-]+)?$ ]] || { echo "--ci-key must be one ssh-ed25519 public key." >&2; exit 2; }
fi
command -v nginx >/dev/null || { echo "nginx is not installed." >&2; exit 1; }

log() { printf '\n==> %s\n' "$*"; }

export DEBIAN_FRONTEND=noninteractive

# PHP and its extensions are managed on the host, not by this script: it only checks them.
log "Checking PHP $PHP_VERSION (setup-server.sh $(sha256sum "${BASH_SOURCE[0]}" | cut -c1-12))"
PHP_BIN="/usr/bin/php$PHP_VERSION"
missing=()
[[ -x "$PHP_BIN" ]] || missing+=("$PHP_BIN")
command -v "php-fpm$PHP_VERSION" >/dev/null || missing+=("php$PHP_VERSION-fpm")
if [[ -x "$PHP_BIN" ]]; then
    "$PHP_BIN" -r 'exit(PHP_VERSION_ID >= 80401 ? 0 : 1);' || missing+=("PHP >= 8.4.1 (found $("$PHP_BIN" -r 'echo PHP_VERSION;'))")
    # Ask PHP itself (no php -m | grep pipeline, whose early exit raced under pipefail and gave random results).
    check_extensions() {
        "$PHP_BIN" -r 'foreach (array_slice($argv, 1) as $e) { if (!extension_loaded($e)) { echo $e, "\n"; } }' -- "$@"
    }
    while IFS= read -r ext; do
        case "$ext" in
            pdo_pgsql) package="php$PHP_VERSION-pgsql" ;;
            mbstring) package="php$PHP_VERSION-mbstring" ;;
            *) package="php$PHP_VERSION-common" ;;
        esac
        [[ -n "$ext" ]] && missing+=("extension $ext ($package)")
    done < <(check_extensions pdo_pgsql mbstring ctype iconv openssl)
    while IFS= read -r ext; do
        [[ -n "$ext" ]] && echo "Recommended but not loaded: $ext"
    done < <(check_extensions "Zend OPcache" intl curl)
fi
command -v composer >/dev/null || missing+=("composer (https://getcomposer.org/download/)")
if [[ ${#missing[@]} -gt 0 ]]; then
    echo "Missing on this host:" >&2
    printf '  - %s\n' "${missing[@]}" >&2
    exit 1
fi
echo "PHP $("$PHP_BIN" -r 'echo PHP_VERSION;'), extensions and Composer are present."

log "Creating user $APP_USER and $BASE"
id "$APP_USER" >/dev/null 2>&1 || useradd --system --home-dir "$BASE" --shell /usr/sbin/nologin "$APP_USER"
install -d -o "$APP_USER" -g "$APP_USER" -m 0750 "$BASE" "$BASE/releases" "$BASE/shared" \
    "$BASE/shared/var" "$BASE/shared/var/share" "$BASE/shared/var/lock" "$BASE/shared/composer"
install -d -o "$APP_USER" -g "$APP_USER" -m 0750 /var/log/$APP
# nginx resolves $realpath_root through these directories.
chmod 0751 "$BASE" "$BASE/releases"

cat > "$BASE/shared/deploy.conf" <<EOF
PHP_VERSION=$PHP_VERSION
DOMAIN=$DOMAIN
EOF
chmod 0644 "$BASE/shared/deploy.conf"

ENV_FILE="$BASE/shared/.env.local"
if [[ ! -f "$ENV_FILE" ]]; then
    log "Creating $ENV_FILE from the template"
    install -o "$APP_USER" -g "$APP_USER" -m 0640 "$HERE/env.prod.example" "$ENV_FILE"
    sed -i "s|^APP_SECRET=.*|APP_SECRET=$(openssl rand -hex 32)|" "$ENV_FILE"
    sed -i "s|api\.church\.example\.org|$DOMAIN|g" "$ENV_FILE"
    CREATED_ENV=1
else
    echo "Keeping existing $ENV_FILE."
    CREATED_ENV=0
fi

if [[ $WITH_POSTGRES -eq 1 ]]; then
    log "Creating the database on the local PostgreSQL"
    command -v psql >/dev/null || { echo "psql not found: PostgreSQL must already be installed." >&2; exit 1; }
    pg() { sudo -u postgres psql -v ON_ERROR_STOP=1 -qtA "$@"; }
    pg -c 'SELECT 1' >/dev/null || { echo "PostgreSQL is not running or not reachable as the postgres user." >&2; exit 1; }
    pg_major="$(pg -c 'SHOW server_version_num' | cut -c1-2)"
    [[ "$pg_major" -ge 13 ]] || { echo "PostgreSQL $pg_major is too old (13+ needed for trusted extensions)." >&2; exit 1; }
    if [[ "$(pg -c "SELECT 1 FROM pg_roles WHERE rolname='onechurch'")" != 1 ]]; then
        db_password="$(openssl rand -hex 24)"
        # Not a superuser: it owns its database and creates the trusted extensions the migrations need.
        pg -c "CREATE ROLE onechurch LOGIN PASSWORD '$db_password'"
        url="postgresql://onechurch:$db_password@127.0.0.1:5432/onechurch?serverVersion=$pg_major\&charset=utf8"
        sed -i "s|^DATABASE_URL=.*|DATABASE_URL=\"$url\"|" "$ENV_FILE"
        echo "Created role 'onechurch'; DATABASE_URL written to $ENV_FILE."
    else
        echo "Role 'onechurch' exists; leaving DATABASE_URL in $ENV_FILE as it is."
    fi
    if [[ "$(pg -c "SELECT 1 FROM pg_database WHERE datname='onechurch'")" != 1 ]]; then
        pg -c "CREATE DATABASE onechurch OWNER onechurch ENCODING 'UTF8' TEMPLATE template0"
        pg -d onechurch -c "REVOKE ALL ON SCHEMA public FROM PUBLIC" -c "ALTER SCHEMA public OWNER TO onechurch"
        echo "Created database 'onechurch' (PostgreSQL $pg_major)."
    else
        echo "Database 'onechurch' exists; left untouched."
    fi
fi

log "Configuring PHP-FPM pool"
install -m 0644 "$HERE/php/99-onechurch.ini" "/etc/php/$PHP_VERSION/fpm/conf.d/99-onechurch.ini"
install -m 0644 "$HERE/php/99-onechurch.ini" "/etc/php/$PHP_VERSION/cli/conf.d/99-onechurch.ini"
install -m 0644 "$HERE/php/fpm-pool.conf" "/etc/php/$PHP_VERSION/fpm/pool.d/$APP.conf"
"php-fpm$PHP_VERSION" -t
systemctl enable "php$PHP_VERSION-fpm"
systemctl restart "php$PHP_VERSION-fpm"

log "Installing the scheduler worker unit"
sed "s|/usr/bin/php |/usr/bin/php$PHP_VERSION |" "$HERE/systemd/$APP-worker.service" > "/etc/systemd/system/$APP-worker.service"
systemctl daemon-reload
systemctl enable "$APP-worker"   # started by the first deploy

cat > /etc/logrotate.d/$APP <<EOF
/var/log/$APP/*.log {
    weekly
    rotate 8
    compress
    delaycompress
    missingok
    notifempty
    copytruncate
}
EOF

log "Installing deploy commands in /usr/local/sbin"
# Root-owned copies: CI can trigger them but never change them. Re-run this script after they change in the repo.
install -o root -g root -m 0755 "$HERE/remote-install.sh" /usr/local/sbin/$APP-install
install -o root -g root -m 0755 "$HERE/ci-receive.sh" /usr/local/sbin/$APP-receive
install -o root -g root -m 0755 "$HERE/rollback.sh" /usr/local/sbin/$APP-rollback

if [[ -n "$CI_KEY" ]]; then
    log "Configuring the CI deploy user"
    DEPLOY_USER=onechurch-deploy
    DEPLOY_HOME=/var/lib/$DEPLOY_USER
    id "$DEPLOY_USER" >/dev/null 2>&1 || useradd --system --create-home --home-dir "$DEPLOY_HOME" --shell /bin/bash "$DEPLOY_USER"
    # No usable password, but not "locked": with UsePAM no, sshd refuses even key logins for a "!" (locked) account.
    usermod -p '*' "$DEPLOY_USER"
    chown "$DEPLOY_USER:$DEPLOY_USER" "$DEPLOY_HOME"
    chmod 0750 "$DEPLOY_HOME"   # StrictModes rejects keys under a group- or world-writable home
    key_line="$(printf 'restrict,command="/usr/local/sbin/%s-receive" %s' "$APP" "$CI_KEY")"

    # Ask sshd what it will really do for this user (Match blocks included) instead of assuming the defaults.
    sshd_bin="$(command -v sshd || echo /usr/sbin/sshd)"
    effective="$("$sshd_bin" -T -C "user=$DEPLOY_USER,host=ci,addr=192.0.2.1" 2>/dev/null || true)"
    setting() { awk -v k="$1" 'tolower($1) == k { $1 = ""; sub(/^ /, ""); print }' <<< "$effective"; }
    problems=()
    if [[ -z "$effective" ]]; then
        echo "Warning: could not read the sshd configuration; writing ~/.ssh/authorized_keys only."
        key_files=".ssh/authorized_keys"
    else
        key_files="$(setting authorizedkeysfile)"
        [[ "$(setting pubkeyauthentication)" == yes ]] || problems+=("PubkeyAuthentication is not 'yes' for $DEPLOY_USER")
        allow_users="$(setting allowusers)"
        if [[ -n "$allow_users" ]] && ! tr ' ' '\n' <<< "$allow_users" | grep -qE "^$DEPLOY_USER(@.*)?$"; then
            problems+=("AllowUsers is set ($allow_users) and does not include $DEPLOY_USER")
        fi
        allow_groups="$(setting allowgroups)"
        if [[ -n "$allow_groups" ]]; then
            in_group=0
            for g in $allow_groups; do id -nG "$DEPLOY_USER" | tr ' ' '\n' | grep -qxF "$g" && in_group=1; done
            if [[ $in_group -eq 0 ]]; then
                first_group="${allow_groups%% *}"
                if getent group "$first_group" >/dev/null; then
                    usermod -aG "$first_group" "$DEPLOY_USER"
                    echo "Added $DEPLOY_USER to group '$first_group' (sshd AllowGroups)."
                else
                    problems+=("AllowGroups is set ($allow_groups) and $DEPLOY_USER is in none of them")
                fi
            fi
        fi
        deny="$(setting denyusers) $(setting denygroups)"
        [[ "$deny" != *"$DEPLOY_USER"* ]] || problems+=("DenyUsers/DenyGroups names $DEPLOY_USER")
        if [[ "$(setting authorizedkeyscommand)" != "" && "$(setting authorizedkeyscommand)" != none ]]; then
            echo "Note: sshd uses an AuthorizedKeysCommand; the files below are still written."
        fi
    fi
    [[ -n "$key_files" && "$key_files" != none ]] || key_files=".ssh/authorized_keys"

    # Write the key to every location sshd reads for this user (%h home, %u user, %% percent; relative = under home).
    for pattern in $key_files; do
        path="${pattern//%%/$'\x01'}"
        path="${path//%h/$DEPLOY_HOME}"
        path="${path//%u/$DEPLOY_USER}"
        path="${path//%U/$(id -u "$DEPLOY_USER")}"
        path="${path//$'\x01'/%}"
        [[ "$path" == /* ]] || path="$DEPLOY_HOME/$path"
        dir="$(dirname "$path")"
        if [[ "$dir" == "$DEPLOY_HOME"* ]]; then
            install -d -o "$DEPLOY_USER" -g "$DEPLOY_USER" -m 0700 "$dir"
            printf '%s\n' "$key_line" > "$path"
            chown "$DEPLOY_USER:$DEPLOY_USER" "$path"
        else
            # A central directory such as /etc/ssh/authorized_keys/%u: root-owned, readable by sshd.
            install -d -m 0755 "$dir"
            printf '%s\n' "$key_line" > "$path"
            chown root:root "$path"
        fi
        chmod 0600 "$path"
        [[ "$dir" == "$DEPLOY_HOME"* ]] || chmod 0644 "$path"
        echo "CI key written to $path"
    done

    sudoers="$(mktemp)"
    printf '%s ALL=(root) NOPASSWD: /usr/local/sbin/%s-install --stdin *\n' "$DEPLOY_USER" "$APP" > "$sudoers"
    visudo -cf "$sudoers" >/dev/null
    install -o root -g root -m 0440 "$sudoers" /etc/sudoers.d/$APP-deploy
    rm -f "$sudoers"

    if [[ ${#problems[@]} -gt 0 ]]; then
        echo "The sshd configuration will refuse the CI key:" >&2
        printf '  - %s\n' "${problems[@]}" >&2
        echo "Fix it in /etc/ssh/sshd_config (or sshd_config.d/), run 'sudo sshd -t && sudo systemctl reload ssh', then re-run." >&2
        echo "This script does not edit sshd_config: a mistake there can lock you out." >&2
        exit 1
    fi
    echo "sshd will accept the CI key for $DEPLOY_USER."
fi

log "Configuring nginx"
install -d -m 0755 /var/www/letsencrypt
install -m 0644 "$HERE/nginx/$APP-fastcgi.conf" "/etc/nginx/snippets/$APP-fastcgi.conf"
SITE=/etc/nginx/sites-available/$APP
CERT=/etc/letsencrypt/live/$DOMAIN/fullchain.pem
# Replace whichever domain the template names (the example one, or a real one already edited in).
TEMPLATE_DOMAIN="$(awk '$1 == "server_name" { sub(";", "", $2); print $2; exit }' "$HERE/nginx/$APP.conf")"
# `http2 on;` needs nginx 1.25.1+; older nginx (Ubuntu 24.04 ships 1.24) takes it on the listen line instead.
NGINX_VERSION="$(nginx -v 2>&1 | sed -n 's|.*nginx/\([0-9.]*\).*|\1|p')"
if [[ "$(printf '%s\n1.25.1\n' "$NGINX_VERSION" | sort -V | head -n1)" == 1.25.1 ]]; then
    HTTP2_FILTER=(cat)
else
    echo "nginx $NGINX_VERSION: using 'listen 443 ssl http2' instead of 'http2 on'."
    HTTP2_FILTER=(sed -e '/^[[:space:]]*http2 on;/d' -e 's|listen \(.*443\) ssl;|listen \1 ssl http2;|')
fi
write_full_site() { sed "s|${TEMPLATE_DOMAIN//./\\.}|$DOMAIN|g" "$HERE/nginx/$APP.conf" | "${HTTP2_FILTER[@]}" > "$SITE"; }
write_http_site() {
    # The port-80 server only, so ACME challenges work before a certificate exists.
    write_full_site
    awk '/^server \{/{n++} n<2' "$SITE" > "$SITE.tmp" && mv "$SITE.tmp" "$SITE"
}
if [[ -f "$CERT" ]]; then write_full_site; else write_http_site; fi
ln -sfn "$SITE" "/etc/nginx/sites-enabled/$APP"
nginx -t
systemctl reload nginx

if [[ ! -f "$CERT" && -n "$CERTBOT_EMAIL" ]]; then
    log "Obtaining a certificate for $DOMAIN"
    apt-get install -y -q certbot
    certbot certonly --webroot -w /var/www/letsencrypt -d "$DOMAIN" \
        --email "$CERTBOT_EMAIL" --agree-tos --no-eff-email --non-interactive
    install -d /etc/letsencrypt/renewal-hooks/deploy
    printf '#!/bin/sh\nnginx -t && systemctl reload nginx\n' > /etc/letsencrypt/renewal-hooks/deploy/reload-nginx
    chmod 0755 /etc/letsencrypt/renewal-hooks/deploy/reload-nginx
    write_full_site
    nginx -t
    systemctl reload nginx
fi

if [[ $UFW -eq 1 ]]; then
    log "Configuring ufw"
    apt-get install -y -q ufw
    ufw allow OpenSSH
    ufw allow 'Nginx Full'
    ufw --force enable
fi

log "Done"
[[ -f "$CERT" ]] || echo "No certificate yet: nginx serves HTTP only. Re-run with --certbot-email to enable HTTPS."
if [[ $CREATED_ENV -eq 1 ]]; then
    echo "Next: edit $ENV_FILE (DATABASE_URL, CORS_ALLOW_ORIGIN, PAYMENTS_TOKEN_KEY, PUSH_TOKEN_KEY, mail), then deploy."
fi
