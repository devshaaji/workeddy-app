<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Console\Command;

use WorkEddy\Platform\Config\ConfigLoader;
use WorkEddy\Platform\Queue\IQueueService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'queue:status', description: 'Show platform queue status counts.')]
final class QueueStatusCommand extends Command
{
    public function __construct(
        private readonly IQueueService $queue,
        private readonly ConfigLoader $config,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('queue', InputArgument::OPTIONAL, 'Queue name to inspect.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $queueName = $input->getArgument('queue');
        $queueName = is_string($queueName) && $queueName !== '' ? $queueName : (string) $this->config->get('queue.default_queue', 'default');
        $output->writeln(json_encode([
            'queue' => $queueName,
            'counts' => $this->queue->counts($queueName),
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return Command::SUCCESS;
    }
}
