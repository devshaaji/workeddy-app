<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Privacy\Application;

use WorkEddy\Modules\Privacy\Domain\Contracts\IPrivacyRepository;
use WorkEddy\Modules\Privacy\Domain\RetentionPolicy;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Support\UuidSupport;

final class UpdateRetentionPolicyUseCase
{
    public function __construct(
        private readonly IPrivacyRepository $privacy,
        private readonly IAuditService $audit,
        private readonly IClock $clock,
    ) {}

    /** @return array<string, mixed> */
    public function execute(int $organizationId, string $organizationUuid, UserContext $actor, string $rawVideoPolicy, bool $retainScreenshotsOnly, bool $retainForPilotEvidence, int $retentionDays): array
    {
        $before = $this->privacy->findRetentionPolicyByOrganizationId($organizationId)?->toView();
        $policy = $this->privacy->upsertRetentionPolicy(new RetentionPolicy(
            id: null,
            organizationId: $organizationId,
            organizationUuid: UuidSupport::requireValid($organizationUuid, 'organizationUuid'),
            rawVideoPolicy: $rawVideoPolicy,
            retainScreenshotsOnly: $retainScreenshotsOnly,
            retainForPilotEvidence: $retainForPilotEvidence,
            retentionDays: $retentionDays,
            updatedBy: $actor->userId,
            updatedAt: $this->clock->now()->format('Y-m-d H:i:s'),
        ));

        $this->audit->record('privacy.retention_policy.updated', 'retention_policy', $organizationUuid, beforeState: $before, afterState: $policy->toView(), actorId: (string) $actor->userId, actorType: 'user');

        return $policy->toView();
    }
}
