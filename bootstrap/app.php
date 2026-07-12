<?php

declare(strict_types=1);

use WorkEddy\Platform\Container\PhpDiContainerFactory;
use WorkEddy\Platform\Config\EnvironmentBootstrap;


require_once __DIR__ . '/autoload.php';

EnvironmentBootstrap::load(dirname(__DIR__));

$envBool = static function (string $key, bool $default): bool {
    $value = $_ENV[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
};

$compileDir = $envBool('WorkEddy_CONTAINER_COMPILE', false)
    ? dirname(__DIR__) . '/storage/cache/container'
    : null;

return (new PhpDiContainerFactory())->create(require __DIR__ . '/container.php', $compileDir);
