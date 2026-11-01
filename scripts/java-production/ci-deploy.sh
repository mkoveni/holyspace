#!/usr/bin/env bash
# Runs only on the trusted GitHub runner, after all backend release checks pass.
set -euo pipefail
: "${VPS_HOST:?Set the production VPS hostname}"
: "${VPS_USER:?Set the deployment account}"
: "${VPS_SSH_PORT:?Set the SSH port}"
: "${VPS_SSH_KEY:?Set the dedicated deployment private key}"
: "${VPS_KNOWN_HOSTS:?Set the independently verified VPS known_hosts entry}"
: "${GITHUB_SHA:?Missing commit}"
: "${GITHUB_RUN_ID:?Missing run}"
: "${GITHUB_RUN_ATTEMPT:?Missing attempt}"
[[ "$GITHUB_SHA" =~ ^[0-9a-f]{40}$ && "$GITHUB_RUN_ID" =~ ^[0-9]+$ && "$GITHUB_RUN_ATTEMPT" =~ ^[0-9]+$ ]]
[[ "$VPS_HOST" =~ ^[a-zA-Z0-9][a-zA-Z0-9.-]*$ && "$VPS_USER" =~ ^[a-z_][a-z0-9_-]*$ ]]
[[ "$VPS_SSH_PORT" =~ ^[0-9]+$ ]] && (( VPS_SSH_PORT > 0 && VPS_SSH_PORT < 65536 ))
release_id="$GITHUB_SHA-$GITHUB_RUN_ID-$GITHUB_RUN_ATTEMPT"
artifact_directory=${1:?Provide the downloaded artifact directory}
(cd "$artifact_directory" && sha256sum --check --status holyspace-release.tar.sha256)
ssh_directory=$(mktemp -d)
transfer_stage='preparing SSH credentials'
finish_transfer() {
  local status=$?
  rm -rf "$ssh_directory"
  if (( status != 0 )); then
    printf 'Release transfer/deployment failed during %s (exit code %s).\n' "$transfer_stage" "$status" >&2
  fi
}
trap finish_transfer EXIT
chmod 700 "$ssh_directory"
printf '%s\n' "$VPS_SSH_KEY" > "$ssh_directory/key"
printf '%s\n' "$VPS_KNOWN_HOSTS" > "$ssh_directory/known_hosts"
chmod 600 "$ssh_directory/key" "$ssh_directory/known_hosts"
options=(-i "$ssh_directory/key" -o "UserKnownHostsFile=$ssh_directory/known_hosts"
  -o StrictHostKeyChecking=yes -o IdentitiesOnly=yes -o BatchMode=yes
  -o ConnectTimeout=15 -o ServerAliveInterval=15 -o ServerAliveCountMax=4)
# Do not discover/trust host keys during a deployment or disable host checking.
transfer_stage='checking VPS release receiver compatibility'
printf 'Deployment: %s\n' "$transfer_stage"
if ! receiver_version=$(ssh "${options[@]}" -p "$VPS_SSH_PORT" "$VPS_USER@$VPS_HOST" \
  '/usr/bin/python3 -u /srv/holyspace/bin/receive-release.py --version') \
  || [[ "$receiver_version" != holyspace-release-v2 ]]; then
  echo 'Update the VPS with the latest holyspace-vps-setup.pyz before deploying the frontend release. See docs/backend-cicd.md.' >&2
  exit 1
fi
transfer_stage='uploading release archive'
printf 'Deployment: %s\n' "$transfer_stage"
scp "${options[@]}" -P "$VPS_SSH_PORT" "$artifact_directory/holyspace-release.tar" \
  "$VPS_USER@$VPS_HOST:/srv/holyspace/incoming/$release_id.tar"
transfer_stage='uploading release checksum'
printf 'Deployment: %s\n' "$transfer_stage"
scp "${options[@]}" -P "$VPS_SSH_PORT" "$artifact_directory/holyspace-release.tar.sha256" \
  "$VPS_USER@$VPS_HOST:/srv/holyspace/incoming/$release_id.tar.sha256"
transfer_stage='running deployment on VPS'
printf 'Deployment: %s\n' "$transfer_stage"
ssh "${options[@]}" -p "$VPS_SSH_PORT" "$VPS_USER@$VPS_HOST" \
  "/usr/bin/python3 -u /srv/holyspace/bin/receive-release.py $release_id"
printf 'Deployment: VPS release completed successfully\n'
