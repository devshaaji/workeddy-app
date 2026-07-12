<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Console;

use WorkEddy\Modules\Analytics\Console\AnalyticsBackfillCommand;
use WorkEddy\Modules\Analytics\Console\AnalyticsRollupRefreshCommand;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\PhpFile;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Tools\Console\Command\CurrentCommand;
use Doctrine\Migrations\Tools\Console\Command\DumpSchemaCommand;
use Doctrine\Migrations\Tools\Console\Command\ExecuteCommand;
use Doctrine\Migrations\Tools\Console\Command\GenerateCommand;
use Doctrine\Migrations\Tools\Console\Command\LatestCommand;
use Doctrine\Migrations\Tools\Console\Command\ListCommand as MigrationListCommand;
use Doctrine\Migrations\Tools\Console\Command\MigrateCommand;
use Doctrine\Migrations\Tools\Console\Command\RollupCommand;
use Doctrine\Migrations\Tools\Console\Command\StatusCommand;
use Doctrine\Migrations\Tools\Console\Command\SyncMetadataCommand;
use Doctrine\Migrations\Tools\Console\Command\UpToDateCommand;
use Doctrine\Migrations\Tools\Console\Command\VersionCommand;
use WorkEddy\Platform\Config\ConfigLoader;
use WorkEddy\Platform\Config\EnvironmentBootstrap;
use WorkEddy\Platform\Config\RuntimeEnvironmentValidator;
use WorkEddy\Platform\Console\Command\CommandLockRunner;
use WorkEddy\Platform\Console\Command\ModuleCompletionCommand;
use WorkEddy\Platform\Console\Command\QueueMaintenanceCommand;
use WorkEddy\Platform\Console\Command\QueueRetryDeadCommand;
use WorkEddy\Platform\Console\Command\QueueStatusCommand;
use WorkEddy\Platform\Console\Command\QueueWorkCommand;
use WorkEddy\Platform\Console\Command\RuntimeDoctorCommand;
use WorkEddy\Platform\Console\Command\PermissionSyncCommand;
use WorkEddy\Platform\Console\Command\SchemaDiffCommand;
use WorkEddy\Platform\Console\Command\TransportInboxProcessCommand;
use WorkEddy\Platform\Console\Command\TransportOutboxDispatchCommand;
use WorkEddy\Platform\Console\Command\DbSeedCommand;
use WorkEddy\Platform\Console\Command\SettingsCommand;
use WorkEddy\Platform\Seeding\SeederRunner;
use WorkEddy\Platform\Queue\IQueueService;
use WorkEddy\Platform\Queue\QueueHandlerRegistry;
use WorkEddy\Platform\Queue\QueueWorker;
use WorkEddy\Platform\Schema\CanonicalSchemaBuilder;
use WorkEddy\Platform\Schema\SchemaDiffSqlGenerator;
use WorkEddy\Platform\Settings\SettingsService;
use WorkEddy\Platform\Transport\Inbound\TransportInboundSourceConfigSeeder;
use WorkEddy\Platform\Transport\Inbound\TransportInboxProcessor;
use WorkEddy\Platform\Transport\Outbound\TransportDestinationConfigSeeder;
use WorkEddy\Platform\Transport\Outbound\TransportOutboxDispatcher;
use Doctrine\DBAL\Connection;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LazyCommand;

