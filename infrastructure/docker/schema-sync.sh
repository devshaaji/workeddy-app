#!/bin/sh
set -eu

MAX_RETRIES="${STARTUP_MAX_RETRIES:-30}"
SLEEP_SECONDS="${STARTUP_RETRY_SLEEP_SECONDS:-2}"
APP_ROOT="/var/www/html"

log() {
  echo "[schema-sync] $1"
}

ensure_dependencies() {
  if [ ! -f "${APP_ROOT}/vendor/autoload.php" ]; then
    log "vendor/autoload.php not found; running composer install"
    composer install --prefer-dist --no-interaction --optimize-autoloader
  fi
}

attempt=1
ensure_dependencies

while [ "$attempt" -le "$MAX_RETRIES" ]; do
  if php "${APP_ROOT}/bin/console" doctrine:migrations:sync-metadata-storage >/dev/null 2>&1 \
    && php "${APP_ROOT}/bin/console" doctrine:migrations:migrate --no-interaction --allow-no-migration; then
    log "database migrations are up to date"
    exit 0
  fi

  log "migrate attempt ${attempt}/${MAX_RETRIES} failed; retrying in ${SLEEP_SECONDS}s"
  attempt=$((attempt + 1))
  sleep "${SLEEP_SECONDS}"
done

log "migration failed after ${MAX_RETRIES} attempts"
exit 1
