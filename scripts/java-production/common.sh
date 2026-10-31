#!/usr/bin/env bash
set -euo pipefail
production_root=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)
production_env=${JAVA_PRODUCTION_ENV_FILE:-"$production_root/.env.java-production.local"}
production_target=${JAVA_PRODUCTION_TARGET:-bundled}
case "$production_target" in
  bundled) production_services=(api web edge); production_images=(api web tools database edge) ;;
  nginx-api) production_services=(api); production_images=(api tools database) ;;
  *) echo 'Unknown JAVA_PRODUCTION_TARGET; use bundled or nginx-api.' >&2; exit 1 ;;
esac
dc() {
  local options=(--env-file "$production_env" -f "$production_root/compose.java-production.yaml")
  if [[ "$production_target" == nginx-api ]]; then
    options+=(-f "$production_root/compose.java-production-nginx.yaml")
  fi
  if [[ -n "${JAVA_PRODUCTION_OVERRIDE_FILE:-}" ]]; then options+=(-f "$JAVA_PRODUCTION_OVERRIDE_FILE"); fi
  if [[ -n "${JAVA_PRODUCTION_PROJECT:-}" ]]; then options+=(-p "$JAVA_PRODUCTION_PROJECT"); fi
  docker compose "${options[@]}" "$@"
}
