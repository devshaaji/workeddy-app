<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Console;

final class ConsoleCommandDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly string $handlerClass,
    ) {}
}
