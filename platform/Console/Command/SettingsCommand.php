<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Console\Command;

use WorkEddy\Platform\Settings\SettingsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'settings', description: 'Manage WorkEddy settings across all modules.')]
final class SettingsCommand extends Command
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('action', InputArgument::REQUIRED, 'Action: list, get, set, sync, sync-all')
            ->addArgument('key', InputArgument::OPTIONAL, 'Qualified setting key (e.g. billing.org_name)')
            ->addArgument('value', InputArgument::OPTIONAL, 'Value to set')
            ->addOption('module', 'm', InputOption::VALUE_REQUIRED, 'Filter by module name')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format: table or json', 'table');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $action = (string) $input->getArgument('action');
        $module = $input->getOption('module');
        $format = (string) $input->getOption('format');

        return match ($action) {
            'list' => $this->doList($output, $module, $format),
            'get' => $this->doGet($input, $output, $format),
            'set' => $this->doSet($input, $output),
            'sync' => $this->doSync($output, $module),
            'sync-all' => $this->doSyncAll($output),
            default => $this->showHelp($output, $action),
        };
    }

    private function doList(OutputInterface $output, ?string $module, string $format): int
    {
        $registry = $this->settings->getRegistry();
        if ($registry === null) {
            $output->writeln('<error>Settings registry is not available.</error>');
            return Command::FAILURE;
        }

        $items = [];
        foreach ($registry->all() as $key => $definition) {
            if ($module !== null && $definition->module !== $module) {
                continue;
            }
            $current = $this->settings->get($key);
            $items[] = [
                'key' => $key,
                'module' => $definition->module,
                'setting_key' => $definition->key,
                'label' => $definition->label,
                'type' => $definition->type->value,
                'value' => $definition->sensitive ? '(hidden)' : ($current ?? ''),
                'default' => $definition->sensitive ? '(hidden)' : ($definition->default ?? ''),
                'editable' => $definition->editable ? 'yes' : 'no',
                'restart_required' => $definition->restartRequired ? 'yes' : 'no',
            ];
        }

        if ($format === 'json') {
            $output->writeln(json_encode($items, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } else {
            $output->writeln('');
            $output->writeln(sprintf('<comment>%-50s %-12s %-20s</comment>', 'Key', 'Type', 'Value'));
            $output->writeln(str_repeat('-', 90));
            foreach ($items as $item) {
                $val = $item['value'] === '' ? '<fg=gray>(empty)</>' : $item['value'];
                $output->writeln(sprintf('%-50s %-12s %-20s', $item['key'], $item['type'], $val));
            }
            $output->writeln('');
            $output->writeln('Total: ' . count($items) . ' setting(s)');
        }

        return Command::SUCCESS;
    }

    private function doGet(InputInterface $input, OutputInterface $output, string $format): int
    {
        $key = $input->getArgument('key');
        if (!is_string($key) || $key === '') {
            $output->writeln('<error>Setting key is required for "get" action.</error>');
            return Command::FAILURE;
        }

        try {
            $value = $this->settings->get($key);
        } catch (\Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        if ($format === 'json') {
            $output->writeln(json_encode(['key' => $key, 'value' => $value], JSON_THROW_ON_ERROR));
        } else {
            $display = $value === null || $value === '' ? '<fg=gray>(empty)</>' : (string) $value;
            $output->writeln($key . ' = ' . $display);
        }

        return Command::SUCCESS;
    }

    private function doSet(InputInterface $input, OutputInterface $output): int
    {
        $key = $input->getArgument('key');
        $value = $input->getArgument('value');
        if (!is_string($key) || $key === '') {
            $output->writeln('<error>Setting key is required for "set" action.</error>');
            return Command::FAILURE;
        }
        if (!is_string($value)) {
            $value = (string) $value;
        }

        try {
            $this->settings->validate($key, $value);
            $this->settings->set($key, $value, 'console');
        } catch (\Throwable $e) {
            $output->writeln('<error>Failed to set ' . $key . ': ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>Setting ' . $key . ' updated to "' . $value . '".</info>');
        return Command::SUCCESS;
    }

    private function doSync(OutputInterface $output, ?string $module): int
    {
        $registry = $this->settings->getRegistry();
        if ($registry === null) {
            $output->writeln('<error>Settings registry is not available.</error>');
            return Command::FAILURE;
        }

        $synced = 0;
        foreach ($registry->all() as $key => $definition) {
            if ($module !== null && $definition->module !== $module) {
                continue;
            }
            $current = $this->settings->get($key);
            if ($current === null || $current === '') {
                $this->settings->set($key, $definition->default, 'console');
                $synced++;
                $output->writeln('  Synced ' . $key . ' => ' . ($definition->default ?? '(empty)'));
            }
        }

        $output->writeln('<info>Synced ' . $synced . ' setting(s) to defaults.</info>');
        return Command::SUCCESS;
    }

    private function doSyncAll(OutputInterface $output): int
    {
        return $this->doSync($output, null);
    }

    private function showHelp(OutputInterface $output, string $action): int
    {
        $output->writeln('<error>Unknown action: ' . $action . '</error>');
        $output->writeln('');
        $output->writeln('Available actions:');
        $output->writeln('  list              List all registered settings');
        $output->writeln('  list --module=X   List settings for a specific module');
        $output->writeln('  get <key>         Get a single setting value');
        $output->writeln('  set <key> <value> Update a single setting');
        $output->writeln('  sync --module=X   Sync missing settings to defaults for a module');
        $output->writeln('  sync-all          Sync all missing settings across all modules');
        return Command::FAILURE;
    }
}
