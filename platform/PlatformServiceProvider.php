<?php

declare(strict_types=1);

namespace WorkEddy\Platform;


use WorkEddy\Platform\Cache\ICacheService;
use WorkEddy\Platform\Cache\SymfonyCacheService;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Platform\Clock\SystemClock;
use WorkEddy\Platform\Config\ConfigLoader;
use WorkEddy\Platform\Config\DotenvConfigLoader;
use WorkEddy\Platform\Console\Command\CommandLockRunner;
use WorkEddy\Platform\Console\ConsoleApplicationFactory;
use WorkEddy\Platform\Console\SymfonyConsoleFactory;
use WorkEddy\Platform\Container\PhpDiContainerFactory;
use WorkEddy\Platform\Database\ConnectionFactory;
use WorkEddy\Platform\Database\MigrationConfigurationFactory;
use WorkEddy\Platform\Events\EventPublisherInterface;
use WorkEddy\Platform\Events\InMemoryEventPublisher;
use WorkEddy\Platform\Http\FastRouteDispatcherFactory;
use WorkEddy\Platform\Identity\NativeUuidGenerator;
use WorkEddy\Platform\Identity\RamseyUuidGenerator;
use WorkEddy\Platform\Identity\UuidGeneratorContract;
use WorkEddy\Platform\Lock\LockManagerContract;
use WorkEddy\Platform\Lock\SymfonyLockManager;
use WorkEddy\Platform\Logging\ILoggerFactory;
use WorkEddy\Platform\Logging\MonologLoggerFactory;
use WorkEddy\Platform\Module\ModuleRegistry;
use WorkEddy\Platform\Module\ModuleServiceProviderInterface;
use WorkEddy\Platform\Queue\DbalQueueService;
use WorkEddy\Platform\Queue\InlineQueueAdapter;
use WorkEddy\Platform\Queue\IQueueService;
use WorkEddy\Platform\Queue\QueueHandlerRegistry;
use WorkEddy\Platform\Queue\QueueJobHandlerInterface;
use WorkEddy\Platform\Queue\QueueWorker;
use WorkEddy\Platform\RateLimiting\FixedWindowRateLimiter;
use WorkEddy\Platform\RateLimiting\RateLimiterContract;
use WorkEddy\Platform\Settings\DbalSettingsStore;
use WorkEddy\Platform\Settings\InMemorySettingsStore;
use WorkEddy\Platform\Settings\SettingsStoreContract;
use WorkEddy\Platform\Transport\DbalTransportStore;
use WorkEddy\Platform\Transport\Drivers\DatabaseTransportDriver;
use WorkEddy\Platform\Transport\Drivers\HttpPollingTransportDriver;
use WorkEddy\Platform\Transport\Drivers\HttpTransportDriver;
use WorkEddy\Platform\Transport\Drivers\NullTransportDriver;
use WorkEddy\Platform\Transport\Drivers\SseTransportDriver;
use WorkEddy\Platform\Transport\Drivers\WebSocketTransportDriver;
use WorkEddy\Platform\Transport\Inbound\DbalTransportInboxRepository;
use WorkEddy\Platform\Transport\Inbound\DbalTransportInboundSourceRepository;
use WorkEddy\Platform\Transport\Inbound\InboundSourceValidator;
use WorkEddy\Platform\Transport\Inbound\NoOpTransportMessageHandler;
use WorkEddy\Platform\Transport\Inbound\TransportInboxProcessor;
use WorkEddy\Platform\Transport\Inbound\TransportInboundSourceConfigSeeder;
use WorkEddy\Platform\Transport\Inbound\TransportInboxRepository;
use WorkEddy\Platform\Transport\Inbound\TransportInboundSourceRepository;
use WorkEddy\Platform\Transport\Inbound\TransportMessageHandlerInterface;
use WorkEddy\Platform\Transport\Inbound\TransportMessageHandlerProviderInterface;
use WorkEddy\Platform\Transport\Inbound\TransportMessageHandlerRegistry;
use WorkEddy\Platform\Transport\Inbound\TransportReceiverService;
use WorkEddy\Platform\Transport\Outbound\TransportDestinationConfigSeeder;
use WorkEddy\Platform\Transport\Outbound\TransportOutboxDispatcher;
use WorkEddy\Platform\Transport\Outbound\TransportSender;
use WorkEddy\Platform\Transport\Shared\HeaderSanitizer;
use WorkEddy\Platform\Transport\Shared\OutboundTransportAckPublisher;
use WorkEddy\Platform\Transport\Shared\PayloadSerializer;
use WorkEddy\Platform\Transport\Shared\RuntimeMessageSigner;
use WorkEddy\Platform\Transport\Shared\TransportAckPublisherInterface;
use WorkEddy\Platform\Transport\Shared\TransportCapabilityService;
use WorkEddy\Platform\Transport\TransportDispatcher;
use WorkEddy\Platform\Transport\TransportDriverRegistry;
use WorkEddy\Platform\Transport\TransportService;
use WorkEddy\Platform\Transport\TransportStoreInterface;
use WorkEddy\Platform\Transaction\DbalTransactionManager;
use WorkEddy\Platform\Transaction\PassthroughTransactionManager;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Platform\Transaction\TransactionManagerInterface;
use WorkEddy\Shared\Presentation\ViewRenderer;
use Psr\Container\ContainerInterface;

