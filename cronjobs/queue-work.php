#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap/autoload.php';

$command = [
    PHP_BINARY,
    dirname(__DIR__) . '/bin/console',
    'queue:work',
    getenv('WorkEddy_QUEUE_DEFAULT') ?: 'default',
];

if (($limit = getenv('WorkEddy_QUEUE_WORKER_LIMIT')) !== false && $limit !== '') {
    $command[] = '--limit=' . $limit;
}

if (($workerId = getenv('WorkEddy_QUEUE_WORKER_ID')) !== false && $workerId !== '') {
    $command[] = '--worker-id=' . $workerId;
}

\WorkEddy\Platform\Cron\CronCommandRunner::run($command);
