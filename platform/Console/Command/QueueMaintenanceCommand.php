<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Console\Command;

use WorkEddy\Platform\Config\ConfigLoader;
use WorkEddy\Platform\Queue\IQueueService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'queue:maintenance', description: 'Release stale platform queue worker locks.')]
final class QueueMaintenanceCommand extends Command
{
    public function __construct(
        private readonly IQueueService $queue,
        private readonly CommandLockRunner $locks,
        private readonly ConfigLoader $config,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum stale locks to release.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = (int) ($input->getOption('limit') ?: $this->config->get('queue.maintenance_limit', 100));

        return $this->locks->run('console:queue:maintenance', 300, function () use ($limit, $output): int {
            $output->writeln(json_encode([
                'stale_locks_released' => $this->queue->releaseStaleLocks(max(1, min(500, $limit))),
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return Command::SUCCESS;
        });
    }
}
