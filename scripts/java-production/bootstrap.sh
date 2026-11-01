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
original_api_id=''
resume_api=false
# Called by the EXIT cleanup below.
# shellcheck disable=SC2329
wait_for_original_api() {
  local state attempt
  for ((attempt = 0; attempt < 240; attempt++)); do
    state=$(docker inspect --format '{{.State.Status}} {{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' "$original_api_id") || return 1
    case "$state" in
      'running healthy'|'running none') return 0 ;;
      'created '*|'exited '*|'dead '*|'removing '*) return 1 ;;
    esac
    sleep 1
  done
  return 1
}
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
    # Restart exactly the container we stopped; Compose service selection can
    # also encounter the one-off API container used by bootstrap.
    if ! docker start "$original_api_id" >/dev/null || ! wait_for_original_api; then
      echo 'The regular API could not restart. Check its container health and memory usage.' >&2
      docker inspect --format 'API state={{.State.Status}} health={{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}} OOMKilled={{.State.OOMKilled}} exit={{.State.ExitCode}}' "$original_api_id" >&2 || true
      return 1
    fi
  fi
  return "$result"
}
trap 'cleanup' EXIT
original_api_id=$(dc ps --status running -q api)
if [[ -n "$original_api_id" ]]; then
  resume_api=true
  echo 'Temporarily stopping the API so administrator setup runs only one Java instance.'
  docker stop "$original_api_id" >/dev/null
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
    if docker logs "$bootstrap_id" 2>&1 | grep -F 'Administrator bootstrap is only allowed before the first account exists.' >/dev/null; then
      echo 'Administrator bootstrap refused: an account already exists.' >&2
    else
      echo 'Bootstrap failed. Check file permissions and whether an account already exists.' >&2
      docker inspect --format 'Bootstrap state={{.State.Status}} OOMKilled={{.State.OOMKilled}} exit={{.State.ExitCode}}' "$bootstrap_id" >&2 || true
    fi
    exit 1
  fi
  sleep 1
done
echo 'Bootstrap did not complete within 120 seconds. Inspect the database before retrying.' >&2
exit 1
