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
        $greeting = $this->getDashboardGreeting();
        $warmMessage = $this->getWorkEddyWarmMessage();
        $userName = ucwords(strtolower($this->session->get('username') ?? 'User'));
        $vars['greeting'] = "$greeting, $userName!";
        $vars['warmMessage'] = $warmMessage;
        $vars['pageTitle'] = 'Dashboard';
        $dashboardData = $this->reportingPageData->dashboardOverview($ctx, $this->dashboardFilters($request));

        return $this->views->render(
            'shared/Views/Pages/Dashboard/workeddy_dashboard.php',
            'WorkEddy',
            array_replace(
                ['routeParams' => $vars],
                $dashboardData,
                [
                    'greeting' => $vars['greeting'],
                    'warmMessage' => $vars['warmMessage'],
                ],
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

    private function getDashboardGreeting(): string
    {
        $hour = (int) date('H');

        return match (true) {
            $hour >= 5 && $hour < 12 => 'Good morning',
            $hour >= 12 && $hour < 17 => 'Good afternoon',
            $hour >= 17 && $hour < 21 => 'Good evening',
            default    => 'Good night',
        };
    }




    private function getWorkEddyWarmMessage(): string
    {
        $hour = (int) date('G');

        return match (true) {
            $hour >= 5  && $hour < 12 => 'Review pending assessments and corrective actions.',
            $hour >= 12 && $hour < 17 => 'Check high-risk tasks and reviewer queue.',
            $hour >= 17 && $hour < 21 => 'Review today\'s completed assessments and follow-ups.',
            default                    => 'Plan tomorrow\'s ergonomic assessments.',
        };
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
