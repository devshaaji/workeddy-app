<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Reporting\Presentation;

use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\Reporting\Authorization\ReportingPermissions;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Shared\Exceptions\AuthenticationException;
use WorkEddy\Shared\Presentation\ViewRenderer;

final class ReportingPageController
{
    public function __construct(
        private readonly ISessionService $session,
        private readonly IPermissionService $permissions,
        private readonly ViewRenderer $views,
        private readonly ReportingPageData $pageData,
    ) {}

    public function dashboard(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, ReportingPermissions::SYSTEM_VIEW);

        return $this->views->render('modules/Reporting/Presentation/Views/Index/index.php', 'Reporting', $this->pageData->dashboard($ctx));
    }

    public function finance(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, ReportingPermissions::SYSTEM_VIEW);

        return $this->views->render('modules/Reporting/Presentation/Views/Finance/index.php', 'Reporting', $this->pageData->finance($ctx));
    }

    public function operations(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, ReportingPermissions::SYSTEM_VIEW);

        return $this->views->render('modules/Reporting/Presentation/Views/Operations/index.php', 'Reporting', $this->pageData->operations($ctx));
    }

    public function pilotSummary(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, ReportingPermissions::VIEW);

        return $this->views->render(
            'modules/Reporting/Presentation/Views/Pilot-summary/index.php',
            'Reporting',
            array_replace(
                $this->pageData->pilotSummary($ctx, $this->pilotFilters($request)),
                ['pageScripts' => ['js/modules/pilot-summary.js']],
            ),
        );
    }

    public function impactTracker(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, ReportingPermissions::VIEW);

        return $this->views->render(
            'modules/Reporting/Presentation/Views/Impact-tracker/index.php',
            'Reporting',
            $this->pageData->impactTracker($ctx, $this->pilotFilters($request)),
        );
    }

    public function assessment(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, ReportingPermissions::VIEW);
        $uuid = (string) $request->routeParam('uuid');

        return $this->views->render('modules/Reporting/Presentation/Views/Assessment/index.php', 'Reporting', $this->pageData->assessment($uuid, $ctx));
    }

    public function correctiveAction(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, ReportingPermissions::VIEW);
        $uuid = (string) $request->routeParam('uuid');

        return $this->views->render('modules/Reporting/Presentation/Views/Corrective-action/index.php', 'Reporting', $this->pageData->correctiveAction($uuid, $ctx));
    }

    public function comparison(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, ReportingPermissions::VIEW);
        $uuid = (string) $request->routeParam('uuid');

        return $this->views->render('modules/Reporting/Presentation/Views/Comparison/index.php', 'Reporting', $this->pageData->comparison($uuid, $ctx));
    }

    public function auditTrail(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, ReportingPermissions::VIEW);
        $uuid = (string) $request->routeParam('uuid');

        return $this->views->render('modules/Reporting/Presentation/Views/Audit-trail/index.php', 'Reporting', $this->pageData->auditTrail($uuid, $ctx));
    }

    public function settings(Request $request): Response
    {
        return $this->dashboard($request);
    }

    private function requireContext(): \WorkEddy\Platform\Session\UserContext
    {
        $ctx = $this->session->getUserContext();
        if ($ctx === null) {
            throw new AuthenticationException('Unauthenticated.');
        }

        return $ctx;
    }

    /** @return array<string, string> */
    private function pilotFilters(Request $request): array
    {
        return [
            'industry' => trim((string) $request->query('industry', '')),
            'worksiteUuid' => trim((string) $request->query('worksiteUuid', '')),
            'departmentUuid' => trim((string) $request->query('departmentUuid', '')),
            'jobRoleUuid' => trim((string) $request->query('jobRoleUuid', '')),
            'bodyRegion' => trim((string) $request->query('bodyRegion', '')),
            'fromDate' => trim((string) $request->query('fromDate', '')),
            'toDate' => trim((string) $request->query('toDate', '')),
            'riskLevel' => trim((string) $request->query('riskLevel', '')),
        ];
    }
}
