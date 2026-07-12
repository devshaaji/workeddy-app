<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Container;

use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;

final class PhpDiContainerFactory
{
    /**
     * @param array<class-string|string, mixed> $definitions
     */
    public function create(array $definitions, ?string $compileDir = null): ContainerInterface
    {
        $builder = new ContainerBuilder();
        $builder->useAutowiring(true);

        if ($compileDir !== null) {
            if (!is_dir($compileDir)) {
                mkdir($compileDir, 0775, true);
            }

            $builder->enableCompilation($compileDir);
        }

        $builder->addDefinitions($definitions);

        return $builder->build();
    }
}
