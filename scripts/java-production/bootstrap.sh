#!/usr/bin/env bash
# Operator-only initial account creation; the handler refuses non-empty account stores.
set -euo pipefail
source "$(dirname "$0")/common.sh"
username=${1:?Usage: bootstrap.sh USERNAME EMAIL DISPLAY_NAME PASSWORD_FILE}
email=${2:?Provide account email}
display_name=${3:?Provide display name}
password_file=$(realpath "${4:?Provide a private password file readable by group 10001}")
[[ -f "$password_file" ]] || { echo 'Password file does not exist.' >&2; exit 1; }
bootstrap_name="holyspace-java-bootstrap-$(date +%s)-$RANDOM"
bootstrap_id=''
cleanup() { if [[ -n "$bootstrap_id" ]]; then docker rm -f "$bootstrap_id" >/dev/null; fi; }
trap cleanup EXIT
bootstrap_id=$(dc run -d --no-deps --name "$bootstrap_name" \
  -e EMAIL_ENABLED=false -e PUSH_ENABLED=false \
  -e ONECHURCH_BOOTSTRAP_ENABLED=true \
  -e "ONECHURCH_BOOTSTRAP_USERNAME=$username" \
  -e "ONECHURCH_BOOTSTRAP_EMAIL=$email" \
  -e "ONECHURCH_BOOTSTRAP_DISPLAY_NAME=$display_name" \
  -e ONECHURCH_BOOTSTRAP_PASSWORD_FILE=/run/bootstrap-password \
  -v "$password_file:/run/bootstrap-password:ro" api)
for attempt in {1..120}; do
  if docker logs "$bootstrap_id" 2>&1 | grep -F 'First administrator created. Disable bootstrap before starting the API.' >/dev/null; then
    echo 'First administrator created. Remove the temporary password file and start the normal API.'
    exit 0
  fi
  if [[ "$(docker inspect --format '{{.State.Running}}' "$bootstrap_id")" != true ]]; then
    echo 'Bootstrap failed. Check file permissions and whether an account already exists.' >&2
    exit 1
  fi
  sleep 1
done
echo 'Bootstrap did not complete within 120 seconds. Inspect the database before retrying.' >&2
exit 1
