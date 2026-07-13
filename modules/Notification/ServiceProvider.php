<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification;

use WorkEddy\Modules\Notification\Application\OrganizationNotificationRecipientFactory;
use WorkEddy\Modules\Organization\Domain\Contracts\IOrganizationRepository;
use WorkEddy\Modules\Notification\Authorization\NotificationPermissionDefinitionProvider;
use WorkEddy\Modules\Notification\Settings\NotificationSettingsProvider;
use WorkEddy\Platform\Authorization\IPermissionDefinitionProvider;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Platform\Module\ModuleServiceProviderInterface;
use Doctrine\DBAL\Connection;
use WorkEddy\Platform\Settings\IModuleSettingsProvider;
use Psr\Container\ContainerInterface;

final class ServiceProvider implements ModuleServiceProviderInterface
{
    public function getName(): string
    {
        return 'notification';
    }

    public function getDefinitions(): array
    {
        return [
            \WorkEddy\Modules\Notification\Contracts\NotificationServiceInterface::class => static function (ContainerInterface $c) {
                return new \WorkEddy\Modules\Notification\Application\NotificationService(
                    $c->get(\WorkEddy\Platform\Queue\IQueueService::class),
                    $c->get(\WorkEddy\Modules\Notification\Contracts\NotificationLogRepositoryInterface::class),
                    $c->get(\WorkEddy\Modules\Notification\Contracts\ChannelResolverInterface::class),
                    $c->get(\WorkEddy\Platform\Identity\UuidGeneratorContract::class),
                    $c->get(IClock::class),
                    $c->get(\WorkEddy\Modules\Notification\Application\ResolveRecipientNotificationChannels::class),
                );
            },
            \WorkEddy\Modules\Notification\Contracts\ChannelResolverInterface::class => \DI\autowire(\WorkEddy\Modules\Notification\Application\ResolveNotificationChannels::class),
            \WorkEddy\Modules\Notification\Application\ResolveRecipientNotificationChannels::class => \DI\autowire(),
            \WorkEddy\Modules\Notification\Contracts\TemplateRendererInterface::class => \DI\create(\WorkEddy\Modules\Notification\Infrastructure\Templates\FileTemplateRenderer::class)
                ->constructor(__DIR__ . '/Templates'),
            \WorkEddy\Modules\Notification\Contracts\NotificationLogRepositoryInterface::class => static function (ContainerInterface $c) {
                return new \WorkEddy\Modules\Notification\Infrastructure\Persistence\DbalNotificationLogRepository(
                    $c->get(Connection::class),
                    $c->get(IClock::class),
                );
            },
            \WorkEddy\Modules\Notification\Contracts\NotificationPreferenceRepositoryInterface::class => static function (ContainerInterface $c) {
                return new \WorkEddy\Modules\Notification\Infrastructure\Persistence\DbalNotificationPreferenceRepository(
                    $c->get(Connection::class),
                    $c->get(IClock::class),
                );
            },
            \WorkEddy\Modules\Notification\Contracts\InAppNotificationRepositoryInterface::class => static function (ContainerInterface $c) {
                return new \WorkEddy\Modules\Notification\Infrastructure\Persistence\DbalInAppNotificationRepository(
                    $c->get(Connection::class),
                    $c->get(IClock::class),
                );
            },
            OrganizationNotificationRecipientFactory::class => static fn(ContainerInterface $c): OrganizationNotificationRecipientFactory => new OrganizationNotificationRecipientFactory(
                $c->get(IOrganizationRepository::class),
            ),

            \WorkEddy\Modules\Notification\Infrastructure\Providers\InAppNotificationProvider::class => \DI\autowire(),
            \WorkEddy\Modules\Notification\Infrastructure\Providers\EmailNotificationProvider::class => \DI\autowire(),
            \WorkEddy\Modules\Notification\Infrastructure\Providers\SmsNotificationProvider::class => \DI\autowire(),
            \WorkEddy\Modules\Notification\Infrastructure\Providers\WhatsAppNotificationProvider::class => \DI\autowire(),

            // Clients & Providers
            \Symfony\Contracts\HttpClient\HttpClientInterface::class => \DI\factory([\Symfony\Component\HttpClient\HttpClient::class, 'create']),
            \WorkEddy\Modules\Notification\Infrastructure\Clients\ProviderRouter::class => \DI\autowire(),
            \WorkEddy\Modules\Notification\Infrastructure\Clients\Twilio\TwilioMessagingClient::class => \DI\autowire(),
            \WorkEddy\Modules\Notification\Infrastructure\Clients\Smtp\SmtpEmailGatewayClient::class => \DI\autowire(),
            \WorkEddy\Modules\Notification\Application\SendNotificationUseCase::class => static function (ContainerInterface $c) {
                return new \WorkEddy\Modules\Notification\Application\SendNotificationUseCase(
                    $c->get(\WorkEddy\Modules\Notification\Contracts\ChannelResolverInterface::class),
                    $c->get(\WorkEddy\Modules\Notification\Contracts\TemplateRendererInterface::class),
                    $c->get(\WorkEddy\Modules\Notification\Contracts\NotificationLogRepositoryInterface::class),
                    $c->get(\WorkEddy\Platform\Identity\UuidGeneratorContract::class),
                    $c,
                    $c->get(\WorkEddy\Platform\Settings\SettingsService::class),
                    $c->get(\WorkEddy\Platform\Queue\IQueueService::class),
                    $c->get(IClock::class),
                    $c->get(\WorkEddy\Modules\Notification\Application\ResolveRecipientNotificationChannels::class),
                );
            },
        ];
    }

    public function getRouteFile(): ?string
    {
        return __DIR__ . '/Presentation/routes.php';
    }

    public function getEventListeners(): array
    {
        return [];
    }

    public function getJobHandlers(): array
    {
        return [
            \WorkEddy\Modules\Notification\Application\Job\SendNotificationJob::JOB_TYPE => \WorkEddy\Modules\Notification\Application\Job\SendNotificationJobHandler::class,
        ];
    }

    public function getPermissionDefinitionProvider(): ?IPermissionDefinitionProvider
    {
        return new NotificationPermissionDefinitionProvider();
    }

    public function getSettingsProvider(): ?IModuleSettingsProvider
    {
        return new NotificationSettingsProvider();
    }

    public function getConsoleCommandProvider(): mixed
    {
        return null;
    }

    public function boot(ContainerInterface $container): void {}
}
