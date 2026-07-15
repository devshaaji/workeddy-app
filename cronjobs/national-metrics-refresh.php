#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap/autoload.php';

$command = [
    PHP_BINARY,
    dirname(__DIR__) . '/bin/console',
    'national-metrics:refresh',
];

\WorkEddy\Platform\Cron\CronCommandRunner::run($command);
