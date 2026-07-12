<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Console\Command;

use Doctrine\DBAL\Connection;
use WorkEddy\Platform\Seeding\SeederRunner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class DbSeedCommand extends Command
{
    public function __construct(
        private readonly SeederRunner $runner,
        private readonly Connection $db,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('db:seed')
            ->setDescription('Run database seeders.')
            ->addOption('filter', 'f', InputOption::VALUE_OPTIONAL, 'Only run seeders whose filename contains this string.')
            ->addOption('list', 'l', InputOption::VALUE_NONE, 'List available seeders without running them.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filter = $input->getOption('filter') ?: null;

        if ($input->getOption('list')) {
            foreach ($this->runner->list($filter) as $name) {
                $output->writeln($name);
            }
            return Command::SUCCESS;
        }

        $ran = $this->runner->run($this->db, $filter);

        if ($ran === []) {
            $output->writeln('<comment>No seeders matched.</comment>');
            return Command::SUCCESS;
        }

        foreach ($ran as $name) {
            $output->writeln("<info>Seeded:</info> {$name}");
        }

        $output->writeln(sprintf('<info>Done.</info> %d seeder(s) applied.', count($ran)));

        return Command::SUCCESS;
    }
}
