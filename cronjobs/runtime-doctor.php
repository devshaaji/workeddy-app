#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap/autoload.php';

$command = [
    PHP_BINARY,
    dirname(__DIR__) . '/bin/console',
    'ops:runtime:doctor',
];

if (filter_var(getenv('WorkEddy_RUNTIME_DOCTOR_STRICT') ?: '0', FILTER_VALIDATE_BOOL)) {
    $command[] = '--strict';
}

\WorkEddy\Platform\Cron\CronCommandRunner::run($command);
