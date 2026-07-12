#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap/autoload.php';

$command = [
    PHP_BINARY,
    dirname(__DIR__) . '/bin/console',
    'transport:inbox:process',
];

if (($limit = getenv('TRANSPORT_INBOX_PROCESSING_LIMIT')) !== false && $limit !== '') {
    $command[] = '--limit=' . $limit;
}

if (filter_var(getenv('TRANSPORT_SKIP_SOURCE_SYNC') ?: '0', FILTER_VALIDATE_BOOL)) {
    $command[] = '--skip-source-sync';
}

\WorkEddy\Platform\Cron\CronCommandRunner::run($command);
