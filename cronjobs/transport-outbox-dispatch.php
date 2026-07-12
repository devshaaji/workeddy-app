#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap/autoload.php';

$command = [
    PHP_BINARY,
    dirname(__DIR__) . '/bin/console',
    'transport:outbox:dispatch',
];

if (($limit = getenv('TRANSPORT_DISPATCH_LIMIT')) !== false && $limit !== '') {
    $command[] = '--limit=' . $limit;
}

if (filter_var(getenv('TRANSPORT_SKIP_DESTINATION_SYNC') ?: '0', FILTER_VALIDATE_BOOL)) {
    $command[] = '--skip-destination-sync';
}

\WorkEddy\Platform\Cron\CronCommandRunner::run($command);
