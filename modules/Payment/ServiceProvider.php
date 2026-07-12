<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Payment;

use WorkEddy\Modules\Notification\Application\OrganizationNotificationRecipientFactory;
use WorkEddy\Modules\Notification\Contracts\NotificationServiceInterface;
use WorkEddy\Modules\Payment\Application\UseCases\CreateGatewayCheckout;
use WorkEddy\Modules\Payment\Application\UseCases\ProcessOnlinePayment;
use WorkEddy\Modules\Payment\Application\UseCases\RecordManualPayment;
use WorkEddy\Modules\Payment\Authorization\PaymentPermissionDefinitionProvider;
use WorkEddy\Modules\Payment\Domain\Contracts\IPaymentRecordRepository;
use WorkEddy\Modules\Payment\Domain\Gateways\PaymentGatewayRegistry;
use WorkEddy\Modules\Payment\Infrastructure\DbalPaymentRecordRepository;
use WorkEddy\Modules\Payment\Infrastructure\Gateways\PaystackPaymentGateway;
use WorkEddy\Modules\Payment\Infrastructure\Gateways\UnsupportedPaymentGateway;
use WorkEddy\Modules\Payment\Presentation\PaymentApiController;
use WorkEddy\Modules\Payment\Settings\PaymentSettings;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Authorization\IPermissionDefinitionProvider;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Platform\Events\EventPublisherInterface;
use WorkEddy\Platform\Module\ModuleServiceProviderInterface;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Platform\Settings\SettingsService;
use Doctrine\DBAL\Connection;
use Psr\Container\ContainerInterface;

final class ServiceProvider implements ModuleServiceProviderInterface
{
    public function getName(): string
    {
        return 'Payment';
    }

    public function getDefinitions(): array
    {
        return [
            IPaymentRecordRepository::class => static function (ContainerInterface $c): IPaymentRecordRepository {
                return $c->get(DbalPaymentRecordRepository::class);
            },
            DbalPaymentRecordRepository::class => static function (ContainerInterface $c) {
                return new DbalPaymentRecordRepository($c->get(Connection::class), $c->get(IClock::class));
            },
            RecordManualPayment::class => static function (ContainerInterface $c) {
                return new RecordManualPayment(
                    $c->get(IPaymentRecordRepository::class),
                    $c->get(IAuditService::class),
                    $c->get(IClock::class),
                    $c->get(EventPublisherInterface::class),
                    $c->get(NotificationServiceInterface::class),
                    $c->get(OrganizationNotificationRecipientFactory::class),
                );
            },
            ProcessOnlinePayment::class => static function (ContainerInterface $c) {
                return new ProcessOnlinePayment(
                    $c->get(IPaymentRecordRepository::class),
                    $c->get(IAuditService::class),
                    $c->get(IClock::class),
                    $c->get(EventPublisherInterface::class),
                    $c->get(PaymentGatewayRegistry::class),
                    $c->get(NotificationServiceInterface::class),
                    $c->get(OrganizationNotificationRecipientFactory::class),
                );
            },
            CreateGatewayCheckout::class => static function (ContainerInterface $c) {
                return new CreateGatewayCheckout(
                    $c->get(IPaymentRecordRepository::class),
                    $c->get(PaymentGatewayRegistry::class),
                    $c->get(PaymentSettings::class),
                    $c->get(IAuditService::class)
                );
            },
            PaymentGatewayRegistry::class => static function (ContainerInterface $c): PaymentGatewayRegistry {
                $settings = $c->get(PaymentSettings::class);
                $configs = $settings->gateways();
                $gateways = [];

                foreach ($configs as $name => $config) {
                    $driver = $settings->gatewayDriver((string) $name);
                    $gateways[] = $driver === 'paystack'
                        ? new PaystackPaymentGateway($config)
                        : new UnsupportedPaymentGateway((string) $name, $settings->gatewayEnabled((string) $name));
                }

                return new PaymentGatewayRegistry($gateways);
            },
            PaymentApiController::class => static function (ContainerInterface $c) {
                return new PaymentApiController(
                    $c->get(IPaymentRecordRepository::class),
                    $c->get(RecordManualPayment::class),
                    $c->get(CreateGatewayCheckout::class),
                    $c->get(ProcessOnlinePayment::class),
                    $c->get(PaymentGatewayRegistry::class),
                    $c->get(ISessionService::class)
                );
            },
            \WorkEddy\Modules\Payment\Presentation\PaymentPageData::class => static function (ContainerInterface $c) {
                return new \WorkEddy\Modules\Payment\Presentation\PaymentPageData(
                    $c->get(IPaymentRecordRepository::class),
                    $c->get(\WorkEddy\Platform\Settings\SettingsService::class)
                );
            },
            \WorkEddy\Modules\Payment\Presentation\PaymentPageController::class => static function (ContainerInterface $c) {
                return new \WorkEddy\Modules\Payment\Presentation\PaymentPageController(
                    $c->get(ISessionService::class),
                    $c->get(\WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService::class),
                    $c->get(\WorkEddy\Shared\Presentation\ViewRenderer::class),
                    $c->get(\WorkEddy\Modules\Payment\Presentation\PaymentPageData::class)
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
        return [];
    }

    public function getPermissionDefinitionProvider(): ?IPermissionDefinitionProvider
    {
        return new PaymentPermissionDefinitionProvider();
    }

    public function getSettingsProvider(): mixed
    {
        return new \WorkEddy\Modules\Payment\Settings\PaymentSettingsProvider();
    }

    public function getConsoleCommandProvider(): mixed
    {
        return null;
    }

    public function boot(ContainerInterface $container): void {}
}
