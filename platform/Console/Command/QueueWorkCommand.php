<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Console\Command;

use WorkEddy\Platform\Config\ConfigLoader;
use WorkEddy\Platform\Queue\QueueHandlerRegistry;
use WorkEddy\Platform\Queue\QueueWorker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'queue:work', description: 'Process queued platform jobs.')]
final class QueueWorkCommand extends Command
{
    public function __construct(
        private readonly QueueWorker $worker,
        private readonly QueueHandlerRegistry $handlers,
        private readonly CommandLockRunner $locks,
        private readonly ConfigLoader $config,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('queue', InputArgument::OPTIONAL, 'Queue name to process.');
        $this->addOption('worker-id', null, InputOption::VALUE_REQUIRED, 'Stable worker identifier.');
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum jobs to process.');
        $this->addOption('lock-seconds', null, InputOption::VALUE_REQUIRED, 'Per-job lock TTL in seconds.');
        $this->addOption('retry-delay-seconds', null, InputOption::VALUE_REQUIRED, 'Delay before retrying failed jobs.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $queue = (string) ($input->getArgument('queue') ?: $this->config->get('queue.default_queue', 'default'));
        $workerId = (string) ($input->getOption('worker-id') ?: 'WorkEddy-queue-worker:' . getmypid());
        $limit = (int) ($input->getOption('limit') ?: $this->config->get('queue.worker_limit', 25));
        $lockSeconds = (int) ($input->getOption('lock-seconds') ?: $this->config->get('queue.lock_seconds', 120));
        $retryDelaySeconds = (int) ($input->getOption('retry-delay-seconds') ?: $this->config->get('queue.retry_delay_seconds', 60));

        return $this->locks->run($this->locks->scopedResource('console:queue:work', $queue), max(30, $lockSeconds), function () use ($queue, $workerId, $limit, $lockSeconds, $retryDelaySeconds, $output): int {
            if ($this->handlers->isEmpty()) {
                $output->writeln(json_encode([
                    'success' => false,
                    'queue' => $queue,
                    'worker_id' => $workerId,
                    'error' => 'No queue job handlers are registered; refusing to claim jobs.',
                ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

                return Command::FAILURE;
            }

            $output->writeln(json_encode(
                $this->worker->work($queue, $workerId, $limit, $lockSeconds, $retryDelaySeconds),
                JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR,
            ));

            return Command::SUCCESS;
        });
    }
}
