<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Subscription;

use WorkEddy\Modules\Billing\Application\UseCases\GenerateInvoice;
use WorkEddy\Modules\Billing\Domain\Contracts\IInvoiceRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\Notification\Application\OrganizationNotificationRecipientFactory;
use WorkEddy\Modules\Notification\Contracts\NotificationServiceInterface;
use WorkEddy\Modules\Organization\Domain\Contracts\IOrganizationRepository;
use WorkEddy\Modules\Subscription\Application\Listeners\GenerateInvoiceOnActivation;
use WorkEddy\Modules\Subscription\Application\Listeners\GenerateInvoiceOnRenewal;
use WorkEddy\Modules\Subscription\Application\Listeners\GenerateProrationInvoiceOnPlanChange;
use WorkEddy\Modules\Subscription\Application\Listeners\ProvisionDefaultSubscription;
use WorkEddy\Modules\Subscription\Application\Listeners\ReactivateSubscriptionOnInvoicePaid;
use WorkEddy\Modules\Subscription\Application\Listeners\SuspendSubscriptionOnOrganizationSuspended;
use WorkEddy\Modules\Subscription\Application\Support\SubscriptionMetricCatalog;
use WorkEddy\Modules\Subscription\Application\UseCases\ActivateSubscription;
use WorkEddy\Modules\Subscription\Application\UseCases\CancelSubscription;
use WorkEddy\Modules\Subscription\Application\UseCases\ChangeSubscriptionPlan;
use WorkEddy\Modules\Subscription\Application\UseCases\CheckSubscriptionLimits;
use WorkEddy\Modules\Subscription\Application\UseCases\CreateSubscriptionPlan;
use WorkEddy\Modules\Subscription\Application\UseCases\ExpireSubscription;
use WorkEddy\Modules\Subscription\Application\UseCases\ReactivateSubscription;
use WorkEddy\Modules\Subscription\Application\UseCases\RecordUsage;
use WorkEddy\Modules\Subscription\Application\UseCases\RenewSubscription;
use WorkEddy\Modules\Subscription\Application\UseCases\RunSubscriptionRenewalSweep;
use WorkEddy\Modules\Subscription\Application\UseCases\SuspendOverdueSubscriptions;
use WorkEddy\Modules\Subscription\Application\UseCases\SuspendSubscription;
use WorkEddy\Modules\Subscription\Application\UseCases\UpdateSubscriptionPlan;
use WorkEddy\Modules\Subscription\Authorization\SubscriptionPermissionDefinitionProvider;
use WorkEddy\Modules\Subscription\Console\SubscriptionConsoleCommandProvider;
use WorkEddy\Modules\Subscription\Console\SubscriptionDunningSweepCommand;
use WorkEddy\Modules\Subscription\Console\SubscriptionRenewalSweepCommand;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionPlanRepository;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionRepository;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionLimitGuard;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionUsageRecorder;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionUsageRepository;
use WorkEddy\Modules\Subscription\Infrastructure\DbalSubscriptionPlanRepository;
use WorkEddy\Modules\Subscription\Infrastructure\DbalSubscriptionRepository;
use WorkEddy\Modules\Subscription\Infrastructure\DbalSubscriptionUsageRepository;
use WorkEddy\Modules\Subscription\Presentation\SubscriptionApiController;
use WorkEddy\Modules\Subscription\Presentation\SubscriptionPageController;
use WorkEddy\Modules\Subscription\Presentation\SubscriptionPageData;
use WorkEddy\Modules\Subscription\Settings\SubscriptionSettings;
use WorkEddy\Modules\Subscription\Settings\SubscriptionSettingsProvider;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Authorization\IPermissionDefinitionProvider;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Platform\Events\EventPublisherInterface;
use WorkEddy\Platform\Logging\ILoggerFactory;
use WorkEddy\Platform\Module\ModuleServiceProviderInterface;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Platform\Settings\SettingsService;
use WorkEddy\Shared\Presentation\ViewRenderer;
use Doctrine\DBAL\Connection;
use Psr\Container\ContainerInterface;

final class ServiceProvider implements ModuleServiceProviderInterface
{
    public function getName(): string
    {
        return 'Subscription';
    }

