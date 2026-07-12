#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap/autoload.php';

$command = [
    PHP_BINARY,
    dirname(__DIR__) . '/bin/console',
    'queue:maintenance',
];

if (($limit = getenv('WorkEddy_QUEUE_MAINTENANCE_LIMIT')) !== false && $limit !== '') {
    $command[] = '--limit=' . $limit;
}

\WorkEddy\Platform\Cron\CronCommandRunner::run($command);
