<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Subscription\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use WorkEddy\Modules\Subscription\Application\UseCases\RunSubscriptionRenewalSweep;
use WorkEddy\Platform\Console\Command\CommandLockRunner;

#[AsCommand(name: 'subscription:renewal:sweep', description: 'Renews active, auto-renewing subscriptions whose current billing period has ended.')]
final class SubscriptionRenewalSweepCommand extends Command
{
    public function __construct(
        private readonly RunSubscriptionRenewalSweep $sweep,
        private readonly CommandLockRunner $locks,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum subscriptions to renew in one run.', 100);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = max(1, min(1000, (int) $input->getOption('limit')));

        return $this->locks->run('console:subscription:renewal:sweep', 900, function () use ($limit, $output): int {
            $result = $this->sweep->execute($limit);

            $output->writeln(json_encode(['success' => true] + $result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return Command::SUCCESS;
        });
    }
}
