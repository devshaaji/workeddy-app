<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Subscription\Presentation;

use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\Subscription\Authorization\SubscriptionPermissions;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionRepository;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Shared\Exceptions\AuthenticationException;
use WorkEddy\Shared\Presentation\ViewRenderer;

final class SubscriptionPageController
{
    public function __construct(
        private readonly ISessionService $session,
        private readonly IPermissionService $permissions,
        private readonly ViewRenderer $views,
        private readonly SubscriptionPageData $pageData,
        private readonly ISubscriptionRepository $repository,
    ) {}

    public function index(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, SubscriptionPermissions::VIEW);

        if ($ctx->organizationId !== null && !$ctx->hasPermission(SubscriptionPermissions::MANAGE_PLANS)) {
            $subscription = $this->repository->findByOrganizationId($ctx->organizationId);
            if ($subscription !== null) {
                return Response::redirect('/subscriptions/' . $subscription->uuid);
            }
        }

        return $this->views->render(
            'modules/Subscription/Presentation/Views/Index/index.php',
            'Subscription',
            $this->pageData->index($ctx),
        );
    }

    public function show(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, SubscriptionPermissions::VIEW);

        return $this->views->render(
            'modules/Subscription/Presentation/Views/Index/detail.php',
            'Subscription',
            $this->pageData->detail((string) $request->routeParam('uuid')),
        );
    }

    public function settings(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, SubscriptionPermissions::MANAGE_PLANS);

        return $this->views->render(
            'modules/Subscription/Presentation/Views/Index/settings.php',
            'Subscription',
            $this->pageData->settings(),
        );
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
