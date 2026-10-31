#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/common.sh"
status=0
for service in ${PRODUCTION_CHECK_SERVICES:-database php web edge outbox communications followups payments}; do
  container_id=$(dc ps -q "$service")
  if [[ -z "$container_id" ]]; then echo "$service: missing" >&2; status=1; continue; fi
  state=$(docker inspect --format '{{.State.Status}} {{if .State.Health}}{{.State.Health.Status}}{{else}}no-healthcheck{{end}}' "$container_id")
  printf '%s: %s\n' "$service" "$state"
  [[ "$state" == 'running healthy' || "$state" == 'running no-healthcheck' ]] || status=1
done
if ! dc exec -T php php bin/console app:operations:check --no-interaction; then status=1; fi
exit "$status"
