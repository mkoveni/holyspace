#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/common.sh"
for service in database "${production_services[@]}"; do
  container_id=$(dc ps -q "$service")
  [[ -n "$container_id" ]] || { echo "Service missing: $service" >&2; exit 1; }
  status=$(docker inspect --format '{{.State.Status}}' "$container_id")
  [[ "$status" == running ]] || { echo "Service not running: $service" >&2; exit 1; }
  health=$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' "$container_id")
  [[ "$health" == healthy || "$health" == none ]] || { echo "Service unhealthy: $service" >&2; exit 1; }
done
dc exec -T api curl --fail --silent --max-time 5 http://127.0.0.1:8082/actuator/health/readiness >/dev/null
dc exec -T database psql -U holyspace_admin -d holyspace -v ON_ERROR_STOP=1 < "$production_root/docker/java-production/operations.sql"
echo 'Java service and queue checks passed.'
