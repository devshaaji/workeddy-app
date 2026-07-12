<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Console\Command;

use WorkEddy\Platform\Config\RuntimeEnvironmentValidator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'ops:runtime:doctor', description: 'Validate WorkEddy runtime environment and deployment readiness.')]
final class RuntimeDoctorCommand extends Command
{
    public function __construct(private readonly RuntimeEnvironmentValidator $validator)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('strict', null, InputOption::VALUE_NONE, 'Treat warnings as failures and check live database schema.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $strict = (bool) $input->getOption('strict');
        $result = $this->validator->diagnose($strict);

        $output->writeln(json_encode($result->toArray($strict), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return $result->passed($strict) ? Command::SUCCESS : Command::FAILURE;
    }
}
