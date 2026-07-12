<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Console;

final class SymfonyConsoleFactory
{
    public function create(string $name = 'WorkEddy Runtime'): object
    {
        if (!class_exists(\Symfony\Component\Console\Application::class)) {
            throw new \RuntimeException('Symfony Console is not installed. Run composer install before using console commands.');
        }

        return new \Symfony\Component\Console\Application($name);
    }
}
