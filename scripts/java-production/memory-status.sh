#!/usr/bin/env bash
# Read-only diagnostics; never print container environments or application logs.
set -euo pipefail
source "$(dirname "$0")/common.sh"
free -h
mapfile -t containers < <(dc ps --all --quiet)
if (( ${#containers[@]} > 0 )); then
  docker stats --no-stream --format 'table {{.Name}}\t{{.MemUsage}}\t{{.MemPerc}}' "${containers[@]}"
  docker inspect --format '{{.Name}}: OOMKilled={{.State.OOMKilled}} Restarts={{.RestartCount}} MemoryLimit={{.HostConfig.Memory}}' "${containers[@]}"
fi
