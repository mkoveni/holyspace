#!/usr/bin/env bash
# One command, run once on your machine, that wires GitHub Actions to the VPS. After it, every push to main that passes
# the checks deploys itself.
#
#   backend-symfony/deploy/bootstrap-ci.sh you@your-vps --domain api.your-church.org [setup-server.sh options]
#
# It needs: SSH access to the VPS as a sudo user (you may be asked for the sudo password), and the GitHub CLI logged in
# with admin rights on this repository (gh auth login). It:
#   1. generates a fresh ed25519 deploy key (never written outside a temporary directory),
#   2. copies deploy/ to the server and runs setup-server.sh with --ci-key (plus any options you pass through),
#   3. reads the server's host key over your already-verified SSH connection,
#   4. creates the GitHub environment "symfony-production" (main branch only) and stores the key, host and host key
#      as its secrets, and sets SYMFONY_API_URL, SYMFONY_VPS_PORT and SYMFONY_DEPLOY_ENABLED=true.
# Re-running it rotates the deploy key and re-applies the server setup (secrets and data are kept).
set -euo pipefail

usage() {
    echo "usage: bootstrap-ci.sh user@host --domain NAME [setup-server.sh options]   (any order; --domain=NAME also works)" >&2
    exit 2
}
HOST=
DOMAIN=
SETUP_ARGS=()
while [[ $# -gt 0 ]]; do
    case "$1" in
        --domain=*) DOMAIN="${1#*=}"; shift ;;
        --domain) [[ $# -ge 2 ]] || usage; DOMAIN="$2"; shift 2 ;;
        # Options that take a value: forward both words (or the --opt=value word) to setup-server.sh.
        --php|--certbot-email) [[ $# -ge 2 ]] || usage; SETUP_ARGS+=("$1" "$2"); shift 2 ;;
        --ci-key|--ci-key=*) echo "bootstrap-ci.sh generates the CI key itself; do not pass --ci-key." >&2; exit 2 ;;
        -h|--help) usage ;;
        -*) SETUP_ARGS+=("$1"); shift ;;
        *) [[ -z "$HOST" ]] || { echo "Unexpected argument: $1 (host is already $HOST)" >&2; usage; }; HOST="$1"; shift ;;
    esac
done
[[ -n "$HOST" ]] || { echo "The SSH host (user@host) is required." >&2; usage; }
[[ -n "$DOMAIN" ]] || { echo "--domain is required." >&2; usage; }
SETUP_ARGS=(--domain "$DOMAIN" "${SETUP_ARGS[@]}")
echo "Host: $HOST   Domain: $DOMAIN   Setup options: ${SETUP_ARGS[*]}"

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
command -v gh >/dev/null || { echo "Install the GitHub CLI (gh) and run gh auth login." >&2; exit 1; }
gh auth status >/dev/null 2>&1 || { echo "Run gh auth login first." >&2; exit 1; }
REPO="$(cd "$HERE" && gh repo view --json nameWithOwner --jq .nameWithOwner)"
ENVIRONMENT=symfony-production

# The real host name and port behind any ~/.ssh/config alias, for GitHub's runner.
REAL_HOST="$(ssh -G "$HOST" | awk '$1 == "hostname" { print $2; exit }')"
REAL_PORT="$(ssh -G "$HOST" | awk '$1 == "port" { print $2; exit }')"

work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT
echo "==> Generating a deploy key"
ssh-keygen -q -t ed25519 -N '' -C "github-actions-onechurch-api" -f "$work/deploy_key"

echo "==> Preparing $HOST (setup-server.sh)"
remote_dir="/tmp/onechurch-deploy-$$"
ssh "$HOST" "rm -rf '$remote_dir' && mkdir -m 0700 '$remote_dir'"
scp -q -r "$HERE"/* "$HOST:$remote_dir/"
ssh -t "$HOST" "sudo bash '$remote_dir/setup-server.sh' $(printf '%q ' "${SETUP_ARGS[@]}") --ci-key '$(cat "$work/deploy_key.pub")'; status=\$?; rm -rf '$remote_dir'; exit \$status"

echo "==> Reading the server host key"
# Every host key type the server has, so the runner verifies whichever one its ssh negotiates.
host_keys="$(ssh "$HOST" 'cat /etc/ssh/ssh_host_*_key.pub' | awk '$1 ~ /^(ssh-|ecdsa-)/ { print $1" "$2 }')"
grep -q '^ssh-ed25519 ' <<< "$host_keys" || { echo "Could not read the server's host keys." >&2; exit 1; }
if [[ "$REAL_PORT" == 22 ]]; then host_pattern="$REAL_HOST"; else host_pattern="[$REAL_HOST]:$REAL_PORT"; fi
known="$(awk -v h="$host_pattern" '{ print h" "$0 }' <<< "$host_keys")"
# A real file: ssh reads known_hosts more than once, so a <(...) pipe is empty the second time.
printf '%s\n' "$known" > "$work/known_hosts"

echo "==> Checking the deploy key the way GitHub Actions will use it (it may only answer 'version')"
# -F /dev/null: ignore your ~/.ssh/config (aliases, HostKeyAlias, ProxyJump) exactly as the runner does.
ssh -F /dev/null -i "$work/deploy_key" -o IdentitiesOnly=yes -o UserKnownHostsFile="$work/known_hosts" \
    -o GlobalKnownHostsFile=/dev/null -o StrictHostKeyChecking=yes -o BatchMode=yes \
    -p "$REAL_PORT" "onechurch-deploy@$REAL_HOST" version || {
    echo "The deploy key was refused. sshd's own reason, from the server log:" >&2
    ssh -t "$HOST" "sudo journalctl -u ssh -u sshd --since '-10 min' --no-pager 2>/dev/null | grep -i onechurch-deploy | tail -n 8 \
        || sudo grep -i onechurch-deploy /var/log/auth.log | tail -n 8" >&2 || true
    echo "Check: sudo sshd -T -C user=onechurch-deploy,host=ci,addr=192.0.2.1 | grep -iE 'allow|deny|authorizedkeys|pubkey'" >&2
    exit 1
}

echo "==> Configuring GitHub ($REPO, environment $ENVIRONMENT)"
gh api -X PUT "repos/$REPO/environments/$ENVIRONMENT" --input - >/dev/null <<'JSON'
{"deployment_branch_policy": {"protected_branches": false, "custom_branch_policies": true}}
JSON
gh api "repos/$REPO/environments/$ENVIRONMENT/deployment-branch-policies" --jq '.branch_policies[].name' | grep -qx main \
    || gh api -X POST "repos/$REPO/environments/$ENVIRONMENT/deployment-branch-policies" -f name=main -f type=branch >/dev/null
gh secret set SYMFONY_VPS_SSH_KEY --repo "$REPO" --env "$ENVIRONMENT" < "$work/deploy_key"
gh secret set SYMFONY_VPS_HOST --repo "$REPO" --env "$ENVIRONMENT" --body "$REAL_HOST"
gh secret set SYMFONY_VPS_KNOWN_HOSTS --repo "$REPO" --env "$ENVIRONMENT" --body "$known"
gh variable set SYMFONY_VPS_PORT --repo "$REPO" --env "$ENVIRONMENT" --body "$REAL_PORT"
gh variable set SYMFONY_API_URL --repo "$REPO" --body "https://$DOMAIN"
gh variable set SYMFONY_DEPLOY_ENABLED --repo "$REPO" --body true

echo
echo "Done. Next push to main touching backend-symfony deploys automatically."
echo "Still yours to do once: fill /srv/onechurch-api/shared/.env.local on the server (the first deploy refuses template values)."
