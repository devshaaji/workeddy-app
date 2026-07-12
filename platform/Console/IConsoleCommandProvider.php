<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Console;

interface IConsoleCommandProvider
{
    /**
     * @return list<ConsoleCommandDefinition>
     */
    public function commands(): array;
}
