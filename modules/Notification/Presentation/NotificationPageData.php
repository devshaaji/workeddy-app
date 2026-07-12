<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Presentation;

use WorkEddy\Modules\IAM\Domain\Contracts\IUserRepository;
use WorkEddy\Modules\Notification\Authorization\NotificationPermissions;
use WorkEddy\Platform\Session\UserContext;

final class NotificationPageData
{
    public function __construct(
        private readonly IUserRepository $users,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function common(UserContext $ctx, string $title): array
    {
        $logView = in_array(NotificationPermissions::LOG_VIEW, $ctx->privileges, true);
        $logRetry = in_array(NotificationPermissions::LOG_RETRY, $ctx->privileges, true);
        $templateView = in_array(NotificationPermissions::TEMPLATE_VIEW, $ctx->privileges, true);
        $templateManage = in_array(NotificationPermissions::TEMPLATE_MANAGE, $ctx->privileges, true);
        $settingsManage = in_array(NotificationPermissions::SETTINGS_MANAGE, $ctx->privileges, true);

        $userName = null;
        $user = $this->users->findById($ctx->userId);
        if ($user !== null) {
            $userName = $user->getFullName();
        }

        return [
            'pageTitle' => $title,
            'pagePurpose' => 'Notification management',
            'organizationUuid' => $ctx->organizationUuid,
            'userId' => $ctx->userId,
            'userName' => $userName,
            'can' => [
                'logView' => $logView,
                'logRetry' => $logRetry,
                'templateView' => $templateView,
                'templateManage' => $templateManage,
                'settingsManage' => $settingsManage,
            ],
        ];
    }
}
