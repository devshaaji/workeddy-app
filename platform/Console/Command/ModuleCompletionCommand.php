<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'ops:modules:completion', description: 'Report WorkEddy module completion status.')]
final class ModuleCompletionCommand extends Command
{
    /** @var array<string, array{directory: string, label: string}> */
    private const MODULES = [
        'iam' => ['directory' => 'IAM', 'label' => 'IAM'],
        'integration' => ['directory' => 'Integration', 'label' => 'Integration'],
        'audit' => ['directory' => 'Audit', 'label' => 'Audit'],
    ];

    public function __construct(private readonly string $root)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        unset($input);

        $completionDoc = $this->read($this->root . '/docs/MODULE_COMPLETION.md');
        $modules = [];
        $complete = 0;

        foreach (self::MODULES as $name => $module) {
            $readiness = $this->read($this->root . '/modules/' . $module['directory'] . '/PRODUCTION_READINESS.md');
            $isComplete = str_contains($completionDoc, '| ' . $module['label'] . ' | COMPLETE |')
                && str_contains($readiness, 'Completion Status')
                && str_contains($readiness, '**COMPLETE**');

            if ($isComplete) {
                $complete++;
            }

            $modules[] = [
                'module' => $name,
                'label' => $module['label'],
                'status' => $isComplete ? 'complete' : 'blocked',
            ];
        }

        $payload = [
            'success' => $complete === count(self::MODULES),
            'summary' => [
                'complete' => $complete,
                'total' => count(self::MODULES),
            ],
            'modules' => $modules,
        ];

        $output->writeln(json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return $payload['success'] ? Command::SUCCESS : Command::FAILURE;
    }

    private function read(string $path): string
    {
        $contents = is_file($path) ? file_get_contents($path) : false;

        return is_string($contents) ? $contents : '';
    }
}