final class PlatformServiceProvider implements ModuleServiceProviderInterface
{
    public function getName(): string
    {
        return 'platform';
    }

    public function getDefinitions(): array
    {
        return [
            ConfigLoader::class => static fn(): ConfigLoader => DotenvConfigLoader::load(dirname(__DIR__)),
            IClock::class => static fn(ContainerInterface $c): IClock => new SystemClock(
                (string) $c->get(ConfigLoader::class)->get('app.timezone', 'Africa/Lagos')
            ),

            ConnectionFactory::class => static fn(ContainerInterface $c): ConnectionFactory => new ConnectionFactory($c->get(ConfigLoader::class)),
            'db' => static fn(ContainerInterface $c): object => $c->get(ConnectionFactory::class)->create(),
            \Doctrine\DBAL\Connection::class => \DI\get('db'),
            'analytics.db.read' => static fn(ContainerInterface $c): object => $c->get(ConnectionFactory::class)->createAnalyticsRead(),
            TransactionManagerInterface::class => static fn(ContainerInterface $c): TransactionManagerInterface => $c->has('db')
                ? new DbalTransactionManager($c->get('db'))
                : new PassthroughTransactionManager(),
            ICacheService::class => static fn(ContainerInterface $c): ICacheService => SymfonyCacheService::fromConfig($c->get(ConfigLoader::class)),
            RateLimiterContract::class => static fn(ContainerInterface $c): RateLimiterContract => new FixedWindowRateLimiter(
                $c->get(ICacheService::class)
            ),
            SettingsStoreContract::class => static fn(ContainerInterface $c): SettingsStoreContract => $c->has('db')
                ? new DbalSettingsStore($c->get('db'))
                : new InMemorySettingsStore(),
            TransportStoreInterface::class => static fn(ContainerInterface $c): TransportStoreInterface => new DbalTransportStore($c->get(\Doctrine\DBAL\Connection::class)),
            HeaderSanitizer::class => static fn(): HeaderSanitizer => new HeaderSanitizer(),
            PayloadSerializer::class => static fn(): PayloadSerializer => new PayloadSerializer(),
            RuntimeMessageSigner::class => static fn(): RuntimeMessageSigner => new RuntimeMessageSigner(),
            TransportInboundSourceRepository::class => static fn(ContainerInterface $c): TransportInboundSourceRepository => new DbalTransportInboundSourceRepository(
                $c->get(\Doctrine\DBAL\Connection::class),
                $c->get(PayloadSerializer::class),
            ),
            TransportInboundSourceConfigSeeder::class => static fn(ContainerInterface $c): TransportInboundSourceConfigSeeder => new TransportInboundSourceConfigSeeder(
                $c->get(TransportInboundSourceRepository::class),
                $c->get(ConfigLoader::class),
                $c->get(IClock::class),
            ),
            InboundSourceValidator::class => static fn(ContainerInterface $c): InboundSourceValidator => new InboundSourceValidator(
                $c->get(TransportInboundSourceRepository::class),
                $c->get(IClock::class),
                $c->get(RuntimeMessageSigner::class),
                $c->get(PayloadSerializer::class),
            ),
            TransportMessageHandlerRegistry::class => static function (ContainerInterface $c): TransportMessageHandlerRegistry {
                $registry = new TransportMessageHandlerRegistry();
                $registry->register(new NoOpTransportMessageHandler(['transport.inbox.processed']));
                foreach ($c->get(ModuleRegistry::class)->providers() as $provider) {
                    if (!$provider instanceof TransportMessageHandlerProviderInterface) {
                        continue;
                    }
                    foreach ($provider->getTransportMessageHandlers() as $handlerClass) {
                        $handler = $c->get($handlerClass);
                        if ($handler instanceof TransportMessageHandlerInterface) {
                            $registry->register($handler);
                        }
                    }
                }

                return $registry;
            },
            TransportInboxRepository::class => static fn(ContainerInterface $c): TransportInboxRepository => new DbalTransportInboxRepository(
                $c->get(\Doctrine\DBAL\Connection::class),
                $c->get(PayloadSerializer::class),
            ),
            TransportReceiverService::class => static fn(ContainerInterface $c): TransportReceiverService => new TransportReceiverService(
                $c->get(TransportInboxRepository::class),
                $c->get(InboundSourceValidator::class),
                $c->get(UuidGeneratorContract::class),
                $c->get(IClock::class),
                $c->get(ConfigLoader::class),
                $c->get(HeaderSanitizer::class),
                $c->get(PayloadSerializer::class),
                $c->get(ILoggerFactory::class),
            ),
            TransportSender::class => static fn(ContainerInterface $c): TransportSender => new TransportSender(
                $c->get(TransportStoreInterface::class),
                $c->get(UuidGeneratorContract::class),
                $c->get(IClock::class),
                $c->get(ConfigLoader::class),
                $c->get(HeaderSanitizer::class),
                $c->get(ILoggerFactory::class),
            ),
            TransportAckPublisherInterface::class => static fn(ContainerInterface $c): TransportAckPublisherInterface => new OutboundTransportAckPublisher(
                $c->get(TransportSender::class),
            ),
            TransportDriverRegistry::class => static function (ContainerInterface $c): TransportDriverRegistry {
                $registry = new TransportDriverRegistry();
                $registry->register(new HttpTransportDriver());
                $registry->register(new HttpPollingTransportDriver());
                $registry->register(new SseTransportDriver());
                $registry->register(new WebSocketTransportDriver());
                $registry->register(new DatabaseTransportDriver(
                    $c->get(TransportStoreInterface::class),
                    $c->get(UuidGeneratorContract::class),
                ));
                $registry->register(new NullTransportDriver());

                return $registry;
            },
            TransportCapabilityService::class => static fn(ContainerInterface $c): TransportCapabilityService => new TransportCapabilityService(
                $c->get(ConfigLoader::class),
                $c->get(ICacheService::class),
            ),
            TransportService::class => static fn(ContainerInterface $c): TransportService => new TransportService(
                $c->get(TransportSender::class),
                $c->get(TransportReceiverService::class),
            ),
            TransportInboxProcessor::class => static fn(ContainerInterface $c): TransportInboxProcessor => new TransportInboxProcessor(
                $c->get(TransportInboxRepository::class),
                $c->get(TransportMessageHandlerRegistry::class),
                $c->get(TransportAckPublisherInterface::class),
                $c->get(IClock::class),
                $c->get(ConfigLoader::class),
                $c->get(LockManagerContract::class),
                $c->get(ILoggerFactory::class),
            ),
            TransportDispatcher::class => static fn(ContainerInterface $c): TransportDispatcher => new TransportDispatcher(
                $c->get(TransportStoreInterface::class),
                $c->get(TransportDriverRegistry::class),
                $c->get(IClock::class),
                $c->get(ConfigLoader::class),
                $c->get(LockManagerContract::class),
                $c->get(ILoggerFactory::class),
            ),
            TransportOutboxDispatcher::class => static fn(ContainerInterface $c): TransportOutboxDispatcher => new TransportOutboxDispatcher(
                $c->get(TransportDispatcher::class),
            ),
            TransportDestinationConfigSeeder::class => static fn(ContainerInterface $c): TransportDestinationConfigSeeder => new TransportDestinationConfigSeeder(
                $c->get(TransportStoreInterface::class),
                $c->get(ConfigLoader::class),
                $c->get(IClock::class),
            ),
            \WorkEddy\Platform\Session\IUserSessionRepository::class => static fn(ContainerInterface $c): \WorkEddy\Platform\Session\IUserSessionRepository => $c->get(\WorkEddy\Platform\Session\DbUserSessionRepository::class),
            ISessionService::class => static fn(ContainerInterface $c): ISessionService => $c->get(\WorkEddy\Platform\Session\PhpSessionAdapter::class),
            EventPublisherInterface::class => static function (ContainerInterface $c): EventPublisherInterface {
                return new \WorkEddy\Platform\Events\EventBus(
                    $c->get(ModuleRegistry::class),
                    $c,
                    $c->get(IQueueService::class),
                    $c->get(ILoggerFactory::class)
                );
            },
            ILoggerFactory::class => static fn(ContainerInterface $c): ILoggerFactory => MonologLoggerFactory::fromConfig($c->get(ConfigLoader::class)),
            UuidGeneratorContract::class => static fn(): UuidGeneratorContract => class_exists(\Ramsey\Uuid\Uuid::class)
                ? new RamseyUuidGenerator()
                : new NativeUuidGenerator(),
            LockManagerContract::class => static fn(ContainerInterface $c): LockManagerContract => SymfonyLockManager::fromConfig($c->get(ConfigLoader::class)),
            CommandLockRunner::class => static fn(ContainerInterface $c): CommandLockRunner => new CommandLockRunner($c->get(LockManagerContract::class)),
            IQueueService::class => static function (ContainerInterface $c): IQueueService {
                $config = $c->get(ConfigLoader::class);
                $maxAttempts = (int) $config->get('queue.default_max_attempts', 3);

                return match ((string) $config->get('queue.driver', 'database')) {
                    'inline' => new InlineQueueAdapter($maxAttempts),
                    'database' => new DbalQueueService($c->get('db'), $maxAttempts),
                    default => throw new \RuntimeException('Unsupported WorkEddy queue driver: ' . (string) $config->get('queue.driver', 'database')),
                };
            },
            QueueHandlerRegistry::class => static function (ContainerInterface $c): QueueHandlerRegistry {
                $registry = new QueueHandlerRegistry();
                foreach ($c->get(ModuleRegistry::class)->providers() as $provider) {
                    foreach ($provider->getJobHandlers() as $jobType => $handlerDefinition) {
                        if (is_string($handlerDefinition)) {
                            $handler = $c->get($handlerDefinition);
                        } else {
                            $handler = $handlerDefinition;
                        }

                        if (!$handler instanceof QueueJobHandlerInterface) {
                            continue;
                        }

                        $registry->register(is_string($jobType) ? $jobType : self::jobTypeFor($handler), $handler);
                    }
                }

                return $registry;
            },
            QueueWorker::class => static fn(ContainerInterface $c): QueueWorker => new QueueWorker(
                $c->get(IQueueService::class),
                $c->get(QueueHandlerRegistry::class),
                $c->get(ILoggerFactory::class)->channel('queue'),
            ),
            FastRouteDispatcherFactory::class => static fn(): FastRouteDispatcherFactory => new FastRouteDispatcherFactory(),
            PhpDiContainerFactory::class => static fn(): PhpDiContainerFactory => new PhpDiContainerFactory(),
            SymfonyConsoleFactory::class => static fn(): SymfonyConsoleFactory => new SymfonyConsoleFactory(),
            ConsoleApplicationFactory::class => static fn(): ConsoleApplicationFactory => new ConsoleApplicationFactory(),
            MigrationConfigurationFactory::class => static fn(ContainerInterface $c): MigrationConfigurationFactory => new MigrationConfigurationFactory($c->get(ConfigLoader::class)),
            ViewRenderer::class => static fn(ContainerInterface $c): ViewRenderer => new ViewRenderer($c->get(ConfigLoader::class), $c->get(ISessionService::class)),
        ];
    }

