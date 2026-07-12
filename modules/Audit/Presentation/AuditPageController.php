<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Audit\Presentation;

use WorkEddy\Modules\Audit\Authorization\AuditPermissions;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\AuthenticationException;
use WorkEddy\Shared\Presentation\ViewRenderer;
use WorkEddy\Shared\Support\UuidSupport;


final class AuditPageController
{
    public function __construct(
        private readonly ViewRenderer $views,
        private readonly ISessionService $session,
        private readonly IPermissionService $permissions,
    ) {}

    public function logs(Request $request): Response
    {
        $vars = $request->routeParams;
        $ctx = $this->requirePrivilege(AuditPermissions::VIEW);
        return $this->render('Log/index.php', $vars, $ctx);
    }

    public function showLog(Request $request): Response
    {
        $vars = $request->routeParams;
        $ctx = $this->requirePrivilege(AuditPermissions::VIEW);
        $this->requireAuditLogUuid($request);
        return $this->render('Log/show.php', $vars, $ctx);
    }

    public function export(Request $request): Response
    {
        $vars = $request->routeParams;
        $ctx = $this->requirePrivilege(AuditPermissions::EXPORT);
        return $this->render('Log/export.php', $vars, $ctx);
    }

    public function settings(Request $request): Response
    {
        $vars = $request->routeParams;
        $ctx = $this->requirePrivilege(AuditPermissions::SETTINGS_MANAGE);
        return $this->render('Settings/index.php', $vars, $ctx);
    }

    private function render(string $view, array $vars, UserContext $ctx): Response
    {
        return $this->views->render(
            'modules/Audit/Presentation/Views/' . $view,
            'Audit',
            [
                'routeParams' => $vars,
                'can' => $this->can($ctx),
            ],
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

    /**
     * @return array<string, bool>
     */
    private function can(UserContext $ctx): array
    {
        return [
            'viewAuditLogs' => in_array(AuditPermissions::VIEW, $ctx->privileges, true),
            'exportAuditLogs' => in_array(AuditPermissions::EXPORT, $ctx->privileges, true),
            'manageSettings' => in_array(AuditPermissions::SETTINGS_MANAGE, $ctx->privileges, true),
        ];
    }

    /**
     * @param array<string, mixed> $vars
     */
    private function requireAuditLogUuid(Request $request): string
    {
        $vars = $request->routeParams;
        return UuidSupport::requireValid((string) ($vars['id'] ?? ''));
    }
}
