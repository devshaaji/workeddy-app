<?php

declare(strict_types=1);

namespace WorkEddy\Modules\CorrectiveAction\Application;

use WorkEddy\Modules\CorrectiveAction\Authorization\CorrectiveActionPermissions;
use WorkEddy\Modules\CorrectiveAction\Domain\Contracts\ICorrectiveActionRepository;
use WorkEddy\Modules\CorrectiveAction\Domain\RecommendationRule;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\ValidationException;
use WorkEddy\Shared\Support\UuidSupport;

final class UpsertRecommendationRuleUseCase
{
    public function __construct(
        private readonly ICorrectiveActionRepository $repository,
        private readonly IPermissionService $permissions,
        private readonly IAuditService $audit,
    ) {}

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function execute(UserContext $actor, array $data): array
    {
        $this->permissions->requirePrivilege($actor, CorrectiveActionPermissions::MANAGE_LIBRARY);
        if (!is_array($data['condition'] ?? null)) {
            throw new ValidationException(['condition' => 'Condition object is required.']);
        }
        if (!is_array($data['action'] ?? null)) {
            throw new ValidationException(['action' => 'Action object is required.']);
        }

        $rule = new RecommendationRule(
            id: null,
            uuid: isset($data['uuid']) && trim((string) $data['uuid']) !== '' ? UuidSupport::requireValid((string) $data['uuid'], 'uuid') : UuidSupport::generate(),
            condition: $data['condition'],
            action: $data['action'],
            weight: (int) ($data['weight'] ?? 100),
            isActive: (bool) ($data['isActive'] ?? $data['is_active'] ?? true),
        );

        $saved = $this->repository->upsertRecommendationRule($rule);
        $this->audit->record('corrective_action.recommendation_rule.upserted', 'recommendation_rule', $saved->uuid, afterState: $saved->toView(), actorId: (string) $actor->userId, actorType: 'user');

        return $saved->toView();
    }
}
