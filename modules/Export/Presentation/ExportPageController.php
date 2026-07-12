<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Export\Presentation;

use WorkEddy\Modules\Export\Authorization\ExportPermissions;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Shared\Exceptions\AuthenticationException;
use WorkEddy\Shared\Presentation\ViewRenderer;

final class ExportPageController
{
    public function __construct(
        private readonly ISessionService $session,
        private readonly IPermissionService $permissions,
        private readonly ViewRenderer $views,
        private readonly ExportPageData $pageData,
    ) {}

    public function index(Request $request): Response
    {
        unset($request);
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, ExportPermissions::VIEW);

        return $this->views->render('modules/Export/Presentation/Views/Index/index.php', 'Export', $this->pageData->index($ctx));
    }

    private function requireContext(): \WorkEddy\Platform\Session\UserContext
    {
        $ctx = $this->session->getUserContext();
        if ($ctx === null) {
            throw new AuthenticationException('Unauthenticated.');
        }

        return $ctx;
    }
}
