#!/bin/sh
set -eu

CRON_FILE="/etc/crontabs/root"
APP_ROOT="/var/www/html"

cat > "${CRON_FILE}" <<EOF
SHELL=/bin/sh
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

${QUEUE_MAINTENANCE_CRON:-40 3 * * *} cd ${APP_ROOT} && php ${APP_ROOT}/cronjobs/queue-maintenance.php
${VIDEO_RETENTION_CRON:-42 3 * * *} cd ${APP_ROOT} && php ${APP_ROOT}/cronjobs/video-retention-enforce.php
${CORRECTIVE_ACTION_CRON:-43 3 * * *} cd ${APP_ROOT} && php ${APP_ROOT}/cronjobs/corrective-action-maintenance.php
${SUBSCRIPTION_RENEWAL_CRON:-15 2 * * *} cd ${APP_ROOT} && php ${APP_ROOT}/cronjobs/subscription-renewal-sweep.php
${SUBSCRIPTION_DUNNING_CRON:-30 2 * * *} cd ${APP_ROOT} && php ${APP_ROOT}/cronjobs/subscription-dunning-sweep.php
${RUNTIME_DOCTOR_CRON:-45 3 * * *} cd ${APP_ROOT} && WorkEddy_RUNTIME_DOCTOR_STRICT=\${WorkEddy_RUNTIME_DOCTOR_STRICT:-0} php ${APP_ROOT}/cronjobs/runtime-doctor.php
EOF
