<?php

declare(strict_types=1);

namespace WorkEddy\Modules\CorrectiveAction\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use WorkEddy\Modules\CorrectiveAction\Application\RunCorrectiveActionMaintenanceUseCase;
use WorkEddy\Modules\CorrectiveAction\Application\SeedCorrectiveActionDefaultsUseCase;
use WorkEddy\Platform\Console\Command\CommandLockRunner;

#[AsCommand(name: 'corrective-action:maintenance', description: 'Seed defaults, mark overdue corrective actions, and emit follow-up due events.')]
final class CorrectiveActionMaintenanceCommand extends Command
{
    public function __construct(
        private readonly SeedCorrectiveActionDefaultsUseCase $seedDefaults,
        private readonly RunCorrectiveActionMaintenanceUseCase $maintenance,
        private readonly CommandLockRunner $locks,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('no-seed', null, InputOption::VALUE_NONE, 'Skip idempotent default library/rule seeding.')
            ->addOption('no-maintenance', null, InputOption::VALUE_NONE, 'Skip overdue/follow-up maintenance.')
            ->addOption('date', null, InputOption::VALUE_REQUIRED, 'Date to evaluate, format YYYY-MM-DD.', date('Y-m-d'))
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum overdue actions and follow-ups to inspect per run.', 100);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $date = (string) $input->getOption('date');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $output->writeln('<error>Invalid --date. Use YYYY-MM-DD.</error>');
            return Command::INVALID;
        }
        $limit = max(1, min(5000, (int) $input->getOption('limit')));

        return $this->locks->run('console:corrective-action:maintenance', 900, function () use ($input, $output, $date, $limit): int {
            $result = [
                'success' => true,
                'seed' => ['library_items' => 0, 'recommendation_rules' => 0],
                'maintenance' => ['overdue_actions' => 0, 'follow_ups_due' => 0],
            ];

            if (!$input->getOption('no-seed')) {
                $result['seed'] = $this->seedDefaults->execute();
            }

            if (!$input->getOption('no-maintenance')) {
                $result['maintenance'] = $this->maintenance->execute($date, $limit);
            }

            $output->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            return Command::SUCCESS;
        });
    }
}
