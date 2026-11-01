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
resume_api=false
# The EXIT trap invokes this function on every exit path.
# shellcheck disable=SC2329
cleanup() {
  local result=$?
  # Remove the temporary JVM before restarting the regular API on a small VPS.
  if [[ -n "$bootstrap_id" ]] && ! docker rm -f "$bootstrap_id" >/dev/null; then
    echo 'Cannot remove the bootstrap container. Keep the API stopped until it is removed.' >&2
    return 1
  fi
  if [[ "$resume_api" == true ]]; then
    echo 'Restarting the regular API after administrator setup...'
    if ! dc start --wait --wait-timeout 240 api; then
      echo 'The regular API could not restart. Check its container health and memory usage.' >&2
      return 1
    fi
  fi
  return "$result"
}
trap 'cleanup' EXIT
if [[ -n "$(dc ps --status running -q api)" ]]; then
  resume_api=true
  echo 'Temporarily stopping the API so administrator setup runs only one Java instance.'
  dc stop api
fi
bootstrap_id=$(dc run -d --no-deps --name "$bootstrap_name" \
  -e 'JAVA_TOOL_OPTIONS=-Xms32m -Xmx256m -XX:ActiveProcessorCount=1 -XX:+UseSerialGC -XX:+ExitOnOutOfMemoryError' \
  -e EMAIL_ENABLED=false -e PUSH_ENABLED=false \
  -e ONECHURCH_BOOTSTRAP_ENABLED=true \
  -e "ONECHURCH_BOOTSTRAP_USERNAME=$username" \
  -e "ONECHURCH_BOOTSTRAP_EMAIL=$email" \
  -e "ONECHURCH_BOOTSTRAP_DISPLAY_NAME=$display_name" \
  -e ONECHURCH_BOOTSTRAP_PASSWORD_FILE=/run/bootstrap-password \
  -v "$password_file:/run/bootstrap-password:ro" api)
for ((attempt = 0; attempt < 120; attempt++)); do
  if docker logs "$bootstrap_id" 2>&1 | grep -F 'First administrator created. Disable bootstrap before starting the API.' >/dev/null; then
    echo 'First administrator created. Remove the temporary password file.'
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
