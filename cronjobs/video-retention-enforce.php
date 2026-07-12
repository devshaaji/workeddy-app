#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap/autoload.php';

$command = [
    PHP_BINARY,
    dirname(__DIR__) . '/bin/console',
    'privacy:video-retention:enforce',
];

if (($limit = getenv('WorkEddy_VIDEO_RETENTION_POLICY_LIMIT')) !== false && $limit !== '') {
    $command[] = '--limit=' . $limit;
}

if (($limit = getenv('WorkEddy_VIDEO_RETENTION_ASSESSMENT_LIMIT')) !== false && $limit !== '') {
    $command[] = '--assessment-limit=' . $limit;
}

\WorkEddy\Platform\Cron\CronCommandRunner::run($command);
