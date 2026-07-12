<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Config;

final class EnvironmentBootstrap
{
    public static function load(string $rootPath): void
    {
        if (!class_exists(\Dotenv\Dotenv::class) || !is_file(rtrim($rootPath, '/') . '/.env')) {
            return;
        }

        \Dotenv\Dotenv::createImmutable($rootPath)->safeLoad();

        $tz = $_ENV['APP_TIMEZONE'] ?? 'Africa/Lagos';
        date_default_timezone_set($tz);
    }
}
