#!/bin/sh
set -eu
case "${1:-}" in
  outbox) command=app:outbox:process ;;
  communications) command=app:communications:deliver ;;
  payments) command=app:payments:maintain ;;
  followups) command=app:followups:run ;;
  *) echo 'Usage: church-worker outbox|communications|followups|payments' >&2; exit 2 ;;
esac
# An error exits the container, preserving the failure in logs. Docker restarts it.
# A successful pass updates a local heartbeat used by the container health check.
while :; do
  php bin/console "$command" --no-interaction
  if [ "$command" = app:outbox:process ]; then
    php bin/console app:volunteering:settle --no-interaction
  fi
  touch /tmp/church-worker-heartbeat
  sleep 10 & wait "$!"
done
