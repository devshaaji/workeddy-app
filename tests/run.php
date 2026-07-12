<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$binary = __DIR__ . '/../vendor/phpunit/phpunit/phpunit';
$config = __DIR__ . '/../phpunit.xml';

$command = sprintf(
    'php %s -c %s',
    escapeshellarg($binary),
    escapeshellarg($config),
);

passthru($command, $exitCode);
exit($exitCode);
