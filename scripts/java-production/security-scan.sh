#!/usr/bin/env bash
set -euo pipefail
release_tag=${1:?Usage: security-scan.sh RELEASE_TAG REPORT_DIRECTORY}
report_directory=$(realpath -m "${2:?Provide a private report directory}")
mkdir -p "$report_directory"
scanner='aquasec/trivy:0.76.1@sha256:790fd66d1697395d8693d1acaa1ea702406a20f109b6fc7bac8db623eca6c06c'
result=0
scan_results=()
scanner_container=''
cleanup_scanner() {
  if [[ -n "$scanner_container" ]]; then
    docker rm -f "$scanner_container" >/dev/null 2>&1 || true
  fi
}
trap 'cleanup_scanner' EXIT
scanner_memory=${SCANNER_MEMORY_LIMIT:-256m}
scanner_go_memory=${SCANNER_GO_MEMORY_LIMIT:-192MiB}
targets=("api|holyspace-java-api:$release_tag" "tools|holyspace-java-tools:$release_tag"
  "database|holyspace-java-database:$release_tag")
case "${JAVA_PRODUCTION_TARGET:-bundled}" in
  bundled) targets+=("web|holyspace-java-web:$release_tag" "edge|holyspace-java-edge:$release_tag") ;;
  nginx-api) ;;
  nginx-app) targets+=("web|holyspace-java-web:$release_tag") ;;
  *) echo 'Unknown JAVA_PRODUCTION_TARGET; use bundled, nginx-api or nginx-app.' >&2; exit 1 ;;
esac
for target in "${targets[@]}"; do
  component=${target%%|*}
  image=${target#*|}
  # Never accidentally summarize a report left by an earlier scan.
  rm -f "$report_directory/$component.json" "$report_directory/$component.status.json"
  scanner_container="holyspace-security-$component-$$-$RANDOM"
  printf 'Scanning %s image (container memory limit: %s)...\n' "$component" "$scanner_memory"
  # Retain the stopped scanner until its OOM state has been recorded.
  if docker run --name "$scanner_container" --memory "$scanner_memory" --memory-swap "$scanner_memory" \
    -e "GOMEMLIMIT=$scanner_go_memory" -e GOMAXPROCS=1 \
    -v /var/run/docker.sock:/var/run/docker.sock:ro \
    -v holyspace-trivy-cache:/root/.cache/ \
    -v "$report_directory:/reports" "$scanner" image \
    --scanners vuln --parallel 1 --severity HIGH,CRITICAL --exit-code 1 --timeout 10m \
    --format json --output "/reports/$component.json" "$image"; then
    scan_status=0
  else
    scan_status=$?
    result=1
  fi
  scanner_oom=$(docker inspect --format '{{.State.OOMKilled}}' "$scanner_container" 2>/dev/null || echo unknown)
  printf 'Scanner result: component=%s exit=%s OOMKilled=%s memory_limit=%s\n' \
    "$component" "$scan_status" "$scanner_oom" "$scanner_memory"
  python3 - "$report_directory/$component.status.json" "$scan_status" "$scanner_oom" "$scanner_memory" <<'PY'
import json
from pathlib import Path
import sys
Path(sys.argv[1]).write_text(json.dumps({
    "exit_code": int(sys.argv[2]),
    "oom_killed": {"true": True, "false": False}.get(sys.argv[3]),
    "memory_limit": sys.argv[4],
}) + "\n")
PY
  cleanup_scanner
  scanner_container=''
  scan_results+=("$component:$scan_status")
done
if ! python3 "$(dirname "$0")/security-summary.py" "$report_directory" "${scan_results[@]}"; then
  result=1
fi
if (( result != 0 )); then
  echo 'Image security gate failed. Inspect the reports; do not waive or hide unresolved findings.' >&2
fi
exit "$result"
