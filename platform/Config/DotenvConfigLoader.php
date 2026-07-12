<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Config;

final class DotenvConfigLoader
{
    public static function load(string $appRoot): ConfigLoader
    {
        if (class_exists(\Dotenv\Dotenv::class) && is_file($appRoot . '/.env')) {
            \Dotenv\Dotenv::createImmutable($appRoot)->safeLoad();
        }

        return new ConfigLoader($appRoot . '/config');
    }
}
