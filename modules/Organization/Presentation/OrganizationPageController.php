<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Organization\Presentation;

use WorkEddy\Modules\Organization\Authorization\OrganizationPermissions;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\AuthenticationException;
use WorkEddy\Shared\Presentation\ViewRenderer;

final class OrganizationPageController
{
    public function __construct(
        private readonly ViewRenderer $views,
        private readonly ISessionService $session,
        private readonly IPermissionService $permissions,
        private readonly OrganizationPageData $pageData,
    ) {}

    public function index(Request $request): Response
    {
        return $this->render('organizations.php', $request, $this->requirePrivilege(OrganizationPermissions::VIEW), 'Organizations', ['pageScripts' => ['js/modules/organization-index.js']]);
    }

    public function show(Request $request): Response
    {
        return $this->render('organization_show.php', $request, $this->requirePrivilege(OrganizationPermissions::VIEW), 'Organization', ['pageScripts' => ['js/modules/organization-show.js']]);
    }

    public function worksites(Request $request): Response
    {
        return $this->render('worksites.php', $request, $this->requirePrivilege(OrganizationPermissions::VIEW), 'Worksites', ['pageScripts' => ['js/modules/organization-worksites.js']]);
    }

    public function pilotSites(Request $request): Response
    {
        return $this->render('pilot_sites.php', $request, $this->requirePrivilege(OrganizationPermissions::VIEW), 'Pilot Sites', ['pageScripts' => ['js/modules/organization-pilot-sites.js']]);
    }

    public function departments(Request $request): Response
    {
        return $this->render('departments.php', $request, $this->requirePrivilege(OrganizationPermissions::VIEW), 'Departments', ['pageScripts' => ['js/modules/organization-departments.js']]);
    }

    public function jobRoles(Request $request): Response
    {
        return $this->render('job_roles.php', $request, $this->requirePrivilege(OrganizationPermissions::VIEW), 'Job Roles', ['pageScripts' => ['js/modules/organization-job-roles.js']]);
    }

    public function members(Request $request): Response
    {
        return $this->render('members.php', $request, $this->requirePrivilege(OrganizationPermissions::VIEW), 'Members', ['pageScripts' => ['js/modules/organization-members.js']]);
    }

    private function render(string $view, Request $request, UserContext $ctx, string $title, array $extra = []): Response
    {
        return $this->views->render(
            'modules/Organization/Presentation/Views/' . $view,
            'Organization',
            array_replace($this->pageData->common($ctx, $title), ['routeParams' => $request->routeParams], $extra),
        );
    }

    private function requirePrivilege(string $privilege): UserContext
    {
        $ctx = $this->context();
        $this->permissions->requirePrivilege($ctx, $privilege);

        return $ctx;
    }

    private function context(): UserContext
    {
        $ctx = $this->session->getUserContext();
        if ($ctx === null) {
            throw new AuthenticationException('Unauthenticated');
        }

        return $ctx;
    }
}
