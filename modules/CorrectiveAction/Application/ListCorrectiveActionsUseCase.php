<?php

declare(strict_types=1);

namespace WorkEddy\Modules\CorrectiveAction\Application;

use WorkEddy\Modules\CorrectiveAction\Authorization\CorrectiveActionPermissions;
use WorkEddy\Modules\CorrectiveAction\Domain\Contracts\ICorrectiveActionRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Platform\Session\UserContext;

final class ListCorrectiveActionsUseCase
{
    public function __construct(
        private readonly ICorrectiveActionRepository $repository,
        private readonly IPermissionService $permissions,
    ) {}

    /** @param array<string, mixed> $filters @return list<array<string, mixed>> */
    public function execute(UserContext $actor, array $filters = []): array
    {
        $this->permissions->requirePrivilege($actor, CorrectiveActionPermissions::VIEW);
        $organizationId = $actor->organizationId ?? 0;

        return array_map(
            static fn($action): array => $action->toView(),
            $this->repository->listActionsByOrganizationId($organizationId, $filters),
        );
    }
}
