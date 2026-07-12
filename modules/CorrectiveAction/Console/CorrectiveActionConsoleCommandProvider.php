<?php

declare(strict_types=1);

namespace WorkEddy\Modules\CorrectiveAction\Console;

use WorkEddy\Platform\Console\ConsoleCommandDefinition;
use WorkEddy\Platform\Console\IConsoleCommandProvider;

final class CorrectiveActionConsoleCommandProvider implements IConsoleCommandProvider
{
    public function commands(): array
    {
        return [
            new ConsoleCommandDefinition(
                name: 'corrective-action:maintenance',
                description: 'Seed defaults, mark overdue corrective actions, and emit follow-up due events.',
                handlerClass: CorrectiveActionMaintenanceCommand::class,
            ),
        ];
    }
}
