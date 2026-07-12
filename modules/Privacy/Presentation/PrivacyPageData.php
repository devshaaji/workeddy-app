<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Privacy\Presentation;

use WorkEddy\Modules\IAM\Domain\Contracts\IUserRepository;
use WorkEddy\Modules\Privacy\Authorization\PrivacyPermissions;
use WorkEddy\Platform\Session\UserContext;

final class PrivacyPageData
{
    public function __construct(
        private readonly IUserRepository $users,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function common(UserContext $ctx, string $title): array
    {
        $recordConsent = in_array(PrivacyPermissions::CONSENT_RECORD, $ctx->privileges, true);
        $accessVideo = in_array(PrivacyPermissions::VIDEO_ACCESS, $ctx->privileges, true);
        $manageRetention = in_array(PrivacyPermissions::RETENTION_MANAGE, $ctx->privileges, true);
        $enforceRetention = in_array(PrivacyPermissions::RETENTION_ENFORCE, $ctx->privileges, true);
        $viewAudit = in_array(PrivacyPermissions::AUDIT_VIEW, $ctx->privileges, true);

        $userName = null;
        $user = $this->users->findById($ctx->userId);
        if ($user !== null) {
            $userName = $user->getFullName();
        }

        return [
            'pageTitle' => $title,
            'pagePurpose' => 'Privacy & compliance',
            'organizationUuid' => $ctx->organizationUuid,
            'userId' => $ctx->userId,
            'userName' => $userName,
            'can' => [
                'viewOverview' => $recordConsent || $manageRetention || $viewAudit,
                'recordConsent' => $recordConsent,
                'accessVideo' => $accessVideo,
                'manageRetention' => $manageRetention,
                'enforceRetention' => $enforceRetention,
                'viewAudit' => $viewAudit,
            ],
        ];
    }
}
