<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Presentation;

use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Modules\IAM\Application\LogoutUseCase;
use WorkEddy\Modules\IAM\Authorization\IAMPermissions;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Shared\Presentation\ViewRenderer;

final class IAMPageController
{
    public function __construct(
        private readonly LogoutUseCase $logoutUseCase,
        private readonly ISessionService $session,
        private readonly IPermissionService $permissions,
        private readonly ViewRenderer $views,
        private readonly IAMPageData $pageData,
    ) {}

    public function login(Request $request): Response
    {
        $vars = $request->routeParams;
        $ctx = $this->session->getUserContext();
        if ($ctx !== null) {
            return Response::redirect('/dashboard');
        }
        return $this->renderAuth('login.php', $vars);
    }

    public function logout(Request $request): Response
    {
        $vars = $request->routeParams;
        $ctx = $this->session->getUserContext();

        if ($ctx !== null) {
            $this->logoutUseCase->execute($ctx);
        }

        return Response::redirect('/login');
    }

    public function register(Request $request): Response
    {
        $vars = $request->routeParams;
        return $this->renderAuth('register.php', $vars);
    }
    public function forgotPassword(Request $request): Response
    {
        $vars = $request->routeParams;
        return $this->renderAuth('forgot_password.php', $vars);
    }
    public function resetPassword(Request $request): Response
    {
        $vars = $request->routeParams;
        return $this->renderAuth('reset_password.php', $vars);
    }
    public function verifyOtp(Request $request): Response
    {
        $vars = $request->routeParams;
        $pending = $this->session->get('pending_auth');

        if (!is_array($pending)) {
            return Response::redirect('/login');
        }

        $expiresAt = isset($pending['expiresAt']) ? strtotime((string) $pending['expiresAt']) : false;
        if ($expiresAt === false || $expiresAt <= time()) {
            $this->session->set('pending_auth', null);

            return Response::redirect('/login');
        }

        return $this->renderAuth('verify_otp.php', $vars, ['pendingAuthExpiresAt' => (string) $pending['expiresAt']]);
    }

    public function users(Request $request): Response
    {
        $vars = $request->routeParams;
        $this->requirePrivilege(IAMPermissions::USER_VIEW);
        return $this->render('User/index.php', $vars);
    }
    public function createUser(Request $request): Response
    {
        $vars = $request->routeParams;
        $this->requirePrivilege(IAMPermissions::USER_CREATE);
        return $this->render('User/create.php', $vars);
    }
    public function showUser(Request $request): Response
    {
        $vars = $request->routeParams;
        $this->requirePrivilege(IAMPermissions::USER_VIEW);
        return $this->render('User/show.php', $vars, $this->pageData->user($this->context(), (string) ($vars['id'] ?? '')));
    }
    public function editUser(Request $request): Response
    {
        $vars = $request->routeParams;
        $this->requirePrivilege(IAMPermissions::USER_UPDATE);
        return $this->render('User/edit.php', $vars, $this->pageData->user($this->context(), (string) ($vars['id'] ?? '')));
    }
    public function assignUserRole(Request $request): Response
    {
        $vars = $request->routeParams;
        $this->requirePrivilege(IAMPermissions::ROLE_ASSIGN);
        return $this->render('User/assign_role.php', $vars, $this->pageData->user($this->context(), (string) ($vars['id'] ?? '')));
    }
    public function userSecurity(Request $request): Response
    {
        $vars = $request->routeParams;
        $this->requirePrivilege(IAMPermissions::USER_PASSWORD_RESET);
        return $this->render('User/security.php', $vars, $this->pageData->user($this->context(), (string) ($vars['id'] ?? '')));
    }
    public function pendingApprovals(Request $request): Response
    {
        $vars = $request->routeParams;
        $this->requirePrivilege(IAMPermissions::USER_VIEW);
        return $this->render('User/pending_approvals.php', $vars);
    }

