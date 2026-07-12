#!/bin/sh
set -eu

NAME="$1"
INTERVAL_SECONDS="$2"
shift 2

log() {
  echo "[docker-loop:${NAME}] $1"
}

if [ "${INTERVAL_SECONDS}" -lt 1 ] 2>/dev/null; then
  log "invalid interval '${INTERVAL_SECONDS}', defaulting to 1 second"
  INTERVAL_SECONDS=1
fi

log "starting loop every ${INTERVAL_SECONDS}s: $*"

while true; do
  if "$@"; then
    :
  else
    STATUS=$?
    log "command failed with exit ${STATUS}: $*"
  fi

  sleep "${INTERVAL_SECONDS}"
done
