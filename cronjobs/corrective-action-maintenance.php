#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap/autoload.php';

$command = [
    PHP_BINARY,
    dirname(__DIR__) . '/bin/console',
    'corrective-action:maintenance',
];

if (($limit = getenv('WorkEddy_CORRECTIVE_ACTION_MAINTENANCE_LIMIT')) !== false && $limit !== '') {
    $command[] = '--limit=' . $limit;
}

if (($date = getenv('WorkEddy_CORRECTIVE_ACTION_MAINTENANCE_DATE')) !== false && $date !== '') {
    $command[] = '--date=' . $date;
}

if (getenv('WorkEddy_CORRECTIVE_ACTION_SKIP_SEED') === '1') {
    $command[] = '--no-seed';
}

\WorkEddy\Platform\Cron\CronCommandRunner::run($command);