final class ConsoleApplicationFactory
{
    /**
     * @param callable(): ContainerInterface $containerFactory
     */
    public static function create(callable $containerFactory): Application
    {
        $application = new Application('WorkEddy Runtime Console', '1.0');
        $container = null;
        $getContainer = static function () use (&$container, $containerFactory): ContainerInterface {
            if (!$container instanceof ContainerInterface) {
                $container = $containerFactory();
            }

            return $container;
        };

        $commands = [
            new LazyCommand(
                'queue:work',
                [],
                'Process queued platform jobs.',
                false,
                static fn(): Command => new QueueWorkCommand(
                    $getContainer()->get(QueueWorker::class),
                    $getContainer()->get(QueueHandlerRegistry::class),
                    $getContainer()->get(CommandLockRunner::class),
                    $getContainer()->get(ConfigLoader::class),
                ),
            ),
            new LazyCommand(
                'queue:status',
                [],
                'Show platform queue status counts.',
                false,
                static fn(): Command => new QueueStatusCommand(
                    $getContainer()->get(IQueueService::class),
                    $getContainer()->get(ConfigLoader::class),
                ),
            ),
            new LazyCommand(
                'queue:retry-dead',
                [],
                'Move dead platform jobs back to queued state.',
                false,
                static fn(): Command => new QueueRetryDeadCommand(
                    $getContainer()->get(IQueueService::class),
                    $getContainer()->get(CommandLockRunner::class),
                    $getContainer()->get(ConfigLoader::class),
                ),
            ),
            new LazyCommand(
                'queue:maintenance',
                [],
                'Release stale platform queue worker locks.',
                false,
                static fn(): Command => new QueueMaintenanceCommand(
                    $getContainer()->get(IQueueService::class),
                    $getContainer()->get(CommandLockRunner::class),
                    $getContainer()->get(ConfigLoader::class),
                ),
            ),
            new LazyCommand(
                'ops:runtime:doctor',
                [],
                'Validate WorkEddy runtime environment and deployment readiness.',
                false,
                static fn(): Command => self::runtimeDoctorCommand(),
            ),
            new LazyCommand(
                'schema:diff',
                [],
                'Compare the live database schema against the canonical WorkEddy schema.',
                false,
                static fn(): Command => new SchemaDiffCommand(
                    $getContainer()->get(Connection::class),
                    new CanonicalSchemaBuilder(),
                    new SchemaDiffSqlGenerator(),
                ),
            ),
            new LazyCommand(
                'transport:outbox:dispatch',
                [],
                'Dispatch due outbound transport messages.',
                false,
                static fn(): Command => new TransportOutboxDispatchCommand(
                    $getContainer()->get(TransportOutboxDispatcher::class),
                    $getContainer()->get(TransportDestinationConfigSeeder::class),
                    $getContainer()->get(CommandLockRunner::class),
                    $getContainer()->get(ConfigLoader::class),
                ),
            ),
            new LazyCommand(
                'transport:inbox:process',
                [],
                'Process pending inbound transport messages.',
                false,
                static fn(): Command => new TransportInboxProcessCommand(
                    $getContainer()->get(TransportInboxProcessor::class),
                    $getContainer()->get(TransportInboundSourceConfigSeeder::class),
                    $getContainer()->get(CommandLockRunner::class),
                    $getContainer()->get(ConfigLoader::class),
                ),
            ),
            new LazyCommand(
                'iam:permissions:sync',
                [],
                'Sync all module permission definitions into IAM persistence.',
                false,
                static fn(): Command => new PermissionSyncCommand(
                    $getContainer()->get(\WorkEddy\Modules\IAM\Application\Services\PermissionCatalogSyncService::class),
                ),
            ),
            new LazyCommand(
                'settings',
                [],
                'Manage WorkEddy settings across all modules.',
                false,
                static fn(): Command => new SettingsCommand(
                    $getContainer()->get(SettingsService::class),
                ),
            ),
            new LazyCommand(
                'db:seed',
                [],
                'Run database seeders.',
                false,
                static fn(): Command => new DbSeedCommand(
                    new SeederRunner(dirname(__DIR__, 2) . '/seeds'),
                    $getContainer()->get(Connection::class),
                ),
            ),
            new LazyCommand(
                'ops:modules:completion',
                [],
                'Report WorkEddy module completion status.',
                false,
                static fn(): Command => new ModuleCompletionCommand(dirname(__DIR__, 2)),
            ),
        ];

        // Load dynamic commands from modules
        $moduleRegistry = $getContainer()->get(\WorkEddy\Platform\Module\ModuleRegistry::class);
        foreach ($moduleRegistry->consoleCommandProviders() as $provider) {
            foreach ($provider->commands() as $def) {
                $commands[] = new LazyCommand(
                    $def->name,
                    [],
                    $def->description,
                    false,
                    static fn(): Command => $getContainer()->get($def->handlerClass),
                );
            }
        }

        $application->addCommands($commands);
        self::addMigrationCommands($application, $getContainer);

        return $application;
    }

    private static function runtimeDoctorCommand(): RuntimeDoctorCommand
    {
        $root = dirname(__DIR__, 2);
        EnvironmentBootstrap::load($root);

        return new RuntimeDoctorCommand(new RuntimeEnvironmentValidator(
            new ConfigLoader($root . '/config'),
            $root,
        ));
    }

    /**
     * @param callable(): ContainerInterface $containerFactory
     */
    private static function addMigrationCommands(Application $application, callable $containerFactory): void
    {
        $root = dirname(__DIR__, 2);
        $configPath = $root . '/config/migrations.php';
        if (!is_file($configPath)) {
            return;
        }

        $container = $containerFactory();
        $dependencyFactory = DependencyFactory::fromConnection(
            new PhpFile($configPath),
            new ExistingConnection($container->get(Connection::class)),
        );

        $application->addCommands([
            new CurrentCommand($dependencyFactory),
            new DumpSchemaCommand($dependencyFactory),
            new ExecuteCommand($dependencyFactory),
            new GenerateCommand($dependencyFactory),
            new LatestCommand($dependencyFactory),
            new MigrateCommand($dependencyFactory),
            new RollupCommand($dependencyFactory),
            new StatusCommand($dependencyFactory),
            new VersionCommand($dependencyFactory),
            new UpToDateCommand($dependencyFactory),
            new SyncMetadataCommand($dependencyFactory),
            new MigrationListCommand($dependencyFactory),
        ]);
    }
}