    public function getDefinitions(): array
    {
        return [
            SubscriptionSettingsProvider::class => static fn(): SubscriptionSettingsProvider => new SubscriptionSettingsProvider(),
            SubscriptionSettings::class => static fn(ContainerInterface $c): SubscriptionSettings => new SubscriptionSettings($c->get(SettingsService::class)),

            ISubscriptionRepository::class => static fn(ContainerInterface $c): ISubscriptionRepository => $c->get(DbalSubscriptionRepository::class),
            DbalSubscriptionRepository::class => static fn(ContainerInterface $c): DbalSubscriptionRepository => new DbalSubscriptionRepository($c->get(Connection::class), $c->get(IClock::class)),

            ISubscriptionPlanRepository::class => static fn(ContainerInterface $c): ISubscriptionPlanRepository => $c->get(DbalSubscriptionPlanRepository::class),
            DbalSubscriptionPlanRepository::class => static fn(ContainerInterface $c): DbalSubscriptionPlanRepository => new DbalSubscriptionPlanRepository($c->get(Connection::class), $c->get(IClock::class)),

            ISubscriptionUsageRepository::class => static fn(ContainerInterface $c): ISubscriptionUsageRepository => $c->get(DbalSubscriptionUsageRepository::class),
            DbalSubscriptionUsageRepository::class => static fn(ContainerInterface $c): DbalSubscriptionUsageRepository => new DbalSubscriptionUsageRepository($c->get(Connection::class)),
            SubscriptionMetricCatalog::class => static fn(): SubscriptionMetricCatalog => new SubscriptionMetricCatalog(),

            CreateSubscriptionPlan::class => static fn(ContainerInterface $c): CreateSubscriptionPlan => new CreateSubscriptionPlan(
                $c->get(ISubscriptionPlanRepository::class),
                $c->get(SubscriptionSettings::class),
                $c->get(IClock::class),
            ),
            UpdateSubscriptionPlan::class => static fn(ContainerInterface $c): UpdateSubscriptionPlan => new UpdateSubscriptionPlan(
                $c->get(ISubscriptionPlanRepository::class),
                $c->get(IClock::class),
            ),

            ActivateSubscription::class => static fn(ContainerInterface $c): ActivateSubscription => new ActivateSubscription(
                $c->get(ISubscriptionRepository::class),
                $c->get(ISubscriptionPlanRepository::class),
                $c->get(IOrganizationRepository::class),
                $c->get(SubscriptionSettings::class),
                $c->get(IClock::class),
                $c->get(EventPublisherInterface::class),
                $c->get(IAuditService::class),
                $c->get(NotificationServiceInterface::class),
                $c->get(OrganizationNotificationRecipientFactory::class),
            ),
            SuspendSubscription::class => static fn(ContainerInterface $c): SuspendSubscription => new SuspendSubscription(
                $c->get(ISubscriptionRepository::class),
                $c->get(IClock::class),
                $c->get(EventPublisherInterface::class),
                $c->get(IAuditService::class),
                $c->get(NotificationServiceInterface::class),
                $c->get(OrganizationNotificationRecipientFactory::class),
            ),
            ReactivateSubscription::class => static fn(ContainerInterface $c): ReactivateSubscription => new ReactivateSubscription(
                $c->get(ISubscriptionRepository::class),
                $c->get(IClock::class),
                $c->get(EventPublisherInterface::class),
                $c->get(IAuditService::class),
                $c->get(NotificationServiceInterface::class),
                $c->get(OrganizationNotificationRecipientFactory::class),
            ),
            ExpireSubscription::class => static fn(ContainerInterface $c): ExpireSubscription => new ExpireSubscription(
                $c->get(ISubscriptionRepository::class),
                $c->get(IClock::class),
                $c->get(EventPublisherInterface::class),
                $c->get(IAuditService::class),
                $c->get(NotificationServiceInterface::class),
                $c->get(OrganizationNotificationRecipientFactory::class),
            ),
            CancelSubscription::class => static fn(ContainerInterface $c): CancelSubscription => new CancelSubscription(
                $c->get(ISubscriptionRepository::class),
                $c->get(IClock::class),
                $c->get(EventPublisherInterface::class),
                $c->get(IAuditService::class),
                $c->get(NotificationServiceInterface::class),
                $c->get(OrganizationNotificationRecipientFactory::class),
            ),
            ChangeSubscriptionPlan::class => static fn(ContainerInterface $c): ChangeSubscriptionPlan => new ChangeSubscriptionPlan(
                $c->get(ISubscriptionRepository::class),
                $c->get(ISubscriptionPlanRepository::class),
                $c->get(IClock::class),
                $c->get(EventPublisherInterface::class),
                $c->get(IAuditService::class),
                $c->get(NotificationServiceInterface::class),
                $c->get(OrganizationNotificationRecipientFactory::class),
            ),
            RenewSubscription::class => static fn(ContainerInterface $c): RenewSubscription => new RenewSubscription(
                $c->get(ISubscriptionRepository::class),
                $c->get(IClock::class),
                $c->get(EventPublisherInterface::class),
                $c->get(IAuditService::class),
                $c->get(NotificationServiceInterface::class),
                $c->get(OrganizationNotificationRecipientFactory::class),
            ),

            CheckSubscriptionLimits::class => static fn(ContainerInterface $c): CheckSubscriptionLimits => new CheckSubscriptionLimits(
                $c->get(ISubscriptionRepository::class),
                $c->get(ISubscriptionPlanRepository::class),
                $c->get(ISubscriptionUsageRepository::class),
                $c->get(IClock::class),
                $c->get(SubscriptionMetricCatalog::class),
            ),
            ISubscriptionLimitGuard::class => static fn(ContainerInterface $c): ISubscriptionLimitGuard => $c->get(CheckSubscriptionLimits::class),
            RecordUsage::class => static fn(ContainerInterface $c): RecordUsage => new RecordUsage(
                $c->get(ISubscriptionRepository::class),
                $c->get(ISubscriptionPlanRepository::class),
                $c->get(ISubscriptionUsageRepository::class),
                $c->get(IClock::class),
                $c->get(EventPublisherInterface::class),
                $c->get(SubscriptionMetricCatalog::class),
            ),
            ISubscriptionUsageRecorder::class => static fn(ContainerInterface $c): ISubscriptionUsageRecorder => $c->get(RecordUsage::class),

            GenerateInvoiceOnActivation::class => static fn(ContainerInterface $c): GenerateInvoiceOnActivation => new GenerateInvoiceOnActivation(
                $c->get(GenerateInvoice::class),
                $c->get(ISubscriptionPlanRepository::class),
                $c->get(ILoggerFactory::class),
            ),
            ReactivateSubscriptionOnInvoicePaid::class => static fn(ContainerInterface $c): ReactivateSubscriptionOnInvoicePaid => new ReactivateSubscriptionOnInvoicePaid(
                $c->get(ISubscriptionRepository::class),
                $c->get(ReactivateSubscription::class),
                $c->get(ILoggerFactory::class),
            ),
            ProvisionDefaultSubscription::class => static fn(ContainerInterface $c): ProvisionDefaultSubscription => new ProvisionDefaultSubscription(
                $c->get(ActivateSubscription::class),
                $c->get(SubscriptionSettings::class),
                $c->get(ILoggerFactory::class),
            ),
            GenerateInvoiceOnRenewal::class => static fn(ContainerInterface $c): GenerateInvoiceOnRenewal => new GenerateInvoiceOnRenewal(
                $c->get(GenerateInvoice::class),
                $c->get(ISubscriptionPlanRepository::class),
                $c->get(SubscriptionSettings::class),
                $c->get(ILoggerFactory::class),
            ),
            GenerateProrationInvoiceOnPlanChange::class => static fn(ContainerInterface $c): GenerateProrationInvoiceOnPlanChange => new GenerateProrationInvoiceOnPlanChange(
                $c->get(GenerateInvoice::class),
                $c->get(ISubscriptionRepository::class),
                $c->get(ISubscriptionPlanRepository::class),
                $c->get(IClock::class),
                $c->get(ILoggerFactory::class),
            ),
            SuspendSubscriptionOnOrganizationSuspended::class => static fn(ContainerInterface $c): SuspendSubscriptionOnOrganizationSuspended => new SuspendSubscriptionOnOrganizationSuspended(
                $c->get(ISubscriptionRepository::class),
                $c->get(SuspendSubscription::class),
                $c->get(ReactivateSubscription::class),
                $c->get(CancelSubscription::class),
                $c->get(ILoggerFactory::class),
            ),

            RunSubscriptionRenewalSweep::class => static fn(ContainerInterface $c): RunSubscriptionRenewalSweep => new RunSubscriptionRenewalSweep(
                $c->get(ISubscriptionRepository::class),
                $c->get(RenewSubscription::class),
                $c->get(IClock::class),
                $c->get(ILoggerFactory::class),
            ),
            SuspendOverdueSubscriptions::class => static fn(ContainerInterface $c): SuspendOverdueSubscriptions => new SuspendOverdueSubscriptions(
                $c->get(IInvoiceRepository::class),
                $c->get(ISubscriptionRepository::class),
                $c->get(SuspendSubscription::class),
                $c->get(SubscriptionSettings::class),
                $c->get(IClock::class),
                $c->get(ILoggerFactory::class),
            ),
            SubscriptionRenewalSweepCommand::class => static fn(ContainerInterface $c): SubscriptionRenewalSweepCommand => new SubscriptionRenewalSweepCommand(
                $c->get(RunSubscriptionRenewalSweep::class),
                $c->get(\WorkEddy\Platform\Console\Command\CommandLockRunner::class),
            ),
            SubscriptionDunningSweepCommand::class => static fn(ContainerInterface $c): SubscriptionDunningSweepCommand => new SubscriptionDunningSweepCommand(
                $c->get(SuspendOverdueSubscriptions::class),
                $c->get(\WorkEddy\Platform\Console\Command\CommandLockRunner::class),
            ),

            SubscriptionPageData::class => static fn(ContainerInterface $c): SubscriptionPageData => new SubscriptionPageData(
                $c->get(ISubscriptionRepository::class),
                $c->get(ISubscriptionPlanRepository::class),
                $c->get(SubscriptionSettings::class),
                $c->get(ISubscriptionUsageRepository::class),
            ),
            SubscriptionPageController::class => static fn(ContainerInterface $c): SubscriptionPageController => new SubscriptionPageController(
                $c->get(ISessionService::class),
                $c->get(IPermissionService::class),
                $c->get(ViewRenderer::class),
                $c->get(SubscriptionPageData::class),
                $c->get(ISubscriptionRepository::class),
            ),
            SubscriptionApiController::class => static fn(ContainerInterface $c): SubscriptionApiController => new SubscriptionApiController(
                $c->get(ISubscriptionRepository::class),
                $c->get(ISubscriptionPlanRepository::class),
                $c->get(ISubscriptionUsageRepository::class),
                $c->get(CreateSubscriptionPlan::class),
                $c->get(UpdateSubscriptionPlan::class),
                $c->get(ActivateSubscription::class),
                $c->get(SuspendSubscription::class),
                $c->get(ReactivateSubscription::class),
                $c->get(ExpireSubscription::class),
                $c->get(CancelSubscription::class),
                $c->get(ChangeSubscriptionPlan::class),
                $c->get(IClock::class),
                $c->get(ISessionService::class),
            ),
        ];
    }

    public function getRouteFile(): ?string
    {
        return __DIR__ . '/Presentation/routes.php';
    }

    public function getEventListeners(): array
    {
        return [
            'subscription.activated' => [
                GenerateInvoiceOnActivation::class,
            ],
            'subscription.renewed' => [
                GenerateInvoiceOnRenewal::class,
            ],
            'subscription.plan_changed' => [
                GenerateProrationInvoiceOnPlanChange::class,
            ],
            'invoice.paid' => [
                ReactivateSubscriptionOnInvoicePaid::class,
            ],
            'organization.created' => [
                ProvisionDefaultSubscription::class,
            ],
            'organization.status_changed' => [
                SuspendSubscriptionOnOrganizationSuspended::class,
            ],
        ];
    }

    public function getJobHandlers(): array
    {
        return [];
    }

    public function getPermissionDefinitionProvider(): ?IPermissionDefinitionProvider
    {
        return new SubscriptionPermissionDefinitionProvider();
    }

    public function getSettingsProvider(): mixed
    {
        return new SubscriptionSettingsProvider();
    }

    public function getConsoleCommandProvider(): mixed
    {
        return new SubscriptionConsoleCommandProvider();
    }

    public function boot(ContainerInterface $container): void {}
}
