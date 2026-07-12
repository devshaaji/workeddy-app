<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Database;

use WorkEddy\Platform\Config\ConfigLoader;

final class MigrationConfigurationFactory
{
    public function __construct(private readonly ConfigLoader $config) {}

    /**
     * @return array<string, mixed>
     */
    public function configuration(): array
    {
        $configuration = $this->config->all('migrations');

        return $configuration !== [] ? $configuration : [
            'table_storage' => ['table_name' => 'doctrine_migration_versions'],
            'migrations_paths' => ['WorkEddy\\Migrations' => dirname(__DIR__, 2) . '/migrations'],
            'all_or_nothing' => true,
            'check_database_platform' => true,
        ];
    }
}
