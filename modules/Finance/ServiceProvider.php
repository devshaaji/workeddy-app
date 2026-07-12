<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Finance;

use WorkEddy\Modules\Finance\Authorization\FinancePermissionDefinitionProvider;
use WorkEddy\Modules\Finance\Application\UseCases\RecordExpense;
use WorkEddy\Modules\Finance\Application\UseCases\RecordIncome;
use WorkEddy\Modules\Finance\Application\UseCases\RefreshPayrollSummary;
use WorkEddy\Modules\Finance\Domain\Contracts\IFinanceRepository;
use WorkEddy\Modules\Finance\Infrastructure\DbalFinanceRepository;
use WorkEddy\Modules\Finance\Presentation\Controllers\FinanceApiController;
use WorkEddy\Modules\Finance\Presentation\Controllers\FinancePageController;
use WorkEddy\Modules\Finance\Presentation\FinancePageData;
use WorkEddy\Modules\Finance\Settings\FinanceSettings;
use WorkEddy\Modules\Finance\Settings\FinanceSettingsProvider;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Platform\Authorization\IPermissionDefinitionProvider;
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
        return 'Finance';
    }

    public function getDefinitions(): array
    {
        return [
            FinanceSettingsProvider::class => static fn(): FinanceSettingsProvider => new FinanceSettingsProvider(),
            FinanceSettings::class => static fn(ContainerInterface $c): FinanceSettings => new FinanceSettings($c->get(SettingsService::class)),
            IFinanceRepository::class => static fn(ContainerInterface $c): IFinanceRepository => $c->get(DbalFinanceRepository::class),
            DbalFinanceRepository::class => static fn(ContainerInterface $c): DbalFinanceRepository => new DbalFinanceRepository($c->get(Connection::class)),
            RecordIncome::class => static fn(ContainerInterface $c): RecordIncome => new RecordIncome($c->get(IFinanceRepository::class)),
            RecordExpense::class => static fn(ContainerInterface $c): RecordExpense => new RecordExpense($c->get(IFinanceRepository::class), $c->get(FinanceSettings::class)),
            RefreshPayrollSummary::class => static fn(ContainerInterface $c): RefreshPayrollSummary => new RefreshPayrollSummary($c->get(IFinanceRepository::class), $c->get(Connection::class), $c->get(FinanceSettings::class)),
            FinancePageData::class => static fn(ContainerInterface $c): FinancePageData => new FinancePageData($c->get(IFinanceRepository::class), $c->get(FinanceSettings::class)),
            FinancePageController::class => static fn(ContainerInterface $c): FinancePageController => new FinancePageController(
                $c->get(ISessionService::class),
                $c->get(IPermissionService::class),
                $c->get(ViewRenderer::class),
                $c->get(FinancePageData::class),
            ),
            FinanceApiController::class => static fn(ContainerInterface $c): FinanceApiController => new FinanceApiController(
                $c->get(IFinanceRepository::class),
                $c->get(RecordIncome::class),
                $c->get(RecordExpense::class),
                $c->get(RefreshPayrollSummary::class),
                $c->get(SettingsService::class),
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
        return [];
    }

    public function getJobHandlers(): array
    {
        return [];
    }

    public function getPermissionDefinitionProvider(): ?IPermissionDefinitionProvider
    {
        return new FinancePermissionDefinitionProvider();
    }

    public function getSettingsProvider(): mixed
    {
        return new FinanceSettingsProvider();
    }

    public function getConsoleCommandProvider(): mixed
    {
        return null;
    }

    public function boot(ContainerInterface $container): void {}
}