    public function getRouteFile(): ?string
    {
        return __DIR__ . '/Transport/Inbound/routes.php';
    }

    public function getEventListeners(): array
    {
        return [];
    }

    public function getJobHandlers(): array
    {
        return [
            \WorkEddy\Platform\Events\AsyncEventJobHandler::JOB_TYPE => static function (ContainerInterface $c) {
                return new \WorkEddy\Platform\Events\AsyncEventJobHandler(
                    $c,
                    $c->get(ILoggerFactory::class)
                );
            },
        ];
    }

    public function getPermissionDefinitionProvider(): mixed
    {
        return null;
    }

    public function getSettingsProvider(): mixed
    {
        return null;
    }

    public function getConsoleCommandProvider(): mixed
    {
        return null;
    }

    public function boot(ContainerInterface $container): void {}

    private static function jobTypeFor(QueueJobHandlerInterface $handler): string
    {
        if (method_exists($handler, 'jobType')) {
            $jobType = $handler->jobType;
            if (is_string($jobType) && $jobType !== '') {
                return $jobType;
            }
        }

        $constant = $handler::class . '::JOB_TYPE';
        if (defined($constant)) {
            $jobType = constant($constant);
            if (is_string($jobType) && $jobType !== '') {
                return $jobType;
            }
        }

        return $handler::class;
    }
}
