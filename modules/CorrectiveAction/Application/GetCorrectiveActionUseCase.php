<?php

declare(strict_types=1);

namespace WorkEddy\Modules\CorrectiveAction\Application;

use WorkEddy\Modules\CorrectiveAction\Authorization\CorrectiveActionPermissions;
use WorkEddy\Modules\CorrectiveAction\Domain\Contracts\ICorrectiveActionRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Support\UuidSupport;

final class GetCorrectiveActionUseCase
{
    public function __construct(
        private readonly ICorrectiveActionRepository $repository,
        private readonly IPermissionService $permissions,
    ) {}

    /** @return array<string, mixed> */
    public function execute(string $actionUuid, UserContext $actor): array
    {
        $this->permissions->requirePrivilege($actor, CorrectiveActionPermissions::VIEW);
        $action = $this->repository->findActionByUuid(UuidSupport::requireValid($actionUuid, 'actionUuid'));
        if ($action === null || ($actor->organizationId !== null && $actor->organizationId !== $action->organizationId)) {
            throw new NotFoundException('Corrective action not found.');
        }

        return [
            'action' => $action->toView(),
            'evidence' => $this->repository->listEvidenceByActionUuid($action->uuid),
            'history' => $this->repository->listStatusHistoryByActionUuid($action->uuid),
        ];
    }
}
