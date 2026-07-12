<?php

declare(strict_types=1);

$candidates = [
    dirname(__DIR__) . '/vendor/autoload.php',
    dirname(__DIR__, 3) . '/browsemx/vendor/autoload.php',
];

foreach ($candidates as $candidate) {
    if (is_file($candidate)) {
        require_once $candidate;

        return;
    }
}

throw new RuntimeException('Composer autoload file was not found for this checkout or the shared main workspace.');
