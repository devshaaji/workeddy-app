<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Organization\Presentation;

use WorkEddy\Modules\Organization\Authorization\OrganizationPermissions;
use WorkEddy\Modules\Organization\Domain\Contracts\IOrganizationRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\AuthenticationException;
use WorkEddy\Shared\Exceptions\WrongScopeException;
use WorkEddy\Shared\Presentation\ViewRenderer;

final class OrganizationPageController
{
    public function __construct(
        private readonly ViewRenderer $views,
        private readonly ISessionService $session,
        private readonly IPermissionService $permissions,
        private readonly OrganizationPageData $pageData,
        private readonly IOrganizationRepository $organizations,
    ) {}

    public function index(Request $request): Response
    {
        return $this->render('organizations.php', $request, $this->requirePrivilege(OrganizationPermissions::VIEW), 'Organizations', ['pageScripts' => ['js/modules/organization-index.js']]);
    }

    public function show(Request $request): Response
    {
        $ctx = $this->requirePrivilege(OrganizationPermissions::VIEW);
        $this->assertOrganizationScope($request, $ctx);
        return $this->render('organization_show.php', $request, $ctx, 'Organization', ['pageScripts' => ['js/modules/organization-show.js']]);
    }

    public function worksites(Request $request): Response
    {
        $ctx = $this->requirePrivilege(OrganizationPermissions::VIEW);
        $this->assertOrganizationScope($request, $ctx);
        return $this->render('worksites.php', $request, $ctx, 'Worksites', ['pageScripts' => ['js/modules/organization-worksites.js']]);
    }

    public function pilotSites(Request $request): Response
    {
        $ctx = $this->requirePrivilege(OrganizationPermissions::VIEW);
        $this->assertOrganizationScope($request, $ctx);
        return $this->render('pilot_sites.php', $request, $ctx, 'Pilot Sites', ['pageScripts' => ['js/modules/organization-pilot-sites.js']]);
    }

    public function departments(Request $request): Response
    {
        $ctx = $this->requirePrivilege(OrganizationPermissions::VIEW);
        $this->assertOrganizationScope($request, $ctx);
        return $this->render('departments.php', $request, $ctx, 'Departments', ['pageScripts' => ['js/modules/organization-departments.js']]);
    }

    public function jobRoles(Request $request): Response
    {
        $ctx = $this->requirePrivilege(OrganizationPermissions::VIEW);
        $this->assertOrganizationScope($request, $ctx);
        return $this->render('job_roles.php', $request, $ctx, 'Job Roles', ['pageScripts' => ['js/modules/organization-job-roles.js']]);
    }

    public function members(Request $request): Response
    {
        $ctx = $this->requirePrivilege(OrganizationPermissions::VIEW);
        $this->assertOrganizationScope($request, $ctx);
        return $this->render('members.php', $request, $ctx, 'Members', ['pageScripts' => ['js/modules/organization-members.js']]);
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

    private function assertOrganizationScope(Request $request, UserContext $ctx): void
    {
        $organizationUuid = trim((string) ($request->routeParam('id') ?? ''));
        if ($organizationUuid === '' || $ctx->organizationUuid === null || $ctx->organizationUuid === '') {
            return;
        }

        if ($ctx->organizationUuid === $organizationUuid) {
            return;
        }

        $organization = $this->organizations->findByUuid($organizationUuid);
        throw new WrongScopeException(
            message: 'This page belongs to a different organization than the one currently selected.',
            organizationName: $organization?->getName(),
            organizationUuid: $organizationUuid,
        );
    }
}
