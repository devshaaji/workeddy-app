#!/bin/sh
set -eu

MAX_RETRIES="${STARTUP_MAX_RETRIES:-30}"
SLEEP_SECONDS="${STARTUP_RETRY_SLEEP_SECONDS:-2}"
BACKGROUND_PIDS=""
MAIN_PID=""

log() {
  echo "[api-entrypoint] $1"
}

ensure_dependencies() {
  if [ ! -f /var/www/html/vendor/autoload.php ]; then
    log "vendor/autoload.php not found; running composer install"
    composer install --prefer-dist --no-interaction --optimize-autoloader
  fi
}

ensure_runtime_dirs() {
  mkdir -p \
    /var/www/html/storage/cache \
    /var/www/html/storage/cache/routes \
    /var/www/html/storage/log \
    /var/www/html/storage/app/private \
    /var/www/html/var/locks \
    /var/log

  chmod -R 0775 \
    /var/www/html/storage \
    /var/www/html/var || true

  chown -R www-data:www-data \
    /var/www/html/storage \
    /var/www/html/var 2>/dev/null || true
}

track_pid() {
  pid="$1"
  BACKGROUND_PIDS="${BACKGROUND_PIDS} ${pid}"
}

start_background() {
  name="$1"
  shift

  log "starting background process: ${name}"
  "$@" &
  track_pid "$!"
}

start_loop() {
  name="$1"
  interval="$2"
  shift 2

  start_background "${name}" /usr/local/bin/run-loop.sh "${name}" "${interval}" "$@"
}

start_cron_daemon() {
  /usr/local/bin/write-crontab.sh
  start_background "crond" crond -f -l 2
}

stop_background_processes() {
  for pid in $BACKGROUND_PIDS; do
    if [ -n "$pid" ] && kill -0 "$pid" 2>/dev/null; then
      kill "$pid" 2>/dev/null || true
    fi
  done
}

handle_signal() {
  log "received shutdown signal"

  if [ -n "${MAIN_PID}" ] && kill -0 "${MAIN_PID}" 2>/dev/null; then
    kill "${MAIN_PID}" 2>/dev/null || true
  fi

  stop_background_processes
  wait || true
  exit 143
}

wait_for_db_and_migrate() {
  attempt=1
  while [ "$attempt" -le "$MAX_RETRIES" ]; do
    if php /var/www/html/bin/console doctrine:migrations:sync-metadata-storage >/dev/null 2>&1 \
      && php /var/www/html/bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration; then
      log "database migrations are up to date"
      return 0
    fi

    log "migrate attempt ${attempt}/${MAX_RETRIES} failed; retrying in ${SLEEP_SECONDS}s"
    attempt=$((attempt + 1))
    sleep "$SLEEP_SECONDS"
  done

  log "migration failed after ${MAX_RETRIES} attempts"
  return 1
}

ensure_dependencies
ensure_runtime_dirs
wait_for_db_and_migrate

trap handle_signal INT TERM

if [ "${DOCKER_START_QUEUE_WORKERS:-1}" = "1" ]; then
  start_loop "queue-default" "${QUEUE_WORKER_INTERVAL_SECONDS:-5}" /bin/sh -lc 'php /var/www/html/bin/console queue:work default --limit="${WorkEddy_QUEUE_WORKER_LIMIT:-25}" --worker-id="docker-default-${HOSTNAME:-unknown}"'
  start_loop "queue-high-priority" "${HIGH_PRIORITY_QUEUE_WORKER_INTERVAL_SECONDS:-5}" /bin/sh -lc 'php /var/www/html/bin/console queue:work high_priority --limit="${HIGH_PRIORITY_QUEUE_WORKER_LIMIT:-25}" --worker-id="docker-high-priority-${HOSTNAME:-unknown}"'
fi

if [ "${DOCKER_START_CRON_DAEMON:-1}" = "1" ]; then
  start_cron_daemon
fi

log "starting: $*"
"$@" &
MAIN_PID="$!"
set +e
wait "${MAIN_PID}"
STATUS="$?"
set -e

stop_background_processes
wait || true
exit "${STATUS}"
