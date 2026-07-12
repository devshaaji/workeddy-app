<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Console\Command;

use WorkEddy\Modules\IAM\Application\Services\PermissionCatalogSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'iam:permissions:sync', description: 'Sync all module permission definitions into IAM persistence.')]
final class PermissionSyncCommand extends Command
{
    public function __construct(
        private readonly PermissionCatalogSyncService $syncService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = $this->syncService->sync();

        $output->writeln(sprintf('Synced %d permission definition%s.', $count, $count === 1 ? '' : 's'));

        return Command::SUCCESS;
    }
}
