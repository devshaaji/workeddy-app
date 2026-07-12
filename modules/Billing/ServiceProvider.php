<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Billing;

use WorkEddy\Modules\Billing\Application\UseCases\AcceptQuotation;
use WorkEddy\Modules\Billing\Application\UseCases\GenerateInvoice;
use WorkEddy\Modules\Billing\Application\UseCases\GenerateQuotation;
use WorkEddy\Modules\Billing\Application\UseCases\GeneratePdf;
use WorkEddy\Modules\Billing\Authorization\BillingPermissionDefinitionProvider;
use WorkEddy\Modules\Billing\Domain\Contracts\IInvoiceRepository;
use WorkEddy\Modules\Billing\Domain\Contracts\IQuotationRepository;
use WorkEddy\Modules\Billing\Infrastructure\DbalInvoiceRepository;
use WorkEddy\Modules\Billing\Infrastructure\DbalQuotationRepository;
use WorkEddy\Modules\Notification\Application\OrganizationNotificationRecipientFactory;
use WorkEddy\Modules\Notification\Contracts\NotificationServiceInterface;
use WorkEddy\Modules\Billing\Presentation\BillingApiController;
use WorkEddy\Modules\Storage\Domain\Contracts\IStorageService;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Authorization\IPermissionDefinitionProvider;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Platform\Module\ModuleServiceProviderInterface;
use WorkEddy\Platform\Session\ISessionService;
use Doctrine\DBAL\Connection;
use Psr\Container\ContainerInterface;

final class ServiceProvider implements ModuleServiceProviderInterface
{
    public function getName(): string
    {
        return 'Billing';
    }

    public function getDefinitions(): array
    {
        return [
            IQuotationRepository::class => static function (ContainerInterface $c): IQuotationRepository {
                return $c->get(DbalQuotationRepository::class);
            },
            IInvoiceRepository::class => static function (ContainerInterface $c): IInvoiceRepository {
                return $c->get(DbalInvoiceRepository::class);
            },
            DbalQuotationRepository::class => static function (ContainerInterface $c) {
                return new DbalQuotationRepository($c->get(Connection::class), $c->get(IClock::class));
            },
            DbalInvoiceRepository::class => static function (ContainerInterface $c) {
                return new DbalInvoiceRepository($c->get(Connection::class), $c->get(IClock::class));
            },
            GenerateQuotation::class => static function (ContainerInterface $c) {
                return new GenerateQuotation(
                    $c->get(IQuotationRepository::class),
                    $c->get(IAuditService::class),
                    $c->get(IClock::class)
                );
            },
            AcceptQuotation::class => static function (ContainerInterface $c) {
                return new AcceptQuotation(
                    $c->get(IQuotationRepository::class),
                    $c->get(IAuditService::class),
                    $c->get(IClock::class)
                );
            },
            GenerateInvoice::class => static function (ContainerInterface $c) {
                return new GenerateInvoice(
                    $c->get(IInvoiceRepository::class),
                    $c->get(IQuotationRepository::class),
                    $c->get(IAuditService::class),
                    $c->get(IClock::class),
                    $c->get(NotificationServiceInterface::class),
                    $c->get(OrganizationNotificationRecipientFactory::class),
                );
            },
            GeneratePdf::class => static function (ContainerInterface $c) {
                return new GeneratePdf(
                    $c->get(IQuotationRepository::class),
                    $c->get(IInvoiceRepository::class),
                    $c->get(IStorageService::class),
                    $c->get(\WorkEddy\Platform\Settings\SettingsService::class),
                    $c->get(\WorkEddy\Modules\Organization\Domain\Contracts\IOrganizationRepository::class),
                );
            },
            BillingApiController::class => static function (ContainerInterface $c) {
                return new BillingApiController(
                    $c->get(IQuotationRepository::class),
                    $c->get(IInvoiceRepository::class),
                    $c->get(IClock::class),
                    $c->get(GenerateQuotation::class),
                    $c->get(AcceptQuotation::class),
                    $c->get(GenerateInvoice::class),
                    $c->get(GeneratePdf::class),
                    $c->get(ISessionService::class),
                    $c->get(IStorageService::class),
                    $c->get(\WorkEddy\Platform\Settings\SettingsService::class)
                );
            },
            \WorkEddy\Modules\Billing\Presentation\BillingPageData::class => static function (ContainerInterface $c) {
                return new \WorkEddy\Modules\Billing\Presentation\BillingPageData(
                    $c->get(IQuotationRepository::class),
                    $c->get(IInvoiceRepository::class),
                    $c->get(\WorkEddy\Platform\Settings\SettingsService::class),
                    $c->get(\WorkEddy\Modules\Organization\Domain\Contracts\IOrganizationRepository::class),
                );
            },
            \WorkEddy\Modules\Billing\Presentation\BillingPageController::class => static function (ContainerInterface $c) {
                return new \WorkEddy\Modules\Billing\Presentation\BillingPageController(
                    $c->get(ISessionService::class),
                    $c->get(\WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService::class),
                    $c->get(\WorkEddy\Shared\Presentation\ViewRenderer::class),
                    $c->get(\WorkEddy\Modules\Billing\Presentation\BillingPageData::class)
                );
            },
            \WorkEddy\Modules\Billing\Application\Listeners\PaymentCompletedListener::class => static function (ContainerInterface $c) {
                return new \WorkEddy\Modules\Billing\Application\Listeners\PaymentCompletedListener(
                    $c->get(IInvoiceRepository::class),
                    $c->get(IClock::class),
                    $c->get(\WorkEddy\Platform\Events\EventPublisherInterface::class)
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
        return [
            'payment.completed' => [
                \WorkEddy\Modules\Billing\Application\Listeners\PaymentCompletedListener::class,
            ],
        ];
    }

    public function getJobHandlers(): array
    {
        return [];
    }

    public function getPermissionDefinitionProvider(): ?IPermissionDefinitionProvider
    {
        return new BillingPermissionDefinitionProvider();
    }

    public function getSettingsProvider(): mixed
    {
        return new \WorkEddy\Modules\Billing\Settings\BillingSettingsProvider();
    }

    public function getConsoleCommandProvider(): mixed
    {
        return null;
    }

    public function boot(ContainerInterface $container): void {}
}
