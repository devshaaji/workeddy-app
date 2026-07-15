<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Reporting\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use WorkEddy\Modules\Reporting\Application\Services\PlatformAggregateMetricsService;
use WorkEddy\Platform\Console\Command\CommandLockRunner;

/**
 * Recomputes the platform-wide (cross-organization) metrics shown on the
 * National Importance dashboard and appends a new dated snapshot row for
 * each. Intended to run nightly via cronjobs/national-metrics-refresh.php —
 * see PlatformAggregateMetricsService for why this is cached rather than
 * computed on page load.
 */
#[AsCommand(name: 'national-metrics:refresh', description: 'Recompute platform-wide National Importance dashboard metrics.')]
final class NationalMetricsRefreshCommand extends Command
{
    public function __construct(
        private readonly PlatformAggregateMetricsService $platformMetrics,
        private readonly CommandLockRunner $locks,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        unset($input);

        return $this->locks->run('console:national-metrics:refresh', 600, function () use ($output): int {
            $this->platformMetrics->refresh();
            $output->writeln(json_encode(['success' => true, 'refreshedAt' => gmdate('Y-m-d H:i:s')], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return Command::SUCCESS;
        });
    }
}
