<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Console\Command;

use WorkEddy\Platform\Config\ConfigLoader;
use WorkEddy\Platform\Transport\Inbound\TransportInboxProcessor;
use WorkEddy\Platform\Transport\Inbound\TransportInboundSourceConfigSeeder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'transport:inbox:process', description: 'Process pending inbound transport messages.')]
final class TransportInboxProcessCommand extends Command
{
    public function __construct(
        private readonly TransportInboxProcessor $processor,
        private readonly TransportInboundSourceConfigSeeder $sources,
        private readonly CommandLockRunner $locks,
        private readonly ConfigLoader $config,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum pending inbox messages to process.');
        $this->addOption('skip-source-sync', null, InputOption::VALUE_NONE, 'Do not upsert inbound sources from config before processing.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = (int) ($input->getOption('limit') ?: $this->config->get('transport.inbox_processing_limit', 100));

        return $this->locks->run('console:transport:inbox:process', 300, function () use ($input, $limit, $output): int {
            $seeded = $input->getOption('skip-source-sync') ? 0 : $this->sources->seed();
            $report = $this->processor->processPending(max(1, min(1000, $limit)));

            $output->writeln(json_encode([
                'success' => $report->errors === [],
                'sources_synced' => $seeded,
                'claimed' => $report->claimed,
                'processed' => $report->processed,
                'failed' => $report->failed,
                'retried' => $report->retried,
                'rejected' => $report->rejected,
                'acks_published' => $report->acksPublished,
                'errors' => $report->errors,
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return $report->errors === [] ? Command::SUCCESS : Command::FAILURE;
        });
    }
}
