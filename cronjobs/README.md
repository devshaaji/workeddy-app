# WorkEddy Cron Jobs

These scripts are deployment wrappers for shared hosting panels, VPS cron, and process managers.

Use the canonical console where possible:

```bash
php bin/console list
php bin/console queue:status
php bin/console transport:outbox:dispatch
php bin/console ops:runtime:doctor
```

Queue wrappers:

```text
php cronjobs/queue-work.php
php cronjobs/queue-maintenance.php
php cronjobs/transport-inbox-process.php
php cronjobs/transport-outbox-dispatch.php
php cronjobs/runtime-doctor.php
php cronjobs/video-retention-enforce.php
php cronjobs/corrective-action-maintenance.php
php cronjobs/subscription-renewal-sweep.php
php cronjobs/subscription-dunning-sweep.php
```

Environment options:

```text
WorkEddy_QUEUE_DRIVER                 Default: database
WorkEddy_QUEUE_DEFAULT                Default: default
WorkEddy_QUEUE_WORKER_LIMIT           Default: 25
WorkEddy_QUEUE_WORKER_ID              Optional stable worker identifier
WorkEddy_QUEUE_MAX_ATTEMPTS           Default: 3
WorkEddy_QUEUE_RETRY_DELAY_SECONDS    Default: 60
WorkEddy_QUEUE_LOCK_SECONDS           Default: 120
WorkEddy_QUEUE_MAINTENANCE_LIMIT      Default: 100
WorkEddy_RUNTIME_DOCTOR_STRICT        Default: 0; when 1, validates live DB schema, queue, sync event store, idempotency locks, and analytics readiness
WorkEddy_VIDEO_RETENTION_POLICY_LIMIT Default: 100
WorkEddy_VIDEO_RETENTION_ASSESSMENT_LIMIT Default: 500
WorkEddy_CORRECTIVE_ACTION_MAINTENANCE_LIMIT Default: 100
WorkEddy_CORRECTIVE_ACTION_MAINTENANCE_DATE Optional YYYY-MM-DD override
WorkEddy_CORRECTIVE_ACTION_SKIP_SEED Default: 0; when 1, skips idempotent default library/rule seeding
WorkEddy_SUBSCRIPTION_RENEWAL_SWEEP_LIMIT Default: 100; max subscriptions renewed per run
WorkEddy_SUBSCRIPTION_DUNNING_SWEEP_LIMIT Default: 100; max overdue invoices inspected per run
TRANSPORT_EDGE_BASE_URL                    Edge runtime base URL for edge.primary
TRANSPORT_EDGE_ENDPOINT                    Default: /api/v1/transport/inbound
TRANSPORT_EDGE_AUTH_TYPE                   none, bearer, api_key, or basic
TRANSPORT_EDGE_SHARED_SECRET               Token/API key/password value; never logged raw
TRANSPORT_EDGE_ENABLED                     Default: 0
TRANSPORT_DISPATCH_LIMIT                   Default: 100
TRANSPORT_INBOX_PROCESSING_LIMIT           Default: 100
TRANSPORT_SKIP_DESTINATION_SYNC            Default: 0; when 1, command does not upsert config destinations
TRANSPORT_SKIP_SOURCE_SYNC                 Default: 0; when 1, command does not upsert config sources
```

Cron examples:

```cron
* * * * * cd /path/to/WorkEddy/app && php cronjobs/queue-work.php
* * * * * cd /path/to/WorkEddy/app && php cronjobs/transport-inbox-process.php
* * * * * cd /path/to/WorkEddy/app && php cronjobs/transport-outbox-dispatch.php
40 3 * * * cd /path/to/WorkEddy/app && php cronjobs/queue-maintenance.php
42 3 * * * cd /path/to/WorkEddy/app && php cronjobs/video-retention-enforce.php
43 3 * * * cd /path/to/WorkEddy/app && php cronjobs/corrective-action-maintenance.php
15 2 * * * cd /path/to/WorkEddy/app && php cronjobs/subscription-renewal-sweep.php
30 2 * * * cd /path/to/WorkEddy/app && php cronjobs/subscription-dunning-sweep.php
45 3 * * * cd /path/to/WorkEddyWorkEddy&& WorkEddy_RUNTIME_DOCTOR_STRICT=1 php cronjobs/runtime-doctor.php
```
