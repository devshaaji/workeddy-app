<?php

declare(strict_types=1);

namespace WorkEddy\Modules\CorrectiveAction\Application;

use WorkEddy\Modules\CorrectiveAction\Application\Services\ControlActionWorkflowService;
use WorkEddy\Modules\CorrectiveAction\Authorization\CorrectiveActionPermissions;
use WorkEddy\Modules\CorrectiveAction\Domain\Contracts\ICorrectiveActionRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Events\EventPublisherInterface;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Support\UuidSupport;

final class UpdateCorrectiveActionStatusUseCase
{
    public function __construct(
        private readonly ICorrectiveActionRepository $repository,
        private readonly ControlActionWorkflowService $workflow,
        private readonly IPermissionService $permissions,
        private readonly IAuditService $audit,
        private readonly ?EventPublisherInterface $events = null,
    ) {}

    /** @return array<string, mixed> */
    public function execute(string $actionUuid, string $status, UserContext $actor, ?string $notes = null): array
    {
        $this->permissions->requirePrivilege($actor, CorrectiveActionPermissions::UPDATE_STATUS);
        $action = $this->repository->findActionByUuid(UuidSupport::requireValid($actionUuid, 'actionUuid'));
        if ($action === null || ($actor->organizationId !== null && $actor->organizationId !== $action->organizationId)) {
            throw new NotFoundException('Corrective action not found.');
        }
        $updated = $this->workflow->transition($action, $status);
        $this->repository->updateAction($updated);
        $this->repository->addStatusHistory(['actionUuid' => $updated->uuid, 'status' => $updated->status, 'actorId' => $actor->userId, 'notes' => $notes]);
        $this->audit->record('corrective_action.status_updated', 'corrective_action', $updated->uuid, beforeState: $action->toView(), afterState: $updated->toView(), actorId: (string) $actor->userId, actorType: 'user');
        $this->events?->publish('corrective_action.status_updated', $updated->toView(), 'corrective_action.status_updated:' . $updated->uuid . ':' . $updated->status);

        return $updated->toView();
    }
}
