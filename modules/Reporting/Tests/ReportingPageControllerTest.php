<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Reporting\Tests;

use PHPUnit\Framework\TestCase;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\Reporting\Authorization\ReportingPermissions;
use WorkEddy\Modules\Reporting\Presentation\ReportingPageController;
use WorkEddy\Modules\Reporting\Presentation\ReportingPageData;
use WorkEddy\Platform\Config\ConfigLoader;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Presentation\ViewRenderer;

final class ReportingPageControllerTest extends TestCase
{
    public function testDashboardRequiresSystemViewPermission(): void
    {
        $permissions = $this->permissionProbe(ReportingPermissions::SYSTEM_VIEW);

        $controller = $this->controller($permissions);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('stop after permission check');

        $controller->dashboard($this->request());
    }

    public function testPilotSummaryKeepsOrgReportingPermission(): void
    {
        $permissions = $this->permissionProbe(ReportingPermissions::VIEW);

        $controller = $this->controller($permissions);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('stop after permission check');

        $controller->pilotSummary($this->request());
    }

    private function controller(IPermissionService $permissions): ReportingPageController
    {
        return new ReportingPageController(
            new ReportingPageTestSessionService(),
            $permissions,
            new ViewRenderer(new ConfigLoader(dirname(__DIR__, 3) . '/config')),
            $this->pageDataStub(),
        );
    }

    private function request(): Request
    {
        return new Request(
            method: 'GET',
            uri: '/reporting/test',
            headers: [],
            query: [],
            body: [],
            routeParams: []
        );
    }

    private function permissionProbe(string $expectedPrivilege): IPermissionService
    {
        return new class($this, $expectedPrivilege) implements IPermissionService {
            public function __construct(
                private readonly TestCase $test,
                private readonly string $expectedPrivilege,
            ) {}

            public function requirePrivilege(UserContext $ctx, string $privilege): void
            {
                $this->test->assertSame($this->expectedPrivilege, $privilege);
                throw new \RuntimeException('stop after permission check');
            }
        };
    }

    private function pageDataStub(): ReportingPageData
    {
        return (new \ReflectionClass(ReportingPageData::class))->newInstanceWithoutConstructor();
    }
}

final class ReportingPageTestSessionService implements ISessionService
{
    public function getUserContext(): ?UserContext
    {
        return new UserContext(userId: 42, organizationUuid: 'org-uuid');
    }

    public function setUserContext(UserContext $context): void
    {
    }

    public function regenerate(): void
    {
    }

    public function destroy(): void
    {
    }

    public function get(string $key): mixed
    {
        return null;
    }

    public function set(string $key, mixed $value): void
    {
    }
}
