<?php

declare(strict_types=1);

namespace WorkEddy\Shared\Presentation;

use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Modules\Reporting\Presentation\ReportingPageData;
use WorkEddy\Shared\Exceptions\AuthenticationException;

final class CorePageController
{
    public function __construct(
        private readonly ViewRenderer $views,
        private readonly ISessionService $session,
        private readonly ReportingPageData $reportingPageData,
    ) {}

    public function index(Request $request): Response
    {
        $vars = $request->routeParams;
        $vars['pageTitle'] = 'Browse Point MX';
        $vars['page'] = 'home';
        $vars['routeParams'] = $vars;
        return $this->views->renderPublic('shared/Views/Pages/Public/index.php', 'Public', $vars);
    }


    public function dashboard(Request $request): Response
    {
        $vars = $request->routeParams;
        $ctx = $this->requireContext();
        $userName = ucwords(strtolower($this->session->get('username') ?? ''));
        $vars['pageTitle'] = 'Dashboard';
        $dashboardData = $this->reportingPageData->dashboardOverview($ctx, $this->dashboardFilters($request));

        return $this->views->render(
            'shared/Views/Pages/Dashboard/workeddy_dashboard.php',
            'WorkEddy',
            array_replace(
                ['routeParams' => $vars],
                $dashboardData,
                ['username' => $userName]
            ),
            true,
            'app'
        );
    }


    private function requireContext(): \WorkEddy\Platform\Session\UserContext
    {
        $ctx = $this->session->getUserContext();
        if ($ctx === null) {
            throw new AuthenticationException('Unauthenticated');
        }

        return $ctx;
    }

    /** @return array<string, string> */
    private function dashboardFilters(Request $request): array
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