    public function roles(Request $request): Response
    {
        $vars = $request->routeParams;
        $this->requirePrivilege(IAMPermissions::ROLE_MANAGE);
        return $this->render('Role/index.php', $vars);
    }
    public function createRole(Request $request): Response
    {
        $vars = $request->routeParams;
        $this->requirePrivilege(IAMPermissions::ROLE_MANAGE);
        return $this->render('Role/create.php', $vars);
    }
    public function showRole(Request $request): Response
    {
        $vars = $request->routeParams;
        $this->requirePrivilege(IAMPermissions::ROLE_MANAGE);
        return $this->render('Role/show.php', $vars, $this->pageData->role((string) ($vars['id'] ?? '')));
    }
    public function editRole(Request $request): Response
    {
        $vars = $request->routeParams;
        $this->requirePrivilege(IAMPermissions::ROLE_MANAGE);
        return $this->render('Role/edit.php', $vars, $this->pageData->role((string) ($vars['id'] ?? '')));
    }
    public function assignPermissions(Request $request): Response
    {
        $vars = $request->routeParams;
        $this->requirePrivilege(IAMPermissions::PERMISSION_ASSIGN);
        return $this->render('Role/assign_permissions.php', $vars, $this->pageData->role((string) ($vars['id'] ?? '')));
    }

    public function permissions(Request $request): Response
    {
        $vars = $request->routeParams;
        $this->requirePrivilege(IAMPermissions::PERMISSION_ASSIGN);
        return $this->render('Permission/index.php', $vars);
    }
    public function profile(Request $request): Response
    {
        $vars = $request->routeParams;
        return $this->render('Profile/show.php', $vars, $this->pageData->profile($this->context()));
    }
    public function profileSecurity(Request $request): Response
    {
        $vars = $request->routeParams;
        return $this->render('Profile/security.php', $vars);
    }
    public function profileSessions(Request $request): Response
    {
        $vars = $request->routeParams;
        return $this->render('Profile/sessions.php', $vars);
    }
    public function wrongScope(Request $request): Response
    {
        return $this->views->renderScopeErrorPage(
            (string) ($request->query('message') ?? 'You do not have access to this page in the current organization scope.'),
            ($request->query('organization') ?? null) !== null ? (string) $request->query('organization') : null,
            ($request->query('organization_uuid') ?? null) !== null ? (string) $request->query('organization_uuid') : null,
            ($request->query('action') ?? null) !== null ? (string) $request->query('action') : null,
        );
    }
    public function settings(Request $request): Response
    {
        $vars = $request->routeParams;
        $ctx = $this->context();
        $requestedModule = trim((string) ($request->query('module') ?? ''));
        $pageMetadata = $requestedModule !== '' ? $this->pageData->settingsPageMetadata($requestedModule) : null;
        if ($requestedModule !== '' && ($pageMetadata === null || !$pageMetadata->canView($ctx))) {
            return $this->views->renderScopeErrorPage('You do not have access to this settings module in the current scope.');
        }

        return $this->render('Settings/index.php', $vars, $this->pageData->settings($ctx, $requestedModule));
    }

    private function render(string $view, array $vars, array $data = []): Response
    {
        $ctx = $this->session->getUserContext();
        if ($ctx !== null) {
            $data = array_replace($this->pageData->common($ctx), $data);
        }

        return $this->views->render(
            'modules/IAM/Presentation/Views/' . $view,
            'IAM',
            array_replace(['routeParams' => $vars], $data),
        );
    }

    private function renderAuth(string $view, array $vars, array $data = []): Response
    {
        return $this->views->renderAuth(
            'modules/IAM/Presentation/Views/Auth/' . $view,
            'IAM',
            array_replace(['routeParams' => $vars], $data),
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
            throw new \WorkEddy\Shared\Exceptions\AuthenticationException('Unauthenticated');
        }

        return $ctx;
    }
}
