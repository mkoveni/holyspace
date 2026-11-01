#!/usr/bin/env bash
set -euo pipefail
# Written only after initdb succeeds. Never adopt an existing unmarked cluster.
printf 'alpine-3.24-pg18\n' > "$PGDATA/.holyspace-runtime"
