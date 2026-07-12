<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Privacy\Presentation;

use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\Privacy\Authorization\PrivacyPermissions;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\AuthenticationException;
use WorkEddy\Shared\Presentation\ViewRenderer;

final class PrivacyPageController
{
    public function __construct(
        private readonly ViewRenderer $views,
        private readonly ISessionService $session,
        private readonly IPermissionService $permissions,
        private readonly PrivacyPageData $pageData,
    ) {}

    public function index(Request $request): Response
    {
        unset($request);

        return $this->views->render(
            'modules/Privacy/Presentation/Views/index.php',
            'Privacy',
            $this->pageData->common($this->context(), 'Privacy Dashboard'),
        );
    }

    public function consent(Request $request): Response
    {
        return $this->render('consent.php', $request, $this->requirePrivilege(PrivacyPermissions::CONSENT_RECORD), 'Video Consent', ['pageScripts' => ['js/modules/privacy.js']]);
    }

    public function retention(Request $request): Response
    {
        return $this->render('retention.php', $request, $this->requirePrivilege(PrivacyPermissions::RETENTION_MANAGE), 'Retention Policy', ['pageScripts' => ['js/modules/privacy.js']]);
    }

    public function videoAccessLog(Request $request): Response
    {
        return $this->render('video_access_log.php', $request, $this->requirePrivilege(PrivacyPermissions::AUDIT_VIEW), 'Video Access Log', ['pageScripts' => ['js/modules/privacy.js']]);
    }

    private function render(string $view, Request $request, UserContext $ctx, string $title, array $extra = []): Response
    {
        return $this->views->render(
            'modules/Privacy/Presentation/Views/' . $view,
            'Privacy',
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
