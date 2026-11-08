#!/usr/bin/env bash
# One command, run once on your machine, that wires GitHub Actions to the VPS for the web app. After it, every push to
# main touching frontend/ that passes the checks deploys itself.
#
#   frontend/deploy/bootstrap-ci.sh you@your-vps [--domain holyspace.rivalani.dev] [--certbot-email you@example.org]
#
# It needs: SSH access to the VPS as a sudo user, the API already set up there, and the GitHub CLI logged in with admin
# rights on this repository (gh auth login). It:
#   1. generates a fresh ed25519 deploy key (never written outside a temporary directory),
#   2. copies deploy/ to the server and runs setup-server.sh with --ci-key (plus the options you pass through),
#   3. reads the server's host key over your already-verified SSH connection,
#   4. creates the GitHub environment "frontend-production" (main branch only), stores the key, host and host key as
#      its secrets, and sets FRONTEND_URL, FRONTEND_VPS_PORT and FRONTEND_DEPLOY_ENABLED=true.
# Re-running it rotates the deploy key and re-applies the server setup (releases are kept).
set -euo pipefail

usage() {
    echo "usage: bootstrap-ci.sh user@host [--domain NAME] [--api-domain NAME] [--certbot-email EMAIL]" >&2
    exit 2
}
HOST=
DOMAIN=holyspace.rivalani.dev
SETUP_ARGS=()
while [[ $# -gt 0 ]]; do
    case "$1" in
        --domain=*) DOMAIN="${1#*=}"; shift ;;
        --domain) [[ $# -ge 2 ]] || usage; DOMAIN="$2"; shift 2 ;;
        --certbot-email) [[ $# -ge 2 ]] || usage; SETUP_ARGS+=("$1" "$2"); shift 2 ;;
        --certbot-email=*) SETUP_ARGS+=("$1"); shift ;;
        --api-domain) [[ $# -ge 2 ]] || usage; SETUP_ARGS+=("$1" "$2"); shift 2 ;;
        --api-domain=*) SETUP_ARGS+=("$1"); shift ;;
        --ci-key|--ci-key=*) echo "bootstrap-ci.sh generates the CI key itself; do not pass --ci-key." >&2; exit 2 ;;
        -h|--help) usage ;;
        -*) echo "Unknown option: $1" >&2; usage ;;
        *) [[ -z "$HOST" ]] || { echo "Unexpected argument: $1 (host is already $HOST)" >&2; usage; }; HOST="$1"; shift ;;
    esac
done
[[ -n "$HOST" ]] || { echo "The SSH host (user@host) is required." >&2; usage; }
SETUP_ARGS=(--domain "$DOMAIN" "${SETUP_ARGS[@]}")
echo "Host: $HOST   Domain: $DOMAIN   Setup options: ${SETUP_ARGS[*]}"

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
command -v gh >/dev/null || { echo "Install the GitHub CLI (gh) and run gh auth login." >&2; exit 1; }
gh auth status >/dev/null 2>&1 || { echo "Run gh auth login first." >&2; exit 1; }
REPO="$(cd "$HERE" && gh repo view --json nameWithOwner --jq .nameWithOwner)"
ENVIRONMENT=frontend-production
DEPLOY_USER=onechurch-web-deploy

# The real host name and port behind any ~/.ssh/config alias, for GitHub's runner.
REAL_HOST="$(ssh -G "$HOST" | awk '$1 == "hostname" { print $2; exit }')"
REAL_PORT="$(ssh -G "$HOST" | awk '$1 == "port" { print $2; exit }')"

work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT
echo "==> Generating a deploy key"
ssh-keygen -q -t ed25519 -N '' -C "github-actions-onechurch-web" -f "$work/deploy_key"

echo "==> Preparing $HOST (setup-server.sh)"
remote_dir="/tmp/onechurch-web-deploy-$$"
ssh "$HOST" "rm -rf '$remote_dir' && mkdir -m 0700 '$remote_dir'"
scp -q -r "$HERE"/* "$HOST:$remote_dir/"
ssh -t "$HOST" "sudo bash '$remote_dir/setup-server.sh' $(printf '%q ' "${SETUP_ARGS[@]}") --ci-key '$(cat "$work/deploy_key.pub")'; status=\$?; rm -rf '$remote_dir'; exit \$status"

echo "==> Reading the server host key"
host_keys="$(ssh "$HOST" 'cat /etc/ssh/ssh_host_*_key.pub' | awk '$1 ~ /^(ssh-|ecdsa-)/ { print $1" "$2 }')"
grep -q '^ssh-ed25519 ' <<< "$host_keys" || { echo "Could not read the server's host keys." >&2; exit 1; }
if [[ "$REAL_PORT" == 22 ]]; then host_pattern="$REAL_HOST"; else host_pattern="[$REAL_HOST]:$REAL_PORT"; fi
known="$(awk -v h="$host_pattern" '{ print h" "$0 }' <<< "$host_keys")"
printf '%s\n' "$known" > "$work/known_hosts"

echo "==> Checking the deploy key the way GitHub Actions will use it (it may only answer 'version')"
ssh -F /dev/null -i "$work/deploy_key" -o IdentitiesOnly=yes -o UserKnownHostsFile="$work/known_hosts" \
    -o GlobalKnownHostsFile=/dev/null -o StrictHostKeyChecking=yes -o BatchMode=yes \
    -p "$REAL_PORT" "$DEPLOY_USER@$REAL_HOST" version || {
    echo "The deploy key was refused. sshd's own reason, from the server log:" >&2
    ssh -t "$HOST" "sudo journalctl -u ssh -u sshd --since '-10 min' --no-pager 2>/dev/null | grep -i $DEPLOY_USER | tail -n 8 \
        || sudo grep -i $DEPLOY_USER /var/log/auth.log | tail -n 8" >&2 || true
    echo "Check: sudo sshd -T -C user=$DEPLOY_USER,host=ci,addr=192.0.2.1 | grep -iE 'allow|deny|authorizedkeys|pubkey'" >&2
    exit 1
}

echo "==> Configuring GitHub ($REPO, environment $ENVIRONMENT)"
gh api -X PUT "repos/$REPO/environments/$ENVIRONMENT" --input - >/dev/null <<'JSON'
{"deployment_branch_policy": {"protected_branches": false, "custom_branch_policies": true}}
JSON
gh api "repos/$REPO/environments/$ENVIRONMENT/deployment-branch-policies" --jq '.branch_policies[].name' | grep -qx main \
    || gh api -X POST "repos/$REPO/environments/$ENVIRONMENT/deployment-branch-policies" -f name=main -f type=branch >/dev/null
gh secret set FRONTEND_VPS_SSH_KEY --repo "$REPO" --env "$ENVIRONMENT" < "$work/deploy_key"
gh secret set FRONTEND_VPS_HOST --repo "$REPO" --env "$ENVIRONMENT" --body "$REAL_HOST"
gh secret set FRONTEND_VPS_KNOWN_HOSTS --repo "$REPO" --env "$ENVIRONMENT" --body "$known"
gh variable set FRONTEND_VPS_PORT --repo "$REPO" --env "$ENVIRONMENT" --body "$REAL_PORT"
gh variable set FRONTEND_URL --repo "$REPO" --body "https://$DOMAIN"
gh variable set FRONTEND_DEPLOY_ENABLED --repo "$REPO" --body true

echo
echo "Done. The next push to main touching frontend/ deploys automatically."
