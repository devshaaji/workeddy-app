<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Reporting\Console;

use WorkEddy\Platform\Console\ConsoleCommandDefinition;
use WorkEddy\Platform\Console\IConsoleCommandProvider;

final class ReportingConsoleCommandProvider implements IConsoleCommandProvider
{
    public function commands(): array
    {
        return [
            new ConsoleCommandDefinition(
                name: 'national-metrics:refresh',
                description: 'Recompute platform-wide National Importance dashboard metrics.',
                handlerClass: NationalMetricsRefreshCommand::class,
            ),
        ];
    }
}
