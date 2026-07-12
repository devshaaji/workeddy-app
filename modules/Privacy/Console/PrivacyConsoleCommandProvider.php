<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Privacy\Console;

use WorkEddy\Platform\Console\ConsoleCommandDefinition;
use WorkEddy\Platform\Console\IConsoleCommandProvider;

final class PrivacyConsoleCommandProvider implements IConsoleCommandProvider
{
    public function commands(): array
    {
        return [
            new ConsoleCommandDefinition(
                name: 'privacy:video-retention:enforce',
                description: 'Enforce configured video retention policies for completed assessment videos.',
                handlerClass: EnforceVideoRetentionCommand::class,
            ),
        ];
    }
}
