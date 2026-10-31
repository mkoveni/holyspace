#!/usr/bin/env bash
set -euo pipefail
release_tag=${1:?Usage: security-scan.sh RELEASE_TAG REPORT_DIRECTORY}
report_directory=$(realpath -m "${2:?Provide a private report directory}")
mkdir -p "$report_directory"
scanner='aquasec/trivy:0.76.1@sha256:790fd66d1697395d8693d1acaa1ea702406a20f109b6fc7bac8db623eca6c06c'
result=0
targets=("api|holyspace-java-api:$release_tag" "tools|holyspace-java-tools:$release_tag"
  "database|holyspace-java-database:$release_tag")
case "${JAVA_PRODUCTION_TARGET:-bundled}" in
  bundled) targets+=("web|holyspace-java-web:$release_tag" "edge|holyspace-java-edge:$release_tag") ;;
  nginx-api) ;;
  *) echo 'Unknown JAVA_PRODUCTION_TARGET; use bundled or nginx-api.' >&2; exit 1 ;;
esac
for target in "${targets[@]}"; do
  component=${target%%|*}
  image=${target#*|}
  if ! docker run --rm \
    -v /var/run/docker.sock:/var/run/docker.sock:ro \
    -v holyspace-trivy-cache:/root/.cache/ \
    -v "$report_directory:/reports" "$scanner" image \
    --scanners vuln --severity HIGH,CRITICAL --exit-code 1 --timeout 10m \
    --format json --output "/reports/$component.json" "$image"; then
    result=1
  fi
done
if (( result != 0 )); then
  echo 'Image security gate failed. Inspect the reports; do not waive or hide unresolved findings.' >&2
fi
exit "$result"
