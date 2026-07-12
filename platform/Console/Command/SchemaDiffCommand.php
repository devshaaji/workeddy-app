<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Console\Command;

use WorkEddy\Platform\Schema\CanonicalSchemaBuilder;
use WorkEddy\Platform\Schema\SchemaDiffSqlGenerator;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'schema:diff', description: 'Compare the live database schema against the canonical WorkEddy schema.')]
final class SchemaDiffCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        private readonly CanonicalSchemaBuilder $schemaBuilder,
        private readonly SchemaDiffSqlGenerator $diffGenerator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('unsafe', null, InputOption::VALUE_NONE, 'Show full SQL diff, including destructive statements.')
            ->addOption('write-sql', null, InputOption::VALUE_OPTIONAL, 'Write the schema diff SQL to a file path.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sql = $this->diffGenerator->diff(
            $this->connection,
            $this->schemaBuilder->buildAll(),
            !((bool) $input->getOption('unsafe')),
        );

        $writePath = $input->getOption('write-sql');
        if (is_string($writePath) && trim($writePath) !== '') {
            file_put_contents($writePath, implode(PHP_EOL, $sql) . PHP_EOL);
            $output->writeln('Schema diff SQL written to ' . $writePath);
        }

        if ($sql === []) {
            $output->writeln('Schema is up to date.');
            return Command::SUCCESS;
        }

        foreach ($sql as $statement) {
            $output->writeln($statement . ';');
        }

        return Command::FAILURE;
    }
}
