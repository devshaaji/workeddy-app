#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap/autoload.php';

$command = [
    PHP_BINARY,
    dirname(__DIR__) . '/bin/console',
    'subscription:dunning:sweep',
];

if (($limit = getenv('WorkEddy_SUBSCRIPTION_DUNNING_SWEEP_LIMIT')) !== false && $limit !== '') {
    $command[] = '--limit=' . $limit;
}

\WorkEddy\Platform\Cron\CronCommandRunner::run($command);
