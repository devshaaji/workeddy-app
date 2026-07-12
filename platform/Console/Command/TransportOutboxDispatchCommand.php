<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Console\Command;

use WorkEddy\Platform\Config\ConfigLoader;
use WorkEddy\Platform\Transport\Outbound\TransportDestinationConfigSeeder;
use WorkEddy\Platform\Transport\Outbound\TransportOutboxDispatcher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'transport:outbox:dispatch', description: 'Dispatch due outbound transport messages.')]
final class TransportOutboxDispatchCommand extends Command
{
    public function __construct(
        private readonly TransportOutboxDispatcher $dispatcher,
        private readonly TransportDestinationConfigSeeder $destinations,
        private readonly CommandLockRunner $locks,
        private readonly ConfigLoader $config,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum due messages to dispatch.');
        $this->addOption('skip-destination-sync', null, InputOption::VALUE_NONE, 'Do not upsert destinations from config before dispatching.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = (int) ($input->getOption('limit') ?: $this->config->get('transport.dispatch_limit', 100));

        return $this->locks->run('console:transport:outbox:dispatch', 300, function () use ($input, $limit, $output): int {
            $seeded = $input->getOption('skip-destination-sync') ? 0 : $this->destinations->seed();
            $report = $this->dispatcher->runOnce(max(1, min(1000, $limit)));

            $output->writeln(json_encode([
                'success' => $report->errors === [],
                'destinations_synced' => $seeded,
                'claimed' => $report->claimed,
                'delivered' => $report->delivered,
                'failed' => $report->failed,
                'retried' => $report->retried,
                'fallbacks' => $report->fallbacks,
                'errors' => $report->errors,
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return $report->errors === [] ? Command::SUCCESS : Command::FAILURE;
        });
    }
}
