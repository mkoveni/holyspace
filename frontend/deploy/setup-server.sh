#!/usr/bin/env bash
# One-time (re-runnable) preparation of the VPS for the web app, next to the Symfony API on the same nginx.
# Run on the server as root:  sudo bash setup-server.sh [options]
#
#   --domain NAME          Public web host name (default holyspace.rivalani.dev).
#   --certbot-email EMAIL  Obtain a Let's Encrypt certificate for --domain with the webroot method.
#   --ci-key "ssh-ed25519 ..."  Public key GitHub Actions deploys with. Creates the onechurch-web-deploy user, whose
#                          key can only run deploy/ci-receive.sh (no shell, no forwarding). bootstrap-ci.sh passes it.
#
# The API must be set up first (backend-symfony/deploy/setup-server.sh): this site sends /api/* to its PHP-FPM pool
# through the fastcgi snippet that script installs. Nothing here touches the API, other sites or existing releases.
set -euo pipefail

APP=onechurch-web
BASE=/srv/$APP
API_SNIPPET=/etc/nginx/snippets/onechurch-api-fastcgi.conf
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

DOMAIN=holyspace.rivalani.dev
CERTBOT_EMAIL=
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
        --certbot-email) CERTBOT_EMAIL="$2"; shift 2 ;;
        --ci-key) CI_KEY="$2"; shift 2 ;;
        *) echo "Unknown option: $1" >&2; exit 2 ;;
    esac
done

[[ $EUID -eq 0 ]] || { echo "Run as root (sudo)." >&2; exit 1; }
[[ "$DOMAIN" =~ ^[A-Za-z0-9.-]+$ ]] || { echo "Invalid domain." >&2; exit 2; }
if [[ -n "$CI_KEY" ]]; then
    [[ "$CI_KEY" =~ ^ssh-ed25519\ [A-Za-z0-9+/=]+(\ [A-Za-z0-9@._-]+)?$ ]] || { echo "--ci-key must be one ssh-ed25519 public key." >&2; exit 2; }
fi
command -v nginx >/dev/null || { echo "nginx is not installed." >&2; exit 1; }
[[ -f "$API_SNIPPET" ]] || {
    echo "$API_SNIPPET is missing: set up the API first (backend-symfony/deploy/setup-server.sh)." >&2
    exit 1
}

log() { printf '\n==> %s\n' "$*"; }
export DEBIAN_FRONTEND=noninteractive

log "Creating $BASE (setup-server.sh $(sha256sum "${BASH_SOURCE[0]}" | cut -c1-12))"
# Static files only: owned by root, readable by nginx. No application user is needed.
install -d -o root -g root -m 0755 "$BASE" "$BASE/releases" "$BASE/shared"
cat > "$BASE/shared/deploy.conf" <<EOF
DOMAIN=$DOMAIN
EOF
chmod 0644 "$BASE/shared/deploy.conf"

log "Installing deploy commands in /usr/local/sbin"
# Root-owned copies: CI can trigger them but never change them. Re-run this script after they change in the repo.
install -o root -g root -m 0755 "$HERE/remote-install.sh" /usr/local/sbin/$APP-install
install -o root -g root -m 0755 "$HERE/ci-receive.sh" /usr/local/sbin/$APP-receive
install -o root -g root -m 0755 "$HERE/rollback.sh" /usr/local/sbin/$APP-rollback

if [[ -n "$CI_KEY" ]]; then
    log "Configuring the CI deploy user"
    # Its own user: the API's onechurch-deploy key is pinned to the API installer and must stay that way.
    DEPLOY_USER=onechurch-web-deploy
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
SITE=/etc/nginx/sites-available/$APP
CERT=/etc/letsencrypt/live/$DOMAIN/fullchain.pem
# Replace whichever domain the template names.
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
    # The log format and the port-80 server only, so ACME challenges work before a certificate exists.
    write_full_site
    awk '/^server \{/{n++} n<2' "$SITE" > "$SITE.tmp" && mv "$SITE.tmp" "$SITE"
}
if [[ -f "$CERT" ]]; then write_full_site; else write_http_site; fi
ln -sfn "$SITE" "/etc/nginx/sites-enabled/$APP"
nginx -t
systemctl reload nginx

if [[ ! -f "$CERT" && -n "$CERTBOT_EMAIL" ]]; then
    log "Obtaining a certificate for $DOMAIN"
    command -v certbot >/dev/null || apt-get install -y -q certbot
    certbot certonly --webroot -w /var/www/letsencrypt -d "$DOMAIN" \
        --email "$CERTBOT_EMAIL" --agree-tos --no-eff-email --non-interactive
    install -d /etc/letsencrypt/renewal-hooks/deploy
    printf '#!/bin/sh\nnginx -t && systemctl reload nginx\n' > /etc/letsencrypt/renewal-hooks/deploy/reload-nginx
    chmod 0755 /etc/letsencrypt/renewal-hooks/deploy/reload-nginx
    write_full_site
    nginx -t
    systemctl reload nginx
fi

log "Done"
[[ -f "$CERT" ]] || echo "No certificate yet: nginx serves HTTP only. Re-run with --certbot-email to enable HTTPS."
[[ -e "$BASE/current" ]] || echo "Next: deploy a release (frontend/deploy/release.sh, or push to main once CI is wired)."
