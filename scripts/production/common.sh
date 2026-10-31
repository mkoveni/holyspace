#!/usr/bin/env bash
set -euo pipefail
production_root=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)
production_env=${PRODUCTION_ENV_FILE:-"$production_root/.env.production.local"}
dc() {
  local files=(-f "${PRODUCTION_COMPOSE_FILE:-$production_root/compose.production.yaml}")
  if [[ -n "${PRODUCTION_OVERRIDE_FILE:-}" ]]; then files+=(-f "$PRODUCTION_OVERRIDE_FILE"); fi
  local project=()
  if [[ -n "${PRODUCTION_PROJECT_NAME:-}" ]]; then project=(-p "$PRODUCTION_PROJECT_NAME"); fi
  docker compose --env-file "$production_env" "${files[@]}" "${project[@]}" "$@"
}
