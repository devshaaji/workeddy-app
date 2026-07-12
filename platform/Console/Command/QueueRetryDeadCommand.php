<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Console\Command;

use WorkEddy\Platform\Config\ConfigLoader;
use WorkEddy\Platform\Queue\IQueueService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'queue:retry-dead', description: 'Move dead platform jobs back to queued state.')]
final class QueueRetryDeadCommand extends Command
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
        $this->addArgument('queue', InputArgument::OPTIONAL, 'Queue name to retry.');
        $this->getDefinition()->addOption(
            new InputOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum dead jobs to retry.', 25)
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $queueName = (string) ($input->getArgument('queue') ?: $this->config->get('queue.default_queue', 'default'));
        $limit = (int) $input->getOption('limit');

        return $this->locks->run($this->locks->scopedResource('console:queue:retry-dead', $queueName), 300, function () use ($queueName, $limit, $output): int {
            $output->writeln(json_encode([
                'queue' => $queueName,
                'retried' => $this->queue->retryDead($queueName, max(1, min(500, $limit))),
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return Command::SUCCESS;
        });
    }
}
